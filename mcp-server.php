<?php
declare(strict_types=1);
ob_start(); // Capture any stray output
header('Content-Type: application/json');
require_once __DIR__ . '/includes/db_connect.php';

// === Logging ===
function log_message(string $message): void {
    file_put_contents(__DIR__ . '/mcp_log.txt', "[".date('Y-m-d H:i:s')."] $message\n", FILE_APPEND);
}
function log_tool(string $tool_name, array $args, mixed $result): void {
    $entry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'tool'      => $tool_name,
        'args'      => $args,
        'result'    => $result
    ];
    file_put_contents(__DIR__ . '/mcp-tool.log', json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n", FILE_APPEND);
}

// === JSON helpers ===
function json_response(mixed $data, int $status = 200): void {
    http_response_code($status);
    ob_clean(); // Clear any stray output
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
function rpc_error($id, int $code, string $message, mixed $data = null): void {
    $error = ['code' => $code, 'message' => $message];
    if ($data !== null) $error['data'] = $data;
    json_response(['jsonrpc' => '2.0', 'id' => $id, 'error' => $error]);
}

// === Arg validation ===
function validate_args(array $args, array $schema): array {
    $required = $schema['required'] ?? [];
    $missing  = [];
    foreach ($required as $key) {
        if (!isset($args[$key])) $missing[] = $key;
    }
    if ($missing) return ['error' => 'Missing required arguments: ' . implode(', ', $missing)];
    return ['success' => true];
}

// === Read input ===
log_message("=== New MCP request received ===");
$raw_input = file_get_contents('php://input');
log_message("Raw input: " . ($raw_input ?: '[EMPTY BODY]'));
if (empty(trim((string)$raw_input))) exit;

$input  = json_decode($raw_input, true);
if (!is_array($input)) {
    log_message("❌ Invalid JSON received");
    rpc_error(null, -32700, 'Parse error: Invalid JSON input');
}
$method = $input['method'] ?? null;
$id     = $input['id']     ?? null;
$params = $input['params'] ?? [];

// === DB check & API key ===
if (!isset($conn) || !$conn instanceof mysqli) {
    log_message("❌ Database connection not initialized");
    rpc_error($id, -32000, 'Database connection not available');
}
$query  = "SELECT api_key FROM users WHERE role = 'superadmin' LIMIT 1";
$result = $conn->query($query);
if ($result && $row = $result->fetch_assoc()) {
    $API_KEY = $row['api_key'];
    log_message("✅ API key loaded successfully");
} else {
    log_message("❌ No API key found in DB");
    rpc_error($id, -32001, 'API key not found');
}

// === Config ===
$API_BASE    = "https://pishonserv.com/api/v1";
$AUTH_SECRET = "f8b4ac4740e6fb980173a3f1300b0502299c9f0d50043f5e411daa972ba7f365";

// === Headers & auth ===
$headers = [];
foreach ($_SERVER as $name => $value) {
    if (substr($name,0,5) === 'HTTP_') {
        $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_',' ',substr($name,5)))))] = $value;
    }
}
$authHeader = $headers['Authorization'] ?? '';
log_message("Received Authorization header: " . ($authHeader ?: '[NONE]'));

if ($method !== 'initialize' && $authHeader !== "Bearer $AUTH_SECRET") {
    log_message("❌ Unauthorized request. Received header: " . $authHeader);
    rpc_error($id, -32600, 'Unauthorized');
}
if ($method !== 'initialize') log_message("✅ Authorization header verified");

// === API Caller ===
function callAPI(string $method, string $url, ?array $data = null, ?string $apiKey = null): mixed {
    log_message("🌐 Calling API [$method] $url with data: " . json_encode($data));
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $hdrs = ["Content-Type: application/json"];
    if ($apiKey) $hdrs[] = "Authorization: Bearer $apiKey";
    curl_setopt($ch, CURLOPT_HTTPHEADER, $hdrs);

    $upper = strtoupper($method);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $upper);
    if (in_array($upper, ['POST','PUT','PATCH']) && $data !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }

    $response = curl_exec($ch);
    $error    = curl_error($ch);
    curl_close($ch);

    if ($error) {
        log_message("❌ cURL Error: $error");
        return ['error' => $error];
    }
    log_message("✅ API Response: $response");
    $decoded = json_decode((string)$response, true);
    return $decoded === null ? $response : $decoded;
}

// === Tool schemas (for validation only) ===
$tools_schema = [
    'check_availability' => ['required' => ['property_id']],
    'get_property'       => ['required' => ['property_id']],
    'create_booking'     => ['required' => ['user_id','property_id','check_in_date','check_out_date','amount']],
];

// === MCP Tools descriptor (use inputSchema — NOT "parameters") ===
$tools = [
    [
        'name' => 'check_availability',
        'description' => 'Check if a property is available or expired',
        'inputSchema' => [
            'type' => 'object',
            'properties' => [
                'property_id' => ['type' => 'integer']
            ],
            'required' => ['property_id']
        ]
    ],
    [
        'name' => 'get_property',
        'description' => 'Fetch detailed property information by ID',
        'inputSchema' => [
            'type' => 'object',
            'properties' => [
                'property_id' => ['type' => 'integer']
            ],
            'required' => ['property_id']
        ]
    ],
    [
        'name' => 'create_booking',
        'description' => 'Create a booking for a property',
        'inputSchema' => [
            'type' => 'object',
            'properties' => [
                'user_id'        => ['type' => 'integer'],
                'property_id'    => ['type' => 'integer'],
                'check_in_date'  => ['type' => 'string','format' => 'date'],
                'check_out_date' => ['type' => 'string','format' => 'date'],
                'amount'         => ['type' => 'string'],
                'zoho_deal_id'   => ['type' => 'string']
            ],
            'required' => ['user_id','property_id','check_in_date','check_out_date','amount']
        ]
    ]
];

// === MCP Router ===
switch ($method) {
    // Handshake
    case 'initialize':
        log_message("🔗 Handling MCP initialize request");
        $clientProto = $params['protocolVersion'] ?? '2025-06-18';
        json_response([
            'jsonrpc' => '2.0',
            'id' => $id,
            'result' => [
                'protocolVersion' => $clientProto,                // echo back client's protocol
                'serverInfo' => ['name' => 'PishonServ MCP', 'version' => '1.0.0'],
                'capabilities' => (object)[],                     // keep as object
                'tools' => $tools,                                 // TOP-LEVEL tools
                // optional: 'resources'=>[], 'prompts'=>[], 'modelProviders'=>[]
            ]
        ]);
        break;

    // Client notifies it's ready — do NOT error; just 204 no-content
    case 'notifications/initialized':
        log_message("🔔 Received notifications/initialized");
        http_response_code(204);
        exit;

    // Canonical list method (and alias)
    case 'tools/list':
    case 'list_tools':
        log_message("🧰 Handling tools/list request");
        json_response(['jsonrpc' => '2.0', 'id' => $id, 'result' => ['tools' => $tools]]);
        break;

    // Canonical call method (and alias)
    case 'tools/call':
    case 'call_tool':
        $tool = $params['name'] ?? null;
        $args = $params['arguments'] ?? [];
        log_message("⚙️ tools/call invoked for [$tool] with args: " . json_encode($args));

        if (!$tool || !isset($tools_schema[$tool])) {
            rpc_error($id, -32601, "Unknown tool: $tool");
        }

        $validation = validate_args($args, $tools_schema[$tool]);
        if (isset($validation['error'])) {
            rpc_error($id, -32602, $validation['error']);
        }

        switch ($tool) {
            case 'check_availability':
                $result = callAPI('POST', "$API_BASE/availability", ['property_id' => $args['property_id']], $API_KEY);
                break;

            case 'get_property':
                $result = callAPI('GET', "$API_BASE/properties/{$args['property_id']}", null, $API_KEY);
                break;

            case 'create_booking':
                $payload = [
                    'user_id'        => $args['user_id'],
                    'property_id'    => $args['property_id'],
                    'check_in_date'  => $args['check_in_date'],
                    'check_out_date' => $args['check_out_date'],
                    'amount'         => $args['amount'],
                    'zoho_deal_id'   => $args['zoho_deal_id'] ?? null
                ];
                $result = callAPI('POST', "$API_BASE/bookings", $payload, $API_KEY);
                break;

            default:
                rpc_error($id, -32601, "Unknown tool: $tool");
        }

        log_tool($tool, $args, $result);
        json_response(['jsonrpc' => '2.0', 'id' => $id, 'result' => $result]);
        break;

    default:
        log_message("❌ Unknown MCP method: " . (string)$method);
        rpc_error($id, -32601, "Unknown MCP method: $method");
}

// No closing PHP tag to avoid stray output.

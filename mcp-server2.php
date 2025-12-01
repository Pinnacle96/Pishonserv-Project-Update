<?php
header('Content-Type: application/json');
require_once __DIR__ . '/includes/db_connect.php';

// === Logger ===
function log_message($message) {
    $log_file = __DIR__ . '/mcp_log.txt';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$timestamp] $message\n", FILE_APPEND);
}

// === JSON Response Helper ===
function json_response($data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

log_message("=== New MCP request received ===");
$raw_input = file_get_contents('php://input');
log_message("Raw input: " . ($raw_input ?: '[EMPTY BODY]'));

// ✅ Ignore blank requests
if (empty(trim($raw_input))) {
    log_message("⚠️ Ignoring empty HTTP request (no JSON-RPC content).");
    exit;
}

// === Decode JSON input ===
$input = json_decode($raw_input, true);
if (!$input) {
    log_message("❌ Invalid JSON received");
    json_response(['error' => 'Invalid JSON input'], 400);
    exit;
}

// === Ensure DB Connection Exists ===
if (!isset($conn) || !$conn instanceof mysqli) {
    log_message("❌ Database connection not initialized");
    json_response(['error' => 'Database connection not available'], 500);
    exit;
}

// === Load API Key from DB (MySQLi) ===
$query = "SELECT api_key FROM users WHERE role = 'superadmin' LIMIT 1";
$result = $conn->query($query);

if ($result && $row = $result->fetch_assoc()) {
    $API_KEY = $row['api_key'];
    log_message("✅ API key loaded successfully");
} else {
    log_message("❌ No API key found in DB");
    json_response(['error' => 'API key not found'], 403);
    exit;
}

// === Config ===
$API_BASE = "https://pishonserv.com/api/v1";
$AUTH_SECRET = "f8b4ac4740e6fb980173a3f1300b0502299c9f0d50043f5e411daa972ba7f365";

// === Verify Authorization Header ===
$headers = [];
foreach ($_SERVER as $name => $value) {
    if (substr($name, 0, 5) === 'HTTP_') {
        $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
    }
}
$authHeader = $headers['Authorization'] ?? '';

if ($authHeader !== "Bearer $AUTH_SECRET") {
    log_message("❌ Unauthorized request. Received header: " . $authHeader);
    json_response(['error' => 'Unauthorized'], 401);
    exit;
}
log_message("✅ Authorization header verified");

// === Utility: API Caller ===
function callAPI($method, $url, $data = null, $apiKey = null) {
    log_message("🌐 Calling API [$method] $url with data: " . json_encode($data));
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $apiKey",
        "Content-Type: application/json"
    ]);

    if (strtoupper($method) === 'POST' && $data) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }

    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        log_message("❌ cURL Error: $error");
        return ['error' => $error];
    } else {
        log_message("✅ API Response: $response");
        return json_decode($response, true);
    }
}

// === Handle JSON-RPC Methods ===
$method = $input['method'] ?? null;
$id = $input['id'] ?? null;
$params = $input['params'] ?? [];

switch ($method) {

    // 🔹 MCP Initialization
    case 'initialize':
        log_message("🔗 Handling MCP initialize request");
        json_response([
            'jsonrpc' => '2.0',
            'id' => $id,
            'result' => [
                'protocolVersion' => '2025-03-26',
                'serverInfo' => [
                    'name' => 'PishonServ MCP',
                    'version' => '1.0.0'
                ],
                'capabilities' => [
                    'tools' => true
                ]
            ]
        ]);
        log_message("✅ Sent MCP initialization response");
        break;

    // 🔹 List available tools
    case 'list_tools':
        log_message("🧰 Handling MCP list_tools request");
        $tools = [
            [
                'name' => 'check_availability',
                'description' => 'Check if a property is available or expired',
                'input_schema' => [
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
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'property_id' => ['type' => 'integer']
                    ],
                    'required' => ['property_id']
                ]
            ],
            [
                'name' => 'create_booking',
                'description' => 'Create a booking for a property (requires user ID and amount)',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'user_id' => ['type' => 'integer'],
                        'property_id' => ['type' => 'integer'],
                        'check_in_date' => ['type' => 'string', 'format' => 'date'],
                        'check_out_date' => ['type' => 'string', 'format' => 'date'],
                        'amount' => ['type' => 'string'],
                        'zoho_deal_id' => ['type' => 'string']
                    ],
                    'required' => ['user_id', 'property_id', 'check_in_date', 'check_out_date', 'amount']
                ]
            ]
        ];

        json_response([
            'jsonrpc' => '2.0',
            'id' => $id,
            'result' => ['tools' => $tools]
        ]);
        log_message("✅ Sent list of available tools");
        break;

    // 🔹 Call a specific tool
    case 'call_tool':
        $tool = $params['name'] ?? null;
        $args = $params['arguments'] ?? [];
        log_message("⚙️ call_tool invoked for [$tool] with args: " . json_encode($args));

        switch ($tool) {
            case 'check_availability':
                $result = callAPI('POST', "$API_BASE/availability", ['property_id' => $args['property_id']], $API_KEY);
                break;

            case 'get_property':
                $result = callAPI('GET', "$API_BASE/properties/{$args['property_id']}", null, $API_KEY);
                break;

            case 'create_booking':
                $payload = [
                    'user_id' => $args['user_id'],
                    'property_id' => $args['property_id'],
                    'check_in_date' => $args['check_in_date'],
                    'check_out_date' => $args['check_out_date'],
                    'amount' => $args['amount'],
                    'zoho_deal_id' => $args['zoho_deal_id'] ?? null
                ];
                $result = callAPI('POST', "$API_BASE/bookings", $payload, $API_KEY);
                break;

            default:
                $result = ['error' => "Unknown tool: $tool"];
        }

        json_response([
            'jsonrpc' => '2.0',
            'id' => $id,
            'result' => $result
        ]);
        log_message("✅ Tool execution result: " . json_encode($result));
        break;

    default:
        log_message("❌ Unknown MCP method: " . $method);
        json_response(['error' => 'Unknown MCP method'], 400);
        break;
}

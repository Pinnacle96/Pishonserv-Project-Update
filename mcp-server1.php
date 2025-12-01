<?php
header('Content-Type: application/json');
require_once __DIR__ . '/includes/db_connect.php';

// === Logger ===
function log_message($message) {
    $log_file = __DIR__ . '/mcp_log.txt';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$timestamp] $message\n", FILE_APPEND);
}

log_message("=== New MCP request received ===");
$raw_input = file_get_contents('php://input');
log_message("Raw input: " . ($raw_input ?: '[EMPTY BODY]'));

// ✅ Ignore blank requests completely (no response)
if (empty(trim($raw_input))) {
    log_message("⚠️ Ignoring empty HTTP request (no JSON-RPC content).");
    exit;
}

// Decode JSON input
$input = json_decode($raw_input, true);
if (!$input) {
    http_response_code(400);
    log_message("❌ Invalid JSON received");
    echo json_encode(['error' => 'Invalid JSON input']);
    exit;
}

// === Load API Key from DB ===
try {
    $stmt = $pdo->prepare("SELECT api_key FROM users WHERE role = 'superadmin' LIMIT 1");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $API_KEY = $result['api_key'] ?? null;

    if (!$API_KEY) {
        http_response_code(403);
        log_message("❌ No API key found in DB");
        echo json_encode(['error' => 'API key not found']);
        exit;
    } else {
        log_message("✅ API key loaded successfully");
    }
} catch (Exception $e) {
    http_response_code(500);
    log_message("❌ Database error: " . $e->getMessage());
    echo json_encode(['error' => 'Database error', 'details' => $e->getMessage()]);
    exit;
}

// === Config ===
$API_BASE = "https://pishonserv.com/api/v1";
$AUTH_SECRET = "f8b4ac4740e6fb980173a3f1300b0502299c9f0d50043f5e411daa972ba7f365";

// === Verify Authorization Header ===
$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? '';

if ($authHeader !== "Bearer $AUTH_SECRET") {
    http_response_code(401);
    log_message("❌ Unauthorized request. Received header: " . $authHeader);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}
log_message("✅ Authorization header verified");

// === Utility: API caller ===
function callAPI($method, $url, $data = null, $apiKey = null) {
    log_message("Calling API [$method] $url with data: " . json_encode($data));
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $apiKey",
        "Content-Type: application/json"
    ]);
    if ($method === 'POST' && $data) {
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
switch ($method) {
    case 'initialize':
        log_message("🔗 Handling MCP initialize request");
        echo json_encode([
            'jsonrpc' => '2.0',
            'id' => $input['id'] ?? null,
            'result' => [
                'capabilities' => ['tools' => true]
            ]
        ]);
        log_message("✅ Sent MCP initialization response");
        break;

    case 'list_tools':
        log_message("🧰 Handling MCP list_tools request");
        $tools = [
            [
                'name' => 'check_availability',
                'description' => 'Check property availability',
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
                'description' => 'Fetch property details by ID',
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
                'description' => 'Create a booking for a property',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'user_id' => ['type' => 'integer'],
                        'property_id' => ['type' => 'integer'],
                        'check_in_date' => ['type' => 'string'],
                        'check_out_date' => ['type' => 'string'],
                        'amount' => ['type' => 'string'],
                        'zoho_deal_id' => ['type' => 'string']
                    ],
                    'required' => ['user_id', 'property_id', 'check_in_date', 'check_out_date', 'amount']
                ]
            ]
        ];
        echo json_encode([
            'jsonrpc' => '2.0',
            'id' => $input['id'] ?? null,
            'result' => ['tools' => $tools]
        ]);
        log_message("✅ Sent list of available tools");
        break;

    case 'call_tool':
        $params = $input['params'] ?? [];
        $tool = $params['name'] ?? null;
        $args = $params['arguments'] ?? [];
        log_message("⚙️ Handling call_tool for: $tool with args: " . json_encode($args));

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
                $result = ['error' => 'Unknown tool'];
        }

        echo json_encode([
            'jsonrpc' => '2.0',
            'id' => $input['id'] ?? null,
            'result' => $result
        ]);
        log_message("✅ Tool execution result: " . json_encode($result));
        break;

    default:
        log_message("❌ Unknown MCP method: " . $method);
        echo json_encode(['error' => 'Unknown MCP method']);
        break;
}

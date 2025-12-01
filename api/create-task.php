<?php
// Set the content type to JSON
header('Content-Type: application/json');

// Include Composer's autoloader and database connection
require '../vendor/autoload.php';
include '../includes/db_connect.php';

// --- API Key Authentication ---
function authenticate($conn) {
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? '';

    if (empty($authHeader) || !preg_match('/Bearer\s(.+)/', $authHeader, $matches)) {
        return false;
    }

    $apiKey = $matches[1];

    // The API key must belong to a 'superadmin' to perform this action.
    $stmt = $conn->prepare("SELECT id FROM users WHERE api_key = ? AND role = 'superadmin'");
    $stmt->bind_param('s', $apiKey);
    $stmt->execute();
    $result = $stmt->get_result();

    return $result->num_rows > 0;
}

if (!authenticate($conn)) {
    http_response_code(401); // Unauthorized
    echo json_encode(['status' => 'error', 'message' => 'Invalid API Key.']);
    exit();
}

// --- Data Validation ---
$json = file_get_contents('php://input');
$data = json_decode($json, true);

$required_fields = ['title', 'assigned_to_user_id', 'due_date'];
foreach ($required_fields as $field) {
    if (empty($data[$field])) {
        http_response_code(400); // Bad Request
        echo json_encode(['status' => 'error', 'message' => "Missing required field: $field."]);
        exit();
    }
}

$title = $data['title'];
$assigned_to_user_id = $data['assigned_to_user_id'];
$due_date = $data['due_date'];
$description = $data['description'] ?? null;

// Validate assigned user ID exists
$stmt = $conn->prepare("SELECT id FROM users WHERE id = ?");
$stmt->bind_param('i', $assigned_to_user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(404); // Not Found
    echo json_encode(['status' => 'error', 'message' => 'Assigned user not found.']);
    exit();
}

// --- Task Creation ---
$status = 'pending';
$stmt = $conn->prepare("INSERT INTO tasks (title, description, assigned_to_user_id, due_date, status) VALUES (?, ?, ?, ?, ?)");
$stmt->bind_param('ssiss', $title, $description, $assigned_to_user_id, $due_date, $status);

if ($stmt->execute()) {
    $taskId = $stmt->insert_id;
    http_response_code(201); // Created
    echo json_encode(['status' => 'success', 'message' => 'Task created successfully.', 'task_id' => $taskId]);
} else {
    http_response_code(500); // Internal Server Error
    echo json_encode(['status' => 'error', 'message' => 'Failed to create task.']);
}

$conn->close();
?>

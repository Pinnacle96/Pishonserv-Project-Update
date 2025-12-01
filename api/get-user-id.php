<?php
// Set the content type to JSON
header('Content-Type: application/json');

// Include Composer's autoloader and database connection
require '../vendor/autoload.php';
include '../includes/db_connect.php';

// --- API Key Authentication ---
// This function is repeated here to ensure the endpoint is self-contained and secure.
function authenticate($conn) {
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? '';

    if (empty($authHeader) || !preg_match('/Bearer\s(.+)/', $authHeader, $matches)) {
        return false;
    }

    $apiKey = $matches[1];

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
// We expect a 'email' query parameter.
$email = $_GET['email'] ?? null;

if (empty($email)) {
    http_response_code(400); // Bad Request
    echo json_encode(['status' => 'error', 'message' => 'Missing required parameter: email.']);
    exit();
}

// --- User Lookup Logic ---
// Use a prepared statement to safely query for the user's ID based on their email.
$stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
$stmt->bind_param('s', $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    // User found, return their ID.
    $user = $result->fetch_assoc();
    http_response_code(200); // OK
    echo json_encode(['status' => 'success', 'message' => 'User found.', 'user_id' => $user['id']]);
} else {
    // User not found.
    http_response_code(404); // Not Found
    echo json_encode(['status' => 'error', 'message' => 'User not found.']);
}

$conn->close();
?>

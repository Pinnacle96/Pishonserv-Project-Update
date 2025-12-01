<?php
// Set the content type to JSON
header('Content-Type: application/json');

// Include Composer's autoloader and database connection
require '../vendor/autoload.php';
include '../includes/db_connect.php';

// --- API Key Authentication ---
// This function is repeated here for security and to ensure the endpoint is self-contained.
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

// --- Logic to Find a Random Admin ---
// We use a prepared statement to get all users with the 'admin' role.
$stmt = $conn->prepare("SELECT id FROM users WHERE role = 'admin'");
$stmt->execute();
$result = $stmt->get_result();

$adminUsers = [];
while ($row = $result->fetch_assoc()) {
    $adminUsers[] = $row['id'];
}

// Check if any admin users were found.
if (empty($adminUsers)) {
    http_response_code(404); // Not Found
    echo json_encode(['status' => 'error', 'message' => 'No admin users found to assign a task to.']);
    exit();
}

// Randomly pick one user from the list.
$randomAdminId = $adminUsers[array_rand($adminUsers)];

// Return the randomly selected user ID.
http_response_code(200); // OK
echo json_encode(['status' => 'success', 'message' => 'Random admin user selected.', 'user_id' => $randomAdminId]);

$conn->close();
?>

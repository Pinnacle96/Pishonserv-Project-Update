<?php
session_start();
header('Content-Type: application/json');

// This security check is crucial. It must match your super-admin session logic.
// The user must be logged in and have the 'superadmin' role.
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'superadmin') {
    http_response_code(403); // Forbidden
    echo json_encode(['status' => 'error', 'message' => 'Access denied.']);
    exit();
}

include '../includes/db_connect.php'; // Path to your database connection file

try {
    // 1. Generate a new secure, unique API key
    $apiKey = bin2hex(random_bytes(32));

    // 2. Update the database record for the logged-in super-admin
    $userId = $_SESSION['user_id'];
    $stmt = $conn->prepare("UPDATE users SET api_key = ? WHERE id = ?");
    $stmt->bind_param('si', $apiKey, $userId);

    if (!$stmt->execute()) {
        throw new Exception('Database update failed: ' . $stmt->error);
    }
    
    $stmt->close();
    $conn->close();

    // 3. Return a JSON success response to the frontend
    echo json_encode([
        'status' => 'success',
        'message' => 'New API key has been generated and saved.',
        'apiKey' => $apiKey
    ]);

} catch (Exception $e) {
    http_response_code(500); // Internal Server Error
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>
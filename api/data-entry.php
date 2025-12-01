<?php
// Set the content type to JSON to ensure a proper response format
header('Content-Type: application/json');

// Include your database connection file
include '../includes/db_connect.php';

// --- API Key Authentication ---
function authenticate($conn) {
    // Get the Authorization header
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? '';

    // Check if the header exists and starts with 'Bearer '
    if (empty($authHeader) || !preg_match('/Bearer\s(.+)/', $authHeader, $matches)) {
        return false;
    }

    $apiKey = $matches[1];

    // Check if the API key exists in the database for a superadmin
    $stmt = $conn->prepare("SELECT id FROM users WHERE api_key = ? AND role = 'superadmin'");
    $stmt->bind_param('s', $apiKey);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Return true if a matching user is found, false otherwise
    return $result->num_rows > 0;
}

// Perform authentication
if (!authenticate($conn)) {
    http_response_code(401); // Unauthorized
    echo json_encode(['status' => 'error', 'message' => 'Invalid API Key.']);
    exit();
}

// --- Data Handling ---
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Check if JSON data was provided and is valid
if ($data === null) {
    http_response_code(400); // Bad Request
    echo json_encode(['status' => 'error', 'message' => 'Invalid JSON data.']);
    exit();
}

// --- Data Validation ---
// Define the required fields
$required_fields = ['name', 'email', 'message'];
foreach ($required_fields as $field) {
    if (empty($data[$field])) {
        http_response_code(400); // Bad Request
        echo json_encode(['status' => 'error', 'message' => "Missing required field: $field."]);
        exit();
    }
}

// Extract data from the request, using empty strings for optional fields
$name = $data['name'];
$email = $data['email'];
$phone = $data['phone'] ?? null; // Use null for optional fields
$property_id = $data['property_id'] ?? null;
$message = $data['message'];

// --- Database Insertion ---
$stmt = $conn->prepare("INSERT INTO property_inquiries (name, email, phone, property_id, message) VALUES (?, ?, ?, ?, ?)");
$stmt->bind_param("sssis", $name, $email, $phone, $property_id, $message);

if ($stmt->execute()) {
    http_response_code(201); // Created
    echo json_encode(['status' => 'success', 'message' => 'Data successfully inserted.']);
} else {
    http_response_code(500); // Internal Server Error
    echo json_encode(['status' => 'error', 'message' => 'Failed to insert data into the database.']);
}

$stmt->close();
$conn->close();
?>

<?php
// Set the content type to JSON to ensure a proper response format
header('Content-Type: application/json');

// Include Composer's autoloader for PHPMailer
require '../vendor/autoload.php';

// Include your database connection file for authentication
include '../includes/db_connect.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// --- API Key Authentication ---
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

// --- Data Handling ---
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if ($data === null) {
    http_response_code(400); // Bad Request
    echo json_encode(['status' => 'error', 'message' => 'Invalid JSON data.']);
    exit();
}

// --- Data Validation ---
// 'from_name' is optional, but included in the payload
$required_fields = ['to', 'subject', 'body'];
foreach ($required_fields as $field) {
    if (empty($data[$field])) {
        http_response_code(400); // Bad Request
        echo json_encode(['status' => 'error', 'message' => "Missing required field: $field."]);
        exit();
    }
}

// --- Email Sending Logic with PHPMailer (Adapted from your provided code) ---
try {
    $mail = new PHPMailer(true);

    // Server settings
    $mail->isSMTP();
    $mail->Host       = SMTP_HOST;
    $mail->SMTPAuth   = true;
    $mail->Username   = SMTP_USER;
    $mail->Password   = SMTP_PASS;
    $mail->SMTPSecure = (SMTP_SECURE === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS);
    $mail->Port       = SMTP_PORT;

    // Recipients
    $mail->setFrom(FROM_EMAIL, $data['from_name'] ?? FROM_NAME);
    $mail->addAddress($data['to']);

    // Content
    $mail->isHTML(true);
    $mail->Subject = $data['subject'];
    $mail->Body    = $data['body'];

    // Send the email
    $mail->send();
    
    // Log the successful send for debugging
    error_log("✅ Email sent successfully to " . $data['to']);
    
    http_response_code(200); // OK
    echo json_encode(['status' => 'success', 'message' => 'Email sent successfully.']);

} catch (Exception $e) {
    // Log the error for debugging
    error_log("❌ Email sending error to " . $data['to'] . ": " . $mail->ErrorInfo);
    
    http_response_code(500); // Internal Server Error
    echo json_encode(['status' => 'error', 'message' => 'Failed to send email. Mailer Error: ' . $mail->ErrorInfo]);
}

$conn->close();
?>
require_once '../includes/config.php';

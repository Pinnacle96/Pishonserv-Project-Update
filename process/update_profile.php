<?php
// process/update_profile.php
session_start();
require_once '../includes/db_connect.php';

$logFile = __DIR__ . '/../logs/profile_updates.log';
function log_profile($msg){
    global $logFile;
    file_put_contents($logFile, '['.date('Y-m-d H:i:s')."] $msg\n", FILE_APPEND);
}

if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = "You must log in first.";
    header("Location: ../auth/login.php");
    exit();
}

// CSRF
if ($_SERVER['REQUEST_METHOD'] !== 'POST'
    || empty($_POST['csrf_token'])
    || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
    $_SESSION['error'] = "Invalid request.";
    header("Location: ../dashboard/buyer_profile.php");
    exit();
}

$uid = (int) $_SESSION['user_id'];
$action = $_POST['action'] ?? 'update_profile';

try {
    if ($action === 'update_profile') {
        $name  = trim($_POST['name']  ?? '');
        $lname = trim($_POST['lname'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');

        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Please provide a valid name and email.");
        }

        // Ensure email unique (excluding self)
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1");
        $stmt->bind_param("si", $email, $uid);
        $stmt->execute();
        $exists = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($exists) {
            throw new Exception("Email is already taken by another user.");
        }

        $stmt = $conn->prepare("UPDATE users SET name=?, lname=?, email=?, phone=? WHERE id=?");
        $stmt->bind_param("ssssi", $name, $lname, $email, $phone, $uid);
        $stmt->execute();
        $stmt->close();

        // Refresh session display fields
        $_SESSION['name']  = $name;
        $_SESSION['email'] = $email;

        $_SESSION['success'] = "Profile updated successfully.";
        log_profile("User {$uid} updated profile.");
        header("Location: ../dashboard/buyer_profile.php");
        exit();
    }

    if ($action === 'upload_avatar') {
    if (empty($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception("Please select a valid image file.");
    }

    $file = $_FILES['avatar'];

    // 2MB limit
    if ($file['size'] > 2 * 1024 * 1024) {
        throw new Exception("Image is too large (max 2MB).");
    }

    // Allow same types as registration
    $allowedExt = ['jpg','jpeg','png'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt, true)) {
        throw new Exception("Only JPG/JPEG/PNG images are allowed.");
    }

    // Build filesystem path using BASE_PATH like registration
    if (!defined('BASE_PATH')) {
        // fallback if BASE_PATH isn’t defined in your includes
        define('BASE_PATH', dirname(__DIR__));
    }
    $uploadDir = BASE_PATH . '/public/uploads/';
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            throw new Exception("Failed to create uploads directory.");
        }
    }

    $filename = uniqid('profile_', true) . '.' . $ext;
    $fsPath   = $uploadDir . $filename;            // filesystem path
    $webPath  = '/public/uploads/' . $filename;    // web path (what we store)

    if (!move_uploaded_file($file['tmp_name'], $fsPath)) {
        throw new Exception("Failed to save image.");
    }

    // (Optional) remove old file if it lived in /public/uploads/
    $old = null;
    $stmt = $conn->prepare("SELECT profile_image FROM users WHERE id=?");
    $stmt->bind_param("i", $uid);
    $stmt->execute();
    $old = $stmt->get_result()->fetch_assoc()['profile_image'] ?? null;
    $stmt->close();

    if ($old && strpos($old, '/public/uploads/') === 0) {
        $oldFs = BASE_PATH . $old; // because $old starts with /public/...
        if (is_file($oldFs)) @unlink($oldFs);
    }

    // Save absolute web path so views can use it directly
    $stmt = $conn->prepare("UPDATE users SET profile_image=? WHERE id=?");
    $stmt->bind_param("si", $webPath, $uid);
    $stmt->execute();
    $stmt->close();

    $_SESSION['profile_image'] = $webPath; // keep session in sync
    $_SESSION['success'] = "Profile photo updated.";
    log_profile("User {$uid} updated avatar to {$webPath}.");

    header("Location: ../dashboard/buyer_profile.php");
    exit();
}

    if ($action === 'change_password') {
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if (strlen($new) < 6) {
            throw new Exception("New password must be at least 6 characters.");
        }
        if ($new !== $confirm) {
            throw new Exception("New password and confirmation do not match.");
        }

        // Fetch hash
        $stmt = $conn->prepare("SELECT password FROM users WHERE id = ? LIMIT 1");
        $stmt->bind_param("i", $uid);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row || !password_verify($current, $row['password'])) {
            throw new Exception("Current password is incorrect.");
        }

        $newHash = password_hash($new, PASSWORD_BCRYPT);
        $stmt = $conn->prepare("UPDATE users SET password=? WHERE id=?");
        $stmt->bind_param("si", $newHash, $uid);
        $stmt->execute();
        $stmt->close();

        $_SESSION['success'] = "Password updated successfully.";
        log_profile("User {$uid} changed password.");
        header("Location: ../dashboard/buyer_profile.php");
        exit();
    }

    // Fallback
    $_SESSION['error'] = "Unknown action.";
    header("Location: ../dashboard/buyer_profile.php");
    exit();

} catch (Exception $e) {
    $_SESSION['error'] = $e->getMessage();
    log_profile("User {$uid} profile error: ".$e->getMessage());
    header("Location: ../dashboard/buyer_profile.php");
    exit();
}

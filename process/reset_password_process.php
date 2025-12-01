<?php
session_start();
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');

require_once '../includes/db_connect.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request.');
    }

    // CSRF (validate if token exists; remain backward-compatible if not present)
    if (isset($_POST['csrf_token'])) {
        if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
            throw new Exception('Invalid form token. Please try again.');
        }
    }

    // Get password fields (support both your old "new_password" and the new pair)
    $password_raw = $_POST['password'] ?? $_POST['new_password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? null;

    if (strlen($password_raw) < 8) {
        throw new Exception('Password must be at least 8 characters long.');
    }
    if ($password_confirm !== null && $password_confirm !== $password_raw) {
        throw new Exception('Passwords do not match.');
    }

    $password_hash = password_hash($password_raw, PASSWORD_BCRYPT);
    if ($password_hash === false) {
        throw new Exception('Could not hash password.');
    }

    // Prefer the new, safer reset flow (user id from OTP-verified session)
    $usingNewFlow = !empty($_SESSION['password_reset_mode']) && !empty($_SESSION['user_id_reset']);
    $affected = 0;

    if ($usingNewFlow) {
        $userId = (int) $_SESSION['user_id_reset'];

        $stmt = $conn->prepare("UPDATE users SET password = ?, otp_hash = NULL, otp_expires_at = NULL WHERE id = ?");
        if (!$stmt) throw new Exception('DB error (prepare).');
        $stmt->bind_param('si', $password_hash, $userId);
        if (!$stmt->execute()) throw new Exception('Could not update password (execute).');
        $affected = $stmt->affected_rows;
        $stmt->close();

        // Clear reset flags, but DO NOT log the user in automatically
        unset($_SESSION['password_reset_mode'], $_SESSION['user_id_reset'], $_SESSION['user_id_to_verify']);
        // Optional: also clear the email we showed earlier (if set)
        unset($_SESSION['user_email_to_verify']);
    } else {
        // Backward compatibility: legacy flow using $_SESSION['email']
        if (empty($_SESSION['email'])) {
            throw new Exception('Session expired. Try resetting again.');
        }
        $email = $_SESSION['email'];

        // Use correct columns (otp_hash/otp_expires_at), not "otp"
        $stmt = $conn->prepare("UPDATE users SET password = ?, otp_hash = NULL, otp_expires_at = NULL WHERE email = ?");
        if (!$stmt) throw new Exception('DB error (prepare legacy).');
        $stmt->bind_param('ss', $password_hash, $email);
        if (!$stmt->execute()) throw new Exception('Could not update password (legacy execute).');
        $affected = $stmt->affected_rows;
        $stmt->close();

        unset($_SESSION['email']); // legacy cleanup
    }

    if ($affected <= 0) {
        // Not strictly an error: email or id might not match, or same password hash set
        // But it's safer to tell the user to try again.
        throw new Exception('Nothing changed. Please try again.');
    }

    $_SESSION['success'] = 'Password reset successfully. You can now log in.';
    // regenerate CSRF for safety after sensitive action
    unset($_SESSION['csrf_token']);
    header('Location: ../auth/login.php');
    exit();

} catch (Exception $e) {
    error_log('Reset Password Error: ' . $e->getMessage());
    $_SESSION['error'] = $e->getMessage();

    // Keep the user in the correct place depending on the flow
    if (!empty($_SESSION['password_reset_mode']) && !empty($_SESSION['user_id_reset'])) {
        header('Location: ../auth/reset_password.php');
    } else {
        header('Location: ../auth/reset_password.php'); // legacy page path
    }
    exit();
}

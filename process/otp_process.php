<?php
/* ---------------------------------------------
    otp_process.php – verify a user’s OTP code
    supports: signup/login verification AND password reset
    --------------------------------------------- */
session_start();
include '../includes/db_connect.php';

/* 1) Basic guards */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../auth/verify-otp.php');
    exit;
}
if (!isset($_SESSION['user_id_to_verify'])) {
    $_SESSION['error'] = 'Session expired. Please log in or register again.';
    header('Location: ../auth/login.php');
    exit;
}

/* 1a) CSRF */
if (!isset($_POST['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
    $_SESSION['error'] = 'Invalid form token. Please try again.';
    header('Location: ../auth/verify-otp.php');
    exit;
}

/* 2) Input */
$otp     = trim($_POST['otp'] ?? '');
$user_id = (int)$_SESSION['user_id_to_verify'];
$postedFlow = $_POST['flow'] ?? '';
$flow = (!empty($_SESSION['password_reset_mode']) || $postedFlow === 'reset')
    ? 'reset'
    : (($postedFlow === 'withdraw' || !empty($_SESSION['withdraw_otp_mode'])) ? 'withdraw' : 'verify');

if (!preg_match('/^[0-9]{6}$/', $otp)) {
    $_SESSION['error'] = 'OTP must be a 6-digit number.';
    header('Location: ../auth/verify-otp.php' . ($flow === 'reset' ? '?mode=reset' : ''));
    exit;
}

/* 3) Fetch user’s OTP hash + expiry */
$stmt = $conn->prepare(
    'SELECT id, name, email, role, profile_image, otp_hash, otp_expires_at
     FROM users WHERE id = ? LIMIT 1'
);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user || empty($user['otp_hash']) || empty($user['otp_expires_at'])) {
    $_SESSION['error'] = 'Invalid or expired OTP.';
    header('Location: ../auth/verify-otp.php' . ($flow === 'reset' ? '?mode=reset' : ''));
    exit;
}

/* 4) Verify OTP + expiry */
$valid      = password_verify($otp, $user['otp_hash']);
$notExpired = (time() < strtotime($user['otp_expires_at']));
if (!$valid || !$notExpired) {
    $_SESSION['error'] = $notExpired ? 'Invalid OTP. Please try again.' : 'OTP expired. Request a new one.';
    header('Location: ../auth/verify-otp.php' . ($flow === 'reset' ? '?mode=reset' : ''));
    exit;
}

/* 5) Clear OTP fields (both flows) */
$clr = $conn->prepare('UPDATE users SET otp_hash = NULL, otp_expires_at = NULL WHERE id = ?');
$clr->bind_param('i', $user['id']);
$clr->execute();
$clr->close();

/* 6) Branch by flow */
if ($flow === 'reset') {
    // ✅ ALSO mark email as verified during reset (as requested)
    $ver = $conn->prepare('UPDATE users SET email_verified = 1 WHERE id = ?');
    $ver->bind_param('i', $user['id']);
    $ver->execute();
    $ver->close();

    // Prepare reset session and redirect to reset page
    $_SESSION['password_reset_mode'] = true;
    $_SESSION['user_id_reset'] = $user['id'];

    // housekeeping
    unset($_SESSION['user_id_to_verify'], $_SESSION['user_email_to_verify']);

    $_SESSION['success'] = 'OTP verified. Please set your new password.';
    header('Location: ../auth/reset_password.php');
    exit;
}

if ($flow === 'withdraw') {
    unset($_SESSION['password_reset_mode']);
    $_SESSION['withdraw_otp_verified'] = true;
    unset($_SESSION['user_id_to_verify'], $_SESSION['user_email_to_verify']);
    $_SESSION['success'] = 'OTP verified.';
    header('Location: ../dashboard/agent_withdraw.php?resume=1');
    exit;
}

/* 7) Signup/Login verification flow → mark verified & log in */
$ver = $conn->prepare('UPDATE users SET email_verified = 1 WHERE id = ?');
$ver->bind_param('i', $user['id']);
$ver->execute();
$ver->close();

$_SESSION['user_id']       = $user['id'];
$_SESSION['name']          = $user['name'];
$_SESSION['role']          = $user['role'];
$_SESSION['profile_image'] = $user['profile_image'];

unset($_SESSION['user_id_to_verify'], $_SESSION['user_email_to_verify'], $_SESSION['password_reset_mode']);

$_SESSION['success'] = 'Your email has been verified successfully!';

/* 8) Redirect (include host) */
$redirect = $_SESSION['redirect_after_login'] ?? match ($user['role']) {
    'buyer' => '../index.php',
    'agent', 'owner', 'hotel_owner', 'developer', 'host' => '../dashboard/agent_dashboard.php',
    'admin' => '../dashboard/admin_dashboard.php',
    'superadmin' => '../dashboard/superadmin_dashboard.php',
    default => '../auth/login.php',
};

unset($_SESSION['redirect_after_login']);
header("Location: $redirect");
exit;

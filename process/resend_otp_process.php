<?php
/* ------------------------------------------------
    resend_otp_process.php – Resend OTP to user
    ------------------------------------------------ */

session_start();
include '../includes/db_connect.php';
require '../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Check for the user ID in the session
if (!isset($_SESSION['user_id_to_verify'])) {
    $_SESSION['error'] = 'Session expired. Please log in or register again.';
    header('Location: ../auth/login.php');
    exit;
}

$mode = $_GET['mode'] ?? '';
$isWithdraw = ($mode === 'withdraw') || !empty($_SESSION['withdraw_otp_mode']);

$user_id = $_SESSION['user_id_to_verify'];

// 1. Fetch user details from the database
$stmt = $conn->prepare('SELECT id, name, email FROM users WHERE id = ?');
$stmt->bind_param('i', $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    $_SESSION['error'] = 'Could not find a user to resend OTP to.';
    header('Location: ../auth/login.php');
    exit;
}

// 2. Generate new OTP and hash it
$otpPlain     = (string)random_int(100000, 999999);
$otpHash      = password_hash($otpPlain, PASSWORD_DEFAULT);
$otpExpiresAt = date('Y-m-d H:i:s', strtotime('+10 minutes'));

// 3. Update the database with the new OTP and expiry time
$update_stmt = $isWithdraw
    ? $conn->prepare('UPDATE users SET otp_hash = ?, otp_expires_at = ? WHERE id = ?')
    : $conn->prepare('UPDATE users SET otp_hash = ?, otp_expires_at = ?, email_verified = 0 WHERE id = ?');
if ($isWithdraw) {
    $update_stmt->bind_param('ssi', $otpHash, $otpExpiresAt, $user['id']);
} else {
    $update_stmt->bind_param('ssi', $otpHash, $otpExpiresAt, $user['id']);
}
$update_stmt->execute();
$update_stmt->close();

// 4. Send the new OTP via email
try {
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = SMTP_HOST;
    $mail->SMTPAuth   = true;
    $mail->Username   = SMTP_USER;
    $mail->Password   = SMTP_PASS;
    $mail->SMTPSecure = (SMTP_SECURE === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS);
    $mail->Port       = SMTP_PORT;
    $mail->SMTPOptions = [
      'ssl' => [
        'verify_peer' => false,
        'verify_peer_name' => false,
        'allow_self_signed' => true
      ]
    ];
    $mail->SMTPDebug = 2;
    $mail->Debugoutput = function($str, $level) {
        $time = date('Y-m-d H:i:s');
        file_put_contents(__DIR__ . '/../logs/withdrawals.log', "[$time] [SMTP][resend][$level] $str\n", FILE_APPEND);
    };
    $mail->setFrom(FROM_EMAIL, FROM_NAME);
    $mail->addAddress($user['email'], $user['name']);
    $mail->isHTML(true);
    $mail->Subject = 'PISHONSERV – Your New OTP Code';
    $mail->Body    = "
        <div style='font-family:Arial;max-width:600px;margin:auto;padding:20px;background:#f9f9f9;'>
            <div style='text-align:center;'>
                <img src='https://pishonserv.com/public/images/logo.png' alt='PISHONSERV' style='width:140px;margin-bottom:20px'>
            </div>
            <div style='background:#fff;padding:30px;border-radius:8px;box-shadow:0 2px 4px rgba(0,0,0,0.1);'>
                <h2 style='color:#092468;'>Hello, {$user['name']}!</h2>
                <p>A new OTP has been requested for your account. Please use the code below (valid for 10 minutes):</p>
                <p style='text-align:center;margin:20px 0;'>
                    <span style='display:inline-block;background:#CC9933;color:#fff;font-size:28px;font-weight:bold;padding:10px 20px;border-radius:5px;'>{$otpPlain}</span>
                </p>
                <p>If you did not request this, please ignore this email.</p>
            </div>
        </div>";
    $mail->AltBody = "Your new PISHONSERV OTP is: {$otpPlain}\nValid for 10 minutes.";
    $mail->send();
    file_put_contents(__DIR__ . '/../logs/withdrawals.log', "[".date('Y-m-d H:i:s')."] Resent OTP to {$user['email']} (withdraw=".($isWithdraw?'1':'0').")\n", FILE_APPEND);

    $_SESSION['user_email_to_verify'] = $user['email'];
    if ($isWithdraw) { $_SESSION['withdraw_otp_mode'] = true; }
    $_SESSION['success'] = "A new OTP has been sent to your email.";
    header('Location: ../auth/verify-otp.php' . ($isWithdraw ? '?mode=withdraw' : ''));
    exit;

} catch (Exception $e) {
    error_log("OTP resend email error to {$user['email']}: " . $mail->ErrorInfo);
    file_put_contents(__DIR__ . '/../logs/withdrawals.log', "[".date('Y-m-d H:i:s')."] Resend OTP error to {$user['email']}: " . ($mail->ErrorInfo ?: $e->getMessage()) . "\n", FILE_APPEND);
    $_SESSION['error'] = "Could not resend OTP. Please contact support.";
    header('Location: ../auth/verify-otp.php' . ($isWithdraw ? '?mode=withdraw' : ''));
    exit;
}
require_once '../includes/config.php';

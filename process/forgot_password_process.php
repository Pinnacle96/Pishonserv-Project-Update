<?php
/**
 * Forgot Password - Send OTP (reset flow)
 */
session_start();
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');

require_once '../includes/db_connect.php';
require_once '../vendor/autoload.php';
require_once '../includes/config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method.');
    }

    // Basic input
    $email = trim($_POST['email'] ?? '');
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Please enter a valid email address.');
    }

    // Look up user
    $stmt = $conn->prepare("SELECT id, name, lname, email_verified FROM users WHERE email = ? LIMIT 1");
    if (!$stmt) throw new Exception('DB error (prepare users lookup).');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $res = $stmt->get_result();
    if (!$res || $res->num_rows === 0) {
        // do not reveal existence — but your old UX does; keep same behavior:
        $_SESSION['error'] = "Email not found.";
        header("Location: ../auth/forgot_password.php");
        exit();
    }
    $user = $res->fetch_assoc();
    $stmt->close();

    $userId = (int)$user['id'];
    $firstName = $user['name'] ?: '';
    $lastName  = $user['lname'] ?: '';
    $displayName = trim($firstName . ' ' . $lastName);

    // Generate OTP (6 digits), hash & expiry (10 minutes)
    $otpPlain      = (string)random_int(100000, 999999);
    $otpHash       = password_hash($otpPlain, PASSWORD_DEFAULT);
    $otpExpiresAt  = date('Y-m-d H:i:s', strtotime('+10 minutes'));

    // Save hashed OTP and expiry — DO NOT overwrite email_verified for password reset
    $upd = $conn->prepare("UPDATE users SET otp_hash = ?, otp_expires_at = ? WHERE id = ?");
    if (!$upd) throw new Exception('DB error (prepare OTP update).');
    $upd->bind_param('ssi', $otpHash, $otpExpiresAt, $userId);
    if (!$upd->execute()) {
        throw new Exception('Failed to set OTP. Please try again.');
    }
    $upd->close();

    // Store some state for the next step
    $_SESSION['user_id_to_verify']   = $userId;
    $_SESSION['user_email_to_verify'] = $email;
    $_SESSION['password_reset_mode'] = true; // so verify-otp can branch to reset flow
    // Optionally keep plain OTP in session if your verify page wants to show/resend without DB:
    // $_SESSION['otp_plain'] = $otpPlain;

    // Send OTP email via Zoho SMTP
    try {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = (SMTP_SECURE === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS);
        $mail->Port       = SMTP_PORT;

        $mail->setFrom(FROM_EMAIL, FROM_NAME);
        $mail->addAddress($email, $displayName ?: $email);


        $mail->isHTML(true);
        $mail->Subject = 'Reset Your Password - OTP Code';

        $mail->Body = '
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Password Reset OTP</title>
  <style>
    body { font-family: Arial, sans-serif; color:#333; margin:0; padding:0; }
    .container { max-width:600px; margin:20px auto; background:#f9f9f9; border:1px solid #ddd; border-radius:8px; padding:24px; }
    .brand { text-align:center; margin-bottom:14px; color:#092468; font-weight:bold; }
    .otp { display:inline-block; background:#CC9933; color:#fff; padding:12px 20px; font-size:24px; font-weight:bold; border-radius:6px; letter-spacing:2px; }
    .muted { color:#777; font-size:14px; }
    a { color:#092468; }
  </style>
</head>
<body>
  <div class="container">
    <div class="brand">PISHONSERV</div>
    <p>Hi '.htmlspecialchars($displayName ?: 'there', ENT_QUOTES, 'UTF-8').',</p>
    <p>We received a request to reset the password for your PISHONSERV account. Use the OTP below (valid for <strong>10 minutes</strong>):</p>
    <p style="text-align:center; margin:20px 0;">
      <span class="otp">'.htmlspecialchars($otpPlain, ENT_QUOTES, 'UTF-8').'</span>
    </p>
    <p>Enter this code on the password reset page to continue. If you did not request a password reset, you can safely ignore this email.</p>
    <p class="muted">Questions? Email <a href="mailto:support@pishonserv.com">support@pishonserv.com</a>.</p>
    <p class="muted">Best regards,<br/>The PISHONSERV Team</p>
  </div>
</body>
</html>';

        $mail->AltBody =
"Reset Your Password

We received a request to reset the password for your PISHONSERV account.
Your OTP Code: {$otpPlain}

This code is valid for 10 minutes. Enter it on the password reset page to continue.
If you did not request a password reset, please ignore this email.

Support: support@pishonserv.com
— PISHONSERV Team";

        // Optional: debug logs to your php_errors.log
        // $mail->SMTPDebug = 2;
        // $mail->Debugoutput = function($str, $level) {
        //     error_log("[SMTP RESET DEBUG][$level] $str");
        // };

        $mail->send();

        $_SESSION['success'] = "OTP sent successfully! Check your email.";
        header("Location: ../auth/verify-otp.php?mode=reset");
        exit();
    } catch (Exception $e) {
        error_log("ForgotPassword Mail Error: ".$e->getMessage());
        $_SESSION['error'] = "We couldn’t send the OTP email. Please try again.";
        header("Location: ../auth/forgot_password.php");
        exit();
    }

} catch (Exception $e) {
    error_log("ForgotPassword Error: ".$e->getMessage());
    $_SESSION['error'] = $e->getMessage();
    header("Location: ../auth/forgot_password.php");
    exit();
}

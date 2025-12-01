<?php
/* =========================================================
    PISHONSERV – Registration Processing (secure edition)
    ========================================================= */
session_start();

/* ---------- 1.  DEBUG & LOGGING  ---------- */
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '../logs/php_errors.log');

define('BASE_PATH', realpath(__DIR__ . '/../'));

/* ---------- 2.  DEPENDENCIES  ---------- */
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Dompdf\Dompdf;
use Dompdf\Options;

/* ---------- 3.  CONSTANTS  ---------- */
const RECAPTCHA_SECRET = '6LesimcrAAAAAAZ_3GhQ145Ypyg372CWb6uqtuJF'; // <— replace!

try {
    /* ---------- 4.  INCLUDES  ---------- */
    include '../includes/db_connect.php';
    include '../includes/zoho_functions.php';
    require '../vendor/autoload.php';

    /* ---------- 5.  BASIC REQUEST GUARDS  ---------- */
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method.');
    }

    /* ---- 5a. CSRF token check ---- */
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
        throw new Exception('Invalid CSRF token.');
    }

    /* ---- 5b. reCAPTCHA check ---- */
    $recaptchaResp = $_POST['g-recaptcha-response'] ?? '';
    $verifyJson = file_get_contents(
        "https://www.google.com/recaptcha/api/siteverify?secret=" .
        urlencode(RECAPTCHA_SECRET) . "&response=" . urlencode($recaptchaResp)
    );
    $verify = json_decode($verifyJson, true);
    if (!($verify['success'] ?? false)) {
        throw new Exception('reCAPTCHA verification failed. Please try again.');
    }

    /* ---------- 6.  COLLECT INPUT  ---------- */
    // keep raw form data for repopulation on error
    $_SESSION['form_data'] = [
        'name'   => trim($_POST['name']   ?? ''),
        'lname'  => trim($_POST['lname']  ?? ''),
        'email'  => trim($_POST['email']  ?? ''),
        'phone'  => trim($_POST['phone']  ?? ''),
        'address'=> trim($_POST['address']?? ''),
        'state'  => trim($_POST['state']  ?? ''),
        'city'   => trim($_POST['city']   ?? ''),
        'nin'    => trim($_POST['nin']    ?? ''),
        'role'   => $_POST['role']       ?? '',
        'agree_mou'=> isset($_POST['agree_mou'])
    ];

    // individual vars
    $name    = $_SESSION['form_data']['name'];
    $lname   = $_SESSION['form_data']['lname'];
    $email   = $_SESSION['form_data']['email'];
    $phone   = $_SESSION['form_data']['phone'];
    $address = $_SESSION['form_data']['address'];
    $state   = $_SESSION['form_data']['state'];
    $city    = $_SESSION['form_data']['city'];
    $nin     = $_SESSION['form_data']['nin'];
    $role    = $_SESSION['form_data']['role'];
    $passwordPlain = $_POST['password'] ?? '';

    /* ---------- 7.  VALIDATION  ---------- */
    $validRoles = ['buyer','agent','owner','hotel_owner','developer','admin','superadmin'];
    if (!in_array($role, $validRoles, true))          throw new Exception('Invalid role selected.');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))   throw new Exception('Invalid email format.');
    if (!preg_match('/^[0-9]{11}$/', $nin))            throw new Exception('NIN must be exactly 11 digits.');
    if (in_array('', [$name,$lname,$phone,$address,$state,$city,$passwordPlain], true))
        throw new Exception('All required fields must be filled.');

    /* ---- 7a. UNIQUE-EMAIL CHECK ---- */
    $stmt = $conn->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    if ($stmt->get_result()->num_rows) {
        throw new Exception('Email already registered.');
    }
    $stmt->close();

    /* ---------- 8.  FILE UPLOAD (profile_image) ---------- */
    $imageName = 'default.png';
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg','jpeg','png']))      throw new Exception('Profile image must be JPG, JPEG, or PNG.');
        if ($_FILES['profile_image']['size'] > 2*1024*1024) throw new Exception('Profile image must be < 2 MB.');

        $imageName  = uniqid('profile_', true) . ".$ext";
        $uploadDir  = BASE_PATH . '/public/uploads/';
        if (!is_dir($uploadDir))  mkdir($uploadDir, 0755, true);
        if (!move_uploaded_file($_FILES['profile_image']['tmp_name'], $uploadDir . $imageName))
            throw new Exception('Failed to upload profile image.');
    }
/* ---------- 9.  OPTIONAL MOU (agents etc.) ---------- */
$mouFile = null;    // file name stored in DB
$mouPath = null;    // absolute path

if (in_array($role, ['agent','owner','hotel_owner','developer'], true)) {
    if (!isset($_POST['agree_mou']) || !trim($_POST['signed_name'] ?? '')) {
        throw new Exception('You must agree to the MOU and provide a signed name.');
    }

    // full name coming from the hidden input
    $signedName  = trim($_POST['signed_name']);
    $signed_name = $signedName;        // 👈 the variable your template expects

    // generate PDF
    $mouFile = 'mou_' . time() . '.pdf';
    $mouDir  = BASE_PATH . '/documents/mou/';
    $mouPath = $mouDir . $mouFile;
    if (!is_dir($mouDir)) mkdir($mouDir, 0755, true);

    ob_start();
    include '../includes/mou_template.php';    // now sees $signed_name and $role
    $html = ob_get_clean();

    $opts = new Options();
    $opts->set('isRemoteEnabled', true);
    $dompdf = new Dompdf($opts);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4');
    $dompdf->render();
    file_put_contents($mouPath, $dompdf->output());
}

    /* ----------10.  PASSWORD & OTP SECURITY ---------- */
    $passwordHash = password_hash($passwordPlain, PASSWORD_BCRYPT);

    // Generate OTP code & hash
    $otpPlain       = (string)random_int(100000, 999999);            // The value we send
    $otpHash        = password_hash($otpPlain, PASSWORD_DEFAULT);    // The hashed value we store
    $otpExpiresAt   = date('Y-m-d H:i:s', strtotime('+10 minutes'));

    /* ----------11.  DATABASE INSERT ---------- */
    $stmt = $conn->prepare(
        "INSERT INTO users
         (name, lname, email, phone, address, state, city, nin,
          password, role, otp_hash, otp_expires_at, profile_image, mou_file)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)");

    /* `s` for every param (all strings) */
    $stmt->bind_param(
        'ssssssssssssss',
        $name, $lname, $email, $phone, $address, $state, $city, $nin,
        $passwordHash, $role, $otpHash, $otpExpiresAt, $imageName, $mouFile
    );
    if (!$stmt->execute()) throw new Exception('Database insert error: ' . $stmt->error);
    $userId = $stmt->insert_id;
    $stmt->close();

    $conn->query("CREATE TABLE IF NOT EXISTS referrals (\n        id INT AUTO_INCREMENT PRIMARY KEY,\n        referrer_user_id INT NOT NULL,\n        referred_user_id INT NOT NULL,\n        context ENUM('customer','agent') NOT NULL,\n        status ENUM('pending','qualified','rejected') NOT NULL DEFAULT 'pending',\n        reward_amount DECIMAL(12,2) DEFAULT 0,\n        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,\n        qualified_at DATETIME NULL,\n        UNIQUE KEY unique_ref (referrer_user_id, referred_user_id, context),\n        INDEX idx_referred (referred_user_id)\n    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $cols = $conn->query("SHOW COLUMNS FROM users");
    $need_ref_code = true; $need_referrer = true;
    while ($c = $cols->fetch_assoc()) { if ($c['Field'] === 'referral_code') $need_ref_code = false; if ($c['Field'] === 'referrer_user_id') $need_referrer = false; }
    if ($need_ref_code) { $conn->query("ALTER TABLE users ADD COLUMN referral_code VARCHAR(32) UNIQUE NULL"); }
    if ($need_referrer) { $conn->query("ALTER TABLE users ADD COLUMN referrer_user_id INT NULL"); }

    $refParam = $_GET['ref'] ?? ($_SESSION['ref'] ?? ($_COOKIE['ref'] ?? ''));
    $refParam = is_string($refParam) ? trim($refParam) : '';
    if ($refParam !== '') {
        $refStmt = $conn->prepare('SELECT id FROM users WHERE referral_code = ?');
        $refStmt->bind_param('s', $refParam);
        $refStmt->execute();
        $refUser = $refStmt->get_result()->fetch_assoc();
        $refStmt->close();
        if ($refUser && (int)$refUser['id'] !== (int)$userId) {
            $referrerId = (int)$refUser['id'];
            $upd = $conn->prepare('UPDATE users SET referrer_user_id = ? WHERE id = ?');
            $upd->bind_param('ii', $referrerId, $userId);
            $upd->execute();
            $upd->close();
            $context = in_array($role, ['agent','owner','hotel_owner','developer'], true) ? 'agent' : 'customer';
            $ins = $conn->prepare('INSERT IGNORE INTO referrals (referrer_user_id, referred_user_id, context, status) VALUES (?,?,?,\'pending\')');
            $ins->bind_param('iis', $referrerId, $userId, $context);
            $ins->execute();
            $ins->close();
        }
    }

    $code = '';
    for ($i=0; $i<5 && $code === ''; $i++) { $candidate = substr(bin2hex(random_bytes(8)),0,16); $chk = $conn->prepare('SELECT id FROM users WHERE referral_code = ?'); $chk->bind_param('s', $candidate); $chk->execute(); $exists = $chk->get_result()->num_rows > 0; $chk->close(); if (!$exists) $code = $candidate; }
    if ($code === '') { $code = 'ps'.dechex($userId).substr(bin2hex(random_bytes(3)),0,6); }
    $set = $conn->prepare('UPDATE users SET referral_code = ? WHERE id = ?');
    $set->bind_param('si', $code, $userId);
    $set->execute();
    $set->close();

    /* ----------12.  ZOHO SYNC ---------- */
    try {
        $leadId = createZohoLead($name, $lname, $email, $phone, $role);
        if ($leadId) {
            $stmt = $conn->prepare('UPDATE users SET zoho_lead_id=? WHERE id=?');
            $stmt->bind_param('si', $leadId, $userId);
            $stmt->execute();
            $stmt->close();

            $contactId = convertZohoLeadToContact($leadId, $email);
            if ($contactId) {
                $stmt = $conn->prepare('UPDATE users SET zoho_contact_id=? WHERE id=?');
                $stmt->bind_param('si', $contactId, $userId);
                $stmt->execute();
                $stmt->close();
            }
        }
    } catch (Exception $e) {
        error_log('Zoho sync failed: ' . $e->getMessage());
    }

    /* ----------13.  SEND OTP EMAIL ---------- */

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
    $mail->addAddress($email, $name);

    $mail->isHTML(true);
    $mail->Subject = 'PISHONSERV – Verify Your Email Address';
    $mail->Body    = "
        <div style='font-family:Arial;max-width:600px;margin:auto;padding:20px;background:#f9f9f9;'>
            <div style='text-align:center;'>
                <img src='https://pishonserv.com/public/images/logo.png' alt='PISHONSERV' style='width:140px;margin-bottom:20px'>
            </div>
            <div style='background:#fff;padding:30px;border-radius:8px;box-shadow:0 2px 4px rgba(0,0,0,0.1);'>
                <h2 style='color:#092468;'>Welcome, {$name}!</h2>
                <p>Please verify your email with the OTP below (valid 10 minutes):</p>
                <p style='text-align:center;margin:20px 0;'>
                    <span style='display:inline-block;background:#CC9933;color:#fff;font-size:28px;font-weight:bold;padding:10px 20px;border-radius:5px;'>{$otpPlain}</span>
                </p>
                <p>If you did not initiate this registration, ignore this email.</p>
            </div>
        </div>";
    $mail->AltBody = "Your PISHONSERV OTP is: {$otpPlain}\nValid for 10 minutes.";

    // Add Debugging
    $mail->SMTPDebug = 3;
    $mail->Debugoutput = function($str, $level) {
        error_log("📧 [OTP] SMTP DEBUG [$level]: $str");
    };
    error_log("📨 Sending OTP email to {$email} – Body Length: " . strlen($mail->Body));

    $mail->send();
    error_log("✅ OTP email successfully sent to {$email}");
} catch (Exception $e) {
    error_log("❌ OTP email error to {$email}: " . $mail->ErrorInfo);
    error_log("❌ Exception: " . $e->getMessage());
}

// --------- OPTIONAL – SEND MOU EMAILS ---------
if ($mouFile && file_exists($mouPath)) {
    // ✅ USER MOU EMAIL
    try {
        $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'pishonserv@gmail.com';     // your Gmail address
    $mail->Password   = 'pqbc kaum lbgo gkml';     // NOT your Gmail password!
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;  // use TLS over SSL
    $mail->Port       = 465;

    $mail->setFrom('pishonserv@gmail.com', 'PISHONSERV');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Invalid email: $email");
        }

        $mail->addAddress($email, $name);
        $mail->addAttachment($mouPath, $mouFile);

        $mail->isHTML(true);
        $mail->Subject = 'PISHONSERV - Your Memorandum of Understanding (MOU)';
        $mail->Body = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; background-color: #f4f4f4; padding: 20px;'>
            <div style='text-align:center;'>
                <img src='https://pishonserv.com/public/images/logo.png' alt='PISHONSERV' style='width:140px;margin-bottom:20px'>
            </div>
            <div style='background-color: #ffffff; padding: 30px; border-radius: 8px; box-shadow: 0 0 5px rgba(0,0,0,0.05);'>
                <h2 style='color: #092468;'>Dear {$name},</h2>
                <p style='font-size: 16px; color: #333;'>Welcome to <strong>PISHONSERV</strong>!</p>
                <p style='font-size: 16px; color: #333;'>We are excited to have you join our platform as a <strong>" . ucfirst($role) . "</strong>. As part of your onboarding, please find attached your personalized <strong>Memorandum of Understanding (MOU)</strong>.</p>
                <p style='font-size: 16px; color: #333;'>This document outlines your commitments and responsibilities, including providing accurate property listings, upholding trust, and following our community standards.</p>
                <p style='font-size: 16px; color: #333;'>Please keep this document for your records. You can also access it from your account dashboard anytime.</p>
                <p style='font-size: 16px; color: #333;'>If you have any questions, feel free to reach out to our team at <a href='mailto:inquiry@pishonserv.com' style='color: #092468;'>inquiry@pishonserv.com</a>.</p>
                <p style='margin-top: 30px; font-size: 14px; color: #777;'>Sincerely,<br>The PISHONSERV Team</p>
            </div>
            <div style='text-align: center; margin-top: 30px; font-size: 12px; color: #aaa;'>
                <p>© " . date('Y') . " PISHONSERV. All rights reserved.</p>
                <p><a href='https://pishonserv.com' style='color: #092468;'>Visit Website</a> | <a href='mailto:inquiry@pishonserv.com' style='color: #092468;'>Contact Support</a></p>
            </div>
        </div>";
        $mail->AltBody = "Dear {$name},\n\nWelcome to PISHONSERV!\n\nYou're now registered as a " . ucfirst($role) . ". Your MOU is attached.\n\nQuestions? Email support@pishonserv.com.\n\n– PISHONSERV Team";

        // Debugging
        $mail->SMTPDebug = 3;
        $mail->Debugoutput = function($str, $level) {
            error_log("📧 [MOU USER] SMTP DEBUG [$level]: $str");
        };
        error_log("📎 Sending MOU to user: {$email}, File: {$mouFile} (" . filesize($mouPath) . " bytes)");

        $mail->send();
        error_log("✅ MOU email sent to user: {$email}");
    } catch (Exception $e) {
        error_log("❌ User MOU email error for {$email}: " . $mail->ErrorInfo);
        
        // ✅ Fallback: Notify Superadmin
        try {
            $failMail = new PHPMailer(true);
            $failMail->isSMTP();
            $failMail->Host       = SMTP_HOST;
            $failMail->SMTPAuth   = true;
            $failMail->Username   = SMTP_USER;
            $failMail->Password   = SMTP_PASS;
            $failMail->SMTPSecure = (SMTP_SECURE === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS);
            $failMail->Port       = SMTP_PORT;
            $failMail->setFrom(FROM_EMAIL, FROM_NAME);
            $failMail->addAddress('pishonserv@gmail.com', 'Super Admin');
            $failMail->isHTML(true);
            $failMail->Subject = 'PISHONSERV - Failed to Send User MOU Email';
            $failMail->Body = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; padding: 20px;'>
                <h2 style='color: #092468;'>MOU Email Delivery Failure</h2>
                <p>Failed to send MOU to:</p>
                <p><strong>Name:</strong> {$name} {$lname}<br>
                    <strong>Email:</strong> {$email}<br>
                    <strong>Role:</strong> " . ucfirst($role) . "</p>
                <p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>
            </div>";
            $failMail->SMTPDebug = 3;
            $failMail->Debugoutput = function($str, $level) {
                error_log("📧 [MOU FALLBACK] SMTP DEBUG [$level]: $str");
            };
            $failMail->send();
            error_log("📧 Superadmin notified about MOU failure for: {$email}");
        } catch (Exception $failEx) {
            error_log("❌ Superadmin notification failed: " . $failEx->getMessage());
        }
    }

    // ✅ SUPERADMIN MOU COPY
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
        $mail->addAddress('pishonserv@gmail.com', 'Super Admin');
        $mail->addAttachment($mouPath, $mouFile);
        $mail->isHTML(true);
        $mail->Subject = 'PISHONSERV - New MOU Submission';
        $mail->Body = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; background-color: #f4f4f4; padding: 20px;'>
            <div style='text-align:center;'>
                <img src='https://pishonserv.com/public/images/logo.png' alt='PISHONSERV' style='width:140px;margin-bottom:20px'>
            </div>
            <div style='background-color: #ffffff; padding: 30px; border-radius: 8px; box-shadow: 0 0 5px rgba(0,0,0,0.05);'>
                <h2 style='color: #092468;'>New MOU Submission Notification</h2>
                <p style='font-size: 16px; color: #333;'>A new user has registered and submitted an MOU:</p>
                <p style='font-size: 16px; color: #333;'>
                    <strong>Name:</strong> {$name} {$lname}<br>
                    <strong>Email:</strong> {$email}<br>
                    <strong>Role:</strong> " . ucfirst($role) . "<br>
                    <strong>Date:</strong> " . date('F d, Y H:i:s') . "
                </p>
                <p style='font-size: 16px; color: #333;'>The MOU is attached for your records and further processing.</p>
            </div>
            <div style='text-align: center; margin-top: 30px; font-size: 12px; color: #aaa;'>
                <p>© " . date('Y') . " PISHONSERV. All rights reserved.</p>
            </div>
        </div>";
        $mail->AltBody = "New MOU Submission\n\nName: {$name} {$lname}\nEmail: {$email}\nRole: " . ucfirst($role) . "\nDate: " . date('F d, Y H:i:s') . "\n\nMOU is attached.";

        $mail->SMTPDebug = 3;
        $mail->Debugoutput = function($str, $level) {
            error_log("📧 [MOU SUPERADMIN] SMTP DEBUG [$level]: $str");
        };
        error_log("📨 Sending Superadmin MOU copy – File: {$mouFile}");

        $mail->send();
        error_log("✅ Superadmin received MOU copy.");
    } catch (Exception $e) {
        error_log("❌ Superadmin MOU email error: " . $e->getMessage());
    }
}

    /* ----------14.  FINAL SESSION & REDIRECT ---------- */
    // Set temporary session data for OTP verification
    $_SESSION['user_id_to_verify'] = $userId;
    $_SESSION['user_email_to_verify'] = $email;

    // Unset form data to avoid repopulating on refresh
    unset($_SESSION['form_data']);
    
    $_SESSION['success'] = "Registration successful! Check your email for OTP verification." . ($mouFile ? " Your MOU has been sent to your email." : "");
    header("Location: ../auth/verify-otp.php");
    exit();
} catch (Exception $e) {
    error_log("Registration error: " . $e->getMessage());
    $_SESSION['error'] = $e->getMessage();
    header("Location: ../auth/register.php");
    exit();
}
require_once '../includes/config.php';

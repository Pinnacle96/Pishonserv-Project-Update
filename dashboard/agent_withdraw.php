<?php
/**
 * Withdraw handler – Safe “reserve then settle” flow
 * Logs every critical step to a file for auditing/debugging
 */
session_start();
require_once '../includes/config.php';     // PAYSTACK_SECRET_KEY
require_once '../includes/db_connect.php'; // $conn (mysqli)
require_once '../vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$logFile = __DIR__ . '/../logs/withdrawals.log';
function log_withdraw($msg) {
    global $logFile;
    $time = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$time] $msg\n", FILE_APPEND);
}

if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = "You must be logged in.";
    header("Location: ../auth/login.php");
    exit();
}

$agent_id = (int)$_SESSION['user_id'];

if (($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['withdraw']))
    || (isset($_GET['resume']) && !empty($_SESSION['withdraw_otp_verified']) && isset($_SESSION['pending_withdraw_amount']) && isset($_SESSION['pending_withdraw_bank_id']))) {
    $amount  = isset($_POST['amount']) ? (float)$_POST['amount'] : (float)($_SESSION['pending_withdraw_amount'] ?? 0.0);
    $bank_id = isset($_POST['bank_id']) ? (int)$_POST['bank_id'] : (int)($_SESSION['pending_withdraw_bank_id'] ?? 0);

    if ($amount <= 0) {
        $_SESSION['error'] = "Invalid amount.";
        header("Location: ../dashboard/agent_earnings.php");
        exit();
    }
    if ($bank_id <= 0) {
        $_SESSION['error'] = "Please choose a bank account.";
        header("Location: ../dashboard/agent_earnings.php");
        exit();
    }

    // Verify bank belongs to this user
    $stmt = $conn->prepare("SELECT paystack_recipient_code FROM bank_accounts WHERE id=? AND user_id=? LIMIT 1");
    $stmt->bind_param("ii", $bank_id, $agent_id);
    $stmt->execute();
    $bank = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$bank || empty($bank['paystack_recipient_code'])) {
        $_SESSION['error'] = "Invalid bank account selected.";
        header("Location: ../dashboard/agent_earnings.php");
        exit();
    }
    $recipient_code = $bank['paystack_recipient_code'];

    if (empty($_SESSION['withdraw_otp_verified'])) {
        $usr = $conn->prepare('SELECT email, name FROM users WHERE id = ? LIMIT 1');
        $usr->bind_param('i', $agent_id);
        $usr->execute();
        $ud = $usr->get_result()->fetch_assoc();
        $usr->close();

        if (!$ud || empty($ud['email'])) {
            $_SESSION['error'] = "Unable to send OTP.";
            header("Location: ../dashboard/agent_earnings.php");
            exit();
        }

        $otpPlain     = (string)random_int(100000, 999999);
        $otpHash      = password_hash($otpPlain, PASSWORD_DEFAULT);
        $otpExpiresAt = date('Y-m-d H:i:s', strtotime('+10 minutes'));

        $upd = $conn->prepare('UPDATE users SET otp_hash = ?, otp_expires_at = ? WHERE id = ?');
        $upd->bind_param('ssi', $otpHash, $otpExpiresAt, $agent_id);
        $upd->execute();
        $upd->close();

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
                file_put_contents(__DIR__ . '/../logs/withdrawals.log', "[$time] [SMTP][$level] $str\n", FILE_APPEND);
            };

            $mail->setFrom(FROM_EMAIL, FROM_NAME);
            $mail->addAddress($ud['email'], $ud['name'] ?? '');
            $mail->isHTML(true);
            $mail->Subject = 'PISHONSERV – Withdrawal OTP';
            $mail->Body    = "<div style='font-family:Arial;max-width:600px;margin:auto;padding:20px;background:#f9f9f9;'>\n<div style='text-align:center;'><img src='https://pishonserv.com/public/images/logo.png' alt='PISHONSERV' style='width:140px;margin-bottom:20px'></div>\n<div style='background:#fff;padding:30px;border-radius:8px;box-shadow:0 2px 4px rgba(0,0,0,0.1);'><h2 style='color:#092468;'>Withdrawal Verification</h2><p>Use the OTP below to confirm your withdrawal (valid 10 minutes):</p><p style='text-align:center;margin:20px 0;'><span style='display:inline-block;background:#CC9933;color:#fff;font-size:28px;font-weight:bold;padding:10px 20px;border-radius:5px;'>{$otpPlain}</span></p></div></div>";
            $mail->AltBody = "Your PISHONSERV withdrawal OTP: {$otpPlain}\nValid for 10 minutes.";
            $mail->send();
            log_withdraw("OTP email sent to {$ud['email']} for user {$agent_id}, amount=₦" . number_format($amount,2) . ", bank_id={$bank_id}");
            $_SESSION['user_id_to_verify'] = $agent_id;
            $_SESSION['user_email_to_verify'] = $ud['email'];
            $_SESSION['withdraw_otp_mode'] = true;
            $_SESSION['pending_withdraw_amount'] = $amount;
            $_SESSION['pending_withdraw_bank_id'] = $bank_id;
            unset($_SESSION['error']);
            $_SESSION['success'] = "OTP sent successfully! Check your email.";
        } catch (Exception $e) {
            error_log('Withdraw OTP mail error: ' . ($mail->ErrorInfo ?? $e->getMessage()));
            log_withdraw("OTP email error for user {$agent_id}: " . ($mail->ErrorInfo ?? $e->getMessage()));
            log_withdraw("Exception detail: " . $e->getMessage());
            $_SESSION['user_id_to_verify'] = $agent_id;
            $_SESSION['user_email_to_verify'] = $ud['email'];
            $_SESSION['withdraw_otp_mode'] = true;
            $_SESSION['pending_withdraw_amount'] = $amount;
            $_SESSION['pending_withdraw_bank_id'] = $bank_id;
            $_SESSION['error'] = "We couldn’t send the OTP email. Please try again.";
            header("Location: ../auth/verify-otp.php?mode=withdraw");
            exit();
        }

        header("Location: ../auth/verify-otp.php?mode=withdraw");
        exit();
    }

    $reference = 'WD-' . $agent_id . '-' . bin2hex(random_bytes(6));
    $reason    = "Agent Withdrawal";
    $fee       = 0.00;

    try {
        // TX#1: Reserve funds + create withdrawal row
        $conn->begin_transaction();

        $stmt = $conn->prepare("
            UPDATE wallets
               SET balance = balance - ?
             WHERE user_id = ? AND balance >= ?
        ");
        $stmt->bind_param("dii", $amount, $agent_id, $amount);
        $stmt->execute();

        if ($stmt->affected_rows !== 1) {
            $stmt->close();
            $conn->rollback();
            log_withdraw("User {$agent_id} withdrawal failed: insufficient balance for ₦{$amount}");
            $_SESSION['error'] = "Insufficient balance!";
            unset($_SESSION['withdraw_otp_verified'], $_SESSION['withdraw_otp_mode'], $_SESSION['pending_withdraw_amount'], $_SESSION['pending_withdraw_bank_id']);
            header("Location: ../dashboard/agent_earnings.php");
            exit();
        }
        $stmt->close();

        $hasReference = false;
        if ($ck = $conn->query("SHOW COLUMNS FROM withdrawals LIKE 'reference'")) {
            $hasReference = ($ck->num_rows === 1);
            $ck->close();
        }

        if (!$hasReference) {
            if ($conn->query("ALTER TABLE withdrawals ADD COLUMN reference VARCHAR(64) NULL AFTER reason")) {
                $hasReference = true;
                log_withdraw("Added missing column withdrawals.reference");
            }
        }

        if ($hasReference) {
            $stmt = $conn->prepare("\n                INSERT INTO withdrawals\n                    (user_id, bank_account_id, amount, fee, currency, status, recipient_code, reason, reference, created_at, updated_at)\n                VALUES (?, ?, ?, ?, 'NGN', 'processing', ?, ?, ?, NOW(), NOW())\n            ");
            $stmt->bind_param("iiddsss", $agent_id, $bank_id, $amount, $fee, $recipient_code, $reason, $reference);
        } else {
            $stmt = $conn->prepare("\n                INSERT INTO withdrawals\n                    (user_id, bank_account_id, amount, fee, currency, status, recipient_code, reason, created_at, updated_at)\n                VALUES (?, ?, ?, ?, 'NGN', 'processing', ?, ?, NOW(), NOW())\n            ");
            $stmt->bind_param("iiddss", $agent_id, $bank_id, $amount, $fee, $recipient_code, $reason);
        }
        $stmt->execute();
        $withdrawal_id = (int)$stmt->insert_id;
        $stmt->close();

        $conn->commit();
        log_withdraw("User {$agent_id} reserved ₦{$amount} for withdrawal #{$withdrawal_id}, ref={$reference}");

        // External Paystack call
        $payload = [
            "source"    => "balance",
            "amount"    => (int) round($amount * 100),
            "recipient" => $recipient_code,
            "reason"    => $reason,
            "reference" => $reference,
        ];
        $headers = [
            "Authorization: Bearer " . PAYSTACK_SECRET_KEY,
            "Content-Type: application/json",
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://api.paystack.co/transfer");
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        if ($response === false) {
            $curlErr = curl_error($ch);
            curl_close($ch);
            throw new Exception("Network error: {$curlErr}");
        }
        curl_close($ch);

        $ps = json_decode($response, true);

        if (!is_array($ps) || !($ps['status'] ?? false)) {
            $errMsg = is_array($ps) ? ($ps['message'] ?? 'Unknown Paystack error') : 'Invalid Paystack response';
            log_withdraw("User {$agent_id} withdrawal #{$withdrawal_id} failed: {$errMsg}");

            $conn->begin_transaction();
            $stmt = $conn->prepare("UPDATE wallets SET balance = balance + ? WHERE user_id = ?");
            $stmt->bind_param("di", $amount, $agent_id);
            $stmt->execute();
            $stmt->close();

            $stmt = $conn->prepare("UPDATE withdrawals SET status='failed', error_message=?, updated_at=NOW() WHERE id=?");
            $stmt->bind_param("si", $errMsg, $withdrawal_id);
            $stmt->execute();
            $stmt->close();

            $conn->commit();

            $_SESSION['error'] = "Withdrawal failed: " . $errMsg;
            unset($_SESSION['withdraw_otp_verified'], $_SESSION['withdraw_otp_mode'], $_SESSION['pending_withdraw_amount'], $_SESSION['pending_withdraw_bank_id']);
            header("Location: ../dashboard/agent_earnings.php");
            exit();
        }

        $transfer_code = $ps['data']['transfer_code'] ?? null;
        $ps_reference  = $ps['data']['reference'] ?? null;

        $conn->begin_transaction();

        $stmt = $conn->prepare("
            UPDATE withdrawals
               SET status='completed',
                   paystack_transfer_code=?,
                   paystack_reference=?,
                   updated_at=NOW()
             WHERE id=? AND status='processing'
        ");
        $stmt->bind_param("ssi", $transfer_code, $ps_reference, $withdrawal_id);
        $stmt->execute();
        $stmt->close();

        $desc = "Withdrawal (WD#{$withdrawal_id}) to bank (recipient: {$recipient_code})";
        $stmt = $conn->prepare("
            INSERT INTO transactions
                (user_id, amount, transaction_type, type, status, description, created_at)
            VALUES (?, ?, 'withdrawal', 'debit', 'completed', ?, NOW())
        ");
        $stmt->bind_param("ids", $agent_id, $amount, $desc);
        $stmt->execute();
        $stmt->close();

        $conn->commit();
        log_withdraw("User {$agent_id} withdrawal #{$withdrawal_id} succeeded, ₦{$amount}, transfer_code={$transfer_code}");

        $_SESSION['success'] = "Withdrawal of ₦" . number_format($amount, 2) . " processed successfully!";
        unset($_SESSION['withdraw_otp_verified'], $_SESSION['withdraw_otp_mode'], $_SESSION['pending_withdraw_amount'], $_SESSION['pending_withdraw_bank_id'], $_SESSION['user_id_to_verify'], $_SESSION['user_email_to_verify']);
        header("Location: ../dashboard/agent_earnings.php");
        exit();

    } catch (Exception $e) {
        if ($conn->errno === 0 && $conn->in_transaction) {
            $conn->rollback();
        }
        log_withdraw("User {$agent_id} withdrawal error: " . $e->getMessage());
        $_SESSION['error'] = "Withdrawal error: " . $e->getMessage();
        unset($_SESSION['withdraw_otp_verified'], $_SESSION['withdraw_otp_mode'], $_SESSION['pending_withdraw_amount'], $_SESSION['pending_withdraw_bank_id']);
        header("Location: ../dashboard/agent_earnings.php");
        exit();
    }
} else {
    header("Location: ../dashboard/agent_earnings.php");
    exit();
}

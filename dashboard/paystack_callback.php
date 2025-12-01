<?php
session_start();

// ✅ Full Debug Error Reporting
error_reporting(E_ALL);
ini_set('display_errors', 0); // Disable for production
ini_set('log_errors', 1);

// ✅ Ensure Logs Folder Exists
$log_dir = "../logs";
$log_file = $log_dir . "/paystack_errors.log";

if (!file_exists($log_dir)) {
    if (!mkdir($log_dir, 0777, true)) {
        error_log("❌ Failed to create log directory: $log_dir");
        http_response_code(500);
        exit("Server error: Unable to initialize logging.");
    }
}
ini_set('error_log', $log_file);

// ✅ Include Config and Functions
$include_files = ['config.php', 'db_connect.php', 'zoho_functions.php'];
foreach ($include_files as $file) {
    $path = "../includes/$file"; // Assuming /dashboard/
    if (!file_exists($path)) {
        error_log("❌ Include file missing: $path");
        http_response_code(500);
        exit("Server error: Missing required file.");
    }
    require_once $path;
}

// ✅ Log Paystack Callback
$reference = $_GET['reference'] ?? '';
error_log("🔔 Paystack Callback Triggered. Reference: " . ($reference ?: 'No Reference'));

if (!$reference) {
    error_log("❌ Invalid transaction reference.");
    $_SESSION['error'] = "Invalid transaction reference.";
    header("Location: ../dashboard/payment_failed.php");
    exit();
}

// ✅ Fetch Transaction from Payments
try {
    $stmt = $conn->prepare("SELECT * FROM payments WHERE transaction_id = ? AND status = 'pending'");
    if (!$stmt) {
        throw new Exception("Database prepare error: " . $conn->error);
    }
    $stmt->bind_param("s", $reference);
    $stmt->execute();
    $transaction = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$transaction) {
        error_log("❌ Transaction not found or already processed. Reference: $reference");
        $_SESSION['error'] = "Transaction not found or already processed.";
        header("Location: ../dashboard/payment_failed.php");
        exit();
    }
} catch (Exception $e) {
    error_log("❌ Database error fetching transaction: " . $e->getMessage());
    http_response_code(500);
    exit("Server error: Database issue.");
}

$user_id = $transaction['user_id'];
$property_id = $transaction['property_id'];
$booking_id = $transaction['booking_id'];
$total_amount = $transaction['amount'];

error_log("✅ Transaction Fetched: user_id=$user_id, property_id=$property_id, booking_id=$booking_id");

// ✅ Fetch Property Info
try {
    $stmt = $conn->prepare("SELECT p.owner_id, p.listing_type, p.zoho_product_id, u.zoho_lead_id 
                            FROM properties p 
                            JOIN users u ON p.owner_id = u.id 
                            WHERE p.id = ?");
    if (!$stmt) {
        throw new Exception("Database prepare error: " . $conn->error);
    }
    $stmt->bind_param("i", $property_id);
    $stmt->execute();
    $property = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$property) {
        throw new Exception("Property not found for ID: $property_id");
    }
} catch (Exception $e) {
    error_log("❌ Error fetching property: " . $e->getMessage());
    http_response_code(500);
    exit("Server error: Property lookup failed.");
}

$owner_id = $property['owner_id'];
$listing_type = $property['listing_type'];
$zoho_product_id = $property['zoho_product_id'];
$zoho_owner_lead_id = $property['zoho_lead_id'];

error_log("✅ Property Fetched: owner_id=$owner_id, listing_type=$listing_type");

// ✅ Fetch Booking Record (Double Check)
try {
    $stmt = $conn->prepare("SELECT id FROM bookings WHERE id = ?");
    if (!$stmt) {
        throw new Exception("Database prepare error: " . $conn->error);
    }
    $stmt->bind_param("i", $booking_id);
    $stmt->execute();
    $booking = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$booking) {
        throw new Exception("Booking record not found for booking_id=$booking_id");
    }
} catch (Exception $e) {
    error_log("❌ Error fetching booking: " . $e->getMessage());
    http_response_code(500);
    exit("Server error: Booking lookup failed.");
}
error_log("✅ Booking Record Verified: booking_id=$booking_id");

// ✅ Verify Payment with Paystack
$paystack_secret_key = defined('PAYSTACK_SECRET_KEY') ? PAYSTACK_SECRET_KEY : '';
if (empty($paystack_secret_key)) {
    error_log("❌ Paystack secret key not defined in config.php");
    http_response_code(500);
    exit("Server error: Payment configuration issue.");
}

$paystack_url = "https://api.paystack.co/transaction/verify/{$reference}";
$headers = [
    "Authorization: Bearer $paystack_secret_key",
    "Content-Type: application/json"
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $paystack_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$paystack_response = json_decode($response, true);
error_log("📡 Paystack Response ($http_code): " . json_encode($paystack_response, JSON_PRETTY_PRINT));

if (!$paystack_response['status'] || $paystack_response['data']['status'] !== 'success') {
    error_log("❌ Payment verification failed for reference: $reference");
    $_SESSION['error'] = "Payment verification failed.";
    header("Location: ../dashboard/payment_failed.php");
    exit();
}

error_log("✅ Paystack Payment Verified Successfully");

// ✅ Start Transaction
try {
    $conn->begin_transaction();

    // ✅ Wallet Calculations
    $platform_fee = 0.10 * $total_amount;
    $owner_earnings = 0.90 * $total_amount;
    $superadmin_id = 9; // Adjust your real Superadmin ID here

    $refCols = $conn->query("SHOW COLUMNS FROM users");
    $hasReferrer = false;
    while ($rc = $refCols->fetch_assoc()) { if ($rc['Field'] === 'referrer_user_id') { $hasReferrer = true; break; } }
    $referrerId = 0; $referralReward = 0.0; $referralApplicable = false;
    if ($hasReferrer) {
        $ru = $conn->prepare('SELECT referrer_user_id FROM users WHERE id = ?');
        $ru->bind_param('i', $user_id);
        $ru->execute();
        $refRow = $ru->get_result()->fetch_assoc();
        $ru->close();
        $referrerId = (int)($refRow['referrer_user_id'] ?? 0);
        if ($referrerId > 0 && $total_amount >= REFERRAL_MIN_BOOKING_NGN) {
            $referralApplicable = true;
            $referralReward = (float)REFERRAL_CUSTOMER_REWARD_NGN;
        }
    }

    // ✅ Wallet Update for Owner
    $stmt = $conn->prepare("SELECT id FROM wallets WHERE user_id = ?");
    $stmt->bind_param("i", $owner_id);
    $stmt->execute();
    $wallet_exists = $stmt->get_result()->num_rows > 0;
    $stmt->close();

    if ($wallet_exists) {
        $stmt = $conn->prepare("UPDATE wallets SET balance = balance + ? WHERE user_id = ?");
        $stmt->bind_param("di", $owner_earnings, $owner_id);
    } else {
        $stmt = $conn->prepare("INSERT INTO wallets (user_id, balance) VALUES (?, ?)");
        $stmt->bind_param("id", $owner_id, $owner_earnings);
    }
    $stmt->execute();
    $stmt->close();

    // ✅ Wallet Update for Platform (net of referral bonus if applicable)
    $platform_credit = $platform_fee - ($referralApplicable ? $referralReward : 0.0);
    if ($platform_credit < 0) { $platform_credit = 0.0; }
    $stmt = $conn->prepare("UPDATE wallets SET balance = balance + ? WHERE user_id = ?");
    $stmt->bind_param("di", $platform_credit, $superadmin_id);
    $stmt->execute();
    $stmt->close();

    // ✅ Record Transactions
    $description_owner = "Earnings from property booking";
    $stmt = $conn->prepare("INSERT INTO transactions (user_id, amount, transaction_type, type, status, description) 
                            VALUES (?, ?, 'booking', 'credit', 'completed', ?)");
    $stmt->bind_param("ids", $owner_id, $owner_earnings, $description_owner);
    $stmt->execute();
    $stmt->close();

    $description_platform = "Platform commission from booking";
    $stmt = $conn->prepare("INSERT INTO transactions (user_id, amount, transaction_type, type, status, description) 
                            VALUES (?, ?, 'booking', 'credit', 'completed', ?)");
    $stmt->bind_param("ids", $superadmin_id, $platform_credit, $description_platform);
    $stmt->execute();
    $stmt->close();

    // ✅ Update Payment
    $stmt = $conn->prepare("UPDATE payments SET status = 'completed' WHERE transaction_id = ?");
    $stmt->bind_param("s", $reference);
    $stmt->execute();
    $stmt->close();

    // ✅ Update Booking Status and Payment Status
    $stmt = $conn->prepare("UPDATE bookings SET status = ?, payment_status = ? WHERE id = ?");
    $confirmed_status = 'confirmed';
    $paid_status = 'paid';
    $stmt->bind_param("ssi", $confirmed_status, $paid_status, $booking_id);
    $stmt->execute();
    $stmt->close();

    error_log("✅ Booking and Payment Updated for booking_id=$booking_id");

    if ($referralApplicable) {
        $conn->query("CREATE TABLE IF NOT EXISTS referrals (\n                id INT AUTO_INCREMENT PRIMARY KEY,\n                referrer_user_id INT NOT NULL,\n                referred_user_id INT NOT NULL,\n                context ENUM('customer','agent') NOT NULL,\n                status ENUM('pending','qualified','rejected') NOT NULL DEFAULT 'pending',\n                reward_amount DECIMAL(12,2) DEFAULT 0,\n                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,\n                qualified_at DATETIME NULL,\n                UNIQUE KEY unique_ref (referrer_user_id, referred_user_id, context),\n                INDEX idx_referred (referred_user_id)\n            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $sel = $conn->prepare("SELECT id, status FROM referrals WHERE referrer_user_id=? AND referred_user_id=? AND context='customer' LIMIT 1");
        $sel->bind_param('ii', $referrerId, $user_id);
        $sel->execute();
        $ref = $sel->get_result()->fetch_assoc();
        $sel->close();
        if (!$ref) {
            $ins = $conn->prepare("INSERT INTO referrals (referrer_user_id, referred_user_id, context, status) VALUES (?,?, 'customer', 'pending')");
            $ins->bind_param('ii', $referrerId, $user_id);
            $ins->execute();
            $ins->close();
        }
        $upd = $conn->prepare("UPDATE referrals SET status='qualified', reward_amount=?, qualified_at=NOW() WHERE referrer_user_id=? AND referred_user_id=? AND context='customer' AND status!='qualified'");
        $reward = $referralReward;
        $upd->bind_param('dii', $reward, $referrerId, $user_id);
        $upd->execute();
        $upd->close();

            $w = $conn->prepare('SELECT id FROM wallets WHERE user_id = ?');
            $w->bind_param('i', $referrerId);
            $w->execute();
            $wx = $w->get_result()->num_rows > 0; $w->close();
            if ($wx) {
            $wu = $conn->prepare('UPDATE wallets SET balance = balance + ? WHERE user_id = ?');
            $wu->bind_param('di', $reward, $referrerId);
            $wu->execute();
            $wu->close();
        } else {
            $wi = $conn->prepare('INSERT INTO wallets (user_id, balance) VALUES (?, ?)');
            $wi->bind_param('id', $referrerId, $reward);
            $wi->execute();
            $wi->close();
        }
        $td = $conn->prepare("INSERT INTO transactions (user_id, amount, transaction_type, type, status, description) VALUES (?, ?, 'referral_bonus', 'credit', 'completed', ?) ");
        $desc = 'Customer referral bonus for booking';
        $td->bind_param('ids', $referrerId, $reward, $desc);
        $td->execute();
        $td->close();
    }

    // ✅ Update Zoho CRM Booking Status
    if ($booking_id) {
        // Double-check zoho_deal_id exists
        $stmt = $conn->prepare("SELECT zoho_deal_id FROM bookings WHERE id = ? AND zoho_deal_id IS NOT NULL");
        $stmt->bind_param("i", $booking_id);
        $stmt->execute();
        $booking = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($booking && !empty($booking['zoho_deal_id'])) {
            try {
                $zoho_update = updateZohoBookingStatus($booking_id, 'Booked');
                if ($zoho_update) {
                    error_log("✅ Zoho Deal updated to Confirmed for booking_id: $booking_id, zoho_deal_id: {$booking['zoho_deal_id']}");
                } else {
                    error_log("❌ Failed to update Zoho Deal for booking_id: $booking_id, zoho_deal_id: {$booking['zoho_deal_id']}. Check Zoho CRM API response or Booking_Status field configuration.");
                }
            } catch (Exception $e) {
                error_log("❌ Zoho CRM update error for booking_id: $booking_id, zoho_deal_id: {$booking['zoho_deal_id']}. Error: " . $e->getMessage());
            }
        } else {
            error_log("⚠️ zoho_deal_id missing for booking_id: $booking_id. Check checkout.php sync to ensure zoho_deal_id is set.");
        }
    } else {
        error_log("⚠️ booking_id missing. Skipping Zoho CRM update.");
    }

    // ✅ Commit Transaction
    $conn->commit();
    error_log("✅ Transaction committed successfully.");

    $_SESSION['success'] = "Payment successful! Booking confirmed.";
    header("Location: ../dashboard/payment_success.php?reference=$reference");
    exit();

} catch (Exception $e) {
    $conn->rollback();
    error_log("❌ Transaction failed: " . $e->getMessage());
    $_SESSION['error'] = "Transaction failed. Please contact support.";
    header("Location: ../dashboard/payment_failed.php");
    exit();
}
?>

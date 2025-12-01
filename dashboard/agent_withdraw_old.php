<?php
session_start();
include '../includes/config.php'; // Assuming PAYSTACK_SECRET_KEY is here
include '../includes/db_connect.php';

$agent_id = $_SESSION['user_id'];

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['withdraw'])) {
    $withdraw_amount = $_POST['amount'];
    $bank_id = $_POST['bank_id']; // This ID now refers to the bank_accounts table

    // ✅ Fetch Wallet Balance from the wallets table
    $stmt = $conn->prepare("SELECT balance FROM wallets WHERE user_id = ?");
    $stmt->bind_param("i", $agent_id);
    $stmt->execute();
    $wallet = $stmt->get_result()->fetch_assoc();

    // Check if wallet exists, if not, handle error
    if (!$wallet) {
        $_SESSION['error'] = "Wallet not found for this user.";
        header("Location: agent_earnings.php");
        exit();
    }

    $balance = (float)$wallet['balance']; // Cast to float to prevent comparison issues

    // ✅ Fetch Bank Details from the NEW bank_accounts table
    $stmt = $conn->prepare("SELECT paystack_recipient_code FROM bank_accounts WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $bank_id, $agent_id);
    $stmt->execute();
    $bank = $stmt->get_result()->fetch_assoc();

    // Check if the bank account exists for the user
    if (!$bank) {
        $_SESSION['error'] = "Invalid bank account selected.";
        header("Location: agent_earnings.php");
        exit();
    }

    $recipient_code = $bank['paystack_recipient_code'];

    // ✅ Check for Sufficient Balance
    if ($withdraw_amount > $balance) {
        $_SESSION['error'] = "Insufficient balance!";
        header("Location: agent_earnings.php");
        exit();
    }

    // ✅ Step 1: Send Money to Agent's Bank Account via Paystack
    $paystack_url = "https://api.paystack.co/transfer";
    $paystack_data = [
        "source" => "balance",
        "amount" => $withdraw_amount * 100, // Convert to kobo
        "recipient" => $recipient_code,
        "reason" => "Agent Withdrawal"
    ];

    $headers = [
        "Authorization: Bearer " . PAYSTACK_SECRET_KEY,
        "Content-Type: application/json"
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $paystack_url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($paystack_data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Recommended for local testing, remove for production

    $response = curl_exec($ch);
    curl_close($ch);

    $paystack_response = json_decode($response, true);

    if (!$paystack_response['status']) {
        $_SESSION['error'] = "Withdrawal failed: " . $paystack_response['message'];
        header("Location: agent_earnings.php");
        exit();
    }

    // ✅ Step 2: Deduct Wallet Balance (This part is correct and doesn't need to change)
    $stmt = $conn->prepare("UPDATE wallets SET balance = balance - ? WHERE user_id = ?");
    $stmt->bind_param("di", $withdraw_amount, $agent_id);
    $stmt->execute();

    $_SESSION['success'] = "Withdrawal of ₦" . number_format($withdraw_amount, 2) . " processed successfully!";
    header("Location: agent_earnings.php");
    exit();
}
<?php
session_start();
include '../includes/db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit();
}

$allowed_roles = ['superadmin','agent','owner','hotel_owner','developer','host','buyer'];
if (!in_array($_SESSION['role'] ?? '', $allowed_roles, true)) {
    header('Location: ../auth/unauthorized.php');
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$type = isset($_GET['type']) && in_array($_GET['type'], ['credit','debit'], true) ? $_GET['type'] : null;

if ($type) {
    $stmt = $conn->prepare("SELECT amount, type, status, created_at FROM transactions WHERE user_id = ? AND type = ? ORDER BY created_at DESC");
    $stmt->bind_param('is', $user_id, $type);
} else {
    $stmt = $conn->prepare("SELECT amount, type, status, created_at FROM transactions WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->bind_param('i', $user_id);
}
$stmt->execute();
$result = $stmt->get_result();

$page_content = __DIR__ . '/agent_transaction_content.php';
include 'dashboard_layout.php';
?>

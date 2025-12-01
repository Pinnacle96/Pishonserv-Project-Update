<?php
// dashboard/admin_withdrawals.php
session_start();
require_once '../includes/db_connect.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin','superadmin'])) {
    header("Location: ../auth/login.php");
    exit();
}

$page_content = __DIR__ . "/admin_withdrawals_content.php";
include 'dashboard_layout.php';

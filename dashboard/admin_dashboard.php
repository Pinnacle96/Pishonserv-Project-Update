<?php
session_start();
include '../includes/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

$page_content = __DIR__ . "/admin_dashboard_content.php";
include 'dashboard_layout.php';
?>
<?php
/* -------------------------------------------------
   agent_mou.php  –  download signed MOU
   ------------------------------------------------- */
session_start();
include '../includes/db_connect.php';

/* 1. AUTH CHECK -------------------------------------------------- */
if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
    $_SESSION['error'] = 'Please log in to access this page.';
    header('Location: ../auth/login.php');
    exit;
}

/* 2. ROLE CHECK -------------------------------------------------- */
$allowedRoles = ['agent','owner','hotel_owner','developer', 'host'];
if (!in_array($_SESSION['role'], $allowedRoles, true)) {
    $_SESSION['error'] = 'You do not have permission to access this page.';
    header('Location: ../index.php');
    exit;
}

/* 3. FETCH USER’S MOU FILE -------------------------------------- */
$userId = $_SESSION['user_id'];

try {
    $stmt = $conn->prepare('SELECT mou_file FROM users WHERE id = ?');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $mouFile = $stmt->get_result()->fetch_column();
    $stmt->close();

    if (!$mouFile) {
        throw new Exception('No MOU file associated with your account.');
    }

    $mouPath = realpath(__DIR__ . '/../documents/mou/' . $mouFile);
    if (!$mouPath || !is_file($mouPath)) {
        throw new Exception('MOU file is missing or inaccessible.');
    }

    /* 4. STREAM FILE --------------------------------------------- */
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . basename($mouFile) . '"');
    header('Content-Length: ' . filesize($mouPath));
    header('Cache-Control: no-cache, must-revalidate');
    readfile($mouPath);
    exit;

} catch (Exception $e) {
    error_log('MOU download error: ' . $e->getMessage());
    $_SESSION['error'] = 'Unable to retrieve your MOU. Please try again.';
    header('Location: ../index.php');
    exit;
}

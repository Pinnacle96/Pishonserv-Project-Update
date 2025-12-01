<?php
session_start();
$ref = isset($_GET['ref']) && is_string($_GET['ref']) ? trim($_GET['ref']) : '';
if ($ref !== '') {
    $_SESSION['ref'] = $ref;
    setcookie('ref', $ref, time()+7*24*3600, '/');
    header('Location: auth/register.php?ref=' . urlencode($ref));
    exit;
}
header('Location: auth/register.php');
exit;

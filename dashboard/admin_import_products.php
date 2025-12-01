<?php
session_start();
require '../vendor/autoload.php';
include '../includes/db_connect.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

// Access control
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'superadmin'])) {
    header("Location: ../auth/login.php");
    exit();
}

// Directories
$logDir = __DIR__ . '/../logs/';
if (!is_dir($logDir)) mkdir($logDir, 0775, true);
$successLogFile = $logDir . 'import_success.log';
$errorLogFile = $logDir . 'import_errors.log';

function log_to_file($file, $msg) {
    file_put_contents($file, '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL, FILE_APPEND);
}

// Run import
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excel_file'])) {
    $file = $_FILES['excel_file']['tmp_name'];
    $success = $errors = [];

    try {
        $spreadsheet = IOFactory::load($file);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, true);

        if (empty($rows)) throw new Exception("Empty file");

        // Extract headers
        $headerRow = array_shift($rows);
        $columns = [];
        foreach ($headerRow as $key => $label) {
            // Skip Meta columns
            if (stripos($label, 'Meta: _yoast') !== false || stripos($label, 'Meta: _aioseo') !== false) continue;
            // Normalize field
            $safeLabel = preg_replace('/[^a-zA-Z0-9_]/', '_', strtolower(trim($label)));
            $columns[$key] = $safeLabel;
        }

        if (empty($columns)) throw new Exception("No usable columns found.");

        $colNames = array_values($columns);
        $placeholders = implode(',', array_fill(0, count($colNames), '?'));
        $updateClause = implode(',', array_map(fn($col) => "$col = VALUES($col)", $colNames));

        $sql = "INSERT INTO products (" . implode(',', $colNames) . ") VALUES ($placeholders) ON DUPLICATE KEY UPDATE $updateClause";
        $stmt = $conn->prepare($sql);
        if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);

        foreach ($rows as $i => $row) {
            $values = [];
            foreach ($columns as $colKey => $colName) {
                $val = trim((string)($row[$colKey] ?? ''));
                if (in_array(strtolower($val), ['yes', 'true'])) $val = 1;
                elseif (in_array(strtolower($val), ['no', 'false'])) $val = 0;
                elseif (preg_match('/^\d{1,2}\/\d{1,2}\/\d{2,4}$/', $val)) $val = date('Y-m-d', strtotime($val));
                $values[] = $val;
            }

            $types = str_repeat('s', count($values));
            $stmt->bind_param($types, ...$values);

            if (!$stmt->execute()) {
                $msg = "❌ Row " . ($i + 2) . " failed: " . $stmt->error;
                $errors[] = $msg;
                log_to_file($errorLogFile, $msg);
            } else {
                $msg = "✅ Row " . ($i + 2) . " imported.";
                $success[] = $msg;
                log_to_file($successLogFile, $msg);
            }
        }

        $_SESSION['success'] = "Import done. ✅: " . count($success) . " ❌: " . count($errors);
        header("Location: admin_products.php");
        exit();

    } catch (Exception $e) {
        $err = "Fatal error: " . $e->getMessage();
        $errors[] = $err;
        log_to_file($errorLogFile, $err);
    }
}

$page_content = __DIR__ . "/admin_import_products_content.php";
include 'dashboard_layout.php';

<?php
declare(strict_types=1);
session_start();

header('Content-Type: application/json; charset=utf-8');

// Includes — adjust paths if needed
include '../includes/db_connect.php';
include '../includes/config.php';
include '../includes/zoho_functions.php';

// ---- Security: set BACKFILL_SECRET in config.php ----
//   e.g. in includes/config.php add:  define('BACKFILL_SECRET','LONG_RANDOM_SECRET');
$SECRET = defined('BACKFILL_SECRET') ? BACKFILL_SECRET : 'CHANGE_ME_LONG_RANDOM_SECRET';
$key    = $_GET['key'] ?? $_POST['key'] ?? '';
if (!hash_equals($SECRET, $key)) {
  http_response_code(403);
  echo json_encode(['ok' => false, 'error' => 'Forbidden (bad key)']);
  exit;
}

// Controls
$dryRun = isset($_REQUEST['dry_run']) && $_REQUEST['dry_run'] === '1';
$limit  = isset($_REQUEST['limit']) ? max(1, (int)$_REQUEST['limit']) : null;

// Sanity checks
if (!function_exists('getZohoAccessToken') || !function_exists('getOrCreateZohoContactIdForUser')) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'Missing Zoho helper functions.']);
  exit;
}

try {
  global $conn;

  $token = getZohoAccessToken();
  if (!$token) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Failed to obtain Zoho access token']);
    exit;
  }

  // We will use owner_id as the listing user/agent for vendor mapping
  $sql = "
    SELECT id, property_code, zoho_product_id, owner_id
    FROM properties
    WHERE zoho_product_id IS NOT NULL
      AND zoho_product_id <> ''
    ORDER BY id ASC
  ";
  if ($limit !== null) $sql .= " LIMIT " . (int)$limit;

  $rs = $conn->query($sql);
  if (!$rs) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'DB query failed: '.$conn->error]);
    exit;
  }

  $headers = [
    "Authorization: Zoho-oauthtoken $token",
    "Content-Type: application/json"
  ];

  $log_prefix = date('Y-m-d H:i:s') . " [Backfill Web] ";
  $attempted = 0; $updated = 0; $skipped = 0;

  while ($row = $rs->fetch_assoc()) {
    $attempted++;
    $productId  = $row['zoho_product_id'];
    $propertyCd = $row['property_code'];
    $ownerId    = (int)$row['owner_id'];

    if (!$productId || !$ownerId) {
      $skipped++;
      error_log($log_prefix . "Skip: {$propertyCd} (missing productId/ownerId)\n", 3, __DIR__ . '/../logs/zoho_debug.log');
      continue;
    }

    // Resolve or create the Contact for the owner
    $contactId = getOrCreateZohoContactIdForUser($ownerId);
    if (!$contactId) {
      $skipped++;
      error_log($log_prefix . "Skip: unable to resolve contact for {$propertyCd} (owner_id={$ownerId})\n", 3, __DIR__ . '/../logs/zoho_debug.log');
      continue;
    }

    if ($dryRun) {
      $updated++;
      error_log($log_prefix . "DRY-RUN: would set Product {$propertyCd} → Vendor_Contact_Name = {$contactId}\n", 3, __DIR__ . '/../logs/zoho_debug.log');
      continue;
    }

    // Update Product -> Vendor_Contact_Name (Contact lookup)
    $payload = ["data" => [[ "id" => $productId, "Vendor_Contact_Name" => ["id" => $contactId] ]]];

    $ch = curl_init("https://www.zohoapis.com/crm/v2/Products");
    curl_setopt_array($ch, [
      CURLOPT_CUSTOMREQUEST => "PUT",
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_HTTPHEADER => $headers,
      CURLOPT_POSTFIELDS => json_encode($payload),
      CURLOPT_SSL_VERIFYPEER => false
    ]);
    $res  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    error_log($log_prefix . "Update {$propertyCd} (ProductID: {$productId}) → Contact {$contactId} | HTTP {$code} | {$res}\n", 3, __DIR__ . '/../logs/zoho_debug.log');

    if ($code >= 200 && $code < 300) $updated++; else $skipped++;
  }

  echo json_encode([
    'ok' => true,
    'dry_run' => $dryRun,
    'limit' => $limit,
    'listing_column_used' => 'owner_id',
    'attempted' => $attempted,
    'updated' => $updated,
    'skipped' => $skipped
  ], JSON_PRETTY_PRINT);

} catch (Throwable $e) {
  error_log(date('Y-m-d H:i:s') . " [Backfill Web] Exception: " . $e->getMessage() . "\n", 3, __DIR__ . '/../logs/zoho_debug.log');
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}

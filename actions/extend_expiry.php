<?php
// /public_html/actions/extend_expiry.php

declare(strict_types=1);

require __DIR__ . '/../api/bootstrap.php';
require __DIR__ . '/../dotenv.php';

$APP_NAME      = 'PISHONSERV';
$LINK_MAX_AGE  = 14 * 24 * 60 * 60; // 14 days
$EXTEND_SECRET = getenv('EXTEND_SECRET') ?: 'CHANGE_THIS_TO_A_LONG_RANDOM_SECRET';

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

function respond_html(bool $ok, string $title, string $message, ?string $ctaHref = null, ?string $ctaText = null): never {
  $color = $ok ? '#16a34a' : '#dc2626';
  $ctaBtn = '';
  if ($ctaHref && $ctaText) {
    $ctaBtn = "<p style='margin:16px 0 0;'><a href='".h($ctaHref)."' style='display:inline-block;background:#2563eb;color:#fff;text-decoration:none;padding:10px 14px;border-radius:8px;font-weight:600'>".h($ctaText)."</a></p>";
  }
  header('Content-Type: text/html; charset=utf-8');
  echo "<!doctype html><html><head><meta charset='utf-8'><meta name='viewport' content='width=device-width,initial-scale=1'><title>".h($title)."</title></head>
  <body style='font-family:Inter,Arial,Helvetica,sans-serif;background:#f6f8fc;padding:24px'>
    <div style='max-width:640px;margin:auto;background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:24px'>
      <h2 style='margin:0 0 8px;color:{$color};font-size:20px'>".h($title)."</h2>
      <p style='color:#334155;font-size:14px;margin:0'>{$message}</p>
      {$ctaBtn}
    </div>
  </body></html>";
  exit;
}

// ---------- Read & validate query params ----------
$id    = isset($_GET['id'])    ? (int) $_GET['id'] : 0;
$owner = isset($_GET['owner']) ? (int) $_GET['owner'] : 0;
$exp   = $_GET['exp'] ?? '';
$ts    = isset($_GET['ts'])    ? (int) $_GET['ts'] : 0;
$sig   = $_GET['sig'] ?? '';

if ($id <= 0 || $owner <= 0 || !$exp || !$ts || !$sig) {
  respond_html(false, 'Invalid Link', 'This link appears to be incomplete or malformed.');
}
if (abs(time() - $ts) > $LINK_MAX_AGE) {
  respond_html(false, 'Link Expired', 'This link has expired. Please request a new extension link.');
}

// Verify signature
$payload  = $id . '.' . $owner . '.' . $exp . '.' . $ts;
$expected = rtrim(strtr(base64_encode(hash_hmac('sha256', $payload, $EXTEND_SECRET, true)), '+/', '-_'), '=');
if (!hash_equals($expected, $sig)) {
  respond_html(false, 'Security Check Failed', 'The link signature is invalid.');
}

$pdo = db();

// ---------- Fetch property ----------
$stmt = $pdo->prepare("
  SELECT id, owner_id, title, expiry_date, listing_type, status
  FROM properties
  WHERE id = :id
  LIMIT 1
");
$stmt->execute([':id' => $id]);
$prop = $stmt->fetch();

if (!$prop) {
  respond_html(false, 'Not Found', 'This property no longer exists or has already been archived.');
}
if ((int)$prop['owner_id'] !== $owner) {
  respond_html(false, 'Owner Mismatch', 'This link does not match the property owner.');
}
if (!in_array($prop['listing_type'], ['for_sale','for_rent'], true)) {
  respond_html(false, 'Not Extendable', 'This listing type cannot be extended.');
}
if (in_array($prop['status'], ['sold','rented'], true)) {
  respond_html(false, 'Already Finalized', 'This listing has been finalized and cannot be extended.');
}

// ---------- Compute new expiry ----------
$baseTs       = max(strtotime('today'), strtotime($prop['expiry_date'] ?: 'today'));
$newTs        = strtotime('+30 days', $baseTs);
$newExpiry    = date('Y-m-d', $newTs);
$currentExpiry = $prop['expiry_date'] ?: date('Y-m-d');

// ---------- Already extended? (no-op) ----------
if (strtotime($currentExpiry) >= strtotime($newExpiry)) {
  // Optional: log a no-op attempt, or just show message
  respond_html(true, 'Already Extended',
    'This listing <strong>'.h((string)$prop['title']).'</strong> (ID: '.$prop['id'].') is already extended until <strong>'.h($currentExpiry).'</strong>.',
    '/', 'Go to Homepage'
  );
}

// ---------- Update + Log "extended" ----------
$pdo->beginTransaction();
try {
  $upd = $pdo->prepare("UPDATE properties SET expiry_date = :e WHERE id = :id");
  $upd->execute([':e' => $newExpiry, ':id' => $id]);

  // Log the extension against the **old** expiry_date (the one we extended from)
  // days_left = 0 for non-reminder events
  $log = $pdo->prepare("
    INSERT IGNORE INTO expiry_notifications (property_id, notice_date, type, days_left)
    VALUES (:pid, :nd, 'extended', 0)
  ");
  $log->execute([':pid' => $id, ':nd' => $currentExpiry]);

  $pdo->commit();
} catch (Throwable $e) {
  $pdo->rollBack();
  respond_html(false, 'Update Failed', 'We could not extend the expiry due to a server error. Please try again later.');
}

respond_html(true, 'Expiry Extended',
  'Your listing <strong>'.h((string)$prop['title']).'</strong> (ID: '.$prop['id'].') has been extended to <strong>'.h($newExpiry).'</strong>.',
  '/', 'Go to Homepage'
);

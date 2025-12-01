<?php
// /public_html/cron/expiry_cron.php
// Run daily via cron: php /public_html/cron/expiry_cron.php

require __DIR__ . '/../api/bootstrap.php';     // uses your PDO db() helper
require __DIR__ . '/../dotenv.php';            // loads .env vars into getenv()
require __DIR__ . '/../vendor/autoload.php';   // PHPMailer

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/** =========================
  SETTINGS (from .env where possible)
========================== */
$APP_URL       = 'https://pishonserv.com';
$BRAND_NAME    = 'PISHONSERV';
$LOGO_URL      = 'https://pishonserv.com/public/images/logo.png';

// Secrets from .env
$EXTEND_SECRET = getenv('EXTEND_SECRET') ?: 'CHANGE_THIS_TO_A_LONG_RANDOM_SECRET';
$SMTP_PASS     = getenv('SMTP_PASS') ?: ''; // <-- must be set in .env

// Gmail SMTP (your existing settings)
$SMTP_HOST   = 'smtp.gmail.com';
$SMTP_USER   = 'pishonserv@gmail.com';
$SMTP_PORT   = 465;
$SMTP_SECURE = PHPMailer::ENCRYPTION_SMTPS;
$FROM_EMAIL  = 'pishonserv@gmail.com';
$FROM_NAME   = 'PISHONSERV';

// --- Mail helper ---
function send_mail($toEmail, $toName, $subject, $html, $alt) use (
  $SMTP_HOST, $SMTP_USER, $SMTP_PASS, $SMTP_PORT, $SMTP_SECURE, $FROM_EMAIL, $FROM_NAME
) {
  $mail = new PHPMailer(true);
  try {
    $mail->isSMTP();
    $mail->Host       = $SMTP_HOST;
    $mail->SMTPAuth   = true;
    $mail->Username   = $SMTP_USER;
    $mail->Password   = $SMTP_PASS;
    $mail->SMTPSecure = $SMTP_SECURE;
    $mail->Port       = $SMTP_PORT;

    $mail->setFrom($FROM_EMAIL, $FROM_NAME);
    $mail->addAddress($toEmail, $toName ?: $toEmail);

    $mail->isHTML(true);
    $mail->Subject = $subject;
    $mail->Body    = $html;
    $mail->AltBody = $alt;

    $mail->SMTPDebug = 0;
    $mail->send();
    return true;
  } catch (Exception $e) {
    error_log("📧 send_mail error to {$toEmail}: " . $mail->ErrorInfo);
    return false;
  }
}

// Signed one-click extend link
function build_extend_url($propertyId, $ownerId, $currentExpiry, $secret, $appUrl) {
  $ts = time();
  $payload = $propertyId . '.' . $ownerId . '.' . $currentExpiry . '.' . $ts;
  $sig = rtrim(strtr(base64_encode(hash_hmac('sha256', $payload, $secret, true)), '+/', '-_'), '=');
  return $appUrl . '/actions/extend_expiry.php'
       . '?id=' . urlencode($propertyId)
       . '&owner=' . urlencode($ownerId)
       . '&exp=' . urlencode($currentExpiry)
       . '&ts=' . $ts
       . '&sig=' . $sig;
}

// Email renderer (T-1 gets special subject + red warning)
function render_email($logoUrl, $brand, $ownerName, $title, $propertyId, $expiryDate, $daysLeft, $ctaUrl) {
  $greeting = $ownerName ? "Hi {$ownerName}," : "Hello,";
  $daysText = $daysLeft === 1 ? "1 day" : "{$daysLeft} days";
  $isFinal  = ($daysLeft === 1);

  $subject  = $isFinal
    ? "{$brand} – FINAL Reminder: Listing expires tomorrow (ID: {$propertyId})"
    : "{$brand} – Listing expires in {$daysText} (ID: {$propertyId})";

  $finalLine = $isFinal
    ? "<p style='margin:0 0 8px;color:#dc2626;font-size:13px;font-weight:700'>⚠️ Final reminder: this listing will be archived tomorrow if no action is taken.</p>"
    : "";

  $body = "
  <div style='font-family:Inter,Arial,Helvetica,sans-serif;max-width:640px;margin:auto;background:#f6f8fc;padding:24px'>
    <div style='text-align:center;margin-bottom:16px'>
      <img src='{$logoUrl}' alt='{$brand}' style='width:140px;height:auto'>
    </div>
    <div style='background:#ffffff;border:1px solid #e5e7eb;border-radius:12px;padding:24px'>
      <h2 style='margin:0 0 8px;color:#0f172a;font-weight:700;font-size:20px'>
        ".($isFinal ? "Final reminder: Listing expires tomorrow" : "Action required: Listing expires in {$daysText}")."
      </h2>
      <p style='margin:0 0 12px;color:#334155;font-size:14px'>{$greeting}</p>
      <p style='margin:0 0 12px;color:#334155;font-size:14px'>
        Your property listing <strong>".htmlspecialchars($title)."</strong> (ID: <strong>{$propertyId}</strong>) is set to expire on
        <strong>{$expiryDate}</strong>.
      </p>
      <p style='margin:0 0 16px;color:#334155;font-size:14px'>
        If this listing is still available, please extend it by <strong>30 days</strong> using the secure link below:
      </p>
      <p style='margin:16px 0 20px;text-align:center'>
        <a href='{$ctaUrl}' style='display:inline-block;background:#2563eb;color:#fff;text-decoration:none;padding:12px 18px;border-radius:8px;font-weight:600'>
          Extend Expiry by 30 Days
        </a>
      </p>
      {$finalLine}
      <p style='margin:0 0 8px;color:#475569;font-size:13px'>
        If no action is taken, this listing will be archived after the expiry date. You can restore it later by contacting support.
      </p>
      <hr style='border:none;border-top:1px solid #e5e7eb;margin:20px 0'>
      <p style='margin:0;color:#94a3b8;font-size:12px'>This is an automated reminder from {$brand}. Please do not reply to this email.</p>
    </div>
  </div>";

  $alt = "{$brand} – ".($isFinal ? "FINAL Reminder: Listing expires tomorrow" : "Listing expires in {$daysText}")."\n"
       . "Property: {$title} (ID: {$propertyId})\n"
       . "Expiry: {$expiryDate}\n\n"
       . "Extend +30 days: {$ctaUrl}\n\n"
       . "If you take no action, the listing will be archived after the expiry date.";

  return [$subject, $body, $alt];
}

$pdo = db();

/** =======================
  A) Daily reminders T-7 … T-1
========================= */
for ($daysLeft = 7; $daysLeft >= 1; $daysLeft--) {
  $targetDate = (new DateTimeImmutable('today'))->modify("+{$daysLeft} days")->format('Y-m-d');

  $stmt = $pdo->prepare("
    SELECT p.id, p.title, p.expiry_date, p.listing_type, p.status,
           u.id AS owner_id, u.name AS owner_name, u.email AS owner_email
    FROM properties p
    JOIN users u ON u.id = p.owner_id
    WHERE p.expiry_date = :target
      AND p.listing_type IN ('for_sale','for_rent')
      AND p.status IN ('available','pending','reserved')
  ");
  $stmt->execute([':target' => $targetDate]);
  $rows = $stmt->fetchAll();

  foreach ($rows as $row) {
    // prevent duplicate email for the same property/day
    $ck = $pdo->prepare("
      SELECT 1 FROM expiry_notifications
      WHERE property_id=:pid AND notice_date=:nd AND type='reminder' AND days_left=:dl
      LIMIT 1
    ");
    $ck->execute([':pid'=>$row['id'], ':nd'=>$row['expiry_date'], ':dl'=>$daysLeft]);
    if ($ck->fetchColumn()) continue;

    $extendUrl = build_extend_url($row['id'], $row['owner_id'], $row['expiry_date'], $EXTEND_SECRET, $APP_URL);

    [$subject, $html, $alt] = render_email(
      $LOGO_URL,
      $BRAND_NAME,
      $row['owner_name'],
      $row['title'],
      (int)$row['id'],
      $row['expiry_date'],
      $daysLeft,
      $extendUrl
    );

    if (!empty($row['owner_email'])) {
      $ok = send_mail($row['owner_email'], $row['owner_name'], $subject, $html, $alt);
      if ($ok) {
        $ins = $pdo->prepare("
          INSERT INTO expiry_notifications (property_id, notice_date, type, days_left)
          VALUES (:pid,:nd,'reminder',:dl)
        ");
        $ins->execute([':pid'=>$row['id'], ':nd'=>$row['expiry_date'], ':dl'=>$daysLeft]);
      }
    }
  }
}

/** =======================
  B) Archive items past expiry
========================= */
$today = (new DateTimeImmutable('today'))->format('Y-m-d');

$stmt2 = $pdo->prepare("
  SELECT p.*
  FROM properties p
  WHERE p.expiry_date IS NOT NULL
    AND p.expiry_date < :today
    AND p.listing_type IN ('for_sale','for_rent')
    AND p.status NOT IN ('sold','rented')
");
$stmt2->execute([':today' => $today]);
$expired = $stmt2->fetchAll();

foreach ($expired as $p) {
  $pdo->beginTransaction();
  try {
    $ins = $pdo->prepare("
      INSERT INTO archived_properties
      (id, title, price, location, type, status, description, owner_id, created_at, images, admin_approved,
       listing_type, bedrooms, bathrooms, size, garage, zoho_deal_id, zoho_product_id, latitude, longitude, expiry_date, archived_at)
      SELECT id, title, price, location, type, status, description, owner_id, created_at, images, admin_approved,
             listing_type, bedrooms, bathrooms, size, garage, zoho_deal_id, zoho_product_id, latitude, longitude, expiry_date, NOW()
      FROM properties WHERE id = :id
    ");
    $ins->execute([':id' => $p['id']]);

    $del = $pdo->prepare("DELETE FROM properties WHERE id = :id");
    $del->execute([':id' => $p['id']]);

    $pdo->prepare("
      INSERT IGNORE INTO expiry_notifications (property_id, notice_date, type, days_left)
      VALUES (:pid, :nd, 'expired', 0)
    ")->execute([':pid'=>$p['id'], ':nd'=>$p['expiry_date']]);

    $pdo->commit();
  } catch (Throwable $e) {
    $pdo->rollBack();
    error_log("Archive failed for property {$p['id']}: " . $e->getMessage());
  }
}

echo "OK " . date('Y-m-d H:i:s') . PHP_EOL;

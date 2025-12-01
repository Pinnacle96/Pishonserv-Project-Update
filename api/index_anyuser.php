<?php
require __DIR__ . '/bootstrap.php';
$cfg = require __DIR__ . '/config.php';

allow_cors($cfg['allowed_origins']);

$method = $_SERVER['REQUEST_METHOD'];
$uri    = strtok($_SERVER['REQUEST_URI'], '?');
$base   = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');   // e.g. /api
$path   = '/' . ltrim(substr($uri, strlen($base)), '/');  // e.g. /v1/availability

if ($method === 'OPTIONS') exit(0);

function parts($path) { return array_values(array_filter(explode('/', $path))); }
$p = parts($path);

try {
  if (count($p) >= 2 && $p[0] === 'v1') {

    // ============ GET /v1/properties/{id}
    if ($method === 'GET' && $p[1] === 'properties' && !empty($p[2])) {
      $user = require_auth_roles(['superadmin','admin','agent','system','automation']);
      $id = (int)$p[2];
      $pdo = db();
      $stmt = $pdo->prepare("
        SELECT id, property_code, title, price, location, type, status, description,
               owner_id, created_at, images, admin_approved, listing_type, bedrooms,
               bathrooms, size, garage, furnishing_status, property_condition, amenities,
               maintenance_fee, agent_fee, caution_fee, price_frequency, minimum_stay,
               checkin_time, checkout_time, room_type, star_rating, policies, zoho_deal_id,
               zoho_product_id, latitude, longitude, expiry_date
        FROM properties WHERE id = :id LIMIT 1
      ");
      $stmt->execute([':id' => $id]);
      $row = $stmt->fetch();
      if (!$row) json_out(['error'=>'Not found'], 404);
      json_out(['data'=>$row]);
    }

    // ============ POST /v1/availability
    if ($method === 'POST' && $p[1] === 'availability') {
      $user = require_auth_roles(['superadmin','admin','agent','system','automation']);
      [, $body] = read_json();
      require_fields($body, ['property_id']);

      $propertyId = (int)$body['property_id'];
      $pdo = db();
      $stmt = $pdo->prepare("
        SELECT status, admin_approved, expiry_date, listing_type
        FROM properties WHERE id = :id
      ");
      $stmt->execute([':id' => $propertyId]);
      $row = $stmt->fetch();
      if (!$row) json_out(['available'=>false, 'reason'=>'not_found']);

      $expired = ($row['expiry_date'] && strtotime($row['expiry_date']) < strtotime('today'));
      $available = (!$expired
        && in_array($row['status'], ['available','pending'], true)
        && (int)$row['admin_approved'] === 1);

      json_out([
        'property_id'    => $propertyId,
        'available'      => $available,
        'status'         => $row['status'],
        'admin_approved' => (int)$row['admin_approved'],
        'expired'        => $expired,
        'listing_type'   => $row['listing_type']
      ]);
    }

    // ============ POST /v1/bookings
    if ($method === 'POST' && $p[1] === 'bookings') {
      $user = require_auth_roles(['superadmin','admin','agent','system','automation']);
      [, $body] = read_json();
      require_fields($body, ['user_id','property_id','check_in_date','check_out_date','amount']);

      $checkIn  = ymd($body['check_in_date']);
      $checkOut = ymd($body['check_out_date']);
      if (strtotime($checkOut) <= strtotime($checkIn)) {
        json_out(['error'=>'check_out_date must be after check_in_date'], 422);
      }
      $duration = (int) round((strtotime($checkOut) - strtotime($checkIn)) / 86400);

      $pdo = db();

      // Ensure property exists
      $pstmt = $pdo->prepare("SELECT id, status FROM properties WHERE id=:id");
      $pstmt->execute([':id' => (int)$body['property_id']]);
      if (!$pstmt->fetch()) json_out(['error'=>'Property not found'], 404);

      // Create booking
      $stmt = $pdo->prepare("
        INSERT INTO bookings
          (user_id, property_id, status, zoho_deal_id, check_in_date, check_out_date, duration, amount, payment_status, created_at)
        VALUES
          (:user_id, :property_id, 'pending', :zoho, :cin, :cout, :dur, :amt, 'pending', NOW())
      ");
      $stmt->execute([
        ':user_id'     => (int)$body['user_id'],
        ':property_id' => (int)$body['property_id'],
        ':zoho'        => $body['zoho_deal_id'] ?? null,
        ':cin'         => $checkIn,
        ':cout'        => $checkOut,
        ':dur'         => $duration,
        ':amt'         => decimal($body['amount']),
      ]);
      $bookingId = (int) db()->lastInsertId();

      // Reserve property when creating booking (optional but common)
      $pdo->prepare("UPDATE properties SET status='reserved' WHERE id=:id AND status IN ('available','pending')")
          ->execute([':id' => (int)$body['property_id']]);

      json_out(['ok'=>true,'booking_id'=>$bookingId,'status'=>'pending']);
    }
  }

  json_out(['error'=>'Route not found'], 404);
} catch (Throwable $e) {
  log_api('error','exception',['msg'=>$e->getMessage()]);
  json_out(['error'=>'Server error'], 500);
}

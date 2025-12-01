<?php
require __DIR__ . '/bootstrap.php';
$cfg = require __DIR__ . '/config.php';

allow_cors($cfg['allowed_origins'] ?? []);

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
      $user = require_auth(); // superadmin-only
      $id = (int)$p[2];
      log_api('info','properties_get_request',[ 'id'=>$id ]);
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
      if (!$row) { log_api('error','properties_get_not_found',[ 'id'=>$id ]); json_out(['error'=>'Not found'], 404); }
      log_api('info','properties_get_success',[ 'id'=>$id ]);
      json_out(['data'=>$row]);
    }

    // ============ POST /v1/availability
    if ($method === 'POST' && $p[1] === 'availability') {
      $user = require_auth(); // superadmin-only
      [, $body] = read_json();
      require_fields($body, ['property_id']);

      $propertyId = (int)$body['property_id'];
      log_api('info','availability_check_request',[ 'property_id'=>$propertyId ]);
      $pdo = db();
      $stmt = $pdo->prepare("
        SELECT status, admin_approved, expiry_date, listing_type
        FROM properties WHERE id = :id
      ");
      $stmt->execute([':id' => $propertyId]);
      $row = $stmt->fetch();
      if (!$row) { log_api('error','availability_check_not_found',[ 'property_id'=>$propertyId ]); json_out(['available'=>false, 'reason'=>'not_found']); }

      $expired = ($row['expiry_date'] && strtotime($row['expiry_date']) < strtotime('today'));
      $available = (
        !$expired
        && in_array($row['status'], ['available','pending'], true)
        && (int)$row['admin_approved'] === 1
      );
      log_api('info','availability_check_result',[ 'property_id'=>$propertyId, 'available'=>$available, 'expired'=>$expired, 'status'=>$row['status'], 'admin_approved'=>(int)$row['admin_approved'] ]);

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
      $user = require_auth(); // superadmin-only
      [, $body] = read_json();
      require_fields($body, ['user_id','property_id','check_in_date','check_out_date','amount']);
      log_api('info','booking_create_request',[ 'user_id'=>(int)$body['user_id'], 'property_id'=>(int)$body['property_id'], 'check_in_date'=>$body['check_in_date'], 'check_out_date'=>$body['check_out_date'], 'amount'=>$body['amount'] ]);

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
      if (!$pstmt->fetch()) { log_api('error','booking_property_not_found',[ 'property_id'=>(int)$body['property_id'] ]); json_out(['error'=>'Property not found'], 404); }

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
      log_api('info','booking_created',[ 'booking_id'=>$bookingId, 'property_id'=>(int)$body['property_id'], 'user_id'=>(int)$body['user_id'] ]);

      // Reserve property when creating booking (optional but common)
      $pdo->prepare("UPDATE properties SET status='reserved' WHERE id=:id AND status IN ('available','pending')")
          ->execute([':id' => (int)$body['property_id']]);
      log_api('info','property_reserved',[ 'property_id'=>(int)$body['property_id'] ]);

      json_out(['ok'=>true,'booking_id'=>$bookingId,'status'=>'pending']);
    }

    if ($method === 'PATCH' && $p[1] === 'bookings' && !empty($p[2])) {
      $user = require_auth();
      [, $body] = read_json();
      $id = (int)$p[2];
      $fields = [];
      $vals = [];
      if (isset($body['status']) && $body['status'] !== '') { $fields[] = 'status = :st'; $vals[':st'] = $body['status']; }
      if (isset($body['payment_status']) && $body['payment_status'] !== '') { $fields[] = 'payment_status = :ps'; $vals[':ps'] = $body['payment_status']; }
      if (isset($body['amount']) && $body['amount'] !== '') { $fields[] = 'amount = :amt'; $vals[':amt'] = decimal($body['amount']); }
      if (!$fields) json_out(['error'=>'No updatable fields'], 422);
      log_api('info','booking_update_request',[ 'booking_id'=>$id, 'fields'=>array_keys($vals) ]);
      $sql = 'UPDATE bookings SET ' . implode(', ', $fields) . ' WHERE id = :id';
      $pdo = db();
      $stmt = $pdo->prepare($sql);
      $vals[':id'] = $id;
      $stmt->execute($vals);
      log_api('info','booking_updated',[ 'booking_id'=>$id ]);
      json_out(['ok'=>true]);
    }

    if ($method === 'POST' && $p[1] === 'payments') {
      $user = require_auth();
      [, $body] = read_json();
      require_fields($body, ['user_id','property_id','amount','payment_gateway','transaction_id']);
      log_api('info','payments_create_request',[ 'user_id'=>(int)$body['user_id'], 'property_id'=>(int)$body['property_id'], 'amount'=>$body['amount'], 'gateway'=>$body['payment_gateway'], 'tx'=>$body['transaction_id'] ]);
      $pdo = db();
      $stmt = $pdo->prepare("INSERT INTO payments (user_id, property_id, booking_id, amount, payment_purpose, payment_gateway, transaction_id, status, created_at) VALUES (:uid, :pid, :bid, :amt, :pp, :gw, :tx, 'pending', NOW())");
      $stmt->execute([
        ':uid' => (int)$body['user_id'],
        ':pid' => (int)$body['property_id'],
        ':bid' => isset($body['booking_id']) ? (int)$body['booking_id'] : null,
        ':amt' => decimal($body['amount']),
        ':pp'  => $body['payment_purpose'] ?? null,
        ':gw'  => $body['payment_gateway'],
        ':tx'  => $body['transaction_id'],
      ]);
      $id = (int) db()->lastInsertId();
      log_api('info','payment_record_created',[ 'payment_id'=>$id ]);
      json_out(['ok'=>true,'payment_id'=>$id]);
    }
    if ($method === 'GET' && $p[1] === 'payments' && empty($p[2])) {
      $user = require_auth();
      $pdo = db();
      $q = [];
      $w = [];
      if (isset($_GET['status']) && $_GET['status'] !== '') { $q[] = 'status = :st'; $w[':st'] = $_GET['status']; }
      if (isset($_GET['user_id']) && $_GET['user_id'] !== '') { $q[] = 'user_id = :uid'; $w[':uid'] = (int)$_GET['user_id']; }
      if (isset($_GET['property_id']) && $_GET['property_id'] !== '') { $q[] = 'property_id = :pid'; $w[':pid'] = (int)$_GET['property_id']; }
      if (isset($_GET['payment_gateway']) && $_GET['payment_gateway'] !== '') { $q[] = 'payment_gateway = :gw'; $w[':gw'] = $_GET['payment_gateway']; }
      if (isset($_GET['transaction_id']) && $_GET['transaction_id'] !== '') { $q[] = 'transaction_id = :tx'; $w[':tx'] = $_GET['transaction_id']; }
      $where = $q ? ('WHERE ' . implode(' AND ', $q)) : '';
      $limit = isset($_GET['limit']) ? max(1, min(100, (int)$_GET['limit'])) : 20;
      $offset = isset($_GET['offset']) ? max(0, (int)$_GET['offset']) : 0;
      log_api('info','payments_list_request',[ 'filters'=>$q, 'limit'=>$limit, 'offset'=>$offset ]);
      $sql = "SELECT id, user_id, property_id, booking_id, amount, payment_purpose, payment_gateway, transaction_id, status, created_at FROM payments $where ORDER BY created_at DESC LIMIT :lim OFFSET :off";
      $stmt = $pdo->prepare($sql);
      foreach ($w as $k=>$v) { $stmt->bindValue($k, $v); }
      $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
      $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
      $stmt->execute();
      $rows = $stmt->fetchAll();
      log_api('info','payments_list_response',[ 'count'=>count($rows) ]);
      json_out(['data'=>$rows,'limit'=>$limit,'offset'=>$offset]);
    }

    if ($method === 'GET' && $p[1] === 'users' && !empty($p[2])) {
      $user = require_auth();
      $pdo = db();
      $id = (int)$p[2];
      log_api('info','users_get_request',[ 'id'=>$id ]);
      $stmt = $pdo->prepare('SELECT id, role, email, name, phone, created_at FROM users WHERE id = :id LIMIT 1');
      $stmt->execute([':id'=>$id]);
      $u = $stmt->fetch();
      if (!$u) { log_api('error','users_get_not_found',[ 'id'=>$id ]); json_out(['error'=>'Not found'], 404); }
      log_api('info','users_get_success',[ 'id'=>$id ]);
      json_out(['data'=>$u]);
    }
    if ($method === 'GET' && $p[1] === 'users' && empty($p[2])) {
      $user = require_auth();
      $pdo = db();
      $q = [];
      $w = [];
      if (isset($_GET['role']) && $_GET['role'] !== '') { $q[] = 'role = :r'; $w[':r'] = $_GET['role']; }
      if (isset($_GET['email']) && $_GET['email'] !== '') { $q[] = 'email LIKE :em'; $w[':em'] = '%' . $_GET['email'] . '%'; }
      if (isset($_GET['name']) && $_GET['name'] !== '') { $q[] = 'name LIKE :nm'; $w[':nm'] = '%' . $_GET['name'] . '%'; }
      $where = $q ? ('WHERE ' . implode(' AND ', $q)) : '';
      $limit = isset($_GET['limit']) ? max(1, min(100, (int)$_GET['limit'])) : 20;
      $offset = isset($_GET['offset']) ? max(0, (int)$_GET['offset']) : 0;
      log_api('info','users_list_request',[ 'filters'=>$q, 'limit'=>$limit, 'offset'=>$offset ]);
      $sql = "SELECT id, role, email, name, phone, created_at FROM users $where ORDER BY created_at DESC LIMIT :lim OFFSET :off";
      $stmt = $pdo->prepare($sql);
      foreach ($w as $k=>$v) { $stmt->bindValue($k, $v); }
      $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
      $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
      $stmt->execute();
      $rows = $stmt->fetchAll();
      log_api('info','users_list_response',[ 'count'=>count($rows) ]);
      json_out(['data'=>$rows,'limit'=>$limit,'offset'=>$offset]);
    }
  }

  json_out(['error'=>'Route not found'], 404);
} catch (Throwable $e) {
  log_api('error','exception',['msg'=>$e->getMessage()]);
  json_out(['error'=>'Server error'], 500);
}
    if ($method === 'GET' && $p[1] === 'properties' && empty($p[2])) {
      $user = require_auth();
      $pdo = db();
      $q = [];
      $w = [];
      if (isset($_GET['listing_type']) && $_GET['listing_type'] !== '') { $q[] = 'listing_type = :lt'; $w[':lt'] = $_GET['listing_type']; }
      if (isset($_GET['status']) && $_GET['status'] !== '') { $q[] = 'status = :st'; $w[':st'] = $_GET['status']; }
      if (isset($_GET['type']) && $_GET['type'] !== '') { $q[] = 'type = :ty'; $w[':ty'] = $_GET['type']; }
      if (isset($_GET['owner_id']) && $_GET['owner_id'] !== '') { $q[] = 'owner_id = :oid'; $w[':oid'] = (int)$_GET['owner_id']; }
      if (isset($_GET['location']) && $_GET['location'] !== '') { $q[] = 'location LIKE :loc'; $w[':loc'] = '%' . $_GET['location'] . '%'; }
      if (isset($_GET['bedrooms_min']) && $_GET['bedrooms_min'] !== '') { $q[] = 'bedrooms >= :bmin'; $w[':bmin'] = (int)$_GET['bedrooms_min']; }
      $where = $q ? ('WHERE ' . implode(' AND ', $q)) : '';
      $limit = isset($_GET['limit']) ? max(1, min(100, (int)$_GET['limit'])) : 20;
      $offset = isset($_GET['offset']) ? max(0, (int)$_GET['offset']) : 0;
      log_api('info','properties_list_request',[ 'filters'=>$q, 'limit'=>$limit, 'offset'=>$offset ]);
      $sql = "SELECT id, property_code, title, price, location, type, status, listing_type, admin_approved, bedrooms, bathrooms, size, garage, created_at FROM properties $where ORDER BY created_at DESC LIMIT :lim OFFSET :off";
      $stmt = $pdo->prepare($sql);
      foreach ($w as $k=>$v) { $stmt->bindValue($k, $v); }
      $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
      $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
      $stmt->execute();
      $rows = $stmt->fetchAll();
      log_api('info','properties_list_response',[ 'count'=>count($rows) ]);
      json_out(['data'=>$rows,'limit'=>$limit,'offset'=>$offset]);
    }
    if ($method === 'POST' && $p[1] === 'property' && !empty($p[2]) && $p[2] === 'similar') {
      $user = require_auth();
      [, $body] = read_json();
      $pdo = db();
      $q = [];
      $w = [];
      if (isset($body['location']) && $body['location'] !== '') { $q[] = 'location LIKE :loc'; $w[':loc'] = '%' . $body['location'] . '%'; }
      if (isset($body['type']) && $body['type'] !== '') { $q[] = 'type = :ty'; $w[':ty'] = $body['type']; }
      if (isset($body['bedrooms']) && $body['bedrooms'] !== '') { $q[] = 'bedrooms = :br'; $w[':br'] = (int)$body['bedrooms']; }
      if (isset($body['price_range']['min']) && $body['price_range']['min'] !== '') { $q[] = 'price >= :pmin'; $w[':pmin'] = decimal($body['price_range']['min']); }
      if (isset($body['price_range']['max']) && $body['price_range']['max'] !== '') { $q[] = 'price <= :pmax'; $w[':pmax'] = decimal($body['price_range']['max']); }
      $where = $q ? ('WHERE admin_approved=1 AND status IN (\'available\',\'pending\') AND ' . implode(' AND ', $q)) : 'WHERE admin_approved=1 AND status IN (\'available\',\'pending\')';
      log_api('info','property_similar_request',[ 'filters'=>$q ]);
      $sql = "SELECT id, property_code, title, price, location, type, bedrooms, images FROM properties $where ORDER BY created_at DESC LIMIT 20";
      $stmt = $pdo->prepare($sql);
      foreach ($w as $k=>$v) { $stmt->bindValue($k, $v); }
      $stmt->execute();
      $rows = $stmt->fetchAll();
      log_api('info','property_similar_response',[ 'count'=>count($rows) ]);
      json_out(['status'=>true,'similar'=>$rows]);
    }

    if (!function_exists('sanitize_text')) {
      function sanitize_text(string $s): string {
        $s = preg_replace('/\b[\w._%+-]+@[\w.-]+\.[A-Za-z]{2,}\b/', '[contact removed]', $s);
        $s = preg_replace('/\b(?:\+?\d{1,3}[\s-]?)?(?:\(\d{2,4}\)[\s-]?)?\d{3,4}[\s-]?\d{3,4}\b/', '[contact removed]', $s);
        return $s;
      }
    }

    if ($method === 'POST' && $p[1] === 'vendor' && !empty($p[2]) && $p[2] === 'request') {
      $user = require_auth();
      [, $body] = read_json();
      require_fields($body, ['property_id','customer_question','conversation_id']);
      $pdo = db();
      $pid = (int)$body['property_id'];
      $stmt = $pdo->prepare('SELECT owner_id, title FROM properties WHERE id = :id');
      $stmt->execute([':id'=>$pid]);
      $prop = $stmt->fetch();
      if (!$prop) json_out(['error'=>'Property not found'], 404);
      $ownerId = (int)$prop['owner_id'];
      $ust = $pdo->prepare('SELECT id, role, email, name, phone FROM users WHERE id = :id');
      $ust->execute([':id'=>$ownerId]);
      $vend = $ust->fetch();
      if (!$vend || !$vend['phone']) json_out(['error'=>'Vendor not found'], 404);
      $text = 'Pishonserv inquiry: ' . (string)$body['customer_question'] . ' [' . $prop['title'] . ']';
      $wr = whatsapp_send($vend['phone'], $text);
      vendor_map_set($vend['phone'], (string)$body['conversation_id'], $pid, 30);
      drive_append_csv_row((string)$body['conversation_id'], [date('c'),'backend','vendor_request','whatsapp','property_id=' . $pid,'sent','']);
      log_api('info','vendor_request',[
        'property_id'=>$pid,
        'property_title'=>$prop['title'],
        'vendor_id'=>$vend['id'],
        'vendor_email'=>$vend['email'],
        'vendor_phone'=>$vend['phone'],
        'http'=>$wr['http']
      ]);
      json_out(['status'=>true,'message'=>'vendor_notified']);
    }

    if ($method === 'POST' && $p[1] === 'vendor' && !empty($p[2]) && $p[2] === 'reply') {
      $user = require_auth();
      [, $body] = read_json();
      require_fields($body, ['vendor_id','conversation_id','raw_message']);
      $clean = sanitize_text((string)$body['raw_message']);
      log_api('info','vendor_reply',[
        'vendor_id'=>$body['vendor_id'],
        'conversation_id'=>$body['conversation_id'],
        'raw_message'=>$body['raw_message'],
        'sanitized'=>$clean
      ]);
      json_out(['status'=>true]);
    }

    if ($method === 'POST' && $p[1] === 'conversation' && !empty($p[2]) && $p[2] === 'create') {
      $user = require_auth();
      [, $body] = read_json();
      require_fields($body, ['conversation_id','customer_id','vendor_id','property_id','channel']);
      log_api('info','conversation_create',[
        'conversation_id'=>$body['conversation_id'],
        'customer_id'=>$body['customer_id'],
        'vendor_id'=>$body['vendor_id'],
        'property_id'=>$body['property_id'],
        'channel'=>$body['channel']
      ]);
      json_out(['status'=>true]);
    }

    if ($method === 'POST' && $p[1] === 'payment' && !empty($p[2]) && $p[2] === 'initiate') {
      $user = require_auth();
      [, $body] = read_json();
      require_fields($body, ['property_id','amount','customer_email']);
      $secret = (defined('PAYSTACK_SECRET_KEY') ? PAYSTACK_SECRET_KEY : (getenv('PAYSTACK_SECRET_KEY') ?: ''));
      if ($secret === '') json_out(['error'=>'PAYSTACK_SECRET_KEY not configured'], 500);
      $amountKobo = (int) round(((float)$body['amount']) * 100);
      $email = $body['customer_email'];
      $ref = 'PSK-' . uniqid();
      $meta = [
        'property_id' => (int)$body['property_id'],
        'conversation_id' => $body['conversation_id'] ?? null,
      ];
      $payload = json_encode([
        'email' => $email,
        'amount' => $amountKobo,
        'reference' => $ref,
        'metadata' => $meta,
        'currency' => 'NGN'
      ]);
      log_api('info','payment_initiate_request',[
        'email'=>$email,
        'amount_kobo'=>$amountKobo,
        'reference'=>$ref,
        'property_id'=>(int)$body['property_id']
      ]);
      $ch = curl_init('https://api.paystack.co/transaction/initialize');
      curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
          'Authorization: Bearer ' . $secret,
          'Content-Type: application/json'
        ],
        CURLOPT_POSTFIELDS => $payload,
      ]);
      $resp = curl_exec($ch);
      $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      curl_close($ch);
      $data = json_decode($resp, true);
      if ($code !== 200 || !$data['status']) {
        log_api('error','payment_initiate_failed',[ 'http_code'=>$code, 'response'=>$data ]);
        json_out(['error'=>'Failed to initialize payment','details'=>$data], 502);
      }
      log_api('info','payment_initiate_response',[
        'http_code'=>$code,
        'authorization_url'=>$data['data']['authorization_url'] ?? null,
        'reference'=>$ref
      ]);
      $pdo = db();
      $uid = null;
      if (!empty($body['user_id'])) { $uid = (int)$body['user_id']; }
      if ($uid === null) {
        $st = $pdo->prepare('SELECT id FROM users WHERE email = :e LIMIT 1');
        $st->execute([':e'=>$email]);
        $row = $st->fetch();
        if ($row) $uid = (int)$row['id'];
      }
      if ($uid === null) json_out(['error'=>'user_id required or resolvable by email'], 422);
      $stmt = $pdo->prepare("INSERT INTO payments (user_id, property_id, booking_id, amount, payment_purpose, payment_gateway, transaction_id, status, created_at) VALUES (:uid, :pid, :bid, :amt, :pp, 'paystack', :tx, 'pending', NOW())");
      $stmt->execute([
        ':uid'=>$uid,
        ':pid'=>(int)$body['property_id'],
        ':bid'=>isset($body['booking_id'])?(int)$body['booking_id']:null,
        ':amt'=>decimal($body['amount']),
        ':pp'=>$body['payment_purpose'] ?? null,
        ':tx'=>$ref,
      ]);
      json_out(['status'=>true,'payment_link'=>$data['data']['authorization_url'],'reference'=>$ref]);
    }

    if ($method === 'POST' && $p[1] === 'payment' && !empty($p[2]) && $p[2] === 'webhook') {
      $secret = (defined('PAYSTACK_SECRET_KEY') ? PAYSTACK_SECRET_KEY : (getenv('PAYSTACK_SECRET_KEY') ?: ''));
      if ($secret === '') json_out(['error'=>'PAYSTACK_SECRET_KEY not configured'], 500);
      $sig = $_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] ?? '';
      $raw = file_get_contents('php://input');
      if ($sig === '' || !hash_equals($sig, hash_hmac('sha512', $raw, $secret))) {
        log_api('error','payment_webhook_invalid_signature',[ 'got'=>$sig ? 'present' : 'none' ]);
        json_out(['error'=>'Invalid signature'], 401);
      }
      $evt = json_decode($raw, true);
      $ref = $evt['data']['reference'] ?? '';
      $status = ($evt['event'] ?? '') === 'charge.success' ? 'completed' : 'failed';
      log_api('info','payment_webhook_received',[ 'event'=>$evt['event'] ?? null, 'reference'=>$ref, 'status'=>$status ]);
      $pdo = db();
      $pdo->prepare('UPDATE payments SET status = :st WHERE transaction_id = :tx')
          ->execute([':st'=>$status, ':tx'=>$ref]);
      log_api('info','payment_status_updated',[ 'reference'=>$ref, 'status'=>$status ]);
      $conv = $evt['data']['metadata']['conversation_id'] ?? null;
      if ($conv) {
        drive_append_csv_row((string)$conv, [date('c'),'backend','payment_webhook','paystack','reference=' . $ref,$status,'']);
        botpress_emit('payment_success', ['conversation_id'=>$conv,'amount'=>(int)$evt['data']['amount']/100,'payment_reference'=>$ref,'status'=>'success']);
      }
      json_out(['ok'=>true]);
    }

    if ($method === 'POST' && $p[1] === 'crm' && !empty($p[2]) && $p[2] === 'log-interaction') {
      $user = require_auth();
      [, $body] = read_json();
      require_fields($body, ['conversation_id']);
      $conv = (string)$body['conversation_id'];
      $status = $body['status'] ?? '';
      $details = json_encode($body);
      drive_append_csv_row($conv, [date('c'),'backend','crm_log','drive',$details,$status,'']);
      json_out(['ok'=>true]);
    }

    if ($method === 'POST' && $p[1] === 'conversation' && !empty($p[2]) && $p[2] === 'create') {
      $user = require_auth();
      [, $body] = read_json();
      require_fields($body, ['conversation_id','property_id','customer_question']);
      $conv = (string)$body['conversation_id'];
      drive_append_csv_row($conv, [date('c'),'customer','conversation_create','botpress',(string)$body['customer_question'],'started','']);
      json_out(['ok'=>true]);
    }

    // ============ GET/POST /v1/whatsapp/incoming (Meta webhook)
    if ($method === 'GET' && $p[1] === 'whatsapp' && !empty($p[2]) && $p[2] === 'incoming') {
      $verify = ($cfg['whatsapp']['verify_token'] ?? '') ?: (defined('WHATSAPP_VERIFY_TOKEN') ? WHATSAPP_VERIFY_TOKEN : '');
      $mode = $_GET['hub_mode'] ?? $_GET['hub.mode'] ?? '';
      $token = $_GET['hub_verify_token'] ?? $_GET['hub.verify_token'] ?? '';
      $challenge = $_GET['hub_challenge'] ?? $_GET['hub.challenge'] ?? '';
      if ($mode === 'subscribe' && $token === $verify) { echo $challenge; exit; }
      json_out(['error'=>'Forbidden'], 403);
    }
    if ($method === 'POST' && $p[1] === 'whatsapp' && !empty($p[2]) && $p[2] === 'incoming') {
      $raw = file_get_contents('php://input');
      $secret = ($cfg['whatsapp']['app_secret'] ?? '') ?: (defined('WHATSAPP_APP_SECRET') ? WHATSAPP_APP_SECRET : '');
      $sig = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';
      if ($secret !== '') {
        $calc = 'sha256=' . hash_hmac('sha256', $raw, $secret);
        if ($sig === '' || !hash_equals($sig, $calc)) { log_api('error','whatsapp_incoming_bad_signature',[]); json_out(['error'=>'Invalid signature'], 401); }
      }
      $j = json_decode($raw, true);
      $msg = null; $from = null;
      if (isset($j['entry'][0]['changes'][0]['value']['messages'][0]['text']['body'])) {
        $msg = (string)$j['entry'][0]['changes'][0]['value']['messages'][0]['text']['body'];
      }
      if (isset($j['entry'][0]['changes'][0]['value']['messages'][0]['from'])) {
        $from = (string)$j['entry'][0]['changes'][0]['value']['messages'][0]['from'];
      } elseif (isset($j['entry'][0]['changes'][0]['value']['contacts'][0]['wa_id'])) {
        $from = (string)$j['entry'][0]['changes'][0]['value']['contacts'][0]['wa_id'];
      }
      if (!$msg || !$from) { log_api('error','whatsapp_incoming_invalid',[]); json_out(['error'=>'invalid'], 422); }
      $map = vendor_map_get($from);
      if (!$map) { log_api('error','whatsapp_incoming_unmapped',['from'=>$from]); json_out(['ok'=>true]); }
      $san = sanitize_vendor_message($msg);
      drive_write_text((string)$map['conversation_id'], 'vendor_raw_' . time() . '.txt', $msg);
      drive_write_text((string)$map['conversation_id'], 'vendor_clean_' . time() . '.txt', $san['clean']);
      drive_append_csv_row((string)$map['conversation_id'], [date('c'),'vendor','vendor_reply','whatsapp','property_id=' . $map['property_id'],'received','']);
      botpress_emit('vendor_reply', ['conversation_id'=>$map['conversation_id'],'reply_message'=>$san['clean']]);
      json_out(['ok'=>true]);
    }

    if ($method === 'POST' && $p[1] === 'vendor' && !empty($p[2]) && $p[2] === 'check-timeouts') {
      $user = require_auth();
      $expired = vendor_map_cleanup();
      foreach ($expired as $e) {
        $conv = (string)$e['conversation_id'];
        if ($conv !== '') {
          drive_append_csv_row($conv, [date('c'),'backend','vendor_timeout','system','property_id=' . (int)$e['property_id'],'timeout','']);
          botpress_emit('vendor_timeout', ['conversation_id'=>$conv]);
        }
      }
      json_out(['ok'=>true,'expired_count'=>count($expired)]);
    }

    if ($method === 'POST' && $p[1] === 'botpress' && !empty($p[2]) && $p[2] === 'vendor-reply') {
      $user = require_auth();
      [, $body] = read_json();
      require_fields($body, ['type','conversation_id','message']);
      $incoming = ($cfg['botpress_incoming_url'] ?? '') ?: (getenv('BOTPRESS_INCOMING_URL') ?: '');
      if ($incoming === '') json_out(['error'=>'BOTPRESS_INCOMING_URL not configured'], 500);
      $payload = json_encode($body);
      $ch = curl_init($incoming);
      curl_setopt_array($ch, [CURLOPT_POST=>true, CURLOPT_RETURNTRANSFER=>true, CURLOPT_POSTFIELDS=>$payload, CURLOPT_HTTPHEADER=>['Content-Type: application/json']]);
      $resp = curl_exec($ch);
      $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      curl_close($ch);
      if ($http >= 200 && $http < 300) {
        log_api('info','botpress_relay',[ 'endpoint'=>'vendor-reply', 'http_code'=>$http ]);
        json_out(['status'=>true]);
      }
      log_api('error','botpress_relay_failed',[ 'endpoint'=>'vendor-reply', 'http_code'=>$http, 'response'=>$resp ]);
      json_out(['error'=>'Botpress relay failed'], 502);
    }

    if ($method === 'POST' && $p[1] === 'botpress' && !empty($p[2]) && $p[2] === 'payment-success') {
      $user = require_auth();
      [, $body] = read_json();
      require_fields($body, ['type','conversation_id','amount']);
      $incoming = ($cfg['botpress_incoming_url'] ?? '') ?: (getenv('BOTPRESS_INCOMING_URL') ?: '');
      if ($incoming === '') json_out(['error'=>'BOTPRESS_INCOMING_URL not configured'], 500);
      $payload = json_encode($body);
      $ch = curl_init($incoming);
      curl_setopt_array($ch, [CURLOPT_POST=>true, CURLOPT_RETURNTRANSFER=>true, CURLOPT_POSTFIELDS=>$payload, CURLOPT_HTTPHEADER=>['Content-Type: application/json']]);
      $resp = curl_exec($ch);
      $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      curl_close($ch);
      if ($http >= 200 && $http < 300) {
        log_api('info','botpress_relay',[ 'endpoint'=>'payment-success', 'http_code'=>$http ]);
        json_out(['status'=>true]);
      }
      log_api('error','botpress_relay_failed',[ 'endpoint'=>'payment-success', 'http_code'=>$http, 'response'=>$resp ]);
      json_out(['error'=>'Botpress relay failed'], 502);
    }

    // ============ POST /v1/botpress/incoming (Botpress -> API webhook)
    if ($method === 'POST' && $p[1] === 'botpress' && !empty($p[2]) && $p[2] === 'incoming') {
      $raw = file_get_contents('php://input');
      $hdrs = [
        'x-bp-signature' => $_SERVER['HTTP_X_BP_SIGNATURE'] ?? null,
        'user-agent'     => $_SERVER['HTTP_USER_AGENT'] ?? null,
        'content-type'   => $_SERVER['CONTENT_TYPE'] ?? null,
      ];
      $data = json_decode($raw, true);
      log_api('info','botpress_incoming',[ 'headers'=>$hdrs, 'body'=>is_array($data)?$data:$raw ]);
      // Optional: add signature verification here if configured
      json_out(['ok'=>true]);
    }

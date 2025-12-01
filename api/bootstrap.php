<?php
$config = require __DIR__ . '/config.php';

function db(): PDO {
  static $pdo;
  if ($pdo) return $pdo;
  $cfg = require __DIR__ . '/config.php';
  $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s',
    $cfg['db']['host'], $cfg['db']['port'], $cfg['db']['name'], $cfg['db']['charset']);
  $pdo = new PDO($dsn, $cfg['db']['user'], $cfg['db']['pass'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);
  return $pdo;
}

function json_out($data, int $code=200): void {
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($data, JSON_UNESCAPED_UNICODE);
  exit;
}

function allow_cors(array $allowed): void {
  $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
  if ($origin && in_array($origin, $allowed, true)) {
    header("Access-Control-Allow-Origin: $origin");
    header('Vary: Origin');
  }
  header('Access-Control-Allow-Methods: GET, POST, PATCH, OPTIONS');
  header('Access-Control-Allow-Headers: Authorization, Content-Type');
  if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit(0);
}

function read_json(): array {
  $raw = file_get_contents('php://input');
  $data = json_decode($raw, true);
  if (!is_array($data)) json_out(['error'=>'Invalid JSON body'], 400);
  return [$raw, $data];
}

function log_api($level, $message, $ctx=[]): void {
  $cfg = require __DIR__ . '/config.php';
  $dir = $cfg['log_dir'] ?? dirname($cfg['log_file']);
  if (!is_dir($dir)) @mkdir($dir, 0775, true);
  $prefix = strtolower(preg_replace('/^([a-z0-9]+).*$/i', '$1', $message));
  $map = [
    'properties' => 'properties.log',
    'availability' => 'availability.log',
    'booking' => 'bookings.log',
    'payments' => 'payments.log',
    'payment' => 'payments.log',
    'users' => 'users.log',
    'vendor' => 'vendors.log',
    'botpress' => 'botpress.log',
    'conversation' => 'conversations.log',
  ];
  $file = $dir . '/' . ($map[$prefix] ?? 'app.log');
  $line = sprintf("[%s] [%s] ip=%s path=%s msg=%s ctx=%s\n",
    date('Y-m-d H:i:s'), strtoupper($level),
    $_SERVER['REMOTE_ADDR'] ?? 'unknown',
    $_SERVER['REQUEST_URI'] ?? '',
    $message, json_encode($ctx));
  @file_put_contents($file, $line, FILE_APPEND);
}

/** ---- Bearer auth (SUPERADMIN-ONLY) ---- */
function bearer_user(PDO $pdo): ?array {
  $hdr = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
  if (!preg_match('/Bearer\s+(.+)/i', $hdr, $m)) return null;
  $apiKey = trim($m[1]);
  if ($apiKey === '') return null;

  $stmt = $pdo->prepare("SELECT id, role, email, name FROM users WHERE api_key = :k LIMIT 1");
  $stmt->execute([':k' => $apiKey]);
  $u = $stmt->fetch();
  return $u ?: null;
}

/** Require superadmin for ALL endpoints */
function require_auth(): array {
  $pdo = db();
  $u = bearer_user($pdo);
  if (!$u) json_out(['error'=>'Invalid or missing API key'], 401);
  if (strtolower($u['role']) !== 'superadmin') {
    json_out(['error'=>'Forbidden – only superadmin can use API'], 403);
  }
  return $u;
}

function require_auth_roles(array $roles): array {
  $pdo = db();
  $u = bearer_user($pdo);
  if (!$u) json_out(['error'=>'Invalid or missing API key'], 401);
  $r = strtolower($u['role']);
  $allowed = array_map('strtolower', $roles);
  if (!in_array($r, $allowed, true)) {
    json_out(['error'=>'Forbidden'], 403);
  }
  return $u;
}

/** ---- Small validators ---- */
function require_fields(array $data, array $fields): void {
  foreach ($fields as $f) {
    if (!isset($data[$f]) || $data[$f] === '' || $data[$f] === null) {
      json_out(['error'=>"Missing field: $f"], 422);
    }
  }
}
function decimal($v): string {
  if (!is_numeric($v)) json_out(['error'=>'Invalid amount'], 422);
  return number_format((float)$v, 2, '.', '');
}
function ymd($v): string {
  $t = strtotime($v);
  if ($t === false) json_out(['error'=>"Invalid date: $v"], 422);
  return date('Y-m-d', $t);
}

function drive_cfg(): array {
  return (require __DIR__ . '/config.php')['drive'] ?? [];
}
function drive_sa_token(): string {
  $cfg = drive_cfg();
  $path = $cfg['credentials_path'] ?? '';
  if ($path === '' || !file_exists($path)) json_out(['error'=>'Drive credentials not configured'], 500);
  $j = json_decode(file_get_contents($path), true);
  $iss = $j['client_email'];
  $key = $j['private_key'];
  $aud = 'https://oauth2.googleapis.com/token';
  $iat = time();
  $exp = $iat + 3600;
  $claims = [
    'iss'=>$iss,
    'scope'=>'https://www.googleapis.com/auth/drive.file',
    'aud'=>$aud,
    'exp'=>$exp,
    'iat'=>$iat,
  ];
  $header = ['alg'=>'RS256','typ'=>'JWT'];
  $enc = function($x){ return rtrim(strtr(base64_encode(json_encode($x)), '+/', '-_'), '=' ); };
  $segments = [$enc($header), $enc($claims)];
  $input = implode('.', $segments);
  $pkey = openssl_pkey_get_private($key);
  $sig = '';
  openssl_sign($input, $sig, $pkey, OPENSSL_ALGO_SHA256);
  $jwt = $input . '.' . rtrim(strtr(base64_encode($sig), '+/', '-_'), '=');
  $ch = curl_init('https://oauth2.googleapis.com/token');
  curl_setopt_array($ch, [CURLOPT_POST=>true, CURLOPT_RETURNTRANSFER=>true, CURLOPT_POSTFIELDS=>http_build_query(['grant_type'=>'urn:ietf:params:oauth:grant-type:jwt-bearer','assertion'=>$jwt])]);
  $resp = curl_exec($ch);
  curl_close($ch);
  $tok = json_decode($resp,true);
  return $tok['access_token'] ?? '';
}
function drive_list(string $q): array {
  $t = drive_sa_token();
  $ch = curl_init('https://www.googleapis.com/drive/v3/files?q=' . urlencode($q) . '&fields=files(id,name,mimeType,parents)');
  curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_HTTPHEADER=>['Authorization: Bearer ' . $t]]);
  $resp = curl_exec($ch);
  curl_close($ch);
  $j = json_decode($resp,true);
  return $j['files'] ?? [];
}
function drive_create_folder(string $name, string $parent): string {
  $t = drive_sa_token();
  $meta = json_encode(['name'=>$name,'mimeType'=>'application/vnd.google-apps.folder','parents'=>[$parent]]);
  $ch = curl_init('https://www.googleapis.com/drive/v3/files');
  curl_setopt_array($ch, [CURLOPT_POST=>true, CURLOPT_RETURNTRANSFER=>true, CURLOPT_HTTPHEADER=>['Authorization: Bearer ' . $t,'Content-Type: application/json'], CURLOPT_POSTFIELDS=>$meta]);
  $resp = curl_exec($ch);
  curl_close($ch);
  $j = json_decode($resp,true);
  return $j['id'] ?? '';
}
function drive_find_or_create_folder(string $name, string $parent): string {
  $f = drive_list("name='$name' and mimeType='application/vnd.google-apps.folder' and '$parent' in parents and trashed=false");
  if ($f) return $f[0]['id'];
  return drive_create_folder($name, $parent);
}
function drive_find_file(string $name, string $parent): string {
  $f = drive_list("name='$name' and '$parent' in parents and trashed=false");
  return $f ? $f[0]['id'] : '';
}
function drive_create_file(string $name, string $parent, string $mime='text/plain'): string {
  $t = drive_sa_token();
  $meta = json_encode(['name'=>$name,'mimeType'=>$mime,'parents'=>[$parent]]);
  $ch = curl_init('https://www.googleapis.com/drive/v3/files');
  curl_setopt_array($ch, [CURLOPT_POST=>true, CURLOPT_RETURNTRANSFER=>true, CURLOPT_HTTPHEADER=>['Authorization: Bearer ' . $t,'Content-Type: application/json'], CURLOPT_POSTFIELDS=>$meta]);
  $resp = curl_exec($ch);
  curl_close($ch);
  $j = json_decode($resp,true);
  return $j['id'] ?? '';
}
function drive_upload_content(string $fileId, string $content, string $mime='text/plain'): void {
  $t = drive_sa_token();
  $ch = curl_init('https://www.googleapis.com/upload/drive/v3/files/' . $fileId . '?uploadType=media');
  curl_setopt_array($ch, [CURLOPT_CUSTOMREQUEST=>'PATCH', CURLOPT_RETURNTRANSFER=>true, CURLOPT_HTTPHEADER=>['Authorization: Bearer ' . $t,'Content-Type: ' . $mime], CURLOPT_POSTFIELDS=>$content]);
  curl_exec($ch);
  curl_close($ch);
}
function drive_append_csv_row(string $convId, array $row): void {
  $cfg = drive_cfg();
  $root = $cfg['root_folder_id'] ?? '';
  if ($root === '') return;
  $folder = drive_find_or_create_folder('Conversation-' . $convId, $root);
  $fid = drive_find_file('Activity.csv', $folder);
  if ($fid === '') $fid = drive_create_file('Activity.csv', $folder, 'text/csv');
  $line = implode(',', array_map(function($v){ $s = str_replace(['"','\n','\r'], ['"',' ',' '], (string)$v); return '"' . str_replace('"','""',$s) . '"'; }, $row)) . "\n";
  $t = drive_sa_token();
  $ch = curl_init('https://www.googleapis.com/drive/v3/files/' . $fid . '?alt=media');
  curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_HTTPHEADER=>['Authorization: Bearer ' . $t]]);
  $existing = curl_exec($ch);
  curl_close($ch);
  $new = ($existing ?: '') . $line;
  drive_upload_content($fid, $new, 'text/csv');
}
function drive_write_text(string $convId, string $filename, string $content): void {
  $cfg = drive_cfg();
  $root = $cfg['root_folder_id'] ?? '';
  if ($root === '') return;
  $folder = drive_find_or_create_folder('Conversation-' . $convId, $root);
  $fid = drive_find_file($filename, $folder);
  if ($fid === '') $fid = drive_create_file($filename, $folder, 'text/plain');
  drive_upload_content($fid, $content, 'text/plain');
}
function botpress_emit(string $name, array $payload): void {
  $cfg = require __DIR__ . '/config.php';
  $url = $cfg['botpress_incoming_url'] ?? (getenv('BOTPRESS_INCOMING_URL') ?: '');
  if ($url === '') return;
  $token = $cfg['botpress_api_token'] ?? (defined('BOTPRESS_API_TOKEN') ? BOTPRESS_API_TOKEN : '');
  $body = json_encode(['type'=>'custom','name'=>$name,'payload'=>$payload]);
  $tries = 0;
  while ($tries < 3) {
    $hdrs = ['Content-Type: application/json'];
    if ($token !== '') $hdrs[] = 'Authorization: Bearer ' . $token;
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_POST=>true, CURLOPT_RETURNTRANSFER=>true, CURLOPT_POSTFIELDS=>$body, CURLOPT_HTTPHEADER=>$hdrs]);
    $resp = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($http >= 200 && $http < 300) return;
    $tries++;
    usleep(300000 * $tries);
  }
}
function sanitize_vendor_message(string $text): array {
  $redactions = [];
  $t = preg_replace('/\b[\w.+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}\b/u','', $text);
  if ($t !== $text) $redactions[] = 'email';
  $text = $t;
  $t = preg_replace('/\b(?:https?:\/\/|www\.)\S+\b/u','', $text);
  if ($t !== $text) $redactions[] = 'url';
  $text = $t;
  $t = preg_replace('/\+?\d[\d\s().-]{6,}\d/u','', $text);
  if ($t !== $text) $redactions[] = 'phone';
  $text = $t;
  $t = preg_replace('/[\x{1F600}-\x{1F64F}\x{1F300}-\x{1F5FF}\x{1F680}-\x{1F6FF}\x{2600}-\x{26FF}]/u','', $text);
  if ($t !== $text) $redactions[] = 'emoji';
  $text = $t;
  $bad = ['fuck','shit','bastard'];
  $t = preg_replace('/(' . implode('|', array_map(function($w){ return preg_quote($w,'/'); }, $bad)) . ')/i','', $text);
  if ($t !== $text) $redactions[] = 'abuse';
  $text = trim($t);
  return ['clean'=>$text,'redactions'=>$redactions];
}
function whatsapp_send(string $to, string $text): array {
  $cfg = require __DIR__ . '/config.php';
  $token = $cfg['whatsapp']['token'] ?? '';
  $phone_id = $cfg['whatsapp']['phone_id'] ?? '';
  $base = $cfg['whatsapp']['base_url'] ?? 'https://graph.facebook.com/v20.0';
  $url = rtrim($base,'/') . '/' . $phone_id . '/messages';
  $body = json_encode(['messaging_product'=>'whatsapp','to'=>$to,'type'=>'text','text'=>['body'=>$text]]);
  $ch = curl_init($url);
  curl_setopt_array($ch, [CURLOPT_POST=>true, CURLOPT_RETURNTRANSFER=>true, CURLOPT_POSTFIELDS=>$body, CURLOPT_HTTPHEADER=>['Content-Type: application/json','Authorization: Bearer ' . $token]]);
  $resp = curl_exec($ch);
  $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
  return ['http'=>$http,'response'=>json_decode($resp,true)];
}
function vendor_map_path(): string { return __DIR__ . '/logs/vendor_map.json'; }
function vendor_map_set(string $phone, string $conversation_id, int $property_id, int $timeout_minutes): void {
  $p = vendor_map_path();
  $data = file_exists($p) ? json_decode(file_get_contents($p), true) : [];
  $data[$phone] = ['conversation_id'=>$conversation_id,'property_id'=>$property_id,'expires_at'=>time()+($timeout_minutes*60)];
  if (!is_dir(dirname($p))) @mkdir(dirname($p), 0775, true);
  file_put_contents($p, json_encode($data));
}
function vendor_map_get(string $phone): ?array {
  $p = vendor_map_path();
  if (!file_exists($p)) return null;
  $data = json_decode(file_get_contents($p), true) ?: [];
  if (!isset($data[$phone])) return null;
  return $data[$phone];
}
function vendor_map_cleanup(): array {
  $p = vendor_map_path();
  if (!file_exists($p)) return [];
  $data = json_decode(file_get_contents($p), true) ?: [];
  $now = time();
  $expired = [];
  foreach ($data as $k=>$v) {
    if (($v['expires_at'] ?? 0) < $now) {
      $expired[] = ['phone'=>$k,'conversation_id'=>$v['conversation_id'] ?? '', 'property_id'=>$v['property_id'] ?? 0];
      unset($data[$k]);
    }
  }
  file_put_contents($p, json_encode($data));
  return $expired;
}

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
  header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
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
  $dir = dirname($cfg['log_file']);
  if (!is_dir($dir)) @mkdir($dir, 0775, true);
  $line = sprintf("[%s] [%s] ip=%s path=%s msg=%s ctx=%s\n",
    date('Y-m-d H:i:s'), strtoupper($level),
    $_SERVER['REMOTE_ADDR'] ?? 'unknown',
    $_SERVER['REQUEST_URI'] ?? '',
    $message, json_encode($ctx));
  @file_put_contents($cfg['log_file'], $line, FILE_APPEND);
}

// ---- Bearer auth using users.api_key ----
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
function require_auth($roles = []): array {
  $pdo = db();
  $u = bearer_user($pdo);
  if (!$u) json_out(['error'=>'Invalid or missing API key'], 401);
  if ($roles && !in_array(strtolower($u['role']), array_map('strtolower',$roles), true)) {
    json_out(['error'=>'Forbidden'], 403);
  }
  return $u;
}

// ---- Small validators ----
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

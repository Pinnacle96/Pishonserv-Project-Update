<?php
// dashboard/admin_withdrawals_content.php
// Assumes session + $conn are already available from wrapper + layout

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function ngn($n){ return '₦'.number_format((float)$n, 2); }

$allowed_status = ['requested','pending','processing','completed','failed','reversed','canceled'];

// ---- Filters ----
$status    = isset($_GET['status']) ? trim($_GET['status']) : '';
$q         = isset($_GET['q']) ? trim($_GET['q']) : '';
$date_from = isset($_GET['from']) ? trim($_GET['from']) : '';
$date_to   = isset($_GET['to']) ? trim($_GET['to']) : '';

$page     = max(1, (int)($_GET['page'] ?? 1));
$per_page = max(10, min(100, (int)($_GET['per_page'] ?? 20)));
$offset   = ($page - 1) * $per_page;

if ($status && !in_array($status, $allowed_status, true)) $status = '';

// ---- Build WHERE ----
$where = [];
$params = [];
$types  = '';

if ($status !== '') { $where[]="w.status=?"; $params[]=$status; $types.='s'; }
if ($q !== '') {
  $where[]="(u.name LIKE CONCAT('%', ?, '%') OR u.lname LIKE CONCAT('%', ?, '%') OR u.email LIKE CONCAT('%', ?, '%'))";
  $params[]=$q; $params[]=$q; $params[]=$q; $types.='sss';
}
if ($date_from !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/',$date_from)) { $where[]="DATE(w.created_at) >= ?"; $params[]=$date_from; $types.='s'; }
if ($date_to   !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/',$date_to))   { $where[]="DATE(w.created_at) <= ?"; $params[]=$date_to;   $types.='s'; }

$where_sql = $where ? ('WHERE '.implode(' AND ', $where)) : '';

// ---- Status counters ----
$counts = array_fill_keys($allowed_status, 0);
if ($res = $conn->query("SELECT status, COUNT(*) c FROM withdrawals GROUP BY status")) {
  while ($r = $res->fetch_assoc()) { $counts[$r['status']] = (int)$r['c']; }
}

// ---- Count for pagination ----
$sql_count = "SELECT COUNT(*) c
              FROM withdrawals w
              JOIN users u ON u.id=w.user_id
              LEFT JOIN bank_accounts b ON b.id=w.bank_account_id
              $where_sql";
$cstmt = $conn->prepare($sql_count);
if ($types) { $cstmt->bind_param($types, ...$params); }
$cstmt->execute();
$total_rows = (int)($cstmt->get_result()->fetch_assoc()['c'] ?? 0);
$cstmt->close();
$total_pages = max(1, (int)ceil($total_rows / $per_page));

// ---- CSV export (filtered) ----
if (isset($_GET['export']) && $_GET['export']==='csv') {
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename=withdrawals_'.date('Ymd_His').'.csv');

  $out = fopen('php://output','w');
  fputcsv($out, [
    'ID','User','Email','Amount','Fee','Currency','Status',
    'Recipient Code','Reason','PS Transfer Code','PS Reference',
    'Created At','Updated At','Bank Name','Account Name','Account Number','Error'
  ]);

  $sql_exp = "SELECT
      w.id, u.name, u.lname, u.email, w.amount, w.fee, w.currency, w.status,
      w.recipient_code, w.reason, w.paystack_transfer_code, w.paystack_reference,
      w.created_at, w.updated_at, b.bank_name, b.account_name, b.account_number, w.error_message
    FROM withdrawals w
    JOIN users u ON u.id=w.user_id
    LEFT JOIN bank_accounts b ON b.id=w.bank_account_id
    $where_sql
    ORDER BY w.created_at DESC";

  $estmt = $conn->prepare($sql_exp);
  if ($types) { $estmt->bind_param($types, ...$params); }
  $estmt->execute();
  $eres = $estmt->get_result();
  while ($r = $eres->fetch_assoc()) {
    fputcsv($out, [
      $r['id'],
      $r['name'].' '.$r['lname'],
      $r['email'],
      $r['amount'],
      $r['fee'],
      $r['currency'],
      $r['status'],
      $r['recipient_code'],
      $r['reason'],
      $r['paystack_transfer_code'],
      $r['paystack_reference'],
      $r['created_at'],
      $r['updated_at'],
      $r['bank_name'],
      $r['account_name'],
      $r['account_number'],
      $r['error_message'],
    ]);
  }
  fclose($out);
  exit;
}

// ---- List query (paged) ----
$sql = "SELECT
          w.id, w.user_id, w.bank_account_id, w.amount, w.fee, w.currency,
          w.status, w.recipient_code, w.reason, w.paystack_transfer_code,
          w.paystack_reference, w.error_message, w.created_at, w.updated_at,
          u.name, u.lname, u.email,
          b.bank_name, b.account_number, b.account_name
        FROM withdrawals w
        JOIN users u ON u.id=w.user_id
        LEFT JOIN bank_accounts b ON b.id=w.bank_account_id
        $where_sql
        ORDER BY w.created_at DESC
        LIMIT ? OFFSET ?";
$lstmt = $conn->prepare($sql);
if ($types) {
  $bind_types = $types.'ii';
  $bind_params = array_merge($params, [$per_page, $offset]);
  $lstmt->bind_param($bind_types, ...$bind_params);
} else {
  $lstmt->bind_param('ii', $per_page, $offset);
}
$lstmt->execute();
$rows = $lstmt->get_result();
$lstmt->close();

function statusBadge($s) {
  $map = [
    'requested'=>'bg-blue-100 text-blue-700',
    'pending'=>'bg-amber-100 text-amber-700',
    'processing'=>'bg-indigo-100 text-indigo-700',
    'completed'=>'bg-green-100 text-green-700',
    'failed'=>'bg-rose-100 text-rose-700',
    'reversed'=>'bg-purple-100 text-purple-700',
    'canceled'=>'bg-gray-200 text-gray-700'
  ];
  $cls = $map[$s] ?? 'bg-gray-100 text-gray-700';
  return '<span class="px-2 py-1 rounded text-xs font-semibold '.$cls.'">'.h(ucfirst($s)).'</span>';
}
?>

<div class="mt-6 space-y-6">

  <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-4">
    <div>
      <h2 class="text-2xl md:text-3xl font-bold text-gray-800 dark:text-gray-200">Withdrawals</h2>
      <p class="text-gray-600 dark:text-gray-400 mt-1">View and export payout requests across the platform.</p>
    </div>

    <form method="get" class="grid grid-cols-2 md:grid-cols-7 gap-2">
      <input type="text" name="q" value="<?= h($q) ?>" placeholder="Search user/email"
             class="col-span-2 md:col-span-2 px-3 py-2 border rounded dark:bg-gray-800 dark:border-gray-700">
      <select name="status" class="col-span-1 px-3 py-2 border rounded dark:bg-gray-800 dark:border-gray-700">
        <option value="">All Status</option>
        <?php foreach ($allowed_status as $st): ?>
          <option value="<?= h($st) ?>" <?= $status===$st?'selected':''; ?>><?= h(ucfirst($st)) ?></option>
        <?php endforeach; ?>
      </select>
      <input type="date" name="from" value="<?= h($date_from) ?>"
             class="col-span-1 px-3 py-2 border rounded dark:bg-gray-800 dark:border-gray-700">
      <input type="date" name="to" value="<?= h($date_to) ?>"
             class="col-span-1 px-3 py-2 border rounded dark:bg-gray-800 dark:border-gray-700">
      <select name="per_page" class="col-span-1 px-3 py-2 border rounded dark:bg-gray-800 dark:border-gray-700">
        <?php foreach ([20,50,100] as $pp): ?>
          <option value="<?= $pp ?>" <?= $per_page==$pp?'selected':''; ?>><?= $pp ?>/page</option>
        <?php endforeach; ?>
      </select>
      <div class="col-span-2 md:col-span-1 flex gap-2">
        <button class="px-3 py-2 bg-[#092468] text-white rounded hover:bg-[#061b47]">Filter</button>
        <a class="px-3 py-2 bg-gray-200 rounded dark:bg-gray-700 dark:text-gray-100" href="admin_withdrawals.php">Reset</a>
      </div>
    </form>
  </div>

  <!-- Status counters -->
  <div class="grid grid-cols-2 md:grid-cols-7 gap-4">
    <?php foreach ($counts as $k=>$v): ?>
      <div class="bg-white dark:bg-gray-800 p-4 rounded border border-gray-100 dark:border-gray-700">
        <div class="text-xs uppercase text-gray-500"><?= h(ucfirst($k)) ?></div>
        <div class="text-2xl font-bold mt-1"><?= number_format($v) ?></div>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="bg-white dark:bg-gray-800 p-4 rounded border border-gray-100 dark:border-gray-700">
    <div class="flex items-center justify-between mb-3">
      <h3 class="text-lg md:text-xl font-bold">Results (<?= number_format($total_rows) ?>)</h3>
      <?php $qs = $_GET; $qs['export']='csv'; $export_url = 'admin_withdrawals.php?'.http_build_query($qs); ?>
      <a href="<?= h($export_url) ?>"
         class="px-3 py-2 rounded bg-emerald-600 text-white hover:bg-emerald-700">Export CSV</a>
    </div>

    <div class="overflow-x-auto">
      <table class="w-full text-sm border border-gray-200 dark:border-gray-700">
        <thead>
          <tr class="bg-gray-100 dark:bg-gray-700 text-gray-900 dark:text-gray-100">
            <th class="p-3 border">ID</th>
            <th class="p-3 border">User</th>
            <th class="p-3 border">Email</th>
            <th class="p-3 border">Amount</th>
            <th class="p-3 border">Fee</th>
            <th class="p-3 border">Status</th>
            <th class="p-3 border">PS Transfer</th>
            <th class="p-3 border">PS Ref</th>
            <th class="p-3 border">Bank</th>
            <th class="p-3 border">Created</th>
            <th class="p-3 border">Updated</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($rows->num_rows === 0): ?>
            <tr><td colspan="11" class="p-3 border text-center text-gray-500">No withdrawals found.</td></tr>
          <?php else: while ($r = $rows->fetch_assoc()): ?>
            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30">
              <td class="p-3 border"><?= (int)$r['id'] ?></td>
              <td class="p-3 border"><?= h($r['name'].' '.$r['lname']) ?></td>
              <td class="p-3 border"><?= h($r['email']) ?></td>
              <td class="p-3 border"><?= ngn($r['amount']) ?></td>
              <td class="p-3 border"><?= ngn($r['fee']) ?></td>
              <td class="p-3 border"><?= statusBadge($r['status']) ?></td>
              <td class="p-3 border"><span class="font-mono"><?= h($r['paystack_transfer_code']) ?></span></td>
              <td class="p-3 border"><span class="font-mono"><?= h($r['paystack_reference']) ?></span></td>
              <td class="p-3 border">
                <div class="text-xs text-gray-600 dark:text-gray-300">
                  <?= h($r['bank_name'] ?: '—') ?><br>
                  <?= h($r['account_name'] ?: '—') ?> (<?= h($r['account_number'] ?: '—') ?>)
                </div>
              </td>
              <td class="p-3 border"><?= h(date('M j, Y H:i', strtotime($r['created_at']))) ?></td>
              <td class="p-3 border"><?= h(date('M j, Y H:i', strtotime($r['updated_at']))) ?></td>
            </tr>
            <?php if (!empty($r['error_message'])): ?>
              <tr>
                <td colspan="11" class="p-3 border bg-rose-50 dark:bg-rose-900/20 text-rose-700 dark:text-rose-300 text-xs">
                  <strong>Error:</strong> <?= h($r['error_message']) ?>
                </td>
              </tr>
            <?php endif; ?>
          <?php endwhile; endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div class="flex items-center justify-between mt-4">
      <div class="text-sm text-gray-600 dark:text-gray-300">
        Page <?= number_format($page) ?> of <?= number_format($total_pages) ?> ·
        Showing <?= number_format($rows->num_rows) ?> / <?= number_format($total_rows) ?>
      </div>
      <div class="flex gap-2">
        <?php $base_qs = $_GET; unset($base_qs['page']); $base = 'admin_withdrawals.php?'.http_build_query($base_qs).'&page='; ?>
        <a href="<?= h($base.max(1,$page-1)) ?>"
           class="px-3 py-2 rounded border bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700">Prev</a>
        <a href="<?= h($base.min($total_pages,$page+1)) ?>"
           class="px-3 py-2 rounded border bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700">Next</a>
      </div>
    </div>
  </div>
</div>

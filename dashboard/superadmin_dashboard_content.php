<?php
// dashboard/superadmin_dashboard_content.php
require_once '../includes/db_connect.php';
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function ngn($n){ return '₦'.number_format((float)$n, 2); }

$me_id = (int)($_SESSION['user_id'] ?? 0);

// Referral stats for me
$ref_count = 0; $ref_commission = 0.0; $ref_code = '';
if ($me_id > 0) {
  $q = $conn->prepare("SELECT COUNT(*) c FROM referrals WHERE referrer_user_id = ?");
  $q->bind_param('i', $me_id);
  $q->execute();
  $ref_count = (int)($q->get_result()->fetch_assoc()['c'] ?? 0);
  $q->close();

  $t = $conn->prepare("SELECT COALESCE(SUM(amount),0) s FROM transactions WHERE user_id = ? AND status='completed' AND type='credit' AND transaction_type='referral_bonus'");
  $t->bind_param('i', $me_id);
  $t->execute();
  $ref_commission = (float)($t->get_result()->fetch_assoc()['s'] ?? 0.0);
  $t->close();

  $c = $conn->prepare("SELECT referral_code FROM users WHERE id = ? LIMIT 1");
  $c->bind_param('i', $me_id);
  $c->execute();
  $ref_code = (string)($c->get_result()->fetch_assoc()['referral_code'] ?? '');
  $c->close();

  if ($ref_code === '') {
    $code = '';
    for ($i=0; $i<5 && $code === ''; $i++) {
      $candidate = substr(bin2hex(random_bytes(8)),0,16);
      $chk = $conn->prepare('SELECT id FROM users WHERE referral_code = ?');
      $chk->bind_param('s', $candidate);
      $chk->execute();
      $exists = $chk->get_result()->num_rows > 0;
      $chk->close();
      if (!$exists) $code = $candidate;
    }
    if ($code === '') { $code = 'ps'.dechex($me_id).substr(bin2hex(random_bytes(3)),0,6); }
    $set = $conn->prepare('UPDATE users SET referral_code = ? WHERE id = ?');
    $set->bind_param('si', $code, $me_id);
    $set->execute();
    $set->close();
    $ref_code = $code;
  }
}

// -------------------- GLOBAL METRICS --------------------
$total_users = (int)($conn->query("SELECT COUNT(*) c FROM users")->fetch_assoc()['c'] ?? 0);
$total_props = (int)($conn->query("SELECT COUNT(*) c FROM properties")->fetch_assoc()['c'] ?? 0);

// GMV = all completed payments (platform gross)
$gmv_total = (float)($conn->query("
    SELECT COALESCE(SUM(amount),0) s
    FROM payments
    WHERE status='completed'
")->fetch_assoc()['s'] ?? 0);

// Platform earnings (completed credits) – platform-wide
$platform_earnings = (float)($conn->query("\n    SELECT COALESCE(SUM(t.amount),0) s\n    FROM transactions t\n    JOIN users u ON u.id = t.user_id\n    WHERE t.status='completed' AND t.type='credit' AND u.role='superadmin'\n")->fetch_assoc()['s'] ?? 0);

// Wallet float (sum of user wallets)
$wallet_float = (float)($conn->query("SELECT COALESCE(SUM(balance),0) s FROM wallets")->fetch_assoc()['s'] ?? 0);

// Approvals / bookings / payments counters
$pending_props = (int)($conn->query("SELECT COUNT(*) c FROM properties WHERE admin_approved=0")->fetch_assoc()['c'] ?? 0);
$active_props  = (int)($conn->query("SELECT COUNT(*) c FROM properties WHERE admin_approved=1")->fetch_assoc()['c'] ?? 0);

$book_pending   = (int)($conn->query("SELECT COUNT(*) c FROM bookings WHERE status='pending'")->fetch_assoc()['c'] ?? 0);
$book_confirmed = (int)($conn->query("SELECT COUNT(*) c FROM bookings WHERE status='confirmed'")->fetch_assoc()['c'] ?? 0);
$book_cancelled = (int)($conn->query("SELECT COUNT(*) c FROM bookings WHERE status='cancelled'")->fetch_assoc()['c'] ?? 0);

$completed_payments = (int)($conn->query("SELECT COUNT(*) c FROM payments WHERE status='completed'")->fetch_assoc()['c'] ?? 0);

// System settings
$settings = $conn->query("SELECT commission, site_status FROM system_settings ORDER BY id ASC LIMIT 1")->fetch_assoc()
    ?: ['commission'=>0, 'site_status'=>'active'];

// -------------------- GROWTH SNAPSHOTS --------------------
// Users MoM (last 30 days vs previous 30)
$users_30  = (int)($conn->query("SELECT COUNT(*) c FROM users WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)")->fetch_assoc()['c'] ?? 0);
$users_prev30 = (int)($conn->query("SELECT COUNT(*) c FROM users WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 60 DAY) AND created_at < DATE_SUB(CURDATE(), INTERVAL 30 DAY)")->fetch_assoc()['c'] ?? 0);
$users_growth = $users_prev30 > 0 ? round((($users_30 - $users_prev30)/$users_prev30)*100, 1) : ($users_30 > 0 ? 100 : 0);

// GMV MoM (last 30 days vs previous 30)
$gmv_30 = (float)($conn->query("
    SELECT COALESCE(SUM(amount),0) s FROM payments
    WHERE status='completed' AND created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
")->fetch_assoc()['s'] ?? 0);
$gmv_prev30 = (float)($conn->query("
    SELECT COALESCE(SUM(amount),0) s FROM payments
    WHERE status='completed'
      AND created_at >= DATE_SUB(CURDATE(), INTERVAL 60 DAY)
      AND created_at < DATE_SUB(CURDATE(), INTERVAL 30 DAY)
")->fetch_assoc()['s'] ?? 0);
$gmv_growth = $gmv_prev30 > 0 ? round((($gmv_30 - $gmv_prev30)/$gmv_prev30)*100, 1) : ($gmv_30 > 0 ? 100 : 0);

// Avg booking value (last 30 days, confirmed bookings only)
$abv_row = $conn->query("
  SELECT COALESCE(AVG(amount),0) AS a
  FROM bookings
  WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    AND amount > 0
    AND status = 'confirmed'
")->fetch_assoc();

$avg_booking_value_30d = (float)($abv_row['a'] ?? 0);

// -------------------- WITHDRAWAL ANALYTICS --------------------
$wd_counts = [
  'requested'  => 0,
  'pending'    => 0,
  'processing' => 0,
  'completed'  => 0,
  'failed'     => 0,
  'reversed'   => 0,
  'canceled'   => 0,
  'total'      => 0
];

$wres = $conn->query("
  SELECT status, COUNT(*) c
  FROM withdrawals
  GROUP BY status
");
while ($w = $wres->fetch_assoc()) {
  $wd_counts[$w['status']] = (int)$w['c'];
  $wd_counts['total'] += (int)$w['c'];
}

// Sums (money)
$wd_sum_completed = (float)($conn->query("
  SELECT COALESCE(SUM(amount),0) s
  FROM withdrawals
  WHERE status = 'completed'
")->fetch_assoc()['s'] ?? 0);

// Last 30 days completed
$wd_sum_completed_30 = (float)($conn->query("
  SELECT COALESCE(SUM(amount),0) s
  FROM withdrawals
  WHERE status = 'completed'
    AND created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
")->fetch_assoc()['s'] ?? 0);

// Recent withdrawals (show last 10, all statuses)
$recent_withdrawals = $conn->query("
  SELECT w.id, w.user_id, u.name, u.lname, w.amount, w.status, w.currency,
         w.paystack_transfer_code, w.paystack_reference, w.error_message,
         w.created_at, w.updated_at,
         ba.bank_name, ba.account_number
  FROM withdrawals w
  JOIN users u ON u.id = w.user_id
  LEFT JOIN bank_accounts ba ON ba.id = w.bank_account_id
  ORDER BY w.created_at DESC
  LIMIT 10
");

// Chart data: status mix
$wd_status_labels = ['Requested','Pending','Processing','Completed','Failed','Reversed','Canceled'];
$wd_status_values = [
  (int)$wd_counts['requested'],
  (int)$wd_counts['pending'],
  (int)$wd_counts['processing'],
  (int)$wd_counts['completed'],
  (int)$wd_counts['failed'],
  (int)$wd_counts['reversed'],
  (int)$wd_counts['canceled'],
];


// -------------------- ROLE BREAKDOWN --------------------
$roles = ['buyer','agent','owner','hotel_owner','host','developer','admin','superadmin'];
$role_counts = [];
$res = $conn->query("SELECT role, COUNT(*) c FROM users GROUP BY role");
while ($r = $res->fetch_assoc()) { $role_counts[$r['role']] = (int)$r['c']; }
$role_labels = []; $role_values = [];
foreach ($roles as $r) { $role_labels[] = ucfirst(str_replace('_',' ', $r)); $role_values[] = (int)($role_counts[$r] ?? 0); }

// -------------------- EARNINGS TREND (last 6 months) --------------------
$earn_labels = []; $earn_values = [];
$res = $conn->query("
    SELECT DATE_FORMAT(created_at, '%Y-%m') ym, COALESCE(SUM(amount),0) s
    FROM transactions
    WHERE status='completed' AND type='credit'
      AND created_at >= DATE_SUB(CURRENT_DATE(), INTERVAL 6 MONTH)
    GROUP BY ym
    ORDER BY ym ASC
");
$series = [];
while ($r = $res->fetch_assoc()) { $series[$r['ym']] = (float)$r['s']; }
for ($i=5; $i>=0; $i--) {
    $k = date('Y-m', strtotime("-$i months"));
    $earn_labels[] = date('M', strtotime($k.'-01'));
    $earn_values[] = $series[$k] ?? 0.0;
}

// -------------------- LISTINGS BY TYPE --------------------
$type_labels = []; $type_values = [];
$types_res = $conn->query("SELECT listing_type, COUNT(*) c FROM properties GROUP BY listing_type");
while ($r = $types_res->fetch_assoc()) {
    $type_labels[] = ucfirst(str_replace('_',' ', $r['listing_type']));
    $type_values[] = (int)$r['c'];
}

// -------------------- BOOKINGS STATUS MIX (pie) --------------------
$book_status_labels = ['Pending', 'Confirmed', 'Cancelled'];
$book_status_values = [$book_pending, $book_confirmed, $book_cancelled];

// -------------------- TOP PERFORMERS --------------------
// Top 5 earners (users) by completed credit
$top_users = $conn->query("
    SELECT u.id, u.name, u.lname, u.role, COALESCE(SUM(t.amount),0) total
    FROM transactions t
    JOIN users u ON u.id=t.user_id
    WHERE t.status='completed' AND t.type='credit'
    GROUP BY u.id, u.name, u.lname, u.role
    ORDER BY total DESC
    LIMIT 5
");

// Top 5 properties by payment sum
$top_properties = $conn->query("
    SELECT p.id, p.title, p.location, COALESCE(SUM(pay.amount),0) total
    FROM payments pay
    JOIN properties p ON p.id = pay.property_id
    WHERE pay.status='completed'
    GROUP BY p.id, p.title, p.location
    ORDER BY total DESC
    LIMIT 5
");

// Recent activities (system-wide)
$activities = $conn->query("
    SELECT a.description, a.created_at, u.name
    FROM activities a
    JOIN users u ON u.id = a.user_id
    ORDER BY a.created_at DESC
    LIMIT 8
");

// -------------------- MY (SUPERADMIN) PERSONAL VIEW --------------------
// My properties count
$stmt = $conn->prepare("SELECT COUNT(*) AS c FROM properties WHERE owner_id = ?");
$stmt->bind_param("i", $me_id);
$stmt->execute();
$my_props_count = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
$stmt->close();

// My earnings (completed credits tied to my account)
$stmt = $conn->prepare("\n  SELECT COALESCE(SUM(amount),0) AS s\n  FROM transactions\n  WHERE user_id = ? AND status='completed' AND type='credit'\n");
$stmt->bind_param("i", $me_id);
$stmt->execute();
$my_credits = (float)($stmt->get_result()->fetch_assoc()['s'] ?? 0.0);
$stmt->close();

$stmt = $conn->prepare("\n  SELECT COALESCE(SUM(amount),0) AS s\n  FROM transactions\n  WHERE user_id = ? AND status='completed' AND transaction_type='withdrawal' AND type='debit'\n");
$stmt->bind_param("i", $me_id);
$stmt->execute();
$my_withdrawals = (float)($stmt->get_result()->fetch_assoc()['s'] ?? 0.0);
$stmt->close();

$stmt = $conn->prepare("\n  SELECT COALESCE(balance,0) AS s\n  FROM wallets\n  WHERE user_id = ?\n");
$stmt->bind_param("i", $me_id);
$stmt->execute();
$my_available = (float)($stmt->get_result()->fetch_assoc()['s'] ?? 0.0);
$stmt->close();

$my_net = max(0.0, $my_credits - $my_withdrawals);

// My recent properties
$stmt = $conn->prepare("
  SELECT id, property_code, title, location, listing_type, price, created_at, admin_approved, status
  FROM properties
  WHERE owner_id = ?
  ORDER BY created_at DESC
  LIMIT 6
");
$stmt->bind_param("i", $me_id);
$stmt->execute();
$my_recent_props = $stmt->get_result();
$stmt->close();

// My recent payments (for my properties)
$stmt = $conn->prepare("
  SELECT pay.id, pay.amount, pay.status, pay.created_at, pay.payment_gateway, pay.transaction_id,
         p.title AS property_name
  FROM payments pay
  JOIN properties p ON p.id = pay.property_id
  WHERE p.owner_id = ?
  ORDER BY pay.created_at DESC
  LIMIT 6
");
$stmt->bind_param("i", $me_id);
$stmt->execute();
$my_recent_payments = $stmt->get_result();
$stmt->close();

// My recent bookings (for my properties)
$stmt = $conn->prepare("
  SELECT b.id, b.status, b.amount, b.created_at, b.check_in_date, b.check_out_date,
         u.name AS buyer_name, p.title AS property_name
  FROM bookings b
  JOIN properties p ON p.id = b.property_id
  JOIN users u ON u.id = b.user_id
  WHERE p.owner_id = ?
  ORDER BY b.created_at DESC
  LIMIT 6
");
$stmt->bind_param("i", $me_id);
$stmt->execute();
$my_recent_bookings = $stmt->get_result();
$stmt->close();

// Fetch API key for current superadmin
$apiKey = 'No key found';
if (!empty($_SESSION['user_id'])) {
    $stmt = $conn->prepare("SELECT api_key FROM users WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $_SESSION['user_id']);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    $apiKey = $r['api_key'] ?? 'No key found';
    $stmt->close();
}
?>

<div class="mt-6 space-y-6">

  <!-- HEADER -->
  <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
    <div>
      <h2 class="text-2xl md:text-3xl font-bold text-gray-800 dark:text-gray-200">Superadmin Dashboard</h2>
      <p class="text-gray-600 dark:text-gray-400 mt-1">
        Holistic overview of platform performance & your personal earnings.
        <span class="block text-xs mt-1">GMV = total completed payments across the platform (gross).</span>
      </p>
      <p class="text-xs text-gray-500 mt-1">
        Site status: <span class="font-semibold"><?= h(strtoupper($settings['site_status'])) ?></span> ·
        Commission: <span class="font-semibold"><?= number_format((float)$settings['commission'], 2) ?>%</span>
      </p>
    </div>
    <a href="zoho_auth.php"
       class="inline-flex items-center justify-center bg-[#092468] text-white px-4 py-2 rounded-lg hover:bg-[#051b47] transition focus:outline-none focus:ring-2 focus:ring-[#092468]">
      Connect Zoho CRM
    </a>
  </div>

  <!-- GLOBAL KPI GRID -->
  <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
    <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-sm border border-gray-100 dark:border-gray-700">
      <div class="flex items-center justify-between mb-3">
        <h3 class="text-lg md:text-xl font-bold">Referral Summary</h3>
        <?php if ($ref_code): ?>
        <button onclick="navigator.clipboard.writeText('https://pishonserv.com/register.php?ref=<?= htmlspecialchars($ref_code) ?>').then(()=>Swal.fire('Copied','Referral link copied','success')).catch(()=>Swal.fire('Error','Unable to copy','error'))" class="px-3 py-2 bg-[#092468] text-white rounded">Copy Link</button>
        <?php endif; ?>
      </div>
      <?php if ($ref_code): ?>
      <div class="flex flex-col md:flex-row gap-3">
        <input type="text" readonly value="https://pishonserv.com/register.php?ref=<?= htmlspecialchars($ref_code) ?>" class="w-full md:flex-1 p-3 border rounded bg-gray-50 dark:bg-gray-700" />
        <a href="https://pishonserv.com/register.php?ref=<?= htmlspecialchars($ref_code) ?>" target="_blank" class="px-4 py-3 bg-[#F4A124] text-white rounded text-center">Open</a>
      </div>
      <?php else: ?>
      <div class="text-sm text-gray-600 dark:text-gray-300">No referral link yet.</div>
      <?php endif; ?>
      <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="p-4 bg-gray-50 dark:bg-gray-700 rounded">
          <div class="text-sm text-gray-600 dark:text-gray-300">Total Referrals</div>
          <div class="text-2xl font-bold"><?= number_format($ref_count) ?></div>
        </div>
        <div class="p-4 bg-gray-50 dark:bg-gray-700 rounded">
          <div class="text-sm text-gray-600 dark:text-gray-300">Referral Commission</div>
          <div class="text-2xl font-bold">₦<?= number_format($ref_commission,2) ?></div>
        </div>
      </div>
    </div>
    <div class="bg-white dark:bg-gray-800 p-5 rounded-lg shadow-sm border border-gray-100 dark:border-gray-700">
      <div class="flex items-center justify-between"><span class="text-xs uppercase tracking-wide text-gray-500">Total Users</span><span>👥</span></div>
      <div class="mt-2 text-3xl font-bold"><?= number_format($total_users) ?></div>
    </div>
    <div class="bg-white dark:bg-gray-800 p-5 rounded-lg shadow-sm border border-gray-100 dark:border-gray-700">
      <div class="flex items-center justify-between"><span class="text-xs uppercase tracking-wide text-gray-500">Total Properties</span><span>🏠</span></div>
      <div class="mt-2 text-3xl font-bold"><?= number_format($total_props) ?></div>
      <div class="mt-1 text-xs text-gray-500">Active: <?= number_format($active_props) ?> · Pending: <?= number_format($pending_props) ?></div>
    </div>
    <div class="bg-white dark:bg-gray-800 p-5 rounded-lg shadow-sm border border-gray-100 dark:border-gray-700">
      <div class="flex items-center justify-between"><span class="text-xs uppercase tracking-wide text-gray-500">GMV</span><span>📈</span></div>
      <div class="mt-2 text-3xl font-bold"><?= ngn($gmv_total) ?></div>
      <div class="mt-1 text-xs text-gray-500">Completed payments (gross)</div>
    </div>
    <div class="bg-white dark:bg-gray-800 p-5 rounded-lg shadow-sm border border-gray-100 dark:border-gray-700">
      <div class="flex items-center justify-between"><span class="text-xs uppercase tracking-wide text-gray-500">Platform Earnings</span><span>💸</span></div>
      <div class="mt-2 text-3xl font-bold"><?= ngn($platform_earnings) ?></div>
      <div class="mt-1 text-xs text-gray-500">Completed credits</div>
    </div>
  </div>

  <!-- BOOKINGS KPI GRID -->
  <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
    <div class="bg-white dark:bg-gray-800 p-5 rounded-lg shadow-sm border border-gray-100 dark:border-gray-700">
      <div class="flex items-center justify-between"><span class="text-xs uppercase tracking-wide text-gray-500">Pending Bookings</span><span>🕒</span></div>
      <div class="mt-2 text-3xl font-bold"><?= number_format($book_pending) ?></div>
    </div>
    <div class="bg-white dark:bg-gray-800 p-5 rounded-lg shadow-sm border border-gray-100 dark:border-gray-700">
      <div class="flex items-center justify-between"><span class="text-xs uppercase tracking-wide text-gray-500">Approved Bookings</span><span>✅</span></div>
      <div class="mt-2 text-3xl font-bold"><?= number_format($book_confirmed) ?></div>
    </div>
    <div class="bg-white dark:bg-gray-800 p-5 rounded-lg shadow-sm border border-gray-100 dark:border-gray-700">
      <div class="flex items-center justify-between"><span class="text-xs uppercase tracking-wide text-gray-500">Cancelled Bookings</span><span>❌</span></div>
      <div class="mt-2 text-3xl font-bold"><?= number_format($book_cancelled) ?></div>
    </div>
    <div class="bg-white dark:bg-gray-800 p-5 rounded-lg shadow-sm border border-gray-100 dark:border-gray-700">
      <div class="flex items-center justify-between"><span class="text-xs uppercase tracking-wide text-gray-500">Avg Booking (30d)</span><span>📊</span></div>
      <div class="mt-2 text-3xl font-bold"><?= ngn($avg_booking_value_30d) ?></div>
      <div class="mt-1 text-xs text-gray-500">Average value last 30 days</div>
    </div>
  </div>
<!-- WITHDRAWALS KPI GRID -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-6">
  <div class="bg-white dark:bg-gray-800 p-5 rounded-lg shadow-sm border border-gray-100 dark:border-gray-700">
    <div class="flex items-center justify-between"><span class="text-xs uppercase tracking-wide text-gray-500">WD Requests</span><span>📝</span></div>
    <div class="mt-2 text-3xl font-bold"><?= number_format($wd_counts['requested']) ?></div>
    <div class="mt-1 text-xs text-gray-500">Total: <?= number_format($wd_counts['total']) ?></div>
  </div>
  <div class="bg-white dark:bg-gray-800 p-5 rounded-lg shadow-sm border border-gray-100 dark:border-gray-700">
    <div class="flex items-center justify-between"><span class="text-xs uppercase tracking-wide text-gray-500">WD Processing</span><span>⏳</span></div>
    <div class="mt-2 text-3xl font-bold"><?= number_format($wd_counts['processing']) ?></div>
    <div class="mt-1 text-xs text-gray-500">Pending: <?= number_format($wd_counts['pending']) ?></div>
  </div>
  <div class="bg-white dark:bg-gray-800 p-5 rounded-lg shadow-sm border border-gray-100 dark:border-gray-700">
    <div class="flex items-center justify-between"><span class="text-xs uppercase tracking-wide text-gray-500">WD Completed (30d)</span><span>✅</span></div>
    <div class="mt-2 text-3xl font-bold"><?= ngn($wd_sum_completed_30) ?></div>
    <div class="mt-1 text-xs text-gray-500">All-time: <?= ngn($wd_sum_completed) ?></div>
  </div>
  <div class="bg-white dark:bg-gray-800 p-5 rounded-lg shadow-sm border border-gray-100 dark:border-gray-700">
    <div class="flex items-center justify-between"><span class="text-xs uppercase tracking-wide text-gray-500">WD Failed</span><span>❌</span></div>
    <div class="mt-2 text-3xl font-bold"><?= number_format($wd_counts['failed']) ?></div>
    <div class="mt-1 text-xs text-gray-500">Reversed: <?= number_format($wd_counts['reversed']) ?></div>
  </div>
</div>

  <!-- GROWTH CARDS -->
  <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
    <div class="bg-white dark:bg-gray-800 p-5 rounded-lg shadow-sm border border-gray-100 dark:border-gray-700">
      <div class="flex items-center justify-between">
        <span class="text-xs uppercase tracking-wide text-gray-500">Users (last 30d)</span>
        <span class="text-xs <?= $users_growth>=0?'text-green-600':'text-red-600' ?>"><?= ($users_growth>=0?'+':'').$users_growth ?>%</span>
      </div>
      <div class="mt-2 text-3xl font-bold"><?= number_format($users_30) ?></div>
      <div class="mt-1 text-xs text-gray-500">Prev 30d: <?= number_format($users_prev30) ?></div>
    </div>
    <div class="bg-white dark:bg-gray-800 p-5 rounded-lg shadow-sm border border-gray-100 dark:border-gray-700">
      <div class="flex items-center justify-between">
        <span class="text-xs uppercase tracking-wide text-gray-500">GMV (last 30d)</span>
        <span class="text-xs <?= $gmv_growth>=0?'text-green-600':'text-red-600' ?>"><?= ($gmv_growth>=0?'+':'').$gmv_growth ?>%</span>
      </div>
      <div class="mt-2 text-3xl font-bold"><?= ngn($gmv_30) ?></div>
      <div class="mt-1 text-xs text-gray-500">Prev 30d: <?= ngn($gmv_prev30) ?></div>
    </div>
  </div>

  <!-- CHARTS -->
  <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
    <div class="xl:col-span-2 bg-white dark:bg-gray-800 p-6 rounded-lg shadow-sm border border-gray-100 dark:border-gray-700">
      <h3 class="text-lg md:text-xl font-bold mb-4">Platform Earnings (Last 6 Months)</h3>
      <div class="relative h-64"><canvas id="earningsChart"></canvas></div>
    </div>
    <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-sm border border-gray-100 dark:border-gray-700">
      <h3 class="text-lg md:text-xl font-bold mb-4">Listings by Type</h3>
      <div class="relative h-64"><canvas id="typesChart"></canvas></div>
    </div>
    <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-sm border border-gray-100 dark:border-gray-700">
  <h3 class="text-lg md:text-xl font-bold mb-4">Withdrawals Status Mix</h3>
  <div class="relative h-64"><canvas id="withdrawalsChart"></canvas></div>
</div>

  </div>
  

  <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
    <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-sm border border-gray-100 dark:border-gray-700">
      <h3 class="text-lg md:text-xl font-bold mb-4">Users by Role</h3>
      <div class="relative h-64"><canvas id="rolesChart"></canvas></div>
    </div>
    <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-sm border border-gray-100 dark:border-gray-700">
      <h3 class="text-lg md:text-xl font-bold mb-4">Bookings Status Mix</h3>
      <div class="relative h-64"><canvas id="bookingsChart"></canvas></div>
    </div>
    <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-sm border border-gray-100 dark:border-gray-700">
      <h3 class="text-lg md:text-xl font-bold mb-4">Manage API Key for Automation</h3>
      <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
        The API key is tied to your superadmin account and used by N8n/webhooks. Keep it private.
      </p>
      <form id="api-key-form" method="POST" action="api_key_manager.php" class="space-y-3">
        <div>
          <label for="api-key-display" class="block text-gray-700 dark:text-gray-300 text-sm font-medium mb-1">Current API Key</label>
          <div class="relative">
            <input type="password" id="api-key-display"
                   value="<?= h($apiKey) ?>"
                   readonly
                   class="w-full px-4 py-2 rounded-lg border dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 bg-gray-100 text-gray-700 text-sm font-mono pr-24">
            <div class="absolute inset-y-0 right-0 flex items-center pr-2">
              <button type="button" id="toggle-api-key"
                      class="text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 focus:outline-none p-2"
                      title="Show/Hide">
                <svg xmlns="http://www.w3.org/2000/svg" id="eye-open" class="h-5 w-5 hidden" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                  <circle cx="12" cy="12" r="3"></circle>
                </svg>
                <svg xmlns="http://www.w3.org/2000/svg" id="eye-closed" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.45 18.45 0 0 1-2.94 3.06"></path>
                  <path d="M1 1l22 22"></path>
                  <circle cx="12" cy="12" r="3"></circle>
                </svg>
              </button>
              <button type="button" id="copy-api-key"
                      class="text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 focus:outline-none p-2"
                      title="Copy">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                  <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                </svg>
              </button>
            </div>
          </div>
        </div>
        <div>
          <button type="submit"
                  class="bg-[#092468] text-white px-4 py-2 rounded-lg hover:bg-[#051b47] transition focus:outline-none focus:ring-2 focus:ring-[#092468]">
            Generate New API Key
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- TABLES: Top performers / Top properties / Recent activities -->
  <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
    <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-sm border border-gray-100 dark:border-gray-700">
      <h3 class="text-lg md:text-xl font-bold mb-4">Top Earners (Users)</h3>
      <div class="overflow-x-auto">
        <table class="w-full text-sm border border-gray-200 dark:border-gray-700">
          <thead>
            <tr class="bg-gray-100 dark:bg-gray-700 text-gray-900 dark:text-gray-100">
              <th class="p-3 border">User</th>
              <th class="p-3 border">Role</th>
              <th class="p-3 border">Earnings</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($top_users->num_rows === 0): ?>
              <tr><td colspan="3" class="p-3 border text-center text-gray-500">No data.</td></tr>
            <?php else: while ($tu = $top_users->fetch_assoc()): ?>
              <tr>
                <td class="p-3 border"><?= h($tu['name'].' '.$tu['lname']) ?></td>
                <td class="p-3 border"><?= h(ucfirst(str_replace('_',' ',$tu['role']))) ?></td>
                <td class="p-3 border"><?= ngn($tu['total']) ?></td>
              </tr>
            <?php endwhile; endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-sm border border-gray-100 dark:border-gray-700">
      <h3 class="text-lg md:text-xl font-bold mb-4">Top Properties by Revenue</h3>
      <div class="overflow-x-auto">
        <table class="w-full text-sm border border-gray-200 dark:border-gray-700">
          <thead>
            <tr class="bg-gray-100 dark:bg-gray-700 text-gray-900 dark:text-gray-100">
              <th class="p-3 border">Property</th>
              <th class="p-3 border">Location</th>
              <th class="p-3 border">Total</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($top_properties->num_rows === 0): ?>
              <tr><td colspan="3" class="p-3 border text-center text-gray-500">No data.</td></tr>
            <?php else: while ($tp = $top_properties->fetch_assoc()): ?>
              <tr>
                <td class="p-3 border"><?= h(mb_strimwidth($tp['title'],0,32,'…')) ?></td>
                <td class="p-3 border"><?= h($tp['location']) ?></td>
                <td class="p-3 border"><?= ngn($tp['total']) ?></td>
              </tr>
            <?php endwhile; endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-sm border border-gray-100 dark:border-gray-700">
      <h3 class="text-lg md:text-xl font-bold mb-4">Recent Activities</h3>
      <ul class="space-y-3">
        <?php
        if ($activities->num_rows === 0): ?>
          <li class="text-sm text-gray-500">No recent activity.</li>
        <?php else: while ($a = $activities->fetch_assoc()): ?>
          <li class="text-sm">
            <span class="text-gray-800 dark:text-gray-100 font-medium"><?= h($a['name']) ?></span>
            <span class="text-gray-600 dark:text-gray-400">– <?= h($a['description']) ?></span>
            <span class="block text-[11px] text-gray-400"><?= h(date('M j, Y H:i', strtotime($a['created_at']))) ?></span>
          </li>
        <?php endwhile; endif; ?>
      </ul>
    </div>
  </div>
  
  <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-sm border border-gray-100 dark:border-gray-700">
  <div class="flex items-center justify-between mb-4">
    <h3 class="text-lg md:text-xl font-bold">Recent Withdrawals</h3>
    <a href="admin_withdrawals.php" class="text-sm text-[#092468] underline">View all</a>
  </div>
  <div class="overflow-x-auto">
    <table class="w-full text-sm border border-gray-200 dark:border-gray-700">
      <thead>
        <tr class="bg-gray-100 dark:bg-gray-700 text-gray-900 dark:text-gray-100">
          <th class="p-3 border">User</th>
          <th class="p-3 border">Amount</th>
          <th class="p-3 border">Status</th>
          <th class="p-3 border">Bank</th>
          <th class="p-3 border">Transfer Code</th>
          <th class="p-3 border">Reference</th>
          <th class="p-3 border">Created</th>
          <th class="p-3 border">Updated</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($recent_withdrawals->num_rows === 0): ?>
          <tr><td colspan="8" class="p-3 border text-center text-gray-500">No withdrawals yet.</td></tr>
        <?php else: while ($w = $recent_withdrawals->fetch_assoc()): ?>
          <tr title="<?= h($w['error_message'] ?? '') ?>">
            <td class="p-3 border"><?= h($w['name'].' '.$w['lname']) ?></td>
            <td class="p-3 border"><?= ngn($w['amount']) ?> <span class="text-xs text-gray-500">(<?= h($w['currency']) ?>)</span></td>
            <td class="p-3 border"><?= h(ucfirst($w['status'])) ?></td>
            <td class="p-3 border">
              <?= h($w['bank_name'] ?: '—') ?>
              <span class="text-xs text-gray-500"><?= h($w['account_number'] ? ' ••••'.substr($w['account_number'], -4) : '') ?></span>
            </td>
            <td class="p-3 border"><?= h($w['paystack_transfer_code'] ?: '—') ?></td>
            <td class="p-3 border"><?= h($w['paystack_reference'] ?: '—') ?></td>
            <td class="p-3 border"><?= h(date('M j, Y H:i', strtotime($w['created_at']))) ?></td>
            <td class="p-3 border"><?= h(date('M j, Y H:i', strtotime($w['updated_at']))) ?></td>
          </tr>
        <?php endwhile; endif; ?>
      </tbody>
    </table>
    <p class="text-xs text-gray-500 mt-2">Tip: hover a row to see the last error message (if any).</p>
  </div>
</div>


  <!-- MY (SUPERADMIN) PERSONAL PANELS -->
  <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <div class="bg-white dark:bg-gray-800 p-5 rounded-lg shadow-sm border border-gray-100 dark:border-gray-700">
      <div class="flex items-center justify-between">
        <span class="text-xs uppercase tracking-wide text-gray-500">My Properties</span>
        <span>🧍🏽‍♂️🏠</span>
      </div>
      <div class="mt-2 text-3xl font-bold"><?= number_format($my_props_count) ?></div>
      <div class="mt-1 text-xs text-gray-500">Properties I personally own/listed</div>
    </div>

    <div class="bg-white dark:bg-gray-800 p-5 rounded-lg shadow-sm border border-gray-100 dark:border-gray-700">
      <div class="flex items-center justify-between">
        <span class="text-xs uppercase tracking-wide text-gray-500">Available (Wallet)</span>
        <span>�</span>
      </div>
      <div class="mt-2 text-3xl font-bold"><?= ngn($my_available) ?></div>
      <div class="mt-1 text-xs text-gray-500">Wallet balance</div>
    </div>

    <div class="bg-white dark:bg-gray-800 p-5 rounded-lg shadow-sm border border-gray-100 dark:border-gray-700">
      <div class="flex items-center justify-between">
        <span class="text-xs uppercase tracking-wide text-gray-500">Net Earnings</span>
        <span>🧮</span>
      </div>
      <div class="mt-2 text-3xl font-bold"><?= ngn($my_net) ?></div>
      <div class="mt-1 text-xs text-gray-500">Credits minus completed withdrawals</div>
    </div>
  </div>

  <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-sm border border-gray-100 dark:border-gray-700 mt-2">
    <div class="flex items-center justify-between mb-4">
      <h3 class="text-lg md:text-xl font-bold">My Recent Properties</h3>
      <a href="admin_properties.php?mine=1" class="text-sm text-[#092468] underline">Manage mine</a>
    </div>
    <div class="overflow-x-auto">
      <table class="w-full text-sm border border-gray-200 dark:border-gray-700">
        <thead>
          <tr class="bg-gray-100 dark:bg-gray-700 text-gray-900 dark:text-gray-100">
            <th class="p-3 border">Code</th>
            <th class="p-3 border">Title</th>
            <th class="p-3 border">Type</th>
            <th class="p-3 border">Price</th>
            <th class="p-3 border">Location</th>
            <th class="p-3 border">Status</th>
            <th class="p-3 border">Approved</th>
            <th class="p-3 border">Date</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($my_recent_props->num_rows === 0): ?>
            <tr><td colspan="8" class="p-3 border text-center text-gray-500">You haven’t posted properties yet.</td></tr>
          <?php else: while ($mp = $my_recent_props->fetch_assoc()): ?>
            <tr>
              <td class="p-3 border"><?= h($mp['property_code']) ?></td>
              <td class="p-3 border"><?= h(mb_strimwidth($mp['title'],0,30,'…')) ?></td>
              <td class="p-3 border"><?= h(ucfirst(str_replace('_',' ', $mp['listing_type']))) ?></td>
              <td class="p-3 border"><?= ngn($mp['price']) ?></td>
              <td class="p-3 border"><?= h($mp['location']) ?></td>
              <td class="p-3 border"><?= h(ucfirst($mp['status'])) ?></td>
              <td class="p-3 border"><?= $mp['admin_approved'] ? 'Yes' : 'No' ?></td>
              <td class="p-3 border"><?= h(date('M j, Y', strtotime($mp['created_at']))) ?></td>
            </tr>
          <?php endwhile; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="grid grid-cols-1 xl:grid-cols-2 gap-6 mt-6">
    <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-sm border border-gray-100 dark:border-gray-700">
      <h3 class="text-lg md:text-xl font-bold mb-4">My Recent Payments (on my properties)</h3>
      <div class="overflow-x-auto">
        <table class="w-full text-sm border border-gray-200 dark:border-gray-700">
          <thead>
            <tr class="bg-gray-100 dark:bg-gray-700 text-gray-900 dark:text-gray-100">
              <th class="p-3 border">Property</th>
              <th class="p-3 border">Gateway</th>
              <th class="p-3 border">Status</th>
              <th class="p-3 border">Amount</th>
              <th class="p-3 border">Ref</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($my_recent_payments->num_rows === 0): ?>
              <tr><td colspan="5" class="p-3 border text-center text-gray-500">No payments yet.</td></tr>
            <?php else: while ($p = $my_recent_payments->fetch_assoc()): ?>
              <tr>
                <td class="p-3 border"><?= h(mb_strimwidth($p['property_name'],0,28,'…')) ?></td>
                <td class="p-3 border"><?= h(ucfirst($p['payment_gateway'])) ?></td>
                <td class="p-3 border"><?= h(ucfirst($p['status'])) ?></td>
                <td class="p-3 border"><?= ngn($p['amount']) ?></td>
                <td class="p-3 border"><?= h($p['transaction_id']) ?></td>
              </tr>
            <?php endwhile; endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-sm border border-gray-100 dark:border-gray-700">
      <h3 class="text-lg md:text-xl font-bold mb-4">My Recent Bookings (on my properties)</h3>
      <div class="overflow-x-auto">
        <table class="w-full text-sm border border-gray-200 dark:border-gray-700">
          <thead>
            <tr class="bg-gray-100 dark:bg-gray-700 text-gray-900 dark:text-gray-100">
              <th class="p-3 border">Property</th>
              <th class="p-3 border">Buyer</th>
              <th class="p-3 border">Status</th>
              <th class="p-3 border">Amount</th>
              <th class="p-3 border">Dates</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($my_recent_bookings->num_rows === 0): ?>
              <tr><td colspan="5" class="p-3 border text-center text-gray-500">No bookings yet.</td></tr>
            <?php else: while ($b = $my_recent_bookings->fetch_assoc()): ?>
              <tr>
                <td class="p-3 border"><?= h(mb_strimwidth($b['property_name'],0,28,'…')) ?></td>
                <td class="p-3 border"><?= h($b['buyer_name']) ?></td>
                <td class="p-3 border"><?= h(ucfirst($b['status'])) ?></td>
                <td class="p-3 border"><?= ngn($b['amount']) ?></td>
                <td class="p-3 border">
                  <?= h(date('M j', strtotime($b['check_in_date'] ?? $b['created_at']))) ?> →
                  <?= h(date('M j', strtotime($b['check_out_date'] ?? $b['created_at']))) ?>
                </td>
              </tr>
            <?php endwhile; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const earnLabels = <?= json_encode($earn_labels) ?>;
  const earnData   = <?= json_encode($earn_values) ?>;
  const typeLabels = <?= json_encode($type_labels) ?>;
  const typeData   = <?= json_encode($type_values) ?>;
  const roleLabels = <?= json_encode($role_labels) ?>;
  const roleData   = <?= json_encode($role_values) ?>;
  const bookLabels = <?= json_encode($book_status_labels) ?>;
  const bookData   = <?= json_encode($book_status_values) ?>;
  const wdLabels   = <?= json_encode($wd_status_labels) ?>;
  const wdData     = <?= json_encode($wd_status_values) ?>;

  // Helper: build chart only if canvas exists
  function buildChart(id, config) {
    const el = document.getElementById(id);
    if (!el) return;
    const ctx = el.getContext('2d');
    if (!ctx) return;
    return new Chart(ctx, config);
  }

  // Earnings (bar)
  buildChart('earningsChart', {
    type: 'bar',
    data: {
      labels: (earnLabels && earnLabels.length) ? earnLabels : ['No Data'],
      datasets: [{
        label: 'Earnings (₦)',
        data: (earnData && earnData.length) ? earnData : [0],
        backgroundColor: 'rgba(9,36,104,0.6)',
        borderColor: 'rgba(9,36,104,1)',
        borderWidth: 1,
        hoverBackgroundColor: 'rgba(204,153,51,0.8)'
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      scales: {
        y: {
          beginAtZero: true,
          ticks: { callback: v => '₦' + Number(v).toLocaleString() },
          grid: { color: 'rgba(229,231,235,0.3)' }
        },
        x: { grid: { display: false } }
      },
      plugins: {
        legend: { display: false },
        tooltip: { callbacks: { label: ctx => `₦${Number(ctx.raw ?? 0).toLocaleString()}` } }
      }
    }
  });

  // Listing types (doughnut)
  buildChart('typesChart', {
    type: 'doughnut',
    data: {
      labels: typeLabels || [],
      datasets: [{
        data: typeData || [],
        backgroundColor: [
          'rgba(9,36,104,0.8)','rgba(204,153,51,0.8)','rgba(99,102,241,0.8)',
          'rgba(16,185,129,0.8)','rgba(234,179,8,0.8)','rgba(244,63,94,0.8)',
          'rgba(59,130,246,0.8)','rgba(139,92,246,0.8)','rgba(34,197,94,0.8)'
        ],
        borderWidth: 0
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      cutout: '60%',
      plugins: { legend: { position: 'bottom' } }
    }
  });

  // Users by role (pie)
  buildChart('rolesChart', {
    type: 'pie',
    data: {
      labels: roleLabels || [],
      datasets: [{
        data: roleData || [],
        backgroundColor: [
          'rgba(59,130,246,0.8)','rgba(16,185,129,0.8)','rgba(234,179,8,0.8)',
          'rgba(244,63,94,0.8)','rgba(99,102,241,0.8)','rgba(139,92,246,0.8)',
          'rgba(9,36,104,0.8)','rgba(204,153,51,0.8)'
        ],
        borderWidth: 0
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: { legend: { position: 'bottom' } }
    }
  });

  // Bookings status mix (pie)
  buildChart('bookingsChart', {
    type: 'pie',
    data: {
      labels: bookLabels || [],
      datasets: [{
        data: bookData || [],
        backgroundColor: [
          'rgba(234,179,8,0.85)',   // pending
          'rgba(16,185,129,0.85)',  // confirmed
          'rgba(244,63,94,0.85)'    // cancelled
        ],
        borderWidth: 0
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: { legend: { position: 'bottom' } }
    }
  });

  // Withdrawals status mix (pie)
  buildChart('withdrawalsChart', {
    type: 'pie',
    data: {
      labels: wdLabels || [],
      datasets: [{
        data: wdData || [],
        backgroundColor: [
          'rgba(59,130,246,0.85)',   // requested
          'rgba(234,179,8,0.85)',    // pending
          'rgba(99,102,241,0.85)',   // processing
          'rgba(16,185,129,0.85)',   // completed
          'rgba(244,63,94,0.85)',    // failed
          'rgba(168,85,247,0.85)',   // reversed
          'rgba(107,114,128,0.85)'   // canceled
        ],
        borderWidth: 0
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: { legend: { position: 'bottom' } }
    }
  });

  // API key UI actions
  const apiKeyInput  = document.getElementById('api-key-display');
  const toggleButton = document.getElementById('toggle-api-key');
  const copyButton   = document.getElementById('copy-api-key');
  const eyeOpen      = document.getElementById('eye-open');
  const eyeClosed    = document.getElementById('eye-closed');

  toggleButton?.addEventListener('click', () => {
    if (!apiKeyInput) return;
    if (apiKeyInput.type === 'password') {
      apiKeyInput.type = 'text';
      eyeOpen?.classList.remove('hidden');
      eyeClosed?.classList.add('hidden');
    } else {
      apiKeyInput.type = 'password';
      eyeOpen?.classList.add('hidden');
      eyeClosed?.classList.remove('hidden');
    }
  });

  copyButton?.addEventListener('click', () => {
    if (!apiKeyInput) return;
    apiKeyInput.select();
    apiKeyInput.setSelectionRange(0, 99999);
    navigator.clipboard.writeText(apiKeyInput.value).then(() => {
      if (window.Swal) Swal.fire({ icon:'success', title:'Copied!', text:'API key copied.', timer:1500, showConfirmButton:false });
    }).catch(console.error);
  });

  document.getElementById('api-key-form')?.addEventListener('submit', (e) => {
    e.preventDefault();
    const form = e.target;
    const formData = new FormData(form);
    fetch(form.action, { method:'POST', body: formData })
      .then(r => r.json())
      .then(data => {
        if (data.status === 'success') {
          if (apiKeyInput) {
            apiKeyInput.value = data.apiKey;
            apiKeyInput.type = 'password';
            eyeOpen?.classList.add('hidden');
            eyeClosed?.classList.remove('hidden');
          }
          if (window.Swal) Swal.fire({ icon:'success', title:'Success!', text:data.message, timer:2500, showConfirmButton:false });
        } else {
          if (window.Swal) Swal.fire({ icon:'error', title:'Error', text:data.message || 'Unable to generate API key.' });
        }
      })
      .catch(() => {
        if (window.Swal) Swal.fire({ icon:'error', title:'Network Error', text:'Could not reach the server.' });
      });
  });
});
</script>

<?php
// File: dashboard/admin_dashboard_content.php

// Safety: if not already required by layout, include once.
require_once '../includes/db_connect.php';

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

// Helpers
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function ngn($n){ return '₦'.number_format((float)$n, 2); }

// ---------- GLOBAL METRICS ----------
$total_users = (int)($conn->query("SELECT COUNT(*) c FROM users")->fetch_assoc()['c'] ?? 0);
$total_props = (int)($conn->query("SELECT COUNT(*) c FROM properties")->fetch_assoc()['c'] ?? 0);
$active_listings = (int)($conn->query("SELECT COUNT(*) c FROM properties WHERE admin_approved=1")->fetch_assoc()['c'] ?? 0);
$pending_approvals = (int)($conn->query("SELECT COUNT(*) c FROM properties WHERE admin_approved=0")->fetch_assoc()['c'] ?? 0);

// Platform earnings (completed credits)
$total_earnings = (float)($conn->query("
  SELECT COALESCE(SUM(amount),0) s
  FROM transactions
  WHERE status='completed' AND type='credit'
")->fetch_assoc()['s'] ?? 0);

// Wallet float (sum of user wallets)
$wallet_float = (float)($conn->query("SELECT COALESCE(SUM(balance),0) s FROM wallets")->fetch_assoc()['s'] ?? 0);

// Bookings/payments snapshots
$pending_bookings = (int)($conn->query("SELECT COUNT(*) c FROM bookings WHERE status='pending'")->fetch_assoc()['c'] ?? 0);
$completed_payments = (int)($conn->query("SELECT COUNT(*) c FROM payments WHERE status='completed'")->fetch_assoc()['c'] ?? 0);

// System settings (first row)
$settings = $conn->query("SELECT commission, site_status FROM system_settings ORDER BY id ASC LIMIT 1")->fetch_assoc()
            ?: ['commission'=>0, 'site_status'=>'active'];

// ---------- CHART DATA (last 6 months earnings) ----------
$earn_labels = []; $earn_values = [];
$stmt = $conn->prepare("
    SELECT DATE_FORMAT(created_at, '%Y-%m') ym, COALESCE(SUM(amount),0) s
    FROM transactions
    WHERE status='completed' AND type='credit'
      AND created_at >= DATE_SUB(CURRENT_DATE(), INTERVAL 6 MONTH)
    GROUP BY ym
    ORDER BY ym ASC
");
$stmt->execute();
$res = $stmt->get_result();
$series = [];
while ($r = $res->fetch_assoc()) { $series[$r['ym']] = (float)$r['s']; }
$stmt->close();

// Normalize to 6 buckets (current month inclusive)
for ($i=5; $i>=0; $i--) {
    $k = date('Y-m', strtotime("-$i months"));
    $earn_labels[] = date('M', strtotime($k.'-01'));
    $earn_values[] = $series[$k] ?? 0.0;
}

// Listing type breakdown
$type_labels = []; $type_values = [];
$types_res = $conn->query("SELECT listing_type, COUNT(*) c FROM properties GROUP BY listing_type");
while ($r = $types_res->fetch_assoc()) {
    $type_labels[] = ucfirst(str_replace('_',' ', $r['listing_type']));
    $type_values[] = (int)$r['c'];
}

// ---------- TABLE SOURCES (global) ----------
$recent_users = $conn->query("
    SELECT id, name, lname, email, role, created_at
    FROM users
    ORDER BY created_at DESC
    LIMIT 6
");

$pending_props = $conn->query("
    SELECT id, property_code, title, location, listing_type, price, created_at
    FROM properties
    WHERE admin_approved=0
    ORDER BY created_at DESC
    LIMIT 8
");

$recent_bookings = $conn->query("
    SELECT b.id, b.status, b.amount, b.created_at, b.check_in_date, b.check_out_date,
           u.name AS buyer_name, p.title AS property_name
    FROM bookings b
    JOIN users u ON u.id = b.user_id
    JOIN properties p ON p.id = b.property_id
    ORDER BY b.created_at DESC
    LIMIT 6
");

$recent_payments = $conn->query("
    SELECT pay.id, pay.amount, pay.status, pay.created_at, pay.payment_gateway, pay.transaction_id,
           p.title AS property_name, u.name AS payer_name
    FROM payments pay
    JOIN properties p ON p.id = pay.property_id
    JOIN users u ON u.id = pay.user_id
    ORDER BY pay.created_at DESC
    LIMIT 6
");

// ---------- MY (ADMIN) PERSONAL VIEW ----------
$me_id = (int)($_SESSION['user_id'] ?? 0);

// Referral stats
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

// My properties count
$stmt = $conn->prepare("SELECT COUNT(*) AS c FROM properties WHERE owner_id = ?");
$stmt->bind_param("i", $me_id);
$stmt->execute();
$my_props_count = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
$stmt->close();

// My earnings (completed credits)
$stmt = $conn->prepare("
  SELECT COALESCE(SUM(amount),0) AS s
  FROM transactions
  WHERE user_id = ? AND status='completed' AND type='credit'
");
$stmt->bind_param("i", $me_id);
$stmt->execute();
$my_earnings = (float)($stmt->get_result()->fetch_assoc()['s'] ?? 0.0);
$stmt->close();

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
?>

<div class="mt-6 space-y-6">
  <div class="flex justify-between items-start gap-4">
    <div>
      <h2 class="text-2xl md:text-3xl font-bold text-gray-800 dark:text-gray-200">Admin Dashboard</h2>
      <p class="text-gray-600 dark:text-gray-400 mt-1">Overview of platform activities and performance.</p>
      <p class="text-xs text-gray-500 mt-1">
        Site status: <span class="font-semibold"><?= h(strtoupper($settings['site_status'])) ?></span> ·
        Commission: <span class="font-semibold"><?= number_format((float)$settings['commission'], 2) ?>%</span>
      </p>
    </div>
    <!-- Optional CTA
    <a href="zoho_auth.php"
       class="hidden md:inline-block bg-[#092468] text-white px-4 py-2 rounded-lg hover:bg-[#051b47] transition">
       Connect Zoho CRM
    </a>
    -->
  </div>

  <!-- GLOBAL KPI GRID -->
  <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
    <div class="bg-white dark:bg-gray-800 p-5 rounded-lg shadow-sm border border-gray-100 dark:border-gray-700">
      <div class="flex items-center justify-between">
        <span class="text-xs uppercase tracking-wide text-gray-500">Total Users</span>
        <span aria-hidden="true">👥</span>
      </div>
      <div class="mt-2 text-3xl font-bold"><?= number_format($total_users) ?></div>
    </div>

    <div class="bg-white dark:bg-gray-800 p-5 rounded-lg shadow-sm border border-gray-100 dark:border-gray-700">
      <div class="flex items-center justify-between">
        <span class="text-xs uppercase tracking-wide text-gray-500">Properties</span>
        <span aria-hidden="true">🏠</span>
      </div>
      <div class="mt-2 text-3xl font-bold"><?= number_format($total_props) ?></div>
      <div class="mt-1 text-xs text-gray-500">
        Active: <?= number_format($active_listings) ?> · Pending: <?= number_format($pending_approvals) ?>
      </div>
    </div>

    <div class="bg-white dark:bg-gray-800 p-5 rounded-lg shadow-sm border border-gray-100 dark:border-gray-700">
      <div class="flex items-center justify-between">
        <span class="text-xs uppercase tracking-wide text-gray-500">Earnings</span>
        <span aria-hidden="true">💸</span>
      </div>
      <div class="mt-2 text-3xl font-bold"><?= ngn($total_earnings) ?></div>
      <div class="mt-1 text-xs text-gray-500">Completed credits</div>
    </div>

    <div class="bg-white dark:bg-gray-800 p-5 rounded-lg shadow-sm border border-gray-100 dark:border-gray-700">
      <div class="flex items-center justify-between">
        <span class="text-xs uppercase tracking-wide text-gray-500">Wallet Float</span>
        <span aria-hidden="true">👛</span>
      </div>
      <div class="mt-2 text-3xl font-bold"><?= ngn($wallet_float) ?></div>
      <div class="mt-1 text-xs text-gray-500">Sum of user wallets</div>
    </div>
  </div>

  <!-- MY SUMMARY (Admin's own data) -->
  <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <div class="bg-white dark:bg-gray-800 p-5 rounded-lg shadow-sm border border-gray-100 dark:border-gray-700">
      <div class="flex items-center justify-between">
        <span class="text-xs uppercase tracking-wide text-gray-500">My Properties</span>
        <span aria-hidden="true">🧍🏽‍♂️🏠</span>
      </div>
      <div class="mt-2 text-3xl font-bold"><?= number_format($my_props_count) ?></div>
      <div class="mt-1 text-xs text-gray-500">Properties I personally own/listed</div>
    </div>

    <div class="bg-white dark:bg-gray-800 p-5 rounded-lg shadow-sm border border-gray-100 dark:border-gray-700">
      <div class="flex items-center justify-between">
        <span class="text-xs uppercase tracking-wide text-gray-500">My Earnings</span>
        <span aria-hidden="true">🧍🏽‍♂️💸</span>
      </div>
      <div class="mt-2 text-3xl font-bold"><?= ngn($my_earnings) ?></div>
      <div class="mt-1 text-xs text-gray-500">Completed credits to my account</div>
    </div>
  </div>

  <!-- CHARTS -->
  <!-- Earnings (Last 6 Months) -->
<div class="xl:col-span-2 bg-white dark:bg-gray-800 p-6 rounded-lg shadow-sm border border-gray-100 dark:border-gray-700">
  <h3 class="text-lg md:text-xl font-bold mb-4">Earnings (Last 6 Months)</h3>
  <div class="relative h-64 md:h-80">
    <canvas id="earningsChart" class="absolute inset-0"></canvas>
  </div>
</div>

<!-- Listings by Type -->
<div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-sm border border-gray-100 dark:border-gray-700">
  <h3 class="text-lg md:text-xl font-bold mb-4">Listings by Type</h3>
  <div class="relative h-64 md:h-80">
    <canvas id="typesChart" class="absolute inset-0"></canvas>
  </div>
</div>

  <!-- TABLES ROW 1: Pending Approvals & Recent Users -->
  <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
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
    <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-sm border border-gray-100 dark:border-gray-700">
      <div class="flex items-center justify-between mb-4">
        <h3 class="text-lg md:text-xl font-bold">Pending Property Approvals</h3>
        <a href="admin_properties.php" class="text-sm text-[#092468] underline">Manage</a>
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
              <th class="p-3 border">Date</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($pending_props->num_rows === 0): ?>
              <tr><td colspan="6" class="p-3 border text-center text-gray-500">No pending approvals.</td></tr>
            <?php else: while ($p = $pending_props->fetch_assoc()): ?>
              <tr>
                <td class="p-3 border"><?= h($p['property_code']) ?></td>
                <td class="p-3 border"><?= h(mb_strimwidth($p['title'],0,30,'…')) ?></td>
                <td class="p-3 border"><?= h(ucfirst(str_replace('_',' ', $p['listing_type']))) ?></td>
                <td class="p-3 border"><?= ngn($p['price']) ?></td>
                <td class="p-3 border"><?= h($p['location']) ?></td>
                <td class="p-3 border"><?= h(date('M j, Y', strtotime($p['created_at']))) ?></td>
              </tr>
            <?php endwhile; endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-sm border border-gray-100 dark:border-gray-700">
      <div class="flex items-center justify-between mb-4">
        <h3 class="text-lg md:text-xl font-bold">Recent Users</h3>
        <a href="admin_users.php" class="text-sm text-[#092468] underline">Manage</a>
      </div>
      <div class="overflow-x-auto">
        <table class="w-full text-sm border border-gray-200 dark:border-gray-700">
          <thead>
            <tr class="bg-gray-100 dark:bg-gray-700 text-gray-900 dark:text-gray-100">
              <th class="p-3 border">Name</th>
              <th class="p-3 border">Email</th>
              <th class="p-3 border">Role</th>
              <th class="p-3 border">Joined</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($recent_users->num_rows === 0): ?>
              <tr><td colspan="4" class="p-3 border text-center text-gray-500">No users found.</td></tr>
            <?php else: while ($u = $recent_users->fetch_assoc()): ?>
              <tr>
                <td class="p-3 border"><?= h($u['name'].' '.$u['lname']) ?></td>
                <td class="p-3 border"><?= h($u['email']) ?></td>
                <td class="p-3 border"><?= h(ucfirst($u['role'])) ?></td>
                <td class="p-3 border"><?= h(date('M j, Y', strtotime($u['created_at']))) ?></td>
              </tr>
            <?php endwhile; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- TABLES ROW 2: Global Bookings & Payments -->
  <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
    <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-sm border border-gray-100 dark:border-gray-700">
      <div class="flex items-center justify-between mb-4">
        <h3 class="text-lg md:text-xl font-bold">Recent Bookings</h3>
        <span class="text-xs text-gray-500">Pending: <?= number_format($pending_bookings) ?></span>
      </div>
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
            <?php if ($recent_bookings->num_rows === 0): ?>
              <tr><td colspan="5" class="p-3 border text-center text-gray-500">No bookings found.</td></tr>
            <?php else: while ($b = $recent_bookings->fetch_assoc()): ?>
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

    <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-sm border border-gray-100 dark:border-gray-700">
      <div class="flex items-center justify-between mb-4">
        <h3 class="text-lg md:text-xl font-bold">Recent Payments</h3>
        <span class="text-xs text-gray-500">Completed: <?= number_format($completed_payments) ?></span>
      </div>
      <div class="overflow-x-auto">
        <table class="w-full text-sm border border-gray-200 dark:border-gray-700">
          <thead>
            <tr class="bg-gray-100 dark:bg-gray-700 text-gray-900 dark:text-gray-100">
              <th class="p-3 border">Property</th>
              <th class="p-3 border">Payer</th>
              <th class="p-3 border">Gateway</th>
              <th class="p-3 border">Status</th>
              <th class="p-3 border">Amount</th>
              <th class="p-3 border">Ref</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($recent_payments->num_rows === 0): ?>
              <tr><td colspan="6" class="p-3 border text-center text-gray-500">No payments found.</td></tr>
            <?php else: while ($p = $recent_payments->fetch_assoc()): ?>
              <tr>
                <td class="p-3 border"><?= h(mb_strimwidth($p['property_name'],0,28,'…')) ?></td>
                <td class="p-3 border"><?= h($p['payer_name']) ?></td>
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
  </div>

  <!-- MY TABLES: My Properties, My Payments, My Bookings -->
  <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-sm border border-gray-100 dark:border-gray-700 mt-6">
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
  // Destroy previous charts if this script runs more than once (e.g., hot reload, partial loads)
  if (!window._pishonCharts) window._pishonCharts = [];
  window._pishonCharts.forEach(c => { try { c.destroy(); } catch(e){} });
  window._pishonCharts = [];

  const earnLabels = <?= json_encode($earn_labels) ?>;
  const earnData   = <?= json_encode($earn_values) ?>;
  const typeLabels = <?= json_encode($type_labels) ?>;
  const typeData   = <?= json_encode($type_values) ?>;

  // Earnings chart (bar)
  const ecEl = document.getElementById('earningsChart');
  if (ecEl) {
    const ec = new Chart(ecEl.getContext('2d'), {
      type: 'bar',
      data: {
        labels: earnLabels.length ? earnLabels : ['No Data'],
        datasets: [{
          label: 'Earnings (₦)',
          data: earnData.length ? earnData : [0],
          backgroundColor: 'rgba(9,36,104,0.6)',
          borderColor: 'rgba(9,36,104,1)',
          borderWidth: 1,
          hoverBackgroundColor: 'rgba(204,153,51,0.8)',
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false, // <-- respect wrapper height
        animation: { duration: 300 },
        scales: {
          y: {
            beginAtZero: true,
            ticks: { callback: (v) => '₦' + Number(v).toLocaleString() },
            grid: { color: 'rgba(0,0,0,0.05)' }
          },
          x: { grid: { display: false } }
        },
        plugins: {
          tooltip: { callbacks: { label: (ctx) => `₦${Number(ctx.raw).toLocaleString()}` } },
          legend: { labels: { color: '#092468' } }
        }
      }
    });
    window._pishonCharts.push(ec);
  }

  // Listing types chart (doughnut)
  const tcEl = document.getElementById('typesChart');
  if (tcEl) {
    const tc = new Chart(tcEl.getContext('2d'), {
      type: 'doughnut',
      data: {
        labels: typeLabels.length ? typeLabels : ['No Data'],
        datasets: [{
          data: typeData.length ? typeData : [1],
          backgroundColor: [
            'rgba(9,36,104,0.8)','rgba(204,153,51,0.8)','rgba(99,102,241,0.8)',
            'rgba(16,185,129,0.8)','rgba(234,179,8,0.8)','rgba(244,63,94,0.8)'
          ],
          borderWidth: 0
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false, // <-- respect wrapper height
        cutout: '60%',
        plugins: { legend: { position: 'bottom' } }
      }
    });
    window._pishonCharts.push(tc);
  }
});
</script>

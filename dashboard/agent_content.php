<?php
// Precondition: session started & role authorized in agent_dashboard.php
require_once '../includes/db_connect.php';

$user_id  = $_SESSION['user_id'];
$user_role = $_SESSION['role']; // 'agent' | 'owner' | 'hotel_owner' | 'developer' | 'host'

// Helpers
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function money_ngn($n){ return '₦'.number_format((float)$n, 2); }

// Filters / pagination for inquiries
$search = trim($_GET['q'] ?? '');
$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 10;
$offset = ($page - 1) * $limit;

/* ===== KPIs ===== */

// Total properties owned/listed by this user
$stmt = $conn->prepare("SELECT COUNT(*) AS c FROM properties WHERE owner_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$total_properties = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
$stmt->close();

// Pending inquiries (UNREAD) -> receiver_id only (role filter removed; schema lacks 'developer','host')
$stmt = $conn->prepare("SELECT COUNT(*) AS c FROM messages WHERE receiver_id = ? AND status='unread'");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$pending_inquiries = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
$stmt->close();

// Total earnings (transactions -> credits completed)
$stmt = $conn->prepare("
    SELECT COALESCE(SUM(amount),0) AS s
    FROM transactions
    WHERE user_id = ? AND status='completed' AND type='credit'
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$total_earnings = (float)($stmt->get_result()->fetch_assoc()['s'] ?? 0.0);
$stmt->close();

// Wallet balance
$stmt = $conn->prepare("SELECT COALESCE(balance,0) AS bal FROM wallets WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$wallet_balance = (float)($stmt->get_result()->fetch_assoc()['bal'] ?? 0.0);
$stmt->close();

// Properties by status (for mini breakdown)
$props_by_status = [];
$stmt = $conn->prepare("
    SELECT status, COUNT(*) AS c
    FROM properties
    WHERE owner_id = ?
    GROUP BY status
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) { $props_by_status[$row['status']] = (int)$row['c']; }
$stmt->close();

// Earnings by month (last 6 months, credits)
$earn_series = [];
$stmt = $conn->prepare("
    SELECT DATE_FORMAT(created_at, '%Y-%m') AS ym, COALESCE(SUM(amount),0) AS s
    FROM transactions
    WHERE user_id = ? AND status='completed' AND type='credit'
      AND created_at >= DATE_SUB(CURRENT_DATE(), INTERVAL 6 MONTH)
    GROUP BY ym
    ORDER BY ym ASC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) { $earn_series[$row['ym']] = (float)$row['s']; }
$stmt->close();

// Normalize last 6 buckets even if empty
$months = [];
for ($i=5; $i>=0; $i--) {
    $k = date('Y-m', strtotime("-$i months"));
    $months[$k] = $earn_series[$k] ?? 0.0;
}

/* ===== Recent Inquiries (search + pagination) ===== */
$params = [$user_id];
$types  = "i";
$inq_base_sql = "
    FROM messages m
    LEFT JOIN users u ON m.sender_id = u.id
    LEFT JOIN properties p ON m.property_id = p.id
    WHERE m.receiver_id = ?
";
if ($search !== '') {
    $inq_base_sql .= " AND (u.name LIKE CONCAT('%', ?, '%') OR p.title LIKE CONCAT('%', ?, '%') OR m.message LIKE CONCAT('%', ?, '%'))";
    $params[] = $search; $params[] = $search; $params[] = $search;
    $types .= "sss";
}

$count_sql = "SELECT COUNT(*) AS c " . $inq_base_sql;
$stmt = $conn->prepare($count_sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$total_inquiries = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
$stmt->close();

$list_sql = "
    SELECT m.id, m.message, m.created_at,
           u.name AS buyer_name,
           p.title AS property_name
    " . $inq_base_sql . "
    ORDER BY m.created_at DESC
    LIMIT ? OFFSET ?
";
$params2 = $params;
$params2[] = $limit; $params2[] = $offset;
$types2 = $types."ii";

$stmt = $conn->prepare($list_sql);
$stmt->bind_param($types2, ...$params2);
$stmt->execute();
$inquiries = $stmt->get_result();
$stmt->close();

/* ===== Recent Bookings for my properties ===== */
$stmt = $conn->prepare("
    SELECT b.id, b.status, b.amount, b.created_at, b.check_in_date, b.check_out_date,
           u.name AS buyer_name, p.title AS property_name
    FROM bookings b
    JOIN properties p ON p.id = b.property_id
    JOIN users u ON u.id = b.user_id
    WHERE p.owner_id = ?
    ORDER BY b.created_at DESC
    LIMIT 5
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$recent_bookings = $stmt->get_result();
$stmt->close();

/* ===== Recent Payments for my properties ===== */
$stmt = $conn->prepare("
    SELECT pay.id, pay.amount, pay.status, pay.created_at, pay.payment_gateway, pay.transaction_id,
           p.title AS property_name
    FROM payments pay
    JOIN properties p ON p.id = pay.property_id
    WHERE p.owner_id = ?
    ORDER BY pay.created_at DESC
    LIMIT 5
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$recent_payments = $stmt->get_result();
$stmt->close();

/* ===== My Tasks ===== */
$stmt = $conn->prepare("
    SELECT id, title, status, due_date, created_at
    FROM tasks
    WHERE assigned_to_user_id = ?
    ORDER BY (status!='completed'), due_date ASC, created_at DESC
    LIMIT 5
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$tasks = $stmt->get_result();
$stmt->close();

/* ===== My Activities feed ===== */
$stmt = $conn->prepare("
    SELECT description, created_at
    FROM activities
    WHERE user_id = ?
    ORDER BY created_at DESC
    LIMIT 5
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$activities = $stmt->get_result();
$stmt->close();

// Pagination helpers
$total_pages = max(1, (int)ceil($total_inquiries / $limit));
function page_url($n, $search){
    $q = [];
    if ($search !== '') $q['q'] = $search;
    $q['page'] = $n;
    return '?'.http_build_query($q);
}
?>

<div class="mt-6 space-y-6">
    <div>
        <h2 class="text-2xl font-bold text-gray-800 dark:text-gray-200">Welcome, <?= h($_SESSION['name']) ?></h2>
        <p class="text-gray-600 dark:text-gray-400">Manage your listings, inquiries, bookings, and earnings.</p>
    </div>

    <!-- KPI Row -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
        <div class="bg-white dark:bg-gray-800 p-6 rounded shadow-md">
            <div class="flex items-center justify-between">
                <h3 class="text-xs uppercase tracking-wide text-gray-500">Total Properties</h3>
                <span aria-hidden="true">🏠</span>
            </div>
            <p class="mt-2 text-3xl font-bold"><?= number_format($total_properties) ?></p>
            <div class="mt-2 text-xs text-gray-500">
                <?php
                $labels = ['pending','available','reserved','booked','sold','rented'];
                $bits = [];
                foreach ($labels as $st) {
                    if (!empty($props_by_status[$st])) $bits[] = ucfirst($st).": ".$props_by_status[$st];
                }
                echo $bits ? h(implode(' · ', $bits)) : '—';
                ?>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 p-6 rounded shadow-md">
            <div class="flex items-center justify-between">
                <h3 class="text-xs uppercase tracking-wide text-gray-500">Pending Inquiries</h3>
                <span aria-hidden="true">📩</span>
            </div>
            <p class="mt-2 text-3xl font-bold"><?= number_format($pending_inquiries) ?></p>
            <div class="mt-2 text-xs text-gray-500">Unread messages</div>
        </div>

        <div class="bg-white dark:bg-gray-800 p-6 rounded shadow-md">
            <div class="flex items-center justify-between">
                <h3 class="text-xs uppercase tracking-wide text-gray-500">Total Earnings</h3>
                <span aria-hidden="true">💸</span>
            </div>
            <p class="mt-2 text-3xl font-bold"><?= money_ngn($total_earnings) ?></p>
            <div class="mt-2 text-xs text-gray-500">Completed credits</div>
        </div>

        <div class="bg-white dark:bg-gray-800 p-6 rounded shadow-md">
            <div class="flex items-center justify-between">
                <h3 class="text-xs uppercase tracking-wide text-gray-500">Wallet Balance</h3>
                <span aria-hidden="true">👛</span>
            </div>
            <p class="mt-2 text-3xl font-bold"><?= money_ngn($wallet_balance) ?></p>
            <div class="mt-2 text-xs text-gray-500">Available</div>
        </div>
    </div>

    <!-- Earnings by Month (mini bars) -->
    <div class="bg-white dark:bg-gray-800 p-6 rounded shadow-md">
        <h3 class="text-xl font-bold mb-4">Earnings (Last 6 Months)</h3>
        <div class="flex items-end gap-3 h-32">
            <?php foreach ($months as $ym => $amt): ?>
                <?php
                    $max = max(1.0, max($months)); // avoid div by zero
                    $height = (int)round(($amt / $max) * 100);
                ?>
                <div class="flex flex-col items-center">
                    <div class="w-8 bg-[#092468] rounded-t" style="height: <?= $height ?>%;"></div>
                    <div class="mt-1 text-[10px] text-gray-500"><?= h(date('M', strtotime($ym.'-01'))) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Inquiries: search + pagination -->
    <div class="bg-white dark:bg-gray-800 p-6 rounded shadow-md">
        <div class="flex items-center justify-between gap-4 mb-4">
            <h3 class="text-xl font-bold">Recent Inquiries</h3>
            <form method="get" class="ml-auto">
                <input type="text" name="q" value="<?= h($search) ?>" placeholder="Search inquiries..."
                       class="w-64 p-2 border rounded focus:outline-none focus:ring-2 focus:ring-[#092468]">
                <button class="ml-2 px-3 py-2 bg-[#092468] text-white rounded">Search</button>
            </form>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full border-collapse border border-gray-200 dark:border-gray-700 text-sm">
                <thead>
                <tr class="bg-gray-100 dark:bg-gray-700 text-gray-900 dark:text-gray-300">
                    <th class="p-3 border">Buyer</th>
                    <th class="p-3 border">Property</th>
                    <th class="p-3 border">Message</th>
                    <th class="p-3 border">Date</th>
                </tr>
                </thead>
                <tbody>
                <?php if ($total_inquiries === 0): ?>
                    <tr><td colspan="4" class="p-3 border text-center text-gray-500">No inquiries found.</td></tr>
                <?php else: ?>
                    <?php while ($r = $inquiries->fetch_assoc()): ?>
                        <tr>
                            <td class="p-3 border"><?= h($r['buyer_name'] ?? '—') ?></td>
                            <td class="p-3 border"><?= h($r['property_name'] ?? '—') ?></td>
                            <td class="p-3 border"><?= h(mb_strimwidth($r['message'] ?? '', 0, 80, '…')) ?></td>
                            <td class="p-3 border"><?= h(date('F j, Y', strtotime($r['created_at']))) ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($total_pages > 1): ?>
            <div class="flex justify-end items-center gap-2 mt-4">
                <a class="px-3 py-2 border rounded <?= $page<=1?'opacity-50 pointer-events-none':'' ?>" href="<?= h(page_url(max(1,$page-1), $search)) ?>">Prev</a>
                <span class="text-sm text-gray-600">Page <?= $page ?> of <?= $total_pages ?></span>
                <a class="px-3 py-2 border rounded <?= $page>=$total_pages?'opacity-50 pointer-events-none':'' ?>" href="<?= h(page_url(min($total_pages,$page+1), $search)) ?>">Next</a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Two-column: Bookings & Payments -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="bg-white dark:bg-gray-800 p-6 rounded shadow-md">
            <h3 class="text-xl font-bold mb-4">Recent Bookings (Your Properties)</h3>
            <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                <?php if ($recent_bookings->num_rows === 0): ?>
                    <li class="py-3 text-gray-500">No bookings yet.</li>
                <?php else: ?>
                    <?php while ($b = $recent_bookings->fetch_assoc()): ?>
                        <li class="py-3">
                            <div class="flex justify-between items-center">
                                <div>
                                    <div class="font-medium"><?= h($b['property_name']) ?></div>
                                    <div class="text-xs text-gray-500">
                                        <?= h($b['buyer_name']) ?> · <?= h(ucfirst($b['status'])) ?> ·
                                        <?= h(date('M j', strtotime($b['check_in_date']))) ?> →
                                        <?= h(date('M j', strtotime($b['check_out_date']))) ?>
                                    </div>
                                </div>
                                <div class="text-sm"><?= money_ngn($b['amount']) ?></div>
                            </div>
                        </li>
                    <?php endwhile; ?>
                <?php endif; ?>
            </ul>
        </div>

        <div class="bg-white dark:bg-gray-800 p-6 rounded shadow-md">
            <h3 class="text-xl font-bold mb-4">Recent Payments (Your Properties)</h3>
            <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                <?php if ($recent_payments->num_rows === 0): ?>
                    <li class="py-3 text-gray-500">No payments yet.</li>
                <?php else: ?>
                    <?php while ($p = $recent_payments->fetch_assoc()): ?>
                        <li class="py-3">
                            <div class="flex justify-between items-center">
                                <div>
                                    <div class="font-medium"><?= h($p['property_name']) ?></div>
                                    <div class="text-xs text-gray-500">
                                        <?= h(strtoupper($p['payment_gateway'])) ?> · <?= h(ucfirst($p['status'])) ?> · <?= h(date('M j, Y', strtotime($p['created_at']))) ?><br>
                                        Ref: <?= h($p['transaction_id']) ?>
                                    </div>
                                </div>
                                <div class="text-sm"><?= money_ngn($p['amount']) ?></div>
                            </div>
                        </li>
                    <?php endwhile; ?>
                <?php endif; ?>
            </ul>
        </div>
    </div>

    <!-- Tasks & Activity -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="bg-white dark:bg-gray-800 p-6 rounded shadow-md">
            <h3 class="text-xl font-bold mb-4">My Tasks</h3>
            <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                <?php if ($tasks->num_rows === 0): ?>
                    <li class="py-3 text-gray-500">No tasks assigned.</li>
                <?php else: ?>
                    <?php while ($t = $tasks->fetch_assoc()): ?>
                        <li class="py-3 flex justify-between items-center">
                            <div>
                                <div class="font-medium"><?= h($t['title']) ?></div>
                                <div class="text-xs text-gray-500">
                                    <?= h(ucwords(str_replace('-', ' ', $t['status']))) ?> · Due <?= h(date('M j, Y', strtotime($t['due_date']))) ?>
                                </div>
                            </div>
                            <a href="#" class="text-sm text-[#092468] underline">View</a>
                        </li>
                    <?php endwhile; ?>
                <?php endif; ?>
            </ul>
        </div>

        <div class="bg-white dark:bg-gray-800 p-6 rounded shadow-md">
            <h3 class="text-xl font-bold mb-4">Recent Activity</h3>
            <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                <?php if ($activities->num_rows === 0): ?>
                    <li class="py-3 text-gray-500">No recent activity.</li>
                <?php else: ?>
                    <?php while ($a = $activities->fetch_assoc()): ?>
                        <li class="py-3">
                            <div class="text-sm"><?= h($a['description']) ?></div>
                            <div class="text-xs text-gray-500"><?= h(date('M j, Y g:i a', strtotime($a['created_at']))) ?></div>
                        </li>
                    <?php endwhile; ?>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</div>

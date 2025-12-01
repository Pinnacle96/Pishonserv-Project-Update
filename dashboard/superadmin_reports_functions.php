<?php
// dashboard/superadmin_reports_functions.php
// Reusable, prepared, date-aware helpers for analytics

function dt_range(array $src): array {
    $start = !empty($src['start_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $src['start_date'])
        ? $src['start_date'] : date('Y-m-d', strtotime('-30 days'));
    $end   = !empty($src['end_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $src['end_date'])
        ? $src['end_date'] : date('Y-m-d');

    // Ensure start <= end
    if ($start > $end) { [$start, $end] = [$end, $start]; }
    return [$start, $end];
}

function scalar($res, $col = 'c', $default = 0) {
    if (!$res) return $default;
    $row = $res->fetch_assoc();
    return isset($row[$col]) ? (float)$row[$col] : $default;
}

/* ---------- High-level snapshots (global) ---------- */
function getTotalUsers(mysqli $conn): int {
    return (int) scalar($conn->query("SELECT COUNT(*) c FROM users"));
}
function getTotalProperties(mysqli $conn): int {
    return (int) scalar($conn->query("SELECT COUNT(*) c FROM properties"));
}
function getTotalRevenue(mysqli $conn, ?string $start=null, ?string $end=null): float {
    if ($start && $end) {
        $stmt = $conn->prepare("SELECT COALESCE(SUM(amount),0) c FROM payments WHERE status='completed' AND DATE(created_at) BETWEEN ? AND ?");
        $stmt->bind_param('ss', $start, $end);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        return (float)($row['c'] ?? 0);
    }
    return (float) scalar($conn->query("SELECT COALESCE(SUM(amount),0) c FROM payments WHERE status='completed'"));
}

/* ---------- Transactions Overview (counts in date window) ---------- */
function getTransactionStats(mysqli $conn, string $start, string $end): array {
    $map = ['completed'=>0,'pending'=>0,'failed'=>0];
    $stmt = $conn->prepare("
        SELECT status, COUNT(*) c
        FROM payments
        WHERE DATE(created_at) BETWEEN ? AND ?
          AND status IN ('completed','pending','failed')
        GROUP BY status
    ");
    $stmt->bind_param('ss', $start, $end);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) $map[$r['status']] = (int)$r['c'];
    $stmt->close();
    return array_values($map); // [completed, pending, failed]
}

/* ---------- GMV Timeseries (daily) ---------- */
function getGMVSeries(mysqli $conn, string $start, string $end): array {
    // Build empty day buckets
    $labels = [];
    $cursor = strtotime($start);
    $endts  = strtotime($end);
    while ($cursor <= $endts) {
        $labels[] = date('Y-m-d', $cursor);
        $cursor   = strtotime('+1 day', $cursor);
    }
    $values = array_fill(0, count($labels), 0.0);
    $index  = array_flip($labels);

    $stmt = $conn->prepare("
        SELECT DATE(created_at) d, COALESCE(SUM(amount),0) s
        FROM payments
        WHERE status='completed'
          AND DATE(created_at) BETWEEN ? AND ?
        GROUP BY d
        ORDER BY d ASC
    ");
    $stmt->bind_param('ss', $start, $end);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        $d = $r['d'];
        if (isset($index[$d])) $values[$index[$d]] = (float)$r['s'];
    }
    $stmt->close();
    return [$labels, $values];
}

/* ---------- Revenue by Gateway ---------- */
function getRevenueByGateway(mysqli $conn, string $start, string $end): array {
    $labels = []; $values = [];
    $stmt = $conn->prepare("
        SELECT payment_gateway, COALESCE(SUM(amount),0) s
        FROM payments
        WHERE status='completed' AND DATE(created_at) BETWEEN ? AND ?
        GROUP BY payment_gateway
        ORDER BY s DESC
    ");
    $stmt->bind_param('ss', $start, $end);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) { $labels[] = $r['payment_gateway'] ?: 'Unknown'; $values[] = (float)$r['s']; }
    $stmt->close();
    return [$labels, $values];
}

/* ---------- Bookings Status Mix (date window) ---------- */
function getBookingsStatusMix(mysqli $conn, string $start, string $end): array {
    $map = ['pending'=>0, 'confirmed'=>0, 'cancelled'=>0];
    $stmt = $conn->prepare("
        SELECT status, COUNT(*) c
        FROM bookings
        WHERE DATE(created_at) BETWEEN ? AND ?
          AND status IN ('pending','confirmed','cancelled')
        GROUP BY status
    ");
    $stmt->bind_param('ss', $start, $end);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) $map[$r['status']] = (int)$r['c'];
    $stmt->close();
    return [ $map['pending'], $map['confirmed'], $map['cancelled'] ];
}

/* ---------- Withdrawals Status Mix (all-time or window) ---------- */
function getWithdrawalsStatusMix(mysqli $conn, string $start, string $end): array {
    $statuses = ['requested','pending','processing','completed','failed','reversed','canceled'];
    $map = array_fill_keys($statuses, 0);
    $stmt = $conn->prepare("
        SELECT status, COUNT(*) c
        FROM withdrawals
        WHERE DATE(created_at) BETWEEN ? AND ?
        GROUP BY status
    ");
    $stmt->bind_param('ss', $start, $end);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) if (isset($map[$r['status']])) $map[$r['status']] = (int)$r['c'];
    $stmt->close();
    return array_values($map);
}

/* ---------- Avg Booking (confirmed only, 30d / window) ---------- */
function getAvgBookingConfirmed(mysqli $conn, string $start, string $end): float {
    $stmt = $conn->prepare("
        SELECT COALESCE(AVG(amount),0) a
        FROM bookings
        WHERE status='confirmed' AND amount>0
          AND DATE(created_at) BETWEEN ? AND ?
    ");
    $stmt->bind_param('ss', $start, $end);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (float)($row['a'] ?? 0);
}

/* ---------- Top Agents / Top Properties (by completed payments) ---------- */
function getTopAgents(mysqli $conn, string $start, string $end, int $limit=5): array {
    $stmt = $conn->prepare("
        SELECT u.id, u.name, u.lname, u.role, COALESCE(SUM(pay.amount),0) total
        FROM payments pay
        JOIN properties p ON p.id = pay.property_id
        JOIN users u ON u.id = p.owner_id
        WHERE pay.status='completed' AND DATE(pay.created_at) BETWEEN ? AND ?
        GROUP BY u.id, u.name, u.lname, u.role
        ORDER BY total DESC
        LIMIT ?
    ");
    $stmt->bind_param('ssi', $start, $end, $limit);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($r = $res->fetch_assoc()) $rows[] = $r;
    $stmt->close();
    return $rows;
}

function getTopProperties(mysqli $conn, string $start, string $end, int $limit=5): array {
    $stmt = $conn->prepare("
        SELECT p.id, p.title, p.location, COALESCE(SUM(pay.amount),0) total
        FROM payments pay
        JOIN properties p ON p.id = pay.property_id
        WHERE pay.status='completed' AND DATE(pay.created_at) BETWEEN ? AND ?
        GROUP BY p.id, p.title, p.location
        ORDER BY total DESC
        LIMIT ?
    ");
    $stmt->bind_param('ssi', $start, $end, $limit);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($r = $res->fetch_assoc()) $rows[] = $r;
    $stmt->close();
    return $rows;
}

/* ---------- Recent Activities ---------- */
function getRecentActivities(mysqli $conn, int $limit=10): array {
    $rows = [];
    $stmt = $conn->prepare("
        SELECT a.description, a.created_at, u.name
        FROM activities a
        JOIN users u ON u.id=a.user_id
        ORDER BY a.created_at DESC
        LIMIT ?
    ");
    $stmt->bind_param('i', $limit);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        $rows[] = $r['name'].' – '.$r['description'].' • '.date('M j, Y H:i', strtotime($r['created_at']));
    }
    $stmt->close();
    return $rows;
}

/* ---------- CSV Exports ---------- */
function exportPaymentsCSV(mysqli $conn, string $start, string $end): void {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=payments_'.date('Ymd_His').'.csv');
    $out = fopen('php://output','w');
    fputcsv($out, ['ID','Property','Gateway','Status','Amount','Reference','Created At']);
    $stmt = $conn->prepare("
        SELECT pay.id, p.title, pay.payment_gateway, pay.status, pay.amount, pay.transaction_id, pay.created_at
        FROM payments pay
        LEFT JOIN properties p ON p.id=pay.property_id
        WHERE DATE(pay.created_at) BETWEEN ? AND ?
        ORDER BY pay.created_at DESC
    ");
    $stmt->bind_param('ss',$start,$end);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        fputcsv($out, [
            $r['id'], $r['title'], $r['payment_gateway'], $r['status'],
            $r['amount'], $r['transaction_id'], $r['created_at']
        ]);
    }
    fclose($out);
    exit;
}

function exportWithdrawalsCSV(mysqli $conn, string $start, string $end): void {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=withdrawals_'.date('Ymd_His').'.csv');
    $out = fopen('php://output','w');
    fputcsv($out, ['ID','User','Email','Amount','Fee','Status','PS Transfer','PS Ref','Bank','Account','Account No','Created At','Updated At','Error']);
    $stmt = $conn->prepare("
        SELECT w.id, u.name, u.lname, u.email, w.amount, w.fee, w.status,
               w.paystack_transfer_code, w.paystack_reference,
               b.bank_name, b.account_name, b.account_number, w.created_at, w.updated_at, w.error_message
        FROM withdrawals w
        JOIN users u ON u.id=w.user_id
        LEFT JOIN bank_accounts b ON b.id=w.bank_account_id
        WHERE DATE(w.created_at) BETWEEN ? AND ?
        ORDER BY w.created_at DESC
    ");
    $stmt->bind_param('ss',$start,$end);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        fputcsv($out, [
            $r['id'], $r['name'].' '.$r['lname'], $r['email'],
            $r['amount'], $r['fee'], $r['status'],
            $r['paystack_transfer_code'], $r['paystack_reference'],
            $r['bank_name'], $r['account_name'], $r['account_number'],
            $r['created_at'], $r['updated_at'], $r['error_message']
        ]);
    }
    fclose($out);
    exit;
}

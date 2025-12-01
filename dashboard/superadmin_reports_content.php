<?php
// dashboard/superadmin_reports_content.php
require_once __DIR__ . '/superadmin_reports_functions.php';

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function ngn($n){ return '₦'.number_format((float)$n, 2); }

[$start_date, $end_date] = dt_range($_GET);

// CSV exports
if (isset($_GET['export']) && $_GET['export']==='payments') {
    exportPaymentsCSV($conn, $start_date, $end_date);
}
if (isset($_GET['export']) && $_GET['export']==='withdrawals') {
    exportWithdrawalsCSV($conn, $start_date, $end_date);
}

// KPIs
$total_users       = getTotalUsers($conn);
$total_properties  = getTotalProperties($conn);
$total_revenue     = getTotalRevenue($conn, $start_date, $end_date);
$tx_stats          = getTransactionStats($conn, $start_date, $end_date);       // [completed,pending,failed]
[$gmv_labels, $gmv_values] = getGMVSeries($conn, $start_date, $end_date);
[$gw_labels, $gw_values]   = getRevenueByGateway($conn, $start_date, $end_date);
$book_mix          = getBookingsStatusMix($conn, $start_date, $end_date);      // [pending,confirmed,cancelled]
$wd_mix            = getWithdrawalsStatusMix($conn, $start_date, $end_date);   // 7 statuses
$avg_booking_conf  = getAvgBookingConfirmed($conn, $start_date, $end_date);
$top_agents        = getTopAgents($conn, $start_date, $end_date, 5);
$top_properties    = getTopProperties($conn, $start_date, $end_date, 5);
$activities        = getRecentActivities($conn, 8);
?>

<div class="mt-6 space-y-6">

  <div class="flex items-end justify-between gap-4">
    <div>
      <h2 class="text-2xl md:text-3xl font-bold text-gray-800 dark:text-gray-200">Reports & Analytics</h2>
      <p class="text-gray-600 dark:text-gray-400">Platform-wide insights and drilldowns.</p>
      <p class="text-xs text-gray-500 mt-1">Window: <span class="font-semibold"><?= h($start_date) ?></span> → <span class="font-semibold"><?= h($end_date) ?></span></p>
    </div>

    <form method="get" class="flex items-end gap-2">
      <div>
        <label class="block text-xs text-gray-600">Start Date</label>
        <input type="date" name="start_date" value="<?= h($start_date) ?>" class="px-3 py-2 border rounded dark:bg-gray-800 dark:border-gray-700">
      </div>
      <div>
        <label class="block text-xs text-gray-600">End Date</label>
        <input type="date" name="end_date" value="<?= h($end_date) ?>" class="px-3 py-2 border rounded dark:bg-gray-800 dark:border-gray-700">
      </div>
      <button class="px-4 py-2 bg-[#092468] text-white rounded hover:bg-[#061b47]">Filter</button>
      <?php
        $qs = $_GET; $qs['export']='payments';
        $payments_csv = 'superadmin_reports.php?'.http_build_query($qs);
        $qs['export']='withdrawals';
        $withdrawals_csv = 'superadmin_reports.php?'.http_build_query($qs);
      ?>
      <a href="<?= h($payments_csv) ?>" class="px-3 py-2 rounded bg-emerald-600 text-white hover:bg-emerald-700">Export Payments CSV</a>
      <a href="<?= h($withdrawals_csv) ?>" class="px-3 py-2 rounded bg-indigo-600 text-white hover:bg-indigo-700">Export Withdrawals CSV</a>
    </form>
  </div>

  <!-- KPI CARDS -->
  <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
    <div class="bg-white dark:bg-gray-800 p-5 rounded-lg shadow-sm border dark:border-gray-700">
      <div class="text-xs uppercase tracking-wide text-gray-500">Users</div>
      <div class="mt-2 text-3xl font-bold"><?= number_format($total_users) ?></div>
    </div>
    <div class="bg-white dark:bg-gray-800 p-5 rounded-lg shadow-sm border dark:border-gray-700">
      <div class="text-xs uppercase tracking-wide text-gray-500">Properties</div>
      <div class="mt-2 text-3xl font-bold"><?= number_format($total_properties) ?></div>
    </div>
    <div class="bg-white dark:bg-gray-800 p-5 rounded-lg shadow-sm border dark:border-gray-700">
      <div class="text-xs uppercase tracking-wide text-gray-500">GMV (Completed)</div>
      <div class="mt-2 text-3xl font-bold"><?= ngn($total_revenue) ?></div>
    </div>
    <div class="bg-white dark:bg-gray-800 p-5 rounded-lg shadow-sm border dark:border-gray-700">
      <div class="text-xs uppercase tracking-wide text-gray-500">Avg Booking (Confirmed)</div>
      <div class="mt-2 text-3xl font-bold"><?= ngn($avg_booking_conf) ?></div>
    </div>
  </div>

  <!-- CHARTS ROW 1 -->
  <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
    <div class="xl:col-span-2 bg-white dark:bg-gray-800 p-6 rounded-lg shadow-sm border dark:border-gray-700">
      <h3 class="text-lg md:text-xl font-bold mb-4">GMV by Day</h3>
      <div class="relative h-64"><canvas id="gmvChart"></canvas></div>
    </div>
    <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-sm border dark:border-gray-700">
      <h3 class="text-lg md:text-xl font-bold mb-4">Transaction Status</h3>
      <div class="relative h-64"><canvas id="transactionsChart"></canvas></div>
      <p class="text-xs text-gray-500 mt-2">Completed · Pending · Failed within window</p>
    </div>
  </div>

  <!-- CHARTS ROW 2 -->
  <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
    <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-sm border dark:border-gray-700">
      <h3 class="text-lg md:text-xl font-bold mb-4">Revenue by Gateway</h3>
      <div class="relative h-64"><canvas id="gatewayChart"></canvas></div>
    </div>
    <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-sm border dark:border-gray-700">
      <h3 class="text-lg md:text-xl font-bold mb-4">Bookings Status Mix</h3>
      <div class="relative h-64"><canvas id="bookingsChart"></canvas></div>
    </div>
    <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-sm border dark:border-gray-700">
      <h3 class="text-lg md:text-xl font-bold mb-4">Withdrawals Status Mix</h3>
      <div class="relative h-64"><canvas id="withdrawalsChart"></canvas></div>
    </div>
  </div>

  <!-- TABLES -->
  <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
    <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-sm border dark:border-gray-700">
      <h3 class="text-lg md:text-xl font-bold mb-4">Top Agents (by GMV)</h3>
      <div class="overflow-x-auto">
        <table class="w-full text-sm border dark:border-gray-700">
          <thead>
            <tr class="bg-gray-100 dark:bg-gray-700 text-gray-900 dark:text-gray-100">
              <th class="p-3 border">Agent</th>
              <th class="p-3 border">Role</th>
              <th class="p-3 border">Total</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$top_agents): ?>
              <tr><td colspan="3" class="p-3 border text-center text-gray-500">No data.</td></tr>
            <?php else: foreach ($top_agents as $a): ?>
              <tr>
                <td class="p-3 border"><?= h($a['name'].' '.$a['lname']) ?></td>
                <td class="p-3 border"><?= h(ucfirst(str_replace('_',' ', $a['role']))) ?></td>
                <td class="p-3 border"><?= ngn($a['total']) ?></td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-sm border dark:border-gray-700">
      <h3 class="text-lg md:text-xl font-bold mb-4">Top Properties (by GMV)</h3>
      <div class="overflow-x-auto">
        <table class="w-full text-sm border dark:border-gray-700">
          <thead>
            <tr class="bg-gray-100 dark:bg-gray-700 text-gray-900 dark:text-gray-100">
              <th class="p-3 border">Property</th>
              <th class="p-3 border">Location</th>
              <th class="p-3 border">Total</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$top_properties): ?>
              <tr><td colspan="3" class="p-3 border text-center text-gray-500">No data.</td></tr>
            <?php else: foreach ($top_properties as $p): ?>
              <tr>
                <td class="p-3 border"><?= h(mb_strimwidth($p['title'],0,36,'…')) ?></td>
                <td class="p-3 border"><?= h($p['location']) ?></td>
                <td class="p-3 border"><?= ngn($p['total']) ?></td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Recent Activities -->
  <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-sm border dark:border-gray-700">
    <h3 class="text-lg md:text-xl font-bold mb-4">Recent Activities</h3>
    <ul class="space-y-2">
      <?php if (!$activities): ?>
        <li class="text-sm text-gray-500">No recent activity.</li>
      <?php else: foreach ($activities as $act): ?>
        <li class="text-sm text-gray-700 dark:text-gray-300"><?= h($act) ?></li>
      <?php endforeach; endif; ?>
    </ul>
  </div>
</div>

<!-- Charts -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const fmtNgn = (v) => '₦' + Number(v||0).toLocaleString();

  const gmvLabels = <?= json_encode($gmv_labels) ?>;
  const gmvData   = <?= json_encode($gmv_values) ?>;
  const txData    = <?= json_encode($tx_stats) ?>; // [completed,pending,failed]
  const gwLabels  = <?= json_encode($gw_labels) ?>;
  const gwData    = <?= json_encode($gw_values) ?>;
  const bookData  = <?= json_encode($book_mix) ?>; // [pending,confirmed,cancelled]
  const wdData    = <?= json_encode($wd_mix) ?>;   // 7 statuses

  // GMV line
  new Chart(document.getElementById('gmvChart').getContext('2d'), {
    type: 'line',
    data: { labels: gmvLabels, datasets: [{ label: 'GMV', data: gmvData }] },
    options: {
      responsive:true, maintainAspectRatio:false,
      scales: { y: { beginAtZero:true, ticks:{ callback:(v)=>fmtNgn(v) } } },
      plugins: { tooltip:{ callbacks:{ label:(ctx)=>fmtNgn(ctx.raw) } } }
    }
  });

  // Transactions status bar (Completed, Pending, Failed)
  new Chart(document.getElementById('transactionsChart').getContext('2d'), {
    type: 'bar',
    data: {
      labels: ['Completed','Pending','Failed'],
      datasets: [{ label: 'Transactions', data: txData }]
    },
    options: {
      responsive:true, maintainAspectRatio:false,
      scales:{ y:{ beginAtZero:true } },
      plugins:{ legend:{ display:false } }
    }
  });

  // Revenue by gateway (horizontal bar if many)
  new Chart(document.getElementById('gatewayChart').getContext('2d'), {
    type: 'bar',
    data: { labels: gwLabels, datasets: [{ label: 'GMV', data: gwData }] },
    options: {
      indexAxis: gwLabels.length > 4 ? 'y' : 'x',
      responsive:true, maintainAspectRatio:false,
      scales:{ x:{ ticks:{ callback:(v)=>fmtNgn(v) }}, y:{} },
      plugins:{ tooltip:{ callbacks:{ label:(ctx)=>fmtNgn(ctx.raw) } }, legend:{ display:false } }
    }
  });

  // Booking status mix
  new Chart(document.getElementById('bookingsChart').getContext('2d'), {
    type: 'pie',
    data: {
      labels: ['Pending','Confirmed','Cancelled'],
      datasets: [{ data: bookData }]
    },
    options: { responsive:true, maintainAspectRatio:false, plugins:{ legend:{ position:'bottom' } } }
  });

  // Withdrawals status mix
  new Chart(document.getElementById('withdrawalsChart').getContext('2d'), {
    type: 'pie',
    data: {
      labels: ['Requested','Pending','Processing','Completed','Failed','Reversed','Canceled'],
      datasets: [{ data: wdData }]
    },
    options: { responsive:true, maintainAspectRatio:false, plugins:{ legend:{ position:'bottom' } } }
  });
});
</script>

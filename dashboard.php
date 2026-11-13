<?php
/**
 * Dashboard & Analytics Screen
 * Guidance Office Logbook Management System (OSAS)
 */

$page_title = 'Dashboard';
require_once __DIR__ . '/includes/header.php';

// Date range filters
$preset = $_GET['preset'] ?? 'all';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

$today = date('Y-m-d');
if ($preset === 'today') {
    $date_from = $today;
    $date_to = $today;
} elseif ($preset === 'this_week') {
    $date_from = date('Y-m-d', strtotime('monday this week'));
    $date_to = date('Y-m-d', strtotime('sunday this week'));
} elseif ($preset === 'this_month') {
    $date_from = date('Y-m-01');
    $date_to = date('Y-m-t');
} elseif ($preset === 'this_year') {
    $date_from = date('Y-01-01');
    $date_to = date('Y-12-31');
}

// SQL date condition helper
$date_sql = "";
$date_params = [];
if (!empty($date_from) && !empty($date_to)) {
    $date_sql = " AND lr.entry_date BETWEEN ? AND ? ";
    $date_params = [$date_from, $date_to];
} elseif (!empty($date_from)) {
    $date_sql = " AND lr.entry_date >= ? ";
    $date_params = [$date_from];
} elseif (!empty($date_to)) {
    $date_sql = " AND lr.entry_date <= ? ";
    $date_params = [$date_to];
}

// 1. Metric: Total Client Visits
$sql_clients = "SELECT COUNT(*) FROM `logbook_records` lr 
    JOIN `logbook_types` lt ON lt.id = lr.logbook_type_id 
    WHERE lt.slug = 'clients-log' AND lr.status = 'active' {$date_sql}";
$stmt = $pdo->prepare($sql_clients);
$stmt->execute($date_params);
$total_client_visits = (int) $stmt->fetchColumn();

// 2. Metric: Total Visitors
$sql_visitors = "SELECT COUNT(*) FROM `logbook_records` lr 
    JOIN `logbook_types` lt ON lt.id = lr.logbook_type_id 
    WHERE lt.slug = 'visitors-log' AND lr.status = 'active' {$date_sql}";
$stmt = $pdo->prepare($sql_visitors);
$stmt->execute($date_params);
$total_visitors = (int) $stmt->fetchColumn();

// 3. Metric: Borrowed Items Not Yet Returned (Pending return)
$sql_unreturned = "SELECT COUNT(*) FROM `logbook_records` lr 
    JOIN `logbook_types` lt ON lt.id = lr.logbook_type_id 
    WHERE lt.slug = 'borrowers-log' AND lr.status = 'active' 
    AND JSON_UNQUOTE(JSON_EXTRACT(lr.data_json, '$.return_status')) = 'Pending' {$date_sql}";
$stmt = $pdo->prepare($sql_unreturned);
$stmt->execute($date_params);
$unreturned_borrowed_count = (int) $stmt->fetchColumn();

// 4. Metric: Certificates of Good Moral Issued
$sql_certificates = "SELECT COUNT(*) FROM `logbook_records` lr 
    JOIN `logbook_types` lt ON lt.id = lr.logbook_type_id 
    WHERE lt.slug = 'good-moral-log' AND lr.status = 'active' 
    AND (JSON_UNQUOTE(JSON_EXTRACT(lr.data_json, '$.date_issued')) IS NOT NULL AND JSON_UNQUOTE(JSON_EXTRACT(lr.data_json, '$.date_issued')) != '') {$date_sql}";
$stmt = $pdo->prepare($sql_certificates);
$stmt->execute($date_params);
$certificates_issued_count = (int) $stmt->fetchColumn();

// 5. Activity by Date for Chart (Last 14 days or filtered period)
$sql_activity = "SELECT lr.entry_date, COUNT(*) as count 
    FROM `logbook_records` lr 
    WHERE lr.status = 'active' {$date_sql}
    GROUP BY lr.entry_date 
    ORDER BY lr.entry_date ASC 
    LIMIT 14";
$stmt = $pdo->prepare($sql_activity);
$stmt->execute($date_params);
$activity_data = $stmt->fetchAll();

$chart_dates = [];
$chart_counts = [];
foreach ($activity_data as $row) {
    $chart_dates[] = date('M d', strtotime($row['entry_date']));
    $chart_counts[] = (int) $row['count'];
}

// 6. Common Purposes distribution across all logbooks
$sql_purposes = "SELECT 
    CASE 
        WHEN lr.purpose LIKE '%Counseling%' THEN 'Counseling'
        WHEN lr.purpose LIKE '%Consultation%' THEN 'Consultation'
        WHEN lr.purpose LIKE '%Clearance%' THEN 'Clearance'
        WHEN lr.purpose LIKE '%Certificate%' OR lr.purpose LIKE '%Exam%' OR lr.purpose LIKE '%PRC%' THEN 'Certificates / Exams'
        WHEN lr.purpose LIKE '%Scholarship%' THEN 'Scholarship'
        WHEN lr.purpose LIKE '%Coordination%' OR lr.purpose LIKE '%Audit%' THEN 'Agency Coordination'
        WHEN lr.purpose LIKE '%Test%' OR lr.purpose LIKE '%Assessment%' OR lr.purpose LIKE '%Projector%' THEN 'Resource Borrowing'
        ELSE 'General Inquiry & Other'
    END AS grouped_purpose, 
    COUNT(*) as count 
    FROM `logbook_records` lr 
    WHERE lr.status = 'active' AND lr.purpose IS NOT NULL AND lr.purpose != '' {$date_sql}
    GROUP BY grouped_purpose 
    ORDER BY count DESC 
    LIMIT 6";
$stmt = $pdo->prepare($sql_purposes);
$stmt->execute($date_params);
$purposes_data = $stmt->fetchAll();

$purpose_labels = [];
$purpose_counts = [];
foreach ($purposes_data as $row) {
    $purpose_labels[] = $row['grouped_purpose'];
    $purpose_counts[] = (int) $row['count'];
}

// 7. Client Type distribution
$sql_clients_types = "SELECT COALESCE(lr.client_type, 'Not Specified') as c_type, COUNT(*) as count 
    FROM `logbook_records` lr 
    WHERE lr.status = 'active' AND lr.client_type IS NOT NULL AND lr.client_type != '' {$date_sql}
    GROUP BY c_type 
    ORDER BY count DESC";
$stmt = $pdo->prepare($sql_clients_types);
$stmt->execute($date_params);
$client_types_data = $stmt->fetchAll();

$client_type_labels = [];
$client_type_counts = [];
foreach ($client_types_data as $row) {
    $client_type_labels[] = $row['c_type'];
    $client_type_counts[] = (int) $row['count'];
}

// 8. Items Pending Return (Immediate Attention List)
$sql_pending_items = "SELECT lr.*, lt.title as logbook_title, lt.slug as logbook_slug 
    FROM `logbook_records` lr
    JOIN `logbook_types` lt ON lt.id = lr.logbook_type_id
    WHERE lt.slug = 'borrowers-log' AND lr.status = 'active'
    AND JSON_UNQUOTE(JSON_EXTRACT(lr.data_json, '$.return_status')) = 'Pending'
    ORDER BY lr.entry_date DESC LIMIT 5";
$pending_items = $pdo->query($sql_pending_items)->fetchAll();

// 9. Recent Entries Feed
$sql_recent = "SELECT lr.*, lt.title as logbook_title, lt.slug as logbook_slug, lt.icon as logbook_icon, lt.color_theme 
    FROM `logbook_records` lr
    JOIN `logbook_types` lt ON lt.id = lr.logbook_type_id
    WHERE lr.status = 'active'
    ORDER BY lr.created_at DESC LIMIT 8";
$recent_entries = $pdo->query($sql_recent)->fetchAll();
?>

<div class="space-y-6">

  <!-- Welcome & Date Filter Toolbar -->
  <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
    <div>
      <h1 class="text-xl sm:text-2xl font-bold text-slate-900 tracking-tight">Guidance Office Overview</h1>
      <p class="text-sm text-slate-500 mt-1">Real-time summaries, visitor traffic, and logbook activity monitoring.</p>
    </div>

    <!-- Date Filter Form -->
    <form method="GET" action="dashboard.php" class="flex flex-wrap items-center gap-2">
      <div class="inline-flex rounded-xl p-1 bg-slate-100 border border-slate-200">
        <a href="dashboard.php?preset=all" class="px-2.5 py-1 text-xs font-medium rounded-lg <?= $preset === 'all' ? 'bg-white text-brand-900 shadow-sm font-semibold' : 'text-slate-600 hover:text-slate-900' ?>">All</a>
        <a href="dashboard.php?preset=today" class="px-2.5 py-1 text-xs font-medium rounded-lg <?= $preset === 'today' ? 'bg-white text-brand-900 shadow-sm font-semibold' : 'text-slate-600 hover:text-slate-900' ?>">Today</a>
        <a href="dashboard.php?preset=this_week" class="px-2.5 py-1 text-xs font-medium rounded-lg <?= $preset === 'this_week' ? 'bg-white text-brand-900 shadow-sm font-semibold' : 'text-slate-600 hover:text-slate-900' ?>">Week</a>
        <a href="dashboard.php?preset=this_month" class="px-2.5 py-1 text-xs font-medium rounded-lg <?= $preset === 'this_month' ? 'bg-white text-brand-900 shadow-sm font-semibold' : 'text-slate-600 hover:text-slate-900' ?>">Month</a>
        <a href="dashboard.php?preset=this_year" class="px-2.5 py-1 text-xs font-medium rounded-lg <?= $preset === 'this_year' ? 'bg-white text-brand-900 shadow-sm font-semibold' : 'text-slate-600 hover:text-slate-900' ?>">Year</a>
      </div>

      <div class="flex items-center gap-1.5 text-xs">
        <input type="date" name="date_from" value="<?= e($date_from) ?>" class="px-2.5 py-1.5 rounded-lg border border-slate-200 text-xs text-slate-700 focus:outline-none focus:ring-1 focus:ring-brand-500" placeholder="From">
        <span class="text-slate-400">to</span>
        <input type="date" name="date_to" value="<?= e($date_to) ?>" class="px-2.5 py-1.5 rounded-lg border border-slate-200 text-xs text-slate-700 focus:outline-none focus:ring-1 focus:ring-brand-500" placeholder="To">
        <button type="submit" class="px-3 py-1.5 bg-brand-900 hover:bg-brand-800 text-white font-medium rounded-lg text-xs transition">Filter</button>
        <?php if (!empty($date_from) || !empty($date_to) || $preset !== 'all'): ?>
          <a href="dashboard.php" class="px-2 py-1.5 text-slate-500 hover:text-slate-700 text-xs" title="Reset filter"><i class="bi bi-x-circle"></i></a>
        <?php endif; ?>
      </div>
    </form>
  </div>

  <!-- 4 Interactive Summary Cards (Click to Drilldown into Logbook) -->
  <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
    
    <!-- 1. Total Client Visits -->
    <a href="logbook.php?type=clients-log<?= !empty($date_from) ? '&date_from='.urlencode($date_from).'&date_to='.urlencode($date_to) : '' ?>" class="stat-card bg-white p-5 rounded-2xl border border-slate-200 shadow-sm block group hover:border-emerald-300">
      <div class="flex items-center justify-between">
        <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Client's Log</span>
        <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-700 flex items-center justify-center text-lg group-hover:scale-110 transition-transform">
          <i class="bi bi-people-fill"></i>
        </div>
      </div>
      <div class="mt-4">
        <div class="text-3xl font-extrabold text-slate-900 tracking-tight"><?= $total_client_visits ?></div>
        <p class="text-xs text-slate-500 mt-1 flex items-center gap-1">
          <span>Total Client Visits</span>
          <i class="bi bi-arrow-right text-emerald-600 group-hover:translate-x-1 transition-transform"></i>
        </p>
      </div>
    </a>

    <!-- 2. Total Visitors -->
    <a href="logbook.php?type=visitors-log<?= !empty($date_from) ? '&date_from='.urlencode($date_from).'&date_to='.urlencode($date_to) : '' ?>" class="stat-card bg-white p-5 rounded-2xl border border-slate-200 shadow-sm block group hover:border-amber-300">
      <div class="flex items-center justify-between">
        <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Visitor's Log</span>
        <div class="w-10 h-10 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center text-lg group-hover:scale-110 transition-transform">
          <i class="bi bi-building-check"></i>
        </div>
      </div>
      <div class="mt-4">
        <div class="text-3xl font-extrabold text-slate-900 tracking-tight"><?= $total_visitors ?></div>
        <p class="text-xs text-slate-500 mt-1 flex items-center gap-1">
          <span>Official Agency Visitors</span>
          <i class="bi bi-arrow-right text-amber-500 group-hover:translate-x-1 transition-transform"></i>
        </p>
      </div>
    </a>

    <!-- 3. Borrowed Items Pending Return -->
    <a href="logbook.php?type=borrowers-log&return_status=Pending" class="stat-card bg-white p-5 rounded-2xl border <?= $unreturned_borrowed_count > 0 ? 'border-amber-300 bg-amber-50/20' : 'border-slate-200' ?> shadow-sm block group hover:border-amber-400">
      <div class="flex items-center justify-between">
        <span class="text-xs font-bold <?= $unreturned_borrowed_count > 0 ? 'text-amber-700' : 'text-slate-400' ?> uppercase tracking-wider">Borrower's Log</span>
        <div class="w-10 h-10 rounded-xl <?= $unreturned_borrowed_count > 0 ? 'bg-amber-100 text-amber-700 animate-pulse' : 'bg-emerald-50 text-emerald-700' ?> flex items-center justify-center text-lg group-hover:scale-110 transition-transform">
          <i class="bi bi-box-arrow-right"></i>
        </div>
      </div>
      <div class="mt-4">
        <div class="text-3xl font-extrabold <?= $unreturned_borrowed_count > 0 ? 'text-amber-800' : 'text-slate-900' ?> tracking-tight"><?= $unreturned_borrowed_count ?></div>
        <p class="text-xs text-slate-500 mt-1 flex items-center gap-1">
          <span>Items Pending Return</span>
          <i class="bi bi-arrow-right text-amber-600 group-hover:translate-x-1 transition-transform"></i>
        </p>
      </div>
    </a>

    <!-- 4. Good Moral Certificates Issued -->
    <a href="logbook.php?type=good-moral-log<?= !empty($date_from) ? '&date_from='.urlencode($date_from).'&date_to='.urlencode($date_to) : '' ?>" class="stat-card bg-white p-5 rounded-2xl border border-slate-200 shadow-sm block group hover:border-emerald-300">
      <div class="flex items-center justify-between">
        <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Good Moral Cert</span>
        <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-lg group-hover:scale-110 transition-transform">
          <i class="bi bi-award-fill"></i>
        </div>
      </div>
      <div class="mt-4">
        <div class="text-3xl font-extrabold text-slate-900 tracking-tight"><?= $certificates_issued_count ?></div>
        <p class="text-xs text-slate-500 mt-1 flex items-center gap-1">
          <span>Certificates Released</span>
          <i class="bi bi-arrow-right text-emerald-500 group-hover:translate-x-1 transition-transform"></i>
        </p>
      </div>
    </a>

  </div>

  <!-- Analytics & Visual Charts Row -->
  <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    
    <!-- Daily & Period Activity Timeline Chart -->
    <div class="lg:col-span-2 bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
      <div class="flex items-center justify-between mb-4">
        <div>
          <h2 class="text-base font-bold text-slate-900">Activity Timeline</h2>
          <p class="text-xs text-slate-500">Total guidance office log entries recorded over time</p>
        </div>
        <span class="badge-pill bg-slate-100 text-slate-600 text-xs">Traffic Trend</span>
      </div>

      <div class="relative h-64 w-full">
        <canvas id="activityChart"></canvas>
      </div>
    </div>

    <!-- Common Visit Purposes Distribution -->
    <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm flex flex-col justify-between">
      <div>
        <div class="flex items-center justify-between mb-2">
          <h2 class="text-base font-bold text-slate-900">Visit Purposes</h2>
          <span class="badge-pill bg-brand-50 text-brand-700 text-xs">Top Reasons</span>
        </div>
        <p class="text-xs text-slate-500 mb-4">Breakdown of student and client visit objectives</p>
        
        <div class="relative h-52 w-full flex items-center justify-center">
          <canvas id="purposesChart"></canvas>
        </div>
      </div>

      <div class="mt-4 pt-3 border-t border-slate-100 text-xs text-slate-500 text-center">
        Counseling, Consultations & Certification requests
      </div>
    </div>

  </div>

  <!-- Client Types & Attention Section -->
  <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    
    <!-- Client Type Distribution Breakdown -->
    <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
      <div class="flex items-center justify-between mb-3">
        <h2 class="text-base font-bold text-slate-900">Client Demographics</h2>
        <span class="badge-pill bg-slate-100 text-slate-600 text-xs">Categories</span>
      </div>
      <p class="text-xs text-slate-500 mb-4">Students, Visitors, Alumni, and Faculty ratio</p>

      <div class="relative h-48 w-full flex items-center justify-center">
        <canvas id="clientTypesChart"></canvas>
      </div>

      <div class="grid grid-cols-2 gap-2 mt-4">
        <?php foreach ($client_types_data as $ct): ?>
          <div class="p-2.5 rounded-xl bg-slate-50 border border-slate-100 text-center">
            <div class="text-xs text-slate-500"><?= e($ct['c_type']) ?></div>
            <div class="text-base font-bold text-slate-800"><?= (int)$ct['count'] ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Items Pending Return (Immediate Action Tracker) -->
    <div class="lg:col-span-2 bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
      <div class="flex items-center justify-between mb-3">
        <div>
          <h2 class="text-base font-bold text-slate-900 flex items-center gap-2">
            <span>Borrowed Resources Pending Return</span>
            <?php if (count($pending_items) > 0): ?>
              <span class="badge-pill bg-amber-100 text-amber-800 text-xs font-bold"><?= count($pending_items) ?> Pending</span>
            <?php endif; ?>
          </h2>
          <p class="text-xs text-slate-500">Borrower log entries with unreturned testing kits, apparatus, or books</p>
        </div>
        <a href="logbook.php?type=borrowers-log&return_status=Pending" class="text-xs text-brand-700 hover:text-brand-900 font-semibold flex items-center gap-1">
          View all <i class="bi bi-chevron-right"></i>
        </a>
      </div>

      <?php if (empty($pending_items)): ?>
        <div class="p-8 text-center bg-slate-50 rounded-xl border border-dashed border-slate-200 my-4">
          <i class="bi bi-check-circle-fill text-3xl text-emerald-500 mb-2 block"></i>
          <p class="text-sm font-semibold text-slate-700">All borrowed items are returned!</p>
          <p class="text-xs text-slate-400 mt-1">There are no unreturned materials or equipment pending at this time.</p>
        </div>
      <?php else: ?>
        <div class="overflow-x-auto mt-2">
          <table class="w-full text-left text-xs">
            <thead>
              <tr class="border-b border-slate-200 text-slate-400 uppercase tracking-wider">
                <th class="py-2.5 px-3">Date</th>
                <th class="py-2.5 px-3">Borrower</th>
                <th class="py-2.5 px-3">Type</th>
                <th class="py-2.5 px-3">Item / Purpose</th>
                <th class="py-2.5 px-3 text-right">Action</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <?php foreach ($pending_items as $item): 
                $data = json_decode($item['data_json'] ?? '{}', true);
              ?>
                <tr class="hover:bg-slate-50 transition">
                  <td class="py-2.5 px-3 whitespace-nowrap text-slate-500 font-medium"><?= format_date($item['entry_date']) ?></td>
                  <td class="py-2.5 px-3 font-semibold text-slate-900"><?= e($item['client_name']) ?></td>
                  <td class="py-2.5 px-3">
                    <span class="badge-pill bg-emerald-50 text-emerald-800 border border-emerald-200"><?= e($item['client_type'] ?? 'Student') ?></span>
                  </td>
                  <td class="py-2.5 px-3 text-slate-700 max-w-xs truncate" title="<?= e($item['purpose']) ?>"><?= e($item['purpose']) ?></td>
                  <td class="py-2.5 px-3 text-right whitespace-nowrap">
                    <a href="logbook.php?type=borrowers-log&highlight=<?= $item['id'] ?>" class="inline-flex items-center gap-1 text-xs px-2.5 py-1 rounded-lg bg-slate-100 hover:bg-brand-900 hover:text-white text-slate-700 font-medium transition">
                      <span>Update</span>
                      <i class="bi bi-pencil-square"></i>
                    </a>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>

  </div>

  <!-- Recent Activity Feed Across All Logbooks -->
  <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
    <div class="flex items-center justify-between mb-4">
      <div>
        <h2 class="text-base font-bold text-slate-900">Recent Logbook Entries</h2>
        <p class="text-xs text-slate-500">Latest recordings across all guidance office sections</p>
      </div>
      <span class="text-xs text-slate-400">Live Office Log</span>
    </div>

    <div class="overflow-x-auto">
      <table class="w-full text-left text-xs">
        <thead>
          <tr class="border-b border-slate-200 text-slate-400 uppercase tracking-wider">
            <th class="py-2.5 px-3">Section</th>
            <th class="py-2.5 px-3">No.</th>
            <th class="py-2.5 px-3">Date</th>
            <th class="py-2.5 px-3">Name</th>
            <th class="py-2.5 px-3">Classification</th>
            <th class="py-2.5 px-3">Purpose / Subject</th>
            <th class="py-2.5 px-3 text-right">View</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <?php foreach ($recent_entries as $entry): ?>
            <tr class="hover:bg-slate-50 transition">
              <td class="py-2.5 px-3 whitespace-nowrap">
                <span class="inline-flex items-center gap-1.5 font-medium text-slate-800">
                  <i class="bi <?= e($entry['logbook_icon']) ?> text-brand-700"></i>
                  <span><?= e($entry['logbook_title']) ?></span>
                </span>
              </td>
              <td class="py-2.5 px-3 font-mono font-semibold text-slate-600">#<?= (int)$entry['record_no'] ?></td>
              <td class="py-2.5 px-3 whitespace-nowrap text-slate-500"><?= format_date($entry['entry_date']) ?></td>
              <td class="py-2.5 px-3 font-semibold text-slate-900"><?= e($entry['client_name']) ?></td>
              <td class="py-2.5 px-3">
                <span class="badge-pill bg-slate-100 text-slate-700"><?= e($entry['client_type'] ?? 'General') ?></span>
              </td>
              <td class="py-2.5 px-3 text-slate-600 max-w-sm truncate" title="<?= e($entry['purpose']) ?>"><?= e($entry['purpose']) ?></td>
              <td class="py-2.5 px-3 text-right whitespace-nowrap">
                <a href="logbook.php?type=<?= urlencode($entry['logbook_slug']) ?>&highlight=<?= $entry['id'] ?>" class="text-brand-700 hover:text-brand-900 font-semibold p-1">
                  <i class="bi bi-box-arrow-up-right"></i>
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>

<!-- Chart Initialization Script -->
<script>
document.addEventListener('DOMContentLoaded', function () {
  
  // 1. Activity Timeline Chart
  const actCtx = document.getElementById('activityChart');
  if (actCtx) {
    const dates = <?= json_encode($chart_dates) ?>;
    const counts = <?= json_encode($chart_counts) ?>;
    
    new Chart(actCtx, {
      type: 'line',
      data: {
        labels: dates.length ? dates : ['No entries'],
        datasets: [{
          label: 'Total Entries',
          data: counts.length ? counts : [0],
          borderColor: '#006633',
          backgroundColor: 'rgba(0, 102, 51, 0.12)',
          fill: true,
          tension: 0.35,
          borderWidth: 2.5,
          pointBackgroundColor: '#006633',
          pointRadius: 4,
          pointHoverRadius: 6
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: {
            backgroundColor: '#004422',
            padding: 10,
            cornerRadius: 8
          }
        },
        scales: {
          y: {
            beginAtZero: true,
            ticks: { precision: 0, font: { size: 11 } },
            grid: { color: '#f0fdf4' }
          },
          x: {
            ticks: { font: { size: 11 } },
            grid: { display: false }
          }
        }
      }
    });
  }

  // 2. Purposes Doughnut Chart
  const purpCtx = document.getElementById('purposesChart');
  if (purpCtx) {
    const pLabels = <?= json_encode($purpose_labels) ?>;
    const pCounts = <?= json_encode($purpose_counts) ?>;
    
    new Chart(purpCtx, {
      type: 'doughnut',
      data: {
        labels: pLabels.length ? pLabels : ['No data'],
        datasets: [{
          data: pCounts.length ? pCounts : [1],
          backgroundColor: [
            '#006633',
            '#28a745',
            '#008844',
            '#ffc107',
            '#17a2b8',
            '#6c757d'
          ],
          borderWidth: 2,
          borderColor: '#ffffff'
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            position: 'bottom',
            labels: { boxWidth: 10, font: { size: 10 }, padding: 8 }
          }
        },
        cutout: '65%'
      }
    });
  }

  // 3. Client Types Doughnut/Pie Chart
  const clientCtx = document.getElementById('clientTypesChart');
  if (clientCtx) {
    const cLabels = <?= json_encode($client_type_labels) ?>;
    const cCounts = <?= json_encode($client_type_counts) ?>;

    new Chart(clientCtx, {
      type: 'pie',
      data: {
        labels: cLabels.length ? cLabels : ['No data'],
        datasets: [{
          data: cCounts.length ? cCounts : [1],
          backgroundColor: ['#2563eb', '#10b981', '#f59e0b', '#8b5cf6', '#64748b'],
          borderWidth: 2,
          borderColor: '#ffffff'
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            position: 'bottom',
            labels: { boxWidth: 10, font: { size: 10 }, padding: 8 }
          }
        }
      }
    });
  }

});
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>

<?php
/**
 * Universal Logbook Records View, Filter & Action Center
 * Guidance Office Logbook Management System (OSAS)
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/helpers.php';

require_login();

// Retrieve requested logbook type
$type_slug = $_GET['type'] ?? 'clients-log';
$logbook_type = get_logbook_type_by_slug($pdo, $type_slug);

if (!$logbook_type) {
    set_flash('danger', 'Logbook section not found.');
    header('Location: dashboard.php');
    exit;
}

$page_title = $logbook_type['title'];
$fields = get_logbook_fields($pdo, $logbook_type['id']);

// Filter parameters
$tab = $_GET['tab'] ?? 'active'; // 'active' or 'archived'
$search = trim($_GET['search'] ?? '');
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$client_type_filter = $_GET['client_type'] ?? '';
$purpose_filter = $_GET['purpose'] ?? '';
$return_status_filter = $_GET['return_status'] ?? '';
$highlight_id = (int)($_GET['highlight'] ?? 0);

// Build SQL Query
$where = ["lr.logbook_type_id = ?"];
$params = [$logbook_type['id']];

if ($tab === 'archived') {
    $where[] = "lr.status = 'archived'";
} else {
    $where[] = "lr.status = 'active'";
}

if (!empty($date_from) && !empty($date_to)) {
    $where[] = "lr.entry_date BETWEEN ? AND ?";
    $params[] = $date_from;
    $params[] = $date_to;
} elseif (!empty($date_from)) {
    $where[] = "lr.entry_date >= ?";
    $params[] = $date_from;
} elseif (!empty($date_to)) {
    $where[] = "lr.entry_date <= ?";
    $params[] = $date_to;
}

if (!empty($client_type_filter)) {
    $where[] = "lr.client_type = ?";
    $params[] = $client_type_filter;
}

if (!empty($purpose_filter)) {
    $where[] = "lr.purpose LIKE ?";
    $params[] = "%{$purpose_filter}%";
}

if (!empty($return_status_filter)) {
    $where[] = "JSON_UNQUOTE(JSON_EXTRACT(lr.data_json, '$.return_status')) = ?";
    $params[] = $return_status_filter;
}

if (!empty($search)) {
    $where[] = "(lr.client_name LIKE ? OR lr.purpose LIKE ? OR lr.notes LIKE ? OR lr.data_json LIKE ?)";
    $like = "%{$search}%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$where_sql = implode(' AND ', $where);
$sql = "SELECT lr.*, u_creator.full_name as creator_name, u_editor.full_name as editor_name 
        FROM `logbook_records` lr
        LEFT JOIN `users` u_creator ON u_creator.id = lr.created_by
        LEFT JOIN `users` u_editor ON u_editor.id = lr.updated_by
        WHERE {$where_sql}
        ORDER BY lr.record_no DESC, lr.entry_date DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$records = $stmt->fetchAll();

// Counts for Active vs Archived tabs
$cnt_active = $pdo->prepare("SELECT COUNT(*) FROM `logbook_records` WHERE `logbook_type_id` = ? AND `status` = 'active'");
$cnt_active->execute([$logbook_type['id']]);
$total_active = (int)$cnt_active->fetchColumn();

$cnt_archived = $pdo->prepare("SELECT COUNT(*) FROM `logbook_records` WHERE `logbook_type_id` = ? AND `status` = 'archived'");
$cnt_archived->execute([$logbook_type['id']]);
$total_archived = (int)$cnt_archived->fetchColumn();

// Next Record Number
$next_record_no = get_next_record_no($pdo, $logbook_type['id']);

require_once __DIR__ . '/includes/header.php';
?>

<div class="space-y-6">

  <!-- Header & Action Bar -->
  <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
    <div class="flex items-start gap-4">
      <div class="w-12 h-12 rounded-2xl bg-brand-50 border border-brand-200 text-brand-900 flex items-center justify-center text-2xl flex-shrink-0 mt-0.5 shadow-sm">
        <i class="bi <?= e($logbook_type['icon']) ?>"></i>
      </div>
      <div>
        <div class="flex items-center gap-2 flex-wrap">
          <h1 class="text-xl sm:text-2xl font-bold text-slate-900"><?= e($logbook_type['title']) ?></h1>
          <?php if ($logbook_type['is_system']): ?>
            <span class="badge-pill bg-slate-100 text-slate-600 text-xs font-semibold">Core Logbook</span>
          <?php else: ?>
            <span class="badge-pill bg-purple-50 text-purple-700 border border-purple-200 text-xs font-semibold">Custom Section</span>
          <?php endif; ?>
        </div>
        <p class="text-xs sm:text-sm text-slate-500 mt-1 max-w-2xl leading-relaxed"><?= e($logbook_type['description']) ?></p>
      </div>
    </div>

    <!-- Actions: Add, Print, Export -->
    <div class="flex flex-wrap items-center gap-2">
      <button type="button" onclick="openCreateModal()" class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-xl bg-[#006633] hover:bg-[#004422] text-white font-semibold text-sm shadow-sm transition">
        <i class="bi bi-plus-lg"></i>
        <span>Add Entry</span>
      </button>

      <!-- Print Paper Replica -->
      <a href="print.php?type=<?= urlencode($type_slug) ?><?= !empty($date_from) ? '&date_from='.urlencode($date_from).'&date_to='.urlencode($date_to) : '' ?>" target="_blank" class="inline-flex items-center gap-1.5 px-3.5 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-medium text-sm transition" title="Print-friendly view replicating the paper logbook sheet">
        <i class="bi bi-printer"></i>
        <span class="hidden sm:inline">Print Paper Sheet</span>
      </a>

      <!-- Export Dropdown -->
      <div class="relative group">
        <button type="button" class="inline-flex items-center gap-1.5 px-3.5 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-medium text-sm transition focus:outline-none">
          <i class="bi bi-download"></i>
          <span>Export</span>
          <i class="bi bi-chevron-down text-xs"></i>
        </button>
        <div class="absolute right-0 top-full pt-1 w-44 hidden group-hover:block group-focus-within:block transition z-50">
          <div class="bg-white border border-slate-200 rounded-xl shadow-xl py-1.5">
            <a href="export.php?format=csv&type=<?= urlencode($type_slug) ?>&tab=<?= urlencode($tab) ?><?= !empty($search) ? '&search='.urlencode($search) : '' ?><?= !empty($date_from) ? '&date_from='.urlencode($date_from).'&date_to='.urlencode($date_to) : '' ?>" class="flex items-center gap-2 px-3 py-2 text-xs font-medium text-slate-700 hover:bg-slate-50">
              <i class="bi bi-filetype-csv text-emerald-600 text-base"></i> Export as CSV
            </a>
            <a href="export.php?format=excel&type=<?= urlencode($type_slug) ?>&tab=<?= urlencode($tab) ?><?= !empty($search) ? '&search='.urlencode($search) : '' ?><?= !empty($date_from) ? '&date_from='.urlencode($date_from).'&date_to='.urlencode($date_to) : '' ?>" class="flex items-center gap-2 px-3 py-2 text-xs font-medium text-slate-700 hover:bg-slate-50">
              <i class="bi bi-file-earmark-excel text-emerald-700 text-base"></i> Export as Excel (.xls)
            </a>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Active vs Archived Tabs -->
  <div class="flex items-center justify-between border-b border-slate-200">
    <div class="flex items-center gap-2">
      <a href="logbook.php?type=<?= urlencode($type_slug) ?>&tab=active" class="px-4 py-2.5 text-sm font-semibold border-b-2 transition flex items-center gap-2 <?= $tab === 'active' ? 'border-[#006633] text-[#006633]' : 'border-transparent text-slate-500 hover:text-slate-700' ?>">
        <span>Active Entries</span>
        <span class="badge-pill <?= $tab === 'active' ? 'bg-[#e6f4ea] text-[#006633]' : 'bg-slate-100 text-slate-600' ?>"><?= $total_active ?></span>
      </a>

      <a href="logbook.php?type=<?= urlencode($type_slug) ?>&tab=archived" class="px-4 py-2.5 text-sm font-semibold border-b-2 transition flex items-center gap-2 <?= $tab === 'archived' ? 'border-amber-600 text-amber-700' : 'border-transparent text-slate-500 hover:text-slate-700' ?>">
        <span>Archived</span>
        <span class="badge-pill <?= $tab === 'archived' ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-600' ?>"><?= $total_archived ?></span>
      </a>
    </div>

    <div class="text-xs text-slate-400 hidden sm:block">
      Showing <?= count($records) ?> records
    </div>
  </div>

  <!-- Search & Filter Controls -->
  <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm">
    <form method="GET" action="logbook.php" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3 items-center">
      <input type="hidden" name="type" value="<?= e($type_slug) ?>">
      <input type="hidden" name="tab" value="<?= e($tab) ?>">

      <!-- Search keyword -->
      <div class="lg:col-span-4 relative">
        <i class="bi bi-search absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
        <input type="text" name="search" value="<?= e($search) ?>" placeholder="Search by name, purpose, course..." class="w-full pl-9 pr-3 py-2 text-xs rounded-xl border border-slate-200 focus:outline-none focus:ring-1 focus:ring-brand-500 text-slate-800">
      </div>

      <!-- Date From -->
      <div class="lg:col-span-2">
        <input type="date" name="date_from" value="<?= e($date_from) ?>" title="From Date" class="w-full px-3 py-2 text-xs rounded-xl border border-slate-200 focus:outline-none focus:ring-1 focus:ring-brand-500 text-slate-700">
      </div>

      <!-- Date To -->
      <div class="lg:col-span-2">
        <input type="date" name="date_to" value="<?= e($date_to) ?>" title="To Date" class="w-full px-3 py-2 text-xs rounded-xl border border-slate-200 focus:outline-none focus:ring-1 focus:ring-brand-500 text-slate-700">
      </div>

      <!-- Section-specific Filters -->
      <?php if ($type_slug === 'borrowers-log'): ?>
        <div class="lg:col-span-2">
          <select name="return_status" class="w-full px-3 py-2 text-xs rounded-xl border border-slate-200 focus:outline-none focus:ring-1 focus:ring-brand-500 text-slate-700">
            <option value="">All Return Statuses</option>
            <option value="Pending" <?= $return_status_filter === 'Pending' ? 'selected' : '' ?>>Pending Return</option>
            <option value="Returned" <?= $return_status_filter === 'Returned' ? 'selected' : '' ?>>Returned</option>
            <option value="Overdue" <?= $return_status_filter === 'Overdue' ? 'selected' : '' ?>>Overdue</option>
          </select>
        </div>
      <?php elseif ($type_slug === 'good-moral-log'): ?>
        <div class="lg:col-span-2">
          <select name="purpose" class="w-full px-3 py-2 text-xs rounded-xl border border-slate-200 focus:outline-none focus:ring-1 focus:ring-brand-500 text-slate-700">
            <option value="">All Moral Purposes</option>
            <option value="PRC" <?= $purpose_filter === 'PRC' ? 'selected' : '' ?>>PRC/Gov't Exam</option>
            <option value="Scholarship" <?= $purpose_filter === 'Scholarship' ? 'selected' : '' ?>>Scholarship</option>
            <option value="Transfer" <?= $purpose_filter === 'Transfer' ? 'selected' : '' ?>>Transfer</option>
            <option value="Employment" <?= $purpose_filter === 'Employment' ? 'selected' : '' ?>>Employment</option>
            <option value="Other" <?= $purpose_filter === 'Other' ? 'selected' : '' ?>>Other</option>
          </select>
        </div>
      <?php else: ?>
        <div class="lg:col-span-2">
          <select name="client_type" class="w-full px-3 py-2 text-xs rounded-xl border border-slate-200 focus:outline-none focus:ring-1 focus:ring-brand-500 text-slate-700">
            <option value="">All Client Types</option>
            <option value="Student" <?= $client_type_filter === 'Student' ? 'selected' : '' ?>>Student</option>
            <option value="Visitor" <?= $client_type_filter === 'Visitor' ? 'selected' : '' ?>>Visitor</option>
            <option value="Alumni" <?= $client_type_filter === 'Alumni' ? 'selected' : '' ?>>Alumni</option>
            <option value="Faculty" <?= $client_type_filter === 'Faculty' ? 'selected' : '' ?>>Faculty</option>
          </select>
        </div>
      <?php endif; ?>

      <!-- Filter / Reset Buttons -->
      <div class="lg:col-span-2 flex items-center gap-2">
        <button type="submit" class="flex-1 py-2 px-3 bg-brand-900 hover:bg-brand-800 text-white font-medium rounded-xl text-xs transition">Apply</button>
        <?php if (!empty($search) || !empty($date_from) || !empty($date_to) || !empty($client_type_filter) || !empty($purpose_filter) || !empty($return_status_filter)): ?>
          <a href="logbook.php?type=<?= urlencode($type_slug) ?>&tab=<?= urlencode($tab) ?>" class="p-2 text-slate-400 hover:text-slate-600 text-xs" title="Reset Filters"><i class="bi bi-x-circle-fill"></i></a>
        <?php endif; ?>
      </div>

    </form>
  </div>

  <!-- Records Table -->
  <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
    <?php if (empty($records)): ?>
      <div class="p-12 text-center">
        <div class="w-16 h-16 rounded-full bg-slate-100 text-slate-400 flex items-center justify-center mx-auto mb-3 text-2xl">
          <i class="bi bi-inbox"></i>
        </div>
        <h3 class="text-base font-bold text-slate-800">No records found</h3>
        <p class="text-xs text-slate-500 mt-1 max-w-sm mx-auto">There are no <?= $tab === 'archived' ? 'archived' : 'active' ?> entries matching your current filters in this logbook section.</p>
        <button type="button" onclick="openCreateModal()" class="mt-4 px-4 py-2 bg-brand-900 text-white rounded-xl text-xs font-semibold hover:bg-brand-800 transition">
          + Add First Entry
        </button>
      </div>
    <?php else: ?>
      <div class="overflow-x-auto">
        <table class="w-full text-left text-xs">
          <thead>
            <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 font-bold uppercase tracking-wider">
              <th class="py-3 px-3.5 whitespace-nowrap">No.</th>
              <th class="py-3 px-3.5 whitespace-nowrap">Date</th>
              <th class="py-3 px-3.5">Name</th>

              <?php if ($type_slug === 'borrowers-log'): ?>
                <th class="py-3 px-3.5">Client Type</th>
                <th class="py-3 px-3.5">Course & Year / Dept</th>
                <th class="py-3 px-3.5">Purpose / Item</th>
                <th class="py-3 px-3.5">Date of Return</th>
                <th class="py-3 px-3.5">Status</th>
                <th class="py-3 px-3.5">Remarks</th>

              <?php elseif ($type_slug === 'good-moral-log'): ?>
                <th class="py-3 px-3.5">Year Graduated</th>
                <th class="py-3 px-3.5">Purpose</th>
                <th class="py-3 px-3.5">Confirmation</th>
                <th class="py-3 px-3.5">Date Issued</th>
                <th class="py-3 px-3.5">Remarks</th>

              <?php elseif ($type_slug === 'clients-log'): ?>
                <th class="py-3 px-3.5">Client Type</th>
                <th class="py-3 px-3.5">Course & Year</th>
                <th class="py-3 px-3.5">Purpose</th>
                <th class="py-3 px-3.5">Time In</th>
                <th class="py-3 px-3.5">Time Out</th>
                <th class="py-3 px-3.5">Signature</th>

              <?php elseif ($type_slug === 'visitors-log'): ?>
                <th class="py-3 px-3.5">Agency / Office</th>
                <th class="py-3 px-3.5">Purpose</th>
                <th class="py-3 px-3.5">Time In</th>
                <th class="py-3 px-3.5">Time Out</th>
                <th class="py-3 px-3.5">Signature</th>
                <th class="py-3 px-3.5">Remarks</th>

              <?php else: ?>
                <!-- Custom Logbook: Dynamic Table Columns -->
                <?php foreach ($fields as $cf): 
                  if (in_array($cf['field_key'], ['name', 'date'])) continue;
                  if (!$cf['show_in_table']) continue;
                ?>
                  <th class="py-3 px-3.5"><?= e($cf['field_label']) ?></th>
                <?php endforeach; ?>
              <?php endif; ?>

              <th class="py-3 px-3.5 text-right whitespace-nowrap">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <?php foreach ($records as $r): 
              $data = json_decode($r['data_json'] ?? '{}', true) ?: [];
              $is_highlighted = ($r['id'] === $highlight_id);
            ?>
              <tr class="hover:bg-slate-50/80 transition <?= $is_highlighted ? 'bg-amber-50/80' : '' ?>">
                
                <!-- No. -->
                <td class="py-3 px-3.5 font-mono font-bold text-slate-700 whitespace-nowrap">
                  #<?= (int)$r['record_no'] ?>
                </td>

                <!-- Date -->
                <td class="py-3 px-3.5 whitespace-nowrap text-slate-600 font-medium">
                  <?= format_date($r['entry_date']) ?>
                </td>

                <!-- Name -->
                <td class="py-3 px-3.5 font-bold text-slate-900">
                  <?= e($r['client_name']) ?>
                </td>

                <!-- Specific Columns per Logbook -->
                <?php if ($type_slug === 'borrowers-log'): 
                  $return_status = $data['return_status'] ?? 'Pending';
                ?>
                  <td class="py-3 px-3.5">
                    <span class="badge-pill bg-emerald-50 text-emerald-800 border border-emerald-200"><?= e($data['client_type'] ?? 'Student') ?></span>
                  </td>
                  <td class="py-3 px-3.5 text-slate-700"><?= e($data['course_year_dept'] ?? '—') ?></td>
                  <td class="py-3 px-3.5 font-medium text-slate-800 max-w-xs truncate" title="<?= e($data['purpose'] ?? '') ?>"><?= e($data['purpose'] ?? '—') ?></td>
                  <td class="py-3 px-3.5 whitespace-nowrap text-slate-600 font-medium"><?= format_date($data['date_of_return'] ?? '') ?></td>
                  <td class="py-3 px-3.5 whitespace-nowrap">
                    <?php if ($return_status === 'Returned'): ?>
                      <span class="badge-pill bg-emerald-50 text-emerald-700 border border-emerald-200"><i class="bi bi-check2 mr-1"></i> Returned</span>
                    <?php elseif ($return_status === 'Overdue'): ?>
                      <span class="badge-pill bg-rose-50 text-rose-700 border border-rose-200"><i class="bi bi-exclamation-octagon mr-1"></i> Overdue</span>
                    <?php else: ?>
                      <span class="badge-pill bg-amber-50 text-amber-700 border border-amber-200"><i class="bi bi-clock-history mr-1"></i> Pending</span>
                    <?php endif; ?>
                  </td>
                  <td class="py-3 px-3.5 text-slate-500 max-w-xs truncate" title="<?= e($data['remarks'] ?? '') ?>"><?= e($data['remarks'] ?? '—') ?></td>

                <?php elseif ($type_slug === 'good-moral-log'): ?>
                  <td class="py-3 px-3.5 text-slate-700"><?= e($data['year_graduated'] ?? '—') ?></td>
                  <td class="py-3 px-3.5 font-medium text-slate-800">
                    <span class="badge-pill bg-emerald-50 text-emerald-800 border border-emerald-200"><?= e($data['purpose'] ?? '—') ?></span>
                    <?php if (!empty($data['purpose_details'])): ?>
                      <div class="text-[11px] text-slate-500 mt-0.5"><?= e($data['purpose_details']) ?></div>
                    <?php endif; ?>
                  </td>
                  <td class="py-3 px-3.5 whitespace-nowrap">
                    <span class="text-emerald-700 font-semibold inline-flex items-center gap-1">
                      <i class="bi bi-check-circle-fill"></i> Confirmed
                    </span>
                  </td>
                  <td class="py-3 px-3.5 whitespace-nowrap font-medium text-slate-700">
                    <?php if (!empty($data['date_issued'])): ?>
                      <span class="badge-pill bg-emerald-50 text-emerald-800 border border-emerald-200"><?= format_date($data['date_issued']) ?></span>
                    <?php else: ?>
                      <span class="badge-pill bg-slate-100 text-slate-500">Processing</span>
                    <?php endif; ?>
                  </td>
                  <td class="py-3 px-3.5 text-slate-500 max-w-xs truncate"><?= e($data['remarks'] ?? '—') ?></td>

                <?php elseif ($type_slug === 'clients-log'): ?>
                  <td class="py-3 px-3.5">
                    <span class="badge-pill bg-emerald-50 text-emerald-800 border border-emerald-200"><?= e($data['client_type'] ?? 'Student') ?></span>
                  </td>
                  <td class="py-3 px-3.5 text-slate-700"><?= e($data['course_year'] ?? '—') ?></td>
                  <td class="py-3 px-3.5 font-medium text-slate-800"><?= e($data['purpose'] ?? '—') ?></td>
                  <td class="py-3 px-3.5 whitespace-nowrap text-slate-700 font-mono font-medium"><?= format_time($data['time_in'] ?? '') ?></td>
                  <td class="py-3 px-3.5 whitespace-nowrap text-slate-700 font-mono font-medium"><?= format_time($data['time_out'] ?? '') ?></td>
                  <td class="py-3 px-3.5 whitespace-nowrap">
                    <span class="text-emerald-700 font-semibold inline-flex items-center gap-1">
                      <i class="bi bi-check2-circle"></i> Signed
                    </span>
                  </td>

                <?php elseif ($type_slug === 'visitors-log'): ?>
                  <td class="py-3 px-3.5 font-medium text-slate-800"><?= e($data['agency'] ?? '—') ?></td>
                  <td class="py-3 px-3.5 text-slate-700"><?= e($data['purpose'] ?? '—') ?></td>
                  <td class="py-3 px-3.5 whitespace-nowrap text-slate-700 font-mono font-medium"><?= format_time($data['time_in'] ?? '') ?></td>
                  <td class="py-3 px-3.5 whitespace-nowrap text-slate-700 font-mono font-medium"><?= format_time($data['time_out'] ?? '') ?></td>
                  <td class="py-3 px-3.5 whitespace-nowrap">
                    <span class="text-emerald-700 font-semibold inline-flex items-center gap-1">
                      <i class="bi bi-check2-circle"></i> Signed
                    </span>
                  </td>
                  <td class="py-3 px-3.5 text-slate-500 max-w-xs truncate"><?= e($data['remarks'] ?? '—') ?></td>

                <?php else: ?>
                  <!-- Custom Fields display -->
                  <?php foreach ($fields as $cf): 
                    if (in_array($cf['field_key'], ['name', 'date'])) continue;
                    if (!$cf['show_in_table']) continue;
                    $val = $data[$cf['field_key']] ?? '';
                  ?>
                    <td class="py-3 px-3.5 text-slate-700">
                      <?php if ($cf['field_type'] === 'date'): ?>
                        <?= format_date($val) ?>
                      <?php elseif ($cf['field_type'] === 'time'): ?>
                        <?= format_time($val) ?>
                      <?php elseif ($cf['field_type'] === 'signature_confirm'): ?>
                        <span class="text-emerald-700 font-semibold inline-flex items-center gap-1">
                          <i class="bi bi-check-circle-fill"></i> Confirmed
                        </span>
                      <?php elseif ($cf['field_type'] === 'multiselect'): ?>
                        <?php if (!empty($val)): 
                          $parts = explode(',', $val);
                        ?>
                          <div class="flex flex-wrap gap-1 max-w-xs">
                            <?php foreach ($parts as $p): ?>
                              <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[11px] font-semibold bg-[#e6f4ea] text-[#006633] border border-[#b7dfc8]/60">
                                <?= e(trim($p)) ?>
                              </span>
                            <?php endforeach; ?>
                          </div>
                        <?php else: ?>
                          —
                        <?php endif; ?>
                      <?php else: ?>
                        <?= e($val ?: '—') ?>
                      <?php endif; ?>
                    </td>
                  <?php endforeach; ?>
                <?php endif; ?>

                <!-- Actions Cell -->
                <td class="py-3 px-3.5 text-right whitespace-nowrap">
                  <div class="inline-flex items-center gap-1">
                    
                    <!-- View Details Modal Trigger -->
                    <button type="button" onclick='openViewModal(<?= json_encode($r, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP) ?>, <?= json_encode($data, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP) ?>)' class="p-1.5 rounded-lg text-slate-500 hover:text-slate-800 hover:bg-slate-100" title="View Record Details">
                      <i class="bi bi-eye"></i>
                    </button>

                    <!-- Edit Entry -->
                    <button type="button" onclick='openEditModal(<?= json_encode($r, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP) ?>, <?= json_encode($data, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP) ?>)' class="p-1.5 rounded-lg text-brand-700 hover:text-brand-900 hover:bg-brand-50" title="Edit Entry">
                      <i class="bi bi-pencil-square"></i>
                    </button>

                    <?php if ($r['status'] === 'active'): ?>
                      <!-- Archive Record -->
                      <a href="entry_action.php?action=archive&id=<?= $r['id'] ?>&token=<?= csrf_token() ?>" onclick="return confirm('Archive record #<?= $r['record_no'] ?>? It will be moved to the Archived section.')" class="p-1.5 rounded-lg text-amber-600 hover:text-amber-800 hover:bg-amber-50" title="Archive Record">
                        <i class="bi bi-archive"></i>
                      </a>
                    <?php else: ?>
                      <!-- Restore Record -->
                      <a href="entry_action.php?action=restore&id=<?= $r['id'] ?>&token=<?= csrf_token() ?>" onclick="return confirm('Restore record #<?= $r['record_no'] ?> back to active status?')" class="p-1.5 rounded-lg text-emerald-600 hover:text-emerald-800 hover:bg-emerald-50" title="Restore Record">
                        <i class="bi bi-arrow-counterclockwise"></i>
                      </a>
                    <?php endif; ?>

                    <!-- Delete Record (Admin Only) -->
                    <?php if (is_admin()): ?>
                      <a href="entry_action.php?action=delete&id=<?= $r['id'] ?>&token=<?= csrf_token() ?>" onclick="return confirm('WARNING: Are you sure you want to PERMANENTLY delete record #<?= $r['record_no'] ?>? This cannot be undone.')" class="p-1.5 rounded-lg text-rose-500 hover:text-rose-700 hover:bg-rose-50" title="Permanent Delete (Admin)">
                        <i class="bi bi-trash"></i>
                      </a>
                    <?php endif; ?>

                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

</div>

<!-- Modal: Add / Edit Logbook Record -->
<div id="entry-modal" class="fixed inset-0 z-50 hidden items-center justify-center modal-backdrop-custom modal-container">
  <div class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full mx-4 overflow-hidden border border-slate-200 flex flex-col max-h-[90vh]">
    
    <!-- Modal Header -->
    <div class="px-6 py-4 border-b border-slate-200 flex items-center justify-between bg-slate-50">
      <div>
        <h3 id="modal-heading" class="text-base font-bold text-slate-900">Add Entry — <?= e($logbook_type['title']) ?></h3>
        <p class="text-xs text-slate-500 mt-0.5">Fill in the logbook sheet fields below. Required fields are marked with (*).</p>
      </div>
      <button type="button" onclick="closeModal('entry-modal')" class="text-slate-400 hover:text-slate-600 text-xl leading-none">&times;</button>
    </div>

    <!-- Modal Form -->
    <form id="record-form" method="POST" action="entry_action.php" class="overflow-y-auto p-6 space-y-4">
      <input type="hidden" name="action" id="form-action" value="create">
      <input type="hidden" name="logbook_type_id" value="<?= $logbook_type['id'] ?>">
      <input type="hidden" name="type_slug" value="<?= e($type_slug) ?>">
      <input type="hidden" name="record_id" id="form-record-id" value="">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

      <!-- Record Number & Date Row -->
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 bg-slate-50 p-3.5 rounded-xl border border-slate-200">
        <div>
          <label class="block text-xs font-semibold text-slate-700 mb-1">
            Record No. <span class="text-rose-500">*</span>
          </label>
          <div class="flex items-center gap-2">
            <input type="number" name="record_no" id="form_record_no" value="<?= $next_record_no ?>" <?= !is_admin() ? 'readonly' : '' ?> required class="w-full px-3 py-2 text-xs rounded-lg border border-slate-200 focus:outline-none focus:ring-1 focus:ring-brand-500 font-mono font-bold <?= !is_admin() ? 'bg-slate-100 cursor-not-allowed text-slate-600' : 'bg-white text-slate-900' ?>">
            <?php if (is_admin()): ?>
              <span class="text-[10px] bg-amber-50 text-amber-700 font-semibold px-2 py-1 rounded border border-amber-200 whitespace-nowrap">Admin Override</span>
            <?php endif; ?>
          </div>
          <span class="text-[10px] text-slate-400 mt-0.5 block">Sequential log number.</span>
        </div>

        <div>
          <label class="block text-xs font-semibold text-slate-700 mb-1">
            Date <span class="text-rose-500">*</span>
          </label>
          <input type="date" name="entry_date" id="form_entry_date" value="<?= date('Y-m-d') ?>" required class="w-full px-3 py-2 text-xs rounded-lg border border-slate-200 focus:outline-none focus:ring-1 focus:ring-brand-500 text-slate-800">
          <span class="text-[10px] text-slate-400 mt-0.5 block">Log entry date</span>
        </div>
      </div>

      <!-- Dynamic Fields Generated from Configuration -->
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <?php foreach ($fields as $field): 
          if ($field['field_key'] === 'date') continue; // Handled in header row
          $is_full_width = in_array($field['field_type'], ['textarea']) || in_array($field['field_key'], ['name', 'agency', 'purpose']);
        ?>
          <div class="<?= $is_full_width ? 'sm:col-span-2' : '' ?>">
            <label class="block text-xs font-semibold text-slate-700 mb-1" for="dynamic_<?= e($field['field_key']) ?>">
              <?= e($field['field_label']) ?>
              <?php if ($field['is_required']): ?>
                <span class="text-rose-500">*</span>
              <?php else: ?>
                <span class="text-[11px] font-normal text-slate-400 normal-case ml-1">(Optional)</span>
              <?php endif; ?>
            </label>

            <?php if ($field['field_type'] === 'select'): 
              $opts = json_decode($field['options_json'] ?? '[]', true) ?: [];
            ?>
              <select name="fields[<?= e($field['field_key']) ?>]" id="dynamic_<?= e($field['field_key']) ?>" <?= $field['is_required'] ? 'required' : '' ?> class="w-full px-3 py-2 text-xs rounded-lg border border-slate-200 focus:outline-none focus:ring-1 focus:ring-brand-500 text-slate-800 bg-white">
                <option value="">— Select <?= e($field['field_label']) ?> —</option>
                <?php 
                $admin_grp = '';
                foreach ($opts as $op): 
                  if (strpos($op, ' - ') !== false) {
                    $parts = explode(' - ', $op, 2);
                    $gname = $parts[0];
                    if ($gname !== $admin_grp) {
                      if ($admin_grp !== '') echo '</optgroup>';
                      echo '<optgroup label="' . e($gname) . '">';
                      $admin_grp = $gname;
                    }
                    echo '<option value="' . e($op) . '">' . e($op) . '</option>';
                  } else {
                    if ($admin_grp !== '') {
                      echo '</optgroup>';
                      $admin_grp = '';
                    }
                    echo '<option value="' . e($op) . '">' . e($op) . '</option>';
                  }
                endforeach; 
                if ($admin_grp !== '') echo '</optgroup>';
                ?>
              </select>

              <?php if ($field['field_key'] === 'course_year' || $field['field_key'] === 'course_year_dept'): ?>
                <div id="admin_course_year_manual_box" class="hidden mt-2 p-2.5 rounded-lg bg-[#f0fdf4] border border-[#b7dfc8] space-y-1">
                  <label class="block text-[11px] font-bold text-[#006633]" for="admin_course_year_custom">
                    <i class="bi bi-pencil-square mr-1"></i> Specify Course & Year or Office *
                  </label>
                  <input type="text" name="course_year_custom" id="admin_course_year_custom" placeholder="e.g. BSED 2-B, CAS Faculty, Accounting Office" class="w-full px-3 py-1.5 text-xs rounded border border-[#b7dfc8] focus:outline-none focus:ring-1 focus:ring-[#006633] text-slate-800 bg-white">
                </div>
              <?php endif; ?>

              <?php if ($field['field_key'] === 'agency'): ?>
                <div id="admin_agency_manual_box" class="hidden mt-2 p-2.5 rounded-lg bg-[#f0fdf4] border border-[#b7dfc8] space-y-1">
                  <label class="block text-[11px] font-bold text-[#006633]" for="admin_agency_custom">
                    <i class="bi bi-pencil-square mr-1"></i> Specify Agency / Institution / Office *
                  </label>
                  <input type="text" name="agency_custom" id="admin_agency_custom" placeholder="e.g. St. Mary's Academy, Private Citizen, LGU Diffun" class="w-full px-3 py-1.5 text-xs rounded border border-[#b7dfc8] focus:outline-none focus:ring-1 focus:ring-[#006633] text-slate-800 bg-white">
                </div>
              <?php endif; ?>

              <?php if ($field['field_key'] === 'purpose' && $type_slug !== 'good-moral-log'): ?>
                <div id="admin_purpose_manual_box" class="hidden mt-2 p-2.5 rounded-lg bg-[#f0fdf4] border border-[#b7dfc8] space-y-1">
                  <label class="block text-[11px] font-bold text-[#006633]" for="admin_purpose_custom">
                    <i class="bi bi-pencil-square mr-1"></i> Specify Purpose of Visit / Entry *
                  </label>
                  <input type="text" name="purpose_custom" id="admin_purpose_custom" placeholder="e.g. OJT Coordination, Scholarship Validation, Campus Tour" class="w-full px-3 py-1.5 text-xs rounded border border-[#b7dfc8] focus:outline-none focus:ring-1 focus:ring-[#006633] text-slate-800 bg-white">
                </div>
              <?php endif; ?>

            <?php elseif ($field['field_type'] === 'multiselect'): 
              $opts = json_decode($field['options_json'] ?? '[]', true) ?: [];
            ?>
              <div class="relative admin-ms-wrapper" data-field-key="<?= e($field['field_key']) ?>">
                <button type="button" 
                        onclick="toggleAdminMultiSelect('<?= e($field['field_key']) ?>')" 
                        id="admin_btn_ms_<?= e($field['field_key']) ?>" 
                        class="w-full px-3 py-2 text-xs rounded-lg border border-slate-200 focus:outline-none focus:ring-1 focus:ring-brand-500 text-slate-800 bg-white flex items-center justify-between text-left">
                  <div class="flex items-center gap-1.5 overflow-hidden flex-1 mr-2" id="admin_display_ms_<?= e($field['field_key']) ?>">
                    <span id="admin_label_ms_<?= e($field['field_key']) ?>" class="truncate text-slate-500">
                      <?= e($field['placeholder'] ?? '— Select Option(s) —') ?>
                    </span>
                  </div>
                  <i class="bi bi-chevron-down text-slate-400 text-[10px]" id="admin_icon_ms_<?= e($field['field_key']) ?>"></i>
                </button>

                <div id="admin_panel_ms_<?= e($field['field_key']) ?>" class="hidden absolute z-50 left-0 right-0 mt-1 bg-white border border-slate-200 rounded-xl shadow-xl p-2.5 space-y-1.5 max-h-60 overflow-y-auto">
                  <div class="flex items-center justify-between pb-1.5 border-b border-slate-100 text-[11px]">
                    <span class="font-bold text-slate-600 uppercase text-[10px]">Select Options</span>
                    <div class="flex gap-2">
                      <button type="button" onclick="selectAllAdminMS('<?= e($field['field_key']) ?>', true)" class="text-[#006633] font-bold hover:underline">All</button>
                      <span>•</span>
                      <button type="button" onclick="selectAllAdminMS('<?= e($field['field_key']) ?>', false)" class="text-slate-500 hover:underline">Clear</button>
                    </div>
                  </div>
                  <?php 
                  $current_day_heading = '';
                  foreach ($opts as $op): 
                    $day_match = '';
                    foreach (['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'MWF', 'TTh'] as $d) {
                      if (strpos($op, $d) !== false) {
                        $day_match = $d;
                        break;
                      }
                    }
                    if ($day_match && $day_match !== $current_day_heading):
                      $current_day_heading = $day_match;
                  ?>
                    <div class="pt-2 pb-0.5 px-2 text-[10px] font-bold text-[#006633] uppercase tracking-wider bg-slate-50 rounded border-l-2 border-[#006633]">
                      <?= e($current_day_heading) ?>
                    </div>
                  <?php endif; ?>
                    <label class="flex items-center gap-2 px-2 py-1 rounded-lg hover:bg-[#f0fdf4] hover:text-[#006633] cursor-pointer text-xs select-none">
                      <input type="checkbox" 
                             name="fields[<?= e($field['field_key']) ?>][]" 
                             value="<?= e($op) ?>" 
                             onchange="updateAdminMultiSelectState('<?= e($field['field_key']) ?>')" 
                             class="admin-ms-checkbox-<?= e($field['field_key']) ?> rounded text-[#006633] focus:ring-[#006633] border-slate-300">
                      <span class="text-xs text-slate-700 font-medium"><?= e($op) ?></span>
                    </label>
                  <?php endforeach; ?>
                </div>
              </div>

            <?php elseif ($field['field_type'] === 'textarea'): ?>
              <textarea name="fields[<?= e($field['field_key']) ?>]" id="dynamic_<?= e($field['field_key']) ?>" rows="2" placeholder="<?= e($field['placeholder'] ?? '') ?>" <?= $field['is_required'] ? 'required' : '' ?> class="w-full px-3 py-2 text-xs rounded-lg border border-slate-200 focus:outline-none focus:ring-1 focus:ring-brand-500 text-slate-800"></textarea>

            <?php elseif ($field['field_type'] === 'date'): ?>
              <input type="date" name="fields[<?= e($field['field_key']) ?>]" id="dynamic_<?= e($field['field_key']) ?>" <?= $field['is_required'] ? 'required' : '' ?> class="w-full px-3 py-2 text-xs rounded-lg border border-slate-200 focus:outline-none focus:ring-1 focus:ring-brand-500 text-slate-800">

            <?php elseif ($field['field_type'] === 'time'): ?>
              <input type="time" name="fields[<?= e($field['field_key']) ?>]" id="dynamic_<?= e($field['field_key']) ?>" <?= $field['is_required'] ? 'required' : '' ?> class="w-full px-3 py-2 text-xs rounded-lg border border-slate-200 focus:outline-none focus:ring-1 focus:ring-brand-500 text-slate-800 font-mono">

            <?php elseif ($field['field_type'] === 'signature_confirm'): ?>
              <div class="flex items-start gap-2.5 p-3 rounded-xl bg-slate-50 border border-slate-200">
                <input type="checkbox" name="fields[<?= e($field['field_key']) ?>]" id="dynamic_<?= e($field['field_key']) ?>" value="1" checked class="mt-0.5 rounded text-brand-900 focus:ring-brand-500">
                <label for="dynamic_<?= e($field['field_key']) ?>" class="text-xs text-slate-700 cursor-pointer">
                  <span class="font-bold">Attested & Confirmed:</span> The client/visitor has appeared in person and confirmed attendance/request.
                </label>
              </div>

            <?php else: ?>
              <input type="<?= $field['field_type'] === 'number' ? 'number' : 'text' ?>" name="fields[<?= e($field['field_key']) ?>]" id="dynamic_<?= e($field['field_key']) ?>" placeholder="<?= e($field['placeholder'] ?? '') ?>" <?= $field['is_required'] ? 'required' : '' ?> class="w-full px-3 py-2 text-xs rounded-lg border border-slate-200 focus:outline-none focus:ring-1 focus:ring-brand-500 text-slate-800">
            <?php endif; ?>

            <?php if (!empty($field['help_text'])): ?>
              <p class="text-[10px] text-slate-400 mt-0.5"><?= e($field['help_text']) ?></p>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>

      <!-- Additional Optional Office Notes -->
      <div>
        <label class="block text-xs font-semibold text-slate-700 mb-1">Optional Office Notes</label>
        <textarea name="notes" id="form_notes" rows="2" placeholder="Administrative notes visible to guidance staff..." class="w-full px-3 py-2 text-xs rounded-lg border border-slate-200 focus:outline-none focus:ring-1 focus:ring-brand-500 text-slate-800"></textarea>
      </div>

      <!-- Modal Footer -->
      <div class="pt-4 border-t border-slate-200 flex items-center justify-end gap-2.5">
        <button type="button" onclick="closeModal('entry-modal')" class="px-4 py-2.5 rounded-xl border border-slate-200 text-slate-700 font-medium hover:bg-slate-50 transition text-xs">
          Cancel
        </button>
        <button type="submit" id="modal-submit-btn" class="px-5 py-2.5 rounded-xl bg-brand-900 hover:bg-brand-800 text-white font-semibold text-xs shadow-sm transition">
          Save Entry
        </button>
      </div>

    </form>
  </div>
</div>

<!-- Modal: View Record Details -->
<div id="view-modal" class="fixed inset-0 z-50 hidden items-center justify-center modal-backdrop-custom modal-container">
  <div class="bg-white rounded-2xl shadow-2xl max-w-lg w-full mx-4 overflow-hidden border border-slate-200 flex flex-col">
    <div class="px-6 py-4 border-b border-slate-200 flex items-center justify-between bg-slate-50">
      <div>
        <h3 id="view-modal-title" class="text-base font-bold text-slate-900">Record Details</h3>
        <p class="text-xs text-slate-500" id="view-modal-sub">Guidance Office Log Entry</p>
      </div>
      <button type="button" onclick="closeModal('view-modal')" class="text-slate-400 hover:text-slate-600 text-xl leading-none">&times;</button>
    </div>

    <div class="p-6 space-y-4 overflow-y-auto max-h-[70vh]" id="view-modal-content">
      <!-- Injected by JavaScript -->
    </div>

    <div class="px-6 py-3 bg-slate-50 border-t border-slate-200 flex items-center justify-between">
      <div class="text-[11px] text-slate-400" id="view-modal-audit">Logged in system</div>
      <button type="button" onclick="closeModal('view-modal')" class="px-4 py-2 bg-slate-200 hover:bg-slate-300 rounded-xl text-xs font-semibold text-slate-800 transition">
        Close
      </button>
    </div>
  </div>
</div>

<script>
// Open Add Entry Modal
function openCreateModal() {
  document.getElementById('form-action').value = 'create';
  document.getElementById('form-record-id').value = '';
  document.getElementById('modal-heading').innerText = 'Add Entry — <?= e($logbook_type['title']) ?>';
  document.getElementById('modal-submit-btn').innerText = 'Save Entry';
  document.getElementById('form_record_no').value = '<?= $next_record_no ?>';
  document.getElementById('form_entry_date').value = '<?= date('Y-m-d') ?>';
  document.getElementById('form_notes').value = '';

  // Clear dynamic fields
  document.querySelectorAll('#record-form [id^="dynamic_"]').forEach(el => {
    if (el.type === 'checkbox') {
      el.checked = true;
    } else {
      el.value = '';
    }
  });

  const adminAgencyManualBox = document.getElementById('admin_agency_manual_box');
  const adminAgencyCustom = document.getElementById('admin_agency_custom');
  if (adminAgencyManualBox) adminAgencyManualBox.classList.add('hidden');
  if (adminAgencyCustom) {
    adminAgencyCustom.value = '';
    adminAgencyCustom.required = false;
  }

  const adminPurposeManualBox = document.getElementById('admin_purpose_manual_box');
  const adminPurposeCustom = document.getElementById('admin_purpose_custom');
  if (adminPurposeManualBox) adminPurposeManualBox.classList.add('hidden');
  if (adminPurposeCustom) {
    adminPurposeCustom.value = '';
    adminPurposeCustom.required = false;
  }

  const adminCourseYearManualBox = document.getElementById('admin_course_year_manual_box');
  const adminCourseYearCustom = document.getElementById('admin_course_year_custom');
  if (adminCourseYearManualBox) adminCourseYearManualBox.classList.add('hidden');
  if (adminCourseYearCustom) {
    adminCourseYearCustom.value = '';
    adminCourseYearCustom.required = false;
  }

  // Clear multiselect fields
  document.querySelectorAll('[class*="admin-ms-checkbox-"]').forEach(cb => cb.checked = false);
  document.querySelectorAll('[id^="admin_label_ms_"]').forEach(l => {
    l.innerText = '— Select Option(s) —';
  });

  openModal('entry-modal');
}

// Open Edit Entry Modal pre-filled
function openEditModal(record, data) {
  document.getElementById('form-action').value = 'update';
  document.getElementById('form-record-id').value = record.id;
  document.getElementById('modal-heading').innerText = 'Edit Entry #' + record.record_no + ' — <?= e($logbook_type['title']) ?>';
  document.getElementById('modal-submit-btn').innerText = 'Update Entry';
  document.getElementById('form_record_no').value = record.record_no;
  document.getElementById('form_entry_date').value = record.entry_date;
  document.getElementById('form_notes').value = record.notes || '';

  // Populate dynamic fields
  document.querySelectorAll('[class*="admin-ms-checkbox-"]').forEach(cb => cb.checked = false);
  for (const [key, val] of Object.entries(data)) {
    const el = document.getElementById('dynamic_' + key);
    if (el) {
      if (el.type === 'checkbox') {
        el.checked = (val == '1' || val === true || val === 'yes');
      } else {
        el.value = val;
      }

      // Special handling for Agency dropdown with manual input
      if (key === 'agency' && el.tagName === 'SELECT') {
        const adminAgencyManualBox = document.getElementById('admin_agency_manual_box');
        const adminAgencyCustom = document.getElementById('admin_agency_custom');
        const hasOption = Array.from(el.options).some(opt => opt.value === val);
        if (hasOption) {
          el.value = val;
          if (adminAgencyCustom) adminAgencyCustom.value = '';
          if (adminAgencyManualBox) {
            if (val.toLowerCase() === 'none' || val.toLowerCase() === 'other') {
              adminAgencyManualBox.classList.remove('hidden');
            } else {
              adminAgencyManualBox.classList.add('hidden');
            }
          }
        } else if (val) {
          const fallbackOpt = Array.from(el.options).some(opt => opt.value === 'Other') ? 'Other' : 'None';
          el.value = fallbackOpt;
          if (adminAgencyCustom) adminAgencyCustom.value = val;
          if (adminAgencyManualBox) adminAgencyManualBox.classList.remove('hidden');
        }
      }

      // Special handling for Purpose dropdown with manual input
      if (key === 'purpose' && el.tagName === 'SELECT') {
        const adminPurposeManualBox = document.getElementById('admin_purpose_manual_box');
        const adminPurposeCustom = document.getElementById('admin_purpose_custom');
        const hasOption = Array.from(el.options).some(opt => opt.value === val);
        if (hasOption) {
          el.value = val;
          if (adminPurposeCustom) adminPurposeCustom.value = '';
          if (adminPurposeManualBox) {
            if (val.toLowerCase() === 'none' || val.toLowerCase() === 'other') {
              adminPurposeManualBox.classList.remove('hidden');
            } else {
              adminPurposeManualBox.classList.add('hidden');
            }
          }
        } else if (val) {
          const fallbackOpt = Array.from(el.options).some(opt => opt.value === 'Other') ? 'Other' : 'None';
          el.value = fallbackOpt;
          if (adminPurposeCustom) adminPurposeCustom.value = val;
          if (adminPurposeManualBox) adminPurposeManualBox.classList.remove('hidden');
        }
      }

      // Special handling for Course & Year dropdown with manual input
      if ((key === 'course_year' || key === 'course_year_dept') && el.tagName === 'SELECT') {
        const adminCourseYearManualBox = document.getElementById('admin_course_year_manual_box');
        const adminCourseYearCustom = document.getElementById('admin_course_year_custom');
        const hasOption = Array.from(el.options).some(opt => opt.value === val);
        if (hasOption) {
          el.value = val;
          if (adminCourseYearCustom) adminCourseYearCustom.value = '';
          if (adminCourseYearManualBox) {
            const vLow = val.toLowerCase();
            if (vLow === 'none' || vLow === 'other' || vLow.includes('office') || vLow.includes('faculty')) {
              adminCourseYearManualBox.classList.remove('hidden');
            } else {
              adminCourseYearManualBox.classList.add('hidden');
            }
          }
        } else if (val) {
          const fallbackOpt = Array.from(el.options).some(opt => opt.value === 'Other') ? 'Other' : 'None';
          el.value = fallbackOpt;
          if (adminCourseYearCustom) adminCourseYearCustom.value = val;
          if (adminCourseYearManualBox) adminCourseYearManualBox.classList.remove('hidden');
        }
      }
    }

    // Populate multiselect fields
    const msCbs = document.querySelectorAll('.admin-ms-checkbox-' + key);
    if (msCbs.length > 0 && typeof val === 'string') {
      const selectedArr = val.split(',').map(s => s.trim());
      msCbs.forEach(cb => {
        if (selectedArr.includes(cb.value)) {
          cb.checked = true;
        }
      });
      updateAdminMultiSelectState(key);
    }
  }

  // Also populate client_name if mapped to dynamic_name
  const nameEl = document.getElementById('dynamic_name');
  if (nameEl && !nameEl.value) {
    nameEl.value = record.client_name || '';
  }

  openModal('entry-modal');
}

function escapeHtml(str) {
  return String(str ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

// Open View Details Modal
function openViewModal(record, data) {
  document.getElementById('view-modal-title').innerText = '<?= e($logbook_type['title']) ?> #' + record.record_no;
  document.getElementById('view-modal-sub').innerText = 'Date: ' + record.entry_date;
  
  let html = '<div class="space-y-3">';
  html += '<div class="p-3 bg-slate-50 rounded-xl border border-slate-200">';
  html += '<div class="text-[10px] uppercase font-bold text-slate-400">Primary Name</div>';
  html += '<div class="text-sm font-bold text-slate-900">' + escapeHtml(record.client_name || '—') + '</div>';
  html += '</div>';

  html += '<div class="grid grid-cols-2 gap-2.5">';
  for (const [k, v] of Object.entries(data)) {
    if (k === 'name') continue;
    const label = k.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
    html += '<div class="p-2.5 rounded-lg border border-slate-100 bg-white">';
    html += '<div class="text-[10px] font-semibold text-slate-400 uppercase">' + escapeHtml(label) + '</div>';
    html += '<div class="text-xs font-medium text-slate-800 mt-0.5">' + escapeHtml(v || '—') + '</div>';
    html += '</div>';
  }
  html += '</div>';

  if (record.notes) {
    html += '<div class="p-3 bg-amber-50/50 rounded-xl border border-amber-200/60">';
    html += '<div class="text-[10px] uppercase font-bold text-amber-800">Staff Remarks / Notes</div>';
    html += '<div class="text-xs text-amber-900 mt-1">' + escapeHtml(record.notes) + '</div>';
    html += '</div>';
  }

  html += '</div>';
  document.getElementById('view-modal-content').innerHTML = html;

  const creator = record.creator_name ? record.creator_name : 'Walk-in / Staff';
  const createdDate = record.created_at ? record.created_at : '';
  document.getElementById('view-modal-audit').innerText = 'Created by: ' + creator + ' (' + createdDate + ')';

  openModal('view-modal');
}

// Auto open modal if action=new in URL
<?php if (isset($_GET['action']) && $_GET['action'] === 'new'): ?>
  document.addEventListener('DOMContentLoaded', function() {
    openCreateModal();
  });
<?php endif; ?>
// Admin Multi-Select Dropdown Handlers
function toggleAdminMultiSelect(key) {
  const panel = document.getElementById('admin_panel_ms_' + key);
  const icon = document.getElementById('admin_icon_ms_' + key);
  if (!panel) return;

  document.querySelectorAll('[id^="admin_panel_ms_"]').forEach(p => {
    if (p.id !== 'admin_panel_ms_' + key) {
      p.classList.add('hidden');
    }
  });

  const isHidden = panel.classList.contains('hidden');
  if (isHidden) {
    panel.classList.remove('hidden');
    if (icon) icon.classList.add('rotate-180');
  } else {
    panel.classList.add('hidden');
    if (icon) icon.classList.remove('rotate-180');
  }
}

function selectAllAdminMS(key, selectAll) {
  document.querySelectorAll('.admin-ms-checkbox-' + key).forEach(cb => {
    cb.checked = selectAll;
  });
  updateAdminMultiSelectState(key);
}

function updateAdminMultiSelectState(key) {
  const checkboxes = document.querySelectorAll('.admin-ms-checkbox-' + key + ':checked');
  const labelEl = document.getElementById('admin_label_ms_' + key);
  if (!labelEl) return;

  if (checkboxes.length === 0) {
    labelEl.innerText = '— Select Option(s) —';
    labelEl.className = 'truncate text-slate-500';
  } else if (checkboxes.length === 1) {
    labelEl.innerHTML = `<span class="px-1.5 py-0.5 rounded text-[11px] font-bold bg-[#e6f4ea] text-[#006633]">${checkboxes[0].value}</span>`;
  } else {
    const vals = Array.from(checkboxes).map(c => c.value);
    labelEl.innerHTML = `<span class="px-1.5 py-0.5 rounded text-[11px] font-bold bg-[#006633] text-white mr-1">${checkboxes.length} Selected</span> <span class="text-slate-800 text-xs font-semibold">${vals.join(', ')}</span>`;
  }
}

document.addEventListener('click', function(e) {
  if (!e.target.closest('.admin-ms-wrapper')) {
    document.querySelectorAll('[id^="admin_panel_ms_"]').forEach(p => {
      p.classList.add('hidden');
      const k = p.id.replace('admin_panel_ms_', '');
      const ic = document.getElementById('admin_icon_ms_' + k);
      if (ic) ic.classList.remove('rotate-180');
    });
  }
});

// Admin Agency / Institution / Office Manual Input Toggle
const adminAgencySelect = document.getElementById('dynamic_agency');
const adminAgencyManualBox = document.getElementById('admin_agency_manual_box');
const adminAgencyCustom = document.getElementById('admin_agency_custom');
if (adminAgencySelect && adminAgencyManualBox) {
  adminAgencySelect.addEventListener('change', function() {
    const val = (adminAgencySelect.value || '').trim().toLowerCase();
    if (val === 'none' || val === 'other' || val === 'others' || val.includes('none') || val.includes('other')) {
      adminAgencyManualBox.classList.remove('hidden');
      if (adminAgencyCustom) adminAgencyCustom.required = true;
    } else {
      adminAgencyManualBox.classList.add('hidden');
      if (adminAgencyCustom) adminAgencyCustom.required = false;
    }
  });
}

// Admin Purpose of Visit Manual Input Toggle
const adminPurposeSelect = document.getElementById('dynamic_purpose');
const adminPurposeManualBox = document.getElementById('admin_purpose_manual_box');
const adminPurposeCustom = document.getElementById('admin_purpose_custom');
if (adminPurposeSelect && adminPurposeManualBox) {
  adminPurposeSelect.addEventListener('change', function() {
    const val = (adminPurposeSelect.value || '').trim().toLowerCase();
    if (val === 'none' || val === 'other' || val === 'others' || val.includes('none') || val.includes('other')) {
      adminPurposeManualBox.classList.remove('hidden');
      if (adminPurposeCustom) adminPurposeCustom.required = true;
    } else {
      adminPurposeManualBox.classList.add('hidden');
      if (adminPurposeCustom) adminPurposeCustom.required = false;
    }
  });
}

// Admin Course & Year / Office Manual Input Toggle
const adminCourseYearSelect = document.getElementById('dynamic_course_year') || document.getElementById('dynamic_course_year_dept');
const adminCourseYearManualBox = document.getElementById('admin_course_year_manual_box');
const adminCourseYearCustom = document.getElementById('admin_course_year_custom');
if (adminCourseYearSelect && adminCourseYearManualBox) {
  adminCourseYearSelect.addEventListener('change', function() {
    const val = (adminCourseYearSelect.value || '').trim().toLowerCase();
    if (val === 'none' || val === 'other' || val === 'others' || val.includes('none') || val.includes('other') || val.includes('office') || val.includes('faculty')) {
      adminCourseYearManualBox.classList.remove('hidden');
      if (adminCourseYearCustom) adminCourseYearCustom.required = true;
    } else {
      adminCourseYearManualBox.classList.add('hidden');
      if (adminCourseYearCustom) adminCourseYearCustom.required = false;
    }
  });
}
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>

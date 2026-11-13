<?php
/**
 * System Audit Trail & Compliance Logger
 * Guidance Office Logbook Management System (OSAS)
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/helpers.php';

require_admin();
$page_title = 'Audit Trail';

$filter_action = $_GET['action'] ?? '';
$search = trim($_GET['search'] ?? '');

$where = ["1=1"];
$params = [];

if (!empty($filter_action)) {
    $where[] = "al.action = ?";
    $params[] = $filter_action;
}

if (!empty($search)) {
    $where[] = "(al.details LIKE ? OR u.full_name LIKE ? OR al.ip_address LIKE ?)";
    $like = "%{$search}%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$where_sql = implode(' AND ', $where);
$sql = "SELECT al.*, u.full_name, u.username, u.role, lt.title as logbook_title 
        FROM `audit_logs` al
        LEFT JOIN `users` u ON u.id = al.user_id
        LEFT JOIN `logbook_types` lt ON lt.id = al.logbook_type_id
        WHERE {$where_sql}
        ORDER BY al.created_at DESC LIMIT 100";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<div class="space-y-6">

  <!-- Header -->
  <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-4">
    <div>
      <h1 class="text-xl sm:text-2xl font-bold text-slate-900 tracking-tight">System Audit Trail</h1>
      <p class="text-xs sm:text-sm text-slate-500 mt-1">Immutable activity log tracking entry creations, updates, archives, and system events for accountability.</p>
    </div>
    <span class="text-xs text-slate-400 font-mono">Showing latest 100 events</span>
  </div>

  <!-- Filters -->
  <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm">
    <form method="GET" action="audit_logs.php" class="flex flex-wrap items-center gap-3">
      <div class="flex-1 min-w-[200px] relative">
        <i class="bi bi-search absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
        <input type="text" name="search" value="<?= e($search) ?>" placeholder="Search user, action details, IP address..." class="w-full pl-9 pr-3 py-2 text-xs rounded-xl border border-slate-200 focus:outline-none focus:ring-1 focus:ring-brand-500">
      </div>

      <div class="w-48">
        <select name="action" class="w-full px-3 py-2 text-xs rounded-xl border border-slate-200 focus:outline-none focus:ring-1 focus:ring-brand-500 bg-white">
          <option value="">All Actions</option>
          <option value="STUDENT_SELF_REGISTRATION" <?= $filter_action === 'STUDENT_SELF_REGISTRATION' ? 'selected' : '' ?>>Walk-in Registration</option>
          <option value="CREATE_RECORD" <?= $filter_action === 'CREATE_RECORD' ? 'selected' : '' ?>>Create Record</option>
          <option value="UPDATE_RECORD" <?= $filter_action === 'UPDATE_RECORD' ? 'selected' : '' ?>>Update Record</option>
          <option value="ARCHIVE_RECORD" <?= $filter_action === 'ARCHIVE_RECORD' ? 'selected' : '' ?>>Archive Record</option>
          <option value="RESTORE_RECORD" <?= $filter_action === 'RESTORE_RECORD' ? 'selected' : '' ?>>Restore Record</option>
          <option value="DELETE_RECORD" <?= $filter_action === 'DELETE_RECORD' ? 'selected' : '' ?>>Delete Record</option>
          <option value="USER_LOGIN" <?= $filter_action === 'USER_LOGIN' ? 'selected' : '' ?>>User Sign In</option>
          <option value="BACKUP_DATABASE" <?= $filter_action === 'BACKUP_DATABASE' ? 'selected' : '' ?>>Database Backup</option>
        </select>
      </div>

      <button type="submit" class="px-4 py-2 bg-[#006633] hover:bg-[#004422] text-white font-medium rounded-xl text-xs transition">Filter</button>
      <?php if (!empty($search) || !empty($filter_action)): ?>
        <a href="audit_logs.php" class="p-2 text-slate-400 hover:text-slate-600 text-xs"><i class="bi bi-x-circle-fill"></i></a>
      <?php endif; ?>
    </form>
  </div>

  <!-- Audit Table -->
  <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
    <?php if (empty($logs)): ?>
      <p class="text-xs text-slate-500 text-center py-12">No audit events found matching your criteria.</p>
    <?php else: ?>
      <table class="w-full text-left text-xs">
        <thead>
          <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 font-bold uppercase tracking-wider">
            <th class="py-3 px-4">Timestamp</th>
            <th class="py-3 px-4">Staff Member</th>
            <th class="py-3 px-4">Action</th>
            <th class="py-3 px-4">Section</th>
            <th class="py-3 px-4">Details / Summary</th>
            <th class="py-3 px-4">IP Address</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <?php foreach ($logs as $l): ?>
            <tr class="hover:bg-slate-50 transition">
              <td class="py-3 px-4 whitespace-nowrap text-slate-500 font-mono">
                <?= date('M d, Y h:i A', strtotime($l['created_at'])) ?>
              </td>
              <td class="py-3 px-4 whitespace-nowrap">
                <span class="font-bold text-slate-900"><?= e($l['full_name'] ?: ($l['action'] === 'STUDENT_SELF_REGISTRATION' ? 'Student / Walk-in Kiosk' : 'System / Admin')) ?></span>
                <?php if ($l['username']): ?>
                  <span class="text-[10px] text-slate-400 block font-mono">@<?= e($l['username']) ?></span>
                <?php endif; ?>
              </td>
              <td class="py-3 px-4 whitespace-nowrap">
                <?php if ($l['action'] === 'STUDENT_SELF_REGISTRATION'): ?>
                  <span class="badge-pill bg-[#e6f4ea] text-[#006633] border border-[#b7dfc8] font-bold">Walk-In Logged</span>
                <?php elseif (strpos($l['action'], 'CREATE') !== false): ?>
                  <span class="badge-pill bg-emerald-50 text-emerald-700 border border-emerald-200 font-bold">Created</span>
                <?php elseif (strpos($l['action'], 'UPDATE') !== false): ?>
                  <span class="badge-pill bg-blue-50 text-blue-700 border border-blue-200 font-bold">Updated</span>
                <?php elseif (strpos($l['action'], 'ARCHIVE') !== false): ?>
                  <span class="badge-pill bg-amber-50 text-amber-700 border border-amber-200 font-bold">Archived</span>
                <?php elseif (strpos($l['action'], 'DELETE') !== false): ?>
                  <span class="badge-pill bg-rose-50 text-rose-700 border border-rose-200 font-bold">Deleted</span>
                <?php else: ?>
                  <span class="badge-pill bg-slate-100 text-slate-700 font-medium"><?= e($l['action']) ?></span>
                <?php endif; ?>
              </td>
              <td class="py-3 px-4 whitespace-nowrap font-medium text-slate-700">
                <?= e($l['logbook_title'] ?: '—') ?>
              </td>
              <td class="py-3 px-4 text-slate-800">
                <?= e($l['details']) ?>
              </td>
              <td class="py-3 px-4 whitespace-nowrap text-slate-400 font-mono">
                <?= e($l['ip_address']) ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

</div>

<?php require __DIR__ . '/includes/footer.php'; ?>

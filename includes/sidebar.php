<?php
/**
 * Sidebar Navigation Component
 * Guidance Office Logbook Management System (OSAS)
 */

$current_slug = $_GET['type'] ?? '';
$current_page = basename($_SERVER['PHP_SELF']);

// Query pending unreturned items count for Borrower's log
$unreturned_count = 0;
try {
    $stmt_unreturned = $pdo->query("SELECT COUNT(*) FROM `logbook_records` lr
        JOIN `logbook_types` lt ON lt.id = lr.logbook_type_id
        WHERE lt.slug = 'borrowers-log' AND lr.status = 'active'
        AND JSON_UNQUOTE(JSON_EXTRACT(lr.data_json, '$.return_status')) = 'Pending'");
    $unreturned_count = (int) $stmt_unreturned->fetchColumn();
} catch (Exception $e) {
    $unreturned_count = 0;
}
?>
<aside id="sidebar" class="w-64 bg-white border-r border-slate-200 flex flex-col justify-between z-40 lg:static fixed inset-y-0 left-0 hidden-mobile lg:translate-x-0 no-print transition-all">
  
  <!-- Navigation Links -->
  <div class="p-4 space-y-6 overflow-y-auto">
    
    <!-- Main Section -->
    <div class="space-y-1">
      <div class="px-3 text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">Overview</div>
      
      <a href="dashboard.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition <?= $current_page === 'dashboard.php' ? 'bg-brand-50 text-brand-900 font-semibold' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' ?>">
        <i class="bi bi-grid-1x2-fill text-lg <?= $current_page === 'dashboard.php' ? 'text-brand-700' : 'text-slate-400' ?>"></i>
        <span>Dashboard</span>
      </a>

      <!-- Student Kiosk Fill-up Link -->
      <a href="index.php" target="_blank" class="flex items-center justify-between px-3 py-2.5 rounded-xl text-sm font-medium transition text-emerald-700 bg-emerald-50 hover:bg-emerald-100 hover:text-emerald-900 border border-emerald-200">
        <div class="flex items-center gap-2.5">
          <i class="bi bi-person-walking text-base text-emerald-600"></i>
          <span class="font-bold">Student Fill-Up Portal</span>
        </div>
        <i class="bi bi-box-arrow-up-right text-xs text-emerald-500"></i>
      </a>
    </div>

    <!-- Logbook Types Navigation -->
    <div>
      <div class="flex items-center justify-between px-3 mb-2">
        <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Logbook Sections</span>
        <span class="text-xs font-medium text-slate-400"><?= count($nav_logbook_types) ?></span>
      </div>

      <nav class="space-y-1">
        <?php foreach ($nav_logbook_types as $lt): 
          $isActive = ($current_page === 'logbook.php' && $current_slug === $lt['slug']);
        ?>
          <a href="logbook.php?type=<?= urlencode($lt['slug']) ?>" class="group flex items-center justify-between px-3 py-2.5 rounded-xl text-sm transition <?= $isActive ? 'bg-[#006633] text-white font-medium shadow-sm' : 'text-slate-700 hover:bg-[#f0fdf4] hover:text-[#006633]' ?>">
            <div class="flex items-center gap-2.5 truncate">
              <i class="bi <?= e($lt['icon']) ?> text-base <?= $isActive ? 'text-white' : 'text-slate-400 group-hover:text-[#006633]' ?>"></i>
              <span class="truncate"><?= e($lt['title']) ?></span>
            </div>

            <div class="flex items-center gap-1.5 ml-2">
              <?php if ($lt['slug'] === 'borrowers-log' && $unreturned_count > 0): ?>
                <span class="px-1.5 py-0.5 text-xs rounded-full bg-amber-500 text-white font-bold" title="<?= $unreturned_count ?> items not yet returned"><?= $unreturned_count ?></span>
              <?php endif; ?>
              <span class="text-xs px-2 py-0.5 rounded-full <?= $isActive ? 'bg-[#004422] text-white' : 'bg-slate-100 text-slate-500 group-hover:bg-[#e6f4ea] group-hover:text-[#006633]' ?>">
                <?= (int)$lt['active_count'] ?>
              </span>
            </div>
          </a>
        <?php endforeach; ?>
      </nav>
    </div>

    <!-- Administrative Tools (Admin Only) -->
    <?php if (is_admin()): ?>
      <div>
        <div class="px-3 text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">Administration</div>
        <nav class="space-y-1">
          <a href="manage_types.php" class="flex items-center gap-3 px-3 py-2 rounded-xl text-sm font-medium transition <?= $current_page === 'manage_types.php' ? 'bg-brand-50 text-brand-900 font-semibold' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' ?>">
            <i class="bi bi-folder-plus text-base <?= $current_page === 'manage_types.php' ? 'text-brand-700' : 'text-slate-400' ?>"></i>
            <span>Logbook Section Builder</span>
          </a>
          
          <a href="users.php" class="flex items-center gap-3 px-3 py-2 rounded-xl text-sm font-medium transition <?= $current_page === 'users.php' ? 'bg-brand-50 text-brand-900 font-semibold' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' ?>">
            <i class="bi bi-people text-base <?= $current_page === 'users.php' ? 'text-brand-700' : 'text-slate-400' ?>"></i>
            <span>User Accounts</span>
          </a>

          <a href="backup.php" class="flex items-center gap-3 px-3 py-2 rounded-xl text-sm font-medium transition <?= $current_page === 'backup.php' ? 'bg-brand-50 text-brand-900 font-semibold' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' ?>">
            <i class="bi bi-database-down text-base <?= $current_page === 'backup.php' ? 'text-brand-700' : 'text-slate-400' ?>"></i>
            <span>Backup & Restore</span>
          </a>

          <a href="audit_logs.php" class="flex items-center gap-3 px-3 py-2 rounded-xl text-sm font-medium transition <?= $current_page === 'audit_logs.php' ? 'bg-brand-50 text-brand-900 font-semibold' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' ?>">
            <i class="bi bi-clock-history text-base <?= $current_page === 'audit_logs.php' ? 'text-brand-700' : 'text-slate-400' ?>"></i>
            <span>Audit Trail</span>
          </a>
        </nav>
      </div>
    <?php endif; ?>

  </div>

  <!-- Sidebar Footer: Session Info -->
  <div class="p-4 border-t border-slate-200 bg-slate-50/50">
    <div class="flex items-center justify-between text-xs text-slate-500 mb-2">
      <span class="flex items-center gap-1.5">
        <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
        <span>MySQL Connected</span>
      </span>
      <span>v1.0 (Part 1)</span>
    </div>
    <div class="text-xs text-slate-400 truncate">
      <?= e($system_settings['office_name'] ?? 'Guidance Office') ?>
    </div>
    <a href="<?= e($system_settings['facebook_url'] ?? 'https://www.facebook.com/profile.php?id=61595216319061') ?>" target="_blank" rel="noopener noreferrer" class="mt-2.5 pt-2 border-t border-slate-200/80 flex items-center gap-1.5 text-xs text-[#006633] font-semibold hover:underline">
      <i class="bi bi-facebook text-sm"></i>
      <span class="truncate">QSU OGCA Facebook</span>
    </a>
  </div>

</aside>

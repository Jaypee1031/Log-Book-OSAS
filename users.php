<?php
/**
 * User Accounts & Staff Access Management
 * Guidance Office Logbook Management System (OSAS)
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/helpers.php';

require_admin();
$page_title = 'User Accounts';

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$current_id = current_user()['id'];

// 1. ADD NEW USER
if ($action === 'create_user' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        set_flash('danger', 'Invalid security token.');
        header('Location: users.php');
        exit;
    }

    $username = trim($_POST['username'] ?? '');
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $role = 'admin';
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($full_name) || empty($password)) {
        set_flash('danger', 'Username, Full Name, and Password are required.');
        header('Location: users.php');
        exit;
    }

    // Check username uniqueness
    $stmt = $pdo->prepare("SELECT id FROM `users` WHERE `username` = ?");
    $stmt->execute([$username]);
    if ($stmt->fetch()) {
        set_flash('danger', "Username '{$username}' is already taken.");
        header('Location: users.php');
        exit;
    }

    $hash = password_hash($password, PASSWORD_BCRYPT);
    $stmt = $pdo->prepare("INSERT INTO `users` (`username`, `password_hash`, `full_name`, `email`, `role`, `is_active`) VALUES (?, ?, ?, ?, ?, 1)");
    $stmt->execute([$username, $hash, $full_name, $email, $role]);

    log_audit($pdo, 'CREATE_USER', null, (int)$pdo->lastInsertId(), "Created user account: {$username} ({$role})");
    set_flash('success', "User account '{$username}' created successfully.");
    header('Location: users.php');
    exit;
}

// 2. TOGGLE USER STATUS (Activate/Deactivate)
if ($action === 'toggle_status') {
    if (!verify_csrf($_GET['token'] ?? '')) {
        set_flash('danger', 'Invalid security token.');
        header('Location: users.php');
        exit;
    }

    $id = (int)($_GET['id'] ?? 0);
    if ($id === $current_id) {
        set_flash('danger', 'You cannot deactivate your own administrative account.');
        header('Location: users.php');
        exit;
    }

    $stmt = $pdo->prepare("SELECT `is_active`, `username` FROM `users` WHERE `id` = ?");
    $stmt->execute([$id]);
    $u = $stmt->fetch();

    if ($u) {
        $new_status = $u['is_active'] ? 0 : 1;
        $pdo->prepare("UPDATE `users` SET `is_active` = ? WHERE `id` = ?")->execute([$new_status, $id]);
        $status_label = $new_status ? 'activated' : 'deactivated';
        log_audit($pdo, 'TOGGLE_USER_STATUS', null, $id, "Set user {$u['username']} to {$status_label}");
        set_flash('info', "User '{$u['username']}' has been {$status_label}.");
    }
    header('Location: users.php');
    exit;
}

// Fetch all users
$users = $pdo->query("SELECT * FROM `users` ORDER BY `role` ASC, `full_name` ASC")->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<div class="space-y-6">

  <!-- Header -->
  <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-4">
    <div>
      <h1 class="text-xl sm:text-2xl font-bold text-slate-900 tracking-tight">Administrator Accounts</h1>
      <p class="text-xs sm:text-sm text-slate-500 mt-1">Manage Guidance Office administrator credentials and login access.</p>
    </div>
    <button type="button" onclick="openModal('add-user-modal')" class="inline-flex items-center gap-1.5 px-4 py-2.5 bg-[#006633] hover:bg-[#004422] text-white font-semibold text-xs rounded-xl shadow-sm transition">
      <i class="bi bi-person-plus-fill"></i> Add Administrator
    </button>
  </div>

  <!-- Users Table -->
  <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
    <table class="w-full text-left text-xs">
      <thead>
        <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 font-bold uppercase tracking-wider">
          <th class="py-3 px-4">User</th>
          <th class="py-3 px-4">Username</th>
          <th class="py-3 px-4">Email</th>
          <th class="py-3 px-4">Role</th>
          <th class="py-3 px-4">Status</th>
          <th class="py-3 px-4">Last Login</th>
          <th class="py-3 px-4 text-right">Actions</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
        <?php foreach ($users as $u): ?>
          <tr class="hover:bg-slate-50 transition">
            <td class="py-3 px-4">
              <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-full bg-brand-100 text-brand-900 font-bold flex items-center justify-center text-xs">
                  <?= strtoupper(substr($u['full_name'], 0, 1)) ?>
                </div>
                <div>
                  <div class="font-bold text-slate-900"><?= e($u['full_name']) ?></div>
                  <div class="text-[10px] text-slate-400">ID: #<?= (int)$u['id'] ?></div>
                </div>
              </div>
            </td>
            <td class="py-3 px-4 font-mono font-medium text-slate-700"><?= e($u['username']) ?></td>
            <td class="py-3 px-4 text-slate-600"><?= e($u['email'] ?: '—') ?></td>
            <td class="py-3 px-4">
              <?php if ($u['role'] === 'admin'): ?>
                <span class="badge-pill bg-amber-50 text-amber-800 border border-amber-200 font-semibold">Administrator</span>
              <?php else: ?>
                <span class="badge-pill bg-emerald-50 text-emerald-800 border border-emerald-200 font-semibold">Guidance Staff</span>
              <?php endif; ?>
            </td>
            <td class="py-3 px-4">
              <?php if ($u['is_active']): ?>
                <span class="badge-pill bg-emerald-50 text-emerald-700 border border-emerald-200 font-semibold"><i class="bi bi-check-circle-fill mr-1"></i> Active</span>
              <?php else: ?>
                <span class="badge-pill bg-slate-100 text-slate-500 font-semibold"><i class="bi bi-x-circle-fill mr-1"></i> Disabled</span>
              <?php endif; ?>
            </td>
            <td class="py-3 px-4 text-slate-500 whitespace-nowrap">
              <?= $u['last_login_at'] ? date('M d, Y h:i A', strtotime($u['last_login_at'])) : 'Never' ?>
            </td>
            <td class="py-3 px-4 text-right whitespace-nowrap">
              <?php if ($u['id'] !== $current_id): ?>
                <a href="users.php?action=toggle_status&id=<?= $u['id'] ?>&token=<?= csrf_token() ?>" class="px-2.5 py-1 text-xs rounded-lg font-medium transition <?= $u['is_active'] ? 'text-amber-700 hover:bg-amber-50' : 'text-emerald-700 hover:bg-emerald-50' ?>">
                  <?= $u['is_active'] ? 'Deactivate' : 'Activate' ?>
                </a>
              <?php else: ?>
                <span class="text-[11px] text-slate-400 italic">Current Session</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

</div>

<!-- Modal: Add New User -->
<div id="add-user-modal" class="fixed inset-0 z-50 hidden items-center justify-center modal-backdrop-custom modal-container">
  <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full mx-4 overflow-hidden border border-slate-200">
    <div class="px-6 py-4 border-b border-slate-200 flex items-center justify-between bg-slate-50">
      <div>
        <h3 class="text-base font-bold text-slate-900">Add Staff Account</h3>
        <p class="text-xs text-slate-500">Provide staff credentials to grant system access.</p>
      </div>
      <button type="button" onclick="closeModal('add-user-modal')" class="text-slate-400 hover:text-slate-600 text-xl leading-none">&times;</button>
    </div>

    <form method="POST" action="users.php" class="p-6 space-y-4">
      <input type="hidden" name="action" value="create_user">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

      <div>
        <label class="block text-xs font-semibold text-slate-700 mb-1">Full Name *</label>
        <input type="text" name="full_name" required placeholder="e.g. Maria Santos, RGC" class="w-full px-3 py-2 text-xs rounded-lg border border-slate-200 focus:outline-none focus:ring-1 focus:ring-brand-500">
      </div>

      <div>
        <label class="block text-xs font-semibold text-slate-700 mb-1">Username *</label>
        <input type="text" name="username" required placeholder="e.g. msantos" class="w-full px-3 py-2 text-xs rounded-lg border border-slate-200 focus:outline-none focus:ring-1 focus:ring-brand-500 font-mono">
      </div>

      <div>
        <label class="block text-xs font-semibold text-slate-700 mb-1">Email Address</label>
        <input type="email" name="email" placeholder="e.g. msantos@osas.edu" class="w-full px-3 py-2 text-xs rounded-lg border border-slate-200 focus:outline-none focus:ring-1 focus:ring-brand-500">
      </div>

      <div>
        <label class="block text-xs font-semibold text-slate-700 mb-1">Account Role</label>
        <div class="p-2.5 rounded-lg bg-amber-50 border border-amber-200 text-xs font-semibold text-amber-900 flex items-center gap-2">
          <i class="bi bi-shield-lock-fill text-amber-600"></i>
          <span>Administrator (Full Office Management & Oversight)</span>
        </div>
      </div>

      <div>
        <label class="block text-xs font-semibold text-slate-700 mb-1">Initial Password *</label>
        <input type="password" name="password" required placeholder="••••••••" class="w-full px-3 py-2 text-xs rounded-lg border border-slate-200 focus:outline-none focus:ring-1 focus:ring-brand-500">
      </div>

      <div class="pt-4 border-t border-slate-200 flex items-center justify-end gap-2">
        <button type="button" onclick="closeModal('add-user-modal')" class="px-4 py-2 rounded-xl border border-slate-200 text-slate-700 text-xs font-medium hover:bg-slate-50 transition">Cancel</button>
        <button type="submit" class="px-5 py-2 rounded-xl bg-[#006633] hover:bg-[#004422] text-white text-xs font-bold shadow-sm transition">Create Account</button>
      </div>
    </form>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>

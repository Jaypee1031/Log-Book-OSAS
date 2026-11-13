<?php
/**
 * Sign In Screen & Authentication
 * Guidance Office Logbook Management System (OSAS)
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/helpers.php';

if (is_logged_in()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$system_settings = get_system_settings($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Please enter both your username and password.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM `users` WHERE `username` = ? LIMIT 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            if (!$user['is_active']) {
                $error = 'This account has been deactivated. Please contact the administrator.';
            } else {
                session_regenerate_id(true);
                $_SESSION['user'] = [
                    'id' => (int)$user['id'],
                    'username' => $user['username'],
                    'full_name' => $user['full_name'],
                    'email' => $user['email'],
                    'role' => $user['role']
                ];

                // Update last login
                $pdo->prepare("UPDATE `users` SET `last_login_at` = NOW() WHERE `id` = ?")->execute([$user['id']]);
                log_audit($pdo, 'USER_LOGIN', null, null, "User '{$user['username']}' signed in successfully");

                header('Location: dashboard.php');
                exit;
            }
        } else {
            $error = 'Invalid username or password. Please try again.';
        }
    }
}

$flash = get_flash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sign In | <?= e($system_settings['office_unit'] ?? 'Guidance & Counseling Center') ?></title>
  
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            brand: {
              50: '#f0fdf4',
              100: '#dcfce7',
              200: '#bbf7d0',
              300: '#86efac',
              400: '#4ade80',
              500: '#22c55e',
              600: '#16a34a',
              700: '#15803d',
              800: '#166534',
              900: '#14532d',
              950: '#052e16'
            }
          }
        }
      }
    }
  </script>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="bg-gradient-to-br from-emerald-50/60 via-slate-50 to-emerald-50/40 min-h-screen flex items-center justify-center p-4 antialiased">

  <div class="max-w-md w-full">
    
    <!-- Header Branding Card (Matched to QSU Admission Portal) -->
    <div class="text-center mb-6">
      <img src="assets/images/qsulogo.png" alt="QSU Logo" class="h-20 w-auto mx-auto mb-3 drop-shadow-md">
      <div class="text-xs uppercase tracking-widest font-extrabold text-[#006633]"><?= e($system_settings['institution_name'] ?? 'Quirino State University') ?></div>
      <h1 class="text-xl sm:text-2xl font-black text-slate-900 mt-0.5"><?= e($system_settings['office_unit'] ?? 'Office of Guidance & Counseling Affairs') ?></h1>
      <p class="text-xs text-slate-500 mt-1"><?= e($system_settings['office_name'] ?? 'Office of Student Affairs and Services') ?></p>
    </div>

    <!-- Login Box -->
    <div class="bg-white rounded-3xl p-7 sm:p-8 shadow-2xl border border-slate-200">
      
      <div class="mb-5 border-b border-slate-100 pb-3">
        <h2 class="text-lg font-bold text-slate-900 flex items-center gap-2">
          <i class="bi bi-shield-lock-fill text-[#006633]"></i>
          <span>Administrator Sign In</span>
        </h2>
        <p class="text-xs text-slate-500 mt-0.5">Authorized guidance personnel sign in portal.</p>
      </div>

      <!-- Flash / Error Alert -->
      <?php if ($flash): ?>
        <div class="mb-4 p-3 rounded-xl text-xs font-medium bg-amber-50 border border-amber-200 text-amber-800 flex items-center gap-2">
          <i class="bi bi-info-circle-fill"></i>
          <span><?= e($flash['message']) ?></span>
        </div>
      <?php endif; ?>

      <?php if (!empty($error)): ?>
        <div class="mb-4 p-3 rounded-xl text-xs font-medium bg-rose-50 border border-rose-200 text-rose-800 flex items-center gap-2">
          <i class="bi bi-exclamation-triangle-fill"></i>
          <span><?= e($error) ?></span>
        </div>
      <?php endif; ?>

      <form method="POST" action="login.php" class="space-y-4">
        
        <div>
          <label class="block text-xs font-bold text-slate-700 mb-1" for="username">Username</label>
          <div class="relative">
            <i class="bi bi-person absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400"></i>
            <input type="text" name="username" id="username" required placeholder="e.g. admin" class="w-full pl-9 pr-3 py-2.5 text-xs rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-brand-800 font-mono text-slate-800">
          </div>
        </div>

        <div>
          <label class="block text-xs font-bold text-slate-700 mb-1" for="password">Password</label>
          <div class="relative">
            <i class="bi bi-lock absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400"></i>
            <input type="password" name="password" id="password" required placeholder="••••••••" class="w-full pl-9 pr-10 py-2.5 text-xs rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-brand-800 text-slate-800">
            <button type="button" onclick="togglePassword()" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 text-sm">
              <i class="bi bi-eye" id="toggle-eye"></i>
            </button>
          </div>
        </div>

        <button type="submit" class="w-full py-3 bg-[#006633] hover:bg-[#004422] text-white font-bold text-xs rounded-xl shadow-md shadow-[#006633]/20 transition mt-2">
          Sign In to Administrator System
        </button>
      </form>

      <!-- Student Walk-in Fill-up Button -->
      <div class="mt-6 pt-5 border-t border-slate-100 space-y-4">
        <div>
          <a href="index.php" class="w-full py-3 px-4 rounded-2xl bg-[#006633] hover:bg-[#004422] text-white font-bold text-xs flex items-center justify-center gap-2 shadow-md shadow-[#006633]/20 transition group">
            <i class="bi bi-person-walking text-base group-hover:scale-110 transition-transform"></i>
            <span>Student Walk-in Fill-Up (No Login Needed)</span>
            <i class="bi bi-arrow-right"></i>
          </a>
          <span class="text-[10px] text-slate-400 text-center block mt-1.5">For students, visitors, alumni, and faculty to log their visit or request.</span>
        </div>

        <!-- Quick Fill Admin Credentials -->
        <div class="pt-2 border-t border-slate-100">
          <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block mb-2 text-center">Administrator Access</span>
          <button type="button" onclick="fillCredentials('admin', 'admin123')" class="w-full px-3 py-2.5 rounded-xl bg-[#f0fdf4] hover:bg-[#e6f4ea] border border-[#b7dfc8] text-[#004422] text-xs font-medium text-left transition flex items-center justify-between">
            <div class="font-bold flex items-center gap-2">
              <i class="bi bi-shield-lock-fill text-[#006633] text-base"></i>
              <span>One-Click Fill Admin Credentials</span>
            </div>
            <span class="text-[10px] text-[#006633] font-mono">admin / admin123</span>
          </button>
        </div>
      </div>

    </div>

    <!-- Official Facebook Page Link -->
    <div class="text-center mt-4">
      <a href="https://www.facebook.com/profile.php?id=61595216319061" target="_blank" rel="noopener noreferrer" class="text-xs text-[#006633] hover:underline font-semibold inline-flex items-center gap-1.5 bg-white/80 border border-[#b7dfc8] px-3.5 py-1.5 rounded-full shadow-sm hover:bg-[#f0fdf4] transition">
        <i class="bi bi-facebook text-sm"></i>
        <span>QSU Guidance Office Facebook Page</span>
      </a>
    </div>

    <!-- Security & Privacy Notice -->
    <div class="text-center text-[11px] text-slate-400 mt-6 leading-relaxed">
      <i class="bi bi-lock-fill mr-1"></i> Authorized Guidance Personnel Only. All access is logged and audited.
    </div>

  </div>

  <script>
    function togglePassword() {
      const pass = document.getElementById('password');
      const eye = document.getElementById('toggle-eye');
      if (pass.type === 'password') {
        pass.type = 'text';
        eye.classList.remove('bi-eye');
        eye.classList.add('bi-eye-slash');
      } else {
        pass.type = 'password';
        eye.classList.remove('bi-eye-slash');
        eye.classList.add('bi-eye');
      }
    }

    function fillCredentials(user, pass) {
      document.getElementById('username').value = user;
      document.getElementById('password').value = pass;
    }
  </script>
</body>
</html>

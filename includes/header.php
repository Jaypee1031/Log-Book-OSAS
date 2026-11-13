<?php
/**
 * Global Header Component
 * Guidance Office Logbook Management System (OSAS)
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';

require_login();
$current_user = current_user();
$system_settings = get_system_settings($pdo);
$nav_logbook_types = get_logbook_types($pdo, true);
$flash = get_flash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= isset($page_title) ? e($page_title) . ' | ' : '' ?><?= e($system_settings['system_title'] ?? 'Guidance Office Logbook System') ?></title>
  
  <!-- Tailwind CSS CDN -->
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            brand: {
              50: '#f0fdf4',
              100: '#e6f4ea',
              200: '#b7dfc8',
              300: '#85c9a4',
              400: '#4fb27f',
              500: '#28a745',
              600: '#1e8e3e',
              700: '#008844',
              800: '#006633',
              900: '#006633',
              950: '#004422',
              DEFAULT: '#006633'
            },
            calm: {
              teal: '#006633',
              slate: '#343a40',
              soft: '#f8f9fa'
            }
          }
        }
      }
    }
  </script>

  <!-- Bootstrap Icons -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  
  <!-- Chart.js for Dashboard Visualizations -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

  <!-- Custom Stylesheet -->
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="bg-slate-50 text-slate-800 min-h-screen flex flex-col antialiased">

  <!-- Mobile Sidebar Overlay -->
  <div id="sidebar-overlay" class="fixed inset-0 bg-slate-900/50 z-40 lg:hidden hidden"></div>

  <!-- Top Navigation Header (QSU Admission Portal Style) -->
  <header id="top-navbar" class="bg-[#006633] text-white sticky top-0 z-30 shadow-md no-print">
    <div class="px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
      
      <!-- Left: Mobile Toggle & Brand -->
      <div class="flex items-center gap-3">
        <button id="sidebar-toggle" type="button" class="lg:hidden p-2 text-white/90 hover:text-white hover:bg-white/10 rounded-lg focus:outline-none" aria-label="Toggle Navigation">
          <i class="bi bi-list text-2xl"></i>
        </button>

        <a href="dashboard.php" class="flex items-center gap-3 group">
          <img src="assets/images/qsulogo.png" alt="QSU Logo" class="h-10 w-auto object-contain drop-shadow group-hover:scale-105 transition-transform">
          <div>
            <div class="font-bold text-white leading-tight text-base sm:text-lg flex items-center gap-1.5">
              <span><?= e($system_settings['office_unit'] ?? 'Office of Guidance & Counseling Affairs') ?></span>
              <span class="text-xs bg-white/20 text-white font-semibold px-2 py-0.5 rounded-full border border-white/30">QSU OSAS</span>
            </div>
            <p class="text-xs text-white/80 hidden sm:block leading-none mt-0.5"><?= e($system_settings['institution_name'] ?? 'Quirino State University - Diffun Campus') ?></p>
          </div>
        </a>
      </div>

      <!-- Right: Quick Action, Student Kiosk & Admin Profile -->
      <div class="flex items-center gap-2.5 sm:gap-3">
        
        <!-- Student Fill-Up Portal Link -->
        <a href="index.php" target="_blank" class="inline-flex items-center gap-1.5 bg-white text-[#006633] hover:bg-slate-100 px-3 py-2 rounded-lg text-xs font-bold shadow-sm transition" title="Open walk-in student data collection portal">
          <i class="bi bi-person-walking"></i>
          <span class="hidden md:inline">Student Fill-Up Kiosk</span>
          <i class="bi bi-box-arrow-up-right text-[10px] opacity-75"></i>
        </a>

        <!-- Quick Add Entry Dropdown -->
        <div class="relative group">
          <button type="button" class="inline-flex items-center gap-1.5 bg-[#004422] hover:bg-[#003311] text-white px-3.5 py-2 rounded-lg text-xs sm:text-sm font-medium shadow-sm transition focus:outline-none">
            <i class="bi bi-plus-circle"></i>
            <span class="hidden sm:inline">New Entry</span>
            <i class="bi bi-chevron-down text-xs opacity-75"></i>
          </button>
          
          <div class="absolute right-0 top-full pt-1 w-64 hidden group-hover:block group-focus-within:block transition z-50">
            <div class="bg-white border border-slate-200 rounded-xl shadow-xl py-2 text-slate-800">
              <div class="px-3 py-1.5 text-xs font-semibold text-slate-400 uppercase tracking-wider">Select Logbook</div>
              <?php foreach ($nav_logbook_types as $nlt): ?>
                <a href="logbook.php?type=<?= urlencode($nlt['slug']) ?>&action=new" class="flex items-center gap-2.5 px-3 py-2 text-sm text-slate-700 hover:bg-[#f0fdf4] hover:text-[#006633]">
                  <i class="bi <?= e($nlt['icon']) ?> text-slate-400"></i>
                  <span class="truncate"><?= e($nlt['title']) ?></span>
                </a>
              <?php endforeach; ?>
            </div>
          </div>
        </div>

        <!-- Administrator Profile -->
        <div class="flex items-center gap-3 pl-3 border-l border-white/20">
          <div class="hidden md:block text-right">
            <div class="text-sm font-semibold text-white leading-tight"><?= e($current_user['full_name']) ?></div>
            <div class="text-xs text-white/80 font-medium flex items-center justify-end gap-1 mt-0.5">
              <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
              Administrator
            </div>
          </div>

          <div class="relative group">
            <button type="button" class="w-9 h-9 rounded-full bg-white/20 border border-white/40 flex items-center justify-center text-white font-bold hover:bg-white/30 focus:outline-none">
              <?= strtoupper(substr($current_user['full_name'], 0, 1)) ?>
            </button>

            <!-- User Menu -->
            <div class="absolute right-0 top-full pt-1 w-48 hidden group-hover:block group-focus-within:block transition z-50">
              <div class="bg-white border border-slate-200 rounded-xl shadow-xl py-1.5 text-slate-800">
                <div class="px-3 py-2 border-b border-slate-100 md:hidden">
                  <div class="text-xs font-bold text-slate-800"><?= e($current_user['full_name']) ?></div>
                  <div class="text-xs text-slate-500">Administrator</div>
                </div>
                <a href="users.php" class="flex items-center gap-2 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50">
                  <i class="bi bi-people text-slate-400"></i> Manage Users
                </a>
                <a href="backup.php" class="flex items-center gap-2 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50">
                  <i class="bi bi-database-down text-slate-400"></i> Backup & Restore
                </a>
                <a href="logout.php" class="flex items-center gap-2 px-3 py-2 text-sm text-rose-600 hover:bg-rose-50">
                  <i class="bi bi-box-arrow-right"></i> Sign Out
                </a>
              </div>
            </div>
          </div>
        </div>

      </div>

    </div>
  </header>

  <!-- Body Content Wrapper with Sidebar and Main Page -->
  <div class="flex-1 flex overflow-hidden">
    <?php require __DIR__ . '/sidebar.php'; ?>

    <!-- Main Content Canvas -->
    <main class="flex-1 overflow-y-auto px-4 sm:px-6 lg:px-8 py-6 main-content">
      
      <!-- Flash Alert Banner -->
      <?php if ($flash): ?>
        <div id="flash-alert" class="mb-6 p-4 rounded-xl flex items-start gap-3 shadow-sm border <?= 
          $flash['type'] === 'success' ? 'bg-emerald-50 border-emerald-200 text-emerald-800' : 
          ($flash['type'] === 'danger' ? 'bg-rose-50 border-rose-200 text-rose-800' : 
          ($flash['type'] === 'warning' ? 'bg-amber-50 border-amber-200 text-amber-800' : 'bg-blue-50 border-blue-200 text-blue-800')) 
        ?>">
          <i class="bi <?= 
            $flash['type'] === 'success' ? 'bi-check-circle-fill text-emerald-600' : 
            ($flash['type'] === 'danger' ? 'bi-exclamation-triangle-fill text-rose-600' : 
            ($flash['type'] === 'warning' ? 'bi-exclamation-circle-fill text-amber-600' : 'bi-info-circle-fill text-blue-600')) 
          ?> text-xl mt-0.5"></i>
          <div class="flex-1 text-sm font-medium">
            <?= e($flash['message']) ?>
          </div>
          <button type="button" onclick="this.parentElement.remove()" class="text-slate-400 hover:text-slate-600 text-lg leading-none">&times;</button>
        </div>
      <?php endif; ?>

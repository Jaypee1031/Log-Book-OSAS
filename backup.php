<?php
/**
 * Database Backup & Restore Manager
 * Guidance Office Logbook Management System (OSAS)
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/helpers.php';

require_admin();
$page_title = 'Backup & Restore';

$backup_dir = __DIR__ . '/backups';
if (!is_dir($backup_dir)) {
    mkdir($backup_dir, 0755, true);
}

// 1. GENERATE & DOWNLOAD SQL BACKUP
if (($_GET['action'] ?? '') === 'download_backup') {
    if (!verify_csrf($_GET['token'] ?? '')) {
        set_flash('danger', 'Invalid security token.');
        header('Location: backup.php');
        exit;
    }

    $tables = ['system_settings', 'users', 'logbook_types', 'logbook_fields', 'logbook_records', 'audit_logs'];
    $sql_dump = "-- Guidance Office Logbook Management System (OSAS) Database Backup\n";
    $sql_dump .= "-- Generated on: " . date('Y-m-d H:i:s') . "\n";
    $sql_dump .= "-- Database: guidance_logbook_db\n\n";
    $sql_dump .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

    foreach ($tables as $tbl) {
        $create_res = $pdo->query("SHOW CREATE TABLE `{$tbl}`")->fetch(PDO::FETCH_NUM);
        $sql_dump .= "DROP TABLE IF EXISTS `{$tbl}`;\n";
        $sql_dump .= $create_res[1] . ";\n\n";

        $rows = $pdo->query("SELECT * FROM `{$tbl}`")->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($rows)) {
            $cols = array_keys($rows[0]);
            $col_names = implode('`, `', $cols);
            
            foreach ($rows as $row) {
                $values = [];
                foreach ($row as $val) {
                    if ($val === null) {
                        $values[] = 'NULL';
                    } else {
                        $values[] = $pdo->quote($val);
                    }
                }
                $val_string = implode(', ', $values);
                $sql_dump .= "INSERT INTO `{$tbl}` (`{$col_names}`) VALUES ({$val_string});\n";
            }
            $sql_dump .= "\n";
        }
    }
    $sql_dump .= "SET FOREIGN_KEY_CHECKS=1;\n";

    $filename = 'guidance_logbook_backup_' . date('Y-m-d_His') . '.sql';
    file_put_contents($backup_dir . '/' . $filename, $sql_dump);

    log_audit($pdo, 'BACKUP_DATABASE', null, null, "Generated backup file: {$filename}");

    header('Content-Type: application/sql');
    header("Content-Disposition: attachment; filename=\"{$filename}\"");
    echo $sql_dump;
    exit;
}

// 1b. DOWNLOAD EXISTING STORED SNAPSHOT
if (($_GET['action'] ?? '') === 'download_existing') {
    if (!verify_csrf($_GET['token'] ?? '')) {
        set_flash('danger', 'Invalid security token.');
        header('Location: backup.php');
        exit;
    }
    $req_file = basename($_GET['file'] ?? '');
    $full_path = $backup_dir . '/' . $req_file;
    if (!empty($req_file) && is_file($full_path) && pathinfo($req_file, PATHINFO_EXTENSION) === 'sql') {
        header('Content-Type: application/sql');
        header("Content-Disposition: attachment; filename=\"{$req_file}\"");
        readfile($full_path);
        exit;
    }
    set_flash('danger', 'Requested backup file not found.');
    header('Location: backup.php');
    exit;
}

// 2. RESTORE BACKUP FROM UPLOADED SQL FILE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'restore_backup') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        set_flash('danger', 'Invalid security token.');
        header('Location: backup.php');
        exit;
    }

    if (!isset($_FILES['backup_file']) || $_FILES['backup_file']['error'] !== UPLOAD_ERR_OK) {
        set_flash('danger', 'Please select a valid .sql backup file to upload.');
        header('Location: backup.php');
        exit;
    }

    $uploaded_file = $_FILES['backup_file']['tmp_name'];
    $sql_content = file_get_contents($uploaded_file);

    if (empty($sql_content)) {
        set_flash('danger', 'Uploaded backup file was empty.');
        header('Location: backup.php');
        exit;
    }

    try {
        $pdo->exec("SET FOREIGN_KEY_CHECKS=0");
        
        // Normalize CRLF/CR to LF and execute queries split by semicolon safely
        $sql_content = str_replace(["\r\n", "\r"], "\n", $sql_content);
        $queries = array_filter(array_map('trim', explode(";\n", $sql_content)));
        foreach ($queries as $q) {
            if (!empty($q)) {
                $pdo->exec($q);
            }
        }
        $pdo->exec("SET FOREIGN_KEY_CHECKS=1");

        log_audit($pdo, 'RESTORE_DATABASE', null, null, "Restored database from uploaded backup file: " . htmlspecialchars($_FILES['backup_file']['name']));
        set_flash('success', 'Database successfully restored from backup file!');
    } catch (Exception $e) {
        $pdo->exec("SET FOREIGN_KEY_CHECKS=1");
        set_flash('danger', 'Error restoring database: ' . $e->getMessage());
    }

    header('Location: backup.php');
    exit;
}

// Table row counts
$stats = [];
$tables = ['users', 'logbook_types', 'logbook_fields', 'logbook_records', 'audit_logs'];
foreach ($tables as $t) {
    $stats[$t] = (int)$pdo->query("SELECT COUNT(*) FROM `{$t}`")->fetchColumn();
}

// Scan previous backups in backups/
$backup_files = array_diff(scandir($backup_dir), ['.', '..']);
rsort($backup_files);

require_once __DIR__ . '/includes/header.php';
?>

<div class="space-y-6">

  <!-- Header -->
  <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-4">
    <div>
      <h1 class="text-xl sm:text-2xl font-bold text-slate-900 tracking-tight">Database Backup & Disaster Recovery</h1>
      <p class="text-xs sm:text-sm text-slate-500 mt-1">Preserve confidential guidance logs, student entries, and configurations with one-click backups.</p>
    </div>
    <a href="backup.php?action=download_backup&token=<?= csrf_token() ?>" class="inline-flex items-center gap-2 px-4 py-2.5 bg-emerald-700 hover:bg-emerald-800 text-white font-bold text-xs rounded-xl shadow-sm transition">
      <i class="bi bi-download"></i> Download Full SQL Backup
    </a>
  </div>

  <!-- Database Statistics Cards -->
  <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
    <div class="bg-white p-4 rounded-xl border border-slate-200 text-center">
      <span class="text-xs text-slate-400 font-semibold uppercase">Total Entries</span>
      <div class="text-2xl font-black text-slate-900 mt-1"><?= $stats['logbook_records'] ?></div>
      <span class="text-[10px] text-slate-500">Logbook records</span>
    </div>
    <div class="bg-white p-4 rounded-xl border border-slate-200 text-center">
      <span class="text-xs text-slate-400 font-semibold uppercase">Logbook Types</span>
      <div class="text-2xl font-black text-slate-900 mt-1"><?= $stats['logbook_types'] ?></div>
      <span class="text-[10px] text-slate-500">Active sections</span>
    </div>
    <div class="bg-white p-4 rounded-xl border border-slate-200 text-center">
      <span class="text-xs text-slate-400 font-semibold uppercase">Form Fields</span>
      <div class="text-2xl font-black text-slate-900 mt-1"><?= $stats['logbook_fields'] ?></div>
      <span class="text-[10px] text-slate-500">Configured fields</span>
    </div>
    <div class="bg-white p-4 rounded-xl border border-slate-200 text-center">
      <span class="text-xs text-slate-400 font-semibold uppercase">Staff & Admin</span>
      <div class="text-2xl font-black text-slate-900 mt-1"><?= $stats['users'] ?></div>
      <span class="text-[10px] text-slate-500">Registered users</span>
    </div>
    <div class="bg-white p-4 rounded-xl border border-slate-200 text-center">
      <span class="text-xs text-slate-400 font-semibold uppercase">Audit Trail</span>
      <div class="text-2xl font-black text-slate-900 mt-1"><?= $stats['audit_logs'] ?></div>
      <span class="text-[10px] text-slate-500">Logged actions</span>
    </div>
  </div>

  <!-- Restore Database Section -->
  <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    
    <!-- Upload and Restore -->
    <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm space-y-4">
      <div class="flex items-center gap-2 border-b border-slate-200 pb-3">
        <div class="w-8 h-8 rounded-lg bg-amber-100 text-amber-700 flex items-center justify-center font-bold">
          <i class="bi bi-upload"></i>
        </div>
        <div>
          <h2 class="text-base font-bold text-slate-900">Restore Database from File</h2>
          <p class="text-xs text-slate-500">Upload a valid .sql backup file to restore records.</p>
        </div>
      </div>

      <div class="p-4 bg-amber-50 border border-amber-200 rounded-xl text-xs text-amber-800">
        <i class="bi bi-exclamation-triangle-fill mr-1"></i>
        <strong>Warning:</strong> Restoring will overwrite existing tables and records with the data in the uploaded backup file. Ensure you download a fresh backup first.
      </div>

      <form method="POST" action="backup.php" enctype="multipart/form-data" class="space-y-4">
        <input type="hidden" name="action" value="restore_backup">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

        <div>
          <label class="block text-xs font-semibold text-slate-700 mb-1">Select .sql Backup File</label>
          <input type="file" name="backup_file" accept=".sql" required class="w-full text-xs text-slate-700 file:mr-3 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-brand-50 file:text-brand-900 hover:file:bg-brand-100 border border-slate-200 rounded-xl p-1">
        </div>

        <button type="submit" onclick="return confirm('CAUTION: Are you sure you want to restore the database from this file? Current records will be replaced.')" class="w-full py-2.5 bg-amber-600 hover:bg-amber-700 text-white font-bold text-xs rounded-xl shadow-sm transition">
          Restore Database Now
        </button>
      </form>
    </div>

    <!-- Local Server Snapshots -->
    <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm space-y-4">
      <div class="flex items-center gap-2 border-b border-slate-200 pb-3">
        <div class="w-8 h-8 rounded-lg bg-emerald-100 text-emerald-800 flex items-center justify-center font-bold">
          <i class="bi bi-folder-check"></i>
        </div>
        <div>
          <h2 class="text-base font-bold text-slate-900">Stored Server Snapshots</h2>
          <p class="text-xs text-slate-500">Backups saved in <code>LOG BOOK OSAS/backups/</code></p>
        </div>
      </div>

      <?php if (empty($backup_files)): ?>
        <p class="text-xs text-slate-500 py-6 text-center">No previous backup files stored on server yet.</p>
      <?php else: ?>
        <div class="overflow-y-auto max-h-56 divide-y divide-slate-100 text-xs">
          <?php foreach ($backup_files as $bf): 
            $filepath = $backup_dir . '/' . $bf;
            $filesize = round(filesize($filepath) / 1024, 1);
            $filetime = date('M d, Y h:i A', filemtime($filepath));
          ?>
            <div class="py-2.5 flex items-center justify-between gap-2">
              <div class="truncate">
                <div class="font-medium text-slate-800 truncate"><?= e($bf) ?></div>
                <div class="text-[10px] text-slate-400"><?= $filesize ?> KB • <?= $filetime ?></div>
              </div>
              <a href="backup.php?action=download_existing&file=<?= urlencode($bf) ?>&token=<?= csrf_token() ?>" class="badge-pill bg-[#e6f4ea] hover:bg-[#006633] text-[#006633] hover:text-white border border-[#b7dfc8] transition shrink-0">
                <i class="bi bi-download mr-1"></i> Download
              </a>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

  </div>

</div>

<?php require __DIR__ . '/includes/footer.php'; ?>

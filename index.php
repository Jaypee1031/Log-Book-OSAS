<?php
/**
 * QSU Guidance Office Logbook — Student & Walk-in Data Collection Portal
 * Styled to match the Quirino State University (QSU) Admission Portal
 * 
 * Default Landing Page (index.php) - Consumes full screen width with QSU Green (#006633) Theme
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/helpers.php';

$system_settings = get_system_settings($pdo);
$active_logbooks = get_logbook_types($pdo, true);

// Selected logbook section (default to Client's Log)
$selected_slug = $_GET['type'] ?? 'clients-log';
$current_logbook = get_logbook_type_by_slug($pdo, $selected_slug);

if (!$current_logbook && !empty($active_logbooks)) {
    $current_logbook = $active_logbooks[0];
    $selected_slug = $current_logbook['slug'];
}

$fields = $current_logbook ? get_logbook_fields($pdo, $current_logbook['id']) : [];
$success_record = null;
$error_message = null;

// Handle Student / Walk-in Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $logbook_type_id = (int)($_POST['logbook_type_id'] ?? 0);
    $submitted_slug = $_POST['type_slug'] ?? 'clients-log';
    $entry_date = date('Y-m-d');
    $time_now = date('H:i:s');
    $fields_data = $_POST['fields'] ?? [];

    // Normalize any array fields (e.g. multiselect checkboxes) to comma-separated string
    foreach ($fields_data as $k => $v) {
        if (is_array($v)) {
            $fields_data[$k] = implode(', ', array_filter(array_map('trim', $v)));
        }
    }

    $client_name = trim($fields_data['name'] ?? $fields_data['client_name'] ?? '');
    $client_type = trim($fields_data['client_type'] ?? 'Student');
    $purpose = trim($fields_data['purpose'] ?? $fields_data['item_borrowed'] ?? $fields_data['referral_type'] ?? '');
    
    // If "None" or "Other" purpose specified with manual input
    $purpose_custom = trim($_POST['purpose_custom'] ?? $_POST['purpose_other_custom'] ?? '');
    if ((strcasecmp($purpose, 'None') === 0 || strcasecmp($purpose, 'Other') === 0 || strcasecmp($purpose, 'Others') === 0) && !empty($purpose_custom)) {
        $purpose = $purpose_custom;
        $fields_data['purpose'] = $purpose;
    }

    // If "None" or "Other" agency specified with manual input
    $agency = trim($fields_data['agency'] ?? '');
    if ((strcasecmp($agency, 'None') === 0 || strcasecmp($agency, 'Other') === 0 || strcasecmp($agency, 'Others') === 0) && !empty($_POST['agency_custom'])) {
        $agency = trim($_POST['agency_custom']);
        $fields_data['agency'] = $agency;
    }

    // If "None", "Other", or "Faculty/Office" course_year specified with manual input
    $course_year_key = isset($fields_data['course_year']) ? 'course_year' : (isset($fields_data['course_year_dept']) ? 'course_year_dept' : null);
    if ($course_year_key) {
        $cy_val = trim($fields_data[$course_year_key] ?? '');
        if ((strcasecmp($cy_val, 'None') === 0 || strcasecmp($cy_val, 'Other') === 0 || strcasecmp($cy_val, 'Others') === 0 || stripos($cy_val, 'Office') !== false || stripos($cy_val, 'Faculty') !== false) && !empty($_POST['course_year_custom'])) {
            $fields_data[$course_year_key] = trim($_POST['course_year_custom']);
        }
    }

    $notes = trim($_POST['student_notes'] ?? '');

    if (!empty($client_name)) {
        // Auto-assign next sequential record number
        $record_no = get_next_record_no($pdo, $logbook_type_id);

        // Auto-fill time_in if not set
        if (!isset($fields_data['time_in']) || empty($fields_data['time_in'])) {
            $fields_data['time_in'] = $time_now;
        }
        $fields_data['date'] = $entry_date;
        $fields_data['name'] = $client_name;
        $fields_data['signature_confirmed'] = '1';

        // Set default return status for borrowers log
        if ($submitted_slug === 'borrowers-log' && empty($fields_data['return_status'])) {
            $fields_data['return_status'] = 'Pending';
        }

        $data_json = json_encode($fields_data, JSON_UNESCAPED_UNICODE);

        try {
            $stmt = $pdo->prepare("INSERT INTO `logbook_records` 
                (`logbook_type_id`, `record_no`, `entry_date`, `client_name`, `client_type`, `purpose`, `status`, `notes`, `data_json`, `created_by`, `created_at`) 
                VALUES (?, ?, ?, ?, ?, ?, 'active', ?, ?, NULL, NOW())");
            $stmt->execute([
                $logbook_type_id, $record_no, $entry_date, $client_name, $client_type, $purpose, $notes, $data_json
            ]);
            $inserted_id = $pdo->lastInsertId();

            log_audit($pdo, 'STUDENT_SELF_REGISTRATION', $logbook_type_id, (int)$inserted_id, "Student walk-in logged #{$record_no} ({$client_name})");

            $success_record = [
                'record_no' => $record_no,
                'name' => $client_name,
                'logbook_title' => $current_logbook['title'],
                'time_in' => $fields_data['time_in'],
                'date' => $entry_date
            ];
        } catch (Exception $e) {
            $error_message = "An error occurred while saving your logbook entry. Please inform the guidance staff.";
        }
    } else {
        $error_message = "Please enter your Full Name.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Guidance Office Logbook | <?= e($system_settings['institution_name'] ?? 'Quirino State University') ?></title>
  <link rel="icon" type="image/x-icon" href="favicon.ico">
  
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            qsu: {
              DEFAULT: '#006633',
              dark: '#004422',
              light: '#008844',
              secondary: '#28a745',
              soft: '#f0fdf4',
              accent: '#ffc107'
            }
          }
        }
      }
    }
  </script>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="bg-slate-100 min-h-screen text-slate-800 flex flex-col justify-between antialiased">

  <!-- QSU Official Navbar (Themed to Match QSU Admission Portal) -->
  <header class="bg-[#006633] text-white shadow-md sticky top-0 z-30">
    <div class="w-full px-4 sm:px-8 lg:px-12 h-18 py-2.5 flex items-center justify-between">
      
      <!-- Left: QSU Logo & Brand Heading -->
      <a href="index.php" class="flex items-center gap-3.5 group">
        <img src="assets/images/qsulogo.png" alt="QSU Logo" class="h-11 sm:h-12 w-auto object-contain drop-shadow group-hover:scale-105 transition-transform">
        <div>
          <div class="font-bold text-white text-sm sm:text-base tracking-wide flex items-center gap-2">
            <span>Quirino State University</span>
            <span class="text-[10px] bg-white/20 text-white font-semibold px-2 py-0.5 rounded-full border border-white/30 uppercase tracking-wider hidden sm:inline-block">Diffun Campus</span>
          </div>
          <p class="text-xs text-white/90 font-medium">Office of Student Affairs & Services • Guidance & Counseling Logbook</p>
        </div>
      </a>

      <!-- Right: Live Clock & Admin Navigation -->
      <div class="flex items-center gap-4">
        <div class="text-right hidden sm:block text-white">
          <div id="live-clock" class="text-xs font-mono font-bold">--:--:--</div>
          <div class="text-[10px] text-white/80"><?= date('l, F d, Y') ?></div>
        </div>

        <?php if (is_admin()): ?>
          <!-- Logged in as Admin: Direct Link to Admin Dashboard -->
          <a href="dashboard.php" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-white text-[#006633] hover:bg-slate-100 text-xs font-bold shadow transition">
            <i class="bi bi-speedometer2"></i>
            <span>Admin Dashboard</span>
          </a>
        <?php else: ?>
          <!-- Not Logged In: Admin Sign In Button -->
          <a href="login.php" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl border border-white/80 hover:bg-white hover:text-[#006633] text-white text-xs font-bold transition">
            <i class="bi bi-shield-lock-fill"></i>
            <span>Admin Sign In</span>
          </a>
        <?php endif; ?>
      </div>

    </div>
  </header>

  <!-- Main Student Walk-in Fullscreen Width Container -->
  <main class="w-full px-4 sm:px-8 lg:px-12 xl:px-16 py-6 sm:py-8 flex-1 flex flex-col justify-center">

    <?php if ($success_record): ?>
      <!-- SUCCESS CONFIRMATION SCREEN (With Countdown & Reset) -->
      <div class="w-full max-w-2xl mx-auto bg-white rounded-3xl p-8 sm:p-12 shadow-2xl border border-slate-200 text-center animate-in zoom-in-95 duration-200">
        <div class="w-20 h-20 rounded-full bg-[#f0fdf4] text-[#006633] flex items-center justify-center text-5xl mx-auto mb-5 shadow-inner ring-8 ring-[#e6f4ea]">
          <i class="bi bi-check-lg"></i>
        </div>

        <span class="badge-pill bg-[#e6f4ea] text-[#006633] border border-[#b7dfc8] text-xs font-bold uppercase tracking-wider mb-2">
          Entry Confirmed
        </span>

        <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 mt-2">
          Thank you, <?= e($success_record['name']) ?>!
        </h1>
        <p class="text-slate-600 text-sm mt-2 max-w-md mx-auto">
          Your attendance has been recorded into the <strong><?= e($success_record['logbook_title']) ?></strong>.
        </p>

        <!-- Ticket Reference Box -->
        <div class="bg-slate-50 rounded-2xl p-5 border border-slate-200 max-w-md mx-auto my-6 text-left space-y-2.5">
          <div class="flex justify-between items-center text-xs">
            <span class="text-slate-500 font-semibold uppercase tracking-wider">Reference Record No.</span>
            <span class="font-mono font-black text-[#006633] text-xl">#<?= $success_record['record_no'] ?></span>
          </div>
          <div class="flex justify-between items-center text-xs">
            <span class="text-slate-500 font-semibold uppercase tracking-wider">Date</span>
            <span class="font-bold text-slate-800"><?= format_date($success_record['date']) ?></span>
          </div>
          <div class="flex justify-between items-center text-xs">
            <span class="text-slate-500 font-semibold uppercase tracking-wider">Time-In Recorded</span>
            <span class="font-mono font-bold text-slate-800 text-sm"><?= format_time($success_record['time_in']) ?></span>
          </div>
        </div>

        <div class="p-4 bg-[#f0fdf4] border border-[#b7dfc8] rounded-2xl max-w-md mx-auto text-xs text-[#004422] font-medium mb-6">
          <i class="bi bi-info-circle-fill mr-1.5 text-[#006633] text-sm"></i>
          Please take a seat in the guidance office waiting lounge. A counselor will attend to you shortly.
        </div>

        <div class="flex flex-col sm:flex-row items-center justify-center gap-3">
          <a href="index.php?type=<?= urlencode($selected_slug) ?>" class="w-full sm:w-auto px-7 py-3 bg-[#006633] hover:bg-[#004422] text-white font-bold text-xs rounded-xl shadow-md transition">
            Log Another Person
          </a>
          <span class="text-xs text-slate-500" id="reset-counter">
            Auto-resetting in <strong id="seconds-left" class="text-[#006633] font-bold">8</strong>s...
          </span>
        </div>
      </div>

      <script>
        let seconds = 8;
        const countdown = setInterval(() => {
          seconds--;
          const el = document.getElementById('seconds-left');
          if (el) el.innerText = seconds;
          if (seconds <= 0) {
            clearInterval(countdown);
            window.location.href = 'index.php?type=<?= urlencode($selected_slug) ?>';
          }
        }, 1000);
      </script>

    <?php else: ?>
      
      <!-- DATA COLLECTION LOGBOOK SCREEN (FULLSCREEN CONSUMED) -->
      <div class="w-full space-y-6">

        <!-- Welcome Banner Header -->
        <div class="text-center space-y-1.5 mb-2">
          <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">
            Guidance Office Logbook
          </h1>
          <p class="text-xs sm:text-sm text-slate-500 max-w-2xl mx-auto">
            Welcome to the Office of Guidance & Counseling Affairs (OGCA). Please select your logbook section, complete your details below, and submit to record your attendance.
          </p>
        </div>

        <!-- STEP 1: QSU ADMISSION PORTAL STYLED SECTION CARDS -->
        <div>
          <div class="text-xs font-bold text-[#006633] uppercase tracking-wider text-center mb-3">
            <i class="bi bi-ui-checks-grid mr-1"></i> STEP 1: CHOOSE WHAT YOU ARE SIGNING / REQUESTING
          </div>

          <!-- Stretches across the full screen width in a balanced grid -->
          <div class="grid grid-cols-2 sm:grid-cols-2 md:grid-cols-4 lg:grid-cols-5 gap-3.5 sm:gap-4">
            <?php foreach ($active_logbooks as $alb): 
              $isSelected = ($alb['slug'] === $selected_slug);
            ?>
              <a href="index.php?type=<?= urlencode($alb['slug']) ?>" class="p-4 sm:p-5 rounded-2xl border transition text-center flex flex-col items-center justify-center gap-2 <?= $isSelected ? 'bg-[#006633] text-white border-[#004422] shadow-lg shadow-[#006633]/25 ring-2 ring-[#008844] scale-[1.02]' : 'bg-white text-slate-700 border-slate-200 hover:border-[#006633] hover:bg-[#f0fdf4] shadow-sm' ?>">
                <div class="w-12 h-12 rounded-xl flex items-center justify-center text-2xl <?= $isSelected ? 'bg-white/15 text-white' : 'bg-[#e6f4ea] text-[#006633]' ?>">
                  <i class="bi <?= e($alb['icon']) ?>"></i>
                </div>
                <div class="font-bold text-xs sm:text-sm leading-tight mt-1"><?= e($alb['title']) ?></div>
                <span class="text-[10px] <?= $isSelected ? 'text-white/80' : 'text-slate-400' ?> hidden sm:block">Click to select</span>
              </a>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- STEP 2: FULL-WIDTH EXPANSIVE FORM CARD -->
        <div class="w-full bg-white rounded-2xl shadow-md border border-slate-200 overflow-hidden">
          
          <!-- Card Header Bar (QSU Green Banner) -->
          <div class="bg-[#006633] text-white px-6 sm:px-8 py-4 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
            <div class="flex items-center gap-3">
              <i class="bi <?= e($current_logbook['icon']) ?> text-2xl"></i>
              <div>
                <h2 class="text-base sm:text-lg font-bold leading-tight">
                  <?= e($current_logbook['title']) ?>
                </h2>
                <p class="text-xs text-white/80 mt-0.5"><?= e($current_logbook['description']) ?></p>
              </div>
            </div>

            <span class="badge-pill bg-white/20 text-white text-xs font-semibold border border-white/30 self-start sm:self-auto">
              Date: <?= date('F d, Y') ?>
            </span>
          </div>

          <!-- Card Body -->
          <div class="p-6 sm:p-8 lg:p-10">

            <?php if (!empty($error_message)): ?>
              <div class="mb-6 p-4 rounded-xl text-xs sm:text-sm font-semibold bg-rose-50 border border-rose-200 text-rose-800 flex items-center gap-3">
                <i class="bi bi-exclamation-triangle-fill text-lg"></i>
                <span><?= e($error_message) ?></span>
              </div>
            <?php endif; ?>

            <form method="POST" action="index.php?type=<?= urlencode($selected_slug) ?>" class="space-y-6">
              <input type="hidden" name="logbook_type_id" value="<?= $current_logbook['id'] ?>">
              <input type="hidden" name="type_slug" value="<?= e($selected_slug) ?>">

              <!-- Form Fields Responsive Grid that consumes the full width -->
              <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-5 sm:gap-6">
                
                <!-- Full Name (Spans 2 columns) -->
                <div class="md:col-span-2 lg:col-span-2">
                  <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5" for="input_name">
                    Full Name <span class="text-rose-500">*</span>
                  </label>
                  <div class="relative">
                    <i class="bi bi-person absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg"></i>
                    <input type="text" name="fields[name]" id="input_name" required placeholder="Last Name, First Name M.I." class="w-full pl-11 pr-4 py-3 text-sm rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-[#006633] focus:border-[#006633] text-slate-900 font-semibold bg-slate-50/50">
                  </div>
                </div>

                <!-- Dynamic Fields for This Specific Logbook -->
                <?php foreach ($fields as $f): 
                  if (in_array($f['field_key'], ['name', 'date', 'return_status', 'date_of_return', 'date_issued', 'signature_confirmed'])) continue;
                  
                  // Determine column span for wide layout
                  $col_span = 'col-span-1';
                  if ($f['field_type'] === 'textarea' || $f['field_type'] === 'multiselect' || $f['field_key'] === 'vacant_time') {
                    $col_span = 'md:col-span-2 lg:col-span-4';
                  } elseif (in_array($f['field_key'], ['purpose', 'agency', 'course_year_dept', 'course_year'])) {
                    $col_span = 'md:col-span-2 lg:col-span-2';
                  }
                ?>
                  <div class="<?= $col_span ?>">
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5" for="dyn_<?= e($f['field_key']) ?>">
                      <?= e($f['field_label']) ?>
                      <?php if ($f['is_required']): ?>
                        <span class="text-rose-500">*</span>
                      <?php else: ?>
                        <span class="text-[11px] font-semibold text-slate-400 normal-case tracking-normal ml-1">(Optional)</span>
                      <?php endif; ?>
                    </label>

                    <?php if ($f['field_type'] === 'select'): 
                      $opts = json_decode($f['options_json'] ?? '[]', true) ?: [];
                    ?>
                      <select name="fields[<?= e($f['field_key']) ?>]" id="dyn_<?= e($f['field_key']) ?>" <?= $f['is_required'] ? 'required' : '' ?> class="w-full px-4 py-3 text-sm rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-[#006633] focus:border-[#006633] text-slate-800 bg-white font-medium">
                        <option value="">— Select <?= e($f['field_label']) ?> —</option>
                        <?php 
                        $cur_grp = '';
                        foreach ($opts as $op): 
                          if (strpos($op, ' - ') !== false) {
                            $parts = explode(' - ', $op, 2);
                            $grp_name = $parts[0];
                            if ($grp_name !== $cur_grp) {
                              if ($cur_grp !== '') echo '</optgroup>';
                              echo '<optgroup label="' . e($grp_name) . '">';
                              $cur_grp = $grp_name;
                            }
                            echo '<option value="' . e($op) . '">' . e($op) . '</option>';
                          } else {
                            if ($cur_grp !== '') {
                              echo '</optgroup>';
                              $cur_grp = '';
                            }
                            echo '<option value="' . e($op) . '">' . e($op) . '</option>';
                          }
                        endforeach; 
                        if ($cur_grp !== '') echo '</optgroup>';
                        ?>
                      </select>

                      <?php if ($f['field_key'] === 'course_year' || $f['field_key'] === 'course_year_dept'): ?>
                        <!-- Manual Input for Course & Year / Office (Revealed when 'None', 'Other', or 'Faculty/Office' selected) -->
                        <div id="course_year_manual_box" class="hidden mt-3 p-3.5 rounded-xl bg-[#f0fdf4] border border-[#b7dfc8] space-y-1.5 animate-in fade-in duration-150">
                          <label class="block text-xs font-bold text-[#006633] uppercase tracking-wider" for="course_year_custom">
                            <i class="bi bi-pencil-square mr-1"></i> Specify Course & Year or Office Name *
                          </label>
                          <input type="text" 
                                 name="course_year_custom" 
                                 id="course_year_custom" 
                                 placeholder="e.g. BSED 2-B, CAS Faculty, Accounting Office" 
                                 class="w-full px-4 py-2.5 text-sm rounded-lg border border-[#b7dfc8] focus:outline-none focus:ring-2 focus:ring-[#006633] text-slate-800 font-medium bg-white">
                          <p class="text-[11px] text-slate-500">Please enter your degree program & section, or department office manually.</p>
                        </div>
                      <?php endif; ?>

                      <?php if ($f['field_key'] === 'agency'): ?>
                        <!-- Manual Input for Agency / Institution / Office (Revealed when 'None' or 'Other' selected) -->
                        <div id="agency_manual_box" class="hidden mt-3 p-3.5 rounded-xl bg-[#f0fdf4] border border-[#b7dfc8] space-y-1.5 animate-in fade-in duration-150">
                          <label class="block text-xs font-bold text-[#006633] uppercase tracking-wider" for="agency_custom">
                            <i class="bi bi-pencil-square mr-1"></i> Specify Agency / Institution / Office Name *
                          </label>
                          <input type="text" 
                                 name="agency_custom" 
                                 id="agency_custom" 
                                 placeholder="e.g. St. Mary's Academy, Private Citizen, LGU Diffun, etc." 
                                 class="w-full px-4 py-2.5 text-sm rounded-lg border border-[#b7dfc8] focus:outline-none focus:ring-2 focus:ring-[#006633] text-slate-800 font-medium bg-white">
                          <p class="text-[11px] text-slate-500">Please enter your company, school, organization, or affiliation manually.</p>
                        </div>
                      <?php endif; ?>

                      <?php if ($f['field_key'] === 'purpose' && $selected_slug !== 'good-moral-log'): ?>
                        <!-- Manual Input for Purpose (Revealed when 'None' or 'Other' selected) -->
                        <div id="purpose_manual_box" class="hidden mt-3 p-3.5 rounded-xl bg-[#f0fdf4] border border-[#b7dfc8] space-y-1.5 animate-in fade-in duration-150">
                          <label class="block text-xs font-bold text-[#006633] uppercase tracking-wider" for="purpose_custom">
                            <i class="bi bi-pencil-square mr-1"></i> Specify Purpose of Visit / Entry *
                          </label>
                          <input type="text" 
                                 name="purpose_custom" 
                                 id="purpose_custom" 
                                 placeholder="e.g. OJT Coordination, Scholarship Validation, Campus Tour, etc." 
                                 class="w-full px-4 py-2.5 text-sm rounded-lg border border-[#b7dfc8] focus:outline-none focus:ring-2 focus:ring-[#006633] text-slate-800 font-medium bg-white">
                          <p class="text-[11px] text-slate-500">Please enter your specific purpose or reason for entry manually.</p>
                        </div>
                      <?php endif; ?>

                    <?php elseif ($f['field_type'] === 'textarea'): ?>
                      <textarea name="fields[<?= e($f['field_key']) ?>]" id="dyn_<?= e($f['field_key']) ?>" rows="2" placeholder="<?= e($f['placeholder'] ?? '') ?>" <?= $f['is_required'] ? 'required' : '' ?> class="w-full px-4 py-3 text-sm rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-[#006633] focus:border-[#006633] text-slate-800 font-medium"></textarea>

                    <?php elseif ($f['field_type'] === 'time'): 
                      $default_time = ($f['field_key'] === 'time_in') ? date('H:i') : '';
                    ?>
                      <input type="time" name="fields[<?= e($f['field_key']) ?>]" id="dyn_<?= e($f['field_key']) ?>" value="<?= $default_time ?>" <?= ($f['field_key'] === 'time_in' && $f['is_required']) ? 'required' : '' ?> class="w-full px-4 py-3 text-sm rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-[#006633] focus:border-[#006633] text-slate-800 font-mono font-medium">
                      <?php if ($f['field_key'] === 'time_out'): ?>
                        <p class="text-[11px] text-slate-400 mt-1">Leave blank on arrival; can be recorded upon departure.</p>
                      <?php endif; ?>

                    <?php elseif ($f['field_type'] === 'multiselect'): 
                      $opts = json_decode($f['options_json'] ?? '[]', true) ?: [];
                    ?>
                      <!-- Dropdown Multi-Select Schedule (Drops down on click, shows all weekdays with zero scroll) -->
                      <div class="relative multiselect-wrapper" data-field-key="<?= e($f['field_key']) ?>">
                        
                        <!-- Hidden input for HTML5 required constraint -->
                        <input type="text" 
                               id="req_ms_<?= e($f['field_key']) ?>" 
                               <?= $f['is_required'] ? 'required' : '' ?> 
                               class="opacity-0 absolute pointer-events-none w-0 h-0" 
                               tabindex="-1"
                               aria-hidden="true">

                        <!-- Dropdown Trigger Button -->
                        <button type="button" 
                                onclick="toggleMultiSelect('<?= e($f['field_key']) ?>')" 
                                id="btn_ms_<?= e($f['field_key']) ?>" 
                                class="w-full px-4 py-3.5 text-sm rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-[#006633] focus:border-[#006633] text-slate-800 bg-white font-medium flex items-center justify-between text-left shadow-xs hover:border-[#006633] transition cursor-pointer group">
                          <div class="flex items-center gap-2.5 overflow-hidden flex-1 mr-3" id="display_ms_<?= e($f['field_key']) ?>">
                            <div class="w-8 h-8 rounded-lg bg-[#e6f4ea] text-[#006633] flex items-center justify-center text-sm font-bold shrink-0">
                              <i class="bi bi-calendar-week"></i>
                            </div>
                            <div class="truncate flex items-center gap-2" id="label_container_ms_<?= e($f['field_key']) ?>">
                              <span id="label_ms_<?= e($f['field_key']) ?>" class="truncate text-slate-500 font-normal">
                                <?= e($f['placeholder'] ?? '— Select Vacant Weekday & Time (Click to open schedule) —') ?>
                              </span>
                            </div>
                          </div>
                          <div class="flex items-center gap-2 shrink-0">
                            <span id="btn_badge_ms_<?= e($f['field_key']) ?>" class="hidden px-2.5 py-1 rounded-full text-xs font-bold bg-[#006633] text-white">
                              0 Selected
                            </span>
                            <i class="bi bi-chevron-down text-slate-400 group-hover:text-[#006633] text-sm transition-transform duration-200" id="icon_ms_<?= e($f['field_key']) ?>"></i>
                          </div>
                        </button>

                        <!-- Dropped-Down Schedule Menu Panel (See All, Easy Fill Up, Zero Scroll) -->
                        <div id="panel_ms_<?= e($f['field_key']) ?>" 
                             class="hidden mt-2 bg-white rounded-2xl border-2 border-[#006633]/30 p-4 sm:p-5 shadow-xl transition-all space-y-4">
                          
                          <!-- Top Header & Action Controls Bar inside Dropdown -->
                          <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-slate-200">
                            <div class="flex items-center gap-2.5">
                              <div class="w-9 h-9 rounded-xl bg-[#e6f4ea] text-[#006633] flex items-center justify-center text-lg font-bold">
                                <i class="bi bi-calendar-check-fill"></i>
                              </div>
                              <div>
                                <div class="text-xs sm:text-sm font-bold text-slate-900 uppercase tracking-wide">
                                  Weekly Vacant Time Schedule
                                </div>
                                <p class="text-[11px] text-slate-500">All weekdays and slots visible at once — click to select when you are free</p>
                              </div>
                            </div>

                            <!-- Live Status & Quick Action Buttons -->
                            <div class="flex items-center gap-2 self-start sm:self-auto">
                              <span class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-bold bg-[#006633] text-white shadow-xs" id="badge_counter_<?= e($f['field_key']) ?>">
                                0 Slots Selected
                              </span>
                              <button type="button" onclick="selectAllMS('<?= e($f['field_key']) ?>', true)" class="px-2.5 py-1 rounded-lg bg-slate-100 hover:bg-[#e6f4ea] text-xs font-bold text-[#006633] transition border border-slate-200">
                                Select All
                              </button>
                              <button type="button" onclick="selectAllMS('<?= e($f['field_key']) ?>', false)" class="px-2.5 py-1 rounded-lg bg-slate-100 hover:bg-slate-200 text-xs font-semibold text-slate-600 transition border border-slate-200">
                                Clear All
                              </button>
                            </div>
                          </div>

                          <!-- 5 Columns for Weekdays: Monday to Friday (Visible All at Once, Zero Scroll) -->
                          <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-3 pt-1">
                            <?php
                            $weekdays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
                            foreach ($weekdays as $wday):
                              $day_slots = [];
                              foreach ($opts as $op) {
                                if (strpos($op, $wday) !== false) {
                                  $day_slots[] = $op;
                                }
                              }
                            ?>
                              <div class="bg-slate-50/80 rounded-xl border border-slate-200 p-2.5 flex flex-col justify-between space-y-2">
                                
                                <!-- Day Header with Quick "All" Toggle -->
                                <div class="pb-1.5 border-b border-slate-200 flex items-center justify-between">
                                  <span class="font-bold text-slate-800 text-xs flex items-center gap-1.5 uppercase tracking-wide">
                                    <span class="w-2 h-2 rounded-full bg-[#006633]"></span>
                                    <span><?= $wday ?></span>
                                  </span>
                                  <button type="button" onclick="toggleDaySlots('<?= e($f['field_key']) ?>', '<?= $wday ?>')" class="text-[10px] text-[#006633] font-bold hover:underline px-1 py-0.5 rounded hover:bg-[#e6f4ea]">
                                    Toggle All
                                  </button>
                                </div>

                                <!-- Slots for this specific day -->
                                <div class="space-y-1.5 flex-1">
                                  <?php foreach ($day_slots as $slot): 
                                    $short_label = trim(str_replace([$wday, '(', ')'], '', $slot));
                                    $is_whole_day = (strpos($slot, 'Whole Day') !== false);
                                  ?>
                                    <label class="ms-slot-card ms-slot-card-<?= e($f['field_key']) ?> flex items-center gap-2 p-2 rounded-lg border border-slate-200 bg-white hover:border-[#006633] hover:bg-[#f0fdf4] cursor-pointer transition select-none group text-left <?= $is_whole_day ? 'bg-amber-50/50 border-amber-200/80' : '' ?>" data-slot="<?= e($slot) ?>">
                                      <input type="checkbox" 
                                             name="fields[<?= e($f['field_key']) ?>][]" 
                                             value="<?= e($slot) ?>" 
                                             onchange="updateMultiSelectState('<?= e($f['field_key']) ?>')" 
                                             class="ms-checkbox-<?= e($f['field_key']) ?> ms-day-<?= $wday ?>-<?= e($f['field_key']) ?> w-3.5 h-3.5 rounded text-[#006633] focus:ring-[#006633] border-slate-300 accent-[#006633] shrink-0">
                                      <span class="text-[11px] font-semibold text-slate-700 group-hover:text-[#006633] leading-tight flex-1 <?= $is_whole_day ? 'text-amber-900 font-bold' : '' ?>">
                                        <?= e($short_label) ?>
                                      </span>
                                    </label>
                                  <?php endforeach; ?>
                                </div>

                              </div>
                            <?php endforeach; ?>
                          </div>

                          <!-- Common Recurring Collegiate Blocks (MWF / TTh) Row -->
                          <div class="p-3 bg-slate-50/80 rounded-xl border border-slate-200 mt-2">
                            <div class="text-[11px] font-bold text-slate-700 uppercase tracking-wider mb-2 flex items-center gap-1.5">
                              <i class="bi bi-clock-history text-[#006633]"></i>
                              <span>Or Select Common Recurring University Blocks</span>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-2">
                              <?php 
                              foreach ($opts as $op):
                                if (strpos($op, 'MWF') === false && strpos($op, 'TTh') === false) continue;
                              ?>
                                <label class="ms-slot-card ms-slot-card-<?= e($f['field_key']) ?> flex items-center gap-2.5 p-2 rounded-lg border border-slate-200 bg-white hover:border-[#006633] hover:bg-[#f0fdf4] cursor-pointer transition select-none group">
                                  <input type="checkbox" 
                                         name="fields[<?= e($f['field_key']) ?>][]" 
                                         value="<?= e($op) ?>" 
                                         onchange="updateMultiSelectState('<?= e($f['field_key']) ?>')" 
                                         class="ms-checkbox-<?= e($f['field_key']) ?> w-4 h-4 rounded text-[#006633] focus:ring-[#006633] border-slate-300 accent-[#006633] shrink-0">
                                  <span class="text-xs font-bold text-slate-800 group-hover:text-[#006633]">
                                    <?= e($op) ?>
                                  </span>
                                </label>
                              <?php endforeach; ?>
                            </div>
                          </div>

                          <!-- Dropdown Panel Footer with Done / Close Button -->
                          <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pt-3 border-t border-slate-200">
                            <span class="text-xs text-slate-500">
                              Tip: You can select multiple slots across different days.
                            </span>
                            <button type="button" 
                                    onclick="toggleMultiSelect('<?= e($f['field_key']) ?>')" 
                                    class="px-5 py-2.5 rounded-xl bg-[#006633] hover:bg-[#004d26] text-white text-xs font-bold shadow-md hover:shadow-lg transition flex items-center justify-center gap-1.5 cursor-pointer">
                              <i class="bi bi-check-lg text-base"></i>
                              <span>Done (Close Dropdown)</span>
                            </button>
                          </div>

                        </div>

                        <!-- Selected Summary Pill Box (Visible below the dropdown button even when closed) -->
                        <div id="summary_box_<?= e($f['field_key']) ?>" class="hidden mt-2.5 p-3 rounded-xl bg-[#f0fdf4] border border-[#b7dfc8] shadow-xs">
                          <div class="text-[10px] font-bold text-[#004422] uppercase tracking-wider mb-1.5 flex items-center justify-between">
                            <span class="flex items-center gap-1.5">
                              <i class="bi bi-check2-circle text-[#006633]"></i>
                              <span>Your Selected Vacant Schedule:</span>
                            </span>
                            <button type="button" onclick="toggleMultiSelect('<?= e($f['field_key']) ?>')" class="text-[10px] text-[#006633] hover:underline font-bold">
                              Edit Schedule
                            </button>
                          </div>
                          <div id="selected_pills_<?= e($f['field_key']) ?>" class="flex flex-wrap gap-1.5">
                            <!-- Injected via JavaScript -->
                          </div>
                        </div>

                      </div>

                    <?php else: ?>
                      <input type="text" name="fields[<?= e($f['field_key']) ?>]" id="dyn_<?= e($f['field_key']) ?>" placeholder="<?= e($f['placeholder'] ?? '') ?>" <?= $f['is_required'] ? 'required' : '' ?> class="w-full px-4 py-3 text-sm rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-[#006633] focus:border-[#006633] text-slate-800 font-medium">
                    <?php endif; ?>

                    <?php if (!empty($f['help_text']) && $f['field_key'] !== 'time_out'): ?>
                      <p class="text-[11px] text-slate-400 mt-1"><?= e($f['help_text']) ?></p>
                    <?php endif; ?>
                  </div>
                <?php endforeach; ?>



                <!-- Digital Signature / In-Person Confirmation Block -->
                <div class="col-span-1 md:col-span-2 lg:col-span-4 p-5 rounded-xl bg-[#f0fdf4] border border-[#b7dfc8] space-y-2.5">
                  <div class="flex items-center gap-2">
                    <i class="bi bi-pen-fill text-[#006633]"></i>
                    <span class="text-xs font-bold text-[#004422] uppercase tracking-wider">Digital Signature & In-Person Confirmation *</span>
                  </div>

                  <label class="flex items-start gap-3 cursor-pointer">
                    <input type="checkbox" name="signature_agree" required checked class="mt-1 w-4 h-4 rounded text-[#006633] focus:ring-[#006633] border-[#b7dfc8]">
                    <span class="text-xs text-slate-700 leading-relaxed font-medium">
                      I hereby certify that I am visiting the <strong>Office of Guidance and Counseling Affairs (OGCA)</strong> in person, and that all information entered in this logbook is true, accurate, and submitted voluntarily.
                    </span>
                  </label>
                </div>

                <!-- Submit Button -->
                <div class="col-span-1 md:col-span-2 lg:col-span-4 pt-2">
                  <button type="submit" class="w-full py-3.5 px-8 rounded-xl bg-[#006633] hover:bg-[#004422] text-white font-bold text-base shadow-md shadow-[#006633]/20 transition transform active:scale-[0.99] flex items-center justify-center gap-2">
                    <i class="bi bi-check2-circle text-xl"></i>
                    <span>Confirm & Submit Entry</span>
                  </button>
                </div>

              </div>

            </form>

          </div>

        </div>

      </div>

    <?php endif; ?>

  </main>

  <!-- QSU Footer (Matched to Admission Portal) -->
  <footer class="w-full px-4 sm:px-8 lg:px-12 py-4 bg-white border-t border-slate-200 text-xs text-slate-500 flex flex-col sm:flex-row items-center justify-between gap-2">
    <div class="flex items-center gap-2">
      <img src="assets/images/qsulogo.png" alt="QSU" class="h-5 w-auto">
      <span>Quirino State University (QSU) – Diffun Campus • Office of Student Affairs and Services</span>
    </div>
    <div class="flex items-center gap-3">
      <a href="https://www.facebook.com/profile.php?id=61595216319061" target="_blank" rel="noopener noreferrer" class="text-[#006633] font-semibold hover:underline flex items-center gap-1">
        <i class="bi bi-facebook"></i> QSU OGCA Facebook Page
      </a>
      <span>•</span>
      <span class="text-slate-400">Guidance Office Logbook v1.0</span>
    </div>
  </footer>

  <script>
    // Live Digital Clock
    function updateClock() {
      const now = new Date();
      const timeStr = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
      const clockEl = document.getElementById('live-clock');
      if (clockEl) clockEl.innerText = timeStr;
    }
    setInterval(updateClock, 1000);
    updateClock();

    // Purpose of Visit Manual Input Toggle (when "None" or "Other" is chosen)
    const purposeSelect = document.getElementById('dyn_purpose');
    const purposeManualBox = document.getElementById('purpose_manual_box');
    const purposeCustomInput = document.getElementById('purpose_custom');
    if (purposeSelect && purposeManualBox) {
      function checkPurpose() {
        const val = (purposeSelect.value || '').trim().toLowerCase();
        if (val === 'none' || val === 'other' || val === 'others' || val.includes('none') || val.includes('other')) {
          purposeManualBox.classList.remove('hidden');
          if (purposeCustomInput) {
            purposeCustomInput.required = true;
          }
        } else {
          purposeManualBox.classList.add('hidden');
          if (purposeCustomInput) {
            purposeCustomInput.required = false;
          }
        }
      }
      purposeSelect.addEventListener('change', checkPurpose);
      checkPurpose();
    }

    // Agency / Institution / Office Manual Input Toggle (when "None" or "Other" is chosen)
    const agencySelect = document.getElementById('dyn_agency');
    const agencyManualBox = document.getElementById('agency_manual_box');
    const agencyCustomInput = document.getElementById('agency_custom');
    if (agencySelect && agencyManualBox) {
      function checkAgency() {
        const val = (agencySelect.value || '').trim().toLowerCase();
        if (val === 'none' || val === 'other' || val === 'others' || val.includes('none') || val.includes('other')) {
          agencyManualBox.classList.remove('hidden');
          if (agencyCustomInput) {
            agencyCustomInput.required = true;
          }
        } else {
          agencyManualBox.classList.add('hidden');
          if (agencyCustomInput) {
            agencyCustomInput.required = false;
          }
        }
      }
      agencySelect.addEventListener('change', checkAgency);
      checkAgency();
    }

    // Course & Year / Office Manual Input Toggle (when "None", "Other", or "Office/Faculty" is chosen)
    const courseYearSelect = document.getElementById('dyn_course_year') || document.getElementById('dyn_course_year_dept');
    const courseYearManualBox = document.getElementById('course_year_manual_box');
    const courseYearCustomInput = document.getElementById('course_year_custom');
    if (courseYearSelect && courseYearManualBox) {
      function checkCourseYear() {
        const val = (courseYearSelect.value || '').trim().toLowerCase();
        if (val === 'none' || val === 'other' || val === 'others' || val.includes('none') || val.includes('other') || val.includes('faculty') || val.includes('office')) {
          courseYearManualBox.classList.remove('hidden');
          if (courseYearCustomInput) {
            courseYearCustomInput.required = true;
          }
        } else {
          courseYearManualBox.classList.add('hidden');
          if (courseYearCustomInput) {
            courseYearCustomInput.required = false;
          }
        }
      }
      courseYearSelect.addEventListener('change', checkCourseYear);
      checkCourseYear();
    }

    // Custom Multi-Select Dropdown Handlers
    function toggleMultiSelect(key) {
      const panel = document.getElementById('panel_ms_' + key);
      const icon = document.getElementById('icon_ms_' + key);
      const btn = document.getElementById('btn_ms_' + key);
      if (!panel) return;

      // Close any other open multiselect panels
      document.querySelectorAll('[id^="panel_ms_"]').forEach(p => {
        if (p.id !== 'panel_ms_' + key) {
          p.classList.add('hidden');
          const otherKey = p.id.replace('panel_ms_', '');
          const otherIcon = document.getElementById('icon_ms_' + otherKey);
          const otherBtn = document.getElementById('btn_ms_' + otherKey);
          if (otherIcon) otherIcon.classList.remove('rotate-180');
          if (otherBtn) otherBtn.classList.remove('border-[#006633]', 'ring-2', 'ring-[#006633]/20');
        }
      });

      const isHidden = panel.classList.contains('hidden');
      if (isHidden) {
        panel.classList.remove('hidden');
        if (icon) icon.classList.add('rotate-180');
        if (btn) btn.classList.add('border-[#006633]', 'ring-2', 'ring-[#006633]/20');
      } else {
        panel.classList.add('hidden');
        if (icon) icon.classList.remove('rotate-180');
        if (btn) btn.classList.remove('border-[#006633]', 'ring-2', 'ring-[#006633]/20');
      }
    }

    function closeMultiSelect(key) {
      const panel = document.getElementById('panel_ms_' + key);
      const icon = document.getElementById('icon_ms_' + key);
      const btn = document.getElementById('btn_ms_' + key);
      if (panel) panel.classList.add('hidden');
      if (icon) icon.classList.remove('rotate-180');
      if (btn) btn.classList.remove('border-[#006633]', 'ring-2', 'ring-[#006633]/20');
    }

    // Close open multiselect dropdown when clicking outside
    document.addEventListener('click', function(e) {
      if (!e.target.closest('.multiselect-wrapper')) {
        document.querySelectorAll('[id^="panel_ms_"]').forEach(p => {
          p.classList.add('hidden');
          const k = p.id.replace('panel_ms_', '');
          const ic = document.getElementById('icon_ms_' + k);
          const bt = document.getElementById('btn_ms_' + k);
          if (ic) ic.classList.remove('rotate-180');
          if (bt) bt.classList.remove('border-[#006633]', 'ring-2', 'ring-[#006633]/20');
        });
      }
    });

    // Interactive Schedule Multi-Select Handlers
    function selectAllMS(key, selectAll) {
      document.querySelectorAll('.ms-checkbox-' + key).forEach(cb => {
        cb.checked = selectAll;
      });
      updateMultiSelectState(key);
    }

    function toggleDaySlots(key, day) {
      const dayCheckboxes = document.querySelectorAll('.ms-day-' + day + '-' + key);
      const allChecked = Array.from(dayCheckboxes).length > 0 && Array.from(dayCheckboxes).every(cb => cb.checked);
      dayCheckboxes.forEach(cb => {
        cb.checked = !allChecked;
      });
      updateMultiSelectState(key);
    }

    function updateMultiSelectState(key) {
      const checkboxes = document.querySelectorAll('.ms-checkbox-' + key + ':checked');
      const reqEl = document.getElementById('req_ms_' + key);
      const badgeCounter = document.getElementById('badge_counter_' + key);
      const btnBadge = document.getElementById('btn_badge_ms_' + key);
      const labelEl = document.getElementById('label_ms_' + key);
      const summaryBox = document.getElementById('summary_box_' + key);
      const selectedPills = document.getElementById('selected_pills_' + key);

      const count = checkboxes.length;
      const countText = count + ' Slot' + (count === 1 ? '' : 's') + ' Selected';

      if (badgeCounter) {
        badgeCounter.innerText = countText;
      }

      // Update card styling (turns green when checked)
      document.querySelectorAll('.ms-slot-card-' + key).forEach(card => {
        const cb = card.querySelector('input[type="checkbox"]');
        if (cb && cb.checked) {
          card.classList.remove('bg-white', 'border-slate-200');
          card.classList.add('bg-[#e6f4ea]', 'border-[#006633]', 'shadow-xs');
        } else {
          card.classList.remove('bg-[#e6f4ea]', 'border-[#006633]', 'shadow-xs');
          if (!card.classList.contains('bg-amber-50/50')) {
            card.classList.add('bg-white', 'border-slate-200');
          }
        }
      });

      if (count === 0) {
        if (reqEl) reqEl.value = '';
        if (btnBadge) btnBadge.classList.add('hidden');
        if (labelEl) {
          labelEl.innerText = '— Select Vacant Weekday & Time (Click to open schedule) —';
          labelEl.className = 'truncate text-slate-500 font-normal';
        }
        if (summaryBox) summaryBox.classList.add('hidden');
        if (selectedPills) selectedPills.innerHTML = '';
      } else {
        const selectedValues = Array.from(checkboxes).map(cb => cb.value);
        if (reqEl) reqEl.value = selectedValues.join(', ');

        if (btnBadge) {
          btnBadge.innerText = count + ' Selected';
          btnBadge.classList.remove('hidden');
        }

        if (labelEl) {
          const previewText = selectedValues.slice(0, 2).join(', ') + (count > 2 ? ' + ' + (count - 2) + ' more' : '');
          labelEl.innerHTML = `<span class="text-[#006633] font-bold">${countText}:</span> <span class="text-slate-700 font-medium">${previewText}</span>`;
          labelEl.className = 'truncate text-sm';
        }

        if (summaryBox) summaryBox.classList.remove('hidden');
        if (selectedPills) {
          selectedPills.innerHTML = selectedValues.map(v => `
            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-bold bg-[#006633] text-white shadow-xs">
              <i class="bi bi-check-circle-fill text-[10px]"></i>
              ${v}
            </span>
          `).join('');
        }
      }
    }
  </script>
</body>
</html>

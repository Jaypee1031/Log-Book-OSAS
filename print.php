<?php
/**
 * Paper Logbook Print-Friendly View (Exact replica of Logbook.docx)
 * Guidance Office Logbook Management System (OSAS)
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/helpers.php';

require_login();

$type_slug = $_GET['type'] ?? 'clients-log';
$logbook_type = get_logbook_type_by_slug($pdo, $type_slug);

if (!$logbook_type) {
    die('Invalid logbook type specified.');
}

$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

$where = ["lr.logbook_type_id = ?", "lr.status = 'active'"];
$params = [$logbook_type['id']];

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

$sql = "SELECT lr.* FROM `logbook_records` lr WHERE " . implode(' AND ', $where) . " ORDER BY lr.record_no ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$records = $stmt->fetchAll();

$system_settings = get_system_settings($pdo);
$fields = get_logbook_fields($pdo, $logbook_type['id']);

$current_user = current_user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Print <?= e($logbook_type['title']) ?> | <?= e($system_settings['office_unit'] ?? 'Guidance Center') ?></title>
  
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
            }
          }
        }
      }
    }
  </script>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="assets/css/style.css">

  <style>
    @media print {
      body {
        margin: 0;
        padding: 0;
        background: #fff;
        font-family: Arial, Helvetica, sans-serif;
      }
      .no-print {
        display: none !important;
      }
      .page-container {
        padding: 0;
        margin: 0;
        max-width: 100% !important;
      }
      table {
        width: 100% !important;
        border-collapse: collapse !important;
      }
      th, td {
        border: 1px solid #000 !important;
        padding: 4px 6px !important;
        font-size: 8.5pt !important;
      }
      th {
        background-color: #f1f5f9 !important;
        font-weight: bold !important;
        text-align: center !important;
      }
    }

    /* Screen preview styling */
    .paper-sheet-table {
      width: 100%;
      border-collapse: collapse;
    }
    .paper-sheet-table th, .paper-sheet-table td {
      border: 1.5px solid #1e293b;
      padding: 6px 8px;
      font-size: 11px;
    }
    .paper-sheet-table th {
      background-color: #f8fafc;
      font-weight: 700;
      text-align: center;
      vertical-align: middle;
    }
  </style>
</head>
<body class="bg-slate-100 p-4 sm:p-8 text-slate-900">

  <!-- Floating Print Controls on Screen -->
  <div class="no-print max-w-6xl mx-auto mb-6 bg-white p-4 rounded-2xl border border-slate-300 shadow-sm flex flex-wrap items-center justify-between gap-4">
    <div class="flex items-center gap-3">
      <a href="logbook.php?type=<?= urlencode($type_slug) ?>" class="px-3.5 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold text-xs flex items-center gap-1.5 transition">
        <i class="bi bi-arrow-left"></i> Back to Logbook
      </a>
      <span class="text-xs text-slate-500">
        Replicating official paper log sheet layout from <strong>Logbook.docx</strong>.
      </span>
    </div>

    <div class="flex items-center gap-2">
      <button onclick="window.print()" class="px-5 py-2 rounded-xl bg-[#006633] hover:bg-[#004422] text-white font-bold text-xs flex items-center gap-2 shadow-sm transition">
        <i class="bi bi-printer-fill"></i> Print / Save as PDF
      </button>
    </div>
  </div>

  <!-- Printable Paper Sheet Container -->
  <div class="page-container max-w-6xl mx-auto bg-white p-8 sm:p-12 rounded-xl shadow-lg border border-slate-300 print:shadow-none print:border-none print:p-0">
    
    <!-- Official OSAS Guidance Header -->
    <?php require __DIR__ . '/includes/print_header.php'; ?>

    <!-- Title of Logbook -->
    <div class="text-center my-4">
      <h2 class="text-base sm:text-lg font-extrabold uppercase tracking-widest text-slate-900 underline decoration-2 underline-offset-4">
        <?php if ($type_slug === 'borrowers-log'): ?>
          BORROWER’S LOG SHEET
        <?php elseif ($type_slug === 'good-moral-log'): ?>
          CERTIFICATE OF GOOD MORAL CHARACTER REGISTRY
        <?php elseif ($type_slug === 'clients-log'): ?>
          CLIENT’S LOG SHEET
        <?php elseif ($type_slug === 'visitors-log'): ?>
          VISITOR’S LOG SHEET
        <?php else: ?>
          <?= strtoupper(e($logbook_type['title'])) ?> SHEET
        <?php endif; ?>
      </h2>
      <div class="text-[11px] text-slate-600 mt-1">
        <?php if (!empty($date_from) && !empty($date_to)): ?>
          Period: <?= format_date($date_from) ?> to <?= format_date($date_to) ?>
        <?php else: ?>
          Current Academic Year Registry
        <?php endif; ?>
      </div>
    </div>

    <!-- 1. BORROWER'S LOG SHEET TABLE -->
    <?php if ($type_slug === 'borrowers-log'): ?>
      <table class="paper-sheet-table mt-4">
        <thead>
          <tr>
            <th rowspan="2" class="w-10">No.</th>
            <th rowspan="2" class="w-24">Date</th>
            <th rowspan="2">Name</th>
            <th colspan="4" class="text-center">Client</th>
            <th rowspan="2">Course & Year / Department</th>
            <th rowspan="2">Purpose / Item</th>
            <th rowspan="2" class="w-24">Date of Return</th>
            <th rowspan="2">Remarks</th>
          </tr>
          <tr>
            <th class="w-14">Student</th>
            <th class="w-14">Visitor</th>
            <th class="w-14">Alumni</th>
            <th class="w-14">Faculty</th>
          </tr>
        </thead>
        <tbody>
          <?php 
          $row_num = 1;
          foreach ($records as $r): 
            $data = json_decode($r['data_json'] ?? '{}', true) ?: [];
            $ctype = $data['client_type'] ?? '';
          ?>
            <tr>
              <td class="text-center font-bold"><?= (int)$r['record_no'] ?></td>
              <td class="text-center whitespace-nowrap"><?= format_date($r['entry_date']) ?></td>
              <td class="font-semibold"><?= e($r['client_name']) ?></td>
              <td class="text-center font-bold text-sm"><?= $ctype === 'Student' ? '✓' : '' ?></td>
              <td class="text-center font-bold text-sm"><?= $ctype === 'Visitor' ? '✓' : '' ?></td>
              <td class="text-center font-bold text-sm"><?= $ctype === 'Alumni' ? '✓' : '' ?></td>
              <td class="text-center font-bold text-sm"><?= $ctype === 'Faculty' ? '✓' : '' ?></td>
              <td><?= e($data['course_year_dept'] ?? '') ?></td>
              <td><?= e($data['purpose'] ?? '') ?></td>
              <td class="text-center whitespace-nowrap"><?= format_date($data['date_of_return'] ?? '') ?></td>
              <td><?= e($data['remarks'] ?? '') ?></td>
            </tr>
          <?php 
            $row_num++;
          endforeach; 
          
          // Fill blank lines up to 15 if fewer records
          while ($row_num <= 15):
          ?>
            <tr class="h-8">
              <td class="text-center text-slate-400"><?= $row_num ?></td>
              <td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td>
            </tr>
          <?php 
            $row_num++;
          endwhile; 
          ?>
        </tbody>
      </table>

    <!-- 2. CERTIFICATE OF GOOD MORAL CHARACTER TABLE -->
    <?php elseif ($type_slug === 'good-moral-log'): ?>
      <table class="paper-sheet-table mt-4">
        <thead>
          <tr>
            <th rowspan="2" class="w-10">No.</th>
            <th rowspan="2" class="w-24">Date</th>
            <th rowspan="2" class="w-28">Year Graduated</th>
            <th rowspan="2">Name</th>
            <th colspan="5" class="text-center">PURPOSE</th>
            <th rowspan="2" class="w-24">Signature</th>
            <th rowspan="2" class="w-24">Date Issued</th>
          </tr>
          <tr>
            <th class="w-24">PRC/Gov’t Exam</th>
            <th class="w-20">Scholarship</th>
            <th class="w-20">Transfer</th>
            <th class="w-20">Employment</th>
            <th class="w-16">Others</th>
          </tr>
        </thead>
        <tbody>
          <?php 
          $row_num = 1;
          foreach ($records as $r): 
            $data = json_decode($r['data_json'] ?? '{}', true) ?: [];
            $purp = $data['purpose'] ?? '';
          ?>
            <tr>
              <td class="text-center font-bold"><?= (int)$r['record_no'] ?></td>
              <td class="text-center whitespace-nowrap"><?= format_date($r['entry_date']) ?></td>
              <td class="text-center"><?= e($data['year_graduated'] ?? '') ?></td>
              <td class="font-semibold"><?= e($r['client_name']) ?></td>
              <td class="text-center font-bold text-sm"><?= strpos($purp, 'PRC') !== false ? '✓' : '' ?></td>
              <td class="text-center font-bold text-sm"><?= strpos($purp, 'Scholarship') !== false ? '✓' : '' ?></td>
              <td class="text-center font-bold text-sm"><?= strpos($purp, 'Transfer') !== false ? '✓' : '' ?></td>
              <td class="text-center font-bold text-sm"><?= strpos($purp, 'Employment') !== false ? '✓' : '' ?></td>
              <td class="text-center font-bold text-sm"><?= (strpos($purp, 'Other') !== false || !in_array($purp, ['PRC/Government Exam', 'Scholarship', 'Transfer', 'Employment'])) ? '✓' : '' ?></td>
              <td class="text-center text-xs italic text-slate-700">Signed</td>
              <td class="text-center whitespace-nowrap font-medium"><?= format_date($data['date_issued'] ?? '') ?></td>
            </tr>
          <?php 
            $row_num++;
          endforeach; 

          while ($row_num <= 15):
          ?>
            <tr class="h-8">
              <td class="text-center text-slate-400"><?= $row_num ?></td>
              <td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td>
            </tr>
          <?php 
            $row_num++;
          endwhile; 
          ?>
        </tbody>
      </table>

    <!-- 3. CLIENT'S LOG SHEET TABLE -->
    <?php elseif ($type_slug === 'clients-log'): ?>
      <table class="paper-sheet-table mt-4">
        <thead>
          <tr>
            <th rowspan="2" class="w-10">No.</th>
            <th rowspan="2" class="w-24">Date</th>
            <th rowspan="2">Name</th>
            <th colspan="4" class="text-center">Client</th>
            <th rowspan="2">Course & Year</th>
            <th rowspan="2">Purpose</th>
            <th rowspan="2" class="w-20">Time-in</th>
            <th rowspan="2" class="w-20">Time-out</th>
            <th rowspan="2" class="w-24">Signature</th>
          </tr>
          <tr>
            <th class="w-14">Student</th>
            <th class="w-14">Visitor</th>
            <th class="w-14">Alumni</th>
            <th class="w-14">Faculty</th>
          </tr>
        </thead>
        <tbody>
          <?php 
          $row_num = 1;
          foreach ($records as $r): 
            $data = json_decode($r['data_json'] ?? '{}', true) ?: [];
            $ctype = $data['client_type'] ?? '';
          ?>
            <tr>
              <td class="text-center font-bold"><?= (int)$r['record_no'] ?></td>
              <td class="text-center whitespace-nowrap"><?= format_date($r['entry_date']) ?></td>
              <td class="font-semibold"><?= e($r['client_name']) ?></td>
              <td class="text-center font-bold text-sm"><?= $ctype === 'Student' ? '✓' : '' ?></td>
              <td class="text-center font-bold text-sm"><?= $ctype === 'Visitor' ? '✓' : '' ?></td>
              <td class="text-center font-bold text-sm"><?= $ctype === 'Alumni' ? '✓' : '' ?></td>
              <td class="text-center font-bold text-sm"><?= $ctype === 'Faculty' ? '✓' : '' ?></td>
              <td><?= e($data['course_year'] ?? '') ?></td>
              <td><?= e($data['purpose'] ?? '') ?></td>
              <td class="text-center font-mono"><?= format_time($data['time_in'] ?? '') ?></td>
              <td class="text-center font-mono"><?= format_time($data['time_out'] ?? '') ?></td>
              <td class="text-center text-xs italic text-slate-700">Signed</td>
            </tr>
          <?php 
            $row_num++;
          endforeach; 

          while ($row_num <= 15):
          ?>
            <tr class="h-8">
              <td class="text-center text-slate-400"><?= $row_num ?></td>
              <td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td>
            </tr>
          <?php 
            $row_num++;
          endwhile; 
          ?>
        </tbody>
      </table>

    <!-- 4. VISITOR'S LOG SHEET TABLE -->
    <?php elseif ($type_slug === 'visitors-log'): ?>
      <table class="paper-sheet-table mt-4">
        <thead>
          <tr>
            <th class="w-10">No.</th>
            <th class="w-24">Date</th>
            <th>Name</th>
            <th>Agency</th>
            <th>Purpose</th>
            <th class="w-24">Time-in</th>
            <th class="w-24">Time-out</th>
            <th class="w-24">Signature</th>
          </tr>
        </thead>
        <tbody>
          <?php 
          $row_num = 1;
          foreach ($records as $r): 
            $data = json_decode($r['data_json'] ?? '{}', true) ?: [];
          ?>
            <tr>
              <td class="text-center font-bold"><?= (int)$r['record_no'] ?></td>
              <td class="text-center whitespace-nowrap"><?= format_date($r['entry_date']) ?></td>
              <td class="font-semibold"><?= e($r['client_name']) ?></td>
              <td><?= e($data['agency'] ?? '') ?></td>
              <td><?= e($data['purpose'] ?? '') ?></td>
              <td class="text-center font-mono"><?= format_time($data['time_in'] ?? '') ?></td>
              <td class="text-center font-mono"><?= format_time($data['time_out'] ?? '') ?></td>
              <td class="text-center text-xs italic text-slate-700">Signed</td>
            </tr>
          <?php 
            $row_num++;
          endforeach; 

          while ($row_num <= 15):
          ?>
            <tr class="h-8">
              <td class="text-center text-slate-400"><?= $row_num ?></td>
              <td></td><td></td><td></td><td></td><td></td><td></td><td></td>
            </tr>
          <?php 
            $row_num++;
          endwhile; 
          ?>
        </tbody>
      </table>

    <!-- 5. DYNAMIC CUSTOM LOGBOOK TABLE -->
    <?php else: ?>
      <table class="paper-sheet-table mt-4">
        <thead>
          <tr>
            <th class="w-12">No.</th>
            <th class="w-24">Date</th>
            <th>Name</th>
            <?php foreach ($fields as $f): 
              if (in_array($f['field_key'], ['name', 'date'])) continue;
              if (!$f['show_in_table']) continue;
            ?>
              <th><?= e($f['field_label']) ?></th>
            <?php endforeach; ?>
          </tr>
        </thead>
        <tbody>
          <?php 
          $row_num = 1;
          foreach ($records as $r): 
            $data = json_decode($r['data_json'] ?? '{}', true) ?: [];
          ?>
            <tr>
              <td class="text-center font-bold"><?= (int)$r['record_no'] ?></td>
              <td class="text-center whitespace-nowrap"><?= format_date($r['entry_date']) ?></td>
              <td class="font-semibold"><?= e($r['client_name']) ?></td>
              <?php foreach ($fields as $f): 
                if (in_array($f['field_key'], ['name', 'date'])) continue;
                if (!$f['show_in_table']) continue;
                $v = $data[$f['field_key']] ?? '';
              ?>
                <td>
                  <?php if ($f['field_type'] === 'date'): ?>
                    <?= format_date($v) ?>
                  <?php elseif ($f['field_type'] === 'time'): ?>
                    <?= format_time($v) ?>
                  <?php elseif ($f['field_type'] === 'signature_confirm'): ?>
                    <span class="text-center block text-xs">Confirmed</span>
                  <?php else: ?>
                    <?= e($v) ?>
                  <?php endif; ?>
                </td>
              <?php endforeach; ?>
            </tr>
          <?php 
            $row_num++;
          endforeach; 

          while ($row_num <= 12):
          ?>
            <tr class="h-8">
              <td class="text-center text-slate-400"><?= $row_num ?></td>
              <td></td><td></td>
              <?php foreach ($fields as $f): 
                if (in_array($f['field_key'], ['name', 'date'])) continue;
                if (!$f['show_in_table']) continue;
              ?>
                <td></td>
              <?php endforeach; ?>
            </tr>
          <?php 
            $row_num++;
          endwhile; 
          ?>
        </tbody>
      </table>
    <?php endif; ?>

    <!-- Official Signatures Block -->
    <div class="print-signatures mt-12 pt-6 grid grid-cols-2 gap-8 text-xs">
      <div>
        <p class="text-slate-600 mb-12">Prepared and certified correct by:</p>
        <div class="border-b border-black w-64 pb-1">
          <p class="font-bold text-slate-900 uppercase"><?= e($current_user['full_name']) ?></p>
        </div>
        <p class="text-slate-600 mt-1"><?= ucfirst(e($current_user['role'])) ?> / Guidance Records Custodian</p>
      </div>

      <div class="text-right">
        <p class="text-slate-600 mb-12">Noted and attested by:</p>
        <div class="border-b border-black w-64 ml-auto pb-1">
          <p class="font-bold text-slate-900 uppercase"><?= e($system_settings['guidance_head'] ?? 'Maria Elena Santos, RGC') ?></p>
        </div>
        <p class="text-slate-600 mt-1"><?= e($system_settings['guidance_head_title'] ?? 'Head, Guidance & Counseling Center') ?></p>
      </div>
    </div>

  </div>

</body>
</html>

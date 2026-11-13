<?php
/**
 * Data Export Generator (CSV & Excel .xls)
 * Guidance Office Logbook Management System (OSAS)
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/helpers.php';

require_login();

$format = strtolower($_GET['format'] ?? 'csv');
$type_slug = $_GET['type'] ?? 'clients-log';
$tab = $_GET['tab'] ?? 'active';

$logbook_type = get_logbook_type_by_slug($pdo, $type_slug);
if (!$logbook_type) {
    die("Invalid logbook type.");
}

$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$search = trim($_GET['search'] ?? '');

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

if (!empty($search)) {
    $where[] = "(lr.client_name LIKE ? OR lr.purpose LIKE ? OR lr.notes LIKE ? OR lr.data_json LIKE ?)";
    $like = "%{$search}%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$sql = "SELECT lr.* FROM `logbook_records` lr WHERE " . implode(' AND ', $where) . " ORDER BY lr.record_no ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$records = $stmt->fetchAll();

$fields = get_logbook_fields($pdo, $logbook_type['id']);
$filename_base = preg_replace('/[^a-zA-Z0-9_-]/', '_', $logbook_type['title']) . '_' . date('Ymd_His');

// Prepare column headers and keys
$headers = ['No.', 'Date', 'Name'];
$field_keys = [];

foreach ($fields as $f) {
    if (in_array($f['field_key'], ['name', 'date'])) continue;
    $headers[] = $f['field_label'];
    $field_keys[] = $f['field_key'];
}
$headers[] = 'Status';
$headers[] = 'Staff Notes';

// 1. CSV EXPORT
if ($format === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header("Content-Disposition: attachment; filename=\"{$filename_base}.csv\"");
    
    // Output BOM for Excel UTF-8 recognition
    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');

    fputcsv($out, $headers);

    foreach ($records as $r) {
        $data = json_decode($r['data_json'] ?? '{}', true) ?: [];
        $row = [
            $r['record_no'],
            $r['entry_date'],
            $r['client_name']
        ];
        foreach ($field_keys as $key) {
            $row[] = $data[$key] ?? '';
        }
        $row[] = ucfirst($r['status']);
        $row[] = $r['notes'] ?? '';

        fputcsv($out, $row);
    }
    fclose($out);
    exit;
}

// 2. EXCEL (XLS HTML) EXPORT
if ($format === 'excel' || $format === 'xls') {
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header("Content-Disposition: attachment; filename=\"{$filename_base}.xls\"");
    
    echo "<html xmlns:x=\"urn:schemas-microsoft-com:office:excel\">
    <head>
      <!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet>
      <x:Name>" . htmlspecialchars(substr($logbook_type['title'], 0, 30)) . "</x:Name>
      <x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions>
      </x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]-->
      <meta http-equiv=\"content-type\" content=\"text/plain; charset=UTF-8\"/>
      <style>
        th { background-color: #14532d; color: #ffffff; font-weight: bold; padding: 6px; border: 1px solid #cbd5e1; }
        td { padding: 5px; border: 1px solid #cbd5e1; }
      </style>
    </head>
    <body>
      <h2>" . htmlspecialchars($logbook_type['title']) . "</h2>
      <p>Export Date: " . date('Y-m-d H:i') . "</p>
      <table>
        <thead>
          <tr>";
          foreach ($headers as $h) {
              echo "<th>" . htmlspecialchars($h) . "</th>";
          }
    echo "</tr>
        </thead>
        <tbody>";
        foreach ($records as $r) {
            $data = json_decode($r['data_json'] ?? '{}', true) ?: [];
            echo "<tr>";
            echo "<td>" . (int)$r['record_no'] . "</td>";
            echo "<td>" . htmlspecialchars($r['entry_date']) . "</td>";
            echo "<td>" . htmlspecialchars($r['client_name']) . "</td>";
            foreach ($field_keys as $key) {
                echo "<td>" . htmlspecialchars($data[$key] ?? '') . "</td>";
            }
            echo "<td>" . htmlspecialchars(ucfirst($r['status'])) . "</td>";
            echo "<td>" . htmlspecialchars($r['notes'] ?? '') . "</td>";
            echo "</tr>";
        }
    echo "</tbody>
      </table>
    </body>
    </html>";
    exit;
}

header("Location: logbook.php?type=" . urlencode($type_slug));
exit;

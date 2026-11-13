<?php
/**
 * Logbook Record Actions Handler (Create, Update, Archive, Restore, Delete)
 * Guidance Office Logbook Management System (OSAS)
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/helpers.php';

require_login();

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$user = current_user();

// 1. CREATE RECORD
if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf($token)) {
        set_flash('danger', 'Security token expired. Please try again.');
        header('Location: dashboard.php');
        exit;
    }

    $logbook_type_id = (int)($_POST['logbook_type_id'] ?? 0);
    $type_slug = $_POST['type_slug'] ?? 'clients-log';
    $entry_date = trim($_POST['entry_date'] ?? date('Y-m-d'));
    $record_no = (int)($_POST['record_no'] ?? 0);
    $notes = trim($_POST['notes'] ?? '');
    $fields = $_POST['fields'] ?? [];

    // Normalize any array fields (e.g. multiselect checkboxes) to comma-separated string
    foreach ($fields as $k => $v) {
        if (is_array($v)) {
            $fields[$k] = implode(', ', array_filter(array_map('trim', $v)));
        }
    }

    // If "None" or "Other" agency specified with manual input
    $agency = trim($fields['agency'] ?? '');
    if ((strcasecmp($agency, 'None') === 0 || strcasecmp($agency, 'Other') === 0 || strcasecmp($agency, 'Others') === 0) && !empty($_POST['agency_custom'])) {
        $fields['agency'] = trim($_POST['agency_custom']);
    }

    // If "None" or "Other" purpose specified with manual input
    $purpose = trim($fields['purpose'] ?? $fields['item_borrowed'] ?? $fields['referral_type'] ?? '');
    $purpose_custom = trim($_POST['purpose_custom'] ?? $_POST['purpose_other_custom'] ?? '');
    if ((strcasecmp($purpose, 'None') === 0 || strcasecmp($purpose, 'Other') === 0 || strcasecmp($purpose, 'Others') === 0) && !empty($purpose_custom)) {
        $fields['purpose'] = $purpose_custom;
    }

    // If "None", "Other", or "Office/Faculty" course_year specified with manual input
    $course_year_key = isset($fields['course_year']) ? 'course_year' : (isset($fields['course_year_dept']) ? 'course_year_dept' : null);
    if ($course_year_key) {
        $cy_val = trim($fields[$course_year_key] ?? '');
        if ((strcasecmp($cy_val, 'None') === 0 || strcasecmp($cy_val, 'Other') === 0 || strcasecmp($cy_val, 'Others') === 0 || stripos($cy_val, 'Office') !== false || stripos($cy_val, 'Faculty') !== false) && !empty($_POST['course_year_custom'])) {
            $fields[$course_year_key] = trim($_POST['course_year_custom']);
        }
    }

    if ($record_no <= 0) {
        $record_no = get_next_record_no($pdo, $logbook_type_id);
    }

    // Determine primary client_name, client_type, purpose for fast indexing
    $client_name = trim($fields['name'] ?? $fields['client_name'] ?? 'Anonymous / Guest');
    $client_type = trim($fields['client_type'] ?? 'General');
    $purpose = trim($fields['purpose'] ?? $fields['item_borrowed'] ?? $fields['referral_type'] ?? '');

    // Ensure date is also kept in dynamic fields json
    $fields['date'] = $entry_date;
    $fields['name'] = $client_name;
    $data_json = json_encode($fields, JSON_UNESCAPED_UNICODE);

    try {
        $stmt = $pdo->prepare("INSERT INTO `logbook_records` 
            (`logbook_type_id`, `record_no`, `entry_date`, `client_name`, `client_type`, `purpose`, `status`, `notes`, `data_json`, `created_by`, `created_at`) 
            VALUES (?, ?, ?, ?, ?, ?, 'active', ?, ?, ?, NOW())");
        $stmt->execute([
            $logbook_type_id, $record_no, $entry_date, $client_name, $client_type, $purpose, $notes, $data_json, $user['id']
        ]);
        $record_id = $pdo->lastInsertId();

        log_audit($pdo, 'CREATE_RECORD', $logbook_type_id, (int)$record_id, "Added entry #{$record_no} for '{$client_name}'");
        set_flash('success', "Record #{$record_no} successfully saved into logbook.");
    } catch (Exception $e) {
        set_flash('danger', "Error saving record: " . $e->getMessage());
    }

    header("Location: logbook.php?type=" . urlencode($type_slug) . "&highlight={$record_id}");
    exit;
}

// 2. UPDATE RECORD
if ($action === 'update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf($token)) {
        set_flash('danger', 'Security token expired. Please try again.');
        header('Location: dashboard.php');
        exit;
    }

    $record_id = (int)($_POST['record_id'] ?? 0);
    $type_slug = $_POST['type_slug'] ?? 'clients-log';
    $entry_date = trim($_POST['entry_date'] ?? date('Y-m-d'));
    $record_no_input = (int)($_POST['record_no'] ?? 0);
    $notes = trim($_POST['notes'] ?? '');
    $fields = $_POST['fields'] ?? [];

    // Normalize any array fields (e.g. multiselect checkboxes) to comma-separated string
    foreach ($fields as $k => $v) {
        if (is_array($v)) {
            $fields[$k] = implode(', ', array_filter(array_map('trim', $v)));
        }
    }

    // If "None" or "Other" agency specified with manual input
    $agency = trim($fields['agency'] ?? '');
    if ((strcasecmp($agency, 'None') === 0 || strcasecmp($agency, 'Other') === 0 || strcasecmp($agency, 'Others') === 0) && !empty($_POST['agency_custom'])) {
        $fields['agency'] = trim($_POST['agency_custom']);
    }

    // If "None" or "Other" purpose specified with manual input
    $purpose = trim($fields['purpose'] ?? $fields['item_borrowed'] ?? $fields['referral_type'] ?? '');
    $purpose_custom = trim($_POST['purpose_custom'] ?? $_POST['purpose_other_custom'] ?? '');
    if ((strcasecmp($purpose, 'None') === 0 || strcasecmp($purpose, 'Other') === 0 || strcasecmp($purpose, 'Others') === 0) && !empty($purpose_custom)) {
        $fields['purpose'] = $purpose_custom;
    }

    // If "None", "Other", or "Office/Faculty" course_year specified with manual input
    $course_year_key = isset($fields['course_year']) ? 'course_year' : (isset($fields['course_year_dept']) ? 'course_year_dept' : null);
    if ($course_year_key) {
        $cy_val = trim($fields[$course_year_key] ?? '');
        if ((strcasecmp($cy_val, 'None') === 0 || strcasecmp($cy_val, 'Other') === 0 || strcasecmp($cy_val, 'Others') === 0 || stripos($cy_val, 'Office') !== false || stripos($cy_val, 'Faculty') !== false) && !empty($_POST['course_year_custom'])) {
            $fields[$course_year_key] = trim($_POST['course_year_custom']);
        }
    }

    // Verify existing record
    $stmt = $pdo->prepare("SELECT * FROM `logbook_records` WHERE `id` = ?");
    $stmt->execute([$record_id]);
    $existing = $stmt->fetch();

    if (!$existing) {
        set_flash('danger', 'Record not found.');
        header("Location: logbook.php?type=" . urlencode($type_slug));
        exit;
    }

    // Only administrator can change the record_no
    $record_no = is_admin() && $record_no_input > 0 ? $record_no_input : (int)$existing['record_no'];

    $client_name = trim($fields['name'] ?? $fields['client_name'] ?? $existing['client_name']);
    $client_type = trim($fields['client_type'] ?? $existing['client_type']);
    $purpose = trim($fields['purpose'] ?? $fields['item_borrowed'] ?? $existing['purpose']);

    $fields['date'] = $entry_date;
    $fields['name'] = $client_name;
    $data_json = json_encode($fields, JSON_UNESCAPED_UNICODE);

    try {
        $update_stmt = $pdo->prepare("UPDATE `logbook_records` SET 
            `record_no` = ?,
            `entry_date` = ?,
            `client_name` = ?,
            `client_type` = ?,
            `purpose` = ?,
            `notes` = ?,
            `data_json` = ?,
            `updated_by` = ?,
            `updated_at` = NOW()
            WHERE `id` = ?");
        $update_stmt->execute([
            $record_no, $entry_date, $client_name, $client_type, $purpose, $notes, $data_json, $user['id'], $record_id
        ]);

        log_audit($pdo, 'UPDATE_RECORD', (int)$existing['logbook_type_id'], $record_id, "Updated entry #{$record_no} for '{$client_name}'");
        set_flash('success', "Record #{$record_no} has been updated.");
    } catch (Exception $e) {
        set_flash('danger', "Error updating record: " . $e->getMessage());
    }

    header("Location: logbook.php?type=" . urlencode($type_slug) . "&highlight={$record_id}");
    exit;
}

// 3. ARCHIVE RECORD (Soft delete)
if ($action === 'archive') {
    $token = $_GET['token'] ?? '';
    if (!verify_csrf($token)) {
        set_flash('danger', 'Invalid security token.');
        header('Location: dashboard.php');
        exit;
    }

    $id = (int)($_GET['id'] ?? 0);
    $stmt = $pdo->prepare("SELECT lr.*, lt.slug FROM `logbook_records` lr JOIN `logbook_types` lt ON lt.id = lr.logbook_type_id WHERE lr.id = ?");
    $stmt->execute([$id]);
    $rec = $stmt->fetch();

    if ($rec) {
        $pdo->prepare("UPDATE `logbook_records` SET `status` = 'archived', `updated_by` = ?, `updated_at` = NOW() WHERE `id` = ?")
            ->execute([$user['id'], $id]);
        log_audit($pdo, 'ARCHIVE_RECORD', (int)$rec['logbook_type_id'], $id, "Archived record #{$rec['record_no']} ({$rec['client_name']})");
        set_flash('warning', "Record #{$rec['record_no']} has been moved to Archives.");
        header("Location: logbook.php?type=" . urlencode($rec['slug']));
        exit;
    }
}

// 4. RESTORE RECORD
if ($action === 'restore') {
    $token = $_GET['token'] ?? '';
    if (!verify_csrf($token)) {
        set_flash('danger', 'Invalid security token.');
        header('Location: dashboard.php');
        exit;
    }

    $id = (int)($_GET['id'] ?? 0);
    $stmt = $pdo->prepare("SELECT lr.*, lt.slug FROM `logbook_records` lr JOIN `logbook_types` lt ON lt.id = lr.logbook_type_id WHERE lr.id = ?");
    $stmt->execute([$id]);
    $rec = $stmt->fetch();

    if ($rec) {
        $pdo->prepare("UPDATE `logbook_records` SET `status` = 'active', `updated_by` = ?, `updated_at` = NOW() WHERE `id` = ?")
            ->execute([$user['id'], $id]);
        log_audit($pdo, 'RESTORE_RECORD', (int)$rec['logbook_type_id'], $id, "Restored record #{$rec['record_no']} ({$rec['client_name']})");
        set_flash('success', "Record #{$rec['record_no']} restored to active logbook.");
        header("Location: logbook.php?type=" . urlencode($rec['slug']) . "&tab=active&highlight={$id}");
        exit;
    }
}

// 5. HARD DELETE (Admin Only)
if ($action === 'delete') {
    require_admin();

    $token = $_GET['token'] ?? '';
    if (!verify_csrf($token)) {
        set_flash('danger', 'Invalid security token.');
        header('Location: dashboard.php');
        exit;
    }

    $id = (int)($_GET['id'] ?? 0);
    $stmt = $pdo->prepare("SELECT lr.*, lt.slug FROM `logbook_records` lr JOIN `logbook_types` lt ON lt.id = lr.logbook_type_id WHERE lr.id = ?");
    $stmt->execute([$id]);
    $rec = $stmt->fetch();

    if ($rec) {
        $pdo->prepare("DELETE FROM `logbook_records` WHERE `id` = ?")->execute([$id]);
        log_audit($pdo, 'DELETE_RECORD', (int)$rec['logbook_type_id'], $id, "Permanently deleted record #{$rec['record_no']} ({$rec['client_name']})");
        set_flash('info', "Record #{$rec['record_no']} was permanently deleted.");
        header("Location: logbook.php?type=" . urlencode($rec['slug']));
        exit;
    }
}

header('Location: dashboard.php');
exit;

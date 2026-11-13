<?php
/**
 * Global Helper Functions & Audit Logging
 * Guidance Office Logbook Management System (OSAS)
 */

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/auth.php';

/**
 * HTML Escaping helper
 */
function e(?string $string): string {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Format date for friendly human reading
 */
function format_date(?string $date): string {
    if (empty($date) || $date === '0000-00-00') {
        return '—';
    }
    $timestamp = strtotime($date);
    return $timestamp ? date('M d, Y', $timestamp) : $date;
}

/**
 * Format time for display (e.g. 09:30 AM)
 */
function format_time(?string $time): string {
    if (empty($time) || $time === '00:00:00') {
        return '—';
    }
    $timestamp = strtotime($time);
    return $timestamp ? date('h:i A', $timestamp) : $time;
}

/**
 * Record an action to the audit logs
 */
function log_audit(PDO $pdo, string $action, ?int $logbook_type_id = null, ?int $record_id = null, ?string $details = null): void {
    try {
        $user_id = current_user()['id'] ?? null;
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        $stmt = $pdo->prepare("INSERT INTO `audit_logs` (`user_id`, `action`, `logbook_type_id`, `record_id`, `details`, `ip_address`, `created_at`) VALUES (?, ?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$user_id, $action, $logbook_type_id, $record_id, $details, $ip_address]);
    } catch (Exception $e) {
        // Do not crash the app if audit logging fails
        error_log("Audit log failed: " . $e->getMessage());
    }
}

/**
 * Compute the next sequential record number for a specific logbook
 */
function get_next_record_no(PDO $pdo, int $logbook_type_id): int {
    $stmt = $pdo->prepare("SELECT COALESCE(MAX(record_no), 0) + 1 AS next_no FROM `logbook_records` WHERE `logbook_type_id` = ?");
    $stmt->execute([$logbook_type_id]);
    return (int) $stmt->fetchColumn();
}

/**
 * Get all available logbook types with their current active record counts
 */
function get_logbook_types(PDO $pdo, bool $active_only = true): array {
    $where = $active_only ? "WHERE lt.is_active = 1" : "";
    $sql = "SELECT lt.*, 
            COUNT(CASE WHEN lr.status = 'active' THEN 1 END) as active_count,
            COUNT(CASE WHEN lr.status = 'archived' THEN 1 END) as archived_count,
            COUNT(lr.id) as total_count
            FROM `logbook_types` lt
            LEFT JOIN `logbook_records` lr ON lr.logbook_type_id = lt.id
            {$where}
            GROUP BY lt.id
            ORDER BY lt.display_order ASC, lt.id ASC";
    return $pdo->query($sql)->fetchAll();
}

/**
 * Fetch a single logbook type by slug
 */
function get_logbook_type_by_slug(PDO $pdo, string $slug): ?array {
    $stmt = $pdo->prepare("SELECT * FROM `logbook_types` WHERE `slug` = ? AND `is_active` = 1 LIMIT 1");
    $stmt->execute([$slug]);
    $type = $stmt->fetch();
    return $type ?: null;
}

/**
 * Fetch fields configured for a specific logbook type
 */
function get_logbook_fields(PDO $pdo, int $type_id): array {
    $stmt = $pdo->prepare("SELECT * FROM `logbook_fields` WHERE `logbook_type_id` = ? ORDER BY `display_order` ASC, `id` ASC");
    $stmt->execute([$type_id]);
    return $stmt->fetchAll();
}

/**
 * Fetch all system settings as a key-value dictionary
 */
function get_system_settings(PDO $pdo): array {
    $stmt = $pdo->query("SELECT `setting_key`, `setting_value` FROM `system_settings`");
    $settings = [];
    while ($row = $stmt->fetch()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    return $settings;
}

/**
 * Get visual theme classes for logbook styling
 */
function get_theme_badge(string $theme): string {
    switch ($theme) {
        case 'emerald':
        case 'green':
            return 'bg-emerald-50 text-emerald-700 border-emerald-200';
        case 'indigo':
            return 'bg-indigo-50 text-indigo-700 border-indigo-200';
        case 'amber':
        case 'yellow':
            return 'bg-amber-50 text-amber-700 border-amber-200';
        case 'purple':
            return 'bg-purple-50 text-purple-700 border-purple-200';
        case 'rose':
        case 'red':
            return 'bg-rose-50 text-rose-700 border-rose-200';
        case 'blue':
        default:
            return 'bg-emerald-50 text-emerald-800 border-emerald-200';
    }
}

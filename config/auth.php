<?php
/**
 * Authentication & Session Management
 * Guidance Office Logbook Management System (OSAS)
 */

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

/**
 * Check if a user is logged in
 */
function is_logged_in(): bool {
    return isset($_SESSION['user']) && !empty($_SESSION['user']['id']);
}

/**
 * Check if the logged-in user is an Administrator
 */
function is_admin(): bool {
    return is_logged_in();
}

/**
 * Get the current logged-in user data
 */
function current_user(): ?array {
    return $_SESSION['user'] ?? null;
}

/**
 * Enforce authentication on protected pages
 */
function require_login(): void {
    if (!is_logged_in()) {
        $_SESSION['flash'] = [
            'type' => 'warning',
            'message' => 'Please sign in to access the Guidance Office Logbook system.'
        ];
        header('Location: login.php');
        exit;
    }
}

/**
 * Enforce administrator privileges
 */
function require_admin(): void {
    require_login();
    if (!is_admin()) {
        $_SESSION['flash'] = [
            'type' => 'danger',
            'message' => 'Access denied. Administrator privileges are required for this action.'
        ];
        header('Location: dashboard.php');
        exit;
    }
}

/**
 * Generate or retrieve CSRF token
 */
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 */
function verify_csrf(?string $token): bool {
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Set flash alert message
 */
function set_flash(string $type, string $message): void {
    $_SESSION['flash'] = [
        'type' => $type, // success, danger, warning, info
        'message' => $message
    ];
}

/**
 * Get and clear flash message
 */
function get_flash(): ?array {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

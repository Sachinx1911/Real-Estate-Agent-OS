<?php
/**
 * RE360 — Global configuration & constants
 * Real Estate Channel Partner Operating System
 */

// ---- Environment ----
// This file is tracked by git, so a value edited here is wiped by the next
// `git pull` — and the site would silently fall back to showing raw errors.
// So the mode is decided the other way round: production unless we can see
// that this is a local machine. Override explicitly in config/env.php
// (never committed) if you need to.
if (!defined('RE360_ENV')) {
    $RE360_ENV = null;

    if (is_file(__DIR__ . '/env.php')) {
        require __DIR__ . '/env.php';   // sets $RE360_ENV = 'development' | 'production'
    }

    if ($RE360_ENV !== 'development' && $RE360_ENV !== 'production') {
        $h = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
        $h = explode(':', $h)[0];       // drop :8080 and friends
        $isLocal = $h === '' || $h === 'localhost' || $h === '127.0.0.1' || $h === '::1'
                || str_ends_with($h, '.local') || str_ends_with($h, '.test')
                || str_ends_with($h, '.localhost');
        $RE360_ENV = $isLocal ? 'development' : 'production';
    }

    define('RE360_ENV', $RE360_ENV);
}
if (RE360_ENV === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    // Never show errors to visitors, but keep a private log so problems on
    // Hostinger can still be diagnosed (logs/ is blocked from the web).
    error_reporting(E_ALL);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    $RE360_LOG_DIR = dirname(__DIR__) . '/logs';
    if (!is_dir($RE360_LOG_DIR)) { @mkdir($RE360_LOG_DIR, 0755, true); }
    ini_set('error_log', $RE360_LOG_DIR . '/php-error.log');
}

// ---- Site ----
define('SITE_NAME', 'RE360');
define('SITE_TAGLINE', 'Real Estate Intelligence');

// ---- Paths ----
define('BASE_PATH', dirname(__DIR__));                 // filesystem root
define('UPLOADS_PATH', BASE_PATH . '/uploads');        // upload dir (filesystem)
define('UPLOADS_URL', 'uploads');                      // upload dir (web, relative)

// ---- Base URL (relative so it works in any Hostinger subfolder) ----
// All internal links use index.php?page=... — no absolute host needed.
define('APP_URL', './index.php');

// ---- Primary market (Navi Mumbai belt) ----
$GLOBALS['RE360_LOCATIONS'] = [
    'Panvel', 'Kharghar', 'Kamothe', 'Kalamboli', 'Ulwe',
    'Taloja', 'Dronagiri', 'Khanda Colony', 'New Panvel', 'Others'
];

// ---- BHK / configuration options ----
$GLOBALS['RE360_CONFIGS'] = [
    '1 RK', '1 BHK', '1.5 BHK', '2 BHK', '2.5 BHK',
    '3 BHK', '3.5 BHK', '4 BHK', 'Penthouse', 'Shop', 'Office'
];

// ---- Inventory status options ----
$GLOBALS['RE360_INV_STATUS'] = [
    'available', 'hold', 'token', 'booked',
    'agreement', 'registered', 'sold', 'cancelled', 'blocked'
];

// ---- Project status options ----
$GLOBALS['RE360_PROJECT_STATUS'] = [
    'new_launch'         => 'New Launch',
    'under_construction' => 'Under Construction',
    'ready'              => 'Ready Possession',
    'upcoming'           => 'Upcoming',
    'on_hold'            => 'On Hold',
];

// ---- Uncaught errors ----
// Development: show what actually broke. Production: friendly page + private log.
set_exception_handler(function (Throwable $ex) {
    error_log('[RE360] Uncaught ' . get_class($ex) . ': ' . $ex->getMessage()
              . ' in ' . $ex->getFile() . ':' . $ex->getLine());

    if (RE360_ENV === 'development') {
        if (!headers_sent()) http_response_code(500);
        echo '<pre style="color:#f88;background:#111;padding:20px;font-family:monospace;'
           . 'white-space:pre-wrap;line-height:1.6">'
           . htmlspecialchars(get_class($ex) . ': ' . $ex->getMessage(), ENT_QUOTES, 'UTF-8')
           . "\n\n" . htmlspecialchars($ex->getFile() . ':' . $ex->getLine(), ENT_QUOTES, 'UTF-8')
           . "\n\n" . htmlspecialchars($ex->getTraceAsString(), ENT_QUOTES, 'UTF-8')
           . '</pre>';
        exit;
    }

    require_once BASE_PATH . '/includes/error_page.php';
    re360_error_page(500, 'Something went wrong',
        'The server hit an unexpected problem. It has been written to the log. Please try again in a moment.');
});

// ---- Session ----
if (session_status() === PHP_SESSION_NONE) {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
          || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
          || (($_SERVER['SERVER_PORT'] ?? '') == 443);

    session_name('RE360SESS');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $https,        // HTTPS-only cookie once SSL is on
        'httponly' => true,          // not readable from JavaScript
        'samesite' => 'Lax',         // blocks cross-site form posts
    ]);
    ini_set('session.use_strict_mode', '1');
    session_start();

    // Auto sign-out after 8 hours of inactivity
    $idleLimit = 8 * 3600;
    if (isset($_SESSION['last_seen']) && (time() - $_SESSION['last_seen']) > $idleLimit) {
        $_SESSION = [];
        session_destroy();
        session_start();
    }
    $_SESSION['last_seen'] = time();
}

// ---- Timezone ----
date_default_timezone_set('Asia/Kolkata');

require_once __DIR__ . '/db.php';
require_once BASE_PATH . '/includes/helpers.php';
require_once BASE_PATH . '/includes/csrf.php';

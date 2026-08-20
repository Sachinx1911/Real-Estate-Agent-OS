<?php
/**
 * RE360 — Global configuration & constants
 * Real Estate Channel Partner Operating System
 */

// ---- Error reporting (turn off display in production on Hostinger) ----
if (!defined('RE360_ENV')) {
    define('RE360_ENV', 'development'); // change to 'production' on Hostinger
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

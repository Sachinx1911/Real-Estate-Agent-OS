<?php
/**
 * RE360 — MySQL (PDO) connection
 *
 * LOCAL (XAMPP):   host=127.0.0.1  user=root  pass=''  db=re360
 * HOSTINGER:       fill in the values from hPanel → Databases → MySQL Databases
 *                  (host is usually 'localhost' on Hostinger shared hosting)
 */

// ---------- Edit these for your environment ----------
$DB_HOST = '127.0.0.1';
$DB_PORT = 3306;          // local testing may use another port; Hostinger uses 3306
$DB_NAME = 're360';
$DB_USER = 'root';
$DB_PASS = '';
$DB_CHARSET = 'utf8mb4';

// Optional local override (config/db.local.php) — never upload this to Hostinger.
if (is_file(__DIR__ . '/db.local.php')) {
    require __DIR__ . '/db.local.php';
}
// -----------------------------------------------------

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    global $DB_HOST, $DB_PORT, $DB_NAME, $DB_USER, $DB_PASS, $DB_CHARSET;

    $port = $DB_PORT ?? 3306;
    $dsn = "mysql:host={$DB_HOST};port={$port};dbname={$DB_NAME};charset={$DB_CHARSET}";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    try {
        $pdo = new PDO($dsn, $DB_USER, $DB_PASS, $options);
    } catch (PDOException $e) {
        if (defined('RE360_ENV') && RE360_ENV === 'development') {
            die('<pre style="color:#f88;background:#111;padding:20px;font-family:monospace">'
                . "Database connection failed:\n" . htmlspecialchars($e->getMessage())
                . "\n\nCheck config/db.php credentials and that the database exists.</pre>");
        }
        die('Service temporarily unavailable.');
    }

    return $pdo;
}

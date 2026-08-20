<?php
/**
 * RE360 — server health check.
 *
 * Open this right after uploading to Hostinger to confirm the server is
 * configured correctly, BEFORE running setup.php.
 *
 * Access rules:
 *   - Before installation (no config/installed.lock) → open to anyone
 *   - After installation                             → admin login required
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

$installed = is_file(BASE_PATH . '/config/installed.lock');
if ($installed) {
    require_role('admin');
}

/* ---------- checks ---------- */
$checks = [];
function check(string $group, string $label, bool $ok, string $detail, bool $warnOnly = false): void
{
    global $checks;
    $checks[$group][] = ['label' => $label, 'ok' => $ok, 'detail' => $detail, 'warn' => $warnOnly];
}

/* PHP */
check('PHP', 'PHP version', version_compare(PHP_VERSION, '8.0', '>='),
      PHP_VERSION . (version_compare(PHP_VERSION, '8.0', '>=') ? ' — good' : ' — set PHP 8.1+ in hPanel'));

foreach (['pdo_mysql' => true, 'mbstring' => true, 'json' => true, 'fileinfo' => true,
          'openssl' => true, 'gd' => false, 'zip' => false] as $ext => $required) {
    check('PHP', "Extension: $ext", extension_loaded($ext),
          extension_loaded($ext) ? 'loaded' : ($required ? 'MISSING — enable in hPanel → PHP Configuration' : 'not loaded (optional)'),
          !$required);
}

$umf = ini_get('upload_max_filesize');
$pms = ini_get('post_max_size');
check('PHP', 'upload_max_filesize', (int)$umf >= 8, $umf . ' (10 MB uploads need 12M — see .user.ini)', true);
check('PHP', 'post_max_size',       (int)$pms >= 8, $pms, true);
check('PHP', 'memory_limit',        true, (string)ini_get('memory_limit'), true);
check('PHP', 'Timezone',            date_default_timezone_get() === 'Asia/Kolkata', date_default_timezone_get());

/* Environment */
$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
      || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
check('Server', 'HTTPS active', $https, $https ? 'secure' : 'not on HTTPS — enable free SSL in hPanel → Security → SSL', true);
check('Server', 'Environment mode', RE360_ENV === 'production',
      RE360_ENV . (RE360_ENV === 'production' ? '' : ' — set RE360_ENV to production in config/config.php before going live'), true);
check('Server', 'Display errors off', ini_get('display_errors') == '0' || ini_get('display_errors') === '',
      ini_get('display_errors') ? 'ON — visitors can see PHP errors' : 'off');
check('Server', 'Session works', session_status() === PHP_SESSION_ACTIVE, 'session id: ' . (session_id() ? 'ok' : 'none'));

/* Folder permissions */
foreach ([
    'uploads' => UPLOADS_PATH,
    'logs'    => BASE_PATH . '/logs',
    'config'  => BASE_PATH . '/config',
] as $name => $dir) {
    $exists = is_dir($dir);
    $writable = $exists && is_writable($dir);
    check('Folders', "$name/ writable", $writable,
          !$exists ? 'folder missing — create it' : ($writable ? 'ok (' . substr(sprintf('%o', fileperms($dir)), -4) . ')' : 'not writable — set permission 755'));
}

/* Database */
$dbOk = false;
try {
    $ver = scalar('SELECT VERSION()');
    $dbOk = true;
    check('Database', 'Connection', true, 'MySQL ' . $ver);
} catch (Throwable $e) {
    // Show what MySQL actually said — "Access denied" and "Unknown database"
    // need completely different fixes.
    check('Database', 'Connection', false, 'FAILED — ' . $e->getMessage());
    check('Database', 'What to check', false,
          'config/db.local.php: database name and username usually need the u123456789_ prefix; host is normally localhost');
}

if ($dbOk) {
    $needed = ['users','builders','projects','inventory','clients','client_requirements',
               'bookings','site_visits','tasks','activity_log','documents'];
    $present = [];
    try {
        foreach (rows("SHOW TABLES") as $r) { $present[] = strtolower(reset($r)); }
    } catch (Throwable $e) {}
    $missing = array_diff($needed, $present);
    check('Database', 'Schema tables', empty($missing),
          empty($missing) ? count($present) . ' tables found' : 'missing: ' . implode(', ', $missing) . ' — run setup.php');

    if (in_array('users', $present, true)) {
        $n = (int) scalar("SELECT COUNT(*) FROM users");
        check('Database', 'User accounts', $n > 0, $n . ' account(s)' . ($n ? '' : ' — run setup.php'));
    }
}

/* Security posture */
check('Security', 'setup.php removed', !is_file(BASE_PATH . '/setup.php'),
      is_file(BASE_PATH . '/setup.php')
        ? ($installed ? 'still present — DELETE it (installation is locked, but delete anyway)' : 'present — delete after running setup')
        : 'deleted', true);
check('Security', 'Install locked', $installed, $installed ? 'config/installed.lock present' : 'setup has not been run yet', true);
check('Security', 'db.local.php in use', is_file(BASE_PATH . '/config/db.local.php'),
      is_file(BASE_PATH . '/config/db.local.php') ? 'credentials kept out of git' : 'using config/db.php defaults', true);
check('Security', 'Reset tool locked', !is_file(BASE_PATH . '/config/reset.allow'),
      is_file(BASE_PATH . '/config/reset.allow') ? 'config/reset.allow EXISTS — delete it' : 'locked');

$fails = 0; $warns = 0;
foreach ($checks as $g) foreach ($g as $c) { if (!$c['ok']) { $c['warn'] ? $warns++ : $fails++; } }
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">
  <title>Health check · <?= SITE_NAME ?></title>
  <link rel="icon" type="image/svg+xml" href="../assets/img/favicon.svg">
  <link rel="stylesheet" href="../assets/css/re360.css">
  <script>
    (function () {
      var t = null;
      try { t = localStorage.getItem('re360-theme'); } catch (e) {}
      if (t !== 'light' && t !== 'dark') {
        t = window.matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark';
      }
      document.documentElement.classList.toggle('light', t === 'light');
    })();
  </script>
  <style>
    .hc-wrap{max-width:760px;margin:40px auto;padding:0 20px}
    .hc-row{display:flex;gap:12px;align-items:flex-start;padding:11px 0;border-bottom:1px solid var(--border-soft)}
    .hc-row:last-child{border-bottom:0}
    .hc-dot{width:9px;height:9px;border-radius:50%;flex:0 0 9px;margin-top:6px}
    .hc-lbl{font-weight:600;font-size:14px;min-width:190px}
    .hc-det{color:var(--text-2);font-size:13px;line-height:1.5}
    .hc-grp{margin-top:18px}
    .hc-grp h3{font-size:12px;letter-spacing:.08em;text-transform:uppercase;color:var(--text-muted);margin:0 0 6px}
  </style>
</head>
<body style="background:var(--bg);color:var(--text)">
<div class="hc-wrap">
  <div class="brand" style="padding:0 0 20px">
    <div class="logo">R</div>
    <div><div class="b-name"><?= SITE_NAME ?></div><div class="b-tag">Server health check</div></div>
  </div>

  <div class="card" style="padding:16px 18px">
    <?php if ($fails === 0 && $warns === 0): ?>
      <strong style="color:var(--green)">✓ Everything looks good — you are ready to go live.</strong>
    <?php elseif ($fails === 0): ?>
      <strong style="color:var(--amber)">⚠ <?= $warns ?> warning(s)</strong>
      <span class="muted small"> — nothing blocking, but worth fixing.</span>
    <?php else: ?>
      <strong style="color:var(--red)">✕ <?= $fails ?> problem(s) must be fixed</strong>
      <?php if ($warns): ?><span class="muted small"> and <?= $warns ?> warning(s).</span><?php endif; ?>
    <?php endif; ?>
  </div>

  <?php foreach ($checks as $group => $items): ?>
    <div class="hc-grp">
      <h3><?= e($group) ?></h3>
      <div class="card" style="padding:6px 18px">
        <?php foreach ($items as $c):
              $color = $c['ok'] ? 'var(--green)' : ($c['warn'] ? 'var(--amber)' : 'var(--red)'); ?>
          <div class="hc-row">
            <span class="hc-dot" style="background:<?= $color ?>"></span>
            <span class="hc-lbl"><?= e($c['label']) ?></span>
            <span class="hc-det"><?= e($c['detail']) ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endforeach; ?>

  <p class="muted small" style="margin-top:22px">
    Delete <code>tools/healthcheck.php</code> once the site is live, or keep it — it needs an admin login after setup.
  </p>
</div>
</body>
</html>

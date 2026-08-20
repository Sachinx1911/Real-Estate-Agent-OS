<?php
/**
 * RE360 — one-time setup.
 * 1. Checks DB connection.
 * 2. Creates all tables from sql/schema.sql (if missing).
 * 3. Optionally loads demo data from sql/seed.sql.
 * 4. Creates the first admin user (password hashed on the server).
 *
 * DELETE or rename this file after setup for security.
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/icons.php';

$messages = [];
$errors   = [];
$done     = false;

/* ------------------------------------------------------------------
 * Install lock — once setup has run successfully this file refuses to
 * do anything again. Even if you forget to delete setup.php, nobody can
 * re-run it and create a second admin account.
 * To re-run intentionally: delete config/installed.lock
 * ---------------------------------------------------------------- */
define('INSTALL_LOCK', __DIR__ . '/config/installed.lock');

if (is_file(INSTALL_LOCK)) {
    http_response_code(403);
    ?><!DOCTYPE html>
    <html lang="en"><head><meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Setup locked · <?= SITE_NAME ?></title>
    <link rel="icon" type="image/svg+xml" href="assets/img/favicon.svg">
    <link rel="stylesheet" href="assets/css/re360.css"></head>
    <body><div class="login-wrap"><div class="login-card">
      <h2>Setup already completed</h2>
      <p class="muted small" style="margin-top:8px">
        This installation is locked. For security please delete
        <code>setup.php</code> from the server.
      </p>
      <a class="btn primary" href="login.php" style="width:100%;justify-content:center;margin-top:18px">Go to Sign in &rarr;</a>
    </div></div></body></html><?php
    exit;
}

function table_exists(string $t): bool
{
    try {
        db()->query("SELECT 1 FROM `$t` LIMIT 1");
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

function run_sql_file(string $path): void
{
    if (!is_file($path)) throw new RuntimeException("Missing SQL file: $path");
    $sql = file_get_contents($path);
    db()->exec($sql); // mysql PDO driver runs multiple statements
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_valid()) {
    $errors[] = 'Session expired. Please reload this page and try again.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$errors) {
    $name  = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';
    $loadDemo = !empty($_POST['load_demo']);

    // 1. connection
    try {
        db();
    } catch (Throwable $e) {
        $errors[] = 'Database connection failed. Check the credentials in config/db.local.php '
                  . '(database name and username usually need the u123456789_ prefix).';
        $errors[] = 'MySQL said: ' . $e->getMessage();
    }

    if (!$errors) {
        // 2. schema
        try {
            if (!table_exists('users')) {
                run_sql_file(__DIR__ . '/sql/schema.sql');
                $messages[] = 'Database tables created.';
            } else {
                $messages[] = 'Tables already exist — skipped schema.';
            }
        } catch (Throwable $e) {
            $errors[] = 'Schema import failed: ' . $e->getMessage();
        }
    }

    if (!$errors && $loadDemo) {
        // 3. demo data (only if no builders yet)
        try {
            $hasData = (int) scalar("SELECT COUNT(*) FROM builders");
            if ($hasData === 0) {
                run_sql_file(__DIR__ . '/sql/seed.sql');
                $messages[] = 'Demo data loaded (builders, projects, inventory, clients).';
            } else {
                $messages[] = 'Data already present — skipped demo seed.';
            }
        } catch (Throwable $e) {
            $errors[] = 'Demo seed failed: ' . $e->getMessage();
        }
    }

    if (!$errors) {
        // 4. admin user
        if ($name === '' || $email === '' || strlen($pass) < 6) {
            $errors[] = 'Enter a name, valid email, and a password of at least 6 characters.';
        } else {
            try {
                $exists = scalar("SELECT id FROM users WHERE email = ?", [$email]);
                if ($exists) {
                    $messages[] = 'A user with this email already exists — you can sign in.';
                    $done = true;
                } else {
                    db()->prepare("INSERT INTO users (name, role, email, password_hash) VALUES (?,?,?,?)")
                        ->execute([$name, 'admin', $email, password_hash($pass, PASSWORD_DEFAULT)]);
                    $messages[] = 'Admin account created successfully!';
                    $done = true;
                }
            } catch (Throwable $e) {
                $errors[] = 'Could not create user: ' . $e->getMessage();
            }
        }
    }

    if ($done && !$errors) {
        @file_put_contents(INSTALL_LOCK, 'Installed on ' . date('c') . "\n");
    }
}
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Setup · <?= SITE_NAME ?></title>
  <meta name="robots" content="noindex, nofollow">
  <link rel="icon" type="image/svg+xml" href="assets/img/favicon.svg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/re360.css">
</head>
<body>
<div class="login-wrap">
  <div class="login-card" style="max-width:460px">
    <div class="brand" style="padding:0 0 18px">
      <div class="logo">R</div>
      <div><div class="b-name"><?= SITE_NAME ?></div><div class="b-tag">First-time setup</div></div>
    </div>

    <?php foreach ($messages as $m): ?><div class="login-err" style="background:var(--green-bg);color:var(--green)">✓ <?= e($m) ?></div><?php endforeach; ?>
    <?php foreach ($errors as $er): ?><div class="login-err"><?= e($er) ?></div><?php endforeach; ?>

    <?php if ($done): ?>
      <p style="margin-top:16px">Setup complete. For security, <strong>delete <code>setup.php</code></strong> now.</p>
      <a class="btn primary" href="login.php" style="width:100%;justify-content:center;margin-top:18px">Go to Sign in →</a>
    <?php else: ?>
      <h2 style="margin-top:6px">Create your admin account</h2>
      <p class="muted small" style="margin-top:4px">This also creates the database tables on first run.</p>
      <form method="post"><?= csrf_field() ?>
        <div class="form-group" style="margin-top:16px"><label>Your name</label>
          <input class="field-input" name="name" required value="<?= e($_POST['name'] ?? '') ?>" placeholder="Your full name"></div>
        <div class="form-group" style="margin-top:14px"><label>Email</label>
          <input class="field-input" type="email" name="email" required value="<?= e($_POST['email'] ?? '') ?>"></div>
        <div class="form-group" style="margin-top:14px"><label>Password (min 6 chars)</label>
          <input class="field-input" type="password" name="password" required></div>
        <label style="display:flex;align-items:center;gap:9px;margin-top:16px;font-size:13px;cursor:pointer">
          <input type="checkbox" name="load_demo" value="1" checked> Load demo data (recommended for first look)
        </label>
        <button class="btn primary" type="submit" style="width:100%;justify-content:center;margin-top:20px">Run setup</button>
      </form>
    <?php endif; ?>
  </div>
</div>
</body>
</html>

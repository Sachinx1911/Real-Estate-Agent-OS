<?php
/**
 * RE360 — emergency password reset.
 *
 * Locked by default. To use it:
 *   1. In hPanel File Manager create an empty file:  config/reset.allow
 *   2. Within 30 minutes open  https://yourdomain.com/tools/reset_password.php
 *   3. Set the new password — the allow file is deleted automatically
 *
 * Without config/reset.allow this page always returns 403.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/error_page.php';

$allowFile = BASE_PATH . '/config/reset.allow';
$WINDOW    = 1800; // 30 minutes

if (!is_file($allowFile)) {
    re360_error_page(403, 'Password reset is locked',
        'Create an empty file named config/reset.allow in the File Manager, then reload this page within 30 minutes.');
}
if ((time() - (int)filemtime($allowFile)) > $WINDOW) {
    @unlink($allowFile);
    re360_error_page(403, 'Reset window expired',
        'The 30 minute window has passed. Create config/reset.allow again to start over.');
}

$msg = ''; $err = ''; $done = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';
    $pass2 = $_POST['password2'] ?? '';

    if (!csrf_valid())            $err = 'Session expired. Reload the page and try again.';
    elseif ($email === '')        $err = 'Enter the account email.';
    elseif (strlen($pass) < 8)    $err = 'Password must be at least 8 characters.';
    elseif ($pass !== $pass2)     $err = 'The two passwords do not match.';
    else {
        try {
            $u = row("SELECT id, name FROM users WHERE email = ? LIMIT 1", [$email]);
            if (!$u) {
                $err = 'No account found with that email.';
            } else {
                db()->prepare("UPDATE users SET password_hash = ? WHERE id = ?")
                    ->execute([password_hash($pass, PASSWORD_DEFAULT), $u['id']]);
                @unlink($allowFile);                                  // single use
                @array_map('unlink', glob(BASE_PATH . '/logs/throttle/*.json') ?: []); // clear lockouts
                $msg  = 'Password updated for ' . $u['name'] . '. You can sign in now.';
                $done = true;
            }
        } catch (Throwable $e) {
            $err = 'Database error. Check config/db.local.php credentials.';
        }
    }
}
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">
  <title>Password reset · <?= SITE_NAME ?></title>
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
</head>
<body>
<div class="login-wrap">
  <div class="login-card">
    <div class="brand" style="padding:0 0 18px">
      <div class="logo">R</div>
      <div><div class="b-name"><?= SITE_NAME ?></div><div class="b-tag">Emergency password reset</div></div>
    </div>

    <?php if ($msg): ?><div class="login-err" style="background:var(--green-bg);color:var(--green)">✓ <?= e($msg) ?></div><?php endif; ?>
    <?php if ($err): ?><div class="login-err"><?= e($err) ?></div><?php endif; ?>

    <?php if ($done): ?>
      <p class="muted small" style="margin-top:14px">For security, delete <code>tools/reset_password.php</code> if you do not need it again.</p>
      <a class="btn primary" href="../login.php" style="width:100%;justify-content:center;margin-top:18px">Go to Sign in →</a>
    <?php else: ?>
      <form method="post">
        <?= csrf_field() ?>
        <div class="form-group" style="margin-top:14px"><label>Account email</label>
          <input class="field-input" type="email" name="email" required value="<?= e($_POST['email'] ?? '') ?>"></div>
        <div class="form-group" style="margin-top:14px"><label>New password (min 8 chars)</label>
          <input class="field-input" type="password" name="password" required></div>
        <div class="form-group" style="margin-top:14px"><label>Repeat new password</label>
          <input class="field-input" type="password" name="password2" required></div>
        <button class="btn primary" type="submit" style="width:100%;justify-content:center;margin-top:20px">Set new password</button>
      </form>
    <?php endif; ?>
  </div>
</div>
</body>
</html>

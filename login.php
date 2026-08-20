<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/icons.php';

if (is_logged_in()) { header('Location: index.php'); exit; }

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lockedFor = login_locked_for();
    if (!csrf_valid()) {
        $err = 'Your session expired. Please try again.';
    } elseif ($lockedFor > 0) {
        $err = 'Too many failed attempts. Try again in ' . ceil($lockedFor / 60) . ' minute(s).';
    } else {
        $email = $_POST['email'] ?? '';
        $pass  = $_POST['password'] ?? '';
        if (attempt_login($email, $pass)) {
            header('Location: index.php');
            exit;
        }
        $err = 'Invalid email or password.';
    }
}
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sign in · <?= SITE_NAME ?></title>
  <meta name="robots" content="noindex, nofollow">
  <link rel="icon" type="image/svg+xml" href="assets/img/favicon.svg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= asset('assets/css/re360.css') ?>">
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
    <div class="brand" style="padding:0 0 22px">
      <div class="logo">R</div>
      <div>
        <div class="b-name"><?= SITE_NAME ?></div>
        <div class="b-tag"><?= SITE_TAGLINE ?></div>
      </div>
    </div>
    <h2>Welcome back</h2>
    <p class="muted small" style="margin-top:4px">Sign in to your channel-partner workspace.</p>

    <?php if ($err): ?><div class="login-err"><?= e($err) ?></div><?php endif; ?>

    <form method="post">
      <?= csrf_field() ?>
      <div class="form-group">
        <label>Email</label>
        <input class="field-input" type="email" name="email" required autofocus placeholder="you@example.com" value="<?= e($_POST['email'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label>Password</label>
        <input class="field-input" type="password" name="password" required placeholder="••••••••">
      </div>
      <button class="btn primary" type="submit"><?= icon('logout', 16) ?> Sign in</button>
    </form>
  </div>
</div>
</body>
</html>

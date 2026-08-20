<?php
/**
 * RE360 — standalone error page renderer.
 * Deliberately has NO database or config dependency, so it still renders
 * when the DB is unreachable or config is broken.
 */
function re360_error_page(int $code, string $title, string $message): void
{
    if (!headers_sent()) http_response_code($code);

    // Work out the app root as the browser sees it, without needing config.php
    $root = rtrim(str_replace('\\', '/', dirname(__DIR__)), '/');
    $doc  = rtrim(str_replace('\\', '/', (string)($_SERVER['DOCUMENT_ROOT'] ?? '')), '/');
    $base = ($doc !== '' && strpos($root, $doc) === 0)
          ? rtrim(substr($root, strlen($doc)), '/') . '/'
          : '/';
    ?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">
  <title><?= $code ?> · RE360</title>
  <link rel="icon" type="image/svg+xml" href="<?= $base ?>assets/img/favicon.svg">
  <style>
    *{box-sizing:border-box}
    body{margin:0;min-height:100vh;display:grid;place-items:center;padding:24px;
         background:#0a0e1a;color:#e8ecf5;
         font-family:Inter,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}
    .box{max-width:460px;width:100%;background:#111730;border:1px solid #1e2740;
         border-radius:18px;padding:38px 34px;box-shadow:0 8px 30px rgba(0,0,0,.35)}
    .logo{width:44px;height:44px;border-radius:12px;display:grid;place-items:center;
          background:linear-gradient(135deg,#7c5cff,#2dd4bf);font-weight:800;font-size:20px;color:#fff}
    .code{font-size:52px;font-weight:800;line-height:1;margin:22px 0 6px;
          background:linear-gradient(135deg,#7c5cff,#2dd4bf);-webkit-background-clip:text;
          background-clip:text;color:transparent}
    h1{font-size:19px;margin:0 0 10px}
    p{color:#aeb8cf;line-height:1.65;font-size:14px;margin:0}
    a.btn{display:inline-block;margin-top:24px;background:#7c5cff;color:#fff;text-decoration:none;
          padding:11px 20px;border-radius:10px;font-weight:600;font-size:14px}
    a.btn:hover{background:#6a4bff}
  </style>
</head>
<body>
  <div class="box">
    <div class="logo">R</div>
    <div class="code"><?= $code ?></div>
    <h1><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h1>
    <p><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p>
    <a class="btn" href="<?= $base ?>index.php">Back to dashboard</a>
  </div>
</body>
</html><?php
    exit;
}

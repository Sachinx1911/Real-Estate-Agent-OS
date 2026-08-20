<?php
/**
 * RE360 — page head + top bar.
 * Expects: $page (slug), optional $greeting (string), $subtitle (string).
 * Opens .app > (sidebar) + .main > .topbar ... ; page then prints .content and footer.php closes.
 */
require_once __DIR__ . '/icons.php';
$u = current_user() ?? ['name' => 'User'];
$firstName = trim(explode(' ', $u['name'])[0]);
$hour = (int)date('G');
$partOfDay = $hour < 12 ? 'Good Morning' : ($hour < 17 ? 'Good Afternoon' : 'Good Evening');
$pageTitle = $pageTitle ?? 'Dashboard';

/* The welcome line belongs to the dashboard only. Every other page states its
   own title in .page-head, so repeating a greeting there is just noise. */
$isHome   = ($page ?? '') === 'dashboard';
$greeting = $greeting ?? ($isHome ? "$partOfDay, $firstName!" : $pageTitle);
$subtitle = $subtitle ?? ($isHome ? "Here's what's happening with your real estate business today." : '');

/* Notifications — open tasks that are overdue or due within 2 days, plus
   site visits scheduled for today. The bell used to show a count with
   nothing behind it; the panel below lists the actual items. */
$notifItems = [];
try {
    foreach (rows(
        "SELECT id, title, subtitle, due_at, priority
           FROM tasks
          WHERE status='open' AND due_at IS NOT NULL
            AND due_at <= DATE_ADD(NOW(), INTERVAL 2 DAY)
          ORDER BY due_at ASC LIMIT 8") as $t) {
        $overdue = strtotime($t['due_at']) < time();
        $notifItems[] = [
            'title' => $t['title'],
            'meta'  => ($overdue ? 'Overdue · ' : 'Due ') . fdate($t['due_at'], 'd M, g:i A'),
            'color' => $overdue ? 'var(--red)' : ($t['priority'] === 'high' ? 'var(--amber)' : 'var(--blue)'),
            'url'   => url('tasks'),
        ];
    }
} catch (Throwable $e) {}

try {
    foreach (rows(
        "SELECT sv.id, sv.visit_date, c.name AS cname, p.name AS pname
           FROM site_visits sv
           JOIN clients c       ON c.id = sv.client_id
           LEFT JOIN projects p ON p.id = sv.project_id
          WHERE sv.status = 'scheduled' AND DATE(sv.visit_date) = CURDATE()
          ORDER BY sv.visit_date ASC LIMIT 5") as $v) {
        $notifItems[] = [
            'title' => 'Site visit — ' . $v['cname'],
            'meta'  => trim(($v['pname'] ?? '') . ' · ' . fdate($v['visit_date'], 'g:i A'), ' ·'),
            'color' => 'var(--teal)',
            'url'   => url('site_visits'),
        ];
    }
} catch (Throwable $e) {}

$notif = count($notifItems);
$defaultLoc = 'Panvel';
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($pageTitle) ?> · <?= SITE_NAME ?></title>
  <meta name="robots" content="noindex, nofollow">
  <meta name="theme-color" content="#0d1017">
  <link rel="icon" type="image/svg+xml" href="assets/img/favicon.svg">
  <link rel="icon" type="image/png" sizes="32x32" href="assets/img/favicon-32.png">
  <link rel="apple-touch-icon" href="assets/img/apple-touch-icon.png">
  <link rel="manifest" href="site.webmanifest">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/re360.css">
  <script>
    // Runs before the body paints, so the saved theme never flashes the wrong one.
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
<div class="app">
  <?php require __DIR__ . '/sidebar.php'; ?>
  <div class="main">
    <header class="topbar">
      <button class="tb-btn hamburger" onclick="document.getElementById('sidebar').classList.toggle('open');document.getElementById('sidebarBackdrop').style.display='block'"><?= icon('menu', 20) ?></button>
      <?php if ($isHome): ?>
      <div class="greet">
        <h1><?= e($greeting) ?> <span style="font-weight:400">👋</span></h1>
        <?php if ($subtitle !== ''): ?><p><?= e($subtitle) ?></p><?php endif; ?>
      </div>
      <?php endif; ?>
      <div class="spacer"></div>
      <div class="search" onclick="RE360.openSearch()">
        <?= icon('search', 18) ?>
        <input type="text" id="globalSearch" placeholder="Search projects, builders, clients, inventory..." autocomplete="off">
        <span class="kbd">Ctrl + K</span>
      </div>
      <button class="tb-btn optional"><?= icon('location', 17) ?> <?= e($defaultLoc) ?></button>
      <button class="tb-btn optional"><?= icon('calendar', 17) ?> <?= date('d M Y') ?></button>
      <div class="notif-wrap">
        <button class="tb-btn" title="Notifications" onclick="RE360.toggleNotifications(event)">
          <?= icon('bell', 18) ?><?php if ($notif): ?><span class="n-badge"><?= $notif ?></span><?php endif; ?>
        </button>
        <div class="notif-panel" id="notifPanel">
          <div class="notif-head">
            <h4>Notifications</h4>
            <span class="badge <?= $notif ? 'amber' : 'grey' ?>"><?= $notif ?></span>
          </div>
          <div class="notif-list">
            <?php if (!$notifItems): ?>
              <div class="notif-empty">Nothing needs your attention right now.</div>
            <?php else: foreach ($notifItems as $n): ?>
              <a class="notif-item" href="<?= e($n['url']) ?>">
                <span class="notif-dot" style="background:<?= $n['color'] ?>"></span>
                <span style="flex:1">
                  <span class="n-title"><?= e($n['title']) ?></span>
                  <span class="n-meta" style="display:block"><?= e($n['meta']) ?></span>
                </span>
              </a>
            <?php endforeach; endif; ?>
          </div>
          <div class="notif-foot"><a href="<?= url('tasks') ?>">View all tasks →</a></div>
        </div>
      </div>
      <button class="tb-btn" id="themeBtn" title="Switch theme" onclick="RE360.toggleTheme()">
        <span class="ic-moon"><?= icon('moon', 18) ?></span><span class="ic-sun"><?= icon('sun', 18) ?></span>
      </button>
      <button class="tb-btn grad" title="Quick add" onclick="location.href='<?= url('project_form') ?>'"><?= icon('bolt', 18) ?></button>
    </header>
    <main class="content">

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

// notifications count (open tasks due soon)
$notif = 0;
try { $notif = (int) scalar("SELECT COUNT(*) FROM tasks WHERE status='open' AND due_at <= DATE_ADD(NOW(), INTERVAL 2 DAY)"); } catch (Throwable $e) {}
$defaultLoc = 'Panvel';
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($pageTitle) ?> · <?= SITE_NAME ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/re360.css">
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
      <button class="tb-btn" title="Notifications"><?= icon('bell', 18) ?><?php if ($notif): ?><span class="n-badge"><?= $notif ?></span><?php endif; ?></button>
      <button class="tb-btn optional" title="Theme" onclick="RE360.toggleTheme()"><?= icon('moon', 18) ?></button>
      <button class="tb-btn grad" title="Quick add" onclick="location.href='<?= url('project_form') ?>'"><?= icon('bolt', 18) ?></button>
    </header>
    <main class="content">

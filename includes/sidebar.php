<?php
/** RE360 — left sidebar. Expects $page (current page slug). */
require_once __DIR__ . '/icons.php';
$page = $page ?? 'dashboard';
$u = current_user() ?? ['name' => 'User', 'role' => 'channel_partner'];

$nav = [
    ['dashboard', 'Dashboard', 'dashboard'],
    ['builders',  'Builders',  'builders'],
    ['projects',  'Projects',  'projects'],
    ['inventory', 'Inventory', 'inventory', 'New'],
    ['leads',     'Leads & Clients', 'leads'],
    ['site_visits','Site Visits', 'visits'],
    ['bookings',  'Bookings',  'bookings'],
    ['reports',   'Reports & Analytics', 'reports'],
    ['comparisons','Comparisons', 'compare'],
    ['documents', 'Documents', 'documents'],
    ['tasks',     'Tasks & Follow Ups', 'tasks'],
    ['calendar',  'Calendar',  'calendar'],
    ['settings',  'Settings',  'settings'],
];

$roleLabel = ['admin' => 'Admin', 'channel_partner' => 'Channel Partner', 'sales' => 'Sales'][$u['role']] ?? 'User';
$initials = strtoupper(substr(trim($u['name']), 0, 1) . (strpos($u['name'], ' ') ? substr(strstr($u['name'], ' '), 1, 1) : ''));
?>
<aside class="sidebar" id="sidebar">
  <div class="brand">
    <div class="logo">R</div>
    <div>
      <div class="b-name"><?= SITE_NAME ?></div>
      <div class="b-tag"><?= SITE_TAGLINE ?></div>
    </div>
  </div>

  <nav class="nav">
    <?php foreach ($nav as $item):
        [$slug, $label, $ic] = $item;
        $new = $item[3] ?? null;
        $active = ($page === $slug) ? 'active' : '';
    ?>
      <a href="<?= url($slug) ?>" class="<?= $active ?>">
        <span class="ic"><?= icon($ic) ?></span>
        <span><?= e($label) ?></span>
        <?php if ($new): ?><span class="badge-new"><?= e($new) ?></span><?php endif; ?>
      </a>
    <?php endforeach; ?>
  </nav>

  <div class="nav-label">Quick Actions</div>
  <div class="quick">
    <a href="<?= url('project_form') ?>" class="qa purple"><span class="ic"><?= icon('plus', 17) ?></span> Add New Project</a>
    <a href="<?= url('inventory_form') ?>" class="qa blue"><span class="ic"><?= icon('inventory', 17) ?></span> Add Inventory</a>
    <a href="<?= url('client_form') ?>" class="qa teal"><span class="ic"><?= icon('leads', 17) ?></span> Add Client / Lead</a>
    <a href="<?= url('documents') ?>" class="qa orange"><span class="ic"><?= icon('upload', 17) ?></span> Upload Document</a>
  </div>

  <div class="user-card">
    <div class="av"><?= e($initials ?: 'U') ?></div>
    <div style="flex:1">
      <div class="u-name"><?= e($u['name']) ?></div>
      <div class="u-role"><?= e($roleLabel) ?></div>
    </div>
    <a href="logout.php" title="Logout" class="muted"><?= icon('logout', 17) ?></a>
  </div>
</aside>
<div class="sidebar-backdrop" id="sidebarBackdrop" onclick="document.getElementById('sidebar').classList.remove('open')" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:39"></div>

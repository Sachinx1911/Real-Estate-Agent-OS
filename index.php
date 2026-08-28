<?php
/**
 * RE360 — front controller / router.
 * All pages are reached via index.php?page=<slug>
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';

require_login();

// Every form POST inside the app must carry a valid CSRF token
csrf_check();

$page = preg_replace('/[^a-z_]/', '', strtolower($_GET['page'] ?? 'dashboard'));

// map slug -> page file
$routes = [
    'dashboard'       => 'dashboard.php',
    'builders'        => 'builders.php',
    'builder_view'    => 'builder_view.php',
    'builder_form'    => 'builder_form.php',
    'projects'        => 'projects.php',
    'project_view'    => 'project_view.php',
    'project_form'    => 'project_form.php',
    'inventory'       => 'inventory.php',
    'inventory_form'  => 'inventory_form.php',
    'leads'           => 'leads.php',
    'client_form'     => 'client_form.php',
    'client_view'     => 'client_view.php',
    'matcher'         => 'matcher.php',
    'site_visits'     => 'site_visits.php',
    'bookings'        => 'bookings.php',
    'rent'            => 'rent.php',
    'rent_flat_form'  => 'rent_flat_form.php',
    'rent_seeker_form'=> 'rent_seeker_form.php',
    'tasks'           => 'tasks.php',
    'calendar'        => 'calendar.php',
    'offers'          => 'offers.php',
    'payment_plans'   => 'payment_plans.php',
    'legal'           => 'legal.php',
    'cp_management'   => 'cp_management.php',
    'documents'       => 'documents.php',
    'reports'         => 'reports.php',
    'comparisons'     => 'comparisons.php',
    'settings'        => 'settings.php',
];

$file = $routes[$page] ?? null;
$path = $file ? __DIR__ . '/pages/' . $file : null;

if ($path && is_file($path)) {
    require $path;
} else {
    // unknown slug — tell crawlers and the browser this is not a real page
    http_response_code(404);
    $pageTitle = ucwords(str_replace('_', ' ', $page));
    require __DIR__ . '/includes/header.php';
    echo '<div class="page-head"><div><h2>' . e($pageTitle) . '</h2>'
       . '<p>This module is planned and coming up in a later phase.</p></div></div>';
    echo '<div class="card empty"><div>🚧 <strong>' . e($pageTitle) . '</strong> module is under construction.</div>'
       . '<p class="muted mt">It will be built as per the approved plan.</p></div>';
    require __DIR__ . '/includes/footer.php';
}

<?php
/** RE360 — filtered inventory rows for the dashboard snapshot (JSON) */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: application/json; charset=utf-8');

// AJAX endpoint: answer with JSON instead of redirecting to the login page
if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'Not signed in']);
    exit;
}

$projectId = (int)($_GET['project'] ?? 0);
$tower     = trim($_GET['tower'] ?? '');
$config    = trim($_GET['config'] ?? '');
$status    = trim($_GET['status'] ?? '');
$q         = trim($_GET['q'] ?? '');

$w = []; $params = [];
if ($projectId) { $w[] = "project_id = ?"; $params[] = $projectId; }
if ($tower !== '')  { $w[] = "tower = ?";  $params[] = $tower; }
if ($config !== '') { $w[] = "config = ?"; $params[] = $config; }
if ($status !== '') { $w[] = "status = ?"; $params[] = $status; }
if ($q !== '')      { $w[] = "(flat_no LIKE ? OR tower LIKE ?)"; $params[] = "%$q%"; $params[] = "%$q%"; }
$where = $w ? 'WHERE ' . implode(' AND ', $w) : '';

$total = (int) scalar("SELECT COUNT(*) FROM inventory $where", $params);
$flats = rows("SELECT * FROM inventory $where ORDER BY tower, floor LIMIT 8", $params);

$out = [];
foreach ($flats as $f) {
    $fr = freshness($f['last_verified_at']);
    $out[] = [
        'flat_no'     => $f['flat_no'],
        'tower'       => $f['tower'],
        'floor'       => (int)$f['floor'],
        'config'      => $f['config'],
        'carpet'      => num($f['carpet']),
        'facing'      => $f['facing'],
        'status'      => $f['status'],
        'statusColor' => status_color($f['status']),
        'price'       => money_full($f['price']),
        'verified'    => $f['last_verified_at'] ? date('d M Y g:i A', strtotime($f['last_verified_at'])) : 'Not verified',
        'freshColor'  => $fr['color'],
    ];
}
echo json_encode(['total' => $total, 'shown' => count($out), 'rows' => $out]);

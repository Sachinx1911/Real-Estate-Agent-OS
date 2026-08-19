<?php
/** RE360 — global search (Ctrl+K). Returns JSON. */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();
header('Content-Type: application/json');

$q = trim($_GET['q'] ?? '');
if (strlen($q) < 2) { echo '[]'; exit; }
$like = '%' . $q . '%';
$out = [];

foreach (rows("SELECT id, name, office_location FROM builders WHERE name LIKE ? LIMIT 5", [$like]) as $r) {
    $out[] = ['type'=>'Builder','color'=>'purple','title'=>$r['name'],'subtitle'=>$r['office_location'],'url'=>url('builder_view',['id'=>$r['id']])];
}
foreach (rows("SELECT id, name, node FROM projects WHERE name LIKE ? LIMIT 6", [$like]) as $r) {
    $out[] = ['type'=>'Project','color'=>'blue','title'=>$r['name'],'subtitle'=>$r['node'],'url'=>url('project_view',['id'=>$r['id']])];
}
foreach (rows("SELECT id, name, mobile FROM clients WHERE name LIKE ? OR mobile LIKE ? LIMIT 5", [$like,$like]) as $r) {
    $out[] = ['type'=>'Client','color'=>'teal','title'=>$r['name'],'subtitle'=>$r['mobile'],'url'=>url('client_view',['id'=>$r['id']])];
}
foreach (rows("SELECT i.id, i.flat_no, i.status, p.name pname, p.id pid FROM inventory i JOIN projects p ON p.id=i.project_id WHERE i.flat_no LIKE ? LIMIT 5", [$like]) as $r) {
    $out[] = ['type'=>'Flat','color'=>'green','title'=>$r['flat_no'].' · '.$r['status'],'subtitle'=>$r['pname'],'url'=>url('inventory',['project'=>$r['pid']])];
}

echo json_encode($out);

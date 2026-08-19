<?php
/** RE360 — Global inventory (flat-level) with freshness engine */
require_once __DIR__ . '/../includes/icons.php';
$page = 'inventory'; $pageTitle = 'Inventory';

$projectId = (int)($_GET['project'] ?? 0);
$status    = trim($_GET['status'] ?? '');
$config    = trim($_GET['config'] ?? '');
$node      = trim($_GET['node'] ?? '');
$fresh     = trim($_GET['fresh'] ?? '');
$q         = trim($_GET['q'] ?? '');
$perPage   = 25;
$pageNo    = max(1, (int)($_GET['p'] ?? 1));

$w = []; $params = [];
if ($projectId) { $w[]="i.project_id=?"; $params[]=$projectId; }
if ($status!=='') { $w[]="i.status=?"; $params[]=$status; }
if ($config!=='') { $w[]="i.config=?"; $params[]=$config; }
if ($node!=='')   { $w[]="p.node=?";   $params[]=$node; }
if ($q!=='')      { $w[]="(i.flat_no LIKE ? OR p.name LIKE ?)"; $params[]="%$q%"; $params[]="%$q%"; }
if ($fresh==='stale') { $w[]="(i.last_verified_at IS NULL OR i.last_verified_at < DATE_SUB(NOW(), INTERVAL 15 DAY))"; }
if ($fresh==='fresh') { $w[]="i.last_verified_at >= DATE_SUB(NOW(), INTERVAL 3 DAY)"; }
$where = $w ? 'WHERE '.implode(' AND ',$w) : '';

$total = (int) scalar("SELECT COUNT(*) FROM inventory i JOIN projects p ON p.id=i.project_id $where", $params);
$pages = max(1, (int)ceil($total / $perPage));
$pageNo = min($pageNo, $pages);
$offset = ($pageNo - 1) * $perPage;

$flats = rows("SELECT i.*, p.name AS pname, p.node, b.name AS bname
               FROM inventory i JOIN projects p ON p.id=i.project_id JOIN builders b ON b.id=p.builder_id
               $where ORDER BY p.name, i.tower, i.floor LIMIT $perPage OFFSET $offset", $params);

// summary counts (respecting filters except status)
$sumAvail = (int) scalar("SELECT COUNT(*) FROM inventory WHERE status='available'");
$sumHold  = (int) scalar("SELECT COUNT(*) FROM inventory WHERE status IN ('hold','token')");
$sumSold  = (int) scalar("SELECT COUNT(*) FROM inventory WHERE status IN ('sold','booked','registered','agreement')");
$sumStale = (int) scalar("SELECT COUNT(*) FROM inventory WHERE last_verified_at IS NULL OR last_verified_at < DATE_SUB(NOW(), INTERVAL 15 DAY)");

$projectsList = rows("SELECT id, name FROM projects ORDER BY name");
$nodes = rows("SELECT DISTINCT node FROM projects WHERE node<>'' ORDER BY node");

function inv_url(array $over = []): string {
    $base = ['page'=>'inventory','project'=>$_GET['project']??'','status'=>$_GET['status']??'','config'=>$_GET['config']??'',
             'node'=>$_GET['node']??'','fresh'=>$_GET['fresh']??'','q'=>$_GET['q']??''];
    return 'index.php?' . http_build_query(array_filter(array_merge($base, $over), fn($v)=>$v!=='' && $v!==null));
}
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
  <div><h2>Inventory</h2><p><?= number_format($total) ?> flats<?= $where?' matching filters':' across all projects' ?></p></div>
  <div style="display:flex;gap:8px">
    <a class="btn ghost" href="<?= inv_url(['fresh'=>'stale']) ?>"><?= icon('clock',15) ?> Needs verification (<?= $sumStale ?>)</a>
    <a class="btn primary" href="<?= url('inventory_form') ?>"><?= icon('plus',16) ?> Add Inventory</a>
  </div>
</div>

<div class="kpi-row" style="grid-template-columns:repeat(4,1fr);margin-bottom:18px">
  <div class="kpi"><div class="top"><div class="ic-box green"><?= icon('inventory',20) ?></div><div class="k-label">Available</div></div><div class="k-value"><?= number_format($sumAvail) ?></div></div>
  <div class="kpi"><div class="top"><div class="ic-box amber"><?= icon('clock',20) ?></div><div class="k-label">Hold / Token</div></div><div class="k-value"><?= number_format($sumHold) ?></div></div>
  <div class="kpi"><div class="top"><div class="ic-box blue"><?= icon('bookings',20) ?></div><div class="k-label">Booked / Sold</div></div><div class="k-value"><?= number_format($sumSold) ?></div></div>
  <div class="kpi"><div class="top"><div class="ic-box pink"><?= icon('refresh',20) ?></div><div class="k-label">Stale (&gt;15 days)</div></div><div class="k-value"><?= number_format($sumStale) ?></div></div>
</div>

<form class="card" style="margin-bottom:18px;padding:14px" method="get">
  <input type="hidden" name="page" value="inventory">
  <div style="display:flex;gap:10px;flex-wrap:wrap">
    <div class="search" style="flex:1;min-width:200px;background:var(--bg-card-2)">
      <?= icon('search',18) ?><input type="text" name="q" value="<?= e($q) ?>" placeholder="Search flat no. or project...">
    </div>
    <select class="select" name="project"><option value="">All Projects</option>
      <?php foreach ($projectsList as $pl): ?><option value="<?= $pl['id'] ?>" <?= $projectId==$pl['id']?'selected':'' ?>><?= e($pl['name']) ?></option><?php endforeach; ?></select>
    <select class="select" name="node"><option value="">All Locations</option>
      <?php foreach ($nodes as $n): ?><option value="<?= e($n['node']) ?>" <?= $node===$n['node']?'selected':'' ?>><?= e($n['node']) ?></option><?php endforeach; ?></select>
    <select class="select" name="config"><option value="">All BHK</option>
      <?php foreach ($GLOBALS['RE360_CONFIGS'] as $c): ?><option value="<?= e($c) ?>" <?= $config===$c?'selected':'' ?>><?= e($c) ?></option><?php endforeach; ?></select>
    <select class="select" name="status"><option value="">All Status</option>
      <?php foreach ($GLOBALS['RE360_INV_STATUS'] as $s): ?><option value="<?= $s ?>" <?= $status===$s?'selected':'' ?>><?= ucfirst($s) ?></option><?php endforeach; ?></select>
    <select class="select" name="fresh"><option value="">Any freshness</option>
      <option value="fresh" <?= $fresh==='fresh'?'selected':'' ?>>Verified &lt; 3 days</option>
      <option value="stale" <?= $fresh==='stale'?'selected':'' ?>>Stale &gt; 15 days</option></select>
    <button class="btn primary" type="submit">Filter</button>
    <?php if ($where): ?><a class="btn ghost" href="<?= url('inventory') ?>">Clear</a><?php endif; ?>
  </div>
</form>

<div class="card pad0">
  <div class="table-wrap">
    <table class="data">
      <thead><tr>
        <th>Flat No</th><th>Project</th><th>Location</th><th>Tower</th><th>Floor</th><th>Config</th>
        <th>Carpet</th><th>Facing</th><th>Status</th><th>Price</th><th>₹/sq.ft.</th><th>Last Verified</th>
      </tr></thead>
      <tbody>
      <?php foreach ($flats as $f): $fr = freshness($f['last_verified_at']); ?>
        <tr>
          <td class="strong"><?= e($f['flat_no']) ?></td>
          <td><a class="link" href="<?= url('project_view',['id'=>$f['project_id'],'tab'=>'inventory']) ?>"><?= e($f['pname']) ?></a></td>
          <td><?= e($f['node']) ?></td>
          <td><?= e($f['tower']) ?></td>
          <td><?= (int)$f['floor'] ?></td>
          <td><?= e($f['config']) ?></td>
          <td><?= num($f['carpet']) ?></td>
          <td><?= e($f['facing']) ?></td>
          <td><span class="badge <?= status_color($f['status']) ?>"><?= e($f['status']) ?></span></td>
          <td class="strong"><?= money_full($f['price']) ?></td>
          <td><?= price_per_sqft($f['price'], $f['carpet']) ?></td>
          <td><span class="fresh-dot" style="background:<?= $fr['color'] ?>"></span><span class="tiny"><?= e($fr['label']) ?></span></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$flats): ?><tr><td colspan="12" class="center muted" style="padding:36px">No inventory matches your filters.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php if ($pages > 1): ?>
  <div class="pager">
    <span class="muted small">Showing <?= $offset+1 ?>–<?= min($offset+$perPage,$total) ?> of <?= number_format($total) ?> entries</span>
    <div class="pages">
      <?php if ($pageNo>1): ?><a class="pg" href="<?= inv_url(['p'=>$pageNo-1]) ?>">‹</a><?php endif; ?>
      <?php for ($i=max(1,$pageNo-2); $i<=min($pages,$pageNo+2); $i++): ?>
        <a class="pg <?= $i===$pageNo?'active':'' ?>" href="<?= inv_url(['p'=>$i]) ?>"><?= $i ?></a>
      <?php endfor; ?>
      <?php if ($pageNo<$pages): ?><a class="pg" href="<?= inv_url(['p'=>$pageNo+1]) ?>">›</a><?php endif; ?>
    </div>
  </div>
  <?php endif; ?>
</div>

<div class="card mt" style="padding:14px">
  <div style="display:flex;gap:18px;flex-wrap:wrap;align-items:center">
    <span class="muted small"><strong style="color:var(--text)">Data freshness:</strong></span>
    <span class="small"><span class="fresh-dot" style="background:#22c55e"></span>Verified &lt; 3 days</span>
    <span class="small"><span class="fresh-dot" style="background:#eab308"></span>3–7 days</span>
    <span class="small"><span class="fresh-dot" style="background:#f59e0b"></span>7–15 days</span>
    <span class="small"><span class="fresh-dot" style="background:#ef4444"></span>&gt; 15 days — verify before quoting to a client</span>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>

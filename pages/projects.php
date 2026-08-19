<?php
/** RE360 — Projects list with filters */
require_once __DIR__ . '/../includes/icons.php';
$page = 'projects'; $pageTitle = 'Projects';

$q      = trim($_GET['q'] ?? '');
$node   = trim($_GET['node'] ?? '');
$status = trim($_GET['status'] ?? '');
$bhk    = trim($_GET['bhk'] ?? '');
$budget = (int)($_GET['budget'] ?? 0);

$w = []; $params = [];
if ($q !== '')      { $w[] = "p.name LIKE ?"; $params[] = "%$q%"; }
if ($node !== '')   { $w[] = "p.node = ?"; $params[] = $node; }
if ($status !== '') { $w[] = "p.status = ?"; $params[] = $status; }
if ($budget > 0)    { $w[] = "p.price_min <= ?"; $params[] = $budget; }
if ($bhk !== '')    { $w[] = "EXISTS (SELECT 1 FROM project_configurations c WHERE c.project_id=p.id AND c.config=?)"; $params[] = $bhk; }
$where = $w ? 'WHERE ' . implode(' AND ', $w) : '';

$projects = rows("SELECT p.*, b.name AS builder_name,
     (SELECT COUNT(*) FROM inventory i WHERE i.project_id=p.id AND i.status='available') AS avail,
     (SELECT GROUP_CONCAT(DISTINCT c.config ORDER BY c.config SEPARATOR ', ') FROM project_configurations c WHERE c.project_id=p.id) AS configs
   FROM projects p JOIN builders b ON b.id=p.builder_id
   $where ORDER BY p.is_featured DESC, p.name", $params);

$nodes = rows("SELECT DISTINCT node FROM projects WHERE node<>'' ORDER BY node");
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
  <div><h2>Projects</h2><p><?= count($projects) ?> projects<?= $where ? ' matching your filters' : ' in your database' ?></p></div>
  <a class="btn primary" href="<?= url('project_form') ?>"><?= icon('plus',16) ?> Add Project</a>
</div>

<form class="card" style="margin-bottom:18px;padding:14px" method="get">
  <input type="hidden" name="page" value="projects">
  <div style="display:flex;gap:10px;flex-wrap:wrap">
    <div class="search" style="flex:1;min-width:220px;background:var(--bg-card-2)">
      <?= icon('search',18) ?><input type="text" name="q" value="<?= e($q) ?>" placeholder="Search project name...">
    </div>
    <select class="select" name="node"><option value="">All Locations</option>
      <?php foreach ($nodes as $n): ?><option value="<?= e($n['node']) ?>" <?= $node===$n['node']?'selected':'' ?>><?= e($n['node']) ?></option><?php endforeach; ?>
    </select>
    <select class="select" name="status"><option value="">All Status</option>
      <?php foreach ($GLOBALS['RE360_PROJECT_STATUS'] as $k=>$v): ?><option value="<?= $k ?>" <?= $status===$k?'selected':'' ?>><?= $v ?></option><?php endforeach; ?>
    </select>
    <select class="select" name="bhk"><option value="">All BHK</option>
      <?php foreach ($GLOBALS['RE360_CONFIGS'] as $c): ?><option value="<?= e($c) ?>" <?= $bhk===$c?'selected':'' ?>><?= e($c) ?></option><?php endforeach; ?>
    </select>
    <select class="select" name="budget"><option value="0">Any Budget</option>
      <?php foreach ([5000000=>'Under ₹50 L',7500000=>'Under ₹75 L',10000000=>'Under ₹1 Cr',12500000=>'Under ₹1.25 Cr',15000000=>'Under ₹1.5 Cr'] as $v=>$l): ?>
        <option value="<?= $v ?>" <?= $budget===$v?'selected':'' ?>><?= $l ?></option><?php endforeach; ?>
    </select>
    <button class="btn primary" type="submit">Filter</button>
    <?php if ($where): ?><a class="btn ghost" href="<?= url('projects') ?>">Clear</a><?php endif; ?>
  </div>
</form>

<div class="grid" style="grid-template-columns:repeat(3,1fr)">
  <?php foreach ($projects as $p): ?>
    <a class="card" href="<?= url('project_view',['id'=>$p['id']]) ?>">
      <div style="height:120px;border-radius:11px;background:linear-gradient(135deg,#1a2544,#0e1428);display:grid;place-items:center;color:var(--text-muted);margin-bottom:12px">
        <?= icon('building',36) ?>
      </div>
      <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:8px">
        <div>
          <div style="font-weight:700;font-size:15px"><?= e($p['name']) ?></div>
          <div class="muted small">by <?= e($p['builder_name']) ?></div>
        </div>
        <?php if ($p['is_featured']): ?><span class="badge violet">Featured</span><?php endif; ?>
      </div>
      <div class="muted small" style="margin-top:6px"><?= icon('location',12) ?> <?= e($p['node']) ?><?= $p['sector'] ? ', Sector '.e($p['sector']) : '' ?></div>
      <div class="chip-row" style="margin-top:10px">
        <span class="badge <?= $p['status']==='ready'?'green':($p['status']==='new_launch'?'violet':($p['status']==='upcoming'?'gold':'blue')) ?>"><?= $GLOBALS['RE360_PROJECT_STATUS'][$p['status']] ?? $p['status'] ?></span>
        <?php if ($p['rera_verified']): ?><span class="badge teal">RERA</span><?php endif; ?>
        <span class="badge grey"><?= (int)$p['avail'] ?> available</span>
      </div>
      <div style="display:flex;justify-content:space-between;margin-top:12px;padding-top:12px;border-top:1px solid var(--border-soft)">
        <div><div class="muted tiny">Price</div><div style="font-weight:700;font-size:13px"><?= money($p['price_min']) ?>+</div></div>
        <div style="text-align:right"><div class="muted tiny">Possession</div><div style="font-weight:700;font-size:13px"><?= e($p['possession_label'] ?: '—') ?></div></div>
      </div>
      <div class="muted tiny" style="margin-top:8px"><?= e($p['configs'] ?: '—') ?></div>
    </a>
  <?php endforeach; ?>
  <?php if (!$projects): ?><div class="card empty" style="grid-column:1/-1">No projects match your filters.</div><?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>

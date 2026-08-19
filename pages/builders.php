<?php
/** RE360 — Builders list */
require_once __DIR__ . '/../includes/icons.php';
$page = 'builders'; $pageTitle = 'Builders';

$search = trim($_GET['q'] ?? '');
$where = ''; $params = [];
if ($search !== '') { $where = "WHERE name LIKE ? OR office_location LIKE ?"; $params = ["%$search%","%$search%"]; }

$builders = rows("SELECT b.*,
    (SELECT COUNT(*) FROM projects p WHERE p.builder_id=b.id) AS proj_count,
    ROUND((score_construction+score_delivery+score_location+score_pricing+score_reputation+score_documentation)/6,1) AS avg_score
    FROM builders b $where ORDER BY name", $params);

require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
  <div><h2>Builders</h2><p><?= count($builders) ?> builders in your network</p></div>
  <a class="btn primary" href="<?= url('builder_form') ?>"><?= icon('plus',16) ?> Add Builder</a>
</div>

<form class="card" style="margin-bottom:18px;padding:12px" method="get">
  <input type="hidden" name="page" value="builders">
  <div class="search" style="max-width:none;background:var(--bg-card-2)">
    <?= icon('search',18) ?>
    <input type="text" name="q" value="<?= e($search) ?>" placeholder="Search builders by name or location...">
  </div>
</form>

<div class="grid" style="grid-template-columns:repeat(3,1fr)">
  <?php foreach ($builders as $b):
    $sc = (float)$b['avg_score']; $scCls = $sc>=8?'green':($sc>=7?'amber':'grey'); ?>
    <a class="card" href="<?= url('builder_view',['id'=>$b['id']]) ?>">
      <div style="display:flex;align-items:center;gap:12px">
        <div class="ic-box purple" style="width:46px;height:46px;font-weight:800;font-size:16px"><?= e(strtoupper(substr($b['name'],0,2))) ?></div>
        <div style="flex:1">
          <div style="font-weight:700;font-size:15px"><?= e($b['name']) ?></div>
          <div class="muted small"><?= icon('location',12) ?> <?= e($b['office_location'] ?: '—') ?></div>
        </div>
        <span class="badge <?= $scCls ?>"><?= $sc ?>/10</span>
      </div>
      <div class="grid" style="grid-template-columns:repeat(3,1fr);gap:8px;margin-top:14px;text-align:center">
        <div><div style="font-weight:800"><?= (int)$b['proj_count'] ?></div><div class="muted tiny">Projects</div></div>
        <div><div style="font-weight:800"><?= (int)$b['delivered_projects'] ?></div><div class="muted tiny">Delivered</div></div>
        <div><div style="font-weight:800"><?= (int)$b['years_in_business'] ?></div><div class="muted tiny">Years</div></div>
      </div>
    </a>
  <?php endforeach; ?>
  <?php if (!$builders): ?><div class="card empty full">No builders found. <a class="link" href="<?= url('builder_form') ?>">Add one →</a></div><?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>

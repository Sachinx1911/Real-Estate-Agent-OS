<?php
/** RE360 — Side-by-side project comparison (the consultant view) */
require_once __DIR__ . '/../includes/icons.php';
$page = 'comparisons'; $pageTitle = 'Comparisons';

$ids = array_slice(array_filter(array_map('intval', (array)($_GET['ids'] ?? []))), 0, 4);
$selected = [];
if ($ids) {
    $in = implode(',', array_fill(0, count($ids), '?'));
    $selected = rows("SELECT p.*, b.name AS builder_name,
          ROUND((b.score_construction+b.score_delivery+b.score_location+b.score_pricing+b.score_reputation+b.score_documentation)/6,1) AS builder_score,
          (SELECT COUNT(*) FROM inventory i WHERE i.project_id=p.id AND i.status='available') AS avail,
          (SELECT GROUP_CONCAT(DISTINCT c.config ORDER BY c.config SEPARATOR ', ') FROM project_configurations c WHERE c.project_id=p.id) AS configs,
          (SELECT MIN(c.carpet_area) FROM project_configurations c WHERE c.project_id=p.id) AS min_carpet,
          (SELECT MAX(c.carpet_area) FROM project_configurations c WHERE c.project_id=p.id) AS max_carpet,
          (SELECT ROUND(AVG(i.price / NULLIF(i.carpet,0))) FROM inventory i WHERE i.project_id=p.id AND i.carpet>0) AS avg_psf,
          (SELECT COUNT(*) FROM project_amenities pa WHERE pa.project_id=p.id) AS amen_count
        FROM projects p JOIN builders b ON b.id=p.builder_id WHERE p.id IN ($in)", $ids);
}
$allProjects = rows("SELECT p.id, p.name, p.node, b.name bname FROM projects p JOIN builders b ON b.id=p.builder_id ORDER BY p.name");
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
  <div><h2>Project Comparison</h2><p>Compare up to 4 projects side by side — talk to clients like a consultant</p></div>
  <?php if ($selected): ?><a class="btn ghost" href="<?= url('comparisons') ?>">Clear</a><?php endif; ?>
</div>

<form method="get" class="card" style="margin-bottom:18px">
  <input type="hidden" name="page" value="comparisons">
  <div class="card-head"><h3>Select projects (max 4)</h3><button class="btn primary sm" type="submit">Compare →</button></div>
  <div class="grid" style="grid-template-columns:repeat(4,1fr);gap:10px;max-height:250px;overflow-y:auto">
    <?php foreach ($allProjects as $p): ?>
      <label class="chip" style="cursor:pointer;<?= in_array($p['id'],$ids)?'border-color:var(--primary);background:var(--primary-soft)':'' ?>">
        <input type="checkbox" name="ids[]" value="<?= $p['id'] ?>" <?= in_array($p['id'],$ids)?'checked':'' ?>>
        <span><?= e($p['name']) ?><br><span class="muted tiny"><?= e($p['node']) ?></span></span>
      </label>
    <?php endforeach; ?>
  </div>
</form>

<?php if (!$selected): ?>
  <div class="card empty"><?= icon('compare',44) ?><div style="margin-top:10px">Select 2–4 projects above and hit Compare.</div></div>
<?php else: ?>
<div class="card pad0">
  <div class="table-wrap">
    <table class="data">
      <thead>
        <tr><th style="min-width:150px">Parameter</th>
          <?php foreach ($selected as $s): ?><th style="min-width:170px"><?= e($s['name']) ?></th><?php endforeach; ?></tr>
      </thead>
      <tbody>
        <?php
        // best-value helpers
        $minPrice = min(array_map(fn($s)=>(int)$s['price_min'] ?: PHP_INT_MAX, $selected));
        $maxAvail = max(array_map(fn($s)=>(int)$s['avail'], $selected));
        $minPsf   = min(array_map(fn($s)=>(int)$s['avg_psf'] ?: PHP_INT_MAX, $selected));
        $maxCarp  = max(array_map(fn($s)=>(int)$s['max_carpet'], $selected));

        $rowsDef = [
          ['Builder',      fn($s)=>e($s['builder_name'])],
          ['Builder Score',fn($s)=>'<span class="badge '.($s['builder_score']>=8?'green':($s['builder_score']>=7?'amber':'grey')).'">'.$s['builder_score'].'/10</span>'],
          ['Location',     fn($s)=>e($s['node'].($s['sector']?', Sector '.$s['sector']:''))],
          ['Status',       fn($s)=>'<span class="badge '.($s['status']==='ready'?'green':'blue').'">'.e($GLOBALS['RE360_PROJECT_STATUS'][$s['status']] ?? $s['status']).'</span>'],
          ['Possession',   fn($s)=>e($s['possession_label'] ?: '—')],
          ['Configurations',fn($s)=>e($s['configs'] ?: '—')],
          ['Carpet Range', fn($s)=>$s['min_carpet'] ? num($s['min_carpet']).'–'.num($s['max_carpet']).' sq.ft.'
                                    . ((int)$s['max_carpet']===$maxCarp ? ' <span class="badge green">Largest</span>' : '') : '—'],
          ['Price From',   fn($s)=>money($s['price_min']) . ((int)$s['price_min']===$minPrice ? ' <span class="badge green">Lowest</span>' : '')],
          ['Price To',     fn($s)=>money($s['price_max'])],
          ['Avg ₹/sq.ft.', fn($s)=>$s['avg_psf'] ? '₹'.num($s['avg_psf']) . ((int)$s['avg_psf']===$minPsf ? ' <span class="badge green">Best value</span>' : '') : '—'],
          ['Available Flats',fn($s)=>'<strong>'.num($s['avail']).'</strong>' . ((int)$s['avail']===$maxAvail && $maxAvail>0 ? ' <span class="badge green">Most</span>' : '')],
          ['Total Towers', fn($s)=>(int)$s['total_towers']],
          ['Total Units',  fn($s)=>num($s['total_units'])],
          ['Amenities',    fn($s)=>(int)$s['amen_count'].' listed'],
          ['MahaRERA',     fn($s)=>e($s['maharera_no'] ?: '—') . ($s['rera_verified'] ? ' <span class="badge teal">Verified</span>' : '')],
          ['Best For',     fn($s)=>e($s['best_for'] ?: '—')],
          ['Budget Band',  fn($s)=>e($s['budget_band'] ?: '—')],
        ];
        foreach ($rowsDef as $rd): ?>
          <tr>
            <td class="strong"><?= $rd[0] ?></td>
            <?php foreach ($selected as $s): ?><td style="white-space:normal"><?= $rd[1]($s) ?></td><?php endforeach; ?>
          </tr>
        <?php endforeach; ?>

        <tr>
          <td class="strong">Strengths</td>
          <?php foreach ($selected as $s): ?>
            <td style="white-space:normal">
              <?php foreach (array_slice(array_filter(array_map('trim', explode("\n", $s['strengths'] ?? ''))),0,3) as $x): ?>
                <div class="tiny" style="color:var(--green)">✓ <?= e($x) ?></div>
              <?php endforeach; ?>
            </td>
          <?php endforeach; ?>
        </tr>
        <tr>
          <td class="strong">Watch-outs</td>
          <?php foreach ($selected as $s): ?>
            <td style="white-space:normal">
              <?php foreach (array_slice(array_filter(array_map('trim', explode("\n", $s['weaknesses'] ?? ''))),0,3) as $x): ?>
                <div class="tiny" style="color:var(--amber)">⚠ <?= e($x) ?></div>
              <?php endforeach; ?>
            </td>
          <?php endforeach; ?>
        </tr>
        <tr>
          <td class="strong"></td>
          <?php foreach ($selected as $s): ?>
            <td><a class="btn ghost sm" href="<?= url('project_view',['id'=>$s['id']]) ?>">Open project →</a></td>
          <?php endforeach; ?>
        </tr>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>

<?php
/** RE360 — Reports & Analytics */
require_once __DIR__ . '/../includes/icons.php';
$page = 'reports'; $pageTitle = 'Reports & Analytics';

/* Inventory by location */
$byLoc = rows("SELECT p.node AS loc,
        COUNT(*) AS total,
        SUM(i.status='available') AS available,
        SUM(i.status IN ('hold','token')) AS held,
        SUM(i.status IN ('booked','sold','agreement','registered')) AS sold,
        COALESCE(SUM(CASE WHEN i.status='available' THEN i.price ELSE 0 END),0) AS avail_value,
        COUNT(DISTINCT p.id) AS projects
      FROM inventory i JOIN projects p ON p.id=i.project_id
      GROUP BY p.node ORDER BY available DESC");

/* By configuration */
$byConfig = rows("SELECT config, COUNT(*) total, SUM(status='available') available
                  FROM inventory GROUP BY config ORDER BY available DESC");

/* Lead conversion */
$pipeline = [];
foreach (rows("SELECT status, COUNT(*) c FROM clients GROUP BY status") as $r) { $pipeline[$r['status']] = (int)$r['c']; }
$totalLeads = array_sum($pipeline) ?: 1;
$booked = $pipeline['booked'] ?? 0;
$convRate = round($booked / $totalLeads * 100, 1);

/* Price per sq.ft. by location */
$ppsf = rows("SELECT p.node AS loc, ROUND(AVG(i.price / NULLIF(i.carpet,0))) AS avg_psf,
              MIN(ROUND(i.price / NULLIF(i.carpet,0))) AS min_psf, MAX(ROUND(i.price / NULLIF(i.carpet,0))) AS max_psf
            FROM inventory i JOIN projects p ON p.id=i.project_id
            WHERE i.carpet > 0 GROUP BY p.node ORDER BY avg_psf DESC");

/* Freshness health */
$freshCounts = [
  'fresh' => (int) scalar("SELECT COUNT(*) FROM inventory WHERE last_verified_at >= DATE_SUB(NOW(), INTERVAL 3 DAY)"),
  'good'  => (int) scalar("SELECT COUNT(*) FROM inventory WHERE last_verified_at < DATE_SUB(NOW(), INTERVAL 3 DAY) AND last_verified_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"),
  'warn'  => (int) scalar("SELECT COUNT(*) FROM inventory WHERE last_verified_at < DATE_SUB(NOW(), INTERVAL 7 DAY) AND last_verified_at >= DATE_SUB(NOW(), INTERVAL 15 DAY)"),
  'stale' => (int) scalar("SELECT COUNT(*) FROM inventory WHERE last_verified_at IS NULL OR last_verified_at < DATE_SUB(NOW(), INTERVAL 15 DAY)"),
];
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
  <div><h2>Reports &amp; Analytics</h2><p>Where your sellable inventory is, and how the pipeline is moving</p></div>
</div>

<!-- Inventory by location — the key question -->
<div class="card">
  <div class="card-head"><h3>Where do we have inventory?</h3><span class="muted small">available units by location</span></div>
  <div class="table-wrap">
    <table class="data">
      <thead><tr><th>Location</th><th>Projects</th><th>Total Flats</th><th>Available</th><th>Hold/Token</th><th>Booked/Sold</th><th>Available Value</th></tr></thead>
      <tbody>
      <?php foreach ($byLoc as $r): ?>
        <tr>
          <td class="strong"><?= e($r['loc'] ?: 'Others') ?></td>
          <td><?= (int)$r['projects'] ?></td>
          <td><?= num($r['total']) ?></td>
          <td><span class="badge green"><?= num($r['available']) ?></span></td>
          <td><span class="badge amber"><?= num($r['held']) ?></span></td>
          <td><span class="badge blue"><?= num($r['sold']) ?></span></td>
          <td class="strong"><?= money($r['avail_value']) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$byLoc): ?><tr><td colspan="7" class="center muted" style="padding:30px">No inventory data yet.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="grid mt" style="grid-template-columns:1fr 1fr">
  <!-- By configuration -->
  <div class="card">
    <div class="card-head"><h3>Inventory by Configuration</h3></div>
    <?php $cMax = 0; foreach ($byConfig as $c) { $cMax = max($cMax,(int)$c['available']); } ?>
    <?php foreach ($byConfig as $c): $pct = $cMax ? round($c['available']/$cMax*100) : 0; ?>
      <div style="margin-bottom:12px">
        <div style="display:flex;justify-content:space-between;margin-bottom:5px">
          <span style="font-size:13px;color:var(--text-2)"><?= e($c['config'] ?: '—') ?></span>
          <span style="font-weight:700;font-size:13px"><?= num($c['available']) ?> <span class="muted tiny">/ <?= num($c['total']) ?></span></span>
        </div>
        <div class="bar-track"><div class="bar-fill" style="width:<?= $pct ?>%"></div></div>
      </div>
    <?php endforeach; ?>
    <?php if (!$byConfig): ?><div class="muted small">No data.</div><?php endif; ?>
  </div>

  <!-- Lead conversion -->
  <div class="card">
    <div class="card-head"><h3>Lead Pipeline</h3><span class="badge <?= $convRate>=20?'green':'amber' ?>"><?= $convRate ?>% conversion</span></div>
    <?php $labels = ['new'=>'New','contacted'=>'Contacted','site_visit'=>'Site Visit','negotiation'=>'Negotiation','booked'=>'Booked','lost'=>'Lost'];
      $pMax = max($pipeline ?: [1]);
      foreach ($labels as $k=>$l): $val = $pipeline[$k] ?? 0; $pct = $pMax ? round($val/$pMax*100) : 0; ?>
      <div style="margin-bottom:12px">
        <div style="display:flex;justify-content:space-between;margin-bottom:5px">
          <span style="font-size:13px;color:var(--text-2)"><?= $l ?></span>
          <span style="font-weight:700;font-size:13px"><?= $val ?></span>
        </div>
        <div class="bar-track"><div class="bar-fill" style="width:<?= $pct ?>%;<?= $k==='lost'?'background:var(--grey)':'' ?>"></div></div>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<div class="grid mt" style="grid-template-columns:1.3fr 1fr">
  <!-- ₹/sq.ft. benchmark -->
  <div class="card">
    <div class="card-head"><h3>Price per sq.ft. by Location</h3><span class="muted small">market benchmark</span></div>
    <div class="table-wrap">
      <table class="data">
        <thead><tr><th>Location</th><th>Average</th><th>Lowest</th><th>Highest</th></tr></thead>
        <tbody>
        <?php foreach ($ppsf as $r): ?>
          <tr><td class="strong"><?= e($r['loc'] ?: '—') ?></td>
            <td class="strong">₹<?= num($r['avg_psf']) ?></td>
            <td>₹<?= num($r['min_psf']) ?></td>
            <td>₹<?= num($r['max_psf']) ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$ppsf): ?><tr><td colspan="4" class="center muted" style="padding:26px">No pricing data.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Data freshness health -->
  <div class="card">
    <div class="card-head"><h3>Data Freshness Health</h3></div>
    <?php
      $fTotal = array_sum($freshCounts) ?: 1;
      $fMeta = ['fresh'=>['Verified < 3 days','#22c55e'],'good'=>['3–7 days','#eab308'],
                'warn'=>['7–15 days','#f59e0b'],'stale'=>['Over 15 days / never','#ef4444']];
      foreach ($fMeta as $k=>$mm): $val = $freshCounts[$k]; $pct = round($val/$fTotal*100); ?>
      <div style="margin-bottom:12px">
        <div style="display:flex;justify-content:space-between;margin-bottom:5px">
          <span style="font-size:13px;color:var(--text-2)"><span class="fresh-dot" style="background:<?= $mm[1] ?>"></span><?= $mm[0] ?></span>
          <span style="font-weight:700;font-size:13px"><?= number_format($val) ?> <span class="muted tiny">(<?= $pct ?>%)</span></span>
        </div>
        <div class="bar-track"><div style="height:100%;width:<?= $pct ?>%;background:<?= $mm[1] ?>;border-radius:20px"></div></div>
      </div>
    <?php endforeach; ?>
    <?php if ($freshCounts['stale'] > 0): ?>
      <a class="btn ghost sm" href="<?= url('inventory',['fresh'=>'stale']) ?>" style="margin-top:8px"><?= icon('refresh',14) ?> Verify stale inventory</a>
    <?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>

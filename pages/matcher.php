<?php
/** RE360 — Client ↔ Project matching engine */
require_once __DIR__ . '/../includes/icons.php';
$page = 'leads'; $pageTitle = 'Client Matcher';

$clientId = (int)($_GET['client'] ?? 0);
$client = null; $req = null;

// Prefill from an existing client, or from the form
if ($clientId) {
    $client = row("SELECT * FROM clients WHERE id=?", [$clientId]);
    $req    = row("SELECT * FROM client_requirements WHERE client_id=?", [$clientId]);
}
$locations = array_filter(array_map('trim', explode(',', $_GET['locations'] ?? (($req['preferred_location'] ?? '') . ',' . ($req['alt_location'] ?? '')))));
$bhk       = $_GET['bhk']    ?? ($req['bhk'] ?? '2 BHK');
$budget    = (int)($_GET['budget'] ?? ($req['all_in_budget'] ?? 10000000));
$minCarpet = (int)($_GET['carpet'] ?? ($req['min_carpet'] ?? 0));
$possMonths= (int)($_GET['poss'] ?? ($req['possession_within_months'] ?? 36));

$reqArr = ['locations'=>$locations, 'bhk'=>$bhk, 'all_in_budget'=>$budget,
           'min_carpet'=>$minCarpet, 'possession_within_months'=>$possMonths];

$fit = match_fit_sql();
$all = rows("SELECT p.*, b.name AS builder_name,
        GROUP_CONCAT(DISTINCT c.config SEPARATOR ',') AS configs,
        MAX(c.carpet_area) AS max_carpet,
        (SELECT COUNT(*) FROM inventory i WHERE i.project_id=p.id AND i.status='available') AS avail,
        $fit
     FROM projects p JOIN builders b ON b.id=p.builder_id
     LEFT JOIN project_configurations c ON c.project_id=p.id
     WHERE p.status IN ('under_construction','ready','new_launch')
     GROUP BY p.id", [$bhk, $bhk, $bhk]);

$results = [];
foreach ($all as $p) {
    $m = match_score($reqArr, $p);
    $results[] = ['p'=>$p, 'score'=>$m['score'], 'reasons'=>$m['reasons'], 'fit'=>$m['fit_count']];
}
usort($results, fn($a,$b)=>$b['score']-$a['score']);
$totalFlats = 0;
foreach ($results as $r) { if ($r['score']>=70) $totalFlats += (int)$r['fit']; }
$goodMatches = count(array_filter($results, fn($r)=>$r['score']>=70));

$nodes = rows("SELECT DISTINCT node FROM projects WHERE node<>'' ORDER BY node");
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
  <div>
    <h2>Client Requirement Matcher</h2>
    <p><?= $client ? 'Matching for <strong>'.e($client['name']).'</strong>' : 'Enter a requirement to find matching projects' ?></p>
  </div>
  <?php if ($client): ?><a class="btn ghost" href="<?= url('client_view',['id'=>$client['id']]) ?>">← Client profile</a><?php endif; ?>
</div>

<form class="card" style="margin-bottom:18px" method="get">
  <input type="hidden" name="page" value="matcher">
  <?php if ($clientId): ?><input type="hidden" name="client" value="<?= $clientId ?>"><?php endif; ?>
  <div class="grid" style="grid-template-columns:repeat(5,1fr);gap:14px">
    <div class="form-group"><label>Locations (comma separated)</label>
      <input class="field-input" name="locations" value="<?= e(implode(', ', $locations)) ?>" placeholder="Panvel, Kamothe" list="nodeList">
      <datalist id="nodeList"><?php foreach ($nodes as $n): ?><option value="<?= e($n['node']) ?>"><?php endforeach; ?></datalist></div>
    <div class="form-group"><label>Configuration</label>
      <select class="select" name="bhk"><?php foreach ($GLOBALS['RE360_CONFIGS'] as $c): ?>
        <option value="<?= e($c) ?>" <?= $bhk===$c?'selected':'' ?>><?= e($c) ?></option><?php endforeach; ?></select></div>
    <div class="form-group"><label>All-in Budget (₹)</label>
      <input class="field-input" type="number" name="budget" value="<?= $budget ?>" step="100000"></div>
    <div class="form-group"><label>Min Carpet (sq.ft.)</label>
      <input class="field-input" type="number" name="carpet" value="<?= $minCarpet ?>" step="10"></div>
    <div class="form-group"><label>Possession within (months)</label>
      <input class="field-input" type="number" name="poss" value="<?= $possMonths ?>" step="6"></div>
  </div>
  <button class="btn primary" type="submit" style="margin-top:14px"><?= icon('search',16) ?> Find Matches</button>
</form>

<div class="kpi-row" style="grid-template-columns:repeat(3,1fr);margin-bottom:18px">
  <div class="kpi"><div class="top"><div class="ic-box green"><?= icon('projects',20) ?></div><div class="k-label">Matching Projects</div></div><div class="k-value"><?= $goodMatches ?></div><div class="k-foot muted">70%+ match score</div></div>
  <div class="kpi"><div class="top"><div class="ic-box blue"><?= icon('inventory',20) ?></div><div class="k-label">Matching Flats</div></div><div class="k-value"><?= number_format($totalFlats) ?></div><div class="k-foot muted">available right now</div></div>
  <div class="kpi"><div class="top"><div class="ic-box gold"><?= icon('money',20) ?></div><div class="k-label">Budget</div></div><div class="k-value" style="font-size:22px"><?= money($budget) ?></div><div class="k-foot muted">all-in</div></div>
</div>

<div class="grid" style="grid-template-columns:repeat(3,1fr)">
  <?php foreach (array_slice($results,0,12) as $r): $p=$r['p']; $s=$r['score'];
    $cls = $s>=90?'':($s>=75?'mid':'low'); ?>
    <div class="card">
      <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:10px">
        <div>
          <a href="<?= url('project_view',['id'=>$p['id']]) ?>" style="font-weight:700;font-size:15px"><?= e($p['name']) ?></a>
          <div class="muted small">by <?= e($p['builder_name']) ?></div>
          <div class="muted small"><?= icon('location',12) ?> <?= e($p['node']) ?></div>
        </div>
        <span class="match-badge <?= $cls ?>"><?= $s ?>%</span>
      </div>

      <div style="display:flex;justify-content:space-between;margin:12px 0;padding:10px 0;border-top:1px solid var(--border-soft);border-bottom:1px solid var(--border-soft)">
        <div><div class="muted tiny"><?= e($bhk) ?> from</div>
          <div style="font-weight:700;font-size:13px"><?= $p['fit_price_min'] ? money($p['fit_price_min']) : money($p['price_min']) ?></div></div>
        <div><div class="muted tiny">Carpet up to</div>
          <div style="font-weight:700;font-size:13px"><?= num($p['fit_carpet_max'] ?: $p['max_carpet'], '—') ?> sq.ft.</div></div>
        <div style="text-align:right"><div class="muted tiny">Matching flats</div>
          <div style="font-weight:700;font-size:13px;color:<?= $r['fit'] ? 'var(--green)' : 'var(--text-muted)' ?>"><?= (int)$r['fit'] ?> of <?= (int)$p['avail'] ?></div></div>
      </div>

      <div style="display:flex;flex-wrap:wrap;gap:6px">
        <?php foreach ($r['reasons'] as $rs): ?>
          <span class="badge <?= $rs['ok']?'green':'amber' ?>"><?= $rs['ok']?'✓':'⚠' ?> <?= e($rs['label']) ?></span>
        <?php endforeach; ?>
      </div>

      <div style="display:flex;justify-content:space-between;align-items:center;margin-top:12px">
        <span class="muted tiny"><?= e($p['possession_label'] ?: '—') ?></span>
        <a class="btn ghost sm" href="<?= url('project_view',['id'=>$p['id'],'tab'=>'sales']) ?>">Sales card →</a>
      </div>
    </div>
  <?php endforeach; ?>
  <?php if (!$results): ?><div class="card empty" style="grid-column:1/-1">No projects available to match.</div><?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>

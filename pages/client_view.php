<?php
/** RE360 — Client profile with requirement + matches */
require_once __DIR__ . '/../includes/icons.php';
$page = 'leads'; $pageTitle = 'Client Profile';

$id = (int)($_GET['id'] ?? 0);
$c = row("SELECT * FROM clients WHERE id=?", [$id]);
if (!$c) { require __DIR__ . '/../includes/header.php'; echo '<div class="card empty">Client not found.</div>'; require __DIR__ . '/../includes/footer.php'; return; }
$r = row("SELECT * FROM client_requirements WHERE client_id=?", [$id]);
$visits = rows("SELECT sv.*, p.name AS pname FROM site_visits sv LEFT JOIN projects p ON p.id=sv.project_id WHERE sv.client_id=? ORDER BY sv.visit_date DESC", [$id]);
$bookings = rows("SELECT b.*, p.name AS pname FROM bookings b JOIN projects p ON p.id=b.project_id WHERE b.client_id=?", [$id]);

// quick top-3 matches
$topMatches = [];
if ($r) {
    $reqArr = ['locations'=>array_filter([$r['preferred_location'], $r['alt_location']]), 'bhk'=>$r['bhk'],
               'all_in_budget'=>(int)$r['all_in_budget'], 'min_carpet'=>(int)$r['min_carpet'],
               'possession_within_months'=>(int)$r['possession_within_months']];
    $fitSql = match_fit_sql();
    $all = rows("SELECT p.*, GROUP_CONCAT(DISTINCT c.config SEPARATOR ',') AS configs, MAX(c.carpet_area) AS max_carpet,
                   (SELECT COUNT(*) FROM inventory i WHERE i.project_id=p.id AND i.status='available') AS avail,
                   $fitSql
                 FROM projects p LEFT JOIN project_configurations c ON c.project_id=p.id
                 WHERE p.status IN ('under_construction','ready','new_launch') GROUP BY p.id",
                [$r['bhk'], $r['bhk'], $r['bhk']]);
    foreach ($all as $p) { $m = match_score($reqArr, $p); $topMatches[] = ['p'=>$p,'score'=>$m['score'],'fit'=>$m['fit_count']]; }
    usort($topMatches, fn($a,$b)=>$b['score']-$a['score']);
    $topMatches = array_slice($topMatches, 0, 3);
}
$statusColors = ['new'=>'blue','contacted'=>'violet','site_visit'=>'amber','negotiation'=>'gold','booked'=>'green','lost'=>'grey'];
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
  <div>
    <h2><?= e($c['name']) ?> <span class="badge <?= $statusColors[$c['status']] ?? 'grey' ?>"><?= e(ucwords(str_replace('_',' ',$c['status']))) ?></span></h2>
    <p><?= e($c['mobile']) ?> <?= $c['email'] ? '· '.e($c['email']) : '' ?> <?= $c['profession'] ? '· '.e($c['profession']) : '' ?></p>
  </div>
  <div style="display:flex;gap:8px">
    <a class="btn ghost" href="<?= url('leads') ?>">← Back</a>
    <a class="btn ghost" href="<?= url('client_form',['id'=>$c['id']]) ?>">Edit</a>
    <a class="btn primary" href="<?= url('matcher',['client'=>$c['id']]) ?>"><?= icon('search',15) ?> Find Matches</a>
  </div>
</div>

<div class="grid" style="grid-template-columns:1fr 1fr">
  <!-- Requirement -->
  <div class="card">
    <div class="card-head"><h3>Requirement</h3></div>
    <?php if ($r): ?>
      <div class="grid" style="grid-template-columns:1fr 1fr;gap:14px">
        <?php foreach ([
          ['Preferred Location',$r['preferred_location']], ['Alternative',$r['alt_location']],
          ['Configuration',$r['bhk']], ['Min Carpet',$r['min_carpet'] ? num($r['min_carpet']).' sq.ft.' : ''],
          ['Possession within',$r['possession_within_months'].' months'], ['Type', ucwords(str_replace('_',' ',$r['ready_or_uc']))],
          ['Preferred Floor',$r['preferred_floor']], ['Facing',$r['facing']],
          ['Purpose', ucwords(str_replace('_',' ',$c['purpose']))], ['Builder Preference',$r['builder_pref']],
        ] as $f): ?>
          <div><div class="muted tiny"><?= e($f[0]) ?></div><div style="font-size:13px;font-weight:600;margin-top:2px"><?= e($f[1] ?: '—') ?></div></div>
        <?php endforeach; ?>
      </div>
    <?php else: ?><div class="muted small">No requirement captured yet. <a class="link" href="<?= url('client_form',['id'=>$c['id']]) ?>">Add it →</a></div><?php endif; ?>
  </div>

  <!-- Budget 3-part -->
  <div class="card">
    <div class="card-head"><h3>Budget Breakdown</h3></div>
    <?php if ($r): ?>
      <div class="grid" style="grid-template-columns:1fr 1fr;gap:12px">
        <div class="card" style="background:var(--bg-card-2)">
          <div class="muted tiny">Agreement Value</div><div style="font-size:19px;font-weight:800;margin-top:3px"><?= money($r['agreement_budget']) ?></div></div>
        <div class="card" style="background:var(--bg-card-2)">
          <div class="muted tiny">All-in Budget</div><div style="font-size:19px;font-weight:800;margin-top:3px;color:var(--primary)"><?= money($r['all_in_budget']) ?></div></div>
        <div class="card" style="background:var(--bg-card-2)">
          <div class="muted tiny">Own Contribution</div><div style="font-size:19px;font-weight:800;margin-top:3px;color:var(--green)"><?= money($r['own_contribution']) ?></div></div>
        <div class="card" style="background:var(--bg-card-2)">
          <div class="muted tiny">Loan <?= $r['loan_required'] ? '' : '(not required)' ?></div>
          <div style="font-size:19px;font-weight:800;margin-top:3px;color:var(--blue)"><?= money($r['loan_amount']) ?></div></div>
      </div>
    <?php else: ?><div class="muted small">No budget captured.</div><?php endif; ?>
  </div>
</div>

<!-- Top matches -->
<?php if ($topMatches): ?>
<div class="card mt">
  <div class="card-head"><h3>Top Matching Projects</h3><a class="link" href="<?= url('matcher',['client'=>$c['id']]) ?>">View all matches →</a></div>
  <div class="grid" style="grid-template-columns:repeat(3,1fr)">
    <?php foreach ($topMatches as $tm): $p=$tm['p']; $cls=$tm['score']>=90?'':($tm['score']>=75?'mid':'low'); ?>
      <a class="card" href="<?= url('project_view',['id'=>$p['id']]) ?>" style="background:var(--bg-card-2)">
        <div style="display:flex;justify-content:space-between;align-items:flex-start">
          <div><div style="font-weight:700;font-size:14px"><?= e($p['name']) ?></div>
            <div class="muted tiny"><?= e($p['node']) ?> · <?= (int)$p['avail'] ?> available</div></div>
          <span class="match-badge <?= $cls ?>"><?= $tm['score'] ?>%</span>
        </div>
        <div style="margin-top:10px;font-weight:700;font-size:13px"><?= money($p['price_min']) ?> – <?= money($p['price_max']) ?></div>
        <div class="muted tiny"><?= e($p['possession_label']) ?></div>
      </a>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<div class="grid mt" style="grid-template-columns:1fr 1fr">
  <div class="card">
    <div class="card-head"><h3>Site Visits</h3></div>
    <?php foreach ($visits as $vv): ?>
      <div class="feed-item"><div class="feed-ic ic-box amber"><?= icon('location',16) ?></div>
        <div style="flex:1"><div style="font-size:13px;font-weight:600"><?= e($vv['pname'] ?: 'Project') ?></div>
          <div class="muted tiny"><?= fdate($vv['visit_date'],'d M Y, g:i A') ?></div></div>
        <span class="badge <?= $vv['status']==='done'?'green':'blue' ?>"><?= e($vv['status']) ?></span></div>
    <?php endforeach; ?>
    <?php if (!$visits): ?><div class="muted small">No site visits recorded.</div><?php endif; ?>
  </div>
  <div class="card">
    <div class="card-head"><h3>Bookings</h3></div>
    <?php foreach ($bookings as $bk): ?>
      <div class="feed-item"><div class="feed-ic ic-box green"><?= icon('bookings',16) ?></div>
        <div style="flex:1"><div style="font-size:13px;font-weight:600"><?= e($bk['pname']) ?></div>
          <div class="muted tiny"><?= fdate($bk['booking_date']) ?> · <?= money($bk['value']) ?></div></div>
        <span class="badge teal"><?= e($bk['stage']) ?></span></div>
    <?php endforeach; ?>
    <?php if (!$bookings): ?><div class="muted small">No bookings yet.</div><?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>

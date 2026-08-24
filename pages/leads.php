<?php
/** RE360 — Leads & Clients */
require_once __DIR__ . '/../includes/icons.php';
$page = 'leads'; $pageTitle = 'Leads & Clients';

$status = trim($_GET['status'] ?? '');
$q   = trim($_GET['q'] ?? '');
$bhk = trim($_GET['bhk'] ?? '');
$loc = trim($_GET['loc'] ?? '');
$w = []; $params = [];
if ($status!=='') { $w[]="c.status=?"; $params[]=$status; }
if ($bhk!=='')    { $w[]="r.bhk=?";    $params[]=$bhk; }
if ($loc!=='')    { $w[]="(r.preferred_location LIKE ? OR r.alt_location LIKE ?)"; $params[]="%$loc%"; $params[]="%$loc%"; }
if ($q!=='')      { $w[]="(c.name LIKE ? OR c.mobile LIKE ?)"; $params[]="%$q%"; $params[]="%$q%"; }
$where = $w ? 'WHERE '.implode(' AND ',$w) : '';

$clients = rows("SELECT c.*, r.bhk, r.preferred_location, r.alt_location, r.min_carpet,
                        r.all_in_budget, r.agreement_budget, r.loan_amount,
                        r.possession_within_months, r.ready_or_uc
                 FROM clients c LEFT JOIN client_requirements r ON r.client_id=c.id
                 $where ORDER BY c.updated_at DESC", $params);

/* the export link carries the current filters so the file matches the screen */
$exportQs = http_build_query(array_filter(
    ['status'=>$status, 'q'=>$q, 'bhk'=>$bhk, 'loc'=>$loc],
    fn($v) => $v !== ''
));

/* location list for the filter — drawn from what clients actually asked for */
$reqLocations = rows("SELECT DISTINCT preferred_location AS loc FROM client_requirements
                      WHERE preferred_location <> '' ORDER BY preferred_location");

$pipeline = ['new'=>0,'contacted'=>0,'site_visit'=>0,'negotiation'=>0,'booked'=>0,'lost'=>0];
foreach (rows("SELECT status, COUNT(*) c FROM clients GROUP BY status") as $r) { $pipeline[$r['status']] = (int)$r['c']; }
$labels = ['new'=>'New','contacted'=>'Contacted','site_visit'=>'Site Visit','negotiation'=>'Negotiation','booked'=>'Booked','lost'=>'Lost'];
$colors = ['new'=>'blue','contacted'=>'violet','site_visit'=>'amber','negotiation'=>'gold','booked'=>'green','lost'=>'grey'];
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head no-print">
  <div><h2>Leads &amp; Clients</h2><p><?= count($clients) ?> customer<?= count($clients)==1?'':'s' ?><?= $where ? ' matching your filters' : ' in your pipeline' ?></p></div>
  <div style="display:flex;gap:8px">
    <a class="btn ghost" href="api/export_clients.php<?= $exportQs ? '?'.e($exportQs) : '' ?>"
       title="Download the list below as a CSV you can open in Excel"><?= icon('export',16) ?> Export to Excel</a>
    <button type="button" class="btn ghost" onclick="window.print()"
            title="Print the list, or choose Save as PDF in the print dialog"><?= icon('file',16) ?> Print / PDF</button>
    <a class="btn primary" href="<?= url('client_form') ?>"><?= icon('plus',16) ?> Add Client</a>
  </div>
</div>

<div class="kpi-row" style="grid-template-columns:repeat(6,1fr);margin-bottom:18px">
  <?php foreach ($labels as $k=>$lbl): ?>
    <a class="kpi" href="<?= url('leads',['status'=>$k]) ?>">
      <div class="top"><div class="ic-box <?= $colors[$k]==='grey'?'purple':$colors[$k] ?>"><?= icon('leads',18) ?></div>
      <div class="k-label"><?= $lbl ?></div></div>
      <div class="k-value" style="font-size:23px"><?= $pipeline[$k] ?></div>
    </a>
  <?php endforeach; ?>
</div>

<form class="card" style="margin-bottom:18px;padding:14px" method="get" data-autofilter>
  <input type="hidden" name="page" value="leads">
  <div style="display:flex;gap:10px;flex-wrap:wrap">
    <div class="search" style="flex:1;min-width:220px;background:var(--bg-card-2)">
      <?= icon('search',18) ?><input type="text" name="q" value="<?= e($q) ?>" placeholder="Search by name or mobile...">
    </div>
    <select class="select" name="status"><option value="">All Stages</option>
      <?php foreach ($labels as $k=>$l): ?><option value="<?= $k ?>" <?= $status===$k?'selected':'' ?>><?= $l ?></option><?php endforeach; ?></select>
    <select class="select" name="bhk"><option value="">All BHK</option>
      <?php foreach ($GLOBALS['RE360_CONFIGS'] as $cfg): ?><option value="<?= e($cfg) ?>" <?= $bhk===$cfg?'selected':'' ?>><?= e($cfg) ?></option><?php endforeach; ?></select>
    <select class="select" name="loc"><option value="">All Locations</option>
      <?php foreach ($reqLocations as $rl): ?><option value="<?= e($rl['loc']) ?>" <?= $loc===$rl['loc']?'selected':'' ?>><?= e($rl['loc']) ?></option><?php endforeach; ?></select>
    <button class="btn primary" type="submit">Filter</button>
    <?php if ($where): ?><a class="btn ghost" href="<?= url('leads') ?>">Clear</a><?php endif; ?>
  </div>
</form>

<?php
  $activeFilters = [];
  if ($status !== '') $activeFilters[] = 'Stage: ' . ($labels[$status] ?? $status);
  if ($bhk    !== '') $activeFilters[] = 'BHK: ' . $bhk;
  if ($loc    !== '') $activeFilters[] = 'Location: ' . $loc;
  if ($q      !== '') $activeFilters[] = 'Search: ' . $q;
?>
<div class="print-only print-head">
  <h1>Customer Requirement List</h1>
  <div class="meta">
    <?= SITE_NAME ?> &middot; <?= count($clients) ?> customer<?= count($clients)==1?'':'s' ?>
    &middot; <?= date('d M Y') ?>
    <?php if ($activeFilters): ?>&middot; <?= e(implode('  |  ', $activeFilters)) ?><?php endif; ?>
  </div>
</div>

<div class="card pad0">
  <div class="table-wrap">
    <table class="data">
      <thead><tr><th>Client</th><th>Mobile</th><th>BHK</th><th>Carpet</th><th>Location</th><th>Budget</th><th>Possession</th><th>Type</th><th>Purpose</th><th>Stage</th><th class="no-print"></th></tr></thead>
      <tbody>
      <?php foreach ($clients as $c):
        $months = (int)$c['possession_within_months'];
        $poss = $months > 0 ? ($months >= 12 ? round($months/12,1).' yr' : $months.' mo') : '—';
        $ruc  = ['ready'=>'Ready','under_construction'=>'Under const.','any'=>'Any'][$c['ready_or_uc']] ?? '—';
      ?>
        <tr>
          <td class="strong"><?= e($c['name']) ?></td>
          <td><?= e($c['mobile']) ?></td>
          <td><?= e($c['bhk'] ?: '—') ?></td>
          <td><?= (int)$c['min_carpet'] > 0 ? num($c['min_carpet']).'+' : '—' ?></td>
          <td><?= e($c['preferred_location'] ?: '—') ?><?php if (!empty($c['alt_location'])): ?><span class="muted tiny"> / <?= e($c['alt_location']) ?></span><?php endif; ?></td>
          <td class="strong"><?= $c['all_in_budget'] ? money($c['all_in_budget']) : '—' ?></td>
          <td><?= e($poss) ?></td>
          <td><?= e($ruc) ?></td>
          <td><?= e(ucwords(str_replace('_',' ',$c['purpose']))) ?></td>
          <td><span class="badge <?= $colors[$c['status']] ?? 'grey' ?>"><?= $labels[$c['status']] ?? $c['status'] ?></span></td>
          <td class="no-print">
            <a class="link" href="<?= url('client_view',['id'=>$c['id']]) ?>">View</a> ·
            <a class="link" href="<?= url('matcher',['client'=>$c['id']]) ?>">Match →</a>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$clients): ?><tr><td colspan="11" class="center muted" style="padding:36px">No clients yet. <a class="link" href="<?= url('client_form') ?>">Add one →</a></td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>

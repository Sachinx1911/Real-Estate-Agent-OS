<?php
/** RE360 — Leads & Clients */
require_once __DIR__ . '/../includes/icons.php';
$page = 'leads'; $pageTitle = 'Leads & Clients';

$status = trim($_GET['status'] ?? '');
$q = trim($_GET['q'] ?? '');
$w = []; $params = [];
if ($status!=='') { $w[]="c.status=?"; $params[]=$status; }
if ($q!=='')      { $w[]="(c.name LIKE ? OR c.mobile LIKE ?)"; $params[]="%$q%"; $params[]="%$q%"; }
$where = $w ? 'WHERE '.implode(' AND ',$w) : '';

$clients = rows("SELECT c.*, r.bhk, r.preferred_location, r.all_in_budget
                 FROM clients c LEFT JOIN client_requirements r ON r.client_id=c.id
                 $where ORDER BY c.updated_at DESC", $params);

$pipeline = ['new'=>0,'contacted'=>0,'site_visit'=>0,'negotiation'=>0,'booked'=>0,'lost'=>0];
foreach (rows("SELECT status, COUNT(*) c FROM clients GROUP BY status") as $r) { $pipeline[$r['status']] = (int)$r['c']; }
$labels = ['new'=>'New','contacted'=>'Contacted','site_visit'=>'Site Visit','negotiation'=>'Negotiation','booked'=>'Booked','lost'=>'Lost'];
$colors = ['new'=>'blue','contacted'=>'violet','site_visit'=>'amber','negotiation'=>'gold','booked'=>'green','lost'=>'grey'];
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
  <div><h2>Leads &amp; Clients</h2><p><?= count($clients) ?> clients in your pipeline</p></div>
  <a class="btn primary" href="<?= url('client_form') ?>"><?= icon('plus',16) ?> Add Client</a>
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
    <button class="btn primary" type="submit">Filter</button>
    <?php if ($where): ?><a class="btn ghost" href="<?= url('leads') ?>">Clear</a><?php endif; ?>
  </div>
</form>

<div class="card pad0">
  <div class="table-wrap">
    <table class="data">
      <thead><tr><th>Client</th><th>Mobile</th><th>Requirement</th><th>Location</th><th>Budget</th><th>Purpose</th><th>Stage</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($clients as $c): ?>
        <tr>
          <td class="strong"><?= e($c['name']) ?></td>
          <td><?= e($c['mobile']) ?></td>
          <td><?= e($c['bhk'] ?: '—') ?></td>
          <td><?= e($c['preferred_location'] ?: '—') ?></td>
          <td class="strong"><?= $c['all_in_budget'] ? money($c['all_in_budget']) : '—' ?></td>
          <td><?= e(ucwords(str_replace('_',' ',$c['purpose']))) ?></td>
          <td><span class="badge <?= $colors[$c['status']] ?? 'grey' ?>"><?= $labels[$c['status']] ?? $c['status'] ?></span></td>
          <td>
            <a class="link" href="<?= url('client_view',['id'=>$c['id']]) ?>">View</a> ·
            <a class="link" href="<?= url('matcher',['client'=>$c['id']]) ?>">Match →</a>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$clients): ?><tr><td colspan="8" class="center muted" style="padding:36px">No clients yet. <a class="link" href="<?= url('client_form') ?>">Add one →</a></td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>

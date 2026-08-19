<?php
/** RE360 — Site Visits */
require_once __DIR__ . '/../includes/icons.php';
require_once __DIR__ . '/../includes/crud.php';
$page = 'site_visits'; $pageTitle = 'Site Visits';
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_POST['mark_done'])) {
        db()->prepare("UPDATE site_visits SET status='done' WHERE id=?")->execute([(int)$_POST['mark_done']]);
        $msg = 'Visit marked as done.';
    } elseif (!empty($_POST['client_id'])) {
        save_row('site_visits', ['client_id','project_id','visit_date','status','notes','done_by'], $_POST, null);
        $cname = scalar("SELECT name FROM clients WHERE id=?", [(int)$_POST['client_id']]);
        log_activity('site_visit_added','client',(int)$_POST['client_id'], "Site visit scheduled – $cname", 'visits');
        $msg = 'Site visit scheduled.';
    }
}

$upcoming = rows("SELECT sv.*, c.name AS cname, c.mobile, p.name AS pname
                  FROM site_visits sv JOIN clients c ON c.id=sv.client_id
                  LEFT JOIN projects p ON p.id=sv.project_id
                  WHERE sv.status='scheduled' ORDER BY sv.visit_date ASC");
$past = rows("SELECT sv.*, c.name AS cname, p.name AS pname
              FROM site_visits sv JOIN clients c ON c.id=sv.client_id
              LEFT JOIN projects p ON p.id=sv.project_id
              WHERE sv.status<>'scheduled' ORDER BY sv.visit_date DESC LIMIT 20");

$clientOpts = []; foreach (rows("SELECT id,name FROM clients ORDER BY name") as $c) { $clientOpts[$c['id']] = $c['name']; }
$projOpts = [];   foreach (rows("SELECT id,name FROM projects ORDER BY name") as $p) { $projOpts[$p['id']] = $p['name']; }
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
  <div><h2>Site Visits</h2><p><?= count($upcoming) ?> upcoming · <?= count($past) ?> completed</p></div>
</div>
<?php if ($msg): ?><div class="login-err" style="background:var(--green-bg);color:var(--green);margin-bottom:16px">✓ <?= e($msg) ?></div><?php endif; ?>

<div class="grid" style="grid-template-columns:1fr 1.6fr">
  <form method="post" class="card">
    <div class="card-head"><h3>Schedule a Visit</h3></div>
    <?= select_field('Client','client_id',$clientOpts,'',true) ?>
    <?= select_field('Project','project_id',$projOpts,'',true) ?>
    <?= field('Date &amp; Time','visit_date','','datetime-local') ?>
    <?= textarea_field('Notes','notes','') ?>
    <button class="btn primary" type="submit" style="margin-top:14px"><?= icon('plus',16) ?> Schedule Visit</button>
  </form>

  <div class="card pad0">
    <div class="card-head" style="padding:18px"><h3>Upcoming Visits</h3></div>
    <div class="table-wrap">
      <table class="data">
        <thead><tr><th>Client</th><th>Mobile</th><th>Project</th><th>Date</th><th>Notes</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($upcoming as $v): ?>
          <tr>
            <td class="strong"><a class="link" href="<?= url('client_view',['id'=>$v['client_id']]) ?>"><?= e($v['cname']) ?></a></td>
            <td><?= e($v['mobile']) ?></td>
            <td><?= e($v['pname'] ?: '—') ?></td>
            <td><?= fdate($v['visit_date'],'d M Y, g:i A') ?></td>
            <td><?= e($v['notes'] ?: '—') ?></td>
            <td><form method="post" style="display:inline"><input type="hidden" name="mark_done" value="<?= $v['id'] ?>">
              <button class="btn ghost sm" type="submit">Mark done</button></form></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$upcoming): ?><tr><td colspan="6" class="center muted" style="padding:30px">No upcoming visits.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="card mt pad0">
  <div class="card-head" style="padding:18px"><h3>Visit History</h3></div>
  <div class="table-wrap">
    <table class="data">
      <thead><tr><th>Client</th><th>Project</th><th>Date</th><th>Status</th><th>Notes</th></tr></thead>
      <tbody>
      <?php foreach ($past as $v): ?>
        <tr><td class="strong"><?= e($v['cname']) ?></td><td><?= e($v['pname'] ?: '—') ?></td>
          <td><?= fdate($v['visit_date'],'d M Y') ?></td>
          <td><span class="badge <?= $v['status']==='done'?'green':'grey' ?>"><?= e($v['status']) ?></span></td>
          <td><?= e($v['notes'] ?: '—') ?></td></tr>
      <?php endforeach; ?>
      <?php if (!$past): ?><tr><td colspan="5" class="center muted" style="padding:30px">No past visits.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>

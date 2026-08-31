<?php
/** RE360 — Builder profile */
require_once __DIR__ . '/../includes/icons.php';
require_once __DIR__ . '/../includes/crud.php';
$page = 'builders'; $pageTitle = 'Builder Profile';
$err = '';

$id = (int)($_GET['id'] ?? 0);
$b = row("SELECT * FROM builders WHERE id=?", [$id]);
if (!$b) { require __DIR__ . '/../includes/header.php'; echo '<div class="card empty">Builder not found.</div>'; require __DIR__ . '/../includes/footer.php'; return; }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_builder') {
    $res = delete_builder($id);
    if ($res['ok']) { header('Location: ' . url('builders')); exit; }
    $err = $res['error'];
}

$projects = rows("SELECT * FROM projects WHERE builder_id=? ORDER BY name", [$id]);
$bkCount  = builder_booking_count($id);
$cp = row("SELECT * FROM cp_details WHERE builder_id=? LIMIT 1", [$id]);
$scores = [
  'Construction'  => (float)$b['score_construction'],
  'Delivery'      => (float)$b['score_delivery'],
  'Location'      => (float)$b['score_location'],
  'Pricing'       => (float)$b['score_pricing'],
  'Reputation'    => (float)$b['score_reputation'],
  'Documentation' => (float)$b['score_documentation'],
];
$avg = round(array_sum($scores)/6, 1);
require __DIR__ . '/../includes/header.php';
?>
<?php if ($err): ?><div class="login-err" style="margin-bottom:16px"><?= e($err) ?></div><?php endif; ?>
<div class="page-head">
  <div>
    <h2><?= e($b['name']) ?></h2>
    <p><?= e($b['company']) ?> <?= $b['established_year'] ? '· Est. '.$b['established_year'] : '' ?> · <?= e($b['office_location']) ?></p>
  </div>
  <div style="display:flex;gap:8px">
    <a class="btn ghost" href="<?= url('builders') ?>">← Back</a>
    <a class="btn primary" href="<?= url('builder_form',['id'=>$b['id']]) ?>">Edit Builder</a>
    <?php if ($bkCount): ?>
      <button class="btn ghost" type="button" disabled style="opacity:.45;cursor:not-allowed"
              title="<?= (int)$bkCount ?> booking(s) exist under this builder's projects">Delete</button>
    <?php else: ?>
      <form method="post" style="display:inline" onsubmit="return confirm('Delete <?= e(addslashes($b['name'])) ?>?\n\nThis also deletes <?= count($projects) ?> project(s) and everything under them — inventory, configurations, towers, pricing, offers, legal documents and uploaded files.\n\nThis cannot be undone.')">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="delete_builder">
        <button class="btn ghost" type="submit" style="color:var(--red)">Delete</button>
      </form>
    <?php endif; ?>
  </div>
</div>

<div class="grid" style="grid-template-columns:1.2fr 1fr">
  <!-- Contact & track record -->
  <div class="card">
    <div class="card-head"><h3>Builder Details</h3></div>
    <div class="grid" style="grid-template-columns:1fr 1fr;gap:14px">
      <?php
      $fields = [
        ['Contact Person', $b['contact_person']], ['Designation', $b['designation']],
        ['Mobile', $b['mobile']], ['WhatsApp', $b['whatsapp']],
        ['Email', $b['email']], ['Website', $b['website']],
        ['RERA Entity', $b['rera_entity']], ['GST No', $b['gst_no']],
        ['Head Office', $b['head_office']], ['Major Locations', $b['major_locations']],
      ];
      foreach ($fields as $f): ?>
        <div><div class="muted tiny"><?= e($f[0]) ?></div><div style="font-size:13px;font-weight:600;margin-top:2px"><?= e($f[1] ?: '—') ?></div></div>
      <?php endforeach; ?>
    </div>
    <div class="grid strip" style="grid-template-columns:repeat(5,1fr);gap:8px;margin-top:18px;text-align:center">
      <?php foreach ([['Total',$b['total_projects']],['Completed',$b['completed_projects']],['Ongoing',$b['ongoing_projects']],['Upcoming',$b['upcoming_projects']],['Delivered',$b['delivered_projects']]] as $s): ?>
        <div class="card" style="background:var(--bg-card-2);padding:10px">
          <div style="font-size:17px;font-weight:800"><?= (int)$s[1] ?></div><div class="muted tiny"><?= $s[0] ?></div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Reliability score -->
  <div class="card">
    <div class="card-head"><h3>Reliability Score</h3><span class="badge <?= $avg>=8?'green':($avg>=7?'amber':'grey') ?>"><?= $avg ?>/10</span></div>
    <?php foreach ($scores as $lbl=>$v): $pct=$v*10; $col = $v>=8?'var(--green)':($v>=7?'var(--amber)':'var(--grey)'); ?>
      <div class="score-row">
        <span class="lbl"><?= $lbl ?></span>
        <span class="track"><span style="display:block;height:100%;width:<?= $pct ?>%;background:<?= $col ?>;border-radius:20px"></span></span>
        <span class="val"><?= $v ?></span>
      </div>
    <?php endforeach; ?>
    <?php if ($b['reputation_note']): ?>
      <div class="muted small" style="margin-top:14px;padding-top:12px;border-top:1px solid var(--border-soft)"><?= e($b['reputation_note']) ?></div>
    <?php endif; ?>
  </div>
</div>

<!-- CP details -->
<?php if ($cp): ?>
<div class="card mt">
  <div class="card-head"><h3>Channel Partner Terms</h3></div>
  <div class="grid" style="grid-template-columns:repeat(4,1fr);gap:14px">
    <?php foreach ([['CP Code',$cp['cp_code']],['Commission',$cp['commission_pct'].'%'],['Basis',$cp['commission_basis']],['Payout Stage',$cp['payout_stage']],['Payout Timeline',$cp['payout_timeline']],['Lead Validity',$cp['lead_validity_days'].' days']] as $f): ?>
      <div><div class="muted tiny"><?= e($f[0]) ?></div><div style="font-size:13px;font-weight:600;margin-top:2px"><?= e($f[1] ?: '—') ?></div></div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<!-- Projects -->
<div class="card mt">
  <div class="card-head"><h3>Projects by <?= e($b['name']) ?></h3><span class="muted small"><?= count($projects) ?> projects</span></div>
  <div class="table-wrap">
    <table class="data">
      <thead><tr><th>Project</th><th>Location</th><th>Status</th><th>Possession</th><th>Price Range</th><th>Units</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($projects as $p): ?>
        <tr>
          <td class="strong"><?= e($p['name']) ?></td>
          <td><?= e($p['node']) ?></td>
          <td><span class="badge <?= $p['status']==='ready'?'green':($p['status']==='new_launch'?'violet':'blue') ?>"><?= e($GLOBALS['RE360_PROJECT_STATUS'][$p['status']] ?? $p['status']) ?></span></td>
          <td><?= e($p['possession_label']) ?></td>
          <td><?= money($p['price_min']) ?> – <?= money($p['price_max']) ?></td>
          <td><?= (int)$p['total_units'] ?></td>
          <td><a class="link" href="<?= url('project_view',['id'=>$p['id']]) ?>">View →</a></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$projects): ?><tr><td colspan="7" class="center muted" style="padding:26px">No projects yet.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>

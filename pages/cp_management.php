<?php
/** RE360 — Channel Partner management (per builder terms) */
require_once __DIR__ . '/../includes/icons.php';
require_once __DIR__ . '/../includes/crud.php';
$page = 'cp_management'; $pageTitle = 'CP Management';
$msg = '';

$fields = ['builder_id','cp_registration_required','cp_code','registration_process','cp_contact','commission_pct',
  'commission_basis','payout_stage','payout_timeline','gst_req','tds','lead_reg_process','lead_validity_days',
  'duplicate_rules','site_visit_process','cancellation_rules'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['builder_id'])) {
    $_POST['cp_registration_required'] = isset($_POST['cp_registration_required']) ? 1 : 0;
    $_POST['gst_req'] = isset($_POST['gst_req']) ? 1 : 0;
    $existing = (int) scalar("SELECT id FROM cp_details WHERE builder_id=?", [(int)$_POST['builder_id']]);
    save_row('cp_details', $fields, $_POST, $existing ?: null);
    $msg = 'CP terms saved.';
}

$editBuilder = (int)($_GET['builder'] ?? 0);
$cp = $editBuilder ? row("SELECT * FROM cp_details WHERE builder_id=?", [$editBuilder]) : [];

$list = rows("SELECT b.id, b.name, b.office_location, cp.*
              FROM builders b LEFT JOIN cp_details cp ON cp.builder_id=b.id ORDER BY b.name");
$builderOpts = []; foreach (rows("SELECT id,name FROM builders ORDER BY name") as $b) { $builderOpts[$b['id']] = $b['name']; }
// raw values — the field helpers escape internally
$v = fn($k,$d='') => $cp[$k] ?? $d;
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
  <div><h2>Channel Partner Management</h2><p>Registration, commission and payout terms for each builder</p></div>
</div>
<?php if ($msg): ?><div class="login-err" style="background:var(--green-bg);color:var(--green);margin-bottom:16px">✓ <?= e($msg) ?></div><?php endif; ?>

<div class="card pad0">
  <div class="card-head" style="padding:18px"><h3>Builder CP Terms</h3></div>
  <div class="table-wrap">
    <table class="data">
      <thead><tr><th>Builder</th><th>Location</th><th>CP Code</th><th>Commission</th><th>Basis</th><th>Payout Stage</th><th>Timeline</th><th>Lead Validity</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($list as $r): ?>
        <tr>
          <td class="strong"><a class="link" href="<?= url('builder_view',['id'=>$r['id']]) ?>"><?= e($r['name']) ?></a></td>
          <td><?= e($r['office_location']) ?></td>
          <td><?= e($r['cp_code'] ?: '—') ?></td>
          <td class="strong"><?= $r['commission_pct'] !== null ? e($r['commission_pct']).'%' : '—' ?></td>
          <td><?= e($r['commission_basis'] ?: '—') ?></td>
          <td><?= e($r['payout_stage'] ?: '—') ?></td>
          <td><?= e($r['payout_timeline'] ?: '—') ?></td>
          <td><?= $r['lead_validity_days'] ? e($r['lead_validity_days']).' days' : '—' ?></td>
          <td><a class="link" href="<?= url('cp_management',['builder'=>$r['id']]) ?>">Edit →</a></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$list): ?><tr><td colspan="9" class="center muted" style="padding:30px">Add builders first.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<form method="post" class="card mt"><?= csrf_field() ?>
  <div class="card-head"><h3><?= $editBuilder ? 'Edit' : 'Add' ?> CP Terms</h3></div>
  <div class="form-grid">
    <?= select_field('Builder *','builder_id',$builderOpts,$editBuilder,true) ?>
    <?= field('CP Code','cp_code',$v('cp_code')) ?>
    <?= field('CP Contact Person','cp_contact',$v('cp_contact')) ?>
    <?= field('Commission %','commission_pct',$v('commission_pct','2'),'number',['step'=>'0.25']) ?>
    <?= field('Commission Basis','commission_basis',$v('commission_basis','Agreement value')) ?>
    <?= field('Payout Stage','payout_stage',$v('payout_stage'),'text',['placeholder'=>'On registration']) ?>
    <?= field('Payout Timeline','payout_timeline',$v('payout_timeline'),'text',['placeholder'=>'30 days']) ?>
    <?= field('TDS','tds',$v('tds'),'text',['placeholder'=>'5%']) ?>
    <?= field('Lead Validity (days)','lead_validity_days',$v('lead_validity_days','30'),'number') ?>
    <div class="form-group"><label>CP Registration Required</label>
      <label style="display:flex;align-items:center;gap:9px;padding:9px 0;font-size:13px">
        <input type="checkbox" name="cp_registration_required" value="1" <?= ($cp['cp_registration_required'] ?? 1) ? 'checked':'' ?>> Yes</label></div>
    <div class="form-group"><label>GST Required</label>
      <label style="display:flex;align-items:center;gap:9px;padding:9px 0;font-size:13px">
        <input type="checkbox" name="gst_req" value="1" <?= ($cp['gst_req'] ?? 1) ? 'checked':'' ?>> Yes</label></div>
    <?= textarea_field('Registration Process','registration_process',$v('registration_process')) ?>
    <?= textarea_field('Lead Registration Process','lead_reg_process',$v('lead_reg_process')) ?>
    <?= textarea_field('Duplicate Lead Rules','duplicate_rules',$v('duplicate_rules')) ?>
    <?= textarea_field('Site Visit Process','site_visit_process',$v('site_visit_process')) ?>
    <?= textarea_field('Cancellation / Commission Rules','cancellation_rules',$v('cancellation_rules')) ?>
  </div>
  <button class="btn primary" type="submit" style="margin-top:18px"><?= icon('verified',16) ?> Save CP Terms</button>
</form>
<?php require __DIR__ . '/../includes/footer.php'; ?>

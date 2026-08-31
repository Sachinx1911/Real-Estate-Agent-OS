<?php
/** RE360 — Offers (official vs verbal, with expiry) */
require_once __DIR__ . '/../includes/icons.php';
require_once __DIR__ . '/../includes/crud.php';
$page = 'offers'; $pageTitle = 'Offers';
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_POST['toggle_id'])) {
        db()->prepare("UPDATE offers SET is_active = 1 - is_active WHERE id=?")->execute([(int)$_POST['toggle_id']]);
        $msg = 'Offer status updated.';
    } elseif (!empty($_POST['project_id'])) {
        $_POST['is_active'] = 1;
        save_row('offers', ['project_id','type','details','official_or_verbal','valid_till','is_active'], $_POST, null);
        $pname = scalar("SELECT name FROM projects WHERE id=?", [(int)$_POST['project_id']]);
        log_activity('offer_added','project',(int)$_POST['project_id'], "New offer added – $pname", 'tag');
        $msg = 'Offer added.';
    }
}

$offers = rows("SELECT o.*, p.name AS pname, p.node, b.name AS bname
                FROM offers o JOIN projects p ON p.id=o.project_id JOIN builders b ON b.id=p.builder_id
                ORDER BY o.is_active DESC, o.valid_till ASC");
$projOpts = []; foreach (rows("SELECT id,name FROM projects ORDER BY name") as $p) { $projOpts[$p['id']] = $p['name']; }
$types = ['festive'=>'Festive Offer','spot'=>'Spot Booking','investor'=>'Investor Offer','cash'=>'Cash Discount',
  'floor'=>'Floor Discount','parking'=>'Free Parking','stamp'=>'Stamp Duty Offer','gst'=>'GST Benefit',
  'furniture'=>'Furniture','rental'=>'Rental Guarantee','assured'=>'Assured Return','waiver'=>'Charges Waiver'];
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
  <div><h2>Offers</h2><p><?= count(array_filter($offers, fn($o)=>$o['is_active'])) ?> active offers across your projects</p></div>
</div>
<?php if ($msg): ?><div class="login-err" style="background:var(--green-bg);color:var(--green);margin-bottom:16px">✓ <?= e($msg) ?></div><?php endif; ?>

<div class="grid" style="grid-template-columns:1fr 1.8fr">
  <form method="post" class="card"><?= csrf_field() ?>
    <div class="card-head"><h3>Add Offer</h3></div>
    <?= select_field('Project','project_id',$projOpts,'',true) ?>
    <?= select_field('Offer Type','type',$types,'festive') ?>
    <?= textarea_field('Offer Details','details','','Stamp duty waiver on select 2 BHK units') ?>
    <?= select_field('Official or Verbal','official_or_verbal',['official'=>'Official (written)','verbal'=>'Verbal (told by sales team)'],'official') ?>
    <?= field('Valid Till','valid_till','','date') ?>
    <button class="btn primary" type="submit" style="margin-top:14px"><?= icon('plus',16) ?> Add Offer</button>
    <p class="muted tiny" style="margin-top:10px">Track verbal offers separately — they are not guaranteed by the builder in writing.</p>
  </form>

  <div class="card pad0">
    <div class="card-head" style="padding:18px"><h3>All Offers</h3></div>
    <div class="table-wrap">
      <table class="data">
        <thead><tr><th>Project</th><th>Type</th><th>Details</th><th>Source</th><th>Valid Till</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($offers as $o):
          $expired = $o['valid_till'] && strtotime($o['valid_till']) < strtotime('today'); ?>
          <tr>
            <td class="strong"><a class="link" href="<?= url('project_view',['id'=>$o['project_id'],'tab'=>'pricing']) ?>"><?= e($o['pname']) ?></a>
              <div class="muted tiny"><?= e($o['node']) ?></div></td>
            <td><span class="badge violet"><?= e($types[$o['type']] ?? $o['type']) ?></span></td>
            <td style="white-space:normal;max-width:280px"><?= e($o['details']) ?></td>
            <td><span class="badge <?= $o['official_or_verbal']==='official'?'green':'amber' ?>"><?= e(ucfirst($o['official_or_verbal'])) ?></span></td>
            <td><?= fdate($o['valid_till']) ?><?php if ($expired): ?><div class="tiny" style="color:var(--red)">Expired</div><?php endif; ?></td>
            <td><span class="badge <?= $o['is_active'] && !$expired ? 'green':'grey' ?>"><?= $o['is_active'] ? ($expired?'Expired':'Active') : 'Inactive' ?></span></td>
            <td><form method="post"><?= csrf_field() ?><input type="hidden" name="toggle_id" value="<?= $o['id'] ?>">
              <button class="btn ghost sm" type="submit"><?= $o['is_active']?'Disable':'Enable' ?></button></form></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$offers): ?><tr><td colspan="7" class="center muted" style="padding:30px">No offers recorded yet.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>

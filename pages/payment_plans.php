<?php
/** RE360 — Payment plans per project */
require_once __DIR__ . '/../includes/icons.php';
require_once __DIR__ . '/../includes/crud.php';
$page = 'payment_plans'; $pageTitle = 'Payment Plans';
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_POST['delete_id'])) {
        db()->prepare("DELETE FROM payment_plans WHERE id=?")->execute([(int)$_POST['delete_id']]);
        $msg = 'Payment plan deleted.';
    } elseif (!empty($_POST['project_id']) && trim($_POST['plan_name'] ?? '') !== '') {
        // milestones come as parallel arrays
        $ms = [];
        $lbls = (array)($_POST['ms_label'] ?? []);
        $pcts = (array)($_POST['ms_pct'] ?? []);
        foreach ($lbls as $i => $l) {
            $l = trim($l); $p = (float)($pcts[$i] ?? 0);
            if ($l !== '' && $p > 0) $ms[] = ['label'=>$l, 'pct'=>$p];
        }
        db()->prepare("INSERT INTO payment_plans (project_id, plan_name, description, milestones, is_default) VALUES (?,?,?,?,?)")
            ->execute([(int)$_POST['project_id'], trim($_POST['plan_name']), trim($_POST['description'] ?? ''),
                       json_encode($ms), isset($_POST['is_default']) ? 1 : 0]);
        $pname = scalar("SELECT name FROM projects WHERE id=?", [(int)$_POST['project_id']]);
        log_activity('payment_updated','project',(int)$_POST['project_id'], "Payment plan updated – $pname", 'money');
        $msg = 'Payment plan saved.';
    }
}

$plans = rows("SELECT pp.*, p.name AS pname, p.node FROM payment_plans pp
               JOIN projects p ON p.id=pp.project_id ORDER BY p.name, pp.is_default DESC");
$projOpts = []; foreach (rows("SELECT id,name FROM projects ORDER BY name") as $p) { $projOpts[$p['id']] = $p['name']; }
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
  <div><h2>Payment Plans</h2><p><?= count($plans) ?> plans across your projects</p></div>
</div>
<?php if ($msg): ?><div class="login-err" style="background:var(--green-bg);color:var(--green);margin-bottom:16px">✓ <?= e($msg) ?></div><?php endif; ?>

<div class="grid" style="grid-template-columns:1fr 1.6fr">
  <form method="post" class="card">
    <div class="card-head"><h3>Add Payment Plan</h3></div>
    <?= select_field('Project','project_id',$projOpts,'',true) ?>
    <div style="margin-top:14px"><?= field('Plan Name','plan_name','','text',['placeholder'=>'Construction Linked (10:10:10:10:60)']) ?></div>
    <div style="margin-top:14px"><?= field('Description','description','','text',['placeholder'=>'Standard CLP payment plan']) ?></div>

    <div class="form-section-title" style="margin-top:18px">Milestones</div>
    <div id="msRows">
      <?php for ($i=0;$i<5;$i++): ?>
        <div style="display:flex;gap:8px;margin-bottom:8px">
          <input class="field-input" name="ms_label[]" placeholder="<?= ['Booking','Agreement','Plinth','Slabs','Possession'][$i] ?>" style="flex:1">
          <input class="field-input" name="ms_pct[]" type="number" step="1" placeholder="%" style="width:80px">
        </div>
      <?php endfor; ?>
    </div>
    <label style="display:flex;align-items:center;gap:9px;margin-top:10px;font-size:13px">
      <input type="checkbox" name="is_default" value="1"> Mark as default plan</label>
    <button class="btn primary" type="submit" style="margin-top:16px"><?= icon('plus',16) ?> Save Plan</button>
  </form>

  <div class="grid" style="grid-template-columns:1fr 1fr;align-content:start">
    <?php foreach ($plans as $pl): $ms = json_decode($pl['milestones'] ?? '[]', true) ?: []; $sum = array_sum(array_column($ms,'pct')); ?>
      <div class="card">
        <div style="display:flex;justify-content:space-between;align-items:flex-start">
          <div>
            <div style="font-weight:700;font-size:14px"><?= e($pl['plan_name']) ?></div>
            <div class="muted tiny"><a class="link" href="<?= url('project_view',['id'=>$pl['project_id'],'tab'=>'pricing']) ?>"><?= e($pl['pname']) ?></a> · <?= e($pl['node']) ?></div>
          </div>
          <?php if ($pl['is_default']): ?><span class="badge teal">Default</span><?php endif; ?>
        </div>
        <?php if ($pl['description']): ?><div class="muted tiny" style="margin-top:6px"><?= e($pl['description']) ?></div><?php endif; ?>
        <div style="margin-top:12px">
          <?php foreach ($ms as $m): ?>
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:6px">
              <span style="flex:1;font-size:12.5px;color:var(--text-2)"><?= e($m['label']) ?></span>
              <span class="bar-track" style="width:90px"><span style="display:block;height:100%;width:<?= min(100,(float)$m['pct']) ?>%;background:linear-gradient(90deg,var(--primary),var(--secondary));border-radius:20px"></span></span>
              <span style="font-weight:700;font-size:12.5px;width:38px;text-align:right"><?= (float)$m['pct'] ?>%</span>
            </div>
          <?php endforeach; ?>
        </div>
        <div style="display:flex;justify-content:space-between;align-items:center;margin-top:10px;padding-top:10px;border-top:1px solid var(--border-soft)">
          <span class="badge <?= abs($sum-100) < 0.01 ? 'green':'amber' ?>">Total <?= $sum ?>%</span>
          <form method="post" onsubmit="return confirm('Delete this plan?')"><input type="hidden" name="delete_id" value="<?= $pl['id'] ?>">
            <button class="link" type="submit" style="color:var(--red);font-size:12px">Delete</button></form>
        </div>
      </div>
    <?php endforeach; ?>
    <?php if (!$plans): ?><div class="card empty" style="grid-column:1/-1">No payment plans yet.</div><?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>

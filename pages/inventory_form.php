<?php
/** RE360 — Add inventory (mobile-friendly, fast entry in the builder office) */
require_once __DIR__ . '/../includes/icons.php';
require_once __DIR__ . '/../includes/crud.php';
$page = 'inventory'; $pageTitle = 'Add Inventory';

$editId    = (int)($_GET['id'] ?? 0);
$projectId = (int)($_GET['project'] ?? 0);
$f = $editId ? row("SELECT * FROM inventory WHERE id=?", [$editId]) : [];
if ($f && !$projectId) $projectId = (int)$f['project_id'];
$msg = ''; $err = '';

$fields = ['project_id','tower','floor','flat_no','config','carpet','facing','view_desc','status','price','source','confidence','notes'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mode = $_POST['mode'] ?? 'single';

    if ($mode === 'bulk') {
        // Bulk: one flat per line — FlatNo, Tower, Floor, Config, Carpet, Facing, Status, Price
        $pid  = (int)($_POST['project_id'] ?? 0);
        $lines = preg_split('/\r\n|\r|\n/', trim($_POST['bulk_data'] ?? ''));
        $count = 0;
        if (!$pid) { $err = 'Select a project first.'; }
        else {
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '') continue;
                $c = array_map('trim', explode(',', $line));
                if (count($c) < 4) continue;
                db()->prepare("INSERT INTO inventory (project_id,flat_no,tower,floor,config,carpet,facing,status,price,last_verified_at,verified_by,source,confidence)
                               VALUES (?,?,?,?,?,?,?,?,?,NOW(),?,?,?)")
                    ->execute([$pid, $c[0], $c[1] ?? '', (int)($c[2] ?? 0), $c[3] ?? '', (int)($c[4] ?? 0),
                               $c[5] ?? '', strtolower($c[6] ?? 'available'), (int)str_replace([',','₹'],'',$c[7] ?? '0'),
                               current_user()['id'] ?? null, $_POST['source'] ?? 'Builder office', 'high']);
                $count++;
            }
            if ($count) {
                $pname = scalar("SELECT name FROM projects WHERE id=?", [$pid]);
                log_activity('inventory_updated','project',$pid,"$count flats added – $pname",'inventory');
                $msg = "$count flats added successfully.";
            } else { $err = 'No valid rows found. Check the format.'; }
        }
    } else {
        if (empty($_POST['project_id']) || trim($_POST['flat_no'] ?? '') === '') {
            $err = 'Project and flat number are required.';
        } else {
            $id = save_row('inventory', $fields, $_POST, $editId ?: null);
            db()->prepare("UPDATE inventory SET last_verified_at=NOW(), verified_by=? WHERE id=?")
                ->execute([current_user()['id'] ?? null, $id]);
            $pname = scalar("SELECT name FROM projects WHERE id=?", [$_POST['project_id']]);
            log_activity('inventory_updated','project',(int)$_POST['project_id'], "Inventory updated – $pname",'inventory');
            $msg = 'Flat ' . htmlspecialchars($_POST['flat_no']) . ' saved. Add the next one below.';
            if (!$editId) { $projectId = (int)$_POST['project_id']; $f = []; }
        }
    }
}

$projectsList = rows("SELECT id, name FROM projects ORDER BY name");
$projOpts = [];
foreach ($projectsList as $pl) { $projOpts[$pl['id']] = $pl['name']; }
$statusOpts = [];
foreach ($GLOBALS['RE360_INV_STATUS'] as $s) { $statusOpts[$s] = ucfirst($s); }
// raw values — the field helpers escape internally
$v = fn($k, $d='') => $f[$k] ?? $d;

// recently added for this project
$recent = $projectId ? rows("SELECT * FROM inventory WHERE project_id=? ORDER BY id DESC LIMIT 8", [$projectId]) : [];
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
  <div><h2><?= $editId ? 'Edit Flat' : 'Add Inventory' ?></h2><p>Fast entry — designed for use inside the builder's office</p></div>
  <a class="btn ghost" href="<?= url('inventory') ?>">← All inventory</a>
</div>

<?php if ($msg): ?><div class="login-err" style="background:var(--green-bg);color:var(--green);margin-bottom:16px">✓ <?= e($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="login-err" style="margin-bottom:16px"><?= e($err) ?></div><?php endif; ?>

<div class="tabs">
  <button class="active" onclick="showMode('single',this)">Single Flat</button>
  <button onclick="showMode('bulk',this)">Bulk Paste</button>
</div>

<!-- SINGLE -->
<form method="post" class="card" id="mode-single"><?= csrf_field() ?>
  <input type="hidden" name="mode" value="single">
  <div class="form-grid">
    <?= select_field('Project *','project_id',$projOpts,$projectId ?: $v('project_id'),true) ?>
    <?= field('Flat No *','flat_no',$v('flat_no'),'text',['placeholder'=>'A-501','required'=>'required']) ?>
    <?= field('Tower','tower',$v('tower'),'text',['placeholder'=>'A']) ?>
    <?= field('Floor','floor',$v('floor'),'number') ?>
    <?= select_field('Configuration','config',$GLOBALS['RE360_CONFIGS'],$v('config','2 BHK')) ?>
    <?= field('Carpet Area (sq.ft.)','carpet',$v('carpet'),'number') ?>
    <?= select_field('Facing','facing',['East','West','North','South','North-East','North-West','South-East','South-West'],$v('facing'),true) ?>
    <?= field('View','view_desc',$v('view_desc'),'text',['placeholder'=>'Garden / Road / Hills']) ?>
    <?= select_field('Status','status',$statusOpts,$v('status','available')) ?>
    <?= field('Price (₹)','price',$v('price'),'number',['step'=>'50000','placeholder'=>'9250000']) ?>
    <?= field('Source','source',$v('source','Sales Manager'),'text',['placeholder'=>'Sales Manager / CP Portal']) ?>
    <?= select_field('Confidence','confidence',['high'=>'High','medium'=>'Medium','low'=>'Low'],$v('confidence','high')) ?>
    <?= field('Notes','notes',$v('notes')) ?>
  </div>
  <div style="display:flex;gap:10px;margin-top:20px">
    <button class="btn primary" type="submit"><?= icon('verified',16) ?> Save &amp; Add Next</button>
    <a class="btn ghost" href="<?= url('inventory') ?>">Done</a>
  </div>
  <p class="muted tiny" style="margin-top:10px">Saving stamps <strong>Last Verified = now</strong>, so freshness stays accurate.</p>
</form>

<!-- BULK -->
<form method="post" class="card" id="mode-bulk" style="display:none"><?= csrf_field() ?>
  <input type="hidden" name="mode" value="bulk">
  <div class="form-grid">
    <?= select_field('Project *','project_id',$projOpts,$projectId,true) ?>
    <?= field('Source','source','Builder office') ?>
  </div>
  <div class="form-group full" style="margin-top:14px">
    <label>Paste rows — one flat per line</label>
    <textarea name="bulk_data" style="min-height:200px;font-family:monospace" placeholder="A-501, A, 5, 2 BHK, 680, East, available, 9250000
A-502, A, 5, 2 BHK, 710, West, hold, 9480000
A-601, A, 6, 2 BHK, 680, East, available, 9310000"></textarea>
    <p class="muted tiny" style="margin-top:8px">Format: <code>FlatNo, Tower, Floor, Config, Carpet, Facing, Status, Price</code></p>
  </div>
  <button class="btn primary" type="submit" style="margin-top:16px"><?= icon('upload',16) ?> Import Flats</button>
</form>

<?php if ($recent): ?>
<div class="card mt pad0">
  <div class="card-head" style="padding:18px"><h3>Recently added</h3>
    <a class="link" href="<?= url('inventory',['project'=>$projectId]) ?>">View all →</a></div>
  <div class="table-wrap">
    <table class="data">
      <thead><tr><th>Flat No</th><th>Tower</th><th>Floor</th><th>Config</th><th>Carpet</th><th>Status</th><th>Price</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($recent as $r): ?>
        <tr><td class="strong"><?= e($r['flat_no']) ?></td><td><?= e($r['tower']) ?></td><td><?= (int)$r['floor'] ?></td>
          <td><?= e($r['config']) ?></td><td><?= num($r['carpet']) ?></td>
          <td><span class="badge <?= status_color($r['status']) ?>"><?= e($r['status']) ?></span></td>
          <td class="strong"><?= money_full($r['price']) ?></td>
          <td><a class="link" href="<?= url('inventory_form',['id'=>$r['id']]) ?>">Edit</a></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php
$inlineScript = <<<JS
function showMode(m, btn){
  document.getElementById('mode-single').style.display = (m==='single')?'block':'none';
  document.getElementById('mode-bulk').style.display   = (m==='bulk')?'block':'none';
  document.querySelectorAll('.tabs button').forEach(b=>b.classList.remove('active'));
  btn.classList.add('active');
}
JS;
require __DIR__ . '/../includes/footer.php';

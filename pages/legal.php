<?php
/** RE360 — Legal / RERA document checklist per project */
require_once __DIR__ . '/../includes/icons.php';
require_once __DIR__ . '/../includes/crud.php';
$page = 'legal'; $pageTitle = 'Legal / RERA';
$msg = '';

$docTypes = ['maharera'=>'MahaRERA Registration','title'=>'Title Certificate','cc'=>'Commencement Certificate',
  'approved_plan'=>'Approved Plan','na_order'=>'NA Order','oc'=>'Occupancy Certificate',
  'bank_approval'=>'Bank Approval','agreement'=>'Agreement Format','society'=>'Society Formation'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_POST['set_status']) && !empty($_POST['doc_id'])) {
        db()->prepare("UPDATE legal_docs SET status=? WHERE id=?")->execute([$_POST['set_status'], (int)$_POST['doc_id']]);
        $msg = 'Document status updated.';
    } elseif (!empty($_POST['project_id']) && !empty($_POST['doc_type'])) {
        save_row('legal_docs', ['project_id','doc_type','status','note'], $_POST, null);
        $pname = scalar("SELECT name FROM projects WHERE id=?", [(int)$_POST['project_id']]);
        log_activity('legal_updated','project',(int)$_POST['project_id'], "Legal document added – $pname", 'file');
        $msg = 'Document record added.';
    }
}

$projectId = (int)($_GET['project'] ?? 0);
$projects = rows("SELECT p.*, b.name AS bname,
    (SELECT COUNT(*) FROM legal_docs l WHERE l.project_id=p.id AND l.status='verified') AS verified,
    (SELECT COUNT(*) FROM legal_docs l WHERE l.project_id=p.id) AS total
  FROM projects p JOIN builders b ON b.id=p.builder_id ORDER BY p.name");

$docs = $projectId
  ? rows("SELECT * FROM legal_docs WHERE project_id=? ORDER BY doc_type", [$projectId])
  : rows("SELECT l.*, p.name AS pname FROM legal_docs l JOIN projects p ON p.id=l.project_id ORDER BY p.name, l.doc_type LIMIT 100");

$projOpts = []; foreach ($projects as $p) { $projOpts[$p['id']] = $p['name']; }
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
  <div><h2>Legal / RERA</h2><p>Document checklist and verification status per project</p></div>
</div>
<?php if ($msg): ?><div class="login-err" style="background:var(--green-bg);color:var(--green);margin-bottom:16px">✓ <?= e($msg) ?></div><?php endif; ?>

<!-- Compliance overview -->
<div class="card">
  <div class="card-head"><h3>Project Compliance Overview</h3></div>
  <div class="table-wrap">
    <table class="data">
      <thead><tr><th>Project</th><th>Builder</th><th>MahaRERA</th><th>RERA Status</th><th>OC</th><th>CC</th><th>Docs Verified</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($projects as $p): ?>
        <tr>
          <td class="strong"><a class="link" href="<?= url('project_view',['id'=>$p['id'],'tab'=>'legal']) ?>"><?= e($p['name']) ?></a></td>
          <td><?= e($p['bname']) ?></td>
          <td><?= e($p['maharera_no'] ?: '—') ?></td>
          <td><span class="badge <?= $p['rera_verified']?'green':'amber' ?>"><?= $p['rera_verified']?'Verified':'Pending' ?></span></td>
          <td><?= e($p['oc_status'] ?: '—') ?></td>
          <td><?= e($p['cc_status'] ?: '—') ?></td>
          <td><span class="badge <?= $p['total'] && $p['verified']==$p['total'] ? 'green' : ($p['total'] ? 'amber':'grey') ?>">
              <?= (int)$p['verified'] ?> / <?= (int)$p['total'] ?></span></td>
          <td><a class="link" href="<?= url('legal',['project'=>$p['id']]) ?>">Documents →</a></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$projects): ?><tr><td colspan="8" class="center muted" style="padding:30px">Add projects first.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="grid mt" style="grid-template-columns:1fr 1.6fr">
  <form method="post" class="card">
    <div class="card-head"><h3>Add Document Record</h3></div>
    <?= select_field('Project','project_id',$projOpts,$projectId,true) ?>
    <div style="margin-top:14px"><?= select_field('Document Type','doc_type',$docTypes,'maharera') ?></div>
    <div style="margin-top:14px"><?= select_field('Status','status',['pending'=>'Pending','verified'=>'Verified','not_verified'=>'Not Verified'],'pending') ?></div>
    <div style="margin-top:14px"><?= field('Note','note','','text',['placeholder'=>'Reference no. / remark']) ?></div>
    <button class="btn primary" type="submit" style="margin-top:16px"><?= icon('plus',16) ?> Add Record</button>
  </form>

  <div class="card pad0">
    <div class="card-head" style="padding:18px">
      <h3><?= $projectId ? 'Documents — ' . e(scalar("SELECT name FROM projects WHERE id=?", [$projectId])) : 'All Documents' ?></h3>
      <?php if ($projectId): ?><a class="link" href="<?= url('legal') ?>">Show all</a><?php endif; ?>
    </div>
    <div class="table-wrap">
      <table class="data">
        <thead><tr><?php if (!$projectId): ?><th>Project</th><?php endif; ?><th>Document</th><th>Status</th><th>Note</th><th>Change</th></tr></thead>
        <tbody>
        <?php foreach ($docs as $d): ?>
          <tr>
            <?php if (!$projectId): ?><td><?= e($d['pname'] ?? '') ?></td><?php endif; ?>
            <td class="strong"><?= icon('file',14) ?> <?= e($docTypes[$d['doc_type']] ?? $d['doc_type']) ?></td>
            <td><span class="badge <?= $d['status']==='verified'?'green':($d['status']==='pending'?'amber':'red') ?>">
                <?= e(ucwords(str_replace('_',' ',$d['status']))) ?></span></td>
            <td><?= e($d['note'] ?: '—') ?></td>
            <td>
              <form method="post" style="display:inline">
                <input type="hidden" name="doc_id" value="<?= $d['id'] ?>">
                <select class="select" name="set_status" style="padding:5px 8px;font-size:12px" onchange="this.form.submit()">
                  <option value="">Set…</option>
                  <option value="verified">Verified</option>
                  <option value="pending">Pending</option>
                  <option value="not_verified">Not Verified</option>
                </select>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$docs): ?><tr><td colspan="5" class="center muted" style="padding:30px">No document records yet.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>

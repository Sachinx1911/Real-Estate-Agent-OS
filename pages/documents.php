<?php
/** RE360 — Documents (brochures, floor plans, price sheets, RERA docs) */
require_once __DIR__ . '/../includes/icons.php';
$page = 'documents'; $pageTitle = 'Documents';
$msg = ''; $err = '';

$allowedExt = ['pdf','jpg','jpeg','png','webp','xlsx','xls','doc','docx','csv'];
$maxBytes   = 10 * 1024 * 1024; // 10 MB

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_POST['delete_id'])) {
        $d = row("SELECT * FROM documents WHERE id=?", [(int)$_POST['delete_id']]);
        if ($d) {
            $fp = BASE_PATH . '/' . $d['file_path'];
            if (is_file($fp)) @unlink($fp);
            db()->prepare("DELETE FROM documents WHERE id=?")->execute([$d['id']]);
            $msg = 'Document deleted.';
        }
    } elseif (!empty($_FILES['file']['name'])) {
        $f = $_FILES['file'];
        $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
        if ($f['error'] !== UPLOAD_ERR_OK)          $err = 'Upload failed. Try a smaller file.';
        elseif (!in_array($ext, $allowedExt, true)) $err = 'File type not allowed. Use PDF, image, Excel or Word.';
        elseif ($f['size'] > $maxBytes)             $err = 'File is larger than 10 MB.';
        else {
            if (!is_dir(UPLOADS_PATH)) @mkdir(UPLOADS_PATH, 0755, true);
            $safe = preg_replace('/[^A-Za-z0-9._-]/', '_', pathinfo($f['name'], PATHINFO_FILENAME));
            $newName = $safe . '_' . time() . '.' . $ext;
            if (move_uploaded_file($f['tmp_name'], UPLOADS_PATH . '/' . $newName)) {
                db()->prepare("INSERT INTO documents (entity_type, entity_id, doc_name, doc_type, file_path, uploaded_by) VALUES (?,?,?,?,?,?)")
                    ->execute([$_POST['entity_type'] ?: null, (int)($_POST['entity_id'] ?: 0) ?: null,
                               $_POST['doc_name'] ?: $f['name'], $_POST['doc_type'] ?: null,
                               UPLOADS_URL . '/' . $newName, current_user()['id'] ?? null]);
                log_activity('document_added', $_POST['entity_type'] ?: 'document', (int)($_POST['entity_id'] ?: 0) ?: null,
                             'Document uploaded – ' . ($_POST['doc_name'] ?: $f['name']), 'file');
                $msg = 'Document uploaded.';
            } else { $err = 'Could not save the file. Check that /uploads is writable.'; }
        }
    }
}

$docs = rows("SELECT * FROM documents ORDER BY created_at DESC LIMIT 100");
$projects = rows("SELECT id,name FROM projects ORDER BY name");
$builders = rows("SELECT id,name FROM builders ORDER BY name");
$docTypes = ['brochure'=>'Brochure','floor_plan'=>'Floor Plan','price_sheet'=>'Price Sheet','rera'=>'RERA Document',
             'agreement'=>'Agreement','payment_plan'=>'Payment Plan','other'=>'Other'];
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
  <div><h2>Documents</h2><p><?= count($docs) ?> files stored</p></div>
</div>
<?php if ($msg): ?><div class="login-err" style="background:var(--green-bg);color:var(--green);margin-bottom:16px">✓ <?= e($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="login-err" style="margin-bottom:16px"><?= e($err) ?></div><?php endif; ?>

<div class="grid" style="grid-template-columns:1fr 1.8fr">
  <form method="post" enctype="multipart/form-data" class="card">
    <div class="card-head"><h3>Upload Document</h3></div>
    <div class="form-group"><label>File (max 10 MB)</label>
      <input class="field-input" type="file" name="file" required></div>
    <div class="form-group" style="margin-top:14px"><label>Document Name</label>
      <input class="field-input" name="doc_name" placeholder="Paradise Heights – Price Sheet"></div>
    <div class="form-group" style="margin-top:14px"><label>Type</label>
      <select class="select" name="doc_type"><?php foreach ($docTypes as $k=>$l): ?><option value="<?= $k ?>"><?= $l ?></option><?php endforeach; ?></select></div>
    <div class="form-group" style="margin-top:14px"><label>Link to</label>
      <select class="select" name="entity_type" id="entType" onchange="swapEntity()">
        <option value="">Not linked</option><option value="project">Project</option><option value="builder">Builder</option></select></div>
    <div class="form-group" style="margin-top:14px"><label>Select</label>
      <select class="select" name="entity_id" id="entId">
        <option value="">—</option>
        <?php foreach ($projects as $p): ?><option value="<?= $p['id'] ?>" data-kind="project"><?= e($p['name']) ?></option><?php endforeach; ?>
        <?php foreach ($builders as $b): ?><option value="<?= $b['id'] ?>" data-kind="builder"><?= e($b['name']) ?></option><?php endforeach; ?>
      </select></div>
    <button class="btn primary" type="submit" style="margin-top:16px"><?= icon('upload',16) ?> Upload</button>
  </form>

  <div class="card pad0">
    <div class="card-head" style="padding:18px"><h3>All Documents</h3></div>
    <div class="table-wrap">
      <table class="data">
        <thead><tr><th>Document</th><th>Type</th><th>Linked to</th><th>Uploaded</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($docs as $d):
          $linked = '—';
          if ($d['entity_type']==='project' && $d['entity_id']) $linked = scalar("SELECT name FROM projects WHERE id=?", [$d['entity_id']]) ?: '—';
          if ($d['entity_type']==='builder' && $d['entity_id']) $linked = scalar("SELECT name FROM builders WHERE id=?", [$d['entity_id']]) ?: '—';
        ?>
          <tr>
            <td class="strong"><?= icon('file',14) ?> <?= e($d['doc_name']) ?></td>
            <td><span class="badge blue"><?= e($docTypes[$d['doc_type']] ?? $d['doc_type'] ?? 'Other') ?></span></td>
            <td><?= e($linked) ?></td>
            <td><?= fdate($d['created_at'],'d M Y') ?></td>
            <td>
              <a class="link" href="<?= e($d['file_path']) ?>" target="_blank" rel="noopener">Open</a> ·
              <form method="post" style="display:inline" onsubmit="return confirm('Delete this document?')">
                <input type="hidden" name="delete_id" value="<?= $d['id'] ?>">
                <button class="link" type="submit" style="color:var(--red)">Delete</button></form>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$docs): ?><tr><td colspan="5" class="center muted" style="padding:30px">No documents uploaded yet.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php
$inlineScript = <<<JS
function swapEntity(){
  var kind = document.getElementById('entType').value;
  var sel  = document.getElementById('entId');
  Array.from(sel.options).forEach(function(o){
    if (!o.dataset.kind) return;
    o.hidden = (kind !== o.dataset.kind);
  });
  sel.value = '';
}
document.addEventListener('DOMContentLoaded', swapEntity);
JS;
require __DIR__ . '/../includes/footer.php';

<?php
/** RE360 — Add / edit project */
require_once __DIR__ . '/../includes/icons.php';
require_once __DIR__ . '/../includes/crud.php';
require_once __DIR__ . '/../includes/upload.php';
$page = 'projects'; $pageTitle = 'Project Form';

$id = (int)($_GET['id'] ?? 0);
$p  = $id ? row("SELECT * FROM projects WHERE id=?", [$id]) : [];
$err = '';

$fields = ['builder_id','name','type','status','address','city','node','sector','micro_market','pincode','latitude','longitude',
  'maharera_no','rera_link','rera_reg_date','rera_verified','proposed_completion','possession_label','current_status',
  'total_towers','total_units','land_parcel','project_area','launch_date','oc_status','cc_status','delay_history',
  'price_min','price_max','description','is_featured','best_for','budget_band','strengths','weaknesses','hero_image'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (trim($_POST['name'] ?? '') === '' || empty($_POST['builder_id'])) {
        $err = 'Project name and builder are required.';
    } else {
        $_POST['rera_verified'] = isset($_POST['rera_verified']) ? 1 : 0;
        $_POST['is_featured']   = isset($_POST['is_featured']) ? 1 : 0;

        /* Image first: if it fails we stop and say so, rather than saving the
         * project and silently dropping the picture the user just picked. */
        $oldImage = $p['hero_image'] ?? null;
        if (!empty($_FILES['hero_image_file']['name'])) {
            $up = save_uploaded_image($_FILES['hero_image_file'], 'projects');
            if (!$up['ok']) {
                $err = $up['error'];
            } else {
                $_POST['hero_image'] = $up['path'];
                delete_upload($oldImage);          // replaced — don't orphan the old file
            }
        } elseif (!empty($_POST['remove_hero_image'])) {
            $_POST['hero_image'] = '';
            delete_upload($oldImage);
        }
    }

    if ($err === '' && trim($_POST['name'] ?? '') !== '' && !empty($_POST['builder_id'])) {
        $id = save_row('projects', $fields, $_POST, $id ?: null);
        log_activity('project_saved','project',$id,'Project saved – ' . $_POST['name'],'building');
        header('Location: ' . url('project_view', ['id'=>$id])); exit;
    }
}
$builders = rows("SELECT id, name FROM builders ORDER BY name");
$builderOpts = [];
foreach ($builders as $b) { $builderOpts[$b['id']] = $b['name']; }
// raw values — the field helpers escape internally
$v = fn($k, $d='') => $_POST[$k] ?? $p[$k] ?? $d;
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
  <div><h2><?= $id ? 'Edit Project' : 'Add New Project' ?></h2><p>Project master — the core of your intelligence system</p></div>
  <a class="btn ghost" href="<?= url('projects') ?>">← Back</a>
</div>
<?php if ($err): ?><div class="login-err" style="margin-bottom:16px"><?= e($err) ?></div><?php endif; ?>
<?php if (!$builders): ?>
  <div class="card empty">Add a builder first. <a class="link" href="<?= url('builder_form') ?>">Add Builder →</a></div>
<?php else: ?>

<form method="post" enctype="multipart/form-data" class="card"><?= csrf_field() ?>
  <div class="form-grid">
    <div class="form-section-title">Basic Information</div>
    <?= field('Project Name *','name',$v('name'),'text',['required'=>'required']) ?>
    <?= select_field('Builder *','builder_id',$builderOpts,$v('builder_id'),true) ?>
    <?= select_field('Type','type',['residential'=>'Residential','commercial'=>'Commercial','mixed'=>'Mixed'],$v('type','residential')) ?>
    <?= select_field('Status','status',$GLOBALS['RE360_PROJECT_STATUS'],$v('status','under_construction')) ?>

    <div class="form-section-title">Project Image</div>
    <div class="form-group full">
      <label>Cover photo</label>
      <?php $cur = $v('hero_image'); ?>
      <?php if ($cur && is_file(BASE_PATH . '/' . $cur)): ?>
        <div style="display:flex;gap:14px;align-items:flex-start;margin-bottom:10px">
          <img src="<?= e($cur) ?>" alt="" style="width:170px;height:110px;object-fit:cover;border-radius:10px;border:1px solid var(--border)">
          <label style="display:flex;align-items:center;gap:8px;font-size:13px;padding-top:4px">
            <input type="checkbox" name="remove_hero_image" value="1"> Remove this image
          </label>
        </div>
      <?php endif; ?>
      <input class="field-input" type="file" name="hero_image_file" accept="image/*">
      <p class="muted tiny" style="margin-top:6px">
        JPG, PNG or WebP, up to 8 MB. Large photos are resized automatically.
        <?= $cur ? 'Choosing a new file replaces the current one.' : '' ?>
      </p>
    </div>

    <div class="form-section-title">Location</div>
    <?= field('Address','address',$v('address')) ?>
    <?= field('City','city',$v('city','Navi Mumbai')) ?>
    <?= select_field('Node / Location','node',$GLOBALS['RE360_LOCATIONS'],$v('node'),true) ?>
    <?= field('Sector','sector',$v('sector')) ?>
    <?= field('Micro Market','micro_market',$v('micro_market')) ?>
    <?= field('Pincode','pincode',$v('pincode')) ?>
    <?= field('Latitude','latitude',$v('latitude')) ?>
    <?= field('Longitude','longitude',$v('longitude')) ?>

    <div class="form-section-title">RERA &amp; Legal</div>
    <?= field('MahaRERA Number','maharera_no',$v('maharera_no'),'text',['placeholder'=>'P52000012345']) ?>
    <?= field('RERA Link','rera_link',$v('rera_link')) ?>
    <?= field('RERA Registration Date','rera_reg_date',$v('rera_reg_date'),'date') ?>
    <div class="form-group"><label>RERA Verified</label>
      <label style="display:flex;align-items:center;gap:9px;padding:9px 0;font-size:13px">
        <input type="checkbox" name="rera_verified" value="1" <?= ($_POST['rera_verified'] ?? $p['rera_verified'] ?? 0) ? 'checked':'' ?>> Mark as verified
      </label></div>
    <?= field('OC Status','oc_status',$v('oc_status')) ?>
    <?= field('CC Status','cc_status',$v('cc_status')) ?>

    <div class="form-section-title">Project Details</div>
    <?= field('Total Towers','total_towers',$v('total_towers'),'number') ?>
    <?= field('Total Units','total_units',$v('total_units'),'number') ?>
    <?= field('Land Parcel','land_parcel',$v('land_parcel'),'text',['placeholder'=>'5 Acres']) ?>
    <?= field('Project Area','project_area',$v('project_area'),'text',['placeholder'=>'2.75 Acres']) ?>
    <?= field('Launch Date','launch_date',$v('launch_date'),'date') ?>
    <?= field('Proposed Completion','proposed_completion',$v('proposed_completion'),'date') ?>
    <?= field('Possession Label','possession_label',$v('possession_label'),'text',['placeholder'=>'Dec 2027 / Ready Possession']) ?>
    <?= field('Current Status Note','current_status',$v('current_status')) ?>

    <div class="form-section-title">Pricing Range</div>
    <?= field('Price From (₹)','price_min',$v('price_min'),'number',['step'=>'100000']) ?>
    <?= field('Price To (₹)','price_max',$v('price_max'),'number',['step'=>'100000']) ?>
    <?= textarea_field('Description','description',$v('description')) ?>

    <div class="form-section-title">Sales Intelligence — internal (Layer 5)</div>
    <?= field('Best For','best_for',$v('best_for'),'text',['placeholder'=>'First-time buyer, Family, Investor']) ?>
    <?= field('Buyer Budget Band','budget_band',$v('budget_band'),'text',['placeholder'=>'₹75L–95L']) ?>
    <?= textarea_field('Top Strengths (one per line)','strengths',$v('strengths'),"Railway connectivity\nLarge carpet\nReputed developer") ?>
    <?= textarea_field('Weaknesses / Watch-outs (one per line)','weaknesses',$v('weaknesses'),"Distance from main road\nLimited parking") ?>

    <div class="form-group full"><label>Featured</label>
      <label style="display:flex;align-items:center;gap:9px;font-size:13px">
        <input type="checkbox" name="is_featured" value="1" <?= ($_POST['is_featured'] ?? $p['is_featured'] ?? 0) ? 'checked':'' ?>> Show in dashboard Project Spotlight
      </label></div>
  </div>
  <div style="display:flex;gap:10px;margin-top:22px">
    <button class="btn primary" type="submit"><?= icon('verified',16) ?> Save Project</button>
    <a class="btn ghost" href="<?= url('projects') ?>">Cancel</a>
  </div>
</form>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>

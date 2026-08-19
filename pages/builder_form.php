<?php
/** RE360 — Add / edit builder */
require_once __DIR__ . '/../includes/icons.php';
require_once __DIR__ . '/../includes/crud.php';
$page = 'builders'; $pageTitle = 'Builder Form';

$id = (int)($_GET['id'] ?? 0);
$b  = $id ? row("SELECT * FROM builders WHERE id=?", [$id]) : [];
$saved = false; $err = '';

$fields = ['name','company','group_name','established_year','head_office','office_location','contact_person','designation',
  'mobile','whatsapp','email','website','rera_entity','gst_no','cp_contact','total_projects','completed_projects',
  'ongoing_projects','upcoming_projects','delivered_projects','years_in_business','major_locations','reputation_note',
  'score_construction','score_delivery','score_location','score_pricing','score_reputation','score_documentation'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (trim($_POST['name'] ?? '') === '') {
        $err = 'Builder name is required.';
    } else {
        $id = save_row('builders', $fields, $_POST, $id ?: null);
        log_activity($id ? 'builder_saved' : 'builder_added', 'builder', $id, 'Builder saved – ' . $_POST['name'], 'builders');
        header('Location: ' . url('builder_view', ['id'=>$id])); exit;
    }
}
// raw values — field()/select_field()/textarea_field() escape internally
$v = fn($k, $d='') => $_POST[$k] ?? $b[$k] ?? $d;
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
  <div><h2><?= $id ? 'Edit Builder' : 'Add New Builder' ?></h2><p>Builder master profile — Layer 1 of project intelligence</p></div>
  <a class="btn ghost" href="<?= url('builders') ?>">← Back</a>
</div>
<?php if ($err): ?><div class="login-err" style="margin-bottom:16px"><?= e($err) ?></div><?php endif; ?>

<form method="post" class="card">
  <div class="form-grid">
    <div class="form-section-title">Basic Information</div>
    <?= field('Builder Name *','name',$v('name'),'text',['required'=>'required']) ?>
    <?= field('Company Name','company',$v('company')) ?>
    <?= field('Group Name','group_name',$v('group_name')) ?>
    <?= field('Established Year','established_year',$v('established_year'),'number') ?>
    <?= field('Head Office','head_office',$v('head_office')) ?>
    <?= select_field('Office Location','office_location',$GLOBALS['RE360_LOCATIONS'],$v('office_location'),true) ?>

    <div class="form-section-title">Contact</div>
    <?= field('Contact Person','contact_person',$v('contact_person')) ?>
    <?= field('Designation','designation',$v('designation')) ?>
    <?= field('Mobile','mobile',$v('mobile')) ?>
    <?= field('WhatsApp','whatsapp',$v('whatsapp')) ?>
    <?= field('Email','email',$v('email'),'email') ?>
    <?= field('Website','website',$v('website')) ?>

    <div class="form-section-title">Registration</div>
    <?= field('RERA Registered Entity','rera_entity',$v('rera_entity')) ?>
    <?= field('GST Number','gst_no',$v('gst_no')) ?>
    <?= field('Channel Partner Contact','cp_contact',$v('cp_contact')) ?>
    <?= field('Years in Business','years_in_business',$v('years_in_business'),'number') ?>

    <div class="form-section-title">Track Record</div>
    <?= field('Total Projects','total_projects',$v('total_projects'),'number') ?>
    <?= field('Completed Projects','completed_projects',$v('completed_projects'),'number') ?>
    <?= field('Ongoing Projects','ongoing_projects',$v('ongoing_projects'),'number') ?>
    <?= field('Upcoming Projects','upcoming_projects',$v('upcoming_projects'),'number') ?>
    <?= field('Delivered Projects','delivered_projects',$v('delivered_projects'),'number') ?>
    <?= field('Major Locations','major_locations',$v('major_locations'),'text',['placeholder'=>'Panvel, Kharghar']) ?>
    <?= textarea_field('Reputation / Notes','reputation_note',$v('reputation_note'),'Construction quality, customer feedback, delivery track record...') ?>

    <div class="form-section-title">Reliability Score (0–10) — your professional judgement</div>
    <?= field('Construction','score_construction',$v('score_construction','0'),'number',['step'=>'0.5','min'=>'0','max'=>'10']) ?>
    <?= field('Delivery','score_delivery',$v('score_delivery','0'),'number',['step'=>'0.5','min'=>'0','max'=>'10']) ?>
    <?= field('Location','score_location',$v('score_location','0'),'number',['step'=>'0.5','min'=>'0','max'=>'10']) ?>
    <?= field('Pricing','score_pricing',$v('score_pricing','0'),'number',['step'=>'0.5','min'=>'0','max'=>'10']) ?>
    <?= field('Reputation','score_reputation',$v('score_reputation','0'),'number',['step'=>'0.5','min'=>'0','max'=>'10']) ?>
    <?= field('Documentation','score_documentation',$v('score_documentation','0'),'number',['step'=>'0.5','min'=>'0','max'=>'10']) ?>
  </div>
  <div style="display:flex;gap:10px;margin-top:22px">
    <button class="btn primary" type="submit"><?= icon('verified',16) ?> Save Builder</button>
    <a class="btn ghost" href="<?= url('builders') ?>">Cancel</a>
  </div>
</form>
<?php require __DIR__ . '/../includes/footer.php'; ?>

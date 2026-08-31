<?php
/** RE360 — add / edit a person looking to take a flat on rent */
require_once __DIR__ . '/../includes/icons.php';
require_once __DIR__ . '/../includes/crud.php';
$page = 'rent';
$id = (int)($_GET['id'] ?? 0);
$pageTitle = $id ? 'Edit Person' : 'Add Person Looking for Rent';

$fields = ['name','mobile','alt_mobile','email','occupation',
           'preferred_location','preferred_sector','config','furnishing',
           'budget_min','budget_max','needed_from','family_type','status','source','notes'];

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (trim($_POST['name'] ?? '') === '') {
        $err = 'Name is required.';
    } else {
        $newId = save_row('rent_seekers', $fields, $_POST, $id ?: null);
        log_activity($id ? 'rent_seeker_updated' : 'rent_seeker_added', 'rent_seeker', (int)($newId ?: $id),
            ($id ? 'Rent enquiry updated – ' : 'Looking for rent – ') . trim($_POST['name']), 'leads');
        header('Location: ' . url('rent', ['tab' => 'enquiries']));
        exit;
    }
}

$r = $id ? row("SELECT * FROM rent_seekers WHERE id=?", [$id]) : [];
if ($id && !$r) {
    require __DIR__ . '/../includes/header.php';
    echo '<div class="card empty">Entry not found.</div>';
    require __DIR__ . '/../includes/footer.php';
    return;
}
$v = fn(string $k, $d = '') => $r[$k] ?? $d;

$locOpts = [];
foreach ($GLOBALS['RE360_LOCATIONS'] as $l) { $locOpts[$l] = $l; }
$cfgOpts = [];
foreach ($GLOBALS['RE360_CONFIGS'] as $c) { $cfgOpts[$c] = $c; }

require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
  <div>
    <h2><?= $id ? 'Edit Person' : 'Add Person Looking for Rent' ?></h2>
    <p>Who they are, and what kind of flat they want</p>
  </div>
  <a class="btn ghost" href="<?= url('rent',['tab'=>'enquiries']) ?>">&larr; Back to list</a>
</div>

<?php if ($err): ?><div class="login-err" style="margin-bottom:16px"><?= e($err) ?></div><?php endif; ?>

<form method="post" class="card"><?= csrf_field() ?>
  <div class="form-grid">

    <div class="form-section-title">Their Details</div>
    <?= field('Name *','name',$v('name'),'text',['required'=>'required']) ?>
    <?= field('Mobile','mobile',$v('mobile'),'text',['placeholder'=>'10-digit number']) ?>
    <?= field('Alternate Mobile','alt_mobile',$v('alt_mobile'),'text') ?>
    <?= field('Email','email',$v('email'),'email') ?>
    <?= field('Occupation / Company','occupation',$v('occupation'),'text',['placeholder'=>'e.g. IT, TCS Airoli']) ?>
    <?= select_field('Family / Bachelor','family_type',[
          'family'=>'Family','bachelor'=>'Bachelor','company'=>'Company lease','other'=>'Other'
        ],$v('family_type','family')) ?>

    <div class="form-section-title">What They Want</div>
    <?= select_field('Preferred Location','preferred_location',$locOpts,$v('preferred_location'),true) ?>
    <?= field('Preferred Sector','preferred_sector',$v('preferred_sector'),'text',['placeholder'=>'e.g. 12, 21']) ?>
    <?= select_field('Configuration','config',$cfgOpts,$v('config'),true) ?>
    <?= select_field('Furnishing','furnishing',[
          'any'=>'Any','unfurnished'=>'Unfurnished','semi_furnished'=>'Semi furnished','fully_furnished'=>'Fully furnished'
        ],$v('furnishing','any')) ?>
    <?= field('Budget from (&#8377; / month)','budget_min',$v('budget_min'),'number',['placeholder'=>'e.g. 15000','min'=>'0']) ?>
    <?= field('Budget up to (&#8377; / month)','budget_max',$v('budget_max'),'number',['placeholder'=>'e.g. 22000','min'=>'0']) ?>
    <?= field('Needed From','needed_from',$v('needed_from'),'date') ?>
    <?= select_field('Status','status',[
          'searching'=>'Searching','shown'=>'Shown flats','finalised'=>'Finalised','dropped'=>'Dropped'
        ],$v('status','searching')) ?>
    <?= field('Where did they come from','source',$v('source'),'text',['placeholder'=>'Reference, walk-in, portal...']) ?>

    <div class="form-section-title">Notes</div>
    <div class="full"><?= textarea_field('Anything worth remembering','notes',$v('notes'),'Needs parking, vegetarian only, shifting after Diwali...') ?></div>
  </div>

  <div style="display:flex;gap:10px;margin-top:20px">
    <button class="btn primary" type="submit"><?= icon('plus',16) ?> <?= $id ? 'Save Changes' : 'Add Person' ?></button>
    <a class="btn ghost" href="<?= url('rent',['tab'=>'enquiries']) ?>">Cancel</a>
  </div>
</form>
<?php require __DIR__ . '/../includes/footer.php'; ?>

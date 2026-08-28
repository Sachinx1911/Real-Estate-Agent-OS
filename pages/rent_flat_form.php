<?php
/** RE360 — add / edit a flat that an owner has given for rent */
require_once __DIR__ . '/../includes/icons.php';
require_once __DIR__ . '/../includes/crud.php';
$page = 'rent';
$id = (int)($_GET['id'] ?? 0);
$pageTitle = $id ? 'Edit Flat' : 'Add Flat for Rent';

$fields = ['building_name','flat_no','wing','floor','address','location','sector','building_location',
           'config','furnishing','rent','deposit','available_from',
           'owner_name','owner_mobile','owner_alt_mobile','owner_email','status','notes'];

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (trim($_POST['building_name'] ?? '') === '' || trim($_POST['owner_name'] ?? '') === '') {
        $err = 'Building name and owner name are required.';
    } else {
        $newId = save_row('rent_flats', $fields, $_POST, $id ?: null);
        log_activity($id ? 'rent_flat_updated' : 'rent_flat_added', 'rent_flat', (int)($newId ?: $id),
            ($id ? 'Rent flat updated – ' : 'Flat added for rent – ') . trim($_POST['building_name']), 'key');
        header('Location: ' . url('rent', ['tab' => 'flats']));
        exit;
    }
}

$r = $id ? row("SELECT * FROM rent_flats WHERE id=?", [$id]) : [];
if ($id && !$r) {
    require __DIR__ . '/../includes/header.php';
    echo '<div class="card empty">Flat not found.</div>';
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
    <h2><?= $id ? 'Edit Flat' : 'Add Flat for Rent' ?></h2>
    <p>What the owner has given you — building, flat and how to reach them</p>
  </div>
  <a class="btn ghost" href="<?= url('rent',['tab'=>'flats']) ?>">&larr; Back to list</a>
</div>

<?php if ($err): ?><div class="login-err" style="margin-bottom:16px"><?= e($err) ?></div><?php endif; ?>

<form method="post" class="card"><?= csrf_field() ?>
  <div class="form-grid">

    <div class="form-section-title">Building &amp; Flat</div>
    <?= field('Building / Society Name *','building_name',$v('building_name'),'text',['required'=>'required','placeholder'=>'e.g. Shree Ganesh Residency']) ?>
    <?= field('Flat Number','flat_no',$v('flat_no'),'text',['placeholder'=>'e.g. A-702']) ?>
    <?= field('Wing','wing',$v('wing'),'text',['placeholder'=>'e.g. A']) ?>
    <?= field('Floor','floor',$v('floor'),'text',['placeholder'=>'e.g. 7th']) ?>
    <?= field('Address','address',$v('address'),'text',['placeholder'=>'Plot / road / area']) ?>
    <?= select_field('Location','location',$locOpts,$v('location'),true) ?>
    <?= field('Sector','sector',$v('sector'),'text',['placeholder'=>'e.g. 12']) ?>
    <?= field('Building Location / Landmark','building_location',$v('building_location'),'text',['placeholder'=>'e.g. behind D-Mart, near station']) ?>

    <div class="form-section-title">The Flat</div>
    <?= select_field('Configuration','config',$cfgOpts,$v('config'),true) ?>
    <?= select_field('Furnishing','furnishing',[
          'unfurnished'=>'Unfurnished','semi_furnished'=>'Semi furnished','fully_furnished'=>'Fully furnished'
        ],$v('furnishing','unfurnished')) ?>
    <?= field('Rent per month (&#8377;)','rent',$v('rent'),'number',['placeholder'=>'e.g. 18000','min'=>'0']) ?>
    <?= field('Deposit (&#8377;)','deposit',$v('deposit'),'number',['placeholder'=>'e.g. 100000','min'=>'0']) ?>
    <?= field('Available From','available_from',$v('available_from'),'date') ?>
    <?= select_field('Status','status',[
          'available'=>'Available','rented'=>'Rented','on_hold'=>'On hold'
        ],$v('status','available')) ?>

    <div class="form-section-title">Owner</div>
    <?= field('Owner Name *','owner_name',$v('owner_name'),'text',['required'=>'required']) ?>
    <?= field('Mobile','owner_mobile',$v('owner_mobile'),'text',['placeholder'=>'10-digit number']) ?>
    <?= field('Alternate Mobile','owner_alt_mobile',$v('owner_alt_mobile'),'text') ?>
    <?= field('Email','owner_email',$v('owner_email'),'email') ?>

    <div class="form-section-title">Notes</div>
    <div class="full"><?= textarea_field('Anything worth remembering','notes',$v('notes'),'Key with watchman, owner prefers family, negotiable on rent...') ?></div>
  </div>

  <div style="display:flex;gap:10px;margin-top:20px">
    <button class="btn primary" type="submit"><?= icon('plus',16) ?> <?= $id ? 'Save Changes' : 'Add Flat' ?></button>
    <a class="btn ghost" href="<?= url('rent',['tab'=>'flats']) ?>">Cancel</a>
  </div>
</form>
<?php require __DIR__ . '/../includes/footer.php'; ?>

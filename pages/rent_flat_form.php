<?php
/** RE360 — add / edit a flat that an owner has given for rent */
require_once __DIR__ . '/../includes/icons.php';
require_once __DIR__ . '/../includes/crud.php';
$page = 'rent';
$id = (int)($_GET['id'] ?? 0);
$pageTitle = $id ? 'Edit Flat' : 'Add Flat for Rent';

$fields = ['building_name','flat_no','wing','floor','address','location','sector','building_location',
           'config','furnishing','rent','deposit','available_from',
           'owner_id','owner_name','owner_mobile','owner_alt_mobile','owner_email','status','notes'];

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (trim($_POST['building_name'] ?? '') === '' || trim($_POST['owner_name'] ?? '') === '') {
        $err = 'Building name and owner name are required.';
    } else {
        /* Keep the Owners list in step. Picking an existing owner reuses that
           record; typing a new name creates one, so the owner tab is never out
           of date with the flats. */
        $ownerId = (int)($_POST['owner_id'] ?? 0);
        $oName   = trim($_POST['owner_name'] ?? '');
        $oMobile = trim($_POST['owner_mobile'] ?? '');
        if (!$ownerId && $oName !== '') {
            $existing = $oMobile !== ''
                ? scalar("SELECT id FROM rent_owners WHERE mobile = ? LIMIT 1", [$oMobile])
                : scalar("SELECT id FROM rent_owners WHERE name = ? LIMIT 1", [$oName]);
            if ($existing) {
                $ownerId = (int)$existing;
            } else {
                db()->prepare("INSERT INTO rent_owners (name, mobile, alt_mobile, email) VALUES (?,?,?,?)")
                    ->execute([$oName, $oMobile ?: null,
                               trim($_POST['owner_alt_mobile'] ?? '') ?: null,
                               trim($_POST['owner_email'] ?? '') ?: null]);
                $ownerId = (int) db()->lastInsertId();
            }
        }
        $_POST['owner_id'] = $ownerId ?: null;

        $newId = save_row('rent_flats', $fields, $_POST, $id ?: null);
        log_activity($id ? 'rent_flat_updated' : 'rent_flat_added', 'rent_flat', (int)($newId ?: $id),
            ($id ? 'Rent flat updated – ' : 'Flat added for rent – ') . trim($_POST['building_name']), 'key');
        // One building often has several flats on offer. Rather than making
        // the user retype the address every time, come back with it filled in.
        if (($_POST['save_mode'] ?? '') === 'another') {
            header('Location: ' . url('rent_flat_form', ['from' => (int)($newId ?: $id)]));
            exit;
        }
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
/* Adding another flat in a building already on file: carry over what is
   genuinely shared (the address) plus the owner, since the same person
   usually hands over more than one. Flat-specific fields stay blank, and a
   banner says what was carried so a different owner is not saved by mistake. */
$carried = null;
if (!$id && !empty($_GET['from'])) {
    $src = row("SELECT * FROM rent_flats WHERE id=?", [(int)$_GET['from']]);
    if ($src) {
        $carried = $src['building_name'];
        foreach (['building_name','address','location','sector','building_location',
                  'owner_id','owner_name','owner_mobile','owner_alt_mobile','owner_email'] as $k) {
            $r[$k] = $src[$k];
        }
    }
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

<?php if ($carried): ?>
  <div class="card" style="margin-bottom:16px;border-color:var(--primary);background:var(--primary-soft);
       display:flex;align-items:center;gap:12px;flex-wrap:wrap">
    <span class="ic-box purple" style="width:34px;height:34px;border-radius:9px"><?= icon('building',18) ?></span>
    <div style="flex:1;min-width:220px">
      <div style="font-weight:700;font-size:13.5px">Another flat in <?= e($carried) ?></div>
      <div class="muted tiny" style="margin-top:2px">
        Address and owner have been filled in for you. Change the owner if this flat belongs to someone else.
      </div>
    </div>
    <a class="btn ghost sm" href="<?= url('rent_flat_form') ?>">Start blank instead</a>
  </div>
<?php endif; ?>

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
    <?php
      $ownerOpts = [];
      foreach (rows("SELECT id, name, mobile FROM rent_owners ORDER BY name") as $o) {
          $ownerOpts[$o['id']] = $o['name'] . ($o['mobile'] ? ' · '.$o['mobile'] : '');
      }
    ?>
    <?php if ($ownerOpts): ?>
      <div class="full">
        <?= select_field('Pick an owner already on file (or just type a new one below)','owner_id',$ownerOpts,$v('owner_id'),true) ?>
      </div>
    <?php endif; ?>
    <?= field('Owner Name *','owner_name',$v('owner_name'),'text',['required'=>'required']) ?>
    <?= field('Mobile','owner_mobile',$v('owner_mobile'),'text',['placeholder'=>'10-digit number']) ?>
    <?= field('Alternate Mobile','owner_alt_mobile',$v('owner_alt_mobile'),'text') ?>
    <?= field('Email','owner_email',$v('owner_email'),'email') ?>

    <div class="form-section-title">Notes</div>
    <div class="full"><?= textarea_field('Anything worth remembering','notes',$v('notes'),'Key with watchman, owner prefers family, negotiable on rent...') ?></div>
  </div>

  <div style="display:flex;gap:10px;margin-top:20px;flex-wrap:wrap">
    <button class="btn primary" type="submit" name="save_mode" value="done"><?= icon('plus',16) ?> <?= $id ? 'Save Changes' : 'Add Flat' ?></button>
    <?php if (!$id): ?>
      <button class="btn ghost" type="submit" name="save_mode" value="another"
              title="Save this one and open a blank form with the same building and owner">
        <?= icon('building',16) ?> Save &amp; Add Another in This Building
      </button>
    <?php endif; ?>
    <a class="btn ghost" href="<?= url('rent',['tab'=>'flats']) ?>">Cancel</a>
  </div>
</form>
<?php require __DIR__ . '/../includes/footer.php'; ?>

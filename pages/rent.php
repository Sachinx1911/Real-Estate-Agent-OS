<?php
/**
 * RE360 — Rent.
 *
 * One menu item, eight tabs. Deliberately separate from the sale side:
 * its own rent_* tables and its own pages. Nothing in builders, projects,
 * inventory or clients is touched.
 *
 * Each tab lives in pages/rent/<tab>.php. A tab file is included twice:
 * once with $preRender = true, before any output, so it can handle its own
 * POST and set $msg or redirect; then again after the header to draw itself.
 */
require_once __DIR__ . '/../includes/icons.php';
require_once __DIR__ . '/../includes/crud.php';
$page = 'rent';

$tabs = [
    'overview'   => 'Overview',
    'flats'      => 'Flats',
    'owners'     => 'Owners',
    'enquiries'  => 'Enquiries',
    'matcher'    => 'Matcher',
    'visits'     => 'Visits',
    'agreements' => 'Agreements',
    'brokerage'  => 'Brokerage',
];

$tab = preg_replace('/[^a-z]/', '', strtolower($_GET['tab'] ?? 'overview'));
if (!isset($tabs[$tab])) $tab = 'overview';
$pageTitle = 'Rent · ' . $tabs[$tab];

/* Labels shared by several tabs */
$furnLabel    = ['unfurnished'=>'Unfurnished','semi_furnished'=>'Semi furnished',
                 'fully_furnished'=>'Fully furnished','any'=>'Any'];
$flatStatus   = ['available'=>'Available','rented'=>'Rented','on_hold'=>'On hold'];
$seekerStatus = ['searching'=>'Searching','shown'=>'Shown flats','finalised'=>'Finalised','dropped'=>'Dropped'];
$agrStatus    = ['active'=>'Active','expired'=>'Expired','renewed'=>'Renewed','terminated'=>'Terminated'];
$visitStatus  = ['scheduled'=>'Scheduled','done'=>'Done','no_show'=>'No show','cancelled'=>'Cancelled'];

/* Counts for the tab strip and the sub-heading */
$nFlats     = (int) scalar("SELECT COUNT(*) FROM rent_flats");
$nAvailable = (int) scalar("SELECT COUNT(*) FROM rent_flats WHERE status='available'");
$nOwners    = (int) scalar("SELECT COUNT(*) FROM rent_owners");
$nSeekers   = (int) scalar("SELECT COUNT(*) FROM rent_seekers");
$nSearching = (int) scalar("SELECT COUNT(*) FROM rent_seekers WHERE status='searching'");
$nVisits    = (int) scalar("SELECT COUNT(*) FROM rent_visits WHERE status='scheduled'");
$nAgr       = (int) scalar("SELECT COUNT(*) FROM rent_agreements WHERE status='active'");
$nDue       = (int) scalar("SELECT COUNT(*) FROM rent_agreements WHERE brokerage_received=0 AND brokerage_amount>0");

$tabCount = ['flats'=>$nFlats,'owners'=>$nOwners,'enquiries'=>$nSeekers,
             'visits'=>$nVisits,'agreements'=>$nAgr,'brokerage'=>$nDue];

$msg     = '';
$tabFile = __DIR__ . '/rent/' . $tab . '.php';

// pass 1 — POST handling, before any output so a redirect still works
$preRender = true;
if (is_file($tabFile)) require $tabFile;

require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
  <div>
    <h2>Rent</h2>
    <p><?= $nAvailable ?> flat<?= $nAvailable==1?'':'s' ?> available &middot;
       <?= $nSearching ?> looking &middot; <?= $nAgr ?> agreement<?= $nAgr==1?'':'s' ?> running</p>
  </div>
  <div style="display:flex;gap:8px;flex-wrap:wrap">
    <a class="btn ghost" href="<?= url('rent_seeker_form') ?>"><?= icon('plus',16) ?> Add Enquiry</a>
    <a class="btn primary" href="<?= url('rent_flat_form') ?>"><?= icon('plus',16) ?> Add Flat</a>
  </div>
</div>

<?php if ($msg): ?>
  <div class="login-err" style="background:var(--green-bg);color:var(--green);margin-bottom:16px">&#10003; <?= e($msg) ?></div>
<?php endif; ?>

<div class="tabs">
  <?php foreach ($tabs as $k => $label): ?>
    <a href="<?= url('rent',['tab'=>$k]) ?>" class="<?= $tab===$k?'active':'' ?>"><?= e($label) ?><?php
      if (!empty($tabCount[$k])): ?> (<?= $tabCount[$k] ?>)<?php endif; ?></a>
  <?php endforeach; ?>
</div>

<?php
// pass 2 — draw the tab
$preRender = false;
if (is_file($tabFile)) {
    require $tabFile;
} else {
    echo '<div class="card empty">This tab is not available.</div>';
}
require __DIR__ . '/../includes/footer.php';

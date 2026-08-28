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

/* The rent tables live in their own SQL file, so an install that has the rest
   of the app can still be missing them — the code was uploaded but setup.php
   was not re-run. This probe has to come before the counts below, or those
   queries throw first and the whole section answers with a bare 500 that says
   nothing about what to do. */
$rentReady = true;
try {
    db()->query("SELECT 1 FROM rent_flats LIMIT 1");
    db()->query("SELECT 1 FROM rent_owners LIMIT 1");
    db()->query("SELECT 1 FROM rent_seekers LIMIT 1");
    db()->query("SELECT 1 FROM rent_visits LIMIT 1");
    db()->query("SELECT 1 FROM rent_agreements LIMIT 1");
} catch (Throwable $e) {
    $rentReady = false;
}

/* Counts for the tab strip and the sub-heading */
$nFlats = $nAvailable = $nOwners = $nSeekers = $nSearching = $nVisits = $nAgr = $nDue = 0;
if ($rentReady) {
    $nFlats     = (int) scalar("SELECT COUNT(*) FROM rent_flats");
    $nAvailable = (int) scalar("SELECT COUNT(*) FROM rent_flats WHERE status='available'");
    $nOwners    = (int) scalar("SELECT COUNT(*) FROM rent_owners");
    $nSeekers   = (int) scalar("SELECT COUNT(*) FROM rent_seekers");
    $nSearching = (int) scalar("SELECT COUNT(*) FROM rent_seekers WHERE status='searching'");
    $nVisits    = (int) scalar("SELECT COUNT(*) FROM rent_visits WHERE status='scheduled'");
    $nAgr       = (int) scalar("SELECT COUNT(*) FROM rent_agreements WHERE status='active'");
    $nDue       = (int) scalar("SELECT COUNT(*) FROM rent_agreements WHERE brokerage_received=0 AND brokerage_amount>0");
}

$tabCount = ['flats'=>$nFlats,'owners'=>$nOwners,'enquiries'=>$nSeekers,
             'visits'=>$nVisits,'agreements'=>$nAgr,'brokerage'=>$nDue];

$msg     = '';
$tabFile = __DIR__ . '/rent/' . $tab . '.php';

// pass 1 — POST handling, before any output so a redirect still works
$preRender = true;
if ($rentReady && is_file($tabFile)) require $tabFile;

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
if (!$rentReady) {
    ?>
    <div class="card">
      <div style="display:flex;gap:14px;align-items:flex-start">
        <span class="ic-box amber" style="width:40px;height:40px;border-radius:11px;flex:0 0 40px"><?= icon('info',20) ?></span>
        <div>
          <h3 style="font-size:16px;font-weight:700">Rent is not set up on this server yet</h3>
          <p class="muted small" style="margin-top:6px;max-width:620px">
            The Rent pages are installed, but the tables they read from are not in the database.
            That happens when new code is uploaded without running setup again.
          </p>
          <p class="small" style="margin-top:12px">To finish it, either:</p>
          <ul class="small muted" style="margin:6px 0 0 18px;line-height:1.9">
            <li>open <strong>setup.php</strong> on your domain and run it — it creates the missing
                tables and leaves everything else untouched; or</li>
            <li>import <strong>sql/rent.sql</strong> once in phpMyAdmin.</li>
          </ul>
          <p class="muted tiny" style="margin-top:12px">
            If setup.php reports that the install is locked, delete
            <code>config/installed.lock</code> and open it again.
          </p>
        </div>
      </div>
    </div>
    <?php
} elseif (is_file($tabFile)) {
    require $tabFile;
} else {
    echo '<div class="card empty">This tab is not available.</div>';
}
require __DIR__ . '/../includes/footer.php';

<?php
/** RE360 — Add / edit client + requirement (3-part budget) */
require_once __DIR__ . '/../includes/icons.php';
require_once __DIR__ . '/../includes/crud.php';
$page = 'leads'; $pageTitle = 'Client Form';

$id = (int)($_GET['id'] ?? 0);
$c  = $id ? row("SELECT * FROM clients WHERE id=?", [$id]) : [];
$r  = $id ? row("SELECT * FROM client_requirements WHERE client_id=?", [$id]) : [];
$err = '';

$clientFields = ['name','mobile','email','location','profession','purpose','status','source'];
$reqFields = ['preferred_location','alt_location','bhk','min_carpet','agreement_budget','all_in_budget',
  'own_contribution','loan_amount','loan_required','preferred_floor','facing','possession_within_months',
  'parking','amenities_pref','builder_pref','ready_or_uc'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (trim($_POST['name'] ?? '') === '') {
        $err = 'Client name is required.';
    } else {
        $_POST['loan_required'] = isset($_POST['loan_required']) ? 1 : 0;
        $id = save_row('clients', $clientFields, $_POST, $id ?: null);
        $reqId = $r['id'] ?? (int) scalar("SELECT id FROM client_requirements WHERE client_id=?", [$id]);
        $_POST['client_id'] = $id;
        save_row('client_requirements', array_merge(['client_id'], $reqFields), $_POST, $reqId ?: null);
        log_activity('client_saved','client',$id,'Client saved – ' . $_POST['name'],'leads');
        header('Location: ' . url('client_view', ['id'=>$id])); exit;
    }
}
// raw values — the field helpers escape internally
$v  = fn($k, $d='') => $_POST[$k] ?? $c[$k] ?? $d;
$vr = fn($k, $d='') => $_POST[$k] ?? $r[$k] ?? $d;
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
  <div><h2><?= $id ? 'Edit Client' : 'Add Client / Lead' ?></h2><p>Capture the requirement properly — the matching engine depends on it</p></div>
  <a class="btn ghost" href="<?= url('leads') ?>">← Back</a>
</div>
<?php if ($err): ?><div class="login-err" style="margin-bottom:16px"><?= e($err) ?></div><?php endif; ?>

<form method="post" class="card">
  <div class="form-grid">
    <div class="form-section-title">Client Details</div>
    <?= field('Name *','name',$v('name'),'text',['required'=>'required']) ?>
    <?= field('Mobile','mobile',$v('mobile')) ?>
    <?= field('Email','email',$v('email'),'email') ?>
    <?= field('Current Location','location',$v('location')) ?>
    <?= field('Profession','profession',$v('profession')) ?>
    <?= select_field('Buying Purpose','purpose',['self'=>'Self Use','investment'=>'Investment','rental'=>'Rental','parents'=>'Parents','second_home'=>'Second Home'],$v('purpose','self')) ?>
    <?= select_field('Pipeline Stage','status',['new'=>'New','contacted'=>'Contacted','site_visit'=>'Site Visit','negotiation'=>'Negotiation','booked'=>'Booked','lost'=>'Lost'],$v('status','new')) ?>
    <?= field('Source','source',$v('source'),'text',['placeholder'=>'Referral / Website / Walk-in']) ?>

    <div class="form-section-title">Requirement</div>
    <?= select_field('Preferred Location','preferred_location',$GLOBALS['RE360_LOCATIONS'],$vr('preferred_location'),true) ?>
    <?= select_field('Alternative Location','alt_location',$GLOBALS['RE360_LOCATIONS'],$vr('alt_location'),true) ?>
    <?= select_field('Configuration','bhk',$GLOBALS['RE360_CONFIGS'],$vr('bhk','2 BHK')) ?>
    <?= field('Min Carpet (sq.ft.)','min_carpet',$vr('min_carpet'),'number') ?>
    <?= field('Preferred Floor','preferred_floor',$vr('preferred_floor')) ?>
    <?= select_field('Facing','facing',['East','West','North','South'],$vr('facing'),true) ?>
    <?= field('Possession within (months)','possession_within_months',$vr('possession_within_months','24'),'number') ?>
    <?= select_field('Ready / Under Construction','ready_or_uc',['any'=>'Any','ready'=>'Ready Possession','under_construction'=>'Under Construction'],$vr('ready_or_uc','any')) ?>
    <?= field('Parking Requirement','parking',$vr('parking')) ?>
    <?= field('Builder Preference','builder_pref',$vr('builder_pref')) ?>
    <?= field('Amenities Preference','amenities_pref',$vr('amenities_pref')) ?>

    <div class="form-section-title">Budget — the 3-part view (critical for real matching)</div>
    <?= field('Agreement Value Budget (₹)','agreement_budget',$vr('agreement_budget'),'number',['step'=>'100000','placeholder'=>'9000000']) ?>
    <?= field('All-in Budget (₹)','all_in_budget',$vr('all_in_budget'),'number',['step'=>'100000','placeholder'=>'10000000']) ?>
    <?= field('Own Contribution (₹)','own_contribution',$vr('own_contribution'),'number',['step'=>'100000','placeholder'=>'3000000']) ?>
    <?= field('Loan Amount (₹)','loan_amount',$vr('loan_amount'),'number',['step'=>'100000','placeholder'=>'7000000']) ?>
    <div class="form-group"><label>Loan Required</label>
      <label style="display:flex;align-items:center;gap:9px;padding:9px 0;font-size:13px">
        <input type="checkbox" name="loan_required" value="1" <?= ($_POST['loan_required'] ?? $r['loan_required'] ?? 1) ? 'checked':'' ?>> Client needs a home loan
      </label></div>
  </div>
  <div style="display:flex;gap:10px;margin-top:22px">
    <button class="btn primary" type="submit"><?= icon('verified',16) ?> Save Client</button>
    <a class="btn ghost" href="<?= url('leads') ?>">Cancel</a>
  </div>
</form>
<?php require __DIR__ . '/../includes/footer.php'; ?>

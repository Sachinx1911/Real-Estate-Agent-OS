<?php
/**
 * RE360 — Rent.
 *
 * Deliberately separate from the sale side: its own tables, its own page,
 * nothing shared with builders / projects / inventory / clients.
 *
 * Two registers:
 *   Flats    — what owners have given us to rent out
 *   Seekers  — people looking to take a flat on rent
 */
require_once __DIR__ . '/../includes/icons.php';
require_once __DIR__ . '/../includes/crud.php';
$page = 'rent'; $pageTitle = 'Rent';

$tab = ($_GET['tab'] ?? 'flats') === 'seekers' ? 'seekers' : 'flats';
$msg = '';

/* ---------------- quick status change + delete ---------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_POST['set_flat_status']) && !empty($_POST['flat_id'])) {
        db()->prepare("UPDATE rent_flats SET status=? WHERE id=?")
            ->execute([$_POST['set_flat_status'], (int)$_POST['flat_id']]);
        $msg = 'Flat status updated.';
    } elseif (!empty($_POST['set_seeker_status']) && !empty($_POST['seeker_id'])) {
        db()->prepare("UPDATE rent_seekers SET status=? WHERE id=?")
            ->execute([$_POST['set_seeker_status'], (int)$_POST['seeker_id']]);
        $msg = 'Status updated.';
    } elseif (!empty($_POST['delete_flat'])) {
        db()->prepare("DELETE FROM rent_flats WHERE id=?")->execute([(int)$_POST['delete_flat']]);
        $msg = 'Flat removed from the rent list.';
    } elseif (!empty($_POST['delete_seeker'])) {
        db()->prepare("DELETE FROM rent_seekers WHERE id=?")->execute([(int)$_POST['delete_seeker']]);
        $msg = 'Entry removed.';
    }
}

/* ---------------- filters ----------------
   The location filter targets a different column on each tab, so the
   condition is built per tab rather than shared. */
$q      = trim($_GET['q'] ?? '');
$loc    = trim($_GET['loc'] ?? '');
$cfg    = trim($_GET['cfg'] ?? '');
$status = trim($_GET['status'] ?? '');

$w = []; $p = [];
if ($cfg !== '')    { $w[] = "config = ?"; $p[] = $cfg; }
if ($status !== '') { $w[] = "status = ?"; $p[] = $status; }

if ($tab === 'flats') {
    if ($loc !== '') { $w[] = "location = ?"; $p[] = $loc; }
    if ($q !== '') {
        $w[] = "(building_name LIKE ? OR flat_no LIKE ? OR owner_name LIKE ? OR owner_mobile LIKE ? OR sector LIKE ?)";
        array_push($p, "%$q%", "%$q%", "%$q%", "%$q%", "%$q%");
    }
    $where = $w ? 'WHERE ' . implode(' AND ', $w) : '';
    $list  = rows("SELECT * FROM rent_flats $where ORDER BY updated_at DESC", $p);
    $locOpts = rows("SELECT DISTINCT location AS l FROM rent_flats WHERE location <> '' ORDER BY location");
} else {
    if ($loc !== '') { $w[] = "preferred_location = ?"; $p[] = $loc; }
    if ($q !== '') {
        $w[] = "(name LIKE ? OR mobile LIKE ? OR preferred_location LIKE ?)";
        array_push($p, "%$q%", "%$q%", "%$q%");
    }
    $where = $w ? 'WHERE ' . implode(' AND ', $w) : '';
    $list  = rows("SELECT * FROM rent_seekers $where ORDER BY updated_at DESC", $p);
    $locOpts = rows("SELECT DISTINCT preferred_location AS l FROM rent_seekers WHERE preferred_location <> '' ORDER BY preferred_location");
}

/* ---------------- counts ---------------- */
$nFlats     = (int) scalar("SELECT COUNT(*) FROM rent_flats");
$nAvailable = (int) scalar("SELECT COUNT(*) FROM rent_flats WHERE status='available'");
$nSeekers   = (int) scalar("SELECT COUNT(*) FROM rent_seekers");
$nSearching = (int) scalar("SELECT COUNT(*) FROM rent_seekers WHERE status='searching'");

$furnLabel    = ['unfurnished'=>'Unfurnished','semi_furnished'=>'Semi furnished','fully_furnished'=>'Fully furnished','any'=>'Any'];
$flatStatus   = ['available'=>'Available','rented'=>'Rented','on_hold'=>'On hold'];
$seekerStatus = ['searching'=>'Searching','shown'=>'Shown flats','finalised'=>'Finalised','dropped'=>'Dropped'];

require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
  <div>
    <h2>Rent</h2>
    <p>Flats given for rent, and people looking for one</p>
  </div>
  <div style="display:flex;gap:8px">
    <?php if ($tab === 'flats'): ?>
      <a class="btn primary" href="<?= url('rent_flat_form') ?>"><?= icon('plus',16) ?> Add Flat</a>
    <?php else: ?>
      <a class="btn primary" href="<?= url('rent_seeker_form') ?>"><?= icon('plus',16) ?> Add Person</a>
    <?php endif; ?>
  </div>
</div>

<?php if ($msg): ?>
  <div class="login-err" style="background:var(--green-bg);color:var(--green);margin-bottom:16px">&#10003; <?= e($msg) ?></div>
<?php endif; ?>

<div class="tabs">
  <a href="<?= url('rent',['tab'=>'flats']) ?>"   class="<?= $tab==='flats'?'active':'' ?>">Flats for Rent (<?= $nFlats ?>)</a>
  <a href="<?= url('rent',['tab'=>'seekers']) ?>" class="<?= $tab==='seekers'?'active':'' ?>">Looking for a Flat (<?= $nSeekers ?>)</a>
</div>

<div class="kpi-row" style="grid-template-columns:repeat(4,1fr);margin-bottom:18px">
  <div class="kpi"><div class="top"><div class="ic-box green"><?= icon('key',20) ?></div><div class="k-label">Available Now</div></div><div class="k-value"><?= $nAvailable ?></div></div>
  <div class="kpi"><div class="top"><div class="ic-box blue"><?= icon('building',20) ?></div><div class="k-label">Total Flats</div></div><div class="k-value"><?= $nFlats ?></div></div>
  <div class="kpi"><div class="top"><div class="ic-box pink"><?= icon('leads',20) ?></div><div class="k-label">Still Searching</div></div><div class="k-value"><?= $nSearching ?></div></div>
  <div class="kpi"><div class="top"><div class="ic-box purple"><?= icon('user',20) ?></div><div class="k-label">Total People</div></div><div class="k-value"><?= $nSeekers ?></div></div>
</div>

<form class="card" style="margin-bottom:18px;padding:14px" method="get" data-autofilter>
  <input type="hidden" name="page" value="rent">
  <input type="hidden" name="tab" value="<?= e($tab) ?>">
  <div style="display:flex;gap:10px;flex-wrap:wrap">
    <div class="search" style="flex:1;min-width:220px;background:var(--bg-card-2)">
      <?= icon('search',18) ?>
      <input type="text" name="q" value="<?= e($q) ?>"
             placeholder="<?= $tab==='flats' ? 'Building, flat no, owner name or mobile...' : 'Name, mobile or location...' ?>">
    </div>
    <select class="select" name="loc"><option value="">All Locations</option>
      <?php foreach ($locOpts as $o): ?><option value="<?= e($o['l']) ?>" <?= $loc===$o['l']?'selected':'' ?>><?= e($o['l']) ?></option><?php endforeach; ?></select>
    <select class="select" name="cfg"><option value="">All BHK</option>
      <?php foreach ($GLOBALS['RE360_CONFIGS'] as $c): ?><option value="<?= e($c) ?>" <?= $cfg===$c?'selected':'' ?>><?= e($c) ?></option><?php endforeach; ?></select>
    <select class="select" name="status"><option value="">All Status</option>
      <?php foreach (($tab==='flats'?$flatStatus:$seekerStatus) as $k=>$l): ?>
        <option value="<?= $k ?>" <?= $status===$k?'selected':'' ?>><?= $l ?></option><?php endforeach; ?></select>
    <button class="btn primary" type="submit">Filter</button>
    <?php if ($where): ?><a class="btn ghost" href="<?= url('rent',['tab'=>$tab]) ?>">Clear</a><?php endif; ?>
  </div>
</form>

<div class="card pad0">
  <div class="table-wrap">
  <?php if ($tab === 'flats'): ?>
    <table class="data">
      <thead><tr>
        <th>Building</th><th>Flat No</th><th>Location</th><th>Sector</th><th>BHK</th>
        <th>Rent</th><th>Deposit</th><th>Furnishing</th><th>Available</th>
        <th>Owner</th><th>Contact</th><th>Status</th><th class="no-print"></th>
      </tr></thead>
      <tbody>
      <?php foreach ($list as $f): ?>
        <tr>
          <td class="strong"><?= e($f['building_name']) ?>
            <?php if ($f['building_location']): ?><div class="muted tiny"><?= e($f['building_location']) ?></div><?php endif; ?>
          </td>
          <td><?= e($f['flat_no'] ?: '—') ?><?php if ($f['wing']): ?> <span class="muted tiny">(<?= e($f['wing']) ?>)</span><?php endif; ?></td>
          <td><?= e($f['location'] ?: '—') ?></td>
          <td><?= e($f['sector'] ?: '—') ?></td>
          <td><?= e($f['config'] ?: '—') ?></td>
          <td class="strong"><?= (int)$f['rent'] ? money_full($f['rent']) : '—' ?></td>
          <td><?= (int)$f['deposit'] ? money_full($f['deposit']) : '—' ?></td>
          <td><?= e($furnLabel[$f['furnishing']] ?? '—') ?></td>
          <td><?= $f['available_from'] ? e(fdate($f['available_from'],'d M Y')) : 'Now' ?></td>
          <td><?= e($f['owner_name']) ?></td>
          <td><?php if ($f['owner_mobile']): ?><a class="link" href="tel:<?= e($f['owner_mobile']) ?>"><?= e($f['owner_mobile']) ?></a><?php else: ?>—<?php endif; ?></td>
          <td>
            <form method="post" style="display:inline"><?= csrf_field() ?>
              <input type="hidden" name="flat_id" value="<?= $f['id'] ?>">
              <select class="select sm" name="set_flat_status" onchange="this.form.submit()">
                <?php foreach ($flatStatus as $k=>$l): ?>
                  <option value="<?= $k ?>" <?= $f['status']===$k?'selected':'' ?>><?= $l ?></option>
                <?php endforeach; ?>
              </select>
            </form>
          </td>
          <td class="no-print">
            <a class="link" href="<?= url('rent_flat_form',['id'=>$f['id']]) ?>">Edit</a> &middot;
            <form method="post" style="display:inline" onsubmit="return confirm('Remove this flat from the rent list?')"><?= csrf_field() ?>
              <button class="link" name="delete_flat" value="<?= $f['id'] ?>" style="color:var(--red)">Delete</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$list): ?>
        <tr><td colspan="13" class="center muted" style="padding:36px">
          No flats yet. <a class="link" href="<?= url('rent_flat_form') ?>">Add the first one &rarr;</a></td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  <?php else: ?>
    <table class="data">
      <thead><tr>
        <th>Name</th><th>Contact</th><th>Occupation</th><th>Wants In</th><th>Sector</th>
        <th>BHK</th><th>Budget / month</th><th>Furnishing</th><th>Needed From</th>
        <th>Type</th><th>Status</th><th class="no-print"></th>
      </tr></thead>
      <tbody>
      <?php foreach ($list as $s):
        $bmin = (int)$s['budget_min']; $bmax = (int)$s['budget_max'];
        if ($bmin && $bmax)      $budget = money_full($bmin).' – '.money_full($bmax);
        elseif ($bmax)           $budget = 'up to '.money_full($bmax);
        elseif ($bmin)           $budget = money_full($bmin).'+';
        else                     $budget = '—';
      ?>
        <tr>
          <td class="strong"><?= e($s['name']) ?></td>
          <td><?php if ($s['mobile']): ?><a class="link" href="tel:<?= e($s['mobile']) ?>"><?= e($s['mobile']) ?></a><?php else: ?>—<?php endif; ?></td>
          <td><?= e($s['occupation'] ?: '—') ?></td>
          <td><?= e($s['preferred_location'] ?: '—') ?></td>
          <td><?= e($s['preferred_sector'] ?: '—') ?></td>
          <td><?= e($s['config'] ?: '—') ?></td>
          <td class="strong"><?= $budget ?></td>
          <td><?= e($furnLabel[$s['furnishing']] ?? 'Any') ?></td>
          <td><?= $s['needed_from'] ? e(fdate($s['needed_from'],'d M Y')) : '—' ?></td>
          <td><?= e(ucfirst($s['family_type'])) ?></td>
          <td>
            <form method="post" style="display:inline"><?= csrf_field() ?>
              <input type="hidden" name="seeker_id" value="<?= $s['id'] ?>">
              <select class="select sm" name="set_seeker_status" onchange="this.form.submit()">
                <?php foreach ($seekerStatus as $k=>$l): ?>
                  <option value="<?= $k ?>" <?= $s['status']===$k?'selected':'' ?>><?= $l ?></option>
                <?php endforeach; ?>
              </select>
            </form>
          </td>
          <td class="no-print">
            <a class="link" href="<?= url('rent_seeker_form',['id'=>$s['id']]) ?>">Edit</a> &middot;
            <form method="post" style="display:inline" onsubmit="return confirm('Remove this person from the list?')"><?= csrf_field() ?>
              <button class="link" name="delete_seeker" value="<?= $s['id'] ?>" style="color:var(--red)">Delete</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$list): ?>
        <tr><td colspan="12" class="center muted" style="padding:36px">
          Nobody added yet. <a class="link" href="<?= url('rent_seeker_form') ?>">Add the first person &rarr;</a></td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  <?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>

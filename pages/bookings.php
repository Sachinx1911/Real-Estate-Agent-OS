<?php
/** RE360 — Bookings (booking a flat auto-updates its inventory status) */
require_once __DIR__ . '/../includes/icons.php';
require_once __DIR__ . '/../includes/crud.php';
$page = 'bookings'; $pageTitle = 'Bookings';
$msg = ''; $err = '';

/* stage → inventory status mapping */
$stageToInvStatus = ['token'=>'token','booked'=>'booked','agreement'=>'agreement','registered'=>'registered'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($_POST['action'] ?? '') === 'delete_booking' && !empty($_POST['booking_id'])) {
        $bid = (int)$_POST['booking_id'];
        $bk  = row("SELECT b.*, c.name AS cname, p.name AS pname FROM bookings b
                    JOIN clients c ON c.id=b.client_id JOIN projects p ON p.id=b.project_id
                    WHERE b.id=?", [$bid]);
        if (!$bk) {
            $err = 'That booking no longer exists.';
        } else {
            db()->prepare("DELETE FROM bookings WHERE id=?")->execute([$bid]);

            /* The booking is what put this flat out of circulation. With the
             * booking gone the flat has to go back on the market, or it stays
             * marked sold forever with nothing behind it. */
            if ($bk['flat_id']) {
                db()->prepare("UPDATE inventory SET status='available', last_verified_at=NOW(), verified_by=? WHERE id=?")
                    ->execute([current_user()['id'] ?? null, $bk['flat_id']]);
            }

            /* Same for the client: only step them back if this was their last
             * booking — someone who booked twice is still a buyer. */
            $left = (int) scalar("SELECT COUNT(*) FROM bookings WHERE client_id=?", [$bk['client_id']]);
            if ($left === 0) {
                db()->prepare("UPDATE clients SET status='negotiation' WHERE id=?")->execute([$bk['client_id']]);
            }

            log_activity('booking_deleted','project',(int)$bk['project_id'],
                         'Booking deleted – ' . $bk['cname'] . ' / ' . $bk['pname'], 'bookings');
            $msg = 'Booking deleted.'
                 . ($bk['flat_id'] ? ' Flat is available again.' : '')
                 . ($left === 0 ? ' Client moved back to Negotiation.' : '');
        }
    } elseif (!empty($_POST['update_stage']) && !empty($_POST['booking_id'])) {
        $bid = (int)$_POST['booking_id']; $stage = $_POST['update_stage'];
        if (isset($stageToInvStatus[$stage])) {
            db()->prepare("UPDATE bookings SET stage=? WHERE id=?")->execute([$stage, $bid]);
            $bk = row("SELECT * FROM bookings WHERE id=?", [$bid]);
            if ($bk && $bk['flat_id']) {
                db()->prepare("UPDATE inventory SET status=?, last_verified_at=NOW(), verified_by=? WHERE id=?")
                    ->execute([$stageToInvStatus[$stage], current_user()['id'] ?? null, $bk['flat_id']]);
            }
            $msg = 'Booking stage updated.';
        }
    } elseif (!empty($_POST['client_id']) && !empty($_POST['project_id'])) {
        $id = save_row('bookings', ['client_id','project_id','flat_id','value','stage','booking_date','notes'], $_POST, null);
        // update flat status + client stage
        if (!empty($_POST['flat_id'])) {
            $st = $stageToInvStatus[$_POST['stage'] ?? 'token'] ?? 'booked';
            db()->prepare("UPDATE inventory SET status=?, last_verified_at=NOW(), verified_by=? WHERE id=?")
                ->execute([$st, current_user()['id'] ?? null, (int)$_POST['flat_id']]);
        }
        db()->prepare("UPDATE clients SET status='booked' WHERE id=?")->execute([(int)$_POST['client_id']]);
        $pname = scalar("SELECT name FROM projects WHERE id=?", [(int)$_POST['project_id']]);
        log_activity('booking_added','project',(int)$_POST['project_id'], "New booking – $pname", 'bookings');
        $msg = 'Booking recorded. Flat status and client stage updated.';
    }
}

$bookings = rows("SELECT b.*, c.name AS cname, c.mobile, p.name AS pname, i.flat_no
                  FROM bookings b JOIN clients c ON c.id=b.client_id JOIN projects p ON p.id=b.project_id
                  LEFT JOIN inventory i ON i.id=b.flat_id
                  ORDER BY b.booking_date DESC");

$thisMonth = (int) scalar("SELECT COUNT(*) FROM bookings WHERE MONTH(booking_date)=MONTH(CURDATE()) AND YEAR(booking_date)=YEAR(CURDATE())");
$monthValue = (int) scalar("SELECT COALESCE(SUM(value),0) FROM bookings WHERE MONTH(booking_date)=MONTH(CURDATE()) AND YEAR(booking_date)=YEAR(CURDATE())");
$totalValue = (int) scalar("SELECT COALESCE(SUM(value),0) FROM bookings");

$clientOpts = []; foreach (rows("SELECT id,name FROM clients ORDER BY name") as $c) { $clientOpts[$c['id']] = $c['name']; }
$projOpts = [];   foreach (rows("SELECT id,name FROM projects ORDER BY name") as $p) { $projOpts[$p['id']] = $p['name']; }
$flatOpts = [];   foreach (rows("SELECT i.id, i.flat_no, p.name pname FROM inventory i JOIN projects p ON p.id=i.project_id WHERE i.status IN ('available','hold','token') ORDER BY p.name, i.flat_no") as $f) {
    $flatOpts[$f['id']] = $f['pname'] . ' — ' . $f['flat_no'];
}
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
  <div><h2>Bookings</h2><p><?= count($bookings) ?> total bookings</p></div>
</div>
<?php if ($msg): ?><div class="login-err" style="background:var(--green-bg);color:var(--green);margin-bottom:16px">✓ <?= e($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="login-err" style="margin-bottom:16px"><?= e($err) ?></div><?php endif; ?>

<div class="kpi-row" style="grid-template-columns:repeat(3,1fr);margin-bottom:18px">
  <div class="kpi"><div class="top"><div class="ic-box teal"><?= icon('bookings',20) ?></div><div class="k-label">This Month</div></div><div class="k-value"><?= $thisMonth ?></div></div>
  <div class="kpi"><div class="top"><div class="ic-box gold"><?= icon('money',20) ?></div><div class="k-label">Month Value</div></div><div class="k-value"><?= money($monthValue) ?></div></div>
  <div class="kpi"><div class="top"><div class="ic-box green"><?= icon('chart',20) ?></div><div class="k-label">Total Value</div></div><div class="k-value"><?= money($totalValue) ?></div></div>
</div>

<div class="grid" style="grid-template-columns:1fr 1.7fr">
  <form method="post" class="card"><?= csrf_field() ?>
    <div class="card-head"><h3>Record a Booking</h3></div>
    <?= select_field('Client','client_id',$clientOpts,'',true) ?>
    <?= select_field('Project','project_id',$projOpts,'',true) ?>
    <?= select_field('Flat (optional)','flat_id',$flatOpts,'',true) ?>
    <?= field('Agreement Value (₹)','value','','number',['step'=>'50000']) ?>
    <?= select_field('Stage','stage',['token'=>'Token','booked'=>'Booked','agreement'=>'Agreement','registered'=>'Registered'],'token') ?>
    <?= field('Booking Date','booking_date',date('Y-m-d'),'date') ?>
    <?= textarea_field('Notes','notes','') ?>
    <button class="btn primary" type="submit" style="margin-top:14px"><?= icon('plus',16) ?> Record Booking</button>
    <p class="muted tiny" style="margin-top:10px">Selecting a flat updates its inventory status automatically.</p>
  </form>

  <div class="card pad0">
    <div class="card-head" style="padding:18px"><h3>All Bookings</h3></div>
    <div class="table-wrap">
      <table class="data">
        <thead><tr><th>Client</th><th>Project</th><th>Flat</th><th>Value</th><th>Stage</th><th>Date</th><th>Move to</th></tr></thead>
        <tbody>
        <?php foreach ($bookings as $b): ?>
          <tr>
            <td class="strong"><a class="link" href="<?= url('client_view',['id'=>$b['client_id']]) ?>"><?= e($b['cname']) ?></a></td>
            <td><?= e($b['pname']) ?></td>
            <td><?= e($b['flat_no'] ?: '—') ?></td>
            <td class="strong"><?= money($b['value']) ?></td>
            <td><span class="badge <?= ['token'=>'violet','booked'=>'blue','agreement'=>'teal','registered'=>'green'][$b['stage']] ?? 'grey' ?>"><?= e(ucfirst($b['stage'])) ?></span></td>
            <td><?= fdate($b['booking_date']) ?></td>
            <td>
              <div style="display:flex;gap:6px;align-items:center">
                <form method="post"><?= csrf_field() ?>
                  <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">
                  <select class="select sm" name="update_stage" onchange="this.form.submit()">
                    <option value="">Change…</option>
                    <?php foreach (['token'=>'Token','booked'=>'Booked','agreement'=>'Agreement','registered'=>'Registered'] as $k=>$l): ?>
                      <?php if ($k !== $b['stage']): ?><option value="<?= $k ?>"><?= $l ?></option><?php endif; ?>
                    <?php endforeach; ?>
                  </select>
                </form>
                <form method="post" onsubmit="return confirm('Delete this booking?\n\n<?= e(addslashes($b['cname'])) ?> — <?= e(addslashes($b['pname'])) ?><?= $b['flat_no'] ? ' (' . e(addslashes($b['flat_no'])) . ')' : '' ?>\nValue <?= e(money($b['value'])) ?>\n\n<?= $b['flat_no'] ? 'The flat goes back to Available. ' : '' ?>This cannot be undone.')">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="delete_booking">
                  <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">
                  <button class="btn ghost sm" type="submit" style="color:var(--red)">Delete</button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$bookings): ?><tr><td colspan="7" class="center muted" style="padding:30px">No bookings yet.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>

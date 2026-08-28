<?php
/** Rent → Enquiries. People looking to take a flat on rent. */

if ($preRender) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!empty($_POST['set_seeker_status']) && !empty($_POST['seeker_id'])) {
            db()->prepare("UPDATE rent_seekers SET status=? WHERE id=?")
                ->execute([$_POST['set_seeker_status'], (int)$_POST['seeker_id']]);
            $msg = 'Status updated.';
        } elseif (!empty($_POST['delete_seeker'])) {
            db()->prepare("DELETE FROM rent_seekers WHERE id=?")->execute([(int)$_POST['delete_seeker']]);
            $msg = 'Entry removed.';
        }
    }

    $q      = trim($_GET['q'] ?? '');
    $loc    = trim($_GET['loc'] ?? '');
    $cfg    = trim($_GET['cfg'] ?? '');
    $status = trim($_GET['status'] ?? '');

    $w = []; $p = [];
    if ($loc !== '')    { $w[] = "preferred_location = ?"; $p[] = $loc; }
    if ($cfg !== '')    { $w[] = "config = ?";             $p[] = $cfg; }
    if ($status !== '') { $w[] = "status = ?";             $p[] = $status; }
    if ($q !== '') {
        $w[] = "(name LIKE ? OR mobile LIKE ? OR preferred_location LIKE ?)";
        array_push($p, "%$q%", "%$q%", "%$q%");
    }
    $where = $w ? 'WHERE ' . implode(' AND ', $w) : '';

    $seekers = rows("SELECT * FROM rent_seekers $where ORDER BY updated_at DESC", $p);
    $locOpts = rows("SELECT DISTINCT preferred_location AS l FROM rent_seekers WHERE preferred_location <> '' ORDER BY preferred_location");
    return;
}
?>
<form class="card" style="margin-bottom:18px;padding:14px" method="get" data-autofilter>
  <input type="hidden" name="page" value="rent">
  <input type="hidden" name="tab" value="enquiries">
  <div style="display:flex;gap:10px;flex-wrap:wrap">
    <div class="search" style="flex:1;min-width:220px;background:var(--bg-card-2)">
      <?= icon('search',18) ?>
      <input type="text" name="q" value="<?= e($q) ?>" placeholder="Name, mobile or location...">
    </div>
    <select class="select" name="loc"><option value="">All Locations</option>
      <?php foreach ($locOpts as $o): ?><option value="<?= e($o['l']) ?>" <?= $loc===$o['l']?'selected':'' ?>><?= e($o['l']) ?></option><?php endforeach; ?></select>
    <select class="select" name="cfg"><option value="">All BHK</option>
      <?php foreach ($GLOBALS['RE360_CONFIGS'] as $c): ?><option value="<?= e($c) ?>" <?= $cfg===$c?'selected':'' ?>><?= e($c) ?></option><?php endforeach; ?></select>
    <select class="select" name="status"><option value="">All Status</option>
      <?php foreach ($seekerStatus as $k=>$l): ?><option value="<?= $k ?>" <?= $status===$k?'selected':'' ?>><?= $l ?></option><?php endforeach; ?></select>
    <button class="btn primary" type="submit">Filter</button>
    <?php if ($where): ?><a class="btn ghost" href="<?= url('rent',['tab'=>'enquiries']) ?>">Clear</a><?php endif; ?>
  </div>
</form>

<div class="card pad0">
  <div class="table-wrap">
    <table class="data">
      <thead><tr>
        <th>Name</th><th>Contact</th><th>Occupation</th><th>Wants In</th><th>Sector</th>
        <th>BHK</th><th>Budget / month</th><th>Furnishing</th><th>Needed From</th>
        <th>Type</th><th>Status</th><th class="no-print"></th>
      </tr></thead>
      <tbody>
      <?php foreach ($seekers as $s):
        $bmin = (int)$s['budget_min']; $bmax = (int)$s['budget_max'];
        if ($bmin && $bmax)  $budget = money_full($bmin).' – '.money_full($bmax);
        elseif ($bmax)       $budget = 'up to '.money_full($bmax);
        elseif ($bmin)       $budget = money_full($bmin).'+';
        else                 $budget = '—';
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
          <td class="no-print nowrap">
            <a class="link" href="<?= url('rent',['tab'=>'matcher','seeker'=>$s['id']]) ?>">Match</a> &middot;
            <a class="link" href="<?= url('rent_seeker_form',['id'=>$s['id']]) ?>">Edit</a> &middot;
            <form method="post" style="display:inline" onsubmit="return confirm('Remove this person from the list?')"><?= csrf_field() ?>
              <button class="link" name="delete_seeker" value="<?= $s['id'] ?>" style="color:var(--red)">Delete</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$seekers): ?>
        <tr><td colspan="12" class="center muted" style="padding:36px">
          Nobody added yet. <a class="link" href="<?= url('rent_seeker_form') ?>">Add the first person &rarr;</a></td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

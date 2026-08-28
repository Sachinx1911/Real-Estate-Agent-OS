<?php
/** Rent → Flats. What owners have handed over. */

if ($preRender) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!empty($_POST['set_flat_status']) && !empty($_POST['flat_id'])) {
            db()->prepare("UPDATE rent_flats SET status=? WHERE id=?")
                ->execute([$_POST['set_flat_status'], (int)$_POST['flat_id']]);
            $msg = 'Flat status updated.';
        } elseif (!empty($_POST['delete_flat'])) {
            db()->prepare("DELETE FROM rent_flats WHERE id=?")->execute([(int)$_POST['delete_flat']]);
            $msg = 'Flat removed from the rent list.';
        }
    }

    $q      = trim($_GET['q'] ?? '');
    $loc    = trim($_GET['loc'] ?? '');
    $cfg    = trim($_GET['cfg'] ?? '');
    $status = trim($_GET['status'] ?? '');

    $w = []; $p = [];
    if ($loc !== '')    { $w[] = "f.location = ?"; $p[] = $loc; }
    if ($cfg !== '')    { $w[] = "f.config = ?";   $p[] = $cfg; }
    if ($status !== '') { $w[] = "f.status = ?";   $p[] = $status; }
    if ($q !== '') {
        $w[] = "(f.building_name LIKE ? OR f.flat_no LIKE ? OR f.owner_name LIKE ? OR f.owner_mobile LIKE ? OR f.sector LIKE ?)";
        array_push($p, "%$q%", "%$q%", "%$q%", "%$q%", "%$q%");
    }
    $where = $w ? 'WHERE ' . implode(' AND ', $w) : '';

    $flats = rows("SELECT f.*, o.name AS o_name, o.mobile AS o_mobile
                   FROM rent_flats f LEFT JOIN rent_owners o ON o.id = f.owner_id
                   $where ORDER BY f.building_name, f.flat_no", $p);
    $locOpts = rows("SELECT DISTINCT location AS l FROM rent_flats WHERE location <> '' ORDER BY location");
    return;
}
?>
<form class="card" style="margin-bottom:18px;padding:14px" method="get" data-autofilter>
  <input type="hidden" name="page" value="rent">
  <input type="hidden" name="tab" value="flats">
  <div style="display:flex;gap:10px;flex-wrap:wrap">
    <div class="search" style="flex:1;min-width:220px;background:var(--bg-card-2)">
      <?= icon('search',18) ?>
      <input type="text" name="q" value="<?= e($q) ?>" placeholder="Building, flat no, owner name or mobile...">
    </div>
    <select class="select" name="loc"><option value="">All Locations</option>
      <?php foreach ($locOpts as $o): ?><option value="<?= e($o['l']) ?>" <?= $loc===$o['l']?'selected':'' ?>><?= e($o['l']) ?></option><?php endforeach; ?></select>
    <select class="select" name="cfg"><option value="">All BHK</option>
      <?php foreach ($GLOBALS['RE360_CONFIGS'] as $c): ?><option value="<?= e($c) ?>" <?= $cfg===$c?'selected':'' ?>><?= e($c) ?></option><?php endforeach; ?></select>
    <select class="select" name="status"><option value="">All Status</option>
      <?php foreach ($flatStatus as $k=>$l): ?><option value="<?= $k ?>" <?= $status===$k?'selected':'' ?>><?= $l ?></option><?php endforeach; ?></select>
    <button class="btn primary" type="submit">Filter</button>
    <?php if ($where): ?><a class="btn ghost" href="<?= url('rent',['tab'=>'flats']) ?>">Clear</a><?php endif; ?>
  </div>
</form>

<div class="card pad0">
  <div class="table-wrap">
    <table class="data">
      <thead><tr>
        <th>Building</th><th>Flat No</th><th>Location</th><th>Sector</th><th>BHK</th>
        <th>Rent</th><th>Deposit</th><th>Furnishing</th><th>Available</th>
        <th>Owner</th><th>Contact</th><th>Status</th><th class="no-print"></th>
      </tr></thead>
      <tbody>
      <?php foreach ($flats as $f):
        $ownerName   = $f['o_name']   ?: $f['owner_name'];
        $ownerMobile = $f['o_mobile'] ?: $f['owner_mobile'];
      ?>
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
          <td><?php if ($f['owner_id']): ?>
                <a class="link" href="<?= url('rent',['tab'=>'owners','owner'=>$f['owner_id']]) ?>"><?= e($ownerName) ?></a>
              <?php else: ?><?= e($ownerName ?: '—') ?><?php endif; ?></td>
          <td><?php if ($ownerMobile): ?><a class="link" href="tel:<?= e($ownerMobile) ?>"><?= e($ownerMobile) ?></a><?php else: ?>—<?php endif; ?></td>
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
          <td class="no-print nowrap">
            <a class="link" href="<?= url('rent_flat_form',['id'=>$f['id']]) ?>">Edit</a> &middot;
            <a class="link" href="<?= url('rent_flat_form',['from'=>$f['id']]) ?>"
               title="Add another flat in this same building">+ Flat</a> &middot;
            <form method="post" style="display:inline" onsubmit="return confirm('Remove this flat from the rent list?')"><?= csrf_field() ?>
              <button class="link" name="delete_flat" value="<?= $f['id'] ?>" style="color:var(--red)">Delete</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$flats): ?>
        <tr><td colspan="13" class="center muted" style="padding:36px">
          No flats yet. <a class="link" href="<?= url('rent_flat_form') ?>">Add the first one &rarr;</a></td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

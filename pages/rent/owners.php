<?php
/** Rent → Owners. One record per owner, with the flats they have given. */

if ($preRender) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!empty($_POST['save_owner'])) {
            $oid = save_row('rent_owners', ['name','mobile','alt_mobile','email','address','notes'],
                            $_POST, !empty($_POST['owner_id']) ? (int)$_POST['owner_id'] : null);
            $msg = !empty($_POST['owner_id']) ? 'Owner updated.' : 'Owner added.';
            log_activity(!empty($_POST['owner_id']) ? 'rent_owner_updated' : 'rent_owner_added',
                         'rent_owner', $oid, $msg . ' ' . trim($_POST['name'] ?? ''), 'user');
        } elseif (!empty($_POST['delete_owner'])) {
            $oid = (int)$_POST['delete_owner'];
            // keep the flats; they simply lose the link and fall back to the
            // owner name already stored on the row
            db()->prepare("UPDATE rent_flats SET owner_id=NULL WHERE owner_id=?")->execute([$oid]);
            db()->prepare("DELETE FROM rent_owners WHERE id=?")->execute([$oid]);
            $msg = 'Owner removed. Their flats are still in the list.';
        }
    }

    $q  = trim($_GET['q'] ?? '');
    $wp = []; $pp = [];
    if ($q !== '') { $wp[] = "(o.name LIKE ? OR o.mobile LIKE ?)"; $pp[] = "%$q%"; $pp[] = "%$q%"; }
    $ow = $wp ? 'WHERE ' . implode(' AND ', $wp) : '';

    $owners = rows("SELECT o.*,
                      (SELECT COUNT(*) FROM rent_flats f WHERE f.owner_id=o.id) AS flats,
                      (SELECT COUNT(*) FROM rent_flats f WHERE f.owner_id=o.id AND f.status='available') AS avail
                    FROM rent_owners o $ow ORDER BY o.name", $pp);

    $editOwner = !empty($_GET['edit']) ? row("SELECT * FROM rent_owners WHERE id=?", [(int)$_GET['edit']]) : null;
    $openOwner = !empty($_GET['owner']) ? (int)$_GET['owner'] : 0;
    $ownerFlats = $openOwner
        ? rows("SELECT * FROM rent_flats WHERE owner_id=? ORDER BY building_name, flat_no", [$openOwner])
        : [];
    return;
}
$ov = fn(string $k) => $editOwner[$k] ?? '';
?>
<div class="grid" style="grid-template-columns:1fr 1.9fr">

  <form method="post" class="card" style="align-content:start"><?= csrf_field() ?>
    <div class="card-head"><h3><?= $editOwner ? 'Edit Owner' : 'Add Owner' ?></h3>
      <?php if ($editOwner): ?><a class="link" href="<?= url('rent',['tab'=>'owners']) ?>">Cancel</a><?php endif; ?>
    </div>
    <?php if ($editOwner): ?><input type="hidden" name="owner_id" value="<?= (int)$editOwner['id'] ?>"><?php endif; ?>
    <?= field('Name *','name',$ov('name'),'text',['required'=>'required']) ?>
    <?= field('Mobile','mobile',$ov('mobile'),'text',['placeholder'=>'10-digit number']) ?>
    <?= field('Alternate Mobile','alt_mobile',$ov('alt_mobile'),'text') ?>
    <?= field('Email','email',$ov('email'),'email') ?>
    <?= field('Address','address',$ov('address'),'text') ?>
    <?= textarea_field('Notes','notes',$ov('notes'),'Prefers family tenants, travels abroad, deals through son...') ?>
    <button class="btn primary" name="save_owner" value="1" type="submit" style="margin-top:14px">
      <?= icon('plus',16) ?> <?= $editOwner ? 'Save Changes' : 'Add Owner' ?>
    </button>
  </form>

  <div>
    <form method="get" class="card" style="margin-bottom:14px;padding:12px" data-autofilter>
      <input type="hidden" name="page" value="rent"><input type="hidden" name="tab" value="owners">
      <div class="search" style="background:var(--bg-card-2)">
        <?= icon('search',18) ?>
        <input type="text" name="q" value="<?= e($q) ?>" placeholder="Search owner name or mobile...">
      </div>
    </form>

    <div class="card pad0">
      <div class="table-wrap">
        <table class="data">
          <thead><tr><th>Owner</th><th>Mobile</th><th>Email</th><th>Flats</th><th>Available</th><th class="no-print"></th></tr></thead>
          <tbody>
          <?php foreach ($owners as $o): ?>
            <tr>
              <td class="strong">
                <a class="link" href="<?= url('rent',['tab'=>'owners','owner'=>$o['id']]) ?>"><?= e($o['name']) ?></a>
                <?php if ($o['address']): ?><div class="muted tiny"><?= e($o['address']) ?></div><?php endif; ?>
              </td>
              <td><?php if ($o['mobile']): ?><a class="link" href="tel:<?= e($o['mobile']) ?>"><?= e($o['mobile']) ?></a><?php else: ?>—<?php endif; ?></td>
              <td><?= e($o['email'] ?: '—') ?></td>
              <td><strong><?= (int)$o['flats'] ?></strong></td>
              <td><?php if ((int)$o['avail']): ?><span class="badge green"><?= (int)$o['avail'] ?></span><?php else: ?><span class="muted">0</span><?php endif; ?></td>
              <td class="no-print nowrap">
                <a class="link" href="<?= url('rent',['tab'=>'owners','edit'=>$o['id']]) ?>">Edit</a> &middot;
                <form method="post" style="display:inline" onsubmit="return confirm('Remove this owner? Their flats stay in the list.')"><?= csrf_field() ?>
                  <button class="link" name="delete_owner" value="<?= $o['id'] ?>" style="color:var(--red)">Delete</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$owners): ?>
            <tr><td colspan="6" class="center muted" style="padding:32px">No owners yet. Adding a flat creates one automatically.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <?php if ($openOwner && $ownerFlats): ?>
      <div class="card mt">
        <div class="card-head"><h3>Flats from this owner</h3>
          <a class="link" href="<?= url('rent',['tab'=>'owners']) ?>">Close</a></div>
        <div class="table-wrap">
          <table class="data">
            <thead><tr><th>Building</th><th>Flat</th><th>Location</th><th>BHK</th><th>Rent</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($ownerFlats as $f): ?>
              <tr>
                <td class="strong"><?= e($f['building_name']) ?></td>
                <td><?= e($f['flat_no'] ?: '—') ?></td>
                <td><?= e($f['location'] ?: '—') ?></td>
                <td><?= e($f['config'] ?: '—') ?></td>
                <td class="strong"><?= (int)$f['rent'] ? money_full($f['rent']) : '—' ?></td>
                <td><span class="badge <?= $f['status']==='available'?'green':($f['status']==='rented'?'blue':'amber') ?>">
                  <?= e($flatStatus[$f['status']] ?? $f['status']) ?></span></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>

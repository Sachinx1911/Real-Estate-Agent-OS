<?php
/** Rent → Visits. Who saw which flat, and what they said. */

if ($preRender) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!empty($_POST['save_visit'])) {
            save_row('rent_visits', ['flat_id','seeker_id','visit_date','status','feedback'], $_POST, null);
            // seeing a flat moves the enquiry along on its own
            if (!empty($_POST['seeker_id'])) {
                db()->prepare("UPDATE rent_seekers SET status='shown' WHERE id=? AND status='searching'")
                    ->execute([(int)$_POST['seeker_id']]);
            }
            $msg = 'Visit saved.';
        } elseif (!empty($_POST['visit_status']) && !empty($_POST['visit_id'])) {
            db()->prepare("UPDATE rent_visits SET status=? WHERE id=?")
                ->execute([$_POST['visit_status'], (int)$_POST['visit_id']]);
            $msg = 'Visit updated.';
        } elseif (!empty($_POST['delete_visit'])) {
            db()->prepare("DELETE FROM rent_visits WHERE id=?")->execute([(int)$_POST['delete_visit']]);
            $msg = 'Visit removed.';
        }
    }

    $visits = rows("SELECT v.*, f.building_name, f.flat_no, f.location, s.name AS seeker_name, s.mobile
                    FROM rent_visits v
                    LEFT JOIN rent_flats f   ON f.id = v.flat_id
                    LEFT JOIN rent_seekers s ON s.id = v.seeker_id
                    ORDER BY (v.status='scheduled') DESC, v.visit_date DESC");

    $flatOpts = [];
    foreach (rows("SELECT id, building_name, flat_no, location FROM rent_flats ORDER BY building_name, flat_no") as $f) {
        $flatOpts[$f['id']] = trim($f['building_name'] . ' ' . ($f['flat_no'] ? '· '.$f['flat_no'] : '')
                                 . ($f['location'] ? ' · '.$f['location'] : ''));
    }
    $seekerOpts = [];
    foreach (rows("SELECT id, name, mobile FROM rent_seekers ORDER BY name") as $s) {
        $seekerOpts[$s['id']] = $s['name'] . ($s['mobile'] ? ' · '.$s['mobile'] : '');
    }

    $preFlat   = (int)($_GET['flat'] ?? 0);
    $preSeeker = (int)($_GET['seeker'] ?? 0);
    return;
}
?>
<div class="grid" style="grid-template-columns:1fr 1.9fr">

  <form method="post" class="card" style="align-content:start"><?= csrf_field() ?>
    <div class="card-head"><h3>Log a Visit</h3></div>
    <?= select_field('Flat','flat_id',$flatOpts,$preFlat ?: '',true) ?>
    <?= select_field('Person','seeker_id',$seekerOpts,$preSeeker ?: '',true) ?>
    <?= field('Date &amp; Time','visit_date','','datetime-local') ?>
    <?= select_field('Status','status',$visitStatus,'scheduled') ?>
    <?= textarea_field('Feedback','feedback','','Liked it but wants the rent lower, kitchen too small...') ?>
    <button class="btn primary" name="save_visit" value="1" type="submit" style="margin-top:14px">
      <?= icon('plus',16) ?> Save Visit
    </button>
  </form>

  <div class="card pad0">
    <div class="card-head" style="padding:18px 18px 12px;margin:0"><h3>Visits</h3>
      <span class="muted small"><?= count($visits) ?> logged</span></div>
    <div class="table-wrap">
      <table class="data">
        <thead><tr><th>When</th><th>Flat</th><th>Person</th><th>Contact</th><th>Feedback</th><th>Status</th><th class="no-print"></th></tr></thead>
        <tbody>
        <?php foreach ($visits as $v): ?>
          <tr>
            <td class="strong"><?= $v['visit_date'] ? e(fdate($v['visit_date'],'d M Y')) : '—' ?>
              <?php if ($v['visit_date']): ?><div class="muted tiny"><?= date('g:i A', strtotime($v['visit_date'])) ?></div><?php endif; ?></td>
            <td><?= e($v['building_name'] ?: '—') ?><?php if ($v['flat_no']): ?> <span class="muted tiny"><?= e($v['flat_no']) ?></span><?php endif; ?></td>
            <td><?= e($v['seeker_name'] ?: '—') ?></td>
            <td><?php if ($v['mobile']): ?><a class="link" href="tel:<?= e($v['mobile']) ?>"><?= e($v['mobile']) ?></a><?php else: ?>—<?php endif; ?></td>
            <td style="white-space:normal;max-width:260px"><?= e($v['feedback'] ?: '—') ?></td>
            <td>
              <form method="post" style="display:inline"><?= csrf_field() ?>
                <input type="hidden" name="visit_id" value="<?= $v['id'] ?>">
                <select class="select sm" name="visit_status" onchange="this.form.submit()">
                  <?php foreach ($visitStatus as $k=>$l): ?>
                    <option value="<?= $k ?>" <?= $v['status']===$k?'selected':'' ?>><?= $l ?></option>
                  <?php endforeach; ?>
                </select>
              </form>
            </td>
            <td class="no-print">
              <form method="post" style="display:inline" onsubmit="return confirm('Remove this visit?')"><?= csrf_field() ?>
                <button class="link" name="delete_visit" value="<?= $v['id'] ?>" style="color:var(--red)">Delete</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$visits): ?>
          <tr><td colspan="7" class="center muted" style="padding:32px">No visits logged yet.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

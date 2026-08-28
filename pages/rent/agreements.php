<?php
/**
 * Rent → Agreements.
 *
 * A rent agreement is usually 11 months, and the renewal is repeat income,
 * so end_date is what this tab is built around: what is running, and what
 * runs out soon.
 */

if ($preRender) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!empty($_POST['save_agreement'])) {
            $fields = ['flat_id','seeker_id','tenant_name','tenant_mobile','start_date','end_date',
                       'rent','deposit','maintenance','escalation_pct','notice_period',
                       'brokerage_amount','brokerage_from','status','notes'];
            $aid = save_row('rent_agreements', $fields, $_POST, null);

            // an agreement means the flat is off the market and the owner is known
            if (!empty($_POST['flat_id'])) {
                $fid = (int)$_POST['flat_id'];
                db()->prepare("UPDATE rent_flats SET status='rented' WHERE id=?")->execute([$fid]);
                $own = scalar("SELECT owner_id FROM rent_flats WHERE id=?", [$fid]);
                if ($own) db()->prepare("UPDATE rent_agreements SET owner_id=? WHERE id=?")->execute([$own, $aid]);
            }
            if (!empty($_POST['seeker_id'])) {
                db()->prepare("UPDATE rent_seekers SET status='finalised' WHERE id=?")->execute([(int)$_POST['seeker_id']]);
            }
            log_activity('rent_agreement_added','rent_agreement',$aid,
                'Rent agreement – ' . trim($_POST['tenant_name'] ?? ''), 'file');
            $msg = 'Agreement saved. The flat is now marked rented.';

        } elseif (!empty($_POST['agr_status']) && !empty($_POST['agr_id'])) {
            $aid = (int)$_POST['agr_id'];
            db()->prepare("UPDATE rent_agreements SET status=? WHERE id=?")->execute([$_POST['agr_status'], $aid]);
            // ending an agreement puts the flat back on the market
            if (in_array($_POST['agr_status'], ['terminated','expired'], true)) {
                $fid = scalar("SELECT flat_id FROM rent_agreements WHERE id=?", [$aid]);
                if ($fid) db()->prepare("UPDATE rent_flats SET status='available' WHERE id=?")->execute([$fid]);
                $msg = 'Agreement closed. The flat is back on the available list.';
            } else {
                $msg = 'Agreement updated.';
            }
        } elseif (!empty($_POST['delete_agreement'])) {
            db()->prepare("DELETE FROM rent_agreements WHERE id=?")->execute([(int)$_POST['delete_agreement']]);
            $msg = 'Agreement removed.';
        }
    }

    $agreements = rows("SELECT a.*, f.building_name, f.flat_no, f.location,
                          o.name AS owner_name, o.mobile AS owner_mobile,
                          DATEDIFF(a.end_date, CURDATE()) AS days_left
                        FROM rent_agreements a
                        LEFT JOIN rent_flats f  ON f.id = a.flat_id
                        LEFT JOIN rent_owners o ON o.id = a.owner_id
                        ORDER BY (a.status='active') DESC, a.end_date ASC");

    $flatOpts = [];
    foreach (rows("SELECT id, building_name, flat_no, location, rent, deposit FROM rent_flats ORDER BY building_name, flat_no") as $f) {
        $flatOpts[$f['id']] = trim($f['building_name'] . ' ' . ($f['flat_no'] ? '· '.$f['flat_no'] : '')
                                 . ($f['location'] ? ' · '.$f['location'] : ''));
    }
    $seekerOpts = [];
    foreach (rows("SELECT id, name, mobile FROM rent_seekers ORDER BY name") as $s) {
        $seekerOpts[$s['id']] = $s['name'] . ($s['mobile'] ? ' · '.$s['mobile'] : '');
    }
    return;
}
?>
<div class="grid" style="grid-template-columns:1fr 2fr">

  <form method="post" class="card" style="align-content:start"><?= csrf_field() ?>
    <div class="card-head"><h3>New Agreement</h3></div>
    <?= select_field('Flat','flat_id',$flatOpts,'',true) ?>
    <?= select_field('Tenant (from enquiries)','seeker_id',$seekerOpts,'',true) ?>
    <?= field('Tenant Name on Agreement','tenant_name','','text') ?>
    <?= field('Tenant Mobile','tenant_mobile','','text') ?>
    <?= field('Start Date','start_date','','date') ?>
    <?= field('End Date','end_date','','date',['title'=>'Usually 11 months from the start']) ?>
    <?= field('Rent per month (&#8377;)','rent','','number',['min'=>'0']) ?>
    <?= field('Deposit (&#8377;)','deposit','','number',['min'=>'0']) ?>
    <?= field('Maintenance (&#8377;)','maintenance','','number',['min'=>'0']) ?>
    <?= field('Escalation on renewal (%)','escalation_pct','5','number',['step'=>'0.5','min'=>'0']) ?>
    <?= field('Notice Period','notice_period','','text',['placeholder'=>'e.g. 2 months']) ?>
    <?= field('Brokerage (&#8377;)','brokerage_amount','','number',['min'=>'0']) ?>
    <?= select_field('Brokerage From','brokerage_from',
          ['tenant'=>'Tenant','owner'=>'Owner','both'=>'Both','none'=>'None'],'tenant') ?>
    <?= textarea_field('Notes','notes','') ?>
    <button class="btn primary" name="save_agreement" value="1" type="submit" style="margin-top:14px">
      <?= icon('plus',16) ?> Save Agreement
    </button>
  </form>

  <div class="card pad0">
    <div class="card-head" style="padding:18px 18px 12px;margin:0"><h3>Agreements</h3>
      <span class="muted small"><?= count($agreements) ?> total</span></div>
    <div class="table-wrap">
      <table class="data">
        <thead><tr>
          <th>Flat</th><th>Tenant</th><th>Period</th><th>Ends In</th>
          <th>Rent</th><th>Deposit</th><th>Brokerage</th><th>Status</th><th class="no-print"></th>
        </tr></thead>
        <tbody>
        <?php foreach ($agreements as $a):
          $d = $a['days_left'];
          // an agreement inside 60 days is the one worth chasing
          if ($a['status'] !== 'active' || $d === null)      $endTxt = '—';
          elseif ($d < 0)                                    $endTxt = '<span class="badge red">overdue '.abs($d).'d</span>';
          elseif ($d <= 30)                                  $endTxt = '<span class="badge red">'.$d.' days</span>';
          elseif ($d <= 60)                                  $endTxt = '<span class="badge amber">'.$d.' days</span>';
          else                                               $endTxt = '<span class="muted">'.$d.' days</span>';
        ?>
          <tr>
            <td class="strong"><?= e($a['building_name'] ?: '—') ?>
              <?php if ($a['flat_no']): ?><div class="muted tiny"><?= e($a['flat_no']) ?><?= $a['location'] ? ' · '.e($a['location']) : '' ?></div><?php endif; ?></td>
            <td><?= e($a['tenant_name'] ?: '—') ?>
              <?php if ($a['tenant_mobile']): ?><div class="muted tiny"><a class="link" href="tel:<?= e($a['tenant_mobile']) ?>"><?= e($a['tenant_mobile']) ?></a></div><?php endif; ?></td>
            <td class="tiny"><?= $a['start_date'] ? e(fdate($a['start_date'],'d M y')) : '—' ?><br>
                <?= $a['end_date'] ? e(fdate($a['end_date'],'d M y')) : '—' ?></td>
            <td><?= $endTxt ?></td>
            <td class="strong"><?= (int)$a['rent'] ? money_full($a['rent']) : '—' ?></td>
            <td><?= (int)$a['deposit'] ? money_full($a['deposit']) : '—' ?></td>
            <td><?= (int)$a['brokerage_amount'] ? money_full($a['brokerage_amount']) : '—' ?>
              <?php if ((int)$a['brokerage_amount']): ?>
                <div class="tiny <?= $a['brokerage_received'] ? '' : 'muted' ?>"
                     style="<?= $a['brokerage_received'] ? 'color:var(--green)' : '' ?>">
                  <?= $a['brokerage_received'] ? 'received' : 'pending' ?></div>
              <?php endif; ?></td>
            <td>
              <form method="post" style="display:inline"><?= csrf_field() ?>
                <input type="hidden" name="agr_id" value="<?= $a['id'] ?>">
                <select class="select sm" name="agr_status" onchange="this.form.submit()">
                  <?php foreach ($agrStatus as $k=>$l): ?>
                    <option value="<?= $k ?>" <?= $a['status']===$k?'selected':'' ?>><?= $l ?></option>
                  <?php endforeach; ?>
                </select>
              </form>
            </td>
            <td class="no-print">
              <form method="post" style="display:inline" onsubmit="return confirm('Remove this agreement?')"><?= csrf_field() ?>
                <button class="link" name="delete_agreement" value="<?= $a['id'] ?>" style="color:var(--red)">Delete</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$agreements): ?>
          <tr><td colspan="9" class="center muted" style="padding:32px">No agreements yet.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php
/** Rent → Brokerage. What was earned, what is still to be collected. */

if ($preRender) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['mark_id'])) {
        $aid = (int)$_POST['mark_id'];
        if (!empty($_POST['mark_received'])) {
            db()->prepare("UPDATE rent_agreements SET brokerage_received=1, brokerage_date=CURDATE() WHERE id=?")
                ->execute([$aid]);
            $msg = 'Marked as received.';
        } else {
            db()->prepare("UPDATE rent_agreements SET brokerage_received=0, brokerage_date=NULL WHERE id=?")
                ->execute([$aid]);
            $msg = 'Marked as pending again.';
        }
    }

    $earned  = (int) scalar("SELECT COALESCE(SUM(brokerage_amount),0) FROM rent_agreements WHERE brokerage_received=1");
    $pending = (int) scalar("SELECT COALESCE(SUM(brokerage_amount),0) FROM rent_agreements WHERE brokerage_received=0 AND brokerage_amount>0");
    $thisMonth = (int) scalar("SELECT COALESCE(SUM(brokerage_amount),0) FROM rent_agreements
                               WHERE brokerage_received=1 AND MONTH(brokerage_date)=MONTH(CURDATE())
                                 AND YEAR(brokerage_date)=YEAR(CURDATE())");
    $deals = (int) scalar("SELECT COUNT(*) FROM rent_agreements WHERE brokerage_amount>0");

    $rowsList = rows("SELECT a.*, f.building_name, f.flat_no, f.location
                      FROM rent_agreements a LEFT JOIN rent_flats f ON f.id=a.flat_id
                      WHERE a.brokerage_amount > 0
                      ORDER BY a.brokerage_received ASC, a.start_date DESC");
    return;
}
?>
<div class="kpi-row" style="grid-template-columns:repeat(4,1fr);margin-bottom:18px">
  <div class="kpi"><div class="top"><div class="ic-box green"><?= icon('money',20) ?></div><div class="k-label">Received</div></div>
    <div class="k-value"><?= money($earned) ?></div></div>
  <div class="kpi"><div class="top"><div class="ic-box amber"><?= icon('clock',20) ?></div><div class="k-label">Still To Collect</div></div>
    <div class="k-value"><?= money($pending) ?></div></div>
  <div class="kpi"><div class="top"><div class="ic-box blue"><?= icon('calendar',20) ?></div><div class="k-label">This Month</div></div>
    <div class="k-value"><?= money($thisMonth) ?></div></div>
  <div class="kpi"><div class="top"><div class="ic-box purple"><?= icon('bookings',20) ?></div><div class="k-label">Deals</div></div>
    <div class="k-value"><?= $deals ?></div></div>
</div>

<div class="card pad0">
  <div class="card-head" style="padding:18px 18px 12px;margin:0"><h3>Brokerage by Deal</h3>
    <span class="muted small">pending shown first</span></div>
  <div class="table-wrap">
    <table class="data">
      <thead><tr><th>Flat</th><th>Tenant</th><th>Agreement From</th><th>Amount</th><th>From</th><th>Received On</th><th class="no-print"></th></tr></thead>
      <tbody>
      <?php foreach ($rowsList as $r): ?>
        <tr>
          <td class="strong"><?= e($r['building_name'] ?: '—') ?>
            <?php if ($r['flat_no']): ?><div class="muted tiny"><?= e($r['flat_no']) ?></div><?php endif; ?></td>
          <td><?= e($r['tenant_name'] ?: '—') ?></td>
          <td><?= $r['start_date'] ? e(fdate($r['start_date'],'d M Y')) : '—' ?></td>
          <td class="strong"><?= money_full($r['brokerage_amount']) ?></td>
          <td><?= e(ucfirst($r['brokerage_from'])) ?></td>
          <td><?php if ($r['brokerage_received']): ?>
                <span class="badge green"><?= $r['brokerage_date'] ? e(fdate($r['brokerage_date'],'d M Y')) : 'received' ?></span>
              <?php else: ?><span class="badge amber">pending</span><?php endif; ?></td>
          <td class="no-print">
            <form method="post" style="display:inline"><?= csrf_field() ?>
              <input type="hidden" name="mark_id" value="<?= $r['id'] ?>">
              <?php if ($r['brokerage_received']): ?>
                <button class="link" type="submit" style="color:var(--text-muted)">Mark pending</button>
              <?php else: ?>
                <button class="link" name="mark_received" value="1" type="submit" style="color:var(--green)">Mark received</button>
              <?php endif; ?>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$rowsList): ?>
        <tr><td colspan="7" class="center muted" style="padding:32px">
          No brokerage recorded yet. Add it on an agreement.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

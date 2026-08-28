<?php
/**
 * Rent → Overview.
 *
 * The one number a channel partner acts on is which agreements run out soon:
 * an 11-month agreement ending is repeat brokerage, and it is easy to miss.
 * That panel comes first, before anything else on this tab.
 */

if ($preRender) {
    // agreements ending within 60 days — the renewal radar
    $expiring = rows("SELECT a.*, f.building_name, f.flat_no, f.location,
                        o.name AS owner_name, o.mobile AS owner_mobile,
                        DATEDIFF(a.end_date, CURDATE()) AS days_left
                      FROM rent_agreements a
                      LEFT JOIN rent_flats f  ON f.id = a.flat_id
                      LEFT JOIN rent_owners o ON o.id = a.owner_id
                      WHERE a.status='active' AND a.end_date IS NOT NULL
                        AND a.end_date <= DATE_ADD(CURDATE(), INTERVAL 60 DAY)
                      ORDER BY a.end_date ASC");

    $rentRoll = (int) scalar("SELECT COALESCE(SUM(rent),0) FROM rent_agreements WHERE status='active'");
    $pendingBrokerage = (int) scalar("SELECT COALESCE(SUM(brokerage_amount),0)
                                      FROM rent_agreements WHERE brokerage_received=0 AND brokerage_amount>0");

    $byLoc = rows("SELECT location AS loc, COUNT(*) AS c FROM rent_flats
                   WHERE status='available' AND location <> '' GROUP BY location ORDER BY c DESC");
    $locMax = 0; foreach ($byLoc as $r) $locMax = max($locMax, (int)$r['c']);

    $recentEnq = rows("SELECT * FROM rent_seekers WHERE status IN ('searching','shown')
                       ORDER BY created_at DESC LIMIT 6");
    $upcomingVisits = rows("SELECT v.*, f.building_name, f.flat_no, s.name AS seeker_name
                            FROM rent_visits v
                            LEFT JOIN rent_flats f   ON f.id=v.flat_id
                            LEFT JOIN rent_seekers s ON s.id=v.seeker_id
                            WHERE v.status='scheduled' ORDER BY v.visit_date ASC LIMIT 6");
    return;
}
?>
<div class="kpi-row" style="grid-template-columns:repeat(5,1fr);margin-bottom:18px">
  <div class="kpi"><div class="top"><div class="ic-box green"><?= icon('key',20) ?></div><div class="k-label">Available Flats</div></div>
    <div class="k-value"><?= $nAvailable ?></div><div class="k-foot muted">of <?= $nFlats ?> on file</div></div>
  <div class="kpi"><div class="top"><div class="ic-box pink"><?= icon('leads',20) ?></div><div class="k-label">Looking</div></div>
    <div class="k-value"><?= $nSearching ?></div><div class="k-foot muted">active enquiries</div></div>
  <div class="kpi"><div class="top"><div class="ic-box blue"><?= icon('file',20) ?></div><div class="k-label">Running Agreements</div></div>
    <div class="k-value"><?= $nAgr ?></div><div class="k-foot muted"><?= count($expiring) ?> ending soon</div></div>
  <div class="kpi"><div class="top"><div class="ic-box gold"><?= icon('money',20) ?></div><div class="k-label">Rent Roll</div></div>
    <div class="k-value"><?= money($rentRoll) ?></div><div class="k-foot muted">per month, under management</div></div>
  <div class="kpi"><div class="top"><div class="ic-box amber"><?= icon('clock',20) ?></div><div class="k-label">Brokerage Due</div></div>
    <div class="k-value"><?= money($pendingBrokerage) ?></div><div class="k-foot muted">still to collect</div></div>
</div>

<!-- Renewal radar first: this is the money that gets forgotten -->
<div class="card" style="margin-bottom:18px;<?= $expiring ? 'border-color:var(--amber)' : '' ?>">
  <div class="card-head">
    <h3>Ending in the next 60 days</h3>
    <a class="link" href="<?= url('rent',['tab'=>'agreements']) ?>">All agreements</a>
  </div>
  <?php if (!$expiring): ?>
    <div class="muted small">Nothing runs out in the next 60 days.</div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="data">
        <thead><tr><th>Ends</th><th>Days</th><th>Flat</th><th>Tenant</th><th>Owner</th><th>Rent</th><th class="no-print"></th></tr></thead>
        <tbody>
        <?php foreach ($expiring as $a): $d = (int)$a['days_left']; ?>
          <tr>
            <td class="strong"><?= e(fdate($a['end_date'],'d M Y')) ?></td>
            <td><span class="badge <?= $d <= 30 ? 'red' : 'amber' ?>"><?= $d < 0 ? 'overdue' : $d.' days' ?></span></td>
            <td><?= e($a['building_name'] ?: '—') ?><?php if ($a['flat_no']): ?> <span class="muted tiny"><?= e($a['flat_no']) ?></span><?php endif; ?></td>
            <td><?= e($a['tenant_name'] ?: '—') ?>
              <?php if ($a['tenant_mobile']): ?><div class="muted tiny"><a class="link" href="tel:<?= e($a['tenant_mobile']) ?>"><?= e($a['tenant_mobile']) ?></a></div><?php endif; ?></td>
            <td><?= e($a['owner_name'] ?: '—') ?>
              <?php if ($a['owner_mobile']): ?><div class="muted tiny"><a class="link" href="tel:<?= e($a['owner_mobile']) ?>"><?= e($a['owner_mobile']) ?></a></div><?php endif; ?></td>
            <td class="strong"><?= (int)$a['rent'] ? money_full($a['rent']) : '—' ?></td>
            <td class="no-print"><a class="link" href="<?= url('rent',['tab'=>'agreements']) ?>">Open</a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<div class="grid" style="grid-template-columns:1fr 1fr 1fr">

  <div class="card">
    <div class="card-head"><h3>Available by Location</h3>
      <a class="link" href="<?= url('rent',['tab'=>'flats']) ?>">All flats</a></div>
    <?php if (!$byLoc): ?><div class="muted small">Nothing available right now.</div><?php endif; ?>
    <?php foreach ($byLoc as $r): $pct = $locMax ? round($r['c']/$locMax*100) : 0; ?>
      <div style="margin-bottom:12px">
        <div style="display:flex;justify-content:space-between;margin-bottom:5px">
          <a class="link" style="font-size:13px;color:var(--text-2)"
             href="<?= url('rent',['tab'=>'flats','loc'=>$r['loc']]) ?>"><?= e($r['loc']) ?></a>
          <span style="font-weight:700;font-size:13px"><?= (int)$r['c'] ?></span>
        </div>
        <div class="bar-track"><div class="bar-fill" style="width:<?= $pct ?>%"></div></div>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="card">
    <div class="card-head"><h3>Latest Enquiries</h3>
      <a class="link" href="<?= url('rent',['tab'=>'enquiries']) ?>">View all</a></div>
    <?php foreach ($recentEnq as $s):
      $bmax = (int)$s['budget_max']; ?>
      <div class="feed-item">
        <div class="feed-ic ic-box pink"><?= icon('user',16) ?></div>
        <div style="flex:1">
          <div style="font-size:13px;font-weight:600"><?= e($s['name']) ?></div>
          <div class="muted tiny">
            <?= e($s['config'] ?: 'Any BHK') ?> · <?= e($s['preferred_location'] ?: 'Any location') ?>
            <?= $bmax ? ' · up to '.money_full($bmax) : '' ?>
          </div>
        </div>
        <a class="btn ghost sm" href="<?= url('rent',['tab'=>'matcher','seeker'=>$s['id']]) ?>">Match</a>
      </div>
    <?php endforeach; ?>
    <?php if (!$recentEnq): ?><div class="muted small">No active enquiries.</div><?php endif; ?>
  </div>

  <div class="card">
    <div class="card-head"><h3>Upcoming Visits</h3>
      <a class="link" href="<?= url('rent',['tab'=>'visits']) ?>">View all</a></div>
    <?php foreach ($upcomingVisits as $v): ?>
      <div class="feed-item">
        <div class="feed-ic ic-box blue"><?= icon('visits',16) ?></div>
        <div style="flex:1">
          <div style="font-size:13px;font-weight:600"><?= e($v['seeker_name'] ?: 'Someone') ?></div>
          <div class="muted tiny"><?= e($v['building_name'] ?: '—') ?><?= $v['flat_no'] ? ' · '.e($v['flat_no']) : '' ?></div>
          <div class="muted tiny"><?= $v['visit_date'] ? e(fdate($v['visit_date'],'d M Y')).' · '.date('g:i A', strtotime($v['visit_date'])) : 'no date set' ?></div>
        </div>
      </div>
    <?php endforeach; ?>
    <?php if (!$upcomingVisits): ?><div class="muted small">No visits scheduled.</div><?php endif; ?>
  </div>
</div>

<?php
/** RE360 — Project detail with tabs (the sales card view) */
require_once __DIR__ . '/../includes/icons.php';
require_once __DIR__ . '/../includes/crud.php';
$err = '';
$page = 'projects'; $pageTitle = 'Project Details';

$id  = (int)($_GET['id'] ?? 0);
$tab = preg_replace('/[^a-z_]/','', $_GET['tab'] ?? 'overview');
$p = row("SELECT p.*, b.name AS builder_name, b.id AS bid,
            ROUND((b.score_construction+b.score_delivery+b.score_location+b.score_pricing+b.score_reputation+b.score_documentation)/6,1) AS builder_score
          FROM projects p JOIN builders b ON b.id=p.builder_id WHERE p.id=?", [$id]);
if (!$p) { require __DIR__ . '/../includes/header.php'; echo '<div class="card empty">Project not found.</div>'; require __DIR__ . '/../includes/footer.php'; return; }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_project') {
    $res = delete_project($id);
    if ($res['ok']) { header('Location: ' . url('projects')); exit; }
    $err = $res['error'];
}
$del = project_delete_summary($id);

$configs = rows("SELECT * FROM project_configurations WHERE project_id=? ORDER BY carpet_area", [$id]);
$towers  = rows("SELECT * FROM towers WHERE project_id=? ORDER BY name", [$id]);
$stats   = ['available'=>0,'hold'=>0,'token'=>0,'booked'=>0,'sold'=>0];
foreach (rows("SELECT status, COUNT(*) c FROM inventory WHERE project_id=? GROUP BY status",[$id]) as $r) {
    if (isset($stats[$r['status']])) $stats[$r['status']] = (int)$r['c'];
    elseif (in_array($r['status'],['registered','agreement'])) $stats['sold'] += (int)$r['c'];
}
$amen    = rows("SELECT a.* FROM amenities a JOIN project_amenities pa ON pa.amenity_id=a.id WHERE pa.project_id=? ORDER BY a.category, a.name", [$id]);
$plans   = rows("SELECT * FROM payment_plans WHERE project_id=?", [$id]);
$offers  = rows("SELECT * FROM offers WHERE project_id=? AND is_active=1", [$id]);
$legal   = rows("SELECT * FROM legal_docs WHERE project_id=?", [$id]);
$flats   = rows("SELECT * FROM inventory WHERE project_id=? ORDER BY tower, floor, flat_no", [$id]);
$pricing = rows("SELECT * FROM pricing WHERE project_id=?", [$id]);

$strengths  = array_filter(array_map('trim', explode("\n", $p['strengths'] ?? '')));
$weaknesses = array_filter(array_map('trim', explode("\n", $p['weaknesses'] ?? '')));

$tabs = ['overview'=>'Overview','inventory'=>'Inventory ('.$stats['available'].')','pricing'=>'Pricing','amenities'=>'Amenities','location'=>'Location','legal'=>'Legal / RERA','sales'=>'Sales Intelligence'];
require __DIR__ . '/../includes/header.php';
?>
<?php if ($err): ?><div class="login-err" style="margin-bottom:16px"><?= e($err) ?></div><?php endif; ?>
<div class="page-head">
  <div>
    <h2><?= e($p['name']) ?> <?php if ($p['is_featured']): ?><span class="badge violet">Featured</span><?php endif; ?></h2>
    <p>by <a class="link" href="<?= url('builder_view',['id'=>$p['bid']]) ?>"><?= e($p['builder_name']) ?></a> · <?= e($p['address']) ?></p>
  </div>
  <div style="display:flex;gap:8px">
    <a class="btn ghost" href="<?= url('projects') ?>">← Back</a>
    <a class="btn ghost" href="<?= url('inventory_form',['project'=>$p['id']]) ?>"><?= icon('plus',15) ?> Add Inventory</a>
    <a class="btn primary" href="<?= url('project_form',['id'=>$p['id']]) ?>">Edit</a>
    <?php if ($del['bookings']): ?>
      <button class="btn ghost" type="button" disabled style="opacity:.45;cursor:not-allowed"
              title="<?= (int)$del['bookings'] ?> booking(s) recorded against this project">Delete</button>
    <?php else: ?>
      <form method="post" style="display:inline" onsubmit="return confirm('Delete <?= e(addslashes($p['name'])) ?>?\n\nAlso deleted: <?= $del['flats'] ?> flat(s), <?= $del['configs'] ?> configuration(s), <?= $del['towers'] ?> tower(s), <?= $del['docs'] ?> document(s), plus pricing, offers and legal records.\n\nThis cannot be undone.')">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="delete_project">
        <button class="btn ghost" type="submit" style="color:var(--red)">Delete</button>
      </form>
    <?php endif; ?>
  </div>
</div>

<div class="pv-shell">

  <!-- Left rail: what the project *is* — stays put while the tabs change -->
  <aside class="pv-rail">
    <?php $hero = $p['hero_image'] ?? ''; ?>
    <?php if ($hero && is_file(BASE_PATH . '/' . $hero)): ?>
      <img class="photo-sq" src="<?= e($hero) ?>" alt="<?= e($p['name']) ?>">
    <?php else: ?>
      <div class="img-ph img-sq"><?= icon('building',44) ?></div>
    <?php endif; ?>

    <div class="card mt">
      <div class="chip-row">
        <span class="badge <?= $p['status']==='ready'?'green':($p['status']==='new_launch'?'violet':'blue') ?>"><?= e($GLOBALS['RE360_PROJECT_STATUS'][$p['status']] ?? $p['status']) ?></span>
        <?php if ($p['rera_verified']): ?><span class="badge teal"><?= icon('verified',12) ?> RERA Verified</span><?php endif; ?>
        <span class="badge grey">Builder score <?= $p['builder_score'] ?>/10</span>
      </div>
      <div class="small muted" style="margin-top:10px">MahaRERA: <strong style="color:var(--text)"><?= e($p['maharera_no'] ?: '—') ?></strong>
        <?= $p['rera_reg_date'] ? ' · Registered '.fdate($p['rera_reg_date']) : '' ?></div>
      <?php if ($p['description']): ?><div class="small" style="margin-top:8px;color:var(--text-2)"><?= e($p['description']) ?></div><?php endif; ?>
    </div>

    <!-- Configurations live here rather than inside Overview: they are the
         reference you keep glancing at while reading pricing or inventory. -->
    <div class="card pad0 mt">
      <div class="card-head" style="padding:16px 18px"><h3>Configurations</h3></div>
      <div class="table-wrap">
        <table class="data">
          <thead><tr><th>Config</th><th>Carpet</th><th>Price</th></tr></thead>
          <tbody>
          <?php foreach ($configs as $c): ?>
            <tr><td class="strong"><?= e($c['config']) ?></td>
                <td><?= num($c['carpet_area']) ?> sq.ft.</td>
                <td class="strong"><?= money($c['base_price']) ?></td></tr>
          <?php endforeach; ?>
          <?php if (!$configs): ?><tr><td colspan="3" class="center muted" style="padding:22px">No configurations added.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </aside>

  <!-- Right column: the numbers, then the tabbed detail -->
  <div class="pv-main">
    <div class="pv-stats">
      <?php foreach ([['Towers',$p['total_towers']],['Units',$p['total_units']],['Available',$stats['available']],['Hold',$stats['hold']],['Sold',$stats['sold']]] as $s): ?>
        <div class="card"><div class="pv-stat-n"><?= num($s[1]) ?></div><div class="muted tiny"><?= $s[0] ?></div></div>
      <?php endforeach; ?>
    </div>

    <div class="tabs mt">
      <?php foreach ($tabs as $k=>$lbl): ?>
        <a href="<?= url('project_view',['id'=>$id,'tab'=>$k]) ?>" class="<?= $tab===$k?'active':'' ?>"><?= e($lbl) ?></a>
      <?php endforeach; ?>
    </div>

<?php if ($tab === 'overview'): ?>
  <div class="card">
      <div class="card-head"><h3>Project Information</h3></div>
      <div class="grid" style="grid-template-columns:repeat(3,1fr);gap:16px">
        <?php foreach ([
          ['Type', ucfirst($p['type'])], ['Status', $GLOBALS['RE360_PROJECT_STATUS'][$p['status']] ?? $p['status']],
          ['Location', $p['node'].($p['sector']?', Sector '.$p['sector']:'')], ['Pincode', $p['pincode']],
          ['Land Parcel', $p['land_parcel']], ['Project Area', $p['project_area']],
          ['Launch Date', fdate($p['launch_date'])], ['Possession', $p['possession_label'] ?: fdate($p['proposed_completion'])],
          ['OC Status', $p['oc_status']], ['CC Status', $p['cc_status']],
          ['Price Range', money($p['price_min']).' – '.money($p['price_max'])], ['Total Units', $p['total_units']],
        ] as $f): ?>
          <div><div class="muted tiny"><?= e($f[0]) ?></div><div style="font-size:13px;font-weight:600;margin-top:2px"><?= e($f[1] ?: '—') ?></div></div>
        <?php endforeach; ?>
      </div>
  </div>
  <?php if ($towers): ?>
  <div class="card mt">
    <div class="card-head"><h3>Towers</h3></div>
    <div class="grid" style="grid-template-columns:repeat(5,1fr)">
      <?php foreach ($towers as $t): ?>
        <div class="card" style="background:var(--bg-card-2)">
          <div style="font-weight:800;font-size:15px">Tower <?= e($t['name']) ?></div>
          <div class="muted tiny" style="margin-top:6px"><?= (int)$t['floors'] ?> floors · <?= (int)$t['units_per_floor'] ?>/floor</div>
          <div class="muted tiny"><?= (int)$t['total_units'] ?> units · <?= (int)$t['lifts'] ?> lifts</div>
          <div class="badge blue" style="margin-top:8px"><?= e($t['possession']) ?></div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

<?php elseif ($tab === 'inventory'): ?>
  <div class="card pad0">
    <div class="card-head" style="padding:18px"><h3>Flat Inventory</h3>
      <a class="btn primary sm" href="<?= url('inventory_form',['project'=>$id]) ?>"><?= icon('plus',14) ?> Add Flat</a></div>
    <div class="table-wrap">
      <table class="data">
        <thead><tr><th>Flat No</th><th>Tower</th><th>Floor</th><th>Config</th><th>Carpet</th><th>Facing</th><th>Status</th><th>Price</th><th>₹/sq.ft.</th><th>Verified</th></tr></thead>
        <tbody>
        <?php foreach ($flats as $f): $fr = freshness($f['last_verified_at']); ?>
          <tr>
            <td class="strong"><?= e($f['flat_no']) ?></td><td><?= e($f['tower']) ?></td><td><?= (int)$f['floor'] ?></td>
            <td><?= e($f['config']) ?></td><td><?= num($f['carpet']) ?></td><td><?= e($f['facing']) ?></td>
            <td><span class="badge <?= status_color($f['status']) ?>"><?= e($f['status']) ?></span></td>
            <td class="strong"><?= money_full($f['price']) ?></td>
            <td><?= price_per_sqft($f['price'], $f['carpet']) ?></td>
            <td><span class="fresh-dot" style="background:<?= $fr['color'] ?>"></span><span class="tiny"><?= e($fr['label']) ?></span></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$flats): ?><tr><td colspan="10" class="center muted" style="padding:30px">No inventory added for this project yet.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

<?php elseif ($tab === 'pricing'): ?>
  <div class="grid" style="grid-template-columns:1.2fr 1fr">
    <div class="card">
      <div class="card-head"><h3>Price Breakdown</h3></div>
      <?php if ($pricing): foreach ($pricing as $pr): ?>
        <div style="margin-bottom:16px">
          <div class="badge blue" style="margin-bottom:8px"><?= e($pr['config']) ?></div>
          <div class="grid" style="grid-template-columns:repeat(3,1fr);gap:12px">
            <?php foreach ([['Base Price',$pr['base_price']],['Floor Rise',$pr['floor_rise']],['Premium',$pr['premium']],['Parking',$pr['parking_charge']],['Club',$pr['club_charge']],['Infrastructure',$pr['infra_charge']],['Development',$pr['dev_charge']],['Registration',$pr['registration']],['Other',$pr['other_charges']]] as $f): ?>
              <div><div class="muted tiny"><?= $f[0] ?></div><div style="font-weight:600;font-size:13px"><?= money($f[1]) ?></div></div>
            <?php endforeach; ?>
            <div><div class="muted tiny">GST</div><div style="font-weight:600;font-size:13px"><?= $pr['gst_pct'] ?>%</div></div>
            <div><div class="muted tiny">Stamp Duty</div><div style="font-weight:600;font-size:13px"><?= $pr['stamp_duty_pct'] ?>%</div></div>
          </div>
        </div>
      <?php endforeach; else: ?>
        <div class="muted small">No detailed pricing added. Base prices are shown in the Overview tab.</div>
      <?php endif; ?>
    </div>
    <div class="card">
      <div class="card-head"><h3>Payment Plans</h3></div>
      <?php foreach ($plans as $pl): $ms = json_decode($pl['milestones'] ?? '[]', true) ?: []; ?>
        <div style="margin-bottom:16px;padding-bottom:14px;border-bottom:1px solid var(--border-soft)">
          <div style="font-weight:700;font-size:13.5px"><?= e($pl['plan_name']) ?> <?php if ($pl['is_default']): ?><span class="badge teal">Default</span><?php endif; ?></div>
          <div class="muted tiny" style="margin-bottom:8px"><?= e($pl['description']) ?></div>
          <?php foreach ($ms as $m): ?>
            <div style="display:flex;justify-content:space-between;font-size:12.5px;padding:3px 0">
              <span class="muted"><?= e($m['label']) ?></span><span style="font-weight:700"><?= (int)$m['pct'] ?>%</span>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
      <?php if (!$plans): ?><div class="muted small">No payment plans added.</div><?php endif; ?>
    </div>
  </div>
  <?php if ($offers): ?>
  <div class="card mt">
    <div class="card-head"><h3>Active Offers</h3></div>
    <?php foreach ($offers as $o): ?>
      <div class="feed-item">
        <div class="feed-ic ic-box amber"><?= icon('tag',16) ?></div>
        <div style="flex:1"><div style="font-size:13px"><?= e($o['details']) ?></div>
          <div class="muted tiny">Valid till <?= fdate($o['valid_till']) ?></div></div>
        <span class="badge <?= $o['official_or_verbal']==='official'?'green':'amber' ?>"><?= ucfirst($o['official_or_verbal']) ?></span>
      </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

<?php elseif ($tab === 'amenities'): ?>
  <div class="card">
    <div class="card-head"><h3>Amenities</h3><span class="muted small"><?= count($amen) ?> amenities</span></div>
    <?php
      $byCat = [];
      foreach ($amen as $a) { $byCat[$a['category']][] = $a; }
      $catLabels = ['lifestyle'=>'Lifestyle','kids'=>'Kids','fitness'=>'Fitness','sports'=>'Sports','security'=>'Security','senior'=>'Senior Citizens','convenience'=>'Convenience'];
      foreach ($byCat as $cat=>$list): ?>
      <div style="margin-bottom:16px">
        <div class="form-section-title"><?= $catLabels[$cat] ?? ucfirst($cat) ?></div>
        <div class="chip-row"><?php foreach ($list as $a): ?><span class="chip"><?= icon('verified',14) ?> <?= e($a['name']) ?></span><?php endforeach; ?></div>
      </div>
    <?php endforeach; ?>
    <?php if (!$amen): ?><div class="muted small">No amenities added.</div><?php endif; ?>
  </div>

<?php elseif ($tab === 'location'): ?>
  <div class="grid" style="grid-template-columns:1fr 1fr">
    <div class="card">
      <div class="card-head"><h3>Location Details</h3></div>
      <div class="grid" style="grid-template-columns:1fr 1fr;gap:14px">
        <?php foreach ([['City',$p['city']],['Node',$p['node']],['Sector',$p['sector']],['Micro Market',$p['micro_market']],['Address',$p['address']],['Pincode',$p['pincode']],['Latitude',$p['latitude']],['Longitude',$p['longitude']]] as $f): ?>
          <div><div class="muted tiny"><?= e($f[0]) ?></div><div style="font-size:13px;font-weight:600;margin-top:2px"><?= e($f[1] ?: '—') ?></div></div>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="card">
      <div class="card-head"><h3>Connectivity</h3></div>
      <div class="muted small">Add nearby landmarks and 5/10/15-minute reach details from the Edit screen to show them here.</div>
    </div>
  </div>

<?php elseif ($tab === 'legal'): ?>
  <div class="card">
    <div class="card-head"><h3>Legal & RERA Documents</h3></div>
    <div class="table-wrap">
      <table class="data">
        <thead><tr><th>Document</th><th>Status</th><th>Note</th></tr></thead>
        <tbody>
          <tr><td class="strong">MahaRERA Registration</td>
            <td><span class="badge <?= $p['rera_verified']?'green':'amber' ?>"><?= $p['rera_verified']?'Verified':'Pending' ?></span></td>
            <td><?= e($p['maharera_no'] ?: '—') ?></td></tr>
          <?php foreach ($legal as $l): ?>
            <tr><td class="strong"><?= e(ucwords(str_replace('_',' ',$l['doc_type']))) ?></td>
              <td><span class="badge <?= $l['status']==='verified'?'green':($l['status']==='pending'?'amber':'grey') ?>"><?= e(str_replace('_',' ',$l['status'])) ?></span></td>
              <td><?= e($l['note'] ?: '—') ?></td></tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

<?php elseif ($tab === 'sales'): ?>
  <div class="grid" style="grid-template-columns:1fr 1fr">
    <div class="card">
      <div class="card-head"><h3>⭐ Why Sell This Project</h3></div>
      <?php foreach ($strengths as $s): ?>
        <div class="feed-item"><div class="feed-ic ic-box green"><?= icon('verified',16) ?></div>
          <div style="flex:1;font-size:13px;align-self:center"><?= e($s) ?></div></div>
      <?php endforeach; ?>
      <?php if (!$strengths): ?><div class="muted small">No strengths recorded yet.</div><?php endif; ?>
    </div>
    <div class="card">
      <div class="card-head"><h3>⚠️ Watch Out (Internal)</h3></div>
      <?php foreach ($weaknesses as $s): ?>
        <div class="feed-item"><div class="feed-ic ic-box amber"><?= icon('info',16) ?></div>
          <div style="flex:1;font-size:13px;align-self:center"><?= e($s) ?></div></div>
      <?php endforeach; ?>
      <?php if (!$weaknesses): ?><div class="muted small">No weaknesses recorded yet.</div><?php endif; ?>
    </div>
  </div>
  <div class="card mt">
    <div class="card-head"><h3>Best Client Profile</h3></div>
    <div class="chip-row">
      <?php foreach (array_filter(array_map('trim', explode(',', $p['best_for'] ?? ''))) as $bf): ?>
        <span class="chip"><?= icon('user',14) ?> <?= e($bf) ?></span>
      <?php endforeach; ?>
      <?php if ($p['budget_band']): ?><span class="chip"><?= icon('money',14) ?> <?= e($p['budget_band']) ?></span><?php endif; ?>
    </div>
  </div>
<?php endif; ?>

  </div><!-- /.pv-main -->
</div><!-- /.pv-shell -->

<?php require __DIR__ . '/../includes/footer.php'; ?>

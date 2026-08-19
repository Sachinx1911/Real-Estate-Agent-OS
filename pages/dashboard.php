<?php
/** RE360 — Dashboard (flagship). Reached via index.php?page=dashboard */
require_once __DIR__ . '/../includes/icons.php';
$page = 'dashboard';
$pageTitle = 'Dashboard';

/* ---------------- KPI numbers ---------------- */
$totalBuilders = (int) scalar("SELECT COUNT(*) FROM builders");
$totalProjects = (int) scalar("SELECT COUNT(*) FROM projects");
$availableUnits= (int) scalar("SELECT COUNT(*) FROM inventory WHERE status='available'");
$totalValue    = (int) scalar("SELECT COALESCE(SUM(price),0) FROM inventory WHERE status='available'");
$activeLeads   = (int) scalar("SELECT COUNT(*) FROM clients WHERE status NOT IN ('booked','lost')");
$bookingsMonth = (int) scalar("SELECT COUNT(*) FROM bookings WHERE MONTH(booking_date)=MONTH(CURDATE()) AND YEAR(booking_date)=YEAR(CURDATE())");
$newBuilders   = (int) scalar("SELECT COUNT(*) FROM builders WHERE MONTH(created_at)=MONTH(CURDATE()) AND YEAR(created_at)=YEAR(CURDATE())");
$newProjects   = (int) scalar("SELECT COUNT(*) FROM projects WHERE MONTH(created_at)=MONTH(CURDATE()) AND YEAR(created_at)=YEAR(CURDATE())");
$confirmedBk   = (int) scalar("SELECT COUNT(*) FROM bookings WHERE stage IN ('booked','agreement','registered') AND MONTH(booking_date)=MONTH(CURDATE())");

/* ---------------- Inventory by location ---------------- */
$locRows = rows("SELECT p.node AS loc, COUNT(*) AS c
                 FROM inventory i JOIN projects p ON p.id=i.project_id
                 WHERE i.status='available'
                 GROUP BY p.node ORDER BY c DESC");
$locMax = 0; foreach ($locRows as $r) { $locMax = max($locMax, (int)$r['c']); }

/* ---------------- Project status (by units) ---------------- */
$statusRows = rows("SELECT status, SUM(total_units) AS units, COUNT(*) AS projects FROM projects GROUP BY status");
$statusData = ['ready'=>0,'under_construction'=>0,'new_launch'=>0,'upcoming'=>0,'on_hold'=>0];
foreach ($statusRows as $r) { $statusData[$r['status']] = (int)$r['units']; }
$statusTotal = array_sum($statusData) ?: 1;
$statusMeta = [
    'ready'              => ['Ready Possession', '#22c55e'],
    'under_construction' => ['Under Construction', '#3b82f6'],
    'new_launch'         => ['New Launch', '#8b5cf6'],
    'upcoming'           => ['Upcoming', '#eab308'],
    'on_hold'            => ['On Hold', '#ef4444'],
];

/* ---------------- Recent updates ---------------- */
$updates = rows("SELECT * FROM activity_log ORDER BY created_at DESC LIMIT 5");

/* ---------------- Top performing projects ---------------- */
$topProjects = rows("SELECT p.id, p.name, p.hero_image,
                        COUNT(b.id) AS bookings, COALESCE(SUM(b.value),0) AS value
                     FROM projects p
                     LEFT JOIN bookings b ON b.project_id=p.id
                        AND MONTH(b.booking_date)=MONTH(CURDATE()) AND YEAR(b.booking_date)=YEAR(CURDATE())
                     GROUP BY p.id
                     ORDER BY bookings DESC, value DESC LIMIT 5");

/* ---------------- Featured project (spotlight) ---------------- */
$feat = row("SELECT p.*, b.name AS builder_name, b.id AS builder_id
             FROM projects p JOIN builders b ON b.id=p.builder_id
             WHERE p.is_featured=1 ORDER BY p.id LIMIT 1");
if (!$feat) $feat = row("SELECT p.*, b.name AS builder_name, b.id AS builder_id FROM projects p JOIN builders b ON b.id=p.builder_id LIMIT 1");
$featStats = ['available'=>0,'sold'=>0,'hold'=>0];
if ($feat) {
    foreach (rows("SELECT status, COUNT(*) c FROM inventory WHERE project_id=? GROUP BY status", [$feat['id']]) as $r) {
        if ($r['status']==='available') $featStats['available'] = (int)$r['c'];
        if ($r['status']==='sold' || $r['status']==='registered') $featStats['sold'] += (int)$r['c'];
        if ($r['status']==='hold') $featStats['hold'] = (int)$r['c'];
    }
    $featConfigs = scalar("SELECT GROUP_CONCAT(DISTINCT config ORDER BY config SEPARATOR ', ') FROM project_configurations WHERE project_id=?", [$feat['id']]);
}

/* ---------------- Live inventory snapshot (featured project) ---------------- */
$snapProject = $feat['id'] ?? 0;
$snap = rows("SELECT * FROM inventory WHERE project_id=? ORDER BY tower, floor LIMIT 8", [$snapProject]);
$snapTotal = (int) scalar("SELECT COUNT(*) FROM inventory WHERE project_id=?", [$snapProject]);

/* ---------------- Client requirement matcher ---------------- */
// aggregate project info for matching (inventory-aware: only flats of the wanted BHK)
$demoReq = ['locations'=>['Panvel','Kamothe'], 'bhk'=>'2 BHK', 'all_in_budget'=>10000000, 'min_carpet'=>650, 'possession_within_months'=>36];
$fitSql = match_fit_sql();
$matchProjects = rows("SELECT p.*,
        GROUP_CONCAT(DISTINCT c.config SEPARATOR ',') AS configs,
        MAX(c.carpet_area) AS max_carpet,
        $fitSql
    FROM projects p LEFT JOIN project_configurations c ON c.project_id=p.id
    WHERE p.status IN ('under_construction','ready','new_launch')
    GROUP BY p.id", [$demoReq['bhk'], $demoReq['bhk'], $demoReq['bhk']]);
$matches = [];
foreach ($matchProjects as $mp) {
    $m = match_score($demoReq, $mp);
    $matches[] = ['p'=>$mp, 'score'=>$m['score'], 'fit'=>$m['fit_count']];
}
usort($matches, fn($a,$b)=>$b['score']-$a['score']);
$topMatches = array_slice($matches, 0, 3);
$moreMatches = max(0, count($matches) - 3);

/* ---------------- Follow ups ---------------- */
$followups = rows("SELECT * FROM tasks WHERE status='open' ORDER BY due_at ASC LIMIT 4");

/* ---------------- Mini calendar events ---------------- */
$calEvents = [];
foreach (rows("SELECT DISTINCT DAY(due_at) d FROM tasks WHERE status='open' AND MONTH(due_at)=MONTH(CURDATE()) AND YEAR(due_at)=YEAR(CURDATE())") as $r) {
    if ($r['d']) $calEvents[(int)$r['d']] = true;
}

require __DIR__ . '/../includes/header.php';
?>

<!-- ============ KPI ROW ============ -->
<div class="kpi-row">
  <div class="kpi">
    <div class="top"><div class="ic-box purple"><?= icon('builders',20) ?></div>
      <div class="k-label">Total Builders</div></div>
    <div class="k-value"><?= number_format($totalBuilders) ?></div>
    <div class="k-foot"><?= growth_pill($newBuilders, ' new this month') ?></div>
  </div>
  <div class="kpi">
    <div class="top"><div class="ic-box blue"><?= icon('projects',20) ?></div>
      <div class="k-label">Total Projects</div></div>
    <div class="k-value"><?= number_format($totalProjects) ?></div>
    <div class="k-foot"><?= growth_pill($newProjects, ' new this month') ?></div>
  </div>
  <div class="kpi">
    <div class="top"><div class="ic-box green"><?= icon('inventory',20) ?></div>
      <div class="k-label">Available Units</div></div>
    <div class="k-value"><?= number_format($availableUnits) ?></div>
    <div class="k-foot muted">across all projects</div>
  </div>
  <div class="kpi">
    <div class="top"><div class="ic-box gold"><?= icon('money',20) ?></div>
      <div class="k-label">Total Value</div></div>
    <div class="k-value"><?= money_cr($totalValue) ?></div>
    <div class="k-foot muted">available inventory</div>
  </div>
  <div class="kpi">
    <div class="top"><div class="ic-box pink"><?= icon('leads',20) ?></div>
      <div class="k-label">Active Leads</div></div>
    <div class="k-value"><?= number_format($activeLeads) ?></div>
    <div class="k-foot muted">in pipeline</div>
  </div>
  <div class="kpi">
    <div class="top"><div class="ic-box teal"><?= icon('bookings',20) ?></div>
      <div class="k-label">Bookings (This Month)</div></div>
    <div class="k-value"><?= number_format($bookingsMonth) ?></div>
    <div class="k-foot"><?= growth_pill($confirmedBk, ' confirmed') ?></div>
  </div>
</div>

<!-- ============ MIDDLE 4 PANELS ============ -->
<div class="grid mt" style="grid-template-columns: 1.15fr 1fr 1fr 1.1fr">
  <!-- Inventory by Location -->
  <div class="card">
    <div class="card-head"><h3>Inventory by Location</h3></div>
    <?php if (!$locRows): ?><div class="muted small">No inventory yet.</div><?php endif; ?>
    <?php foreach ($locRows as $r): $pct = $locMax ? round($r['c']/$locMax*100) : 0; ?>
      <div style="margin-bottom:13px">
        <div style="display:flex;justify-content:space-between;margin-bottom:5px">
          <span style="font-size:13px;color:var(--text-2)"><?= e($r['loc'] ?: 'Others') ?></span>
          <span style="font-weight:700;font-size:13px"><?= number_format($r['c']) ?></span>
        </div>
        <div class="bar-track"><div class="bar-fill" style="width:<?= $pct ?>%"></div></div>
      </div>
    <?php endforeach; ?>
    <a class="card-head link" href="<?= url('reports') ?>" style="margin-top:6px;display:inline-block">View full location report →</a>
  </div>

  <!-- Project Status donut -->
  <div class="card">
    <div class="card-head"><h3>Project Status Overview</h3></div>
    <div style="display:flex;align-items:center;gap:8px">
      <div style="width:130px;height:130px"><canvas id="statusChart"></canvas></div>
      <div style="flex:1">
        <?php foreach ($statusMeta as $k=>$mm): $val=$statusData[$k]; $pct=round($val/$statusTotal*100); ?>
          <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;font-size:12.5px">
            <span style="width:9px;height:9px;border-radius:50%;background:<?= $mm[1] ?>"></span>
            <span style="flex:1;color:var(--text-2)"><?= $mm[0] ?></span>
            <span style="font-weight:700"><?= number_format($val) ?></span>
            <span class="muted tiny">(<?= $pct ?>%)</span>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <a class="link" href="<?= url('projects') ?>" style="margin-top:8px;display:inline-block;font-size:12.5px">View all projects →</a>
  </div>

  <!-- Recent Updates -->
  <div class="card">
    <div class="card-head"><h3>Recent Updates</h3><a class="link" href="<?= url('reports') ?>">View All</a></div>
    <?php foreach ($updates as $up): ?>
      <div class="feed-item">
        <div class="feed-ic ic-box <?= ['inventory'=>'green','tag'=>'purple','money'=>'gold','verified'=>'teal','building'=>'blue'][$up['icon']] ?? 'purple' ?>"><?= icon($up['icon'] ?: 'info',16) ?></div>
        <div style="flex:1">
          <div style="font-size:12.5px;color:var(--text)"><?= e($up['message']) ?></div>
          <div class="muted tiny" style="margin-top:2px"><?= e(time_ago($up['created_at'])) ?></div>
        </div>
      </div>
    <?php endforeach; ?>
    <?php if (!$updates): ?><div class="muted small">No recent activity.</div><?php endif; ?>
  </div>

  <!-- Top Performing -->
  <div class="card">
    <div class="card-head"><h3>Top Performing Projects</h3><span class="badge grey">This Month</span></div>
    <?php $rk=1; foreach ($topProjects as $tp): ?>
      <div class="rank-item">
        <span class="rank-no"><?= $rk++ ?></span>
        <div class="rank-thumb"></div>
        <div style="flex:1">
          <div style="font-weight:600;font-size:13px"><?= e($tp['name']) ?></div>
          <div class="muted tiny"><?= (int)$tp['bookings'] ?> Bookings</div>
        </div>
        <div style="text-align:right"><div style="font-weight:700;font-size:13px"><?= money($tp['value']) ?></div></div>
      </div>
    <?php endforeach; ?>
    <a class="link" href="<?= url('reports') ?>" style="margin-top:10px;display:inline-block;font-size:12.5px">View full performance →</a>
  </div>
</div>

<!-- ============ SPOTLIGHT + INVENTORY SNAPSHOT ============ -->
<div class="grid mt" style="grid-template-columns: 1fr 1.25fr">
  <!-- Project Spotlight -->
  <?php if ($feat): ?>
  <div class="card">
    <div class="card-head">
      <h3>Project Spotlight <span class="badge violet" style="margin-left:6px">Featured</span></h3>
      <a class="btn ghost sm" href="<?= url('project_view',['id'=>$feat['id']]) ?>">View Details</a>
    </div>
    <div style="height:170px;border-radius:12px;background:linear-gradient(135deg,#1a2544,#0e1428);display:grid;place-items:center;color:var(--text-muted);margin-bottom:14px">
      <?= icon('building',46) ?>
    </div>
    <div style="display:flex;justify-content:space-between;align-items:flex-start">
      <div>
        <div style="font-size:18px;font-weight:800"><?= e($feat['name']) ?></div>
        <div class="muted small">by <a class="link" href="<?= url('builder_view',['id'=>$feat['builder_id']]) ?>"><?= e($feat['builder_name']) ?></a> · <?= e($feat['address']) ?></div>
        <div class="small" style="margin-top:6px">MahaRERA: <?= e($feat['maharera_no']) ?>
          <?php if ($feat['rera_verified']): ?><span class="badge teal" style="margin-left:5px"><?= icon('verified',12) ?> Verified</span><?php endif; ?></div>
      </div>
      <span class="badge <?= $feat['status']==='ready'?'green':'blue' ?>"><?= $GLOBALS['RE360_PROJECT_STATUS'][$feat['status']] ?? $feat['status'] ?></span>
    </div>

    <div class="grid" style="grid-template-columns:repeat(4,1fr);gap:10px;margin-top:14px">
      <?php
        $facts = [
          ['Type', ucfirst($feat['type'])],
          ['Config', $featConfigs ?: '—'],
          ['Price Range', money($feat['price_min']).' – '.money($feat['price_max'])],
          ['Possession', $feat['possession_label'] ?: fdate($feat['proposed_completion'],'M Y')],
        ];
        foreach ($facts as $f): ?>
        <div class="card" style="padding:10px;background:var(--bg-card-2)">
          <div class="muted tiny"><?= e($f[0]) ?></div>
          <div style="font-weight:700;font-size:12.5px;margin-top:3px"><?= e($f[1]) ?></div>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="grid" style="grid-template-columns:repeat(5,1fr);gap:8px;margin-top:12px;text-align:center">
      <?php foreach ([['Towers',$feat['total_towers']],['Units',$feat['total_units']],['Available',$featStats['available']],['Sold',$featStats['sold']],['On Hold',$featStats['hold']]] as $s): ?>
        <div><div style="font-size:17px;font-weight:800"><?= num($s[1]) ?></div><div class="muted tiny"><?= $s[0] ?></div></div>
      <?php endforeach; ?>
    </div>

    <div class="chip-row" style="margin-top:14px">
      <?php foreach (array_slice(array_filter(array_map('trim', explode("\n", $feat['strengths'] ?? ''))),0,3) as $ss): ?>
        <span class="chip"><?= icon('verified',14) ?> <?= e($ss) ?></span>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- Live Inventory Snapshot -->
  <div class="card pad0">
    <div class="card-head" style="padding:18px 18px 12px;margin:0">
      <h3>Live Inventory Snapshot</h3>
      <a class="btn ghost sm" href="<?= url('inventory') ?>"><?= icon('export',14) ?> Export</a>
    </div>
    <?php
      $snapTowers  = rows("SELECT DISTINCT tower FROM inventory WHERE project_id=? AND tower<>'' ORDER BY tower", [$snapProject]);
      $snapConfigs = rows("SELECT DISTINCT config FROM inventory WHERE project_id=? AND config<>'' ORDER BY config", [$snapProject]);
    ?>
    <div style="display:flex;gap:8px;padding:0 18px 12px;flex-wrap:wrap">
      <select class="select" id="fTower" onchange="loadSnapshot()"><option value="">Tower: All</option>
        <?php foreach ($snapTowers as $t): ?><option value="<?= e($t['tower']) ?>"><?= e($t['tower']) ?></option><?php endforeach; ?></select>
      <select class="select" id="fBhk" onchange="loadSnapshot()"><option value="">BHK: All</option>
        <?php foreach ($snapConfigs as $c): ?><option value="<?= e($c['config']) ?>"><?= e($c['config']) ?></option><?php endforeach; ?></select>
      <select class="select" id="fStatus" onchange="loadSnapshot()"><option value="">Status: All</option>
        <?php foreach ($GLOBALS['RE360_INV_STATUS'] as $s): ?><option value="<?= $s ?>"><?= ucfirst($s) ?></option><?php endforeach; ?></select>
      <div class="search" style="flex:1;min-width:150px;background:var(--bg-card-2);padding:7px 12px">
        <?= icon('search',15) ?><input type="text" id="fSearch" placeholder="Search flat no. / tower..." oninput="debounceSnapshot()">
      </div>
    </div>
    <div class="table-wrap">
      <table class="data">
        <thead><tr>
          <th>Flat No</th><th>Tower</th><th>Floor</th><th>BHK</th><th>Carpet</th><th>Facing</th><th>Status</th><th>Price</th><th>Last Updated</th>
        </tr></thead>
        <tbody id="snapBody">
        <?php foreach ($snap as $fl): $fr = freshness($fl['last_verified_at']); ?>
          <tr>
            <td class="strong"><?= e($fl['flat_no']) ?></td>
            <td><?= e($fl['tower']) ?></td>
            <td><?= (int)$fl['floor'] ?></td>
            <td><?= e($fl['config']) ?></td>
            <td><?= num($fl['carpet']) ?></td>
            <td><?= e($fl['facing']) ?></td>
            <td><span class="badge <?= status_color($fl['status']) ?>"><?= e($fl['status']) ?></span></td>
            <td class="strong"><?= money_full($fl['price']) ?></td>
            <td><span class="fresh-dot" style="background:<?= $fr['color'] ?>"></span><span class="tiny"><?= e(fdate($fl['last_verified_at'],'d M') ) ?> <?= $fl['last_verified_at']?date('g:i A',strtotime($fl['last_verified_at'])):'' ?></span></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$snap): ?><tr><td colspan="9" class="center muted" style="padding:30px">No inventory for this project yet.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
    <div class="pager">
      <span class="muted small" id="snapCount">Showing <?= count($snap) ?> of <?= $snapTotal ?> entries</span>
      <a class="link" href="<?= url('inventory',['project'=>$snapProject]) ?>">View all inventory →</a>
    </div>
  </div>
</div>

<!-- ============ MATCHER + FOLLOWUPS + CALENDAR ============ -->
<div class="grid mt" style="grid-template-columns: 1.4fr 1fr 0.9fr">
  <!-- Client Requirement Matcher -->
  <div class="card">
    <div class="card-head"><h3>Client Requirement Matcher</h3><a class="btn ghost sm" href="<?= url('matcher') ?>"><?= icon('search',14) ?> View All Matches</a></div>
    <div class="chip-row" style="margin-bottom:14px">
      <span class="chip">Budget: <?= money($demoReq['all_in_budget']) ?></span>
      <span class="chip">BHK: <?= e($demoReq['bhk']) ?></span>
      <span class="chip"><?= icon('location',14) ?> <?= e(implode(', ', $demoReq['locations'])) ?></span>
    </div>
    <div class="grid" style="grid-template-columns:repeat(4,1fr);gap:12px">
      <?php foreach ($topMatches as $tm): $p=$tm['p']; $cls=$tm['score']>=90?'':($tm['score']>=80?'mid':'low'); ?>
        <a class="card" href="<?= url('project_view',['id'=>$p['id']]) ?>" style="background:var(--bg-card-2);padding:13px">
          <div style="display:flex;justify-content:space-between;align-items:flex-start">
            <div style="font-weight:700;font-size:13px;line-height:1.3"><?= e($p['name']) ?></div>
            <span class="match-badge <?= $cls ?>"><?= $tm['score'] ?>%</span>
          </div>
          <div class="muted tiny" style="margin-top:3px"><?= e($p['node']) ?></div>
          <div style="margin-top:10px;font-weight:700;font-size:12.5px"><?= money($p['price_min']) ?> – <?= money($p['price_max']) ?></div>
          <div class="muted tiny" style="margin-top:2px"><?= e($p['possession_label']) ?></div>
        </a>
      <?php endforeach; ?>
      <a class="card" href="<?= url('matcher') ?>" style="background:var(--bg-card-2);padding:13px;display:grid;place-items:center;text-align:center">
        <div><div style="font-weight:800;font-size:15px;color:var(--primary)">+<?= $moreMatches ?></div>
        <div class="muted tiny">More Matches</div></div>
      </a>
    </div>
  </div>

  <!-- Activity & Follow Ups -->
  <div class="card">
    <div class="card-head"><h3>Activity & Follow Ups</h3><a class="link" href="<?= url('tasks') ?>">View All</a></div>
    <?php foreach ($followups as $t):
      $pc = ['high'=>'red','medium'=>'amber','low'=>'grey'][$t['priority']] ?? 'grey';
      $ic = ['followup'=>'phone','document'=>'file','callback'=>'phone','visit'=>'location'][$t['type']] ?? 'clock';
    ?>
      <div class="task-item">
        <div class="feed-ic ic-box <?= $pc==='red'?'pink':($pc==='amber'?'amber':'blue') ?>"><?= icon($ic,16) ?></div>
        <div style="flex:1">
          <div style="font-size:13px;font-weight:600"><?= e($t['title']) ?></div>
          <div class="muted tiny"><?= e($t['subtitle']) ?></div>
          <div class="muted tiny" style="margin-top:3px"><?= e(fdate($t['due_at'],'d M')) ?>, <?= $t['due_at']?date('g:i A',strtotime($t['due_at'])):'' ?></div>
        </div>
        <span class="badge <?= $pc ?>"><?= ucfirst($t['priority']) ?></span>
      </div>
    <?php endforeach; ?>
    <?php if (!$followups): ?><div class="muted small">No pending follow-ups.</div><?php endif; ?>
  </div>

  <!-- Mini calendar -->
  <div class="card">
    <div class="card-head"><h3>Calendar</h3><a class="link" href="<?= url('calendar') ?>">View Calendar</a></div>
    <?php
      $mFirst = strtotime(date('Y-m-01'));
      $daysIn = (int)date('t');
      $startDow = (int)date('w', $mFirst); // 0=Sun
      $today = (int)date('j');
    ?>
    <div class="cal-head"><span><?= date('F Y') ?></span></div>
    <div class="cal-grid">
      <?php foreach (['S','M','T','W','T','F','S'] as $d): ?><div class="dow"><?= $d ?></div><?php endforeach; ?>
      <?php for ($i=0;$i<$startDow;$i++): ?><div class="day out"></div><?php endfor; ?>
      <?php for ($d=1;$d<=$daysIn;$d++):
        $cls = 'day'; if ($d===$today) $cls.=' today'; if (isset($calEvents[$d])) $cls.=' has-event'; ?>
        <div class="<?= $cls ?>"><?= $d ?></div>
      <?php endfor; ?>
    </div>
  </div>
</div>

<?php
$statusLabels = json_encode(array_map(fn($k)=>$statusMeta[$k][0], array_keys($statusData)));
$statusValues = json_encode(array_values($statusData));
$statusColors = json_encode(array_map(fn($k)=>$statusMeta[$k][1], array_keys($statusData)));
$snapProjectJs = (int)$snapProject;
$inlineScript = <<<JS
(function(){
  var el = document.getElementById('statusChart');
  if (el && window.Chart) {
    new Chart(el, {
      type: 'doughnut',
      data: { labels: $statusLabels, datasets: [{ data: $statusValues, backgroundColor: $statusColors, borderWidth: 0 }] },
      options: { cutout: '68%', plugins: { legend: { display: false } }, responsive: true, maintainAspectRatio: false }
    });
  }
})();

var SNAP_PROJECT = $snapProjectJs, snapTimer;
function debounceSnapshot(){ clearTimeout(snapTimer); snapTimer = setTimeout(loadSnapshot, 250); }
function loadSnapshot(){
  var p = new URLSearchParams({
    project: SNAP_PROJECT,
    tower:  document.getElementById('fTower').value,
    config: document.getElementById('fBhk').value,
    status: document.getElementById('fStatus').value,
    q:      document.getElementById('fSearch').value
  });
  fetch('api/inventory_filter.php?' + p.toString())
    .then(function(r){ return r.json(); })
    .then(function(d){
      var body = document.getElementById('snapBody');
      if (!d.rows.length) {
        body.innerHTML = '<tr><td colspan="9" class="center muted" style="padding:30px">No flats match these filters.</td></tr>';
      } else {
        body.innerHTML = d.rows.map(function(f){
          return '<tr><td class="strong">' + f.flat_no + '</td><td>' + f.tower + '</td><td>' + f.floor +
            '</td><td>' + f.config + '</td><td>' + f.carpet + '</td><td>' + f.facing +
            '</td><td><span class="badge ' + f.statusColor + '">' + f.status + '</span></td>' +
            '<td class="strong">' + f.price + '</td>' +
            '<td><span class="fresh-dot" style="background:' + f.freshColor + '"></span><span class="tiny">' + f.verified + '</span></td></tr>';
        }).join('');
      }
      document.getElementById('snapCount').textContent = 'Showing ' + d.shown + ' of ' + d.total + ' entries';
    })
    .catch(function(){});
}
JS;

require __DIR__ . '/../includes/footer.php';

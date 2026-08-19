<?php
/** RE360 — Calendar (site visits + follow-ups + possession dates) */
require_once __DIR__ . '/../includes/icons.php';
$page = 'calendar'; $pageTitle = 'Calendar';

$m = (int)($_GET['m'] ?? date('n'));
$y = (int)($_GET['y'] ?? date('Y'));
if ($m < 1) { $m = 12; $y--; } if ($m > 12) { $m = 1; $y++; }
$first  = mktime(0,0,0,$m,1,$y);
$daysIn = (int)date('t', $first);
$startDow = (int)date('w', $first);
$isCurrentMonth = ($m == (int)date('n') && $y == (int)date('Y'));
$todayD = (int)date('j');

$events = [];
foreach (rows("SELECT sv.*, c.name cname, p.name pname FROM site_visits sv JOIN clients c ON c.id=sv.client_id
               LEFT JOIN projects p ON p.id=sv.project_id
               WHERE MONTH(sv.visit_date)=? AND YEAR(sv.visit_date)=?", [$m,$y]) as $r) {
    $events[(int)date('j', strtotime($r['visit_date']))][] =
        ['type'=>'visit','color'=>'amber','label'=>$r['cname'].' — '.($r['pname'] ?: 'Site visit'),'time'=>date('g:i A', strtotime($r['visit_date']))];
}
foreach (rows("SELECT * FROM tasks WHERE MONTH(due_at)=? AND YEAR(due_at)=? AND status='open'", [$m,$y]) as $r) {
    $events[(int)date('j', strtotime($r['due_at']))][] =
        ['type'=>'task','color'=>($r['priority']==='high'?'red':'blue'),'label'=>$r['title'],'time'=>date('g:i A', strtotime($r['due_at']))];
}
foreach (rows("SELECT * FROM projects WHERE MONTH(proposed_completion)=? AND YEAR(proposed_completion)=?", [$m,$y]) as $r) {
    $events[(int)date('j', strtotime($r['proposed_completion']))][] =
        ['type'=>'possession','color'=>'teal','label'=>$r['name'].' — possession','time'=>''];
}
$prevM = $m-1; $prevY = $y; if ($prevM<1) { $prevM=12; $prevY--; }
$nextM = $m+1; $nextY = $y; if ($nextM>12) { $nextM=1; $nextY++; }
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
  <div><h2>Calendar</h2><p>Site visits, follow-ups and possession dates</p></div>
  <div style="display:flex;gap:8px;align-items:center">
    <a class="btn ghost sm" href="<?= url('calendar',['m'=>$prevM,'y'=>$prevY]) ?>"><?= icon('chevron-left',14) ?></a>
    <strong style="min-width:150px;text-align:center"><?= date('F Y', $first) ?></strong>
    <a class="btn ghost sm" href="<?= url('calendar',['m'=>$nextM,'y'=>$nextY]) ?>"><?= icon('chevron-right',14) ?></a>
    <a class="btn ghost sm" href="<?= url('calendar') ?>">Today</a>
  </div>
</div>

<div class="card">
  <div style="display:grid;grid-template-columns:repeat(7,1fr);gap:8px">
    <?php foreach (['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $d): ?>
      <div class="muted tiny center" style="padding:6px 0;font-weight:600;letter-spacing:.5px;text-transform:uppercase"><?= $d ?></div>
    <?php endforeach; ?>

    <?php for ($i=0;$i<$startDow;$i++): ?><div></div><?php endfor; ?>

    <?php for ($d=1;$d<=$daysIn;$d++):
      $isToday = $isCurrentMonth && $d===$todayD;
      $dayEvents = $events[$d] ?? []; ?>
      <div class="card" style="background:var(--bg-card-2);padding:9px;min-height:96px;<?= $isToday?'box-shadow:inset 0 0 0 1.5px var(--primary)':'' ?>">
        <div style="font-weight:<?= $isToday?'800':'600' ?>;font-size:12.5px;color:<?= $isToday?'var(--primary)':'var(--text-2)' ?>;margin-bottom:6px"><?= $d ?></div>
        <?php foreach (array_slice($dayEvents,0,3) as $ev): ?>
          <div class="badge <?= $ev['color'] ?>" style="display:block;margin-bottom:4px;font-size:10px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
            <?= e($ev['label']) ?>
          </div>
        <?php endforeach; ?>
        <?php if (count($dayEvents)>3): ?><div class="muted tiny">+<?= count($dayEvents)-3 ?> more</div><?php endif; ?>
      </div>
    <?php endfor; ?>
  </div>
</div>

<div class="card mt" style="padding:14px">
  <div style="display:flex;gap:18px;flex-wrap:wrap;align-items:center">
    <span class="muted small"><strong style="color:var(--text)">Legend:</strong></span>
    <span class="badge amber">Site Visit</span>
    <span class="badge red">High-priority task</span>
    <span class="badge blue">Follow-up</span>
    <span class="badge teal">Possession date</span>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>

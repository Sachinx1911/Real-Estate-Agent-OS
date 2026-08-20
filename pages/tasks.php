<?php
/** RE360 — Tasks & Follow Ups */
require_once __DIR__ . '/../includes/icons.php';
require_once __DIR__ . '/../includes/crud.php';
$page = 'tasks'; $pageTitle = 'Tasks & Follow Ups';
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_POST['done_id'])) {
        db()->prepare("UPDATE tasks SET status='done' WHERE id=?")->execute([(int)$_POST['done_id']]);
        $msg = 'Task completed.';
    } elseif (trim($_POST['title'] ?? '') !== '') {
        $_POST['assigned_to'] = current_user()['id'] ?? null;
        save_row('tasks', ['title','type','related_type','related_id','subtitle','due_at','priority','assigned_to'], $_POST, null);
        $msg = 'Task added.';
    }
}

$filter = $_GET['filter'] ?? 'open';
$where = $filter === 'done' ? "WHERE status='done'" : ($filter === 'all' ? '' : "WHERE status='open'");
$tasks = rows("SELECT * FROM tasks $where ORDER BY (status='open') DESC, due_at ASC LIMIT 100");

$overdue  = (int) scalar("SELECT COUNT(*) FROM tasks WHERE status='open' AND due_at < NOW()");
$today    = (int) scalar("SELECT COUNT(*) FROM tasks WHERE status='open' AND DATE(due_at)=CURDATE()");
$openAll  = (int) scalar("SELECT COUNT(*) FROM tasks WHERE status='open'");
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
  <div><h2>Tasks &amp; Follow Ups</h2><p><?= $openAll ?> open · <?= $overdue ?> overdue</p></div>
</div>
<?php if ($msg): ?><div class="login-err" style="background:var(--green-bg);color:var(--green);margin-bottom:16px">✓ <?= e($msg) ?></div><?php endif; ?>

<div class="kpi-row" style="grid-template-columns:repeat(3,1fr);margin-bottom:18px">
  <div class="kpi"><div class="top"><div class="ic-box pink"><?= icon('clock',20) ?></div><div class="k-label">Overdue</div></div><div class="k-value"><?= $overdue ?></div></div>
  <div class="kpi"><div class="top"><div class="ic-box amber"><?= icon('calendar',20) ?></div><div class="k-label">Due Today</div></div><div class="k-value"><?= $today ?></div></div>
  <div class="kpi"><div class="top"><div class="ic-box blue"><?= icon('tasks',20) ?></div><div class="k-label">Open Tasks</div></div><div class="k-value"><?= $openAll ?></div></div>
</div>

<div class="grid" style="grid-template-columns:1fr 1.7fr">
  <form method="post" class="card"><?= csrf_field() ?>
    <div class="card-head"><h3>Add Follow Up</h3></div>
    <?= field('Title','title','','text',['placeholder'=>'Follow up with Ramesh Patel','required'=>'required']) ?>
    <?= field('Subtitle / Context','subtitle','','text',['placeholder'=>'Paradise Heights – Site Visit']) ?>
    <?= select_field('Type','type',['followup'=>'Follow Up','callback'=>'Call Back','document'=>'Document','visit'=>'Site Visit','other'=>'Other'],'followup') ?>
    <?= select_field('Priority','priority',['high'=>'High','medium'=>'Medium','low'=>'Low'],'medium') ?>
    <?= field('Due Date &amp; Time','due_at','','datetime-local') ?>
    <button class="btn primary" type="submit" style="margin-top:14px"><?= icon('plus',16) ?> Add Task</button>
  </form>

  <div class="card">
    <div class="card-head"><h3>Your Tasks</h3>
      <div class="tabs" style="border:none;margin:0">
        <a href="<?= url('tasks',['filter'=>'open']) ?>" class="<?= $filter==='open'?'active':'' ?>">Open</a>
        <a href="<?= url('tasks',['filter'=>'done']) ?>" class="<?= $filter==='done'?'active':'' ?>">Done</a>
        <a href="<?= url('tasks',['filter'=>'all']) ?>" class="<?= $filter==='all'?'active':'' ?>">All</a>
      </div>
    </div>
    <?php foreach ($tasks as $t):
      $pc = ['high'=>'red','medium'=>'amber','low'=>'grey'][$t['priority']] ?? 'grey';
      $ic = ['followup'=>'phone','document'=>'file','callback'=>'phone','visit'=>'location'][$t['type']] ?? 'clock';
      $isOverdue = $t['status']==='open' && $t['due_at'] && strtotime($t['due_at']) < time();
    ?>
      <div class="task-item">
        <div class="feed-ic ic-box <?= $pc==='red'?'pink':($pc==='amber'?'amber':'blue') ?>"><?= icon($ic,16) ?></div>
        <div style="flex:1">
          <div style="font-size:13px;font-weight:600;<?= $t['status']==='done'?'text-decoration:line-through;opacity:.55':'' ?>"><?= e($t['title']) ?></div>
          <div class="muted tiny"><?= e($t['subtitle']) ?></div>
          <div class="tiny" style="margin-top:3px;color:<?= $isOverdue?'var(--red)':'var(--text-muted)' ?>">
            <?= $t['due_at'] ? fdate($t['due_at'],'d M Y, g:i A') : 'No due date' ?><?= $isOverdue ? ' · Overdue' : '' ?>
          </div>
        </div>
        <span class="badge <?= $pc ?>"><?= ucfirst($t['priority']) ?></span>
        <?php if ($t['status']==='open'): ?>
          <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="done_id" value="<?= $t['id'] ?>">
            <button class="btn ghost sm" type="submit">Done</button></form>
        <?php else: ?><span class="badge green">Completed</span><?php endif; ?>
      </div>
    <?php endforeach; ?>
    <?php if (!$tasks): ?><div class="muted small" style="padding:20px 0">No tasks here.</div><?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>

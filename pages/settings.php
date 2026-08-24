<?php
/** RE360 — Settings (profile, team, system info) */
require_once __DIR__ . '/../includes/icons.php';
require_once __DIR__ . '/../includes/crud.php';
$page = 'settings'; $pageTitle = 'Settings';
$msg = ''; $err = '';
$me = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($_POST['action'] ?? '') === 'profile') {
        db()->prepare("UPDATE users SET name=?, email=?, mobile=? WHERE id=?")
            ->execute([trim($_POST['name']), trim($_POST['email']), trim($_POST['mobile']), $me['id']]);
        $_SESSION['user']['name'] = trim($_POST['name']);
        $_SESSION['user']['email'] = trim($_POST['email']);
        $msg = 'Profile updated.';
    } elseif (($_POST['action'] ?? '') === 'password') {
        $u = row("SELECT password_hash FROM users WHERE id=?", [$me['id']]);
        if (!password_verify($_POST['current_password'] ?? '', $u['password_hash'])) {
            $err = 'Current password is incorrect.';
        } elseif (strlen($_POST['new_password'] ?? '') < 6) {
            $err = 'New password must be at least 6 characters.';
        } else {
            db()->prepare("UPDATE users SET password_hash=? WHERE id=?")
                ->execute([password_hash($_POST['new_password'], PASSWORD_DEFAULT), $me['id']]);
            $msg = 'Password changed.';
        }
    } elseif (($_POST['action'] ?? '') === 'edituser' && $me['role'] === 'admin') {
        $uid   = (int)($_POST['u_id'] ?? 0);
        $target = $uid ? row("SELECT * FROM users WHERE id=?", [$uid]) : null;
        $editId = $uid;                 // stay on this member unless the save succeeds
        $email = trim($_POST['u_email'] ?? '');
        $role  = $_POST['u_role'] ?? '';
        $pass  = $_POST['u_password'] ?? '';

        if (!$target) {
            $err = 'That team member no longer exists.';
        } elseif ($email === '') {
            $err = 'Email cannot be empty.';
        } elseif (scalar("SELECT id FROM users WHERE email=? AND id<>?", [$email, $uid])) {
            $err = 'Another user already has that email.';
        } elseif ($pass !== '' && strlen($pass) < 6) {
            $err = 'New password must be at least 6 characters.';
        } elseif ($target['role'] === 'admin' && $role !== 'admin' && admin_count() <= 1) {
            // Demoting the only admin would leave nobody able to manage the team.
            $err = 'This is the only admin. Make someone else an admin before changing this role.';
        } else {
            db()->prepare("UPDATE users SET name=?, email=?, mobile=?, role=? WHERE id=?")
                ->execute([trim($_POST['u_name'] ?? ''), $email, trim($_POST['u_mobile'] ?? ''), $role, $uid]);
            if ($pass !== '') {
                db()->prepare("UPDATE users SET password_hash=? WHERE id=?")
                    ->execute([password_hash($pass, PASSWORD_DEFAULT), $uid]);
            }
            log_activity('user_updated', 'user', $uid, 'Team member updated – ' . trim($_POST['u_name'] ?? ''), 'settings');
            $msg = 'Team member updated.' . ($pass !== '' ? ' Password changed.' : '');
            $editId = 0;   // close the edit form
        }

    } elseif (($_POST['action'] ?? '') === 'deleteuser' && $me['role'] === 'admin') {
        $uid    = (int)($_POST['u_id'] ?? 0);
        $target = $uid ? row("SELECT * FROM users WHERE id=?", [$uid]) : null;

        if (!$target) {
            $err = 'That team member no longer exists.';
        } elseif ($uid === (int)$me['id']) {
            $err = 'You cannot remove your own account.';
        } elseif ($target['role'] === 'admin' && admin_count() <= 1) {
            $err = 'This is the only admin account — it cannot be removed.';
        } else {
            /* Their work must not disappear with them. Clients and open tasks
             * move to the admin doing the removal; verification history keeps
             * its timestamp but loses the name. */
            db()->prepare("UPDATE clients SET assigned_to=? WHERE assigned_to=?")->execute([$me['id'], $uid]);
            db()->prepare("UPDATE tasks   SET assigned_to=? WHERE assigned_to=?")->execute([$me['id'], $uid]);
            db()->prepare("UPDATE inventory   SET verified_by=NULL WHERE verified_by=?")->execute([$uid]);
            db()->prepare("UPDATE activity_log SET user_id=NULL   WHERE user_id=?")->execute([$uid]);
            db()->prepare("DELETE FROM users WHERE id=?")->execute([$uid]);

            log_activity('user_removed', 'user', $uid,
                         'Team member removed – ' . $target['name'] . ' (their clients and tasks moved to you)', 'settings');
            $msg = $target['name'] . ' removed. Their clients and open tasks are now assigned to you.';
            $editId = 0;
        }

    } elseif (($_POST['action'] ?? '') === 'adduser' && $me['role'] === 'admin') {
        $email = trim($_POST['u_email'] ?? '');
        if ($email === '' || strlen($_POST['u_password'] ?? '') < 6) {
            $err = 'Enter an email and a password of at least 6 characters.';
        } elseif (scalar("SELECT id FROM users WHERE email=?", [$email])) {
            $err = 'A user with that email already exists.';
        } else {
            db()->prepare("INSERT INTO users (name, role, email, password_hash, mobile) VALUES (?,?,?,?,?)")
                ->execute([trim($_POST['u_name']), $_POST['u_role'], $email,
                           password_hash($_POST['u_password'], PASSWORD_DEFAULT), trim($_POST['u_mobile'] ?? '')]);
            $msg = 'Team member added.';
        }
    }
    $me = row("SELECT * FROM users WHERE id=?", [$me['id']]);
    unset($me['password_hash']);
    $_SESSION['user'] = array_merge($_SESSION['user'], $me);
}

/* How many admins are left — the guard against locking everyone out. */
function admin_count(): int
{
    return (int) scalar("SELECT COUNT(*) FROM users WHERE role='admin'");
}

$team = rows("SELECT id, name, role, email, mobile, last_login FROM users ORDER BY id");

/* Which member the left-hand card is editing (0 = the card adds a new one). */
if (!isset($editId)) $editId = (int)($_GET['edit'] ?? 0);
$editUser = null;
if ($editId && $me['role'] === 'admin') {
    foreach ($team as $t) { if ((int)$t['id'] === $editId) { $editUser = $t; break; } }
}
$roleOpts = ['sales'=>'Sales','channel_partner'=>'Channel Partner','admin'=>'Admin'];
$counts = [
  'Builders' => (int) scalar("SELECT COUNT(*) FROM builders"),
  'Projects' => (int) scalar("SELECT COUNT(*) FROM projects"),
  'Flats'    => (int) scalar("SELECT COUNT(*) FROM inventory"),
  'Clients'  => (int) scalar("SELECT COUNT(*) FROM clients"),
  'Bookings' => (int) scalar("SELECT COUNT(*) FROM bookings"),
  'Documents'=> (int) scalar("SELECT COUNT(*) FROM documents"),
];
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
  <div><h2>Settings</h2><p>Your profile, team access and system information</p></div>
</div>
<?php if ($msg): ?><div class="login-err" style="background:var(--green-bg);color:var(--green);margin-bottom:16px">✓ <?= e($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="login-err" style="margin-bottom:16px"><?= e($err) ?></div><?php endif; ?>

<div class="grid" style="grid-template-columns:1fr 1fr">
  <form method="post" class="card"><?= csrf_field() ?>
    <input type="hidden" name="action" value="profile">
    <div class="card-head"><h3>My Profile</h3></div>
    <?= field('Name','name',$me['name']) ?>
    <div style="margin-top:14px"><?= field('Email','email',$me['email'],'email') ?></div>
    <div style="margin-top:14px"><?= field('Mobile','mobile',$me['mobile'] ?? '') ?></div>
    <div class="form-group" style="margin-top:14px"><label>Role</label>
      <div><span class="badge violet"><?= e(ucwords(str_replace('_',' ',$me['role']))) ?></span></div></div>
    <button class="btn primary" type="submit" style="margin-top:16px">Save Profile</button>
  </form>

  <form method="post" class="card"><?= csrf_field() ?>
    <input type="hidden" name="action" value="password">
    <div class="card-head"><h3>Change Password</h3></div>
    <div class="form-group"><label>Current Password</label><input class="field-input" type="password" name="current_password" required></div>
    <div class="form-group" style="margin-top:14px"><label>New Password (min 6)</label><input class="field-input" type="password" name="new_password" required></div>
    <button class="btn primary" type="submit" style="margin-top:16px">Change Password</button>
  </form>
</div>

<?php if ($me['role'] === 'admin'): ?>
<div class="grid mt" id="team" style="grid-template-columns:1fr 1.5fr">
  <form method="post" class="card"><?= csrf_field() ?>
    <input type="hidden" name="action" value="<?= $editUser ? 'edituser' : 'adduser' ?>">
    <?php if ($editUser): ?><input type="hidden" name="u_id" value="<?= (int)$editUser['id'] ?>"><?php endif; ?>
    <div class="card-head"><h3><?= $editUser ? 'Edit ' . e($editUser['name']) : 'Add Team Member' ?></h3></div>
    <?= field('Name','u_name', $editUser['name'] ?? '') ?>
    <div style="margin-top:14px"><?= field('Email','u_email', $editUser['email'] ?? '', 'email') ?></div>
    <div style="margin-top:14px"><?= field('Mobile','u_mobile', $editUser['mobile'] ?? '') ?></div>
    <div style="margin-top:14px"><?= select_field('Role','u_role', $roleOpts, $editUser['role'] ?? 'sales') ?></div>
    <?php if ($editUser && (int)$editUser['id'] === (int)$me['id']): ?>
      <p class="muted tiny" style="margin-top:8px">This is your own account. Changing your role here can lock you out of admin screens.</p>
    <?php endif; ?>
    <div class="form-group" style="margin-top:14px">
      <label><?= $editUser ? 'New Password' : 'Password (min 6)' ?></label>
      <input class="field-input" type="password" name="u_password" autocomplete="new-password"
             placeholder="<?= $editUser ? 'Leave blank to keep current password' : '' ?>">
    </div>
    <div style="display:flex;gap:10px;margin-top:16px">
      <button class="btn primary" type="submit">
        <?= $editUser ? 'Save Changes' : icon('plus',16) . ' Add Member' ?>
      </button>
      <?php if ($editUser): ?><a class="btn ghost" href="<?= url('settings') ?>">Cancel</a><?php endif; ?>
    </div>
  </form>

  <div class="card pad0">
    <div class="card-head" style="padding:18px"><h3>Team</h3></div>
    <div class="table-wrap">
      <table class="data">
        <thead><tr><th>Name</th><th>Role</th><th>Email</th><th>Mobile</th><th>Last Login</th><th style="text-align:right">Actions</th></tr></thead>
        <tbody>
        <?php
        $adminsLeft = admin_count();
        foreach ($team as $t):
          $isMe    = (int)$t['id'] === (int)$me['id'];
          $lastAdm = $t['role'] === 'admin' && $adminsLeft <= 1;
          // Why a delete is refused, so the disabled button explains itself
          $noDel   = $isMe ? 'You cannot remove your own account'
                   : ($lastAdm ? 'The only admin account cannot be removed' : '');
        ?>
          <tr><td class="strong"><?= e($t['name']) ?><?= $isMe ? ' <span class="badge teal">You</span>' : '' ?></td>
            <td><span class="badge violet"><?= e(ucwords(str_replace('_',' ',$t['role']))) ?></span></td>
            <td><?= e($t['email']) ?></td><td><?= e($t['mobile'] ?: '—') ?></td>
            <td><?= $t['last_login'] ? fdate($t['last_login'],'d M Y, g:i A') : 'Never' ?></td>
            <td style="text-align:right;white-space:nowrap">
              <a class="btn ghost sm" href="<?= url('settings', ['edit' => $t['id']]) ?>#team">Edit</a>
              <?php if ($noDel): ?>
                <button class="btn ghost sm" type="button" disabled
                        style="opacity:.45;cursor:not-allowed" title="<?= e($noDel) ?>">Remove</button>
              <?php else: ?>
                <form method="post" style="display:inline" onsubmit="return confirm('Remove <?= e(addslashes($t['name'])) ?> from the team?\n\nTheir clients and open tasks will be reassigned to you. This cannot be undone.')">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="deleteuser">
                  <input type="hidden" name="u_id" value="<?= (int)$t['id'] ?>">
                  <button class="btn ghost sm" type="submit" style="color:var(--red)">Remove</button>
                </form>
              <?php endif; ?>
            </td></tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php endif; ?>

<div class="card mt">
  <div class="card-head"><h3>System Data</h3></div>
  <div class="grid strip" style="grid-template-columns:repeat(6,1fr);gap:12px;text-align:center">
    <?php foreach ($counts as $lbl=>$n): ?>
      <div class="card" style="background:var(--bg-card-2);padding:12px">
        <div style="font-size:20px;font-weight:800"><?= number_format($n) ?></div>
        <div class="muted tiny"><?= $lbl ?></div>
      </div>
    <?php endforeach; ?>
  </div>
  <p class="muted tiny" style="margin-top:14px">
    <?= SITE_NAME ?> · PHP <?= PHP_VERSION ?> · Environment: <?= RE360_ENV ?>
    <?php if (is_file(BASE_PATH . '/setup.php')): ?>
      <br><span style="color:var(--amber)">⚠ setup.php still exists — delete it from your server for security.</span>
    <?php endif; ?>
  </p>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>

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

$team = rows("SELECT id, name, role, email, mobile, last_login FROM users ORDER BY id");
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
  <form method="post" class="card">
    <input type="hidden" name="action" value="profile">
    <div class="card-head"><h3>My Profile</h3></div>
    <?= field('Name','name',$me['name']) ?>
    <div style="margin-top:14px"><?= field('Email','email',$me['email'],'email') ?></div>
    <div style="margin-top:14px"><?= field('Mobile','mobile',$me['mobile'] ?? '') ?></div>
    <div class="form-group" style="margin-top:14px"><label>Role</label>
      <div><span class="badge violet"><?= e(ucwords(str_replace('_',' ',$me['role']))) ?></span></div></div>
    <button class="btn primary" type="submit" style="margin-top:16px">Save Profile</button>
  </form>

  <form method="post" class="card">
    <input type="hidden" name="action" value="password">
    <div class="card-head"><h3>Change Password</h3></div>
    <div class="form-group"><label>Current Password</label><input class="field-input" type="password" name="current_password" required></div>
    <div class="form-group" style="margin-top:14px"><label>New Password (min 6)</label><input class="field-input" type="password" name="new_password" required></div>
    <button class="btn primary" type="submit" style="margin-top:16px">Change Password</button>
  </form>
</div>

<?php if ($me['role'] === 'admin'): ?>
<div class="grid mt" style="grid-template-columns:1fr 1.5fr">
  <form method="post" class="card">
    <input type="hidden" name="action" value="adduser">
    <div class="card-head"><h3>Add Team Member</h3></div>
    <?= field('Name','u_name') ?>
    <div style="margin-top:14px"><?= field('Email','u_email','','email') ?></div>
    <div style="margin-top:14px"><?= field('Mobile','u_mobile') ?></div>
    <div style="margin-top:14px"><?= select_field('Role','u_role',['sales'=>'Sales','channel_partner'=>'Channel Partner','admin'=>'Admin'],'sales') ?></div>
    <div class="form-group" style="margin-top:14px"><label>Password (min 6)</label><input class="field-input" type="password" name="u_password"></div>
    <button class="btn primary" type="submit" style="margin-top:16px"><?= icon('plus',16) ?> Add Member</button>
  </form>

  <div class="card pad0">
    <div class="card-head" style="padding:18px"><h3>Team</h3></div>
    <div class="table-wrap">
      <table class="data">
        <thead><tr><th>Name</th><th>Role</th><th>Email</th><th>Mobile</th><th>Last Login</th></tr></thead>
        <tbody>
        <?php foreach ($team as $t): ?>
          <tr><td class="strong"><?= e($t['name']) ?><?= $t['id']==$me['id']?' <span class="badge teal">You</span>':'' ?></td>
            <td><span class="badge violet"><?= e(ucwords(str_replace('_',' ',$t['role']))) ?></span></td>
            <td><?= e($t['email']) ?></td><td><?= e($t['mobile'] ?: '—') ?></td>
            <td><?= $t['last_login'] ? fdate($t['last_login'],'d M Y, g:i A') : 'Never' ?></td></tr>
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

<?php
/**
 * RE360 — authentication guard
 * include at top of any protected page (after config.php)
 */

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function is_logged_in(): bool
{
    return !empty($_SESSION['user']);
}

function require_login(): void
{
    if (!is_logged_in()) {
        header('Location: ' . app_base_url() . 'login.php');
        exit;
    }
}

/* ---------------- Brute-force throttle ----------------
 * File based so it works on any shared host without a DB table.
 * 8 failed attempts from one IP  ->  locked for 15 minutes.
 */
define('LOGIN_MAX_ATTEMPTS', 8);
define('LOGIN_LOCK_SECONDS', 900);

function login_throttle_file(): string
{
    $dir = BASE_PATH . '/logs/throttle';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'cli';
    return $dir . '/' . sha1($ip) . '.json';
}

function login_attempts(): array
{
    $f = login_throttle_file();
    if (!is_file($f)) return ['count' => 0, 'first' => time()];
    $d = json_decode((string)@file_get_contents($f), true);
    if (!is_array($d)) return ['count' => 0, 'first' => time()];
    if ((time() - (int)($d['first'] ?? 0)) > LOGIN_LOCK_SECONDS) {
        return ['count' => 0, 'first' => time()];   // window expired
    }
    return ['count' => (int)($d['count'] ?? 0), 'first' => (int)($d['first'] ?? time())];
}

/* Seconds still locked out, or 0 when the user may try again */
function login_locked_for(): int
{
    $a = login_attempts();
    if ($a['count'] < LOGIN_MAX_ATTEMPTS) return 0;
    $left = LOGIN_LOCK_SECONDS - (time() - $a['first']);
    return $left > 0 ? $left : 0;
}

function login_register_failure(): void
{
    $a = login_attempts();
    $a['count']++;
    @file_put_contents(login_throttle_file(), json_encode($a), LOCK_EX);
}

function login_clear_failures(): void
{
    $f = login_throttle_file();
    if (is_file($f)) @unlink($f);
}

function attempt_login(string $email, string $password): bool
{
    if (login_locked_for() > 0) return false;

    $u = row("SELECT * FROM users WHERE email = ? LIMIT 1", [trim($email)]);
    if ($u && password_verify($password, $u['password_hash'])) {
        // Rotate the session id so a pre-set cookie cannot be reused (session fixation)
        session_regenerate_id(true);
        unset($u['password_hash']);
        $_SESSION['user'] = $u;
        $_SESSION['last_seen'] = time();
        login_clear_failures();
        try {
            db()->prepare("UPDATE users SET last_login = NOW(), is_online = 1 WHERE id = ?")
                ->execute([$u['id']]);
        } catch (Throwable $e) {}
        return true;
    }

    login_register_failure();
    // Slow down automated guessing a little
    usleep(300000);
    return false;
}

function logout(): void
{
    if (!empty($_SESSION['user']['id'])) {
        try {
            db()->prepare("UPDATE users SET is_online = 0 WHERE id = ?")
                ->execute([$_SESSION['user']['id']]);
        } catch (Throwable $e) {}
    }
    $_SESSION = [];
    session_destroy();
}

/* Role guard: require_role('admin') */
function require_role(string ...$roles): void
{
    require_login();
    $r = current_user()['role'] ?? '';
    if (!in_array($r, $roles, true)) {
        require_once BASE_PATH . '/includes/error_page.php';
        re360_error_page(403, 'Access denied',
            'This section is limited to administrators. Ask your admin if you need access.');
    }
}

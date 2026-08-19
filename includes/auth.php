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
        header('Location: login.php');
        exit;
    }
}

function attempt_login(string $email, string $password): bool
{
    $u = row("SELECT * FROM users WHERE email = ? LIMIT 1", [trim($email)]);
    if ($u && password_verify($password, $u['password_hash'])) {
        unset($u['password_hash']);
        $_SESSION['user'] = $u;
        try {
            db()->prepare("UPDATE users SET last_login = NOW(), is_online = 1 WHERE id = ?")
                ->execute([$u['id']]);
        } catch (Throwable $e) {}
        return true;
    }
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
        http_response_code(403);
        die('Access denied.');
    }
}

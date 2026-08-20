<?php
/**
 * RE360 — CSRF protection
 *
 * Every POST form must contain the hidden token printed by csrf_field().
 * index.php verifies it automatically for all page POSTs; login.php and
 * setup.php verify it themselves because they sit outside the router.
 */

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/* Hidden input to drop inside every <form method="post"> */
function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . csrf_token() . '">';
}

function csrf_valid(): bool
{
    $sent = $_POST['_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    $have = $_SESSION['csrf_token'] ?? '';
    return $sent !== '' && $have !== '' && hash_equals($have, $sent);
}

/* Stop the request when the token is missing or wrong */
function csrf_check(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
    if (csrf_valid()) return;

    /* An upload bigger than post_max_size arrives with $_POST completely
     * empty — that is a size problem, not a CSRF problem. Say so clearly. */
    $len = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
    if (empty($_POST) && $len > 0) {
        http_response_code(413);
        $limit = ini_get('post_max_size') ?: '8M';
        echo '<!doctype html><meta charset="utf-8"><title>File too large</title>'
           . '<div style="font-family:Inter,system-ui,sans-serif;max-width:520px;margin:12vh auto;padding:28px;'
           . 'background:#141821;color:#e6e9ef;border-radius:14px;border:1px solid #262c38">'
           . '<h2 style="margin:0 0 10px">Upload too large</h2>'
           . '<p style="color:#8b93a7;line-height:1.6">The file exceeds this server\'s limit of '
           . htmlspecialchars($limit) . '. Compress the file, or raise upload_max_filesize and '
           . 'post_max_size in <code>.user.ini</code>.</p>'
           . '<a href="javascript:history.back()" style="display:inline-block;margin-top:14px;background:#4f7cff;'
           . 'color:#fff;text-decoration:none;padding:10px 18px;border-radius:9px;font-weight:600">Go back</a></div>';
        exit;
    }

    http_response_code(419);
    if (!headers_sent()) header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><meta charset="utf-8"><title>Session expired</title>'
       . '<div style="font-family:Inter,system-ui,sans-serif;max-width:520px;margin:12vh auto;padding:28px;'
       . 'background:#141821;color:#e6e9ef;border-radius:14px;border:1px solid #262c38">'
       . '<h2 style="margin:0 0 10px">Session expired</h2>'
       . '<p style="color:#8b93a7;line-height:1.6">Your session timed out or the page was open for too long. '
       . 'Please go back, reload the page and submit the form again.</p>'
       . '<a href="index.php" style="display:inline-block;margin-top:14px;background:#4f7cff;color:#fff;'
       . 'text-decoration:none;padding:10px 18px;border-radius:9px;font-weight:600">Back to dashboard</a></div>';
    exit;
}

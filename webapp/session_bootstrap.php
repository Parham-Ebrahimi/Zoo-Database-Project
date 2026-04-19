<?php
/**
 * One session per browser: consistent cookie path and SameSite so logins survive refresh.
 * Must be loaded instead of calling session_start() directly (before any output).
 */
if (session_status() !== PHP_SESSION_NONE) {
    return;
}

// PHP default gc_maxlifetime is often ~24 minutes: server may delete session files while the
// browser still sends the cookie → empty session on refresh → redirect to login. Align lifetime.
$sessionLifetime = 60 * 60 * 24 * 7; // 7 days (seconds)
ini_set('session.gc_maxlifetime', (string) $sessionLifetime);

$https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
$forwarded = isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https';
$secure = $https || $forwarded;

session_set_cookie_params([
    'lifetime' => $sessionLifetime,
    'path'     => '/',
    'domain'   => '',
    'secure'   => $secure,
    'httponly' => true,
    'samesite' => 'Lax',
]);

session_start();

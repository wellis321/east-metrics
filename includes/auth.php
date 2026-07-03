<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

function is_logged_in(): bool
{
    return !empty($_SESSION['admin_logged_in']);
}

function require_login(): void
{
    if (!is_logged_in()) {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        redirect(app_url('/login.php') . '?next=' . urlencode($uri));
    }
}

function require_admin(): void
{
    require_login();
    if (($_SESSION['app_role'] ?? '') !== 'admin') {
        http_response_code(403);
        exit('Forbidden — admin access required.');
    }
}

function resolve_login_next(string $next, string $defaultPath = '/dashboard.php'): string
{
    if ($next === '') {
        return app_url($defaultPath);
    }
    if (str_starts_with($next, APP_URL . '/')) {
        return $next;
    }
    if (str_starts_with($next, '/') && !str_starts_with($next, '//')) {
        return app_url($next);
    }

    return app_url($defaultPath);
}

function client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function is_rate_limited(string $ip): bool
{
    $stmt = auth_db()->prepare(
        'SELECT COUNT(*) FROM login_attempts
          WHERE ip = ? AND attempted_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)'
    );
    $stmt->execute([$ip]);

    return (int) $stmt->fetchColumn() >= 10;
}

function record_failed_attempt(string $ip): void
{
    auth_db()->prepare('INSERT INTO login_attempts (ip) VALUES (?)')->execute([$ip]);
}

function clear_attempts(string $ip): void
{
    auth_db()->prepare('DELETE FROM login_attempts WHERE ip = ?')->execute([$ip]);
}

/** @param array<string,mixed> $user */
function establish_session(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_user']      = (string) ($user['display_name'] ?: $user['username']);
    $_SESSION['user_id']         = (int) $user['id'];
    $_SESSION['auth_provider']   = (string) ($user['auth_provider'] ?? 'local');
    $_SESSION['app_role']        = (string) ($user['app_role'] ?? 'viewer');
}

/**
 * Returns true on success, false on bad credentials. Accounts with no
 * password set (Microsoft-only accounts provisioned in SOR/AS-IS) cannot
 * log in here since this app does not implement Entra SSO.
 */
function attempt_login(string $user, string $pass): bool
{
    $user  = trim($user);
    $email = strtolower($user);

    $db   = auth_db();
    $stmt = $db->prepare(
        'SELECT id, username, password_hash, auth_provider, display_name, app_role, email
         FROM users
         WHERE is_active = 1
           AND (username = ? OR LOWER(email) = ?)
         LIMIT 1'
    );
    $stmt->execute([$user, $email]);
    $row = $stmt->fetch();

    $hash  = $row && !empty($row['password_hash'])
        ? $row['password_hash']
        : '$2y$12$invalidsaltinvalidsaltinvalidsaltinvalidsaltinvalidsa';
    $valid = password_verify($pass, $hash);

    $reason = 'bad_password';
    if (!$row) {
        $reason = 'user_not_found';
    } elseif (empty($row['password_hash'])) {
        $reason = 'no_local_password';
    } elseif ($valid) {
        $reason = 'ok';
    }
    $_SESSION['_last_login_fail'] = $reason;

    if ($row && $valid && !empty($row['password_hash'])) {
        establish_session($row);
        unset($_SESSION['_last_login_fail']);
        return true;
    }

    return false;
}

function last_login_fail_reason(): string
{
    return (string) ($_SESSION['_last_login_fail'] ?? '');
}

function get_current_user_id(): int
{
    return (int) ($_SESSION['user_id'] ?? 0);
}

function logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

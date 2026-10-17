<?php
/**
 * Sign-in, sessions and role checks.
 * Roles: "admin" manages everything; "teacher" records and monitors attendance for their own classes.
 */

const MAX_LOGIN_ATTEMPTS = 5;
const LOCKOUT_SECONDS    = 300;

/** The signed-in, active user, or null. Re-read from the database on each request. */
function current_user(): ?array
{
    static $user = false;
    if ($user === false) {
        $user = null;
        if (!empty($_SESSION['user_id'])) {
            $stmt = db()->prepare('SELECT id, username, full_name, email, role FROM users WHERE id = ? AND is_active = 1');
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch() ?: null;
            if ($user === null) {
                unset($_SESSION['user_id']);
            }
        }
    }
    return $user;
}

function is_admin(): bool
{
    $user = current_user();
    return $user !== null && $user['role'] === 'admin';
}

function require_login(): array
{
    $user = current_user();
    if ($user === null) {
        redirect('login.php');
    }
    return $user;
}

function require_admin(): array
{
    $user = require_login();
    if ($user['role'] !== 'admin') {
        flash('error', 'That page is only available to administrators.');
        redirect('index.php');
    }
    return $user;
}

/**
 * Checks credentials and starts a session. Accounts lock for a few minutes after
 * repeated failures. Returns null on success, otherwise an error message.
 */
function attempt_login(string $username, string $password): ?string
{
    $stmt = db()->prepare('SELECT * FROM users WHERE username = ?');
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && $user['locked_until'] !== null && strtotime($user['locked_until']) > time()) {
        $minutes = (int) ceil((strtotime($user['locked_until']) - time()) / 60);
        return "Too many failed attempts. Try again in {$minutes} minute(s).";
    }

    if (!$user || !password_verify($password, $user['password_hash'])) {
        if ($user) {
            $fails  = (int) $user['failed_logins'] + 1;
            $locked = $fails >= MAX_LOGIN_ATTEMPTS ? date('Y-m-d H:i:s', time() + LOCKOUT_SECONDS) : null;
            db()->prepare('UPDATE users SET failed_logins = ?, locked_until = ? WHERE id = ?')
                ->execute([$locked ? 0 : $fails, $locked, $user['id']]);
        }
        return 'Incorrect username or password.';
    }

    if (!$user['is_active']) {
        return 'This account has been deactivated. Please contact an administrator.';
    }

    if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
        db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
            ->execute([password_hash($password, PASSWORD_DEFAULT), $user['id']]);
    }
    db()->prepare('UPDATE users SET failed_logins = 0, locked_until = NULL, last_login = NOW() WHERE id = ?')
        ->execute([$user['id']]);

    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    return null;
}

function logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        setcookie(session_name(), '', time() - 3600, '/');
    }
    session_destroy();
}

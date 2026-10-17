<?php
/**
 * Loaded first by every page: configuration, session, and shared helpers.
 */
require_once __DIR__ . '/../config.php';

date_default_timezone_set(APP_TIMEZONE);

if (session_status() === PHP_SESSION_NONE) {
    session_name('attendtrack');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/attendance.php';

set_exception_handler(function (Throwable $e) {
    error_log((string) $e);
    // 42S02 = table not found: the database exists but the installer has not run.
    if ($e instanceof PDOException && $e->getCode() === '42S02') {
        render_error_page('Database tables are missing', 'The database exists but its tables have not been created yet.', true);
    }
    render_error_page('Something went wrong', $e->getMessage());
});

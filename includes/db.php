<?php
/**
 * Returns the shared PDO connection. Shows a setup hint instead of a stack trace
 * when MySQL is not running or the database has not been created yet.
 */
function db(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);
    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        // Keep MySQL's NOW()/CURRENT_TIMESTAMP in step with PHP's timezone.
        $pdo->exec("SET time_zone = '" . date('P') . "'");
    } catch (PDOException $e) {
        $code = (int) $e->getCode();
        if ($code === 1049) {
            render_error_page('Database not set up yet', 'The database "' . DB_NAME . '" does not exist. Run the installer to create it.', true, 503);
        }
        if ($code === 2002 || $code === 2003) {
            render_error_page('Cannot reach MySQL', 'Start MySQL from the XAMPP Control Panel, then reload this page.', false, 503);
        }
        render_error_page('Database connection failed', $e->getMessage() . ' — check the settings in config.php.', false, 503);
    }
    return $pdo;
}

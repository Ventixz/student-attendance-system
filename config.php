<?php
/**
 * Application configuration.
 * The database defaults match a fresh XAMPP install (MySQL user "root", no password).
 */

// --- Database -------------------------------------------------------------
define('DB_HOST', '127.0.0.1');
define('DB_PORT', 3306);
define('DB_NAME', 'attendance_db');
define('DB_USER', 'root');
define('DB_PASS', '');

// --- Application ----------------------------------------------------------
define('APP_NAME', 'Student Attendance Monitoring System');
define('APP_SHORT_NAME', 'AttendTrack');
define('SCHOOL_NAME', 'Your School Name');

// Colour theme for first-time visitors: 'dark' or 'light'. Everyone can switch with the sun/moon button;
// their choice is remembered in their browser.
define('DEFAULT_THEME', 'dark');

// Timezone used for attendance date/time stamps. Full list: https://www.php.net/timezones
define('APP_TIMEZONE', 'Asia/Shanghai');

// When true, "Late" counts toward the attendance percentage (the student did attend).
define('LATE_COUNTS_AS_PRESENT', true);

// Students below this attendance percentage are flagged on the dashboard and in reports.
define('LOW_ATTENDANCE_THRESHOLD', 75);

// Rows per page on long listings.
define('PER_PAGE', 25);

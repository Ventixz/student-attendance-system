<?php
/**
 * Admin panel layout (top half). Set $pageTitle and $activeNav before including.
 */
$me        = current_user();
$activeNav = $activeNav ?? '';

$navGroups = [
    'Overview' => [
        ['dashboard', 'index.php', 'dashboard', 'Dashboard'],
    ],
    'Attendance' => [
        ['take', 'attendance.php', 'check', 'Take Attendance'],
        ['quick', 'quick_entry.php', 'zap', 'Quick Entry'],
        ['records', 'records.php', 'list', 'Attendance Records'],
    ],
    'Reports' => [
        ['daily', 'report_daily.php', 'calendar', 'Daily Attendance'],
        ['present', 'report_status.php?status=Present', 'user-check', 'Present Students'],
        ['absent', 'report_status.php?status=Absent', 'user-x', 'Absent Students'],
        ['late', 'report_status.php?status=Late', 'clock', 'Late Students'],
        ['student', 'report_student.php', 'file', 'Student Report'],
        ['summary', 'report_summary.php', 'pie', 'Summary & Percentage'],
    ],
];
if ($me['role'] === 'admin') {
    $navGroups['Management'] = [
        ['students', 'students.php', 'users', 'Students'],
        ['subjects', 'subjects.php', 'book', 'Classes & Subjects'],
        ['users', 'users.php', 'shield', 'Staff Accounts'],
    ];
}
?><!doctype html>
<html lang="en"<?= theme_attr() ?>>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> · <?= e(APP_SHORT_NAME) ?></title>
    <link rel="icon" href="../assets/img/favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="../assets/css/style.css">
    <?= theme_script() ?>
</head>
<body class="admin-body">
<aside class="sidebar" id="sidebar">
    <a class="brand" href="<?= e(page('index.php')) ?>">
        <span class="brand-mark"><?= icon('check') ?></span>
        <span class="brand-text"><strong><?= e(APP_SHORT_NAME) ?></strong><small>Attendance Admin</small></span>
    </a>
    <nav class="nav" aria-label="Main">
        <?php foreach ($navGroups as $group => $items): ?>
            <p class="nav-group"><?= e($group) ?></p>
            <?php foreach ($items as [$key, $href, $iconName, $label]): ?>
                <a class="nav-link<?= $key === $activeNav ? ' active' : '' ?>" href="<?= e(page($href)) ?>"<?= $key === $activeNav ? ' aria-current="page"' : '' ?>>
                    <?= icon($iconName) ?><span><?= e($label) ?></span>
                </a>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </nav>
    <div class="sidebar-foot">
        <a class="nav-link<?= $activeNav === 'profile' ? ' active' : '' ?>" href="<?= e(page('profile.php')) ?>"><?= icon('user') ?><span>My Profile</span></a>
        <a class="nav-link" href="<?= e(page('../index.php')) ?>"><?= icon('home') ?><span>View Website</span></a>
        <form method="post" action="<?= e(page('logout.php')) ?>">
            <?= csrf_field() ?>
            <button type="submit" class="nav-link"><?= icon('logout') ?><span>Sign Out</span></button>
        </form>
    </div>
</aside>
<div class="sidebar-backdrop" data-close-sidebar></div>

<div class="main">
    <header class="topbar">
        <button type="button" class="icon-btn menu-btn" data-toggle-sidebar aria-label="Open menu" aria-controls="sidebar"><?= icon('menu') ?></button>
        <div class="topbar-title">
            <h1><?= e($pageTitle) ?></h1>
            <span class="muted"><?= e(date('l, F j, Y')) ?></span>
        </div>
        <?= theme_toggle() ?>
        <a class="user-chip" href="<?= e(page('profile.php')) ?>" title="My profile">
            <span class="avatar"><?= e(initials($me['full_name'])) ?></span>
            <span class="user-chip-text"><strong><?= e($me['full_name']) ?></strong><small><?= $me['role'] === 'admin' ? 'Administrator' : 'Teacher' ?></small></span>
        </a>
    </header>
    <main class="content">
        <?= render_flashes() ?>

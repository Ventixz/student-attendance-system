<?php
/** Public website header. Set $pageTitle before including. */
$signedIn = !empty($_SESSION['user_id']) && current_user() !== null;
?><!doctype html>
<html lang="en"<?= theme_attr() ?>>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?></title>
    <meta name="description" content="<?= e(APP_NAME) ?> for <?= e(SCHOOL_NAME) ?>: record and monitor student attendance.">
    <link rel="icon" href="assets/img/favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="assets/css/style.css">
    <?= theme_script() ?>
</head>
<body class="site-body">
<header class="site-nav">
    <div class="site-wrap">
        <a class="brand" href="<?= e(page('index.php')) ?>">
            <span class="brand-mark"><?= icon('check') ?></span>
            <span class="brand-text"><strong><?= e(APP_SHORT_NAME) ?></strong><small><?= e(SCHOOL_NAME) ?></small></span>
        </a>
        <nav class="site-links" aria-label="Website">
            <a href="<?= e(page('index.php')) ?>#features">Features</a>
            <a href="<?= e(page('index.php')) ?>#how">How it works</a>
            <a href="<?= e(page('check.php')) ?>">Check attendance</a>
            <?= theme_toggle() ?>
            <?php if ($signedIn): ?>
                <a class="btn btn-primary btn-sm" href="<?= e(page('admin/index.php')) ?>"><?= icon('dashboard') ?> Dashboard</a>
            <?php else: ?>
                <a class="btn btn-primary btn-sm" href="<?= e(page('admin/login.php')) ?>"><?= icon('lock') ?> Staff login</a>
            <?php endif; ?>
        </nav>
    </div>
</header>

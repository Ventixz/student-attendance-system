<?php
require __DIR__ . '/../includes/bootstrap.php';

if (current_user()) {
    redirect('index.php');
}

// First run: no accounts yet, so send the visitor to the installer.
if ((int) db()->query('SELECT COUNT(*) FROM users')->fetchColumn() === 0) {
    redirect('../install.php');
}

$error    = null;
$username = '';

if (is_post()) {
    verify_csrf();
    $username = input('username');
    $password = (string) ($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $error = 'Enter your username and password.';
    } else {
        $error = attempt_login($username, $password);
        if ($error === null) {
            redirect('index.php');
        }
    }
}
?><!doctype html>
<html lang="en"<?= theme_attr() ?>>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Staff login · <?= e(APP_SHORT_NAME) ?></title>
    <link rel="icon" href="../assets/img/favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="../assets/css/style.css">
    <?= theme_script() ?>
</head>
<body class="auth-body">
<div class="auth">
    <aside class="auth-aside">
        <a class="brand" href="<?= e(page('../index.php')) ?>">
            <span class="brand-mark"><?= icon('check') ?></span>
            <span class="brand-text"><strong><?= e(APP_SHORT_NAME) ?></strong><small><?= e(SCHOOL_NAME) ?></small></span>
        </a>
        <div>
            <h2>Attendance, recorded and reported in one place.</h2>
            <p class="lead">Sign in to take attendance, monitor your classes, and generate reports.</p>
            <ul class="auth-points">
                <li><?= icon('user-check') ?> Present, Late and Absent in one tap</li>
                <li><?= icon('clock') ?> Date and time saved automatically</li>
                <li><?= icon('pie') ?> Daily records, lists and percentages</li>
            </ul>
        </div>
        <p class="small" style="color:#a5b4fc"><?= e(APP_NAME) ?></p>
    </aside>

    <main class="auth-main">
        <?= theme_toggle('corner-toggle') ?>
        <form class="auth-form form-stack" method="post" novalidate>
            <div>
                <h1>Staff login</h1>
                <p class="muted">Administrators and teachers</p>
            </div>
            <?= render_flashes() ?>
            <?php if ($error): ?>
                <div class="alert alert-error" role="alert"><?= icon('alert') ?><span><?= e($error) ?></span></div>
            <?php endif; ?>
            <?= csrf_field() ?>
            <div class="field">
                <label for="username">Username</label>
                <input id="username" name="username" value="<?= e($username) ?>" required autocomplete="username" autofocus>
            </div>
            <div class="field">
                <label for="password">Password</label>
                <input id="password" name="password" type="password" required autocomplete="current-password">
            </div>
            <button class="btn btn-primary btn-block" type="submit"><?= icon('lock') ?> Sign in</button>
            <a class="back-link" href="<?= e(page('../index.php')) ?>"><?= icon('arrow-left') ?> Back to website</a>
        </form>
    </main>
</div>
<script src="../assets/js/app.js"></script>
</body>
</html>

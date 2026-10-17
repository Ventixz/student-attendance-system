<?php
/**
 * One-time installer: creates the database and tables, then the first administrator account.
 * Once an administrator exists it only reports status, so it cannot be used to add more admins.
 */
require_once __DIR__ . '/config.php';
date_default_timezone_set(APP_TIMEZONE);
session_name('attendtrack');
session_start();
require_once __DIR__ . '/includes/helpers.php';

/** Connection to the MySQL server itself (the database may not exist yet). */
function server_connection(): PDO
{
    $pdo = new PDO(sprintf('mysql:host=%s;port=%d;charset=utf8mb4', DB_HOST, DB_PORT), DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec("SET time_zone = '" . date('P') . "'");
    return $pdo;
}

function quoted_db_name(): string
{
    return '`' . str_replace('`', '``', DB_NAME) . '`';
}

function install_state(PDO $pdo): array
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?');
    $stmt->execute([DB_NAME]);
    $state = ['database' => (bool) $stmt->fetchColumn(), 'tables' => false, 'admin' => false];

    if ($state['database']) {
        $stmt = $pdo->prepare("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = ?");
        $stmt->execute([DB_NAME]);
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $state['tables'] = !array_diff(['users', 'students', 'subjects', 'enrollments', 'attendance'], $tables);
        if (in_array('users', $tables, true)) {
            $state['admin'] = (int) $pdo->query('SELECT COUNT(*) FROM ' . quoted_db_name() . ".users WHERE role = 'admin'")->fetchColumn() > 0;
        }
    }
    return $state;
}

function run_schema(PDO $pdo): void
{
    $pdo->exec('CREATE DATABASE IF NOT EXISTS ' . quoted_db_name() . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $pdo->exec('USE ' . quoted_db_name());

    $sql = preg_replace('/^\s*--.*$/m', '', file_get_contents(__DIR__ . '/sql/schema.sql'));
    foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
        // schema.sql names its own database for phpMyAdmin imports; here config.php's DB_NAME is used.
        if (preg_match('/^(CREATE DATABASE|USE)\b/i', $statement)) {
            continue;
        }
        $pdo->exec($statement);
    }
}

/** Demo classes, students and three weeks of attendance so every report has something to show. */
function seed_sample_data(PDO $pdo, int $teacherId): void
{
    mt_srand(20261017);

    $subjects = [
        // code, name, schedule, start time, weekdays (1 = Mon)
        ['CS101', 'Introduction to Computing', 'Mon/Wed/Fri · 8:00–9:00 AM', '08:00', [1, 3, 5]],
        ['CS102', 'Computer Programming 1', 'Tue/Thu · 10:00–11:30 AM', '10:00', [2, 4]],
        ['GE103', 'Purposive Communication', 'Mon/Wed/Fri · 1:00–2:00 PM', '13:00', [1, 3, 5]],
    ];
    // student no, first, last, section, chance absent, chance late
    $students = [
        ['2026-0001', 'Ana', 'Reyes', 'Section A', .04, .06],
        ['2026-0002', 'Ben', 'Carter', 'Section A', .05, .08],
        ['2026-0003', 'Chloe', 'Tan', 'Section A', .02, .04],
        ['2026-0004', 'Daniel', 'Kim', 'Section A', .30, .10],
        ['2026-0005', 'Elena', 'Garcia', 'Section A', .06, .05],
        ['2026-0006', 'Farah', 'Hassan', 'Section A', .03, .12],
        ['2026-0007', 'Gabriel', 'Lopez', 'Section A', .08, .08],
        ['2026-0008', 'Hana', 'Sato', 'Section A', .02, .03],
        ['2026-0009', 'Isaac', 'Mensah', 'Section B', .05, .06],
        ['2026-0010', 'Julia', 'Novak', 'Section B', .04, .09],
        ['2026-0011', 'Kevin', 'Wong', 'Section B', .12, .30],
        ['2026-0012', 'Lina', 'Park', 'Section B', .03, .05],
        ['2026-0013', 'Marco', 'Rossi', 'Section B', .07, .07],
        ['2026-0014', 'Nadia', 'Ali', 'Section B', .35, .08],
        ['2026-0015', 'Oliver', 'Chen', 'Section B', .05, .04],
    ];

    $insSubject = $pdo->prepare('INSERT IGNORE INTO subjects (code, name, schedule, teacher_id) VALUES (?, ?, ?, ?)');
    $subjectIds = [];
    foreach ($subjects as [$code, $name, $schedule]) {
        $insSubject->execute([$code, $name, $schedule, $teacherId]);
        $subjectIds[$code] = (int) $pdo->query('SELECT id FROM subjects WHERE code = ' . $pdo->quote($code))->fetchColumn();
    }

    $insStudent = $pdo->prepare('INSERT IGNORE INTO students (student_no, first_name, last_name, section) VALUES (?, ?, ?, ?)');
    $studentIds = [];
    foreach ($students as $i => [$no, $first, $last, $section]) {
        $insStudent->execute([$no, $first, $last, $section]);
        $studentIds[$i] = (int) $pdo->query('SELECT id FROM students WHERE student_no = ' . $pdo->quote($no))->fetchColumn();
    }

    // CS101 and CS102: everyone. GE103: Section B plus three students from Section A.
    $rosters = [
        'CS101' => array_keys($students),
        'CS102' => array_keys($students),
        'GE103' => array_merge([0, 1, 2], range(8, 14)),
    ];
    $enroll = $pdo->prepare('INSERT IGNORE INTO enrollments (student_id, subject_id) VALUES (?, ?)');
    foreach ($rosters as $code => $indexes) {
        foreach ($indexes as $i) {
            $enroll->execute([$studentIds[$i], $subjectIds[$code]]);
        }
    }

    $mark = $pdo->prepare('INSERT IGNORE INTO attendance
        (student_id, subject_id, teacher_id, attendance_date, attendance_time, status, remarks, recorded_by, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');

    for ($daysAgo = 21; $daysAgo >= 0; $daysAgo--) {
        $date    = date('Y-m-d', strtotime("-{$daysAgo} days"));
        $weekday = (int) date('N', strtotime($date));
        foreach ($subjects as [$code, , , $start, $weekdays]) {
            // Today always gets a CS101 session so the dashboard has live numbers.
            $held = $daysAgo === 0 ? $code === 'CS101' : in_array($weekday, $weekdays, true);
            if (!$held) {
                continue;
            }
            $startTs = strtotime("$date $start");
            foreach ($rosters[$code] as $i) {
                [, , , , $pAbsent, $pLate] = $students[$i];
                $roll    = mt_rand() / mt_getrandmax();
                $remarks = null;
                if ($roll < $pAbsent) {
                    $status = 'Absent';
                    $ts     = $startTs + 15 * 60;
                    if (mt_rand(1, 4) === 1) {
                        $remarks = 'Excused (medical)';
                    }
                } elseif ($roll < $pAbsent + $pLate) {
                    $status = 'Late';
                    $ts     = $startTs + mt_rand(5, 25) * 60;
                } else {
                    $status = 'Present';
                    $ts     = $startTs - mt_rand(0, 10) * 60;
                }
                $stamp = date('Y-m-d H:i:s', $ts);
                $mark->execute([$studentIds[$i], $subjectIds[$code], $teacherId, $date, date('H:i:s', $ts),
                    $status, $remarks, $teacherId, $stamp, $stamp]);
            }
        }
    }
}

// --- Request handling ------------------------------------------------------

$pdo         = null;
$serverError = null;
try {
    $pdo = server_connection();
} catch (PDOException $e) {
    $serverError = $e->getMessage();
}

$state     = $pdo ? install_state($pdo) : ['database' => false, 'tables' => false, 'admin' => false];
$errors    = [];
$installed = false;
$form      = ['full_name' => '', 'username' => 'admin', 'email' => '', 'sample' => true];

if ($pdo && !$state['admin'] && is_post()) {
    verify_csrf();
    $form = [
        'full_name' => clean_text(input('full_name'), 100),
        'username'  => input('username'),
        'email'     => clean_text(input('email'), 120),
        'sample'    => isset($_POST['sample']),
    ];
    $password = (string) ($_POST['password'] ?? '');
    $confirm  = (string) ($_POST['password_confirm'] ?? '');

    if ($form['full_name'] === '') {
        $errors['full_name'] = 'Enter your full name.';
    }
    if (!preg_match('/^[A-Za-z0-9._-]{3,50}$/', $form['username'])) {
        $errors['username'] = 'Use 3–50 letters, numbers, dots, dashes or underscores.';
    }
    if ($form['email'] !== '' && !filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Enter a valid email address or leave it blank.';
    }
    if (strlen($password) < 8) {
        $errors['password'] = 'Use at least 8 characters.';
    } elseif ($password !== $confirm) {
        $errors['password_confirm'] = 'The passwords do not match.';
    }

    if (!$errors) {
        run_schema($pdo);
        $state = install_state($pdo);
        if (!$state['admin']) {
            $pdo->prepare("INSERT INTO users (username, password_hash, full_name, email, role) VALUES (?, ?, ?, ?, 'admin')")
                ->execute([$form['username'], password_hash($password, PASSWORD_DEFAULT), $form['full_name'], $form['email'] ?: null]);
            if ($form['sample']) {
                seed_sample_data($pdo, (int) $pdo->lastInsertId());
            }
        }
        $state     = install_state($pdo);
        $installed = true;
    }
}

// Once set up, the installer is hidden: visitors are sent to the home page instead.
if ($state['admin'] && !$installed) {
    redirect('index.php');
}

$steps = [
    ['MySQL server', $pdo !== null, $pdo ? 'Connected to ' . DB_HOST . ':' . DB_PORT : 'Not reachable'],
    ['Database "' . DB_NAME . '"', $state['database'], $state['database'] ? 'Exists' : 'Will be created'],
    ['Tables', $state['tables'], $state['tables'] ? 'Ready' : 'Will be created'],
    ['Administrator account', $state['admin'], $state['admin'] ? 'Created' : 'Create one below'],
];
?><!doctype html>
<html lang="en"<?= theme_attr() ?>>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Install · <?= e(APP_SHORT_NAME) ?></title>
    <link rel="icon" href="assets/img/favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="assets/css/style.css">
    <?= theme_script() ?>
</head>
<body class="auth-body">
<main class="center-card wide">
    <div class="card card-pad">
        <div class="install-head">
            <span class="brand-mark lg"><?= icon('check') ?></span>
            <div>
                <h1 class="h2">Set up <?= e(APP_SHORT_NAME) ?></h1>
                <p class="muted"><?= e(APP_NAME) ?></p>
            </div>
        </div>

        <ul class="checklist">
            <?php foreach ($steps as [$label, $ok, $note]): ?>
                <li class="<?= $ok ? 'ok' : 'todo' ?>">
                    <span class="check-dot"><?= $ok ? icon('check') : '' ?></span>
                    <span class="check-label"><?= e($label) ?></span>
                    <span class="muted"><?= e($note) ?></span>
                </li>
            <?php endforeach; ?>
        </ul>

        <?php if ($serverError !== null): ?>
            <div class="alert alert-error"><?= icon('alert') ?><span>
                Could not connect to MySQL: <?= e($serverError) ?><br>
                Open the <strong>XAMPP Control Panel</strong>, click <strong>Start</strong> next to MySQL, then reload this page.
                If you changed the MySQL password, update <code>config.php</code>.
            </span></div>
            <a class="btn btn-primary" href="<?= e(page('install.php')) ?>">Try again</a>

        <?php elseif ($state['admin']): ?>
            <div class="alert alert-success"><?= icon('check') ?><span>
                <?= $installed ? 'Installation complete. Your administrator account is ready.' : 'The system is already installed.' ?>
                <?= $installed && $form['sample'] ? ' Sample classes, students and attendance were added so you can explore the reports.' : '' ?>
            </span></div>
            <div class="btn-row">
                <a class="btn btn-primary" href="<?= e(page('admin/login.php')) ?>"><?= icon('lock') ?> Go to admin login</a>
                <a class="btn btn-light" href="<?= e(page('index.php')) ?>"><?= icon('home') ?> View website</a>
            </div>
            <p class="muted small">This installer cannot create more administrators now that one exists. Add staff from <em>Staff Accounts</em> in the admin panel.</p>

        <?php else: ?>
            <h2 class="h3">Create the administrator account</h2>
            <p class="muted small">This account can manage students, classes, staff and all attendance records.</p>
            <form method="post" class="form-grid" novalidate>
                <?= csrf_field() ?>
                <div class="field span-2">
                    <label for="full_name">Full name</label>
                    <input id="full_name" name="full_name" value="<?= e($form['full_name']) ?>" required maxlength="100" autofocus>
                    <?php if (isset($errors['full_name'])): ?><p class="field-error"><?= e($errors['full_name']) ?></p><?php endif; ?>
                </div>
                <div class="field">
                    <label for="username">Username</label>
                    <input id="username" name="username" value="<?= e($form['username']) ?>" required maxlength="50" autocomplete="username">
                    <?php if (isset($errors['username'])): ?><p class="field-error"><?= e($errors['username']) ?></p><?php endif; ?>
                </div>
                <div class="field">
                    <label for="email">Email <span class="muted">(optional)</span></label>
                    <input id="email" name="email" type="email" value="<?= e($form['email']) ?>" maxlength="120">
                    <?php if (isset($errors['email'])): ?><p class="field-error"><?= e($errors['email']) ?></p><?php endif; ?>
                </div>
                <div class="field">
                    <label for="password">Password</label>
                    <input id="password" name="password" type="password" required minlength="8" autocomplete="new-password">
                    <?php if (isset($errors['password'])): ?><p class="field-error"><?= e($errors['password']) ?></p><?php endif; ?>
                </div>
                <div class="field">
                    <label for="password_confirm">Confirm password</label>
                    <input id="password_confirm" name="password_confirm" type="password" required minlength="8" autocomplete="new-password">
                    <?php if (isset($errors['password_confirm'])): ?><p class="field-error"><?= e($errors['password_confirm']) ?></p><?php endif; ?>
                </div>
                <label class="check-row span-2">
                    <input type="checkbox" name="sample" value="1"<?= checked($form['sample']) ?>>
                    <span><strong>Add sample data</strong><br><span class="muted small">3 classes, 15 students and three weeks of attendance, assigned to you. Delete them any time.</span></span>
                </label>
                <div class="span-2">
                    <button type="submit" class="btn btn-primary btn-block"><?= icon('database') ?> Install</button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</main>
</body>
</html>

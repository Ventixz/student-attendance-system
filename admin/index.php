<?php
require __DIR__ . '/../includes/bootstrap.php';
$me = require_login();

$today = today();
$ids   = scoped_subject_ids();
$in    = in_list($ids);

// Headline numbers
if (is_admin()) {
    $totalStudents = (int) db()->query('SELECT COUNT(*) FROM students')->fetchColumn();
} else {
    $stmt = db()->prepare("SELECT COUNT(DISTINCT student_id) FROM enrollments WHERE subject_id IN ($in)");
    $stmt->execute($ids);
    $totalStudents = (int) $stmt->fetchColumn();
}

$stmt = db()->prepare("SELECT status, COUNT(*) FROM attendance WHERE attendance_date = ? AND subject_id IN ($in) GROUP BY status");
$stmt->execute(array_merge([$today], $ids));
$todayCounts = array_fill_keys(STATUSES, 0);
foreach ($stmt->fetchAll(PDO::FETCH_KEY_PAIR) as $status => $count) {
    $todayCounts[$status] = (int) $count;
}
$todayTotal = array_sum($todayCounts);
$todayPct   = attendance_pct($todayCounts['Present'], $todayCounts['Late'], $todayTotal);

// Last 7 days, stacked by status
$weekStart = date('Y-m-d', strtotime('-6 days'));
$stmt = db()->prepare("SELECT attendance_date,
        SUM(status = 'Present') AS present, SUM(status = 'Late') AS late, SUM(status = 'Absent') AS absent
    FROM attendance
    WHERE attendance_date BETWEEN ? AND ? AND subject_id IN ($in)
    GROUP BY attendance_date");
$stmt->execute(array_merge([$weekStart, $today], $ids));
$byDay = [];
foreach ($stmt as $row) {
    $byDay[$row['attendance_date']] = $row;
}
$week    = [];
$weekMax = 0;
for ($i = 6; $i >= 0; $i--) {
    $d   = date('Y-m-d', strtotime("-{$i} days"));
    $row = $byDay[$d] ?? ['present' => 0, 'late' => 0, 'absent' => 0];
    $day = ['date' => $d, 'present' => (int) $row['present'], 'late' => (int) $row['late'], 'absent' => (int) $row['absent']];
    $day['total'] = $day['present'] + $day['late'] + $day['absent'];
    $weekMax      = max($weekMax, $day['total']);
    $week[]       = $day;
}

// Today's classes and how many students have been marked
$stmt = db()->prepare("SELECT sub.id, sub.code, sub.name, sub.schedule, u.full_name AS teacher,
        (SELECT COUNT(*) FROM enrollments en WHERE en.subject_id = sub.id) AS enrolled,
        (SELECT COUNT(*) FROM attendance a WHERE a.subject_id = sub.id AND a.attendance_date = ?) AS marked
    FROM subjects sub
    LEFT JOIN users u ON u.id = sub.teacher_id
    WHERE sub.id IN ($in)
    ORDER BY sub.code");
$stmt->execute(array_merge([$today], $ids));
$classes = $stmt->fetchAll();

// Students below the attendance threshold (all-time, within the user's classes)
$attended = attended_sql('a.status');
$stmt = db()->prepare("SELECT st.id, st.student_no, st.first_name, st.last_name, st.section,
        COUNT(*) AS total, SUM(a.status = 'Present') AS present, SUM(a.status = 'Late') AS late, SUM(a.status = 'Absent') AS absent
    FROM attendance a
    JOIN students st ON st.id = a.student_id
    WHERE a.subject_id IN ($in)
    GROUP BY st.id, st.student_no, st.first_name, st.last_name, st.section
    HAVING $attended * 100 / COUNT(*) < ?
    ORDER BY $attended / COUNT(*) ASC, st.last_name
    LIMIT 8");
$stmt->execute(array_merge($ids, [LOW_ATTENDANCE_THRESHOLD]));
$lowStudents = $stmt->fetchAll();

// Latest activity
$stmt = db()->prepare("SELECT a.status, a.attendance_date, a.attendance_time, a.updated_at,
        st.id AS student_id, st.first_name, st.last_name, st.student_no, sub.code
    FROM attendance a
    JOIN students st ON st.id = a.student_id
    JOIN subjects sub ON sub.id = a.subject_id
    WHERE a.subject_id IN ($in)
    ORDER BY a.updated_at DESC, a.id DESC
    LIMIT 8");
$stmt->execute($ids);
$recent = $stmt->fetchAll();

$hour      = (int) date('G');
$greeting  = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');
$firstName = explode(' ', $me['full_name'])[0];

$pageTitle = 'Dashboard';
$activeNav = 'dashboard';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-actions">
    <div class="page-intro">
        <h2><?= e($greeting . ', ' . $firstName) ?></h2>
        <p><?= is_admin() ? 'Here is attendance across the whole school today.' : 'Here is attendance for your classes today.' ?></p>
    </div>
    <div class="btn-row">
        <a class="btn btn-light" href="<?= e(page('report_daily.php')) ?>"><?= icon('calendar') ?> Today's record</a>
        <a class="btn btn-primary" href="<?= e(page('attendance.php')) ?>"><?= icon('check') ?> Take attendance</a>
    </div>
</div>

<div class="stats">
    <div class="stat"><div class="stat-icon"><?= icon('users') ?></div><div><div class="stat-label">Students</div><div class="stat-value"><?= $totalStudents ?></div><div class="stat-sub"><?= count($ids) ?> class<?= count($ids) === 1 ? '' : 'es' ?></div></div></div>
    <div class="stat present"><div class="stat-icon"><?= icon('user-check') ?></div><div><div class="stat-label">Present today</div><div class="stat-value"><?= $todayCounts['Present'] ?></div><div class="stat-sub"><a href="<?= e(page('report_status.php')) ?>?status=Present">View list</a></div></div></div>
    <div class="stat late"><div class="stat-icon"><?= icon('clock') ?></div><div><div class="stat-label">Late today</div><div class="stat-value"><?= $todayCounts['Late'] ?></div><div class="stat-sub"><a href="<?= e(page('report_status.php')) ?>?status=Late">View list</a></div></div></div>
    <div class="stat absent"><div class="stat-icon"><?= icon('user-x') ?></div><div><div class="stat-label">Absent today</div><div class="stat-value"><?= $todayCounts['Absent'] ?></div><div class="stat-sub"><a href="<?= e(page('report_status.php')) ?>?status=Absent">View list</a></div></div></div>
    <div class="stat info"><div class="stat-icon"><?= icon('pie') ?></div><div><div class="stat-label">Today's attendance</div><div class="stat-value"><?= e(pct_label($todayPct)) ?></div><div class="stat-sub"><?= $todayTotal ?> mark<?= $todayTotal === 1 ? '' : 's' ?> recorded</div></div></div>
</div>

<div class="grid-main">
    <div class="card">
        <div class="card-head">
            <div><h3>Last 7 days</h3><p>Attendance marks per day</p></div>
            <div class="legend">
                <span><i style="background:var(--present-solid)"></i>Present</span>
                <span><i style="background:var(--late-solid)"></i>Late</span>
                <span><i style="background:var(--absent-solid)"></i>Absent</span>
            </div>
        </div>
        <div class="card-body">
            <div class="chart" role="img" aria-label="Attendance for the last seven days">
                <?php foreach ($week as $day):
                    $height = $weekMax ? max(3, round($day['total'] / $weekMax * 100)) : 3;
                    $label  = date('D', strtotime($day['date']));
                    $tip    = fmt_date($day['date']) . ": {$day['present']} present, {$day['late']} late, {$day['absent']} absent";
                ?>
                    <div class="chart-col" title="<?= e($tip) ?>">
                        <span class="chart-total"><?= $day['total'] ?: '' ?></span>
                        <div class="chart-plot">
                            <div class="chart-bar" style="height:<?= $height ?>%">
                                <span class="b-absent" style="flex-grow:<?= $day['absent'] ?>"></span>
                                <span class="b-late" style="flex-grow:<?= $day['late'] ?>"></span>
                                <span class="b-present" style="flex-grow:<?= $day['present'] ?>"></span>
                            </div>
                        </div>
                        <span class="chart-label<?= $day['date'] === $today ? ' today' : '' ?>"><?= e($day['date'] === $today ? 'Today' : $label) ?><br><?= e(date('M j', strtotime($day['date']))) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-head"><div><h3>Today's classes</h3><p>Students marked so far</p></div></div>
        <?php if (!$classes): ?>
            <div class="empty"><?= icon('book') ?>
                <h3>No classes yet</h3>
                <p><?= is_admin() ? 'Create a class and enroll students to start.' : 'No classes are assigned to you yet.' ?></p>
                <?php if (is_admin()): ?><a class="btn btn-primary btn-sm" href="<?= e(page('subjects.php')) ?>"><?= icon('plus') ?> Add a class</a><?php endif; ?>
            </div>
        <?php else: ?>
            <ul class="class-list">
                <?php foreach ($classes as $c): $done = $c['enrolled'] > 0 && $c['marked'] >= $c['enrolled']; ?>
                    <li>
                        <div class="class-info">
                            <strong><?= e($c['code'] . ' — ' . $c['name']) ?></strong>
                            <span><?= e($c['schedule'] ?: 'No schedule set') ?> · <?= e($c['teacher'] ?? 'No instructor') ?></span>
                        </div>
                        <span class="progress-ring<?= $done ? ' done' : '' ?>"><?= (int) $c['marked'] ?>/<?= (int) $c['enrolled'] ?></span>
                        <a class="btn btn-light btn-sm" href="<?= e(page('attendance.php')) ?>?subject_id=<?= (int) $c['id'] ?>"><?= $done ? 'Review' : 'Mark' ?></a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>

<div class="grid-2">
    <div class="card">
        <div class="card-head">
            <div><h3>Students needing attention</h3><p>Below <?= (int) LOW_ATTENDANCE_THRESHOLD ?>% attendance overall</p></div>
            <a class="btn btn-ghost btn-sm" href="<?= e(page('report_summary.php')) ?>?sort=pct_asc">Full summary <?= icon('arrow-right') ?></a>
        </div>
        <?php if (!$lowStudents): ?>
            <div class="empty"><?= icon('user-check') ?><h3>Everyone is on track</h3><p>No student is below <?= (int) LOW_ATTENDANCE_THRESHOLD ?>%.</p></div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Student</th><th class="num">Absent</th><th>Attendance</th></tr></thead>
                    <tbody>
                    <?php foreach ($lowStudents as $s): ?>
                        <tr>
                            <td><a class="cell-strong" href="<?= e(page('report_student.php')) ?>?id=<?= (int) $s['id'] ?>"><?= e(student_name($s)) ?></a><span class="cell-sub"><?= e($s['student_no']) ?><?= $s['section'] ? ' · ' . e($s['section']) : '' ?></span></td>
                            <td class="num"><?= (int) $s['absent'] ?> / <?= (int) $s['total'] ?></td>
                            <td><?= pct_bar(attendance_pct((int) $s['present'], (int) $s['late'], (int) $s['total'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="card-head">
            <div><h3>Recent activity</h3><p>Latest attendance marks</p></div>
            <a class="btn btn-ghost btn-sm" href="<?= e(page('records.php')) ?>">All records <?= icon('arrow-right') ?></a>
        </div>
        <?php if (!$recent): ?>
            <div class="empty"><?= icon('list') ?><h3>No attendance yet</h3><p>Marks will appear here as they're recorded.</p></div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Student</th><th>Class</th><th>Status</th><th>When</th></tr></thead>
                    <tbody>
                    <?php foreach ($recent as $r): ?>
                        <tr>
                            <td><a class="cell-strong" href="<?= e(page('report_student.php')) ?>?id=<?= (int) $r['student_id'] ?>"><?= e(student_name($r)) ?></a><span class="cell-sub"><?= e($r['student_no']) ?></span></td>
                            <td><?= e($r['code']) ?></td>
                            <td><?= status_badge($r['status']) ?></td>
                            <td class="nowrap"><?= e(fmt_date($r['attendance_date'])) ?><span class="cell-sub"><?= e(fmt_time($r['attendance_time'])) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>

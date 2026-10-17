<?php
/**
 * Public student lookup: Student ID + last name shows that student's attendance summary.
 */
require __DIR__ . '/includes/bootstrap.php';

$studentNo = clean_text(input('student_no'), 30);
$lastName  = clean_text(input('last_name'), 60);
$student   = null;
$error     = null;
$bySubject = [];
$recent    = [];
$totals    = ['present' => 0, 'late' => 0, 'absent' => 0, 'total' => 0];

if ($studentNo !== '' || $lastName !== '') {
    if ($studentNo === '' || $lastName === '') {
        $error = 'Enter both your Student ID and your last name.';
    } else {
        // The column collation is case-insensitive, so "reyes" matches "Reyes".
        $stmt = db()->prepare('SELECT * FROM students WHERE student_no = ? AND last_name = ?');
        $stmt->execute([$studentNo, $lastName]);
        $student = $stmt->fetch() ?: null;
        if (!$student) {
            $error = "We couldn't find a student with that ID and last name. Check the spelling or ask your teacher.";
        }
    }
}

if ($student) {
    $sid  = (int) $student['id'];
    $stmt = db()->prepare("SELECT sub.code, sub.name, u.full_name AS teacher,
            COUNT(a.id) AS total,
            SUM(a.status = 'Present') AS present,
            SUM(a.status = 'Late') AS late,
            SUM(a.status = 'Absent') AS absent
        FROM subjects sub
        LEFT JOIN users u ON u.id = sub.teacher_id
        LEFT JOIN attendance a ON a.subject_id = sub.id AND a.student_id = ?
        WHERE sub.id IN (SELECT subject_id FROM enrollments WHERE student_id = ?
                         UNION SELECT subject_id FROM attendance WHERE student_id = ?)
        GROUP BY sub.id, sub.code, sub.name, u.full_name
        ORDER BY sub.code");
    $stmt->execute([$sid, $sid, $sid]);
    $bySubject = $stmt->fetchAll();
    foreach ($bySubject as $row) {
        foreach ($totals as $key => $_) {
            $totals[$key] += (int) $row[$key];
        }
    }

    $stmt = db()->prepare('SELECT a.attendance_date, a.attendance_time, a.status, sub.code, sub.name
        FROM attendance a JOIN subjects sub ON sub.id = a.subject_id
        WHERE a.student_id = ?
        ORDER BY a.attendance_date DESC, a.attendance_time DESC
        LIMIT 15');
    $stmt->execute([$sid]);
    $recent = $stmt->fetchAll();
}

$overall   = attendance_pct($totals['present'], $totals['late'], $totals['total']);
$pageTitle = 'Check attendance · ' . APP_SHORT_NAME;
require __DIR__ . '/includes/site_header.php';
?>
<section class="result-wrap">
    <div class="site-wrap">
        <?php if (!$student): ?>
            <div class="center-card" style="padding-top:0">
                <div class="lookup-card">
                    <h2>Check your attendance</h2>
                    <p>Enter your Student ID and last name to see your attendance summary.</p>
                    <?php if ($error): ?>
                        <div class="alert alert-error"><?= icon('alert') ?><span><?= e($error) ?></span></div>
                    <?php endif; ?>
                    <form class="form-stack" method="get">
                        <div class="field">
                            <label for="sid">Student ID</label>
                            <input id="sid" name="student_no" value="<?= e($studentNo) ?>" placeholder="e.g. 2026-0001" required maxlength="30" autocomplete="off" autofocus>
                        </div>
                        <div class="field">
                            <label for="lname">Last name</label>
                            <input id="lname" name="last_name" value="<?= e($lastName) ?>" required maxlength="60">
                        </div>
                        <button class="btn btn-primary btn-block" type="submit"><?= icon('search') ?> View my attendance</button>
                    </form>
                    <p class="lookup-note"><?= icon('lock') ?> Your last name is used to confirm it's you.</p>
                </div>
            </div>
        <?php else: ?>
            <div class="page-actions">
                <a class="btn btn-light btn-sm" href="<?= e(page('check.php')) ?>"><?= icon('arrow-left') ?> Look up another student</a>
                <button type="button" class="btn btn-light btn-sm" data-print><?= icon('printer') ?> Print</button>
            </div>

            <div class="card profile">
                <span class="avatar"><?= e(initials($student['first_name'] . ' ' . $student['last_name'])) ?></span>
                <div>
                    <h2><?= e($student['first_name'] . ' ' . $student['last_name']) ?></h2>
                    <div class="meta">
                        <span>Student ID <strong class="mono"><?= e($student['student_no']) ?></strong></span>
                        <?php if ($student['section']): ?><span><?= e($student['section']) ?></span><?php endif; ?>
                        <span><?= count($bySubject) ?> class<?= count($bySubject) === 1 ? '' : 'es' ?></span>
                    </div>
                </div>
                <div class="big-pct">
                    <strong><?= e(pct_label($overall)) ?></strong>
                    <span class="muted small">Overall attendance</span>
                </div>
            </div>

            <div class="stats">
                <div class="stat info"><div class="stat-icon"><?= icon('calendar') ?></div><div><div class="stat-label">Sessions recorded</div><div class="stat-value"><?= $totals['total'] ?></div></div></div>
                <div class="stat present"><div class="stat-icon"><?= icon('user-check') ?></div><div><div class="stat-label">Present</div><div class="stat-value"><?= $totals['present'] ?></div></div></div>
                <div class="stat late"><div class="stat-icon"><?= icon('clock') ?></div><div><div class="stat-label">Late</div><div class="stat-value"><?= $totals['late'] ?></div></div></div>
                <div class="stat absent"><div class="stat-icon"><?= icon('user-x') ?></div><div><div class="stat-label">Absent</div><div class="stat-value"><?= $totals['absent'] ?></div></div></div>
            </div>

            <div class="card">
                <div class="card-head">
                    <div><h3>Attendance by class</h3><p><?= LATE_COUNTS_AS_PRESENT ? 'Late arrivals count as attended.' : 'Only Present counts as attended.' ?></p></div>
                </div>
                <?php if (!$bySubject): ?>
                    <div class="empty"><?= icon('book') ?><h3>No classes yet</h3><p>You are not enrolled in any class yet.</p></div>
                <?php else: ?>
                    <div class="table-wrap">
                        <table class="table">
                            <thead><tr><th>Class</th><th>Instructor</th><th class="num">Present</th><th class="num">Late</th><th class="num">Absent</th><th class="num">Total</th><th>Attendance</th></tr></thead>
                            <tbody>
                            <?php foreach ($bySubject as $row): $pct = attendance_pct((int) $row['present'], (int) $row['late'], (int) $row['total']); ?>
                                <tr>
                                    <td><span class="cell-strong"><?= e($row['code']) ?></span><span class="cell-sub"><?= e($row['name']) ?></span></td>
                                    <td><?= e($row['teacher'] ?? '—') ?></td>
                                    <td class="num"><?= (int) $row['present'] ?></td>
                                    <td class="num"><?= (int) $row['late'] ?></td>
                                    <td class="num"><?= (int) $row['absent'] ?></td>
                                    <td class="num"><?= (int) $row['total'] ?></td>
                                    <td><?= pct_bar($pct) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <div class="card">
                <div class="card-head"><div><h3>Recent attendance</h3><p>Your latest 15 records</p></div></div>
                <?php if (!$recent): ?>
                    <div class="empty"><?= icon('calendar') ?><h3>No attendance recorded yet</h3></div>
                <?php else: ?>
                    <div class="table-wrap">
                        <table class="table">
                            <thead><tr><th>Date</th><th>Class</th><th>Status</th><th>Time</th></tr></thead>
                            <tbody>
                            <?php foreach ($recent as $r): ?>
                                <tr>
                                    <td class="nowrap"><?= e(date('D, M j, Y', strtotime($r['attendance_date']))) ?></td>
                                    <td><span class="cell-strong"><?= e($r['code']) ?></span><span class="cell-sub"><?= e($r['name']) ?></span></td>
                                    <td><?= status_badge($r['status']) ?></td>
                                    <td class="nowrap"><?= e(fmt_time($r['attendance_time'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php require __DIR__ . '/includes/site_footer.php'; ?>

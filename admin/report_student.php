<?php
/**
 * Student Attendance Report: one student's totals, per-class percentages and full history.
 */
require __DIR__ . '/../includes/bootstrap.php';
$me = require_login();

$subjects  = accessible_subjects();
$students  = accessible_students();
$studentId = (int) query_param('id');
$lookup    = query_param('student');
$from      = query_param('from');
$to        = query_param('to');
$from      = valid_date($from) ? $from : '';
$to        = valid_date($to) ? $to : '';
if ($from !== '' && $to !== '' && $from > $to) {
    [$from, $to] = [$to, $from];
}
$subjectId = (int) query_param('subject_id');
$matches   = [];
$error     = null;

// Resolve a typed Student ID or name to one student.
if ($lookup !== '') {
    $needle = mb_strtolower($lookup);
    foreach ($students as $s) {
        if (mb_strtolower($s['student_no']) === $needle) {
            $matches = [$s];
            break;
        }
        $hay = mb_strtolower($s['first_name'] . ' ' . $s['last_name'] . ' ' . student_name($s));
        if (strpos($hay, $needle) !== false) {
            $matches[] = $s;
        }
    }
    if (count($matches) === 1) {
        redirect('report_student.php' . query_with(['id' => $matches[0]['id'], 'student' => null]));
    }
    if (!$matches) {
        $error = 'No student matches “' . $lookup . '”.';
    }
}

$student = null;
if ($studentId) {
    $stmt = db()->prepare('SELECT * FROM students WHERE id = ?');
    $stmt->execute([$studentId]);
    $student = $stmt->fetch() ?: null;
    if (!$student || !can_view_student($studentId)) {
        $student = null;
        $error   = 'That student could not be found in your classes.';
    }
}

$breakdown = [];
$history   = [];
$totals    = ['present' => 0, 'late' => 0, 'absent' => 0, 'total' => 0];
if ($student) {
    $ids       = scoped_subject_ids($subjectId);
    $in        = in_list($ids);
    $dateSql   = '';
    $dateParam = [];
    if ($from !== '') {
        $dateSql    .= ' AND a.attendance_date >= ?';
        $dateParam[] = $from;
    }
    if ($to !== '') {
        $dateSql    .= ' AND a.attendance_date <= ?';
        $dateParam[] = $to;
    }

    $stmt = db()->prepare("SELECT sub.id, sub.code, sub.name, u.full_name AS teacher,
            COUNT(a.id) AS total, SUM(a.status = 'Present') AS present, SUM(a.status = 'Late') AS late, SUM(a.status = 'Absent') AS absent
        FROM subjects sub
        LEFT JOIN users u ON u.id = sub.teacher_id
        LEFT JOIN attendance a ON a.subject_id = sub.id AND a.student_id = ? $dateSql
        WHERE sub.id IN ($in)
          AND sub.id IN (SELECT subject_id FROM enrollments WHERE student_id = ? UNION SELECT subject_id FROM attendance WHERE student_id = ?)
        GROUP BY sub.id, sub.code, sub.name, u.full_name
        ORDER BY sub.code");
    $stmt->execute(array_merge([$studentId], $dateParam, $ids, [$studentId, $studentId]));
    $breakdown = $stmt->fetchAll();
    foreach ($breakdown as $b) {
        foreach ($totals as $k => $_) {
            $totals[$k] += (int) $b[$k];
        }
    }

    $stmt = db()->prepare("SELECT a.*, sub.code, sub.name AS subject_name, t.full_name AS teacher
        FROM attendance a
        JOIN subjects sub ON sub.id = a.subject_id
        LEFT JOIN users t ON t.id = a.teacher_id
        WHERE a.student_id = ? AND a.subject_id IN ($in) $dateSql
        ORDER BY a.attendance_date DESC, a.attendance_time DESC");
    $stmt->execute(array_merge([$studentId], $ids, $dateParam));
    $history = $stmt->fetchAll();

    if (query_param('export') === 'csv') {
        send_csv('attendance-report-' . $student['student_no'] . '.csv',
            ['Student ID', 'Student Name', 'Date', 'Day', 'Time', 'Class Code', 'Class', 'Status', 'Instructor', 'Remarks'],
            array_map(function ($h) use ($student) {
                return [$student['student_no'], student_name($student), $h['attendance_date'], date('l', strtotime($h['attendance_date'])),
                    substr($h['attendance_time'], 0, 5), $h['code'], $h['subject_name'], $h['status'], $h['teacher'], $h['remarks']];
            }, $history));
    }
}
$overall = attendance_pct($totals['present'], $totals['late'], $totals['total']);

$pageTitle = 'Student Attendance Report';
$activeNav = 'student';
require __DIR__ . '/../includes/header.php';
?>
<div class="card">
    <form class="filters" method="get">
        <div class="field grow">
            <label for="student">Student</label>
            <input id="student" name="student" list="student-list" value="<?= e($lookup !== '' ? $lookup : ($student['student_no'] ?? '')) ?>" placeholder="Type a Student ID or name" autocomplete="off" required>
            <datalist id="student-list">
                <?php foreach ($students as $s): ?><option value="<?= e($s['student_no']) ?>"><?= e(student_name($s)) ?></option><?php endforeach; ?>
            </datalist>
        </div>
        <div class="field narrow"><label for="from">From <span class="muted">(optional)</span></label><input type="date" id="from" name="from" value="<?= e($from) ?>"></div>
        <div class="field narrow"><label for="to">To <span class="muted">(optional)</span></label><input type="date" id="to" name="to" value="<?= e($to) ?>"></div>
        <div class="field"><label for="subject_id">Class</label><select id="subject_id" name="subject_id"><?= subject_options($subjects, $subjectId, 'All classes') ?></select></div>
        <div class="filter-actions"><button class="btn btn-primary" type="submit"><?= icon('search') ?> View report</button></div>
    </form>
</div>

<?php if ($error): ?>
    <div class="alert alert-error" style="margin-top:20px"><?= icon('alert') ?><span><?= e($error) ?></span></div>
<?php endif; ?>

<?php if (count($matches) > 1): ?>
    <div class="card" style="margin-top:20px">
        <div class="card-head"><div><h3><?= count($matches) ?> students match “<?= e($lookup) ?>”</h3><p>Choose one.</p></div></div>
        <ul class="class-list">
            <?php foreach ($matches as $m): ?>
                <li><div class="class-info"><strong><?= e(student_name($m)) ?></strong><span><?= e($m['student_no']) ?><?= $m['section'] ? ' · ' . e($m['section']) : '' ?></span></div>
                    <a class="btn btn-light btn-sm" href="<?= e(query_with(['id' => $m['id'], 'student' => null])) ?>">View report</a></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php elseif (!$student && !$error): ?>
    <div class="card" style="margin-top:20px">
        <div class="empty"><?= icon('file') ?><h3>Choose a student</h3><p>Type a Student ID or name above to see their attendance report.</p></div>
    </div>
<?php endif; ?>

<?php if ($student): ?>
    <div class="page-actions no-print" style="margin:20px 0 0">
        <span class="muted small"><?= e(range_label($from, $to)) ?><?= $subjectId && find_accessible_subject($subjectId) ? ' · ' . e(find_accessible_subject($subjectId)['code']) : '' ?></span>
        <div class="btn-row">
            <button type="button" class="btn btn-light btn-sm" data-print><?= icon('printer') ?> Print</button>
            <a class="btn btn-light btn-sm" href="<?= e(query_with(['export' => 'csv'])) ?>"><?= icon('download') ?> Export CSV</a>
        </div>
    </div>

    <?= print_header('Student Attendance Report', student_name($student) . ' (' . $student['student_no'] . ') · ' . range_label($from, $to)) ?>

    <div class="card profile" style="margin-top:16px">
        <span class="avatar"><?= e(initials($student['first_name'] . ' ' . $student['last_name'])) ?></span>
        <div>
            <h2><?= e($student['first_name'] . ' ' . $student['last_name']) ?></h2>
            <div class="meta">
                <span>Student ID <strong class="mono"><?= e($student['student_no']) ?></strong></span>
                <?php if ($student['section']): ?><span><?= e($student['section']) ?></span><?php endif; ?>
                <?php if ($student['email']): ?><span><?= e($student['email']) ?></span><?php endif; ?>
            </div>
        </div>
        <div class="big-pct">
            <strong><?= e(pct_label($overall)) ?></strong>
            <?= standing_badge($overall) ?>
        </div>
    </div>

    <div class="stats" style="margin-top:20px">
        <div class="stat info"><div class="stat-icon"><?= icon('calendar') ?></div><div><div class="stat-label">Sessions</div><div class="stat-value"><?= $totals['total'] ?></div></div></div>
        <div class="stat present"><div class="stat-icon"><?= icon('user-check') ?></div><div><div class="stat-label">Present</div><div class="stat-value"><?= $totals['present'] ?></div></div></div>
        <div class="stat late"><div class="stat-icon"><?= icon('clock') ?></div><div><div class="stat-label">Late</div><div class="stat-value"><?= $totals['late'] ?></div></div></div>
        <div class="stat absent"><div class="stat-icon"><?= icon('user-x') ?></div><div><div class="stat-label">Absent</div><div class="stat-value"><?= $totals['absent'] ?></div></div></div>
    </div>

    <div class="card" style="margin-top:20px">
        <div class="card-head"><div><h3>Attendance by class</h3><p><?= LATE_COUNTS_AS_PRESENT ? 'Late counts as attended.' : 'Only Present counts as attended.' ?></p></div></div>
        <?php if (!$breakdown): ?>
            <div class="empty"><?= icon('book') ?><h3>Not enrolled in any of your classes</h3></div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Class</th><th>Instructor</th><th class="num">Present</th><th class="num">Late</th><th class="num">Absent</th><th class="num">Total</th><th>Attendance</th></tr></thead>
                    <tbody>
                    <?php foreach ($breakdown as $b): ?>
                        <tr>
                            <td><span class="cell-strong"><?= e($b['code']) ?></span><span class="cell-sub"><?= e($b['name']) ?></span></td>
                            <td><?= e($b['teacher'] ?? '—') ?></td>
                            <td class="num"><?= (int) $b['present'] ?></td>
                            <td class="num"><?= (int) $b['late'] ?></td>
                            <td class="num"><?= (int) $b['absent'] ?></td>
                            <td class="num"><?= (int) $b['total'] ?></td>
                            <td><?= pct_bar(attendance_pct((int) $b['present'], (int) $b['late'], (int) $b['total'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr><td colspan="2">Overall</td><td class="num"><?= $totals['present'] ?></td><td class="num"><?= $totals['late'] ?></td><td class="num"><?= $totals['absent'] ?></td><td class="num"><?= $totals['total'] ?></td><td><?= pct_bar($overall) ?></td></tr>
                    </tfoot>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="card-head"><div><h3>Attendance history</h3><p><?= count($history) ?> record(s)</p></div></div>
        <?php if (!$history): ?>
            <div class="empty"><?= icon('calendar') ?><h3>No attendance recorded</h3><p>Nothing recorded for <?= e(range_label($from, $to)) ?>.</p></div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Date</th><th>Time</th><th>Class</th><th>Status</th><th>Instructor</th><th>Remarks</th></tr></thead>
                    <tbody>
                    <?php foreach ($history as $h): ?>
                        <tr>
                            <td class="nowrap"><?= e(date('D, M j, Y', strtotime($h['attendance_date']))) ?></td>
                            <td class="nowrap"><?= e(fmt_time($h['attendance_time'])) ?></td>
                            <td><span class="cell-strong"><?= e($h['code']) ?></span><span class="cell-sub"><?= e($h['subject_name']) ?></span></td>
                            <td><?= status_badge($h['status']) ?></td>
                            <td><?= e($h['teacher'] ?? '—') ?></td>
                            <td class="muted"><?= e($h['remarks'] ?? '') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>

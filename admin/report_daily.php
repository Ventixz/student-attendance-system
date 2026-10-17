<?php
/**
 * Daily Attendance Record: every class for one date, including students not yet marked.
 */
require __DIR__ . '/../includes/bootstrap.php';
$me = require_login();

$subjects  = accessible_subjects();
$date      = query_param('date');
$date      = valid_date($date) ? $date : today();
$subjectId = (int) query_param('subject_id');
$showAll   = query_param('all') === '1' || $subjectId > 0;

$ids  = scoped_subject_ids($subjectId);
$in   = in_list($ids);
$stmt = db()->prepare("SELECT x.subject_id, st.id AS student_id, st.student_no, st.first_name, st.last_name, st.section,
        a.status, a.attendance_time, a.remarks, t.full_name AS teacher
    FROM (SELECT subject_id, student_id FROM enrollments WHERE subject_id IN ($in)
          UNION
          SELECT subject_id, student_id FROM attendance WHERE attendance_date = ? AND subject_id IN ($in)) x
    JOIN subjects sub ON sub.id = x.subject_id
    JOIN students st ON st.id = x.student_id
    LEFT JOIN attendance a ON a.subject_id = x.subject_id AND a.student_id = x.student_id AND a.attendance_date = ?
    LEFT JOIN users t ON t.id = a.teacher_id
    ORDER BY sub.code, st.last_name, st.first_name");
$stmt->execute(array_merge($ids, [$date], $ids, [$date]));

$subjectInfo = array_column($subjects, null, 'id');
$groups      = [];
foreach ($stmt as $row) {
    $sid = (int) $row['subject_id'];
    if (!isset($groups[$sid])) {
        $groups[$sid] = ['subject' => $subjectInfo[$sid], 'rows' => [], 'counts' => ['Present' => 0, 'Late' => 0, 'Absent' => 0, 'none' => 0], 'teacher' => null];
    }
    $groups[$sid]['rows'][] = $row;
    $groups[$sid]['counts'][$row['status'] ?: 'none']++;
    $groups[$sid]['teacher'] = $groups[$sid]['teacher'] ?: $row['teacher'];
}
// Unless asked, hide classes with no attendance taken that day (e.g. classes not held).
if (!$showAll) {
    $groups = array_filter($groups, function ($g) {
        return $g['counts']['none'] < count($g['rows']);
    });
}

$totals = ['Present' => 0, 'Late' => 0, 'Absent' => 0, 'none' => 0];
foreach ($groups as $g) {
    foreach ($g['counts'] as $k => $v) {
        $totals[$k] += $v;
    }
}
$marked = $totals['Present'] + $totals['Late'] + $totals['Absent'];

if (query_param('export') === 'csv') {
    $out = [];
    foreach ($groups as $g) {
        foreach ($g['rows'] as $r) {
            $out[] = [$date, $g['subject']['code'], $g['subject']['name'], $r['student_no'], student_name($r), $r['section'],
                $r['status'] ?: 'Not marked', $r['attendance_time'] ? substr($r['attendance_time'], 0, 5) : '',
                $r['teacher'] ?: $g['subject']['teacher_name'], $r['remarks']];
        }
    }
    send_csv("daily-attendance-{$date}.csv", ['Date', 'Class Code', 'Class', 'Student ID', 'Student Name', 'Section', 'Status', 'Time', 'Instructor', 'Remarks'], $out);
}

$prev      = date('Y-m-d', strtotime($date . ' -1 day'));
$next      = date('Y-m-d', strtotime($date . ' +1 day'));
$pageTitle = 'Daily Attendance Record';
$activeNav = 'daily';
require __DIR__ . '/../includes/header.php';
?>
<div class="card">
    <form class="filters" method="get">
        <div class="field narrow"><label for="date">Date</label><input type="date" id="date" name="date" value="<?= e($date) ?>"></div>
        <div class="field grow"><label for="subject_id">Class</label><select id="subject_id" name="subject_id"><?= subject_options($subjects, $subjectId, 'All classes') ?></select></div>
        <label class="check-row" style="flex:1 1 220px"><input type="checkbox" name="all" value="1"<?= checked(query_param('all') === '1') ?>><span class="small">Include classes with no attendance taken</span></label>
        <div class="filter-actions">
            <button class="btn btn-primary" type="submit"><?= icon('search') ?> Show</button>
        </div>
    </form>
</div>

<div class="page-actions no-print" style="margin-top:20px">
    <div class="btn-row">
        <a class="btn btn-light btn-sm" href="<?= e(query_with(['date' => $prev])) ?>"><?= icon('arrow-left') ?> Previous day</a>
        <a class="btn btn-light btn-sm" href="<?= e(query_with(['date' => today()])) ?>">Today</a>
        <a class="btn btn-light btn-sm" href="<?= e(query_with(['date' => $next])) ?>">Next day <?= icon('arrow-right') ?></a>
    </div>
    <div class="btn-row">
        <button type="button" class="btn btn-light btn-sm" data-print><?= icon('printer') ?> Print</button>
        <a class="btn btn-light btn-sm" href="<?= e(query_with(['export' => 'csv'])) ?>"><?= icon('download') ?> Export CSV</a>
    </div>
</div>

<?= print_header('Daily Attendance Record', fmt_day($date) . ($subjectId && isset($subjectInfo[$subjectId]) ? ' · ' . $subjectInfo[$subjectId]['code'] : '')) ?>

<div class="stats">
    <div class="stat"><div class="stat-icon"><?= icon('calendar') ?></div><div><div class="stat-label"><?= e(date('D, M j', strtotime($date))) ?></div><div class="stat-value"><?= count($groups) ?></div><div class="stat-sub">class<?= count($groups) === 1 ? '' : 'es' ?></div></div></div>
    <div class="stat present"><div class="stat-icon"><?= icon('user-check') ?></div><div><div class="stat-label">Present</div><div class="stat-value"><?= $totals['Present'] ?></div></div></div>
    <div class="stat late"><div class="stat-icon"><?= icon('clock') ?></div><div><div class="stat-label">Late</div><div class="stat-value"><?= $totals['Late'] ?></div></div></div>
    <div class="stat absent"><div class="stat-icon"><?= icon('user-x') ?></div><div><div class="stat-label">Absent</div><div class="stat-value"><?= $totals['Absent'] ?></div></div></div>
    <div class="stat info"><div class="stat-icon"><?= icon('pie') ?></div><div><div class="stat-label">Attendance</div><div class="stat-value"><?= e(pct_label(attendance_pct($totals['Present'], $totals['Late'], $marked))) ?></div><div class="stat-sub"><?= $totals['none'] ?> not marked</div></div></div>
</div>

<?php if (!$groups): ?>
    <div class="card" style="margin-top:20px">
        <div class="empty"><?= icon('calendar') ?>
            <h3>No attendance recorded on <?= e(fmt_date($date)) ?></h3>
            <p>Pick another date, or tick “Include classes with no attendance taken” to see full class lists.</p>
            <a class="btn btn-primary no-print" href="<?= e(page('attendance.php')) ?>?date=<?= e($date) ?>"><?= icon('check') ?> Take attendance</a>
        </div>
    </div>
<?php endif; ?>

<?php foreach ($groups as $sid => $g): $c = $g['counts']; ?>
    <div class="card" style="margin-top:20px">
        <div class="card-head">
            <div>
                <h3><?= e($g['subject']['code'] . ' — ' . $g['subject']['name']) ?></h3>
                <p>Instructor: <?= e($g['teacher'] ?: ($g['subject']['teacher_name'] ?: 'Not assigned')) ?><?= $g['subject']['schedule'] ? ' · ' . e($g['subject']['schedule']) : '' ?></p>
            </div>
            <div class="chips">
                <span class="chip present">Present <b><?= $c['Present'] ?></b></span>
                <span class="chip late">Late <b><?= $c['Late'] ?></b></span>
                <span class="chip absent">Absent <b><?= $c['Absent'] ?></b></span>
                <?php if ($c['none']): ?><span class="chip">Not marked <b><?= $c['none'] ?></b></span><?php endif; ?>
                <a class="btn btn-ghost btn-sm no-print" href="<?= e(page('attendance.php')) ?>?subject_id=<?= $sid ?>&amp;date=<?= e($date) ?>"><?= icon('edit') ?> Edit</a>
            </div>
        </div>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th class="num">#</th><th>Student ID</th><th>Student name</th><th>Section</th><th>Status</th><th>Time</th><th>Remarks</th></tr></thead>
                <tbody>
                <?php foreach ($g['rows'] as $n => $r): ?>
                    <tr>
                        <td class="num muted"><?= $n + 1 ?></td>
                        <td class="mono"><?= e($r['student_no']) ?></td>
                        <td class="cell-strong"><?= e(student_name($r)) ?></td>
                        <td><?= e($r['section'] ?: '—') ?></td>
                        <td><?= status_badge($r['status']) ?></td>
                        <td class="nowrap"><?= e(fmt_time($r['attendance_time'])) ?></td>
                        <td class="muted"><?= e($r['remarks'] ?? '') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endforeach; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>

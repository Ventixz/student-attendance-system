<?php
/**
 * Attendance Summary and Percentage: totals and percentages per class and per student.
 */
require __DIR__ . '/../includes/bootstrap.php';
$me = require_login();

$subjects  = accessible_subjects();
$from      = query_param('from');
$to        = query_param('to');
$from      = valid_date($from) ? $from : '';
$to        = valid_date($to) ? $to : '';
if ($from !== '' && $to !== '' && $from > $to) {
    [$from, $to] = [$to, $from];
}
$subjectId = (int) query_param('subject_id');
$section   = query_param('section');
$sort      = query_param('sort');
if (!in_array($sort, ['name', 'pct_asc', 'pct_desc'], true)) {
    $sort = 'name';
}

$ids = scoped_subject_ids($subjectId);
$in  = in_list($ids);

// Conditions applied inside the attendance join
$joinSql    = '';
$joinParams = [];
if ($from !== '') {
    $joinSql     .= ' AND a.attendance_date >= ?';
    $joinParams[] = $from;
}
if ($to !== '') {
    $joinSql     .= ' AND a.attendance_date <= ?';
    $joinParams[] = $to;
}

// Per class
$classJoin   = $joinSql . ($section !== '' ? ' AND a.student_id IN (SELECT id FROM students WHERE section = ?)' : '');
$classParams = array_merge($joinParams, $section !== '' ? [$section] : []);
$stmt = db()->prepare("SELECT sub.id, sub.code, sub.name, u.full_name AS teacher,
        (SELECT COUNT(*) FROM enrollments en WHERE en.subject_id = sub.id) AS enrolled,
        COUNT(DISTINCT a.attendance_date) AS days, COUNT(a.id) AS total,
        SUM(a.status = 'Present') AS present, SUM(a.status = 'Late') AS late, SUM(a.status = 'Absent') AS absent
    FROM subjects sub
    LEFT JOIN users u ON u.id = sub.teacher_id
    LEFT JOIN attendance a ON a.subject_id = sub.id $classJoin
    WHERE sub.id IN ($in)
    GROUP BY sub.id, sub.code, sub.name, u.full_name
    ORDER BY sub.code");
$stmt->execute(array_merge($classParams, $ids));
$classes = $stmt->fetchAll();

// Per student: everyone enrolled in (or with records in) the selected classes
$sectionSql = $section !== '' ? ' AND st.section = ?' : '';
$stmt = db()->prepare("SELECT st.id, st.student_no, st.first_name, st.last_name, st.section,
        COUNT(a.id) AS total, SUM(a.status = 'Present') AS present, SUM(a.status = 'Late') AS late, SUM(a.status = 'Absent') AS absent
    FROM students st
    LEFT JOIN attendance a ON a.student_id = st.id AND a.subject_id IN ($in) $joinSql
    WHERE st.id IN (SELECT student_id FROM enrollments WHERE subject_id IN ($in)
                    UNION SELECT student_id FROM attendance WHERE subject_id IN ($in)) $sectionSql
    GROUP BY st.id, st.student_no, st.first_name, st.last_name, st.section
    ORDER BY st.last_name, st.first_name");
$stmt->execute(array_merge($ids, $joinParams, $ids, $ids, $section !== '' ? [$section] : []));
$students = $stmt->fetchAll();

$totals = ['present' => 0, 'late' => 0, 'absent' => 0, 'total' => 0];
$below  = 0;
foreach ($students as &$s) {
    $s['pct'] = attendance_pct((int) $s['present'], (int) $s['late'], (int) $s['total']);
    foreach ($totals as $k => $_) {
        $totals[$k] += (int) $s[$k];
    }
    if ($s['pct'] !== null && $s['pct'] < LOW_ATTENDANCE_THRESHOLD) {
        $below++;
    }
}
unset($s);

if ($sort !== 'name') {
    usort($students, function ($a, $b) use ($sort) {
        // Students without records go last either way.
        if ($a['pct'] === null || $b['pct'] === null) {
            return ($a['pct'] === null) <=> ($b['pct'] === null);
        }
        return $sort === 'pct_asc' ? $a['pct'] <=> $b['pct'] : $b['pct'] <=> $a['pct'];
    });
}
$overall = attendance_pct($totals['present'], $totals['late'], $totals['total']);

$export = query_param('export');
if ($export === 'csv') {
    send_csv('attendance-summary-students.csv',
        ['Student ID', 'Student Name', 'Section', 'Sessions', 'Present', 'Late', 'Absent', 'Attendance %', 'Standing'],
        array_map(function ($s) {
            $standing = $s['pct'] === null ? 'No records' : ($s['pct'] >= LOW_ATTENDANCE_THRESHOLD ? 'Good standing' : 'Below ' . LOW_ATTENDANCE_THRESHOLD . '%');
            return [$s['student_no'], student_name($s), $s['section'], (int) $s['total'], (int) $s['present'], (int) $s['late'],
                (int) $s['absent'], $s['pct'] === null ? '' : $s['pct'], $standing];
        }, $students));
}
if ($export === 'classes') {
    send_csv('attendance-summary-classes.csv',
        ['Class Code', 'Class', 'Instructor', 'Enrolled', 'Days Held', 'Present', 'Late', 'Absent', 'Attendance %'],
        array_map(function ($c) {
            $pct = attendance_pct((int) $c['present'], (int) $c['late'], (int) $c['total']);
            return [$c['code'], $c['name'], $c['teacher'], $c['enrolled'], $c['days'], (int) $c['present'], (int) $c['late'],
                (int) $c['absent'], $pct === null ? '' : $pct];
        }, $classes));
}

$scopeLabel = range_label($from, $to)
    . ($subjectId && $ids ? ' · ' . find_accessible_subject($subjectId)['code'] : ' · All classes')
    . ($section !== '' ? ' · ' . $section : '');
$pageTitle = 'Attendance Summary & Percentage';
$activeNav = 'summary';
require __DIR__ . '/../includes/header.php';
?>
<div class="card">
    <form class="filters" method="get">
        <div class="field narrow"><label for="from">From <span class="muted">(optional)</span></label><input type="date" id="from" name="from" value="<?= e($from) ?>"></div>
        <div class="field narrow"><label for="to">To <span class="muted">(optional)</span></label><input type="date" id="to" name="to" value="<?= e($to) ?>"></div>
        <div class="field grow"><label for="subject_id">Class</label><select id="subject_id" name="subject_id"><?= subject_options($subjects, $subjectId, 'All classes') ?></select></div>
        <div class="field narrow"><label for="section">Section</label><select id="section" name="section"><?= section_options($section) ?></select></div>
        <div class="field narrow"><label for="sort">Sort students</label>
            <select id="sort" name="sort">
                <option value="name"<?= selected('name', $sort) ?>>By name</option>
                <option value="pct_asc"<?= selected('pct_asc', $sort) ?>>Lowest % first</option>
                <option value="pct_desc"<?= selected('pct_desc', $sort) ?>>Highest % first</option>
            </select>
        </div>
        <div class="filter-actions">
            <button class="btn btn-primary" type="submit"><?= icon('search') ?> Show</button>
            <a class="btn btn-ghost" href="<?= e(page('report_summary.php')) ?>">Reset</a>
        </div>
    </form>
</div>

<div class="page-actions no-print" style="margin:20px 0">
    <span class="muted small"><?= e($scopeLabel) ?></span>
    <div class="btn-row">
        <button type="button" class="btn btn-light btn-sm" data-print><?= icon('printer') ?> Print</button>
        <a class="btn btn-light btn-sm" href="<?= e(query_with(['export' => 'classes'])) ?>"><?= icon('download') ?> Classes CSV</a>
        <a class="btn btn-light btn-sm" href="<?= e(query_with(['export' => 'csv'])) ?>"><?= icon('download') ?> Students CSV</a>
    </div>
</div>

<?= print_header('Attendance Summary and Percentage', $scopeLabel) ?>

<div class="stats">
    <div class="stat info"><div class="stat-icon"><?= icon('pie') ?></div><div><div class="stat-label">Overall attendance</div><div class="stat-value"><?= e(pct_label($overall)) ?></div><div class="stat-sub"><?= $totals['total'] ?> marks</div></div></div>
    <div class="stat present"><div class="stat-icon"><?= icon('user-check') ?></div><div><div class="stat-label">Present</div><div class="stat-value"><?= $totals['present'] ?></div><div class="stat-sub"><?= e(pct_label($totals['total'] ? round($totals['present'] / $totals['total'] * 100, 1) : null)) ?> of marks</div></div></div>
    <div class="stat late"><div class="stat-icon"><?= icon('clock') ?></div><div><div class="stat-label">Late</div><div class="stat-value"><?= $totals['late'] ?></div><div class="stat-sub"><?= e(pct_label($totals['total'] ? round($totals['late'] / $totals['total'] * 100, 1) : null)) ?> of marks</div></div></div>
    <div class="stat absent"><div class="stat-icon"><?= icon('user-x') ?></div><div><div class="stat-label">Absent</div><div class="stat-value"><?= $totals['absent'] ?></div><div class="stat-sub"><?= e(pct_label($totals['total'] ? round($totals['absent'] / $totals['total'] * 100, 1) : null)) ?> of marks</div></div></div>
    <div class="stat"><div class="stat-icon"><?= icon('alert') ?></div><div><div class="stat-label">Below <?= (int) LOW_ATTENDANCE_THRESHOLD ?>%</div><div class="stat-value"><?= $below ?></div><div class="stat-sub">of <?= count($students) ?> students</div></div></div>
</div>

<div class="card" style="margin-top:20px">
    <div class="card-head"><div><h3>Summary by class</h3><p>Days held counts dates with at least one mark.</p></div></div>
    <?php if (!$classes): ?>
        <div class="empty"><?= icon('book') ?><h3>No classes</h3></div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Class</th><th>Instructor</th><th class="num">Enrolled</th><th class="num">Days held</th><th class="num">Present</th><th class="num">Late</th><th class="num">Absent</th><th>Attendance</th></tr></thead>
                <tbody>
                <?php foreach ($classes as $c): ?>
                    <tr>
                        <td><span class="cell-strong"><?= e($c['code']) ?></span><span class="cell-sub"><?= e($c['name']) ?></span></td>
                        <td><?= e($c['teacher'] ?? '—') ?></td>
                        <td class="num"><?= (int) $c['enrolled'] ?></td>
                        <td class="num"><?= (int) $c['days'] ?></td>
                        <td class="num"><?= (int) $c['present'] ?></td>
                        <td class="num"><?= (int) $c['late'] ?></td>
                        <td class="num"><?= (int) $c['absent'] ?></td>
                        <td><?= pct_bar(attendance_pct((int) $c['present'], (int) $c['late'], (int) $c['total'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-head">
        <div><h3>Summary by student</h3><p>Attendance % = (Present<?= LATE_COUNTS_AS_PRESENT ? ' + Late' : '' ?>) ÷ sessions recorded. Rows below <?= (int) LOW_ATTENDANCE_THRESHOLD ?>% are highlighted.</p></div>
    </div>
    <?php if (!$students): ?>
        <div class="empty"><?= icon('users') ?><h3>No students in these classes</h3></div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th class="num">#</th><th>Student ID</th><th>Student name</th><th>Section</th><th class="num">Sessions</th><th class="num">Present</th><th class="num">Late</th><th class="num">Absent</th><th>Attendance</th><th>Standing</th></tr></thead>
                <tbody>
                <?php foreach ($students as $n => $s): ?>
                    <tr<?= $s['pct'] !== null && $s['pct'] < LOW_ATTENDANCE_THRESHOLD ? ' class="row-low"' : '' ?>>
                        <td class="num muted"><?= $n + 1 ?></td>
                        <td class="mono"><?= e($s['student_no']) ?></td>
                        <td><a class="cell-strong" href="<?= e(page('report_student.php')) ?><?= e(query_with(['id' => $s['id'], 'section' => null, 'sort' => null])) ?>"><?= e(student_name($s)) ?></a></td>
                        <td><?= e($s['section'] ?: '—') ?></td>
                        <td class="num"><?= (int) $s['total'] ?></td>
                        <td class="num"><?= (int) $s['present'] ?></td>
                        <td class="num"><?= (int) $s['late'] ?></td>
                        <td class="num"><?= (int) $s['absent'] ?></td>
                        <td><?= pct_bar($s['pct']) ?></td>
                        <td><?= standing_badge($s['pct']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr><td colspan="4">Total</td><td class="num"><?= $totals['total'] ?></td><td class="num"><?= $totals['present'] ?></td><td class="num"><?= $totals['late'] ?></td><td class="num"><?= $totals['absent'] ?></td><td><?= pct_bar($overall) ?></td><td></td></tr>
                </tfoot>
            </table>
        </div>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>

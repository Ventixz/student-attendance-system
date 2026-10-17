<?php
/**
 * Lists of Present, Absent and Late students for a date range, as individual
 * records or grouped per student (how often each student had that status).
 */
require __DIR__ . '/../includes/bootstrap.php';
$me = require_login();

$status = query_param('status');
if (!in_array($status, STATUSES, true)) {
    $status = 'Present';
}
$subjects  = accessible_subjects();
$from      = query_param('from');
$to        = query_param('to');
$from      = valid_date($from) ? $from : today();
$to        = valid_date($to) ? $to : $from;
if ($from > $to) {
    [$from, $to] = [$to, $from];
}
$subjectId = (int) query_param('subject_id');
$section   = query_param('section');
$view      = query_param('view') === 'student' ? 'student' : 'records';

$ids    = scoped_subject_ids($subjectId);
$where  = 'a.subject_id IN (' . in_list($ids) . ') AND a.attendance_date BETWEEN ? AND ?';
$params = array_merge($ids, [$from, $to]);
if ($section !== '') {
    $where   .= ' AND st.section = ?';
    $params[] = $section;
}
$base = "FROM attendance a JOIN students st ON st.id = a.student_id JOIN subjects sub ON sub.id = a.subject_id
    LEFT JOIN users t ON t.id = a.teacher_id WHERE $where";

$stmt = db()->prepare("SELECT a.status, COUNT(*) $base GROUP BY a.status");
$stmt->execute($params);
$counts = array_fill_keys(STATUSES, 0);
foreach ($stmt->fetchAll(PDO::FETCH_KEY_PAIR) as $s => $n) {
    $counts[$s] = (int) $n;
}

if ($view === 'student') {
    $stmt = db()->prepare("SELECT st.id, st.student_no, st.first_name, st.last_name, st.section, COUNT(*) AS times,
            GROUP_CONCAT(DISTINCT sub.code ORDER BY sub.code SEPARATOR ', ') AS classes, MAX(a.attendance_date) AS last_date
        $base AND a.status = ?
        GROUP BY st.id, st.student_no, st.first_name, st.last_name, st.section
        ORDER BY times DESC, st.last_name, st.first_name");
} else {
    $stmt = db()->prepare("SELECT a.attendance_date, a.attendance_time, a.remarks, st.id AS student_id, st.student_no,
            st.first_name, st.last_name, st.section, sub.code, sub.name AS subject_name, t.full_name AS teacher
        $base AND a.status = ?
        ORDER BY a.attendance_date DESC, sub.code, st.last_name, st.first_name");
}
$stmt->execute(array_merge($params, [$status]));
$rows = $stmt->fetchAll();

$title = "List of $status Students";
if (query_param('export') === 'csv') {
    $file = strtolower($status) . "-students-{$from}-to-{$to}.csv";
    if ($view === 'student') {
        send_csv($file, ['Student ID', 'Student Name', 'Section', "Times $status", 'Classes', 'Most Recent'],
            array_map(function ($r) {
                return [$r['student_no'], student_name($r), $r['section'], $r['times'], $r['classes'], $r['last_date']];
            }, $rows));
    }
    send_csv($file, ['Date', 'Time', 'Student ID', 'Student Name', 'Section', 'Class Code', 'Class', 'Instructor', 'Remarks'],
        array_map(function ($r) {
            return [$r['attendance_date'], substr($r['attendance_time'], 0, 5), $r['student_no'], student_name($r), $r['section'],
                $r['code'], $r['subject_name'], $r['teacher'], $r['remarks']];
        }, $rows));
}

$tabIcons  = ['Present' => 'user-check', 'Late' => 'clock', 'Absent' => 'user-x'];
$pageTitle = $title;
$activeNav = strtolower($status);
require __DIR__ . '/../includes/header.php';
?>
<div class="card">
    <form class="filters" method="get">
        <input type="hidden" name="status" value="<?= e($status) ?>">
        <div class="field narrow"><label for="from">From</label><input type="date" id="from" name="from" value="<?= e($from) ?>"></div>
        <div class="field narrow"><label for="to">To</label><input type="date" id="to" name="to" value="<?= e($to) ?>"></div>
        <div class="field grow"><label for="subject_id">Class</label><select id="subject_id" name="subject_id"><?= subject_options($subjects, $subjectId, 'All classes') ?></select></div>
        <div class="field narrow"><label for="section">Section</label><select id="section" name="section"><?= section_options($section) ?></select></div>
        <div class="field narrow"><label for="view">Show</label>
            <select id="view" name="view">
                <option value="records"<?= selected('records', $view) ?>>Each record</option>
                <option value="student"<?= selected('student', $view) ?>>Per student</option>
            </select>
        </div>
        <div class="filter-actions"><button class="btn btn-primary" type="submit"><?= icon('search') ?> Show</button></div>
    </form>
</div>

<?= print_header($title, range_label($from, $to) . ($subjectId && $ids ? ' · ' . find_accessible_subject($subjectId)['code'] : '') . ($section !== '' ? ' · ' . $section : '')) ?>

<div class="card">
    <nav class="tabs" aria-label="Status">
        <?php foreach (STATUSES as $s): ?>
            <a class="tab<?= $s === $status ? ' active' : '' ?>" href="<?= e(query_with(['status' => $s])) ?>"><?= icon($tabIcons[$s]) ?> <?= $s ?> <span class="count"><?= $counts[$s] ?></span></a>
        <?php endforeach; ?>
    </nav>
    <div class="card-head">
        <div>
            <h3><?= e($title) ?></h3>
            <p><?= e(range_label($from, $to)) ?> · <?= $view === 'student' ? count($rows) . ' student(s)' : count($rows) . ' record(s)' ?></p>
        </div>
        <div class="btn-row" style="margin:0">
            <button type="button" class="btn btn-light btn-sm" data-print><?= icon('printer') ?> Print</button>
            <a class="btn btn-light btn-sm" href="<?= e(query_with(['export' => 'csv'])) ?>"><?= icon('download') ?> Export CSV</a>
        </div>
    </div>

    <?php if (!$rows): ?>
        <div class="empty"><?= icon($tabIcons[$status]) ?><h3>No <?= strtolower($status) ?> students</h3><p>Nobody was marked <?= strtolower($status) ?> for <?= e(range_label($from, $to)) ?> with these filters.</p></div>
    <?php elseif ($view === 'student'): ?>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th class="num">#</th><th>Student ID</th><th>Student name</th><th>Section</th><th class="num">Times <?= e(strtolower($status)) ?></th><th>Classes</th><th>Most recent</th></tr></thead>
                <tbody>
                <?php foreach ($rows as $n => $r): ?>
                    <tr>
                        <td class="num muted"><?= $n + 1 ?></td>
                        <td class="mono"><?= e($r['student_no']) ?></td>
                        <td><a class="cell-strong" href="<?= e(page('report_student.php')) ?>?id=<?= (int) $r['id'] ?>"><?= e(student_name($r)) ?></a></td>
                        <td><?= e($r['section'] ?: '—') ?></td>
                        <td class="num"><strong><?= (int) $r['times'] ?></strong></td>
                        <td><?= e($r['classes']) ?></td>
                        <td class="nowrap"><?= e(fmt_date($r['last_date'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th class="num">#</th><th>Date</th><th>Time</th><th>Student ID</th><th>Student name</th><th>Section</th><th>Class</th><th>Instructor</th><th>Remarks</th></tr></thead>
                <tbody>
                <?php foreach ($rows as $n => $r): ?>
                    <tr>
                        <td class="num muted"><?= $n + 1 ?></td>
                        <td class="nowrap"><?= e(fmt_date($r['attendance_date'])) ?></td>
                        <td class="nowrap"><?= e(fmt_time($r['attendance_time'])) ?></td>
                        <td class="mono"><?= e($r['student_no']) ?></td>
                        <td><a class="cell-strong" href="<?= e(page('report_student.php')) ?>?id=<?= (int) $r['student_id'] ?>"><?= e(student_name($r)) ?></a></td>
                        <td><?= e($r['section'] ?: '—') ?></td>
                        <td><span class="cell-strong"><?= e($r['code']) ?></span><span class="cell-sub"><?= e($r['subject_name']) ?></span></td>
                        <td><?= e($r['teacher'] ?? '—') ?></td>
                        <td class="muted"><?= e($r['remarks'] ?? '') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>

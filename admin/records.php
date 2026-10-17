<?php
/**
 * View and monitor attendance records with filters; edit or delete individual marks.
 */
require __DIR__ . '/../includes/bootstrap.php';
$me = require_login();

if (is_post() && input('action') === 'delete') {
    verify_csrf();
    $ids  = scoped_subject_ids();
    $stmt = db()->prepare('DELETE FROM attendance WHERE id = ? AND subject_id IN (' . in_list($ids) . ')');
    $stmt->execute(array_merge([input_int('id')], $ids));
    flash($stmt->rowCount() ? 'success' : 'error', $stmt->rowCount() ? 'Attendance record deleted.' : 'That record could not be found.');
    redirect('records.php' . safe_return(input('return')));
}

$subjects = accessible_subjects();
$filters  = [
    'from'       => input('from', date('Y-m-d', strtotime('-6 days'))),
    'to'         => input('to', today()),
    'subject_id' => input_int('subject_id'),
    'status'     => input('status'),
    'section'    => input('section'),
    'q'          => input('q'),
];
if (!valid_date($filters['from'])) {
    $filters['from'] = date('Y-m-d', strtotime('-6 days'));
}
if (!valid_date($filters['to'])) {
    $filters['to'] = today();
}
if ($filters['from'] > $filters['to']) {
    [$filters['from'], $filters['to']] = [$filters['to'], $filters['from']];
}

$ids    = scoped_subject_ids($filters['subject_id']);
$where  = ['a.subject_id IN (' . in_list($ids) . ')', 'a.attendance_date BETWEEN ? AND ?'];
$params = array_merge($ids, [$filters['from'], $filters['to']]);
if (in_array($filters['status'], STATUSES, true)) {
    $where[]  = 'a.status = ?';
    $params[] = $filters['status'];
}
if ($filters['section'] !== '') {
    $where[]  = 'st.section = ?';
    $params[] = $filters['section'];
}
if ($filters['q'] !== '') {
    $where[] = "(st.student_no LIKE ? OR CONCAT(st.first_name, ' ', st.last_name) LIKE ? OR CONCAT(st.last_name, ', ', st.first_name) LIKE ?)";
    $like    = '%' . addcslashes($filters['q'], '%_\\') . '%';
    array_push($params, $like, $like, $like);
}
$from = 'FROM attendance a
    JOIN students st ON st.id = a.student_id
    JOIN subjects sub ON sub.id = a.subject_id
    LEFT JOIN users t ON t.id = a.teacher_id
    LEFT JOIN users r ON r.id = a.recorded_by
    WHERE ' . implode(' AND ', $where);
$columns = 'SELECT a.*, st.student_no, st.first_name, st.last_name, st.section, sub.code, sub.name AS subject_name,
    t.full_name AS teacher, r.full_name AS recorder ';
$order = ' ORDER BY a.attendance_date DESC, sub.code, st.last_name, st.first_name';

if (input('export') === 'csv') {
    $stmt = db()->prepare($columns . $from . $order);
    $stmt->execute($params);
    $rows = [];
    foreach ($stmt as $r) {
        $rows[] = [$r['attendance_date'], substr($r['attendance_time'], 0, 5), $r['student_no'], student_name($r), $r['section'],
            $r['code'], $r['subject_name'], $r['status'], $r['teacher'], $r['remarks'], $r['recorder'], $r['updated_at']];
    }
    send_csv("attendance-records-{$filters['from']}-to-{$filters['to']}.csv",
        ['Date', 'Time', 'Student ID', 'Student Name', 'Section', 'Class Code', 'Class', 'Status', 'Instructor', 'Remarks', 'Recorded By', 'Last Updated'], $rows);
}

$stmt = db()->prepare('SELECT a.status, COUNT(*) ' . $from . ' GROUP BY a.status');
$stmt->execute($params);
$counts = array_fill_keys(STATUSES, 0);
foreach ($stmt->fetchAll(PDO::FETCH_KEY_PAIR) as $status => $count) {
    $counts[$status] = (int) $count;
}
$total = array_sum($counts);

$p    = paginate($total, input_int('page'));
$stmt = db()->prepare($columns . $from . $order . " LIMIT {$p['limit']} OFFSET {$p['offset']}");
$stmt->execute($params);
$records = $stmt->fetchAll();

$return    = '?' . ($_SERVER['QUERY_STRING'] ?? '');
$pageTitle = 'Attendance Records';
$activeNav = 'records';
require __DIR__ . '/../includes/header.php';
?>
<div class="card">
    <form class="filters" method="get">
        <div class="field narrow"><label for="from">From</label><input type="date" id="from" name="from" value="<?= e($filters['from']) ?>"></div>
        <div class="field narrow"><label for="to">To</label><input type="date" id="to" name="to" value="<?= e($filters['to']) ?>"></div>
        <div class="field grow"><label for="subject_id">Class</label><select id="subject_id" name="subject_id"><?= subject_options($subjects, $filters['subject_id'], 'All classes') ?></select></div>
        <div class="field narrow"><label for="status">Status</label>
            <select id="status" name="status">
                <option value="">All statuses</option>
                <?php foreach (STATUSES as $s): ?><option<?= selected($s, $filters['status']) ?>><?= $s ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="field narrow"><label for="section">Section</label><select id="section" name="section"><?= section_options($filters['section']) ?></select></div>
        <div class="field"><label for="q">Student</label><input type="search" id="q" name="q" value="<?= e($filters['q']) ?>" placeholder="ID or name"></div>
        <div class="filter-actions">
            <button class="btn btn-primary" type="submit"><?= icon('search') ?> Filter</button>
            <a class="btn btn-ghost" href="<?= e(page('records.php')) ?>">Reset</a>
        </div>
    </form>
</div>

<div class="card">
    <div class="card-head">
        <div class="chips">
            <span class="chip"><b><?= $total ?></b> records</span>
            <span class="chip present">Present <b><?= $counts['Present'] ?></b></span>
            <span class="chip late">Late <b><?= $counts['Late'] ?></b></span>
            <span class="chip absent">Absent <b><?= $counts['Absent'] ?></b></span>
            <span class="chip">Attendance <b><?= e(pct_label(attendance_pct($counts['Present'], $counts['Late'], $total))) ?></b></span>
        </div>
        <div class="btn-row" style="margin:0">
            <a class="btn btn-light btn-sm" href="<?= e(query_with(['export' => 'csv', 'page' => null])) ?>"><?= icon('download') ?> Export CSV</a>
            <a class="btn btn-primary btn-sm" href="<?= e(page('quick_entry.php')) ?>"><?= icon('plus') ?> Add record</a>
        </div>
    </div>
    <?php if (!$records): ?>
        <div class="empty"><?= icon('search') ?><h3>No records match these filters</h3><p>Try a wider date range or clear the filters.</p></div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr><th>Date &amp; time</th><th>Student</th><th>Section</th><th>Class</th><th>Status</th><th>Instructor</th><th>Remarks</th><th class="actions">Actions</th></tr>
                </thead>
                <tbody>
                <?php foreach ($records as $r): ?>
                    <tr>
                        <td class="nowrap"><?= e(fmt_date($r['attendance_date'])) ?><span class="cell-sub"><?= e(fmt_time($r['attendance_time'])) ?></span></td>
                        <td><a class="cell-strong" href="<?= e(page('report_student.php')) ?>?id=<?= (int) $r['student_id'] ?>"><?= e(student_name($r)) ?></a><span class="cell-sub mono"><?= e($r['student_no']) ?></span></td>
                        <td><?= e($r['section'] ?: '—') ?></td>
                        <td><span class="cell-strong"><?= e($r['code']) ?></span><span class="cell-sub"><?= e($r['subject_name']) ?></span></td>
                        <td><?= status_badge($r['status']) ?></td>
                        <td><?= e($r['teacher'] ?? '—') ?></td>
                        <td class="muted"><?= e($r['remarks'] ?? '') ?></td>
                        <td class="actions">
                            <a class="btn btn-ghost btn-sm btn-icon" href="<?= e(page('record_edit.php')) ?>?id=<?= (int) $r['id'] ?>&amp;return=<?= e(urlencode($return)) ?>" title="Edit" aria-label="Edit record"><?= icon('edit') ?></a>
                            <form method="post" data-confirm="Delete this attendance record for <?= e(student_name($r)) ?>?">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                <input type="hidden" name="return" value="<?= e($return) ?>">
                                <button class="btn btn-ghost btn-sm btn-icon" type="submit" title="Delete" aria-label="Delete record"><?= icon('trash') ?></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?= pagination_links($p) ?>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>

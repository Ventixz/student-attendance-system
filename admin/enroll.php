<?php
/**
 * Choose which students belong to a class (admin only).
 */
require __DIR__ . '/../includes/bootstrap.php';
$me = require_admin();

$subject = find_accessible_subject(input_int('subject_id'));
if (!$subject) {
    flash('error', 'Choose a class to manage its students.');
    redirect('subjects.php');
}

if (is_post()) {
    verify_csrf();
    $allIds   = array_map('intval', db()->query('SELECT id FROM students')->fetchAll(PDO::FETCH_COLUMN));
    $selected = array_values(array_intersect(array_map('intval', (array) ($_POST['students'] ?? [])), $allIds));

    db()->beginTransaction();
    $stmt = db()->prepare('DELETE FROM enrollments WHERE subject_id = ?' . ($selected ? ' AND student_id NOT IN (' . in_list($selected) . ')' : ''));
    $stmt->execute(array_merge([$subject['id']], $selected));
    $enroll = db()->prepare('INSERT IGNORE INTO enrollments (student_id, subject_id) VALUES (?, ?)');
    foreach ($selected as $studentId) {
        $enroll->execute([$studentId, $subject['id']]);
    }
    db()->commit();

    flash('success', sprintf('Class list saved: %d student(s) enrolled in %s.', count($selected), $subject['code']));
    redirect('enroll.php?subject_id=' . (int) $subject['id']);
}

$stmt = db()->prepare('SELECT st.id, st.student_no, st.first_name, st.last_name, st.section, (en.student_id IS NOT NULL) AS enrolled
    FROM students st
    LEFT JOIN enrollments en ON en.student_id = st.id AND en.subject_id = ?
    ORDER BY st.last_name, st.first_name');
$stmt->execute([$subject['id']]);
$students = $stmt->fetchAll();

$pageTitle = 'Enroll Students';
$activeNav = 'subjects';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-actions">
    <div class="page-intro">
        <h2><?= e($subject['code'] . ' — ' . $subject['name']) ?></h2>
        <p><?= e($subject['teacher_name'] ? 'Instructor: ' . $subject['teacher_name'] : 'No instructor assigned') ?><?= $subject['schedule'] ? ' · ' . e($subject['schedule']) : '' ?></p>
    </div>
    <div class="btn-row">
        <a class="btn btn-light" href="<?= e(page('subjects.php')) ?>"><?= icon('arrow-left') ?> All classes</a>
        <a class="btn btn-light" href="<?= e(page('attendance.php')) ?>?subject_id=<?= (int) $subject['id'] ?>"><?= icon('check') ?> Take attendance</a>
    </div>
</div>

<?php if (!$students): ?>
    <div class="card"><div class="empty"><?= icon('users') ?><h3>No students yet</h3><p>Add students first, then enroll them here.</p><a class="btn btn-primary" href="<?= e(page('students.php')) ?>"><?= icon('plus') ?> Add students</a></div></div>
<?php else: ?>
    <form method="post" class="card" data-enroll>
        <?= csrf_field() ?>
        <input type="hidden" name="subject_id" value="<?= (int) $subject['id'] ?>">
        <div class="filters">
            <div class="field grow"><label for="filter_text">Find students</label><input type="search" id="filter_text" data-filter-text placeholder="Type a name or Student ID"></div>
            <div class="field narrow"><label for="filter_section">Section</label><select id="filter_section" data-filter-section><?= section_options('') ?></select></div>
            <div class="filter-actions">
                <button type="button" class="btn btn-light" data-select-visible="1">Select shown</button>
                <button type="button" class="btn btn-ghost" data-select-visible="0">Unselect shown</button>
            </div>
        </div>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th style="width:48px"><span class="sr-only">Enrolled</span></th><th>Student ID</th><th>Name</th><th>Section</th></tr></thead>
                <tbody>
                <?php foreach ($students as $s): ?>
                    <tr data-search="<?= e(mb_strtolower($s['student_no'] . ' ' . $s['first_name'] . ' ' . $s['last_name'] . ' ' . student_name($s))) ?>" data-section="<?= e($s['section'] ?? '') ?>">
                        <td><input type="checkbox" name="students[]" value="<?= (int) $s['id'] ?>" id="st<?= (int) $s['id'] ?>"<?= checked((bool) $s['enrolled']) ?>></td>
                        <td class="mono"><label for="st<?= (int) $s['id'] ?>"><?= e($s['student_no']) ?></label></td>
                        <td class="cell-strong"><label for="st<?= (int) $s['id'] ?>"><?= e(student_name($s)) ?></label></td>
                        <td><?= e($s['section'] ?: '—') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="card-foot save-bar">
            <span class="muted"><strong data-selected-count>0</strong> of <?= count($students) ?> students selected</span>
            <button class="btn btn-primary" type="submit"><?= icon('check') ?> Save class list</button>
        </div>
    </form>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>

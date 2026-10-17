<?php
/**
 * Manage students (admin only): add, edit, enroll in classes, delete.
 */
require __DIR__ . '/../includes/bootstrap.php';
$me = require_admin();

$subjects = accessible_subjects();
$blank    = ['id' => 0, 'student_no' => '', 'first_name' => '', 'last_name' => '', 'section' => '', 'email' => '', 'subjects' => []];
$form     = $blank;
$errors   = [];

if (is_post()) {
    verify_csrf();

    if (input('action') === 'delete') {
        $stmt = db()->prepare('DELETE FROM students WHERE id = ?');
        $stmt->execute([input_int('id')]);
        flash('success', 'Student deleted, along with their attendance records.');
        redirect('students.php' . safe_return(input('return')));
    }

    $form = [
        'id'         => input_int('id'),
        'student_no' => clean_text(input('student_no'), 30),
        'first_name' => clean_text(input('first_name'), 60),
        'last_name'  => clean_text(input('last_name'), 60),
        'section'    => clean_text(input('section'), 50),
        'email'      => clean_text(input('email'), 120),
        'subjects'   => array_values(array_intersect(
            array_map('intval', (array) ($_POST['subjects'] ?? [])),
            array_map('intval', array_column($subjects, 'id'))
        )),
    ];

    if (!preg_match('/^[A-Za-z0-9._-]+$/', $form['student_no'])) {
        $errors['student_no'] = 'Use letters, numbers, dots, dashes or underscores.';
    } else {
        $stmt = db()->prepare('SELECT id FROM students WHERE student_no = ? AND id <> ?');
        $stmt->execute([$form['student_no'], $form['id']]);
        if ($stmt->fetch()) {
            $errors['student_no'] = 'Another student already has this Student ID.';
        }
    }
    if ($form['first_name'] === '') {
        $errors['first_name'] = 'Enter a first name.';
    }
    if ($form['last_name'] === '') {
        $errors['last_name'] = 'Enter a last name.';
    }
    if ($form['email'] !== '' && !filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Enter a valid email or leave it blank.';
    }

    if (!$errors) {
        $values = [$form['student_no'], $form['first_name'], $form['last_name'], $form['section'] ?: null, $form['email'] ?: null];
        db()->beginTransaction();
        if ($form['id']) {
            db()->prepare('UPDATE students SET student_no = ?, first_name = ?, last_name = ?, section = ?, email = ? WHERE id = ?')
                ->execute(array_merge($values, [$form['id']]));
            $studentId = $form['id'];
        } else {
            db()->prepare('INSERT INTO students (student_no, first_name, last_name, section, email) VALUES (?, ?, ?, ?, ?)')
                ->execute($values);
            $studentId = (int) db()->lastInsertId();
        }

        // Sync class enrollments with the ticked boxes
        $keep = $form['subjects'];
        $stmt = db()->prepare('DELETE FROM enrollments WHERE student_id = ?' . ($keep ? ' AND subject_id NOT IN (' . in_list($keep) . ')' : ''));
        $stmt->execute(array_merge([$studentId], $keep));
        $enroll = db()->prepare('INSERT IGNORE INTO enrollments (student_id, subject_id) VALUES (?, ?)');
        foreach ($keep as $subjectId) {
            $enroll->execute([$studentId, $subjectId]);
        }
        db()->commit();

        flash('success', ($form['id'] ? 'Updated ' : 'Added ') . $form['first_name'] . ' ' . $form['last_name'] . '.');
        redirect('students.php');
    }
} elseif (input_int('edit')) {
    $stmt = db()->prepare('SELECT * FROM students WHERE id = ?');
    $stmt->execute([input_int('edit')]);
    $student = $stmt->fetch();
    if (!$student) {
        flash('error', 'That student could not be found.');
        redirect('students.php');
    }
    $stmt = db()->prepare('SELECT subject_id FROM enrollments WHERE student_id = ?');
    $stmt->execute([$student['id']]);
    $form = array_merge($blank, $student, ['subjects' => array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN))]);
    $form['section'] = (string) $form['section'];
    $form['email']   = (string) $form['email'];
}

// Listing
$q       = query_param('q');
$section = query_param('section');
$where   = ['1=1'];
$params  = [];
if ($q !== '') {
    $where[] = "(st.student_no LIKE ? OR CONCAT(st.first_name, ' ', st.last_name) LIKE ? OR CONCAT(st.last_name, ', ', st.first_name) LIKE ?)";
    $like    = '%' . addcslashes($q, '%_\\') . '%';
    array_push($params, $like, $like, $like);
}
if ($section !== '') {
    $where[]  = 'st.section = ?';
    $params[] = $section;
}
$whereSql = implode(' AND ', $where);

$stmt = db()->prepare("SELECT COUNT(*) FROM students st WHERE $whereSql");
$stmt->execute($params);
$p = paginate((int) $stmt->fetchColumn(), input_int('page'));

$stmt = db()->prepare("SELECT st.*,
        (SELECT COUNT(*) FROM enrollments en WHERE en.student_id = st.id) AS classes,
        COALESCE(agg.total, 0) AS total, COALESCE(agg.present, 0) AS present, COALESCE(agg.late, 0) AS late
    FROM students st
    LEFT JOIN (SELECT student_id, COUNT(*) AS total, SUM(status = 'Present') AS present, SUM(status = 'Late') AS late
               FROM attendance GROUP BY student_id) agg ON agg.student_id = st.id
    WHERE $whereSql
    ORDER BY st.last_name, st.first_name
    LIMIT {$p['limit']} OFFSET {$p['offset']}");
$stmt->execute($params);
$students = $stmt->fetchAll();

$return    = '?' . ($_SERVER['QUERY_STRING'] ?? '');
$pageTitle = 'Students';
$activeNav = 'students';
require __DIR__ . '/../includes/header.php';
?>
<div class="grid-side">
    <div class="card">
        <form class="filters" method="get">
            <div class="field grow"><label for="q">Search</label><input type="search" id="q" name="q" value="<?= e($q) ?>" placeholder="Student ID or name"></div>
            <div class="field narrow"><label for="section">Section</label><select id="section" name="section"><?= section_options($section) ?></select></div>
            <div class="filter-actions">
                <button class="btn btn-primary" type="submit"><?= icon('search') ?> Search</button>
                <?php if ($q !== '' || $section !== ''): ?><a class="btn btn-ghost" href="<?= e(page('students.php')) ?>">Reset</a><?php endif; ?>
            </div>
        </form>
        <?php if (!$students): ?>
            <div class="empty"><?= icon('users') ?>
                <h3><?= $p['total'] || $q !== '' || $section !== '' ? 'No students match your search' : 'No students yet' ?></h3>
                <p>Use the form to add your first student.</p>
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Student ID</th><th>Name</th><th>Section</th><th class="num">Classes</th><th>Attendance</th><th class="actions">Actions</th></tr></thead>
                    <tbody>
                    <?php foreach ($students as $s): ?>
                        <tr>
                            <td class="mono"><?= e($s['student_no']) ?></td>
                            <td><span class="cell-strong"><?= e(student_name($s)) ?></span><?php if ($s['email']): ?><span class="cell-sub"><?= e($s['email']) ?></span><?php endif; ?></td>
                            <td><?= e($s['section'] ?: '—') ?></td>
                            <td class="num"><?= (int) $s['classes'] ?></td>
                            <td><?= pct_bar(attendance_pct((int) $s['present'], (int) $s['late'], (int) $s['total'])) ?></td>
                            <td class="actions">
                                <a class="btn btn-ghost btn-sm btn-icon" href="<?= e(page('report_student.php')) ?>?id=<?= (int) $s['id'] ?>" title="Attendance report" aria-label="Attendance report"><?= icon('file') ?></a>
                                <a class="btn btn-ghost btn-sm btn-icon" href="<?= e(page('students.php')) ?>?edit=<?= (int) $s['id'] ?>" title="Edit" aria-label="Edit student"><?= icon('edit') ?></a>
                                <form method="post" data-confirm="Delete <?= e($s['first_name'] . ' ' . $s['last_name']) ?>? All of their attendance records will be deleted too.">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                                    <input type="hidden" name="return" value="<?= e($return) ?>">
                                    <button class="btn btn-ghost btn-sm btn-icon" type="submit" title="Delete" aria-label="Delete student"><?= icon('trash') ?></button>
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

    <form method="post" class="card sticky" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int) $form['id'] ?>">
        <div class="card-head">
            <div><h3><?= $form['id'] ? 'Edit student' : 'Add a student' ?></h3><p><?= $form['id'] ? 'Update details and class enrollment.' : 'Required fields: Student ID and name.' ?></p></div>
        </div>
        <div class="card-body form-stack">
            <div class="field<?= isset($errors['student_no']) ? ' has-error' : '' ?>">
                <label for="student_no">Student ID</label>
                <input id="student_no" name="student_no" value="<?= e($form['student_no']) ?>" required maxlength="30" placeholder="e.g. 2026-0016">
                <?php if (isset($errors['student_no'])): ?><p class="field-error"><?= e($errors['student_no']) ?></p><?php endif; ?>
            </div>
            <div class="form-grid">
                <div class="field<?= isset($errors['first_name']) ? ' has-error' : '' ?>">
                    <label for="first_name">First name</label>
                    <input id="first_name" name="first_name" value="<?= e($form['first_name']) ?>" required maxlength="60">
                    <?php if (isset($errors['first_name'])): ?><p class="field-error"><?= e($errors['first_name']) ?></p><?php endif; ?>
                </div>
                <div class="field<?= isset($errors['last_name']) ? ' has-error' : '' ?>">
                    <label for="last_name">Last name</label>
                    <input id="last_name" name="last_name" value="<?= e($form['last_name']) ?>" required maxlength="60">
                    <?php if (isset($errors['last_name'])): ?><p class="field-error"><?= e($errors['last_name']) ?></p><?php endif; ?>
                </div>
            </div>
            <div class="field">
                <label for="form_section">Section / Year level</label>
                <input id="form_section" name="section" value="<?= e($form['section']) ?>" maxlength="50" list="section-list" placeholder="e.g. Section A">
                <datalist id="section-list"><?php foreach (all_sections() as $sec): ?><option value="<?= e($sec) ?>"><?php endforeach; ?></datalist>
            </div>
            <div class="field<?= isset($errors['email']) ? ' has-error' : '' ?>">
                <label for="email">Email <span class="muted">(optional)</span></label>
                <input id="email" name="email" type="email" value="<?= e($form['email']) ?>" maxlength="120">
                <?php if (isset($errors['email'])): ?><p class="field-error"><?= e($errors['email']) ?></p><?php endif; ?>
            </div>
            <div class="field">
                <span class="field-label">Enrolled classes</span>
                <?php if (!$subjects): ?>
                    <p class="muted small">No classes yet. <a href="<?= e(page('subjects.php')) ?>">Create one</a> to enroll students.</p>
                <?php else: ?>
                    <div class="check-list">
                        <?php foreach ($subjects as $sub): ?>
                            <label><input type="checkbox" name="subjects[]" value="<?= (int) $sub['id'] ?>"<?= checked(in_array((int) $sub['id'], $form['subjects'], true)) ?>> <span><strong><?= e($sub['code']) ?></strong> <?= e($sub['name']) ?></span></label>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="card-foot">
            <?php if ($form['id']): ?><a class="btn btn-ghost" href="<?= e(page('students.php')) ?>">Cancel</a><?php else: ?><span></span><?php endif; ?>
            <button class="btn btn-primary" type="submit"><?= icon($form['id'] ? 'check' : 'plus') ?> <?= $form['id'] ? 'Save changes' : 'Add student' ?></button>
        </div>
    </form>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>

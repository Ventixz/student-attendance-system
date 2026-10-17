<?php
/**
 * Manage classes / subjects and their instructors (admin only).
 */
require __DIR__ . '/../includes/bootstrap.php';
$me = require_admin();

$blank  = ['id' => 0, 'code' => '', 'name' => '', 'schedule' => '', 'teacher_id' => 0];
$form   = $blank;
$errors = [];

if (is_post()) {
    verify_csrf();

    if (input('action') === 'delete') {
        db()->prepare('DELETE FROM subjects WHERE id = ?')->execute([input_int('id')]);
        flash('success', 'Class deleted, along with its enrollments and attendance records.');
        redirect('subjects.php');
    }

    $form = [
        'id'         => input_int('id'),
        'code'       => strtoupper(clean_text(input('code'), 20)),
        'name'       => clean_text(input('name'), 120),
        'schedule'   => clean_text(input('schedule'), 100),
        'teacher_id' => input_int('teacher_id'),
    ];

    if (!preg_match('/^[A-Z0-9._ -]+$/', $form['code'])) {
        $errors['code'] = 'Use letters, numbers, spaces, dots or dashes.';
    } else {
        $stmt = db()->prepare('SELECT id FROM subjects WHERE code = ? AND id <> ?');
        $stmt->execute([$form['code'], $form['id']]);
        if ($stmt->fetch()) {
            $errors['code'] = 'Another class already uses this code.';
        }
    }
    if ($form['name'] === '') {
        $errors['name'] = 'Enter the class or subject name.';
    }
    if ($form['teacher_id'] && !in_array($form['teacher_id'], array_map('intval', array_column(instructors(), 'id')), true)) {
        $errors['teacher_id'] = 'Choose an active staff account.';
    }

    if (!$errors) {
        $values = [$form['code'], $form['name'], $form['schedule'] ?: null, $form['teacher_id'] ?: null];
        if ($form['id']) {
            db()->prepare('UPDATE subjects SET code = ?, name = ?, schedule = ?, teacher_id = ? WHERE id = ?')
                ->execute(array_merge($values, [$form['id']]));
            flash('success', "Updated {$form['code']}.");
        } else {
            db()->prepare('INSERT INTO subjects (code, name, schedule, teacher_id) VALUES (?, ?, ?, ?)')->execute($values);
            $newId = (int) db()->lastInsertId();
            flash('success', "Added {$form['code']}. Next, enroll students in it.");
            redirect('enroll.php?subject_id=' . $newId);
        }
        redirect('subjects.php');
    }
} elseif (input_int('edit')) {
    $stmt = db()->prepare('SELECT id, code, name, schedule, teacher_id FROM subjects WHERE id = ?');
    $stmt->execute([input_int('edit')]);
    $row = $stmt->fetch();
    if (!$row) {
        flash('error', 'That class could not be found.');
        redirect('subjects.php');
    }
    $form = ['id' => (int) $row['id'], 'code' => $row['code'], 'name' => $row['name'],
        'schedule' => (string) $row['schedule'], 'teacher_id' => (int) $row['teacher_id']];
}

$classes = db()->query("SELECT sub.*, u.full_name AS teacher,
        (SELECT COUNT(*) FROM enrollments en WHERE en.subject_id = sub.id) AS enrolled,
        COALESCE(agg.days, 0) AS days, COALESCE(agg.total, 0) AS total,
        COALESCE(agg.present, 0) AS present, COALESCE(agg.late, 0) AS late
    FROM subjects sub
    LEFT JOIN users u ON u.id = sub.teacher_id
    LEFT JOIN (SELECT subject_id, COUNT(DISTINCT attendance_date) AS days, COUNT(*) AS total,
                      SUM(status = 'Present') AS present, SUM(status = 'Late') AS late
               FROM attendance GROUP BY subject_id) agg ON agg.subject_id = sub.id
    ORDER BY sub.code")->fetchAll();

$pageTitle = 'Classes & Subjects';
$activeNav = 'subjects';
require __DIR__ . '/../includes/header.php';
?>
<div class="grid-side">
    <div class="card">
        <div class="card-head"><div><h3>All classes</h3><p><?= count($classes) ?> class<?= count($classes) === 1 ? '' : 'es' ?></p></div></div>
        <?php if (!$classes): ?>
            <div class="empty"><?= icon('book') ?><h3>No classes yet</h3><p>Use the form to add your first class or subject.</p></div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Class</th><th>Instructor</th><th class="num">Students</th><th class="num">Days held</th><th>Attendance</th><th class="actions">Actions</th></tr></thead>
                    <tbody>
                    <?php foreach ($classes as $c): ?>
                        <tr>
                            <td><span class="cell-strong"><?= e($c['code']) ?> — <?= e($c['name']) ?></span><span class="cell-sub"><?= e($c['schedule'] ?: 'No schedule set') ?></span></td>
                            <td><?= $c['teacher'] ? e($c['teacher']) : '<span class="muted">Not assigned</span>' ?></td>
                            <td class="num"><?= (int) $c['enrolled'] ?></td>
                            <td class="num"><?= (int) $c['days'] ?></td>
                            <td><?= pct_bar(attendance_pct((int) $c['present'], (int) $c['late'], (int) $c['total'])) ?></td>
                            <td class="actions">
                                <a class="btn btn-light btn-sm" href="<?= e(page('enroll.php')) ?>?subject_id=<?= (int) $c['id'] ?>"><?= icon('users') ?> Students</a>
                                <a class="btn btn-ghost btn-sm btn-icon" href="<?= e(page('attendance.php')) ?>?subject_id=<?= (int) $c['id'] ?>" title="Take attendance" aria-label="Take attendance"><?= icon('check') ?></a>
                                <a class="btn btn-ghost btn-sm btn-icon" href="<?= e(page('subjects.php')) ?>?edit=<?= (int) $c['id'] ?>" title="Edit" aria-label="Edit class"><?= icon('edit') ?></a>
                                <form method="post" data-confirm="Delete <?= e($c['code']) ?>? Its enrollments and all of its attendance records will be deleted.">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                                    <button class="btn btn-ghost btn-sm btn-icon" type="submit" title="Delete" aria-label="Delete class"><?= icon('trash') ?></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <form method="post" class="card sticky" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int) $form['id'] ?>">
        <div class="card-head"><div><h3><?= $form['id'] ? 'Edit class' : 'Add a class' ?></h3><p>A class or subject, with its teacher or instructor.</p></div></div>
        <div class="card-body form-stack">
            <div class="field<?= isset($errors['code']) ? ' has-error' : '' ?>">
                <label for="code">Code</label>
                <input id="code" name="code" value="<?= e($form['code']) ?>" required maxlength="20" placeholder="e.g. MATH101">
                <?php if (isset($errors['code'])): ?><p class="field-error"><?= e($errors['code']) ?></p><?php endif; ?>
            </div>
            <div class="field<?= isset($errors['name']) ? ' has-error' : '' ?>">
                <label for="name">Class / Subject name</label>
                <input id="name" name="name" value="<?= e($form['name']) ?>" required maxlength="120" placeholder="e.g. College Algebra">
                <?php if (isset($errors['name'])): ?><p class="field-error"><?= e($errors['name']) ?></p><?php endif; ?>
            </div>
            <div class="field">
                <label for="schedule">Schedule <span class="muted">(optional)</span></label>
                <input id="schedule" name="schedule" value="<?= e($form['schedule']) ?>" maxlength="100" placeholder="e.g. Mon/Wed · 9:00–10:30 AM">
            </div>
            <div class="field<?= isset($errors['teacher_id']) ? ' has-error' : '' ?>">
                <label for="teacher_id">Teacher / Instructor</label>
                <select id="teacher_id" name="teacher_id">
                    <option value="0">— Not assigned —</option>
                    <?php foreach (instructors() as $i): ?>
                        <option value="<?= (int) $i['id'] ?>"<?= selected($i['id'], $form['teacher_id']) ?>><?= e($i['full_name']) ?><?= $i['role'] === 'admin' ? ' (admin)' : '' ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($errors['teacher_id'])): ?><p class="field-error"><?= e($errors['teacher_id']) ?></p><?php endif; ?>
                <p class="field-hint">Teachers see and record attendance only for classes assigned to them. <a href="<?= e(page('users.php')) ?>">Add a teacher</a></p>
            </div>
        </div>
        <div class="card-foot">
            <?php if ($form['id']): ?><a class="btn btn-ghost" href="<?= e(page('subjects.php')) ?>">Cancel</a><?php else: ?><span></span><?php endif; ?>
            <button class="btn btn-primary" type="submit"><?= icon($form['id'] ? 'check' : 'plus') ?> <?= $form['id'] ? 'Save changes' : 'Add class' ?></button>
        </div>
    </form>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>

<?php
require __DIR__ . '/../includes/bootstrap.php';
$me = require_login();

$return = safe_return(input('return'));
$ids    = scoped_subject_ids();
$stmt   = db()->prepare('SELECT a.*, st.student_no, st.first_name, st.last_name, st.section,
        sub.code, sub.name AS subject_name, sub.teacher_id AS class_teacher_id
    FROM attendance a
    JOIN students st ON st.id = a.student_id
    JOIN subjects sub ON sub.id = a.subject_id
    WHERE a.id = ? AND a.subject_id IN (' . in_list($ids) . ')');
$stmt->execute(array_merge([input_int('id')], $ids));
$record = $stmt->fetch();
if (!$record) {
    flash('error', 'That attendance record could not be found.');
    redirect('records.php' . $return);
}

$form   = [
    'status'     => $record['status'],
    'date'       => $record['attendance_date'],
    'time'       => substr($record['attendance_time'], 0, 5),
    'teacher_id' => (int) $record['teacher_id'],
    'remarks'    => (string) $record['remarks'],
];
$errors = [];

if (is_post()) {
    verify_csrf();
    $form = [
        'status'     => input('status'),
        'date'       => input('date'),
        'time'       => input('time'),
        'teacher_id' => input_int('teacher_id'),
        'remarks'    => clean_text(input('remarks'), 255),
    ];
    if (!in_array($form['status'], STATUSES, true)) {
        $errors['status'] = 'Choose Present, Late or Absent.';
    }
    if (!valid_date($form['date']) || $form['date'] > today()) {
        $errors['date'] = 'Enter a valid date that is not in the future.';
    }
    if (!valid_time($form['time'])) {
        $errors['time'] = 'Enter a valid time.';
    }

    if (!$errors) {
        $teacherId = resolve_instructor($form['teacher_id'], ['teacher_id' => $record['class_teacher_id']]);
        try {
            db()->prepare('UPDATE attendance SET status = ?, attendance_date = ?, attendance_time = ?, teacher_id = ?, remarks = ?, recorded_by = ? WHERE id = ?')
                ->execute([$form['status'], $form['date'], normalize_time($form['time']), $teacherId,
                    $form['remarks'] !== '' ? $form['remarks'] : null, $me['id'], $record['id']]);
            flash('success', 'Attendance record updated for ' . student_name($record) . '.');
            redirect('records.php' . $return);
        } catch (PDOException $e) {
            if ($e->getCode() !== '23000') {
                throw $e;
            }
            $errors['date'] = 'This student already has a mark in ' . $record['code'] . ' on that date. Edit that record instead.';
        }
    }
}

$pageTitle = 'Edit Attendance Record';
$activeNav = 'records';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-actions">
    <a class="btn btn-light btn-sm" href="<?= e(page('records.php')) ?><?= e($return) ?>"><?= icon('arrow-left') ?> Back to records</a>
</div>

<form method="post" class="card" style="max-width:720px" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int) $record['id'] ?>">
    <input type="hidden" name="return" value="<?= e($return) ?>">
    <div class="card-head">
        <div>
            <h3><?= e(student_name($record)) ?> <span class="muted mono">· <?= e($record['student_no']) ?></span></h3>
            <p><?= e($record['code'] . ' — ' . $record['subject_name']) ?><?= $record['section'] ? ' · ' . e($record['section']) : '' ?></p>
        </div>
        <?= status_badge($record['status']) ?>
    </div>
    <div class="card-body form-grid">
        <div class="field span-2">
            <span class="field-label">Attendance status</span>
            <div class="seg seg-lg" role="radiogroup" aria-label="Attendance status">
                <?php foreach (STATUSES as $s): ?>
                    <label class="seg-<?= strtolower($s) ?>"><input type="radio" name="status" value="<?= $s ?>"<?= checked($form['status'] === $s) ?>><span><?= $s ?></span></label>
                <?php endforeach; ?>
            </div>
            <?php if (isset($errors['status'])): ?><p class="field-error"><?= e($errors['status']) ?></p><?php endif; ?>
        </div>
        <div class="field<?= isset($errors['date']) ? ' has-error' : '' ?>">
            <label for="date">Date</label>
            <input type="date" id="date" name="date" value="<?= e($form['date']) ?>" max="<?= e(today()) ?>" required>
            <?php if (isset($errors['date'])): ?><p class="field-error"><?= e($errors['date']) ?></p><?php endif; ?>
        </div>
        <div class="field<?= isset($errors['time']) ? ' has-error' : '' ?>">
            <label for="time">Time</label>
            <input type="time" id="time" name="time" value="<?= e($form['time']) ?>" required>
            <?php if (isset($errors['time'])): ?><p class="field-error"><?= e($errors['time']) ?></p><?php endif; ?>
        </div>
        <div class="field span-2">
            <label for="teacher_id">Teacher / Instructor</label>
            <?php if (is_admin()): ?>
                <select id="teacher_id" name="teacher_id">
                    <option value="0">Class instructor (automatic)</option>
                    <?php foreach (instructors() as $i): ?>
                        <option value="<?= (int) $i['id'] ?>"<?= selected($i['id'], $form['teacher_id']) ?>><?= e($i['full_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            <?php else: ?>
                <input id="teacher_id" value="<?= e($me['full_name']) ?>" readonly>
            <?php endif; ?>
        </div>
        <div class="field span-2">
            <label for="remarks">Remarks</label>
            <input id="remarks" name="remarks" value="<?= e($form['remarks']) ?>" maxlength="255">
        </div>
        <p class="muted small span-2">First recorded <?= e(fmt_datetime($record['created_at'])) ?> · last updated <?= e(fmt_datetime($record['updated_at'])) ?></p>
    </div>
    <div class="card-foot">
        <a class="btn btn-ghost" href="<?= e(page('records.php')) ?><?= e($return) ?>">Cancel</a>
        <button class="btn btn-primary" type="submit"><?= icon('check') ?> Save changes</button>
    </div>
</form>
<?php require __DIR__ . '/../includes/footer.php'; ?>

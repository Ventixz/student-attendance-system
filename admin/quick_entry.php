<?php
/**
 * Mark a single student by Student ID: the input form described in the system requirements
 * (Student ID, name, class/subject, status, teacher, plus date and time).
 */
require __DIR__ . '/../includes/bootstrap.php';
$me = require_login();

$subjects = accessible_subjects();
$students = accessible_students();
$errors   = [];

$date = input('date', today());
$form = [
    'student_no' => '',
    'subject_id' => input_int('subject_id'),
    'status'     => 'Present',
    'teacher_id' => 0,
    'date'       => valid_date($date) && $date <= today() ? $date : today(),
    'time'       => date('H:i'),
    'remarks'    => '',
];

if (is_post()) {
    verify_csrf();
    $form = [
        'student_no' => clean_text(input('student_no'), 30),
        'subject_id' => input_int('subject_id'),
        'status'     => input('status'),
        'teacher_id' => input_int('teacher_id'),
        'date'       => input('date'),
        'time'       => input('time'),
        'remarks'    => clean_text(input('remarks'), 255),
    ];

    $student = $form['student_no'] !== '' ? find_student_by_no($form['student_no']) : null;
    $subject = find_accessible_subject($form['subject_id']);

    if (!$student) {
        $errors['student_no'] = $form['student_no'] === '' ? 'Enter a Student ID.' : 'No student has this ID. Check it, or add the student first.';
    }
    if (!$subject) {
        $errors['subject_id'] = 'Choose a class.';
    }
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
        $enroll = db()->prepare('INSERT IGNORE INTO enrollments (student_id, subject_id) VALUES (?, ?)');
        $enroll->execute([$student['id'], $subject['id']]);
        $newlyEnrolled = $enroll->rowCount() > 0;

        $result = save_attendance([
            'student_id' => (int) $student['id'],
            'subject_id' => (int) $subject['id'],
            'teacher_id' => resolve_instructor($form['teacher_id'], $subject),
            'date'       => $form['date'],
            'time'       => normalize_time($form['time']),
            'status'     => $form['status'],
            'remarks'    => $form['remarks'],
        ]);

        $message = sprintf('%s marked %s in %s on %s at %s.', student_name($student), $form['status'],
            $subject['code'], fmt_date($form['date']), fmt_time($form['time']));
        if ($result === 'updated') {
            $message .= ' This replaced the earlier mark for that day.';
        }
        flash('success', $message);
        if ($newlyEnrolled) {
            flash('info', student_name($student) . ' was not enrolled in ' . $subject['code'] . ' and has been added to the class.');
        }
        redirect('quick_entry.php?' . http_build_query(['subject_id' => $subject['id'], 'date' => $form['date']]));
    }
}

// Student ID → name lookup for the auto-filled name field
$studentMap = [];
foreach ($students as $s) {
    $studentMap[$s['student_no']] = student_name($s) . ($s['section'] ? ' · ' . $s['section'] : '');
}

$stmt = db()->prepare('SELECT a.status, a.attendance_date, a.attendance_time, st.id AS student_id, st.student_no, st.first_name, st.last_name, sub.code
    FROM attendance a
    JOIN students st ON st.id = a.student_id
    JOIN subjects sub ON sub.id = a.subject_id
    WHERE a.recorded_by = ?
    ORDER BY a.updated_at DESC, a.id DESC
    LIMIT 10');
$stmt->execute([$me['id']]);
$recent = $stmt->fetchAll();

$pageTitle = 'Quick Entry';
$activeNav = 'quick';
require __DIR__ . '/../includes/header.php';
?>
<div class="grid-side">
    <form method="post" class="card" novalidate>
        <?= csrf_field() ?>
        <div class="card-head">
            <div><h3>Record attendance for one student</h3><p>Type or pick a Student ID. The name fills in automatically.</p></div>
        </div>
        <div class="card-body form-grid">
            <div class="field<?= isset($errors['student_no']) ? ' has-error' : '' ?>">
                <label for="student_no">Student ID</label>
                <input id="student_no" name="student_no" list="student-list" value="<?= e($form['student_no']) ?>" required maxlength="30" autocomplete="off" autofocus>
                <datalist id="student-list">
                    <?php foreach ($studentMap as $no => $label): ?>
                        <option value="<?= e($no) ?>"><?= e($label) ?></option>
                    <?php endforeach; ?>
                </datalist>
                <?php if (isset($errors['student_no'])): ?><p class="field-error"><?= e($errors['student_no']) ?></p><?php endif; ?>
                <p class="field-hint" id="student_hint"></p>
            </div>
            <div class="field">
                <label for="student_name">Student name</label>
                <input id="student_name" readonly tabindex="-1" placeholder="Filled in from the Student ID">
            </div>

            <div class="field span-2<?= isset($errors['subject_id']) ? ' has-error' : '' ?>">
                <label for="subject_id">Class / Subject</label>
                <select id="subject_id" name="subject_id" required>
                    <?= subject_options($subjects, $form['subject_id'], 'Choose a class…') ?>
                </select>
                <?php if (isset($errors['subject_id'])): ?><p class="field-error"><?= e($errors['subject_id']) ?></p><?php endif; ?>
            </div>

            <div class="field span-2">
                <span class="field-label">Attendance status</span>
                <div class="seg seg-lg" role="radiogroup" aria-label="Attendance status">
                    <?php foreach (STATUSES as $s): ?>
                        <label class="seg-<?= strtolower($s) ?>">
                            <input type="radio" name="status" value="<?= $s ?>"<?= checked($form['status'] === $s) ?>>
                            <span><?= $s ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <?php if (isset($errors['status'])): ?><p class="field-error"><?= e($errors['status']) ?></p><?php endif; ?>
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

            <div class="field<?= isset($errors['date']) ? ' has-error' : '' ?>">
                <label for="date">Date</label>
                <input type="date" id="date" name="date" value="<?= e($form['date']) ?>" max="<?= e(today()) ?>" required>
                <?php if (isset($errors['date'])): ?><p class="field-error"><?= e($errors['date']) ?></p><?php endif; ?>
            </div>
            <div class="field<?= isset($errors['time']) ? ' has-error' : '' ?>">
                <label for="time">Time</label>
                <input type="time" id="time" name="time" value="<?= e(substr($form['time'], 0, 5)) ?>" required>
                <?php if (isset($errors['time'])): ?><p class="field-error"><?= e($errors['time']) ?></p><?php endif; ?>
            </div>

            <div class="field span-2">
                <label for="remarks">Remarks <span class="muted">(optional)</span></label>
                <input id="remarks" name="remarks" value="<?= e($form['remarks']) ?>" maxlength="255" placeholder="e.g. Excused, arrived with a note">
            </div>
        </div>
        <div class="card-foot">
            <span class="muted small">If the student already has a mark for this class and date, it will be updated.</span>
            <button class="btn btn-primary" type="submit"><?= icon('check') ?> Save attendance</button>
        </div>
    </form>

    <div class="card sticky">
        <div class="card-head"><div><h3>Your recent entries</h3><p>Last 10 marks you saved</p></div></div>
        <?php if (!$recent): ?>
            <div class="empty"><?= icon('zap') ?><h3>Nothing yet</h3><p>Saved marks will appear here.</p></div>
        <?php else: ?>
            <ul class="class-list">
                <?php foreach ($recent as $r): ?>
                    <li>
                        <div class="class-info">
                            <strong><?= e(student_name($r)) ?></strong>
                            <span><?= e($r['code']) ?> · <?= e(fmt_date($r['attendance_date'])) ?> · <?= e(fmt_time($r['attendance_time'])) ?></span>
                        </div>
                        <?= status_badge($r['status']) ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>
<script type="application/json" id="students-data"><?= json_encode($studentMap, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<?php require __DIR__ . '/../includes/footer.php'; ?>

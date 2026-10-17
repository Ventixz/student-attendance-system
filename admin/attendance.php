<?php
/**
 * Take attendance for a whole class on one date. Re-opening a class/date loads the saved marks,
 * so the same page is used to correct them.
 */
require __DIR__ . '/../includes/bootstrap.php';
$me = require_login();

$subjects  = accessible_subjects();
$subjectId = input_int('subject_id');
$date      = input('date', today());
if (!valid_date($date) || $date > today()) {
    $date = today();
}
$subject = $subjectId ? find_accessible_subject($subjectId) : null;
if ($subjectId && !$subject) {
    flash('error', 'That class does not exist or is not assigned to you.');
    redirect('attendance.php');
}

if (is_post() && $subject) {
    verify_csrf();
    $teacherId = resolve_instructor(input_int('teacher_id'), $subject);
    $statuses  = (array) ($_POST['status'] ?? []);
    $remarks   = (array) ($_POST['remarks'] ?? []);

    $stmt = db()->prepare('SELECT student_id FROM enrollments WHERE subject_id = ?');
    $stmt->execute([$subject['id']]);
    $enrolled = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $now   = date('H:i:s');
    $tally = ['created' => 0, 'updated' => 0, 'unchanged' => 0, 'skipped' => 0];
    db()->beginTransaction();
    foreach ($enrolled as $studentId) {
        $status = $statuses[$studentId] ?? '';
        if (!in_array($status, STATUSES, true)) {
            $tally['skipped']++;
            continue;
        }
        $result = save_attendance([
            'student_id' => (int) $studentId,
            'subject_id' => (int) $subject['id'],
            'teacher_id' => $teacherId,
            'date'       => $date,
            'time'       => $now,
            'status'     => $status,
            'remarks'    => clean_text($remarks[$studentId] ?? '', 255),
        ], true);
        $tally[$result]++;
    }
    db()->commit();

    $parts = [];
    foreach (['created' => 'new', 'updated' => 'updated', 'unchanged' => 'unchanged'] as $key => $word) {
        if ($tally[$key]) {
            $parts[] = $tally[$key] . ' ' . $word;
        }
    }
    flash('success', sprintf('Attendance saved for %s on %s', $subject['code'], fmt_date($date)) . ($parts ? ' (' . implode(', ', $parts) . ').' : '.'));
    if ($tally['skipped']) {
        flash('warning', $tally['skipped'] . ' student(s) were left unmarked. Mark them and save again when ready.');
    }
    redirect('attendance.php?' . http_build_query(['subject_id' => $subject['id'], 'date' => $date]));
}

$roster    = [];
$teacherId = 0;
if ($subject) {
    $stmt = db()->prepare('SELECT st.id, st.student_no, st.first_name, st.last_name, st.section,
            a.status, a.attendance_time, a.remarks, a.teacher_id
        FROM enrollments en
        JOIN students st ON st.id = en.student_id
        LEFT JOIN attendance a ON a.student_id = st.id AND a.subject_id = en.subject_id AND a.attendance_date = ?
        WHERE en.subject_id = ?
        ORDER BY st.last_name, st.first_name');
    $stmt->execute([$date, $subject['id']]);
    $roster = $stmt->fetchAll();

    // Default instructor: whoever was recorded on this day's marks, else the class instructor.
    foreach ($roster as $r) {
        if ($r['teacher_id']) {
            $teacherId = (int) $r['teacher_id'];
            break;
        }
    }
    $teacherId = $teacherId ?: (int) $subject['teacher_id'];
}

$pageTitle = 'Take Attendance';
$activeNav = 'take';
require __DIR__ . '/../includes/header.php';
?>
<div class="card">
    <form class="filters" method="get">
        <div class="field grow">
            <label for="subject_id">Class / Subject</label>
            <select id="subject_id" name="subject_id" required>
                <?= subject_options($subjects, $subjectId, 'Choose a class…') ?>
            </select>
        </div>
        <div class="field narrow">
            <label for="date">Date</label>
            <input type="date" id="date" name="date" value="<?= e($date) ?>" max="<?= e(today()) ?>" required>
        </div>
        <div class="filter-actions">
            <button class="btn btn-primary" type="submit"><?= icon('users') ?> Load class list</button>
        </div>
    </form>
</div>

<?php if (!$subjects): ?>
    <div class="card">
        <div class="empty"><?= icon('book') ?>
            <h3>No classes available</h3>
            <?php if (is_admin()): ?>
                <p>Create a class and enroll students before taking attendance.</p>
                <a class="btn btn-primary" href="<?= e(page('subjects.php')) ?>"><?= icon('plus') ?> Add a class</a>
            <?php else: ?>
                <p>No classes are assigned to you yet. Ask an administrator to assign you as instructor.</p>
            <?php endif; ?>
        </div>
    </div>

<?php elseif (!$subject): ?>
    <div class="card">
        <div class="empty"><?= icon('check') ?>
            <h3>Choose a class and date</h3>
            <p>The class list will load with each student's status for that day.</p>
        </div>
    </div>

<?php elseif (!$roster): ?>
    <div class="card">
        <div class="empty"><?= icon('users') ?>
            <h3>No students in <?= e($subject['code']) ?> yet</h3>
            <?php if (is_admin()): ?>
                <p>Enroll students in this class first.</p>
                <a class="btn btn-primary" href="<?= e(page('enroll.php')) ?>?subject_id=<?= (int) $subject['id'] ?>"><?= icon('plus') ?> Enroll students</a>
            <?php else: ?>
                <p>Ask an administrator to enroll students in this class.</p>
            <?php endif; ?>
        </div>
    </div>

<?php else: ?>
    <form method="post" class="card" data-roster>
        <?= csrf_field() ?>
        <input type="hidden" name="subject_id" value="<?= (int) $subject['id'] ?>">
        <input type="hidden" name="date" value="<?= e($date) ?>">

        <div class="card-head">
            <div>
                <h3><?= e($subject['code'] . ' — ' . $subject['name']) ?></h3>
                <p><?= e(fmt_day($date)) ?><?= $subject['schedule'] ? ' · ' . e($subject['schedule']) : '' ?> · <?= count($roster) ?> students</p>
            </div>
            <div class="chips" aria-live="polite">
                <span class="chip present">Present <b data-tally="Present">0</b></span>
                <span class="chip late">Late <b data-tally="Late">0</b></span>
                <span class="chip absent">Absent <b data-tally="Absent">0</b></span>
                <span class="chip">Unmarked <b data-tally="unmarked">0</b></span>
            </div>
        </div>

        <div class="toolbar">
            <span class="muted small">Quick mark:</span>
            <button type="button" class="btn btn-light btn-sm" data-mark="unmarked-present">Unmarked → Present</button>
            <button type="button" class="btn btn-light btn-sm" data-mark="Present">All Present</button>
            <button type="button" class="btn btn-light btn-sm" data-mark="Absent">All Absent</button>
            <button type="button" class="btn btn-ghost btn-sm" data-mark="clear">Clear</button>
            <span class="spacer"></span>
            <div class="field">
                <label for="teacher_id">Instructor</label>
                <?php if (is_admin()): ?>
                    <select id="teacher_id" name="teacher_id">
                        <?php if (!$teacherId): ?><option value="0">— Not set —</option><?php endif; ?>
                        <?php foreach (instructors() as $i): ?>
                            <option value="<?= (int) $i['id'] ?>"<?= selected($i['id'], $teacherId) ?>><?= e($i['full_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php else: ?>
                    <strong><?= e($me['full_name']) ?></strong>
                <?php endif; ?>
            </div>
        </div>

        <div class="table-wrap">
            <table class="table roster">
                <thead>
                <tr><th class="num">#</th><th>Student ID</th><th>Student name</th><th>Section</th><th>Status</th><th>Remarks</th><th>Time</th></tr>
                </thead>
                <tbody>
                <?php foreach ($roster as $n => $r): ?>
                    <tr>
                        <td class="num muted"><?= $n + 1 ?></td>
                        <td class="mono"><?= e($r['student_no']) ?></td>
                        <td class="cell-strong"><?= e(student_name($r)) ?></td>
                        <td><?= e($r['section'] ?: '—') ?></td>
                        <td>
                            <div class="seg" role="radiogroup" aria-label="Status for <?= e(student_name($r)) ?>">
                                <?php foreach (STATUSES as $s): ?>
                                    <label class="seg-<?= strtolower($s) ?>">
                                        <input type="radio" name="status[<?= (int) $r['id'] ?>]" value="<?= $s ?>"<?= checked($r['status'] === $s) ?>>
                                        <span><?= $s ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </td>
                        <td><input class="input-sm" name="remarks[<?= (int) $r['id'] ?>]" value="<?= e($r['remarks'] ?? '') ?>" maxlength="255" placeholder="Optional" aria-label="Remarks for <?= e(student_name($r)) ?>"></td>
                        <td class="nowrap muted"><?= $r['attendance_time'] ? e(fmt_time($r['attendance_time'])) : '—' ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="card-foot save-bar">
            <span class="muted small">The date and current time are saved with each mark. Unmarked students are skipped.</span>
            <button type="submit" class="btn btn-primary"><?= icon('check') ?> Save attendance</button>
        </div>
    </form>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>

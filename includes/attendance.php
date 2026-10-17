<?php
/**
 * Data helpers shared by the attendance pages and reports.
 * Teachers only ever see the classes assigned to them; admins see everything.
 */

const STATUSES = ['Present', 'Late', 'Absent'];

/** Classes the signed-in user may work with: all for admins, their own for teachers. */
function accessible_subjects(): array
{
    static $cache = null;
    if ($cache === null) {
        $sql = 'SELECT sub.id, sub.code, sub.name, sub.schedule, sub.teacher_id, u.full_name AS teacher_name
                FROM subjects sub
                LEFT JOIN users u ON u.id = sub.teacher_id';
        $params = [];
        if (!is_admin()) {
            $sql     .= ' WHERE sub.teacher_id = ?';
            $params[] = current_user()['id'];
        }
        $stmt = db()->prepare($sql . ' ORDER BY sub.code');
        $stmt->execute($params);
        $cache = $stmt->fetchAll();
    }
    return $cache;
}

function find_accessible_subject(int $id): ?array
{
    foreach (accessible_subjects() as $subject) {
        if ((int) $subject['id'] === $id) {
            return $subject;
        }
    }
    return null;
}

/** IDs of the classes the user may see, optionally narrowed to one of them. */
function scoped_subject_ids(int $only = 0): array
{
    $ids = array_map('intval', array_column(accessible_subjects(), 'id'));
    if ($only) {
        $ids = in_array($only, $ids, true) ? [$only] : [];
    }
    return $ids;
}

/** Placeholders for an SQL IN (...) list; "NULL" for an empty list so it matches nothing. */
function in_list(array $values): string
{
    return $values ? implode(',', array_fill(0, count($values), '?')) : 'NULL';
}

/** SQL aggregate counting attended sessions (Late counts when LATE_COUNTS_AS_PRESENT is on). */
function attended_sql(string $column = 'a.status'): string
{
    return LATE_COUNTS_AS_PRESENT ? "SUM($column IN ('Present','Late'))" : "SUM($column = 'Present')";
}

/** Attendance percentage, or null when there are no records yet. */
function attendance_pct(int $present, int $late, int $total): ?float
{
    if ($total === 0) {
        return null;
    }
    $attended = $present + (LATE_COUNTS_AS_PRESENT ? $late : 0);
    return round($attended / $total * 100, 1);
}

function pct_label(?float $pct): string
{
    if ($pct === null) {
        return '—';
    }
    return rtrim(rtrim(number_format($pct, 1), '0'), '.') . '%';
}

/** Coloured progress bar for an attendance percentage. */
function pct_bar(?float $pct): string
{
    if ($pct === null) {
        return '<span class="muted">No records</span>';
    }
    $level = $pct >= 90 ? 'good' : ($pct >= LOW_ATTENDANCE_THRESHOLD ? 'ok' : 'low');
    return '<div class="pct"><div class="pct-track"><div class="pct-fill pct-' . $level . '" style="width:' . min(100, $pct) . '%"></div></div>'
        . '<span class="pct-num">' . pct_label($pct) . '</span></div>';
}

function standing_badge(?float $pct): string
{
    if ($pct === null) {
        return '<span class="badge badge-none">No records</span>';
    }
    return $pct >= LOW_ATTENDANCE_THRESHOLD
        ? '<span class="badge badge-present">Good standing</span>'
        : '<span class="badge badge-absent">Below ' . LOW_ATTENDANCE_THRESHOLD . '%</span>';
}

function status_badge(?string $status): string
{
    if ($status === null || $status === '') {
        return '<span class="badge badge-none">Not marked</span>';
    }
    return '<span class="badge badge-' . e(strtolower($status)) . '">' . e($status) . '</span>';
}

/** Active staff accounts that can be set as a class instructor. */
function instructors(): array
{
    static $cache = null;
    if ($cache === null) {
        $cache = db()->query('SELECT id, full_name, role FROM users WHERE is_active = 1 ORDER BY full_name')->fetchAll();
    }
    return $cache;
}

/** Instructor stored on a mark: teachers always record as themselves; admins may pick any active user. */
function resolve_instructor(int $requested, array $subject): ?int
{
    if (!is_admin()) {
        return (int) current_user()['id'];
    }
    if ($requested) {
        foreach (instructors() as $i) {
            if ((int) $i['id'] === $requested) {
                return $requested;
            }
        }
    }
    return $subject['teacher_id'] !== null ? (int) $subject['teacher_id'] : null;
}

function all_sections(): array
{
    return db()->query("SELECT DISTINCT section FROM students WHERE section IS NOT NULL AND section <> '' ORDER BY section")
        ->fetchAll(PDO::FETCH_COLUMN);
}

function find_student_by_no(string $studentNo): ?array
{
    $stmt = db()->prepare('SELECT * FROM students WHERE student_no = ?');
    $stmt->execute([$studentNo]);
    return $stmt->fetch() ?: null;
}

/** Students visible to the user: everyone for admins, students in their classes for teachers. */
function accessible_students(): array
{
    if (is_admin()) {
        return db()->query('SELECT id, student_no, first_name, last_name, section FROM students ORDER BY last_name, first_name')->fetchAll();
    }
    $ids  = scoped_subject_ids();
    $stmt = db()->prepare('SELECT DISTINCT st.id, st.student_no, st.first_name, st.last_name, st.section
        FROM students st
        JOIN enrollments en ON en.student_id = st.id
        WHERE en.subject_id IN (' . in_list($ids) . ')
        ORDER BY st.last_name, st.first_name');
    $stmt->execute($ids);
    return $stmt->fetchAll();
}

function can_view_student(int $studentId): bool
{
    if (is_admin()) {
        return true;
    }
    $ids  = scoped_subject_ids();
    $in   = in_list($ids);
    $stmt = db()->prepare("SELECT 1 FROM enrollments WHERE student_id = ? AND subject_id IN ($in)
        UNION SELECT 1 FROM attendance WHERE student_id = ? AND subject_id IN ($in) LIMIT 1");
    $stmt->execute(array_merge([$studentId], $ids, [$studentId], $ids));
    return (bool) $stmt->fetchColumn();
}

/**
 * Saves one attendance mark. A student has at most one mark per class per day,
 * so saving again updates that day's record.
 *
 * $mark keys: student_id, subject_id, teacher_id, date (Y-m-d), time (H:i:s), status, remarks.
 * With $keepTimeIfSameStatus, re-saving an unchanged status keeps the original time stamp.
 *
 * @return string 'created', 'updated' or 'unchanged'
 */
function save_attendance(array $mark, bool $keepTimeIfSameStatus = false): string
{
    $find = db()->prepare('SELECT id, status, attendance_time, remarks, teacher_id FROM attendance
        WHERE student_id = ? AND subject_id = ? AND attendance_date = ?');
    $find->execute([$mark['student_id'], $mark['subject_id'], $mark['date']]);
    $existing = $find->fetch();

    $remarks    = $mark['remarks'] !== '' ? $mark['remarks'] : null;
    $recordedBy = (int) current_user()['id'];

    if (!$existing) {
        db()->prepare('INSERT INTO attendance
                (student_id, subject_id, teacher_id, attendance_date, attendance_time, status, remarks, recorded_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([$mark['student_id'], $mark['subject_id'], $mark['teacher_id'], $mark['date'],
                $mark['time'], $mark['status'], $remarks, $recordedBy]);
        return 'created';
    }

    $time = ($keepTimeIfSameStatus && $existing['status'] === $mark['status']) ? $existing['attendance_time'] : $mark['time'];
    if ($existing['status'] === $mark['status'] && $existing['attendance_time'] === $time
        && $existing['remarks'] === $remarks && (int) $existing['teacher_id'] === (int) $mark['teacher_id']) {
        return 'unchanged';
    }

    db()->prepare('UPDATE attendance SET status = ?, attendance_time = ?, remarks = ?, teacher_id = ?, recorded_by = ? WHERE id = ?')
        ->execute([$mark['status'], $time, $remarks, $mark['teacher_id'], $recordedBy, $existing['id']]);
    return 'updated';
}

// --- Shared form controls --------------------------------------------------

function subject_options(array $subjects, int $selectedId, ?string $blankLabel = null): string
{
    $html = $blankLabel !== null ? '<option value="">' . e($blankLabel) . '</option>' : '';
    foreach ($subjects as $s) {
        $html .= '<option value="' . (int) $s['id'] . '"' . selected($s['id'], $selectedId) . '>'
            . e($s['code'] . ' — ' . $s['name']) . '</option>';
    }
    return $html;
}

function section_options(string $selected): string
{
    $html = '<option value="">All sections</option>';
    foreach (all_sections() as $section) {
        $html .= '<option value="' . e($section) . '"' . selected($section, $selected) . '>' . e($section) . '</option>';
    }
    return $html;
}

/** Report heading that only appears on paper. */
function print_header(string $title, string $subtitle = ''): string
{
    return '<div class="print-only print-header"><p class="print-school">' . e(SCHOOL_NAME) . ' · ' . e(APP_NAME) . '</p>'
        . '<h2>' . e($title) . '</h2>' . ($subtitle !== '' ? '<p>' . e($subtitle) . '</p>' : '')
        . '<p class="print-meta">Generated ' . e(date('M j, Y g:i A')) . ' by ' . e(current_user()['full_name'] ?? '') . '</p></div>';
}

<?php
/**
 * Staff accounts (admin only): administrators and teachers/instructors.
 */
require __DIR__ . '/../includes/bootstrap.php';
$me = require_admin();

function other_active_admins(int $excludeId): int
{
    $stmt = db()->prepare("SELECT COUNT(*) FROM users WHERE role = 'admin' AND is_active = 1 AND id <> ?");
    $stmt->execute([$excludeId]);
    return (int) $stmt->fetchColumn();
}

$blank  = ['id' => 0, 'username' => '', 'full_name' => '', 'email' => '', 'role' => 'teacher', 'is_active' => 1];
$form   = $blank;
$errors = [];

if (is_post()) {
    verify_csrf();

    if (input('action') === 'delete') {
        $id   = input_int('id');
        $stmt = db()->prepare('SELECT COUNT(*) FROM attendance WHERE teacher_id = ? OR recorded_by = ?');
        $stmt->execute([$id, $id]);
        if ($id === (int) $me['id']) {
            flash('error', 'You cannot delete your own account.');
        } elseif ((int) $stmt->fetchColumn() > 0) {
            flash('error', 'This account appears on attendance records, so it can\'t be deleted. Edit it and untick "Active" to block sign-in instead.');
        } else {
            db()->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
            flash('success', 'Staff account deleted. Their classes are now unassigned.');
        }
        redirect('users.php');
    }

    $form = [
        'id'        => input_int('id'),
        'username'  => input('username'),
        'full_name' => clean_text(input('full_name'), 100),
        'email'     => clean_text(input('email'), 120),
        'role'      => input('role') === 'admin' ? 'admin' : 'teacher',
        'is_active' => isset($_POST['is_active']) ? 1 : 0,
    ];
    $password = (string) ($_POST['password'] ?? '');
    $isSelf   = $form['id'] === (int) $me['id'];
    if ($isSelf) {
        // Prevent locking yourself out.
        $form['role']      = 'admin';
        $form['is_active'] = 1;
    }

    if (!preg_match('/^[A-Za-z0-9._-]{3,50}$/', $form['username'])) {
        $errors['username'] = 'Use 3–50 letters, numbers, dots, dashes or underscores.';
    } else {
        $stmt = db()->prepare('SELECT id FROM users WHERE username = ? AND id <> ?');
        $stmt->execute([$form['username'], $form['id']]);
        if ($stmt->fetch()) {
            $errors['username'] = 'That username is taken.';
        }
    }
    if ($form['full_name'] === '') {
        $errors['full_name'] = 'Enter the full name.';
    }
    if ($form['email'] !== '' && !filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Enter a valid email or leave it blank.';
    }
    if (($form['id'] === 0 || $password !== '') && strlen($password) < 8) {
        $errors['password'] = 'Use at least 8 characters.';
    }
    if ($form['id'] && !$isSelf && ($form['role'] !== 'admin' || !$form['is_active']) && other_active_admins($form['id']) === 0) {
        $errors['role'] = 'At least one active administrator is required.';
    }

    if (!$errors) {
        $values = [$form['username'], $form['full_name'], $form['email'] ?: null, $form['role'], $form['is_active']];
        if ($form['id']) {
            db()->prepare('UPDATE users SET username = ?, full_name = ?, email = ?, role = ?, is_active = ? WHERE id = ?')
                ->execute(array_merge($values, [$form['id']]));
            if ($password !== '') {
                db()->prepare('UPDATE users SET password_hash = ?, failed_logins = 0, locked_until = NULL WHERE id = ?')
                    ->execute([password_hash($password, PASSWORD_DEFAULT), $form['id']]);
            }
            flash('success', "Updated {$form['full_name']}.");
        } else {
            db()->prepare('INSERT INTO users (username, full_name, email, role, is_active, password_hash) VALUES (?, ?, ?, ?, ?, ?)')
                ->execute(array_merge($values, [password_hash($password, PASSWORD_DEFAULT)]));
            flash('success', "Created an account for {$form['full_name']}. Share the username and password with them securely.");
        }
        redirect('users.php');
    }
} elseif (input_int('edit')) {
    $stmt = db()->prepare('SELECT id, username, full_name, email, role, is_active FROM users WHERE id = ?');
    $stmt->execute([input_int('edit')]);
    $row = $stmt->fetch();
    if (!$row) {
        flash('error', 'That account could not be found.');
        redirect('users.php');
    }
    $form = array_merge($row, ['id' => (int) $row['id'], 'email' => (string) $row['email'], 'is_active' => (int) $row['is_active']]);
}

$staff = db()->query('SELECT u.*, (SELECT COUNT(*) FROM subjects s WHERE s.teacher_id = u.id) AS classes
    FROM users u ORDER BY u.role, u.full_name')->fetchAll();
$isSelf = $form['id'] === (int) $me['id'];

$pageTitle = 'Staff Accounts';
$activeNav = 'users';
require __DIR__ . '/../includes/header.php';
?>
<div class="grid-side">
    <div class="card">
        <div class="card-head"><div><h3>Administrators &amp; teachers</h3><p>Admins manage everything. Teachers record and view attendance for their own classes.</p></div></div>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Name</th><th>Username</th><th>Role</th><th class="num">Classes</th><th>Status</th><th>Last sign-in</th><th class="actions">Actions</th></tr></thead>
                <tbody>
                <?php foreach ($staff as $u): ?>
                    <tr>
                        <td><span class="cell-strong"><?= e($u['full_name']) ?><?= (int) $u['id'] === (int) $me['id'] ? ' <span class="muted">(you)</span>' : '' ?></span><?php if ($u['email']): ?><span class="cell-sub"><?= e($u['email']) ?></span><?php endif; ?></td>
                        <td class="mono"><?= e($u['username']) ?></td>
                        <td><span class="badge badge-<?= e($u['role']) ?>"><?= $u['role'] === 'admin' ? 'Administrator' : 'Teacher' ?></span></td>
                        <td class="num"><?= (int) $u['classes'] ?></td>
                        <td><?= $u['is_active'] ? '<span class="badge badge-present">Active</span>' : '<span class="badge badge-none">Inactive</span>' ?></td>
                        <td class="nowrap muted"><?= e($u['last_login'] ? fmt_datetime($u['last_login']) : 'Never') ?></td>
                        <td class="actions">
                            <a class="btn btn-ghost btn-sm btn-icon" href="<?= e(page('users.php')) ?>?edit=<?= (int) $u['id'] ?>" title="Edit" aria-label="Edit account"><?= icon('edit') ?></a>
                            <?php if ((int) $u['id'] !== (int) $me['id']): ?>
                                <form method="post" data-confirm="Delete the account for <?= e($u['full_name']) ?>?">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                                    <button class="btn btn-ghost btn-sm btn-icon" type="submit" title="Delete" aria-label="Delete account"><?= icon('trash') ?></button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <form method="post" class="card sticky" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int) $form['id'] ?>">
        <div class="card-head"><div><h3><?= $form['id'] ? 'Edit account' : 'Add a staff account' ?></h3><p><?= $form['id'] ? 'Leave the password blank to keep it.' : 'They sign in at the staff login page.' ?></p></div></div>
        <div class="card-body form-stack">
            <div class="field<?= isset($errors['full_name']) ? ' has-error' : '' ?>">
                <label for="full_name">Full name</label>
                <input id="full_name" name="full_name" value="<?= e($form['full_name']) ?>" required maxlength="100">
                <?php if (isset($errors['full_name'])): ?><p class="field-error"><?= e($errors['full_name']) ?></p><?php endif; ?>
            </div>
            <div class="field<?= isset($errors['username']) ? ' has-error' : '' ?>">
                <label for="username">Username</label>
                <input id="username" name="username" value="<?= e($form['username']) ?>" required maxlength="50" autocomplete="off">
                <?php if (isset($errors['username'])): ?><p class="field-error"><?= e($errors['username']) ?></p><?php endif; ?>
            </div>
            <div class="field<?= isset($errors['email']) ? ' has-error' : '' ?>">
                <label for="email">Email <span class="muted">(optional)</span></label>
                <input id="email" name="email" type="email" value="<?= e($form['email']) ?>" maxlength="120">
                <?php if (isset($errors['email'])): ?><p class="field-error"><?= e($errors['email']) ?></p><?php endif; ?>
            </div>
            <div class="field<?= isset($errors['role']) ? ' has-error' : '' ?>">
                <label for="role">Role</label>
                <select id="role" name="role"<?= $isSelf ? ' disabled' : '' ?>>
                    <option value="teacher"<?= selected('teacher', $form['role']) ?>>Teacher / Instructor</option>
                    <option value="admin"<?= selected('admin', $form['role']) ?>>Administrator</option>
                </select>
                <?php if ($isSelf): ?><p class="field-hint">You can't change your own role.</p><?php endif; ?>
                <?php if (isset($errors['role'])): ?><p class="field-error"><?= e($errors['role']) ?></p><?php endif; ?>
            </div>
            <div class="field<?= isset($errors['password']) ? ' has-error' : '' ?>">
                <label for="password"><?= $form['id'] ? 'New password' : 'Password' ?></label>
                <input id="password" name="password" type="password" minlength="8" autocomplete="new-password" <?= $form['id'] ? 'placeholder="Leave blank to keep current"' : 'required' ?>>
                <?php if (isset($errors['password'])): ?><p class="field-error"><?= e($errors['password']) ?></p><?php endif; ?>
            </div>
            <?php if (!$isSelf): ?>
                <label class="check-row"><input type="checkbox" name="is_active" value="1"<?= checked((bool) $form['is_active']) ?>><span><strong>Active</strong><br><span class="muted small">Inactive accounts cannot sign in.</span></span></label>
            <?php endif; ?>
        </div>
        <div class="card-foot">
            <?php if ($form['id']): ?><a class="btn btn-ghost" href="<?= e(page('users.php')) ?>">Cancel</a><?php else: ?><span></span><?php endif; ?>
            <button class="btn btn-primary" type="submit"><?= icon($form['id'] ? 'check' : 'plus') ?> <?= $form['id'] ? 'Save changes' : 'Create account' ?></button>
        </div>
    </form>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>

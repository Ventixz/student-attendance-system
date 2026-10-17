<?php
require __DIR__ . '/../includes/bootstrap.php';
$me = require_login();

$profile = ['full_name' => $me['full_name'], 'email' => (string) $me['email']];
$errors  = [];

if (is_post()) {
    verify_csrf();

    if (input('action') === 'password') {
        $current = (string) ($_POST['current_password'] ?? '');
        $new     = (string) ($_POST['new_password'] ?? '');
        $confirm = (string) ($_POST['confirm_password'] ?? '');

        $stmt = db()->prepare('SELECT password_hash FROM users WHERE id = ?');
        $stmt->execute([$me['id']]);
        if (!password_verify($current, (string) $stmt->fetchColumn())) {
            $errors['current_password'] = 'Your current password is incorrect.';
        }
        if (strlen($new) < 8) {
            $errors['new_password'] = 'Use at least 8 characters.';
        } elseif ($new !== $confirm) {
            $errors['confirm_password'] = 'The new passwords do not match.';
        }
        if (!$errors) {
            db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([password_hash($new, PASSWORD_DEFAULT), $me['id']]);
            session_regenerate_id(true);
            flash('success', 'Your password has been changed.');
            redirect('profile.php');
        }
    } else {
        $profile = ['full_name' => clean_text(input('full_name'), 100), 'email' => clean_text(input('email'), 120)];
        if ($profile['full_name'] === '') {
            $errors['full_name'] = 'Enter your full name.';
        }
        if ($profile['email'] !== '' && !filter_var($profile['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Enter a valid email or leave it blank.';
        }
        if (!$errors) {
            db()->prepare('UPDATE users SET full_name = ?, email = ? WHERE id = ?')
                ->execute([$profile['full_name'], $profile['email'] ?: null, $me['id']]);
            flash('success', 'Your profile has been updated.');
            redirect('profile.php');
        }
    }
}

$pageTitle = 'My Profile';
$activeNav = 'profile';
require __DIR__ . '/../includes/header.php';
?>
<div class="grid-2" style="margin-top:0">
    <form method="post" class="card" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="profile">
        <div class="card-head"><div><h3>Profile</h3><p>Signed in as <span class="mono"><?= e($me['username']) ?></span> · <?= $me['role'] === 'admin' ? 'Administrator' : 'Teacher' ?></p></div></div>
        <div class="card-body form-stack">
            <div class="field<?= isset($errors['full_name']) ? ' has-error' : '' ?>">
                <label for="full_name">Full name</label>
                <input id="full_name" name="full_name" value="<?= e($profile['full_name']) ?>" required maxlength="100">
                <?php if (isset($errors['full_name'])): ?><p class="field-error"><?= e($errors['full_name']) ?></p><?php endif; ?>
            </div>
            <div class="field<?= isset($errors['email']) ? ' has-error' : '' ?>">
                <label for="email">Email</label>
                <input id="email" name="email" type="email" value="<?= e($profile['email']) ?>" maxlength="120">
                <?php if (isset($errors['email'])): ?><p class="field-error"><?= e($errors['email']) ?></p><?php endif; ?>
            </div>
        </div>
        <div class="card-foot"><span></span><button class="btn btn-primary" type="submit"><?= icon('check') ?> Save profile</button></div>
    </form>

    <form method="post" class="card" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="password">
        <div class="card-head"><div><h3>Change password</h3><p>Use at least 8 characters.</p></div></div>
        <div class="card-body form-stack">
            <div class="field<?= isset($errors['current_password']) ? ' has-error' : '' ?>">
                <label for="current_password">Current password</label>
                <input id="current_password" name="current_password" type="password" required autocomplete="current-password">
                <?php if (isset($errors['current_password'])): ?><p class="field-error"><?= e($errors['current_password']) ?></p><?php endif; ?>
            </div>
            <div class="field<?= isset($errors['new_password']) ? ' has-error' : '' ?>">
                <label for="new_password">New password</label>
                <input id="new_password" name="new_password" type="password" required minlength="8" autocomplete="new-password">
                <?php if (isset($errors['new_password'])): ?><p class="field-error"><?= e($errors['new_password']) ?></p><?php endif; ?>
            </div>
            <div class="field<?= isset($errors['confirm_password']) ? ' has-error' : '' ?>">
                <label for="confirm_password">Confirm new password</label>
                <input id="confirm_password" name="confirm_password" type="password" required minlength="8" autocomplete="new-password">
                <?php if (isset($errors['confirm_password'])): ?><p class="field-error"><?= e($errors['confirm_password']) ?></p><?php endif; ?>
            </div>
        </div>
        <div class="card-foot"><span></span><button class="btn btn-primary" type="submit"><?= icon('lock') ?> Change password</button></div>
    </form>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>

<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();

$user = current_user();
$error = '';
$saved = isset($_GET['updated']);
$username = (string)$user['username'];
$_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string)($_POST['username'] ?? ''));
    $currentPassword = (string)($_POST['current_password'] ?? '');
    $newPassword = (string)($_POST['new_password'] ?? '');
    $confirmation = (string)($_POST['password_confirmation'] ?? '');

    if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], (string)$_POST['csrf_token'])) {
        $error = 'Your form session expired. Refresh the page and try again.';
    } elseif ($username === '' || strlen($username) > 100) {
        $error = 'Enter a username between 1 and 100 characters.';
    } else {
        $account = $pdo->prepare('SELECT password_hash FROM users WHERE id=? AND is_active=1 LIMIT 1');
        $account->execute([(int)$user['id']]);
        $hash = $account->fetchColumn();

        if (!$hash || !password_verify($currentPassword, $hash)) {
            $error = 'Your current password is incorrect.';
        } elseif ($newPassword !== '' && strlen($newPassword) < 12) {
            $error = 'The new password must be at least 12 characters.';
        } elseif ($newPassword !== $confirmation) {
            $error = 'The new password and confirmation do not match.';
        } else {
            $newHash = $newPassword === '' ? $hash : password_hash($newPassword, PASSWORD_DEFAULT);
            try {
                $update = $pdo->prepare('UPDATE users SET username=?, password_hash=? WHERE id=?');
                $update->execute([$username, $newHash, (int)$user['id']]);
                $_SESSION['user']['username'] = $username;
                audit('UPDATE', 'user', (int)$user['id'], 'Account username/password updated');
                header('Location: ' . BASE_URL . '/account/settings.php?updated=1');
                exit;
            } catch (PDOException $exception) {
                $error = $exception->getCode() === '23000'
                    ? 'That username is already in use. Choose another one.'
                    : 'Your account details could not be updated. Please try again.';
            }
        }
    }
}

$page_title = 'Account Settings';
require __DIR__ . '/../includes/header.php';
?>
<div class="account-settings-wrap">
    <div class="account-settings-heading">
        <div class="settings-icon" aria-hidden="true">⚙</div>
        <div><p class="settings-eyebrow">YOUR PROFILE</p><h1>Account settings</h1><p>Update the sign-in details for your portal account.</p></div>
    </div>
    <?php if ($saved): ?><div class="notice success" role="status">Your account details were updated.</div><?php endif; ?>
    <?php if ($error): ?><div class="notice error" role="alert"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <form method="post" class="panel account-settings-card" autocomplete="off">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
        <div class="settings-section-title"><span>01</span><div><h2>Username</h2><p>This is the name you use to sign in.</p></div></div>
        <div class="field settings-field"><label for="username">Username</label><input id="username" name="username" value="<?= htmlspecialchars($username) ?>" maxlength="100" autocomplete="username" required></div>
        <div class="settings-section-title password-title"><span>02</span><div><h2>Password</h2><p>Confirm your current password before making account changes.</p></div></div>
        <div class="settings-fields-grid">
            <div class="field"><label for="current_password">Current password</label><input id="current_password" type="password" name="current_password" autocomplete="current-password" required></div>
            <div class="field"><label for="new_password">New password <span class="optional-label">Optional</span></label><input id="new_password" type="password" name="new_password" minlength="12" autocomplete="new-password"><small class="field-hint">Leave blank to keep your current password. Use at least 12 characters to change it.</small></div>
            <div class="field settings-field-wide"><label for="password_confirmation">Confirm new password</label><input id="password_confirmation" type="password" name="password_confirmation" minlength="12" autocomplete="new-password"></div>
        </div>
        <div class="settings-actions"><button class="btn settings-save" type="submit">Save account changes</button></div>
    </form>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>

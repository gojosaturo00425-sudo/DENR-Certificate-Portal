<?php
require_once __DIR__ . '/../includes/auth.php';
require_roles(['System Administrator','Regional Training Administrator','Office/PENRO/CENRO Administrator']);

$employeeId = (int)($_GET['employee_id'] ?? $_POST['employee_id'] ?? 0);
$lookup = $pdo->prepare('SELECT e.id, e.employee_no, e.first_name, e.last_name, u.id user_id, u.username FROM employees e LEFT JOIN users u ON u.employee_id=e.id WHERE e.id=? LIMIT 1');
$lookup->execute([$employeeId]);
$employee = $lookup->fetch();
if (!$employee) {
    http_response_code(404);
    exit('Employee profile not found.');
}
if (!$employee['user_id']) {
    http_response_code(404);
    exit('This employee does not have a linked portal account.');
}

$_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));
$error = '';
$success = isset($_GET['updated']);
$username = $employee['username'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['new_password'] ?? '');
    $confirmation = (string)($_POST['password_confirmation'] ?? '');

    if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], (string)$_POST['csrf_token'])) {
        $error = 'Your form session expired. Refresh and try again.';
    } elseif ($username === '' || strlen($username) > 100) {
        $error = 'Username must be between 1 and 100 characters.';
    } elseif (strlen($password) < 12) {
        $error = 'Set a temporary password of at least 12 characters.';
    } elseif ($password !== $confirmation) {
        $error = 'The password and confirmation do not match.';
    } else {
        try {
            $update = $pdo->prepare('UPDATE users SET username=?, password_hash=?, is_active=1 WHERE id=? AND employee_id=?');
            $update->execute([$username, password_hash($password, PASSWORD_DEFAULT), (int)$employee['user_id'], $employeeId]);
            audit('RESET_CREDENTIALS', 'user', (int)$employee['user_id'], 'Administrator reset employee portal credentials');
            header('Location: employee_account.php?employee_id=' . $employeeId . '&updated=1');
            exit;
        } catch (PDOException $exception) {
            $error = $exception->getCode() === '23000'
                ? 'That username is already in use. Choose another username.'
                : 'Credentials could not be updated. Please try again.';
        }
    }
}

$page_title = 'Reset Employee Login';
require __DIR__ . '/../includes/header.php';
?>
<div class="account-settings-wrap">
    <div class="account-settings-heading"><div class="settings-icon" aria-hidden="true">↻</div><div><p class="settings-eyebrow">EMPLOYEE ACCOUNT</p><h1>Reset portal login</h1><p><?= htmlspecialchars($employee['employee_no'] . ' · ' . $employee['first_name'] . ' ' . $employee['last_name']) ?></p></div></div>
    <?php if ($success): ?><div class="notice success" role="status">Portal login updated. Share the temporary password securely with the employee.</div><?php endif; ?>
    <?php if ($error): ?><div class="notice error" role="alert"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <form method="post" class="panel account-settings-card" autocomplete="off">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
        <input type="hidden" name="employee_id" value="<?= $employeeId ?>">
        <div class="field settings-field"><label for="username">New username</label><input id="username" name="username" value="<?= htmlspecialchars($username) ?>" maxlength="100" autocomplete="off" required></div>
        <div class="settings-section-title password-title"><span>RESET</span><div><h2>Temporary password</h2><p>Set a password and share it with the employee through a private channel.</p></div></div>
        <div class="settings-fields-grid">
            <div class="field"><label for="new_password">New password</label><input id="new_password" name="new_password" type="password" minlength="12" autocomplete="new-password" required><small class="field-hint">At least 12 characters.</small></div>
            <div class="field"><label for="password_confirmation">Confirm password</label><input id="password_confirmation" name="password_confirmation" type="password" minlength="12" autocomplete="new-password" required></div>
        </div>
        <div class="settings-actions"><a class="btn secondary" href="employees.php">Cancel</a><button class="btn settings-save" type="submit">Update login</button></div>
    </form>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>

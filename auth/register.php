<?php
require_once __DIR__ . '/../includes/auth.php';

if (current_user()) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$error = '';
$values = array_fill_keys(['username', 'first_name', 'middle_name', 'last_name', 'email', 'phone', 'penro_cenro', 'division_section'], '');
$offices = $pdo->query('SELECT id, name FROM offices ORDER BY name')->fetchAll();
$positions = $pdo->query('SELECT id, name FROM positions ORDER BY name')->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($values as $key => $_) {
        $values[$key] = trim((string)($_POST[$key] ?? ''));
    }
    foreach (['first_name', 'middle_name', 'last_name'] as $nameField) {
        $values[$nameField] = function_exists('mb_strtoupper')
            ? mb_strtoupper($values[$nameField], 'UTF-8')
            : strtoupper($values[$nameField]);
    }
    $password = (string)($_POST['password'] ?? '');
    $confirmation = (string)($_POST['password_confirmation'] ?? '');
    $positionId = (int)($_POST['position_id'] ?? 0) ?: null;
    $officeId = (int)($_POST['office_id'] ?? 0) ?: null;
    $employmentStatus = (string)($_POST['employment_status'] ?? 'Permanent');
    $statuses = ['Permanent', 'Contractual', 'Job Order', 'Casual', 'Other'];

    if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', (string)$_POST['csrf_token'])) {
        $error = 'Your form session expired. Please try again.';
    } elseif ($values['username'] === '' || strlen($values['username']) > 100) {
        $error = 'Enter a username within the allowed length.';
    } elseif ($values['first_name'] === '' || $values['last_name'] === '' || $values['email'] === '' || !filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
        $error = 'Enter your first name, last name, and a valid email address.';
    } elseif (strlen($password) < 12 || $password !== $confirmation) {
        $error = $password !== $confirmation ? 'The passwords do not match.' : 'Password must be at least 12 characters.';
    } elseif (!in_array($employmentStatus, $statuses, true)) {
        $error = 'Select a valid employment status.';
    } else {
        try {
            $pdo->beginTransaction();
            $role = $pdo->prepare("SELECT id FROM roles WHERE name='Employee' LIMIT 1");
            $role->execute();
            $roleId = $role->fetchColumn();
            if (!$roleId) {
                throw new RuntimeException('Employee role is not configured.');
            }

            // Insert a temporary unique value, then derive the permanent employee ID
            // from the database-generated primary key while still in the transaction.
            $employeeNo = 'NEW-' . strtoupper(bin2hex(random_bytes(12)));
            $employee = $pdo->prepare('INSERT INTO employees (employee_no, first_name, middle_name, last_name, position_id, office_id, penro_cenro, division_section, employment_status, email, phone) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $employee->execute([
                $employeeNo, $values['first_name'], $values['middle_name'] ?: null,
                $values['last_name'], $positionId, $officeId, $values['penro_cenro'] ?: null,
                $values['division_section'] ?: null, $employmentStatus, $values['email'], $values['phone'] ?: null,
            ]);
            $employeeId = (int)$pdo->lastInsertId();
            $employeeNo = 'DENR12-' . str_pad((string)$employeeId, 6, '0', STR_PAD_LEFT);
            $duplicateId = $pdo->prepare('SELECT id FROM employees WHERE employee_no=? AND id<>? LIMIT 1');
            $duplicateId->execute([$employeeNo, $employeeId]);
            if ($duplicateId->fetchColumn()) {
                $employeeNo .= '-' . strtoupper(bin2hex(random_bytes(3)));
            }
            $pdo->prepare('UPDATE employees SET employee_no=? WHERE id=?')->execute([$employeeNo, $employeeId]);

            $account = $pdo->prepare('INSERT INTO users (username, password_hash, role_id, employee_id, is_active) VALUES (?, ?, ?, ?, 1)');
            $account->execute([$values['username'], password_hash($password, PASSWORD_DEFAULT), $roleId, $employeeId]);
            $userId = (int)$pdo->lastInsertId();
            $pdo->commit();

            login_user([
                'id' => $userId, 'username' => $values['username'], 'role_id' => $roleId,
                'role_name' => 'Employee', 'employee_id' => $employeeId,
            ]);
            header('Location: ' . BASE_URL . '/employee/dashboard.php');
            exit;
        } catch (PDOException $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = $exception->getCode() === '23000'
                ? 'That username is already registered. Please choose another username.'
                : 'Registration could not be completed. Please try again.';
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = 'Registration could not be completed. Please contact the portal administrator.';
        }
    }
}

$_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Employee Registration - DENR XII</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous"><link rel="stylesheet" href="<?= BASE_URL ?>/assets/style.css">
</head>
<body class="registration-page">
<header class="registration-top container-xxl"><a class="registration-brand" href="<?= BASE_URL ?>/auth/login.php"><img class="registration-logo" src="<?= BASE_URL ?>/assets/denr-logo.webp" alt="DENR seal"><span><strong>DENR XII</strong><small>Employee Certification Portal</small></span></a><a class="top-login" href="<?= BASE_URL ?>/auth/login.php">Already registered? <strong>Sign in</strong></a></header>
<main class="container-xxl registration-shell py-2 py-lg-3"><div class="row g-3 g-xl-4 align-items-stretch">
<aside class="col-12 col-lg-5 col-xl-4"><div class="registration-intro h-100"><div class="intro-kicker"><span></span> EMPLOYEE SELF-SERVICE</div><h1>Your work.<br>Your growth.<br><em>All in one place.</em></h1><p>Create your portal account to follow your training progress and keep your certificates close at hand.</p><div class="intro-steps"><div><span>01</span><p><strong>Set up your profile</strong><small>Use your official employee details.</small></p></div><div><span>02</span><p><strong>Track your training</strong><small>See your registrations and completion status.</small></p></div><div><span>03</span><p><strong>Request certificates</strong><small>Send eligible requests for review.</small></p></div></div><div class="intro-note"><span class="note-icon">✓</span><span>Your information is used to create your employee profile in the portal.</span></div></div></aside>
<section class="col-12 col-lg-7 col-xl-8" aria-labelledby="form-title"><div class="registration-card h-100"><div class="form-heading"><div><div class="form-eyebrow">LET'S GET STARTED</div><h2 id="form-title">Create your account</h2><p>Fill in your details below. Fields marked <b>*</b> are required.</p></div><div class="form-step-badge"><span>1</span> of 1</div></div>
<?php if ($error): ?><div class="notice error registration-error" role="alert"><strong>We couldn't create your account yet.</strong><span><?= htmlspecialchars($error) ?></span></div><?php endif; ?>
<form method="post" class="registration-form"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
<div class="form-section"><div class="section-title"><span>1</span><div><h3>Employee information</h3><p>Enter the details associated with your DENR profile.</p></div></div>
<div class="row g-3 registration-grid">
<div class="col-12 col-md-6"><div class="field"><span class="form-label">Employee ID</span><div class="employee-id-preview"><span aria-hidden="true">#</span><div><strong>Generated automatically</strong><small id="employee-id-help">Your unique employee ID will be assigned when you register.</small></div></div></div></div>
<?php foreach ([['first_name','First name',true],['middle_name','Middle name',false],['last_name','Last name',true]] as [$name,$label,$required]): ?>
<div class="col-12 col-md-6"><div class="field"><label for="<?= $name ?>"><?= $label ?><?= $required?' <b>*</b>':'' ?></label><input class="form-control name-uppercase" id="<?= $name ?>" name="<?= $name ?>" value="<?= htmlspecialchars($values[$name]) ?>" <?= $required?'required':'' ?> autocomplete="<?= $name ?>" autocapitalize="characters" placeholder="Enter <?= strtolower($label) ?>"></div></div>
<?php endforeach; ?>
<div class="col-12 col-md-6"><div class="field"><label for="email">Work email <b>*</b></label><input class="form-control" id="email" type="email" name="email" value="<?= htmlspecialchars($values['email']) ?>" required autocomplete="email" placeholder="name@denr.gov.ph"></div></div>
<div class="col-12 col-md-6"><div class="field"><label for="phone">Phone number</label><input class="form-control" id="phone" type="tel" name="phone" value="<?= htmlspecialchars($values['phone']) ?>" autocomplete="tel" placeholder="e.g. 09XX XXX XXXX"></div></div>
</div></div>
<div class="form-section"><div class="section-title"><span>2</span><div><h3>Office details</h3><p>Help us connect your profile with your office.</p></div></div>
<div class="row g-3 registration-grid">
<div class="col-12 col-md-6"><div class="field"><label for="office_id">Office</label><select class="form-select" id="office_id" name="office_id"><option value="">Choose your office</option><?php foreach ($offices as $office): ?><option value="<?= $office['id'] ?>" <?= (int)($_POST['office_id'] ?? 0)===(int)$office['id']?'selected':'' ?>><?= htmlspecialchars($office['name']) ?></option><?php endforeach; ?></select></div></div>
<div class="col-12 col-md-6"><div class="field"><label for="position_id">Position</label><select class="form-select" id="position_id" name="position_id"><option value="">Choose your position</option><?php foreach ($positions as $position): ?><option value="<?= $position['id'] ?>" <?= (int)($_POST['position_id'] ?? 0)===(int)$position['id']?'selected':'' ?>><?= htmlspecialchars($position['name']) ?></option><?php endforeach; ?></select></div></div>
<div class="col-12 col-md-6"><div class="field"><label for="employment_status">Employment status</label><select class="form-select" id="employment_status" name="employment_status"><?php foreach (['Permanent','Contractual','Job Order','Casual','Other'] as $status): ?><option <?= $status===($_POST['employment_status'] ?? 'Permanent')?'selected':'' ?>><?= $status ?></option><?php endforeach; ?></select></div></div>
<div class="col-12 col-md-6"><div class="field"><label for="penro_cenro">Please specify other position</label><input class="form-control" id="penro_cenro" name="penro_cenro" value="<?= htmlspecialchars($values['penro_cenro']) ?>" placeholder="If applicable"></div></div>
<div class="col-12"><div class="field"><label for="division_section">Division / section</label><input class="form-control" id="division_section" name="division_section" value="<?= htmlspecialchars($values['division_section']) ?>" placeholder="Your division or section"></div></div>
</div></div>
<div class="form-section account-section"><div class="section-title"><span>3</span><div><h3>Portal login</h3><p>Choose the credentials you'll use to sign in.</p></div></div>
<div class="row g-3 registration-grid"><div class="col-12 col-md-6"><div class="field"><label for="username">Username <b>*</b></label><input class="form-control" id="username" name="username" value="<?= htmlspecialchars($values['username']) ?>" required autocomplete="username" placeholder="Choose a username"></div></div>
<div class="col-12 col-md-6"><div class="field"><label for="password">Password <b>*</b></label><input class="form-control" id="password" type="password" name="password" minlength="12" autocomplete="new-password" required placeholder="At least 12 characters"><small class="field-hint">Use at least 12 characters.</small></div></div>
<div class="col-12"><div class="field"><label for="password_confirmation">Confirm password <b>*</b></label><input class="form-control" id="password_confirmation" type="password" name="password_confirmation" minlength="12" autocomplete="new-password" required placeholder="Enter your password again"></div></div></div></div>
<div class="form-actions"><button class="btn registration-submit" type="submit">Create employee account <span aria-hidden="true">→</span></button><p>By creating an account, you confirm these employee details are accurate.</p></div>
</form></div></section>
</div></main><footer class="registration-footer container-xxl">Department of Environment and Natural Resources · Region XII <span>Employee Certification Portal</span></footer>
</body></html>

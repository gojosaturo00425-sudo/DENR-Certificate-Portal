<?php
require_once __DIR__ . '/../includes/auth.php';
require_roles(['Employee']);

$user = current_user();
$employeeId = (int)($user['employee_id'] ?? 0);
if (!$employeeId) {
    http_response_code(403);
    exit('This account is not linked to an employee profile. Contact a portal administrator.');
}

$_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));
$error = '';
$message = '';
$otherCertificateTypes = [
    'Certificate of No Pending Case',
    'Certificate of Bank Endorsement',
    'Certificate of No Take Home Pay',
    'Certificate of Salary-Remuneration',
    'Certificate of Employment',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_other_certificate'])) {
    if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], (string)$_POST['csrf_token'])) {
        $error = 'Your form session expired. Refresh the page and try again.';
    } else {
        $certificateType = trim((string)($_POST['certificate_type'] ?? ''));
        if (!in_array($certificateType, $otherCertificateTypes, true)) {
            $error = 'Choose a certificate type from the list.';
        } else {
            $existing = $pdo->prepare("SELECT id FROM certificates WHERE employee_id=? AND certificate_type=? AND status IN ('PENDING','APPROVED','ACTIVE','EXPIRING') LIMIT 1");
            $existing->execute([$employeeId, $certificateType]);
            if ($existing->fetchColumn()) {
                $error = 'You already have a pending or active request for this certificate type.';
            } else {
                try {
                    $pdo->beginTransaction();
                    $certificateNo = 'DENR12-REQ-' . strtoupper(bin2hex(random_bytes(5)));
                    $qrToken = bin2hex(random_bytes(32));
                    $create = $pdo->prepare("INSERT INTO certificates (certificate_no,employee_id,training_id,certificate_type,status,qr_token) VALUES (?,?,NULL,?,'PENDING',?)");
                    $create->execute([$certificateNo, $employeeId, $certificateType, $qrToken]);
                    $certificateId = (int)$pdo->lastInsertId();
                    $notify = $pdo->prepare('INSERT INTO notifications (employee_id,user_id,title,message,type) VALUES (?,?,?,?,?)');
                    $notify->execute([$employeeId, (int)$user['id'], 'Certificate request submitted', 'Your request for ' . $certificateType . ' is pending administrator review.', 'certificate_request']);
                    $admins = $pdo->query("SELECT u.id FROM users u JOIN roles r ON r.id=u.role_id WHERE u.is_active=1 AND r.name IN ('System Administrator','Regional Training Administrator','Approving Officer')")->fetchAll(PDO::FETCH_COLUMN);
                    foreach ($admins as $adminId) {
                        $notify->execute([null, (int)$adminId, 'Pending certificate request', $user['username'] . ' requested ' . $certificateType . ' (' . $certificateNo . ').', 'certificate_request']);
                    }
                    audit('REQUEST', 'certificate', $certificateId, 'Employee requested ' . $certificateType);
                    $pdo->commit();
                    header('Location: ' . BASE_URL . '/employee/dashboard.php?certificate_type_requested=1');
                    exit;
                } catch (Throwable $exception) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    $error = 'The certificate request could not be submitted. Please try again.';
                }
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_certificate'])) {
    if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], (string)$_POST['csrf_token'])) {
        $error = 'Your form session expired. Refresh the page and try again.';
    } else {
        $trainingId = (int)($_POST['training_id'] ?? 0);
        $eligible = $pdo->prepare("SELECT t.id, t.title FROM training_registrations r JOIN trainings t ON t.id=r.training_id WHERE r.employee_id=? AND r.training_id=? AND r.status='Completed' LIMIT 1");
        $eligible->execute([$employeeId, $trainingId]);
        $training = $eligible->fetch();

        if (!$training) {
            $error = 'Certificate requests are available for completed trainings only.';
        } else {
            $existing = $pdo->prepare('SELECT id FROM certificates WHERE employee_id=? AND training_id=? LIMIT 1');
            $existing->execute([$employeeId, $trainingId]);
            if ($existing->fetchColumn()) {
                $error = 'A certificate request or certificate already exists for this training.';
            } else {
                try {
                    $pdo->beginTransaction();
                    $certificateNo = 'DENR12-REQ-' . strtoupper(bin2hex(random_bytes(5)));
                    $qrToken = bin2hex(random_bytes(32));
                    $create = $pdo->prepare("INSERT INTO certificates (certificate_no, employee_id, training_id, status, qr_token) VALUES (?, ?, ?, 'PENDING', ?)");
                    $create->execute([$certificateNo, $employeeId, $trainingId, $qrToken]);
                    $certificateId = (int)$pdo->lastInsertId();

                    $notify = $pdo->prepare("INSERT INTO notifications (employee_id, user_id, title, message, type) VALUES (?, ?, ?, ?, ?)");
                    $notify->execute([$employeeId, (int)$user['id'], 'Certificate request submitted', 'Your certificate request for ' . $training['title'] . ' is pending review.', 'certificate_request']);

                    $admins = $pdo->query("SELECT u.id FROM users u JOIN roles r ON r.id=u.role_id WHERE u.is_active=1 AND r.name IN ('System Administrator','Regional Training Administrator','Approving Officer')")->fetchAll(PDO::FETCH_COLUMN);
                    foreach ($admins as $adminId) {
                        $notify->execute([null, (int)$adminId, 'Pending certificate request', $user['username'] . ' submitted a certificate request for ' . $training['title'] . ' (' . $certificateNo . ').', 'certificate_request']);
                    }
                    audit('REQUEST','certificate',$certificateId,'Certificate requested for training: ' . $training['title']);
                    $pdo->commit();
                    header('Location: ' . BASE_URL . '/employee/dashboard.php?requested=1');
                    exit;
                } catch (Throwable $exception) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    $error = 'The certificate request could not be submitted. Please try again.';
                }
            }
        }
    }
}

if (isset($_GET['requested'])) $message = 'Your certificate request was sent and is pending administrator review.';
if (isset($_GET['certificate_type_requested'])) $message = 'Your certificate request was sent and is pending administrator review.';
$profileStmt = $pdo->prepare('SELECT e.*, o.name office_name, p.name position_name FROM employees e LEFT JOIN offices o ON o.id=e.office_id LEFT JOIN positions p ON p.id=e.position_id WHERE e.id=?');
$profileStmt->execute([$employeeId]);
$profile = $profileStmt->fetch();

$summary = $pdo->prepare("SELECT COUNT(DISTINCT training_id) trainings, COALESCE(SUM(status='Completed'),0) completed FROM training_registrations WHERE employee_id=?");
$summary->execute([$employeeId]);
$stats = $summary->fetch() ?: [];
$certificateSummary = $pdo->prepare("SELECT COALESCE(SUM(status='PENDING'),0) pending,COALESCE(SUM(status IN ('ACTIVE','APPROVED')),0) active FROM certificates WHERE employee_id=?");
$certificateSummary->execute([$employeeId]);
$stats = array_merge($stats, $certificateSummary->fetch() ?: []);

$trainingStmt = $pdo->prepare('SELECT t.id, t.title, t.start_at, r.status registration_status, c.certificate_no, c.status certificate_status FROM training_registrations r JOIN trainings t ON t.id=r.training_id LEFT JOIN certificates c ON c.employee_id=r.employee_id AND c.training_id=r.training_id WHERE r.employee_id=? ORDER BY t.start_at DESC');
$trainingStmt->execute([$employeeId]);
$trainingRows = $trainingStmt->fetchAll();

$eligibleStmt = $pdo->prepare("SELECT t.id, t.title FROM training_registrations r JOIN trainings t ON t.id=r.training_id LEFT JOIN certificates c ON c.employee_id=r.employee_id AND c.training_id=r.training_id WHERE r.employee_id=? AND r.status='Completed' AND c.id IS NULL ORDER BY t.title");
$eligibleStmt->execute([$employeeId]);
$eligibleTrainings = $eligibleStmt->fetchAll();

$noticeStmt = $pdo->prepare('SELECT title, message, is_read, created_at FROM notifications WHERE employee_id=? ORDER BY created_at DESC LIMIT 10');
$noticeStmt->execute([$employeeId]);
$notifications = $noticeStmt->fetchAll();
foreach ($notifications as &$notification) {
    $notification['message'] = preg_replace('/^TRAINING_ID:\\d+\\s*\\|\\s*/', '', $notification['message']);
}
unset($notification);

$page_title = 'Employee Dashboard';
require __DIR__ . '/../includes/header.php';
?>
<div class="dashboard-heading"><div><span class="dashboard-eyebrow">EMPLOYEE WORKSPACE</span><h1>Employee Dashboard</h1><p>Your training progress, certificate requests, and portal updates at a glance.</p></div><span class="heading-icon"><?= $portalIcon('employees') ?></span></div>
<?php if ($message): ?><div class="notice success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="notice error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<div class="panel employee-profile"><h2><?= htmlspecialchars($profile['first_name'] . ' ' . $profile['last_name']) ?></h2><p><?= htmlspecialchars($profile['employee_no']) ?> · <?= htmlspecialchars($profile['position_name'] ?? 'Position not set') ?> · <?= htmlspecialchars($profile['office_name'] ?? 'Office not set') ?></p><p><?= htmlspecialchars($profile['email']) ?><?= $profile['phone'] ? ' · ' . htmlspecialchars($profile['phone']) : '' ?></p></div><br>
<div class="cards dashboard-metrics">
<div class="card metric-card"><span class="metric-icon blue"><?= $portalIcon('training') ?></span><div><h3>My Trainings</h3><div class="num"><?= (int)($stats['trainings'] ?? 0) ?></div><small>Enrolled programs</small></div></div>
<div class="card metric-card"><span class="metric-icon green"><?= $portalIcon('training') ?></span><div><h3>Completed Trainings</h3><div class="num"><?= (int)($stats['completed'] ?? 0) ?></div><small>Completed successfully</small></div></div>
<div class="card metric-card"><span class="metric-icon amber"><?= $portalIcon('certificate') ?></span><div><h3>Pending Requests</h3><div class="num"><?= (int)($stats['pending'] ?? 0) ?></div><small>Awaiting administrator review</small></div></div>
<div class="card metric-card"><span class="metric-icon teal"><?= $portalIcon('certificate') ?></span><div><h3>Active Certificates</h3><div class="num"><?= (int)($stats['active'] ?? 0) ?></div><small>Issued to your account</small></div></div>
</div><br>
<div class="employee-request-grid"><div class="panel dashboard-panel"><div class="panel-heading"><span class="panel-icon"><?= $portalIcon('certificate') ?></span><div><h2>Request a Training Certificate</h2><p>Request a certificate for a completed training.</p></div></div>
<?php if ($eligibleTrainings): ?><form method="post" class="actions"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>"><div class="field"><label for="training_id">Completed training</label><select name="training_id" id="training_id" required><?php foreach ($eligibleTrainings as $training): ?><option value="<?= (int)$training['id'] ?>"><?= htmlspecialchars($training['title']) ?></option><?php endforeach; ?></select></div><button class="btn" name="request_certificate" value="1">Submit Certificate Request</button></form>
<?php else: ?><p>No completed trainings are available for a certificate request yet.</p><?php endif; ?></div><br>
<div class="panel dashboard-panel"><div class="panel-heading"><span class="panel-icon"><?= $portalIcon('certificate') ?></span><div><h2>Request Another Certificate</h2><p>Select the certificate type you need. Requests are reviewed within five working days, Monday through Thursday; Friday, Saturday, and Sunday are excluded.</p></div></div><form method="post" class="other-certificate-request-form"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>"><div class="field"><label for="certificate_type">Certificate type</label><select id="certificate_type" name="certificate_type" required><option value="">Choose a certificate</option><?php foreach ($otherCertificateTypes as $certificateType): ?><option value="<?= htmlspecialchars($certificateType) ?>"><?= htmlspecialchars($certificateType) ?></option><?php endforeach; ?></select></div><button class="btn" type="submit" name="request_other_certificate" value="1">Submit Certificate Request</button></form></div><br>
</div><div class="panel"><h2>Training and Certificate Status</h2><div class="table-wrap"><table class="table"><thead><tr><th>Training</th><th>Training Status</th><th>Certificate Status</th><th>Request / Certificate No.</th></tr></thead><tbody>
<?php foreach ($trainingRows as $row): ?><tr><td><?= htmlspecialchars($row['title']) ?></td><td><?= htmlspecialchars($row['registration_status']) ?></td><td><?= htmlspecialchars($row['certificate_status'] ?? 'Not requested') ?></td><td><?= htmlspecialchars($row['certificate_no'] ?? '—') ?></td></tr><?php endforeach; ?>
<?php if (!$trainingRows): ?><tr><td colspan="4">No training registrations found for this account.</td></tr><?php endif; ?>
</tbody></table></div></div><br>
<section class="panel dashboard-panel employee-notifications"><div class="panel-heading"><span class="panel-icon"><?= $portalIcon('bell') ?></span><div><h2>Portal Notifications</h2><p>Recent updates about trainings and certificate requests.</p></div></div><div class="table-wrap"><table class="table"><thead><tr><th>Notification</th><th>Message</th><th>Date</th><th>Status</th></tr></thead><tbody>
<?php foreach ($notifications as $notification): ?><tr><td><strong><?= htmlspecialchars($notification['title']) ?></strong></td><td><?= htmlspecialchars($notification['message']) ?></td><td><?= htmlspecialchars($notification['created_at']) ?></td><td><span class="notification-status <?= (int)$notification['is_read'] ? 'read' : 'unread' ?>"><?= (int)$notification['is_read'] ? 'Read' : 'New' ?></span></td></tr><?php endforeach; ?>
<?php if (!$notifications): ?><tr><td colspan="4">No notifications yet.</td></tr><?php endif; ?>
</tbody></table></div></section><?php require __DIR__ . '/../includes/footer.php'; ?>

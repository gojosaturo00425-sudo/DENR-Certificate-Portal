<?php
require_once __DIR__ . '/../includes/auth.php';
require_roles(['Employee']);

$employee = (int)(current_user()['employee_id'] ?? 0);
if (!$employee) {
    http_response_code(403);
    exit('This account is not linked to an employee profile.');
}
$_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], (string)$_POST['csrf_token'])) {
        $error = 'Your form session expired. Please refresh and try again.';
    } elseif (($_POST['action'] ?? '') === 'request_cancellation') {
        $trainingId = (int)($_POST['training_id'] ?? 0);
        $reason = trim((string)($_POST['reason'] ?? ''));
        $registration = $pdo->prepare("SELECT r.id,r.status,t.title,t.start_at,t.status training_status FROM training_registrations r JOIN trainings t ON t.id=r.training_id WHERE r.training_id=? AND r.employee_id=? LIMIT 1");
        $registration->execute([$trainingId, $employee]);
        $row = $registration->fetch();
        if (strlen($reason) < 5 || strlen($reason) > 500) {
            $error = 'Please provide a cancellation reason between 5 and 500 characters.';
        } elseif (!$row || $row['status'] !== 'Registered' || $row['training_status'] === 'Cancelled' || strtotime($row['start_at']) <= time()) {
            $error = 'A cancellation request is only available for a registered training that has not started.';
        } else {
            $pending = $pdo->prepare("SELECT id FROM training_cancellation_requests WHERE registration_id=? AND status='Pending' LIMIT 1");
            $pending->execute([(int)$row['id']]);
            if ($pending->fetchColumn()) {
                $error = 'A cancellation request for this training is already waiting for review.';
            } else {
                try {
                    $pdo->beginTransaction();
                    $create = $pdo->prepare("INSERT INTO training_cancellation_requests (registration_id,employee_id,reason) VALUES (?,?,?)");
                    $create->execute([(int)$row['id'], $employee, $reason]);
                    $requestId = (int)$pdo->lastInsertId();
                    $notify = $pdo->prepare("INSERT INTO notifications (employee_id,user_id,title,message,type) VALUES (?,?,?,?, 'training_cancel_request')");
                    $notify->execute([$employee, (int)current_user()['id'], 'Cancellation request submitted', 'Your request to cancel ' . $row['title'] . ' is pending administrator review.']);
                    $admins = $pdo->query("SELECT u.id FROM users u JOIN roles r ON r.id=u.role_id WHERE u.is_active=1 AND r.name IN ('System Administrator','Regional Training Administrator','Office/PENRO/CENRO Administrator','Training Coordinator')")->fetchAll(PDO::FETCH_COLUMN);
                    $adminMessage = current_user()['username'] . ' requested cancellation of ' . $row['title'] . '. Please review request #' . $requestId . '.';
                    foreach ($admins as $adminId) {
                        $notify->execute([null, (int)$adminId, 'Training cancellation requested', $adminMessage]);
                    }
                    audit('REQUEST_CANCELLATION', 'training_registration', (int)$row['id'], 'Cancellation requested for ' . $row['title']);
                    $pdo->commit();
                    header('Location: my_training.php?cancellation=requested');
                    exit;
                } catch (Throwable $exception) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    error_log('Training cancellation request failed: ' . $exception->getMessage());
                    $error = 'The cancellation request could not be submitted. Please try again.';
                }
            }
        }
    } else {
        $trainingId = (int)($_POST['training_id'] ?? 0);
        $eligible = $pdo->prepare("SELECT t.id,t.max_participants,(SELECT COUNT(*) FROM training_registrations r WHERE r.training_id=t.id AND r.status<>'Cancelled') participant_count FROM trainings t WHERE t.id=? AND t.status='Published' AND t.start_at>NOW()");
        $eligible->execute([$trainingId]);
        $training = $eligible->fetch();
        if (!$training) {
            $error = 'This training is not currently open for registration.';
        } elseif ((int)$training['max_participants'] > 0 && (int)$training['participant_count'] >= (int)$training['max_participants']) {
            $error = 'This training has reached its participant limit.';
        } else {
            $existing = $pdo->prepare('SELECT id,status FROM training_registrations WHERE training_id=? AND employee_id=? LIMIT 1');
            $existing->execute([$trainingId, $employee]);
            $priorRegistration = $existing->fetch();
            if ($priorRegistration && $priorRegistration['status'] !== 'Cancelled') {
                $error = 'You are already registered for this training.';
            } else {
                try {
                    if ($priorRegistration) {
                        $restore = $pdo->prepare("UPDATE training_registrations SET status='Registered',registered_at=CURRENT_TIMESTAMP WHERE id=? AND status='Cancelled'");
                        $restore->execute([(int)$priorRegistration['id']]);
                    } else {
                        $insert = $pdo->prepare('INSERT INTO training_registrations(training_id,employee_id) VALUES(?,?)');
                        $insert->execute([$trainingId, $employee]);
                    }
                    header('Location: my_training.php?registered=1');
                    exit;
                } catch (PDOException $exception) {
                    $error = $exception->getCode() === '23000' ? 'You are already registered for this training.' : 'Unable to register for this training right now.';
                }
            }
        }
    }
}

$trainingStmt = $pdo->prepare('SELECT t.id,t.title,t.start_at,t.status training_status,r.id registration_id,r.status registration_status,cr.status cancellation_status FROM trainings t JOIN training_registrations r ON r.training_id=t.id LEFT JOIN training_cancellation_requests cr ON cr.id=(SELECT MAX(cr2.id) FROM training_cancellation_requests cr2 WHERE cr2.registration_id=r.id) WHERE r.employee_id=? ORDER BY t.start_at DESC');
$trainingStmt->execute([$employee]);
$trainings = $trainingStmt->fetchAll();
$availableStmt = $pdo->prepare("SELECT t.*,t.status training_status,r.status registration_status,(SELECT COUNT(*) FROM training_registrations active_r WHERE active_r.training_id=t.id AND active_r.status<>'Cancelled') participant_count FROM trainings t LEFT JOIN training_registrations r ON r.training_id=t.id AND r.employee_id=? WHERE t.status IN ('Published','Completed') ORDER BY t.start_at ASC");
$availableStmt->execute([$employee]);
$publishedTrainings = $availableStmt->fetchAll();
$available = [];
$trainingHistory = [];
foreach ($publishedTrainings as $publishedTraining) {
    $registrationOpen = $publishedTraining['training_status'] === 'Published'
        && strtotime($publishedTraining['start_at']) > time()
        && in_array($publishedTraining['registration_status'], [null, 'Cancelled'], true)
        && ((int)$publishedTraining['max_participants'] === 0 || (int)$publishedTraining['participant_count'] < (int)$publishedTraining['max_participants']);
    if ($registrationOpen) {
        $available[] = $publishedTraining;
    } elseif ($publishedTraining['training_status'] === 'Completed'
        || strtotime($publishedTraining['start_at']) <= time()
        || ((int)$publishedTraining['max_participants'] > 0 && (int)$publishedTraining['participant_count'] >= (int)$publishedTraining['max_participants'])) {
        $trainingHistory[] = $publishedTraining;
    }
}
$message = isset($_GET['registered']) ? 'You are registered for the training.' : ((($_GET['cancellation'] ?? '') === 'requested') ? 'Your cancellation request was sent to the administrator for review.' : '');
$page_title = 'My Training';
require __DIR__ . '/../includes/header.php';
?>
<div class="dashboard-heading"><div><span class="dashboard-eyebrow">LEARNING & DEVELOPMENT</span><h1>My Training</h1><p>Manage your registrations and find upcoming employee training programs.</p></div><span class="heading-icon"><?= $portalIcon('training') ?></span></div>
<?php if ($message): ?><div class="notice success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="notice error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<section class="panel dashboard-panel"><div class="panel-heading"><span class="panel-icon"><?= $portalIcon('training') ?></span><div><h2>My Registrations</h2><p>Request administrator approval to withdraw before training starts.</p></div></div><div class="table-wrap"><table class="table"><thead><tr><th>Training</th><th>Date</th><th>Registration status</th><th>Cancellation request</th></tr></thead><tbody>
<?php foreach ($trainings as $training): ?><tr><td><?= htmlspecialchars($training['title']) ?><?php if ($training['training_status'] === 'Cancelled'): ?><br><span class="small">Training cancelled by administrator</span><?php endif; ?></td><td><?= htmlspecialchars($training['start_at']) ?></td><td><?= htmlspecialchars($training['registration_status']) ?><?php if ($training['cancellation_status'] === 'Pending'): ?><br><span class="small">Cancellation awaiting review</span><?php elseif ($training['cancellation_status'] === 'Rejected' && $training['registration_status'] === 'Registered'): ?><br><span class="small">Previous request declined</span><?php endif; ?></td><td><?php if ($training['registration_status'] === 'Registered' && $training['training_status'] !== 'Cancelled' && strtotime($training['start_at']) > time() && $training['cancellation_status'] !== 'Pending'): ?><details class="training-cancel-details"><summary class="btn warning">Request cancellation</summary><form method="post"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>"><input type="hidden" name="training_id" value="<?= (int)$training['id'] ?>"><input type="hidden" name="action" value="request_cancellation"><label for="reason-<?= (int)$training['id'] ?>">Reason</label><textarea id="reason-<?= (int)$training['id'] ?>" name="reason" minlength="5" maxlength="500" required placeholder="Briefly tell us why you need to withdraw"></textarea><button class="btn danger" type="submit">Send cancellation request</button></form></details><?php else: ?><span class="small"><?= $training['cancellation_status'] === 'Pending' ? 'Awaiting review' : '—' ?></span><?php endif; ?></td></tr><?php endforeach; ?>
<?php if (!$trainings): ?><tr><td colspan="4">You have no training registrations yet.</td></tr><?php endif; ?>
</tbody></table></div></section>
<section class="panel dashboard-panel"><div class="panel-heading"><span class="panel-icon"><?= $portalIcon('dashboard') ?></span><div><h2>Open for Registration <span class="request-count"><?= count($available) ?></span></h2><p>Only upcoming trainings with available spaces and open registration appear here.</p></div></div>
<?php if (!$available): ?><p class="empty-state">There are no trainings open for registration right now.</p><?php else: ?><div class="available-training-list"><?php foreach ($available as $training): ?><article class="available-training"><div><span class="training-date-label">STARTS <?= htmlspecialchars(date('M j, Y g:i A', strtotime($training['start_at']))) ?></span><h3><?= htmlspecialchars($training['title']) ?></h3><p><?= htmlspecialchars($training['description'] ?: 'Open to eligible employees.') ?></p><span class="published-training-status"><?= $training['registration_status'] === 'Cancelled' ? 'Your earlier registration was cancelled - you may register again' : 'Open for registration' ?></span></div><form method="post"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>"><input type="hidden" name="training_id" value="<?= (int)$training['id'] ?>"><button class="btn" type="submit">Register</button></form></article><?php endforeach; ?></div><?php endif; ?></section>
<section class="panel dashboard-panel"><div class="panel-heading"><span class="panel-icon"><?= $portalIcon('training') ?></span><div><h2>Training History <span class="request-count"><?= count($trainingHistory) ?></span></h2><p>Completed trainings and programs whose registration has closed or filled up.</p></div></div><div class="table-wrap"><table class="table"><thead><tr><th>Training</th><th>Start date</th><th>Status</th></tr></thead><tbody>
<?php foreach ($trainingHistory as $training): ?><tr><td><?= htmlspecialchars($training['title']) ?></td><td><?= htmlspecialchars($training['start_at']) ?></td><td><?php if ($training['training_status'] === 'Completed'): ?>Completed<?php elseif (strtotime($training['start_at']) <= time()): ?>Registration closed<?php else: ?>Registration full<?php endif; ?></td></tr><?php endforeach; ?>
<?php if (!$trainingHistory): ?><tr><td colspan="3">No completed or closed trainings yet.</td></tr><?php endif; ?>
</tbody></table></div></section><?php require __DIR__ . '/../includes/footer.php'; ?>

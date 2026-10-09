<?php
require_once __DIR__ . '/../includes/auth.php';
require_roles(['System Administrator','Regional Training Administrator','Office/PENRO/CENRO Administrator','Training Coordinator']);
$_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $requestId = (int)($_POST['request_id'] ?? 0);
    $decision = (string)($_POST['decision'] ?? '');
    $adminNote = trim((string)($_POST['admin_note'] ?? ''));
    if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], (string)$_POST['csrf_token'])) {
        $error = 'Your form session expired. Refresh the page and try again.';
    } elseif (!in_array($decision, ['Approved','Rejected'], true)) {
        $error = 'Choose whether to approve or reject this request.';
    } elseif (strlen($adminNote) > 500) {
        $error = 'The review note must be 500 characters or fewer.';
    } else {
        try {
            $pdo->beginTransaction();
            $lookup = $pdo->prepare("SELECT cr.id,cr.status,cr.registration_id,cr.employee_id,t.id training_id,t.title,CONCAT(e.first_name,' ',e.last_name) employee_name FROM training_cancellation_requests cr JOIN training_registrations r ON r.id=cr.registration_id JOIN trainings t ON t.id=r.training_id JOIN employees e ON e.id=cr.employee_id WHERE cr.id=? FOR UPDATE");
            $lookup->execute([$requestId]);
            $request = $lookup->fetch();
            if (!$request || $request['status'] !== 'Pending') {
                throw new RuntimeException('This cancellation request has already been reviewed or no longer exists.');
            }
            if ($decision === 'Approved') {
                $cancelRegistration = $pdo->prepare("UPDATE training_registrations SET status='Cancelled' WHERE id=? AND status='Registered'");
                $cancelRegistration->execute([(int)$request['registration_id']]);
                if ($cancelRegistration->rowCount() !== 1) {
                    throw new RuntimeException('The registration is no longer in Registered status and cannot be cancelled.');
                }
            }
            $review = $pdo->prepare('UPDATE training_cancellation_requests SET status=?,reviewed_by=?,reviewed_at=NOW(),admin_note=? WHERE id=? AND status=\'Pending\'');
            $review->execute([$decision, (int)current_user()['id'], $adminNote !== '' ? $adminNote : null, $requestId]);
            $employeeUser = $pdo->prepare('SELECT id FROM users WHERE employee_id=? AND is_active=1 LIMIT 1');
            $employeeUser->execute([(int)$request['employee_id']]);
            $employeeUserId = $employeeUser->fetchColumn();
            $title = $decision === 'Approved' ? 'Cancellation request approved' : 'Cancellation request declined';
            $message = $decision === 'Approved'
                ? 'Your request to cancel ' . $request['title'] . ' was approved. Your registration has been cancelled.'
                : 'Your request to cancel ' . $request['title'] . ' was declined. Your training registration remains active.';
            if ($adminNote !== '') $message .= ' Reviewer note: ' . $adminNote;
            $notify = $pdo->prepare('INSERT INTO notifications (employee_id,user_id,title,message,type) VALUES (?,?,?,?,?)');
            $notify->execute([(int)$request['employee_id'], $employeeUserId ? (int)$employeeUserId : null, $title, $message, 'training_cancel_decision']);
            audit('REVIEW_CANCELLATION', 'training_registration', (int)$request['registration_id'], $decision . ' cancellation request #' . $requestId);
            $pdo->commit();
            header('Location: training_cancellations.php?updated=' . strtolower($decision));
            exit;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            if ($exception instanceof RuntimeException) {
                $error = $exception->getMessage();
            } else {
                error_log('Training cancellation review failed: ' . $exception->getMessage());
                $error = 'The request could not be updated. Confirm the cancellation migration has been applied, then try again.';
            }
        }
    }
}

$pendingStmt = $pdo->query("SELECT cr.id,cr.reason,cr.requested_at,r.id registration_id,e.employee_no,CONCAT(e.first_name,' ',e.last_name) employee_name,t.title training_title,t.start_at FROM training_cancellation_requests cr JOIN training_registrations r ON r.id=cr.registration_id JOIN employees e ON e.id=cr.employee_id JOIN trainings t ON t.id=r.training_id WHERE cr.status='Pending' ORDER BY cr.requested_at ASC");
$pendingRequests = $pendingStmt->fetchAll();
$history = $pdo->query("SELECT cr.status,cr.requested_at,cr.reviewed_at,cr.admin_note,e.employee_no,CONCAT(e.first_name,' ',e.last_name) employee_name,t.title training_title,u.username reviewer FROM training_cancellation_requests cr JOIN training_registrations r ON r.id=cr.registration_id JOIN employees e ON e.id=cr.employee_id JOIN trainings t ON t.id=r.training_id LEFT JOIN users u ON u.id=cr.reviewed_by WHERE cr.status<>'Pending' ORDER BY cr.reviewed_at DESC LIMIT 30")->fetchAll();
$page_title = 'Training Cancellation Requests';
require __DIR__ . '/../includes/header.php';
?>
<div class="dashboard-heading"><div><span class="dashboard-eyebrow">TRAINING ADMINISTRATION</span><h1>Cancellation Requests</h1><p>Review employee requests to withdraw from upcoming trainings.</p></div><span class="heading-icon"><?= $portalIcon('training') ?></span></div>
<?php if ($error): ?><div class="notice error" role="alert"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php if (($_GET['updated'] ?? '') === 'approved'): ?><div class="notice success">The cancellation request was approved and the employee was notified.</div><?php elseif (($_GET['updated'] ?? '') === 'rejected'): ?><div class="notice success">The cancellation request was declined and the employee was notified.</div><?php endif; ?>
<section class="panel dashboard-panel"><div class="panel-heading"><span class="panel-icon"><?= $portalIcon('dashboard') ?></span><div><h2>Waiting for Review <span class="request-count"><?= count($pendingRequests) ?></span></h2><p>Approving a request cancels the employee’s registration and releases the seat.</p></div></div><div class="table-wrap"><table class="table"><thead><tr><th>Employee</th><th>Training</th><th>Starts</th><th>Reason</th><th>Review</th></tr></thead><tbody>
<?php foreach ($pendingRequests as $request): ?><tr><td><?= htmlspecialchars($request['employee_name']) ?><br><span class="small"><?= htmlspecialchars($request['employee_no']) ?></span></td><td><?= htmlspecialchars($request['training_title']) ?></td><td><?= htmlspecialchars($request['start_at']) ?></td><td><?= nl2br(htmlspecialchars($request['reason'])) ?><br><span class="small">Requested <?= htmlspecialchars($request['requested_at']) ?></span></td><td><form method="post" class="cancellation-review-form"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>"><input type="hidden" name="request_id" value="<?= (int)$request['id'] ?>"><label class="small" for="note-<?= (int)$request['id'] ?>">Reviewer note (optional)</label><input id="note-<?= (int)$request['id'] ?>" name="admin_note" maxlength="500" placeholder="Add a short note"><div class="actions"><button class="btn" name="decision" value="Approved" type="submit">Approve</button><button class="btn secondary" name="decision" value="Rejected" type="submit">Decline</button></div></form></td></tr><?php endforeach; ?>
<?php if (!$pendingRequests): ?><tr><td colspan="5">There are no cancellation requests waiting for review.</td></tr><?php endif; ?>
</tbody></table></div></section>
<section class="panel dashboard-panel"><div class="panel-heading"><span class="panel-icon"><?= $portalIcon('certificate') ?></span><div><h2>Recent Decisions</h2><p>Latest approved and declined employee requests.</p></div></div><div class="table-wrap"><table class="table"><thead><tr><th>Employee</th><th>Training</th><th>Decision</th><th>Reviewed</th><th>Reviewer</th><th>Note</th></tr></thead><tbody>
<?php foreach ($history as $item): ?><tr><td><?= htmlspecialchars($item['employee_name']) ?><br><span class="small"><?= htmlspecialchars($item['employee_no']) ?></span></td><td><?= htmlspecialchars($item['training_title']) ?></td><td><span class="sla-badge <?= $item['status'] === 'Approved' ? 'on-track' : 'overdue' ?>"><?= htmlspecialchars($item['status']) ?></span></td><td><?= htmlspecialchars($item['reviewed_at'] ?? '-') ?></td><td><?= htmlspecialchars($item['reviewer'] ?? '-') ?></td><td><?= htmlspecialchars($item['admin_note'] ?? '-') ?></td></tr><?php endforeach; ?>
<?php if (!$history): ?><tr><td colspan="6">No decisions have been recorded yet.</td></tr><?php endif; ?>
</tbody></table></div></section>
<?php require __DIR__ . '/../includes/footer.php'; ?>

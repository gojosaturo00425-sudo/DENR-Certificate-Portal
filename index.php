<?php
require_once __DIR__ . '/includes/auth.php';
$page_title = 'Dashboard';
if (!current_user()) {
    header('Location: ' . BASE_URL . '/auth/login.php');
    exit;
}
if (current_user()['role_name'] === 'Employee') {
    header('Location: ' . BASE_URL . '/employee/dashboard.php');
    exit;
}
$totalEmployees = (int)$pdo->query('SELECT COUNT(*) FROM employees')->fetchColumn();
$completedTrainings = (int)$pdo->query("SELECT COUNT(*) FROM training_registrations WHERE status='Completed'")->fetchColumn();
$activeCerts = (int)$pdo->query("SELECT COUNT(*) FROM certificates WHERE status IN ('ACTIVE','APPROVED')")->fetchColumn();
$expiring = (int)$pdo->query("SELECT COUNT(*) FROM certificates WHERE valid_until BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 90 DAY) AND status IN ('ACTIVE','APPROVED','EXPIRING')")->fetchColumn();
$expired = (int)$pdo->query("SELECT COUNT(*) FROM certificates WHERE valid_until < CURDATE() AND status NOT IN ('REVOKED','EXPIRED')")->fetchColumn();
$pending = (int)$pdo->query("SELECT COUNT(*) FROM certificates WHERE status='PENDING'")->fetchColumn();
$noticeStmt = $pdo->prepare('SELECT title,message,created_at FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 8');
$noticeStmt->execute([current_user()['id']]);
$notices = $noticeStmt->fetchAll();
require __DIR__ . '/includes/header.php';
?>
<div class="dashboard-heading"><div><span class="dashboard-eyebrow">ADMINISTRATIVE OVERVIEW</span><h1>Portal Dashboard</h1><p>Monitor employee training, certificates, and requests across the portal.</p></div><span class="heading-icon"><?= $portalIcon('dashboard') ?></span></div>
<div class="cards dashboard-metrics">
<div class="card metric-card"><span class="metric-icon green"><?= $portalIcon('employees') ?></span><div><h3>Total Employees</h3><div class="num"><?= $totalEmployees ?></div><small>Registered staff profiles</small></div></div>
<div class="card metric-card"><span class="metric-icon blue"><?= $portalIcon('training') ?></span><div><h3>Completed Trainings</h3><div class="num"><?= $completedTrainings ?></div><small>Marked as completed</small></div></div>
<div class="card metric-card"><span class="metric-icon teal"><?= $portalIcon('certificate') ?></span><div><h3>Active Certificates</h3><div class="num"><?= $activeCerts ?></div><small>Valid employee certificates</small></div></div>
<div class="card metric-card"><span class="metric-icon amber"><?= $portalIcon('certificate') ?></span><div><h3>Expiring within 90 days</h3><div class="num"><?= $expiring ?></div><small>Coming up for renewal</small></div></div>
<div class="card metric-card"><span class="metric-icon red"><?= $portalIcon('certificate') ?></span><div><h3>Expired Certifications</h3><div class="num"><?= $expired ?></div><small>Past their validity date</small></div></div>
<div class="card metric-card"><span class="metric-icon violet"><?= $portalIcon('dashboard') ?></span><div><h3>Pending Approval</h3><div class="num"><?= $pending ?></div><small>Requests awaiting review</small></div></div>
</div>
<div class="dashboard-columns">
<section class="panel dashboard-panel"><div class="panel-heading"><span class="panel-icon"><?= $portalIcon('dashboard') ?></span><div><h2>Portal Notifications</h2><p>Recent updates that need your attention.</p></div></div>
<?php if ($notices): ?><ul class="notification-list"><?php foreach ($notices as $notice): ?><li><span class="notification-dot"></span><div><strong><?= htmlspecialchars($notice['title']) ?></strong><p><?= htmlspecialchars($notice['message']) ?></p><span class="small"><?= htmlspecialchars($notice['created_at']) ?></span></div></li><?php endforeach; ?></ul><?php else: ?><p class="empty-state">No notifications yet. New request updates will appear here.</p><?php endif; ?>
<a class="btn dashboard-action" href="<?= BASE_URL ?>/admin/certificates.php"><?= $portalIcon('certificate') ?> Review certificate requests</a></section>
<section class="panel dashboard-panel workflow-panel"><div class="panel-heading"><span class="panel-icon workflow-check">✓</span><div><h2>System Workflow</h2><p>From employee training to verified certificate.</p></div></div><ol class="workflow-list"><li><span>01</span>Training is created</li><li><span>02</span>Employee registers and attends</li><li><span>03</span>Completion is validated</li><li><span>04</span>Certificate is reviewed and issued</li><li><span>05</span>Employee receives certificate and QR verification</li></ol></section>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>

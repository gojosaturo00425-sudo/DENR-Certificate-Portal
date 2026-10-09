<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/certificate_sla.php';
$portalUser = current_user();
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
$portalNotifications = [];
$unreadNotificationCount = 0;
if ($portalUser) {
    $_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));
    if ($portalUser['role_name'] === 'Employee' && !empty($portalUser['employee_id'])) {
        $availableTrainings = $pdo->query("SELECT t.id,t.title,t.start_at FROM trainings t WHERE t.status='Published' AND t.start_at>=NOW() AND (t.max_participants=0 OR (SELECT COUNT(*) FROM training_registrations r WHERE r.training_id=t.id AND r.status<>'Cancelled')<t.max_participants) ORDER BY t.start_at ASC")->fetchAll();
        $trainingNoticeExists = $pdo->prepare("SELECT 1 FROM notifications WHERE user_id=? AND type='training_available' AND message LIKE ? LIMIT 1");
        $createTrainingNotice = $pdo->prepare("INSERT INTO notifications (employee_id,user_id,title,message,type) VALUES (?,?,? ,?,'training_available')");
        foreach ($availableTrainings as $availableTraining) {
            $trainingNoticeExists->execute([(int)$portalUser['id'], 'TRAINING_ID:' . (int)$availableTraining['id'] . ' |%']);
            if ($trainingNoticeExists->fetchColumn()) continue;
            $startLabel = date('M j, Y g:i A', strtotime($availableTraining['start_at']));
            $createTrainingNotice->execute([(int)$portalUser['employee_id'], (int)$portalUser['id'], 'New training available', 'TRAINING_ID:' . (int)$availableTraining['id'] . ' | ' . $availableTraining['title'] . ' is open for registration. Training starts ' . $startLabel . '.']);
        }
    }
    if (in_array($portalUser['role_name'], ['System Administrator', 'Regional Training Administrator', 'Approving Officer'], true)) {
        create_certificate_sla_reminders($pdo, [(int)$portalUser['id']]);
    }
    if ($portalUser['role_name'] === 'Employee' && !empty($portalUser['employee_id'])) {
        $notificationStmt = $pdo->prepare('SELECT id,title,message,type,created_at,is_read FROM notifications WHERE user_id=? OR employee_id=? ORDER BY created_at DESC LIMIT 7');
        $notificationStmt->execute([(int)$portalUser['id'], (int)$portalUser['employee_id']]);
    } else {
        $notificationStmt = $pdo->prepare('SELECT id,title,message,type,created_at,is_read FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 7');
        $notificationStmt->execute([(int)$portalUser['id']]);
    }
    $portalNotifications = $notificationStmt->fetchAll();
    foreach ($portalNotifications as &$portalNotification) {
        $portalNotification['message'] = preg_replace('/^TRAINING_ID:\\d+\\s*\\|\\s*/', '', $portalNotification['message']);
    }
    unset($portalNotification);
    if ($portalUser['role_name'] === 'Employee' && !empty($portalUser['employee_id'])) {
        $unreadStmt = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE is_read=0 AND (user_id=? OR employee_id=?)');
        $unreadStmt->execute([(int)$portalUser['id'], (int)$portalUser['employee_id']]);
    } else {
        $unreadStmt = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE is_read=0 AND user_id=?');
        $unreadStmt->execute([(int)$portalUser['id']]);
    }
    $unreadNotificationCount = (int)$unreadStmt->fetchColumn();
}
$portalIcon = static function (string $name): string {
    $paths = [
        'dashboard' => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/>',
        'settings' => '<circle cx="12" cy="12" r="3"/><path d="m19.4 15 .1.1 1.4 1.1-1.4 2.4-1.7-.7a8 8 0 0 1-1.6.9l-.3 1.8h-2.8l-.3-1.8a8 8 0 0 1-1.6-.9l-1.7.7-1.4-2.4 1.4-1.1a8 8 0 0 1 0-1.9l-1.4-1.1 1.4-2.4 1.7.7a8 8 0 0 1 1.6-.9l.3-1.8h2.8l.3 1.8a8 8 0 0 1 1.6.9l1.7-.7 1.4 2.4-1.4 1.1a8 8 0 0 1 0 1.9Z"/>',
        'employees' => '<path d="M16 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="10" cy="7" r="4"/><path d="M20 21v-2a4 4 0 0 0-3-3.9M16 3.1a4 4 0 0 1 0 7.8"/>',
        'training' => '<path d="m2 10 10-5 10 5-10 5-10-5Z"/><path d="M6 12v5c3.5 3 8.5 3 12 0v-5M22 10v6"/>',
        'certificate' => '<path d="M7 3h8l5 5v7"/><path d="M15 3v5h5M5 3H4a2 2 0 0 0-2 2v15a2 2 0 0 0 2 2h9"/><circle cx="17" cy="17" r="3"/><path d="m15.5 19.5-.5 3 2-1 2 1-.5-3"/>',
        'verify' => '<path d="m9 12 2 2 4-4"/><circle cx="11" cy="11" r="8"/><path d="m16.5 16.5 4 4"/>',
        'logout' => '<path d="M10 17l5-5-5-5M15 12H3"/><path d="M12 3h6a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-6"/>',
        'bell' => '<path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/>',
        'leaf' => '<path d="M20 4c-8 0-14 4-14 11a5 5 0 0 0 5 5c7 0 11-6 9-16Z"/><path d="M4 21c2-5 6-9 12-12"/>',
    ];
    return '<svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($paths[$name] ?? $paths['dashboard']) . '</svg>';
};
$nav = [];
if (!$portalUser || $portalUser['role_name'] !== 'Employee') {
    $nav[] = ['dashboard', 'Dashboard', BASE_URL . '/index.php'];
}
if ($portalUser) {
    if ($portalUser['role_name'] === 'Employee') {
        $nav[] = ['dashboard', 'My Dashboard', BASE_URL . '/employee/dashboard.php'];
        $nav[] = ['training', 'My Training', BASE_URL . '/employee/my_training.php'];
        $nav[] = ['certificate', 'My Certificates', BASE_URL . '/employee/my_certificates.php'];
    } else {
        $nav[] = ['employees', 'Employees', BASE_URL . '/admin/employees.php'];
        $nav[] = ['training', 'Trainings', BASE_URL . '/admin/trainings.php'];
        $nav[] = ['certificate', 'Certifications / Requests', BASE_URL . '/admin/certificates.php'];
    }
}
$nav[] = ['verify', 'Verify Certificate', BASE_URL . '/public/verify.php'];
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($page_title ?? 'DENR XII Portal') ?></title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/style.css">
</head>
<body class="portal-body">
<header class="topbar">
  <a class="portal-brand" href="<?= BASE_URL ?>/index.php" aria-label="DENR XII Employee Certification Portal home"><img class="brand-logo" src="<?= BASE_URL ?>/assets/denr-logo.webp" alt="DENR seal"><span><strong>DENR XII</strong><small>Employee Certification Portal</small></span></a>
  <?php if ($portalUser): ?>
  <div class="userbar"><details class="notification-bell"><summary aria-label="Notifications, <?= $unreadNotificationCount ?> unread"><?= $portalIcon('bell') ?><?php if ($unreadNotificationCount): ?><span class="bell-count"><?= $unreadNotificationCount > 9 ? '9+' : $unreadNotificationCount ?></span><?php endif; ?></summary><div class="notification-popover"><div class="notification-popover-head"><div><strong>Notifications</strong><small><?= $unreadNotificationCount ?> unread</small></div><?php if ($unreadNotificationCount): ?><form method="post" action="<?= BASE_URL ?>/notifications/read.php"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>"><button type="submit">Mark all read</button></form><?php endif; ?></div><?php if ($portalNotifications): ?><ul><?php foreach ($portalNotifications as $portalNotification): ?><li class="<?= (int)$portalNotification['is_read'] ? '' : 'unread' ?>"><span class="popover-dot"></span><div><strong><?= htmlspecialchars($portalNotification['title']) ?></strong><p><?= htmlspecialchars($portalNotification['message']) ?></p><small><?= htmlspecialchars($portalNotification['created_at']) ?></small></div></li><?php endforeach; ?></ul><?php else: ?><p class="notification-empty">You’re all caught up. New training announcements and request updates will appear here.</p><?php endif; ?></div></details><span class="user-avatar"><?= htmlspecialchars(strtoupper(substr($portalUser['username'], 0, 1))) ?></span><span class="user-meta"><strong><?= htmlspecialchars($portalUser['username']) ?></strong><small><?= htmlspecialchars($portalUser['role_name']) ?></small></span><a class="logout-link" href="<?= BASE_URL ?>/auth/logout.php"><?= $portalIcon('logout') ?><span>Sign out</span></a></div>
  <?php endif; ?>
</header>
<div class="layout">
<aside class="sidebar"><div class="sidebar-caption">WORKSPACE</div><nav class="side-nav">
<?php foreach ($nav as [$icon, $label, $href]): $path = parse_url($href, PHP_URL_PATH) ?: ''; $active = $path === $currentPath; ?>
<a class="nav-link<?= $active ? ' active' : '' ?>" href="<?= htmlspecialchars($href) ?>"<?= $active ? ' aria-current="page"' : '' ?>><?= $portalIcon($icon) ?><span><?= htmlspecialchars($label) ?></span></a>
<?php endforeach; ?>
</nav><div class="sidebar-footer"><?php if ($portalUser): ?><a class="footer-settings<?= str_ends_with($currentPath, '/account/settings.php') ? ' active' : '' ?>" href="<?= BASE_URL ?>/account/settings.php"><?= $portalIcon('settings') ?><span>Account Settings</span></a><?php if (in_array($portalUser['role_name'], ['System Administrator', 'Regional Training Administrator', 'Approving Officer'], true)): ?><a class="footer-settings<?= str_ends_with($currentPath, '/admin/transaction_report.php') ? ' active' : '' ?>" href="<?= BASE_URL ?>/admin/transaction_report.php"><?= $portalIcon('dashboard') ?><span>Transaction Report</span></a><?php endif; ?><?php endif; ?><div class="footer-office"><span class="footer-leaf"><?= $portalIcon('leaf') ?></span><span><strong>Regional Office XII</strong><small>Environment · Service · Integrity</small></span></div></div></aside>
<main class="content">

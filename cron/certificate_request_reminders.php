<?php
// Run once daily from Windows Task Scheduler or cron.
// Example: php C:\\xampp\\htdocs\\denr_xii_portal\\cron\\certificate_request_reminders.php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/certificate_sla.php';

$today = new DateTimeImmutable('today', new DateTimeZone('Asia/Manila'));
if ((int)$today->format('N') > 4) {
    echo "No certificate SLA reminders are sent Friday through Sunday.\n";
    exit;
}

$admins = $pdo->query("SELECT u.id FROM users u JOIN roles r ON r.id=u.role_id WHERE u.is_active=1 AND r.name IN ('System Administrator','Regional Training Administrator','Approving Officer')")->fetchAll(PDO::FETCH_COLUMN);
if (!$admins) {
    echo "No active certificate reviewers found.\n";
    exit;
}

$sent = create_certificate_sla_reminders($pdo, array_map('intval', $admins), $today);

echo "Created {$sent} certificate review reminder(s).\n";

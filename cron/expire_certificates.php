<?php
// Run daily using Windows Task Scheduler or a cron job.
// php C:\xampp\htdocs\denr_xii_portal\cron\expire_certificates.php
require_once __DIR__ . '/../includes/config.php';
$pdo->exec("UPDATE certificates SET status='EXPIRED' WHERE valid_until < CURDATE() AND status IN ('ACTIVE','APPROVED','EXPIRING')");
$pdo->exec("UPDATE certificates SET status='EXPIRING' WHERE valid_until BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 90 DAY) AND status='ACTIVE'");
echo "Certificate status refresh completed.\n";

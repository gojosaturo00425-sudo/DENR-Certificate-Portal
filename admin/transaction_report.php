<?php
require_once __DIR__ . '/../includes/auth.php';
require_roles(['System Administrator', 'Regional Training Administrator', 'Approving Officer']);
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/mailer_config.php';

$timezone = new DateTimeZone('Asia/Manila');
$_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));
$emailError = '';
$emailSubject = '';
$emailDescription = '';
$today = new DateTimeImmutable('today', $timezone);
$defaultFrom = $today->modify('first day of this month')->format('Y-m-d');
$from = (string)($_GET['from'] ?? $defaultFrom);
$to = (string)($_GET['to'] ?? $today->format('Y-m-d'));
$validDate = static function (string $value): bool {
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return $date && $date->format('Y-m-d') === $value;
};
if (!$validDate($from)) $from = $defaultFrom;
if (!$validDate($to)) $to = $today->format('Y-m-d');
if ($from > $to) [$from, $to] = [$to, $from];
$emailSubject = 'DENR XII Transaction Summary ' . $from . ' to ' . $to;
$emailDescription = 'Please find the DENR XII portal transaction summary for the selected reporting period.';
$rangeStart = $from . ' 00:00:00';
$rangeEnd = (new DateTimeImmutable($to, $timezone))->modify('+1 day')->format('Y-m-d') . ' 00:00:00';

$count = static function (PDO $pdo, string $sql, string $dateColumn, string $start, string $end): int {
    $statement = $pdo->prepare($sql . " WHERE {$dateColumn} >= ? AND {$dateColumn} < ?");
    $statement->execute([$start, $end]);
    return (int)$statement->fetchColumn();
};
$summary = [
    'Employees registered' => $count($pdo, 'SELECT COUNT(*) FROM employees', 'created_at', $rangeStart, $rangeEnd),
    'Training programs created' => $count($pdo, 'SELECT COUNT(*) FROM trainings', 'created_at', $rangeStart, $rangeEnd),
    'Training registrations' => $count($pdo, 'SELECT COUNT(*) FROM training_registrations', 'registered_at', $rangeStart, $rangeEnd),
    'Certificate requests' => $count($pdo, 'SELECT COUNT(*) FROM certificates', 'created_at', $rangeStart, $rangeEnd),
    'Certificates emailed' => $count($pdo, 'SELECT COUNT(*) FROM certificates', 'email_sent_at', $rangeStart, $rangeEnd),
    'Cancellation requests' => $count($pdo, 'SELECT COUNT(*) FROM training_cancellation_requests', 'requested_at', $rangeStart, $rangeEnd),
];

$auditStmt = $pdo->prepare('SELECT a.created_at,a.action,a.entity_type,a.entity_id,a.details,u.username FROM audit_logs a LEFT JOIN users u ON u.id=a.user_id WHERE a.created_at>=? AND a.created_at<? ORDER BY a.created_at DESC,a.id DESC LIMIT 500');
$auditStmt->execute([$rangeStart, $rangeEnd]);
$transactions = $auditStmt->fetchAll();
$pendingCertificateCount = (int)$pdo->query("SELECT COUNT(*) FROM certificates WHERE status='PENDING'")->fetchColumn();
$pendingCancellationCount = (int)$pdo->query("SELECT COUNT(*) FROM training_cancellation_requests WHERE status='Pending'")->fetchColumn();

$emailRecipient = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_report_email'])) {
    $emailRecipient = trim((string)($_POST['email_to'] ?? ''));
    $emailSubject = trim((string)($_POST['email_subject'] ?? ''));
    $emailDescription = trim((string)($_POST['email_description'] ?? ''));
    if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], (string)$_POST['csrf_token'])) {
        $emailError = 'Your form session expired. Refresh the page and try again.';
    } elseif (!filter_var($emailRecipient, FILTER_VALIDATE_EMAIL)) {
        $emailError = 'Enter a valid recipient email address.';
    } elseif ($emailSubject === '' || strlen($emailSubject) > 200 || preg_match('/[\r\n]/', $emailSubject)) {
        $emailError = 'Enter an email subject up to 200 characters without line breaks.';
    } elseif (strlen($emailDescription) > 2000) {
        $emailError = 'The email description must be 2,000 characters or fewer.';
    } else {
        try {
            $escape = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $summaryRows = '';
            $textLines = [
                $emailSubject,
                $emailDescription,
                'Reporting period: ' . $from . ' to ' . $to,
                '',
            ];
            foreach ($summary as $label => $value) {
                $summaryRows .= '<tr><th style="padding:8px;text-align:left;border-bottom:1px solid #ddd">' . $escape($label) . '</th><td style="padding:8px;text-align:right;border-bottom:1px solid #ddd">' . number_format($value) . '</td></tr>';
                $textLines[] = $label . ': ' . $value;
            }
            $textLines[] = 'Pending certificate requests (current): ' . $pendingCertificateCount;
            $textLines[] = 'Pending cancellation requests (current): ' . $pendingCancellationCount;
            $textLines[] = 'Recorded activity entries: ' . count($transactions);
            $activityRows = '';
            foreach ($transactions as $transaction) {
                $record = trim(($transaction['entity_type'] ?? '') . ' #' . ($transaction['entity_id'] ?? ''));
                $activityRows .= '<tr><td>' . $escape((string)$transaction['created_at']) . '</td><td>' . $escape((string)$transaction['action']) . '</td><td>' . $escape($record) . '</td><td>' . $escape((string)($transaction['details'] ?? '')) . '</td><td>' . $escape((string)($transaction['username'] ?? 'System')) . '</td></tr>';
            }
            if ($activityRows === '') {
                $activityRows = '<tr><td colspan="5">No audit activity was recorded during this period.</td></tr>';
            }
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            configureApplicationMailer($mail);
            $mail->CharSet = \PHPMailer\PHPMailer\PHPMailer::CHARSET_UTF8;
            $mail->addAddress($emailRecipient);
            $mail->Subject = $emailSubject;
            $mail->isHTML(true);
            $mail->Body = '<div style="font-family:Arial,sans-serif;color:#24372b"><h2>' . $escape($emailSubject) . '</h2><p>' . nl2br($escape($emailDescription)) . '</p><p>Reporting period: <strong>' . $escape($from) . ' to ' . $escape($to) . '</strong></p><table style="border-collapse:collapse;width:100%;max-width:650px">' . $summaryRows . '</table><h3>Current pending items</h3><p>' . number_format($pendingCertificateCount) . ' certificate request(s) and ' . number_format($pendingCancellationCount) . ' training cancellation request(s) are awaiting review.</p><h3>Transaction activity (' . count($transactions) . ' most recent entries)</h3><table style="border-collapse:collapse;width:100%;font-size:12px"><thead><tr><th>Date and time</th><th>Action</th><th>Record</th><th>Details</th><th>Portal user</th></tr></thead><tbody>' . $activityRows . '</tbody></table><p>Prepared by the DENR XII Employee Certification Portal.</p></div>';
            $mail->AltBody = implode("\n", $textLines) . "\n\nThis summary was prepared in the DENR XII Employee Certification Portal.";
            $mail->send();
            header('Location: ' . BASE_URL . '/admin/transaction_report.php?' . http_build_query(['from' => $from, 'to' => $to, 'email' => 'sent']));
            exit;
        } catch (Throwable $exception) {
            error_log('Transaction summary email failed: ' . $exception->getMessage());
            $emailError = 'The report could not be emailed. Check the SMTP settings and recipient address, then try again.';
        }
    }
}

$page_title = 'Transaction Summary Report';
require __DIR__ . '/../includes/header.php';
?>
<div class="dashboard-heading report-heading"><div><span class="dashboard-eyebrow">ADMINISTRATION</span><h1>Transaction Summary Report</h1><p>Review portal activity for the selected period, then print or email the summary.</p></div><span class="heading-icon"><?= $portalIcon('dashboard') ?></span></div>

<?php if (($_GET['email'] ?? '') === 'sent'): ?><div class="notice success" role="status">Transaction summary sent successfully to the recipient email.</div><?php endif; ?><?php if ($emailError): ?><div class="notice error" role="alert"><?= htmlspecialchars($emailError) ?></div><?php endif; ?>
<section class="panel dashboard-panel report-filter no-print"><div class="report-controls"><form method="get" class="report-filter-form"><div class="field"><label for="from">From</label><input type="date" id="from" name="from" value="<?= htmlspecialchars($from) ?>" required></div><div class="field"><label for="to">To</label><input type="date" id="to" name="to" value="<?= htmlspecialchars($to) ?>" required></div><button class="btn" type="submit">Update report</button></form><button class="btn secondary" type="button" onclick="window.print()">Print report</button></div><form method="post" class="report-email-form"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>"><input type="hidden" name="from" value="<?= htmlspecialchars($from) ?>"><input type="hidden" name="to" value="<?= htmlspecialchars($to) ?>"><div class="field"><label for="email_to">Recipient email</label><input type="email" id="email_to" name="email_to" value="<?= htmlspecialchars($emailRecipient) ?>" placeholder="name@example.com" autocomplete="email" required></div><div class="field"><label for="email_subject">Subject</label><input type="text" id="email_subject" name="email_subject" maxlength="200" value="<?= htmlspecialchars($emailSubject, ENT_QUOTES, 'UTF-8') ?>" required></div><div class="field report-email-description"><label for="email_description">Description</label><textarea id="email_description" name="email_description" maxlength="2000" rows="3" placeholder="Add a short message for the recipient."><?= htmlspecialchars($emailDescription) ?></textarea></div><button class="btn" type="submit" name="send_report_email" value="1">Send report</button></form></section>

<div class="report-meta"><strong>Reporting period:</strong> <?= htmlspecialchars($from) ?> to <?= htmlspecialchars($to) ?> <span>Generated <?= htmlspecialchars((new DateTimeImmutable('now', $timezone))->format('M j, Y g:i A')) ?></span></div>
<section class="cards dashboard-metrics report-metrics">
<?php $metricIcons = ['Employees registered' => 'employees', 'Training programs created' => 'training', 'Training registrations' => 'training', 'Certificate requests' => 'certificate', 'Certificates emailed' => 'certificate', 'Cancellation requests' => 'dashboard']; foreach ($summary as $label => $value): ?><article class="card metric-card"><span class="metric-icon blue"><?= $portalIcon($metricIcons[$label]) ?></span><div><h3><?= htmlspecialchars($label) ?></h3><div class="num"><?= number_format($value) ?></div><small>During selected period</small></div></article><?php endforeach; ?>
</section>

<section class="report-current-status"><h2>Current pending items</h2><p><strong><?= number_format($pendingCertificateCount) ?></strong> certificate request(s) awaiting review <span>•</span> <strong><?= number_format($pendingCancellationCount) ?></strong> training cancellation request(s) awaiting review</p></section>

<section class="panel dashboard-panel report-activity"><div class="panel-heading"><span class="panel-icon"><?= $portalIcon('dashboard') ?></span><div><h2>Transaction Activity</h2><p>Audit trail entries recorded during the selected period (up to 500 most recent).</p></div></div><div class="table-wrap"><table class="table"><thead><tr><th>Date and time</th><th>Action</th><th>Record</th><th>Details</th><th>Portal user</th></tr></thead><tbody>
<?php foreach ($transactions as $transaction): ?><tr><td><?= htmlspecialchars($transaction['created_at']) ?></td><td><?= htmlspecialchars($transaction['action']) ?></td><td><?= htmlspecialchars(trim(($transaction['entity_type'] ?? '') . ' #' . ($transaction['entity_id'] ?? ''))) ?></td><td><?= htmlspecialchars($transaction['details'] ?? '') ?></td><td><?= htmlspecialchars($transaction['username'] ?? 'System') ?></td></tr><?php endforeach; ?>
<?php if (!$transactions): ?><tr><td colspan="5">No audit activity was recorded during this period.</td></tr><?php endif; ?>
</tbody></table></div></section>
<p class="report-footnote">Email delivery uses the portal's configured SMTP account. Use the print action to print or save this report as PDF.</p>
<?php require __DIR__ . '/../includes/footer.php'; ?>

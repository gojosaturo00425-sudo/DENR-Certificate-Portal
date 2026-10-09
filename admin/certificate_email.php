<?php
require_once __DIR__ . '/../includes/auth.php';
require_roles(['System Administrator','Regional Training Administrator','Approving Officer']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST'
    || empty($_POST['csrf_token'])
    || !hash_equals($_SESSION['csrf_token'] ?? '', (string)$_POST['csrf_token'])) {
    http_response_code(400);
    exit('Invalid request. Refresh the certificates page and try again.');
}

$certificateId = (int)($_POST['certificate_id'] ?? 0);
try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("SELECT c.certificate_no,c.pdf_path,c.email_sent_at,c.status,e.id employee_id,e.email,e.first_name,e.last_name,COALESCE(c.certificate_type,t.title,'Training Certificate') training_title FROM certificates c JOIN employees e ON e.id=c.employee_id LEFT JOIN trainings t ON t.id=c.training_id WHERE c.id=? FOR UPDATE");
    $stmt->execute([$certificateId]);
    $certificate = $stmt->fetch();
    if (!$certificate || $certificate['status'] !== 'ACTIVE' || !filter_var($certificate['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
        $pdo->rollBack();
        header('Location: certificates.php?email=failed');
        exit;
    }
    if ($certificate['email_sent_at'] !== null) {
        $pdo->rollBack();
        header('Location: certificates.php?email=already_sent');
        exit;
    }

    $storageRoot = realpath(dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'certificates');
    $filePath = $storageRoot ? realpath(dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $certificate['pdf_path'] ?? '')) : false;
    if (!$storageRoot || !$filePath || !str_starts_with(strtolower($filePath), strtolower($storageRoot . DIRECTORY_SEPARATOR))) {
        $pdo->rollBack();
        header('Location: certificates.php?email=failed');
        exit;
    }

    require_once __DIR__ . '/../includes/mailer.php';
    send_certificate_email($certificate['email'], $certificate['first_name'] . ' ' . $certificate['last_name'], $certificate['training_title'], $certificate['certificate_no'], $filePath);

    $markSent = $pdo->prepare('UPDATE certificates SET email_sent_at=NOW() WHERE id=? AND email_sent_at IS NULL');
    $markSent->execute([$certificateId]);
    if ($markSent->rowCount() !== 1) {
        throw new RuntimeException('The email delivery status could not be recorded.');
    }
    $pdo->commit();
    try {
        audit('SEND_CERTIFICATE_EMAIL', 'certificate', $certificateId, 'Certificate image email sent successfully');
    } catch (Throwable $auditException) {
        error_log('Certificate email audit failed for certificate ' . $certificateId . ': ' . $auditException->getMessage());
    }
    try {
        $notice = $pdo->prepare("INSERT INTO notifications (employee_id,title,message,type) VALUES (?,?,?,'certificate_emailed')");
        $notice->execute([(int)$certificate['employee_id'], 'Certificate email sent', 'Your certificate image for ' . $certificate['training_title'] . ' was sent successfully to your email address.']);
    } catch (Throwable $notificationException) {
        error_log('Certificate email notification failed for certificate ' . $certificateId . ': ' . $notificationException->getMessage());
    }
    header('Location: certificates.php?email=sent');
    exit;
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Certificate email delivery failed for certificate ' . $certificateId . ': ' . $exception->getMessage());
    header('Location: certificates.php?email=failed');
    exit;
}

<?php
require_once __DIR__ . '/../includes/auth.php';
require_roles(['System Administrator','Regional Training Administrator','Approving Officer']);

$certificateId = (int)($_GET['id'] ?? $_POST['certificate_id'] ?? 0);
$requestStmt = $pdo->prepare("SELECT c.id,c.certificate_no,c.status,e.id employee_id,e.first_name,e.last_name,e.email,e.employee_no,COALESCE(c.certificate_type,t.title,'Training Certificate') training_title FROM certificates c JOIN employees e ON e.id=c.employee_id LEFT JOIN trainings t ON t.id=c.training_id WHERE c.id=? LIMIT 1");
$requestStmt->execute([$certificateId]);
$request = $requestStmt->fetch();
if (!$request || $request['status'] !== 'PENDING') {
    header('Location: ' . BASE_URL . '/admin/certificates.php');
    exit;
}

$_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));
$error = '';
$allowedTypes = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], (string)$_POST['csrf_token'])) {
        $error = 'Your form session expired. Refresh and try again.';
    } elseif (!filter_var($request['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
        $error = 'The employee does not have a valid email address. Update their profile before approving.';
    } elseif (!isset($_FILES['certificate_file']) || $_FILES['certificate_file']['error'] !== UPLOAD_ERR_OK) {
        $error = 'Choose a certificate file to upload.';
    } elseif ((int)$_FILES['certificate_file']['size'] > 10 * 1024 * 1024) {
        $error = 'The certificate file must be 10 MB or smaller.';
    } else {
        $temporaryFile = $_FILES['certificate_file']['tmp_name'];
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($temporaryFile);
        if (!isset($allowedTypes[$mimeType]) || @getimagesize($temporaryFile) === false) {
            $error = 'Upload a valid JPG, PNG, or WebP certificate image.';
        } else {
            $storageDirectory = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'certificates';
            if (!is_dir($storageDirectory) && !mkdir($storageDirectory, 0750, true) && !is_dir($storageDirectory)) {
                $error = 'The private certificate storage folder could not be created.';
            } else {
                $fileName = 'certificate-' . $certificateId . '-' . bin2hex(random_bytes(12)) . '.' . $allowedTypes[$mimeType];
                $absolutePath = $storageDirectory . DIRECTORY_SEPARATOR . $fileName;
                $relativePath = 'storage/certificates/' . $fileName;
                if (!move_uploaded_file($temporaryFile, $absolutePath)) {
                    $error = 'The uploaded certificate could not be saved.';
                } else {
                    try {
                        $pdo->beginTransaction();
                        $number = 'DENR12-' . date('Y') . '-' . str_pad((string)$certificateId, 6, '0', STR_PAD_LEFT);
                        $approve = $pdo->prepare("UPDATE certificates SET certificate_no=?,pdf_path=?,status='ACTIVE',issued_date=CURDATE(),valid_until=DATE_ADD(CURDATE(),INTERVAL 2 YEAR),approved_by=?,approved_at=NOW() WHERE id=? AND status='PENDING'");
                        $approve->execute([$number, $relativePath, (int)current_user()['id'], $certificateId]);
                        if ($approve->rowCount() !== 1) {
                            throw new RuntimeException('This request has already been processed.');
                        }

                        audit('APPROVE', 'certificate', $certificateId, 'Certificate approved and attachment uploaded: ' . $fileName);
                        $pdo->commit();
                    } catch (Throwable $exception) {
                        if ($pdo->inTransaction()) $pdo->rollBack();
                        @unlink($absolutePath);
                        $error = 'The certificate could not be approved. Please refresh and try again.';
                    }

                    if ($error === '') {
                        $emailSent = false;
                        try {
                            require_once __DIR__ . '/../includes/mailer.php';
                            send_certificate_email($request['email'], $request['first_name'] . ' ' . $request['last_name'], $request['training_title'], $number, $absolutePath);
                            $markSent = $pdo->prepare('UPDATE certificates SET email_sent_at=NOW() WHERE id=? AND email_sent_at IS NULL');
                            $markSent->execute([$certificateId]);
                            if ($markSent->rowCount() !== 1) {
                                throw new RuntimeException('Certificate email delivery status could not be recorded.');
                            }
                            $emailSent = true;
                        } catch (Throwable $exception) {
                            error_log('Certificate email failed for certificate ' . $certificateId . ': ' . $exception->getMessage());
                        }

                        try {
                            $notify = $pdo->prepare("INSERT INTO notifications (employee_id,title,message,type) VALUES (?,?,?,?)");
                            $notice = $emailSent
                                ? 'Your certificate for ' . $request['training_title'] . ' was approved and sent to your email.'
                                : 'Your certificate request for ' . $request['training_title'] . ' was approved, but the email could not be delivered. Please contact the portal administrator.';
                            $notify->execute([(int)$request['employee_id'], 'Certificate approved', $notice, 'certificate_approved']);
                        } catch (Throwable $exception) {
                            error_log('Certificate approval notification failed for certificate ' . $certificateId . ': ' . $exception->getMessage());
                        }

                        $deliveryResult = $emailSent ? 'sent' : 'failed';
                        header('Location: ' . BASE_URL . '/admin/certificates.php?email=' . $deliveryResult);
                        exit;
                    }
                }
            }
        }
    }
}

$page_title = 'Approve Certificate Request';
require __DIR__ . '/../includes/header.php';
?>
<h1>Approve Certificate Request</h1>
<div class="panel approval-details"><h2><?= htmlspecialchars($request['training_title']) ?></h2><p><strong>Employee:</strong> <?= htmlspecialchars($request['employee_no'] . ' · ' . $request['first_name'] . ' ' . $request['last_name']) ?></p><p><strong>Email:</strong> <?= htmlspecialchars($request['email'] ?? 'No email provided') ?></p><p><strong>Request reference:</strong> <?= htmlspecialchars($request['certificate_no']) ?></p></div><br>
<?php if ($error): ?><div class="notice error" role="alert"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<form method="post" enctype="multipart/form-data" class="panel certificate-upload-form">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
    <input type="hidden" name="certificate_id" value="<?= $certificateId ?>">
    <div class="upload-heading"><span class="upload-icon" aria-hidden="true">↑</span><div><h2>Attach the approved certificate image</h2><p>The image will be sent to the employee’s registered email address.</p></div></div>
    <div class="field"><label for="certificate_file">Certificate image</label><input class="certificate-file-input" id="certificate_file" type="file" name="certificate_file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" required><small class="field-hint">JPG, PNG, or WebP image · maximum 10 MB</small></div>
    <div class="approval-actions"><button class="btn" type="submit">Approve and Email Certificate</button><a class="btn secondary" href="certificates.php">Cancel</a></div>
</form>
<?php require __DIR__ . '/../includes/footer.php'; ?>

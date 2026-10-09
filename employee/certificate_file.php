<?php
require_once __DIR__ . '/../includes/auth.php';
require_roles(['Employee']);

$employeeId = (int)(current_user()['employee_id'] ?? 0);
$certificateId = (int)($_GET['id'] ?? 0);
if ($employeeId < 1 || $certificateId < 1) {
    http_response_code(404);
    exit('Certificate file not found.');
}

$stmt = $pdo->prepare("SELECT certificate_no,pdf_path FROM certificates WHERE id=? AND employee_id=? AND status IN ('ACTIVE','APPROVED','EXPIRING','EXPIRED') LIMIT 1");
$stmt->execute([$certificateId, $employeeId]);
$certificate = $stmt->fetch();
if (!$certificate || !$certificate['pdf_path']) {
    http_response_code(404);
    exit('No approved certificate file is available.');
}

$projectRoot = dirname(__DIR__);
$storageRoot = realpath($projectRoot . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'certificates');
$relativePath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, (string)$certificate['pdf_path']);
$filePath = realpath($projectRoot . DIRECTORY_SEPARATOR . $relativePath);
if (!$storageRoot || !$filePath || !str_starts_with(strtolower($filePath), strtolower($storageRoot . DIRECTORY_SEPARATOR)) || !is_file($filePath) || !is_readable($filePath)) {
    http_response_code(404);
    exit('Certificate file not found.');
}

$mimeType = (new finfo(FILEINFO_MIME_TYPE))->file($filePath);
$extensions = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'];
if (!isset($extensions[$mimeType])) {
    http_response_code(415);
    exit('This certificate file type cannot be viewed or downloaded.');
}

$safeNumber = preg_replace('/[^A-Za-z0-9_-]/', '-', (string)$certificate['certificate_no']);
$filename = 'certificate-' . $safeNumber . '.' . $extensions[$mimeType];
$disposition = isset($_GET['download']) ? 'attachment' : 'inline';
header('Content-Type: ' . $mimeType);
header('Content-Length: ' . (string)filesize($filePath));
header('Content-Disposition: ' . $disposition . '; filename="' . $filename . '"');
header('X-Content-Type-Options: nosniff');
readfile($filePath);
exit;

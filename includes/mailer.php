<?php
declare(strict_types=1);
use PHPMailer\PHPMailer\PHPMailer;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/mailer_config.php';

function send_certificate_email(string $recipientEmail, string $recipientName, string $trainingTitle, string $certificateNumber, string $attachmentPath): void
{
    if (!is_file($attachmentPath) || !is_readable($attachmentPath)) {
        throw new RuntimeException('The certificate attachment is missing or unreadable.');
    }
    $imageInfo = @getimagesize($attachmentPath);
    if ($imageInfo === false || !in_array($imageInfo['mime'] ?? '', ['image/jpeg', 'image/png', 'image/webp'], true)) {
        throw new RuntimeException('Only valid JPG, PNG, or WebP certificate images can be emailed.');
    }

    $mail = new PHPMailer(true);
    configureApplicationMailer($mail);
    $mail->CharSet = PHPMailer::CHARSET_UTF8;
    $mail->addAddress($recipientEmail, $recipientName);
    $mail->Subject = 'Your DENR XII training certificate';
    $safeName = htmlspecialchars($recipientName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $safeTraining = htmlspecialchars($trainingTitle, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $safeNumber = htmlspecialchars($certificateNumber, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $mail->isHTML(true);
    $mail->Body = '<p>Dear ' . $safeName . ',</p><p>Your certificate for <strong>' . $safeTraining . '</strong> has been approved.</p><p>Certificate number: <strong>' . $safeNumber . '</strong></p><p>Your certificate image is attached to this email.</p><p>DENR XII Employee Certification Portal</p>';
    $mail->AltBody = "Dear {$recipientName},\n\nYour certificate for {$trainingTitle} has been approved.\nCertificate number: {$certificateNumber}\n\nYour certificate image is attached to this email.\n\nDENR XII Employee Certification Portal";
    $mail->addAttachment($attachmentPath, basename($attachmentPath));
    $mail->send();
}

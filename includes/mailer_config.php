<?php
function configureApplicationMailer(\PHPMailer\PHPMailer\PHPMailer $mailer) {
    $mailer->isSMTP();
    $mailer->Host = 'smtp.gmail.com';
    $mailer->SMTPAuth = true;
    $mailer->Username = 'gojosaturo.00425@gmail.com';
    $mailer->Password = 'jmqgweinummchtjp';
    $mailer->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
    $mailer->Port = 465;
    $mailer->setFrom('gojosaturo.00425@gmail.com', 'PENRO Sarangani Human Resource');
    $mailer->addReplyTo('gojosaturo.00425@gmail.com', 'PENRO Sarangani Human Resource');
}
# Certificate email setup

The portal sends certificate attachments through PHPMailer and authenticated SMTP. PHPMailer is installed with Composer; run `composer install` after copying the project to another machine.

SMTP is configured by `configureApplicationMailer()` in `includes/mailer_config.php`. The certificate mailer loads this helper and uses it for the SMTP connection and sender address. For Gmail or Google Workspace, use `smtp.gmail.com`, an app password, implicit TLS, and port `465`.

Keep mail configuration private and out of source control. Do not send SMTP passwords in chat. If credentials were exposed, revoke the app password at Google and replace it in the local configuration. If delivery fails, the certificate stays in **Ready to Send** so an admin can try **Send Email** after fixing SMTP.

Certificate images are limited to JPG, PNG, or WebP and 10 MB. They are stored under `storage/certificates`, which Apache blocks from direct web access. After a successful delivery the record moves to **Sent Certificates** and cannot be sent again from the portal.

For an existing database, apply `sql/migrate_certificate_email_tracking.sql` once. It records successful certificate deliveries and preserves those found in the portal’s earlier delivery notifications or audit log. Fresh database imports include the tracking column in `sql/database.sql`.

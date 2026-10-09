# DENR XII Employee Certification Portal

XAMPP starter implementation using PHP 8+, MySQL/MariaDB, HTML, CSS and JavaScript.

## Requirements
- XAMPP (Apache + MySQL)
- PHP 8.1+
- MySQL/MariaDB
- Browser

## Installation
1. Copy the `denr_xii_portal` folder to `C:\xampp\htdocs\`.
2. Start Apache and MySQL in XAMPP.
3. Open phpMyAdmin: `http://localhost/phpmyadmin`
4. Import `sql/database.sql`.
5. Edit `includes/config.php` if your MySQL username/password differs.
6. Open `http://localhost/denr_xii_portal/`
7. Set or reset the administrator credentials from a terminal opened in the project folder:
   - Run `php scripts/reset_admin.php`.
   - Enter the new username and a password of at least 12 characters when prompted.
   - Sign in at `http://localhost/denr_xii_portal/auth/login.php`.

The reset command updates the first System Administrator account, reactivates it, or creates one if none exists. It does not print the password. Run it locally because the password is entered in the terminal.

## Employee self-service
- Employees can create an account from the login page. Registration creates both the employee profile and its linked portal account; the username must be unique and the employee ID is generated automatically.
- Signed-in users can change their username and password from **Account Settings**; the current password is required to save changes.
- Administrators can reset an employee's portal username and password from **Employees → Reset Login** when the employee has forgotten their password. Give the temporary password to the employee privately; they can change it after signing in.
- Certificate approval accepts a JPG/PNG/WebP image and emails it to the employee through PHPMailer. The portal confirms successful delivery to the admin and notifies the employee. Configure SMTP as described in [MAIL_SETUP.md](MAIL_SETUP.md).
- Employees can view or download their approved certificate files from **My Certificates**. File access is limited to the signed-in employee who owns the certificate.
- Position choices are read from the `positions` table. New installs seed a starter position catalog from `sql/database.sql`; on an existing database, run `php scripts/seed_positions.php` to add any missing catalog entries.
- Employees can register for published trainings from **My Training**. Staff record attendance and set the training registration to **Completed** after checking completion requirements.
- Employees can request one certificate per completed training from their dashboard. Requests appear as **PENDING** in Certifications and generate portal notifications for the employee and active system, regional, and approving administrators.
- Employees can also request a No Pending Case, Bank Endorsement, No Take Home Pay, Salary-Remuneration, or Employment certificate. The request date is recorded automatically; admins use the same image upload, email, and Sent Certificates workflow. On an existing database, apply `sql/migrate_general_certificate_requests.sql` once.

## Main workflow
Admin creates training -> employee registers -> attendance is recorded -> assessment is completed -> coordinator validates completion -> certificate becomes PENDING -> approving officer approves -> certificate becomes ACTIVE -> QR verification becomes available -> expiration monitoring/notifications.

## Security notes
This is a functional starter system, not a production government deployment. Before production:
- Enable HTTPS.
- Move database credentials outside the public web root.
- Add CSRF tokens to all state-changing forms.
- Use stronger password policies and 2FA.
- Configure secure file storage and MIME/type validation.
- Add scheduled cron/Task Scheduler jobs for notifications.
- Review DENR privacy, records-retention, accessibility, and security requirements.

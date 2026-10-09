# Portal notifications and certificate review reminders

## Training cancellation requests

Employees can request to withdraw from a registered training before it starts. The request includes a reason and stays pending until an administrator approves or declines it. Approval cancels the registration and releases the seat; either decision sends a notification to the employee. Admins review these under **Trainings → Review Cancellation Requests**.

For an existing database, run `sql/migrate_training_cancellation_requests.sql` once in phpMyAdmin before using this feature. Fresh database imports already create the required table through `sql/database.sql`.

## Available training alerts

Employees receive a portal notification when an administrator publishes an upcoming training. Employees who create an account after a training was published receive the alert the next time they sign in or open a portal page. Use the notification bell in the header to review alerts and mark them read.

## Certificate request review target

Pending certificate requests have a five working day review target. For this portal, working days are Monday through Thursday; Friday, Saturday, and Sunday are excluded. The request date counts as day one when it falls Monday through Thursday. Admins see daily remaining-day or overdue reminders in the notification bell on working days.

For reminders to be generated daily even when admins do not open the portal, create a daily Windows Task Scheduler task:

- Program: `D:\DENR_BOLENCES\Programs_2026\XAMMP\php\php.exe`
- Arguments: `D:\DENR_BOLENCES\Programs_2026\XAMMP\htdocs\denr_xii_portal\cron\certificate_request_reminders.php`
- Start in: `D:\DENR_BOLENCES\Programs_2026\XAMMP\htdocs\denr_xii_portal`
- Trigger: Daily; the script skips Friday through Sunday.

The reminder script is safe to run more than once per day; it creates at most one reminder per pending certificate and reviewer per day.

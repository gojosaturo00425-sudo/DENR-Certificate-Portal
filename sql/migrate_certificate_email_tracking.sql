ALTER TABLE certificates
    ADD COLUMN email_sent_at DATETIME NULL AFTER pdf_path;

-- Preserve successful deliveries already recorded by the portal.
UPDATE certificates c
JOIN trainings t ON t.id=c.training_id
SET c.email_sent_at = (
    SELECT MAX(n.created_at)
    FROM notifications n
    WHERE n.employee_id=c.employee_id
      AND n.type='certificate_approved'
      AND n.message LIKE CONCAT('%certificate for ', t.title, '%was approved and sent to your email.%')
)
WHERE c.email_sent_at IS NULL
  AND EXISTS (
    SELECT 1
    FROM notifications n
    WHERE n.employee_id=c.employee_id
      AND n.type='certificate_approved'
      AND n.message LIKE CONCAT('%certificate for ', t.title, '%was approved and sent to your email.%')
  );

UPDATE certificates c
JOIN (
    SELECT entity_id, MAX(created_at) sent_at
    FROM audit_logs
    WHERE action='RESEND_CERTIFICATE_EMAIL' AND entity_type='certificate'
    GROUP BY entity_id
) sent ON sent.entity_id=c.id
SET c.email_sent_at=sent.sent_at
WHERE c.email_sent_at IS NULL;

UPDATE certificates c
JOIN trainings t ON t.id=c.training_id
SET c.email_sent_at = (
    SELECT MAX(n.created_at)
    FROM notifications n
    WHERE n.employee_id=c.employee_id
      AND n.type='certificate_emailed'
      AND n.message LIKE CONCAT('%certificate image for ', t.title, '%sent successfully to your email address.%')
)
WHERE c.email_sent_at IS NULL
  AND EXISTS (
    SELECT 1
    FROM notifications n
    WHERE n.employee_id=c.employee_id
      AND n.type='certificate_emailed'
      AND n.message LIKE CONCAT('%certificate image for ', t.title, '%sent successfully to your email address.%')
  );

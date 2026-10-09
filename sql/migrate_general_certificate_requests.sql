ALTER TABLE certificates
    MODIFY COLUMN training_id INT NULL,
    ADD COLUMN certificate_type VARCHAR(120) NULL AFTER training_id;

CREATE TABLE IF NOT EXISTS training_cancellation_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    registration_id INT NOT NULL,
    employee_id INT NOT NULL,
    reason VARCHAR(500) NOT NULL,
    status ENUM('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
    requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reviewed_by INT NULL,
    reviewed_at DATETIME NULL,
    admin_note VARCHAR(500) NULL,
    INDEX idx_training_cancel_status_requested (status, requested_at),
    INDEX idx_training_cancel_registration (registration_id, id),
    FOREIGN KEY (registration_id) REFERENCES training_registrations(id) ON DELETE CASCADE,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
);

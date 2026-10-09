CREATE DATABASE IF NOT EXISTS denr_xii_portal CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE denr_xii_portal;

CREATE TABLE roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(80) NOT NULL UNIQUE
);

INSERT INTO roles (name) VALUES
('System Administrator'),
('Regional Training Administrator'),
('Office/PENRO/CENRO Administrator'),
('Training Coordinator'),
('Approving Officer'),
('Employee'),
('Public Verifier');

CREATE TABLE offices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    office_type ENUM('Regional Office','PENRO','CENRO','Division','Section','Other') DEFAULT 'Other',
    parent_id INT NULL,
    CONSTRAINT fk_office_parent FOREIGN KEY (parent_id) REFERENCES offices(id) ON DELETE SET NULL
);

INSERT INTO offices (name, office_type) VALUES ('DENR XII - Regional Office','Regional Office');
INSERT INTO offices (name, office_type) VALUES ('PENRO South Cotabato','PENRO');
INSERT INTO offices (name, office_type) VALUES ('PENRO Sultan Kudarat','PENRO');
INSERT INTO offices (name, office_type) VALUES ('PENRO Sarangani','PENRO');
INSERT INTO offices (name, office_type) VALUES ('PENRO Cotabato','PENRO');

CREATE TABLE positions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL UNIQUE
);

INSERT IGNORE INTO positions (name) VALUES
('Administrative Aide'),
('Administrative Assistant'),
('Administrative Officer'),
('Accountant'),
('Attorney'),
('Community Affairs Officer'),
('Ecosystems Management Specialist'),
('Engineer'),
('Forester'),
('Forest Ranger'),
('Geodetic Engineer'),
('Information Systems Analyst'),
('Planning Officer'),
('Science Research Specialist'),
('Other');

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role_id INT NOT NULL,
    employee_id INT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(id)
);

CREATE TABLE employees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_no VARCHAR(50) NOT NULL UNIQUE,
    first_name VARCHAR(100) NOT NULL,
    middle_name VARCHAR(100) NULL,
    last_name VARCHAR(100) NOT NULL,
    position_id INT NULL,
    office_id INT NULL,
    penro_cenro VARCHAR(150) NULL,
    division_section VARCHAR(150) NULL,
    employment_status ENUM('Permanent','Contractual','Job Order','Casual','Other') DEFAULT 'Permanent',
    email VARCHAR(190) NULL,
    phone VARCHAR(50) NULL,
    photo VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (position_id) REFERENCES positions(id) ON DELETE SET NULL,
    FOREIGN KEY (office_id) REFERENCES offices(id) ON DELETE SET NULL
);

ALTER TABLE users ADD CONSTRAINT fk_user_employee FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE SET NULL;

CREATE TABLE training_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL UNIQUE
);

INSERT INTO training_categories (name) VALUES
('Environmental Management'),('GIS'),('Forest Management'),('Occupational Safety'),('Other');

CREATE TABLE trainings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    category_id INT NULL,
    instructor VARCHAR(200),
    start_at DATETIME NOT NULL,
    end_at DATETIME NOT NULL,
    venue VARCHAR(255),
    meeting_link VARCHAR(500),
    max_participants INT DEFAULT 0,
    completion_requirements TEXT,
    certificate_template VARCHAR(255),
    status ENUM('Draft','Published','Completed','Cancelled') DEFAULT 'Draft',
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES training_categories(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE training_materials (
    id INT AUTO_INCREMENT PRIMARY KEY,
    training_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    FOREIGN KEY (training_id) REFERENCES trainings(id) ON DELETE CASCADE
);

CREATE TABLE training_registrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    training_id INT NOT NULL,
    employee_id INT NOT NULL,
    registered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('Registered','Attended','Completed','Cancelled') DEFAULT 'Registered',
    UNIQUE KEY uq_training_employee (training_id, employee_id),
    FOREIGN KEY (training_id) REFERENCES trainings(id) ON DELETE CASCADE,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
);

CREATE TABLE training_cancellation_requests (
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

CREATE TABLE attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    registration_id INT NOT NULL UNIQUE,
    attendance_status ENUM('Present','Absent','Partial') NOT NULL,
    checked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    checked_by INT NULL,
    FOREIGN KEY (registration_id) REFERENCES training_registrations(id) ON DELETE CASCADE,
    FOREIGN KEY (checked_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE assessments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    training_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    passing_score DECIMAL(5,2) DEFAULT 75,
    FOREIGN KEY (training_id) REFERENCES trainings(id) ON DELETE CASCADE
);

CREATE TABLE assessment_questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    assessment_id INT NOT NULL,
    question TEXT NOT NULL,
    option_a TEXT,
    option_b TEXT,
    option_c TEXT,
    option_d TEXT,
    correct_option CHAR(1) NOT NULL,
    points INT DEFAULT 1,
    FOREIGN KEY (assessment_id) REFERENCES assessments(id) ON DELETE CASCADE
);

CREATE TABLE assessment_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    assessment_id INT NOT NULL,
    employee_id INT NOT NULL,
    score DECIMAL(5,2) NOT NULL,
    passed TINYINT(1) NOT NULL,
    attempts_no INT DEFAULT 1,
    completed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (assessment_id) REFERENCES assessments(id) ON DELETE CASCADE,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
);

CREATE TABLE certificates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    certificate_no VARCHAR(80) NOT NULL UNIQUE,
    employee_id INT NOT NULL,
    training_id INT NULL,
    certificate_type VARCHAR(120) NULL,
    issued_date DATE NULL,
    valid_until DATE NULL,
    status ENUM('PENDING','APPROVED','ACTIVE','EXPIRING','EXPIRED','REVOKED') DEFAULT 'PENDING',
    pdf_path VARCHAR(255) NULL,
    email_sent_at DATETIME NULL,
    qr_token VARCHAR(100) NOT NULL UNIQUE,
    approved_by INT NULL,
    approved_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (training_id) REFERENCES trainings(id) ON DELETE CASCADE,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NULL,
    user_id INT NULL,
    title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    type VARCHAR(50) DEFAULT 'system',
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE audit_logs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    action VARCHAR(150) NOT NULL,
    entity_type VARCHAR(80),
    entity_id INT NULL,
    details TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Default admin: password is admin123
INSERT INTO users (username, password_hash, role_id)
SELECT 'admin', '$2y$10$I7QqwF.60aC7bNn0fY8ike4wT9t9ioHaXx335PDMYItYgMyRnUNyC', id
FROM roles WHERE name='System Administrator'
AND NOT EXISTS (SELECT 1 FROM users WHERE username='admin');

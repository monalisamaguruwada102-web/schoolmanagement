CREATE DATABASE IF NOT EXISTS student_fees_db;
USE student_fees_db;

-- Students table
CREATE TABLE IF NOT EXISTS students (
    id        INT AUTO_INCREMENT PRIMARY KEY,
    student_id VARCHAR(20) NOT NULL UNIQUE,
    full_name  VARCHAR(100) NOT NULL,
    course     VARCHAR(100) NOT NULL,
    total_fees DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    fees_paid  DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    balance    DECIMAL(10,2) GENERATED ALWAYS AS (total_fees - fees_paid) STORED,
    payment_status ENUM('paid','partial','unpaid') GENERATED ALWAYS AS (
        CASE
            WHEN fees_paid >= total_fees THEN 'paid'
            WHEN fees_paid > 0 THEN 'partial'
            ELSE 'unpaid'
        END
    ) STORED,
    enrollment_date DATE NOT NULL DEFAULT (CURDATE()),
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Payment transactions table
CREATE TABLE IF NOT EXISTS payments (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    student_id     VARCHAR(20) NOT NULL,
    amount         DECIMAL(10,2) NOT NULL,
    payment_date   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    receipt_number VARCHAR(50) UNIQUE,
    entered_by     VARCHAR(100) DEFAULT 'Accounts Dept',
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE
);

-- Audit log
CREATE TABLE IF NOT EXISTS audit_log (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    action       VARCHAR(50),
    student_id   VARCHAR(20),
    details      TEXT,
    performed_by VARCHAR(100),
    timestamp    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Sample data
INSERT INTO students (student_id, full_name, course, total_fees, fees_paid, enrollment_date) VALUES
('STU001', 'Tendai Moyo',        'Bachelor of Commerce',    2500.00, 2500.00, '2024-02-01'),
('STU002', 'Rudo Chikwanda',     'Bachelor of Science IT',  3000.00, 1500.00, '2024-02-01'),
('STU003', 'Farai Mutamba',      'Bachelor of Laws',        3500.00, 3500.00, '2024-02-01'),
('STU004', 'Ngonidzashe Dube',   'Bachelor of Engineering',  4000.00,    0.00, '2024-02-01'),
('STU005', 'Shamiso Ncube',      'Bachelor of Medicine',    5000.00, 5000.00, '2024-02-01'),
('STU006', 'Tatenda Zimba',      'Bachelor of Education',   2000.00,  800.00, '2024-02-01');

-- View: only fully paid students (used by Registry)
CREATE OR REPLACE VIEW registry_approved_students AS
SELECT
    student_id, full_name, course,
    total_fees, fees_paid, enrollment_date, payment_status
FROM students
WHERE payment_status = 'paid';
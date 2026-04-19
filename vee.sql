-- =====================================================
-- VeeCare Medical Centre - Database Schema
-- Database: veecare_medical
-- =====================================================

-- Create database (if not exists)
CREATE DATABASE IF NOT EXISTS `veecare_medical` 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

USE `veecare_medical`;

-- =====================================================
-- 1. USERS TABLE (Staff accounts)
-- =====================================================
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `full_name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(100) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `role` ENUM('admin', 'doctor', 'staff', 'receptionist') NOT NULL DEFAULT 'staff',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `remember_token` VARCHAR(255) DEFAULT NULL,
    `token_expiry` DATETIME DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_email` (`email`),
    INDEX `idx_role` (`role`),
    INDEX `idx_remember_token` (`remember_token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 2. PATIENTS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS `patients` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `patient_id` VARCHAR(20) NOT NULL UNIQUE,
    `full_name` VARCHAR(100) NOT NULL,
    `date_of_birth` DATE NOT NULL,
    `gender` ENUM('Male', 'Female', 'Other') NOT NULL,
    `email` VARCHAR(100) DEFAULT NULL,
    `phone` VARCHAR(20) NOT NULL,
    `address` TEXT,
    `emergency_contact_name` VARCHAR(100) DEFAULT NULL,
    `emergency_contact_phone` VARCHAR(20) DEFAULT NULL,
    `blood_group` ENUM('A+', 'A-', 'B+', 'B-', 'O+', 'O-', 'AB+', 'AB-') DEFAULT NULL,
    `allergies` TEXT,
    `medical_history` TEXT,
    `created_by` INT(11) DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_patient_id` (`patient_id`),
    INDEX `idx_full_name` (`full_name`),
    INDEX `idx_phone` (`phone`),
    INDEX `idx_created_by` (`created_by`),
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 3. APPOINTMENTS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS `appointments` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `patient_id` INT(11) NOT NULL,
    `doctor_id` INT(11) DEFAULT NULL,
    `appointment_date` DATE NOT NULL,
    `appointment_time` TIME NOT NULL,
    `duration` INT(11) DEFAULT 30,
    `purpose` TEXT,
    `status` ENUM('scheduled', 'completed', 'cancelled', 'no_show') NOT NULL DEFAULT 'scheduled',
    `notes` TEXT,
    `created_by` INT(11) DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_patient_id` (`patient_id`),
    INDEX `idx_doctor_id` (`doctor_id`),
    INDEX `idx_appointment_date` (`appointment_date`),
    INDEX `idx_status` (`status`),
    FOREIGN KEY (`patient_id`) REFERENCES `patients`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`doctor_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 4. PRESCRIPTIONS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS `prescriptions` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `prescription_number` VARCHAR(20) NOT NULL UNIQUE,
    `patient_id` INT(11) NOT NULL,
    `doctor_id` INT(11) NOT NULL,
    `prescription_date` DATE NOT NULL,
    `diagnosis` TEXT,
    `notes` TEXT,
    `follow_up_date` DATE DEFAULT NULL,
    `status` ENUM('active', 'dispensed', 'expired', 'cancelled') NOT NULL DEFAULT 'active',
    `created_by` INT(11) DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_prescription_number` (`prescription_number`),
    INDEX `idx_patient_id` (`patient_id`),
    INDEX `idx_doctor_id` (`doctor_id`),
    INDEX `idx_prescription_date` (`prescription_date`),
    FOREIGN KEY (`patient_id`) REFERENCES `patients`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`doctor_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 5. PRESCRIPTION ITEMS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS `prescription_items` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `prescription_id` INT(11) NOT NULL,
    `medication_name` VARCHAR(100) NOT NULL,
    `dosage` VARCHAR(50) NOT NULL,
    `frequency` VARCHAR(100) NOT NULL,
    `duration` VARCHAR(50) NOT NULL,
    `quantity` INT(11) NOT NULL,
    `instructions` TEXT,
    `refills` INT(11) DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_prescription_id` (`prescription_id`),
    FOREIGN KEY (`prescription_id`) REFERENCES `prescriptions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 6. TREATMENTS / DIAGNOSIS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS `treatments` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `patient_id` INT(11) NOT NULL,
    `doctor_id` INT(11) NOT NULL,
    `appointment_id` INT(11) DEFAULT NULL,
    `diagnosis` TEXT NOT NULL,
    `symptoms` TEXT,
    `vitals` JSON,
    `treatment_plan` TEXT,
    `medications_prescribed` TEXT,
    `lab_tests` TEXT,
    `follow_up_required` TINYINT(1) DEFAULT 0,
    `follow_up_date` DATE DEFAULT NULL,
    `clinical_notes` TEXT,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_patient_id` (`patient_id`),
    INDEX `idx_doctor_id` (`doctor_id`),
    INDEX `idx_appointment_id` (`appointment_id`),
    FOREIGN KEY (`patient_id`) REFERENCES `patients`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`doctor_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`appointment_id`) REFERENCES `appointments`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 7. BILLING / INVOICES TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS `invoices` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `invoice_number` VARCHAR(20) NOT NULL UNIQUE,
    `patient_id` INT(11) NOT NULL,
    `appointment_id` INT(11) DEFAULT NULL,
    `invoice_date` DATE NOT NULL,
    `due_date` DATE NOT NULL,
    `subtotal` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `tax` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `discount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `total_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `amount_paid` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `balance_due` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `status` ENUM('pending', 'paid', 'overdue', 'cancelled') NOT NULL DEFAULT 'pending',
    `notes` TEXT,
    `created_by` INT(11) DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_invoice_number` (`invoice_number`),
    INDEX `idx_patient_id` (`patient_id`),
    INDEX `idx_status` (`status`),
    INDEX `idx_invoice_date` (`invoice_date`),
    FOREIGN KEY (`patient_id`) REFERENCES `patients`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`appointment_id`) REFERENCES `appointments`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 8. PAYMENTS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS `payments` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `payment_number` VARCHAR(20) NOT NULL UNIQUE,
    `invoice_id` INT(11) NOT NULL,
    `patient_id` INT(11) NOT NULL,
    `amount` DECIMAL(10,2) NOT NULL,
    `payment_method` ENUM('cash', 'credit_card', 'debit_card', 'bank_transfer', 'insurance', 'online') NOT NULL,
    `payment_date` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `transaction_id` VARCHAR(100) DEFAULT NULL,
    `status` ENUM('completed', 'pending', 'failed', 'refunded') NOT NULL DEFAULT 'completed',
    `receipt_number` VARCHAR(20) DEFAULT NULL,
    `notes` TEXT,
    `received_by` INT(11) DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_payment_number` (`payment_number`),
    INDEX `idx_invoice_id` (`invoice_id`),
    INDEX `idx_patient_id` (`patient_id`),
    INDEX `idx_payment_date` (`payment_date`),
    FOREIGN KEY (`invoice_id`) REFERENCES `invoices`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`patient_id`) REFERENCES `patients`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`received_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 9. INVOICE ITEMS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS `invoice_items` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `invoice_id` INT(11) NOT NULL,
    `description` VARCHAR(255) NOT NULL,
    `quantity` INT(11) NOT NULL DEFAULT 1,
    `unit_price` DECIMAL(10,2) NOT NULL,
    `total_price` DECIMAL(10,2) NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_invoice_id` (`invoice_id`),
    FOREIGN KEY (`invoice_id`) REFERENCES `invoices`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 10. CLINIC SETTINGS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS `clinic_settings` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `setting_key` VARCHAR(100) NOT NULL UNIQUE,
    `setting_value` TEXT,
    `setting_type` ENUM('text', 'number', 'boolean', 'json') NOT NULL DEFAULT 'text',
    `description` TEXT,
    `updated_by` INT(11) DEFAULT NULL,
    `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_setting_key` (`setting_key`),
    FOREIGN KEY (`updated_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 11. ACTIVITY LOGS TABLE (Audit Trail)
-- =====================================================
CREATE TABLE IF NOT EXISTS `activity_logs` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) DEFAULT NULL,
    `action` VARCHAR(100) NOT NULL,
    `table_name` VARCHAR(50) DEFAULT NULL,
    `record_id` INT(11) DEFAULT NULL,
    `old_data` JSON,
    `new_data` JSON,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `user_agent` TEXT,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_action` (`action`),
    INDEX `idx_created_at` (`created_at`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- INSERT DEFAULT DATA
-- =====================================================

-- Insert default admin user (password: Admin@123)
INSERT INTO `users` (`full_name`, `email`, `password_hash`, `role`, `is_active`) VALUES
('System Administrator', 'admin@veecare.com', '$2y$10$YourHashHere', 'admin', 1);

-- Insert sample clinic settings
INSERT INTO `clinic_settings` (`setting_key`, `setting_value`, `setting_type`, `description`) VALUES
('clinic_name', 'VeeCare Medical Centre', 'text', 'Name of the clinic'),
('clinic_address', '123 Healthcare Avenue, Medical District', 'text', 'Clinic physical address'),
('clinic_phone', '(555) 123-4567', 'text', 'Clinic contact number'),
('clinic_email', 'info@veecare.com', 'text', 'Clinic email address'),
('tax_rate', '0.00', 'number', 'Tax rate applied to invoices'),
('currency_symbol', '$', 'text', 'Currency symbol for billing'),
('timezone', 'America/New_York', 'text', 'System timezone'),
('date_format', 'Y-m-d', 'text', 'Date format for display');

-- =====================================================
-- CREATE TRIGGERS FOR AUTO-UPDATING BALANCE
-- =====================================================

-- Trigger to update invoice balance when payment is added
DELIMITER $$
CREATE TRIGGER `update_invoice_balance_on_payment`
AFTER INSERT ON `payments`
FOR EACH ROW
BEGIN
    UPDATE `invoices` 
    SET 
        `amount_paid` = (
            SELECT COALESCE(SUM(amount), 0) 
            FROM `payments` 
            WHERE `invoice_id` = NEW.invoice_id AND `status` = 'completed'
        ),
        `balance_due` = `total_amount` - (
            SELECT COALESCE(SUM(amount), 0) 
            FROM `payments` 
            WHERE `invoice_id` = NEW.invoice_id AND `status` = 'completed'
        ),
        `status` = CASE
            WHEN `total_amount` <= (
                SELECT COALESCE(SUM(amount), 0) 
                FROM `payments` 
                WHERE `invoice_id` = NEW.invoice_id AND `status` = 'completed'
            ) THEN 'paid'
            WHEN `due_date` < CURDATE() THEN 'overdue'
            ELSE 'pending'
        END
    WHERE `id` = NEW.invoice_id;
END$$

-- Trigger to generate patient_id automatically
CREATE TRIGGER `generate_patient_id`
BEFORE INSERT ON `patients`
FOR EACH ROW
BEGIN
    DECLARE next_id INT;
    SET next_id = COALESCE((SELECT MAX(CAST(SUBSTRING(patient_id, 4) AS UNSIGNED)) FROM patients), 0) + 1;
    SET NEW.patient_id = CONCAT('PAT', LPAD(next_id, 6, '0'));
END$$

-- Trigger to generate prescription number automatically
CREATE TRIGGER `generate_prescription_number`
BEFORE INSERT ON `prescriptions`
FOR EACH ROW
BEGIN
    DECLARE next_id INT;
    SET next_id = COALESCE((SELECT MAX(CAST(SUBSTRING(prescription_number, 4) AS UNSIGNED)) FROM prescriptions), 0) + 1;
    SET NEW.prescription_number = CONCAT('RX', LPAD(next_id, 6, '0'));
END$$

-- Trigger to generate invoice number automatically
CREATE TRIGGER `generate_invoice_number`
BEFORE INSERT ON `invoices`
FOR EACH ROW
BEGIN
    DECLARE next_id INT;
    SET next_id = COALESCE((SELECT MAX(CAST(SUBSTRING(invoice_number, 4) AS UNSIGNED)) FROM invoices), 0) + 1;
    SET NEW.invoice_number = CONCAT('INV', LPAD(next_id, 6, '0'));
END$$

-- Trigger to generate payment number automatically
CREATE TRIGGER `generate_payment_number`
BEFORE INSERT ON `payments`
FOR EACH ROW
BEGIN
    DECLARE next_id INT;
    SET next_id = COALESCE((SELECT MAX(CAST(SUBSTRING(payment_number, 4) AS UNSIGNED)) FROM payments), 0) + 1;
    SET NEW.payment_number = CONCAT('PAY', LPAD(next_id, 6, '0'));
END$$

DELIMITER ;

-- =====================================================
-- CREATE VIEWS FOR REPORTS
-- =====================================================

-- View for daily appointment summary
CREATE OR REPLACE VIEW `view_daily_appointments` AS
SELECT 
    DATE(appointment_date) AS date,
    COUNT(*) AS total_appointments,
    SUM(CASE WHEN status = 'scheduled' THEN 1 ELSE 0 END) AS scheduled,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed,
    SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled,
    SUM(CASE WHEN status = 'no_show' THEN 1 ELSE 0 END) AS no_show
FROM `appointments`
GROUP BY DATE(appointment_date);

-- View for revenue summary
CREATE OR REPLACE VIEW `view_revenue_summary` AS
SELECT 
    DATE_FORMAT(payment_date, '%Y-%m') AS month,
    COUNT(*) AS payment_count,
    SUM(amount) AS total_amount,
    AVG(amount) AS average_amount,
    payment_method
FROM `payments`
WHERE `status` = 'completed'
GROUP BY DATE_FORMAT(payment_date, '%Y-%m'), payment_method;

-- View for patient statistics
CREATE OR REPLACE VIEW `view_patient_statistics` AS
SELECT 
    DATE_FORMAT(created_at, '%Y-%m') AS month,
    COUNT(*) AS new_patients,
    gender,
    blood_group
FROM `patients`
GROUP BY DATE_FORMAT(created_at, '%Y-%m'), gender, blood_group;

-- =====================================================
-- INDEX OPTIMIZATION
-- =====================================================

-- Additional indexes for better query performance
CREATE INDEX `idx_patients_name_phone` ON `patients` (`full_name`, `phone`);
CREATE INDEX `idx_appointments_date_status` ON `appointments` (`appointment_date`, `status`);
CREATE INDEX `idx_prescriptions_date_doctor` ON `prescriptions` (`prescription_date`, `doctor_id`);
CREATE INDEX `idx_invoices_status_date` ON `invoices` (`status`, `invoice_date`);
CREATE INDEX `idx_payments_date_method` ON `payments` (`payment_date`, `payment_method`);

-- =====================================================
-- END OF SCHEMA
-- =====================================================
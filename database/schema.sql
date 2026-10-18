-- ============================================================================
-- LabMaster - Pathology Laboratory Management System & Website
-- Complete Database Schema (MariaDB/MySQL 10.4+ / MySQL 8.0+)
-- ============================================================================

CREATE DATABASE IF NOT EXISTS `labmaster_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `labmaster_db`;

SET FOREIGN_KEY_CHECKS = 0;

-- 1. System Settings Table
DROP TABLE IF EXISTS `system_settings`;
CREATE TABLE `system_settings` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `setting_key` VARCHAR(100) NOT NULL UNIQUE,
  `setting_value` LONGTEXT NULL,
  `category` VARCHAR(50) NOT NULL DEFAULT 'general',
  `display_name` VARCHAR(150) NULL,
  `field_type` VARCHAR(30) DEFAULT 'text',
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX (`setting_key`),
  INDEX (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Roles Table
DROP TABLE IF EXISTS `roles`;
CREATE TABLE `roles` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(50) NOT NULL UNIQUE,
  `slug` VARCHAR(50) NOT NULL UNIQUE,
  `description` VARCHAR(255) NULL,
  `is_system` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Permissions Table
DROP TABLE IF EXISTS `permissions`;
CREATE TABLE `permissions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `slug` VARCHAR(80) NOT NULL UNIQUE,
  `name` VARCHAR(100) NOT NULL,
  `module` VARCHAR(50) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (`module`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Role Permissions Mapping
DROP TABLE IF EXISTS `role_permissions`;
CREATE TABLE `role_permissions` (
  `role_id` INT NOT NULL,
  `permission_id` INT NOT NULL,
  PRIMARY KEY (`role_id`, `permission_id`),
  FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`permission_id`) REFERENCES `permissions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Users / Staff Table
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `role_id` INT NOT NULL,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `full_name` VARCHAR(100) NOT NULL,
  `phone` VARCHAR(20) NULL,
  `designation` VARCHAR(100) NULL,
  `qualification` VARCHAR(150) NULL,
  `registration_number` VARCHAR(50) NULL,
  `signature_image` VARCHAR(255) NULL,
  `is_active` TINYINT(1) DEFAULT 1,
  `failed_logins` INT DEFAULT 0,
  `lockout_until` DATETIME NULL,
  `last_login` DATETIME NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`),
  INDEX (`username`),
  INDEX (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. Referring Doctors Table
DROP TABLE IF EXISTS `doctors`;
CREATE TABLE `doctors` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `doctor_code` VARCHAR(30) NOT NULL UNIQUE,
  `name` VARCHAR(100) NOT NULL,
  `degree` VARCHAR(100) NULL,
  `specialization` VARCHAR(100) NULL,
  `clinic_hospital` VARCHAR(150) NULL,
  `phone` VARCHAR(20) NULL,
  `email` VARCHAR(100) NULL,
  `commission_rate` DECIMAL(5,2) DEFAULT 0.00,
  `notes` TEXT NULL,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (`name`),
  INDEX (`phone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. Patients Master Table
DROP TABLE IF EXISTS `patients`;
CREATE TABLE `patients` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `patient_id` VARCHAR(50) NOT NULL UNIQUE,
  `full_name` VARCHAR(100) NOT NULL,
  `gender` ENUM('Male', 'Female', 'Other') NOT NULL,
  `dob` DATE NULL,
  `age` INT NOT NULL,
  `age_unit` ENUM('Years', 'Months', 'Days') NOT NULL DEFAULT 'Years',
  `phone` VARCHAR(20) NOT NULL,
  `email` VARCHAR(100) NULL,
  `address` TEXT NULL,
  `blood_group` VARCHAR(10) NULL,
  `referring_doctor_id` INT NULL,
  `medical_history` TEXT NULL,
  `password_hash` VARCHAR(255) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`referring_doctor_id`) REFERENCES `doctors`(`id`) ON DELETE SET NULL,
  INDEX (`patient_id`),
  INDEX (`phone`),
  INDEX (`full_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 8. Test Categories Table
DROP TABLE IF EXISTS `test_categories`;
CREATE TABLE `test_categories` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `code` VARCHAR(50) NOT NULL UNIQUE,
  `description` TEXT NULL,
  `icon` VARCHAR(50) DEFAULT 'bi-droplet',
  `sort_order` INT DEFAULT 0,
  `is_active` TINYINT(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 9. Tests Master Table
DROP TABLE IF EXISTS `tests`;
CREATE TABLE `tests` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `category_id` INT NOT NULL,
  `test_code` VARCHAR(50) NOT NULL UNIQUE,
  `test_name` VARCHAR(150) NOT NULL,
  `short_name` VARCHAR(50) NULL,
  `specimen_type` VARCHAR(100) NOT NULL DEFAULT 'Serum',
  `department` VARCHAR(100) NULL,
  `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `cost_price` DECIMAL(10,2) DEFAULT 0.00,
  `turnaround_time` VARCHAR(100) DEFAULT 'Same Day (4-6 Hours)',
  `method` VARCHAR(100) NULL,
  `instructions` TEXT NULL,
  `clinical_significance` TEXT NULL,
  `is_package` TINYINT(1) DEFAULT 0,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`category_id`) REFERENCES `test_categories`(`id`),
  INDEX (`test_code`),
  INDEX (`test_name`),
  INDEX (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 10. Test Parameters Table
DROP TABLE IF EXISTS `test_parameters`;
CREATE TABLE `test_parameters` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `test_id` INT NOT NULL,
  `parameter_name` VARCHAR(150) NOT NULL,
  `parameter_code` VARCHAR(50) NOT NULL,
  `unit` VARCHAR(50) NULL,
  `result_type` ENUM('numeric', 'text', 'dropdown', 'formula') NOT NULL DEFAULT 'numeric',
  `dropdown_options` TEXT NULL,
  `formula` VARCHAR(255) NULL,
  `decimal_places` INT DEFAULT 2,
  `sort_order` INT DEFAULT 0,
  `critical_low` DECIMAL(12,4) NULL,
  `critical_high` DECIMAL(12,4) NULL,
  FOREIGN KEY (`test_id`) REFERENCES `tests`(`id`) ON DELETE CASCADE,
  INDEX (`test_id`),
  INDEX (`parameter_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 11. Reference Intervals Table (Age & Gender Specific)
DROP TABLE IF EXISTS `reference_intervals`;
CREATE TABLE `reference_intervals` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `parameter_id` INT NOT NULL,
  `gender` ENUM('All', 'Male', 'Female') NOT NULL DEFAULT 'All',
  `age_min` INT NOT NULL DEFAULT 0,
  `age_max` INT NOT NULL DEFAULT 150,
  `age_unit` ENUM('Years', 'Months', 'Days') NOT NULL DEFAULT 'Years',
  `min_val` DECIMAL(12,4) NULL,
  `max_val` DECIMAL(12,4) NULL,
  `normal_text` VARCHAR(255) NULL,
  `display_interval` VARCHAR(150) NOT NULL,
  `interpretation` TEXT NULL,
  FOREIGN KEY (`parameter_id`) REFERENCES `test_parameters`(`id`) ON DELETE CASCADE,
  INDEX (`parameter_id`),
  INDEX (`gender`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 12. Public Quick Test Bookings Table
DROP TABLE IF EXISTS `bookings`;
CREATE TABLE `bookings` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `booking_reference` VARCHAR(50) NOT NULL UNIQUE,
  `patient_id` INT NULL,
  `patient_name` VARCHAR(100) NOT NULL,
  `phone` VARCHAR(20) NOT NULL,
  `email` VARCHAR(100) NULL,
  `age` INT NOT NULL,
  `gender` ENUM('Male', 'Female', 'Other') NOT NULL,
  `test_ids` TEXT NOT NULL,
  `preferred_date` DATE NOT NULL,
  `preferred_time_slot` VARCHAR(50) NOT NULL,
  `is_home_collection` TINYINT(1) DEFAULT 0,
  `collection_address` TEXT NULL,
  `referring_doctor` VARCHAR(100) NULL,
  `notes` TEXT NULL,
  `status` ENUM('New', 'Pending Confirmation', 'Confirmed', 'Sample Collection Scheduled', 'Sample Collected', 'Completed', 'Cancelled') NOT NULL DEFAULT 'New',
  `triage_notes` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`patient_id`) REFERENCES `patients`(`id`) ON DELETE SET NULL,
  INDEX (`booking_reference`),
  INDEX (`phone`),
  INDEX (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 13. Test Orders / Accessioning Table
DROP TABLE IF EXISTS `test_orders`;
CREATE TABLE `test_orders` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `accession_number` VARCHAR(50) NOT NULL UNIQUE,
  `patient_id` INT NOT NULL,
  `referring_doctor_id` INT NULL,
  `booking_id` INT NULL,
  `order_date` DATE NOT NULL,
  `priority` ENUM('Routine', 'Urgent', 'Stat') NOT NULL DEFAULT 'Routine',
  `clinical_notes` TEXT NULL,
  `total_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `discount_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `net_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `status` ENUM('Order Created', 'Sample Collected', 'Processing', 'Results Entered', 'Under Review', 'Approved', 'Partially Approved', 'Amended', 'Cancelled') NOT NULL DEFAULT 'Order Created',
  `payment_status` ENUM('Unpaid', 'Partially Paid', 'Paid') NOT NULL DEFAULT 'Unpaid',
  `created_by` INT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`patient_id`) REFERENCES `patients`(`id`),
  FOREIGN KEY (`referring_doctor_id`) REFERENCES `doctors`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`booking_id`) REFERENCES `bookings`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  INDEX (`accession_number`),
  INDEX (`patient_id`),
  INDEX (`status`),
  INDEX (`order_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 14. Test Order Items Table
DROP TABLE IF EXISTS `test_order_items`;
CREATE TABLE `test_order_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `order_id` INT NOT NULL,
  `test_id` INT NOT NULL,
  `price` DECIMAL(10,2) NOT NULL,
  `sample_barcode` VARCHAR(50) NULL,
  `sample_status` ENUM('Pending', 'Collected', 'Received', 'Rejected') NOT NULL DEFAULT 'Pending',
  `sample_type` VARCHAR(100) NULL,
  `collected_at` DATETIME NULL,
  `collected_by` INT NULL,
  `received_at` DATETIME NULL,
  `rejection_reason` VARCHAR(255) NULL,
  FOREIGN KEY (`order_id`) REFERENCES `test_orders`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`test_id`) REFERENCES `tests`(`id`),
  FOREIGN KEY (`collected_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  INDEX (`order_id`),
  INDEX (`test_id`),
  INDEX (`sample_barcode`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 15. Test Results Table
DROP TABLE IF EXISTS `test_results`;
CREATE TABLE `test_results` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `order_id` INT NOT NULL,
  `order_item_id` INT NOT NULL,
  `parameter_id` INT NOT NULL,
  `result_value` VARCHAR(255) NULL,
  `flag` ENUM('NORMAL', 'LOW', 'HIGH', 'CRITICAL_LOW', 'CRITICAL_HIGH', 'ABNORMAL') NOT NULL DEFAULT 'NORMAL',
  `remarks` VARCHAR(255) NULL,
  `entered_by` INT NULL,
  `entered_at` DATETIME NULL,
  UNIQUE KEY `uq_order_param` (`order_id`, `parameter_id`),
  FOREIGN KEY (`order_id`) REFERENCES `test_orders`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`order_item_id`) REFERENCES `test_order_items`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`parameter_id`) REFERENCES `test_parameters`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`entered_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  INDEX (`order_id`),
  INDEX (`parameter_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 16. Pathology Reports Master Table
DROP TABLE IF EXISTS `reports`;
CREATE TABLE `reports` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `report_number` VARCHAR(50) NOT NULL UNIQUE,
  `order_id` INT NOT NULL,
  `patient_id` INT NOT NULL,
  `version_number` INT NOT NULL DEFAULT 1,
  `report_status` ENUM('Draft', 'Pending Approval', 'Approved', 'Amended') NOT NULL DEFAULT 'Draft',
  `clinical_remarks` TEXT NULL,
  `interpretation` TEXT NULL,
  `approved_by` INT NULL,
  `approved_at` DATETIME NULL,
  `digital_signature_used` VARCHAR(255) NULL,
  `is_released_for_patient` TINYINT(1) DEFAULT 0,
  `pdf_filename` VARCHAR(255) NULL,
  `verification_token` VARCHAR(64) NOT NULL UNIQUE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`order_id`) REFERENCES `test_orders`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`patient_id`) REFERENCES `patients`(`id`),
  FOREIGN KEY (`approved_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  INDEX (`report_number`),
  INDEX (`order_id`),
  INDEX (`patient_id`),
  INDEX (`report_status`),
  INDEX (`verification_token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 17. Report Approvals & Review History
DROP TABLE IF EXISTS `report_approvals`;
CREATE TABLE `report_approvals` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `report_id` INT NOT NULL,
  `reviewer_id` INT NOT NULL,
  `action` ENUM('Submitted', 'Approved', 'Rejected', 'Amended') NOT NULL,
  `remarks` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`report_id`) REFERENCES `reports`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`reviewer_id`) REFERENCES `users`(`id`),
  INDEX (`report_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 18. Report Access & Audit Logs
DROP TABLE IF EXISTS `report_access_logs`;
CREATE TABLE `report_access_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `report_id` INT NOT NULL,
  `access_method` VARCHAR(50) NOT NULL DEFAULT 'Public Verification',
  `ip_address` VARCHAR(50) NULL,
  `user_agent` TEXT NULL,
  `mobile_verified` VARCHAR(20) NULL,
  `accessed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`report_id`) REFERENCES `reports`(`id`) ON DELETE CASCADE,
  INDEX (`report_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 19. Invoices Table
DROP TABLE IF EXISTS `invoices`;
CREATE TABLE `invoices` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `invoice_number` VARCHAR(50) NOT NULL UNIQUE,
  `order_id` INT NOT NULL,
  `patient_id` INT NOT NULL,
  `subtotal` DECIMAL(10,2) NOT NULL,
  `discount_type` ENUM('percentage', 'fixed') NOT NULL DEFAULT 'fixed',
  `discount_rate` DECIMAL(5,2) DEFAULT 0.00,
  `discount_amount` DECIMAL(10,2) DEFAULT 0.00,
  `tax_rate` DECIMAL(5,2) DEFAULT 0.00,
  `tax_amount` DECIMAL(10,2) DEFAULT 0.00,
  `grand_total` DECIMAL(10,2) NOT NULL,
  `paid_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `balance_amount` DECIMAL(10,2) NOT NULL,
  `payment_status` ENUM('Unpaid', 'Partially Paid', 'Paid', 'Cancelled', 'Refunded') NOT NULL DEFAULT 'Unpaid',
  `invoice_date` DATE NOT NULL,
  `due_date` DATE NULL,
  `notes` TEXT NULL,
  `created_by` INT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`order_id`) REFERENCES `test_orders`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`patient_id`) REFERENCES `patients`(`id`),
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  INDEX (`invoice_number`),
  INDEX (`order_id`),
  INDEX (`patient_id`),
  INDEX (`payment_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 20. Invoice Items Table
DROP TABLE IF EXISTS `invoice_items`;
CREATE TABLE `invoice_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `invoice_id` INT NOT NULL,
  `item_description` VARCHAR(200) NOT NULL,
  `unit_price` DECIMAL(10,2) NOT NULL,
  `quantity` INT NOT NULL DEFAULT 1,
  `total_amount` DECIMAL(10,2) NOT NULL,
  FOREIGN KEY (`invoice_id`) REFERENCES `invoices`(`id`) ON DELETE CASCADE,
  INDEX (`invoice_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 21. Payments Receipts Table
DROP TABLE IF EXISTS `payments`;
CREATE TABLE `payments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `receipt_number` VARCHAR(50) NOT NULL UNIQUE,
  `invoice_id` INT NOT NULL,
  `amount` DECIMAL(10,2) NOT NULL,
  `payment_method` ENUM('Cash', 'UPI', 'Bank Transfer', 'Card', 'Cheque') NOT NULL DEFAULT 'Cash',
  `transaction_reference` VARCHAR(100) NULL,
  `payment_date` DATE NOT NULL,
  `notes` VARCHAR(255) NULL,
  `received_by` INT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`invoice_id`) REFERENCES `invoices`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`received_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  INDEX (`receipt_number`),
  INDEX (`invoice_id`),
  INDEX (`payment_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 22. Operational Expenses Table
DROP TABLE IF EXISTS `expenses`;
CREATE TABLE `expenses` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `expense_number` VARCHAR(50) NOT NULL UNIQUE,
  `category` VARCHAR(100) NOT NULL,
  `amount` DECIMAL(10,2) NOT NULL,
  `payment_method` ENUM('Cash', 'UPI', 'Bank Transfer', 'Card', 'Cheque') NOT NULL DEFAULT 'Cash',
  `paid_to` VARCHAR(150) NOT NULL,
  `expense_date` DATE NOT NULL,
  `reference_no` VARCHAR(100) NULL,
  `notes` TEXT NULL,
  `recorded_by` INT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`recorded_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  INDEX (`expense_number`),
  INDEX (`expense_date`),
  INDEX (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 23. Suppliers Table
DROP TABLE IF EXISTS `suppliers`;
CREATE TABLE `suppliers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `supplier_code` VARCHAR(50) NOT NULL UNIQUE,
  `company_name` VARCHAR(150) NOT NULL,
  `contact_person` VARCHAR(100) NULL,
  `phone` VARCHAR(20) NOT NULL,
  `email` VARCHAR(100) NULL,
  `address` TEXT NULL,
  `gstin` VARCHAR(30) NULL,
  `current_balance` DECIMAL(10,2) DEFAULT 0.00,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (`company_name`),
  INDEX (`phone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 24. Reagent & Inventory Items Table
DROP TABLE IF EXISTS `inventory_items`;
CREATE TABLE `inventory_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `item_code` VARCHAR(50) NOT NULL UNIQUE,
  `name` VARCHAR(150) NOT NULL,
  `category` ENUM('Reagent', 'Test Kit', 'Calibrator', 'Control', 'Collection Tube', 'Needle/Consumable', 'Chemical', 'Other') NOT NULL DEFAULT 'Reagent',
  `manufacturer` VARCHAR(100) NULL,
  `unit` VARCHAR(50) NOT NULL DEFAULT 'Bottles',
  `minimum_stock` INT NOT NULL DEFAULT 5,
  `current_stock` INT NOT NULL DEFAULT 0,
  `storage_temperature` VARCHAR(50) DEFAULT '2°C - 8°C (Refrigerated)',
  `storage_location` VARCHAR(100) DEFAULT 'Main Lab Refrigerator',
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (`item_code`),
  INDEX (`name`),
  INDEX (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 25. Inventory Batches Table (Lot & Expiry Tracking)
DROP TABLE IF EXISTS `inventory_batches`;
CREATE TABLE `inventory_batches` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `item_id` INT NOT NULL,
  `supplier_id` INT NULL,
  `batch_number` VARCHAR(100) NOT NULL,
  `initial_quantity` INT NOT NULL,
  `current_quantity` INT NOT NULL,
  `unit_cost` DECIMAL(10,2) DEFAULT 0.00,
  `mfg_date` DATE NULL,
  `expiry_date` DATE NOT NULL,
  `status` ENUM('Active', 'Near Expiry', 'Expired', 'Exhausted') NOT NULL DEFAULT 'Active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`item_id`) REFERENCES `inventory_items`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`supplier_id`) REFERENCES `suppliers`(`id`) ON DELETE SET NULL,
  INDEX (`item_id`),
  INDEX (`batch_number`),
  INDEX (`expiry_date`),
  INDEX (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 26. Stock Movements Ledger Table
DROP TABLE IF EXISTS `stock_movements`;
CREATE TABLE `stock_movements` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `item_id` INT NOT NULL,
  `batch_id` INT NULL,
  `movement_type` ENUM('Purchase', 'Test_Consumption', 'Manual_Issue', 'Adjustment_Add', 'Adjustment_Deduct', 'Return', 'Expired_Discard') NOT NULL,
  `quantity` INT NOT NULL,
  `quantity_before` INT NOT NULL,
  `quantity_after` INT NOT NULL,
  `reference_id` VARCHAR(50) NULL,
  `reason` VARCHAR(255) NULL,
  `performed_by` INT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`item_id`) REFERENCES `inventory_items`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`batch_id`) REFERENCES `inventory_batches`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`performed_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  INDEX (`item_id`),
  INDEX (`batch_id`),
  INDEX (`movement_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 27. Purchases from Suppliers Table
DROP TABLE IF EXISTS `purchases`;
CREATE TABLE `purchases` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `purchase_number` VARCHAR(50) NOT NULL UNIQUE,
  `supplier_id` INT NOT NULL,
  `invoice_number` VARCHAR(100) NULL,
  `purchase_date` DATE NOT NULL,
  `total_amount` DECIMAL(10,2) NOT NULL,
  `paid_amount` DECIMAL(10,2) DEFAULT 0.00,
  `balance_amount` DECIMAL(10,2) NOT NULL,
  `payment_status` ENUM('Unpaid', 'Partially Paid', 'Paid') NOT NULL DEFAULT 'Unpaid',
  `notes` TEXT NULL,
  `created_by` INT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`supplier_id`) REFERENCES `suppliers`(`id`),
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  INDEX (`purchase_number`),
  INDEX (`supplier_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 28. Purchase Line Items Table
DROP TABLE IF EXISTS `purchase_items`;
CREATE TABLE `purchase_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `purchase_id` INT NOT NULL,
  `item_id` INT NOT NULL,
  `batch_number` VARCHAR(100) NOT NULL,
  `expiry_date` DATE NOT NULL,
  `quantity` INT NOT NULL,
  `unit_price` DECIMAL(10,2) NOT NULL,
  `total_price` DECIMAL(10,2) NOT NULL,
  FOREIGN KEY (`purchase_id`) REFERENCES `purchases`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`item_id`) REFERENCES `inventory_items`(`id`),
  INDEX (`purchase_id`),
  INDEX (`item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 29. WhatsApp Sharing & Communication Logs
DROP TABLE IF EXISTS `whatsapp_logs`;
CREATE TABLE `whatsapp_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `recipient_phone` VARCHAR(20) NOT NULL,
  `patient_id` INT NULL,
  `report_id` INT NULL,
  `message_type` VARCHAR(50) NOT NULL DEFAULT 'Report Link',
  `message_body` TEXT NOT NULL,
  `status` ENUM('Not Sent', 'Queued', 'Sent', 'Delivered', 'Failed') NOT NULL DEFAULT 'Not Sent',
  `api_response` TEXT NULL,
  `sent_by` INT NULL,
  `sent_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`patient_id`) REFERENCES `patients`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`report_id`) REFERENCES `reports`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`sent_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  INDEX (`recipient_phone`),
  INDEX (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 30. In-App Notifications Table
DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(150) NOT NULL,
  `message` TEXT NOT NULL,
  `type` ENUM('info', 'warning', 'danger', 'success') NOT NULL DEFAULT 'info',
  `link` VARCHAR(255) NULL,
  `is_read` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (`is_read`),
  INDEX (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 31. Security and Clinical Audit Logs Table
DROP TABLE IF EXISTS `audit_logs`;
CREATE TABLE `audit_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NULL,
  `action` VARCHAR(100) NOT NULL,
  `module` VARCHAR(50) NOT NULL,
  `record_id` VARCHAR(50) NULL,
  `details` TEXT NULL,
  `ip_address` VARCHAR(50) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  INDEX (`user_id`),
  INDEX (`module`),
  INDEX (`action`),
  INDEX (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 32. Dynamic Website Pages Table
DROP TABLE IF EXISTS `website_pages`;
CREATE TABLE `website_pages` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `slug` VARCHAR(80) NOT NULL UNIQUE,
  `title` VARCHAR(150) NOT NULL,
  `meta_description` TEXT NULL,
  `hero_title` VARCHAR(200) NULL,
  `hero_subtitle` TEXT NULL,
  `content` LONGTEXT NULL,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 33. Database Backup Logs Table
DROP TABLE IF EXISTS `backup_logs`;
CREATE TABLE `backup_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `filename` VARCHAR(255) NOT NULL,
  `file_size` BIGINT DEFAULT 0,
  `backup_type` VARCHAR(50) DEFAULT 'manual',
  `created_by` INT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  INDEX (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;

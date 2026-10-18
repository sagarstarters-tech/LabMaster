<?php
/**
 * LabMaster - Migration: Doctor Management & Financial Reconciliation
 * Additive schema changes with zero data destruction.
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/config.php';
// config.php already requires config/database.php

echo "=== LabMaster Doctor & Reconciliation Migration ===\n";

try {
    $db = Database::getInstance();

    // 1. Additive columns to doctors table
    echo "1. Checking and updating `doctors` table...\n";
    $columns = Database::fetchAll("SHOW COLUMNS FROM `doctors`");
    $colNames = array_column($columns, 'Field');

    if (!in_array('qualification', $colNames, true)) {
        Database::execute("ALTER TABLE `doctors` ADD COLUMN `qualification` VARCHAR(150) NULL AFTER `degree`");
        echo "   + Added `qualification` to `doctors`\n";
    } else {
        echo "   . `qualification` already exists\n";
    }

    if (!in_array('medical_registration_number', $colNames, true)) {
        Database::execute("ALTER TABLE `doctors` ADD COLUMN `medical_registration_number` VARCHAR(80) NULL AFTER `specialization`");
        echo "   + Added `medical_registration_number` to `doctors`\n";
    } else {
        echo "   . `medical_registration_number` already exists\n";
    }

    if (!in_array('address', $colNames, true)) {
        Database::execute("ALTER TABLE `doctors` ADD COLUMN `address` TEXT NULL AFTER `clinic_hospital`");
        echo "   + Added `address` to `doctors`\n";
    } else {
        echo "   . `address` already exists\n";
    }

    if (!in_array('updated_at', $colNames, true)) {
        Database::execute("ALTER TABLE `doctors` ADD COLUMN `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
        echo "   + Added `updated_at` to `doctors`\n";
    } else {
        echo "   . `updated_at` already exists\n";
    }

    // Populate qualification from degree for existing records if null
    Database::execute("UPDATE `doctors` SET `qualification` = `degree` WHERE `qualification` IS NULL AND `degree` IS NOT NULL");

    // 2. Create `doctor_service_agreements`
    echo "\n2. Creating `doctor_service_agreements` table...\n";
    $sqlAgreements = "
    CREATE TABLE IF NOT EXISTS `doctor_service_agreements` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `agreement_code` VARCHAR(50) NOT NULL UNIQUE,
        `doctor_id` INT NOT NULL,
        `counterparty_name` VARCHAR(150) NOT NULL,
        `agreement_type` VARCHAR(80) NOT NULL COMMENT 'Consulting, Retainership, Camp Supervision, SLA, etc.',
        `service_description` TEXT NOT NULL,
        `effective_date` DATE NOT NULL,
        `expiry_date` DATE NULL,
        `payment_basis` VARCHAR(80) NOT NULL COMMENT 'Fixed Monthly, Fixed Per Case, Hourly, Per Camp, Non-Monetary',
        `rate_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        `supporting_document_path` VARCHAR(255) NULL,
        `approval_status` ENUM('Draft', 'Under Review', 'Approved', 'Rejected', 'Terminated', 'Expired') NOT NULL DEFAULT 'Draft',
        `reviewer_id` INT NULL,
        `reviewed_at DATETIME NULL,
        `review_notes` TEXT NULL,
        `created_by` INT NOT NULL,
        `notes` TEXT NULL,
        `is_active` TINYINT(1) NOT NULL DEFAULT 1,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_doc_agr (`doctor_id`),
        INDEX idx_status_agr (`approval_status`),
        INDEX idx_dates_agr (`effective_date`, `expiry_date`),
        CONSTRAINT `fk_agr_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`) ON DELETE RESTRICT,
        CONSTRAINT `fk_agr_reviewer` FOREIGN KEY (`reviewer_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
        CONSTRAINT `fk_agr_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    // Check syntax and fix missing backtick on reviewed_at
    $sqlAgreements = str_replace('`reviewed_at DATETIME NULL', '`reviewed_at` DATETIME NULL', $sqlAgreements);
    Database::execute($sqlAgreements);
    echo "   + Table `doctor_service_agreements` is ready.\n";

    // 3. Create `financial_reconciliation_ledger`
    echo "\n3. Creating `financial_reconciliation_ledger` table...\n";
    $sqlLedger = "
    CREATE TABLE IF NOT EXISTS `financial_reconciliation_ledger` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `transaction_code` VARCHAR(50) NOT NULL UNIQUE,
        `agreement_id` INT NOT NULL,
        `doctor_id` INT NOT NULL,
        `related_invoice_id` INT NULL,
        `related_order_id` INT NULL,
        `transaction_date` DATE NOT NULL,
        `transaction_type` VARCHAR(80) NOT NULL COMMENT 'Retainer, Consultation Review, Camp Stipend, etc.',
        `calculation_basis` VARCHAR(150) NOT NULL COMMENT 'Approved documented lawful calculation basis',
        `amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        `payment_status` ENUM('Draft', 'Pending Review', 'Approved', 'Paid', 'Rejected', 'Reversed') NOT NULL DEFAULT 'Draft',
        `payment_method` ENUM('Bank Transfer', 'NEFT/RTGS', 'Cheque', 'UPI', 'Cash', 'Other') NULL,
        `transaction_reference` VARCHAR(100) NULL COMMENT 'UTR/Cheque/Ref number',
        `supporting_voucher_no` VARCHAR(100) NULL,
        `supporting_document_path` VARCHAR(255) NULL,
        `expense_id` INT NULL COMMENT 'Cross-link to accounting expenses table when paid',
        `created_by` INT NOT NULL,
        `approved_by` INT NULL,
        `approved_at` DATETIME NULL,
        `paid_by` INT NULL,
        `paid_at` DATETIME NULL,
        `reversed_by` INT NULL,
        `reversed_at` DATETIME NULL,
        `reversal_reason` TEXT NULL,
        `rejection_reason` TEXT NULL,
        `notes` TEXT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_ledger_agr (`agreement_id`),
        INDEX idx_ledger_doc (`doctor_id`),
        INDEX idx_ledger_status (`payment_status`),
        INDEX idx_ledger_date (`transaction_date`),
        CONSTRAINT `fk_ledg_agreement` FOREIGN KEY (`agreement_id`) REFERENCES `doctor_service_agreements` (`id`) ON DELETE RESTRICT,
        CONSTRAINT `fk_ledg_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`) ON DELETE RESTRICT,
        CONSTRAINT `fk_ledg_invoice` FOREIGN KEY (`related_invoice_id`) REFERENCES `invoices` (`id`) ON DELETE SET NULL,
        CONSTRAINT `fk_ledg_order` FOREIGN KEY (`related_order_id`) REFERENCES `test_orders` (`id`) ON DELETE SET NULL,
        CONSTRAINT `fk_ledg_expense` FOREIGN KEY (`expense_id`) REFERENCES `expenses` (`id`) ON DELETE SET NULL,
        CONSTRAINT `fk_ledg_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
        CONSTRAINT `fk_ledg_approver` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
        CONSTRAINT `fk_ledg_payer` FOREIGN KEY (`paid_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
        CONSTRAINT `fk_ledg_reverser` FOREIGN KEY (`reversed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    Database::execute($sqlLedger);
    echo "   + Table `financial_reconciliation_ledger` is ready.\n";

    // 4. Permissions & Role Mapping
    echo "\n4. Setting up Permissions and RBAC...\n";
    $newPermissions = [
        ['slug' => 'doctors.view', 'name' => 'View Doctor Records', 'module' => 'Doctors', 'description' => 'View doctor directory, profile details, and history.'],
        ['slug' => 'doctors.create', 'name' => 'Create Doctor Records', 'module' => 'Doctors', 'description' => 'Add new doctors to Doctor Master with validation.'],
        ['slug' => 'doctors.edit', 'name' => 'Edit Doctor Records', 'module' => 'Doctors', 'description' => 'Update doctor profile and referral associations.'],
        ['slug' => 'reconciliation.view', 'name' => 'View Financial Reconciliation', 'module' => 'Reconciliation', 'description' => 'View service agreements and financial reconciliation ledger.'],
        ['slug' => 'reconciliation.create', 'name' => 'Create Transaction Drafts', 'module' => 'Reconciliation', 'description' => 'Draft new service agreements and ledger reconciliation entries.'],
        ['slug' => 'agreements.review', 'name' => 'Review Service Agreements', 'module' => 'Reconciliation', 'description' => 'Authorize and approve lawful professional service agreements.'],
        ['slug' => 'reconciliation.approve', 'name' => 'Approve Transactions', 'module' => 'Reconciliation', 'description' => 'Review and approve reconciliation ledger transactions for disbursement.'],
        ['slug' => 'reconciliation.pay', 'name' => 'Disburse & Record Payments', 'module' => 'Reconciliation', 'description' => 'Disburse approved service payouts and generate vouchers.'],
        ['slug' => 'reconciliation.reverse', 'name' => 'Reverse Transactions', 'module' => 'Reconciliation', 'description' => 'Reverse or reject reconciliation items with mandatory audit trail.'],
        ['slug' => 'reconciliation.export', 'name' => 'Export Financial Reports', 'module' => 'Reconciliation', 'description' => 'Export reconciliation ledger and doctor billing statements.']
    ];

    $permMap = [];
    foreach ($newPermissions as $p) {
        $existing = Database::fetch("SELECT id FROM `permissions` WHERE `slug` = ?", [$p['slug']]);
        if (!$existing) {
            Database::execute(
                "INSERT INTO `permissions` (`slug`, `name`, `module`) VALUES (?, ?, ?)",
                [$p['slug'], $p['name'], $p['module']]
            );
            $permId = (int)Database::getInstance()->lastInsertId();
            echo "   + Created permission: {$p['slug']}\n";
        } else {
            $permId = (int)$existing['id'];
            echo "   . Permission {$p['slug']} already exists (ID: {$permId})\n";
        }
        $permMap[$p['slug']] = $permId;
    }

    // Role mapping
    // Fetch roles
    $roles = Database::fetchAll("SELECT id, slug FROM `roles`");
    $rolesBySlug = [];
    foreach ($roles as $r) {
        $rolesBySlug[$r['slug']] = (int)$r['id'];
    }

    // Role assignments:
    // super_admin gets all automatically via Auth::can('...') logic, but we also link into role_permissions
    // lab_admin gets all
    // accountant gets reconciliation.* and doctors.view
    // receptionist gets doctors.view, doctors.create, doctors.edit
    $roleAssignments = [
        'super_admin' => array_keys($permMap),
        'lab_admin'   => array_keys($permMap),
        'accountant'  => ['doctors.view', 'reconciliation.view', 'reconciliation.create', 'agreements.review', 'reconciliation.approve', 'reconciliation.pay', 'reconciliation.reverse', 'reconciliation.export'],
        'receptionist'=> ['doctors.view', 'doctors.create', 'doctors.edit']
    ];

    foreach ($roleAssignments as $roleSlug => $slugs) {
        if (!isset($rolesBySlug[$roleSlug])) continue;
        $roleId = $rolesBySlug[$roleSlug];
        foreach ($slugs as $s) {
            $pId = $permMap[$s];
            $link = Database::fetch("SELECT 1 FROM `role_permissions` WHERE `role_id` = ? AND `permission_id` = ?", [$roleId, $pId]);
            if (!$link) {
                Database::execute("INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (?, ?)", [$roleId, $pId]);
            }
        }
        echo "   + Assigned permissions to role: {$roleSlug}\n";
    }

    // Seed Sample Lawful Agreements & Reconciliation entries for demonstration
    // Let's check if agreements already exist
    $agrCount = (int)(Database::fetch("SELECT COUNT(*) as c FROM `doctor_service_agreements`")['c'] ?? 0);
    if ($agrCount === 0) {
        echo "\n5. Seeding initial documented lawful service agreements...\n";
        // Doctor 1: Dr. Rajesh Sharma - Retainership / Consultant Pathologist
        Database::execute(
            "INSERT INTO `doctor_service_agreements` 
             (`agreement_code`, `doctor_id`, `counterparty_name`, `agreement_type`, `service_description`, 
              `effective_date`, `expiry_date`, `payment_basis`, `rate_amount`, `approval_status`, 
              `reviewer_id`, `reviewed_at`, `review_notes`, `created_by`, `notes`)
             VALUES 
             ('AGR-2026-001', 1, 'Dr. Rajesh Sharma, MD (Path)', 'Retainership / Consultant Pathologist', 
              'Monthly on-call pathological consultation, clinical quality assurance review, and peripheral smear verification.',
              '2026-01-01', '2026-12-31', 'Fixed Monthly Fee', 25000.00, 'Approved', 
              1, NOW(), 'Approved following credentialing and medical council registration verification.', 1, 'Documented contract signed on Jan 1, 2026.')"
        );
        $agr1Id = (int)$db->lastInsertId();

        // Doctor 2: Dr. Priya Patel - Consulting & Second Opinion
        Database::execute(
            "INSERT INTO `doctor_service_agreements` 
             (`agreement_code`, `doctor_id`, `counterparty_name`, `agreement_type`, `service_description`, 
              `effective_date`, `expiry_date`, `payment_basis`, `rate_amount`, `approval_status`, 
              `reviewer_id`, `reviewed_at`, `review_notes`, `created_by`, `notes`)
             VALUES 
             ('AGR-2026-002', 2, 'Carewell Hospital & Dr. Priya Patel', 'Consulting & Second Opinion', 
              'Second-opinion histopathology / endocrinology interpretation for abnormal and critical laboratory profiles.',
              '2026-02-01', '2026-12-31', 'Fixed Per Case / Consultation', 500.00, 'Approved', 
              1, NOW(), 'Lawful institutional expert consultation arrangement with audit compliance.', 1, 'Institutional SLA active.')"
        );
        $agr2Id = (int)$db->lastInsertId();

        // Doctor 3: Dr. Amit Verma - Preventive Camp Supervision (Under Review)
        Database::execute(
            "INSERT INTO `doctor_service_agreements` 
             (`agreement_code`, `doctor_id`, `counterparty_name`, `agreement_type`, `service_description`, 
              `effective_date`, `expiry_date`, `payment_basis`, `rate_amount`, `approval_status`, 
              `reviewer_id`, `reviewed_at`, `review_notes`, `created_by`, `notes`)
             VALUES 
             ('AGR-2026-003', 3, 'Dr. Amit Verma', 'Preventive Camp Supervision', 
              'Supervision and patient counseling during monthly community diabetes and cardiac health screening camps.',
              '2026-03-01', '2026-09-30', 'Per Camp / Event Session', 3500.00, 'Under Review', 
              NULL, NULL, NULL, 1, 'Pending final compliance clearance.')"
        );

        echo "   + Seeded 3 service agreements (Approved & Under Review)\n";

        // Seed reconciliation ledger entries for Dr. Rajesh Sharma
        echo "\n6. Seeding initial documented reconciliation ledger entries...\n";
        // Jan 2026 retainership: Paid
        Database::execute(
            "INSERT INTO `financial_reconciliation_ledger`
             (`transaction_code`, `agreement_id`, `doctor_id`, `transaction_date`, `transaction_type`, 
              `calculation_basis`, `amount`, `payment_status`, `payment_method`, `transaction_reference`, 
              `supporting_voucher_no`, `created_by`, `approved_by`, `approved_at`, `paid_by`, `paid_at`, `notes`)
             VALUES
             ('REC-2026-0001', ?, 1, '2026-01-31', 'Retainership Fee', 
              'Fixed monthly retainership per contract #AGR-2026-001', 25000.00, 'Paid', 'NEFT/RTGS', 'HDFC98214457', 
              'VCH-2026-01-08', 1, 1, '2026-01-31 16:00:00', 1, '2026-01-31 17:30:00', 'January 2026 Retainership Honorarium Paid.')",
            [$agr1Id]
        );

        // Feb 2026 retainership: Approved (Pending Payout)
        Database::execute(
            "INSERT INTO `financial_reconciliation_ledger`
             (`transaction_code`, `agreement_id`, `doctor_id`, `transaction_date`, `transaction_type`, 
              `calculation_basis`, `amount`, `payment_status`, `created_by`, `approved_by`, `approved_at`, `notes`)
             VALUES
             ('REC-2026-0002', ?, 1, '2026-02-28', 'Retainership Fee', 
              'Fixed monthly retainership per contract #AGR-2026-001', 25000.00, 'Approved', 
              1, 1, '2026-03-01 11:00:00', 'February 2026 Retainership Honorarium Approved, awaiting disbursement.')",
            [$agr1Id]
        );

        // March 2026 Consultation fees: Draft
        Database::execute(
            "INSERT INTO `financial_reconciliation_ledger`
             (`transaction_code`, `agreement_id`, `doctor_id`, `transaction_date`, `transaction_type`, 
              `calculation_basis`, `amount`, `payment_status`, `created_by`, `notes`)
             VALUES
             ('REC-2026-0003', ?, 2, '2026-03-15', 'Consultation & Review Fee', 
              'Fixed fee (4 cases @ ₹500/case) per approved agreement #AGR-2026-002', 2000.00, 'Pending Review', 
              1, 'Second-opinion tele-pathology batch review for complex biopsies.')",
            [$agr2Id]
        );

        echo "   + Seeded 3 reconciliation transactions (Paid, Approved, Pending Review)\n";
    }

    echo "\n=== Migration Completed Successfully! ===\n";

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

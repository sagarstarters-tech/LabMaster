-- ============================================================================
-- LabMaster - Pathology Laboratory Management System & Website
-- Comprehensive Realistic Seed Data
-- ============================================================================

USE `labmaster_db`;
SET FOREIGN_KEY_CHECKS = 0;

-- 1. System Settings
INSERT INTO `system_settings` (`setting_key`, `setting_value`, `category`, `display_name`, `field_type`) VALUES
('lab_name', 'LabMaster Diagnostic & Research Center', 'general', 'Laboratory Name', 'text'),
('lab_tagline', 'Precision Diagnostics & Clinical Pathology Excellence', 'general', 'Tagline', 'text'),
('lab_phone', '+91 98765 43210', 'general', 'Primary Contact Phone', 'text'),
('lab_alt_phone', '+91 91234 56789', 'general', 'Emergency / Hotline Phone', 'text'),
('lab_email', 'info@labmasterdiagnostics.com', 'general', 'Contact Email', 'email'),
('lab_address', 'Plot No. 42, Healthcare Square, Central Avenue, Metro City, 400001, India', 'general', 'Laboratory Address', 'textarea'),
('working_hours', 'Monday - Saturday: 07:00 AM - 09:00 PM | Sunday: 08:00 AM - 02:00 PM', 'general', 'Working Hours', 'text'),
('accreditation_text', 'ISO 15189:2022 Compliant & NABL Accredited Reference Laboratory', 'general', 'Accreditation Text', 'text'),
('currency_symbol', '₹', 'billing', 'Currency Symbol', 'text'),
('currency_code', 'INR', 'billing', 'Currency Code', 'text'),
('tax_rate', '0.00', 'billing', 'Default GST / Tax Rate (%)', 'number'),
('patient_prefix', 'LM-P-', 'general', 'Patient ID Prefix', 'text'),
('order_prefix', 'LM-ACC-', 'general', 'Accession / Order Prefix', 'text'),
('report_prefix', 'LM-REP-', 'general', 'Report Number Prefix', 'text'),
('invoice_prefix', 'LM-INV-', 'general', 'Invoice Number Prefix', 'text'),
('receipt_prefix', 'LM-REC-', 'general', 'Receipt Number Prefix', 'text'),
('booking_prefix', 'LM-BK-', 'general', 'Booking Reference Prefix', 'text'),
('whatsapp_floating_phone', '919876543210', 'whatsapp', 'Floating WhatsApp Number (with country code)', 'text'),
('whatsapp_floating_text', 'Hello LabMaster Laboratory, I would like to enquire about test availability and home sample collection.', 'whatsapp', 'Floating WhatsApp Default Greeting', 'textarea'),
('whatsapp_floating_enabled', '1', 'whatsapp', 'Enable Floating WhatsApp Chat', 'select'),
('whatsapp_api_enabled', '0', 'whatsapp', 'Enable WhatsApp Cloud API Integration', 'select'),
('whatsapp_cloud_phone_number_id', '', 'whatsapp', 'Meta WhatsApp Phone Number ID', 'text'),
('whatsapp_cloud_access_token', '', 'whatsapp', 'Meta WhatsApp Cloud Access Token', 'password'),
('whatsapp_cloud_business_account_id', '', 'whatsapp', 'WhatsApp Business Account ID', 'text'),
('whatsapp_report_template', 'labmaster_report_ready', 'whatsapp', 'WhatsApp Report Message Template Name', 'text'),
('pdf_header_title', 'LABMASTER DIAGNOSTIC & RESEARCH CENTER', 'pdf', 'PDF Header Title', 'text'),
('pdf_show_qr_code', '1', 'pdf', 'Show QR Code for Report Verification', 'select'),
('pdf_disclaimer', 'This medical report is based on the specimen received and processed using calibrated clinical laboratory standards. In case of unexpected results, clinical correlation with patient symptoms and treating physician guidance is advised.', 'pdf', 'Report Legal Disclaimer', 'textarea'),
('theme_primary_color', '#0ea5e9', 'appearance', 'Primary Brand Color', 'color'),
('theme_dark_color', '#0f172a', 'appearance', 'Dark Accent Color', 'color'),
('home_sample_collection_enabled', '1', 'general', 'Enable Home Sample Collection Booking', 'select'),
('google_map_embed', 'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d15282225.79979123!2d73.7250245393691!3d20.750301298393563!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x30635ff06b92b791%3A0xd78c4fa1854213a6!2sIndia!5e0!3m2!1sen!2sin!4v1652174829141!5m2!1sen!2sin', 'general', 'Google Maps Embed URL', 'textarea');

-- 2. Roles
INSERT INTO `roles` (`id`, `name`, `slug`, `description`, `is_system`) VALUES
(1, 'Super Administrator', 'super_admin', 'Full unrestricted administrative access to entire laboratory system', 1),
(2, 'Laboratory Administrator', 'lab_admin', 'Manages lab operations, inventory, billing, staff, and clinical workflows', 1),
(3, 'Pathologist', 'pathologist', 'Medical doctor authorized to review, sign, approve, and amend pathology reports', 1),
(4, 'Laboratory Technician', 'technician', 'Specimen processing, analyzer operations, and clinical test result entry', 1),
(5, 'Receptionist', 'receptionist', 'Patient registration, quick booking triage, appointment scheduling, report release', 1),
(6, 'Billing Operator', 'billing', 'Patient invoices, payment collection, receipts, and cashier management', 1),
(7, 'Inventory Manager', 'inventory_manager', 'Reagent stock receipts, batch management, expiry tracking, supplier orders', 1),
(8, 'Accountant', 'accountant', 'Daily cashbook, expense tracking, accounts receivables, supplier payables, ledger', 1);

-- 3. Permissions
INSERT INTO `permissions` (`id`, `slug`, `name`, `module`) VALUES
(1, 'patients.view', 'View Patients', 'Patients'),
(2, 'patients.create', 'Register New Patient', 'Patients'),
(3, 'patients.edit', 'Edit Patient Info', 'Patients'),
(4, 'patients.export', 'Export Patient Records', 'Patients'),
(5, 'bookings.manage', 'Manage Quick Bookings', 'Bookings'),
(6, 'tests.manage', 'Manage Tests & Parameters', 'Tests'),
(7, 'orders.create', 'Create Test Order / Accession', 'Orders'),
(8, 'orders.view', 'View Orders', 'Orders'),
(9, 'orders.sample_collect', 'Record Sample Collection', 'Orders'),
(10, 'results.entry', 'Enter & Edit Test Results', 'Results'),
(11, 'reports.view', 'View Pathology Reports', 'Reports'),
(12, 'reports.review', 'Review Reports', 'Reports'),
(13, 'reports.approve', 'Approve & Digitally Sign Reports', 'Reports'),
(14, 'reports.amend', 'Amend Approved Reports', 'Reports'),
(15, 'reports.print', 'Print & Export PDF Reports', 'Reports'),
(16, 'reports.whatsapp', 'Share Reports on WhatsApp', 'Reports'),
(17, 'billing.manage', 'Manage Invoices & Payments', 'Billing'),
(18, 'billing.refund', 'Authorize Refunds', 'Billing'),
(19, 'accounting.manage', 'Manage Cashbook & Expenses', 'Accounting'),
(20, 'inventory.manage', 'Manage Reagents & Batches', 'Inventory'),
(21, 'inventory.adjust', 'Adjust Stock & Discard Expired', 'Inventory'),
(22, 'suppliers.manage', 'Manage Suppliers & Purchases', 'Suppliers'),
(23, 'users.manage', 'Manage Staff & Roles', 'Staff'),
(24, 'settings.manage', 'Manage System & Lab Settings', 'Settings'),
(25, 'backup.manage', 'Manage Database Backups', 'Backup'),
(26, 'audit.view', 'View Audit Logs', 'Audit');

-- 4. Role Permissions
-- Super Admin has all permissions (1..26)
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 1, `id` FROM `permissions`;

-- Lab Admin (almost all except user destruction)
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 2, `id` FROM `permissions` WHERE `id` NOT IN (25);

-- Pathologist: reports review, approve, amend, print, whatsapp, results entry, orders view, patient view, doctors view
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES
(3, 1), (3, 8), (3, 10), (3, 11), (3, 12), (3, 13), (3, 14), (3, 15), (3, 16), (3, 27);

-- Technician: sample collection, orders view, patient view, result entry, inventory manage, inventory adjust, reports view, reports print
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES
(4, 1), (4, 8), (4, 9), (4, 10), (4, 11), (4, 15), (4, 20), (4, 21);

-- Receptionist: patients create/edit/view, bookings manage, orders create/view, doctors view, billing manage, reports view, reports print, reports whatsapp
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES
(5, 1), (5, 2), (5, 3), (5, 5), (5, 7), (5, 8), (5, 11), (5, 15), (5, 16), (5, 17), (5, 27);

-- Billing Operator: patients, orders, billing, payments
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES
(6, 1), (6, 2), (6, 7), (6, 8), (6, 17);

-- Inventory Manager: inventory, suppliers
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES
(7, 20), (7, 21), (7, 22);

-- Accountant: billing, accounting, suppliers
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES
(8, 17), (8, 18), (8, 19), (8, 22);

-- 5. Default Users
-- admin / Admin@123
-- dr.sharma / Patho@123
-- tech.anil / Tech@123
-- reception / Staff@123
INSERT INTO `users` (`id`, `role_id`, `username`, `email`, `password_hash`, `full_name`, `phone`, `designation`, `qualification`, `registration_number`, `signature_image`, `is_active`) VALUES
(1, 1, 'admin', 'admin@labmasterdiagnostics.com', '$2y$10$cWeR7bxX6LcVGj4WltP1FOlOvL9KdRww8agXA19V2r9H/rWX0YKQm', 'System Administrator', '+91 98765 43210', 'Chief Technology Officer', 'B.Tech IT, M.Sc Data Systems', 'ADMIN-001', NULL, 1),
(2, 3, 'dr.sharma', 'dr.sharma@labmasterdiagnostics.com', '$2y$10$SHnqcW3kbKCQ1A6gOV.Xpe5capVmvFbYDAGZ5Cbzgw0Rzuknov6jq', 'Dr. Rajesh Sharma', '+91 98221 54321', 'Senior Consultant Pathologist & Lab Director', 'MBBS, MD (Pathology), DCP', 'MCI-48291 / MMC-2009', 'sig_dr_sharma.png', 1),
(3, 4, 'tech.anil', 'tech.anil@labmasterdiagnostics.com', '$2y$10$oKAQLgquh1pvKla/Y8.chOGQK85RpnIaWSaRZ6/40qWZqTkBXY88.', 'Anil Kumar', '+91 97654 32109', 'Senior Laboratory Technician', 'B.Sc (MLT), DMLT', 'LT-2015-883', NULL, 1),
(4, 5, 'reception', 'frontdesk@labmasterdiagnostics.com', '$2y$10$xnMuAalrUB7c2y.dI2X4g.9OEwDeGLfuvf02jK6VwFns3.PBea6t2', 'Pooja Verma', '+91 96543 21098', 'Front Desk & Patient Care Executive', 'B.Com, Healthcare Administration', 'FD-104', NULL, 1);

-- 6. Referring Doctors
INSERT INTO `doctors` (`id`, `doctor_code`, `name`, `degree`, `specialization`, `clinic_hospital`, `phone`, `email`, `commission_rate`, `notes`, `is_active`) VALUES
(1, 'DOC-001', 'Dr. Vikram Malhotra', 'MD (Internal Medicine)', 'Consultant Physician', 'Apex Multi-Specialty Clinic', '+91 98111 22334', 'dr.vikram@apexclinic.com', 0.00, 'Senior Physician, General Medicine referrals', 1),
(2, 'DOC-002', 'Dr. Sneha Joshi', 'MS (OBGYN), DGO', 'Obstetrician & Gynecologist', 'Mother & Child Care Hospital', '+91 98222 33445', 'dr.sneha@mothercare.org', 0.00, 'Antenatal and endocrine profiles', 1),
(3, 'DOC-003', 'Dr. Arvind Roy', 'MD (Pediatrics)', 'Senior Pediatrician', 'Little Stars Children Hospital', '+91 98333 44556', 'dr.arvind@littlestars.com', 0.00, 'Pediatric routine and neonatal screening', 1),
(4, 'DOC-004', 'Self / Walk-in Patient', 'N/A', 'Direct Laboratory Walk-in', 'Direct Laboratory Access', '+91 98765 43210', 'walkin@labmasterdiagnostics.com', 0.00, 'Default entry for direct walk-in patients', 1);

-- 7. Test Categories
INSERT INTO `test_categories` (`id`, `name`, `code`, `description`, `icon`, `sort_order`, `is_active`) VALUES
(1, 'Hematology', 'HEM', 'Complete blood cell counts, coagulation assays, peripheral smear examinations', 'bi-droplet-half', 1, 1),
(2, 'Clinical Biochemistry', 'BIO', 'Metabolic profiles, liver enzymes, renal markers, cardiac enzymes, lipids', 'bi-capsule', 2, 1),
(3, 'Endocrinology & Hormones', 'ENDO', 'Thyroid stimulators, fertility hormones, diabetes biomarkers, vitamins', 'bi-activity', 3, 1),
(4, 'Serology & Immunology', 'SERO', 'Infectious disease markers, viral antibodies, antigen screening, Widal test', 'bi-shield-check', 4, 1),
(5, 'Clinical Pathology & Urinalysis', 'CP', 'Routine urine analysis, sediment microscopy, stool examination', 'bi-cup-hot', 5, 1),
(6, 'Microbiology', 'MICRO', 'Bacterial cultures, fungal examinations, antibiotic sensitivity testing', 'bi-virus', 6, 1),
(7, 'Histopathology & Cytology', 'HISTO', 'Tissue biopsies, PAP smears, fine needle aspiration cytology (FNAC)', 'bi-eyedropper', 7, 1);

-- 8. Tests Master
INSERT INTO `tests` (`id`, `category_id`, `test_code`, `test_name`, `short_name`, `specimen_type`, `department`, `price`, `cost_price`, `turnaround_time`, `method`, `instructions`, `clinical_significance`, `is_package`, `is_active`) VALUES
(1, 1, 'CBC', 'Complete Blood Count (CBC) with Differential & ESR', 'CBC + ESR', 'EDTA Whole Blood (2ml)', 'Hematology', 450.00, 110.00, '3-4 Hours', 'Automated 5-Part Hematology Analyzer + Manual Microscopy', 'No fasting required. Avoid strenuous exercise prior to sample collection.', 'Comprehensive evaluation of overall health, detection of anemia, leukemia, and infections.', 0, 1),
(2, 2, 'LFT', 'Liver Function Test (LFT Panel)', 'LFT', 'Plain Serum (Yellow/Red Top 3ml)', 'Biochemistry', 850.00, 220.00, '4-6 Hours', 'Spectrophotometry & Colorimetric Enzymatic Assay', 'Overnight 10-12 hours fasting recommended. Avoid alcohol 24 hours prior.', 'Screening for liver diseases, hepatitis, jaundice, cirrhosis, and hepatotoxicity.', 0, 1),
(3, 2, 'KFT', 'Kidney Function Test / Renal Profile (KFT)', 'KFT / RFT', 'Plain Serum (3ml)', 'Biochemistry', 750.00, 180.00, '4-6 Hours', 'Kinetic Colorimetric (Jaffe) & Enzymatic Spectrophotometry', 'Overnight fasting advised. Drink normal amounts of water.', 'Monitors renal filtration, acute and chronic kidney injury, electrolyte status.', 0, 1),
(4, 2, 'LIPID', 'Lipid Profile Comprehensive', 'Lipid Profile', 'Plain Serum (3ml)', 'Biochemistry', 700.00, 160.00, 'Same Day', 'Enzymatic CHOD-PAP / GPO-PAP Photometry', 'Strict 12-14 hours overnight fasting mandatory. Water intake permitted.', 'Cardiovascular disease risk assessment, coronary artery disease monitoring.', 0, 1),
(5, 3, 'THYROID', 'Thyroid Profile Total (T3, T4, TSH)', 'Thyroid Profile', 'Plain Serum (2ml)', 'Endocrinology', 650.00, 190.00, 'Same Day', 'Chemiluminescence Immunoassay (CLIA)', 'Morning sample preferred. Take thyroid medications only after blood draw.', 'Evaluation of hypo- or hyper-thyroidism and goiter.', 0, 1),
(6, 3, 'HBA1C', 'Glycated Hemoglobin (HbA1c) with eAG', 'HbA1c', 'EDTA Whole Blood (2ml)', 'Biochemistry', 550.00, 140.00, '4 Hours', 'HPLC (High-Performance Liquid Chromatography)', 'No fasting required. Can be drawn at any time of day.', 'Gold standard for long-term (2-3 months) glycemic control in diabetic patients.', 0, 1),
(7, 2, 'BSF', 'Blood Glucose - Fasting', 'FBS', 'Fluoride Plasma (Grey Top 2ml)', 'Biochemistry', 100.00, 25.00, '2 Hours', 'GOD-POD Enzymatic Method', '8-10 hours strict fasting. Only plain water allowed.', 'Screening and diagnosis of Diabetes Mellitus and Impaired Fasting Glucose.', 0, 1),
(8, 2, 'BSPP', 'Blood Glucose - Post Prandial (PP)', 'PPBS', 'Fluoride Plasma (Grey Top 2ml)', 'Biochemistry', 100.00, 25.00, '2 Hours', 'GOD-POD Enzymatic Method', 'Sample to be collected exactly 2 hours after start of normal meal.', 'Assessment of post-prandial glycemic excursions.', 0, 1),
(9, 5, 'URINE-RE', 'Urine Routine & Microscopic Examination', 'Urine R/E', 'Fresh Morning Midstream Urine (20ml)', 'Clinical Pathology', 200.00, 40.00, '2 Hours', 'Reflectance Photometry Dry Strip & Centrifuged Sediment Microscopy', 'Clean-catch midstream urine in sterile container after washing genital area.', 'Detects urinary tract infections, kidney disorders, proteinuria, glycosuria.', 0, 1),
(10, 4, 'WIDAL', 'Widal Slide Agglutination Test', 'Widal Test', 'Plain Serum (2ml)', 'Serology', 250.00, 50.00, '2 Hours', 'Slide Agglutination with S. Typhi & Paratyphi Antigen Suspensions', 'No fasting required.', 'Qualitative serodiagnosis of Enteric (Typhoid) Fever.', 0, 1),
(11, 4, 'DENGUE-NS1', 'Dengue NS1 Antigen & IgM/IgG Duo', 'Dengue Duo', 'Plain Serum (2ml)', 'Serology', 900.00, 280.00, '2-3 Hours', 'Rapid Immunochromatographic Card Test & ELISA Confirmation', 'No fasting required. Collect within 1-5 days of fever onset.', 'Early detection of Dengue viral infection and distinguishing primary from secondary cases.', 0, 1);

-- 9. Test Parameters
-- Parameters for CBC (test_id = 1)
INSERT INTO `test_parameters` (`id`, `test_id`, `parameter_name`, `parameter_code`, `unit`, `result_type`, `dropdown_options`, `formula`, `decimal_places`, `sort_order`, `critical_low`, `critical_high`) VALUES
(1, 1, 'Hemoglobin (Hb)', 'HB', 'g/dL', 'numeric', NULL, NULL, 1, 1, 7.0, 20.0),
(2, 1, 'Total RBC Count', 'RBC', '10^6/µL', 'numeric', NULL, NULL, 2, 2, 2.50, 6.80),
(3, 1, 'Total Leucocyte Count (WBC)', 'TLC', 'cells/cu.mm', 'numeric', NULL, NULL, 0, 3, 2500, 30000),
(4, 1, 'Neutrophils', 'NEUT', '%', 'numeric', NULL, NULL, 0, 4, 15, 90),
(5, 1, 'Lymphocytes', 'LYMPH', '%', 'numeric', NULL, NULL, 0, 5, 10, 75),
(6, 1, 'Eosinophils', 'EOS', '%', 'numeric', NULL, NULL, 0, 6, NULL, 15),
(7, 1, 'Monocytes', 'MONO', '%', 'numeric', NULL, NULL, 0, 7, NULL, 12),
(8, 1, 'Basophils', 'BASO', '%', 'numeric', NULL, NULL, 0, 8, NULL, 3),
(9, 1, 'Platelet Count', 'PLT', 'lakhs/cu.mm', 'numeric', NULL, NULL, 2, 9, 0.50, 7.00),
(10, 1, 'Packed Cell Volume (PCV / Hematocrit)', 'PCV', '%', 'numeric', NULL, NULL, 1, 10, 20.0, 60.0),
(11, 1, 'Mean Corpuscular Volume (MCV)', 'MCV', 'fL', 'numeric', NULL, NULL, 1, 11, 60.0, 115.0),
(12, 1, 'Mean Corpuscular Hemoglobin (MCH)', 'MCH', 'pg', 'numeric', NULL, NULL, 1, 12, 18.0, 38.0),
(13, 1, 'Mean Corpuscular Hb Conc (MCHC)', 'MCHC', 'g/dL', 'numeric', NULL, NULL, 1, 13, 25.0, 38.0),
(14, 1, 'Erythrocyte Sedimentation Rate (ESR)', 'ESR', 'mm/1st hr', 'numeric', NULL, NULL, 0, 14, NULL, 70);

-- Parameters for LFT (test_id = 2)
INSERT INTO `test_parameters` (`id`, `test_id`, `parameter_name`, `parameter_code`, `unit`, `result_type`, `dropdown_options`, `formula`, `decimal_places`, `sort_order`, `critical_low`, `critical_high`) VALUES
(15, 2, 'Bilirubin - Total', 'TBIL', 'mg/dL', 'numeric', NULL, NULL, 2, 1, NULL, 12.0),
(16, 2, 'Bilirubin - Direct (Conjugated)', 'DBIL', 'mg/dL', 'numeric', NULL, NULL, 2, 2, NULL, 5.0),
(17, 2, 'Bilirubin - Indirect (Unconjugated)', 'IBIL', 'mg/dL', 'formula', NULL, 'TBIL - DBIL', 2, 3, NULL, NULL),
(18, 2, 'SGOT / AST (Aspartate Aminotransferase)', 'SGOT', 'U/L', 'numeric', NULL, NULL, 0, 4, NULL, 500),
(19, 2, 'SGPT / ALT (Alanine Aminotransferase)', 'SGPT', 'U/L', 'numeric', NULL, NULL, 0, 5, NULL, 500),
(20, 2, 'Alkaline Phosphatase (ALP)', 'ALP', 'U/L', 'numeric', NULL, NULL, 0, 6, NULL, 800),
(21, 2, 'Total Protein', 'TP', 'g/dL', 'numeric', NULL, NULL, 2, 7, 4.0, 10.0),
(22, 2, 'Serum Albumin', 'ALB', 'g/dL', 'numeric', NULL, NULL, 2, 8, 2.0, 6.0),
(23, 2, 'Serum Globulin', 'GLOB', 'g/dL', 'formula', NULL, 'TP - ALB', 2, 9, NULL, NULL),
(24, 2, 'Albumin / Globulin (A/G) Ratio', 'AGRATIO', 'ratio', 'formula', NULL, 'ALB / GLOB', 2, 10, NULL, NULL);

-- Parameters for KFT (test_id = 3)
INSERT INTO `test_parameters` (`id`, `test_id`, `parameter_name`, `parameter_code`, `unit`, `result_type`, `dropdown_options`, `formula`, `decimal_places`, `sort_order`, `critical_low`, `critical_high`) VALUES
(25, 3, 'Blood Urea', 'UREA', 'mg/dL', 'numeric', NULL, NULL, 1, 1, 10.0, 120.0),
(26, 3, 'Blood Urea Nitrogen (BUN)', 'BUN', 'mg/dL', 'formula', NULL, 'UREA / 2.14', 1, 2, 5.0, 60.0),
(27, 3, 'Serum Creatinine', 'CREAT', 'mg/dL', 'numeric', NULL, NULL, 2, 3, 0.4, 5.0),
(28, 3, 'Uric Acid', 'URIC', 'mg/dL', 'numeric', NULL, NULL, 2, 4, 1.5, 12.0),
(29, 3, 'Serum Sodium (Na+)', 'SODIUM', 'mEq/L', 'numeric', NULL, NULL, 1, 5, 120.0, 160.0),
(30, 3, 'Serum Potassium (K+)', 'POTASSIUM', 'mEq/L', 'numeric', NULL, NULL, 2, 6, 2.8, 6.5);

-- Parameters for Lipid Profile (test_id = 4)
INSERT INTO `test_parameters` (`id`, `test_id`, `parameter_name`, `parameter_code`, `unit`, `result_type`, `dropdown_options`, `formula`, `decimal_places`, `sort_order`, `critical_low`, `critical_high`) VALUES
(31, 4, 'Total Cholesterol', 'CHOL', 'mg/dL', 'numeric', NULL, NULL, 1, 1, NULL, 300.0),
(32, 4, 'Triglycerides', 'TRIG', 'mg/dL', 'numeric', NULL, NULL, 1, 2, NULL, 500.0),
(33, 4, 'HDL Cholesterol (Good)', 'HDL', 'mg/dL', 'numeric', NULL, NULL, 1, 3, 20.0, NULL),
(34, 4, 'LDL Cholesterol (Bad)', 'LDL', 'mg/dL', 'numeric', NULL, NULL, 1, 4, NULL, 190.0),
(35, 4, 'VLDL Cholesterol', 'VLDL', 'mg/dL', 'formula', NULL, 'TRIG / 5', 1, 5, NULL, NULL),
(36, 4, 'Cholesterol / HDL Ratio', 'CHOLHDL', 'ratio', 'formula', NULL, 'CHOL / HDL', 2, 6, NULL, NULL);

-- Parameters for Thyroid Profile (test_id = 5)
INSERT INTO `test_parameters` (`id`, `test_id`, `parameter_name`, `parameter_code`, `unit`, `result_type`, `dropdown_options`, `formula`, `decimal_places`, `sort_order`, `critical_low`, `critical_high`) VALUES
(37, 5, 'Total Triiodothyronine (T3)', 'T3', 'ng/mL', 'numeric', NULL, NULL, 2, 1, 0.4, 3.5),
(38, 5, 'Total Thyroxine (T4)', 'T4', 'µg/dL', 'numeric', NULL, NULL, 2, 2, 2.0, 18.0),
(39, 5, 'Thyroid Stimulating Hormone (TSH)', 'TSH', 'µIU/mL', 'numeric', NULL, NULL, 3, 3, 0.05, 30.0);

-- Parameters for HbA1c (test_id = 6)
INSERT INTO `test_parameters` (`id`, `test_id`, `parameter_name`, `parameter_code`, `unit`, `result_type`, `dropdown_options`, `formula`, `decimal_places`, `sort_order`, `critical_low`, `critical_high`) VALUES
(40, 6, 'HbA1c (Glycated Hemoglobin)', 'HBA1C_VAL', '%', 'numeric', NULL, NULL, 1, 1, 3.5, 14.0),
(41, 6, 'Estimated Average Glucose (eAG)', 'EAG', 'mg/dL', 'formula', NULL, '(28.7 * HBA1C_VAL) - 46.7', 1, 2, NULL, NULL);

-- Parameters for Fasting Blood Sugar (test_id = 7)
INSERT INTO `test_parameters` (`id`, `test_id`, `parameter_name`, `parameter_code`, `unit`, `result_type`, `dropdown_options`, `formula`, `decimal_places`, `sort_order`, `critical_low`, `critical_high`) VALUES
(42, 7, 'Blood Glucose - Fasting', 'BS_FASTING', 'mg/dL', 'numeric', NULL, NULL, 1, 1, 50.0, 350.0);

-- Parameters for PP Blood Sugar (test_id = 8)
INSERT INTO `test_parameters` (`id`, `test_id`, `parameter_name`, `parameter_code`, `unit`, `result_type`, `dropdown_options`, `formula`, `decimal_places`, `sort_order`, `critical_low`, `critical_high`) VALUES
(43, 8, 'Blood Glucose - Post Prandial', 'BS_PP', 'mg/dL', 'numeric', NULL, NULL, 1, 1, 60.0, 400.0);

-- Parameters for Urine Routine (test_id = 9)
INSERT INTO `test_parameters` (`id`, `test_id`, `parameter_name`, `parameter_code`, `unit`, `result_type`, `dropdown_options`, `formula`, `decimal_places`, `sort_order`, `critical_low`, `critical_high`) VALUES
(44, 9, 'Color', 'U_COLOR', '', 'dropdown', 'Pale Yellow, Straw, Amber, Turbid, Reddish', NULL, 0, 1, NULL, NULL),
(45, 9, 'Appearance / Transparency', 'U_APP', '', 'dropdown', 'Clear, Slightly Hazy, Cloudy, Turbid', NULL, 0, 2, NULL, NULL),
(46, 9, 'pH', 'U_PH', '', 'numeric', NULL, NULL, 1, 3, NULL, NULL),
(47, 9, 'Specific Gravity', 'U_SPGRAV', '', 'numeric', NULL, NULL, 3, 4, NULL, NULL),
(48, 9, 'Proteins / Albumin', 'U_PROT', '', 'dropdown', 'Nil, Trace, 1+ (30 mg/dL), 2+ (100 mg/dL), 3+ (300 mg/dL), 4+ (1000 mg/dL)', NULL, 0, 5, NULL, NULL),
(49, 9, 'Glucose / Sugar', 'U_SUGAR', '', 'dropdown', 'Nil, Trace, 0.5% (1+), 1% (2+), 1.5% (3+), 2% (4+)', NULL, 0, 6, NULL, NULL),
(50, 9, 'Pus Cells (Leucocytes)', 'U_PUS', '/HPF', 'text', NULL, NULL, 0, 7, NULL, NULL),
(51, 9, 'Red Blood Cells (RBCs)', 'U_RBC', '/HPF', 'text', NULL, NULL, 0, 8, NULL, NULL),
(52, 9, 'Epithelial Cells', 'U_EPI', '/HPF', 'text', NULL, NULL, 0, 9, NULL, NULL),
(53, 9, 'Crystals / Casts', 'U_CRYST', '', 'text', NULL, NULL, 0, 10, NULL, NULL);

-- Parameters for Widal (test_id = 10)
INSERT INTO `test_parameters` (`id`, `test_id`, `parameter_name`, `parameter_code`, `unit`, `result_type`, `dropdown_options`, `formula`, `decimal_places`, `sort_order`, `critical_low`, `critical_high`) VALUES
(54, 10, 'S. Typhi \'O\' Antigen Titer', 'WIDAL_TO', 'Titer', 'dropdown', 'Negative (<1:20), 1:40, 1:80, 1:160, 1:320', NULL, 0, 1, NULL, NULL),
(55, 10, 'S. Typhi \'H\' Antigen Titer', 'WIDAL_TH', 'Titer', 'dropdown', 'Negative (<1:20), 1:40, 1:80, 1:160, 1:320', NULL, 0, 2, NULL, NULL),
(56, 10, 'S. Paratyphi \'AH\' Titer', 'WIDAL_AH', 'Titer', 'dropdown', 'Negative (<1:20), 1:40, 1:80, 1:160', NULL, 0, 3, NULL, NULL),
(57, 10, 'S. Paratyphi \'BH\' Titer', 'WIDAL_BH', 'Titer', 'dropdown', 'Negative (<1:20), 1:40, 1:80, 1:160', NULL, 0, 4, NULL, NULL);

-- Parameters for Dengue (test_id = 11)
INSERT INTO `test_parameters` (`id`, `test_id`, `parameter_name`, `parameter_code`, `unit`, `result_type`, `dropdown_options`, `formula`, `decimal_places`, `sort_order`, `critical_low`, `critical_high`) VALUES
(58, 11, 'Dengue NS1 Antigen', 'DENG_NS1', '', 'dropdown', 'NON-REACTIVE (Negative), REACTIVE (Positive)', NULL, 0, 1, NULL, NULL),
(59, 11, 'Dengue IgM Antibodies', 'DENG_IGM', '', 'dropdown', 'NON-REACTIVE (Negative), REACTIVE (Positive), EQUIVOCAL', NULL, 0, 2, NULL, NULL),
(60, 11, 'Dengue IgG Antibodies', 'DENG_IGG', '', 'dropdown', 'NON-REACTIVE (Negative), REACTIVE (Positive)', NULL, 0, 3, NULL, NULL);

-- 10. Reference Intervals (Accurate Biological Ranges with Gender/Age Awareness)
-- Hemoglobin
INSERT INTO `reference_intervals` (`parameter_id`, `gender`, `age_min`, `age_max`, `age_unit`, `min_val`, `max_val`, `normal_text`, `display_interval`, `interpretation`) VALUES
(1, 'Male', 12, 120, 'Years', 13.0, 17.0, NULL, '13.0 - 17.0', 'Adult Male Reference Interval'),
(1, 'Female', 12, 120, 'Years', 12.0, 15.5, NULL, '12.0 - 15.5', 'Adult Female Reference Interval'),
(1, 'All', 0, 11, 'Years', 11.0, 14.5, NULL, '11.0 - 14.5', 'Pediatric Reference Interval');

-- RBC Count
INSERT INTO `reference_intervals` (`parameter_id`, `gender`, `age_min`, `age_max`, `age_unit`, `min_val`, `max_val`, `normal_text`, `display_interval`, `interpretation`) VALUES
(2, 'Male', 12, 120, 'Years', 4.50, 5.90, NULL, '4.50 - 5.90', 'Adult Male'),
(2, 'Female', 12, 120, 'Years', 4.00, 5.20, NULL, '4.00 - 5.20', 'Adult Female'),
(2, 'All', 0, 11, 'Years', 3.80, 5.10, NULL, '3.80 - 5.10', 'Pediatric');

-- Total Leucocyte Count (TLC)
INSERT INTO `reference_intervals` (`parameter_id`, `gender`, `age_min`, `age_max`, `age_unit`, `min_val`, `max_val`, `normal_text`, `display_interval`, `interpretation`) VALUES
(3, 'All', 0, 120, 'Years', 4000, 11000, NULL, '4,000 - 11,000', 'Standard Reference Range');

-- Differential Leucocyte Counts
INSERT INTO `reference_intervals` (`parameter_id`, `gender`, `age_min`, `age_max`, `age_unit`, `min_val`, `max_val`, `normal_text`, `display_interval`, `interpretation`) VALUES
(4, 'All', 0, 120, 'Years', 40, 75, NULL, '40 - 75', 'Neutrophils %'),
(5, 'All', 0, 120, 'Years', 20, 45, NULL, '20 - 45', 'Lymphocytes %'),
(6, 'All', 0, 120, 'Years', 1, 6, NULL, '1 - 6', 'Eosinophils %'),
(7, 'All', 0, 120, 'Years', 2, 10, NULL, '2 - 10', 'Monocytes %'),
(8, 'All', 0, 120, 'Years', 0, 1, NULL, '0 - 1', 'Basophils %'),
(9, 'All', 0, 120, 'Years', 1.50, 4.50, NULL, '1.50 - 4.50', 'Normal Platelet Count'),
(10, 'Male', 12, 120, 'Years', 40.0, 50.0, NULL, '40.0 - 50.0', 'Male PCV'),
(10, 'Female', 12, 120, 'Years', 36.0, 46.0, NULL, '36.0 - 46.0', 'Female PCV'),
(11, 'All', 0, 120, 'Years', 80.0, 100.0, NULL, '80.0 - 100.0', 'Normocytic MCV'),
(12, 'All', 0, 120, 'Years', 27.0, 32.0, NULL, '27.0 - 32.0', 'MCH Range'),
(13, 'All', 0, 120, 'Years', 31.5, 36.0, NULL, '31.5 - 36.0', 'MCHC Range'),
(14, 'Male', 0, 120, 'Years', 0, 15, NULL, '0 - 15', 'Westergren Male ESR'),
(14, 'Female', 0, 120, 'Years', 0, 20, NULL, '0 - 20', 'Westergren Female ESR');

-- LFT Reference Ranges
INSERT INTO `reference_intervals` (`parameter_id`, `gender`, `age_min`, `age_max`, `age_unit`, `min_val`, `max_val`, `normal_text`, `display_interval`, `interpretation`) VALUES
(15, 'All', 0, 120, 'Years', 0.20, 1.20, NULL, '0.20 - 1.20', 'Total Bilirubin'),
(16, 'All', 0, 120, 'Years', 0.00, 0.30, NULL, '0.00 - 0.30', 'Direct Bilirubin'),
(17, 'All', 0, 120, 'Years', 0.20, 0.80, NULL, '0.20 - 0.80', 'Indirect Bilirubin'),
(18, 'Male', 0, 120, 'Years', 10, 40, NULL, '10 - 40', 'Male SGOT'),
(18, 'Female', 0, 120, 'Years', 9, 32, NULL, '9 - 32', 'Female SGOT'),
(19, 'Male', 0, 120, 'Years', 10, 45, NULL, '10 - 45', 'Male SGPT'),
(19, 'Female', 0, 120, 'Years', 7, 35, NULL, '7 - 35', 'Female SGPT'),
(20, 'All', 0, 120, 'Years', 44, 147, NULL, '44 - 147', 'Adult ALP'),
(21, 'All', 0, 120, 'Years', 6.40, 8.30, NULL, '6.40 - 8.30', 'Total Serum Protein'),
(22, 'All', 0, 120, 'Years', 3.50, 5.20, NULL, '3.50 - 5.20', 'Albumin'),
(23, 'All', 0, 120, 'Years', 2.30, 3.50, NULL, '2.30 - 3.50', 'Globulin'),
(24, 'All', 0, 120, 'Years', 1.00, 2.20, NULL, '1.00 - 2.20', 'A/G Ratio');

-- KFT Reference Ranges
INSERT INTO `reference_intervals` (`parameter_id`, `gender`, `age_min`, `age_max`, `age_unit`, `min_val`, `max_val`, `normal_text`, `display_interval`, `interpretation`) VALUES
(25, 'All', 0, 120, 'Years', 15.0, 45.0, NULL, '15.0 - 45.0', 'Blood Urea'),
(26, 'All', 0, 120, 'Years', 7.0, 21.0, NULL, '7.0 - 21.0', 'BUN'),
(27, 'Male', 0, 120, 'Years', 0.70, 1.30, NULL, '0.70 - 1.30', 'Male Creatinine'),
(27, 'Female', 0, 120, 'Years', 0.50, 1.10, NULL, '0.50 - 1.10', 'Female Creatinine'),
(28, 'Male', 0, 120, 'Years', 3.50, 7.20, NULL, '3.50 - 7.20', 'Male Uric Acid'),
(28, 'Female', 0, 120, 'Years', 2.60, 6.00, NULL, '2.60 - 6.00', 'Female Uric Acid'),
(29, 'All', 0, 120, 'Years', 135.0, 145.0, NULL, '135.0 - 145.0', 'Serum Sodium'),
(30, 'All', 0, 120, 'Years', 3.50, 5.10, NULL, '3.50 - 5.10', 'Serum Potassium');

-- Lipid Reference Ranges
INSERT INTO `reference_intervals` (`parameter_id`, `gender`, `age_min`, `age_max`, `age_unit`, `min_val`, `max_val`, `normal_text`, `display_interval`, `interpretation`) VALUES
(31, 'All', 0, 120, 'Years', 125.0, 200.0, NULL, '< 200.0 Desirable', 'Desirable <200, Borderline 200-239, High >240'),
(32, 'All', 0, 120, 'Years', 50.0, 150.0, NULL, '< 150.0 Normal', 'Normal <150, Borderline 150-199, High >200'),
(33, 'Male', 0, 120, 'Years', 40.0, 60.0, NULL, '> 40.0 High Cardioprotective', 'Cardioprotective >40'),
(33, 'Female', 0, 120, 'Years', 50.0, 70.0, NULL, '> 50.0 High Cardioprotective', 'Cardioprotective >50'),
(34, 'All', 0, 120, 'Years', 60.0, 100.0, NULL, '< 100.0 Optimal', 'Optimal <100, Near optimal 100-129, High >160'),
(35, 'All', 0, 120, 'Years', 10.0, 30.0, NULL, '10.0 - 30.0', 'VLDL Normal'),
(36, 'All', 0, 120, 'Years', 2.50, 4.50, NULL, '< 4.50 Optimal', 'Low Cardiovascular Risk <4.5');

-- Thyroid Reference Ranges
INSERT INTO `reference_intervals` (`parameter_id`, `gender`, `age_min`, `age_max`, `age_unit`, `min_val`, `max_val`, `normal_text`, `display_interval`, `interpretation`) VALUES
(37, 'All', 0, 120, 'Years', 0.80, 2.00, NULL, '0.80 - 2.00', 'Total T3'),
(38, 'All', 0, 120, 'Years', 5.10, 14.10, NULL, '5.10 - 14.10', 'Total T4'),
(39, 'All', 0, 120, 'Years', 0.350, 4.940, NULL, '0.350 - 4.940', 'Euthyroid Range');

-- HbA1c Reference Ranges
INSERT INTO `reference_intervals` (`parameter_id`, `gender`, `age_min`, `age_max`, `age_unit`, `min_val`, `max_val`, `normal_text`, `display_interval`, `interpretation`) VALUES
(40, 'All', 0, 120, 'Years', 4.0, 5.6, NULL, '< 5.7 Normal, 5.7-6.4 Prediabetes, ≥ 6.5 Diabetes', 'ADA Guidelines 2026'),
(41, 'All', 0, 120, 'Years', 70.0, 115.0, NULL, '70.0 - 115.0', 'Non-Diabetic Mean Glucose');

-- Fasting Glucose
INSERT INTO `reference_intervals` (`parameter_id`, `gender`, `age_min`, `age_max`, `age_unit`, `min_val`, `max_val`, `normal_text`, `display_interval`, `interpretation`) VALUES
(42, 'All', 0, 120, 'Years', 70.0, 100.0, NULL, '70.0 - 100.0 Normal (101-125 Impaired, ≥126 Diabetic)', 'ADA Criteria');

-- PP Glucose
INSERT INTO `reference_intervals` (`parameter_id`, `gender`, `age_min`, `age_max`, `age_unit`, `min_val`, `max_val`, `normal_text`, `display_interval`, `interpretation`) VALUES
(43, 'All', 0, 120, 'Years', 80.0, 140.0, NULL, '< 140.0 Normal (140-199 Impaired, ≥200 Diabetic)', 'ADA Criteria');

-- Urine Ranges
INSERT INTO `reference_intervals` (`parameter_id`, `gender`, `age_min`, `age_max`, `age_unit`, `min_val`, `max_val`, `normal_text`, `display_interval`, `interpretation`) VALUES
(44, 'All', 0, 120, 'Years', NULL, NULL, 'Pale Yellow', 'Pale Yellow', 'Normal Color'),
(45, 'All', 0, 120, 'Years', NULL, NULL, 'Clear', 'Clear', 'Normal Clarity'),
(46, 'All', 0, 120, 'Years', 5.0, 7.5, NULL, '5.0 - 7.5', 'Normal Urine pH'),
(47, 'All', 0, 120, 'Years', 1.010, 1.025, NULL, '1.010 - 1.025', 'Specific Gravity'),
(48, 'All', 0, 120, 'Years', NULL, NULL, 'Nil', 'Nil', 'Negative for Proteinuria'),
(49, 'All', 0, 120, 'Years', NULL, NULL, 'Nil', 'Nil', 'Negative for Glucosuria'),
(50, 'All', 0, 120, 'Years', NULL, NULL, '0 - 5 /HPF', '0 - 5 /HPF', 'Normal Leucocytes'),
(51, 'All', 0, 120, 'Years', NULL, NULL, '0 - 2 /HPF', '0 - 2 /HPF', 'Normal Microscopic RBCs'),
(52, 'All', 0, 120, 'Years', NULL, NULL, 'Few', 'Few', 'Normal Epithelial'),
(53, 'All', 0, 120, 'Years', NULL, NULL, 'Not Seen', 'Not Seen', 'Normal');

-- Widal & Dengue Qualitative Normal
INSERT INTO `reference_intervals` (`parameter_id`, `gender`, `age_min`, `age_max`, `age_unit`, `min_val`, `max_val`, `normal_text`, `display_interval`, `interpretation`) VALUES
(54, 'All', 0, 120, 'Years', NULL, NULL, 'Negative (<1:20)', 'Titer < 1:80 (Non-significant)', 'Significant if ≥ 1:160'),
(55, 'All', 0, 120, 'Years', NULL, NULL, 'Negative (<1:20)', 'Titer < 1:80 (Non-significant)', 'Significant if ≥ 1:160'),
(56, 'All', 0, 120, 'Years', NULL, NULL, 'Negative (<1:20)', 'Negative (<1:20)', 'Non-significant'),
(57, 'All', 0, 120, 'Years', NULL, NULL, 'Negative (<1:20)', 'Negative (<1:20)', 'Non-significant'),
(58, 'All', 0, 120, 'Years', NULL, NULL, 'NON-REACTIVE', 'NON-REACTIVE (Negative)', 'Early Dengue Antigen Marker'),
(59, 'All', 0, 120, 'Years', NULL, NULL, 'NON-REACTIVE', 'NON-REACTIVE (Negative)', 'Acute Phase Dengue Antibody'),
(60, 'All', 0, 120, 'Years', NULL, NULL, 'NON-REACTIVE', 'NON-REACTIVE (Negative)', 'Convalescent / Past Infection');

-- 11. Realistic Patients
INSERT INTO `patients` (`id`, `patient_id`, `full_name`, `gender`, `dob`, `age`, `age_unit`, `phone`, `email`, `address`, `blood_group`, `referring_doctor_id`, `medical_history`, `password_hash`) VALUES
(1, 'LM-P-2026-0001', 'Rahul Sharma', 'Male', '1988-04-12', 38, 'Years', '9812345670', 'rahul.sharma@example.com', 'Flat 302, Green Valley Apts, Sector 14, Metro City', 'B+', 1, 'Known case of Mild Essential Hypertension on Telmisartan 40mg.', '$2y$10$cWeR7bxX6LcVGj4WltP1FOlOvL9KdRww8agXA19V2r9H/rWX0YKQm'),
(2, 'LM-P-2026-0002', 'Sunita Deshmukh', 'Female', '1975-09-24', 50, 'Years', '9823456781', 'sunita.d@example.com', 'Bungalow 7, Shanti Nagar, Ring Road, Metro City', 'O+', 2, 'Type 2 Diabetes Mellitus x 8 years, Hypothyroidism.', '$2y$10$cWeR7bxX6LcVGj4WltP1FOlOvL9KdRww8agXA19V2r9H/rWX0YKQm'),
(3, 'LM-P-2026-0003', 'Master Aarav Patel', 'Male', '2019-11-05', 6, 'Years', '9834567892', 'manish.patel@example.com', 'Row House 12, Sunrise Enclave, Metro City', 'A+', 3, 'High grade fever with chills x 4 days. Rule out Dengue / Malaria.', '$2y$10$cWeR7bxX6LcVGj4WltP1FOlOvL9KdRww8agXA19V2r9H/rWX0YKQm'),
(4, 'LM-P-2026-0004', 'Priyanka Sen', 'Female', '1995-02-18', 31, 'Years', '9845678903', 'priyanka.sen@example.com', 'Apartment 5B, Skyline Towers, Tech Park Road', 'AB+', 1, 'Routine executive health checkup before overseas travel.', '$2y$10$cWeR7bxX6LcVGj4WltP1FOlOvL9KdRww8agXA19V2r9H/rWX0YKQm'),
(5, 'LM-P-2026-0005', 'Kishore Kulkarni', 'Male', '1962-07-30', 63, 'Years', '9856789014', 'kishore.k@example.com', 'Lane 4, Prabhat Colony, Old City, Metro City', 'B-', 1, 'Chronic kidney disease Stage 3 follow-up, Dyslipidemia.', '$2y$10$cWeR7bxX6LcVGj4WltP1FOlOvL9KdRww8agXA19V2r9H/rWX0YKQm');

-- 12. Public Quick Test Bookings
INSERT INTO `bookings` (`id`, `booking_reference`, `patient_id`, `patient_name`, `phone`, `email`, `age`, `gender`, `test_ids`, `preferred_date`, `preferred_time_slot`, `is_home_collection`, `collection_address`, `referring_doctor`, `notes`, `status`, `triage_notes`, `created_at`) VALUES
(1, 'LM-BK-2026-0001', 1, 'Rahul Sharma', '9812345670', 'rahul.sharma@example.com', 38, 'Male', '1,4', '2026-10-18', '07:30 AM - 08:30 AM', 1, 'Flat 302, Green Valley Apts, Sector 14, Metro City', 'Dr. Vikram Malhotra', 'Fasting sample required. Please bring collection tubes.', 'Sample Collection Scheduled', 'Phlebotomist Assigned: Ramesh (Call before arrival)', '2026-10-17 08:30:00'),
(2, 'LM-BK-2026-0002', 2, 'Sunita Deshmukh', '9823456781', 'sunita.d@example.com', 50, 'Female', '6,7,8', '2026-10-18', '08:00 AM - 09:00 AM', 1, 'Bungalow 7, Shanti Nagar, Ring Road, Metro City', 'Dr. Vikram Malhotra', 'Fasting & PP 2-hour sample collection.', 'Confirmed', 'Patient confirmed over phone. Phlebotomy scheduled.', '2026-10-17 09:15:00'),
(3, 'LM-BK-2026-0003', NULL, 'Amitabh Roy', '9899887766', 'amitabh.roy@example.com', 45, 'Male', '1,2,3', '2026-10-19', '09:00 AM - 10:00 AM', 0, NULL, 'Self Walk-in', 'Pre-surgery profile tests.', 'New', NULL, '2026-10-17 11:20:00');

-- 13. Reagent Inventory Items
INSERT INTO `inventory_items` (`id`, `item_code`, `name`, `category`, `manufacturer`, `unit`, `minimum_stock`, `current_stock`, `storage_temperature`, `storage_location`, `is_active`) VALUES
(1, 'REG-001', 'Celltac CBC Diluent & Lyse Kit', 'Reagent', 'Nihon Kohden / Transasia', 'Kits', 5, 18, '15°C - 25°C (Room Temp)', 'Hematology Analyzer Bay 1', 1),
(2, 'REG-002', 'Erba XL LFT Reagent Pack (Enzymatic)', 'Reagent', 'Transasia Bio-Medicals', 'Kits', 4, 12, '2°C - 8°C (Refrigerated)', 'Main Lab Refrigerator A - Shelf 2', 1),
(3, 'REG-003', 'Serum Creatinine Jaffe Kinetic Reagent', 'Reagent', 'Roche Diagnostics', 'Bottles', 6, 24, '15°C - 25°C (Room Temp)', 'Biochemistry Reagent Rack 3', 1),
(4, 'REG-004', 'Lipid Profile Enzymatic CHOD-PAP Kit', 'Reagent', 'Tulip Diagnostics', 'Kits', 3, 8, '2°C - 8°C (Refrigerated)', 'Main Lab Refrigerator B - Shelf 1', 1),
(5, 'REG-005', 'TSH Ultrasensitive CLIA Reagent Cartridge', 'Reagent', 'Abbott / Siemens', 'Cartridges', 2, 5, '2°C - 8°C (Refrigerated)', 'Cold Storage Unit 1', 1),
(6, 'CON-001', 'BD Vacutainer K2-EDTA Lavender 2ml', 'Collection Tube', 'Becton Dickinson (BD)', 'Boxes (100 pcs)', 10, 35, '15°C - 30°C (Room Temp)', 'Phlebotomy Station Drawers', 1),
(7, 'CON-002', 'BD Vacutainer Serum SST Gel Yellow 3.5ml', 'Collection Tube', 'Becton Dickinson (BD)', 'Boxes (100 pcs)', 10, 28, '15°C - 30°C (Room Temp)', 'Phlebotomy Station Drawers', 1),
(8, 'CON-003', 'Sterile Urine Container 50ml with Leakproof Cap', 'Collection Tube', 'Polylab Scientific', 'Boxes (50 pcs)', 5, 22, 'Room Temperature', 'Central Consumable Store Shelf C', 1);

-- 14. Inventory Batches
INSERT INTO `inventory_batches` (`id`, `item_id`, `supplier_id`, `batch_number`, `initial_quantity`, `current_quantity`, `unit_cost`, `mfg_date`, `expiry_date`, `status`) VALUES
(1, 1, 1, 'NK-2026-081', 20, 18, 1450.00, '2026-01-10', '2027-06-30', 'Active'),
(2, 2, 1, 'ERBA-LF-441', 15, 12, 1850.00, '2026-02-15', '2027-08-31', 'Active'),
(3, 3, 2, 'ROCHE-CR-902', 30, 24, 720.00, '2025-11-01', '2027-03-31', 'Active'),
(4, 4, 3, 'TULIP-LP-119', 10, 8, 1200.00, '2026-03-01', '2027-09-30', 'Active'),
(5, 5, 2, 'ABB-TSH-773', 6, 5, 4200.00, '2026-04-10', '2026-11-30', 'Near Expiry'),
(6, 6, 3, 'BD-EDTA-8821', 40, 35, 650.00, '2025-10-01', '2028-09-30', 'Active'),
(7, 7, 3, 'BD-SST-9912', 30, 28, 750.00, '2025-12-01', '2028-11-30', 'Active'),
(8, 8, 1, 'PL-UR-3310', 25, 22, 380.00, '2026-01-01', '2029-01-01', 'Active');

-- 15. Suppliers
INSERT INTO `suppliers` (`id`, `supplier_code`, `company_name`, `contact_person`, `phone`, `email`, `address`, `gstin`, `current_balance`, `is_active`) VALUES
(1, 'SUP-001', 'Transasia Bio-Medicals Ltd.', 'Suresh Nambiar', '+91 98200 11223', 'orders@transasia.co.in', 'Transasia House, 8 Chandivali Studio Road, Mumbai 400072', '27AAACT1234F1Z8', 0.00, 1),
(2, 'SUP-002', 'Roche Diagnostics India Pvt Ltd', 'Ananya Deshmukh', '+91 98200 44556', 'diagnostics.sales@roche.com', 'Godrej BKC, 15th Floor, Bandra Kurla Complex, Mumbai', '27AAACR9988H1Z2', 8400.00, 1),
(3, 'SUP-003', 'Tulip Diagnostics & BD Distributor Network', 'Rajiv Singhal', '+91 98200 77889', 'rajiv@tulipdistributors.com', 'Plot 18, MIDC Industrial Area, Pune 411018', '27AABCT5544K1Z4', 0.00, 1);

-- 16. Test Orders (Accessions)
INSERT INTO `test_orders` (`id`, `accession_number`, `patient_id`, `referring_doctor_id`, `booking_id`, `order_date`, `priority`, `clinical_notes`, `total_amount`, `discount_amount`, `net_amount`, `status`, `payment_status`, `created_by`, `created_at`) VALUES
(1, 'LM-ACC-2026-0001', 1, 1, NULL, '2026-10-16', 'Routine', 'Routine hypertension and lipid evaluation.', 1150.00, 0.00, 1150.00, 'Approved', 'Paid', 4, '2026-10-16 08:45:00'),
(2, 'LM-ACC-2026-0002', 2, 2, NULL, '2026-10-16', 'Routine', 'Diabetic nephropathy screening & thyroid review.', 1300.00, 100.00, 1200.00, 'Approved', 'Paid', 4, '2026-10-16 09:10:00'),
(3, 'LM-ACC-2026-0003', 3, 3, NULL, '2026-10-17', 'Stat', 'Pediatric patient high fever with vomiting. Rule out dengue and acute infection.', 1350.00, 0.00, 1350.00, 'Processing', 'Paid', 4, '2026-10-17 10:00:00');

-- 17. Test Order Items
INSERT INTO `test_order_items` (`id`, `order_id`, `test_id`, `price`, `sample_barcode`, `sample_status`, `sample_type`, `collected_at`, `collected_by`, `received_at`, `rejection_reason`) VALUES
-- Order 1: CBC & Lipid
(1, 1, 1, 450.00, 'BAR-2026-0001-CBC', 'Received', 'EDTA Whole Blood', '2026-10-16 09:00:00', 3, '2026-10-16 09:30:00', NULL),
(2, 1, 4, 700.00, 'BAR-2026-0001-LIPID', 'Received', 'Plain Serum', '2026-10-16 09:00:00', 3, '2026-10-16 09:30:00', NULL),
-- Order 2: Thyroid & HbA1c
(3, 2, 5, 650.00, 'BAR-2026-0002-THY', 'Received', 'Plain Serum', '2026-10-16 09:20:00', 3, '2026-10-16 09:45:00', NULL),
(4, 2, 6, 550.00, 'BAR-2026-0002-HBA', 'Received', 'EDTA Whole Blood', '2026-10-16 09:20:00', 3, '2026-10-16 09:45:00', NULL),
-- Order 3: CBC & Dengue
(5, 3, 1, 450.00, 'BAR-2026-0003-CBC', 'Received', 'EDTA Whole Blood', '2026-10-17 10:15:00', 3, '2026-10-17 10:30:00', NULL),
(6, 3, 11, 900.00, 'BAR-2026-0003-DENG', 'Received', 'Plain Serum', '2026-10-17 10:15:00', 3, '2026-10-17 10:30:00', NULL);

-- 18. Test Results (Order 1: CBC & Lipid)
INSERT INTO `test_results` (`order_id`, `order_item_id`, `parameter_id`, `result_value`, `flag`, `remarks`, `entered_by`, `entered_at`) VALUES
(1, 1, 1, '14.6', 'NORMAL', NULL, 3, '2026-10-16 11:30:00'),
(1, 1, 2, '4.85', 'NORMAL', NULL, 3, '2026-10-16 11:30:00'),
(1, 1, 3, '7800', 'NORMAL', NULL, 3, '2026-10-16 11:30:00'),
(1, 1, 4, '62', 'NORMAL', NULL, 3, '2026-10-16 11:30:00'),
(1, 1, 5, '31', 'NORMAL', NULL, 3, '2026-10-16 11:30:00'),
(1, 1, 6, '4', 'NORMAL', NULL, 3, '2026-10-16 11:30:00'),
(1, 1, 7, '3', 'NORMAL', NULL, 3, '2026-10-16 11:30:00'),
(1, 1, 8, '0', 'NORMAL', NULL, 3, '2026-10-16 11:30:00'),
(1, 1, 9, '2.80', 'NORMAL', NULL, 3, '2026-10-16 11:30:00'),
(1, 1, 10, '44.2', 'NORMAL', NULL, 3, '2026-10-16 11:30:00'),
(1, 1, 11, '91.1', 'NORMAL', NULL, 3, '2026-10-16 11:30:00'),
(1, 1, 12, '30.1', 'NORMAL', NULL, 3, '2026-10-16 11:30:00'),
(1, 1, 13, '33.0', 'NORMAL', NULL, 3, '2026-10-16 11:30:00'),
(1, 1, 14, '8', 'NORMAL', NULL, 3, '2026-10-16 11:30:00'),
-- Lipid Results
(1, 2, 31, '218.0', 'HIGH', 'Borderline elevated total cholesterol.', 3, '2026-10-16 12:00:00'),
(1, 2, 32, '185.0', 'HIGH', 'Mild hypertriglyceridemia.', 3, '2026-10-16 12:00:00'),
(1, 2, 33, '38.0', 'LOW', 'Below optimal cardioprotective level (<40 mg/dL).', 3, '2026-10-16 12:00:00'),
(1, 2, 34, '143.0', 'HIGH', 'Elevated LDL (bad cholesterol). Dietary modification suggested.', 3, '2026-10-16 12:00:00'),
(1, 2, 35, '37.0', 'HIGH', NULL, 3, '2026-10-16 12:00:00'),
(1, 2, 36, '5.74', 'HIGH', 'Increased atherogenic ratio.', 3, '2026-10-16 12:00:00');

-- Test Results (Order 2: Thyroid & HbA1c)
INSERT INTO `test_results` (`order_id`, `order_item_id`, `parameter_id`, `result_value`, `flag`, `remarks`, `entered_by`, `entered_at`) VALUES
(2, 3, 37, '1.15', 'NORMAL', NULL, 3, '2026-10-16 12:30:00'),
(2, 3, 38, '8.40', 'NORMAL', NULL, 3, '2026-10-16 12:30:00'),
(2, 3, 39, '3.210', 'NORMAL', 'Normal euthyroid state maintained on current Thyroxine replacement.', 3, '2026-10-16 12:30:00'),
(2, 4, 40, '7.8', 'HIGH', 'Suboptimal glycemic control (Target < 7.0% for most adults).', 3, '2026-10-16 12:45:00'),
(2, 4, 41, '177.2', 'HIGH', 'Estimated average glucose over past 90 days.', 3, '2026-10-16 12:45:00');

-- 19. Pathology Reports
INSERT INTO `reports` (`id`, `report_number`, `order_id`, `patient_id`, `version_number`, `report_status`, `clinical_remarks`, `interpretation`, `approved_by`, `approved_at`, `digital_signature_used`, `is_released_for_patient`, `pdf_filename`, `verification_token`) VALUES
(1, 'LM-REP-2026-0001', 1, 1, 1, 'Approved', 'CBC parameters are within normal biological reference intervals. Lipid panel demonstrates mixed dyslipidemia with hypercholesterolemia, elevated LDL, and mild hypertriglyceridemia.', 'Dietary fat restriction, regular aerobic exercise, and clinical follow-up with treating physician recommended.', 2, '2026-10-16 14:30:00', 'sig_dr_sharma.png', 1, 'LM-REP-2026-0001.pdf', 'a1b2c3d4e5f67890123456789abcdef0123456789abcdef0123456789abcdef0'),
(2, 'LM-REP-2026-0002', 2, 2, 1, 'Approved', 'Thyroid panel is within normal therapeutic target range. HbA1c of 7.8% indicates fair to poor long-term glycemic regulation.', 'Suggest review of anti-diabetic medication dosage and diabetic diet counseling.', 2, '2026-10-16 15:15:00', 'sig_dr_sharma.png', 1, 'LM-REP-2026-0002.pdf', 'b2c3d4e5f6a17890123456789abcdef0123456789abcdef0123456789abcdef1');

-- 20. Report Approvals History
INSERT INTO `report_approvals` (`id`, `report_id`, `reviewer_id`, `action`, `remarks`, `created_at`) VALUES
(1, 1, 2, 'Approved', 'Results verified against clinical history. Final digital sign-off completed.', '2026-10-16 14:30:00'),
(2, 2, 2, 'Approved', 'HbA1c calibration and internal quality control verified. Approved for release.', '2026-10-16 15:15:00');

-- 21. Invoices
INSERT INTO `invoices` (`id`, `invoice_number`, `order_id`, `patient_id`, `subtotal`, `discount_type`, `discount_rate`, `discount_amount`, `tax_rate`, `tax_amount`, `grand_total`, `paid_amount`, `balance_amount`, `payment_status`, `invoice_date`, `due_date`, `notes`, `created_by`) VALUES
(1, 'LM-INV-2026-0001', 1, 1, 1150.00, 'fixed', 0.00, 0.00, 0.00, 0.00, 1150.00, 1150.00, 0.00, 'Paid', '2026-10-16', '2026-10-16', 'Full payment received via UPI', 4),
(2, 'LM-INV-2026-0002', 2, 2, 1200.00, 'fixed', 0.00, 0.00, 0.00, 0.00, 1200.00, 1200.00, 0.00, 'Paid', '2026-10-16', '2026-10-16', 'Senior citizen discount applied (₹100). Paid in Cash.', 4),
(3, 'LM-INV-2026-0003', 3, 3, 1350.00, 'fixed', 0.00, 0.00, 0.00, 0.00, 1350.00, 1350.00, 0.00, 'Paid', '2026-10-17', '2026-10-17', 'Paid via Card', 4);

-- 22. Invoice Items
INSERT INTO `invoice_items` (`id`, `invoice_id`, `item_description`, `unit_price`, `quantity`, `total_amount`) VALUES
(1, 1, 'Complete Blood Count (CBC) with Differential & ESR', 450.00, 1, 450.00),
(2, 1, 'Lipid Profile Comprehensive', 700.00, 1, 700.00),
(3, 2, 'Thyroid Profile Total (T3, T4, TSH)', 650.00, 1, 650.00),
(4, 2, 'Glycated Hemoglobin (HbA1c) with eAG', 550.00, 1, 550.00),
(5, 3, 'Complete Blood Count (CBC) with Differential & ESR', 450.00, 1, 450.00),
(6, 3, 'Dengue NS1 Antigen & IgM/IgG Duo', 900.00, 1, 900.00);

-- 23. Payments Receipts
INSERT INTO `payments` (`id`, `receipt_number`, `invoice_id`, `amount`, `payment_method`, `transaction_reference`, `payment_date`, `notes`, `received_by`) VALUES
(1, 'LM-REC-2026-0001', 1, 1150.00, 'UPI', 'UPI/261016/982144', '2026-10-16', 'Google Pay payment confirmed', 4),
(2, 'LM-REC-2026-0002', 2, 1200.00, 'Cash', 'CASH-REC-102', '2026-10-16', 'Cash received at reception', 4),
(3, 'LM-REC-2026-0003', 3, 1350.00, 'Card', 'POS-TXN-88421', '2026-10-17', 'Debit Card swipe on POS 2', 4);

-- 24. Operational Expenses
INSERT INTO `expenses` (`id`, `expense_number`, `category`, `amount`, `payment_method`, `paid_to`, `expense_date`, `reference_no`, `notes`, `recorded_by`) VALUES
(1, 'LM-EXP-2026-0001', 'Biomedical Waste', 2500.00, 'Bank Transfer', 'CleanCare Bio-Waste Management Ltd.', '2026-10-15', 'NEFT-883921', 'Monthly authorized biomedical waste collection fee', 1),
(2, 'LM-EXP-2026-0002', 'Stationery', 1200.00, 'Cash', 'Vikas Paper Mart', '2026-10-16', 'BILL-4410', 'A4 report paper reams and barcoded labels', 4),
(3, 'LM-EXP-2026-0003', 'Utilities', 3450.00, 'UPI', 'City Power Distribution Co.', '2026-10-16', 'MSEDCL-9921', 'Electricity bill for lab cooling and analyzers', 1);

-- 25. Stock Movements
INSERT INTO `stock_movements` (`id`, `item_id`, `batch_id`, `movement_type`, `quantity`, `quantity_before`, `quantity_after`, `reference_id`, `reason`, `performed_by`, `created_at`) VALUES
(1, 1, 1, 'Purchase', 20, 0, 20, 'PUR-001', 'Initial stock received from supplier', 1, '2026-10-10 10:00:00'),
(2, 1, 1, 'Test_Consumption', 2, 20, 18, 'LM-ACC-2026-0001', 'Processed 2 CBC runs on analyzer', 3, '2026-10-16 11:30:00');

-- 26. WhatsApp Logs
INSERT INTO `whatsapp_logs` (`id`, `recipient_phone`, `patient_id`, `report_id`, `message_type`, `message_body`, `status`, `api_response`, `sent_by`, `sent_at`) VALUES
(1, '9812345670', 1, 1, 'Report Link', 'Dear Rahul Sharma, your pathology report from LabMaster Diagnostic & Research Center is ready. Access securely at: http://localhost/LabMaster/verify-report.php?token=a1b2c3d4e5f67890123456789abcdef0123456789abcdef0123456789abcdef0', 'Sent', 'API simulated dispatch success. Status 200 OK.', 2, '2026-10-16 14:35:00');

-- 27. In-App Notifications
INSERT INTO `notifications` (`id`, `title`, `message`, `type`, `link`, `is_read`, `created_at`) VALUES
(1, 'New Quick Booking Received', 'Patient Amitabh Roy booked Routine Pathology Tests for 2026-10-19.', 'info', 'bookings/index.php', 0, '2026-10-17 11:20:00'),
(2, 'Stat Test Order In Progress', 'Urgent pediatric CBC & Dengue panel for Master Aarav Patel requires immediate processing.', 'warning', 'orders/view.php?id=3', 0, '2026-10-17 10:05:00'),
(3, 'Reagent Near Expiry Warning', 'TSH Ultrasensitive CLIA Reagent Batch ABB-TSH-773 expires in 44 days (2026-11-30).', 'danger', 'inventory/batches.php', 0, '2026-10-17 08:00:00');

-- 28. Audit Logs
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `module`, `record_id`, `details`, `ip_address`, `created_at`) VALUES
(1, 1, 'System Installed & Seeded', 'System', 'SCHEMA', 'Clean database schema initialized with baseline clinical pathology catalog.', '127.0.0.1', '2026-10-17 08:00:00'),
(2, 4, 'Patient Registered', 'Patients', 'LM-P-2026-0001', 'Registered patient Rahul Sharma (38 Y / Male).', '127.0.0.1', '2026-10-16 08:40:00'),
(3, 2, 'Report Approved', 'Reports', 'LM-REP-2026-0001', 'Dr. Rajesh Sharma approved report version 1 with digital signature.', '127.0.0.1', '2026-10-16 14:30:00');

-- 29. Website Pages
INSERT INTO `website_pages` (`id`, `slug`, `title`, `meta_description`, `hero_title`, `hero_subtitle`, `content`) VALUES
(1, 'home', 'Home - Precision Pathology Laboratory', 'Leading NABL-accredited diagnostic laboratory providing automated pathology testing, home sample collection, and rapid digital reports.', 'Advanced Diagnostic & Clinical Pathology Laboratory', 'Empowering clinical diagnosis with robotic automation, 100% calibrated reference ranges, and verified medical pathologist sign-offs.', '{"hero_badge":"NABL Accredited & ISO 15189:2022 Compliant Laboratory","stats_patients":"150,000+","stats_tests":"350+","stats_accuracy":"99.98%","stats_turnaround":"4 Hours"}'),
(2, 'about', 'About Our Laboratory', 'Learn about LabMaster history, certified pathologists, advanced clinical equipment, and international quality standards.', 'Precision, Integrity & Clinical Dedication', 'Serving physicians, hospitals, and patients with uncompromised diagnostic accuracy since 2012.', '<p>LabMaster Diagnostic & Research Center is a premier pathology reference laboratory equipped with state-of-the-art automated hematology, biochemistry, and chemiluminescence analyzers. Our medical operations are governed by qualified MD Pathologists and licensed medical technologists who adhere strictly to international clinical laboratory standards.</p>'),
(3, 'services', 'Diagnostic & Laboratory Services', 'Comprehensive pathology services including Hematology, Clinical Biochemistry, Endocrinology, Microbiology, and Histopathology.', 'State-of-the-Art Laboratory Disciplines', 'Specialized diagnostic departments delivering deep clinical insights for timely medical interventions.', '<p>Our comprehensive testing menu spans routine preventative screenings to intricate molecular diagnostics, supported by automated cold-chain sample preservation and Barcoded Pre-Analytical Validation.</p>'),
(4, 'tests', 'Pathology Test Catalog & Directory', 'Explore our comprehensive directory of diagnostic pathology tests, sample prerequisites, and biological reference parameters.', 'Complete Pathology Test Directory', 'Find required clinical investigations, specimen guidelines, and prepare for your diagnostic visit.', ''),
(5, 'price-list', 'Transparent Diagnostic Test Price List', 'Review clear, affordable, and honest pricing for all pathology tests and preventative health checkup packages.', 'Honest & Transparent Diagnostic Pricing', 'High quality pathology testing made affordable, ethical, and accessible to every patient.', ''),
(6, 'booking', 'Quick Online Test Booking', 'Book pathology tests and home sample collection in less than 60 seconds with instant booking reference.', 'Quick & Seamless Test Booking', 'Book your test or request a certified phlebotomist at your home or office.', ''),
(7, 'download-report', 'Download Your Medical Pathology Report', 'Securely access and download your certified digital pathology reports using your Patient ID and Mobile Number.', 'Secure Patient Report Download', 'Instant, encrypted access to your verified medical reports with authentic QR code verification.', ''),
(8, 'contact', 'Contact Our Laboratory & Centers', 'Get in touch with our pathology team, view center locations, operating hours, and customer care hotlines.', 'We Are Here to Assist Your Health Journey', 'Reach our diagnostic center, customer care team, or phlebotomy dispatch coordinator.', ''),
(9, 'faqs', 'Frequently Asked Questions', 'Answers to common questions regarding diagnostic testing, fasting requirements, report turnaround, and home collection.', 'Got Questions? We Have Answers', 'Clear, medically reviewed guidance on laboratory testing, fasting protocols, and report interpretation.', ''),
(10, 'privacy-policy', 'Patient Privacy Policy & Medical Data Safeguards', 'How LabMaster protects patient medical records, personal health information, and maintains strict confidentiality.', 'Patient Privacy & Medical Data Protection', 'Your health data and diagnostic test results are protected with enterprise encryption and strict confidentiality protocols.', ''),
(11, 'terms-conditions', 'Terms & Conditions of Laboratory Services', 'Terms governing diagnostic investigations, specimen collection, biological testing, and report release.', 'Terms of Diagnostic Services', 'Important terms governing clinical laboratory testing, sample processing, and diagnostic limitations.', ''),
(12, 'patient-instructions', 'Patient Preparation & Fasting Instructions', 'Essential guidelines for fasting, medication timing, and preparation before blood and urine sample collections.', 'Essential Test Preparation Guidelines', 'Follow these clinical instructions to ensure peak sample accuracy and prevent testing interference.', ''),
(13, 'sample-collection', 'Specimen Collection & Phlebotomy Information', 'Learn about our vacuum tube safety standards, certified phlebotomists, and home collection safety protocols.', 'Safe, Gentle & Sterile Specimen Collection', 'Certified vacuum collection techniques that protect sample integrity and patient comfort.', '');

SET FOREIGN_KEY_CHECKS = 1;

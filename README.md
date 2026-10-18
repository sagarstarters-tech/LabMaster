# LabMaster - Professional Pathology Laboratory Management System & Website

**LabMaster** is a complete, enterprise-grade, secure, dynamic, and production-ready Pathology Laboratory Management System (LIS) and patient-facing website built using modern PHP (8.2+), MariaDB/MySQL (PDO), Bootstrap 5, Vanilla CSS/JS, and Dompdf.

Designed specifically for small to medium-sized clinical diagnostic centers, pathology laboratories, and reference testing centers, LabMaster delivers an end-to-end laboratory information workflow: from online test booking and barcoded accessioning to multi-parameter result entry, automated biological range evaluation, pathologist digital signatures, tamper-proof QR verification, Dompdf generation, WhatsApp sharing, billing ledgers, and lot-tracked reagent inventory.

---

## Table of Contents
1. [Core Features & Modules](#core-features--modules)
2. [Technology Stack](#technology-stack)
3. [Project Directory Structure](#project-directory-structure)
4. [Database Installation & Setup](#database-installation--setup)
5. [Default User Credentials](#default-user-credentials)
6. [Laboratory Workflows & Architectural Highlights](#laboratory-workflows--architectural-highlights)
7. [Automated Acceptance Test Suite](#automated-acceptance-test-suite)
8. [WhatsApp Cloud API & External Integrations](#whatsapp-cloud-api--external-integrations)
9. [Production Deployment Checklist & Security](#production-deployment-checklist--security)

---

## Core Features & Modules

### 1. Professional Public Website (14 Dynamic Pages)
* **Home (`index.php`)**: Hero section, quick test search, clinical discipline overview, popular tests & profiles with live prices, 4-step home collection guide, quality standards, patient reviews, and Google Maps embed.
* **About Our Laboratory (`about.php`)**: Mission, clinical legacy, leadership profiles (Head Pathologist, Technical Officer, Microbiologist), and ISO 15189:2022 quality accreditations.
* **Diagnostic Services (`services.php`)**: Detailed departments covering Hematology, Biochemistry, Endocrinology, Serology, Urinalysis, and Phlebotomy.
* **Test Catalog (`tests.php`)**: Real-time database-driven catalog with category filters, keyword search, specimen types, turnaround times, and direct booking links.
* **Transparent Price List (`price-list.php`)**: Printable and searchable price schedule with instant client-side search.
* **Quick Test Booking (`booking.php`)**: 2-minute booking form with multi-test selection, live estimated price calculation, home sample collection toggle, address field, and triage generation (`LM-BK-YYYY-XXXX`).
* **Download Test Report (`download-report.php`)**: Secure patient report retrieval using registered mobile number + Patient ID/Accession Number/Report Number with full access audit logging.
* **QR Report Authenticity Verification (`verify-report.php`)**: Public clinical verification resolving digital tokens printed on report QR codes to confirm genuine, unaltered pathology records.
* **Contact Us (`contact.php`)**: Physical address, direct telephone lines, emergency helpline, working hours, interactive inquiry form, and interactive Google Map.
* **Patient Resources**:
  * `patient-instructions.php`: Detailed fasting protocols, diabetic meal timing (FBS vs. PPBS), urine clean-catch instructions, and medication alerts.
  * `sample-collection.php`: Vacutainer tube guide (Lavender EDTA, Red/Gold SST, Light Blue Citrate, Grey Fluoride), sterile single-use venipuncture standards, cold-chain transport, and sample rejection criteria.
  * `faqs.php`: Comprehensive clinical and operational FAQs.
  * `privacy-policy.php`: Medical confidentiality and data protection policy.
  * `terms-conditions.php`: Terms of clinical diagnostic service.
* **Floating WhatsApp Live Chat Widget**: Fixed click-to-chat button integrated across all public pages with customizable greeting.

### 2. Patient Health Portal (`/portal`)
* **Secure Authentication (`portal/login.php`)**: Demographic login using registered mobile phone and unique Patient ID (`LM-P-YYYY-XXXX`).
* **Patient Dashboard (`portal/index.php`)**: View medical profile, completed orders, approved reports, instant PDF downloads, invoices, and billing history.
* **Safe Logout (`portal/logout.php`)**: Session invalidation and audit logging.

### 3. Advanced Administration Panel (`/admin`)
* **Live Operational Metrics Dashboard (`admin/index.php`)**: 100% database-calculated statistics—Total Registered Patients, Today's Bookings, Pending Samples, Tests in Progress, Reports Pending Approval, Reports Approved, Collections, Outstanding Balances, Monthly Revenue, Expiring Reagents, and Chart.js 7-day order volume trend.
* **Patient Management (`admin/patients/`)**: Patient registration with automatic duplicate checking, historical bookings, accession orders, diagnostic reports, and payment ledger.
* **Referring Doctors Master (`admin/doctors/`)**: Doctor registration, medical registration number, specialization, clinic address, and commission/referral tracking.
* **Pathology Test & Parameter Master (`admin/tests/`)**: Test catalog configurator with specimen types, departments, prices, preparation instructions, parameter ordering, units, data types (numeric, text, dropdown, calculated formula), and age- and sex-stratified biological reference intervals.
* **Online Booking Triage (`admin/bookings/`)**: Triage incoming bookings, schedule phlebotomist visits, and convert to active laboratory orders with 1 click without duplicate records.
* **Laboratory Accessioning & Orders (`admin/orders/`)**: Order generation (`LM-ACC-YYYY-XXXX`), barcoded tube assignment, priority tagging (Routine, Urgent, Stat), and specimen collection workflow.
* **Clinical Result Entry Engine (`admin/results/`)**:
  * Real-time range evaluation engine (`NORMAL`, `LOW`, `HIGH`, `CRITICAL_LOW`, `CRITICAL_HIGH`, `ABNORMAL`).
  * Dynamic formula calculation engine (Indirect Bilirubin, Globulin, A/G Ratio, Blood Urea Nitrogen, Estimated Average Glucose, Total Cholesterol/HDL Ratio, LDL/HDL Ratio).
* **Pathologist Review & Approval Workflow (`admin/reports/`)**:
  * Multi-stage clinical sign-off (`Draft` -> `Pending Approval` -> `Approved` -> `Amended`).
  * Digital signature stamping.
  * Tamper-evident 64-character verification token generation.
  * Versioning and clinical amendment audit trail.
* **PDF Report Generation & Printing (`admin/reports/pdf.php`, `print.php`)**:
  * High-fidelity Dompdf binary report generation.
  * Medical header with lab logo, accreditation stamp, patient demographics, accession barcode, investigation parameters table with reference intervals and flags, pathologist digital signature stamp, and verification QR code.
* **WhatsApp Report Sharing (`admin/reports/share_whatsapp.php`)**:
  * Dual integration: Meta WhatsApp Business Cloud API + Web dispatch fallback.
  * Transmission audit logging into `whatsapp_logs`.
* **Billing, Invoicing & Receipts (`admin/billing/`)**:
  * Automated invoice generation from test orders (`LM-INV-YYYY-XXXX`).
  * Discounts, taxes, partial payment ledger, and printable receipts (`LM-REC-YYYY-XXXX`).
* **Accounting & Daily Cashbook (`admin/accounting/`)**:
  * Daily cashbook reconciliation (Cash, UPI, Card, Bank Transfer).
  * Operational expense tracking (`LM-EXP-YYYY-XXXX`).
  * Financial intelligence reports: Daily collections, monthly revenue, test-wise revenue, and net profit summaries.
* **Reagents & Consumables Inventory (`admin/inventory/`)**:
  * Reagent catalog, lot/batch tracking (`inventory_batches`), and expiry blocking.
  * Automated stock ledger (`stock_movements`) tracking Purchases, Consumption, Discards, and Adjustments.
* **Supplier & Purchase Management (`admin/suppliers/`)**:
  * Supplier directory, purchase orders, purchase invoices (`LM-PUR-YYYY-XXXX`), and automatic batch creation upon receipt.
* **Staff & Role-Based Access Control (`admin/users/`)**:
  * RBAC matrix with granular permissions (Super Admin, Lab Admin, Pathologist, Technician, Receptionist, Billing Operator, Inventory Manager, Accountant).
  * Digital signature upload for medical pathologists.
* **Security & Clinical Audit Trail (`admin/audit_logs/`)**:
  * Comprehensive logging of logins, clinical result edits, report approvals, amendments, and financial adjustments.
* **Database Backup & Restore (`admin/backup/`)**:
  * Administrator-controlled SQL dump creation, history, and secure downloads outside web root.
* **Central System Preferences (`admin/settings/`)**:
  * Laboratory branding, address, contact details, numbering prefixes, WhatsApp credentials, and PDF layout options.

---

## Technology Stack

| Layer | Technologies |
|---|---|
| **Backend** | PHP 8.2 / 8.3+ (Strict Types, OOP, MVC-style modular architecture) |
| **Database** | MariaDB 10.4+ / MySQL 8.0+ via PDO Prepared Statements (UTF-8 mb4) |
| **Frontend** | Bootstrap 5.3.3, Bootstrap Icons 1.11.3, Chart.js 4.4, Vanilla JavaScript & Custom CSS |
| **PDF Generation** | Dompdf ^3.1 (Embedded in `vendor/`) |
| **Typography** | Inter & Plus Jakarta Sans (Google Fonts) |
| **Integrations** | Meta WhatsApp Cloud API / Web URL Dispatch, QR Code API |

---

## Project Directory Structure

```
LabMaster/
├── admin/                      # Administration Portal
│   ├── accounting/             # Cashbook, Expenses, and Financial Reports
│   ├── audit_logs/             # Clinical & Security Audit Log Ledger
│   ├── backup/                 # Database Backup & SQL Dump Management
│   ├── billing/                # Invoices, Receipts, and Partial Payments
│   ├── bookings/               # Online Booking Triage & Order Conversion
│   ├── doctors/                # Referring Doctors Directory
│   ├── includes/               # Admin Header, Footer, Sidebar, and Auth Guards
│   ├── inventory/              # Reagents, Batches, Stock Movements, Expiry
│   ├── orders/                 # Accessions & Specimen Collection Workflow
│   ├── patients/               # Patient Directory, Registration & History
│   ├── reports/                # Report Builder, Sign-off, PDF, Print, WhatsApp
│   ├── results/                # Clinical Result Entry & Formula Engine
│   ├── settings/               # Lab Profile, Prefixes, WhatsApp & PDF Settings
│   ├── suppliers/              # Suppliers, Purchases & Goods Receipts
│   ├── tests/                  # Pathology Test Master, Parameters & Intervals
│   ├── users/                  # Staff Users, RBAC Roles, and Signatures
│   ├── index.php               # Live Metrics Admin Dashboard
│   ├── login.php               # Staff Secure Authentication
│   └── logout.php              # Session Sign-Out
├── assets/                     # Frontend Assets
│   ├── css/
│   │   ├── admin.css           # Admin Panel Theme & Print Styles
│   │   └── style.css           # Public Medical Aesthetic Theme
│   └── js/
│       ├── admin.js            # Admin Formulas & Live Flag Evaluation
│       └── main.js             # Public Search, Filters, and Booking Calculator
├── config/                     # Core Configuration
│   ├── config.php              # App Constants, URL Auto-detect, Session, Flash
│   └── database.php            # PDO Database Singleton & Query Helpers
├── database/                   # Database Migrations & Testing
│   ├── schema.sql              # 33 Normalized Tables Schema
│   ├── seed_data.sql           # Complete Clinical Catalog, Intervals & Demo Data
│   └── test_runner.php         # Automated 20-Point Acceptance Test Suite
├── includes/                   # Shared Reusable Components
│   ├── auth.php                # Authentication & RBAC Engine (Auth::can)
│   ├── footer.php              # Public Footer & Floating WhatsApp Widget
│   ├── functions.php           # Numbering, CSRF, Audit, Range Engine, Stock Ledger
│   ├── header.php              # Public Navbar & Demographic Header
│   └── whatsapp_service.php    # WhatsApp Cloud API & Web Dispatch Fallback
├── portal/                     # Patient Health Portal
│   ├── index.php               # Patient Dashboard, Reports & Invoices
│   ├── login.php               # Demographic Mobile + Patient ID Authentication
│   └── logout.php              # Patient Sign-Out
├── storage/                    # Persistent Server Storage
│   ├── backups/                # Protected SQL Database Dumps
│   ├── logs/                   # System & Error Logs
│   ├── pdfs/                   # Cached / Generated Report PDFs
│   └── signatures/             # Uploaded Pathologist Signatures
├── vendor/                     # Bundled Dependencies (Dompdf ^3.1)
├── about.php                   # About Our Laboratory
├── booking.php                 # Quick Test Booking System
├── contact.php                 # Contact Us & Location
├── download-report.php         # Secure Public Report Download
├── faqs.php                    # Clinical FAQs
├── index.php                   # Public Homepage
├── patient-instructions.php    # Fasting & Preparation Guidelines
├── price-list.php              # Transparent Test Price List
├── privacy-policy.php          # Medical Data Privacy Policy
├── sample-collection.php       # Phlebotomy & Vacutainer Safety
├── services.php                # Diagnostic Services
├── terms-conditions.php        # Terms of Clinical Service
├── tests.php                   # Pathology Test Directory
└── verify-report.php           # QR Code Report Authenticity Verification
```

---

## Database Installation & Setup

1. **Verify Environment**:
   - Web Server: Apache (XAMPP / WampServer / LAMP)
   - PHP: 8.2 or 8.3+ (Extensions: `pdo_mysql`, `curl`, `mbstring`, `gd`, `openssl`)
   - Database: MySQL 8.0+ or MariaDB 10.4+

2. **Create Database**:
   ```sql
   CREATE DATABASE IF NOT EXISTS `labmaster_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

3. **Import Schema & Seed Data**:
   Using MySQL CLI:
   ```bash
   mysql -u root -p labmaster_db < database/schema.sql
   mysql -u root -p labmaster_db < database/seed_data.sql
   ```
   Or via phpMyAdmin: Select `labmaster_db`, click **Import**, and import `schema.sql` followed by `seed_data.sql`.

4. **Verify Database Configuration**:
   Check `config/database.php` (defaults to host `127.0.0.1`, user `root`, empty password, database `labmaster_db`). Update credentials if different in your environment.

---

## Default User Credentials

### Staff & Administrative Accounts (`/admin/login.php`)

| Role | Username | Password | Full Name | Primary Responsibilities |
|---|---|---|---|---|
| **Super Administrator** | `admin` | `Admin@123` | Dr. Vikram Malhotra | Full System Configuration, RBAC, Backups, Master Settings |
| **Pathologist / Approver** | `dr.sharma` | `Patho@123` | Dr. Rajesh K. Sharma | Medical Smear Review, Critical Values, Report Approval & Digital Signature |
| **Senior Technician** | `tech.anil` | `Tech@123` | Anil Verma | Sample Processing, Analyzer Result Entry, Reagent Consumption |
| **Receptionist** | `reception` | `Staff@123` | Priya Nair | Patient Registration, Quick Booking Triage, Sample Collection Accessions |

### Patient Health Portal (`/portal/login.php`)

| Patient Name | Registered Mobile | Unique Patient ID | Sample Data Included |
|---|---|---|---|
| **Rahul Sharma** | `9812345670` | `LM-P-2026-0001` | CBC, Lipid Profile, Approved Report, Invoices |
| **Sunita Deshmukh** | `9823456781` | `LM-P-2026-0002` | LFT, KFT, HbA1c, Approved Report, Invoices |

---

## Laboratory Workflows & Architectural Highlights

### 1. Clinical Range Evaluation Engine (`evaluate_result_flag`)
Located in `includes/functions.php`, this engine evaluates observed values against patient-specific criteria:
- Matches the patient's biological sex (`Male`, `Female`, or `All`) and age in years.
- Evaluates against `critical_low` and `critical_high` first (`CRITICAL_LOW`, `CRITICAL_HIGH`).
- Evaluates against standard biological reference intervals (`LOW`, `HIGH`, `NORMAL`).
- Formats UI display with high-visibility badges and pulsing alert indicators for life-threatening panic values.

### 2. Automated Biochemical Formulas
Integrated into `assets/js/admin.js` for real-time frontend calculations and validated server-side during sign-off:
- **Indirect Bilirubin** = `Total Bilirubin - Direct Bilirubin`
- **Globulin** = `Total Protein - Albumin`
- **Albumin/Globulin Ratio (A/G)** = `Albumin / Globulin`
- **Blood Urea Nitrogen (BUN)** = `Serum Urea / 2.14`
- **Estimated Average Glucose (eAG)** = `(28.7 × HbA1c) - 46.7`
- **Cholesterol / HDL Ratio** = `Total Cholesterol / HDL`
- **LDL / HDL Ratio** = `LDL / HDL`

### 3. Report Versioning & Amendment Protection
Approved reports cannot be silently edited. When an amendment is necessary:
- A new version number is assigned (`v2`, `v3`).
- Pathologist amendment remarks and reasons must be recorded.
- Reviewer ID and timestamp are stamped into `report_approvals` and `audit_logs`.
- The digital verification token is refreshed and the previous version is archived.

### 4. Lot-Tracked Reagent Inventory & Expiry Blocking
- Every reagent purchase enters a specific batch (`inventory_batches`) with a manufacturer lot number, expiry date, unit cost, and initial quantity.
- Expired reagents (`status = 'Expired'` or `expiry_date < CURDATE()`) are automatically excluded from active stock dropdowns in result entry and cannot be issued.
- Every stock movement (Purchases, Consumption, Discards, Adjustments) records before and after balances in `stock_movements`.

---

## Automated Acceptance Test Suite

LabMaster includes an automated test runner validating all 20 acceptance criteria from Section 26 of the project specification.

To execute the test suite:
```bash
C:\xampp\php\php.exe -d display_errors=1 database/test_runner.php
```

### Verified Test Summary:
1. **Test 1**: Administrator can configure a pathology test.
2. **Test 1b**: Parameter configuration with unit, datatype, and sort order.
3. **Test 2**: Patient submits Quick Test Booking request (`LM-BK-YYYY-XXXX`).
4. **Test 3a**: Patient registered from booking without duplicate records.
5. **Test 3b**: Accession created (`LM-ACC-YYYY-XXXX`) and order items assigned.
6. **Test 4a**: Automated range evaluation engine detects `LOW` value.
7. **Test 4b**: Automated range evaluation engine detects `CRITICAL_LOW` value.
8. **Test 4c**: Technician can enter clinical test results.
9. **Test 5**: Pathologist reviews and approves report with 64-character verification token.
10. **Test 6**: System generates valid binary PDF report via Dompdf.
11. **Test 7**: Direct print endpoint available and styled for laboratory letterhead.
12. **Test 8**: Patient securely retrieves approved report via mobile + identifier.
13. **Test 9**: Access blocked when mobile number does not match patient record.
14. **Test 10**: QR code verification endpoint matches genuine report token.
15. **Test 11**: WhatsApp report sharing records real transmission status in `whatsapp_logs`.
16. **Test 12**: Invoice created from test order with subtotal, discount, tax, and balance.
17. **Test 13**: Payments recorded, receipts generated (`LM-REC-YYYY-XXXX`), and balance tracked.
18. **Test 14**: Reagent purchase increases inventory batch quantity.
19. **Test 15**: Stock issue decreases batch quantity.
20. **Test 16**: Expired stock flagged and blocked from active use.
21. **Test 17**: Financial reports match completed transactions ledger.
22. **Test 18**: Server-side RBAC restricts report approval to pathologists.
23. **Test 19**: Security and clinical audit logging active in `audit_logs`.
24. **Test 20**: Database backup storage and SQL dump creation functional.

---

## WhatsApp Cloud API & External Integrations

### 1. Meta WhatsApp Business Cloud API Configuration
In the Admin Panel, navigate to **Settings** (`/admin/settings/index.php`) -> **WhatsApp Settings**:
1. Check **Enable WhatsApp Cloud API**.
2. Enter your **WhatsApp Phone Number ID** (from Meta Developer App -> WhatsApp -> API Setup).
3. Enter your **WhatsApp Business Account ID (WABA ID)**.
4. Enter your **Permanent System User Access Token**.
5. Save settings.

When enabled, clicking **Share Report on WhatsApp** sends an automated message containing the secure verification link. When disabled, LabMaster automatically falls back to generating a direct click-to-chat web link for instant dispatch.

### 2. Floating Click-to-Chat Button
- Configurable via `whatsapp_floating_phone` and `whatsapp_floating_text` in System Settings.
- Appears on all public pages for prospective patient inquiries.

---

## Production Deployment Checklist & Security

1. **Environment Security**:
   - Ensure `storage/` permissions are set to `0755` (writeable by web server).
   - Protect `storage/backups/` from public HTTP directory indexing using `.htaccess` or Nginx `deny all`.
2. **HTTPS / SSL Configuration**:
   - Enforce HTTPS across all pages by setting `session.cookie_secure = 1` in `config/config.php` when running in production.
3. **Database Credentials**:
   - Change MySQL `root` user password and create a dedicated database user with `SELECT, INSERT, UPDATE, DELETE` privileges.
4. **Change Default Administrative Passwords**:
   - Log into `/admin` as `admin` and immediately update the Super Administrator and staff passwords.
5. **Scheduled Database Backups**:
   - Configure a daily cron job or Windows Task Scheduler to invoke `admin/backup/` or trigger `mysqldump` directly into `storage/backups/`.

---

*LabMaster Laboratory Management System — Designed and Engineered for Diagnostic Precision, Clinical Safety, and Operational Reliability.*

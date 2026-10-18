<?php
/**
 * LabMaster - Medical Data Privacy Policy
 */

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Medical Privacy Policy | ' . Settings::get('lab_name', 'LabMaster Diagnostic & Research Center');
$pageDesc  = 'Our commitment to protecting your clinical diagnostic records, health information, and personal data confidentiality.';
$activePage = 'privacy-policy';

require_once __DIR__ . '/includes/header.php';
?>

<!-- Header Banner -->
<section class="py-5 bg-light border-bottom">
  <div class="container text-center">
    <span class="badge bg-primary-subtle text-primary fw-semibold px-3 py-2 rounded-pill mb-2">Data Protection</span>
    <h1 class="fw-bold mb-3">Clinical Medical Privacy Policy</h1>
    <p class="text-muted max-w-700 mx-auto">
      Safeguarding your sensitive diagnostic health records with end-to-end security, strict role-based access, and medical ethics.
    </p>
  </div>
</section>

<section class="py-5 bg-white">
  <div class="container">
    <div class="row justify-content-center">
      <div class="col-lg-9">
        
        <div class="p-4 p-md-5 rounded-4 border bg-white shadow-sm content-block">
          
          <div class="alert alert-info small mb-4">
            <i class="bi bi-shield-check me-1"></i> <strong>Last Updated:</strong> October 2026. This policy applies to all patients, visitors, and users of <strong><?= e(Settings::get('lab_name', 'LabMaster Diagnostic & Research Center')) ?></strong> physical diagnostic facilities, phlebotomy services, and web portal.
          </div>

          <h5 class="fw-bold text-dark mb-3">1. Information We Collect</h5>
          <p class="text-secondary small mb-3">
            To provide medical laboratory diagnostic testing and generate accurate clinical reports, we collect:
          </p>
          <ul class="text-secondary small mb-4 ps-3">
            <li><strong>Demographic Identifiers:</strong> Patient full name, age, biological sex, date of birth, residential address, telephone number, and email.</li>
            <li><strong>Clinical Information:</strong> Referring physician name, clinical indication or symptoms, medications (e.g. anticoagulants or insulin), fasting status, and biological specimens (blood, urine, fluids).</li>
            <li><strong>Technical & Access Logs:</strong> IP address, device user agent, and timestamps when medical reports are retrieved or verified via QR code.</li>
          </ul>

          <h5 class="fw-bold text-dark mb-3">2. How We Use Clinical Data</h5>
          <p class="text-secondary small mb-3">
            Your personal and clinical data is used strictly for medical laboratory operations:
          </p>
          <ul class="text-secondary small mb-4 ps-3">
            <li>Analyzing specimens on automated analyzers and generating pathologist-signed reports.</li>
            <li>Comparing findings against age- and sex-specific biological reference intervals.</li>
            <li>Alerting referring physicians or patients immediately in the event of panic or critical laboratory values.</li>
            <li>Facilitating secure digital report download, WhatsApp transmission (with patient consent), and billing reconciliation.</li>
          </ul>

          <h5 class="fw-bold text-dark mb-3">3. Medical Confidentiality & Access Control</h5>
          <p class="text-secondary small mb-3">
            Medical records are confidential. We maintain strict role-based access controls (RBAC) ensuring only authorized laboratory technicians, reviewing pathologists, and authenticated patients can view test results. We never sell, rent, or trade patient personal information or medical data to third-party commercial marketing entities.
          </p>

          <h5 class="fw-bold text-dark mb-3">4. WhatsApp & SMS Communications</h5>
          <p class="text-secondary small mb-3">
            When you provide your mobile number during booking or registration, you consent to receiving appointment confirmations, phlebotomist arrival updates, and secure links to your final approved PDF reports via SMS or the official WhatsApp Business Platform.
          </p>

          <h5 class="fw-bold text-dark mb-3">5. Record Retention & Archival</h5>
          <p class="text-secondary small mb-3">
            In compliance with statutory medical council guidelines and laboratory accreditation standards, verified pathology reports and associated audit logs are securely retained in an encrypted format for a minimum statutory period of 5 years.
          </p>

          <h5 class="fw-bold text-dark mb-3">6. Patient Rights</h5>
          <p class="text-secondary small mb-3">
            As a patient, you have the right to access your diagnostic reports, request corrections to demographic inaccuracies, and access past payment receipts through our secure Patient Health Portal.
          </p>

          <h5 class="fw-bold text-dark mb-3">7. Privacy Inquiries & Grievances</h5>
          <p class="text-secondary small mb-0">
            For privacy inquiries or medical record concerns, please contact our Laboratory Compliance Officer at <strong><?= e(Settings::get('lab_email', 'info@labmasterdiagnostics.com')) ?></strong> or call <strong><?= e(Settings::get('lab_phone', '+91 98765 43210')) ?></strong>.
          </p>

        </div>

      </div>
    </div>
  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

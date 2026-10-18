<?php
/**
 * LabMaster - Terms & Conditions of Clinical Diagnostic Service
 */

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Terms of Clinical Service | ' . Settings::get('lab_name', 'LabMaster Diagnostic & Research Center');
$pageDesc  = 'Terms and conditions governing pathology testing, home sample collection, report issuance, and diagnostic services.';
$activePage = 'terms-conditions';

require_once __DIR__ . '/includes/header.php';
?>

<!-- Header Banner -->
<section class="py-5 bg-light border-bottom">
  <div class="container text-center">
    <span class="badge bg-primary-subtle text-primary fw-semibold px-3 py-2 rounded-pill mb-2">Legal & Clinical Protocol</span>
    <h1 class="fw-bold mb-3">Terms & Conditions of Service</h1>
    <p class="text-muted max-w-700 mx-auto">
      Guidelines and policies governing medical laboratory testing, specimen accessioning, and clinical report delivery.
    </p>
  </div>
</section>

<section class="py-5 bg-white">
  <div class="container">
    <div class="row justify-content-center">
      <div class="col-lg-9">
        
        <div class="p-4 p-md-5 rounded-4 border bg-white shadow-sm content-block">
          
          <div class="alert alert-warning small mb-4">
            <i class="bi bi-info-circle me-1"></i> <strong>Important Medical Advisory:</strong> Pathology laboratory results are clinical aids designed to assist licensed physicians in medical evaluation and should always be clinically correlated with patient symptoms and history.
          </div>

          <h5 class="fw-bold text-dark mb-3">1. Scope of Diagnostic Testing</h5>
          <p class="text-secondary small mb-3">
            <strong><?= e(Settings::get('lab_name', 'LabMaster Diagnostic & Research Center')) ?></strong> performs in-vitro diagnostic analysis of biological specimens in accordance with validated clinical methodologies and manufacturer guidelines. Biological reference intervals vary with age, sex, and methodology and are explicitly stated on each report.
          </p>

          <h5 class="fw-bold text-dark mb-3">2. Home Sample Collection</h5>
          <p class="text-secondary small mb-3">
            Home phlebotomy visits are scheduled based on mutual appointment confirmation. Patients must ensure a safe and hygienic environment for venipuncture. In the event of unforeseen transit delays or traffic contingencies, our collection desk will notify the patient promptly.
          </p>

          <h5 class="fw-bold text-dark mb-3">3. Specimen Recollects & Quality Assurance</h5>
          <p class="text-secondary small mb-3">
            If a biological specimen is deemed unsuitable for analysis due to pre-analytical factors beyond laboratory control (such as physiological hemolysis, micro-clots, or lipemia), LabMaster reserves the right to request a fresh specimen recollect at no extra charge to ensure medical accuracy.
          </p>

          <h5 class="fw-bold text-dark mb-3">4. Turnaround Times (TAT)</h5>
          <p class="text-secondary small mb-3">
            Standard turnaround times are indicative and calculated from the time of sample accessioning in the main laboratory. Routine blood counts and biochemical panels are typically completed within 4 to 6 hours. Specialized cultures, biopsy histology, or send-out genetic panels require established microbiological incubation or molecular processing times.
          </p>

          <h5 class="fw-bold text-dark mb-3">5. Report Issuance & Digital Signatures</h5>
          <p class="text-secondary small mb-3">
            Only reports signed by authorized medical pathologists carrying valid clinical registration numbers are deemed final. Draft reports are for internal quality review only and are not released for clinical treatment. Final reports are protected by encrypted verification tokens.
          </p>

          <h5 class="fw-bold text-dark mb-3">6. Invoicing & Refunds</h5>
          <p class="text-secondary small mb-3">
            Payments can be settled through cash, UPI, or card. Bookings cancelled before phlebotomist dispatch or sample processing are eligible for full refund. Once a specimen has been accessioned or chemically processed, laboratory fees are non-refundable.
          </p>

          <h5 class="fw-bold text-dark mb-3">7. Medical Legal Jurisdiction</h5>
          <p class="text-secondary small mb-0">
            All diagnostic services, transactions, and agreements are subject to the exclusive jurisdiction of the competent courts in the city of the laboratory's primary registered office.
          </p>

        </div>

      </div>
    </div>
  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<?php
/**
 * LabMaster - Patient Instructions & Fasting Guidelines
 * Clinical pre-analytical preparation protocol
 */

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Patient Preparation & Fasting Guidelines | ' . Settings::get('lab_name', 'LabMaster Diagnostic & Research Center');
$pageDesc  = 'Detailed clinical instructions for fasting, medication timing, and urine sample collection to ensure maximum diagnostic accuracy.';
$activePage = 'patient-instructions';

require_once __DIR__ . '/includes/header.php';
?>

<!-- Header Banner -->
<section class="py-5 bg-light border-bottom">
  <div class="container text-center">
    <span class="badge bg-primary-subtle text-primary fw-semibold px-3 py-2 rounded-pill mb-2">Pre-Analytical Care</span>
    <h1 class="fw-bold mb-3">Patient Instructions & Test Preparation</h1>
    <p class="text-muted max-w-700 mx-auto">
      Accurate pathology results begin before sample collection. Proper fasting and medication timing eliminate up to 70% of pre-analytical errors.
    </p>
  </div>
</section>

<section class="py-5 bg-white">
  <div class="container">
    
    <div class="row g-4">
      
      <!-- Fasting Blood Tests -->
      <div class="col-lg-6">
        <div class="card border rounded-4 p-4 shadow-sm h-100">
          <div class="d-flex align-items-center gap-3 mb-3">
            <div class="feature-icon-box mb-0">
              <i class="bi bi-clock-history"></i>
            </div>
            <div>
              <h5 class="fw-bold mb-0">Overnight Fasting Protocol</h5>
              <span class="badge bg-danger-subtle text-danger">Strict 10 - 12 Hours</span>
            </div>
          </div>
          <p class="text-secondary small mb-3">
            Fasting means no food or beverages other than plain water for the specified duration. Eating shortly before a test alters blood glucose, triglycerides, and liver enzyme levels.
          </p>
          <div class="bg-light p-3 rounded-3 mb-3 small">
            <h6 class="fw-bold text-dark mb-2">Tests Requiring Strict Fasting:</h6>
            <ul class="mb-0 ps-3 text-secondary">
              <li>Fasting Blood Sugar (FBS): 8 to 10 hours</li>
              <li>Lipid Profile (Cholesterol, Triglycerides, HDL, LDL): 10 to 12 hours</li>
              <li>Fasting Insulin & C-Peptide: 10 to 12 hours</li>
              <li>Iron Profile & Total Iron Binding Capacity (TIBC): 12 hours</li>
              <li>Comprehensive Metabolic Panels (CMP / Master Health): 10 to 12 hours</li>
            </ul>
          </div>
          <div class="small text-muted">
            <strong class="text-dark">Hydration Tip:</strong> Drink 1-2 glasses of plain lukewarm water in the morning before phlebotomy to ensure smooth blood flow.
          </div>
        </div>
      </div>

      <!-- Diabetic Blood Sugar Protocol -->
      <div class="col-lg-6">
        <div class="card border rounded-4 p-4 shadow-sm h-100">
          <div class="d-flex align-items-center gap-3 mb-3">
            <div class="feature-icon-box mb-0">
              <i class="bi bi-activity"></i>
            </div>
            <div>
              <h5 class="fw-bold mb-0">Diabetic Blood Glucose Protocol</h5>
              <span class="badge bg-warning-subtle text-warning-emphasis">Precise Timing Required</span>
            </div>
          </div>
          <p class="text-secondary small mb-3">
            To evaluate glycemic control accurately, sequential blood draws must strictly follow medical timings:
          </p>
          <div class="bg-light p-3 rounded-3 mb-3 small">
            <ul class="mb-0 ps-3 text-secondary">
              <li class="mb-2">
                <strong>Fasting Blood Sugar (FBS):</strong> Given in the morning after overnight fasting. Take regular morning insulin or oral medications ONLY after this sample is drawn and when you sit down for breakfast.
              </li>
              <li class="mb-2">
                <strong>Postprandial Blood Sugar (PPBS):</strong> Must be drawn <em>exactly 2 hours</em> from the start of your regular breakfast or meal (time begins from the first bite of food, not when finished).
              </li>
              <li>
                <strong>HbA1c (Glycated Hemoglobin):</strong> Evaluates 3-month average glucose. <em>Does not require fasting</em> and can be drawn at any time of day.
              </li>
            </ul>
          </div>
        </div>
      </div>

      <!-- Urine Specimen Collection -->
      <div class="col-lg-6">
        <div class="card border rounded-4 p-4 shadow-sm h-100">
          <div class="d-flex align-items-center gap-3 mb-3">
            <div class="feature-icon-box mb-0">
              <i class="bi bi-cup-straw"></i>
            </div>
            <div>
              <h5 class="fw-bold mb-0">Midstream Clean-Catch Urine Collection</h5>
              <span class="badge bg-primary-subtle text-primary">Aseptic Procedure</span>
            </div>
          </div>
          <p class="text-secondary small mb-3">
            Proper urine collection avoids contamination by external genital skin flora, ensuring true microscopic and culture evaluation:
          </p>
          <div class="bg-light p-3 rounded-3 mb-3 small">
            <ol class="mb-0 ps-3 text-secondary">
              <li class="mb-1">Use only the sterile, screw-capped container provided by LabMaster.</li>
              <li class="mb-1">Wash hands thoroughly with soap and water before opening the container.</li>
              <li class="mb-1">Cleanse genital area with water. Do not touch the inside of the sterile container or cap.</li>
              <li class="mb-1">Pass the initial stream of urine into the toilet, and without stopping the stream, catch approximately 30-50 mL of midstream urine in the cup.</li>
              <li class="mb-1">Close the lid tightly and deliver to the laboratory or collection staff within 1 hour.</li>
            </ol>
          </div>
        </div>
      </div>

      <!-- Medication & Supplement Guidelines -->
      <div class="col-lg-6">
        <div class="card border rounded-4 p-4 shadow-sm h-100">
          <div class="d-flex align-items-center gap-3 mb-3">
            <div class="feature-icon-box mb-0">
              <i class="bi bi-capsule"></i>
            </div>
            <div>
              <h5 class="fw-bold mb-0">Medications, Hormones & Supplements</h5>
              <span class="badge bg-info-subtle text-info-emphasis">Consultation Advised</span>
            </div>
          </div>
          <p class="text-secondary small mb-3">
            Certain everyday pharmaceuticals and vitamins interfere with analytical assay reagents:
          </p>
          <div class="bg-light p-3 rounded-3 mb-3 small">
            <ul class="mb-0 ps-3 text-secondary">
              <li class="mb-2">
                <strong>Thyroid Medications (Thyroxine / Eltroxin):</strong> Take your morning tablet <em>after</em> giving your blood sample for Thyroid Profile (T3, T4, TSH).
              </li>
              <li class="mb-2">
                <strong>Biotin (Vitamin B7) Supplements:</strong> High-dose biotin can cause falsely high or low immunoassays (Thyroid, Troponin, Vitamin D). Discontinue biotin 48 hours prior if approved by doctor.
              </li>
              <li>
                <strong>Blood Thinners (Aspirin, Warfarin, Heparin):</strong> Inform the phlebotomist prior to blood draw so extended pressure can be applied to the puncture site to prevent bruising.
              </li>
            </ul>
          </div>
        </div>
      </div>

    </div>

    <!-- Quick Action Banner -->
    <div class="card border-0 bg-primary text-white rounded-4 p-4 p-md-5 mt-5 text-center">
      <h3 class="fw-bold text-white mb-2">Ready for Your Pathology Test?</h3>
      <p class="text-white-50 max-w-700 mx-auto mb-4">
        Our phlebotomists follow every aseptic guideline to ensure your comfort and safety.
      </p>
      <div class="d-flex justify-content-center gap-3">
        <a href="<?= BASE_URL ?>/booking.php" class="btn btn-light text-primary fw-bold">
          <i class="bi bi-calendar-check me-1"></i> Book Sample Collection
        </a>
        <a href="<?= BASE_URL ?>/tests.php" class="btn btn-outline-light">
          <i class="bi bi-list-ul me-1"></i> Browse Test Catalog
        </a>
      </div>
    </div>

  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

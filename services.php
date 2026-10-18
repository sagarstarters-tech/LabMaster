<?php
/**
 * LabMaster - Our Diagnostic Services
 */

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Our Diagnostic Services | ' . Settings::get('lab_name', 'LabMaster Diagnostic & Research Center');
$pageDesc  = 'Comprehensive diagnostic testing services across Hematology, Biochemistry, Endocrinology, Serology, Microbiology, and Preventive Health Panels.';
$activePage = 'services';

require_once __DIR__ . '/includes/header.php';
?>

<!-- Header Banner -->
<section class="py-5 bg-light border-bottom">
  <div class="container text-center">
    <span class="badge bg-primary-subtle text-primary fw-semibold px-3 py-2 rounded-pill mb-2">Clinical Diagnostics</span>
    <h1 class="fw-bold mb-3">Our Laboratory Testing Services</h1>
    <p class="text-muted max-w-700 mx-auto">
      Advanced multi-department pathology testing powered by automated closed-system analyzers and interpreted by certified clinical specialists.
    </p>
  </div>
</section>

<!-- Detailed Services Grid -->
<section class="py-5 bg-white">
  <div class="container">
    <div class="row g-4">
      
      <!-- Hematology -->
      <div class="col-lg-6">
        <div class="p-4 rounded-4 border bg-light h-100 d-flex flex-column justify-content-between">
          <div>
            <div class="d-flex align-items-center gap-3 mb-3">
              <div class="feature-icon-box mb-0">
                <i class="bi bi-droplet-half"></i>
              </div>
              <div>
                <h4 class="fw-bold mb-0">Clinical Hematology & Coagulation</h4>
                <span class="badge bg-danger-subtle text-danger">Same Day Turnaround</span>
              </div>
            </div>
            <p class="text-secondary small mb-3">
              State-of-the-art 5-part differential laser flow cytometry analyzing cellular blood morphology with automated flag verification and microscopic peripheral smear reviews.
            </p>
            <div class="mb-3">
              <h6 class="fw-bold small text-dark mb-2">Key Diagnostic Tests:</h6>
              <div class="d-flex flex-wrap gap-2">
                <span class="badge bg-white text-dark border">Complete Blood Count (CBC)</span>
                <span class="badge bg-white text-dark border">Erythrocyte Sedimentation Rate (ESR)</span>
                <span class="badge bg-white text-dark border">Peripheral Blood Smear</span>
                <span class="badge bg-white text-dark border">Prothrombin Time (PT/INR)</span>
                <span class="badge bg-white text-dark border">APTT</span>
                <span class="badge bg-white text-dark border">D-Dimer</span>
              </div>
            </div>
          </div>
          <div class="pt-3 border-top d-flex justify-content-between align-items-center">
            <span class="text-muted small"><i class="bi bi-eyedropper text-primary me-1"></i> Specimen: Whole Blood (EDTA / Citrate)</span>
            <a href="<?= BASE_URL ?>/booking.php" class="btn btn-sm btn-primary-lm">Book Test</a>
          </div>
        </div>
      </div>

      <!-- Biochemistry -->
      <div class="col-lg-6">
        <div class="p-4 rounded-4 border bg-light h-100 d-flex flex-column justify-content-between">
          <div>
            <div class="d-flex align-items-center gap-3 mb-3">
              <div class="feature-icon-box mb-0">
                <i class="bi bi-activity"></i>
              </div>
              <div>
                <h4 class="fw-bold mb-0">Clinical Biochemistry & Organ Profiles</h4>
                <span class="badge bg-primary-subtle text-primary">Fully Automated Analyzers</span>
              </div>
            </div>
            <p class="text-secondary small mb-3">
              Spectrophotometric dry and liquid chemistry measuring renal, hepatic, cardiac, and electrolyte balance with age- and sex-stratified biological reference intervals.
            </p>
            <div class="mb-3">
              <h6 class="fw-bold small text-dark mb-2">Key Diagnostic Tests:</h6>
              <div class="d-flex flex-wrap gap-2">
                <span class="badge bg-white text-dark border">Liver Function Test (LFT)</span>
                <span class="badge bg-white text-dark border">Kidney Function Test (KFT / RFT)</span>
                <span class="badge bg-white text-dark border">Lipid Profile (Cholesterol, HDL, LDL, VLDL)</span>
                <span class="badge bg-white text-dark border">Fasting Blood Sugar (FBS)</span>
                <span class="badge bg-white text-dark border">Postprandial Blood Sugar (PPBS)</span>
                <span class="badge bg-white text-dark border">Serum Electrolytes (Na+, K+, Cl-)</span>
              </div>
            </div>
          </div>
          <div class="pt-3 border-top d-flex justify-content-between align-items-center">
            <span class="text-muted small"><i class="bi bi-eyedropper text-primary me-1"></i> Specimen: Serum (Clot Activator)</span>
            <a href="<?= BASE_URL ?>/booking.php" class="btn btn-sm btn-primary-lm">Book Test</a>
          </div>
        </div>
      </div>

      <!-- Endocrinology -->
      <div class="col-lg-6">
        <div class="p-4 rounded-4 border bg-light h-100 d-flex flex-column justify-content-between">
          <div>
            <div class="d-flex align-items-center gap-3 mb-3">
              <div class="feature-icon-box mb-0">
                <i class="bi bi-heart-pulse"></i>
              </div>
              <div>
                <h4 class="fw-bold mb-0">Endocrinology & Chemiluminescence (CLIA)</h4>
                <span class="badge bg-warning-subtle text-warning-emphasis">Ultrasensitive Assays</span>
              </div>
            </div>
            <p class="text-secondary small mb-3">
              Sub-picogram sensitivity electrochemiluminescence testing for thyroid disorders, reproductive hormones, diabetes management, and essential micronutrient deficiencies.
            </p>
            <div class="mb-3">
              <h6 class="fw-bold small text-dark mb-2">Key Diagnostic Tests:</h6>
              <div class="d-flex flex-wrap gap-2">
                <span class="badge bg-white text-dark border">Thyroid Profile (Total T3, T4, Ultrasensitive TSH)</span>
                <span class="badge bg-white text-dark border">Glycated Hemoglobin (HbA1c by HPLC)</span>
                <span class="badge bg-white text-dark border">Vitamin D (25-Hydroxy)</span>
                <span class="badge bg-white text-dark border">Vitamin B12</span>
                <span class="badge bg-white text-dark border">Serum Ferritin</span>
                <span class="badge bg-white text-dark border">Beta hCG</span>
              </div>
            </div>
          </div>
          <div class="pt-3 border-top d-flex justify-content-between align-items-center">
            <span class="text-muted small"><i class="bi bi-eyedropper text-primary me-1"></i> Specimen: Serum / EDTA Whole Blood</span>
            <a href="<?= BASE_URL ?>/booking.php" class="btn btn-sm btn-primary-lm">Book Test</a>
          </div>
        </div>
      </div>

      <!-- Serology & Infectious Diseases -->
      <div class="col-lg-6">
        <div class="p-4 rounded-4 border bg-light h-100 d-flex flex-column justify-content-between">
          <div>
            <div class="d-flex align-items-center gap-3 mb-3">
              <div class="feature-icon-box mb-0">
                <i class="bi bi-shield-plus"></i>
              </div>
              <div>
                <h4 class="fw-bold mb-0">Serology & Infectious Diseases</h4>
                <span class="badge bg-success-subtle text-success">Rapid & Confirmation</span>
              </div>
            </div>
            <p class="text-secondary small mb-3">
              Specific antibody and antigen screening detecting seasonal vector-borne diseases, bacterial infections, and viral markers under sterile bio-safety standards.
            </p>
            <div class="mb-3">
              <h6 class="fw-bold small text-dark mb-2">Key Diagnostic Tests:</h6>
              <div class="d-flex flex-wrap gap-2">
                <span class="badge bg-white text-dark border">Dengue Duo (NS1 Antigen, IgM, IgG)</span>
                <span class="badge bg-white text-dark border">Widal Tube Agglutination</span>
                <span class="badge bg-white text-dark border">TyphiDot IgM / IgG</span>
                <span class="badge bg-white text-dark border">Hepatitis B Surface Antigen (HBsAg)</span>
                <span class="badge bg-white text-dark border">HIV 1 & 2 4th Gen Ag/Ab</span>
                <span class="badge bg-white text-dark border">VDRL / RPR</span>
              </div>
            </div>
          </div>
          <div class="pt-3 border-top d-flex justify-content-between align-items-center">
            <span class="text-muted small"><i class="bi bi-eyedropper text-primary me-1"></i> Specimen: Serum</span>
            <a href="<?= BASE_URL ?>/booking.php" class="btn btn-sm btn-primary-lm">Book Test</a>
          </div>
        </div>
      </div>

      <!-- Clinical Pathology & Urinalysis -->
      <div class="col-lg-6">
        <div class="p-4 rounded-4 border bg-light h-100 d-flex flex-column justify-content-between">
          <div>
            <div class="d-flex align-items-center gap-3 mb-3">
              <div class="feature-icon-box mb-0">
                <i class="bi bi-prescription2"></i>
              </div>
              <div>
                <h4 class="fw-bold mb-0">Clinical Pathology & Urinalysis</h4>
                <span class="badge bg-secondary-subtle text-secondary-emphasis">Automated Strip & Microscopy</span>
              </div>
            </div>
            <p class="text-secondary small mb-3">
              Physical, biochemical strip testing and standardized high-power microscopic sediment analysis for crystals, casts, RBCs, pus cells, and proteinuria evaluation.
            </p>
            <div class="mb-3">
              <h6 class="fw-bold small text-dark mb-2">Key Diagnostic Tests:</h6>
              <div class="d-flex flex-wrap gap-2">
                <span class="badge bg-white text-dark border">Urine Routine & Microscopic Examination</span>
                <span class="badge bg-white text-dark border">Urine Albumin / Creatinine Ratio</span>
                <span class="badge bg-white text-dark border">24-Hour Urine Protein</span>
                <span class="badge bg-white text-dark border">Stool Routine & Occult Blood</span>
                <span class="badge bg-white text-dark border">Semen Analysis</span>
              </div>
            </div>
          </div>
          <div class="pt-3 border-top d-flex justify-content-between align-items-center">
            <span class="text-muted small"><i class="bi bi-cup-straw text-primary me-1"></i> Specimen: Fresh Sterile Midstream Urine / Stool</span>
            <a href="<?= BASE_URL ?>/booking.php" class="btn btn-sm btn-primary-lm">Book Test</a>
          </div>
        </div>
      </div>

      <!-- Home Sample Collection -->
      <div class="col-lg-6">
        <div class="p-4 rounded-4 border bg-light h-100 d-flex flex-column justify-content-between">
          <div>
            <div class="d-flex align-items-center gap-3 mb-3">
              <div class="feature-icon-box mb-0">
                <i class="bi bi-house-door"></i>
              </div>
              <div>
                <h4 class="fw-bold mb-0">Phlebotomy & Home Sample Collection</h4>
                <span class="badge bg-success">Doorstep Convenience</span>
              </div>
            </div>
            <p class="text-secondary small mb-3">
              DMLT-certified phlebotomists equipped with calibrated vacuum vacutainers, cold-storage transport kits, and barcoded accessioning at your doorstep across the city.
            </p>
            <div class="mb-3">
              <h6 class="fw-bold small text-dark mb-2">Key Advantages:</h6>
              <div class="d-flex flex-wrap gap-2">
                <span class="badge bg-white text-dark border">Aseptic Single-Use Needles</span>
                <span class="badge bg-white text-dark border">Zero Pre-analytical Contamination</span>
                <span class="badge bg-white text-dark border">Flexible 7:00 AM - 8:00 PM Slots</span>
                <span class="badge bg-white text-dark border">Senior Citizen & Bedridden Care</span>
                <span class="badge bg-white text-dark border">Instant WhatsApp PDF Reports</span>
              </div>
            </div>
          </div>
          <div class="pt-3 border-top d-flex justify-content-between align-items-center">
            <span class="text-muted small"><i class="bi bi-geo-alt text-primary me-1"></i> Across All City Zones</span>
            <a href="<?= BASE_URL ?>/booking.php" class="btn btn-sm btn-primary-lm">Schedule Visit</a>
          </div>
        </div>
      </div>

    </div>
  </div>
</section>

<!-- Call to action -->
<section class="py-5 bg-light border-top text-center">
  <div class="container">
    <h3 class="fw-bold mb-2">Have a Doctor's Prescription?</h3>
    <p class="text-muted mb-4">Book your tests online in under 2 minutes or view our full transparent price catalog.</p>
    <div class="d-flex justify-content-center gap-3">
      <a href="<?= BASE_URL ?>/booking.php" class="btn btn-primary-lm">Book Tests Now</a>
      <a href="<?= BASE_URL ?>/price-list.php" class="btn btn-outline-lm">View Full Price List</a>
    </div>
  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

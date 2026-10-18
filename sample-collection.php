<?php
/**
 * LabMaster - Sample Collection & Phlebotomy Safety Information
 */

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Sample Collection Safety & Phlebotomy | ' . Settings::get('lab_name', 'LabMaster Diagnostic & Research Center');
$pageDesc  = 'Learn about our vacuum vacutainer protocols, sterile single-use needles, cold-chain logistics, and specimen safety standards.';
$activePage = 'sample-collection';

require_once __DIR__ . '/includes/header.php';
?>

<!-- Header Banner -->
<section class="py-5 bg-light border-bottom">
  <div class="container text-center">
    <span class="badge bg-primary-subtle text-primary fw-semibold px-3 py-2 rounded-pill mb-2">Clinical Quality & Safety</span>
    <h1 class="fw-bold mb-3">Specimen Collection & Phlebotomy Safety</h1>
    <p class="text-muted max-w-700 mx-auto">
      Zero contamination, 100% sterile disposable vacutainers, and rigorous cold-chain tracking safeguarding biological integrity.
    </p>
  </div>
</section>

<section class="py-5 bg-white">
  <div class="container">
    
    <!-- Safety Principles -->
    <div class="row g-4 mb-5">
      <div class="col-md-4">
        <div class="feature-card text-center">
          <div class="feature-icon-box mx-auto">
            <i class="bi bi-shield-fill-check"></i>
          </div>
          <h5 class="fw-bold">100% Sterile & Disposable</h5>
          <p class="text-muted small mb-0">Every needle and vacuum holder is opened fresh in front of the patient and safely discarded into biohazard sharps containers immediately after use.</p>
        </div>
      </div>
      <div class="col-md-4">
        <div class="feature-card text-center">
          <div class="feature-icon-box mx-auto">
            <i class="bi bi-qr-code"></i>
          </div>
          <h5 class="fw-bold">Barcoded Accessioning</h5>
          <p class="text-muted small mb-0">Labels are affixed to each tube before the patient leaves the collection chair or phlebotomist departs, eliminating tube mix-ups.</p>
        </div>
      </div>
      <div class="col-md-4">
        <div class="feature-card text-center">
          <div class="feature-icon-box mx-auto">
            <i class="bi bi-snow2"></i>
          </div>
          <h5 class="fw-bold">Calibrated Cold-Chain</h5>
          <p class="text-muted small mb-0">Insulated thermal transport carriers fitted with calibrated phase-change ice packs maintain specimens strictly between 2°C and 8°C.</p>
        </div>
      </div>
    </div>

    <!-- Vacutainer Tube Guide -->
    <div class="mb-5">
      <div class="text-center max-w-700 mx-auto mb-4">
        <span class="badge bg-primary-subtle text-primary fw-semibold px-3 py-2 rounded-pill mb-2">Laboratory Protocol</span>
        <h3 class="fw-bold">Understanding Specimen Collection Tubes</h3>
        <p class="text-muted small">Standardized evacuated blood tubes with precision additives ensuring cellular and biochemical stability.</p>
      </div>

      <div class="row g-3">
        <div class="col-lg-3 col-sm-6">
          <div class="p-3 border rounded-3 bg-light h-100">
            <div class="d-flex align-items-center gap-2 mb-2">
              <span class="p-2 rounded-circle" style="background-color: #7c3aed; width: 20px; height: 20px; display: inline-block;"></span>
              <h6 class="fw-bold mb-0">Lavender / Purple Cap</h6>
            </div>
            <div class="small fw-semibold text-primary mb-1">EDTA K2 / K3 Anticoagulant</div>
            <p class="small text-muted mb-0">Used for Complete Blood Counts (CBC), Erythrocyte Sedimentation Rate (ESR), HbA1c, and peripheral smear morphology.</p>
          </div>
        </div>

        <div class="col-lg-3 col-sm-6">
          <div class="p-3 border rounded-3 bg-light h-100">
            <div class="d-flex align-items-center gap-2 mb-2">
              <span class="p-2 rounded-circle" style="background-color: #dc2626; width: 20px; height: 20px; display: inline-block;"></span>
              <h6 class="fw-bold mb-0">Red / Gold Cap (SST)</h6>
            </div>
            <div class="small fw-semibold text-primary mb-1">Clot Activator & Gel Separator</div>
            <p class="small text-muted mb-0">Used for Liver Function (LFT), Kidney Function (KFT), Lipid Profile, Thyroid, Hormones, and Serological viral antibodies.</p>
          </div>
        </div>

        <div class="col-lg-3 col-sm-6">
          <div class="p-3 border rounded-3 bg-light h-100">
            <div class="d-flex align-items-center gap-2 mb-2">
              <span class="p-2 rounded-circle" style="background-color: #0284c7; width: 20px; height: 20px; display: inline-block;"></span>
              <h6 class="fw-bold mb-0">Light Blue Cap</h6>
            </div>
            <div class="small fw-semibold text-primary mb-1">3.2% Buffered Sodium Citrate</div>
            <p class="small text-muted mb-0">Used for coagulation assays, Prothrombin Time (PT/INR), Activated Partial Thromboplastin Time (APTT), and D-Dimer.</p>
          </div>
        </div>

        <div class="col-lg-3 col-sm-6">
          <div class="p-3 border rounded-3 bg-light h-100">
            <div class="d-flex align-items-center gap-2 mb-2">
              <span class="p-2 rounded-circle" style="background-color: #64748b; width: 20px; height: 20px; display: inline-block;"></span>
              <h6 class="fw-bold mb-0">Grey Cap</h6>
            </div>
            <div class="small fw-semibold text-primary mb-1">Sodium Fluoride & Potassium Oxalate</div>
            <p class="small text-muted mb-0">Glycolytic inhibitor used specifically for Fasting Blood Sugar (FBS) and Postprandial Blood Sugar (PPBS) to stop glucose decay.</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Rejection Criteria -->
    <div class="card border rounded-4 p-4 p-md-5 shadow-sm mb-5 bg-light">
      <div class="row align-items-center g-4">
        <div class="col-lg-5">
          <h4 class="fw-bold mb-3"><i class="bi bi-x-circle text-danger me-2"></i>Specimen Rejection Policy</h4>
          <p class="text-secondary small mb-0">
            To prevent inaccurate reports that could mislead physicians, our laboratory strictly rejects and calls for a free repeat sample under any of the following conditions:
          </p>
        </div>
        <div class="col-lg-7">
          <ul class="mb-0 small text-secondary">
            <li class="mb-2"><strong>Gross Hemolysis:</strong> Ruptured red blood cells falsely spike potassium, LDH, and AST values.</li>
            <li class="mb-2"><strong>Micro-Clots in EDTA Tubes:</strong> Clots invalidate automated platelet and white blood cell counts.</li>
            <li class="mb-2"><strong>Insufficient Quantity (QNS):</strong> Tube vacuum underfilled, altering blood-to-anticoagulant chemical ratios.</li>
            <li class="mb-2"><strong>Lipemic / Chylous Specimens:</strong> Excessively milky serum from high-fat meals interfering with optical light paths.</li>
            <li><strong>Mislabeled or Unlabeled Specimens:</strong> Any tube with missing or disputed identity barcodes.</li>
          </ul>
        </div>
      </div>
    </div>

    <!-- Post-Collection Care -->
    <div class="p-4 bg-white border rounded-4 shadow-sm mb-4">
      <h5 class="fw-bold mb-3"><i class="bi bi-bandaid text-primary me-2"></i>Post-Phlebotomy Care</h5>
      <div class="row g-3 small text-secondary">
        <div class="col-md-4">
          <div class="fw-bold text-dark mb-1">1. Firm Pressure</div>
          Apply direct thumb pressure to the puncture site for 3 to 5 minutes without bending your arm to stop bleeding.
        </div>
        <div class="col-md-4">
          <div class="fw-bold text-dark mb-1">2. Keep Bandage Dry</div>
          Keep the spot adhesive bandage in place for at least 1 hour. Do not scratch or scrub the vein site.
        </div>
        <div class="col-md-4">
          <div class="fw-bold text-dark mb-1">3. Avoid Heavy Lifting</div>
          Avoid carrying heavy bags or intense weight training with the punctured arm for 2 hours to prevent hematoma formation.
        </div>
      </div>
    </div>

    <!-- CTA -->
    <div class="text-center mt-5">
      <a href="<?= BASE_URL ?>/booking.php" class="btn btn-primary-lm btn-lg">
        <i class="bi bi-calendar-check me-2"></i> Schedule Safe Sample Collection
      </a>
    </div>

  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

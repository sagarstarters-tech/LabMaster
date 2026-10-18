<?php
/**
 * LabMaster - About Our Laboratory
 */

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'About Our Laboratory | ' . Settings::get('lab_name', 'LabMaster Diagnostic & Research Center');
$pageDesc  = 'Learn about our clinical pathology excellence, advanced automated analyzers, NABL accreditation, and board-certified medical leadership.';
$activePage = 'about';

require_once __DIR__ . '/includes/header.php';
?>

<!-- Header Banner -->
<section class="py-5 bg-light border-bottom">
  <div class="container text-center">
    <span class="badge bg-primary-subtle text-primary fw-semibold px-3 py-2 rounded-pill mb-2">Our Clinical Legacy</span>
    <h1 class="fw-bold mb-3">About LabMaster Diagnostics</h1>
    <p class="text-muted max-w-700 mx-auto">
      Dedicated to precision medicine and evidence-based pathology through continuous quality control, state-of-the-art robotic instrumentation, and compassionate patient care.
    </p>
  </div>
</section>

<!-- Mission & Vision -->
<section class="py-5 bg-white">
  <div class="container">
    <div class="row g-5 align-items-center">
      <div class="col-lg-6">
        <span class="badge bg-info-subtle text-info fw-semibold px-3 py-2 rounded-pill mb-2">Who We Are</span>
        <h2 class="section-title mb-4">Empowering Healthcare with Reliable Diagnostic Answers</h2>
        <p class="text-secondary mb-3">
          Established as a premier clinical pathology and diagnostic testing center, <strong><?= e(Settings::get('lab_name', 'LabMaster Diagnostic & Research Center')) ?></strong> bridges the gap between high-complexity clinical science and everyday patient accessibility.
        </p>
        <p class="text-secondary mb-4">
          Over 70% of clinical medical decisions are guided by laboratory pathology results. We recognize this profound responsibility and adhere strictly to zero-defect workflows, double-verification protocols, and rapid turnaround commitments.
        </p>

        <div class="row g-3">
          <div class="col-sm-6">
            <div class="p-3 border rounded-3 bg-light">
              <div class="fw-bold text-primary mb-1"><i class="bi bi-bullseye me-1"></i> Our Mission</div>
              <div class="small text-muted">To deliver medically accurate, affordable, and rapid diagnostic insights that improve patient outcomes.</div>
            </div>
          </div>
          <div class="col-sm-6">
            <div class="p-3 border rounded-3 bg-light">
              <div class="fw-bold text-primary mb-1"><i class="bi bi-eye me-1"></i> Our Vision</div>
              <div class="small text-muted">To be the most trusted reference laboratory recognized for clinical integrity and continuous diagnostic innovation.</div>
            </div>
          </div>
        </div>
      </div>

      <div class="col-lg-6">
        <div class="p-4 p-md-5 bg-light rounded-4 border shadow-sm">
          <h4 class="fw-bold mb-4"><i class="bi bi-patch-check-fill text-primary me-2"></i>Quality Accreditations</h4>
          
          <div class="d-flex align-items-start gap-3 mb-4">
            <div class="feature-icon-box flex-shrink-0">
              <i class="bi bi-award"></i>
            </div>
            <div>
              <h6 class="fw-bold mb-1">ISO 15189:2022 Medical Laboratory Standard</h6>
              <p class="text-muted small mb-0">Full compliance with international quality management systems, competence of testing personnel, and calibrated equipment verification.</p>
            </div>
          </div>

          <div class="d-flex align-items-start gap-3 mb-4">
            <div class="feature-icon-box flex-shrink-0">
              <i class="bi bi-shield-check"></i>
            </div>
            <div>
              <h6 class="fw-bold mb-1">NABL Alignment & Proficiency Testing</h6>
              <p class="text-muted small mb-0">Active participation in External Quality Assurance Schemes (EQAS) through CMC Vellore and international proficiency test panels.</p>
            </div>
          </div>

          <div class="d-flex align-items-start gap-3">
            <div class="feature-icon-box flex-shrink-0">
              <i class="bi bi-fingerprint"></i>
            </div>
            <div>
              <h6 class="fw-bold mb-1">Barcoded Closed-System Architecture</h6>
              <p class="text-muted small mb-0">Automated unidirectional and bidirectional LIS communication preventing manual transcription or label confusion.</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Clinical Leadership -->
<section class="py-5 bg-light">
  <div class="container">
    <div class="text-center max-w-700 mx-auto mb-5">
      <span class="badge bg-primary-subtle text-primary fw-semibold px-3 py-2 rounded-pill mb-2">Medical Expertise</span>
      <h2 class="section-title">Our Pathologist & Technical Leadership</h2>
      <p class="text-muted">Led by experienced clinical pathologists and certified medical laboratory technologists dedicated to diagnostic excellence.</p>
    </div>

    <div class="row g-4 justify-content-center">
      <div class="col-lg-4 col-md-6">
        <div class="card border-0 shadow-sm rounded-4 text-center p-4 h-100">
          <div class="mx-auto mb-3 rounded-circle bg-primary-subtle text-primary d-flex align-items-center justify-content-center" style="width: 90px; height: 90px; font-size: 2.5rem;">
            <i class="bi bi-person-badge"></i>
          </div>
          <h5 class="fw-bold mb-1">Dr. Rajesh K. Sharma</h5>
          <div class="text-primary small fw-semibold mb-2">MD, DCP (Clinical Pathology)</div>
          <div class="text-muted small mb-3">Head of Laboratory & Consultant Pathologist<br>Reg. No: MCI-2004-9842</div>
          <p class="text-secondary small">
            With over 18 years of clinical pathology experience in tertiary referral hospitals, Dr. Sharma oversees all hematology smear evaluations, critical value reporting, and quality assurance.
          </p>
        </div>
      </div>

      <div class="col-lg-4 col-md-6">
        <div class="card border-0 shadow-sm rounded-4 text-center p-4 h-100">
          <div class="mx-auto mb-3 rounded-circle bg-info-subtle text-info-emphasis d-flex align-items-center justify-content-center" style="width: 90px; height: 90px; font-size: 2.5rem;">
            <i class="bi bi-person-workspace"></i>
          </div>
          <h5 class="fw-bold mb-1">Anil Verma</h5>
          <div class="text-info-emphasis small fw-semibold mb-2">M.Sc. MLT, Senior Technical Officer</div>
          <div class="text-muted small mb-3">Laboratory Operations & Quality Manager</div>
          <p class="text-secondary small">
            Specializing in high-throughput automated biochemistry and immunoassay calibration, overseeing internal quality control charts and automated robotic pipetting systems.
          </p>
        </div>
      </div>

      <div class="col-lg-4 col-md-6">
        <div class="card border-0 shadow-sm rounded-4 text-center p-4 h-100">
          <div class="mx-auto mb-3 rounded-circle bg-success-subtle text-success d-flex align-items-center justify-content-center" style="width: 90px; height: 90px; font-size: 2.5rem;">
            <i class="bi bi-shield-lock"></i>
          </div>
          <h5 class="fw-bold mb-1">Dr. Sunita Deshpande</h5>
          <div class="text-success small fw-semibold mb-2">MBBS, MD (Microbiology)</div>
          <div class="text-muted small mb-3">Consultant Microbiologist & Infection Control</div>
          <p class="text-secondary small">
            Directs bacterial culture sensitivity, automated serology screening, and molecular infectious disease testing adhering to CLSI interpretive guidelines.
          </p>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Call to Action Banner -->
<section class="py-5 bg-primary text-white text-center">
  <div class="container">
    <h2 class="text-white fw-bold mb-3">Need Diagnostic Testing or Health Checkups?</h2>
    <p class="text-white-50 max-w-700 mx-auto mb-4">
      Book your home collection appointment today or walk in to our state-of-the-art facility.
    </p>
    <div class="d-flex justify-content-center gap-3">
      <a href="<?= BASE_URL ?>/booking.php" class="btn btn-light text-primary fw-bold px-4 py-2">
        <i class="bi bi-calendar-check me-1"></i> Quick Test Booking
      </a>
      <a href="<?= BASE_URL ?>/contact.php" class="btn btn-outline-light px-4 py-2">
        <i class="bi bi-geo-alt me-1"></i> Visit Laboratory
      </a>
    </div>
  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

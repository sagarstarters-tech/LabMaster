<?php
/**
 * LabMaster - Public Homepage
 * Dynamic, clinical-grade medical website
 */

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = Settings::get('lab_name', 'LabMaster Diagnostic & Research Center') . ' | Precision Pathology & Automated Diagnostics';
$pageDesc  = 'ISO 15189 compliant and NABL-aligned pathology laboratory offering advanced clinical hematology, biochemistry, molecular diagnostics, home collection, and instant WhatsApp reports.';
$activePage = 'home';

// Fetch Categories with test count
$categories = Database::fetchAll(
    "SELECT c.*, COUNT(t.id) as test_count 
     FROM `test_categories` c 
     LEFT JOIN `tests` t ON c.id = t.category_id AND t.is_active = 1 
     GROUP BY c.id 
     ORDER BY c.name ASC"
);

// Fetch Popular / Featured Tests
$popularTests = Database::fetchAll(
    "SELECT t.*, c.name as category_name 
     FROM `tests` t 
     JOIN `test_categories` c ON t.category_id = c.id 
     WHERE t.is_active = 1 
     ORDER BY t.price DESC 
     LIMIT 6"
);

// Lab Metrics
$totalTestsCount = Database::fetchColumn("SELECT COUNT(*) FROM `tests` WHERE `is_active` = 1") ?: 120;
$totalPatientsCount = Database::fetchColumn("SELECT COUNT(*) FROM `patients`") ?: 1500;
$totalReportsDelivered = Database::fetchColumn("SELECT COUNT(*) FROM `reports` WHERE `report_status` = 'Approved'") ?: 2400;

require_once __DIR__ . '/includes/header.php';
?>

<!-- Hero Section -->
<section class="hero-section">
  <div class="container">
    <div class="row align-items-center g-5">
      <div class="col-lg-7">
        <div class="hero-badge">
          <i class="bi bi-patch-check-fill text-primary"></i>
          <span><?= e(Settings::get('accreditation_text', 'NABL Accredited & ISO 15189:2022 Certified')) ?></span>
        </div>
        <h1 class="hero-title">
          Accurate Pathology Results for <span>Better Clinical Decisions</span>
        </h1>
        <p class="hero-lead">
          Experience cutting-edge laboratory medicine with automated robotic analyzers, barcoded pre-analytical safety, and board-certified pathologist oversight for unmatched diagnostic accuracy.
        </p>
        
        <div class="d-flex flex-wrap gap-3 mb-4">
          <a href="<?= BASE_URL ?>/booking.php" class="btn btn-primary-lm btn-lg">
            <i class="bi bi-calendar-check me-2"></i> Book Home Sample Collection
          </a>
          <a href="<?= BASE_URL ?>/download-report.php" class="btn btn-outline-lm btn-lg">
            <i class="bi bi-file-earmark-medical me-2"></i> Download Test Report
          </a>
          <a href="<?= BASE_URL ?>/price-list.php" class="btn btn-light border btn-lg">
            <i class="bi bi-tag me-1"></i> View Price List
          </a>
        </div>

        <div class="row g-4 pt-3 border-top">
          <div class="col-4">
            <div class="fw-bold fs-3 text-primary"><?= number_format((int)$totalTestsCount) ?>+</div>
            <div class="text-muted small">Validated Pathology Tests</div>
          </div>
          <div class="col-4">
            <div class="fw-bold fs-3 text-primary">< 6 Hrs</div>
            <div class="text-muted small">Routine Turnaround Time</div>
          </div>
          <div class="col-4">
            <div class="fw-bold fs-3 text-primary">100%</div>
            <div class="text-muted small">Barcoded Sample Safety</div>
          </div>
        </div>
      </div>

      <div class="col-lg-5">
        <div class="hero-card">
          <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom">
            <h5 class="mb-0 fw-bold"><i class="bi bi-search text-primary me-2"></i>Quick Test Finder</h5>
            <span class="badge bg-primary-subtle text-primary">Live Database</span>
          </div>
          <p class="text-muted small mb-3">Search from over <?= (int)$totalTestsCount ?> clinical pathology parameters, packages, and health checkups.</p>
          
          <form action="<?= BASE_URL ?>/tests.php" method="GET" class="mb-3">
            <div class="input-group">
              <input type="text" name="q" class="form-control" placeholder="e.g. CBC, Lipid, Thyroid, HbA1c..." required>
              <button class="btn btn-primary" type="submit"><i class="bi bi-search"></i></button>
            </div>
          </form>

          <div class="p-3 bg-light rounded-3 mb-3">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <span class="fw-semibold small"><i class="bi bi-house-door text-success me-1"></i> Home Collection Available</span>
              <span class="badge bg-success">Active Today</span>
            </div>
            <p class="text-muted small mb-0">Certified phlebotomists collect your sample safely at your doorstep with cold-chain maintenance.</p>
          </div>

          <div class="d-grid gap-2">
            <a href="<?= BASE_URL ?>/booking.php" class="btn btn-primary-lm">
              <i class="bi bi-send-check me-1"></i> Instant 2-Minute Booking
            </a>
            <a href="<?= BASE_URL ?>/verify-report.php" class="btn btn-sm btn-outline-secondary">
              <i class="bi bi-shield-lock me-1"></i> Verify QR Report Authenticity
            </a>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Pathology Disciplines / Categories -->
<section class="py-5 bg-white">
  <div class="container">
    <div class="text-center max-w-700 mx-auto mb-5">
      <span class="badge bg-primary-subtle text-primary fw-semibold px-3 py-2 rounded-pill mb-2">Clinical Departments</span>
      <h2 class="section-title">Comprehensive Pathology Disciplines</h2>
      <p class="text-muted">From routine health screenings to esoteric clinical biochemistry, our laboratory is equipped with international gold-standard diagnostics.</p>
    </div>

    <div class="row g-4">
      <?php 
      $icons = [
          'Hematology' => 'bi-droplet-half',
          'Biochemistry' => 'bi-activity',
          'Clinical Pathology' => 'bi-prescription2',
          'Serology' => 'bi-shield-plus',
          'Immunology' => 'bi-virus',
          'Microbiology' => 'bi-bug',
          'Hormone Assays' => 'bi-heart-pulse'
      ];
      foreach ($categories as $cat): 
          $icon = $icons[$cat['name']] ?? 'bi-clipboard2-pulse';
      ?>
      <div class="col-lg-3 col-md-4 col-sm-6">
        <div class="feature-card text-center">
          <div class="feature-icon-box mx-auto">
            <i class="bi <?= $icon ?>"></i>
          </div>
          <h5 class="fw-bold mb-2"><?= e($cat['name']) ?></h5>
          <p class="text-muted small mb-3"><?= e($cat['description'] ?? 'Automated precision diagnostics.') ?></p>
          <div class="d-flex align-items-center justify-content-between pt-2 border-top">
            <span class="badge bg-light text-dark border"><?= (int)$cat['test_count'] ?> Tests</span>
            <a href="<?= BASE_URL ?>/tests.php?cat=<?= (int)$cat['id'] ?>" class="text-primary small fw-semibold text-decoration-none">
              Explore <i class="bi bi-arrow-right"></i>
            </a>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- Popular Tests & Health Packages -->
<section class="py-5 bg-light">
  <div class="container">
    <div class="d-flex flex-wrap justify-content-between align-items-end mb-4">
      <div>
        <span class="badge bg-info-subtle text-info-emphasis fw-semibold px-3 py-2 rounded-pill mb-2">Most Prescribed</span>
        <h2 class="section-title mb-1">Popular Laboratory Tests & Profiles</h2>
        <p class="text-muted mb-0">Transparent pricing with fully automated reference interval reporting.</p>
      </div>
      <a href="<?= BASE_URL ?>/tests.php" class="btn btn-outline-lm mt-3 mt-md-0">
        Browse All <?= (int)$totalTestsCount ?> Tests <i class="bi bi-arrow-right ms-1"></i>
      </a>
    </div>

    <div class="row g-4">
      <?php foreach ($popularTests as $test): ?>
      <div class="col-lg-4 col-md-6">
        <div class="test-card h-100 d-flex flex-column justify-content-between">
          <div>
            <div class="d-flex justify-content-between align-items-start mb-2">
              <span class="badge bg-primary-subtle text-primary"><?= e($test['category_name']) ?></span>
              <span class="test-code"><?= e($test['test_code']) ?></span>
            </div>
            <h5 class="fw-bold mb-2"><?= e($test['test_name']) ?></h5>
            <div class="text-muted small mb-3">
              <i class="bi bi-eyedropper text-secondary me-1"></i> Specimen: <strong><?= e($test['specimen_type']) ?></strong>
              <span class="mx-2">•</span>
              <i class="bi bi-clock-history text-secondary me-1"></i> TAT: <strong><?= e($test['turnaround_time'] ?? 'Same Day') ?></strong>
            </div>
            <?php if (!empty($test['instructions'])): ?>
              <p class="small text-secondary mb-3 bg-light p-2 rounded">
                <i class="bi bi-info-circle text-primary me-1"></i> <?= e($test['instructions']) ?>
              </p>
            <?php endif; ?>
          </div>

          <div class="pt-3 border-top d-flex align-items-center justify-content-between">
            <div>
              <span class="text-muted small">Standard Fee</span>
              <div class="fs-4 fw-bold text-dark"><?= format_currency((float)$test['price']) ?></div>
            </div>
            <a href="<?= BASE_URL ?>/booking.php?test_id=<?= (int)$test['id'] ?>" class="btn btn-primary-lm btn-sm">
              <i class="bi bi-cart-plus me-1"></i> Book Now
            </a>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- Home Collection Feature Section -->
<section class="py-5 bg-white">
  <div class="container">
    <div class="row align-items-center g-5">
      <div class="col-lg-6">
        <span class="badge bg-success-subtle text-success fw-semibold px-3 py-2 rounded-pill mb-2">Doorstep Service</span>
        <h2 class="section-title">Hassle-Free Home Sample Collection</h2>
        <p class="text-muted mb-4">
          Skip hospital waiting queues. Our DMLT certified phlebotomists follow strict aseptic vacuum tube protocols and cold-chain transport right from your home or office.
        </p>

        <div class="d-flex gap-3 mb-4">
          <div class="feature-icon-box flex-shrink-0">
            <i class="bi bi-shield-check"></i>
          </div>
          <div>
            <h5 class="fw-bold mb-1">100% Sterile & Vacuum Tubes</h5>
            <p class="text-muted small mb-0">Vacuum evacuated BD Vacutainers with unique barcoding to eliminate pre-analytical contamination or misidentification.</p>
          </div>
        </div>

        <div class="d-flex gap-3 mb-4">
          <div class="feature-icon-box flex-shrink-0">
            <i class="bi bi-snow"></i>
          </div>
          <div>
            <h5 class="fw-bold mb-1">Temperature-Controlled Cold Chain</h5>
            <p class="text-muted small mb-0">Samples are immediately placed in calibrated gel icebox carriers preserving enzyme integrity until accessioning.</p>
          </div>
        </div>

        <div class="d-flex gap-3 mb-4">
          <div class="feature-icon-box flex-shrink-0">
            <i class="bi bi-whatsapp"></i>
          </div>
          <div>
            <h5 class="fw-bold mb-1">Instant Digital WhatsApp Delivery</h5>
            <p class="text-muted small mb-0">As soon as the report is signed off by our consultant pathologist, an encrypted PDF is transmitted directly to your WhatsApp.</p>
          </div>
        </div>

        <a href="<?= BASE_URL ?>/booking.php" class="btn btn-primary-lm">
          <i class="bi bi-calendar-check me-2"></i> Schedule Home Collection
        </a>
      </div>

      <div class="col-lg-6">
        <div class="p-4 p-md-5 rounded-4 bg-light border shadow-sm">
          <h4 class="fw-bold mb-4 text-center">How It Works in 4 Simple Steps</h4>
          
          <div class="d-flex align-items-start gap-3 mb-4">
            <div class="badge bg-primary rounded-circle p-3 fs-6">1</div>
            <div>
              <h6 class="fw-bold mb-1">Select Tests & Schedule</h6>
              <p class="text-muted small mb-0">Choose your required tests online and pick your preferred morning or evening time slot.</p>
            </div>
          </div>

          <div class="d-flex align-items-start gap-3 mb-4">
            <div class="badge bg-primary rounded-circle p-3 fs-6">2</div>
            <div>
              <h6 class="fw-bold mb-1">Phlebotomist Arrives</h6>
              <p class="text-muted small mb-0">Our trained professional arrives with sterile single-use equipment and draws the sample painlessly.</p>
            </div>
          </div>

          <div class="d-flex align-items-start gap-3 mb-4">
            <div class="badge bg-primary rounded-circle p-3 fs-6">3</div>
            <div>
              <h6 class="fw-bold mb-1">Automated Analysis & Verification</h6>
              <p class="text-muted small mb-0">Samples are processed on 5-part hematology and automated dry-chemistry analyzers under pathologist review.</p>
            </div>
          </div>

          <div class="d-flex align-items-start gap-3">
            <div class="badge bg-primary rounded-circle p-3 fs-6">4</div>
            <div>
              <h6 class="fw-bold mb-1">Receive Verified PDF Report</h6>
              <p class="text-muted small mb-0">Download from our portal, scan the QR code to verify authenticity, or view on WhatsApp.</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Why Choose LabMaster & Quality Standards -->
<section class="py-5 bg-dark text-white">
  <div class="container">
    <div class="text-center max-w-700 mx-auto mb-5">
      <span class="badge bg-primary px-3 py-2 rounded-pill mb-2">Quality & Precision</span>
      <h2 class="text-white section-title">Uncompromising Quality Assurance</h2>
      <p class="text-light-emphasis">We operate under stringent ISO 15189 laboratory standards with continuous Internal Quality Control (IQC) and External Quality Assurance Services (EQAS).</p>
    </div>

    <div class="row g-4 text-dark">
      <div class="col-md-4">
        <div class="p-4 bg-white rounded-3 h-100">
          <div class="text-primary fs-2 mb-3"><i class="bi bi-cpu"></i></div>
          <h5 class="fw-bold">Robotic Automation</h5>
          <p class="text-muted small mb-0">High-throughput automated analyzers minimize manual pipetting errors and deliver reproducible parameters with strict CV thresholds.</p>
        </div>
      </div>
      <div class="col-md-4">
        <div class="p-4 bg-white rounded-3 h-100">
          <div class="text-primary fs-2 mb-3"><i class="bi bi-qr-code-scan"></i></div>
          <h5 class="fw-bold">Barcoded Pre-Analytics</h5>
          <p class="text-muted small mb-0">Every specimen tube is tracked with unique GS1-compliant barcodes from phlebotomy to report archiving, preventing specimen mix-ups.</p>
        </div>
      </div>
      <div class="col-md-4">
        <div class="p-4 bg-white rounded-3 h-100">
          <div class="text-primary fs-2 mb-3"><i class="bi bi-file-earmark-check"></i></div>
          <h5 class="fw-bold">Pathologist Sign-Off</h5>
          <p class="text-muted small mb-0">Every abnormal result is correlated clinically with peripheral smear examination or serum repeat runs before final digital approval.</p>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Testimonials & FAQs Preview -->
<section class="py-5 bg-light">
  <div class="container">
    <div class="row g-5">
      <!-- Testimonials -->
      <div class="col-lg-6">
        <span class="badge bg-primary-subtle text-primary fw-semibold px-3 py-2 rounded-pill mb-2">Patient Experiences</span>
        <h3 class="fw-bold mb-4">Trusted by Over 25,000+ Patients</h3>
        
        <div class="p-4 bg-white rounded-4 border shadow-sm mb-3">
          <div class="text-warning mb-2">
            <i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i>
          </div>
          <p class="text-secondary small fst-italic mb-3">
            "The home collection service was prompt and completely painless. I received my CBC and Thyroid reports on WhatsApp within 4 hours. The QR verification feature gave my consulting physician complete confidence."
          </p>
          <div class="d-flex align-items-center gap-3">
            <div class="rounded-circle bg-primary-subtle text-primary fw-bold p-2 px-3">RS</div>
            <div>
              <div class="fw-bold text-dark">Rajesh Sharma</div>
              <div class="text-muted small">Executive Health Checkup</div>
            </div>
          </div>
        </div>

        <div class="p-4 bg-white rounded-4 border shadow-sm">
          <div class="text-warning mb-2">
            <i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i>
          </div>
          <p class="text-secondary small fst-italic mb-3">
            "Extremely clean laboratory, polite reception staff, and well-organized reports with clear biological reference intervals and high/low flags. LabMaster is definitely our family's trusted pathology center."
          </p>
          <div class="d-flex align-items-center gap-3">
            <div class="rounded-circle bg-success-subtle text-success fw-bold p-2 px-3">PK</div>
            <div>
              <div class="fw-bold text-dark">Pooja Kulkarni</div>
              <div class="text-muted small">Diabetes Monitoring Panel</div>
            </div>
          </div>
        </div>
      </div>

      <!-- Quick FAQs -->
      <div class="col-lg-6">
        <span class="badge bg-info-subtle text-info fw-semibold px-3 py-2 rounded-pill mb-2">Got Questions?</span>
        <h3 class="fw-bold mb-4">Frequently Asked Questions</h3>

        <div class="accordion" id="homeFaq">
          <div class="accordion-item border rounded-3 mb-2 overflow-hidden">
            <h2 class="accordion-header">
              <button class="accordion-button fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#hfaq1">
                Do I need to fast before my blood test?
              </button>
            </h2>
            <div id="hfaq1" class="accordion-collapse collapse show" data-bs-parent="#homeFaq">
              <div class="accordion-body small text-muted">
                Tests such as Fasting Blood Sugar (FBS), Lipid Profile, and Comprehensive Metabolic Panels require 8 to 12 hours of overnight water-only fasting. Routine CBC and HbA1c do not require fasting.
              </div>
            </div>
          </div>

          <div class="accordion-item border rounded-3 mb-2 overflow-hidden">
            <h2 class="accordion-header">
              <button class="accordion-button collapsed fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#hfaq2">
                How quickly will I receive my pathology report?
              </button>
            </h2>
            <div id="hfaq2" class="accordion-collapse collapse" data-bs-parent="#homeFaq">
              <div class="accordion-body small text-muted">
                Most routine hematology and biochemistry reports are released within 4 to 6 hours after sample processing. Specialized hormone assays and cultures take 24 to 48 hours.
              </div>
            </div>
          </div>

          <div class="accordion-item border rounded-3 mb-2 overflow-hidden">
            <h2 class="accordion-header">
              <button class="accordion-button collapsed fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#hfaq3">
                How do I download my approved report online?
              </button>
            </h2>
            <div id="hfaq3" class="accordion-collapse collapse" data-bs-parent="#homeFaq">
              <div class="accordion-body small text-muted">
                Navigate to our <strong>Download Report</strong> page and enter your registered 10-digit mobile number along with either your unique Patient ID or Accession Number. You will instantly receive a digitally signed PDF.
              </div>
            </div>
          </div>
        </div>

        <div class="mt-4 text-center text-lg-start">
          <a href="<?= BASE_URL ?>/faqs.php" class="text-primary fw-semibold text-decoration-none">
            View All Clinical FAQs <i class="bi bi-arrow-right"></i>
          </a>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Location & Hours Section -->
<section class="py-5 bg-white border-top">
  <div class="container">
    <div class="row g-4 align-items-center">
      <div class="col-lg-5">
        <span class="badge bg-primary-subtle text-primary fw-semibold px-3 py-2 rounded-pill mb-2">Visit Our Center</span>
        <h3 class="fw-bold mb-3">Convenient Central Location</h3>
        <p class="text-muted small mb-4">
          Equipped with comfortable phlebotomy lounges, senior citizen priority counters, and dedicated pediatric sample collection facilities.
        </p>

        <div class="mb-3 d-flex gap-3">
          <i class="bi bi-geo-alt-fill text-primary fs-4"></i>
          <div>
            <div class="fw-bold">Address</div>
            <div class="text-muted small"><?= e(Settings::get('lab_address', 'Plot No. 42, Healthcare Square, Central Avenue, Metro City, 400001, India')) ?></div>
          </div>
        </div>

        <div class="mb-3 d-flex gap-3">
          <i class="bi bi-clock-fill text-primary fs-4"></i>
          <div>
            <div class="fw-bold">Operating Hours</div>
            <div class="text-muted small"><?= e(Settings::get('working_hours', 'Monday - Saturday: 07:00 AM - 09:00 PM | Sunday: 08:00 AM - 01:00 PM')) ?></div>
          </div>
        </div>

        <div class="mb-4 d-flex gap-3">
          <i class="bi bi-telephone-fill text-primary fs-4"></i>
          <div>
            <div class="fw-bold">Helpline & WhatsApp Support</div>
            <div class="text-muted small"><?= e(Settings::get('lab_phone', '+91 98765 43210')) ?></div>
          </div>
        </div>

        <a href="<?= BASE_URL ?>/contact.php" class="btn btn-outline-lm">
          <i class="bi bi-map me-1"></i> View Detailed Map & Contact Form
        </a>
      </div>

      <div class="col-lg-7">
        <div class="rounded-4 overflow-hidden border shadow-sm ratio ratio-16x9">
          <iframe 
            src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3771.8028723321557!2d72.8561!3d19.027!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x0%3A0x0!2zMTnCsDAxJzM3LjIiTiA3MsKwNTEnMjIuMCJF!5e0!3m2!1sen!2sin!4v1620000000000!5m2!1sen!2sin" 
            style="border:0;" 
            allowfullscreen="" 
            loading="lazy" 
            referrerpolicy="no-referrer-when-downgrade">
          </iframe>
        </div>
      </div>
    </div>
  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

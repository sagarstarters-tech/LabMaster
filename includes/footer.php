<?php
/**
 * LabMaster - Public Website Footer & WhatsApp Live Chat
 */

declare(strict_types=1);

require_once __DIR__ . '/whatsapp_service.php';

$labName = Settings::get('lab_name', 'LabMaster Diagnostic & Research Center');
$labTagline = Settings::get('lab_tagline', 'Precision Diagnostics & Clinical Pathology Excellence');
$labPhone = Settings::get('lab_phone', '+91 98765 43210');
$labAltPhone = Settings::get('lab_alt_phone', '+91 91234 56789');
$labEmail = Settings::get('lab_email', 'info@labmasterdiagnostics.com');
$labAddress = Settings::get('lab_address', 'Plot No. 42, Healthcare Square, Central Avenue, Metro City, 400001, India');
$accreditation = Settings::get('accreditation_text', 'ISO 15189:2022 Compliant & NABL Accredited Reference Laboratory');

$waEnabled = (int)Settings::get('whatsapp_floating_enabled', '1') === 1;
$waUrl = WhatsAppService::buildChatUrl();
?>
  <!-- Footer -->
  <footer class="footer-main">
    <div class="container">
      <div class="row g-4">
        <!-- Lab Profile -->
        <div class="col-lg-4 col-md-6">
          <div class="d-flex align-items-center gap-2 mb-3">
            <i class="bi bi-heart-pulse-fill text-info fs-2"></i>
            <span class="brand-font fs-4 text-white">Lab<span class="text-info">Master</span></span>
          </div>
          <p class="text-light-emphasis small mb-3">
            <?= e($labTagline) ?>. Powered by fully automated multi-parameter analyzers and verified by certified clinical pathologists.
          </p>
          <div class="p-2 rounded bg-dark border border-secondary mb-3 small">
            <i class="bi bi-award-fill text-warning me-1"></i>
            <span class="text-light"><?= e($accreditation) ?></span>
          </div>
          <div class="d-flex gap-2">
            <a href="#" class="btn btn-sm btn-outline-secondary text-light rounded-circle"><i class="bi bi-facebook"></i></a>
            <a href="#" class="btn btn-sm btn-outline-secondary text-light rounded-circle"><i class="bi bi-twitter-x"></i></a>
            <a href="#" class="btn btn-sm btn-outline-secondary text-light rounded-circle"><i class="bi bi-linkedin"></i></a>
            <a href="#" class="btn btn-sm btn-outline-secondary text-light rounded-circle"><i class="bi bi-instagram"></i></a>
          </div>
        </div>

        <!-- Quick Links -->
        <div class="col-lg-2 col-md-6">
          <h5 class="footer-title">Quick Links</h5>
          <ul class="footer-links">
            <li><a href="<?= BASE_URL ?>/index.php">Home</a></li>
            <li><a href="<?= BASE_URL ?>/about.php">About Us</a></li>
            <li><a href="<?= BASE_URL ?>/services.php">Services</a></li>
            <li><a href="<?= BASE_URL ?>/tests.php">Pathology Tests</a></li>
            <li><a href="<?= BASE_URL ?>/price-list.php">Test Price List</a></li>
            <li><a href="<?= BASE_URL ?>/booking.php">Quick Booking</a></li>
            <li><a href="<?= BASE_URL ?>/download-report.php">Download Report</a></li>
          </ul>
        </div>

        <!-- Patient Guidance -->
        <div class="col-lg-3 col-md-6">
          <h5 class="footer-title">Patient Resources</h5>
          <ul class="footer-links">
            <li><a href="<?= BASE_URL ?>/patient-instructions.php">Preparation & Fasting Guide</a></li>
            <li><a href="<?= BASE_URL ?>/sample-collection.php">Sample Collection Protocol</a></li>
            <li><a href="<?= BASE_URL ?>/verify-report.php">Online QR Report Verification</a></li>
            <li><a href="<?= PORTAL_URL ?>/login.php">Patient Health Portal</a></li>
            <li><a href="<?= BASE_URL ?>/faqs.php">Frequently Asked Questions</a></li>
            <li><a href="<?= BASE_URL ?>/privacy-policy.php">Medical Privacy Policy</a></li>
            <li><a href="<?= BASE_URL ?>/terms-conditions.php">Terms of Clinical Service</a></li>
          </ul>
        </div>

        <!-- Contact & Hours -->
        <div class="col-lg-3 col-md-6">
          <h5 class="footer-title">Laboratory Access</h5>
          <ul class="footer-links small">
            <li class="d-flex gap-2 text-light-emphasis">
              <i class="bi bi-geo-alt-fill text-info mt-1"></i>
              <span><?= e($labAddress) ?></span>
            </li>
            <li class="d-flex gap-2 text-light-emphasis">
              <i class="bi bi-telephone-fill text-info mt-1"></i>
              <span><?= e($labPhone) ?><br><?= e($labAltPhone) ?></span>
            </li>
            <li class="d-flex gap-2 text-light-emphasis">
              <i class="bi bi-envelope-fill text-info mt-1"></i>
              <span><?= e($labEmail) ?></span>
            </li>
            <li class="d-flex gap-2 text-light-emphasis">
              <i class="bi bi-clock-fill text-info mt-1"></i>
              <span><?= e(Settings::get('working_hours', 'Mon - Sat: 7am - 9pm')) ?></span>
            </li>
          </ul>
        </div>
      </div>

      <hr class="border-secondary my-4">

      <div class="d-flex flex-wrap justify-content-between align-items-center small text-light-emphasis">
        <div>
          &copy; <?= date('Y') ?> <strong><?= e($labName) ?></strong>. All rights reserved.
        </div>
        <div class="d-flex gap-3">
          <a href="<?= BASE_URL ?>/privacy-policy.php" class="text-light-emphasis text-decoration-none">Privacy</a>
          <a href="<?= BASE_URL ?>/terms-conditions.php" class="text-light-emphasis text-decoration-none">Terms</a>
          <a href="<?= ADMIN_URL ?>/login.php" class="text-info text-decoration-none fw-semibold">Staff Portal</a>
        </div>
      </div>
    </div>
  </footer>

  <?php if ($waEnabled): ?>
  <!-- Floating WhatsApp Live Chat Widget -->
  <a href="<?= e($waUrl) ?>" target="_blank" rel="noopener noreferrer" class="whatsapp-float" aria-label="Chat with LabMaster on WhatsApp">
    <i class="bi bi-whatsapp"></i>
    <span class="whatsapp-tooltip d-none d-md-block">Need help? Chat on WhatsApp</span>
  </a>
  <?php endif; ?>

  <!-- Bootstrap 5.3 Bundle JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <!-- Public Main JS -->
  <script src="<?= ASSETS_URL ?>/js/main.js?v=<?= APP_VERSION ?>"></script>
</body>
</html>

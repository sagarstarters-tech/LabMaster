<?php
/**
 * LabMaster - Secure Patient Report Download
 * Retrieve verified pathology reports using Registered Mobile + (Patient ID or Report No. or Accession No.)
 */

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Download Test Report | ' . Settings::get('lab_name', 'LabMaster Diagnostic & Research Center');
$pageDesc  = 'Securely access and download your verified pathology reports and clinical test results online.';
$activePage = 'download-report';

$matchedReports = [];
$searched = false;
$errorMessage = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $phone = trim($_POST['phone'] ?? '');
    $identifier = trim($_POST['identifier'] ?? '');

    if (empty($phone) || empty($identifier)) {
        $errorMessage = 'Please enter both your registered 10-digit mobile number and your Patient ID, Accession, or Report Number.';
    } else {
        $searched = true;

        // Query database: check by patient_id, report_number, or accession_number with phone verification
        $sql = "SELECT r.*, p.patient_id as patient_code, p.full_name as patient_name, p.gender, p.age,
                       o.accession_number, o.created_at as order_date,
                       u.full_name as approved_by_name, u.qualification as approved_by_qualification
                FROM `reports` r
                JOIN `patients` p ON r.patient_id = p.id
                JOIN `test_orders` o ON r.order_id = o.id
                LEFT JOIN `users` u ON r.approved_by = u.id
                WHERE p.phone = ? 
                  AND (p.patient_id = ? OR r.report_number = ? OR o.accession_number = ?)
                ORDER BY r.id DESC";

        $matchedReports = Database::fetchAll($sql, [$phone, $identifier, $identifier, $identifier]);

        if (empty($matchedReports)) {
            // Check if patient exists but report is not yet generated/approved
            $patientCheck = Database::fetch("SELECT `id`, `full_name` FROM `patients` WHERE `phone` = ? AND `patient_id` = ?", [$phone, $identifier]);
            if ($patientCheck) {
                $pendingOrders = Database::fetchAll("SELECT * FROM `test_orders` WHERE `patient_id` = ? ORDER BY `id` DESC LIMIT 1", [$patientCheck['id']]);
                if (!empty($pendingOrders)) {
                    $errorMessage = "Your sample is currently undergoing automated analysis and pathologist verification. Routine reports are approved within 4-6 hours. Please check back shortly or call our lab desk.";
                } else {
                    $errorMessage = "No diagnostic orders found for this Patient ID.";
                }
            } else {
                $errorMessage = "No laboratory record found matching this Mobile Number and Identifier combination. Please check your billing receipt.";
            }
        } else {
            // Log access attempt for each matched report
            $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
            foreach ($matchedReports as $rep) {
                Database::insert(
                    "INSERT INTO `report_access_logs` (`report_id`, `access_method`, `ip_address`, `user_agent`, `mobile_verified`)
                     VALUES (?, 'Web Portal Download Search', ?, ?, ?)",
                    [$rep['id'], $ip, $ua, $phone]
                );
            }
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<!-- Header Banner -->
<section class="py-5 bg-light border-bottom">
  <div class="container text-center">
    <span class="badge bg-primary-subtle text-primary fw-semibold px-3 py-2 rounded-pill mb-2">Secure Patient Portal</span>
    <h1 class="fw-bold mb-3">Download Diagnostic Pathology Report</h1>
    <p class="text-muted max-w-700 mx-auto">
      Access your digitally signed medical reports 24/7. Authenticated with dual-factor demographic verification for complete clinical confidentiality.
    </p>
  </div>
</section>

<section class="py-5 bg-white">
  <div class="container">
    
    <div class="row justify-content-center">
      <div class="col-lg-7">
        
        <!-- Search Card -->
        <div class="card border rounded-4 p-4 p-md-5 shadow-sm mb-5">
          <div class="d-flex align-items-center gap-3 mb-4">
            <div class="feature-icon-box mb-0">
              <i class="bi bi-shield-lock"></i>
            </div>
            <div>
              <h4 class="fw-bold mb-0">Patient Report Retrieval</h4>
              <p class="text-muted small mb-0">Enter the details printed on your sample collection receipt.</p>
            </div>
          </div>

          <?php if (!empty($errorMessage)): ?>
            <div class="alert alert-warning border-0 shadow-sm d-flex align-items-start gap-2 mb-4">
              <i class="bi bi-exclamation-triangle-fill fs-5 flex-shrink-0 text-warning"></i>
              <div><?= e($errorMessage) ?></div>
            </div>
          <?php endif; ?>

          <form action="<?= BASE_URL ?>/download-report.php" method="POST">
            <?= csrf_field() ?>

            <div class="mb-3">
              <label class="form-label fw-semibold">Registered Mobile Number <span class="text-danger">*</span></label>
              <div class="input-group">
                <span class="input-group-text bg-white"><i class="bi bi-telephone text-muted"></i></span>
                <input type="tel" name="phone" class="form-control" placeholder="10-digit registered mobile number" value="<?= e($_POST['phone'] ?? '') ?>" required>
              </div>
              <div class="form-text">The mobile number given during sample booking or billing.</div>
            </div>

            <div class="mb-4">
              <label class="form-label fw-semibold">Patient ID, Accession No, or Report No <span class="text-danger">*</span></label>
              <div class="input-group">
                <span class="input-group-text bg-white"><i class="bi bi-upc-scan text-muted"></i></span>
                <input type="text" name="identifier" class="form-control" placeholder="e.g. LM-P-2026-0001 or LM-ACC-2026-0001 or LM-REP-2026-0001" value="<?= e($_POST['identifier'] ?? '') ?>" required>
              </div>
              <div class="form-text">Found at the top of your payment receipt or barcode label.</div>
            </div>

            <button type="submit" class="btn btn-primary-lm btn-lg w-100">
              <i class="bi bi-search me-1"></i> Retrieve My Report
            </button>
          </form>

          <div class="mt-4 pt-3 border-top text-center">
            <span class="text-muted small">Are you a registered frequent patient?</span>
            <a href="<?= PORTAL_URL ?>/login.php" class="text-primary small fw-semibold text-decoration-none ms-1">
              Log in to Patient Health Portal <i class="bi bi-arrow-right"></i>
            </a>
          </div>
        </div>

        <!-- Search Results -->
        <?php if ($searched && !empty($matchedReports)): ?>
          <h4 class="fw-bold mb-3"><i class="bi bi-file-earmark-medical text-primary me-2"></i>Matched Diagnostic Reports</h4>
          
          <div class="d-flex flex-column gap-3 mb-5">
            <?php foreach ($matchedReports as $rep): 
                $isApproved = ($rep['report_status'] === 'Approved');
                $isReleased = (int)($rep['is_released_for_patient'] ?? 0) === 1;
            ?>
              <div class="card border rounded-4 shadow-sm p-4">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
                  <div>
                    <span class="badge bg-primary-subtle text-primary mb-1">Report #<?= e($rep['report_number']) ?></span>
                    <h5 class="fw-bold mb-0 text-dark"><?= e($rep['patient_name']) ?> <span class="text-muted small">(<?= e($rep['gender']) ?>, <?= (int)$rep['age'] ?> yrs)</span></h5>
                    <div class="text-muted small">
                      Patient ID: <strong class="text-dark font-monospace"><?= e($rep['patient_code']) ?></strong> | 
                      Order: <strong class="text-dark font-monospace"><?= e($rep['accession_number']) ?></strong>
                    </div>
                  </div>

                  <div>
                    <?php if ($isApproved && $isReleased): ?>
                      <span class="badge bg-success-subtle text-success px-3 py-2"><i class="bi bi-check-circle me-1"></i> Approved & Released</span>
                    <?php elseif ($isApproved): ?>
                      <span class="badge bg-info-subtle text-info-emphasis px-3 py-2"><i class="bi bi-clock me-1"></i> Under Release Review</span>
                    <?php else: ?>
                      <span class="badge bg-warning-subtle text-warning-emphasis px-3 py-2"><i class="bi bi-hourglass-split me-1"></i> <?= e($rep['report_status']) ?></span>
                    <?php endif; ?>
                  </div>
                </div>

                <div class="row g-2 mb-3 small bg-light p-3 rounded-3">
                  <div class="col-sm-6">
                    <span class="text-muted">Approval Date:</span>
                    <div class="fw-semibold text-dark"><?= $rep['approved_at'] ? date('d M Y, h:i A', strtotime($rep['approved_at'])) : 'Pending' ?></div>
                  </div>
                  <div class="col-sm-6">
                    <span class="text-muted">Consultant Pathologist:</span>
                    <div class="fw-semibold text-dark"><?= e($rep['approved_by_name'] ?? 'Medical Laboratory Director') ?> <?= !empty($rep['approved_by_qualification']) ? '('.e($rep['approved_by_qualification']).')' : '' ?></div>
                  </div>
                </div>

                <div class="d-flex flex-wrap gap-2 justify-content-end pt-2 border-top">
                  <?php if ($isApproved && $isReleased): ?>
                    <a href="<?= ADMIN_URL ?>/reports/pdf.php?token=<?= urlencode($rep['verification_token']) ?>" target="_blank" class="btn btn-primary-lm btn-sm">
                      <i class="bi bi-file-earmark-pdf me-1"></i> Download PDF Report
                    </a>
                    <a href="<?= BASE_URL ?>/verify-report.php?token=<?= urlencode($rep['verification_token']) ?>" class="btn btn-outline-secondary btn-sm">
                      <i class="bi bi-qr-code me-1"></i> Verify Authenticity
                    </a>
                  <?php else: ?>
                    <button class="btn btn-secondary btn-sm" disabled>
                      <i class="bi bi-lock me-1"></i> Pending Final Approval
                    </button>
                  <?php endif; ?>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <!-- Instructions Card -->
        <div class="p-4 bg-light rounded-4 border text-muted small">
          <h6 class="fw-bold text-dark mb-2"><i class="bi bi-info-circle text-primary me-1"></i> Patient Notice Regarding Medical Reports</h6>
          <p class="mb-2">Laboratory reports are confidential clinical records. Please safeguard your electronic copies and consult your treating medical practitioner for clinical diagnosis and therapeutic decisions.</p>
          <p class="mb-0">If you experience difficulty accessing your report, please contact our 24/7 laboratory reception at <strong><?= e(Settings::get('lab_phone', '+91 98765 43210')) ?></strong>.</p>
        </div>

      </div>
    </div>

  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

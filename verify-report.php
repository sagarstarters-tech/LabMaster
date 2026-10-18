<?php
/**
 * LabMaster - QR Code Report Authenticity Verification
 * Public clinical verification of pathology reports
 */

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Verify Report Authenticity | ' . Settings::get('lab_name', 'LabMaster Diagnostic & Research Center');
$pageDesc  = 'Verify the digital authenticity and clinical integrity of LabMaster pathology test reports.';
$activePage = 'verify-report';

$token = trim($_GET['token'] ?? '');
$report = null;
$error = null;

if (!empty($token)) {
    $report = Database::fetch(
        "SELECT r.*, p.patient_id as patient_code, p.full_name as patient_name, p.gender, p.age,
                o.accession_number, o.created_at as accession_date,
                d.name as doctor_name,
                u.full_name as pathologist_name, u.qualification as pathologist_qual, u.medical_reg_no
         FROM `reports` r
         JOIN `patients` p ON r.patient_id = p.id
         JOIN `test_orders` o ON r.order_id = o.id
         LEFT JOIN `doctors` d ON o.referring_doctor_id = d.id
         LEFT JOIN `users` u ON r.approved_by = u.id
         WHERE r.verification_token = ? OR r.report_number = ?
         LIMIT 1",
        [$token, $token]
    );

    if ($report) {
        // Fetch tests performed
        $testNames = Database::fetchAll(
            "SELECT t.test_name, t.test_code, t.specimen_type 
             FROM `test_order_items` oi 
             JOIN `tests` t ON oi.test_id = t.id 
             WHERE oi.order_id = ?",
            [$report['order_id']]
        );
        $report['tests'] = $testNames;

        // Log public verification access
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        Database::insert(
            "INSERT INTO `report_access_logs` (`report_id`, `access_method`, `ip_address`, `user_agent`)
             VALUES (?, 'QR Code Scan Verification', ?, ?)",
            [$report['id'], $ip, $ua]
        );
    } else {
        $error = 'The provided report verification token or report number was not found in our diagnostic records. Please ensure the QR code was scanned accurately.';
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<!-- Header Banner -->
<section class="py-5 bg-light border-bottom">
  <div class="container text-center">
    <span class="badge bg-primary-subtle text-primary fw-semibold px-3 py-2 rounded-pill mb-2">Tamper-Proof Verification</span>
    <h1 class="fw-bold mb-3">Report Authenticity & Integrity Check</h1>
    <p class="text-muted max-w-700 mx-auto">
      Every genuine LabMaster laboratory report is digitally watermarked and verifiable against our central database to prevent medical record fraud.
    </p>
  </div>
</section>

<section class="py-5 bg-white">
  <div class="container">
    <div class="row justify-content-center">
      <div class="col-lg-8">
        
        <?php if (!empty($report)): ?>
          <!-- Verified Report Card -->
          <div class="card border-0 shadow-lg rounded-4 overflow-hidden mb-5">
            <div class="bg-success text-white p-4 text-center">
              <div class="display-3 mb-2"><i class="bi bi-patch-check-fill"></i></div>
              <h3 class="fw-bold mb-1">Authentic Laboratory Report Verified</h3>
              <p class="text-white-50 mb-0">Record Matched in LabMaster LIS Central Database</p>
            </div>

            <div class="p-4 p-md-5">
              <div class="row g-3 mb-4 p-3 bg-light rounded-3 small">
                <div class="col-sm-6">
                  <span class="text-muted">Report Number:</span>
                  <div class="fw-bold text-dark font-monospace fs-6"><?= e($report['report_number']) ?></div>
                </div>
                <div class="col-sm-6">
                  <span class="text-muted">Order Accession:</span>
                  <div class="fw-bold text-dark font-monospace fs-6"><?= e($report['accession_number']) ?></div>
                </div>
                <div class="col-sm-6">
                  <span class="text-muted">Patient Name:</span>
                  <div class="fw-bold text-dark fs-6"><?= e($report['patient_name']) ?></div>
                </div>
                <div class="col-sm-6">
                  <span class="text-muted">Patient Demographics:</span>
                  <div class="fw-bold text-dark"><?= e($report['gender']) ?> / <?= (int)$report['age'] ?> Yrs (ID: <?= e($report['patient_code']) ?>)</div>
                </div>
                <div class="col-sm-6">
                  <span class="text-muted">Referring Doctor:</span>
                  <div class="fw-semibold text-dark"><?= e($report['doctor_name'] ?? 'Self / Direct') ?></div>
                </div>
                <div class="col-sm-6">
                  <span class="text-muted">Accession Date & Time:</span>
                  <div class="fw-semibold text-dark"><?= date('d M Y, h:i A', strtotime($report['accession_date'])) ?></div>
                </div>
                <div class="col-sm-6">
                  <span class="text-muted">Report Status:</span>
                  <div>
                    <span class="badge bg-success-subtle text-success px-2 py-1"><i class="bi bi-check-circle me-1"></i> <?= e($report['report_status']) ?> (v<?= (int)$report['version_number'] ?>)</span>
                  </div>
                </div>
                <div class="col-sm-6">
                  <span class="text-muted">Approved & Digitally Signed:</span>
                  <div class="fw-semibold text-dark"><?= $report['approved_at'] ? date('d M Y, h:i A', strtotime($report['approved_at'])) : 'N/A' ?></div>
                </div>
              </div>

              <!-- Signatory Info -->
              <div class="p-3 border rounded-3 mb-4 bg-white d-flex align-items-center gap-3">
                <div class="rounded-circle bg-primary-subtle text-primary p-3 fs-3">
                  <i class="bi bi-person-badge"></i>
                </div>
                <div>
                  <span class="text-muted small">Authorized Medical Signatory:</span>
                  <div class="fw-bold text-dark"><?= e($report['pathologist_name'] ?? 'Consultant Clinical Pathologist') ?></div>
                  <div class="small text-muted">
                    <?= e($report['pathologist_qual'] ?? 'MD, DCP') ?> 
                    <?php if (!empty($report['medical_reg_no'])): ?>
                      • Reg No: <?= e($report['medical_reg_no']) ?>
                    <?php endif; ?>
                  </div>
                </div>
              </div>

              <!-- Tests Performed -->
              <h5 class="fw-bold mb-3">Diagnostic Panels Performed</h5>
              <div class="table-responsive border rounded-3 mb-4">
                <table class="table table-sm mb-0 align-middle">
                  <thead class="table-light">
                    <tr>
                      <th>Test Code</th>
                      <th>Investigation Name</th>
                      <th>Specimen</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($report['tests'] as $pt): ?>
                      <tr>
                        <td class="font-monospace text-primary fw-bold"><?= e($pt['test_code']) ?></td>
                        <td class="fw-semibold text-dark"><?= e($pt['test_name']) ?></td>
                        <td class="text-muted small"><?= e($pt['specimen_type']) ?></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>

              <div class="d-flex flex-wrap gap-2 justify-content-center pt-2">
                <a href="<?= ADMIN_URL ?>/reports/pdf.php?token=<?= urlencode($report['verification_token']) ?>" target="_blank" class="btn btn-primary-lm">
                  <i class="bi bi-file-earmark-pdf me-1"></i> View Official PDF Document
                </a>
                <a href="<?= BASE_URL ?>/verify-report.php" class="btn btn-outline-secondary">
                  <i class="bi bi-arrow-repeat me-1"></i> Check Another Report
                </a>
              </div>
            </div>
          </div>

        <?php else: ?>

          <!-- Manual Lookup Form -->
          <div class="card border rounded-4 p-4 p-md-5 shadow-sm mb-4">
            <div class="text-center mb-4">
              <div class="feature-icon-box mx-auto mb-3">
                <i class="bi bi-qr-code-scan"></i>
              </div>
              <h4 class="fw-bold mb-1">Verify Pathology Report</h4>
              <p class="text-muted small">Enter the security verification token or report number printed beneath the QR code.</p>
            </div>

            <?php if (!empty($error)): ?>
              <div class="alert alert-danger d-flex align-items-center gap-2 mb-4">
                <i class="bi bi-x-octagon-fill fs-5 flex-shrink-0"></i>
                <div><?= e($error) ?></div>
              </div>
            <?php endif; ?>

            <form action="<?= BASE_URL ?>/verify-report.php" method="GET">
              <div class="mb-4">
                <label class="form-label fw-semibold">Security Verification Token / Report Number <span class="text-danger">*</span></label>
                <div class="input-group input-group-lg">
                  <span class="input-group-text bg-white"><i class="bi bi-key text-muted"></i></span>
                  <input type="text" name="token" class="form-control" placeholder="e.g. LM-REP-2026-0001 or 64-char security hash" value="<?= e($token) ?>" required>
                  <button class="btn btn-primary-lm" type="submit">Verify Now</button>
                </div>
                <div class="form-text">You can find this on the top right or bottom footer of any official LabMaster report.</div>
              </div>
            </form>
          </div>

        <?php endif; ?>

      </div>
    </div>
  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<?php
/**
 * LabMaster - Create / Edit Professional Service Agreement
 * Lawful, documented medical and professional arrangements with strict ethics compliance
 */

declare(strict_types=1);

$pageTitle = 'Configure Service Agreement';
$activeNav = 'doctors';

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';

Auth::requirePermission('reconciliation.create');

$agreementId = (int)($_GET['id'] ?? 0);
$isEdit = $agreementId > 0;
$preselectedDoctorId = (int)($_GET['doctor_id'] ?? 0);

$agreement = null;
if ($isEdit) {
    $agreement = Database::fetch("SELECT * FROM `doctor_service_agreements` WHERE `id` = ?", [$agreementId]);
    if (!$agreement) {
        set_flash('danger', 'Service agreement not found.');
        header('Location: ' . ADMIN_URL . '/doctors/agreements.php');
        exit;
    }
}

// Fetch active doctors for selection
$doctors = Database::fetchAll("SELECT id, doctor_code, name, specialization, clinic_hospital FROM `doctors` WHERE `is_active` = 1 ORDER BY name ASC");

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $agreementCode = strtoupper(trim($_POST['agreement_code'] ?? ''));
    $doctorId = (int)($_POST['doctor_id'] ?? 0);
    $counterpartyName = trim($_POST['counterparty_name'] ?? '');
    $agreementType = trim($_POST['agreement_type'] ?? '');
    $serviceDescription = trim($_POST['service_description'] ?? '');
    $effectiveDate = trim($_POST['effective_date'] ?? '');
    $expiryDate = !empty($_POST['expiry_date']) ? trim($_POST['expiry_date']) : null;
    $paymentBasis = trim($_POST['payment_basis'] ?? '');
    $rateAmount = (float)($_POST['rate_amount'] ?? 0);
    $supportingDocPath = trim($_POST['supporting_document_path'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $actionType = $_POST['submit_action'] ?? 'save_draft';

    // Validation
    if (empty($agreementCode)) {
        $errors[] = 'Unique Agreement Code is required.';
    }
    if ($doctorId <= 0) {
        $errors[] = 'Please select a valid counterparty doctor from the registry.';
    }
    if (empty($counterpartyName)) {
        $selectedDoc = Database::fetch("SELECT name, clinic_hospital FROM `doctors` WHERE `id` = ?", [$doctorId]);
        $counterpartyName = $selectedDoc ? ($selectedDoc['name'] . ' (' . ($selectedDoc['clinic_hospital'] ?: 'Practitioner') . ')') : 'Consultant Physician';
    }
    if (empty($agreementType)) {
        $errors[] = 'Agreement Type is required.';
    }
    if (empty($serviceDescription)) {
        $errors[] = 'Detailed scope of professional services is required.';
    }
    if (empty($effectiveDate)) {
        $errors[] = 'Effective start date is required.';
    }
    if (!empty($expiryDate) && strtotime($expiryDate) < strtotime($effectiveDate)) {
        $errors[] = 'Expiry date cannot precede the effective start date.';
    }
    if (empty($paymentBasis)) {
        $errors[] = 'Approved payment basis is required.';
    }

    // STRICT COMPLIANCE: Reject any percentage referral attempt
    if (stripos($paymentBasis, 'percentage') !== false || stripos($paymentBasis, 'referral') !== false || stripos($paymentBasis, 'commission') !== false) {
        $errors[] = 'COMPLIANCE VIOLATION: Percentage-based referral fee structures are strictly prohibited under NMC regulations. Only documented fixed fees, hourly rates, camp stipends, or retainerships are lawful.';
    }

    // Check unique agreement code
    $codeCheckSql = "SELECT id FROM `doctor_service_agreements` WHERE `agreement_code` = ?" . ($isEdit ? " AND `id` != ?" : "");
    $codeCheckParams = $isEdit ? [$agreementCode, $agreementId] : [$agreementCode];
    if (Database::fetch($codeCheckSql, $codeCheckParams)) {
        $errors[] = "Agreement Code '{$agreementCode}' already exists. Please choose a unique code.";
    }

    // File upload handler for supporting contract PDF/Doc
    if (!empty($_FILES['contract_file']['name'])) {
        $allowedExtensions = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
        $fileInfo = pathinfo($_FILES['contract_file']['name']);
        $ext = strtolower($fileInfo['extension'] ?? '');

        if (!in_array($ext, $allowedExtensions, true)) {
            $errors[] = 'Supporting document must be a PDF, DOC, DOCX, or Image file.';
        } else {
            $uploadDir = STORAGE_PATH . '/agreements';
            if (!is_dir($uploadDir)) {
                @mkdir($uploadDir, 0777, true);
            }
            $filename = 'AGR_' . time() . '_' . rand(100, 999) . '.' . $ext;
            $dest = $uploadDir . '/' . $filename;
            if (move_uploaded_file($_FILES['contract_file']['tmp_name'], $dest)) {
                $supportingDocPath = 'storage/agreements/' . $filename;
            } else {
                $errors[] = 'Failed to upload supporting document file.';
            }
        }
    }

    if (empty($errors)) {
        $status = ($actionType === 'submit_review') ? 'Under Review' : 'Draft';
        $userId = Auth::id();

        if ($isEdit) {
            // If already approved, keep status unless resubmitting
            $finalStatus = ($agreement['approval_status'] === 'Approved' && $actionType !== 'submit_review') 
                ? 'Approved' : $status;

            Database::execute(
                "UPDATE `doctor_service_agreements` 
                 SET `agreement_code` = ?, `doctor_id` = ?, `counterparty_name` = ?, 
                     `agreement_type` = ?, `service_description` = ?, `effective_date` = ?, 
                     `expiry_date` = ?, `payment_basis` = ?, `rate_amount` = ?, 
                     `supporting_document_path` = COALESCE(NULLIF(?, ''), `supporting_document_path`), 
                     `approval_status` = ?, `notes` = ?
                 WHERE `id` = ?",
                [
                    $agreementCode, $doctorId, $counterpartyName,
                    $agreementType, $serviceDescription, $effectiveDate,
                    $expiryDate, $paymentBasis, $rateAmount,
                    $supportingDocPath, $finalStatus, $notes, $agreementId
                ]
            );
            log_audit('Service Agreement Updated', 'Reconciliation', $agreementCode, "Updated service agreement {$agreementCode} (Status: {$finalStatus}).");
            set_flash('success', "Service Agreement {$agreementCode} updated successfully.");
        } else {
            Database::execute(
                "INSERT INTO `doctor_service_agreements`
                 (`agreement_code`, `doctor_id`, `counterparty_name`, `agreement_type`, 
                  `service_description`, `effective_date`, `expiry_date`, `payment_basis`, 
                  `rate_amount`, `supporting_document_path`, `approval_status`, `created_by`, `notes`)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    $agreementCode, $doctorId, $counterpartyName, $agreementType,
                    $serviceDescription, $effectiveDate, $expiryDate, $paymentBasis,
                    $rateAmount, $supportingDocPath, $status, $userId, $notes
                ]
            );
            $newAgrId = (int)Database::getInstance()->lastInsertId();
            log_audit('Service Agreement Created', 'Reconciliation', $agreementCode, "Drafted professional service agreement {$agreementCode} for doctor ID {$doctorId}.");
            set_flash('success', "Service Agreement {$agreementCode} created successfully (Status: {$status}).");
        }

        header('Location: ' . ADMIN_URL . '/doctors/agreements.php');
        exit;
    }
}

// Generate code for new agreement
if (!$isEdit) {
    $yr = date('Y');
    $cnt = (int)(Database::fetch("SELECT COUNT(*) as c FROM `doctor_service_agreements`")['c'] ?? 0) + 1;
    $defaultAgrCode = sprintf('AGR-%s-%03d', $yr, $cnt);
} else {
    $defaultAgrCode = $agreement['agreement_code'];
}

$currentDoctorId = (int)($_POST['doctor_id'] ?? ($agreement['doctor_id'] ?? $preselectedDoctorId));

require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="row justify-content-center">
  <div class="col-lg-10 col-xl-9">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <div>
        <h2 class="h4 fw-bold mb-1">
          <i class="bi bi-file-earmark-text text-primary me-2"></i><?= $isEdit ? 'Edit Service Agreement' : 'New Professional Service Agreement' ?>
        </h2>
        <p class="text-muted small mb-0">Record lawful, documented professional agreements with physicians and healthcare institutions</p>
      </div>
      <a href="<?= ADMIN_URL ?>/doctors/agreements.php" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i> Back to Agreements
      </a>
    </div>

    <!-- Compliance Banner -->
    <div class="alert alert-light border border-info border-opacity-50 shadow-sm p-3 mb-4 rounded-3 d-flex align-items-start gap-3">
      <i class="bi bi-shield-check text-info fs-4 mt-1"></i>
      <div class="small text-secondary">
        <strong class="text-dark">Lawful Service Agreement Guidelines:</strong>
        Professional payments to doctors are lawful when they represent fair-market compensation for actual, documented professional services (e.g., Clinical Pathology Retainership, Histopathology Second Opinion Reviews, Community Camp Supervision, or Institutional Diagnostic SLAs).
        <strong>Commission or referral-based fee cuts are strictly blocked.</strong>
      </div>
    </div>

    <?php if (!empty($errors)): ?>
      <div class="alert alert-danger shadow-sm mb-4 border-start border-4 border-danger">
        <div class="fw-bold mb-2"><i class="bi bi-exclamation-triangle-fill me-1"></i> Submission Incomplete:</div>
        <ul class="mb-0 small ps-3">
          <?php foreach ($errors as $err): ?>
            <li><?= e($err) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <div class="admin-card">
      <div class="admin-card-body p-4">
        <form method="POST" action="" enctype="multipart/form-data">
          <?= csrf_field() ?>

          <h5 class="fw-bold text-dark border-bottom pb-2 mb-3">1. Counterparty & Agreement Identification</h5>
          <div class="row g-3 mb-4">
            <div class="col-md-4">
              <label class="form-label small fw-semibold">Agreement Code <span class="text-danger">*</span></label>
              <input type="text" name="agreement_code" class="form-control font-monospace" 
                     value="<?= e($_POST['agreement_code'] ?? $defaultAgrCode) ?>" required>
              <small class="text-muted" style="font-size:0.75rem;">Unique contract identifier</small>
            </div>

            <div class="col-md-8">
              <label class="form-label small fw-semibold">Select Physician / Specialist <span class="text-danger">*</span></label>
              <select name="doctor_id" id="doctorSelect" class="form-select" required>
                <option value="">-- Choose Registered Doctor --</option>
                <?php foreach ($doctors as $d): ?>
                  <option value="<?= $d['id'] ?>" <?= $currentDoctorId === (int)$d['id'] ? 'selected' : '' ?>
                          data-name="<?= e($d['name']) ?>" data-clinic="<?= e($d['clinic_hospital']) ?>">
                    <?= e($d['name']) ?> (<?= e($d['doctor_code']) ?>) - <?= e($d['specialization'] ?: 'General') ?> [<?= e($d['clinic_hospital'] ?: 'Independent') ?>]
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-md-12">
              <label class="form-label small fw-semibold">Counterparty Legal Name / Entity</label>
              <input type="text" name="counterparty_name" id="counterpartyName" class="form-control" 
                     placeholder="e.g. Dr. Rajesh Sharma / City Healthcare Trust" 
                     value="<?= e($_POST['counterparty_name'] ?? ($agreement['counterparty_name'] ?? '')) ?>">
              <small class="text-muted" style="font-size:0.75rem;">Contracted individual physician or clinical institution</small>
            </div>
          </div>

          <h5 class="fw-bold text-dark border-bottom pb-2 mb-3">2. Service Type & Scope of Work</h5>
          <div class="row g-3 mb-4">
            <div class="col-md-6">
              <label class="form-label small fw-semibold">Agreement Type <span class="text-danger">*</span></label>
              <select name="agreement_type" class="form-select" required>
                <?php
                $types = [
                    'Retainership / Consultant Pathologist',
                    'Consulting & Second Opinion',
                    'Preventive Camp Supervision',
                    'Institutional Service Level Agreement',
                    'Sample Collection Center / Phlebotomy SLA',
                    'Quality Assurance & Audit Advisory',
                    'Other Lawful Professional Service'
                ];
                $currType = $_POST['agreement_type'] ?? ($agreement['agreement_type'] ?? 'Consulting & Second Opinion');
                foreach ($types as $t): ?>
                  <option value="<?= e($t) ?>" <?= $currType === $t ? 'selected' : '' ?>><?= e($t) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-md-3">
              <label class="form-label small fw-semibold">Effective Start Date <span class="text-danger">*</span></label>
              <input type="date" name="effective_date" class="form-control" 
                     value="<?= e($_POST['effective_date'] ?? ($agreement['effective_date'] ?? date('Y-m-d'))) ?>" required>
            </div>

            <div class="col-md-3">
              <label class="form-label small fw-semibold">Expiry Date</label>
              <input type="date" name="expiry_date" class="form-control" 
                     value="<?= e($_POST['expiry_date'] ?? ($agreement['expiry_date'] ?? '')) ?>">
              <small class="text-muted" style="font-size:0.75rem;">Leave blank for open-ended</small>
            </div>

            <div class="col-12">
              <label class="form-label small fw-semibold">Service Description & Professional Responsibilities <span class="text-danger">*</span></label>
              <textarea name="service_description" class="form-control" rows="3" required
                        placeholder="Detail the documented medical services, such as weekly smear verification, second-opinion tele-pathology reporting, community screening counseling, or advisory obligations..."><?= e($_POST['service_description'] ?? ($agreement['service_description'] ?? '')) ?></textarea>
            </div>
          </div>

          <h5 class="fw-bold text-dark border-bottom pb-2 mb-3">3. Approved Payment Policy & Rate Structure</h5>
          <div class="row g-3 mb-4">
            <div class="col-md-6">
              <label class="form-label small fw-semibold">Approved Payment Basis <span class="text-danger">*</span></label>
              <select name="payment_basis" class="form-select" required>
                <?php
                $bases = [
                    'Fixed Monthly Fee' => 'Fixed Monthly Retainership (e.g. ₹25,000/month)',
                    'Fixed Per Case / Consultation' => 'Fixed Per Case Second-Opinion (e.g. ₹500/review)',
                    'Hourly Rate' => 'Documented Hourly Consultation Rate',
                    'Per Camp / Event Session' => 'Fixed Stipend Per Health Camp Session',
                    'Honorarium Per Report' => 'Fixed Honorarium Per Clinical Report Verified',
                    'Non-Monetary / Mutual Service' => 'Non-Monetary / Reciprocal SLA (Zero Cash)'
                ];
                $currBasis = $_POST['payment_basis'] ?? ($agreement['payment_basis'] ?? 'Fixed Monthly Fee');
                foreach ($bases as $k => $lbl): ?>
                  <option value="<?= e($k) ?>" <?= $currBasis === $k ? 'selected' : '' ?>><?= e($lbl) ?></option>
                <?php endforeach; ?>
              </select>
              <small class="text-muted" style="font-size:0.75rem;">Percentage-based referral models are disabled per ethics guidelines.</small>
            </div>

            <div class="col-md-6">
              <label class="form-label small fw-semibold">Applicable Rate / Fixed Fee (₹) <span class="text-danger">*</span></label>
              <div class="input-group">
                <span class="input-group-text bg-light">₹</span>
                <input type="number" step="0.01" min="0" name="rate_amount" class="form-control fw-bold" 
                       value="<?= e((string)($_POST['rate_amount'] ?? ($agreement['rate_amount'] ?? '0.00'))) ?>" required>
              </div>
              <small class="text-muted" style="font-size:0.75rem;">Fixed rate per monthly cycle, case, or camp session</small>
            </div>

            <div class="col-md-12">
              <label class="form-label small fw-semibold">Upload Supporting Contract / Agreement Document (PDF, Word, Image)</label>
              <input type="file" name="contract_file" class="form-control" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
              <?php if (!empty($agreement['supporting_document_path'])): ?>
                <div class="mt-2 small text-muted">
                  <i class="bi bi-file-earmark-check text-success me-1"></i> Current Document: 
                  <a href="<?= BASE_URL ?>/<?= e($agreement['supporting_document_path']) ?>" target="_blank" class="fw-semibold">
                    View Uploaded Document
                  </a>
                </div>
              <?php endif; ?>
            </div>

            <div class="col-12">
              <label class="form-label small fw-semibold">Internal Audit & Regulatory Notes</label>
              <textarea name="notes" class="form-control" rows="2" 
                        placeholder="Documentation of credentials review, hospital affiliation verification, and compliance notes..."><?= e($_POST['notes'] ?? ($agreement['notes'] ?? '')) ?></textarea>
            </div>
          </div>

          <div class="d-flex justify-content-between align-items-center pt-3 border-top">
            <a href="<?= ADMIN_URL ?>/doctors/agreements.php" class="btn btn-light border">Cancel</a>
            <div class="d-flex gap-2">
              <button type="submit" name="submit_action" value="save_draft" class="btn btn-outline-secondary">
                <i class="bi bi-save me-1"></i> Save as Draft
              </button>
              <button type="submit" name="submit_action" value="submit_review" class="btn btn-primary-lm">
                <i class="bi bi-send-check me-1"></i> Submit for Review
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
document.getElementById('doctorSelect').addEventListener('change', function() {
  const opt = this.options[this.selectedIndex];
  const nameInput = document.getElementById('counterpartyName');
  if (opt.value && (!nameInput.value || nameInput.value.trim() === '')) {
    const docName = opt.getAttribute('data-name');
    const docClinic = opt.getAttribute('data-clinic');
    nameInput.value = docName + (docClinic ? ' (' + docClinic + ')' : '');
  }
});
</script>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>

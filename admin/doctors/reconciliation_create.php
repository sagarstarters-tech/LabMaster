<?php
/**
 * LabMaster - Create Reconciliation Ledger Entry
 * Draft documented financial transaction strictly linked to an Approved Service Agreement
 */

declare(strict_types=1);

$pageTitle = 'Record Reconciliation Transaction';
$activeNav = 'doctors';

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';

Auth::requirePermission('reconciliation.create');

$preselectedDoctorId = (int)($_GET['doctor_id'] ?? 0);
$preselectedAgrId = (int)($_GET['agreement_id'] ?? 0);

// Fetch Approved Service Agreements (transactions can only be drafted against Approved agreements)
$agreements = Database::fetchAll(
    "SELECT a.*, d.name as doctor_name, d.doctor_code, d.specialization, d.clinic_hospital
     FROM `doctor_service_agreements` a
     JOIN `doctors` d ON a.doctor_id = d.id
     WHERE a.approval_status = 'Approved'
     ORDER BY d.name ASC, a.agreement_code ASC"
);

// If no approved agreements exist, warn the user
$hasApprovedAgreements = !empty($agreements);

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $transactionCode = strtoupper(trim($_POST['transaction_code'] ?? ''));
    $agreementId = (int)($_POST['agreement_id'] ?? 0);
    $transactionDate = trim($_POST['transaction_date'] ?? date('Y-m-d'));
    $transactionType = trim($_POST['transaction_type'] ?? '');
    $calculationBasis = trim($_POST['calculation_basis'] ?? '');
    $amount = (float)($_POST['amount'] ?? 0);
    $supportingVoucherNo = trim($_POST['supporting_voucher_no'] ?? '');
    $relatedInvoiceId = !empty($_POST['related_invoice_id']) ? (int)$_POST['related_invoice_id'] : null;
    $relatedOrderId = !empty($_POST['related_order_id']) ? (int)$_POST['related_order_id'] : null;
    $notes = trim($_POST['notes'] ?? '');
    $actionType = $_POST['submit_action'] ?? 'save_draft';

    // Validation
    if (empty($transactionCode)) {
        $errors[] = 'Transaction reference code is required.';
    }
    if ($agreementId <= 0) {
        $errors[] = 'A valid Approved Service Agreement reference is mandatory for reconciliation.';
    }
    if ($amount <= 0) {
        $errors[] = 'Transaction amount must be greater than zero.';
    }
    if (empty($transactionDate)) {
        $errors[] = 'Transaction date is required.';
    }
    if (empty($transactionType)) {
        $errors[] = 'Transaction type is required.';
    }
    if (empty($calculationBasis)) {
        $errors[] = 'Documented calculation basis narrative is required (e.g. Contracted retainership period or case tally).';
    }

    // STRICT COMPLIANCE: Prohibit commission / percentage kickback language
    if (stripos($calculationBasis, 'referral percentage') !== false || stripos($calculationBasis, 'commission cut') !== false || stripos($calculationBasis, 'kickback') !== false) {
        $errors[] = 'COMPLIANCE VIOLATION: Medical Council regulations prohibit referral kickbacks. Calculation basis must reflect bona fide professional services (e.g. retainership, second opinion, supervision).';
    }

    // Verify selected agreement is truly approved
    $agr = Database::fetch("SELECT * FROM `doctor_service_agreements` WHERE `id` = ?", [$agreementId]);
    if (!$agr || $agr['approval_status'] !== 'Approved') {
        $errors[] = 'Financial transactions can only be recorded against Active & Approved Service Agreements.';
    }

    // Check unique transaction code
    if (Database::fetch("SELECT id FROM `financial_reconciliation_ledger` WHERE `transaction_code` = ?", [$transactionCode])) {
        $errors[] = "Transaction Code '{$transactionCode}' already exists. Please provide a unique voucher code.";
    }

    // Handle document voucher upload
    $supportingDocPath = null;
    if (!empty($_FILES['voucher_file']['name'])) {
        $allowedExtensions = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
        $fileInfo = pathinfo($_FILES['voucher_file']['name']);
        $ext = strtolower($fileInfo['extension'] ?? '');

        if (!in_array($ext, $allowedExtensions, true)) {
            $errors[] = 'Supporting voucher document must be a PDF, DOC, DOCX, or Image file.';
        } else {
            $uploadDir = STORAGE_PATH . '/vouchers';
            if (!is_dir($uploadDir)) {
                @mkdir($uploadDir, 0777, true);
            }
            $filename = 'VCH_' . time() . '_' . rand(100, 999) . '.' . $ext;
            $dest = $uploadDir . '/' . $filename;
            if (move_uploaded_file($_FILES['voucher_file']['tmp_name'], $dest)) {
                $supportingDocPath = 'storage/vouchers/' . $filename;
            } else {
                $errors[] = 'Failed to upload supporting voucher file.';
            }
        }
    }

    if (empty($errors)) {
        $doctorId = (int)$agr['doctor_id'];
        $userId = Auth::id();
        $status = ($actionType === 'submit_review') ? 'Pending Review' : 'Draft';

        Database::execute(
            "INSERT INTO `financial_reconciliation_ledger`
             (`transaction_code`, `agreement_id`, `doctor_id`, `related_invoice_id`, `related_order_id`, 
              `transaction_date`, `transaction_type`, `calculation_basis`, `amount`, `payment_status`, 
              `supporting_voucher_no`, `supporting_document_path`, `created_by`, `notes`)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $transactionCode, $agreementId, $doctorId, $relatedInvoiceId, $relatedOrderId,
                $transactionDate, $transactionType, $calculationBasis, $amount, $status,
                $supportingVoucherNo, $supportingDocPath, $userId, $notes
            ]
        );

        $newLedgerId = (int)Database::getInstance()->lastInsertId();
        log_audit('Reconciliation Transaction Drafted', 'Reconciliation', $transactionCode, "Drafted reconciliation transaction {$transactionCode} for " . format_currency($amount) . " under agreement {$agr['agreement_code']} (Status: {$status}).");

        set_flash('success', "Reconciliation transaction {$transactionCode} recorded successfully (Status: {$status}).");
        header('Location: ' . ADMIN_URL . '/doctors/reconciliation.php');
        exit;
    }
}

// Generate default transaction code
$yr = date('Y');
$cnt = (int)(Database::fetch("SELECT COUNT(*) as c FROM `financial_reconciliation_ledger`")['c'] ?? 0) + 1;
$defaultTxCode = sprintf('REC-%s-%04d', $yr, $cnt);
$defaultVoucherNo = sprintf('VCH-%s-%04d', $yr, $cnt);

require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="row justify-content-center">
  <div class="col-lg-9 col-xl-8">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <div>
        <h2 class="h4 fw-bold mb-1">
          <i class="bi bi-wallet2 text-success me-2"></i>Record Reconciliation Transaction
        </h2>
        <p class="text-muted small mb-0">Record and document authorized service fee liabilities and consultant vouchers</p>
      </div>
      <a href="<?= ADMIN_URL ?>/doctors/reconciliation.php" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i> Back to Ledger
      </a>
    </div>

    <?php if (!$hasApprovedAgreements): ?>
      <div class="alert alert-warning shadow-sm mb-4">
        <div class="fw-bold mb-1"><i class="bi bi-exclamation-triangle-fill me-1"></i> No Approved Service Agreements Found</div>
        <div class="small">
          In compliance with laboratory financial governance, reconciliation transactions can only be recorded against 
          <strong>Approved Service Agreements</strong>. Please configure and authorize an agreement first.
        </div>
        <div class="mt-3">
          <a href="<?= ADMIN_URL ?>/doctors/agreements.php" class="btn btn-warning btn-sm text-dark">
            <i class="bi bi-file-earmark-plus me-1"></i> Go to Service Agreements
          </a>
        </div>
      </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
      <div class="alert alert-danger shadow-sm mb-4 border-start border-4 border-danger">
        <div class="fw-bold mb-2"><i class="bi bi-exclamation-octagon-fill me-1"></i> Cannot Record Transaction:</div>
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

          <h5 class="fw-bold text-dark border-bottom pb-2 mb-3">1. Voucher Identification & Agreement Reference</h5>
          <div class="row g-3 mb-4">
            <div class="col-md-6">
              <label class="form-label small fw-semibold">Transaction ID / Code <span class="text-danger">*</span></label>
              <input type="text" name="transaction_code" class="form-control font-monospace" 
                     value="<?= e($_POST['transaction_code'] ?? $defaultTxCode) ?>" required>
              <small class="text-muted" style="font-size:0.75rem;">Unique reconciliation reference code</small>
            </div>

            <div class="col-md-6">
              <label class="form-label small fw-semibold">Transaction Date <span class="text-danger">*</span></label>
              <input type="date" name="transaction_date" class="form-control" 
                     value="<?= e($_POST['transaction_date'] ?? date('Y-m-d')) ?>" required>
            </div>

            <div class="col-md-12">
              <label class="form-label small fw-semibold">Approved Service Agreement Reference <span class="text-danger">*</span></label>
              <select name="agreement_id" id="agreementSelect" class="form-select" required <?= !$hasApprovedAgreements ? 'disabled' : '' ?>>
                <option value="">-- Select Approved Agreement --</option>
                <?php foreach ($agreements as $ag): ?>
                  <?php 
                    $isSelected = ($preselectedAgrId === (int)$ag['id']) || ($preselectedDoctorId === (int)$ag['doctor_id']);
                  ?>
                  <option value="<?= $ag['id'] ?>" <?= $isSelected ? 'selected' : '' ?>
                          data-rate="<?= $ag['rate_amount'] ?>" 
                          data-type="<?= e($ag['agreement_type']) ?>"
                          data-basis="<?= e($ag['payment_basis']) ?>">
                    [<?= e($ag['agreement_code']) ?>] <?= e($ag['doctor_name']) ?> &mdash; <?= e($ag['agreement_type']) ?> (<?= format_currency($ag['rate_amount']) ?> <?= e($ag['payment_basis']) ?>)
                  </option>
                <?php endforeach; ?>
              </select>
              <small class="text-muted" style="font-size:0.75rem;">Only active agreements with status <strong>Approved</strong> are selectable.</small>
            </div>
          </div>

          <h5 class="fw-bold text-dark border-bottom pb-2 mb-3">2. Transaction Type & Lawful Calculation Basis</h5>
          <div class="row g-3 mb-4">
            <div class="col-md-6">
              <label class="form-label small fw-semibold">Transaction Type <span class="text-danger">*</span></label>
              <select name="transaction_type" id="transactionType" class="form-select" required>
                <?php
                $txTypes = [
                    'Retainership Fee' => 'Retainership / Consultant Pathologist Honorarium',
                    'Consultation & Review Fee' => 'Consultation & Histopathology Review Fee',
                    'Camp Honorarium' => 'Health Camp Supervision Stipend',
                    'Quality Assurance & Audit' => 'Quality Assurance & Clinical Audit Advisory Fee',
                    'Institutional Service Charge' => 'Institutional SLA Service Fee',
                    'Direct Service Allowance' => 'Approved Direct Medical Service Allowance'
                ];
                $currTxType = $_POST['transaction_type'] ?? 'Retainership Fee';
                foreach ($txTypes as $val => $lbl): ?>
                  <option value="<?= e($val) ?>" <?= $currTxType === $val ? 'selected' : '' ?>><?= e($lbl) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-md-6">
              <label class="form-label small fw-semibold">Disbursement Amount (₹) <span class="text-danger">*</span></label>
              <div class="input-group">
                <span class="input-group-text bg-light fw-bold">₹</span>
                <input type="number" step="0.01" min="0.01" name="amount" id="amountInput" class="form-control fw-bold" 
                       value="<?= e((string)($_POST['amount'] ?? '0.00')) ?>" required>
              </div>
              <small class="text-muted" style="font-size:0.75rem;">Approved liability amount</small>
            </div>

            <div class="col-12">
              <label class="form-label small fw-semibold">Approved Calculation Basis Narrative <span class="text-danger">*</span></label>
              <textarea name="calculation_basis" id="calcBasis" class="form-control" rows="2" required
                        placeholder="e.g. Fixed monthly retainership honorarium for March 2026 per signed agreement #AGR-2026-001"><?= e($_POST['calculation_basis'] ?? '') ?></textarea>
              <small class="text-muted" style="font-size:0.75rem;">Must articulate the lawful basis of the disbursement (never referral commissions).</small>
            </div>
          </div>

          <h5 class="fw-bold text-dark border-bottom pb-2 mb-3">3. Supporting Voucher & Clinical Cross-Reference</h5>
          <div class="row g-3 mb-4">
            <div class="col-md-6">
              <label class="form-label small fw-semibold">Supporting Internal Voucher Number</label>
              <input type="text" name="supporting_voucher_no" class="form-control font-monospace" 
                     placeholder="e.g. VCH-2026-0004" 
                     value="<?= e($_POST['supporting_voucher_no'] ?? $defaultVoucherNo) ?>">
            </div>

            <div class="col-md-6">
              <label class="form-label small fw-semibold">Upload Voucher / Billing Invoice File</label>
              <input type="file" name="voucher_file" class="form-control" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
            </div>

            <div class="col-12">
              <label class="form-label small fw-semibold">Administrative & Reconciliation Notes</label>
              <textarea name="notes" class="form-control" rows="2" 
                        placeholder="Any additional accounting notes or bank disbursement details..."><?= e($_POST['notes'] ?? '') ?></textarea>
            </div>
          </div>

          <div class="d-flex justify-content-between align-items-center pt-3 border-top">
            <a href="<?= ADMIN_URL ?>/doctors/reconciliation.php" class="btn btn-light border">Cancel</a>
            <div class="d-flex gap-2">
              <button type="submit" name="submit_action" value="save_draft" class="btn btn-outline-secondary" <?= !$hasApprovedAgreements ? 'disabled' : '' ?>>
                <i class="bi bi-save me-1"></i> Save as Draft
              </button>
              <button type="submit" name="submit_action" value="submit_review" class="btn btn-success" <?= !$hasApprovedAgreements ? 'disabled' : '' ?>>
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
document.getElementById('agreementSelect').addEventListener('change', function() {
  const opt = this.options[this.selectedIndex];
  if (opt.value) {
    const rate = opt.getAttribute('data-rate');
    const type = opt.getAttribute('data-type');
    const basis = opt.getAttribute('data-basis');

    const amountInput = document.getElementById('amountInput');
    if (parseFloat(rate) > 0 && (!amountInput.value || parseFloat(amountInput.value) === 0)) {
      amountInput.value = parseFloat(rate).toFixed(2);
    }

    const calcBasis = document.getElementById('calcBasis');
    if (!calcBasis.value || calcBasis.value.trim() === '') {
      calcBasis.value = 'Approved disbursement per ' + type + ' policy (' + basis + ') under contract.';
    }
  }
});
</script>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>

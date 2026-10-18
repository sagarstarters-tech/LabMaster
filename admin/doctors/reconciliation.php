<?php
/**
 * LabMaster - Financial Reconciliation Ledger
 * Authorized disbursements, multi-stage approval workflow, payment cross-posting, and reversal audit trail
 */

declare(strict_types=1);

$pageTitle = 'Financial Reconciliation Ledger';
$activeNav = 'doctors';

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';

Auth::requirePermission('reconciliation.view');

// Handle Post Actions (Approve, Pay, Reject, Reverse, Submit) BEFORE sending headers
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? '';
    $ledgerId = (int)($_POST['ledger_id'] ?? 0);
    $userId = Auth::id();

    $item = Database::fetch(
        "SELECT l.*, a.agreement_code, d.name as doctor_name 
         FROM `financial_reconciliation_ledger` l
         JOIN `doctor_service_agreements` a ON l.agreement_id = a.id
         JOIN `doctors` d ON l.doctor_id = d.id
         WHERE l.id = ?",
        [$ledgerId]
    );

    if ($item) {
        $pdo = Database::getInstance();

        // Action 1: Submit for Review
        if ($action === 'submit_review' && $item['payment_status'] === 'Draft' && Auth::can('reconciliation.create')) {
            Database::execute(
                "UPDATE `financial_reconciliation_ledger` SET `payment_status` = 'Pending Review' WHERE `id` = ?",
                [$ledgerId]
            );
            log_audit('Reconciliation Submitted', 'Reconciliation', $item['transaction_code'], "Submitted transaction {$item['transaction_code']} for managerial review.");
            set_flash('info', "Transaction {$item['transaction_code']} submitted for review.");
        }

        // Action 2: Approve Transaction
        elseif ($action === 'approve' && $item['payment_status'] === 'Pending Review' && Auth::can('reconciliation.approve')) {
            $notes = trim($_POST['approval_notes'] ?? '');
            Database::execute(
                "UPDATE `financial_reconciliation_ledger` 
                 SET `payment_status` = 'Approved', `approved_by` = ?, `approved_at` = NOW(), 
                     `notes` = CONCAT(COALESCE(`notes`,''), ' | Approved: ', ?)
                 WHERE `id` = ?",
                [$userId, $notes ?: 'Authorized for payment', $ledgerId]
            );
            log_audit('Reconciliation Approved', 'Reconciliation', $item['transaction_code'], "Approved voucher {$item['transaction_code']} for disbursement (" . format_currency($item['amount']) . ").");
            set_flash('success', "Transaction {$item['transaction_code']} has been approved.");
        }

        // Action 3: Disburse & Record Payment (Atomic Transaction + Expense Integration)
        elseif ($action === 'record_payment' && $item['payment_status'] === 'Approved' && Auth::can('reconciliation.pay')) {
            $paymentMethod = trim($_POST['payment_method'] ?? 'Bank Transfer');
            $txRef = trim($_POST['transaction_reference'] ?? '');
            $voucherNo = trim($_POST['supporting_voucher_no'] ?? ($item['supporting_voucher_no'] ?: 'VCH-' . time()));

            if (empty($txRef)) {
                set_flash('danger', 'Bank transaction reference (UTR, Cheque No, or Transfer ID) is required to record disbursement.');
            } else {
                try {
                    $pdo->beginTransaction();

                    // 1. Cross-post to expenses table to balance laboratory cashbook
                    $expenseNo = generate_expense_number();
                    $category = 'Professional & Consultant Fees';
                    $expenseNotes = "Disbursement for {$item['transaction_code']} ({$item['transaction_type']}) under agreement {$item['agreement_code']}. Ref: {$txRef}";
                    $today = date('Y-m-d');

                    Database::execute(
                        "INSERT INTO `expenses` 
                         (`expense_number`, `category`, `amount`, `payment_method`, `paid_to`, `expense_date`, `reference_no`, `notes`, `recorded_by`)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                        [$expenseNo, $category, $item['amount'], $paymentMethod, $item['doctor_name'], $today, $txRef, $expenseNotes, $userId]
                    );
                    $expenseId = (int)$pdo->lastInsertId();

                    // 2. Update reconciliation ledger record
                    Database::execute(
                        "UPDATE `financial_reconciliation_ledger` 
                         SET `payment_status` = 'Paid', `payment_method` = ?, `transaction_reference` = ?, 
                             `supporting_voucher_no` = ?, `expense_id` = ?, `paid_by` = ?, `paid_at` = NOW()
                         WHERE `id` = ?",
                        [$paymentMethod, $txRef, $voucherNo, $expenseId, $userId, $ledgerId]
                    );

                    $pdo->commit();

                    log_audit('Reconciliation Payment Disbursed', 'Reconciliation', $item['transaction_code'], "Disbursed " . format_currency($item['amount']) . " to {$item['doctor_name']} via {$paymentMethod} (Ref: {$txRef}, Expense: {$expenseNo}).");
                    set_flash('success', "Payment recorded successfully! Expense voucher {$expenseNo} logged in Cashbook.");
                } catch (Exception $ex) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    set_flash('danger', 'Payment processing failed: ' . $ex->getMessage());
                }
            }
        }

        // Action 4: Reject Transaction
        elseif ($action === 'reject' && in_array($item['payment_status'], ['Draft', 'Pending Review']) && Auth::can('reconciliation.approve')) {
            $reason = trim($_POST['rejection_reason'] ?? '');
            if (empty($reason)) {
                set_flash('danger', 'Rejection reason is mandatory.');
            } else {
                Database::execute(
                    "UPDATE `financial_reconciliation_ledger` 
                     SET `payment_status` = 'Rejected', `rejection_reason` = ? 
                     WHERE `id` = ?",
                    [$reason, $ledgerId]
                );
                log_audit('Reconciliation Rejected', 'Reconciliation', $item['transaction_code'], "Rejected voucher {$item['transaction_code']}: {$reason}");
                set_flash('warning', "Transaction {$item['transaction_code']} has been rejected.");
            }
        }

        // Action 5: Reverse Transaction
        elseif ($action === 'reverse' && in_array($item['payment_status'], ['Approved', 'Paid']) && Auth::can('reconciliation.reverse')) {
            $reason = trim($_POST['reversal_reason'] ?? '');
            if (empty($reason)) {
                set_flash('danger', 'Reversal justification is required for auditing.');
            } else {
                try {
                    $pdo->beginTransaction();

                    Database::execute(
                        "UPDATE `financial_reconciliation_ledger` 
                         SET `payment_status` = 'Reversed', `reversed_by` = ?, `reversed_at` = NOW(), `reversal_reason` = ?
                         WHERE `id` = ?",
                        [$userId, $reason, $ledgerId]
                    );

                    // If linked to an expense, annotate expense record
                    if (!empty($item['expense_id'])) {
                        Database::execute(
                            "UPDATE `expenses` 
                             SET `notes` = CONCAT(COALESCE(`notes`,''), ' | REVERSED: ', ?) 
                             WHERE `id` = ?",
                            [$reason, $item['expense_id']]
                        );
                    }

                    $pdo->commit();

                    log_audit('Reconciliation Reversed', 'Reconciliation', $item['transaction_code'], "Reversed transaction {$item['transaction_code']}: {$reason}");
                    set_flash('info', "Transaction {$item['transaction_code']} has been reversed.");
                } catch (Exception $ex) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    set_flash('danger', 'Reversal failed: ' . $ex->getMessage());
                }
            }
        }
    }

    header('Location: ' . ADMIN_URL . '/doctors/reconciliation.php');
    exit;
}

// Search & Filter Parameters
$search = trim($_GET['search'] ?? '');
$filterStatus = trim($_GET['status'] ?? '');
$filterDoctorId = (int)($_GET['doctor_id'] ?? 0);
$filterDateFrom = trim($_GET['date_from'] ?? '');
$filterDateTo = trim($_GET['date_to'] ?? '');

$whereClauses = ['1=1'];
$params = [];

if ($search !== '') {
    $whereClauses[] = "(l.transaction_code LIKE ? OR l.supporting_voucher_no LIKE ? OR l.transaction_reference LIKE ? OR d.name LIKE ? OR a.agreement_code LIKE ?)";
    $like = '%' . $search . '%';
    $params = array_merge($params, [$like, $like, $like, $like, $like]);
}

if ($filterStatus !== '') {
    $whereClauses[] = "l.payment_status = ?";
    $params[] = $filterStatus;
}

if ($filterDoctorId > 0) {
    $whereClauses[] = "l.doctor_id = ?";
    $params[] = $filterDoctorId;
}

if ($filterDateFrom !== '') {
    $whereClauses[] = "l.transaction_date >= ?";
    $params[] = $filterDateFrom;
}

if ($filterDateTo !== '') {
    $whereClauses[] = "l.transaction_date <= ?";
    $params[] = $filterDateTo;
}

$whereSql = implode(' AND ', $whereClauses);

// CSV Export Handler
if (isset($_GET['export']) && $_GET['export'] === 'csv' && Auth::can('reconciliation.export')) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=reconciliation_ledger_' . date('Ymd_His') . '.csv');
    $out = fopen('php://output', 'w');
    fputcsv($out, [
        'Transaction ID', 'Date', 'Doctor Code', 'Doctor Name', 'Hospital/Clinic', 
        'Agreement Reference', 'Agreement Type', 'Transaction Type', 'Calculation Basis', 
        'Amount (INR)', 'Payment Status', 'Payment Method', 'Transaction Ref (UTR)', 
        'Voucher No', 'Created By', 'Approved By', 'Paid At'
    ]);

    $allExport = Database::fetchAll(
        "SELECT l.*, a.agreement_code, a.agreement_type, d.doctor_code, d.name as doctor_name, d.clinic_hospital,
                u.full_name as creator_name, app.full_name as approver_name
         FROM `financial_reconciliation_ledger` l
         JOIN `doctor_service_agreements` a ON l.agreement_id = a.id
         JOIN `doctors` d ON l.doctor_id = d.id
         LEFT JOIN `users` u ON l.created_by = u.id
         LEFT JOIN `users` app ON l.approved_by = app.id
         WHERE {$whereSql}
         ORDER BY l.id DESC",
        $params
    );

    foreach ($allExport as $row) {
        fputcsv($out, [
            $row['transaction_code'],
            $row['transaction_date'],
            $row['doctor_code'],
            $row['doctor_name'],
            $row['clinic_hospital'] ?: 'Independent',
            $row['agreement_code'],
            $row['agreement_type'],
            $row['transaction_type'],
            $row['calculation_basis'],
            $row['amount'],
            $row['payment_status'],
            $row['payment_method'] ?: 'N/A',
            $row['transaction_reference'] ?: 'N/A',
            $row['supporting_voucher_no'] ?: 'N/A',
            $row['creator_name'] ?: 'System',
            $row['approver_name'] ?: 'N/A',
            $row['paid_at'] ?: 'N/A'
        ]);
    }
    fclose($out);
    exit;
}

require_once dirname(__DIR__) . '/includes/header.php';

// Fetch ledger entries
$ledgerItems = Database::fetchAll(
    "SELECT l.*, a.agreement_code, a.agreement_type, a.payment_basis, 
            d.name as doctor_name, d.doctor_code, d.clinic_hospital,
            u.full_name as creator_name, app.full_name as approver_name, pd.full_name as payer_name,
            rev.full_name as reverser_name
     FROM `financial_reconciliation_ledger` l
     JOIN `doctor_service_agreements` a ON l.agreement_id = a.id
     JOIN `doctors` d ON l.doctor_id = d.id
     LEFT JOIN `users` u ON l.created_by = u.id
     LEFT JOIN `users` app ON l.approved_by = app.id
     LEFT JOIN `users` pd ON l.paid_by = pd.id
     LEFT JOIN `users` rev ON l.reversed_by = rev.id
     WHERE {$whereSql}
     ORDER BY l.id DESC",
    $params
);

// Aggregate KPI Metrics
$totalDisbursed = (float)(Database::fetch("SELECT COALESCE(SUM(amount), 0) as s FROM `financial_reconciliation_ledger` WHERE `payment_status` = 'Paid'")['s'] ?? 0);
$totalApprovedDue = (float)(Database::fetch("SELECT COALESCE(SUM(amount), 0) as s FROM `financial_reconciliation_ledger` WHERE `payment_status` = 'Approved'")['s'] ?? 0);
$totalPendingReview = (float)(Database::fetch("SELECT COALESCE(SUM(amount), 0) as s FROM `financial_reconciliation_ledger` WHERE `payment_status` = 'Pending Review'")['s'] ?? 0);
$totalDraftCount = (int)(Database::fetch("SELECT COUNT(*) as c FROM `financial_reconciliation_ledger` WHERE `payment_status` = 'Draft'")['c'] ?? 0);

$allDoctors = Database::fetchAll("SELECT id, name, doctor_code FROM `doctors` ORDER BY name ASC");
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
  <div>
    <h2 class="h4 fw-bold mb-1">
      <i class="bi bi-wallet2 text-success me-2"></i>Financial Reconciliation Ledger
    </h2>
    <p class="text-muted small mb-0">Lawful service fee vouchers, multi-stage approval workflow, payment disbursement, and audit trail</p>
  </div>
  <div class="d-flex flex-wrap gap-2">
    <?php if (Auth::can('reconciliation.create')): ?>
      <a href="<?= ADMIN_URL ?>/doctors/reconciliation_create.php" class="btn btn-success d-inline-flex align-items-center gap-2">
        <i class="bi bi-plus-circle-fill"></i> Record Transaction
      </a>
    <?php endif; ?>
    <?php if (Auth::can('reconciliation.export')): ?>
      <a href="?<?= http_build_query(array_merge($_GET, ['export' => 'csv'])) ?>" class="btn btn-outline-secondary d-inline-flex align-items-center gap-2">
        <i class="bi bi-download"></i> Export CSV
      </a>
    <?php endif; ?>
    <a href="<?= ADMIN_URL ?>/doctors/agreements.php" class="btn btn-outline-primary d-inline-flex align-items-center gap-2">
      <i class="bi bi-file-earmark-text"></i> Service Agreements
    </a>
  </div>
</div>

<!-- 4 Financial Reconciliation Summary Cards -->
<div class="row g-3 mb-4">
  <div class="col-sm-6 col-xl-3">
    <div class="admin-card p-3 border-start border-4 border-teal h-100" style="border-left-color: #0d9488 !important;">
      <span class="text-muted small fw-semibold text-uppercase">Total Disbursed</span>
      <h3 class="fw-bold mb-0 mt-1" style="color: #0d9488;"><?= format_currency($totalDisbursed) ?></h3>
      <small class="text-muted">Reconciled & paid vouchers</small>
    </div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="admin-card p-3 border-start border-4 border-primary h-100">
      <span class="text-muted small fw-semibold text-uppercase">Approved Liability Due</span>
      <h3 class="fw-bold mb-0 mt-1 text-primary"><?= format_currency($totalApprovedDue) ?></h3>
      <small class="text-muted">Authorized, pending payout</small>
    </div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="admin-card p-3 border-start border-4 border-warning h-100">
      <span class="text-muted small fw-semibold text-uppercase">Pending Review</span>
      <h3 class="fw-bold mb-0 mt-1 text-warning-emphasis"><?= format_currency($totalPendingReview) ?></h3>
      <small class="text-muted">Under managerial review</small>
    </div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="admin-card p-3 border-start border-4 border-secondary h-100">
      <span class="text-muted small fw-semibold text-uppercase">Draft Transactions</span>
      <h3 class="fw-bold mb-0 mt-1 text-secondary"><?= number_format($totalDraftCount) ?></h3>
      <small class="text-muted">Unsubmitted vouchers</small>
    </div>
  </div>
</div>

<!-- Search & Filters -->
<div class="admin-card mb-4">
  <div class="admin-card-body p-3">
    <form method="GET" action="" class="row g-2 align-items-center">
      <div class="col-md-3">
        <input type="text" name="search" class="form-control" 
               placeholder="Search voucher, doctor, UTR..." 
               value="<?= e($search) ?>">
      </div>

      <div class="col-md-2">
        <select name="status" class="form-select">
          <option value="">All Statuses</option>
          <option value="Draft" <?= $filterStatus === 'Draft' ? 'selected' : '' ?>>Draft</option>
          <option value="Pending Review" <?= $filterStatus === 'Pending Review' ? 'selected' : '' ?>>Pending Review</option>
          <option value="Approved" <?= $filterStatus === 'Approved' ? 'selected' : '' ?>>Approved</option>
          <option value="Paid" <?= $filterStatus === 'Paid' ? 'selected' : '' ?>>Paid (Reconciled)</option>
          <option value="Rejected" <?= $filterStatus === 'Rejected' ? 'selected' : '' ?>>Rejected</option>
          <option value="Reversed" <?= $filterStatus === 'Reversed' ? 'selected' : '' ?>>Reversed</option>
        </select>
      </div>

      <div class="col-md-3">
        <select name="doctor_id" class="form-select">
          <option value="">All Physicians</option>
          <?php foreach ($allDoctors as $doc): ?>
            <option value="<?= $doc['id'] ?>" <?= $filterDoctorId === (int)$doc['id'] ? 'selected' : '' ?>>
              <?= e($doc['name']) ?> (<?= e($doc['doctor_code']) ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-md-3 d-flex gap-1">
        <input type="date" name="date_from" class="form-control" title="From Date" value="<?= e($filterDateFrom) ?>">
        <input type="date" name="date_to" class="form-control" title="To Date" value="<?= e($filterDateTo) ?>">
      </div>

      <div class="col-md-1 d-flex gap-1">
        <button type="submit" class="btn btn-primary w-100" title="Filter">
          <i class="bi bi-funnel"></i>
        </button>
        <?php if ($search !== '' || $filterStatus !== '' || $filterDoctorId > 0 || $filterDateFrom !== '' || $filterDateTo !== ''): ?>
          <a href="<?= ADMIN_URL ?>/doctors/reconciliation.php" class="btn btn-outline-secondary" title="Reset">
            <i class="bi bi-x-lg"></i>
          </a>
        <?php endif; ?>
      </div>
    </form>
  </div>
</div>

<!-- Ledger Transactions Table -->
<div class="admin-card">
  <div class="admin-card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>Voucher / Code</th>
            <th>Date</th>
            <th>Doctor / Beneficiary</th>
            <th>Agreement Ref</th>
            <th>Service Type & Narrative</th>
            <th class="text-end">Amount</th>
            <th>Payment Status</th>
            <th>Disbursement Details</th>
            <th class="text-end text-nowrap pe-3" style="width: 160px;">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($ledgerItems)): ?>
            <tr>
              <td colspan="9" class="text-center py-5 text-muted">
                <i class="bi bi-inbox display-6 d-block mb-2 text-secondary"></i>
                No financial reconciliation transactions found matching your filters.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($ledgerItems as $tx): ?>
              <tr>
                <td>
                  <span class="badge bg-light text-dark border font-monospace fw-bold">
                    <?= e($tx['transaction_code']) ?>
                  </span>
                  <?php if (!empty($tx['supporting_voucher_no'])): ?>
                    <div class="text-muted small mt-1 font-monospace"><?= e($tx['supporting_voucher_no']) ?></div>
                  <?php endif; ?>
                </td>
                <td>
                  <div class="small fw-semibold"><?= format_date($tx['transaction_date']) ?></div>
                </td>
                <td>
                  <a href="<?= ADMIN_URL ?>/doctors/view.php?id=<?= $tx['doctor_id'] ?>" class="fw-bold text-dark text-decoration-none">
                    <?= e($tx['doctor_name']) ?>
                  </a>
                  <div class="text-secondary small"><?= e($tx['clinic_hospital'] ?: 'Practitioner') ?></div>
                </td>
                <td>
                  <span class="badge bg-light text-secondary border font-monospace">
                    <?= e($tx['agreement_code']) ?>
                  </span>
                  <div class="text-muted small mt-1"><?= e($tx['agreement_type']) ?></div>
                </td>
                <td>
                  <div class="fw-semibold text-dark small"><?= e($tx['transaction_type']) ?></div>
                  <div class="text-muted small text-truncate" style="max-width: 250px;" title="<?= e($tx['calculation_basis']) ?>">
                    <?= e($tx['calculation_basis']) ?>
                  </div>
                </td>
                <td class="text-end fw-bold text-dark">
                  <?= format_currency($tx['amount']) ?>
                </td>
                <td>
                  <?php
                  $badgeClass = match($tx['payment_status']) {
                      'Paid' => 'bg-success',
                      'Approved' => 'bg-primary',
                      'Pending Review' => 'bg-warning text-dark',
                      'Draft' => 'bg-secondary',
                      'Rejected' => 'bg-danger',
                      'Reversed' => 'bg-dark',
                      default => 'bg-light text-dark'
                  };
                  ?>
                  <span class="badge <?= $badgeClass ?>"><?= e($tx['payment_status']) ?></span>
                </td>
                <td>
                  <?php if ($tx['payment_status'] === 'Paid'): ?>
                    <div class="small fw-semibold text-success"><i class="bi bi-check-circle-fill me-1"></i><?= e($tx['payment_method']) ?></div>
                    <div class="text-muted font-monospace" style="font-size:0.72rem;">Ref: <?= e($tx['transaction_reference'] ?: 'N/A') ?></div>
                    <div class="text-muted" style="font-size:0.7rem;"><?= format_date($tx['paid_at']) ?></div>
                  <?php elseif ($tx['payment_status'] === 'Approved'): ?>
                    <span class="badge bg-primary-subtle text-primary-emphasis">Ready to Disburse</span>
                  <?php elseif ($tx['payment_status'] === 'Reversed'): ?>
                    <span class="text-danger small fw-semibold">Reversed: <?= e($tx['reversal_reason'] ?: 'Audited') ?></span>
                  <?php else: ?>
                    <span class="text-muted small"><?= e($tx['payment_status']) ?></span>
                  <?php endif; ?>
                </td>
                <td class="text-end text-nowrap pe-3">
                  <div class="d-inline-flex align-items-center justify-content-end gap-1">
                    <!-- View Transaction & Doctor Ledger -->
                    <a href="<?= ADMIN_URL ?>/doctors/view.php?id=<?= $tx['doctor_id'] ?>#reconciliation" class="btn btn-sm btn-outline-primary btn-action-icon" title="View Transaction & Doctor History">
                      <i class="bi bi-eye"></i>
                    </a>

                    <!-- Edit / Process / Disburse Action -->
                    <?php if ($tx['payment_status'] === 'Draft' && Auth::can('reconciliation.create')): ?>
                      <form method="POST" action="" class="d-inline m-0 p-0">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="submit_review">
                        <input type="hidden" name="ledger_id" value="<?= $tx['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-outline-secondary btn-action-icon" title="Submit for Review">
                          <i class="bi bi-send-check"></i>
                        </button>
                      </form>
                    <?php elseif ($tx['payment_status'] === 'Pending Review' && Auth::can('reconciliation.approve')): ?>
                      <form method="POST" action="" class="d-inline m-0 p-0">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="approve">
                        <input type="hidden" name="ledger_id" value="<?= $tx['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-outline-success btn-action-icon" title="Authorize & Approve">
                          <i class="bi bi-check-circle"></i>
                        </button>
                      </form>
                    <?php elseif ($tx['payment_status'] === 'Approved' && Auth::can('reconciliation.pay')): ?>
                      <button class="btn btn-sm btn-outline-success btn-action-icon" type="button" 
                              data-bs-toggle="modal" data-bs-target="#payModal" 
                              data-id="<?= $tx['id'] ?>" data-code="<?= e($tx['transaction_code']) ?>" 
                              data-amount="<?= $tx['amount'] ?>" data-doc="<?= e($tx['doctor_name']) ?>"
                              title="Disburse & Record Payment">
                        <i class="bi bi-cash-stack"></i>
                      </button>
                    <?php endif; ?>

                    <!-- Delete / Reject / Reverse -->
                    <?php if ($tx['payment_status'] === 'Pending Review' && Auth::can('reconciliation.approve')): ?>
                      <button class="btn btn-sm btn-outline-danger btn-action-icon" type="button" 
                              data-bs-toggle="modal" data-bs-target="#rejectModal" 
                              data-id="<?= $tx['id'] ?>" data-code="<?= e($tx['transaction_code']) ?>"
                              title="Reject Transaction">
                        <i class="bi bi-x-circle"></i>
                      </button>
                    <?php elseif (in_array($tx['payment_status'], ['Approved', 'Paid']) && Auth::can('reconciliation.reverse')): ?>
                      <button class="btn btn-sm btn-outline-danger btn-action-icon" type="button" 
                              data-bs-toggle="modal" data-bs-target="#reverseModal" 
                              data-id="<?= $tx['id'] ?>" data-code="<?= e($tx['transaction_code']) ?>"
                              title="Reverse Transaction">
                        <i class="bi bi-arrow-counterclockwise"></i>
                      </button>
                    <?php endif; ?>

                    <!-- More Options Dropdown -->
                    <div class="dropdown d-inline-block">
                      <button class="btn btn-sm btn-light border btn-action-icon" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="More Options">
                        <i class="bi bi-three-dots-vertical"></i>
                      </button>
                      <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                        <?php if ($tx['payment_status'] === 'Approved' && Auth::can('reconciliation.pay')): ?>
                          <li>
                            <button class="dropdown-item text-success fw-bold" type="button" 
                                    data-bs-toggle="modal" data-bs-target="#payModal" 
                                    data-id="<?= $tx['id'] ?>" data-code="<?= e($tx['transaction_code']) ?>" 
                                    data-amount="<?= $tx['amount'] ?>" data-doc="<?= e($tx['doctor_name']) ?>">
                              <i class="bi bi-cash-stack me-2"></i> Disburse & Record Payment
                            </button>
                          </li>
                        <?php endif; ?>
                        <li>
                          <a class="dropdown-item" href="<?= ADMIN_URL ?>/doctors/view.php?id=<?= $tx['doctor_id'] ?>&tab=reconciliation">
                            <i class="bi bi-person-badge text-primary me-2"></i> Doctor History
                          </a>
                        </li>
                      </ul>
                    </div>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Payment Disbursement Modal -->
<div class="modal fade" id="payModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" action="">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="record_payment">
        <input type="hidden" name="ledger_id" id="payLedgerId" value="">

        <div class="modal-header">
          <h5 class="modal-title fw-bold text-success"><i class="bi bi-cash-stack me-2"></i>Disburse Service Payment</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="alert alert-light border p-3 mb-3">
            <div><strong>Transaction:</strong> <span id="payCode"></span></div>
            <div><strong>Payee:</strong> <span id="payDoc"></span></div>
            <div><strong>Disbursement Amount:</strong> <span class="fw-bold text-success" id="payAmount"></span></div>
          </div>

          <div class="mb-3">
            <label class="form-label small fw-semibold">Payment Method <span class="text-danger">*</span></label>
            <select name="payment_method" class="form-select" required>
              <option value="Bank Transfer">Bank Transfer (NEFT / RTGS)</option>
              <option value="Cheque">Bank Cheque</option>
              <option value="UPI">UPI Payment</option>
              <option value="Cash">Cash Voucher</option>
              <option value="Other">Other Documented Channel</option>
            </select>
          </div>

          <div class="mb-3">
            <label class="form-label small fw-semibold">Transaction Reference / UTR Number <span class="text-danger">*</span></label>
            <input type="text" name="transaction_reference" class="form-control font-monospace" 
                   placeholder="e.g. UTR / NEFT reference number or Cheque No" required>
            <small class="text-muted" style="font-size:0.75rem;">Audit reference number</small>
          </div>

          <div class="mb-3">
            <label class="form-label small fw-semibold">Disbursement Voucher Number</label>
            <input type="text" name="supporting_voucher_no" class="form-control font-monospace" 
                   placeholder="e.g. VCH-2026-009">
          </div>

          <div class="small text-muted border-top pt-2">
            <i class="bi bi-info-circle me-1"></i> Confirming this payment automatically cross-posts an expense in the Laboratory Daily Cashbook under <em>Professional & Consultant Fees</em>.
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-success fw-bold">Confirm & Disburse</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Rejection Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" action="">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="reject">
        <input type="hidden" name="ledger_id" id="rejectLedgerId" value="">

        <div class="modal-header">
          <h5 class="modal-title fw-bold text-danger">Reject Reconciliation Transaction</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <p class="small text-secondary">
            Rejecting transaction <strong id="rejectCode"></strong> prevents it from being disbursed.
          </p>
          <div class="mb-3">
            <label class="form-label small fw-semibold">Rejection Justification <span class="text-danger">*</span></label>
            <textarea name="rejection_reason" class="form-control" rows="3" required
                      placeholder="Specify the reason for transaction rejection..."></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-danger">Confirm Rejection</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Reversal Modal -->
<div class="modal fade" id="reverseModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" action="">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="reverse">
        <input type="hidden" name="ledger_id" id="reverseLedgerId" value="">

        <div class="modal-header">
          <h5 class="modal-title fw-bold text-danger"><i class="bi bi-arrow-counterclockwise me-2"></i>Reverse Transaction</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="alert alert-warning small">
            <i class="bi bi-exclamation-triangle-fill me-1"></i>
            Reversing transaction <strong id="reverseCode"></strong> is an audited financial action. If already paid, the accounting ledger will reflect this reversal.
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold">Mandatory Reversal Justification Reason <span class="text-danger">*</span></label>
            <textarea name="reversal_reason" class="form-control" rows="3" required
                      placeholder="Document the exact reason for reversing this voucher (e.g. incorrect amount, billing cancellation, bank bounce)..."></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-danger">Confirm Reversal</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
// Payout modal
const payModal = document.getElementById('payModal');
if (payModal) {
  payModal.addEventListener('show.bs.modal', function(event) {
    const btn = event.relatedTarget;
    document.getElementById('payLedgerId').value = btn.getAttribute('data-id');
    document.getElementById('payCode').innerText = btn.getAttribute('data-code');
    document.getElementById('payDoc').innerText = btn.getAttribute('data-doc');
    document.getElementById('payAmount').innerText = '₹' + parseFloat(btn.getAttribute('data-amount')).toFixed(2);
  });
}

// Reject modal
const rejectModal = document.getElementById('rejectModal');
if (rejectModal) {
  rejectModal.addEventListener('show.bs.modal', function(event) {
    const btn = event.relatedTarget;
    document.getElementById('rejectLedgerId').value = btn.getAttribute('data-id');
    document.getElementById('rejectCode').innerText = btn.getAttribute('data-code');
  });
}

// Reverse modal
const reverseModal = document.getElementById('reverseModal');
if (reverseModal) {
  reverseModal.addEventListener('show.bs.modal', function(event) {
    const btn = event.relatedTarget;
    document.getElementById('reverseLedgerId').value = btn.getAttribute('data-id');
    document.getElementById('reverseCode').innerText = btn.getAttribute('data-code');
  });
}
</script>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>

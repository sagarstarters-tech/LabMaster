<?php
/**
 * LabMaster - Daily Cashbook & Financial Accounting
 */

declare(strict_types=1);

$pageTitle = 'Daily Cashbook';
$activeNav = 'accounting';

require_once dirname(__DIR__) . '/includes/auth_guard.php';
Auth::requirePermission('accounting.manage');

$selectedDate = !empty($_GET['date']) ? trim($_GET['date']) : date('Y-m-d');

// 1. Fetch Inflows (Patient Payments) on Selected Date
$inflows = Database::fetchAll(
    "SELECT p.*, i.invoice_number, pt.full_name as patient_name, pt.patient_id as patient_code,
            u.full_name as receiver_name
     FROM `payments` p
     JOIN `invoices` i ON p.invoice_id = i.id
     JOIN `patients` pt ON i.patient_id = pt.id
     LEFT JOIN `users` u ON p.received_by = u.id
     WHERE p.payment_date = ?
     ORDER BY p.id ASC",
    [$selectedDate]
);

// 2. Fetch Outflows (Operational Expenses) on Selected Date
$outflows = Database::fetchAll(
    "SELECT e.*, u.full_name as recorder_name 
     FROM `expenses` e 
     LEFT JOIN `users` u ON e.recorded_by = u.id 
     WHERE e.expense_date = ? 
     ORDER BY e.id ASC",
    [$selectedDate]
);

// Calculate Totals for the Date
$totalInflow = 0.0;
$inflowsByMethod = ['Cash' => 0.0, 'UPI' => 0.0, 'Card' => 0.0, 'Bank Transfer' => 0.0, 'Cheque' => 0.0];
foreach ($inflows as $in) {
    $amt = (float)$in['amount'];
    $totalInflow += $amt;
    $meth = $in['payment_method'] ?? 'Cash';
    if (isset($inflowsByMethod[$meth])) {
        $inflowsByMethod[$meth] += $amt;
    }
}

$totalOutflow = 0.0;
foreach ($outflows as $out) {
    $totalOutflow += (float)$out['amount'];
}

$netDailyCash = $totalInflow - $totalOutflow;

require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
  <div>
    <h2 class="h4 fw-bold mb-1">Laboratory Daily Cashbook</h2>
    <p class="text-muted small mb-0">Reconciliation of patient receipts, operational disbursements, and daily closing balance</p>
  </div>
  <div class="d-flex gap-2">
    <a href="<?= ADMIN_URL ?>/accounting/expenses.php" class="btn btn-outline-danger btn-sm">
      <i class="bi bi-dash-circle me-1"></i> Record Expense
    </a>
    <a href="<?= ADMIN_URL ?>/accounting/reports.php" class="btn btn-outline-primary btn-sm">
      <i class="bi bi-graph-up me-1"></i> Financial Reports
    </a>
  </div>
</div>

<!-- Date Selector -->
<div class="admin-card mb-4">
  <div class="admin-card-body p-3">
    <form method="GET" action="" class="row g-2 align-items-center">
      <div class="col-md-4">
        <label class="form-label small fw-semibold mb-1">Select Cashbook Date</label>
        <div class="input-group">
          <span class="input-group-text bg-light"><i class="bi bi-calendar3"></i></span>
          <input type="date" name="date" class="form-control" value="<?= e($selectedDate) ?>">
        </div>
      </div>
      <div class="col-md-3 align-self-end">
        <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i> View Ledger</button>
        <?php if ($selectedDate !== date('Y-m-d')): ?>
          <a href="<?= ADMIN_URL ?>/accounting/index.php" class="btn btn-outline-secondary ms-1">Today</a>
        <?php endif; ?>
      </div>
    </form>
  </div>
</div>

<!-- Summary Cards for Selected Date -->
<div class="row g-3 mb-4">
  <div class="col-md-4">
    <div class="p-3 bg-white rounded border shadow-sm">
      <div class="text-muted small text-uppercase fw-semibold">Total Collections (Inflow)</div>
      <div class="h3 fw-bold mb-0 text-success"><?= format_currency($totalInflow) ?></div>
      <div class="small text-muted mt-1"><?= count($inflows) ?> Patient Payment Receipts</div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="p-3 bg-white rounded border shadow-sm">
      <div class="text-muted small text-uppercase fw-semibold">Operational Expenses (Outflow)</div>
      <div class="h3 fw-bold mb-0 text-danger"><?= format_currency($totalOutflow) ?></div>
      <div class="small text-muted mt-1"><?= count($outflows) ?> Expense Disbursements</div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="p-3 bg-white rounded border shadow-sm">
      <div class="text-muted small text-uppercase fw-semibold">Net Daily Balance</div>
      <div class="h3 fw-bold mb-0 <?= $netDailyCash >= 0 ? 'text-primary' : 'text-danger' ?>">
        <?= format_currency($netDailyCash) ?>
      </div>
      <div class="small text-muted mt-1">For <?= format_date($selectedDate) ?></div>
    </div>
  </div>
</div>

<!-- Payment Modes Breakdown -->
<div class="admin-card mb-4">
  <div class="admin-card-body p-3">
    <div class="small fw-semibold text-muted mb-2 text-uppercase">Payment Channel Breakdown (<?= format_date($selectedDate) ?>):</div>
    <div class="row g-2">
      <?php foreach ($inflowsByMethod as $m => $val): ?>
        <div class="col-sm">
          <div class="p-2 bg-light rounded border text-center">
            <div class="text-muted small"><?= $m ?></div>
            <div class="fw-bold text-dark"><?= format_currency($val) ?></div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<div class="row g-4">
  <!-- Inflows Table -->
  <div class="col-lg-7">
    <div class="admin-card">
      <div class="admin-card-header bg-white d-flex justify-content-between align-items-center">
        <h3 class="admin-card-title text-success">
          <i class="bi bi-arrow-down-left-circle-fill"></i> Inflows (Patient Collections)
        </h3>
        <span class="badge bg-success-subtle text-success-emphasis"><?= count($inflows) ?> Entries</span>
      </div>
      <div class="admin-card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0 small">
            <thead class="table-light">
              <tr>
                <th>Receipt No</th>
                <th>Patient Details</th>
                <th>Method</th>
                <th class="text-end">Amount</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($inflows)): ?>
                <tr><td colspan="4" class="text-center text-muted py-4">No collections recorded on this date.</td></tr>
              <?php else: ?>
                <?php foreach ($inflows as $in): ?>
                  <tr>
                    <td>
                      <span class="fw-bold font-monospace"><?= e($in['receipt_number']) ?></span>
                      <div class="text-muted" style="font-size:0.7rem;">Inv: <?= e($in['invoice_number']) ?></div>
                    </td>
                    <td>
                      <div class="fw-semibold text-dark"><?= e($in['patient_name']) ?></div>
                      <div class="text-muted" style="font-size:0.7rem;"><?= e($in['patient_code']) ?></div>
                    </td>
                    <td>
                      <span class="badge bg-light text-dark border"><?= e($in['payment_method']) ?></span>
                    </td>
                    <td class="text-end fw-bold text-success">
                      <?= format_currency($in['amount']) ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- Outflows Table -->
  <div class="col-lg-5">
    <div class="admin-card">
      <div class="admin-card-header bg-white d-flex justify-content-between align-items-center">
        <h3 class="admin-card-title text-danger">
          <i class="bi bi-arrow-up-right-circle-fill"></i> Outflows (Expenses)
        </h3>
        <span class="badge bg-danger-subtle text-danger-emphasis"><?= count($outflows) ?> Entries</span>
      </div>
      <div class="admin-card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0 small">
            <thead class="table-light">
              <tr>
                <th>Voucher / Category</th>
                <th>Paid To</th>
                <th class="text-end">Amount</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($outflows)): ?>
                <tr><td colspan="3" class="text-center text-muted py-4">No expenses recorded on this date.</td></tr>
              <?php else: ?>
                <?php foreach ($outflows as $out): ?>
                  <tr>
                    <td>
                      <span class="badge bg-light text-dark border font-monospace"><?= e($out['expense_number']) ?></span>
                      <div class="fw-semibold text-dark mt-1"><?= e($out['category']) ?></div>
                    </td>
                    <td>
                      <div class="text-dark"><?= e($out['paid_to']) ?></div>
                      <span class="badge bg-light text-muted border" style="font-size:0.68rem;"><?= e($out['payment_method']) ?></span>
                    </td>
                    <td class="text-end fw-bold text-danger">
                      <?= format_currency($out['amount']) ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>

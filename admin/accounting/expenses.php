<?php
/**
 * LabMaster - Operational Expense Management
 */

declare(strict_types=1);

$pageTitle = 'Operational Expenses';
$activeNav = 'expenses';

require_once dirname(__DIR__) . '/includes/auth_guard.php';
Auth::requirePermission('accounting.manage');

$categories = [
    'Reagents & Supplies',
    'Biomedical Waste Disposal',
    'Analyzer Maintenance & AMC',
    'Laboratory Utilities (Electricity/Water)',
    'Staff Salaries & Incentives',
    'Rent & Facilities',
    'Stationery & Printing Paper',
    'Quality Control & Calibrators',
    'Miscellaneous'
];

$errors = [];

// Handle Expense Creation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'create_expense') {
        $category = trim($_POST['category'] ?? 'Miscellaneous');
        $amount = (float)($_POST['amount'] ?? 0);
        $paymentMethod = trim($_POST['payment_method'] ?? 'Cash');
        $paidTo = trim($_POST['paid_to'] ?? '');
        $expenseDate = !empty($_POST['expense_date']) ? trim($_POST['expense_date']) : date('Y-m-d');
        $referenceNo = trim($_POST['reference_no'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        $userId = Auth::id();

        if ($amount <= 0) {
            $errors[] = 'Expense amount must be greater than zero.';
        }
        if (empty($paidTo)) {
            $errors[] = 'Please specify the vendor or payee name.';
        }

        if (empty($errors)) {
            $expenseNo = generate_expense_number();
            Database::execute(
                "INSERT INTO `expenses` (`expense_number`, `category`, `amount`, `payment_method`, `paid_to`, `expense_date`, `reference_no`, `notes`, `recorded_by`)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [$expenseNo, $category, $amount, $paymentMethod, $paidTo, $expenseDate, $referenceNo, $notes, $userId]
            );

            log_audit('Expense Recorded', 'Accounting', $expenseNo, "Recorded expense of " . format_currency($amount) . " to {$paidTo} under {$category}.");
            set_flash('success', "Expense voucher {$expenseNo} recorded successfully.");
            header('Location: ' . ADMIN_URL . '/accounting/expenses.php');
            exit;
        }
    }

    if ($action === 'delete_expense') {
        Auth::requirePermission('accounting.manage');
        $expId = (int)($_POST['expense_id'] ?? 0);
        $ex = Database::fetch("SELECT * FROM `expenses` WHERE `id` = ?", [$expId]);
        if ($ex) {
            Database::execute("DELETE FROM `expenses` WHERE `id` = ?", [$expId]);
            log_audit('Expense Deleted', 'Accounting', $ex['expense_number'], "Deleted expense voucher {$ex['expense_number']}.");
            set_flash('success', "Expense voucher \"{$ex['expense_number']}\" deleted successfully.");
        } else {
            set_flash('danger', 'Expense voucher not found.');
        }
        header('Location: ' . ADMIN_URL . '/accounting/expenses.php');
        exit;
    }
}

// Fetch Expenses List
$categoryFilter = trim($_GET['category'] ?? '');
$monthFilter = trim($_GET['month'] ?? date('Y-m'));

$sql = "SELECT e.*, u.full_name as recorder_name 
        FROM `expenses` e 
        LEFT JOIN `users` u ON e.recorded_by = u.id 
        WHERE e.expense_date LIKE ?";
$params = ["{$monthFilter}-%"];

if (!empty($categoryFilter)) {
    $sql .= " AND e.category = ?";
    $params[] = $categoryFilter;
}

$sql .= " ORDER BY e.id DESC";
$expenses = Database::fetchAll($sql, $params);

$totalExpense = 0.0;
foreach ($expenses as $ex) {
    $totalExpense += (float)$ex['amount'];
}

require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
  <div>
    <h2 class="h4 fw-bold mb-1">Operational Expenses & Disbursements</h2>
    <p class="text-muted small mb-0">Record and track laboratory expenditures and operational overheads</p>
  </div>
  <button class="btn btn-primary-lm d-inline-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#newExpenseModal">
    <i class="bi bi-dash-circle"></i> Add Expense Voucher
  </button>
</div>

<?php if (!empty($errors)): ?>
  <div class="alert alert-danger shadow-sm mb-4">
    <ul class="mb-0 small ps-3">
      <?php foreach ($errors as $err): ?>
        <li><?= e($err) ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<!-- Filter & Summary Bar -->
<div class="admin-card mb-4">
  <div class="admin-card-body p-3">
    <form method="GET" action="" class="row g-2 align-items-center">
      <div class="col-md-4">
        <label class="form-label small fw-semibold mb-1">Select Month</label>
        <input type="month" name="month" class="form-control" value="<?= e($monthFilter) ?>">
      </div>
      <div class="col-md-4">
        <label class="form-label small fw-semibold mb-1">Category</label>
        <select name="category" class="form-select">
          <option value="">All Expense Categories</option>
          <?php foreach ($categories as $cat): ?>
            <option value="<?= $cat ?>" <?= $categoryFilter === $cat ? 'selected' : '' ?>><?= $cat ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-4 align-self-end d-flex justify-content-between align-items-center">
        <button type="submit" class="btn btn-primary"><i class="bi bi-funnel"></i> Filter</button>
        <div class="text-end">
          <span class="text-muted small">Month Total:</span>
          <div class="h5 fw-bold mb-0 text-danger"><?= format_currency($totalExpense) ?></div>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- Expenses Table -->
<div class="admin-card">
  <div class="admin-card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>Voucher No</th>
            <th>Date</th>
            <th>Category</th>
            <th>Paid To / Payee</th>
            <th>Method & Ref</th>
            <th>Notes</th>
            <th>Recorded By</th>
            <th class="text-end">Amount</th>
            <th class="text-end text-nowrap pe-3" style="width: 120px;">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($expenses)): ?>
            <tr><td colspan="9" class="text-center text-muted py-5">No expenses recorded for the selected period.</td></tr>
          <?php else: ?>
            <?php foreach ($expenses as $ex): ?>
              <tr>
                <td><span class="badge bg-light text-dark border font-monospace"><?= e($ex['expense_number']) ?></span></td>
                <td class="small"><?= format_date($ex['expense_date']) ?></td>
                <td><span class="badge bg-danger-subtle text-danger-emphasis"><?= e($ex['category']) ?></span></td>
                <td class="fw-bold text-dark"><?= e($ex['paid_to']) ?></td>
                <td>
                  <span class="badge bg-light text-dark border"><?= e($ex['payment_method']) ?></span>
                  <?php if (!empty($ex['reference_no'])): ?>
                    <span class="text-muted small ms-1">(<?= e($ex['reference_no']) ?>)</span>
                  <?php endif; ?>
                </td>
                <td class="small text-muted text-truncate" style="max-width: 200px;"><?= e($ex['notes'] ?: '-') ?></td>
                <td class="small text-muted"><?= e($ex['recorder_name'] ?: 'Staff') ?></td>
                <td class="text-end fw-bold text-danger"><?= format_currency($ex['amount']) ?></td>
                <td class="text-end text-nowrap pe-3">
                  <div class="d-inline-flex align-items-center justify-content-end gap-1">
                    <button type="button" class="btn btn-sm btn-outline-primary btn-action-icon" 
                            onclick="viewExpense(<?= htmlspecialchars(json_encode($ex), ENT_QUOTES, 'UTF-8') ?>)" 
                            title="View Voucher Details">
                      <i class="bi bi-eye"></i>
                    </button>
                    <form method="POST" action="<?= ADMIN_URL ?>/accounting/expenses.php" class="d-inline m-0 p-0" onsubmit="return confirm('Are you sure you want to delete expense voucher <?= e(addslashes($ex['expense_number'])) ?>?');">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="delete_expense">
                      <input type="hidden" name="expense_id" value="<?= $ex['id'] ?>">
                      <button type="submit" class="btn btn-sm btn-outline-danger btn-action-icon" title="Delete Expense Voucher">
                        <i class="bi bi-trash"></i>
                      </button>
                    </form>
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

<!-- Modal: New Expense Voucher -->
<div class="modal fade" id="newExpenseModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="POST" action="">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="create_expense">
        <div class="modal-header">
          <h5 class="modal-title fw-bold">Record Expense Voucher</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label small fw-semibold">Expense Category <span class="text-danger">*</span></label>
              <select name="category" class="form-select" required>
                <?php foreach ($categories as $cat): ?>
                  <option value="<?= $cat ?>"><?= $cat ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-md-6">
              <label class="form-label small fw-semibold">Amount (₹) <span class="text-danger">*</span></label>
              <input type="number" step="0.01" name="amount" class="form-control" placeholder="0.00" required>
            </div>

            <div class="col-md-6">
              <label class="form-label small fw-semibold">Paid To (Vendor / Person) <span class="text-danger">*</span></label>
              <input type="text" name="paid_to" class="form-control" placeholder="e.g. CleanCare Bio-Waste Management" required>
            </div>

            <div class="col-md-6">
              <label class="form-label small fw-semibold">Payment Mode <span class="text-danger">*</span></label>
              <select name="payment_method" class="form-select" required>
                <option value="Cash">Cash</option>
                <option value="UPI">UPI</option>
                <option value="Bank Transfer">Bank Transfer / NEFT</option>
                <option value="Card">Card</option>
                <option value="Cheque">Cheque</option>
              </select>
            </div>

            <div class="col-md-6">
              <label class="form-label small fw-semibold">Expense Date <span class="text-danger">*</span></label>
              <input type="date" name="expense_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
            </div>

            <div class="col-md-6">
              <label class="form-label small fw-semibold">Bill / Reference Number</label>
              <input type="text" name="reference_no" class="form-control" placeholder="e.g. INVOICE-4421">
            </div>

            <div class="col-12">
              <label class="form-label small fw-semibold">Description / Notes</label>
              <textarea name="notes" class="form-control" rows="2" placeholder="Clinical justification or expense purpose"></textarea>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-save">Record Voucher</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal: View Expense Voucher Details -->
<div class="modal fade" id="viewExpenseModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content shadow border-0">
      <div class="modal-header bg-light">
        <h5 class="modal-title fw-bold" id="viewExpTitle">Expense Voucher</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <table class="table table-sm table-borderless mb-0">
          <tbody>
            <tr><th class="text-muted w-35">Voucher No:</th><td id="expVoucher" class="font-monospace fw-bold text-dark"></td></tr>
            <tr><th class="text-muted">Date:</th><td id="expDate"></td></tr>
            <tr><th class="text-muted">Category:</th><td id="expCat"></td></tr>
            <tr><th class="text-muted">Paid To / Payee:</th><td id="expPayee" class="fw-semibold"></td></tr>
            <tr><th class="text-muted">Amount:</th><td id="expAmt" class="fw-bold text-danger"></td></tr>
            <tr><th class="text-muted">Payment Mode:</th><td id="expMode"></td></tr>
            <tr><th class="text-muted">Reference / Bill:</th><td id="expRef"></td></tr>
            <tr><th class="text-muted">Recorded By:</th><td id="expRecorder"></td></tr>
            <tr><th class="text-muted">Description / Notes:</th><td id="expNotes"></td></tr>
          </tbody>
        </table>
      </div>
      <div class="modal-footer bg-light py-2">
        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<script>
function viewExpense(e) {
  document.getElementById('viewExpTitle').innerText = 'Expense Voucher ' + (e.expense_number || '');
  document.getElementById('expVoucher').innerText = e.expense_number || '';
  document.getElementById('expDate').innerText = e.expense_date || '';
  document.getElementById('expCat').innerHTML = '<span class="badge bg-danger-subtle text-danger-emphasis">' + (e.category || '') + '</span>';
  document.getElementById('expPayee').innerText = e.paid_to || '';
  document.getElementById('expAmt').innerText = '₹' + parseFloat(e.amount || 0).toLocaleString('en-IN', {minimumFractionDigits: 2});
  document.getElementById('expMode').innerText = e.payment_method || 'Cash';
  document.getElementById('expRef').innerText = e.reference_no || '-';
  document.getElementById('expRecorder').innerText = e.recorder_name || 'Staff';
  document.getElementById('expNotes').innerText = e.notes || '-';

  new bootstrap.Modal(document.getElementById('viewExpenseModal')).show();
}
</script>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>

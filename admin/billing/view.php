<?php
/**
 * LabMaster - Invoice Details & Payment Collection Ledger
 */

declare(strict_types=1);

$invoiceId = (int)($_GET['id'] ?? 0);
$pageTitle = 'Invoice Details';
$activeNav = 'billing';

require_once dirname(__DIR__) . '/includes/auth_guard.php';
Auth::requirePermission('billing.manage');

$invoice = Database::fetch(
    "SELECT i.*, p.full_name as patient_name, p.patient_id as patient_code, p.phone, p.address,
            o.accession_number, o.order_date,
            u.full_name as creator_name
     FROM `invoices` i
     JOIN `patients` p ON i.patient_id = p.id
     JOIN `test_orders` o ON i.order_id = o.id
     LEFT JOIN `users` u ON i.created_by = u.id
     WHERE i.id = ?",
    [$invoiceId]
);

if (!$invoice) {
    set_flash('danger', 'Invoice not found.');
    header('Location: ' . ADMIN_URL . '/billing/index.php');
    exit;
}

// Fetch invoice items
$items = Database::fetchAll("SELECT * FROM `invoice_items` WHERE `invoice_id` = ?", [$invoiceId]);

// Fetch payments recorded
$payments = Database::fetchAll(
    "SELECT p.*, u.full_name as receiver_name 
     FROM `payments` p 
     LEFT JOIN `users` u ON p.received_by = u.id 
     WHERE p.invoice_id = ? 
     ORDER BY p.id ASC",
    [$invoiceId]
);

// Handle Payment Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'record_payment') {
        $amount = (float)($_POST['amount'] ?? 0);
        $method = trim($_POST['payment_method'] ?? 'Cash');
        $reference = trim($_POST['transaction_reference'] ?? '');
        $paymentDate = !empty($_POST['payment_date']) ? trim($_POST['payment_date']) : date('Y-m-d');
        $notes = trim($_POST['notes'] ?? '');
        $userId = Auth::id();

        if ($amount <= 0) {
            set_flash('danger', 'Payment amount must be greater than zero.');
        } elseif ($amount > (float)$invoice['balance_amount']) {
            set_flash('danger', 'Payment amount cannot exceed the remaining balance of ' . format_currency($invoice['balance_amount']));
        } else {
            $db = Database::getInstance();
            $db->beginTransaction();

            try {
                $receiptNo = generate_receipt_number();

                // 1. Insert payment record
                Database::execute(
                    "INSERT INTO `payments` (`receipt_number`, `invoice_id`, `amount`, `payment_method`, `transaction_reference`, `payment_date`, `notes`, `received_by`)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                    [$receiptNo, $invoiceId, $amount, $method, $reference, $paymentDate, $notes, $userId]
                );

                // 2. Recalculate invoice totals
                $newPaid = (float)$invoice['paid_amount'] + $amount;
                $newBalance = max(0.0, (float)$invoice['grand_total'] - $newPaid);
                $newStatus = $newBalance <= 0.0 ? 'Paid' : 'Partially Paid';

                Database::execute(
                    "UPDATE `invoices` SET `paid_amount` = ?, `balance_amount` = ?, `payment_status` = ? WHERE `id` = ?",
                    [$newPaid, $newBalance, $newStatus, $invoiceId]
                );

                // 3. Update order payment status
                Database::execute(
                    "UPDATE `test_orders` SET `payment_status` = ? WHERE `id` = ?",
                    [$newStatus, $invoice['order_id']]
                );

                $db->commit();
                log_audit('Payment Recorded', 'Billing', $receiptNo, "Recorded payment of " . format_currency($amount) . " via {$method} for invoice {$invoice['invoice_number']}.");
                set_flash('success', "Payment of " . format_currency($amount) . " recorded. Receipt {$receiptNo} generated.");
                header("Location: " . ADMIN_URL . "/billing/view.php?id={$invoiceId}");
                exit;
            } catch (Exception $e) {
                $db->rollBack();
                set_flash('danger', 'Error recording payment: ' . $e->getMessage());
            }
        }
    }
}

require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
  <div>
    <h2 class="h4 fw-bold mb-1">
      Invoice: <?= e($invoice['invoice_number']) ?>
      <span class="badge bg-light text-dark border ms-2"><?= e($invoice['payment_status']) ?></span>
    </h2>
    <p class="text-muted small mb-0">Order Accession: <strong><?= e($invoice['accession_number']) ?></strong> • Billed on <?= format_date($invoice['invoice_date']) ?></p>
  </div>
  <div class="d-flex gap-2">
    <?php if ((float)$invoice['balance_amount'] > 0): ?>
      <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#paymentModal">
        <i class="bi bi-cash-stack me-1"></i> Collect Payment
      </button>
    <?php endif; ?>
    <a href="<?= ADMIN_URL ?>/billing/print_invoice.php?id=<?= $invoiceId ?>" target="_blank" class="btn btn-outline-secondary btn-sm">
      <i class="bi bi-printer me-1"></i> Print Tax Invoice
    </a>
    <a href="<?= ADMIN_URL ?>/billing/index.php" class="btn btn-light btn-sm">
      <i class="bi bi-arrow-left me-1"></i> Back to Invoices
    </a>
  </div>
</div>

<!-- Invoice Overview Card -->
<div class="admin-card mb-4 bg-light">
  <div class="admin-card-body p-3">
    <div class="row g-3">
      <div class="col-md-4">
        <label class="text-muted small">Billed To (Patient):</label>
        <div class="fw-bold fs-6 text-dark"><?= e($invoice['patient_name']) ?></div>
        <div class="small text-muted font-monospace"><?= e($invoice['patient_code']) ?> • <?= e($invoice['phone']) ?></div>
        <?php if (!empty($invoice['address'])): ?>
          <div class="small text-muted text-truncate"><?= e($invoice['address']) ?></div>
        <?php endif; ?>
      </div>
      <div class="col-md-4">
        <label class="text-muted small">Billing Particulars:</label>
        <div class="small">Order Date: <strong><?= format_date($invoice['order_date']) ?></strong></div>
        <div class="small">Billed By: <strong><?= e($invoice['creator_name'] ?: 'Billing Desk') ?></strong></div>
      </div>
      <div class="col-md-4 text-md-end">
        <label class="text-muted small">Outstanding Balance Due:</label>
        <div class="h4 fw-bold mb-0 <?= $invoice['balance_amount'] > 0 ? 'text-danger' : 'text-success' ?>">
          <?= format_currency($invoice['balance_amount']) ?>
        </div>
        <div class="small text-muted">Paid: <?= format_currency($invoice['paid_amount']) ?> of <?= format_currency($invoice['grand_total']) ?></div>
      </div>
    </div>
  </div>
</div>

<div class="row g-4">
  <!-- Line Items Table -->
  <div class="col-lg-7">
    <div class="admin-card">
      <div class="admin-card-header bg-white">
        <h3 class="admin-card-title text-primary">
          <i class="bi bi-list-check"></i> Invoiced Pathology Investigations
        </h3>
      </div>
      <div class="admin-card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0 small">
            <thead class="table-light">
              <tr>
                <th>#</th>
                <th>Investigation Item Description</th>
                <th class="text-center">Qty</th>
                <th class="text-end">Unit Price</th>
                <th class="text-end">Total</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($items as $idx => $it): ?>
                <tr>
                  <td><?= $idx + 1 ?></td>
                  <td class="fw-semibold text-dark"><?= e($it['item_description']) ?></td>
                  <td class="text-center"><?= $it['quantity'] ?></td>
                  <td class="text-end"><?= format_currency($it['unit_price']) ?></td>
                  <td class="text-end fw-bold"><?= format_currency($it['total_amount']) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
            <tfoot class="table-light">
              <tr>
                <td colspan="4" class="text-end fw-semibold">Subtotal:</td>
                <td class="text-end fw-bold"><?= format_currency($invoice['subtotal']) ?></td>
              </tr>
              <?php if ((float)$invoice['discount_amount'] > 0): ?>
              <tr>
                <td colspan="4" class="text-end text-success fw-semibold">Discount Applied:</td>
                <td class="text-end text-success fw-bold">- <?= format_currency($invoice['discount_amount']) ?></td>
              </tr>
              <?php endif; ?>
              <tr class="fs-6">
                <td colspan="4" class="text-end fw-bold text-dark">Grand Total:</td>
                <td class="text-end fw-bold text-primary"><?= format_currency($invoice['grand_total']) ?></td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- Payments Ledger -->
  <div class="col-lg-5">
    <div class="admin-card">
      <div class="admin-card-header bg-white d-flex justify-content-between align-items-center">
        <h3 class="admin-card-title text-success">
          <i class="bi bi-receipt"></i> Payment Receipts
        </h3>
        <?php if ((float)$invoice['balance_amount'] > 0): ?>
          <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#paymentModal">
            <i class="bi bi-plus-lg me-1"></i> Add Payment
          </button>
        <?php endif; ?>
      </div>
      <div class="admin-card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0 small">
            <thead class="table-light">
              <tr>
                <th>Receipt No</th>
                <th>Method</th>
                <th>Date</th>
                <th class="text-end">Amount</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($payments)): ?>
                <tr><td colspan="4" class="text-center text-muted py-4">No payments recorded yet for this bill.</td></tr>
              <?php else: ?>
                <?php foreach ($payments as $pay): ?>
                  <tr>
                    <td>
                      <span class="fw-bold font-monospace"><?= e($pay['receipt_number']) ?></span>
                      <?php if (!empty($pay['transaction_reference'])): ?>
                        <div class="text-muted" style="font-size:0.7rem;">Ref: <?= e($pay['transaction_reference']) ?></div>
                      <?php endif; ?>
                    </td>
                    <td>
                      <span class="badge bg-light text-dark border"><?= e($pay['payment_method']) ?></span>
                    </td>
                    <td><?= format_date($pay['payment_date']) ?></td>
                    <td class="text-end fw-bold text-success"><?= format_currency($pay['amount']) ?></td>
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

<!-- Modal: Record Payment -->
<div class="modal fade" id="paymentModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" action="">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="record_payment">
        <div class="modal-header">
          <h5 class="modal-title fw-bold">Record Payment Receipt</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label small fw-semibold">Amount to Collect (₹) <span class="text-danger">*</span></label>
            <input type="number" step="0.01" name="amount" class="form-control fs-5 fw-bold text-success" value="<?= $invoice['balance_amount'] ?>" max="<?= $invoice['balance_amount'] ?>" required>
            <div class="form-text">Remaining balance due: <?= format_currency($invoice['balance_amount']) ?></div>
          </div>

          <div class="mb-3">
            <label class="form-label small fw-semibold">Payment Mode <span class="text-danger">*</span></label>
            <select name="payment_method" class="form-select" required>
              <option value="Cash">Cash</option>
              <option value="UPI">UPI (Google Pay / PhonePe / Paytm)</option>
              <option value="Card">Debit / Credit Card (POS)</option>
              <option value="Bank Transfer">Bank Transfer / NEFT / IMPS</option>
              <option value="Cheque">Cheque</option>
            </select>
          </div>

          <div class="mb-3">
            <label class="form-label small fw-semibold">Payment Date</label>
            <input type="date" name="payment_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
          </div>

          <div class="mb-3">
            <label class="form-label small fw-semibold">Transaction / Reference ID (Optional)</label>
            <input type="text" name="transaction_reference" class="form-control" placeholder="e.g. UPI Ref / Card Txn No / Cheque No">
          </div>

          <div class="mb-3">
            <label class="form-label small fw-semibold">Cashier Notes</label>
            <input type="text" name="notes" class="form-control" placeholder="Optional payment comments">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-save">
            <i class="bi bi-check2-circle me-1"></i> Confirm & Generate Receipt
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>

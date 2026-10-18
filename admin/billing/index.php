<?php
/**
 * LabMaster - Laboratory Invoices & Billing Directory
 */

declare(strict_types=1);

$pageTitle = 'Invoices & Billing';
$activeNav = 'billing';

require_once dirname(__DIR__) . '/includes/auth_guard.php';
Auth::requirePermission('billing.manage');

$statusFilter = trim($_GET['status'] ?? '');
$search = trim($_GET['search'] ?? '');

$sql = "SELECT i.*, p.full_name as patient_name, p.patient_id as patient_code, p.phone,
               o.accession_number
        FROM `invoices` i
        JOIN `patients` p ON i.patient_id = p.id
        JOIN `test_orders` o ON i.order_id = o.id
        WHERE 1=1";
$params = [];

if (!empty($statusFilter)) {
    $sql .= " AND i.payment_status = ?";
    $params[] = $statusFilter;
}
if (!empty($search)) {
    $sql .= " AND (i.invoice_number LIKE ? OR p.full_name LIKE ? OR p.patient_id LIKE ? OR o.accession_number LIKE ?)";
    $like = "%{$search}%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

// Handle POST Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'cancel_invoice') {
        Auth::requirePermission('billing.manage');
        $invoiceId = (int)($_POST['invoice_id'] ?? 0);
        $inv = Database::fetch("SELECT * FROM `invoices` WHERE `id` = ?", [$invoiceId]);
        if ($inv) {
            Database::execute("UPDATE `invoices` SET `payment_status` = 'Cancelled' WHERE `id` = ?", [$invoiceId]);
            log_audit('Invoice Cancelled', 'Billing', $inv['invoice_number'], "Voided/Cancelled invoice {$inv['invoice_number']}.");
            set_flash('success', "Invoice \"{$inv['invoice_number']}\" marked as Cancelled.");
            header('Location: ' . ADMIN_URL . '/billing/index.php');
            exit;
        }
    }
}

// Financial aggregates
$totalBilled = (float)(Database::fetch("SELECT COALESCE(SUM(grand_total), 0) as s FROM `invoices` WHERE `payment_status` != 'Cancelled'")['s'] ?? 0);
$totalCollected = (float)(Database::fetch("SELECT COALESCE(SUM(paid_amount), 0) as s FROM `invoices` WHERE `payment_status` != 'Cancelled'")['s'] ?? 0);
$totalOutstanding = (float)(Database::fetch("SELECT COALESCE(SUM(balance_amount), 0) as s FROM `invoices` WHERE `payment_status` IN ('Unpaid', 'Partially Paid')")['s'] ?? 0);

require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
  <div>
    <h2 class="h4 fw-bold mb-1">Laboratory Billing & Patient Invoices</h2>
    <p class="text-muted small mb-0">Manage payments, partial installments, discounts, and receipts</p>
  </div>
</div>

<!-- Financial Summary Cards -->
<div class="row g-3 mb-4">
  <div class="col-md-4">
    <div class="p-3 bg-white rounded border shadow-sm">
      <div class="text-muted small text-uppercase fw-semibold">Total Invoiced Value</div>
      <div class="h4 fw-bold mb-0 text-dark"><?= format_currency($totalBilled) ?></div>
      <div class="small text-muted"><?= count($invoices) ?> Total Bills Issued</div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="p-3 bg-white rounded border shadow-sm">
      <div class="text-muted small text-uppercase fw-semibold">Total Revenue Collected</div>
      <div class="h4 fw-bold mb-0 text-success"><?= format_currency($totalCollected) ?></div>
      <div class="small text-muted">Cash, UPI & Bank Receipts</div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="p-3 bg-white rounded border shadow-sm">
      <div class="text-muted small text-uppercase fw-semibold">Outstanding Patient Dues</div>
      <div class="h4 fw-bold mb-0 text-danger"><?= format_currency($totalOutstanding) ?></div>
      <div class="small text-muted">Awaiting Settlement</div>
    </div>
  </div>
</div>

<!-- Status Filters -->
<div class="d-flex flex-wrap gap-2 mb-3">
  <a href="?status=" class="btn btn-sm <?= empty($statusFilter) ? 'btn-primary-lm' : 'btn-outline-secondary' ?>">All Invoices</a>
  <a href="?status=Paid" class="btn btn-sm <?= $statusFilter === 'Paid' ? 'btn-success text-white' : 'btn-outline-secondary' ?>">Paid</a>
  <a href="?status=Partially Paid" class="btn btn-sm <?= $statusFilter === 'Partially Paid' ? 'btn-info text-dark' : 'btn-outline-secondary' ?>">Partially Paid</a>
  <a href="?status=Unpaid" class="btn btn-sm <?= $statusFilter === 'Unpaid' ? 'btn-danger text-white' : 'btn-outline-secondary' ?>">Unpaid</a>
</div>

<!-- Invoices Table -->
<div class="admin-card">
  <div class="admin-card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>Invoice No</th>
            <th>Patient Details</th>
            <th>Accession</th>
            <th>Invoice Date</th>
            <th>Grand Total</th>
            <th>Paid</th>
            <th>Balance</th>
            <th>Status</th>
            <th class="text-end text-nowrap pe-3" style="width: 140px;">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($invoices)): ?>
            <tr><td colspan="9" class="text-center text-muted py-5">No billing invoices found.</td></tr>
          <?php else: ?>
            <?php foreach ($invoices as $inv): ?>
              <tr>
                <td>
                  <a href="<?= ADMIN_URL ?>/billing/view.php?id=<?= $inv['id'] ?>" class="fw-bold font-monospace text-primary text-decoration-none">
                    <?= e($inv['invoice_number']) ?>
                  </a>
                </td>
                <td>
                  <div class="fw-bold text-dark"><?= e($inv['patient_name']) ?></div>
                  <div class="small text-muted font-monospace"><?= e($inv['patient_code']) ?></div>
                </td>
                <td>
                  <span class="badge bg-light text-dark border font-monospace"><?= e($inv['accession_number']) ?></span>
                </td>
                <td class="small"><?= format_date($inv['invoice_date']) ?></td>
                <td class="fw-bold"><?= format_currency($inv['grand_total']) ?></td>
                <td class="text-success fw-semibold"><?= format_currency($inv['paid_amount']) ?></td>
                <td class="<?= $inv['balance_amount'] > 0 ? 'text-danger fw-bold' : 'text-muted' ?>">
                  <?= format_currency($inv['balance_amount']) ?>
                </td>
                <td>
                  <?php
                  $pBadge = match ($inv['payment_status']) {
                      'Paid' => 'bg-success-subtle text-success-emphasis border border-success',
                      'Partially Paid' => 'bg-info-subtle text-info-emphasis border border-info',
                      'Unpaid' => 'bg-danger-subtle text-danger-emphasis border border-danger',
                      default => 'bg-secondary'
                  };
                  ?>
                  <span class="badge <?= $pBadge ?>"><?= e($inv['payment_status']) ?></span>
                </td>
                <td class="text-end text-nowrap pe-3">
                  <div class="d-inline-flex align-items-center justify-content-end gap-1">
                    <a href="<?= ADMIN_URL ?>/billing/view.php?id=<?= $inv['id'] ?>" class="btn btn-sm btn-outline-primary btn-action-icon" title="View Invoice & Payments">
                      <i class="bi bi-eye"></i>
                    </a>
                    <a href="<?= ADMIN_URL ?>/billing/print_invoice.php?id=<?= $inv['id'] ?>" target="_blank" class="btn btn-sm btn-outline-secondary btn-action-icon" title="Print Invoice">
                      <i class="bi bi-printer"></i>
                    </a>
                    <?php if ($inv['payment_status'] !== 'Cancelled'): ?>
                    <form method="POST" action="<?= ADMIN_URL ?>/billing/index.php" class="d-inline m-0 p-0" onsubmit="return confirm('Cancel or void invoice <?= e($inv['invoice_number']) ?>?');">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="cancel_invoice">
                      <input type="hidden" name="invoice_id" value="<?= $inv['id'] ?>">
                      <button type="submit" class="btn btn-sm btn-outline-danger btn-action-icon" title="Void / Cancel Invoice">
                        <i class="bi bi-trash"></i>
                      </button>
                    </form>
                    <?php endif; ?>
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

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>

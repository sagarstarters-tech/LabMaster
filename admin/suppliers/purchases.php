<?php
/**
 * LabMaster - Supplier Purchases Invoices Directory
 */

declare(strict_types=1);

$pageTitle = 'Supplier Purchases';
$activeNav = 'purchases';

require_once dirname(__DIR__) . '/includes/auth_guard.php';
Auth::requirePermission('suppliers.manage');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_purchase') {
    require_csrf();
    $purId = (int)($_POST['purchase_id'] ?? 0);
    $pur = Database::fetch("SELECT * FROM `purchases` WHERE `id` = ?", [$purId]);
    if ($pur) {
        try {
            Database::execute("DELETE FROM `purchase_items` WHERE `purchase_id` = ?", [$purId]);
            Database::execute("DELETE FROM `purchases` WHERE `id` = ?", [$purId]);
            set_flash('success', 'Purchase invoice record deleted successfully.');
        } catch (\PDOException $e) {
            set_flash('danger', 'Cannot delete purchase invoice linked to active inventory movements.');
        }
    }
    header('Location: ' . ADMIN_URL . '/suppliers/purchases.php');
    exit;
}

$purchases = Database::fetchAll(
    "SELECT p.*, s.company_name as supplier_name, u.full_name as creator_name,
            (SELECT COUNT(*) FROM `purchase_items` pi WHERE pi.purchase_id = p.id) as item_count
     FROM `purchases` p
     JOIN `suppliers` s ON p.supplier_id = s.id
     LEFT JOIN `users` u ON p.created_by = u.id
     ORDER BY p.id DESC"
);

require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
  <div>
    <h2 class="h4 fw-bold mb-1">Supplier Purchases & Goods Receipt</h2>
    <p class="text-muted small mb-0">Record of all inventory stock acquisitions and vendor invoices</p>
  </div>
  <a href="<?= ADMIN_URL ?>/suppliers/create_purchase.php" class="btn btn-primary-lm d-inline-flex align-items-center gap-2">
    <i class="bi bi-cart-plus"></i> New Purchase Order
  </a>
</div>

<div class="admin-card">
  <div class="admin-card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>Purchase No</th>
            <th>Supplier</th>
            <th>Vendor Invoice</th>
            <th>Date</th>
            <th>Total Amount</th>
            <th>Paid</th>
            <th>Balance Due</th>
            <th>Status</th>
            <th class="text-end text-nowrap pe-3" style="width: 140px;">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($purchases)): ?>
            <tr><td colspan="9" class="text-center text-muted py-5">No purchase invoices recorded yet.</td></tr>
          <?php else: ?>
            <?php foreach ($purchases as $pur): ?>
              <tr>
                <td><span class="badge bg-light text-dark border font-monospace"><?= e($pur['purchase_number']) ?></span></td>
                <td>
                  <div class="fw-bold text-dark"><?= e($pur['supplier_name']) ?></div>
                  <span class="text-muted small"><?= $pur['item_count'] ?> items received</span>
                </td>
                <td><?= e($pur['invoice_number'] ?: 'Direct Entry') ?></td>
                <td class="small"><?= format_date($pur['purchase_date']) ?></td>
                <td class="fw-bold"><?= format_currency($pur['total_amount']) ?></td>
                <td class="text-success fw-semibold"><?= format_currency($pur['paid_amount']) ?></td>
                <td class="<?= $pur['balance_amount'] > 0 ? 'text-danger fw-bold' : 'text-muted' ?>">
                  <?= format_currency($pur['balance_amount']) ?>
                </td>
                <td>
                  <span class="badge <?= $pur['payment_status'] === 'Paid' ? 'bg-success' : ($pur['payment_status'] === 'Partially Paid' ? 'bg-warning text-dark' : 'bg-danger') ?>">
                    <?= e($pur['payment_status']) ?>
                  </span>
                </td>
                <td class="text-end text-nowrap pe-3">
                  <div class="d-inline-flex align-items-center justify-content-end gap-1">
                    <button type="button" class="btn btn-sm btn-outline-primary btn-action-icon" 
                            onclick="viewPurchase(<?= htmlspecialchars(json_encode($pur), ENT_QUOTES, 'UTF-8') ?>)" 
                            title="View Purchase Details">
                      <i class="bi bi-eye"></i>
                    </button>
                    <a href="<?= ADMIN_URL ?>/suppliers/create_purchase.php?supplier_id=<?= $pur['supplier_id'] ?>" 
                       class="btn btn-sm btn-outline-secondary btn-action-icon" 
                       title="New Purchase with Supplier">
                      <i class="bi bi-pencil"></i>
                    </a>
                    <form method="POST" action="<?= ADMIN_URL ?>/suppliers/purchases.php" class="d-inline m-0 p-0" onsubmit="return confirm('Are you sure you want to delete purchase record <?= e(addslashes($pur['purchase_number'])) ?>?');">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="delete_purchase">
                      <input type="hidden" name="purchase_id" value="<?= $pur['id'] ?>">
                      <button type="submit" class="btn btn-sm btn-outline-danger btn-action-icon" title="Delete Purchase Record">
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

<!-- Modal: View Purchase Record Details -->
<div class="modal fade" id="viewPurchaseModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content shadow border-0">
      <div class="modal-header bg-light">
        <h5 class="modal-title fw-bold" id="viewPurTitle">Purchase Order Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <table class="table table-sm table-borderless mb-0">
          <tbody>
            <tr><th class="text-muted w-35">Purchase No:</th><td id="purNo" class="font-monospace fw-bold text-dark"></td></tr>
            <tr><th class="text-muted">Supplier:</th><td id="purSupplier" class="fw-semibold"></td></tr>
            <tr><th class="text-muted">Vendor Invoice:</th><td id="purInv"></td></tr>
            <tr><th class="text-muted">Purchase Date:</th><td id="purDate"></td></tr>
            <tr><th class="text-muted">Items Count:</th><td id="purItems"></td></tr>
            <tr><th class="text-muted">Total Amount:</th><td id="purTotal" class="fw-bold"></td></tr>
            <tr><th class="text-muted">Paid Amount:</th><td id="purPaid" class="text-success fw-bold"></td></tr>
            <tr><th class="text-muted">Balance Due:</th><td id="purDue" class="text-danger fw-bold"></td></tr>
            <tr><th class="text-muted">Payment Status:</th><td id="purStatus"></td></tr>
            <tr><th class="text-muted">Recorded By:</th><td id="purCreator"></td></tr>
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
function viewPurchase(p) {
  document.getElementById('viewPurTitle').innerText = 'Purchase ' + (p.purchase_number || '');
  document.getElementById('purNo').innerText = p.purchase_number || '';
  document.getElementById('purSupplier').innerText = p.supplier_name || '';
  document.getElementById('purInv').innerText = p.invoice_number || 'Direct Entry';
  document.getElementById('purDate').innerText = p.purchase_date || '';
  document.getElementById('purItems').innerText = (p.item_count || 1) + ' received item(s)';
  document.getElementById('purTotal').innerText = '₹' + parseFloat(p.total_amount || 0).toLocaleString('en-IN', {minimumFractionDigits: 2});
  document.getElementById('purPaid').innerText = '₹' + parseFloat(p.paid_amount || 0).toLocaleString('en-IN', {minimumFractionDigits: 2});
  document.getElementById('purDue').innerText = '₹' + parseFloat(p.balance_amount || 0).toLocaleString('en-IN', {minimumFractionDigits: 2});
  document.getElementById('purStatus').innerHTML = '<span class="badge bg-primary">' + (p.payment_status || 'Pending') + '</span>';
  document.getElementById('purCreator').innerText = p.creator_name || 'Staff';

  new bootstrap.Modal(document.getElementById('viewPurchaseModal')).show();
}
</script>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>

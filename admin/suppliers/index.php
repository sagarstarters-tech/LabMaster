<?php
/**
 * LabMaster - Reagent Suppliers Master
 */

declare(strict_types=1);

$pageTitle = 'Suppliers Directory';
$activeNav = 'suppliers';

require_once dirname(__DIR__) . '/includes/auth_guard.php';
Auth::requirePermission('suppliers.manage');

// Handle Supplier Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? 'add_supplier';

    if ($action === 'add_supplier' || !isset($_POST['action'])) {
        $companyName = trim($_POST['company_name'] ?? '');
        $contactPerson = trim($_POST['contact_person'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $gstin = trim($_POST['gstin'] ?? '');

        if (empty($companyName) || empty($phone)) {
            set_flash('danger', 'Company name and contact phone are required.');
        } else {
            $cnt = (int)(Database::fetch("SELECT COUNT(*) as c FROM `suppliers`")['c'] ?? 0);
            $supplierCode = sprintf('SUP-%03d', $cnt + 1);

            Database::execute(
                "INSERT INTO `suppliers` (`supplier_code`, `company_name`, `contact_person`, `phone`, `email`, `address`, `gstin`, `current_balance`, `is_active`)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 0.00, 1)",
                [$supplierCode, $companyName, $contactPerson, $phone, $email, $address, $gstin]
            );

            log_audit('Supplier Created', 'Suppliers', $supplierCode, "Added supplier {$companyName}.");
            set_flash('success', "Supplier {$companyName} registered successfully.");
            header('Location: ' . ADMIN_URL . '/suppliers/index.php');
            exit;
        }
    } elseif ($action === 'edit_supplier') {
        $supId = (int)($_POST['supplier_id'] ?? 0);
        $companyName = trim($_POST['company_name'] ?? '');
        $contactPerson = trim($_POST['contact_person'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $gstin = trim($_POST['gstin'] ?? '');
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if ($supId <= 0 || empty($companyName) || empty($phone)) {
            set_flash('danger', 'Valid supplier, company name and contact phone are required.');
        } else {
            Database::execute(
                "UPDATE `suppliers` 
                 SET `company_name` = ?, `contact_person` = ?, `phone` = ?, `email` = ?, `address` = ?, `gstin` = ?, `is_active` = ?
                 WHERE `id` = ?",
                [$companyName, $contactPerson, $phone, $email, $address, $gstin, $isActive, $supId]
            );

            log_audit('Supplier Updated', 'Suppliers', (string)$supId, "Updated supplier details for {$companyName}.");
            set_flash('success', "Supplier {$companyName} details updated successfully.");
            header('Location: ' . ADMIN_URL . '/suppliers/index.php');
            exit;
        }
    } elseif ($action === 'delete_supplier') {
        $supId = (int)($_POST['supplier_id'] ?? 0);
        $target = Database::fetch("SELECT * FROM `suppliers` WHERE `id` = ?", [$supId]);

        if (!$target) {
            set_flash('danger', 'Supplier not found.');
        } else {
            $purchaseCount = (int)(Database::fetch("SELECT COUNT(*) as c FROM `purchases` WHERE `supplier_id` = ?", [$supId])['c'] ?? 0);
            $batchCount = (int)(Database::fetch("SELECT COUNT(*) as c FROM `inventory_batches` WHERE `supplier_id` = ?", [$supId])['c'] ?? 0);

            if ($purchaseCount > 0 || $batchCount > 0) {
                Database::execute("UPDATE `suppliers` SET `is_active` = 0 WHERE `id` = ?", [$supId]);
                log_audit('Supplier Deactivated', 'Suppliers', $target['supplier_code'], "Deactivated supplier {$target['company_name']} with existing purchase/inventory history.");
                set_flash('warning', "Supplier {$target['company_name']} has active invoices/batches. To maintain financial and stock integrity, the supplier has been deactivated instead of deleted.");
            } else {
                Database::execute("DELETE FROM `suppliers` WHERE `id` = ?", [$supId]);
                log_audit('Supplier Deleted', 'Suppliers', $target['supplier_code'], "Deleted supplier {$target['company_name']}.");
                set_flash('success', "Supplier {$target['company_name']} ({$target['supplier_code']}) deleted successfully.");
            }
        }
        header('Location: ' . ADMIN_URL . '/suppliers/index.php');
        exit;
    }
}

$suppliers = Database::fetchAll(
    "SELECT s.*, 
            (SELECT COUNT(*) FROM `purchases` p WHERE p.supplier_id = s.id) as purchase_count,
            (SELECT COALESCE(SUM(p.balance_amount), 0) FROM `purchases` p WHERE p.supplier_id = s.id) as total_payable
     FROM `suppliers` s
     ORDER BY s.company_name ASC"
);

require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
  <div>
    <h2 class="h4 fw-bold mb-1">Reagent & Consumable Suppliers</h2>
    <p class="text-muted small mb-0">Authorized diagnostic distributors, equipment vendors, and payables</p>
  </div>
  <div class="d-flex gap-2">
    <a href="<?= ADMIN_URL ?>/suppliers/create_purchase.php" class="btn btn-primary-lm d-inline-flex align-items-center gap-1">
      <i class="bi bi-cart-plus"></i> New Purchase Order
    </a>
    <button class="btn btn-outline-primary d-inline-flex align-items-center gap-1" data-bs-toggle="modal" data-bs-target="#newSupplierModal">
      <i class="bi bi-person-plus"></i> Add Supplier
    </button>
  </div>
</div>

<div class="admin-card">
  <div class="admin-card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th style="width: 85px;">Code</th>
            <th>Company Name</th>
            <th>Contact Person</th>
            <th>Phone & Email</th>
            <th>GSTIN</th>
            <th class="text-center">Orders</th>
            <th class="text-end">Payable</th>
            <th class="text-center">Status</th>
            <th class="text-end text-nowrap pe-3" style="width: 120px;">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($suppliers as $sup): ?>
            <tr>
              <td><span class="badge bg-light text-dark border font-monospace"><?= e($sup['supplier_code']) ?></span></td>
              <td>
                <div class="fw-bold text-dark"><?= e($sup['company_name']) ?></div>
                <div class="text-muted small text-truncate" style="max-width:250px;"><?= e($sup['address'] ?: 'N/A') ?></div>
              </td>
              <td><?= e($sup['contact_person'] ?: 'Sales Representative') ?></td>
              <td>
                <div><i class="bi bi-telephone me-1 text-muted"></i><?= e($sup['phone']) ?></div>
                <div class="text-muted small"><?= e($sup['email'] ?: '-') ?></div>
              </td>
              <td><span class="badge bg-light text-secondary font-monospace"><?= e($sup['gstin'] ?: 'N/A') ?></span></td>
              <td class="text-center">
                <span class="badge bg-info-subtle text-info-emphasis"><?= $sup['purchase_count'] ?> Invoices</span>
              </td>
              <td class="text-end">
                <span class="fw-bold <?= $sup['total_payable'] > 0 ? 'text-danger' : 'text-success' ?>">
                  <?= format_currency($sup['total_payable']) ?>
                </span>
              </td>
              <td class="text-center">
                <span class="badge <?= $sup['is_active'] ? 'bg-success-subtle text-success-emphasis' : 'bg-secondary' ?>">
                  <?= $sup['is_active'] ? 'Active' : 'Inactive' ?>
                </span>
              </td>
              <td class="text-end text-nowrap pe-3">
                <div class="d-inline-flex align-items-center justify-content-end gap-1">
                  <!-- View Invoices Button -->
                  <a href="<?= ADMIN_URL ?>/suppliers/purchases.php" 
                     class="btn btn-sm btn-outline-primary btn-action-icon" 
                     title="View Invoices">
                    <i class="bi bi-eye"></i>
                  </a>

                  <!-- Edit Supplier Button -->
                  <button type="button" 
                          class="btn btn-sm btn-outline-secondary btn-action-icon" 
                          onclick='openEditSupplierModal(<?= htmlspecialchars(json_encode($sup), ENT_QUOTES, "UTF-8") ?>)' 
                          title="Edit Supplier">
                    <i class="bi bi-pencil"></i>
                  </button>

                  <!-- Delete Supplier Button -->
                  <form method="POST" action="<?= ADMIN_URL ?>/suppliers/index.php" class="d-inline m-0 p-0" 
                        onsubmit="return confirm('Delete or deactivate supplier <?= e(addslashes($sup['company_name'])) ?> (<?= e($sup['supplier_code']) ?>)?');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete_supplier">
                    <input type="hidden" name="supplier_id" value="<?= $sup['id'] ?>">
                    <button type="submit" 
                            class="btn btn-sm btn-outline-danger btn-action-icon" 
                            title="Delete Supplier">
                      <i class="bi bi-trash"></i>
                    </button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Modal: New Supplier -->
<div class="modal fade" id="newSupplierModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" action="">
        <?= csrf_field() ?>
        <div class="modal-header">
          <h5 class="modal-title fw-bold">Register Vendor / Supplier</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label small fw-semibold">Company / Vendor Name <span class="text-danger">*</span></label>
            <input type="text" name="company_name" class="form-control" placeholder="e.g. Roche Diagnostics India" required>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold">Contact Person</label>
            <input type="text" name="contact_person" class="form-control" placeholder="e.g. Ananya Deshmukh">
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold">Phone <span class="text-danger">*</span></label>
            <input type="tel" name="phone" class="form-control" placeholder="e.g. 9820011223" required>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold">Email</label>
            <input type="email" name="email" class="form-control" placeholder="e.g. orders@supplier.com">
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold">GSTIN</label>
            <input type="text" name="gstin" class="form-control font-monospace" placeholder="e.g. 27AAACT1234F1Z8">
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold">Office Address</label>
            <textarea name="address" class="form-control" rows="2"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-save">Save Supplier</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal: Edit Supplier -->
<div class="modal fade" id="editSupplierModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" action="">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="edit_supplier">
        <input type="hidden" name="supplier_id" id="edit_supplier_id">
        <div class="modal-header">
          <h5 class="modal-title fw-bold">Edit Supplier Details</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label small fw-semibold">Company / Vendor Name <span class="text-danger">*</span></label>
            <input type="text" name="company_name" id="edit_company_name" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold">Contact Person</label>
            <input type="text" name="contact_person" id="edit_contact_person" class="form-control">
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold">Phone <span class="text-danger">*</span></label>
            <input type="tel" name="phone" id="edit_phone" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold">Email</label>
            <input type="email" name="email" id="edit_email" class="form-control">
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold">GSTIN</label>
            <input type="text" name="gstin" id="edit_gstin" class="form-control font-monospace">
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold">Office Address</label>
            <textarea name="address" id="edit_address" class="form-control" rows="2"></textarea>
          </div>
          <div class="form-check form-switch mb-2">
            <input class="form-check-input" type="checkbox" name="is_active" id="edit_is_active" value="1">
            <label class="form-check-label small fw-semibold" for="edit_is_active">Active Vendor</label>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-save">Update Supplier</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function openEditSupplierModal(sup) {
  document.getElementById('edit_supplier_id').value = sup.id;
  document.getElementById('edit_company_name').value = sup.company_name || '';
  document.getElementById('edit_contact_person').value = sup.contact_person || '';
  document.getElementById('edit_phone').value = sup.phone || '';
  document.getElementById('edit_email').value = sup.email || '';
  document.getElementById('edit_gstin').value = sup.gstin || '';
  document.getElementById('edit_address').value = sup.address || '';
  document.getElementById('edit_is_active').checked = parseInt(sup.is_active) === 1;

  var modal = new bootstrap.Modal(document.getElementById('editSupplierModal'));
  modal.show();
}
</script>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>

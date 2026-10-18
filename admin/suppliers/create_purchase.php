<?php
/**
 * LabMaster - Record Supplier Purchase & Goods Receipt
 */

declare(strict_types=1);

$pageTitle = 'Record Purchase Invoice';
$activeNav = 'purchases';

require_once dirname(__DIR__) . '/includes/auth_guard.php';
Auth::requirePermission('suppliers.manage');

$suppliers = Database::fetchAll("SELECT * FROM `suppliers` WHERE `is_active` = 1 ORDER BY `company_name` ASC");
$items = Database::fetchAll("SELECT * FROM `inventory_items` WHERE `is_active` = 1 ORDER BY `name` ASC");
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $supplierId = (int)($_POST['supplier_id'] ?? 0);
    $invoiceNo = trim($_POST['invoice_number'] ?? '');
    $purchaseDate = !empty($_POST['purchase_date']) ? trim($_POST['purchase_date']) : date('Y-m-d');
    $itemId = (int)($_POST['item_id'] ?? 0);
    $batchNo = strtoupper(trim($_POST['batch_number'] ?? ''));
    $expiryDate = trim($_POST['expiry_date'] ?? '');
    $quantity = (int)($_POST['quantity'] ?? 0);
    $unitPrice = (float)($_POST['unit_price'] ?? 0);
    $paidAmount = (float)($_POST['paid_amount'] ?? 0);
    $notes = trim($_POST['notes'] ?? '');
    $userId = Auth::id();

    if ($supplierId <= 0 || $itemId <= 0) {
        $errors[] = 'Please select a valid supplier and inventory item.';
    }
    if (empty($batchNo)) {
        $errors[] = 'Reagent lot/batch number is mandatory for clinical tracking.';
    }
    if (empty($expiryDate)) {
        $errors[] = 'Reagent expiration date is mandatory.';
    }
    if ($quantity <= 0) {
        $errors[] = 'Quantity must be at least 1.';
    }
    if ($unitPrice < 0) {
        $errors[] = 'Unit price cannot be negative.';
    }

    if (empty($errors)) {
        $totalAmount = $quantity * $unitPrice;
        $balance = max(0.0, $totalAmount - $paidAmount);
        $paymentStatus = $balance <= 0 ? 'Paid' : ($paidAmount > 0 ? 'Partially Paid' : 'Unpaid');

        $db = Database::getInstance();
        $db->beginTransaction();

        try {
            $purchaseNo = generate_purchase_number();

            // 1. Insert Purchase
            Database::execute(
                "INSERT INTO `purchases` (`purchase_number`, `supplier_id`, `invoice_number`, `purchase_date`, `total_amount`, `paid_amount`, `balance_amount`, `payment_status`, `notes`, `created_by`)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [$purchaseNo, $supplierId, $invoiceNo, $purchaseDate, $totalAmount, $paidAmount, $balance, $paymentStatus, $notes, $userId]
            );
            $purchaseId = (int)Database::lastInsertId();

            // 2. Insert Purchase Line Item
            Database::execute(
                "INSERT INTO `purchase_items` (`purchase_id`, `item_id`, `batch_number`, `expiry_date`, `quantity`, `unit_price`, `total_price`)
                 VALUES (?, ?, ?, ?, ?, ?, ?)",
                [$purchaseId, $itemId, $batchNo, $expiryDate, $quantity, $unitPrice, $totalAmount]
            );

            // 3. Create or Update Inventory Batch
            $existingBatch = Database::fetch(
                "SELECT `id`, `current_quantity` FROM `inventory_batches` WHERE `item_id` = ? AND `batch_number` = ?",
                [$itemId, $batchNo]
            );

            if ($existingBatch) {
                $batchId = (int)$existingBatch['id'];
                Database::execute(
                    "UPDATE `inventory_batches` 
                     SET `current_quantity` = `current_quantity` + ?, `unit_cost` = ?, `expiry_date` = ?, `status` = 'Active' 
                     WHERE `id` = ?",
                    [$quantity, $unitPrice, $expiryDate, $batchId]
                );
            } else {
                Database::execute(
                    "INSERT INTO `inventory_batches` (`item_id`, `supplier_id`, `batch_number`, `initial_quantity`, `current_quantity`, `unit_cost`, `expiry_date`, `status`)
                     VALUES (?, ?, ?, ?, ?, ?, ?, 'Active')",
                    [$itemId, $supplierId, $batchNo, $quantity, $quantity, $unitPrice, $expiryDate]
                );
                $batchId = (int)Database::lastInsertId();
            }

            // 4. Update Inventory Item current stock
            $targetItem = Database::fetch("SELECT `current_stock` FROM `inventory_items` WHERE `id` = ?", [$itemId]);
            $beforeQty = (int)($targetItem['current_stock'] ?? 0);
            $afterQty = $beforeQty + $quantity;

            Database::execute(
                "UPDATE `inventory_items` SET `current_stock` = ? WHERE `id` = ?",
                [$afterQty, $itemId]
            );

            // 5. Record in Stock Movement Ledger
            Database::execute(
                "INSERT INTO `stock_movements` (`item_id`, `batch_id`, `movement_type`, `quantity`, `quantity_before`, `quantity_after`, `reference_id`, `reason`, `performed_by`)
                 VALUES (?, ?, 'Purchase', ?, ?, ?, ?, 'Supplier purchase receipt', ?)",
                [$itemId, $batchId, $quantity, $beforeQty, $afterQty, $purchaseNo, $userId]
            );

            // 6. Update Supplier Balance
            if ($balance > 0) {
                Database::execute(
                    "UPDATE `suppliers` SET `current_balance` = `current_balance` + ? WHERE `id` = ?",
                    [$balance, $supplierId]
                );
            }

            $db->commit();
            log_audit('Purchase Recorded', 'Suppliers', $purchaseNo, "Recorded purchase {$purchaseNo} from supplier ID {$supplierId}. Added {$quantity} units to lot {$batchNo}.");
            set_flash('success', "Purchase {$purchaseNo} recorded. Batch {$batchNo} stocked successfully!");
            header('Location: ' . ADMIN_URL . '/suppliers/purchases.php');
            exit;
        } catch (Exception $e) {
            $db->rollBack();
            $errors[] = 'Failed to record purchase: ' . $e->getMessage();
        }
    }
}

require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="row justify-content-center">
  <div class="col-lg-8">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <div>
        <h2 class="h4 fw-bold mb-1">Record Supplier Purchase & Stock In</h2>
        <p class="text-muted small mb-0">Receive reagents, generate lots, and update laboratory stock automatically</p>
      </div>
      <a href="<?= ADMIN_URL ?>/suppliers/purchases.php" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i> Back to Purchases
      </a>
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

    <div class="admin-card">
      <div class="admin-card-body p-4">
        <form method="POST" action="">
          <?= csrf_field() ?>

          <div class="row g-3 mb-4">
            <div class="col-md-6">
              <label class="form-label small fw-semibold">Select Supplier <span class="text-danger">*</span></label>
              <select name="supplier_id" class="form-select" required>
                <option value="">-- Choose Vendor --</option>
                <?php foreach ($suppliers as $s): ?>
                  <option value="<?= $s['id'] ?>"><?= e($s['company_name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-md-6">
              <label class="form-label small fw-semibold">Supplier Bill / Invoice Number</label>
              <input type="text" name="invoice_number" class="form-control" placeholder="e.g. INV-2026-9921">
            </div>

            <div class="col-md-6">
              <label class="form-label small fw-semibold">Purchase Date <span class="text-danger">*</span></label>
              <input type="date" name="purchase_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
            </div>

            <div class="col-md-6">
              <label class="form-label small fw-semibold">Reagent Item Received <span class="text-danger">*</span></label>
              <select name="item_id" class="form-select" required>
                <option value="">-- Choose Item --</option>
                <?php foreach ($items as $it): ?>
                  <option value="<?= $it['id'] ?>">
                    <?= e($it['name']) ?> (<?= e($it['item_code']) ?> - Stock: <?= $it['current_stock'] ?> <?= e($it['unit']) ?>)
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-md-6">
              <label class="form-label small fw-semibold">Manufacturer Lot / Batch Number <span class="text-danger">*</span></label>
              <input type="text" name="batch_number" class="form-control font-monospace text-uppercase" placeholder="e.g. ROCHE-CR-2026" required>
            </div>

            <div class="col-md-6">
              <label class="form-label small fw-semibold">Reagent Expiration Date <span class="text-danger">*</span></label>
              <input type="date" name="expiry_date" class="form-control" required>
            </div>

            <div class="col-md-4">
              <label class="form-label small fw-semibold">Quantity Received <span class="text-danger">*</span></label>
              <input type="number" name="quantity" id="purchaseQty" class="form-control" min="1" value="1" required>
            </div>

            <div class="col-md-4">
              <label class="form-label small fw-semibold">Unit Purchase Price (₹) <span class="text-danger">*</span></label>
              <input type="number" step="0.01" name="unit_price" id="purchaseUnitPrice" class="form-control" placeholder="0.00" required>
            </div>

            <div class="col-md-4">
              <label class="form-label small fw-semibold">Total Bill Amount</label>
              <input type="text" id="purchaseTotalDisplay" class="form-control fw-bold bg-light" readonly value="₹ 0.00">
            </div>

            <div class="col-md-6">
              <label class="form-label small fw-semibold">Paid Amount (₹)</label>
              <input type="number" step="0.01" name="paid_amount" class="form-control" value="0.00">
              <div class="form-text">Remaining amount will be marked as supplier payable.</div>
            </div>

            <div class="col-md-6">
              <label class="form-label small fw-semibold">Notes / Purchase Remarks</label>
              <input type="text" name="notes" class="form-control" placeholder="Optional comments">
            </div>
          </div>

          <div class="d-flex justify-content-between align-items-center pt-3 border-top">
            <a href="<?= ADMIN_URL ?>/suppliers/purchases.php" class="btn btn-light">Cancel</a>
            <button type="submit" class="btn btn-save">
              <i class="bi bi-box-seam me-1"></i> Receive Stock & Post Purchase
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const qty = document.getElementById('purchaseQty');
  const price = document.getElementById('purchaseUnitPrice');
  const display = document.getElementById('purchaseTotalDisplay');

  function recalc() {
    const q = parseFloat(qty.value || '0');
    const p = parseFloat(price.value || '0');
    const total = q * p;
    display.value = '₹ ' + total.toFixed(2);
  }

  qty.addEventListener('input', recalc);
  price.addEventListener('input', recalc);
});
</script>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>

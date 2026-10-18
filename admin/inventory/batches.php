<?php
/**
 * LabMaster - Reagent Lot & Batch Expiration Tracking
 */

declare(strict_types=1);

$pageTitle = 'Batches & Expiry Management';
$activeNav = 'batches';

require_once dirname(__DIR__) . '/includes/auth_guard.php';
Auth::requirePermission('inventory.manage');

// Automatically check and flag expired batches in database
Database::execute(
    "UPDATE `inventory_batches` 
     SET `status` = 'Expired' 
     WHERE `expiry_date` < CURDATE() AND `status` != 'Expired' AND `current_quantity` > 0"
);

// Flag near expiry (within 60 days)
Database::execute(
    "UPDATE `inventory_batches` 
     SET `status` = 'Near Expiry' 
     WHERE `expiry_date` >= CURDATE() 
       AND `expiry_date` <= DATE_ADD(CURDATE(), INTERVAL 60 DAY) 
       AND `status` = 'Active' 
       AND `current_quantity` > 0"
);

$itemId = (int)($_GET['item_id'] ?? 0);
$statusFilter = trim($_GET['status'] ?? '');

// Handle Actions (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? '';

    // 1. Discard Expired Action
    if ($action === 'discard_expired') {
        Auth::requirePermission('inventory.adjust');
        $batchId = (int)($_POST['batch_id'] ?? 0);
        $reason = trim($_POST['reason'] ?? 'Expired batch discarded and incinerated per biomedical waste protocol.');

        $targetBatch = Database::fetch("SELECT * FROM `inventory_batches` WHERE `id` = ?", [$batchId]);
        if ($targetBatch && $targetBatch['current_quantity'] > 0) {
            $discardQty = (int)$targetBatch['current_quantity'];
            update_inventory_stock(
                (int)$targetBatch['item_id'],
                $batchId,
                $discardQty,
                'Expired_Discard',
                $reason,
                $targetBatch['batch_number']
            );

            log_audit('Expired Stock Discarded', 'Inventory', $targetBatch['batch_number'], "Discarded {$discardQty} units of expired lot {$targetBatch['batch_number']}.");
            set_flash('success', "Batch {$targetBatch['batch_number']} marked as exhausted and discarded.");
            header('Location: ' . ADMIN_URL . '/inventory/batches.php' . ($itemId > 0 ? '?item_id=' . $itemId : ''));
            exit;
        }
    }

    // 2. Edit Batch
    if ($action === 'edit_batch') {
        Auth::requirePermission('inventory.manage');
        $batchId = (int)($_POST['batch_id'] ?? 0);
        $batchNumber = trim($_POST['batch_number'] ?? '');
        $supplierId = !empty($_POST['supplier_id']) ? (int)$_POST['supplier_id'] : null;
        $currentQty = max(0, (int)($_POST['current_quantity'] ?? 0));
        $unitCost = max(0, (float)($_POST['unit_cost'] ?? 0));
        $mfgDate = !empty($_POST['mfg_date']) ? trim($_POST['mfg_date']) : null;
        $expiryDate = trim($_POST['expiry_date'] ?? '');
        $status = trim($_POST['status'] ?? 'Active');

        $targetBatch = Database::fetch("SELECT * FROM `inventory_batches` WHERE `id` = ?", [$batchId]);
        if (!$targetBatch) {
            set_flash('danger', 'Batch record not found.');
        } elseif (empty($batchNumber) || empty($expiryDate)) {
            set_flash('danger', 'Batch / Lot number and Expiry date are required.');
        } else {
            $today = date('Y-m-d');
            if ($expiryDate < $today) {
                $status = 'Expired';
            } elseif ($currentQty <= 0) {
                $status = 'Exhausted';
            } elseif ($status === 'Expired' && $expiryDate >= $today && $currentQty > 0) {
                $status = 'Active';
            }

            Database::execute(
                "UPDATE `inventory_batches` 
                 SET `batch_number` = ?, `supplier_id` = ?, `current_quantity` = ?, `unit_cost` = ?, `mfg_date` = ?, `expiry_date` = ?, `status` = ?
                 WHERE `id` = ?",
                [$batchNumber, $supplierId, $currentQty, $unitCost, $mfgDate, $expiryDate, $status, $batchId]
            );

            // Recalculate parent item current_stock
            $newTotalStock = (int)Database::fetchColumn(
                "SELECT COALESCE(SUM(current_quantity), 0) FROM `inventory_batches` WHERE `item_id` = ?",
                [$targetBatch['item_id']]
            );
            Database::execute("UPDATE `inventory_items` SET `current_stock` = ? WHERE `id` = ?", [$newTotalStock, $targetBatch['item_id']]);

            log_audit('Batch Updated', 'Inventory', $batchNumber, "Updated batch #{$batchId} ({$batchNumber}). Current Qty: {$currentQty}, Status: {$status}.");
            set_flash('success', "Batch \"{$batchNumber}\" updated successfully.");
            header('Location: ' . ADMIN_URL . '/inventory/batches.php' . ($itemId > 0 ? '?item_id=' . $itemId : ''));
            exit;
        }
    }

    // 3. Delete Batch (Safe deactivation if movements exist)
    if ($action === 'delete_batch') {
        Auth::requirePermission('inventory.manage');
        $batchId = (int)($_POST['batch_id'] ?? 0);

        $targetBatch = Database::fetch("SELECT * FROM `inventory_batches` WHERE `id` = ?", [$batchId]);
        if (!$targetBatch) {
            set_flash('danger', 'Batch record not found.');
        } else {
            $movementCount = (int)Database::fetchColumn(
                "SELECT COUNT(*) FROM `stock_movements` WHERE `batch_id` = ?",
                [$batchId]
            );

            if ($movementCount > 0) {
                // Safeguard clinical audit trail: zero stock and set status to Exhausted
                Database::execute(
                    "UPDATE `inventory_batches` SET `current_quantity` = 0, `status` = 'Exhausted' WHERE `id` = ?",
                    [$batchId]
                );
                $newTotalStock = (int)Database::fetchColumn(
                    "SELECT COALESCE(SUM(current_quantity), 0) FROM `inventory_batches` WHERE `item_id` = ?",
                    [$targetBatch['item_id']]
                );
                Database::execute("UPDATE `inventory_items` SET `current_stock` = ? WHERE `id` = ?", [$newTotalStock, $targetBatch['item_id']]);

                log_audit('Batch Archived', 'Inventory', $targetBatch['batch_number'], "Batch {$targetBatch['batch_number']} safely zeroed & exhausted due to {$movementCount} stock movements.");
                set_flash('warning', "Batch \"{$targetBatch['batch_number']}\" has historical movement logs. To preserve clinical audit records, it was safely archived and stock set to 0.");
            } else {
                Database::execute("DELETE FROM `inventory_batches` WHERE `id` = ?", [$batchId]);

                $newTotalStock = (int)Database::fetchColumn(
                    "SELECT COALESCE(SUM(current_quantity), 0) FROM `inventory_batches` WHERE `item_id` = ?",
                    [$targetBatch['item_id']]
                );
                Database::execute("UPDATE `inventory_items` SET `current_stock` = ? WHERE `id` = ?", [$newTotalStock, $targetBatch['item_id']]);

                log_audit('Batch Deleted', 'Inventory', $targetBatch['batch_number'], "Deleted batch {$targetBatch['batch_number']} (No movements).");
                set_flash('success', "Batch \"{$targetBatch['batch_number']}\" deleted successfully.");
            }

            header('Location: ' . ADMIN_URL . '/inventory/batches.php' . ($itemId > 0 ? '?item_id=' . $itemId : ''));
            exit;
        }
    }

    // 4. Add Batch
    if ($action === 'add_batch') {
        Auth::requirePermission('inventory.manage');
        $newItemId = (int)($_POST['item_id'] ?? 0);
        $batchNumber = trim($_POST['batch_number'] ?? '');
        $supplierId = !empty($_POST['supplier_id']) ? (int)$_POST['supplier_id'] : null;
        $qty = max(1, (int)($_POST['quantity'] ?? 1));
        $unitCost = max(0, (float)($_POST['unit_cost'] ?? 0));
        $mfgDate = !empty($_POST['mfg_date']) ? trim($_POST['mfg_date']) : null;
        $expiryDate = trim($_POST['expiry_date'] ?? '');

        if ($newItemId <= 0 || empty($batchNumber) || empty($expiryDate)) {
            set_flash('danger', 'Please select an item and provide batch number & expiry date.');
        } else {
            $today = date('Y-m-d');
            $nearExpiryDate = date('Y-m-d', strtotime('+60 days'));
            $status = 'Active';
            if ($expiryDate < $today) {
                $status = 'Expired';
            } elseif ($expiryDate <= $nearExpiryDate) {
                $status = 'Near Expiry';
            }

            Database::execute(
                "INSERT INTO `inventory_batches` (`item_id`, `supplier_id`, `batch_number`, `initial_quantity`, `current_quantity`, `unit_cost`, `mfg_date`, `expiry_date`, `status`)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [$newItemId, $supplierId, $batchNumber, $qty, $qty, $unitCost, $mfgDate, $expiryDate, $status]
            );
            $newBatchId = (int)Database::lastInsertId();

            Database::execute(
                "INSERT INTO `stock_movements` (`item_id`, `batch_id`, `movement_type`, `quantity`, `reference_id`, `notes`, `created_by`)
                 VALUES (?, ?, 'Purchase', ?, ?, 'Direct batch registration', ?)",
                [$newItemId, $newBatchId, $qty, $batchNumber, Auth::id()]
            );

            $newTotalStock = (int)Database::fetchColumn(
                "SELECT COALESCE(SUM(current_quantity), 0) FROM `inventory_batches` WHERE `item_id` = ?",
                [$newItemId]
            );
            Database::execute("UPDATE `inventory_items` SET `current_stock` = ? WHERE `id` = ?", [$newTotalStock, $newItemId]);

            log_audit('Batch Created', 'Inventory', $batchNumber, "Registered new batch {$batchNumber} with {$qty} units.");
            set_flash('success', "Batch \"{$batchNumber}\" registered successfully.");
            header('Location: ' . ADMIN_URL . '/inventory/batches.php' . ($itemId > 0 ? '?item_id=' . $itemId : ''));
            exit;
        }
    }
}

// Fetch Batches Query
$sql = "SELECT b.*, i.name as item_name, i.item_code, i.unit, i.storage_temperature, i.storage_location, s.company_name as supplier_name
        FROM `inventory_batches` b
        JOIN `inventory_items` i ON b.item_id = i.id
        LEFT JOIN `suppliers` s ON b.supplier_id = s.id
        WHERE 1=1";
$params = [];

if ($itemId > 0) {
    $sql .= " AND b.item_id = ?";
    $params[] = $itemId;
}
if (!empty($statusFilter)) {
    $sql .= " AND b.status = ?";
    $params[] = $statusFilter;
}

$sql .= " ORDER BY (b.status = 'Expired') DESC, (b.status = 'Near Expiry') DESC, b.expiry_date ASC";
$batches = Database::fetchAll($sql, $params);

// Load Reference Catalogs for Modals
$allSuppliers = Database::fetchAll("SELECT id, company_name FROM `suppliers` WHERE `is_active` = 1 ORDER BY company_name ASC");
$allItems = Database::fetchAll("SELECT id, name, item_code, unit FROM `inventory_items` WHERE `is_active` = 1 ORDER BY name ASC");

require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
  <div>
    <h2 class="h4 fw-bold mb-1">Batch & Expiration Tracking Engine</h2>
    <p class="text-muted small mb-0">Maintain quality compliance: Expired reagents are blocked from clinical testing</p>
  </div>
  <div class="d-flex flex-wrap gap-2">
    <?php if ($itemId > 0): ?>
      <a href="<?= ADMIN_URL ?>/inventory/batches.php" class="btn btn-outline-secondary d-inline-flex align-items-center gap-2">
        <i class="bi bi-x-circle"></i> Clear Item Filter
      </a>
    <?php endif; ?>
    <a href="<?= ADMIN_URL ?>/inventory/index.php" class="btn btn-outline-secondary d-inline-flex align-items-center gap-2">
      <i class="bi bi-boxes"></i> Inventory Catalog
    </a>
    <button type="button" class="btn btn-primary-lm d-inline-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#newBatchModal">
      <i class="bi bi-plus-circle"></i> Add Batch / Lot
    </button>
  </div>
</div>

<!-- Status Filters -->
<div class="d-flex flex-wrap gap-2 mb-3">
  <a href="?<?= http_build_query(array_merge($_GET, ['status' => ''])) ?>" class="btn btn-sm <?= empty($statusFilter) ? 'btn-primary-lm' : 'btn-outline-secondary' ?>">All Batches</a>
  <a href="?<?= http_build_query(array_merge($_GET, ['status' => 'Active'])) ?>" class="btn btn-sm <?= $statusFilter === 'Active' ? 'btn-success text-white' : 'btn-outline-secondary' ?>">Active Good Batches</a>
  <a href="?<?= http_build_query(array_merge($_GET, ['status' => 'Near Expiry'])) ?>" class="btn btn-sm <?= $statusFilter === 'Near Expiry' ? 'btn-warning text-dark fw-bold' : 'btn-outline-secondary' ?>">
    <i class="bi bi-clock-history me-1"></i> Near Expiry (≤ 60 Days)
  </a>
  <a href="?<?= http_build_query(array_merge($_GET, ['status' => 'Expired'])) ?>" class="btn btn-sm <?= $statusFilter === 'Expired' ? 'btn-danger text-white fw-bold' : 'btn-outline-secondary' ?>">
    <i class="bi bi-x-circle-fill me-1"></i> Expired (Blocked)
  </a>
</div>

<!-- Batches Table -->
<div class="admin-card">
  <div class="admin-card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>Lot / Batch No</th>
            <th>Item Details</th>
            <th>Supplier</th>
            <th>Current Qty</th>
            <th>Unit Cost</th>
            <th>Expiry Date</th>
            <th>Status</th>
            <th class="text-end text-nowrap pe-3" style="width: 140px;">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($batches)): ?>
            <tr><td colspan="8" class="text-center text-muted py-5">No inventory batches found.</td></tr>
          <?php else: ?>
            <?php foreach ($batches as $b): ?>
              <?php
              $daysLeft = ceil((strtotime($b['expiry_date']) - time()) / 86400);
              $isExpired = $daysLeft <= 0 || $b['status'] === 'Expired';
              ?>
              <tr class="<?= $isExpired ? 'table-danger bg-opacity-25' : ($daysLeft <= 60 ? 'table-warning bg-opacity-10' : '') ?>">
                <td>
                  <span class="fw-bold font-monospace text-dark"><?= e($b['batch_number']) ?></span>
                </td>
                <td>
                  <div class="fw-semibold text-dark"><?= e($b['item_name']) ?></div>
                  <span class="badge bg-light text-dark border font-monospace"><?= e($b['item_code']) ?></span>
                </td>
                <td class="small text-muted"><?= e($b['supplier_name'] ?: 'Direct Purchase') ?></td>
                <td>
                  <span class="fw-bold fs-6 <?= $isExpired ? 'text-danger' : 'text-dark' ?>">
                    <?= $b['current_quantity'] ?>
                  </span>
                  <span class="text-muted small"><?= e($b['unit']) ?></span>
                </td>
                <td class="small"><?= format_currency($b['unit_cost']) ?></td>
                <td>
                  <div class="fw-bold <?= $isExpired ? 'text-danger' : ($daysLeft <= 60 ? 'text-warning' : 'text-dark') ?>">
                    <?= format_date($b['expiry_date']) ?>
                  </div>
                  <div class="small text-muted" style="font-size:0.75rem;">
                    <?= $isExpired ? '<strong class="text-danger">EXPIRED (' . abs($daysLeft) . 'd ago)</strong>' : "{$daysLeft} days remaining" ?>
                  </div>
                </td>
                <td>
                  <?php if ($isExpired): ?>
                    <span class="badge bg-danger text-white pulse-alert"><i class="bi bi-shield-x me-1"></i> BLOCKED EXPIRED</span>
                  <?php elseif ($daysLeft <= 60): ?>
                    <span class="badge bg-warning text-dark fw-bold"><i class="bi bi-exclamation-triangle me-1"></i> NEAR EXPIRY</span>
                  <?php elseif ($b['current_quantity'] <= 0): ?>
                    <span class="badge bg-secondary">EXHAUSTED</span>
                  <?php else: ?>
                    <span class="badge bg-success-subtle text-success-emphasis border border-success">ACTIVE</span>
                  <?php endif; ?>
                </td>
                <td class="text-end text-nowrap pe-3">
                  <div class="d-inline-flex align-items-center justify-content-end gap-1">
                    <!-- View Batch Button -->
                    <button type="button" 
                            class="btn btn-sm btn-outline-primary btn-action-icon" 
                            onclick='openViewBatchModal(<?= htmlspecialchars(json_encode($b), ENT_QUOTES, "UTF-8") ?>)' 
                            title="View Batch Details">
                      <i class="bi bi-eye"></i>
                    </button>

                    <!-- Edit Batch Button -->
                    <?php if (Auth::can('inventory.manage')): ?>
                    <button type="button" 
                            class="btn btn-sm btn-outline-secondary btn-action-icon" 
                            onclick='openEditBatchModal(<?= htmlspecialchars(json_encode($b), ENT_QUOTES, "UTF-8") ?>)' 
                            title="Edit Batch / Lot">
                      <i class="bi bi-pencil"></i>
                    </button>
                    <?php endif; ?>

                    <!-- Delete Batch Button -->
                    <?php if (Auth::can('inventory.manage')): ?>
                    <form method="POST" action="<?= ADMIN_URL ?>/inventory/batches.php<?= $itemId > 0 ? '?item_id=' . $itemId : '' ?>" class="d-inline m-0 p-0" 
                          onsubmit="return confirm('Are you sure you want to delete or archive batch <?= e(addslashes($b['batch_number'])) ?>?');">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="delete_batch">
                      <input type="hidden" name="batch_id" value="<?= $b['id'] ?>">
                      <button type="submit" 
                              class="btn btn-sm btn-outline-danger btn-action-icon" 
                              title="Delete / Archive Batch">
                        <i class="bi bi-trash"></i>
                      </button>
                    </form>
                    <?php endif; ?>

                    <!-- Discard Expired Action (Only when expired & stock exists) -->
                    <?php if ($isExpired && $b['current_quantity'] > 0 && Auth::can('inventory.adjust')): ?>
                    <form method="POST" action="<?= ADMIN_URL ?>/inventory/batches.php<?= $itemId > 0 ? '?item_id=' . $itemId : '' ?>" class="d-inline m-0 p-0" 
                          onsubmit="return confirm('Discard and write-off expired batch <?= e(addslashes($b['batch_number'])) ?>?');">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="discard_expired">
                      <input type="hidden" name="batch_id" value="<?= $b['id'] ?>">
                      <button type="submit" 
                              class="btn btn-sm btn-danger btn-action-icon" 
                              title="Discard Expired Reagent">
                        <i class="bi bi-shield-x"></i>
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

<!-- Modal: View Batch Details -->
<div class="modal fade" id="viewBatchModal" tabindex="-1" aria-labelledby="viewBatchModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <div class="modal-header bg-light">
        <h5 class="modal-title fw-bold" id="viewBatchModalLabel">
          <i class="bi bi-info-circle text-primary me-2"></i>Batch / Lot Details
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-4">
        <div class="d-flex justify-content-between align-items-start mb-3 pb-3 border-bottom">
          <div>
            <span class="text-muted small d-block">Lot / Batch Number</span>
            <span class="fs-5 fw-bold font-monospace text-dark" id="view_batch_number"></span>
          </div>
          <div id="view_status_badge"></div>
        </div>

        <div class="row g-3 mb-3">
          <div class="col-sm-6">
            <span class="text-muted small d-block">Reagent / Item</span>
            <span class="fw-semibold text-dark" id="view_item_name"></span>
            <div class="font-monospace text-muted small" id="view_item_code"></div>
          </div>
          <div class="col-sm-6">
            <span class="text-muted small d-block">Supplier / Vendor</span>
            <span class="fw-semibold text-dark" id="view_supplier_name"></span>
          </div>

          <div class="col-sm-6">
            <span class="text-muted small d-block">Current Stock</span>
            <span class="fs-5 fw-bold text-dark" id="view_current_quantity"></span>
            <span class="text-muted small" id="view_item_unit"></span>
            <div class="text-muted small" id="view_initial_quantity"></div>
          </div>
          <div class="col-sm-6">
            <span class="text-muted small d-block">Unit Cost & Value</span>
            <span class="fw-bold text-dark" id="view_unit_cost"></span>
            <div class="text-muted small" id="view_total_value"></div>
          </div>

          <div class="col-sm-6">
            <span class="text-muted small d-block">Manufacturing Date</span>
            <span class="fw-medium text-dark" id="view_mfg_date"></span>
          </div>
          <div class="col-sm-6">
            <span class="text-muted small d-block">Expiration Date</span>
            <span class="fw-bold text-dark" id="view_expiry_date"></span>
            <div class="small" id="view_expiry_countdown"></div>
          </div>

          <div class="col-12" id="view_storage_container" style="display: none;">
            <div class="p-2 bg-light rounded small">
              <span class="text-muted d-block">Storage Conditions:</span>
              <span class="fw-medium text-dark" id="view_storage_info"></span>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer bg-light">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        <?php if (Auth::can('inventory.manage')): ?>
        <button type="button" class="btn btn-primary-lm d-inline-flex align-items-center gap-1" id="viewModalEditBtn">
          <i class="bi bi-pencil"></i> Edit This Batch
        </button>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- Modal: Edit Batch -->
<div class="modal fade" id="editBatchModal" tabindex="-1" aria-labelledby="editBatchModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <form method="POST" action="<?= ADMIN_URL ?>/inventory/batches.php<?= $itemId > 0 ? '?item_id=' . $itemId : '' ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="edit_batch">
        <input type="hidden" name="batch_id" id="edit_batch_id">

        <div class="modal-header bg-light">
          <h5 class="modal-title fw-bold" id="editBatchModalLabel">
            <i class="bi bi-pencil-square text-primary me-2"></i>Edit Batch / Lot Information
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body p-4">
          <div class="mb-3">
            <label class="form-label small fw-semibold text-muted">Item Name</label>
            <input type="text" class="form-control bg-light" id="edit_item_name_display" readonly>
          </div>

          <div class="row g-3">
            <div class="col-sm-6">
              <label class="form-label small fw-semibold">Batch / Lot Number <span class="text-danger">*</span></label>
              <input type="text" name="batch_number" id="edit_batch_number" class="form-control font-monospace" required>
            </div>
            <div class="col-sm-6">
              <label class="form-label small fw-semibold">Supplier / Vendor</label>
              <select name="supplier_id" id="edit_supplier_id" class="form-select">
                <option value="">Direct / None Specified</option>
                <?php foreach ($allSuppliers as $sup): ?>
                  <option value="<?= $sup['id'] ?>"><?= e($sup['company_name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-sm-6">
              <label class="form-label small fw-semibold">Current Quantity <span class="text-danger">*</span></label>
              <input type="number" name="current_quantity" id="edit_current_quantity" class="form-control" min="0" required>
            </div>
            <div class="col-sm-6">
              <label class="form-label small fw-semibold">Unit Cost (₹)</label>
              <input type="number" step="0.01" name="unit_cost" id="edit_unit_cost" class="form-control" min="0">
            </div>

            <div class="col-sm-6">
              <label class="form-label small fw-semibold">Manufacturing Date</label>
              <input type="date" name="mfg_date" id="edit_mfg_date" class="form-control">
            </div>
            <div class="col-sm-6">
              <label class="form-label small fw-semibold">Expiry Date <span class="text-danger">*</span></label>
              <input type="date" name="expiry_date" id="edit_expiry_date" class="form-control" required>
            </div>

            <div class="col-12">
              <label class="form-label small fw-semibold">Status <span class="text-danger">*</span></label>
              <select name="status" id="edit_status" class="form-select" required>
                <option value="Active">Active (Good Batch)</option>
                <option value="Near Expiry">Near Expiry</option>
                <option value="Expired">Expired (Blocked)</option>
                <option value="Exhausted">Exhausted (Zero Stock)</option>
              </select>
            </div>
          </div>
        </div>
        <div class="modal-footer bg-light">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-save">
            <i class="bi bi-check-circle me-1"></i> Update Batch
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal: New Batch -->
<div class="modal fade" id="newBatchModal" tabindex="-1" aria-labelledby="newBatchModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <form method="POST" action="<?= ADMIN_URL ?>/inventory/batches.php<?= $itemId > 0 ? '?item_id=' . $itemId : '' ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add_batch">

        <div class="modal-header bg-light">
          <h5 class="modal-title fw-bold" id="newBatchModalLabel">
            <i class="bi bi-plus-circle text-primary me-2"></i>Add Reagent Lot / Batch
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body p-4">
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label small fw-semibold">Inventory Item <span class="text-danger">*</span></label>
              <select name="item_id" class="form-select" required>
                <option value="">-- Select Reagent or Supply Item --</option>
                <?php foreach ($allItems as $it): ?>
                  <option value="<?= $it['id'] ?>" <?= $itemId === (int)$it['id'] ? 'selected' : '' ?>>
                    <?= e($it['name']) ?> (<?= e($it['item_code']) ?> - <?= e($it['unit']) ?>)
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-sm-6">
              <label class="form-label small fw-semibold">Lot / Batch Number <span class="text-danger">*</span></label>
              <input type="text" name="batch_number" class="form-control font-monospace" placeholder="e.g. BD-EDTA-8821" required>
            </div>
            <div class="col-sm-6">
              <label class="form-label small fw-semibold">Supplier / Vendor</label>
              <select name="supplier_id" class="form-select">
                <option value="">Direct / None Specified</option>
                <?php foreach ($allSuppliers as $sup): ?>
                  <option value="<?= $sup['id'] ?>"><?= e($sup['company_name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-sm-6">
              <label class="form-label small fw-semibold">Initial Quantity <span class="text-danger">*</span></label>
              <input type="number" name="quantity" class="form-control" min="1" value="10" required>
            </div>
            <div class="col-sm-6">
              <label class="form-label small fw-semibold">Unit Cost (₹)</label>
              <input type="number" step="0.01" name="unit_cost" class="form-control" min="0" value="0.00">
            </div>

            <div class="col-sm-6">
              <label class="form-label small fw-semibold">Manufacturing Date</label>
              <input type="date" name="mfg_date" class="form-control">
            </div>
            <div class="col-sm-6">
              <label class="form-label small fw-semibold">Expiry Date <span class="text-danger">*</span></label>
              <input type="date" name="expiry_date" class="form-control" required>
            </div>
          </div>
        </div>
        <div class="modal-footer bg-light">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-save">
            <i class="bi bi-check-circle me-1"></i> Register Batch
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
let currentViewedBatch = null;

function openViewBatchModal(batch) {
  currentViewedBatch = batch;
  document.getElementById('view_batch_number').textContent = batch.batch_number || '';
  document.getElementById('view_item_name').textContent = batch.item_name || '';
  document.getElementById('view_item_code').textContent = 'Code: ' + (batch.item_code || 'N/A');
  document.getElementById('view_supplier_name').textContent = batch.supplier_name || 'Direct Purchase';
  document.getElementById('view_current_quantity').textContent = batch.current_quantity;
  document.getElementById('view_item_unit').textContent = batch.unit || '';
  document.getElementById('view_initial_quantity').textContent = 'Initial received: ' + (batch.initial_quantity || batch.current_quantity) + ' ' + (batch.unit || '');
  
  const unitCost = parseFloat(batch.unit_cost || 0);
  const totalVal = unitCost * parseFloat(batch.current_quantity || 0);
  document.getElementById('view_unit_cost').textContent = '₹ ' + unitCost.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
  document.getElementById('view_total_value').textContent = 'Batch Value: ₹ ' + totalVal.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
  
  document.getElementById('view_mfg_date').textContent = batch.mfg_date ? batch.mfg_date : 'Not recorded';
  document.getElementById('view_expiry_date').textContent = batch.expiry_date || 'N/A';
  
  // Calculate countdown
  if (batch.expiry_date) {
    const expTime = new Date(batch.expiry_date).getTime();
    const nowTime = new Date().setHours(0,0,0,0);
    const daysLeft = Math.ceil((expTime - nowTime) / (1000 * 60 * 60 * 24));
    
    let countdownHtml = '';
    let badgeHtml = '';
    
    if (daysLeft <= 0 || batch.status === 'Expired') {
      countdownHtml = '<span class="text-danger fw-bold"><i class="bi bi-x-circle me-1"></i> Expired ' + Math.abs(daysLeft) + ' days ago</span>';
      badgeHtml = '<span class="badge bg-danger text-white"><i class="bi bi-shield-x me-1"></i> BLOCKED EXPIRED</span>';
    } else if (daysLeft <= 60 || batch.status === 'Near Expiry') {
      countdownHtml = '<span class="text-warning fw-bold"><i class="bi bi-clock-history me-1"></i> ' + daysLeft + ' days remaining</span>';
      badgeHtml = '<span class="badge bg-warning text-dark fw-bold"><i class="bi bi-exclamation-triangle me-1"></i> NEAR EXPIRY</span>';
    } else {
      countdownHtml = '<span class="text-success"><i class="bi bi-check-circle me-1"></i> ' + daysLeft + ' days remaining</span>';
      badgeHtml = '<span class="badge bg-success-subtle text-success-emphasis border border-success">ACTIVE</span>';
    }
    
    document.getElementById('view_expiry_countdown').innerHTML = countdownHtml;
    document.getElementById('view_status_badge').innerHTML = badgeHtml;
  }
  
  // Storage conditions
  const storageInfo = (batch.storage_temperature ? batch.storage_temperature : '') + (batch.storage_location ? ' | ' + batch.storage_location : '');
  const storageContainer = document.getElementById('view_storage_container');
  if (storageInfo.trim()) {
    document.getElementById('view_storage_info').textContent = storageInfo;
    storageContainer.style.display = 'block';
  } else {
    storageContainer.style.display = 'none';
  }
  
  const modal = new bootstrap.Modal(document.getElementById('viewBatchModal'));
  modal.show();
}

function openEditBatchModal(batch) {
  document.getElementById('edit_batch_id').value = batch.id;
  document.getElementById('edit_item_name_display').value = (batch.item_name || '') + ' (' + (batch.item_code || '') + ')';
  document.getElementById('edit_batch_number').value = batch.batch_number || '';
  document.getElementById('edit_supplier_id').value = batch.supplier_id || '';
  document.getElementById('edit_current_quantity').value = batch.current_quantity;
  document.getElementById('edit_unit_cost').value = batch.unit_cost || '0.00';
  document.getElementById('edit_mfg_date').value = batch.mfg_date || '';
  document.getElementById('edit_expiry_date').value = batch.expiry_date || '';
  document.getElementById('edit_status').value = batch.status || 'Active';

  // If view modal is open, hide it
  const viewModalEl = document.getElementById('viewBatchModal');
  const viewModal = bootstrap.Modal.getInstance(viewModalEl);
  if (viewModal) {
    viewModal.hide();
  }

  const editModal = new bootstrap.Modal(document.getElementById('editBatchModal'));
  editModal.show();
}

document.getElementById('viewModalEditBtn')?.addEventListener('click', function() {
  if (currentViewedBatch) {
    openEditBatchModal(currentViewedBatch);
  }
});
</script>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>

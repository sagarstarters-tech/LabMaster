<?php
/**
 * LabMaster - Laboratory Reagents & Consumables Inventory
 */

declare(strict_types=1);

$pageTitle = 'Inventory Master';
$activeNav = 'inventory';

require_once dirname(__DIR__) . '/includes/auth_guard.php';
Auth::requirePermission('inventory.manage');

$categoryFilter = trim($_GET['category'] ?? '');
$search = trim($_GET['search'] ?? '');

// Handle Actions (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? 'add_item';

    // 1. Add New Item
    if ($action === 'add_item') {
        $name = trim($_POST['name'] ?? '');
        $code = strtoupper(trim($_POST['item_code'] ?? ''));
        $category = trim($_POST['category'] ?? 'Reagent');
        $manufacturer = trim($_POST['manufacturer'] ?? '');
        $unit = trim($_POST['unit'] ?? 'Kits');
        $minStock = (int)($_POST['minimum_stock'] ?? 5);
        $storageTemp = trim($_POST['storage_temperature'] ?? '2°C - 8°C (Refrigerated)');
        $storageLoc = trim($_POST['storage_location'] ?? 'Main Lab Refrigerator');

        if (empty($name) || empty($code)) {
            set_flash('danger', 'Item name and code are required.');
        } else {
            // Check code duplicate
            $dup = Database::fetch("SELECT `id` FROM `inventory_items` WHERE `item_code` = ?", [$code]);
            if ($dup) {
                set_flash('danger', "Item code {$code} already exists. Please use a unique code.");
            } else {
                Database::execute(
                    "INSERT INTO `inventory_items` (`item_code`, `name`, `category`, `manufacturer`, `unit`, `minimum_stock`, `current_stock`, `storage_temperature`, `storage_location`, `is_active`)
                     VALUES (?, ?, ?, ?, ?, ?, 0, ?, ?, 1)",
                    [$code, $name, $category, $manufacturer, $unit, $minStock, $storageTemp, $storageLoc]
                );
                log_audit('Inventory Item Added', 'Inventory', $code, "Added item {$name} ({$code}).");
                set_flash('success', "Item {$name} added to inventory catalog.");
                header('Location: ' . ADMIN_URL . '/inventory/index.php');
                exit;
            }
        }
    }

    // 2. Edit Item
    if ($action === 'edit_item') {
        $itemId = (int)($_POST['item_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $code = strtoupper(trim($_POST['item_code'] ?? ''));
        $category = trim($_POST['category'] ?? 'Reagent');
        $manufacturer = trim($_POST['manufacturer'] ?? '');
        $unit = trim($_POST['unit'] ?? 'Kits');
        $minStock = (int)($_POST['minimum_stock'] ?? 5);
        $storageTemp = trim($_POST['storage_temperature'] ?? '2°C - 8°C (Refrigerated)');
        $storageLoc = trim($_POST['storage_location'] ?? 'Main Lab Refrigerator');

        $targetItem = Database::fetch("SELECT * FROM `inventory_items` WHERE `id` = ?", [$itemId]);
        if (!$targetItem) {
            set_flash('danger', 'Item not found.');
        } elseif (empty($name) || empty($code)) {
            set_flash('danger', 'Item name and code are required.');
        } else {
            // Check duplicate code on other items
            $dup = Database::fetch("SELECT `id` FROM `inventory_items` WHERE `item_code` = ? AND `id` != ?", [$code, $itemId]);
            if ($dup) {
                set_flash('danger', "Item code {$code} is already used by another inventory item.");
            } else {
                Database::execute(
                    "UPDATE `inventory_items` 
                     SET `item_code` = ?, `name` = ?, `category` = ?, `manufacturer` = ?, `unit` = ?, `minimum_stock` = ?, `storage_temperature` = ?, `storage_location` = ?
                     WHERE `id` = ?",
                    [$code, $name, $category, $manufacturer, $unit, $minStock, $storageTemp, $storageLoc, $itemId]
                );
                log_audit('Inventory Item Updated', 'Inventory', $code, "Updated item #{$itemId} ({$name}).");
                set_flash('success', "Item \"{$name}\" updated successfully.");
                header('Location: ' . ADMIN_URL . '/inventory/index.php');
                exit;
            }
        }
    }

    // 3. Delete Item
    if ($action === 'delete_item') {
        $itemId = (int)($_POST['item_id'] ?? 0);
        $targetItem = Database::fetch("SELECT * FROM `inventory_items` WHERE `id` = ?", [$itemId]);
        if (!$targetItem) {
            set_flash('danger', 'Item not found.');
        } else {
            // Check for batches
            $batchCount = (int)Database::fetchColumn("SELECT COUNT(*) FROM `inventory_batches` WHERE `item_id` = ?", [$itemId]);
            if ($batchCount > 0) {
                // Safely soft deactivate to protect batch history
                Database::execute("UPDATE `inventory_items` SET `is_active` = 0 WHERE `id` = ?", [$itemId]);
                log_audit('Inventory Item Deactivated', 'Inventory', $targetItem['item_code'], "Item {$targetItem['name']} has {$batchCount} batches. Deactivated item.");
                set_flash('warning', "Item \"{$targetItem['name']}\" has {$batchCount} associated batch records. To preserve audit history, it was set to Inactive.");
            } else {
                Database::execute("DELETE FROM `inventory_items` WHERE `id` = ?", [$itemId]);
                log_audit('Inventory Item Deleted', 'Inventory', $targetItem['item_code'], "Deleted item {$targetItem['name']}.");
                set_flash('success', "Item \"{$targetItem['name']}\" deleted successfully.");
            }
            header('Location: ' . ADMIN_URL . '/inventory/index.php');
            exit;
        }
    }
}

$sql = "SELECT i.*, 
        (SELECT COUNT(*) FROM `inventory_batches` b WHERE b.item_id = i.id AND b.status = 'Active' AND b.current_quantity > 0) as active_batches,
        (SELECT MIN(b.expiry_date) FROM `inventory_batches` b WHERE b.item_id = i.id AND b.status IN ('Active', 'Near Expiry') AND b.current_quantity > 0) as nearest_expiry
        FROM `inventory_items` i
        WHERE i.is_active = 1";
$params = [];

if (!empty($categoryFilter)) {
    $sql .= " AND i.category = ?";
    $params[] = $categoryFilter;
}
if (!empty($search)) {
    $sql .= " AND (i.name LIKE ? OR i.item_code LIKE ? OR i.manufacturer LIKE ?)";
    $like = "%{$search}%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$sql .= " ORDER BY (i.current_stock <= i.minimum_stock) DESC, i.name ASC";
$items = Database::fetchAll($sql, $params);

require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
  <div>
    <h2 class="h4 fw-bold mb-1">Reagents & Laboratory Consumables</h2>
    <p class="text-muted small mb-0">Monitor analyzer test packs, vacutainers, controls, and minimum safety thresholds</p>
  </div>
  <div class="d-flex flex-wrap gap-2">
    <a href="<?= ADMIN_URL ?>/inventory/batches.php" class="btn btn-outline-secondary d-inline-flex align-items-center gap-2">
      <i class="bi bi-clock-history"></i> Batches & Expiry Engine
    </a>
    <button class="btn btn-primary-lm d-inline-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#newItemModal">
      <i class="bi bi-plus-circle"></i> Add Reagent / Consumable
    </button>
  </div>
</div>

<!-- Search & Category Filters -->
<div class="admin-card mb-4">
  <div class="admin-card-body p-3">
    <form method="GET" action="" class="row g-2 align-items-center">
      <div class="col-md-6">
        <div class="input-group">
          <span class="input-group-text bg-light text-muted"><i class="bi bi-search"></i></span>
          <input type="text" name="search" class="form-control" placeholder="Search item by name, code, manufacturer..." value="<?= e($search) ?>">
        </div>
      </div>
      <div class="col-md-4">
        <select name="category" class="form-select">
          <option value="">All Supply Categories</option>
          <?php foreach (['Reagent', 'Test Kit', 'Calibrator', 'Control', 'Collection Tube', 'Needle/Consumable', 'Chemical', 'Other'] as $c): ?>
            <option value="<?= $c ?>" <?= $categoryFilter === $c ? 'selected' : '' ?>><?= $c ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2 d-flex gap-2">
        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-funnel"></i> Filter</button>
        <?php if (!empty($search) || !empty($categoryFilter)): ?>
          <a href="<?= ADMIN_URL ?>/inventory/index.php" class="btn btn-outline-secondary" title="Reset"><i class="bi bi-x-lg"></i></a>
        <?php endif; ?>
      </div>
    </form>
  </div>
</div>

<!-- Inventory Items Table -->
<div class="admin-card">
  <div class="admin-card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>Item Code</th>
            <th>Item Name & Manufacturer</th>
            <th>Category</th>
            <th>In-Stock Qty</th>
            <th>Safety Threshold</th>
            <th>Nearest Expiry</th>
            <th>Storage Cond.</th>
            <th class="text-end text-nowrap pe-3" style="width: 120px;">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($items)): ?>
            <tr><td colspan="8" class="text-center text-muted py-5">No catalog items matching criteria.</td></tr>
          <?php else: ?>
            <?php foreach ($items as $it): ?>
              <?php $isLow = $it['current_stock'] <= $it['minimum_stock']; ?>
              <tr class="<?= $isLow ? 'table-danger bg-opacity-10' : '' ?>">
                <td><span class="badge bg-light text-dark border font-monospace"><?= e($it['item_code']) ?></span></td>
                <td>
                  <div class="fw-bold text-dark"><?= e($it['name']) ?></div>
                  <div class="text-muted small"><?= e($it['manufacturer'] ?: 'Standard Laboratory Supplier') ?></div>
                </td>
                <td><span class="badge bg-info-subtle text-info-emphasis"><?= e($it['category']) ?></span></td>
                <td>
                  <span class="fs-6 fw-bold <?= $isLow ? 'text-danger' : 'text-success' ?>">
                    <?= $it['current_stock'] ?>
                  </span>
                  <span class="text-muted small"><?= e($it['unit']) ?></span>
                  <?php if ($isLow): ?>
                    <span class="badge bg-danger ms-1" style="font-size:0.65rem;">LOW STOCK</span>
                  <?php endif; ?>
                </td>
                <td class="text-muted small"><?= $it['minimum_stock'] ?> <?= e($it['unit']) ?></td>
                <td>
                  <?php if ($it['nearest_expiry']): ?>
                    <?php
                    $daysLeft = ceil((strtotime($it['nearest_expiry']) - time()) / 86400);
                    $expBadge = $daysLeft <= 30 ? 'bg-danger text-white' : ($daysLeft <= 60 ? 'bg-warning text-dark' : 'bg-light text-dark border');
                    ?>
                    <span class="badge <?= $expBadge ?>">
                      <?= format_date($it['nearest_expiry']) ?> (<?= $daysLeft ?>d)
                    </span>
                  <?php else: ?>
                    <span class="text-muted small">No active batches</span>
                  <?php endif; ?>
                </td>
                <td class="small">
                  <div class="text-dark"><i class="bi bi-thermometer-half text-primary"></i> <?= e($it['storage_temperature']) ?></div>
                  <div class="text-muted" style="font-size: 0.72rem;"><?= e($it['storage_location']) ?></div>
                </td>
                <td class="text-end text-nowrap pe-3">
                  <div class="d-inline-flex align-items-center justify-content-end gap-1">
                    <!-- View Batches Button -->
                    <a href="<?= ADMIN_URL ?>/inventory/batches.php?item_id=<?= $it['id'] ?>" 
                       class="btn btn-sm btn-outline-primary btn-action-icon" 
                       title="View Batches (<?= $it['active_batches'] ?>)">
                      <i class="bi bi-layers"></i>
                    </a>

                    <!-- Edit Item Button -->
                    <?php if (Auth::can('inventory.manage')): ?>
                    <button type="button" 
                            class="btn btn-sm btn-outline-secondary btn-action-icon" 
                            onclick='openEditItemModal(<?= htmlspecialchars(json_encode($it), ENT_QUOTES, "UTF-8") ?>)' 
                            title="Edit Item Details">
                      <i class="bi bi-pencil"></i>
                    </button>
                    <?php endif; ?>

                    <!-- Delete Item Button -->
                    <?php if (Auth::can('inventory.manage')): ?>
                    <form method="POST" action="<?= ADMIN_URL ?>/inventory/index.php" class="d-inline m-0 p-0" 
                          onsubmit="return confirm('Delete or deactivate item <?= e(addslashes($it['name'])) ?> (<?= e($it['item_code']) ?>)?');">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="delete_item">
                      <input type="hidden" name="item_id" value="<?= $it['id'] ?>">
                      <button type="submit" 
                              class="btn btn-sm btn-outline-danger btn-action-icon" 
                              title="Delete Item">
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

<!-- Modal: New Inventory Item -->
<div class="modal fade" id="newItemModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="POST" action="">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add_item">
        <div class="modal-header bg-light">
          <h5 class="modal-title fw-bold"><i class="bi bi-plus-circle text-primary me-2"></i>Add Reagent or Supply Item</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body p-4">
          <div class="row g-3">
            <div class="col-md-8">
              <label class="form-label small fw-semibold">Item Name <span class="text-danger">*</span></label>
              <input type="text" name="name" class="form-control" placeholder="e.g. Total Cholesterol Reagent Kit" required>
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-semibold">Item Code <span class="text-danger">*</span></label>
              <input type="text" name="item_code" class="form-control font-monospace text-uppercase" placeholder="e.g. REG-CHOL" required>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-semibold">Category <span class="text-danger">*</span></label>
              <select name="category" class="form-select" required>
                <?php foreach (['Reagent', 'Test Kit', 'Calibrator', 'Control', 'Collection Tube', 'Needle/Consumable', 'Chemical', 'Other'] as $c): ?>
                  <option value="<?= $c ?>"><?= $c ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-semibold">Manufacturer</label>
              <input type="text" name="manufacturer" class="form-control" placeholder="e.g. Roche / Transasia / BD">
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-semibold">Unit of Measurement</label>
              <input type="text" name="unit" class="form-control" placeholder="e.g. Kits, Bottles, Vials, Boxes" value="Kits" required>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-semibold">Minimum Safety Threshold <span class="text-danger">*</span></label>
              <input type="number" name="minimum_stock" class="form-control" value="5" required>
              <div class="form-text">Triggers dashboard warning when stock dips to or below this level.</div>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-semibold">Storage Temperature</label>
              <select name="storage_temperature" class="form-select">
                <option value="2°C - 8°C (Refrigerated)">2°C - 8°C (Refrigerated)</option>
                <option value="15°C - 25°C (Room Temp)">15°C - 25°C (Room Temp)</option>
                <option value="-20°C (Frozen)">-20°C (Frozen)</option>
                <option value="Dry Controlled Room">Dry Controlled Room</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-semibold">Storage Location</label>
              <input type="text" name="storage_location" class="form-control" placeholder="e.g. Fridge 1 - Shelf B">
            </div>
          </div>
        </div>
        <div class="modal-footer bg-light">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-save"><i class="bi bi-check-circle me-1"></i> Save Supply Item</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal: Edit Inventory Item -->
<div class="modal fade" id="editItemModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="POST" action="">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="edit_item">
        <input type="hidden" name="item_id" id="edit_item_id">

        <div class="modal-header bg-light">
          <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square text-primary me-2"></i>Edit Reagent / Supply Item</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body p-4">
          <div class="row g-3">
            <div class="col-md-8">
              <label class="form-label small fw-semibold">Item Name <span class="text-danger">*</span></label>
              <input type="text" name="name" id="edit_item_name" class="form-control" required>
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-semibold">Item Code <span class="text-danger">*</span></label>
              <input type="text" name="item_code" id="edit_item_code" class="form-control font-monospace text-uppercase" required>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-semibold">Category <span class="text-danger">*</span></label>
              <select name="category" id="edit_category" class="form-select" required>
                <?php foreach (['Reagent', 'Test Kit', 'Calibrator', 'Control', 'Collection Tube', 'Needle/Consumable', 'Chemical', 'Other'] as $c): ?>
                  <option value="<?= $c ?>"><?= $c ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-semibold">Manufacturer</label>
              <input type="text" name="manufacturer" id="edit_manufacturer" class="form-control">
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-semibold">Unit of Measurement</label>
              <input type="text" name="unit" id="edit_unit" class="form-control" required>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-semibold">Minimum Safety Threshold <span class="text-danger">*</span></label>
              <input type="number" name="minimum_stock" id="edit_minimum_stock" class="form-control" required>
              <div class="form-text">Triggers dashboard warning when stock dips to or below this level.</div>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-semibold">Storage Temperature</label>
              <select name="storage_temperature" id="edit_storage_temperature" class="form-select">
                <option value="2°C - 8°C (Refrigerated)">2°C - 8°C (Refrigerated)</option>
                <option value="15°C - 25°C (Room Temp)">15°C - 25°C (Room Temp)</option>
                <option value="-20°C (Frozen)">-20°C (Frozen)</option>
                <option value="Dry Controlled Room">Dry Controlled Room</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-semibold">Storage Location</label>
              <input type="text" name="storage_location" id="edit_storage_location" class="form-control">
            </div>
          </div>
        </div>
        <div class="modal-footer bg-light">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-save"><i class="bi bi-check-circle me-1"></i> Update Supply Item</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function openEditItemModal(item) {
  document.getElementById('edit_item_id').value = item.id;
  document.getElementById('edit_item_name').value = item.name || '';
  document.getElementById('edit_item_code').value = item.item_code || '';
  document.getElementById('edit_category').value = item.category || 'Reagent';
  document.getElementById('edit_manufacturer').value = item.manufacturer || '';
  document.getElementById('edit_unit').value = item.unit || 'Kits';
  document.getElementById('edit_minimum_stock').value = item.minimum_stock || 5;
  document.getElementById('edit_storage_temperature').value = item.storage_temperature || '2°C - 8°C (Refrigerated)';
  document.getElementById('edit_storage_location').value = item.storage_location || '';

  const modal = new bootstrap.Modal(document.getElementById('editItemModal'));
  modal.show();
}
</script>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>

<?php
/**
 * LabMaster - Inventory Movement Ledger & Stock Audit Trail
 */

declare(strict_types=1);

$pageTitle = 'Stock Movement Ledger';
$activeNav = 'movements';

require_once dirname(__DIR__) . '/includes/auth_guard.php';
Auth::requirePermission('inventory.manage');

$typeFilter = trim($_GET['type'] ?? '');

$sql = "SELECT m.*, i.name as item_name, i.item_code, i.unit, b.batch_number, u.full_name as user_name
        FROM `stock_movements` m
        JOIN `inventory_items` i ON m.item_id = i.id
        LEFT JOIN `inventory_batches` b ON m.batch_id = b.id
        LEFT JOIN `users` u ON m.performed_by = u.id
        WHERE 1=1";
$params = [];

if (!empty($typeFilter)) {
    $sql .= " AND m.movement_type = ?";
    $params[] = $typeFilter;
}

$sql .= " ORDER BY m.id DESC LIMIT 200";
$movements = Database::fetchAll($sql, $params);

require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
  <div>
    <h2 class="h4 fw-bold mb-1">Inventory Ledger & Stock Movement Audit</h2>
    <p class="text-muted small mb-0">Immutable tracking of reagent purchases, analyzer consumption, and manual adjustments</p>
  </div>
</div>

<!-- Type Filter Pills -->
<div class="d-flex flex-wrap gap-2 mb-3">
  <a href="?type=" class="btn btn-sm <?= empty($typeFilter) ? 'btn-primary-lm' : 'btn-outline-secondary' ?>">All Movements</a>
  <a href="?type=Purchase" class="btn btn-sm <?= $typeFilter === 'Purchase' ? 'btn-success text-white' : 'btn-outline-secondary' ?>">Purchases (Stock In)</a>
  <a href="?type=Test_Consumption" class="btn btn-sm <?= $typeFilter === 'Test_Consumption' ? 'btn-info text-dark' : 'btn-outline-secondary' ?>">Analyzer Test Consumption</a>
  <a href="?type=Adjustment_Add" class="btn btn-sm <?= $typeFilter === 'Adjustment_Add' ? 'btn-primary text-white' : 'btn-outline-secondary' ?>">Adjustment Add</a>
  <a href="?type=Expired_Discard" class="btn btn-sm <?= $typeFilter === 'Expired_Discard' ? 'btn-danger text-white' : 'btn-outline-secondary' ?>">Expired Discards</a>
</div>

<!-- Stock Ledger Table -->
<div class="admin-card">
  <div class="admin-card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0 small">
        <thead class="table-light">
          <tr>
            <th>Date & Time</th>
            <th>Item & Code</th>
            <th>Batch / Lot</th>
            <th>Movement Type</th>
            <th>Change Qty</th>
            <th>Before &rarr; After</th>
            <th>Reference & Reason</th>
            <th>Operator</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($movements)): ?>
            <tr><td colspan="8" class="text-center text-muted py-5">No stock movement records found.</td></tr>
          <?php else: ?>
            <?php foreach ($movements as $m): ?>
              <?php
              $isAddition = in_array($m['movement_type'], ['Purchase', 'Adjustment_Add', 'Return'], true);
              ?>
              <tr>
                <td class="text-muted"><?= format_date($m['created_at'], true) ?></td>
                <td>
                  <div class="fw-bold text-dark"><?= e($m['item_name']) ?></div>
                  <span class="badge bg-light text-dark border font-monospace"><?= e($m['item_code']) ?></span>
                </td>
                <td>
                  <span class="badge bg-light text-secondary font-monospace"><?= e($m['batch_number'] ?: 'N/A') ?></span>
                </td>
                <td>
                  <?php
                  $tBadge = match ($m['movement_type']) {
                      'Purchase' => 'bg-success-subtle text-success-emphasis border border-success',
                      'Test_Consumption' => 'bg-info-subtle text-info-emphasis border border-info',
                      'Expired_Discard' => 'bg-danger-subtle text-danger-emphasis border border-danger',
                      default => 'bg-secondary-subtle text-secondary-emphasis'
                  };
                  ?>
                  <span class="badge <?= $tBadge ?>"><?= e($m['movement_type']) ?></span>
                </td>
                <td>
                  <span class="fw-bold fs-6 <?= $isAddition ? 'text-success' : 'text-danger' ?>">
                    <?= $isAddition ? '+' : '-' ?><?= $m['quantity'] ?> <?= e($m['unit']) ?>
                  </span>
                </td>
                <td>
                  <span class="text-muted"><?= $m['quantity_before'] ?></span>
                  <i class="bi bi-arrow-right mx-1 text-secondary"></i>
                  <span class="fw-bold text-dark"><?= $m['quantity_after'] ?></span>
                </td>
                <td>
                  <?php if (!empty($m['reference_id'])): ?>
                    <span class="font-monospace text-primary fw-semibold"><?= e($m['reference_id']) ?>:</span>
                  <?php endif; ?>
                  <span class="text-muted"><?= e($m['reason'] ?: 'Routine operation') ?></span>
                </td>
                <td><?= e($m['user_name'] ?: 'System') ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>

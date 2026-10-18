<?php
/**
 * LabMaster - Orders Awaiting Result Entry
 */

declare(strict_types=1);

$pageTitle = 'Result Entry Worklist';
$activeNav = 'results';

require_once dirname(__DIR__) . '/includes/auth_guard.php';
Auth::requirePermission('results.entry');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_order') {
    require_csrf();
    $orderId = (int)($_POST['order_id'] ?? 0);
    $order = Database::fetch("SELECT * FROM `test_orders` WHERE `id` = ?", [$orderId]);
    if ($order) {
        $hasReports = (int)(Database::fetch("SELECT COUNT(*) as c FROM `reports` WHERE `order_id` = ?", [$orderId])['c'] ?? 0);
        $hasInvoices = (int)(Database::fetch("SELECT COUNT(*) as c FROM `invoices` WHERE `order_id` = ?", [$orderId])['c'] ?? 0);
        if ($hasReports > 0 || $hasInvoices > 0) {
            Database::execute("UPDATE `test_orders` SET `status` = 'Cancelled' WHERE `id` = ?", [$orderId]);
            log_audit('Order Cancelled', 'Results', $order['accession_number'], "Cancelled order {$order['accession_number']}.");
            set_flash('warning', "Order \"{$order['accession_number']}\" has linked reports or billing records; marked as Cancelled.");
        } else {
            Database::execute("DELETE FROM `test_order_items` WHERE `order_id` = ?", [$orderId]);
            Database::execute("DELETE FROM `test_orders` WHERE `id` = ?", [$orderId]);
            log_audit('Order Removed', 'Results', $order['accession_number'], "Removed order accession {$order['accession_number']}.");
            set_flash('success', "Order accession \"{$order['accession_number']}\" removed successfully.");
        }
    } else {
        set_flash('danger', 'Order record not found.');
    }
    header('Location: ' . ADMIN_URL . '/results/index.php');
    exit;
}

$orders = Database::fetchAll(
    "SELECT o.*, p.full_name as patient_name, p.patient_id as patient_code, p.age, p.gender,
            (SELECT COUNT(*) FROM `test_order_items` oi WHERE oi.order_id = o.id) as item_count
     FROM `test_orders` o
     JOIN `patients` p ON o.patient_id = p.id
     WHERE o.status IN ('Order Created', 'Sample Collected', 'Processing', 'Results Entered')
     ORDER BY (CASE WHEN o.priority = 'Stat' THEN 1 WHEN o.priority = 'Urgent' THEN 2 ELSE 3 END), o.id ASC"
);

require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
  <div>
    <h2 class="h4 fw-bold mb-1">Analyzer Result Entry Worklist</h2>
    <p class="text-muted small mb-0">Specimens queued for clinical result entry and automated range checking</p>
  </div>
</div>

<div class="admin-card">
  <div class="admin-card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>Priority</th>
            <th>Accession Number</th>
            <th>Patient Details</th>
            <th>Investigations</th>
            <th>Order Date</th>
            <th>Status</th>
            <th class="text-end text-nowrap pe-3" style="width: 140px;">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($orders)): ?>
            <tr>
              <td colspan="7" class="text-center text-muted py-5">
                <i class="bi bi-check2-all fs-1 text-success d-block mb-2"></i>
                All active accessions have results entered! No pending worklist orders.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($orders as $ord): ?>
              <tr>
                <td>
                  <?php if ($ord['priority'] === 'Stat'): ?>
                    <span class="badge bg-danger pulse-alert">STAT</span>
                  <?php elseif ($ord['priority'] === 'Urgent'): ?>
                    <span class="badge bg-warning text-dark">Urgent</span>
                  <?php else: ?>
                    <span class="badge bg-light text-secondary border">Routine</span>
                  <?php endif; ?>
                </td>
                <td>
                  <span class="fw-bold font-monospace text-primary"><?= e($ord['accession_number']) ?></span>
                </td>
                <td>
                  <div class="fw-bold text-dark"><?= e($ord['patient_name']) ?></div>
                  <div class="small text-muted font-monospace"><?= e($ord['patient_code']) ?> (<?= $ord['age'] ?> Y / <?= e($ord['gender']) ?>)</div>
                </td>
                <td>
                  <span class="badge bg-info-subtle text-info-emphasis"><?= $ord['item_count'] ?> Tests</span>
                </td>
                <td class="small"><?= format_date($ord['order_date']) ?></td>
                <td>
                  <span class="badge bg-warning-subtle text-warning-emphasis"><?= e($ord['status']) ?></span>
                </td>
                <td class="text-end text-nowrap pe-3">
                  <div class="d-inline-flex align-items-center justify-content-end gap-1">
                    <a href="<?= ADMIN_URL ?>/orders/view.php?id=<?= $ord['id'] ?>" class="btn btn-sm btn-outline-primary btn-action-icon" title="View Order Details">
                      <i class="bi bi-eye"></i>
                    </a>
                    <a href="<?= ADMIN_URL ?>/results/enter.php?order_id=<?= $ord['id'] ?>" class="btn btn-sm btn-outline-warning btn-action-icon" title="Enter / Edit Results">
                      <i class="bi bi-pencil-square"></i>
                    </a>
                    <form method="POST" action="<?= ADMIN_URL ?>/results/index.php" class="d-inline m-0 p-0" onsubmit="return confirm('Are you sure you want to cancel / remove order <?= e($ord['accession_number']) ?>?');">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="delete_order">
                      <input type="hidden" name="order_id" value="<?= $ord['id'] ?>">
                      <button type="submit" class="btn btn-sm btn-outline-danger btn-action-icon" title="Cancel / Remove Order">
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

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>

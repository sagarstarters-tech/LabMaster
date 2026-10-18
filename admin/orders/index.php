<?php
/**
 * LabMaster - Test Orders / Accessioning Master Directory
 */

declare(strict_types=1);

$pageTitle = 'Test Orders & Accessions';
$activeNav = 'orders';

require_once dirname(__DIR__) . '/includes/auth_guard.php';
Auth::requirePermission('orders.view');

$status = trim($_GET['status'] ?? '');
$search = trim($_GET['search'] ?? '');
$priority = trim($_GET['priority'] ?? '');

$sql = "SELECT o.*, p.full_name as patient_name, p.patient_id as patient_code, p.gender, p.age,
               d.name as doctor_name,
               (SELECT COUNT(*) FROM `test_order_items` oi WHERE oi.order_id = o.id) as item_count,
               (SELECT COUNT(*) FROM `reports` r WHERE r.order_id = o.id) as has_report
        FROM `test_orders` o
        JOIN `patients` p ON o.patient_id = p.id
        LEFT JOIN `doctors` d ON o.referring_doctor_id = d.id
        WHERE 1=1";
$params = [];

if (!empty($status)) {
    $sql .= " AND o.status = ?";
    $params[] = $status;
}
if (!empty($priority)) {
    $sql .= " AND o.priority = ?";
    $params[] = $priority;
}
if (!empty($search)) {
    $sql .= " AND (o.accession_number LIKE ? OR p.full_name LIKE ? OR p.patient_id LIKE ? OR p.phone LIKE ?)";
    $like = "%{$search}%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$sql .= " ORDER BY o.id DESC";
$orders = Database::fetchAll($sql, $params);

// Handle POST Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'delete_order') {
        Auth::requirePermission('orders.create');
        $orderId = (int)($_POST['order_id'] ?? 0);
        $ord = Database::fetch("SELECT * FROM `test_orders` WHERE `id` = ?", [$orderId]);
        if (!$ord) {
            set_flash('danger', 'Order not found.');
        } else {
            $reportCount = (int)Database::fetchColumn("SELECT COUNT(*) FROM `reports` WHERE `order_id` = ?", [$orderId]);
            if ($ord['status'] === 'Cancelled' || $reportCount === 0) {
                Database::execute("DELETE FROM `reports` WHERE `order_id` = ?", [$orderId]);
                Database::execute("DELETE FROM `test_results` WHERE `order_id` = ?", [$orderId]);
                Database::execute("DELETE FROM `test_order_items` WHERE `order_id` = ?", [$orderId]);
                Database::execute("DELETE FROM `invoices` WHERE `order_id` = ?", [$orderId]);
                Database::execute("DELETE FROM `test_orders` WHERE `id` = ?", [$orderId]);
                log_audit('Order Deleted', 'Orders', $ord['accession_number'], "Permanently deleted order {$ord['accession_number']}.");
                set_flash('success', "Order \"{$ord['accession_number']}\" permanently deleted successfully.");
            } else {
                Database::execute("UPDATE `test_orders` SET `status` = 'Cancelled' WHERE `id` = ?", [$orderId]);
                log_audit('Order Cancelled', 'Orders', $ord['accession_number'], "Cancelled order {$ord['accession_number']} (reports existed).");
                set_flash('warning', "Order \"{$ord['accession_number']}\" has clinical reports on file, so it was marked as Cancelled. Click delete again to permanently remove it.");
            }
            header('Location: ' . ADMIN_URL . '/orders/index.php');
            exit;
        }
    }
}

require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
  <div>
    <h2 class="h4 fw-bold mb-1">Laboratory Orders & Accessions</h2>
    <p class="text-muted small mb-0">Total <?= count($orders) ?> accession records found</p>
  </div>
  <?php if (Auth::can('orders.create')): ?>
  <a href="<?= ADMIN_URL ?>/orders/create.php" class="btn btn-primary-lm d-inline-flex align-items-center gap-2">
    <i class="bi bi-plus-circle"></i> Create New Accession
  </a>
  <?php endif; ?>
</div>

<!-- Search & Filter Card -->
<div class="admin-card mb-4">
  <div class="admin-card-body p-3">
    <form method="GET" action="" class="row g-2 align-items-center">
      <div class="col-md-5">
        <div class="input-group">
          <span class="input-group-text bg-light text-muted"><i class="bi bi-search"></i></span>
          <input type="text" name="search" class="form-control" placeholder="Search Accession No, Patient Name, or ID..." value="<?= e($search) ?>">
        </div>
      </div>
      <div class="col-md-3">
        <select name="status" class="form-select">
          <option value="">All Workflow Statuses</option>
          <?php foreach (['Order Created', 'Sample Collected', 'Processing', 'Results Entered', 'Under Review', 'Approved', 'Cancelled'] as $st): ?>
            <option value="<?= $st ?>" <?= $status === $st ? 'selected' : '' ?>><?= $st ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <select name="priority" class="form-select">
          <option value="">All Priorities</option>
          <option value="Routine" <?= $priority === 'Routine' ? 'selected' : '' ?>>Routine</option>
          <option value="Urgent" <?= $priority === 'Urgent' ? 'selected' : '' ?>>Urgent</option>
          <option value="Stat" <?= $priority === 'Stat' ? 'selected' : '' ?>>STAT (Emergency)</option>
        </select>
      </div>
      <div class="col-md-2 d-flex gap-2">
        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-funnel"></i> Filter</button>
        <?php if (!empty($search) || !empty($status) || !empty($priority)): ?>
          <a href="<?= ADMIN_URL ?>/orders/index.php" class="btn btn-outline-secondary" title="Reset"><i class="bi bi-x-lg"></i></a>
        <?php endif; ?>
      </div>
    </form>
  </div>
</div>

<!-- Orders Table -->
<div class="admin-card">
  <div class="admin-card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>Accession No</th>
            <th>Patient Details</th>
            <th>Order Date</th>
            <th>Priority</th>
            <th>Tests</th>
            <th>Amount</th>
            <th>Status</th>
            <th>Payment</th>
            <th class="text-end text-nowrap pe-3" style="width: 150px;">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($orders)): ?>
            <tr><td colspan="9" class="text-center text-muted py-5">No test orders found.</td></tr>
          <?php else: ?>
            <?php foreach ($orders as $ord): ?>
              <tr>
                <td>
                  <a href="<?= ADMIN_URL ?>/orders/view.php?id=<?= $ord['id'] ?>" class="fw-bold font-monospace text-primary text-decoration-none">
                    <?= e($ord['accession_number']) ?>
                  </a>
                </td>
                <td>
                  <div class="fw-semibold text-dark"><?= e($ord['patient_name']) ?></div>
                  <div class="small text-muted font-monospace"><?= e($ord['patient_code']) ?> (<?= $ord['age'] ?> Y / <?= e($ord['gender']) ?>)</div>
                </td>
                <td class="small"><?= format_date($ord['order_date']) ?></td>
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
                  <span class="badge bg-light text-dark border"><?= $ord['item_count'] ?> Investigations</span>
                </td>
                <td class="fw-bold text-dark">
                  <?= format_currency($ord['net_amount']) ?>
                </td>
                <td>
                  <?php
                  $stClass = match ($ord['status']) {
                      'Order Created' => 'bg-secondary',
                      'Sample Collected' => 'bg-info text-dark',
                      'Processing' => 'bg-primary',
                      'Results Entered' => 'bg-warning text-dark',
                      'Approved' => 'bg-success',
                      default => 'bg-light text-dark border'
                  };
                  ?>
                  <span class="badge <?= $stClass ?>"><?= e($ord['status']) ?></span>
                </td>
                <td>
                  <span class="badge <?= $ord['payment_status'] === 'Paid' ? 'bg-success-subtle text-success-emphasis' : 'bg-warning-subtle text-warning-emphasis' ?>">
                    <?= e($ord['payment_status']) ?>
                  </span>
                </td>
                <td class="text-end text-nowrap pe-3">
                  <div class="d-inline-flex align-items-center justify-content-end gap-1">
                    <a href="<?= ADMIN_URL ?>/orders/view.php?id=<?= $ord['id'] ?>" class="btn btn-sm btn-outline-primary btn-action-icon" title="View Order Details">
                      <i class="bi bi-eye"></i>
                    </a>
                    <?php if (Auth::can('results.entry')): ?>
                    <a href="<?= ADMIN_URL ?>/results/enter.php?order_id=<?= $ord['id'] ?>" class="btn btn-sm btn-outline-secondary btn-action-icon" title="Enter / Review Results">
                      <i class="bi bi-pencil-square"></i>
                    </a>
                    <?php endif; ?>
                    <?php if ($ord['has_report'] > 0): ?>
                    <a href="<?= ADMIN_URL ?>/reports/view.php?order_id=<?= $ord['id'] ?>" class="btn btn-sm btn-outline-success btn-action-icon" title="View Clinical Report">
                      <i class="bi bi-file-earmark-medical"></i>
                    </a>
                    <?php endif; ?>
                    <?php if (Auth::can('orders.create')): ?>
                    <?php
                    $isCancelled = $ord['status'] === 'Cancelled';
                    $confirmMsg = $isCancelled
                        ? 'Permanently delete cancelled order ' . e(addslashes($ord['accession_number'])) . ' and all related reports?'
                        : 'Delete or cancel order ' . e(addslashes($ord['accession_number'])) . '?';
                    $btnTitle = $isCancelled ? 'Permanently Delete Order' : 'Delete / Cancel Order';
                    ?>
                    <form method="POST" action="<?= ADMIN_URL ?>/orders/index.php" class="d-inline m-0 p-0" onsubmit="return confirm('<?= $confirmMsg ?>');">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="delete_order">
                      <input type="hidden" name="order_id" value="<?= $ord['id'] ?>">
                      <button type="submit" class="btn btn-sm btn-outline-danger btn-action-icon" title="<?= $btnTitle ?>">
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

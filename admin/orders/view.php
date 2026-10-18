<?php
/**
 * LabMaster - Laboratory Accession Order Details & Sample Workflow
 */

declare(strict_types=1);

$orderId = (int)($_GET['id'] ?? 0);
$pageTitle = 'Accession Details';
$activeNav = 'orders';

require_once dirname(__DIR__) . '/includes/auth_guard.php';
Auth::requirePermission('orders.view');

$order = Database::fetch(
    "SELECT o.*, p.full_name as patient_name, p.patient_id as patient_code, p.gender, p.age, p.phone, p.email,
            d.name as doctor_name, u.full_name as creator_name
     FROM `test_orders` o
     JOIN `patients` p ON o.patient_id = p.id
     LEFT JOIN `doctors` d ON o.referring_doctor_id = d.id
     LEFT JOIN `users` u ON o.created_by = u.id
     WHERE o.id = ?",
    [$orderId]
);

if (!$order) {
    set_flash('danger', 'Laboratory accession not found.');
    header('Location: ' . ADMIN_URL . '/orders/index.php');
    exit;
}

// Handle Sample Status Update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'update_sample_status') {
        Auth::requirePermission('orders.sample_collect');
        $itemId = (int)($_POST['item_id'] ?? 0);
        $sampleStatus = trim($_POST['sample_status'] ?? 'Collected');
        $rejectionReason = trim($_POST['rejection_reason'] ?? '');
        $userId = Auth::id();

        if ($sampleStatus === 'Collected') {
            Database::execute(
                "UPDATE `test_order_items` SET `sample_status` = ?, `collected_at` = NOW(), `collected_by` = ? WHERE `id` = ? AND `order_id` = ?",
                [$sampleStatus, $userId, $itemId, $orderId]
            );
        } elseif ($sampleStatus === 'Received') {
            Database::execute(
                "UPDATE `test_order_items` SET `sample_status` = ?, `received_at` = NOW() WHERE `id` = ? AND `order_id` = ?",
                [$sampleStatus, $itemId, $orderId]
            );
        } else {
            Database::execute(
                "UPDATE `test_order_items` SET `sample_status` = ?, `rejection_reason` = ? WHERE `id` = ? AND `order_id` = ?",
                [$sampleStatus, $rejectionReason, $itemId, $orderId]
            );
        }

        // Update overall order status if all received
        $pendingCount = (int)(Database::fetch(
            "SELECT COUNT(*) as c FROM `test_order_items` WHERE `order_id` = ? AND `sample_status` IN ('Pending', 'Collected')",
            [$orderId]
        )['c'] ?? 0);

        if ($pendingCount === 0 && $order['status'] === 'Order Created') {
            Database::execute("UPDATE `test_orders` SET `status` = 'Processing' WHERE `id` = ?", [$orderId]);
        } elseif ($order['status'] === 'Order Created') {
            Database::execute("UPDATE `test_orders` SET `status` = 'Sample Collected' WHERE `id` = ?", [$orderId]);
        }

        log_audit('Sample Status Updated', 'Orders', $order['accession_number'], "Updated sample item ID {$itemId} to {$sampleStatus}.");
        set_flash('success', "Sample status updated to {$sampleStatus}.");
        header("Location: " . ADMIN_URL . "/orders/view.php?id={$orderId}");
        exit;
    }

    if ($action === 'update_doctor_association' && (Auth::can('doctors.edit') || Auth::can('orders.manage') || Auth::can('orders.create'))) {
        $newDoctorId = !empty($_POST['referring_doctor_id']) ? (int)$_POST['referring_doctor_id'] : null;
        $reason = trim($_POST['change_reason'] ?? 'Administrative update');
        
        $oldDocId = (int)($order['referring_doctor_id'] ?? 0);
        $oldDocName = $oldDocId > 0 ? (Database::fetch("SELECT name FROM `doctors` WHERE `id` = ?", [$oldDocId])['name'] ?? 'Doctor #' . $oldDocId) : 'Direct Walk-in / Self';
        $newDocName = $newDoctorId ? (Database::fetch("SELECT name FROM `doctors` WHERE `id` = ?", [$newDoctorId])['name'] ?? 'Doctor #' . $newDoctorId) : 'Direct Walk-in / Self';

        Database::execute(
            "UPDATE `test_orders` SET `referring_doctor_id` = ? WHERE `id` = ?",
            [$newDoctorId, $orderId]
        );

        log_audit(
            'Order Doctor Association Updated',
            'Orders',
            $order['accession_number'],
            "Reassigned referring doctor from '{$oldDocName}' to '{$newDocName}'. Reason: {$reason}"
        );
        set_flash('success', "Referring doctor association updated to {$newDocName}. Changes recorded in audit trail.");
        header("Location: " . ADMIN_URL . "/orders/view.php?id={$orderId}");
        exit;
    }
}

// Fetch Order Items
$items = Database::fetchAll(
    "SELECT oi.*, t.test_name, t.test_code, t.specimen_type, t.turnaround_time, u.full_name as phlebotomist_name
     FROM `test_order_items` oi
     JOIN `tests` t ON oi.test_id = t.id
     LEFT JOIN `users` u ON oi.collected_by = u.id
     WHERE oi.order_id = ?",
    [$orderId]
);

// Check if Report Exists
$report = Database::fetch("SELECT * FROM `reports` WHERE `order_id` = ? LIMIT 1", [$orderId]);

// Check if Invoice Exists
$invoice = Database::fetch("SELECT * FROM `invoices` WHERE `order_id` = ? LIMIT 1", [$orderId]);

// Fetch Active Doctors for Association
$allActiveDoctors = Database::fetchAll("SELECT id, doctor_code, name, specialization, clinic_hospital FROM `doctors` WHERE `is_active` = 1 ORDER BY name ASC");

require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
  <div>
    <h2 class="h4 fw-bold mb-1">
      Accession: <?= e($order['accession_number']) ?>
      <span class="badge bg-primary-subtle text-primary-emphasis ms-2"><?= e($order['status']) ?></span>
    </h2>
    <p class="text-muted small mb-0">Ordered on <?= format_date($order['order_date']) ?> • Registered by <?= e($order['creator_name'] ?: 'Staff') ?></p>
  </div>
  <div class="d-flex gap-2">
    <?php if (Auth::can('results.entry')): ?>
    <a href="<?= ADMIN_URL ?>/results/enter.php?order_id=<?= $order['id'] ?>" class="btn btn-warning btn-sm fw-semibold">
      <i class="bi bi-pencil-square me-1"></i> Enter / Edit Results
    </a>
    <?php endif; ?>
    <?php if ($report && Auth::can('reports.view')): ?>
    <a href="<?= ADMIN_URL ?>/reports/view.php?id=<?= $report['id'] ?>" class="btn btn-success btn-sm">
      <i class="bi bi-file-earmark-medical me-1"></i> View Clinical Report
    </a>
    <?php endif; ?>
    <?php if ($invoice && Auth::can('billing.manage')): ?>
    <a href="<?= ADMIN_URL ?>/billing/view.php?id=<?= $invoice['id'] ?>" class="btn btn-info btn-sm text-dark">
      <i class="bi bi-receipt me-1"></i> Invoice (<?= format_currency($invoice['grand_total']) ?>)
    </a>
    <?php endif; ?>
  </div>
</div>

<!-- Accession Details Cards -->
<div class="row g-3 mb-4">
  <div class="col-md-3 col-sm-6">
    <div class="p-3 bg-white rounded border shadow-sm">
      <div class="text-muted small">Patient</div>
      <div class="fw-bold fs-6 text-dark"><?= e($order['patient_name']) ?></div>
      <div class="small text-muted font-monospace"><?= e($order['patient_code']) ?> (<?= $order['age'] ?> Y / <?= e($order['gender']) ?>)</div>
      <div class="small text-primary"><i class="bi bi-telephone me-1"></i><?= e($order['phone']) ?></div>
    </div>
  </div>

  <div class="col-md-3 col-sm-6">
    <div class="p-3 bg-white rounded border shadow-sm">
      <div class="d-flex justify-content-between align-items-center mb-1">
        <span class="text-muted small">Referring Doctor</span>
        <?php if (Auth::can('doctors.edit') || Auth::can('orders.manage')): ?>
          <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none" data-bs-toggle="modal" data-bs-target="#reassignDoctorModal" title="Associate / Reassign Doctor">
            <i class="bi bi-pencil-square"></i> Change
          </button>
        <?php endif; ?>
      </div>
      <div class="fw-bold fs-6 text-dark text-truncate">
        <?php if (!empty($order['doctor_name'])): ?>
          <a href="<?= ADMIN_URL ?>/doctors/view.php?id=<?= $order['referring_doctor_id'] ?>" class="text-dark text-decoration-none">
            <?= e($order['doctor_name']) ?>
          </a>
        <?php else: ?>
          Direct Walk-in / Self
        <?php endif; ?>
      </div>
      <div class="small text-muted">Priority: 
        <span class="badge <?= $order['priority'] === 'Stat' ? 'bg-danger' : ($order['priority'] === 'Urgent' ? 'bg-warning text-dark' : 'bg-light text-dark border') ?>">
          <?= e($order['priority']) ?>
        </span>
      </div>
    </div>
  </div>

  <div class="col-md-3 col-sm-6">
    <div class="p-3 bg-white rounded border shadow-sm">
      <div class="text-muted small">Financial Summary</div>
      <div class="fw-bold fs-6 text-dark"><?= format_currency($order['net_amount']) ?></div>
      <div class="small">
        Payment: 
        <span class="badge <?= $order['payment_status'] === 'Paid' ? 'bg-success' : 'bg-warning text-dark' ?>">
          <?= e($order['payment_status']) ?>
        </span>
      </div>
    </div>
  </div>

  <div class="col-md-3 col-sm-6">
    <div class="p-3 bg-white rounded border shadow-sm">
      <div class="text-muted small">Report Status</div>
      <div class="fw-bold fs-6 text-dark">
        <?= $report ? e($report['report_status']) : 'Pending Generation' ?>
      </div>
      <div class="small text-muted">
        <?= $report && $report['approved_at'] ? 'Approved ' . format_date($report['approved_at'], true) : 'Under Clinical Review' ?>
      </div>
    </div>
  </div>
</div>

<!-- Order Investigations & Specimen Workflow Table -->
<div class="admin-card">
  <div class="admin-card-header bg-light d-flex justify-content-between align-items-center">
    <h3 class="admin-card-title text-primary">
      <i class="bi bi-eyedropper"></i> Specimen & Investigation Tracking
    </h3>
    <span class="badge bg-light text-dark border"><?= count($items) ?> Tests In Accession</span>
  </div>
  <div class="admin-card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0 small">
        <thead class="table-light">
          <tr>
            <th>Test Code & Name</th>
            <th>Sample Type</th>
            <th>Barcode Identifier</th>
            <th>Phlebotomy Status</th>
            <th>Collected Info</th>
            <th class="text-end">Manage Specimen</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($items as $item): ?>
            <tr>
              <td>
                <div class="fw-bold fs-6"><?= e($item['test_name']) ?></div>
                <span class="badge bg-light text-dark border font-monospace"><?= e($item['test_code']) ?></span>
              </td>
              <td>
                <span class="badge bg-danger-subtle text-danger-emphasis">
                  <i class="bi bi-droplet-half me-1"></i><?= e($item['sample_type'] ?: $item['specimen_type']) ?>
                </span>
              </td>
              <td>
                <span class="badge bg-dark font-monospace"><i class="bi bi-upc me-1"></i><?= e($item['sample_barcode']) ?></span>
              </td>
              <td>
                <?php
                $sBadge = match ($item['sample_status']) {
                    'Pending' => 'bg-warning text-dark',
                    'Collected' => 'bg-info text-dark',
                    'Received' => 'bg-success text-white',
                    'Rejected' => 'bg-danger text-white',
                    default => 'bg-secondary'
                };
                ?>
                <span class="badge <?= $sBadge ?>"><?= e($item['sample_status']) ?></span>
              </td>
              <td>
                <?php if ($item['collected_at']): ?>
                  <div class="text-dark fw-semibold"><?= format_date($item['collected_at'], true) ?></div>
                  <div class="text-muted" style="font-size:0.75rem;">By: <?= e($item['phlebotomist_name'] ?: 'Staff') ?></div>
                <?php else: ?>
                  <span class="text-muted">Awaiting Phlebotomy</span>
                <?php endif; ?>
              </td>
              <td class="text-end text-nowrap pe-3">
                <div class="d-inline-flex align-items-center justify-content-end gap-1">
                  <?php if (Auth::can('orders.sample_collect')): ?>
                    <?php if ($item['sample_status'] === 'Pending'): ?>
                      <form method="POST" action="" class="d-inline m-0 p-0">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="update_sample_status">
                        <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                        <input type="hidden" name="sample_status" value="Collected">
                        <button type="submit" class="btn btn-sm btn-outline-info btn-action-icon" title="Mark Specimen Collected">
                          <i class="bi bi-droplet-half"></i>
                        </button>
                      </form>
                    <?php elseif ($item['sample_status'] === 'Collected'): ?>
                      <form method="POST" action="" class="d-inline m-0 p-0">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="update_sample_status">
                        <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                        <input type="hidden" name="sample_status" value="Received">
                        <button type="submit" class="btn btn-sm btn-outline-success btn-action-icon" title="Mark Received in Laboratory">
                          <i class="bi bi-box-arrow-in-down"></i>
                        </button>
                      </form>
                    <?php else: ?>
                      <span class="badge bg-success-subtle text-success-emphasis"><i class="bi bi-check-all me-1"></i> In Laboratory</span>
                    <?php endif; ?>
                  <?php else: ?>
                    <span class="badge <?= $sBadge ?>"><?= e($item['sample_status']) ?></span>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Reassign / Associate Referring Doctor Modal -->
<div class="modal fade" id="reassignDoctorModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" action="">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="update_doctor_association">

        <div class="modal-header">
          <h5 class="modal-title fw-bold text-dark">
            <i class="bi bi-person-check-fill text-primary me-2"></i>Associate / Update Referring Doctor
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="alert alert-light border small text-secondary mb-3">
            <strong>Accession #<?= e($order['accession_number']) ?></strong> for patient <strong><?= e($order['patient_name']) ?></strong>.
            <div class="mt-1">Changes to doctor association are permanently tracked in the system audit trail.</div>
          </div>

          <div class="mb-3">
            <label class="form-label small fw-semibold">Referring Physician / Institution</label>
            <select name="referring_doctor_id" class="form-select" required>
              <option value="">-- Direct Walk-in / Self (No Doctor) --</option>
              <?php foreach ($allActiveDoctors as $doc): ?>
                <option value="<?= $doc['id'] ?>" <?= (int)($order['referring_doctor_id'] ?? 0) === (int)$doc['id'] ? 'selected' : '' ?>>
                  <?= e($doc['name']) ?> (<?= e($doc['doctor_code']) ?>) &bull; <?= e($doc['specialization'] ?: 'General Practice') ?> [<?= e($doc['clinic_hospital'] ?: 'Independent') ?>]
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="mb-3">
            <label class="form-label small fw-semibold">Reason for Association Update <span class="text-danger">*</span></label>
            <input type="text" name="change_reason" class="form-control" 
                   placeholder="e.g. Patient prescription submitted post-registration" required>
            <small class="text-muted" style="font-size:0.75rem;">Mandatory for clinical and administrative audit trail.</small>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Save Doctor Association</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>


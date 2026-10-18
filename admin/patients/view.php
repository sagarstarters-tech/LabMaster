<?php
/**
 * LabMaster - Comprehensive Patient Profile & Clinical History View
 */

declare(strict_types=1);

$pageTitle = 'Patient Medical Record';
$activeNav = 'patients';

require_once dirname(__DIR__) . '/includes/auth_guard.php';
Auth::requirePermission('patients.view');

$patientId = (int)($_GET['id'] ?? 0);
if ($patientId <= 0) {
    set_flash('danger', 'Invalid patient record requested.');
    header('Location: ' . ADMIN_URL . '/patients/index.php');
    exit;
}

$patient = Database::fetch(
    "SELECT p.*, d.name as doctor_name, d.specialization as doctor_spec 
     FROM `patients` p 
     LEFT JOIN `doctors` d ON p.referring_doctor_id = d.id 
     WHERE p.id = ?",
    [$patientId]
);

if (!$patient) {
    set_flash('danger', 'Patient record not found.');
    header('Location: ' . ADMIN_URL . '/patients/index.php');
    exit;
}

// Fetch all test orders for this patient
$orders = Database::fetchAll(
    "SELECT o.*, u.full_name as created_by_name,
            (SELECT COUNT(*) FROM `test_order_items` oi WHERE oi.order_id = o.id) as item_count
     FROM `test_orders` o
     LEFT JOIN `users` u ON o.created_by = u.id
     WHERE o.patient_id = ?
     ORDER BY o.id DESC",
    [$patientId]
);

// Fetch all approved/draft pathology reports
$reports = Database::fetchAll(
    "SELECT r.*, o.accession_number, u.full_name as pathologist_name
     FROM `reports` r
     JOIN `test_orders` o ON r.order_id = o.id
     LEFT JOIN `users` u ON r.approved_by = u.id
     WHERE r.patient_id = ?
     ORDER BY r.id DESC",
    [$patientId]
);

// Fetch all billing invoices
$invoices = Database::fetchAll(
    "SELECT * FROM `invoices` WHERE `patient_id` = ? ORDER BY `id` DESC",
    [$patientId]
);

// Calculate total balance
$totalBalance = (float)(Database::fetch(
    "SELECT COALESCE(SUM(`balance_amount`), 0) as s FROM `invoices` WHERE `patient_id` = ? AND `payment_status` != 'Cancelled'",
    [$patientId]
)['s'] ?? 0);

require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
  <div>
    <h2 class="h4 fw-bold mb-1">
      <?= e($patient['full_name']) ?>
      <span class="badge bg-light text-dark border fs-6 font-monospace ms-2"><?= e($patient['patient_id']) ?></span>
    </h2>
    <p class="text-muted small mb-0">Registered on <?= format_date($patient['created_at']) ?></p>
  </div>
  <div class="d-flex gap-2">
    <?php if (Auth::can('orders.create')): ?>
    <a href="<?= ADMIN_URL ?>/orders/create.php?patient_id=<?= $patient['id'] ?>" class="btn btn-primary-lm btn-sm">
      <i class="bi bi-plus-circle me-1"></i> New Test Accession
    </a>
    <?php endif; ?>
    <?php if (Auth::can('patients.edit')): ?>
    <a href="<?= ADMIN_URL ?>/patients/edit.php?id=<?= $patient['id'] ?>" class="btn btn-outline-secondary btn-sm">
      <i class="bi bi-pencil me-1"></i> Edit Profile
    </a>
    <?php endif; ?>
  </div>
</div>

<!-- Patient Summary Cards -->
<div class="row g-3 mb-4">
  <div class="col-md-3 col-sm-6">
    <div class="p-3 bg-white rounded border shadow-sm">
      <div class="text-muted small">Age & Gender</div>
      <div class="h5 fw-bold mb-0 text-dark"><?= $patient['age'] ?> <?= e($patient['age_unit']) ?> / <?= e($patient['gender']) ?></div>
      <div class="small text-muted">DOB: <?= format_date($patient['dob']) ?></div>
    </div>
  </div>
  <div class="col-md-3 col-sm-6">
    <div class="p-3 bg-white rounded border shadow-sm">
      <div class="text-muted small">Mobile Phone</div>
      <div class="h5 fw-bold mb-0 text-dark"><?= e($patient['phone']) ?></div>
      <div class="small text-muted"><?= e($patient['email'] ?: 'No email on file') ?></div>
    </div>
  </div>
  <div class="col-md-3 col-sm-6">
    <div class="p-3 bg-white rounded border shadow-sm">
      <div class="text-muted small">Referring Doctor</div>
      <div class="h5 fw-bold mb-0 text-dark text-truncate"><?= e($patient['doctor_name'] ?: 'Direct / Walk-in') ?></div>
      <div class="small text-muted"><?= e($patient['doctor_spec'] ?: 'N/A') ?></div>
    </div>
  </div>
  <div class="col-md-3 col-sm-6">
    <div class="p-3 bg-white rounded border shadow-sm">
      <div class="text-muted small">Outstanding Balance</div>
      <div class="h5 fw-bold mb-0 <?= $totalBalance > 0 ? 'text-danger' : 'text-success' ?>">
        <?= format_currency($totalBalance) ?>
      </div>
      <div class="small text-muted"><?= count($invoices) ?> Total Invoices</div>
    </div>
  </div>
</div>

<!-- Tabs for Orders, Reports, and Invoices -->
<div class="admin-card">
  <div class="admin-card-header p-0 border-bottom">
    <ul class="nav nav-tabs border-0" id="patientTabs" role="tablist">
      <li class="nav-item">
        <button class="nav-link active px-4 py-3 fw-semibold border-0" id="orders-tab" data-bs-toggle="tab" data-bs-target="#ordersPane" type="button">
          <i class="bi bi-collection-fill me-1 text-primary"></i> Test Accessions (<?= count($orders) ?>)
        </button>
      </li>
      <li class="nav-item">
        <button class="nav-link px-4 py-3 fw-semibold border-0" id="reports-tab" data-bs-toggle="tab" data-bs-target="#reportsPane" type="button">
          <i class="bi bi-file-earmark-medical-fill me-1 text-success"></i> Pathology Reports (<?= count($reports) ?>)
        </button>
      </li>
      <li class="nav-item">
        <button class="nav-link px-4 py-3 fw-semibold border-0" id="billing-tab" data-bs-toggle="tab" data-bs-target="#billingPane" type="button">
          <i class="bi bi-receipt me-1 text-info"></i> Invoices & Payments (<?= count($invoices) ?>)
        </button>
      </li>
    </ul>
  </div>

  <div class="admin-card-body p-0">
    <div class="tab-content" id="patientTabContent">
      <!-- 1. Orders Tab -->
      <div class="tab-pane fade show active p-3" id="ordersPane">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0 small">
            <thead class="table-light">
              <tr>
                <th>Accession No</th>
                <th>Order Date</th>
                <th>Tests</th>
                <th>Priority</th>
                <th>Total</th>
                <th>Status</th>
                <th>Payment</th>
                <th class="text-end text-nowrap pe-3" style="width: 120px;">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($orders)): ?>
                <tr><td colspan="8" class="text-center text-muted py-4">No test orders created yet for this patient.</td></tr>
              <?php else: ?>
                <?php foreach ($orders as $ord): ?>
                  <tr>
                    <td class="fw-bold font-monospace"><?= e($ord['accession_number']) ?></td>
                    <td><?= format_date($ord['order_date']) ?></td>
                    <td><span class="badge bg-light text-dark border"><?= $ord['item_count'] ?> Tests</span></td>
                    <td>
                      <?php if ($ord['priority'] === 'Stat'): ?>
                        <span class="badge bg-danger">STAT</span>
                      <?php elseif ($ord['priority'] === 'Urgent'): ?>
                        <span class="badge bg-warning text-dark">Urgent</span>
                      <?php else: ?>
                        <span class="badge bg-light text-secondary">Routine</span>
                      <?php endif; ?>
                    </td>
                    <td class="fw-bold"><?= format_currency($ord['net_amount']) ?></td>
                    <td>
                      <span class="badge bg-info-subtle text-info-emphasis"><?= e($ord['status']) ?></span>
                    </td>
                    <td>
                      <span class="badge <?= $ord['payment_status'] === 'Paid' ? 'bg-success' : 'bg-warning text-dark' ?>">
                        <?= e($ord['payment_status']) ?>
                      </span>
                    </td>
                    <td class="text-end text-nowrap pe-3">
                      <div class="d-inline-flex align-items-center justify-content-end gap-1">
                        <a href="<?= ADMIN_URL ?>/orders/view.php?id=<?= $ord['id'] ?>" class="btn btn-sm btn-outline-primary btn-action-icon" title="View Order">
                          <i class="bi bi-eye"></i>
                        </a>
                        <a href="<?= ADMIN_URL ?>/results/enter.php?order_id=<?= $ord['id'] ?>" class="btn btn-sm btn-outline-warning btn-action-icon" title="Enter / Edit Results">
                          <i class="bi bi-pencil-square"></i>
                        </a>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- 2. Reports Tab -->
      <div class="tab-pane fade p-3" id="reportsPane">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0 small">
            <thead class="table-light">
              <tr>
                <th>Report Number</th>
                <th>Order Accession</th>
                <th>Status</th>
                <th>Approved By</th>
                <th>Date</th>
                <th class="text-end text-nowrap pe-3" style="width: 140px;">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($reports)): ?>
                <tr><td colspan="6" class="text-center text-muted py-4">No reports recorded for this patient.</td></tr>
              <?php else: ?>
                <?php foreach ($reports as $rep): ?>
                  <tr>
                    <td class="fw-bold font-monospace"><?= e($rep['report_number']) ?></td>
                    <td><?= e($rep['accession_number']) ?></td>
                    <td>
                      <?php if ($rep['report_status'] === 'Approved'): ?>
                        <span class="badge bg-success-subtle text-success-emphasis"><i class="bi bi-check-circle me-1"></i> Approved</span>
                      <?php else: ?>
                        <span class="badge bg-warning text-dark"><?= e($rep['report_status']) ?></span>
                      <?php endif; ?>
                    </td>
                    <td><?= e($rep['pathologist_name'] ?: 'Pending Review') ?></td>
                    <td><?= format_date($rep['approved_at'] ?: $rep['created_at'], true) ?></td>
                    <td class="text-end text-nowrap pe-3">
                      <div class="d-inline-flex align-items-center justify-content-end gap-1">
                        <a href="<?= ADMIN_URL ?>/reports/view.php?id=<?= $rep['id'] ?>" class="btn btn-sm btn-outline-primary btn-action-icon" title="View Full Report"><i class="bi bi-eye"></i></a>
                        <a href="<?= ADMIN_URL ?>/reports/print.php?id=<?= $rep['id'] ?>" target="_blank" class="btn btn-sm btn-outline-secondary btn-action-icon" title="Print Report"><i class="bi bi-printer"></i></a>
                        <a href="<?= ADMIN_URL ?>/reports/pdf.php?id=<?= $rep['id'] ?>" target="_blank" class="btn btn-sm btn-outline-danger btn-action-icon" title="Download PDF"><i class="bi bi-file-earmark-pdf"></i></a>
                        <?php if (Auth::can('reports.whatsapp') && $rep['report_status'] === 'Approved'): ?>
                          <a href="<?= ADMIN_URL ?>/reports/share_whatsapp.php?id=<?= $rep['id'] ?>" class="btn btn-sm btn-outline-success btn-action-icon" title="Share via WhatsApp"><i class="bi bi-whatsapp"></i></a>
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

      <!-- 3. Billing Tab -->
      <div class="tab-pane fade p-3" id="billingPane">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0 small">
            <thead class="table-light">
              <tr>
                <th>Invoice Number</th>
                <th>Date</th>
                <th>Subtotal</th>
                <th>Discount</th>
                <th>Grand Total</th>
                <th>Paid</th>
                <th>Balance</th>
                <th>Status</th>
                <th class="text-end text-nowrap pe-3" style="width: 120px;">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($invoices)): ?>
                <tr><td colspan="9" class="text-center text-muted py-4">No billing invoices found.</td></tr>
              <?php else: ?>
                <?php foreach ($invoices as $inv): ?>
                  <tr>
                    <td class="fw-bold font-monospace"><?= e($inv['invoice_number']) ?></td>
                    <td><?= format_date($inv['invoice_date']) ?></td>
                    <td><?= format_currency($inv['subtotal']) ?></td>
                    <td><?= format_currency($inv['discount_amount']) ?></td>
                    <td class="fw-bold"><?= format_currency($inv['grand_total']) ?></td>
                    <td class="text-success fw-semibold"><?= format_currency($inv['paid_amount']) ?></td>
                    <td class="<?= $inv['balance_amount'] > 0 ? 'text-danger fw-bold' : 'text-muted' ?>">
                      <?= format_currency($inv['balance_amount']) ?>
                    </td>
                    <td>
                      <span class="badge <?= $inv['payment_status'] === 'Paid' ? 'bg-success' : ($inv['payment_status'] === 'Partially Paid' ? 'bg-info' : 'bg-danger') ?>">
                        <?= e($inv['payment_status']) ?>
                      </span>
                    </td>
                    <td class="text-end text-nowrap pe-3">
                      <div class="d-inline-flex align-items-center justify-content-end gap-1">
                        <a href="<?= ADMIN_URL ?>/billing/view.php?id=<?= $inv['id'] ?>" class="btn btn-sm btn-outline-primary btn-action-icon" title="View Bill & Payments">
                          <i class="bi bi-eye"></i>
                        </a>
                        <a href="<?= ADMIN_URL ?>/billing/print_invoice.php?id=<?= $inv['id'] ?>" target="_blank" class="btn btn-sm btn-outline-secondary btn-action-icon" title="Print Invoice Receipt">
                          <i class="bi bi-printer"></i>
                        </a>
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
  </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>

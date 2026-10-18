<?php
/**
 * LabMaster - Advanced Admin Control Dashboard
 * Live real-time database-driven pathology operations analytics
 */

declare(strict_types=1);

$pageTitle = 'Dashboard Overview';
$activeNav = 'dashboard';

require_once __DIR__ . '/includes/header.php';

// 1. Calculate Real Clinical & Financial Metrics from Database
$today = date('Y-m-d');
$monthStart = date('Y-m-01');

// Patients metrics
$totalPatients = (int)(Database::fetch("SELECT COUNT(*) as c FROM `patients`")['c'] ?? 0);
$todayPatients = (int)(Database::fetch("SELECT COUNT(*) as c FROM `patients` WHERE DATE(`created_at`) = ?", [$today])['c'] ?? 0);

// Bookings metrics
$todayBookings = (int)(Database::fetch("SELECT COUNT(*) as c FROM `bookings` WHERE DATE(`created_at`) = ? OR `preferred_date` = ?", [$today, $today])['c'] ?? 0);

// Operational order & sample metrics
$pendingSamples = (int)(Database::fetch("SELECT COUNT(*) as c FROM `test_order_items` WHERE `sample_status` IN ('Pending', 'Collected')")['c'] ?? 0);
$testsInProgress = (int)(Database::fetch("SELECT COUNT(*) as c FROM `test_orders` WHERE `status` IN ('Sample Collected', 'Processing', 'Results Entered')")['c'] ?? 0);
$reportsPendingApproval = (int)(Database::fetch("SELECT COUNT(*) as c FROM `reports` WHERE `report_status` = 'Pending Approval'")['c'] ?? 0);
$reportsCompleted = (int)(Database::fetch("SELECT COUNT(*) as c FROM `reports` WHERE `report_status` = 'Approved'")['c'] ?? 0);
$reportsDelivered = (int)(Database::fetch("SELECT COUNT(DISTINCT `report_id`) as c FROM `whatsapp_logs` WHERE `status` IN ('Sent', 'Delivered')")['c'] ?? 0);

// Financial metrics
$todayCollections = (float)(Database::fetch("SELECT COALESCE(SUM(`amount`), 0) as s FROM `payments` WHERE `payment_date` = ?", [$today])['s'] ?? 0);
$monthlyRevenue = (float)(Database::fetch("SELECT COALESCE(SUM(`amount`), 0) as s FROM `payments` WHERE `payment_date` >= ?", [$monthStart])['s'] ?? 0);
$outstandingBalances = (float)(Database::fetch("SELECT COALESCE(SUM(`balance_amount`), 0) as s FROM `invoices` WHERE `payment_status` IN ('Unpaid', 'Partially Paid')")['s'] ?? 0);
$pendingSupplierPayments = (float)(Database::fetch("SELECT COALESCE(SUM(`balance_amount`), 0) as s FROM `purchases` WHERE `payment_status` IN ('Unpaid', 'Partially Paid')")['s'] ?? 0);

// Inventory alerts
$lowStockItems = (int)(Database::fetch("SELECT COUNT(*) as c FROM `inventory_items` WHERE `current_stock` <= `minimum_stock` AND `is_active` = 1")['c'] ?? 0);
$expiringReagents = (int)(Database::fetch(
    "SELECT COUNT(*) as c FROM `inventory_batches` 
     WHERE `status` IN ('Active', 'Near Expiry') 
       AND `expiry_date` <= DATE_ADD(CURDATE(), INTERVAL 60 DAY) 
       AND `current_quantity` > 0"
)['c'] ?? 0);

// 2. Fetch Recent Transactions
$recentPayments = Database::fetchAll(
    "SELECT p.*, i.invoice_number, pt.full_name as patient_name, pt.patient_id as patient_code 
     FROM `payments` p
     JOIN `invoices` i ON p.invoice_id = i.id
     JOIN `patients` pt ON i.patient_id = pt.id
     ORDER BY p.id DESC LIMIT 5"
);

// 3. Fetch Recent Patient Registrations
$recentPatients = Database::fetchAll(
    "SELECT * FROM `patients` ORDER BY `id` DESC LIMIT 5"
);

// 4. Fetch Recent Pathology Reports
$recentReports = Database::fetchAll(
    "SELECT r.*, pt.full_name as patient_name, pt.patient_id as patient_code, o.accession_number
     FROM `reports` r
     JOIN `patients` pt ON r.patient_id = pt.id
     JOIN `test_orders` o ON r.order_id = o.id
     ORDER BY r.id DESC LIMIT 5"
);

// 5. Weekly Trend Data for Chart
$trendLabels = [];
$trendData = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-{$i} days"));
    $trendLabels[] = date('D (d M)', strtotime($d));
    $cnt = (int)(Database::fetch("SELECT COUNT(*) as c FROM `test_orders` WHERE `order_date` = ?", [$d])['c'] ?? 0);
    $trendData[] = $cnt;
}

// 6. Fetch recent orders for clinical staff who do not have financial access
$recentOrders = Database::fetchAll(
    "SELECT o.*, p.full_name as patient_name, p.patient_id as patient_code, d.name as doctor_name
     FROM `test_orders` o
     JOIN `patients` p ON o.patient_id = p.id
     LEFT JOIN `doctors` d ON o.referring_doctor_id = d.id
     ORDER BY o.id DESC LIMIT 5"
);
?>

<!-- Quick Action & Welcome Bar -->
<div class="row align-items-center mb-4">
  <div class="col-md-7">
    <div class="d-flex align-items-center gap-2 mb-1">
      <h2 class="h4 fw-bold mb-0">Welcome back, <?= e($currentUser['full_name']) ?>!</h2>
      <?php if (Auth::isSuperAdmin()): ?>
        <span class="badge bg-danger"><i class="bi bi-shield-shaded me-1"></i> Root Administrator</span>
      <?php elseif (Auth::isReceptionist()): ?>
        <span class="badge bg-primary"><i class="bi bi-person-badge me-1"></i> Front Desk & Reception</span>
      <?php elseif (Auth::isTechnician()): ?>
        <span class="badge bg-info text-dark"><i class="bi bi-eyedropper me-1"></i> Laboratory Technician</span>
      <?php elseif (Auth::isPathologist()): ?>
        <span class="badge bg-success"><i class="bi bi-clipboard2-pulse me-1"></i> Consultant Pathologist</span>
      <?php else: ?>
        <span class="badge bg-secondary"><?= e($currentUser['role_name']) ?></span>
      <?php endif; ?>
    </div>
    <p class="text-muted small mb-0">
      <i class="bi bi-clock me-1"></i> Today: <?= date('l, d F Y') ?> | Current Shift Operational Live Feed
    </p>
  </div>
  <div class="col-md-5 text-md-end mt-3 mt-md-0 d-flex gap-2 justify-content-md-end flex-wrap">
    <?php if (Auth::can('patients.create')): ?>
      <a href="<?= ADMIN_URL ?>/patients/create.php" class="btn btn-sm btn-outline-primary d-inline-flex align-items-center gap-1">
        <i class="bi bi-person-plus-fill"></i> Add Patient
      </a>
    <?php endif; ?>

    <?php if (Auth::can('orders.create')): ?>
      <a href="<?= ADMIN_URL ?>/orders/create.php" class="btn btn-sm btn-primary-lm d-inline-flex align-items-center gap-1">
        <i class="bi bi-plus-lg"></i> New Accession
      </a>
    <?php endif; ?>

    <?php if (Auth::can('results.entry') && !Auth::can('orders.create')): ?>
      <a href="<?= ADMIN_URL ?>/results/index.php" class="btn btn-sm btn-warning text-dark fw-semibold d-inline-flex align-items-center gap-1">
        <i class="bi bi-pencil-square"></i> Enter Results
      </a>
    <?php endif; ?>

    <?php if (Auth::can('reports.review') && !Auth::can('orders.create')): ?>
      <a href="<?= ADMIN_URL ?>/reports/index.php?status=Pending+Approval" class="btn btn-sm btn-danger d-inline-flex align-items-center gap-1">
        <i class="bi bi-shield-check"></i> Review & Approve
      </a>
    <?php endif; ?>

    <?php if (Auth::can('users.manage')): ?>
      <a href="<?= ADMIN_URL ?>/users/roles.php" class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-1">
        <i class="bi bi-shield-lock-fill text-warning"></i> Manage Permissions
      </a>
    <?php endif; ?>
  </div>
</div>

<!-- Primary Operational Metrics Grid -->
<div class="row g-3 mb-4">
  <?php if (Auth::can('patients.view')): ?>
  <!-- Registered Patients -->
  <div class="col-xl-3 col-sm-6">
    <a href="<?= ADMIN_URL ?>/patients/index.php" class="text-decoration-none d-block h-100" title="View Patient Directory">
      <div class="stat-card">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <span class="stat-label">Total Patients</span>
          <div class="stat-icon bg-primary bg-opacity-10 text-primary">
            <i class="bi bi-people-fill"></i>
          </div>
        </div>
        <div class="stat-val"><?= number_format($totalPatients) ?></div>
        <div class="small text-muted">
          <span class="text-success fw-semibold"><i class="bi bi-arrow-up"></i> +<?= $todayPatients ?></span> today
        </div>
      </div>
    </a>
  </div>
  <?php endif; ?>

  <?php if (Auth::can('bookings.manage')): ?>
  <!-- Today's Bookings -->
  <div class="col-xl-3 col-sm-6">
    <a href="<?= ADMIN_URL ?>/bookings/index.php" class="text-decoration-none d-block h-100" title="Review Quick Bookings">
      <div class="stat-card">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <span class="stat-label">Today's Bookings</span>
          <div class="stat-icon bg-warning bg-opacity-10 text-warning">
            <i class="bi bi-calendar2-check-fill"></i>
          </div>
        </div>
        <div class="stat-val"><?= $todayBookings ?></div>
        <div class="small text-muted">
          <span class="text-primary fw-medium">Review bookings &rarr;</span>
        </div>
      </div>
    </a>
  </div>
  <?php endif; ?>

  <?php if (Auth::can('reports.review') || Auth::can('reports.view')): ?>
  <!-- Pending Approvals -->
  <div class="col-xl-3 col-sm-6">
    <a href="<?= ADMIN_URL ?>/reports/index.php?status=Pending+Approval" class="text-decoration-none d-block h-100" title="Review Reports Requiring Pathologist Sign-off">
      <div class="stat-card">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <span class="stat-label">Pending Approval</span>
          <div class="stat-icon bg-danger bg-opacity-10 text-danger">
            <i class="bi bi-shield-exclamation"></i>
          </div>
        </div>
        <div class="stat-val text-danger"><?= $reportsPendingApproval ?></div>
        <div class="small text-muted">
          Requires Pathologist sign-off &rarr;
        </div>
      </div>
    </a>
  </div>
  <?php endif; ?>

  <?php if (Auth::can('billing.manage') || Auth::can('accounting.manage')): ?>
  <!-- Today's Collections -->
  <div class="col-xl-3 col-sm-6">
    <a href="<?= ADMIN_URL ?>/billing/index.php" class="text-decoration-none d-block h-100" title="View Invoices & Billing">
      <div class="stat-card">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <span class="stat-label">Today's Collections</span>
          <div class="stat-icon bg-success bg-opacity-10 text-success">
            <i class="bi bi-cash-stack"></i>
          </div>
        </div>
        <div class="stat-val"><?= format_currency($todayCollections) ?></div>
        <div class="small text-muted">
          Month Revenue: <strong><?= format_currency($monthlyRevenue) ?></strong>
        </div>
      </div>
    </a>
  </div>
  <?php endif; ?>
</div>

<!-- Secondary Operational Metrics (Samples, Progress, Balances, Inventory) -->
<div class="row g-3 mb-4">
  <?php if (Auth::can('orders.sample_collect') || Auth::can('orders.view')): ?>
  <div class="col-md-3 col-6">
    <a href="<?= ADMIN_URL ?>/orders/index.php" class="text-decoration-none d-block h-100 text-dark" title="View Orders & Sample Accessions">
      <div class="p-3 bg-white rounded border shadow-sm d-flex align-items-center gap-3 metric-card-hover h-100">
        <div class="stat-icon bg-info bg-opacity-10 text-info">
          <i class="bi bi-droplet-half"></i>
        </div>
        <div>
          <div class="h5 mb-0 fw-bold"><?= $pendingSamples ?></div>
          <div class="text-muted small">Pending Samples</div>
        </div>
      </div>
    </a>
  </div>
  <?php endif; ?>

  <?php if (Auth::can('results.entry')): ?>
  <div class="col-md-3 col-6">
    <a href="<?= ADMIN_URL ?>/results/index.php" class="text-decoration-none d-block h-100 text-dark" title="View Result Entry Worklist">
      <div class="p-3 bg-white rounded border shadow-sm d-flex align-items-center gap-3 metric-card-hover h-100">
        <div class="stat-icon bg-primary bg-opacity-10 text-primary">
          <i class="bi bi-gear-wide-connected"></i>
        </div>
        <div>
          <div class="h5 mb-0 fw-bold"><?= $testsInProgress ?></div>
          <div class="text-muted small">Tests In Progress</div>
        </div>
      </div>
    </a>
  </div>
  <?php endif; ?>

  <?php if (Auth::can('billing.manage')): ?>
  <div class="col-md-3 col-6">
    <a href="<?= ADMIN_URL ?>/billing/index.php?status=Unpaid" class="text-decoration-none d-block h-100 text-dark" title="View Unpaid Patient Invoices">
      <div class="p-3 bg-white rounded border shadow-sm d-flex align-items-center gap-3 metric-card-hover h-100">
        <div class="stat-icon bg-danger bg-opacity-10 text-danger">
          <i class="bi bi-credit-card-2-front"></i>
        </div>
        <div>
          <div class="h5 mb-0 fw-bold text-danger"><?= format_currency($outstandingBalances) ?></div>
          <div class="text-muted small">Patient Outstanding</div>
        </div>
      </div>
    </a>
  </div>
  <?php endif; ?>

  <?php if (Auth::can('inventory.manage')): ?>
  <div class="col-md-3 col-6">
    <a href="<?= ADMIN_URL ?>/inventory/batches.php" class="text-decoration-none d-block h-100 text-dark" title="View Batches & Expiry Management">
      <div class="p-3 bg-white rounded border shadow-sm d-flex align-items-center gap-3 metric-card-hover h-100">
        <div class="stat-icon bg-warning bg-opacity-10 text-warning">
          <i class="bi bi-exclamation-triangle-fill"></i>
        </div>
        <div>
          <div class="h5 mb-0 fw-bold"><?= $lowStockItems ?> / <?= $expiringReagents ?></div>
          <div class="text-muted small">Low Stock / Expiring</div>
        </div>
      </div>
    </a>
  </div>
  <?php endif; ?>
</div>

<!-- Charts & Quick Overview Row -->
<div class="row g-3 mb-4">
  <!-- 7-Day Order Volume Trend Chart -->
  <div class="col-lg-8">
    <div class="admin-card h-100 mb-0">
      <div class="admin-card-header">
        <h3 class="admin-card-title">
          <i class="bi bi-graph-up-arrow text-primary"></i> Accessions & Test Volume (Past 7 Days)
        </h3>
        <span class="badge bg-light text-dark border">Automated Counter</span>
      </div>
      <div class="admin-card-body">
        <canvas id="orderTrendChart" height="110"></canvas>
      </div>
    </div>
  </div>

  <!-- Operational Status Breakdown -->
  <div class="col-lg-4">
    <div class="admin-card h-100 mb-0">
      <div class="admin-card-header">
        <h3 class="admin-card-title">
          <i class="bi bi-pie-chart-fill text-info"></i> Clinical Pipeline
        </h3>
      </div>
      <div class="admin-card-body">
        <div class="list-group list-group-flush small">
          <a href="<?= ADMIN_URL ?>/orders/index.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center px-2 py-2 rounded text-decoration-none" title="View Pending Phlebotomy Accessions">
            <span><i class="bi bi-hourglass-split text-secondary me-2"></i> Pending Phlebotomy</span>
            <span class="badge bg-secondary rounded-pill"><?= $pendingSamples ?></span>
          </a>
          <a href="<?= ADMIN_URL ?>/results/index.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center px-2 py-2 rounded text-decoration-none" title="View Analyzer Processing Worklist">
            <span><i class="bi bi-cpu text-info me-2"></i> Analyzer Processing</span>
            <span class="badge bg-info rounded-pill"><?= $testsInProgress ?></span>
          </a>
          <a href="<?= ADMIN_URL ?>/reports/index.php?status=Pending+Approval" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center px-2 py-2 rounded text-decoration-none" title="View Reports Pending Pathologist Review">
            <span><i class="bi bi-check2-circle text-primary me-2"></i> Pending Pathologist Review</span>
            <span class="badge bg-primary rounded-pill"><?= $reportsPendingApproval ?></span>
          </a>
          <a href="<?= ADMIN_URL ?>/reports/index.php?status=Approved" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center px-2 py-2 rounded text-decoration-none" title="View Approved Reports Released">
            <span><i class="bi bi-patch-check-fill text-success me-2"></i> Approved Reports Released</span>
            <span class="badge bg-success rounded-pill"><?= $reportsCompleted ?></span>
          </a>
          <a href="<?= ADMIN_URL ?>/reports/index.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center px-2 py-2 rounded text-decoration-none" title="View Dispatched Reports">
            <span><i class="bi bi-whatsapp text-success me-2"></i> Dispatched on WhatsApp</span>
            <span class="badge bg-success-subtle text-success-emphasis rounded-pill"><?= $reportsDelivered ?></span>
          </a>
          <a href="<?= ADMIN_URL ?>/suppliers/purchases.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center px-2 py-2 rounded text-decoration-none" title="View Supplier Payables & Purchases">
            <span><i class="bi bi-truck text-warning me-2"></i> Supplier Payables Due</span>
            <span class="badge bg-warning-subtle text-warning-emphasis rounded-pill"><?= format_currency($pendingSupplierPayments) ?></span>
          </a>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Recent Activity Tables: Recent Reports, Recent Patients & Cash Inflow -->
<div class="row g-3">
  <!-- Recent Reports -->
  <div class="col-lg-6">
    <div class="admin-card">
      <div class="admin-card-header">
        <h3 class="admin-card-title">
          <i class="bi bi-file-earmark-medical text-primary"></i> Recent Pathology Reports
        </h3>
        <a href="<?= ADMIN_URL ?>/reports/index.php" class="btn btn-sm btn-link text-decoration-none">View All</a>
      </div>
      <div class="admin-card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0 small">
            <thead class="table-light">
              <tr>
                <th>Report No</th>
                <th>Patient</th>
                <th>Status</th>
                <th class="text-end text-nowrap pe-3" style="width: 100px;">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($recentReports)): ?>
                <tr><td colspan="4" class="text-center text-muted py-3">No reports generated yet</td></tr>
              <?php else: ?>
                <?php foreach ($recentReports as $rep): ?>
                  <tr>
                    <td>
                      <span class="fw-bold"><?= e($rep['report_number']) ?></span><br>
                      <span class="text-muted" style="font-size:0.75rem;"><?= e($rep['accession_number']) ?></span>
                    </td>
                    <td>
                      <div class="fw-semibold"><?= e($rep['patient_name']) ?></div>
                      <span class="text-muted" style="font-size:0.75rem;"><?= e($rep['patient_code']) ?></span>
                    </td>
                    <td>
                      <?php if ($rep['report_status'] === 'Approved'): ?>
                        <span class="badge bg-success-subtle text-success-emphasis">Approved</span>
                      <?php elseif ($rep['report_status'] === 'Pending Approval'): ?>
                        <span class="badge bg-warning text-dark">Pending</span>
                      <?php else: ?>
                        <span class="badge bg-secondary"><?= e($rep['report_status']) ?></span>
                      <?php endif; ?>
                    </td>
                    <td class="text-end text-nowrap pe-3">
                      <div class="d-inline-flex align-items-center justify-content-end gap-1">
                        <a href="<?= ADMIN_URL ?>/reports/view.php?id=<?= $rep['id'] ?>" class="btn btn-sm btn-outline-primary btn-action-icon" title="View Full Report"><i class="bi bi-eye"></i></a>
                        <a href="<?= ADMIN_URL ?>/reports/pdf.php?id=<?= $rep['id'] ?>" target="_blank" class="btn btn-sm btn-outline-danger btn-action-icon" title="Download PDF"><i class="bi bi-file-earmark-pdf"></i></a>
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

  <?php if (Auth::can('billing.manage') || Auth::can('accounting.manage')): ?>
  <!-- Recent Payments / Cashflow -->
  <div class="col-lg-6">
    <div class="admin-card">
      <div class="admin-card-header">
        <h3 class="admin-card-title">
          <i class="bi bi-cash text-success"></i> Recent Payments & Inflows
        </h3>
        <a href="<?= ADMIN_URL ?>/billing/index.php" class="btn btn-sm btn-link text-decoration-none">View Billing</a>
      </div>
      <div class="admin-card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0 small">
            <thead class="table-light">
              <tr>
                <th>Receipt / Invoice</th>
                <th>Patient</th>
                <th>Method</th>
                <th class="text-end">Amount</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($recentPayments)): ?>
                <tr><td colspan="4" class="text-center text-muted py-3">No payments recorded</td></tr>
              <?php else: ?>
                <?php foreach ($recentPayments as $pay): ?>
                  <tr>
                    <td>
                      <span class="fw-bold text-dark"><?= e($pay['receipt_number']) ?></span><br>
                      <span class="text-muted" style="font-size:0.75rem;"><?= e($pay['invoice_number']) ?></span>
                    </td>
                    <td>
                      <div class="fw-semibold"><?= e($pay['patient_name']) ?></div>
                      <span class="text-muted" style="font-size:0.75rem;"><?= format_date($pay['payment_date']) ?></span>
                    </td>
                    <td>
                      <span class="badge bg-light text-dark border"><?= e($pay['payment_method']) ?></span>
                    </td>
                    <td class="text-end fw-bold text-success">
                      <?= format_currency($pay['amount']) ?>
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
  <?php elseif (Auth::can('orders.view')): ?>
  <!-- Recent Orders & Specimen Accessions (For Clinical Bench & Testing) -->
  <div class="col-lg-6">
    <div class="admin-card">
      <div class="admin-card-header">
        <h3 class="admin-card-title">
          <i class="bi bi-collection-fill text-info"></i> Recent Accession Orders
        </h3>
        <a href="<?= ADMIN_URL ?>/orders/index.php" class="btn btn-sm btn-link text-decoration-none">View Orders</a>
      </div>
      <div class="admin-card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0 small">
            <thead class="table-light">
              <tr>
                <th>Accession No</th>
                <th>Patient</th>
                <th>Doctor</th>
                <th>Status</th>
                <th class="text-end text-nowrap pe-3">Action</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($recentOrders)): ?>
                <tr><td colspan="5" class="text-center text-muted py-3">No active accessions recorded</td></tr>
              <?php else: ?>
                <?php foreach ($recentOrders as $ro): ?>
                  <tr>
                    <td>
                      <span class="fw-bold text-primary font-monospace"><?= e($ro['accession_number']) ?></span><br>
                      <span class="text-muted" style="font-size:0.75rem;"><?= format_date($ro['order_date']) ?></span>
                    </td>
                    <td>
                      <div class="fw-semibold text-dark"><?= e($ro['patient_name']) ?></div>
                      <span class="text-muted" style="font-size:0.75rem;"><?= e($ro['patient_code']) ?></span>
                    </td>
                    <td>
                      <span class="text-muted"><?= e($ro['doctor_name'] ?: 'Direct / Self') ?></span>
                    </td>
                    <td>
                      <span class="badge bg-primary-subtle text-primary-emphasis"><?= e($ro['status']) ?></span>
                    </td>
                    <td class="text-end text-nowrap pe-3">
                      <a href="<?= ADMIN_URL ?>/orders/view.php?id=<?= $ro['id'] ?>" class="btn btn-sm btn-outline-primary btn-action-icon" title="View Accession">
                        <i class="bi bi-eye"></i>
                      </a>
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
  <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const ctx = document.getElementById('orderTrendChart');
  if (ctx) {
    new Chart(ctx, {
      type: 'line',
      data: {
        labels: <?= json_encode($trendLabels) ?>,
        datasets: [{
          label: 'Test Orders / Accessions',
          data: <?= json_encode($trendData) ?>,
          borderColor: '#0284c7',
          backgroundColor: 'rgba(2, 132, 199, 0.1)',
          fill: true,
          tension: 0.35,
          borderWidth: 2,
          pointBackgroundColor: '#0284c7',
          pointRadius: 4
        }]
      },
      options: {
        responsive: true,
        plugins: {
          legend: { display: false }
        },
        scales: {
          y: {
            beginAtZero: true,
            ticks: { precision: 0 }
          }
        }
      }
    });
  }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

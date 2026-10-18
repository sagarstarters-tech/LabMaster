<?php
/**
 * LabMaster - Comprehensive Financial & Revenue Reports
 */

declare(strict_types=1);

$pageTitle = 'Financial Reports & Analytics';
$activeNav = 'financial_reports';

require_once dirname(__DIR__) . '/includes/auth_guard.php';
Auth::requirePermission('accounting.manage');

$startDate = !empty($_GET['start_date']) ? trim($_GET['start_date']) : date('Y-m-01');
$endDate = !empty($_GET['end_date']) ? trim($_GET['end_date']) : date('Y-m-d');

// 1. Total Collections in Date Range
$collections = (float)(Database::fetch(
    "SELECT COALESCE(SUM(amount), 0) as s FROM `payments` WHERE `payment_date` BETWEEN ? AND ?",
    [$startDate, $endDate]
)['s'] ?? 0);

// 2. Total Invoiced Value in Date Range
$invoiced = (float)(Database::fetch(
    "SELECT COALESCE(SUM(grand_total), 0) as s FROM `invoices` WHERE `invoice_date` BETWEEN ? AND ? AND `payment_status` != 'Cancelled'",
    [$startDate, $endDate]
)['s'] ?? 0);

// 3. Total Expenses in Date Range
$expenses = (float)(Database::fetch(
    "SELECT COALESCE(SUM(amount), 0) as s FROM `expenses` WHERE `expense_date` BETWEEN ? AND ?",
    [$startDate, $endDate]
)['s'] ?? 0);

// 4. Net Operating Margin
$netSurplus = $collections - $expenses;

// 5. Payment Methods Summary
$methodsSummary = Database::fetchAll(
    "SELECT `payment_method`, COUNT(*) as txn_count, COALESCE(SUM(amount), 0) as total_amount
     FROM `payments`
     WHERE `payment_date` BETWEEN ? AND ?
     GROUP BY `payment_method`
     ORDER BY total_amount DESC",
    [$startDate, $endDate]
);

// 6. Test-Wise Revenue Breakdown
$testRevenue = Database::fetchAll(
    "SELECT t.test_name, t.test_code, c.name as category_name,
            COUNT(oi.id) as order_count,
            COALESCE(SUM(oi.price), 0) as total_revenue
     FROM `test_order_items` oi
     JOIN `tests` t ON oi.test_id = t.id
     JOIN `test_categories` c ON t.category_id = c.id
     JOIN `test_orders` o ON oi.order_id = o.id
     WHERE o.order_date BETWEEN ? AND ?
     GROUP BY t.id
     ORDER BY total_revenue DESC",
    [$startDate, $endDate]
);

// 7. Referring Doctor Volume & Revenue
$doctorVolume = Database::fetchAll(
    "SELECT d.name as doctor_name, d.specialization,
            COUNT(o.id) as total_referrals,
            COALESCE(SUM(o.net_amount), 0) as total_referred_revenue
     FROM `test_orders` o
     JOIN `doctors` d ON o.referring_doctor_id = d.id
     WHERE o.order_date BETWEEN ? AND ?
     GROUP BY d.id
     ORDER BY total_referred_revenue DESC",
    [$startDate, $endDate]
);

require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
  <div>
    <h2 class="h4 fw-bold mb-1">Financial Intelligence & Revenue Analytics</h2>
    <p class="text-muted small mb-0">Period: <?= format_date($startDate) ?> to <?= format_date($endDate) ?></p>
  </div>
</div>

<!-- Date Range Filter -->
<div class="admin-card mb-4">
  <div class="admin-card-body p-3">
    <form method="GET" action="" class="row g-2 align-items-center">
      <div class="col-md-4">
        <label class="form-label small fw-semibold mb-1">Start Date</label>
        <input type="date" name="start_date" class="form-control" value="<?= e($startDate) ?>">
      </div>
      <div class="col-md-4">
        <label class="form-label small fw-semibold mb-1">End Date</label>
        <input type="date" name="end_date" class="form-control" value="<?= e($endDate) ?>">
      </div>
      <div class="col-md-4 align-self-end">
        <button type="submit" class="btn btn-primary"><i class="bi bi-funnel"></i> Apply Date Filter</button>
      </div>
    </form>
  </div>
</div>

<!-- Performance Summary Cards -->
<div class="row g-3 mb-4">
  <div class="col-md-3 col-sm-6">
    <div class="p-3 bg-white rounded border shadow-sm">
      <div class="text-muted small text-uppercase fw-semibold">Patient Invoiced Value</div>
      <div class="h4 fw-bold mb-0 text-dark"><?= format_currency($invoiced) ?></div>
      <div class="small text-muted">Test Orders Generated</div>
    </div>
  </div>
  <div class="col-md-3 col-sm-6">
    <div class="p-3 bg-white rounded border shadow-sm">
      <div class="text-muted small text-uppercase fw-semibold">Actual Cash Collected</div>
      <div class="h4 fw-bold mb-0 text-success"><?= format_currency($collections) ?></div>
      <div class="small text-muted">All Payment Modes</div>
    </div>
  </div>
  <div class="col-md-3 col-sm-6">
    <div class="p-3 bg-white rounded border shadow-sm">
      <div class="text-muted small text-uppercase fw-semibold">Operational Expenses</div>
      <div class="h4 fw-bold mb-0 text-danger"><?= format_currency($expenses) ?></div>
      <div class="small text-muted">Disbursements in Period</div>
    </div>
  </div>
  <div class="col-md-3 col-sm-6">
    <div class="p-3 bg-white rounded border shadow-sm">
      <div class="text-muted small text-uppercase fw-semibold">Net Operating Surplus</div>
      <div class="h4 fw-bold mb-0 <?= $netSurplus >= 0 ? 'text-primary' : 'text-danger' ?>">
        <?= format_currency($netSurplus) ?>
      </div>
      <div class="small text-muted">Collections Minus Expenses</div>
    </div>
  </div>
</div>

<div class="row g-4 mb-4">
  <!-- Payment Channel Breakdown -->
  <div class="col-lg-5">
    <div class="admin-card h-100 mb-0">
      <div class="admin-card-header bg-white">
        <h3 class="admin-card-title text-success">
          <i class="bi bi-wallet2"></i> Collections by Payment Method
        </h3>
      </div>
      <div class="admin-card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0 small">
            <thead class="table-light">
              <tr>
                <th>Payment Mode</th>
                <th class="text-center">Receipts</th>
                <th class="text-end">Total Amount</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($methodsSummary as $ms): ?>
                <tr>
                  <td><span class="badge bg-light text-dark border"><?= e($ms['payment_method']) ?></span></td>
                  <td class="text-center"><?= $ms['txn_count'] ?></td>
                  <td class="text-end fw-bold text-success"><?= format_currency($ms['total_amount']) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- Referring Doctor Volume -->
  <div class="col-lg-7">
    <div class="admin-card h-100 mb-0">
      <div class="admin-card-header bg-white">
        <h3 class="admin-card-title text-primary">
          <i class="bi bi-hospital"></i> Referring Doctor Volume & Share
        </h3>
      </div>
      <div class="admin-card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0 small">
            <thead class="table-light">
              <tr>
                <th>Doctor</th>
                <th>Specialization</th>
                <th class="text-center">Referrals</th>
                <th class="text-end">Referred Revenue</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($doctorVolume as $dv): ?>
                <tr>
                  <td class="fw-bold"><?= e($dv['doctor_name']) ?></td>
                  <td class="text-muted"><?= e($dv['specialization'] ?: 'Physician') ?></td>
                  <td class="text-center"><span class="badge bg-light text-dark border"><?= $dv['total_referrals'] ?></span></td>
                  <td class="text-end fw-bold text-dark"><?= format_currency($dv['total_referred_revenue']) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Test-Wise Revenue Breakdown Table -->
<div class="admin-card">
  <div class="admin-card-header bg-white">
    <h3 class="admin-card-title text-primary">
      <i class="bi bi-pie-chart-fill"></i> Pathology Test-Wise Revenue & Order Volume
    </h3>
  </div>
  <div class="admin-card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0 small">
        <thead class="table-light">
          <tr>
            <th>Test Code</th>
            <th>Investigation Name</th>
            <th>Discipline / Category</th>
            <th class="text-center">Tests Conducted</th>
            <th class="text-end">Gross Invoiced Revenue</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($testRevenue)): ?>
            <tr><td colspan="5" class="text-center text-muted py-4">No test order data in selected range.</td></tr>
          <?php else: ?>
            <?php foreach ($testRevenue as $tr): ?>
              <tr>
                <td><span class="badge bg-light text-dark border font-monospace"><?= e($tr['test_code']) ?></span></td>
                <td class="fw-bold text-dark"><?= e($tr['test_name']) ?></td>
                <td><span class="badge bg-info-subtle text-info-emphasis"><?= e($tr['category_name']) ?></span></td>
                <td class="text-center"><span class="badge bg-light text-dark border"><?= $tr['order_count'] ?> Orders</span></td>
                <td class="text-end fw-bold text-primary"><?= format_currency($tr['total_revenue']) ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>

<?php
/**
 * LabMaster - Doctor Management & Financial Reconciliation Reports
 * Strict Cash vs. Accrual distinction, doctor-wise summaries, and monthly reconciliation matrix
 */

declare(strict_types=1);

$pageTitle = 'Doctor Reconciliation & Revenue Reports';
$activeNav = 'doctors';

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';

Auth::requirePermission('reconciliation.view');

// Date Range Filters (Default: Current Year)
$currentYear = date('Y');
$dateFrom = trim($_GET['date_from'] ?? ($currentYear . '-01-01'));
$dateTo = trim($_GET['date_to'] ?? date('Y-m-d'));
$selectedDoctorId = (int)($_GET['doctor_id'] ?? 0);

$whereDoc = $selectedDoctorId > 0 ? "AND d.id = {$selectedDoctorId}" : "";

// CSV Export Handler
if (isset($_GET['export']) && $_GET['export'] === 'csv' && Auth::can('reconciliation.export')) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=doctor_reconciliation_report_' . date('Ymd_His') . '.csv');
    $out = fopen('php://output', 'w');
    
    // Header
    fputcsv($out, ['=== LABMASTER DOCTOR RECONCILIATION & REVENUE REPORT ===']);
    fputcsv($out, ['Period From:', $dateFrom, 'Period To:', $dateTo]);
    fputcsv($out, []);
    fputcsv($out, [
        'Doctor Code', 'Doctor Name', 'Hospital/Clinic', 'Associated Patients', 
        'Associated Orders', 'Billed Revenue (Accrual)', 'Actual Collected Cash', 
        'Uncollected Receivables', 'Refunds & Cancellations', 'Approved Service Fees', 
        'Disbursed Service Payments', 'Unpaid Approved Liability', 'Net Lab Operating Surplus'
    ]);

    $exportSql = "
        SELECT d.id, d.doctor_code, d.name as doctor_name, d.clinic_hospital,
               (SELECT COUNT(DISTINCT p.id) FROM `patients` p WHERE p.referring_doctor_id = d.id AND DATE(p.created_at) BETWEEN ? AND ?) as patient_count,
               (SELECT COUNT(o.id) FROM `test_orders` o WHERE o.referring_doctor_id = d.id AND o.order_date BETWEEN ? AND ?) as order_count,
               (SELECT COALESCE(SUM(o.net_amount), 0) FROM `test_orders` o WHERE o.referring_doctor_id = d.id AND o.order_date BETWEEN ? AND ?) as billed_revenue,
               (SELECT COALESCE(SUM(py.amount), 0) 
                FROM `payments` py 
                JOIN `invoices` inv ON py.invoice_id = inv.id 
                JOIN `test_orders` o ON inv.order_id = o.id 
                WHERE o.referring_doctor_id = d.id AND py.payment_date BETWEEN ? AND ?) as collected_cash,
               (SELECT COALESCE(SUM(inv.grand_total), 0) 
                FROM `invoices` inv 
                JOIN `test_orders` o ON inv.order_id = o.id 
                WHERE o.referring_doctor_id = d.id AND o.order_date BETWEEN ? AND ? AND inv.payment_status = 'Refunded') as refunds_cancelled,
               (SELECT COALESCE(SUM(l.amount), 0) FROM `financial_reconciliation_ledger` l WHERE l.doctor_id = d.id AND l.payment_status IN ('Approved', 'Paid') AND l.transaction_date BETWEEN ? AND ?) as total_approved_service_fees,
               (SELECT COALESCE(SUM(l.amount), 0) FROM `financial_reconciliation_ledger` l WHERE l.doctor_id = d.id AND l.payment_status = 'Paid' AND l.transaction_date BETWEEN ? AND ?) as disbursed_service_fees,
               (SELECT COALESCE(SUM(l.amount), 0) FROM `financial_reconciliation_ledger` l WHERE l.doctor_id = d.id AND l.payment_status = 'Approved' AND l.transaction_date BETWEEN ? AND ?) as unpaid_approved_fees
        FROM `doctors` d
        WHERE 1=1 {$whereDoc}
        ORDER BY billed_revenue DESC, d.name ASC
    ";

    $params = [
        $dateFrom, $dateTo,
        $dateFrom, $dateTo,
        $dateFrom, $dateTo,
        $dateFrom, $dateTo,
        $dateFrom, $dateTo,
        $dateFrom, $dateTo,
        $dateFrom, $dateTo,
        $dateFrom, $dateTo
    ];

    $rows = Database::fetchAll($exportSql, $params);
    foreach ($rows as $r) {
        $receivable = max(0, (float)$r['billed_revenue'] - (float)$r['collected_cash']);
        $surplus = (float)$r['collected_cash'] - (float)$r['disbursed_service_fees'];
        fputcsv($out, [
            $r['doctor_code'],
            $r['doctor_name'],
            $r['clinic_hospital'] ?: 'Independent',
            $r['patient_count'],
            $r['order_count'],
            $r['billed_revenue'],
            $r['collected_cash'],
            $receivable,
            $r['refunds_cancelled'],
            $r['total_approved_service_fees'],
            $r['disbursed_service_fees'],
            $r['unpaid_approved_fees'],
            $surplus
        ]);
    }
    fclose($out);
    exit;
}

require_once dirname(__DIR__) . '/includes/header.php';

// 1. Doctor-wise Summary Query
$summarySql = "
    SELECT d.id, d.doctor_code, d.name as doctor_name, d.specialization, d.clinic_hospital,
           (SELECT COUNT(DISTINCT p.id) FROM `patients` p WHERE p.referring_doctor_id = d.id AND DATE(p.created_at) BETWEEN ? AND ?) as patient_count,
           (SELECT COUNT(o.id) FROM `test_orders` o WHERE o.referring_doctor_id = d.id AND o.order_date BETWEEN ? AND ?) as order_count,
           (SELECT COALESCE(SUM(o.net_amount), 0) FROM `test_orders` o WHERE o.referring_doctor_id = d.id AND o.order_date BETWEEN ? AND ?) as billed_revenue,
           (SELECT COALESCE(SUM(py.amount), 0) 
            FROM `payments` py 
            JOIN `invoices` inv ON py.invoice_id = inv.id 
            JOIN `test_orders` o ON inv.order_id = o.id 
            WHERE o.referring_doctor_id = d.id AND py.payment_date BETWEEN ? AND ?) as collected_cash,
           (SELECT COALESCE(SUM(inv.grand_total), 0) 
            FROM `invoices` inv 
            JOIN `test_orders` o ON inv.order_id = o.id 
            WHERE o.referring_doctor_id = d.id AND o.order_date BETWEEN ? AND ? AND inv.payment_status = 'Refunded') as refunds_cancelled,
           (SELECT COALESCE(SUM(l.amount), 0) FROM `financial_reconciliation_ledger` l WHERE l.doctor_id = d.id AND l.payment_status IN ('Approved', 'Paid') AND l.transaction_date BETWEEN ? AND ?) as total_approved_service_fees,
           (SELECT COALESCE(SUM(l.amount), 0) FROM `financial_reconciliation_ledger` l WHERE l.doctor_id = d.id AND l.payment_status = 'Paid' AND l.transaction_date BETWEEN ? AND ?) as disbursed_service_fees,
           (SELECT COALESCE(SUM(l.amount), 0) FROM `financial_reconciliation_ledger` l WHERE l.doctor_id = d.id AND l.payment_status = 'Approved' AND l.transaction_date BETWEEN ? AND ?) as unpaid_approved_fees
    FROM `doctors` d
    WHERE 1=1 {$whereDoc}
    ORDER BY billed_revenue DESC, d.name ASC
";
$params = [
    $dateFrom, $dateTo,
    $dateFrom, $dateTo,
    $dateFrom, $dateTo,
    $dateFrom, $dateTo,
    $dateFrom, $dateTo,
    $dateFrom, $dateTo,
    $dateFrom, $dateTo,
    $dateFrom, $dateTo
];
$doctorSummaries = Database::fetchAll($summarySql, $params);

// Aggregate Totals across doctors
$grandTotalPatients = 0;
$grandTotalOrders = 0;
$grandBilledRevenue = 0.0;
$grandCollectedCash = 0.0;
$grandRefunds = 0.0;
$grandApprovedFees = 0.0;
$grandDisbursedFees = 0.0;
$grandUnpaidApproved = 0.0;

foreach ($doctorSummaries as $row) {
    $grandTotalPatients += (int)$row['patient_count'];
    $grandTotalOrders += (int)$row['order_count'];
    $grandBilledRevenue += (float)$row['billed_revenue'];
    $grandCollectedCash += (float)$row['collected_cash'];
    $grandRefunds += (float)$row['refunds_cancelled'];
    $grandApprovedFees += (float)$row['total_approved_service_fees'];
    $grandDisbursedFees += (float)$row['disbursed_service_fees'];
    $grandUnpaidApproved += (float)$row['unpaid_approved_fees'];
}
$grandReceivables = max(0, $grandBilledRevenue - $grandCollectedCash);
$grandLabSurplus = $grandCollectedCash - $grandDisbursedFees;

// 2. Monthly Reconciliation Matrix (Past 12 Months)
$monthlyMatrix = [];
for ($i = 0; $i < 12; $i++) {
    $mTime = strtotime("-{$i} month");
    $ym = date('Y-m', $mTime);
    $mLabel = date('M Y', $mTime);

    $mBilled = (float)(Database::fetch(
        "SELECT COALESCE(SUM(net_amount), 0) as s FROM `test_orders` WHERE DATE_FORMAT(order_date, '%Y-%m') = ? AND referring_doctor_id IS NOT NULL",
        [$ym]
    )['s'] ?? 0);

    $mCollected = (float)(Database::fetch(
        "SELECT COALESCE(SUM(p.amount), 0) as s 
         FROM `payments` p 
         JOIN `invoices` i ON p.invoice_id = i.id 
         JOIN `test_orders` o ON i.order_id = o.id 
         WHERE DATE_FORMAT(p.payment_date, '%Y-%m') = ? AND o.referring_doctor_id IS NOT NULL",
        [$ym]
    )['s'] ?? 0);

    $mDisbursed = (float)(Database::fetch(
        "SELECT COALESCE(SUM(amount), 0) as s FROM `financial_reconciliation_ledger` WHERE DATE_FORMAT(transaction_date, '%Y-%m') = ? AND payment_status = 'Paid'",
        [$ym]
    )['s'] ?? 0);

    $mApproved = (float)(Database::fetch(
        "SELECT COALESCE(SUM(amount), 0) as s FROM `financial_reconciliation_ledger` WHERE DATE_FORMAT(transaction_date, '%Y-%m') = ? AND payment_status = 'Approved'",
        [$ym]
    )['s'] ?? 0);

    $monthlyMatrix[] = [
        'year_month' => $ym,
        'month_label' => $mLabel,
        'monthly_billed' => $mBilled,
        'monthly_collected' => $mCollected,
        'monthly_service_paid' => $mDisbursed,
        'monthly_service_approved' => $mApproved
    ];
}

// 3. Agreement-wise Transaction Summary
$agreementSummaries = Database::fetchAll(
    "SELECT a.agreement_type, a.payment_basis,
            COUNT(DISTINCT a.id) as total_agreements,
            COUNT(DISTINCT l.id) as total_vouchers,
            COALESCE(SUM(CASE WHEN l.payment_status = 'Paid' THEN l.amount ELSE 0 END), 0) as paid_amount,
            COALESCE(SUM(CASE WHEN l.payment_status = 'Approved' THEN l.amount ELSE 0 END), 0) as pending_amount
     FROM `doctor_service_agreements` a
     LEFT JOIN `financial_reconciliation_ledger` l ON l.agreement_id = a.id
     GROUP BY a.agreement_type, a.payment_basis
     ORDER BY paid_amount DESC"
);

$allDoctors = Database::fetchAll("SELECT id, name, doctor_code FROM `doctors` ORDER BY name ASC");
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
  <div>
    <h2 class="h4 fw-bold mb-1">
      <i class="bi bi-graph-up-arrow text-primary me-2"></i>Doctor Financial Reconciliation Reports
    </h2>
    <p class="text-muted small mb-0">Multi-dimensional reconciliation: Cash vs Accrual revenue, doctor volumes, service payouts, and monthly matrix</p>
  </div>
  <div class="d-flex flex-wrap gap-2">
    <?php if (Auth::can('reconciliation.export')): ?>
      <a href="?<?= http_build_query(array_merge($_GET, ['export' => 'csv'])) ?>" class="btn btn-outline-secondary d-inline-flex align-items-center gap-2">
        <i class="bi bi-file-earmark-excel"></i> Export Report to CSV
      </a>
    <?php endif; ?>
    <a href="<?= ADMIN_URL ?>/doctors/reconciliation.php" class="btn btn-outline-success d-inline-flex align-items-center gap-2">
      <i class="bi bi-wallet2"></i> Reconciliation Ledger
    </a>
    <a href="<?= ADMIN_URL ?>/doctors/index.php" class="btn btn-light border d-inline-flex align-items-center gap-2">
      <i class="bi bi-hospital"></i> Doctor Master
    </a>
  </div>
</div>

<!-- Strict Accounting Distinction Advisory -->
<div class="alert alert-light border border-secondary border-opacity-25 shadow-sm p-3 mb-4 rounded-3 d-flex align-items-start gap-3">
  <i class="bi bi-info-circle text-primary fs-4 mt-1"></i>
  <div class="small text-secondary">
    <strong class="text-dark">Strict Cash vs. Accrual Accounting Segregation (Section 5):</strong>
    Patient billing revenue is computed on an accrual basis when invoices are generated. Realized cash is tracked exclusively from completed payment transactions.
    <strong>Uncollected invoices are never classified as realized cash.</strong> Service fees owed to doctors are tracked independently based on approved service contracts and are never deducted automatically from patient revenue.
  </div>
</div>

<!-- Date Range & Doctor Filter Bar -->
<div class="admin-card mb-4">
  <div class="admin-card-body p-3">
    <form method="GET" action="" class="row g-2 align-items-center">
      <div class="col-md-3">
        <label class="form-label small fw-semibold mb-1">From Date</label>
        <input type="date" name="date_from" class="form-control" value="<?= e($dateFrom) ?>" required>
      </div>

      <div class="col-md-3">
        <label class="form-label small fw-semibold mb-1">To Date</label>
        <input type="date" name="date_to" class="form-control" value="<?= e($dateTo) ?>" required>
      </div>

      <div class="col-md-4">
        <label class="form-label small fw-semibold mb-1">Doctor Filter</label>
        <select name="doctor_id" class="form-select">
          <option value="">All Doctors & Institutions</option>
          <?php foreach ($allDoctors as $doc): ?>
            <option value="<?= $doc['id'] ?>" <?= $selectedDoctorId === (int)$doc['id'] ? 'selected' : '' ?>>
              <?= e($doc['name']) ?> (<?= e($doc['doctor_code']) ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-md-2 d-flex align-items-end">
        <button type="submit" class="btn btn-primary w-100">
          <i class="bi bi-funnel me-1"></i> Generate Report
        </button>
      </div>
    </form>
  </div>
</div>

<!-- Top Metrics Cards -->
<div class="row g-3 mb-4">
  <div class="col-sm-6 col-xl-3">
    <div class="admin-card p-3 border-start border-4 border-warning h-100">
      <span class="text-muted small fw-semibold text-uppercase">Billed Revenue (Accrual)</span>
      <h3 class="fw-bold mb-0 mt-1 text-dark"><?= format_currency($grandBilledRevenue) ?></h3>
      <small class="text-muted"><?= number_format($grandTotalOrders) ?> Orders (<?= number_format($grandTotalPatients) ?> Patients)</small>
    </div>
  </div>

  <div class="col-sm-6 col-xl-3">
    <div class="admin-card p-3 border-start border-4 border-success h-100">
      <span class="text-muted small fw-semibold text-uppercase">Realized Collected Cash</span>
      <h3 class="fw-bold mb-0 mt-1 text-success"><?= format_currency($grandCollectedCash) ?></h3>
      <small class="text-success"><i class="bi bi-check-circle me-1"></i>Actual bank/cash deposits</small>
    </div>
  </div>

  <div class="col-sm-6 col-xl-3">
    <div class="admin-card p-3 border-start border-4 border-teal h-100" style="border-left-color: #0d9488 !important;">
      <span class="text-muted small fw-semibold text-uppercase">Disbursed Service Fees</span>
      <h3 class="fw-bold mb-0 mt-1" style="color: #0d9488;"><?= format_currency($grandDisbursedFees) ?></h3>
      <small class="text-muted">Paid per approved agreements</small>
    </div>
  </div>

  <div class="col-sm-6 col-xl-3">
    <div class="admin-card p-3 border-start border-4 border-primary h-100">
      <span class="text-muted small fw-semibold text-uppercase">Unpaid Approved Liability</span>
      <h3 class="fw-bold mb-0 mt-1 text-primary"><?= format_currency($grandUnpaidApproved) ?></h3>
      <small class="text-muted">Approved obligations due</small>
    </div>
  </div>
</div>

<!-- 1. Doctor-wise Reconciliation & Revenue Summary -->
<div class="admin-card mb-4">
  <div class="admin-card-header bg-light py-3 px-4 d-flex justify-content-between align-items-center">
    <h5 class="fw-bold mb-0 text-dark">
      <i class="bi bi-people text-primary me-2"></i>Doctor-wise Financial Reconciliation Breakdown
    </h5>
    <span class="small text-muted">Reporting Period: <?= format_date($dateFrom) ?> to <?= format_date($dateTo) ?></span>
  </div>
  <div class="admin-card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>Doctor ID & Name</th>
            <th>Specialization & Hospital</th>
            <th class="text-center">Volume (Pat/Ord)</th>
            <th class="text-end">Billed Revenue</th>
            <th class="text-end">Collected Cash</th>
            <th class="text-end">Uncollected Balance</th>
            <th class="text-end">Approved Service Fees</th>
            <th class="text-end">Disbursed Fees</th>
            <th class="text-end">Unpaid Liability</th>
            <th class="text-end">Net Lab Margin</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($doctorSummaries)): ?>
            <tr>
              <td colspan="10" class="text-center py-5 text-muted">
                No doctor activity records found for this period.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($doctorSummaries as $d): 
              $receivable = max(0, (float)$d['billed_revenue'] - (float)$d['collected_cash']);
              $netMargin = (float)$d['collected_cash'] - (float)$d['disbursed_service_fees'];
            ?>
              <tr>
                <td>
                  <a href="<?= ADMIN_URL ?>/doctors/view.php?id=<?= $d['id'] ?>" class="fw-bold text-dark text-decoration-none">
                    <?= e($d['doctor_name']) ?>
                  </a>
                  <div class="text-muted small font-monospace"><?= e($d['doctor_code']) ?></div>
                </td>
                <td>
                  <div class="small fw-semibold text-dark"><?= e($d['specialization'] ?: 'General Practice') ?></div>
                  <div class="text-secondary small"><?= e($d['clinic_hospital'] ?: 'Independent') ?></div>
                </td>
                <td class="text-center">
                  <span class="badge bg-light text-dark border"><?= $d['patient_count'] ?> pts</span>
                  <span class="badge bg-primary-subtle text-primary-emphasis"><?= $d['order_count'] ?> ord</span>
                </td>
                <td class="text-end fw-semibold text-dark"><?= format_currency($d['billed_revenue']) ?></td>
                <td class="text-end text-success fw-bold"><?= format_currency($d['collected_cash']) ?></td>
                <td class="text-end <?= $receivable > 0 ? 'text-danger' : 'text-muted' ?>"><?= format_currency($receivable) ?></td>
                <td class="text-end text-primary fw-semibold"><?= format_currency($d['total_approved_service_fees']) ?></td>
                <td class="text-end" style="color:#0d9488; font-weight: 600;"><?= format_currency($d['disbursed_service_fees']) ?></td>
                <td class="text-end <?= (float)$d['unpaid_approved_fees'] > 0 ? 'text-warning-emphasis fw-bold' : 'text-muted' ?>">
                  <?= format_currency($d['unpaid_approved_fees']) ?>
                </td>
                <td class="text-end fw-bold <?= $netMargin >= 0 ? 'text-success' : 'text-danger' ?>">
                  <?= format_currency($netMargin) ?>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
        <tfoot class="table-light fw-bold">
          <tr>
            <td colspan="2">Grand Totals (<?= count($doctorSummaries) ?> Doctors)</td>
            <td class="text-center"><?= $grandTotalPatients ?> pts / <?= $grandTotalOrders ?> ord</td>
            <td class="text-end"><?= format_currency($grandBilledRevenue) ?></td>
            <td class="text-end text-success"><?= format_currency($grandCollectedCash) ?></td>
            <td class="text-end text-danger"><?= format_currency($grandReceivables) ?></td>
            <td class="text-end text-primary"><?= format_currency($grandApprovedFees) ?></td>
            <td class="text-end" style="color:#0d9488;"><?= format_currency($grandDisbursedFees) ?></td>
            <td class="text-end text-warning-emphasis"><?= format_currency($grandUnpaidApproved) ?></td>
            <td class="text-end <?= $grandLabSurplus >= 0 ? 'text-success' : 'text-danger' ?>">
              <?= format_currency($grandLabSurplus) ?>
            </td>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>
</div>

<div class="row g-4">
  <!-- 2. Monthly Reconciliation Matrix -->
  <div class="col-lg-7">
    <div class="admin-card h-100">
      <div class="admin-card-header bg-light py-3 px-4">
        <h5 class="fw-bold mb-0 text-dark">
          <i class="bi bi-calendar3 text-primary me-2"></i>Monthly Reconciliation Statement (Past 12 Months)
        </h5>
      </div>
      <div class="admin-card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th>Month</th>
                <th class="text-end">Billed Revenue</th>
                <th class="text-end">Realized Cash</th>
                <th class="text-end">Service Honorariums</th>
                <th class="text-end">Pending Liability</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($monthlyMatrix as $m): ?>
                <tr>
                  <td class="fw-semibold text-dark"><?= e($m['month_label']) ?></td>
                  <td class="text-end"><?= format_currency($m['monthly_billed']) ?></td>
                  <td class="text-end text-success fw-bold"><?= format_currency($m['monthly_collected']) ?></td>
                  <td class="text-end" style="color:#0d9488;"><?= format_currency($m['monthly_service_paid']) ?></td>
                  <td class="text-end <?= (float)$m['monthly_service_approved'] > 0 ? 'text-primary fw-semibold' : 'text-muted' ?>">
                    <?= format_currency($m['monthly_service_approved']) ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- 3. Agreement-wise Transaction Summary -->
  <div class="col-lg-5">
    <div class="admin-card h-100">
      <div class="admin-card-header bg-light py-3 px-4">
        <h5 class="fw-bold mb-0 text-dark">
          <i class="bi bi-file-earmark-check text-primary me-2"></i>Agreement-wise Disbursements
        </h5>
      </div>
      <div class="admin-card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th>Agreement Type</th>
                <th>Agreements</th>
                <th class="text-end">Paid Amount</th>
                <th class="text-end">Pending Liability</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($agreementSummaries)): ?>
                <tr>
                  <td colspan="4" class="text-center py-4 text-muted">No agreement summaries recorded.</td>
                </tr>
              <?php else: ?>
                <?php foreach ($agreementSummaries as $ag): ?>
                  <tr>
                    <td>
                      <div class="fw-semibold text-dark small"><?= e($ag['agreement_type']) ?></div>
                      <div class="text-muted small"><?= e($ag['payment_basis']) ?></div>
                    </td>
                    <td><span class="badge bg-light text-dark border"><?= $ag['total_agreements'] ?> Agreements</span></td>
                    <td class="text-end fw-bold text-success"><?= format_currency($ag['paid_amount']) ?></td>
                    <td class="text-end <?= (float)$ag['pending_amount'] > 0 ? 'text-primary fw-semibold' : 'text-muted' ?>">
                      <?= format_currency($ag['pending_amount']) ?>
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

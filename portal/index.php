<?php
/**
 * LabMaster - Patient Health Portal Dashboard
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';

// Patient Auth Check
if (empty($_SESSION['patient']['id'])) {
    set_flash('warning', 'Please sign in to access your patient portal.');
    header('Location: ' . PORTAL_URL . '/login.php');
    exit;
}

$patientSession = $_SESSION['patient'];
$patientId = (int)$patientSession['id'];

$patient = Database::fetch("SELECT * FROM `patients` WHERE `id` = ?", [$patientId]);
if (!$patient) {
    unset($_SESSION['patient']);
    header('Location: ' . PORTAL_URL . '/login.php');
    exit;
}

// Fetch Patient's Approved Reports
$reports = Database::fetchAll(
    "SELECT r.*, o.accession_number, o.order_date, u.full_name as pathologist_name 
     FROM `reports` r
     JOIN `test_orders` o ON r.order_id = o.id
     LEFT JOIN `users` u ON r.approved_by = u.id
     WHERE r.patient_id = ? AND r.report_status = 'Approved'
     ORDER BY r.id DESC",
    [$patientId]
);

// Fetch Patient's Orders (all statuses)
$orders = Database::fetchAll(
    "SELECT o.*, (SELECT COUNT(*) FROM `test_order_items` oi WHERE oi.order_id = o.id) as item_count 
     FROM `test_orders` o 
     WHERE o.patient_id = ? 
     ORDER BY o.id DESC",
    [$patientId]
);

// Fetch Patient's Invoices
$invoices = Database::fetchAll("SELECT * FROM `invoices` WHERE `patient_id` = ? ORDER BY `id` DESC", [$patientId]);

$labName = Settings::get('lab_name', 'LabMaster Diagnostic & Research Center');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Health Dashboard | <?= e($labName) ?></title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/style.css">
</head>
<body class="bg-light">

  <!-- Portal Topbar -->
  <nav class="navbar navbar-expand-lg bg-white border-bottom shadow-sm">
    <div class="container">
      <a class="navbar-brand" href="<?= PORTAL_URL ?>/index.php">
        <i class="bi bi-heart-pulse-fill text-primary"></i>
        <span>Lab<span class="text-primary">Master</span> <small class="text-muted fs-6">Patient Portal</small></span>
      </a>

      <div class="d-flex align-items-center gap-3">
        <div class="d-none d-sm-block text-end">
          <div class="fw-bold small text-dark"><?= e($patient['full_name']) ?></div>
          <div class="text-muted font-monospace" style="font-size:0.75rem;"><?= e($patient['patient_id']) ?></div>
        </div>
        <a href="<?= PORTAL_URL ?>/logout.php" class="btn btn-outline-danger btn-sm">
          <i class="bi bi-box-arrow-right me-1"></i> Sign Out
        </a>
      </div>
    </div>
  </nav>

  <div class="container py-4">
    <?= render_flash() ?>

    <!-- Welcome & Demographics Banner -->
    <div class="card border-0 shadow-sm rounded-4 mb-4" style="background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%); color: #ffffff;">
      <div class="card-body p-4">
        <div class="row align-items-center">
          <div class="col-md-8">
            <span class="badge bg-white bg-opacity-25 text-white mb-2 font-monospace">Patient ID: <?= e($patient['patient_id']) ?></span>
            <h1 class="h3 fw-bold mb-1">Hello, <?= e($patient['full_name']) ?>!</h1>
            <p class="mb-0 text-white-50 small">
              <?= $patient['age'] ?> <?= e($patient['age_unit']) ?> • <?= e($patient['gender']) ?> • Registered Phone: <?= e($patient['phone']) ?>
              <?= !empty($patient['blood_group']) ? ' • Blood Group: ' . e($patient['blood_group']) : '' ?>
            </p>
          </div>
          <div class="col-md-4 text-md-end mt-3 mt-md-0">
            <a href="<?= BASE_URL ?>/booking.php" class="btn btn-light text-primary fw-semibold">
              <i class="bi bi-plus-circle me-1"></i> Book New Test
            </a>
          </div>
        </div>
      </div>
    </div>

    <!-- Quick Stats -->
    <div class="row g-3 mb-4">
      <div class="col-md-4">
        <div class="p-3 bg-white rounded-3 border shadow-sm">
          <div class="d-flex align-items-center gap-3">
            <div class="p-2 rounded bg-success bg-opacity-10 text-success fs-3">
              <i class="bi bi-file-earmark-check-fill"></i>
            </div>
            <div>
              <div class="h4 fw-bold mb-0 text-dark"><?= count($reports) ?></div>
              <div class="small text-muted">Ready Medical Reports</div>
            </div>
          </div>
        </div>
      </div>

      <div class="col-md-4">
        <div class="p-3 bg-white rounded-3 border shadow-sm">
          <div class="d-flex align-items-center gap-3">
            <div class="p-2 rounded bg-primary bg-opacity-10 text-primary fs-3">
              <i class="bi bi-collection-fill"></i>
            </div>
            <div>
              <div class="h4 fw-bold mb-0 text-dark"><?= count($orders) ?></div>
              <div class="small text-muted">Total Test Accessions</div>
            </div>
          </div>
        </div>
      </div>

      <div class="col-md-4">
        <div class="p-3 bg-white rounded-3 border shadow-sm">
          <div class="d-flex align-items-center gap-3">
            <div class="p-2 rounded bg-info bg-opacity-10 text-info fs-3">
              <i class="bi bi-receipt"></i>
            </div>
            <div>
              <div class="h4 fw-bold mb-0 text-dark"><?= count($invoices) ?></div>
              <div class="small text-muted">Billing Statements</div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Approved Reports Card -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
      <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
        <h2 class="h5 fw-bold mb-0 text-dark">
          <i class="bi bi-file-earmark-medical-fill text-success me-2"></i> My Verified Diagnostic Reports
        </h2>
        <span class="badge bg-success-subtle text-success-emphasis"><?= count($reports) ?> Certified</span>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light small">
              <tr>
                <th>Report Number</th>
                <th>Accession Ref</th>
                <th>Approved Date</th>
                <th>Pathologist Signatory</th>
                <th class="text-end">Download</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($reports)): ?>
                <tr>
                  <td colspan="5" class="text-center text-muted py-5">
                    <i class="bi bi-hourglass-split fs-1 text-secondary d-block mb-2"></i>
                    No approved reports released yet. If your test was recently collected, our laboratory team is processing your specimen.
                  </td>
                </tr>
              <?php else: ?>
                <?php foreach ($reports as $rep): ?>
                  <tr>
                    <td>
                      <span class="fw-bold font-monospace text-primary"><?= e($rep['report_number']) ?></span>
                      <span class="badge bg-success-subtle text-success ms-1"><i class="bi bi-check-circle"></i> Signed</span>
                    </td>
                    <td class="small font-monospace"><?= e($rep['accession_number']) ?></td>
                    <td class="small text-muted"><?= format_date($rep['approved_at'], true) ?></td>
                    <td class="small fw-semibold"><?= e($rep['pathologist_name'] ?: 'Clinical Pathologist') ?></td>
                    <td class="text-end">
                      <a href="<?= ADMIN_URL ?>/reports/pdf.php?token=<?= urlencode($rep['verification_token']) ?>" target="_blank" class="btn btn-sm btn-danger">
                        <i class="bi bi-file-earmark-pdf me-1"></i> Download PDF
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

    <!-- Test Orders Timeline -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
      <div class="card-header bg-white py-3 border-bottom">
        <h2 class="h5 fw-bold mb-0 text-dark">
          <i class="bi bi-clock-history text-primary me-2"></i> Diagnostic Order History
        </h2>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0 small">
            <thead class="table-light">
              <tr>
                <th>Accession</th>
                <th>Date</th>
                <th>Tests</th>
                <th>Amount</th>
                <th>Testing Status</th>
                <th>Payment Status</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($orders)): ?>
                <tr><td colspan="6" class="text-center text-muted py-4">No order records found.</td></tr>
              <?php else: ?>
                <?php foreach ($orders as $ord): ?>
                  <tr>
                    <td class="fw-bold font-monospace text-dark"><?= e($ord['accession_number']) ?></td>
                    <td><?= format_date($ord['order_date']) ?></td>
                    <td><span class="badge bg-light text-dark border"><?= $ord['item_count'] ?> Investigations</span></td>
                    <td class="fw-bold"><?= format_currency($ord['net_amount']) ?></td>
                    <td>
                      <span class="badge bg-info-subtle text-info-emphasis"><?= e($ord['status']) ?></span>
                    </td>
                    <td>
                      <span class="badge <?= $ord['payment_status'] === 'Paid' ? 'bg-success' : 'bg-warning text-dark' ?>">
                        <?= e($ord['payment_status']) ?>
                      </span>
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

  <footer class="text-center py-4 text-muted small border-top bg-white">
    &copy; <?= date('Y') ?> <strong><?= e($labName) ?></strong>. Patient Confidentiality Protected.
  </footer>

</body>
</html>

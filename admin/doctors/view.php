<?php
/**
 * LabMaster - Doctor Profile & Comprehensive History View
 * Aggregated patient referrals, test orders, invoices, service agreements, and reconciliation
 */

declare(strict_types=1);

$doctorId = (int)($_GET['id'] ?? 0);
$pageTitle = 'Doctor Profile & History';
$activeNav = 'doctors';

require_once dirname(__DIR__) . '/includes/header.php';
Auth::requirePermission('doctors.view');

$doctor = Database::fetch("SELECT * FROM `doctors` WHERE `id` = ?", [$doctorId]);
if (!$doctor) {
    set_flash('danger', 'Doctor profile not found in master records.');
    header('Location: ' . ADMIN_URL . '/doctors/index.php');
    exit;
}

$activeTab = trim($_GET['tab'] ?? 'overview');

// 1. Associated Patients Query
$patients = Database::fetchAll(
    "SELECT p.*, 
            (SELECT COUNT(*) FROM `test_orders` o WHERE o.patient_id = p.id) as order_count,
            (SELECT COALESCE(SUM(o.net_amount), 0) FROM `test_orders` o WHERE o.patient_id = p.id) as total_spent
     FROM `patients` p
     WHERE p.referring_doctor_id = ?
     ORDER BY p.id DESC",
    [$doctorId]
);

// 2. Doctor-Associated Test Orders Query (Preserves historical doctor associations on completed transactions)
$orders = Database::fetchAll(
    "SELECT o.*, p.full_name as patient_name, p.patient_id as patient_code, p.phone as patient_phone,
            (SELECT COUNT(*) FROM `test_order_items` oi WHERE oi.order_id = o.id) as item_count
     FROM `test_orders` o
     JOIN `patients` p ON o.patient_id = p.id
     WHERE o.referring_doctor_id = ?
     ORDER BY o.id DESC",
    [$doctorId]
);

// 3. Doctor-Associated Invoices & Collections Query
$invoices = Database::fetchAll(
    "SELECT i.*, o.accession_number as order_number, p.full_name as patient_name,
            COALESCE((SELECT SUM(py.amount) FROM `payments` py WHERE py.invoice_id = i.id), 0) as paid_amount
     FROM `invoices` i
     JOIN `test_orders` o ON i.order_id = o.id
     JOIN `patients` p ON o.patient_id = p.id
     WHERE o.referring_doctor_id = ?
     ORDER BY i.id DESC",
    [$doctorId]
);

// 4. Service Agreements Query
$agreements = Database::fetchAll(
    "SELECT a.*, u.full_name as creator_name, r.full_name as reviewer_name
     FROM `doctor_service_agreements` a
     LEFT JOIN `users` u ON a.created_by = u.id
     LEFT JOIN `users` r ON a.reviewer_id = r.id
     WHERE a.doctor_id = ?
     ORDER BY a.id DESC",
    [$doctorId]
);

// 5. Financial Reconciliation Ledger Query
$ledger = Database::fetchAll(
    "SELECT l.*, a.agreement_code, a.agreement_type, 
            u.full_name as creator_name, app.full_name as approver_name, pd.full_name as payer_name
     FROM `financial_reconciliation_ledger` l
     JOIN `doctor_service_agreements` a ON l.agreement_id = a.id
     LEFT JOIN `users` u ON l.created_by = u.id
     LEFT JOIN `users` app ON l.approved_by = app.id
     LEFT JOIN `users` pd ON l.paid_by = pd.id
     WHERE l.doctor_id = ?
     ORDER BY l.id DESC",
    [$doctorId]
);

// Compute Totals
$totalPatientsCount = count($patients);
$totalOrdersCount = count($orders);
$totalInvoicedAmount = array_sum(array_column($invoices, 'grand_total'));
$totalCollectedCash = array_sum(array_column($invoices, 'paid_amount'));
$totalBalanceReceivable = max(0, $totalInvoicedAmount - $totalCollectedCash);

$approvedServiceLiability = 0.0;
$totalDisbursedHonorarium = 0.0;
foreach ($ledger as $item) {
    if ($item['payment_status'] === 'Approved') {
        $approvedServiceLiability += (float)$item['amount'];
    } elseif ($item['payment_status'] === 'Paid') {
        $totalDisbursedHonorarium += (float)$item['amount'];
    }
}
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
  <div>
    <div class="d-flex align-items-center gap-2">
      <h2 class="h4 fw-bold mb-0"><?= e($doctor['name']) ?></h2>
      <span class="badge bg-light text-dark border font-monospace"><?= e($doctor['doctor_code']) ?></span>
      <span class="badge <?= $doctor['is_active'] ? 'bg-success-subtle text-success-emphasis' : 'bg-danger-subtle text-danger-emphasis' ?>">
        <?= $doctor['is_active'] ? 'Active Referring Doctor' : 'Inactive' ?>
      </span>
    </div>
    <p class="text-muted small mb-0 mt-1">
      <?= e($doctor['qualification'] ?: ($doctor['degree'] ?: 'MBBS')) ?> &bull; 
      <?= e($doctor['specialization'] ?: 'General Practice') ?> &bull; 
      <i class="bi bi-hospital me-1"></i><?= e($doctor['clinic_hospital'] ?: 'Independent') ?>
    </p>
  </div>
  <div class="d-flex flex-wrap gap-2">
    <?php if (Auth::can('doctors.edit')): ?>
      <a href="<?= ADMIN_URL ?>/doctors/create_edit.php?id=<?= $doctor['id'] ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-pencil me-1"></i> Edit Profile
      </a>
    <?php endif; ?>
    <?php if (Auth::can('reconciliation.create')): ?>
      <a href="<?= ADMIN_URL ?>/doctors/agreement_create.php?doctor_id=<?= $doctor['id'] ?>" class="btn btn-outline-primary btn-sm">
        <i class="bi bi-file-earmark-plus me-1"></i> New Agreement
      </a>
      <a href="<?= ADMIN_URL ?>/doctors/reconciliation_create.php?doctor_id=<?= $doctor['id'] ?>" class="btn btn-success btn-sm">
        <i class="bi bi-plus-circle me-1"></i> Record Reconciliation
      </a>
    <?php endif; ?>
    <a href="<?= ADMIN_URL ?>/doctors/index.php" class="btn btn-light border btn-sm">
      <i class="bi bi-arrow-left me-1"></i> Back to Directory
    </a>
  </div>
</div>

<!-- 5 Real-Time Summary Cards for this Doctor -->
<div class="row g-3 mb-4">
  <div class="col-sm-6 col-lg">
    <div class="admin-card p-3 border-start border-4 border-info h-100">
      <span class="text-muted small fw-semibold text-uppercase">Referred Patients</span>
      <h4 class="fw-bold mb-0 mt-1"><?= $totalPatientsCount ?></h4>
      <small class="text-muted">Unique patients</small>
    </div>
  </div>
  <div class="col-sm-6 col-lg">
    <div class="admin-card p-3 border-start border-4 border-primary h-100">
      <span class="text-muted small fw-semibold text-uppercase">Test Orders</span>
      <h4 class="fw-bold mb-0 mt-1"><?= $totalOrdersCount ?></h4>
      <small class="text-muted">Lab accessions</small>
    </div>
  </div>
  <div class="col-sm-6 col-lg">
    <div class="admin-card p-3 border-start border-4 border-warning h-100">
      <span class="text-muted small fw-semibold text-uppercase">Invoiced Billing</span>
      <h4 class="fw-bold mb-0 mt-1 text-dark"><?= format_currency($totalInvoicedAmount) ?></h4>
      <small class="text-muted">Accrual total</small>
    </div>
  </div>
  <div class="col-sm-6 col-lg">
    <div class="admin-card p-3 border-start border-4 border-success h-100">
      <span class="text-muted small fw-semibold text-uppercase">Realized Cash</span>
      <h4 class="fw-bold mb-0 mt-1 text-success"><?= format_currency($totalCollectedCash) ?></h4>
      <small class="text-success"><i class="bi bi-check-circle me-1"></i>Actual payments</small>
    </div>
  </div>
  <div class="col-sm-6 col-lg">
    <div class="admin-card p-3 border-start border-4 border-teal h-100" style="border-left-color: #0d9488 !important;">
      <span class="text-muted small fw-semibold text-uppercase">Service Honorarium</span>
      <h4 class="fw-bold mb-0 mt-1" style="color: #0d9488;"><?= format_currency($totalDisbursedHonorarium) ?></h4>
      <small class="text-muted">Paid per agreements</small>
    </div>
  </div>
</div>

<!-- Tabs Navigation -->
<div class="admin-card mb-4">
  <div class="admin-card-body p-0">
    <ul class="nav nav-tabs nav-fill border-0 px-3 pt-3" id="doctorTab" role="tablist">
      <li class="nav-item">
        <a class="nav-link <?= $activeTab === 'overview' ? 'active fw-bold' : '' ?>" href="?id=<?= $doctorId ?>&tab=overview">
          <i class="bi bi-person-lines-fill me-1"></i> Profile & Registration
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?= $activeTab === 'patients' ? 'active fw-bold' : '' ?>" href="?id=<?= $doctorId ?>&tab=patients">
          <i class="bi bi-people-fill me-1"></i> Associated Patients (<?= $totalPatientsCount ?>)
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?= $activeTab === 'orders' ? 'active fw-bold' : '' ?>" href="?id=<?= $doctorId ?>&tab=orders">
          <i class="bi bi-collection-fill me-1"></i> Test Orders (<?= $totalOrdersCount ?>)
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?= $activeTab === 'invoices' ? 'active fw-bold' : '' ?>" href="?id=<?= $doctorId ?>&tab=invoices">
          <i class="bi bi-receipt me-1"></i> Invoices & Collections
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?= $activeTab === 'agreements' ? 'active fw-bold' : '' ?>" href="?id=<?= $doctorId ?>&tab=agreements">
          <i class="bi bi-file-earmark-check me-1"></i> Service Agreements (<?= count($agreements) ?>)
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?= $activeTab === 'reconciliation' ? 'active fw-bold' : '' ?>" href="?id=<?= $doctorId ?>&tab=reconciliation">
          <i class="bi bi-wallet2 me-1"></i> Reconciliation Ledger (<?= count($ledger) ?>)
        </a>
      </li>
    </ul>
  </div>
</div>

<!-- TAB 1: OVERVIEW & REGISTRATION -->
<?php if ($activeTab === 'overview'): ?>
  <div class="row g-4">
    <div class="col-lg-6">
      <div class="admin-card h-100">
        <div class="admin-card-header bg-light py-3 px-4">
          <h5 class="fw-bold mb-0 text-dark"><i class="bi bi-card-heading text-primary me-2"></i>Medical Credentials & Identity</h5>
        </div>
        <div class="admin-card-body p-4">
          <table class="table table-borderless mb-0">
            <tr>
              <th class="ps-0 text-muted small text-uppercase" style="width: 40%;">System Doctor ID:</th>
              <td class="fw-bold">#<?= $doctor['id'] ?></td>
            </tr>
            <tr>
              <th class="ps-0 text-muted small text-uppercase">Doctor Code:</th>
              <td><span class="badge bg-light text-dark border font-monospace fs-6"><?= e($doctor['doctor_code']) ?></span></td>
            </tr>
            <tr>
              <th class="ps-0 text-muted small text-uppercase">Full Name:</th>
              <td class="fw-bold text-dark"><?= e($doctor['name']) ?></td>
            </tr>
            <tr>
              <th class="ps-0 text-muted small text-uppercase">Degrees / Qualifications:</th>
              <td><?= e($doctor['qualification'] ?: ($doctor['degree'] ?: 'None Specified')) ?></td>
            </tr>
            <tr>
              <th class="ps-0 text-muted small text-uppercase">Specialization:</th>
              <td><span class="badge bg-primary-subtle text-primary-emphasis"><?= e($doctor['specialization'] ?: 'General Practice') ?></span></td>
            </tr>
            <tr>
              <th class="ps-0 text-muted small text-uppercase">Medical Council Reg. No:</th>
              <td>
                <?php if (!empty($doctor['medical_registration_number'])): ?>
                  <span class="badge bg-secondary-subtle text-secondary-emphasis font-monospace">
                    <?= e($doctor['medical_registration_number']) ?>
                  </span>
                <?php else: ?>
                  <span class="text-muted">Not registered</span>
                <?php endif; ?>
              </td>
            </tr>
            <tr>
              <th class="ps-0 text-muted small text-uppercase">Status:</th>
              <td>
                <span class="badge <?= $doctor['is_active'] ? 'bg-success-subtle text-success-emphasis' : 'bg-danger-subtle text-danger-emphasis' ?>">
                  <?= $doctor['is_active'] ? 'Active' : 'Inactive' ?>
                </span>
              </td>
            </tr>
          </table>
        </div>
      </div>
    </div>

    <div class="col-lg-6">
      <div class="admin-card h-100">
        <div class="admin-card-header bg-light py-3 px-4">
          <h5 class="fw-bold mb-0 text-dark"><i class="bi bi-geo-alt text-primary me-2"></i>Affiliations & Communication</h5>
        </div>
        <div class="admin-card-body p-4">
          <table class="table table-borderless mb-0">
            <tr>
              <th class="ps-0 text-muted small text-uppercase" style="width: 40%;">Hospital / Clinic:</th>
              <td class="fw-semibold text-dark"><?= e($doctor['clinic_hospital'] ?: 'Independent Practice') ?></td>
            </tr>
            <tr>
              <th class="ps-0 text-muted small text-uppercase">Clinic Address:</th>
              <td><?= nl2br(e($doctor['address'] ?: 'Not recorded')) ?></td>
            </tr>
            <tr>
              <th class="ps-0 text-muted small text-uppercase">Mobile Phone:</th>
              <td>
                <?php if (!empty($doctor['phone'])): ?>
                  <a href="tel:<?= e($doctor['phone']) ?>" class="text-decoration-none">
                    <i class="bi bi-telephone-fill text-success me-1"></i><?= e($doctor['phone']) ?>
                  </a>
                <?php else: ?>
                  <span class="text-muted">Not provided</span>
                <?php endif; ?>
              </td>
            </tr>
            <tr>
              <th class="ps-0 text-muted small text-uppercase">Email Address:</th>
              <td>
                <?php if (!empty($doctor['email'])): ?>
                  <a href="mailto:<?= e($doctor['email']) ?>" class="text-decoration-none">
                    <i class="bi bi-envelope-fill text-primary me-1"></i><?= e($doctor['email']) ?>
                  </a>
                <?php else: ?>
                  <span class="text-muted">Not provided</span>
                <?php endif; ?>
              </td>
            </tr>
            <tr>
              <th class="ps-0 text-muted small text-uppercase">Created On:</th>
              <td><?= format_date($doctor['created_at']) ?></td>
            </tr>
            <tr>
              <th class="ps-0 text-muted small text-uppercase">Last Updated:</th>
              <td><?= !empty($doctor['updated_at']) ? format_date($doctor['updated_at']) : 'N/A' ?></td>
            </tr>
            <tr>
              <th class="ps-0 text-muted small text-uppercase">Internal Notes:</th>
              <td class="text-muted small"><?= nl2br(e($doctor['notes'] ?: 'No notes')) ?></td>
            </tr>
          </table>
        </div>
      </div>
    </div>
  </div>

<!-- TAB 2: ASSOCIATED PATIENTS -->
<?php elseif ($activeTab === 'patients'): ?>
  <div class="admin-card">
    <div class="admin-card-header bg-light py-3 px-4 d-flex justify-content-between align-items-center">
      <h5 class="fw-bold mb-0 text-dark">
        <i class="bi bi-people text-primary me-2"></i>Associated Patients (<?= count($patients) ?>)
      </h5>
      <span class="small text-muted">Patients referred or linked to Dr. <?= e($doctor['name']) ?></span>
    </div>
    <div class="admin-card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th>Patient ID</th>
              <th>Patient Name</th>
              <th>Gender & Age</th>
              <th>Contact Phone</th>
              <th>Registration Date</th>
              <th class="text-center">Orders</th>
              <th class="text-end">Total Spent</th>
              <th class="text-end text-nowrap pe-3" style="width: 120px;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($patients)): ?>
              <tr>
                <td colspan="8" class="text-center py-5 text-muted">
                  <i class="bi bi-person-x display-6 d-block mb-2 text-secondary"></i>
                  No patients currently associated with this doctor.
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($patients as $pat): ?>
                <tr>
                  <td><span class="badge bg-light text-dark border font-monospace"><?= e($pat['patient_id']) ?></span></td>
                  <td>
                    <a href="<?= ADMIN_URL ?>/patients/view.php?id=<?= $pat['id'] ?>" class="fw-bold text-dark text-decoration-none">
                      <?= e($pat['full_name']) ?>
                    </a>
                  </td>
                  <td><?= e($pat['gender']) ?>, <?= (int)$pat['age'] ?> <?= e($pat['age_unit']) ?></td>
                  <td><?= e($pat['phone']) ?></td>
                  <td><?= format_date($pat['created_at']) ?></td>
                  <td class="text-center">
                    <span class="badge bg-primary-subtle text-primary-emphasis"><?= $pat['order_count'] ?> Orders</span>
                  </td>
                  <td class="text-end fw-semibold text-dark"><?= format_currency($pat['total_spent']) ?></td>
                  <td class="text-end text-nowrap pe-3">
                    <div class="d-inline-flex align-items-center justify-content-end gap-1">
                      <a href="<?= ADMIN_URL ?>/patients/view.php?id=<?= $pat['id'] ?>" class="btn btn-sm btn-outline-primary btn-action-icon" title="View Patient Details">
                        <i class="bi bi-eye"></i>
                      </a>
                      <a href="<?= ADMIN_URL ?>/orders/create.php?patient_id=<?= $pat['id'] ?>" class="btn btn-sm btn-outline-success btn-action-icon" title="New Test Order">
                        <i class="bi bi-plus-circle"></i>
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

<!-- TAB 3: ASSOCIATED TEST ORDERS -->
<?php elseif ($activeTab === 'orders'): ?>
  <div class="admin-card">
    <div class="admin-card-header bg-light py-3 px-4 d-flex justify-content-between align-items-center">
      <div>
        <h5 class="fw-bold mb-0 text-dark">
          <i class="bi bi-collection text-primary me-2"></i>Doctor-Associated Test Orders (<?= count($orders) ?>)
        </h5>
        <small class="text-muted">Historical doctor associations on completed transactions remain permanently preserved.</small>
      </div>
    </div>
    <div class="admin-card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th>Order / Accession</th>
              <th>Order Date</th>
              <th>Patient Details</th>
              <th>Items</th>
              <th>Net Amount</th>
              <th>Lab Status</th>
              <th>Payment</th>
              <th class="text-end text-nowrap pe-3" style="width: 120px;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($orders)): ?>
              <tr>
                <td colspan="8" class="text-center py-5 text-muted">
                  <i class="bi bi-inbox display-6 d-block mb-2 text-secondary"></i>
                  No test orders associated with this referring doctor yet.
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($orders as $ord): ?>
                <tr>
                  <td>
                    <span class="badge bg-light text-dark border font-monospace fw-bold">
                      <?= e($ord['accession_number']) ?>
                    </span>
                  </td>
                  <td><?= format_date($ord['order_date']) ?></td>
                  <td>
                    <div class="fw-bold"><?= e($ord['patient_name']) ?></div>
                    <div class="text-muted small"><?= e($ord['patient_code']) ?></div>
                  </td>
                  <td><span class="badge bg-light text-dark border"><?= $ord['item_count'] ?> Tests</span></td>
                  <td class="fw-semibold text-dark"><?= format_currency($ord['net_amount']) ?></td>
                  <td>
                    <span class="badge <?= $ord['status'] === 'Completed' ? 'bg-success-subtle text-success-emphasis' : 'bg-info-subtle text-info-emphasis' ?>">
                      <?= e($ord['status']) ?>
                    </span>
                  </td>
                  <td>
                    <span class="badge <?= $ord['payment_status'] === 'Paid' ? 'bg-success' : ($ord['payment_status'] === 'Partial' ? 'bg-warning text-dark' : 'bg-danger') ?>">
                      <?= e($ord['payment_status']) ?>
                    </span>
                  </td>
                  <td class="text-end text-nowrap pe-3">
                    <div class="d-inline-flex align-items-center justify-content-end gap-1">
                      <a href="<?= ADMIN_URL ?>/orders/view.php?id=<?= $ord['id'] ?>" class="btn btn-sm btn-outline-primary btn-action-icon" title="View Accession Order">
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
  </div>

<!-- TAB 4: INVOICES & COLLECTIONS -->
<?php elseif ($activeTab === 'invoices'): ?>
  <div class="admin-card">
    <div class="admin-card-header bg-light py-3 px-4 d-flex justify-content-between align-items-center">
      <div>
        <h5 class="fw-bold mb-0 text-dark"><i class="bi bi-receipt text-primary me-2"></i>Doctor Billing & Collection History</h5>
        <small class="text-muted">Strict Cash vs. Accrual distinction: Invoiced receivables are segregated from actual cash received.</small>
      </div>
      <div class="d-flex gap-3 text-end">
        <div>
          <small class="text-muted d-block">Total Invoiced</small>
          <strong class="text-dark"><?= format_currency($totalInvoicedAmount) ?></strong>
        </div>
        <div>
          <small class="text-muted d-block">Collected Cash</small>
          <strong class="text-success"><?= format_currency($totalCollectedCash) ?></strong>
        </div>
        <div>
          <small class="text-muted d-block">Uncollected Balance</small>
          <strong class="<?= $totalBalanceReceivable > 0 ? 'text-danger' : 'text-muted' ?>"><?= format_currency($totalBalanceReceivable) ?></strong>
        </div>
      </div>
    </div>
    <div class="admin-card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th>Invoice Number</th>
              <th>Invoice Date</th>
              <th>Patient</th>
              <th>Subtotal</th>
              <th>Discount</th>
              <th>Net Invoiced</th>
              <th>Actual Cash Paid</th>
              <th>Due Balance</th>
              <th>Status</th>
              <th class="text-end text-nowrap pe-3" style="width: 120px;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($invoices)): ?>
              <tr>
                <td colspan="10" class="text-center py-5 text-muted">
                  <i class="bi bi-receipt display-6 d-block mb-2 text-secondary"></i>
                  No invoices generated for this doctor's referrals.
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($invoices as $inv): 
                $due = max(0, (float)$inv['grand_total'] - (float)$inv['paid_amount']);
              ?>
                <tr>
                  <td>
                    <span class="badge bg-light text-dark border font-monospace fw-bold">
                      <?= e($inv['invoice_number']) ?>
                    </span>
                  </td>
                  <td><?= format_date($inv['invoice_date']) ?></td>
                  <td><?= e($inv['patient_name']) ?></td>
                  <td><?= format_currency($inv['subtotal']) ?></td>
                  <td class="text-muted"><?= format_currency($inv['discount_amount']) ?></td>
                  <td class="fw-semibold text-dark"><?= format_currency($inv['grand_total']) ?></td>
                  <td class="text-success fw-bold"><?= format_currency($inv['paid_amount']) ?></td>
                  <td class="<?= $due > 0 ? 'text-danger fw-bold' : 'text-muted' ?>"><?= format_currency($due) ?></td>
                  <td>
                    <span class="badge <?= $inv['payment_status'] === 'Paid' ? 'bg-success' : ($inv['payment_status'] === 'Partial' ? 'bg-warning text-dark' : 'bg-danger') ?>">
                      <?= e($inv['payment_status']) ?>
                    </span>
                  </td>
                  <td class="text-end text-nowrap pe-3">
                    <div class="d-inline-flex align-items-center justify-content-end gap-1">
                      <a href="<?= ADMIN_URL ?>/billing/view.php?id=<?= $inv['id'] ?>" class="btn btn-sm btn-outline-primary btn-action-icon" title="View Invoice">
                        <i class="bi bi-eye"></i>
                      </a>
                      <a href="<?= ADMIN_URL ?>/billing/print_invoice.php?id=<?= $inv['id'] ?>" target="_blank" class="btn btn-sm btn-outline-secondary btn-action-icon" title="Print Invoice">
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

<!-- TAB 5: SERVICE AGREEMENTS -->
<?php elseif ($activeTab === 'agreements'): ?>
  <div class="admin-card">
    <div class="admin-card-header bg-light py-3 px-4 d-flex justify-content-between align-items-center">
      <div>
        <h5 class="fw-bold mb-0 text-dark"><i class="bi bi-file-earmark-check text-primary me-2"></i>Lawful Service Agreements & Policies</h5>
        <small class="text-muted">Documented arrangements for consultation, retainership, second opinions, and supervised camps</small>
      </div>
      <?php if (Auth::can('reconciliation.create')): ?>
        <a href="<?= ADMIN_URL ?>/doctors/agreement_create.php?doctor_id=<?= $doctor['id'] ?>" class="btn btn-sm btn-primary-lm">
          <i class="bi bi-plus-circle me-1"></i> Add Agreement
        </a>
      <?php endif; ?>
    </div>
    <div class="admin-card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th>Agreement Code</th>
              <th>Agreement Type</th>
              <th>Payment Basis & Rate</th>
              <th>Effective Dates</th>
              <th>Counterparty</th>
              <th>Review Status</th>
              <th>Reviewer</th>
              <th class="text-end text-nowrap pe-3" style="width: 120px;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($agreements)): ?>
              <tr>
                <td colspan="8" class="text-center py-5 text-muted">
                  <i class="bi bi-file-earmark-x display-6 d-block mb-2 text-secondary"></i>
                  No service agreements recorded for this physician.
                  <?php if (Auth::can('reconciliation.create')): ?>
                    <div class="mt-3">
                      <a href="<?= ADMIN_URL ?>/doctors/agreement_create.php?doctor_id=<?= $doctor['id'] ?>" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-plus-lg me-1"></i> Create Initial Service Agreement
                      </a>
                    </div>
                  <?php endif; ?>
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($agreements as $agr): ?>
                <tr>
                  <td><span class="badge bg-light text-dark border font-monospace fw-bold"><?= e($agr['agreement_code']) ?></span></td>
                  <td>
                    <div class="fw-semibold text-dark"><?= e($agr['agreement_type']) ?></div>
                    <div class="text-muted small text-truncate" style="max-width: 250px;" title="<?= e($agr['service_description']) ?>">
                      <?= e($agr['service_description']) ?>
                    </div>
                  </td>
                  <td>
                    <div class="fw-bold text-primary"><?= format_currency($agr['rate_amount']) ?></div>
                    <div class="text-secondary small"><?= e($agr['payment_basis']) ?></div>
                  </td>
                  <td>
                    <div class="small">From: <?= format_date($agr['effective_date']) ?></div>
                    <div class="small text-muted">To: <?= $agr['expiry_date'] ? format_date($agr['expiry_date']) : 'Indefinite' ?></div>
                  </td>
                  <td><?= e($agr['counterparty_name']) ?></td>
                  <td>
                    <span class="badge <?= $agr['approval_status'] === 'Approved' ? 'bg-success-subtle text-success-emphasis' : ($agr['approval_status'] === 'Under Review' ? 'bg-warning-subtle text-warning-emphasis' : 'bg-secondary-subtle text-secondary-emphasis') ?>">
                      <?= e($agr['approval_status']) ?>
                    </span>
                  </td>
                  <td>
                    <?php if (!empty($agr['reviewer_name'])): ?>
                      <div class="small fw-semibold"><?= e($agr['reviewer_name']) ?></div>
                      <div class="text-muted" style="font-size:0.7rem;"><?= format_date($agr['reviewed_at']) ?></div>
                    <?php else: ?>
                      <span class="text-muted small">Pending Review</span>
                    <?php endif; ?>
                  </td>
                  <td class="text-end text-nowrap pe-3">
                    <div class="d-inline-flex align-items-center justify-content-end gap-1">
                      <a href="<?= ADMIN_URL ?>/doctors/agreements.php?id=<?= $agr['id'] ?>" class="btn btn-sm btn-outline-primary btn-action-icon" title="View Agreement">
                        <i class="bi bi-eye"></i>
                      </a>
                      <?php if (Auth::can('reconciliation.create') && in_array($agr['approval_status'], ['Draft', 'Under Review'])): ?>
                        <a href="<?= ADMIN_URL ?>/doctors/agreement_create.php?id=<?= $agr['id'] ?>" class="btn btn-sm btn-outline-secondary btn-action-icon" title="Edit Agreement">
                          <i class="bi bi-pencil"></i>
                        </a>
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

<!-- TAB 6: RECONCILIATION LEDGER -->
<?php elseif ($activeTab === 'reconciliation'): ?>
  <div class="admin-card">
    <div class="admin-card-header bg-light py-3 px-4 d-flex justify-content-between align-items-center">
      <div>
        <h5 class="fw-bold mb-0 text-dark"><i class="bi bi-wallet2 text-success me-2"></i>Financial Reconciliation Ledger</h5>
        <small class="text-muted">Documented service fee disbursements, honorariums, vouchers, and audit trail</small>
      </div>
      <?php if (Auth::can('reconciliation.create')): ?>
        <a href="<?= ADMIN_URL ?>/doctors/reconciliation_create.php?doctor_id=<?= $doctor['id'] ?>" class="btn btn-sm btn-success">
          <i class="bi bi-plus-circle me-1"></i> Record Transaction
        </a>
      <?php endif; ?>
    </div>
    <div class="admin-card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th>Transaction ID</th>
              <th>Date</th>
              <th>Transaction Type</th>
              <th>Agreement Ref</th>
              <th>Calculation Basis</th>
              <th>Amount</th>
              <th>Status</th>
              <th>Payment Details</th>
              <th class="text-end text-nowrap pe-3" style="width: 120px;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($ledger)): ?>
              <tr>
                <td colspan="9" class="text-center py-5 text-muted">
                  <i class="bi bi-cash-stack display-6 d-block mb-2 text-secondary"></i>
                  No reconciliation vouchers recorded for this doctor.
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($ledger as $item): ?>
                <tr>
                  <td><span class="badge bg-light text-dark border font-monospace fw-bold"><?= e($item['transaction_code']) ?></span></td>
                  <td><?= format_date($item['transaction_date']) ?></td>
                  <td><?= e($item['transaction_type']) ?></td>
                  <td>
                    <span class="badge bg-secondary-subtle text-secondary-emphasis font-monospace">
                      <?= e($item['agreement_code']) ?>
                    </span>
                  </td>
                  <td>
                    <div class="small text-truncate" style="max-width: 250px;" title="<?= e($item['calculation_basis']) ?>">
                      <?= e($item['calculation_basis']) ?>
                    </div>
                  </td>
                  <td class="fw-bold text-dark"><?= format_currency($item['amount']) ?></td>
                  <td>
                    <span class="badge <?= $item['payment_status'] === 'Paid' ? 'bg-success' : ($item['payment_status'] === 'Approved' ? 'bg-primary' : ($item['payment_status'] === 'Pending Review' ? 'bg-warning text-dark' : 'bg-secondary')) ?>">
                      <?= e($item['payment_status']) ?>
                    </span>
                  </td>
                  <td>
                    <?php if ($item['payment_status'] === 'Paid'): ?>
                      <div class="small fw-semibold"><?= e($item['payment_method']) ?></div>
                      <div class="text-muted font-monospace" style="font-size:0.72rem;">Ref: <?= e($item['transaction_reference'] ?: 'N/A') ?></div>
                    <?php elseif ($item['payment_status'] === 'Approved'): ?>
                      <span class="text-primary small fw-semibold">Ready for Disbursement</span>
                    <?php else: ?>
                      <span class="text-muted small"><?= e($item['payment_status']) ?></span>
                    <?php endif; ?>
                  </td>
                  <td class="text-end text-nowrap pe-3">
                    <div class="d-inline-flex align-items-center justify-content-end gap-1">
                      <a href="<?= ADMIN_URL ?>/doctors/reconciliation.php?id=<?= $item['id'] ?>" class="btn btn-sm btn-outline-primary btn-action-icon" title="View Transaction Voucher">
                        <i class="bi bi-eye"></i>
                      </a>
                      <?php if ($item['payment_status'] === 'Approved' && Auth::can('reconciliation.pay')): ?>
                        <a href="<?= ADMIN_URL ?>/doctors/reconciliation.php?filter=approved" class="btn btn-sm btn-outline-success btn-action-icon" title="Disburse Payment">
                          <i class="bi bi-cash-stack"></i>
                        </a>
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
<?php endif; ?>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>

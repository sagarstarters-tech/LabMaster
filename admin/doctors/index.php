<?php
/**
 * LabMaster - Doctor Master & Financial Reconciliation Dashboard
 * Real-time clinical referral analytics, lawful service agreements, and reconciliation
 */

declare(strict_types=1);

$pageTitle = 'Doctor Master & Financial Reconciliation';
$activeNav = 'doctors';

require_once dirname(__DIR__) . '/includes/auth_guard.php';
Auth::requirePermission('doctors.view');

// Handle POST Actions (e.g. Delete / Deactivate Doctor)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'delete_doctor') {
        Auth::requirePermission('doctors.edit');
        $doctorId = (int)($_POST['doctor_id'] ?? 0);
        $doc = Database::fetch("SELECT * FROM `doctors` WHERE `id` = ?", [$doctorId]);
        if (!$doc) {
            set_flash('danger', 'Doctor record not found.');
        } else {
            $orderCount = (int)Database::fetchColumn("SELECT COUNT(*) FROM `test_orders` WHERE `referring_doctor_id` = ?", [$doctorId]);
            $agreementCount = (int)Database::fetchColumn("SELECT COUNT(*) FROM `doctor_service_agreements` WHERE `doctor_id` = ?", [$doctorId]);
            if ($orderCount > 0 || $agreementCount > 0) {
                Database::execute("UPDATE `doctors` SET `is_active` = 0 WHERE `id` = ?", [$doctorId]);
                log_audit('Doctor Deactivated', 'Doctors', $doc['doctor_code'], "Doctor {$doc['name']} has existing clinical/contract history. Deactivated doctor.");
                set_flash('warning', "Doctor \"{$doc['name']}\" is linked to {$orderCount} patient orders or contracts. To preserve clinical audit records, doctor was set to Inactive.");
            } else {
                Database::execute("DELETE FROM `doctors` WHERE `id` = ?", [$doctorId]);
                log_audit('Doctor Deleted', 'Doctors', $doc['doctor_code'], "Deleted doctor {$doc['name']}.");
                set_flash('success', "Doctor \"{$doc['name']}\" deleted successfully.");
            }
        }
        header('Location: ' . ADMIN_URL . '/doctors/index.php');
        exit;
    }
}

// Fetch Real-time Dashboard KPI Metrics
$totalDoctors = (int)(Database::fetch("SELECT COUNT(*) as c FROM `doctors`")['c'] ?? 0);
$activeDoctors = (int)(Database::fetch("SELECT COUNT(*) as c FROM `doctors` WHERE `is_active` = 1")['c'] ?? 0);

$associatedPatientsCount = (int)(Database::fetch(
    "SELECT COUNT(DISTINCT id) as c FROM `patients` WHERE `referring_doctor_id` IS NOT NULL"
)['c'] ?? 0);

$associatedBilledRevenue = (float)(Database::fetch(
    "SELECT COALESCE(SUM(o.net_amount), 0) as s 
     FROM `test_orders` o 
     WHERE o.referring_doctor_id IS NOT NULL"
)['s'] ?? 0.0);

$associatedCashCollected = (float)(Database::fetch(
    "SELECT COALESCE(SUM(p.amount), 0) as s 
     FROM `payments` p 
     JOIN `invoices` i ON p.invoice_id = i.id 
     JOIN `test_orders` o ON i.order_id = o.id 
     WHERE o.referring_doctor_id IS NOT NULL"
)['s'] ?? 0.0);

$pendingReconciliationItems = (int)(Database::fetch(
    "SELECT COUNT(*) as c FROM `financial_reconciliation_ledger` WHERE `payment_status` IN ('Draft', 'Pending Review')"
)['c'] ?? 0);

$pendingAgreementReviews = (int)(Database::fetch(
    "SELECT COUNT(*) as c FROM `doctor_service_agreements` WHERE `approval_status` IN ('Draft', 'Under Review')"
)['c'] ?? 0);

$approvedServiceLiability = (float)(Database::fetch(
    "SELECT COALESCE(SUM(amount), 0) as s FROM `financial_reconciliation_ledger` WHERE `payment_status` = 'Approved'"
)['s'] ?? 0.0);

$completedDisbursements = (float)(Database::fetch(
    "SELECT COALESCE(SUM(amount), 0) as s FROM `financial_reconciliation_ledger` WHERE `payment_status` = 'Paid'"
)['s'] ?? 0.0);

// Search & Filter Parameters
$search = trim($_GET['search'] ?? '');
$filterSpecialization = trim($_GET['specialization'] ?? '');
$filterClinic = trim($_GET['clinic'] ?? '');
$filterStatus = trim($_GET['status'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;
$offset = ($page - 1) * $perPage;

$whereClauses = ['1=1'];
$params = [];

if ($search !== '') {
    $whereClauses[] = "(d.name LIKE ? OR d.doctor_code LIKE ? OR d.medical_registration_number LIKE ? OR d.clinic_hospital LIKE ? OR d.phone LIKE ?)";
    $like = '%' . $search . '%';
    $params = array_merge($params, [$like, $like, $like, $like, $like]);
}

if ($filterSpecialization !== '') {
    $whereClauses[] = "d.specialization = ?";
    $params[] = $filterSpecialization;
}

if ($filterClinic !== '') {
    $whereClauses[] = "d.clinic_hospital = ?";
    $params[] = $filterClinic;
}

if ($filterStatus !== '') {
    $whereClauses[] = "d.is_active = ?";
    $params[] = $filterStatus === 'active' ? 1 : 0;
}

$whereSql = implode(' AND ', $whereClauses);

// Count total matching
$totalMatching = (int)(Database::fetch("SELECT COUNT(*) as c FROM `doctors` d WHERE {$whereSql}", $params)['c'] ?? 0);
$totalPages = max(1, ceil($totalMatching / $perPage));

// Query Doctors with Real-time Associated Metrics
$sql = "
    SELECT d.*,
           (SELECT COUNT(*) FROM `patients` p WHERE p.referring_doctor_id = d.id) as patient_count,
           (SELECT COUNT(*) FROM `test_orders` o WHERE o.referring_doctor_id = d.id) as order_count,
           (SELECT COALESCE(SUM(o.net_amount), 0) FROM `test_orders` o WHERE o.referring_doctor_id = d.id) as total_invoiced,
           (SELECT COALESCE(SUM(p.amount), 0) 
            FROM `payments` p 
            JOIN `invoices` i ON p.invoice_id = i.id 
            JOIN `test_orders` o ON i.order_id = o.id 
            WHERE o.referring_doctor_id = d.id) as total_collected,
           (SELECT COUNT(*) FROM `doctor_service_agreements` a WHERE a.doctor_id = d.id AND a.approval_status = 'Approved') as active_agreements_count,
           (SELECT COUNT(*) FROM `financial_reconciliation_ledger` l WHERE l.doctor_id = d.id AND l.payment_status = 'Pending Review') as pending_ledger_count
    FROM `doctors` d
    WHERE {$whereSql}
    ORDER BY d.id ASC
    LIMIT {$perPage} OFFSET {$offset}
";
$doctors = Database::fetchAll($sql, $params);

// Specializations for filter
$specializations = Database::fetchAll("SELECT DISTINCT specialization FROM `doctors` WHERE specialization IS NOT NULL AND specialization != '' ORDER BY specialization ASC");
$clinics = Database::fetchAll("SELECT DISTINCT clinic_hospital FROM `doctors` WHERE clinic_hospital IS NOT NULL AND clinic_hospital != '' ORDER BY clinic_hospital ASC");

// Handle Doctor Delete / Deactivate Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_doctor') {
    require_csrf();
    Auth::requirePermission('doctors.edit');

    $delId = (int)($_POST['doctor_id'] ?? 0);
    $targetDoc = Database::fetch("SELECT * FROM `doctors` WHERE `id` = ?", [$delId]);

    if (!$targetDoc) {
        set_flash('danger', 'Doctor profile not found.');
    } else {
        $pCount = (int)(Database::fetch("SELECT COUNT(*) as c FROM `patients` WHERE `referring_doctor_id` = ?", [$delId])['c'] ?? 0);
        $oCount = (int)(Database::fetch("SELECT COUNT(*) as c FROM `test_orders` WHERE `referring_doctor_id` = ?", [$delId])['c'] ?? 0);
        $aCount = (int)(Database::fetch("SELECT COUNT(*) as c FROM `doctor_service_agreements` WHERE `doctor_id` = ?", [$delId])['c'] ?? 0);
        $lCount = (int)(Database::fetch("SELECT COUNT(*) as c FROM `financial_reconciliation_ledger` WHERE `doctor_id` = ?", [$delId])['c'] ?? 0);
        $totalAssoc = $pCount + $oCount + $aCount + $lCount;

        if ($totalAssoc > 0) {
            Database::execute("UPDATE `doctors` SET `is_active` = 0 WHERE `id` = ?", [$delId]);
            log_audit('Doctor Deactivated', 'Doctors', $targetDoc['doctor_code'], "Doctor {$targetDoc['name']} deactivated ({$totalAssoc} existing associations).");
            set_flash('warning', "Doctor {$targetDoc['name']} has {$totalAssoc} associated clinical/financial records. To protect patient history and financial integrity, doctor has been safely deactivated instead of deleted.");
        } else {
            Database::execute("DELETE FROM `doctors` WHERE `id` = ?", [$delId]);
            log_audit('Doctor Deleted', 'Doctors', $targetDoc['doctor_code'], "Permanently deleted doctor {$targetDoc['name']}.");
            set_flash('success', "Doctor {$targetDoc['name']} ({$targetDoc['doctor_code']}) deleted successfully.");
        }
    }
    header('Location: ' . ADMIN_URL . '/doctors/index.php');
    exit;
}

require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
  <div>
    <h2 class="h4 fw-bold mb-1">
      <i class="bi bi-hospital text-primary me-2"></i>Doctor Master & Financial Reconciliation
    </h2>
    <p class="text-muted small mb-0">
      Consultant physician directory, lawful service agreements, and financial ledger reconciliation
    </p>
  </div>
  <div class="d-flex flex-wrap gap-2">
    <?php if (Auth::can('doctors.create')): ?>
    <a href="<?= ADMIN_URL ?>/doctors/create_edit.php" class="btn btn-primary-lm d-inline-flex align-items-center gap-2">
      <i class="bi bi-person-plus-fill"></i> Add Doctor
    </a>
    <?php endif; ?>
    <?php if (Auth::can('reconciliation.view')): ?>
    <a href="<?= ADMIN_URL ?>/doctors/agreements.php" class="btn btn-outline-primary d-inline-flex align-items-center gap-2">
      <i class="bi bi-file-earmark-text"></i> Service Agreements
      <?php if ($pendingAgreementReviews > 0): ?>
        <span class="badge bg-warning text-dark"><?= $pendingAgreementReviews ?></span>
      <?php endif; ?>
    </a>
    <a href="<?= ADMIN_URL ?>/doctors/reconciliation.php" class="btn btn-outline-success d-inline-flex align-items-center gap-2">
      <i class="bi bi-wallet2"></i> Reconciliation Ledger
      <?php if ($pendingReconciliationItems > 0): ?>
        <span class="badge bg-danger"><?= $pendingReconciliationItems ?></span>
      <?php endif; ?>
    </a>
    <a href="<?= ADMIN_URL ?>/doctors/reports.php" class="btn btn-outline-secondary d-inline-flex align-items-center gap-2">
      <i class="bi bi-graph-up-arrow"></i> Financial Reports
    </a>
    <?php endif; ?>
  </div>
</div>

<!-- Legal Compliance Advisory Banner -->
<div class="alert alert-light border border-info border-opacity-50 shadow-sm d-flex align-items-center gap-3 p-3 mb-4 rounded-3">
  <div class="text-info fs-3">
    <i class="bi bi-shield-check"></i>
  </div>
  <div class="small text-secondary flex-grow-1">
    <strong class="text-dark">Regulatory Compliance Notice (MCI / NMC Ethics Guidelines):</strong>
    LabMaster enforces ethical laboratory operations. Automated commission kickbacks and percentage fee-splitting for patient referrals are strictly disabled. All professional disbursements must be linked to verified, documented <strong>Service Agreements</strong> (e.g. Consulting, Retainership, Camp Supervision, or Institutional SLAs) and audited through the Financial Reconciliation Ledger.
  </div>
</div>

<!-- 8 Live Dashboard KPI Cards -->
<div class="row g-3 mb-4">
  <!-- Card 1: Total Doctors -->
  <div class="col-sm-6 col-xl-3">
    <div class="admin-card h-100 p-3 border-start border-4 border-primary">
      <div class="d-flex justify-content-between align-items-center">
        <div>
          <span class="text-muted small fw-semibold text-uppercase">Total Doctors</span>
          <h3 class="fw-bold mb-0 mt-1"><?= number_format($totalDoctors) ?></h3>
          <span class="badge bg-success-subtle text-success-emphasis mt-1"><?= $activeDoctors ?> Active</span>
        </div>
        <div class="rounded-circle bg-primary-subtle text-primary p-3">
          <i class="bi bi-person-badge fs-4"></i>
        </div>
      </div>
    </div>
  </div>

  <!-- Card 2: Associated Patients -->
  <div class="col-sm-6 col-xl-3">
    <div class="admin-card h-100 p-3 border-start border-4 border-info">
      <div class="d-flex justify-content-between align-items-center">
        <div>
          <span class="text-muted small fw-semibold text-uppercase">Associated Patients</span>
          <h3 class="fw-bold mb-0 mt-1"><?= number_format($associatedPatientsCount) ?></h3>
          <small class="text-muted">Referred pathology patients</small>
        </div>
        <div class="rounded-circle bg-info-subtle text-info p-3">
          <i class="bi bi-people-fill fs-4"></i>
        </div>
      </div>
    </div>
  </div>

  <!-- Card 3: Doctor Billing Summary -->
  <div class="col-sm-6 col-xl-3">
    <div class="admin-card h-100 p-3 border-start border-4 border-warning">
      <div class="d-flex justify-content-between align-items-center">
        <div>
          <span class="text-muted small fw-semibold text-uppercase">Associated Billed</span>
          <h3 class="fw-bold mb-0 mt-1 text-dark"><?= format_currency($associatedBilledRevenue) ?></h3>
          <small class="text-muted">Gross invoiced volume</small>
        </div>
        <div class="rounded-circle bg-warning-subtle text-warning-emphasis p-3">
          <i class="bi bi-receipt-cutoff fs-4"></i>
        </div>
      </div>
    </div>
  </div>

  <!-- Card 4: Actual Payments Collected -->
  <div class="col-sm-6 col-xl-3">
    <div class="admin-card h-100 p-3 border-start border-4 border-success">
      <div class="d-flex justify-content-between align-items-center">
        <div>
          <span class="text-muted small fw-semibold text-uppercase">Actual Collected</span>
          <h3 class="fw-bold mb-0 mt-1 text-success"><?= format_currency($associatedCashCollected) ?></h3>
          <small class="text-success fw-medium"><i class="bi bi-check-circle me-1"></i>Realized Cash</small>
        </div>
        <div class="rounded-circle bg-success-subtle text-success p-3">
          <i class="bi bi-cash-coin fs-4"></i>
        </div>
      </div>
    </div>
  </div>

  <!-- Card 5: Outstanding Reconciliation Items -->
  <div class="col-sm-6 col-xl-3">
    <div class="admin-card h-100 p-3 border-start border-4 border-danger">
      <div class="d-flex justify-content-between align-items-center">
        <div>
          <span class="text-muted small fw-semibold text-uppercase">Reconciliation Pending</span>
          <h3 class="fw-bold mb-0 mt-1 <?= $pendingReconciliationItems > 0 ? 'text-danger' : 'text-dark' ?>">
            <?= number_format($pendingReconciliationItems) ?>
          </h3>
          <small class="text-muted">Drafts & pending review</small>
        </div>
        <div class="rounded-circle bg-danger-subtle text-danger p-3">
          <i class="bi bi-hourglass-split fs-4"></i>
        </div>
      </div>
    </div>
  </div>

  <!-- Card 6: Pending Agreement Reviews -->
  <div class="col-sm-6 col-xl-3">
    <div class="admin-card h-100 p-3 border-start border-4 border-secondary">
      <div class="d-flex justify-content-between align-items-center">
        <div>
          <span class="text-muted small fw-semibold text-uppercase">Agreements Under Review</span>
          <h3 class="fw-bold mb-0 mt-1"><?= number_format($pendingAgreementReviews) ?></h3>
          <small class="text-muted">Awaiting compliance review</small>
        </div>
        <div class="rounded-circle bg-secondary-subtle text-secondary p-3">
          <i class="bi bi-file-earmark-check fs-4"></i>
        </div>
      </div>
    </div>
  </div>

  <!-- Card 7: Approved Service Payments -->
  <div class="col-sm-6 col-xl-3">
    <div class="admin-card h-100 p-3 border-start border-4 border-primary">
      <div class="d-flex justify-content-between align-items-center">
        <div>
          <span class="text-muted small fw-semibold text-uppercase">Approved Service Due</span>
          <h3 class="fw-bold mb-0 mt-1 text-primary"><?= format_currency($approvedServiceLiability) ?></h3>
          <small class="text-muted">Authorized payable liability</small>
        </div>
        <div class="rounded-circle bg-primary-subtle text-primary p-3">
          <i class="bi bi-credit-card-2-front fs-4"></i>
        </div>
      </div>
    </div>
  </div>

  <!-- Card 8: Completed Payments -->
  <div class="col-sm-6 col-xl-3">
    <div class="admin-card h-100 p-3 border-start border-4 border-teal" style="border-left-color: #0d9488 !important;">
      <div class="d-flex justify-content-between align-items-center">
        <div>
          <span class="text-muted small fw-semibold text-uppercase">Completed Payouts</span>
          <h3 class="fw-bold mb-0 mt-1" style="color: #0d9488;"><?= format_currency($completedDisbursements) ?></h3>
          <small class="text-muted">Vouchers disbursed & reconciled</small>
        </div>
        <div class="rounded-circle p-3" style="background: rgba(13, 148, 136, 0.15); color: #0d9488;">
          <i class="bi bi-check-all fs-4"></i>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Search, Filter & Controls Bar -->
<div class="admin-card mb-4">
  <div class="admin-card-body p-3">
    <form method="GET" action="<?= ADMIN_URL ?>/doctors/index.php" class="row g-2 align-items-center">
      <div class="col-md-4">
        <div class="input-group">
          <span class="input-group-text bg-white text-muted border-end-0"><i class="bi bi-search"></i></span>
          <input type="text" name="search" class="form-control border-start-0" 
                 placeholder="Search by name, code, hospital, reg no..." 
                 value="<?= e($search) ?>">
        </div>
      </div>

      <div class="col-md-2">
        <select name="specialization" class="form-select">
          <option value="">All Specializations</option>
          <?php foreach ($specializations as $s): ?>
            <option value="<?= e($s['specialization']) ?>" <?= $filterSpecialization === $s['specialization'] ? 'selected' : '' ?>>
              <?= e($s['specialization']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-md-3">
        <select name="clinic" class="form-select">
          <option value="">All Hospitals & Clinics</option>
          <?php foreach ($clinics as $c): ?>
            <option value="<?= e($c['clinic_hospital']) ?>" <?= $filterClinic === $c['clinic_hospital'] ? 'selected' : '' ?>>
              <?= e($c['clinic_hospital']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-md-2">
        <select name="status" class="form-select">
          <option value="">All Statuses</option>
          <option value="active" <?= $filterStatus === 'active' ? 'selected' : '' ?>>Active Only</option>
          <option value="inactive" <?= $filterStatus === 'inactive' ? 'selected' : '' ?>>Inactive Only</option>
        </select>
      </div>

      <div class="col-md-1 d-flex gap-1">
        <button type="submit" class="btn btn-primary w-100" title="Apply Filters">
          <i class="bi bi-funnel"></i>
        </button>
        <?php if ($search !== '' || $filterSpecialization !== '' || $filterClinic !== '' || $filterStatus !== ''): ?>
          <a href="<?= ADMIN_URL ?>/doctors/index.php" class="btn btn-outline-secondary" title="Reset Filters">
            <i class="bi bi-x-lg"></i>
          </a>
        <?php endif; ?>
      </div>
    </form>
  </div>
</div>

<!-- Doctors Master Table -->
<div class="admin-card">
  <div class="admin-card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0 small">
        <thead class="table-light">
          <tr>
            <th style="width: 85px;">Code</th>
            <th>Doctor Name</th>
            <th>Specialization</th>
            <th>Clinic / Affiliation</th>
            <th>Contact</th>
            <th class="text-center" style="width: 110px;">Volume</th>
            <th class="text-end" style="width: 100px;">Financials</th>
            <th class="text-center" style="width: 90px;">Agreements</th>
            <th class="text-center" style="width: 70px;">Status</th>
            <th class="text-end text-nowrap pe-3" style="width: 150px;">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($doctors)): ?>
            <tr>
              <td colspan="10" class="text-center py-5 text-muted">
                <i class="bi bi-people display-6 d-block mb-2 text-secondary"></i>
                No doctor profiles matching your criteria.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($doctors as $doc): ?>
              <tr>
                <td>
                  <span class="badge bg-light text-dark border font-monospace fw-bold">
                    <?= e($doc['doctor_code']) ?>
                  </span>
                  <div class="text-muted" style="font-size: 0.7rem;">#<?= $doc['id'] ?></div>
                </td>
                <td>
                  <a href="<?= ADMIN_URL ?>/doctors/view.php?id=<?= $doc['id'] ?>" class="fw-bold text-decoration-none text-dark d-block">
                    <?= e($doc['name']) ?>
                  </a>
                  <div class="text-secondary" style="font-size: 0.75rem;">
                    <?= e($doc['qualification'] ?: ($doc['degree'] ?: 'Degree Unspecified')) ?>
                  </div>
                </td>
                <td>
                  <div class="fw-semibold text-dark">
                    <?= e($doc['specialization'] ?: 'General Practice') ?>
                  </div>
                  <?php if (!empty($doc['medical_registration_number'])): ?>
                    <div class="badge bg-light text-secondary border font-monospace" style="font-size: 0.68rem;">
                      <?= e($doc['medical_registration_number']) ?>
                    </div>
                  <?php endif; ?>
                </td>
                <td>
                  <div class="text-dark fw-medium text-truncate" style="max-width: 135px;" title="<?= e($doc['clinic_hospital'] ?: 'Independent') ?>">
                    <i class="bi bi-building me-1 text-secondary"></i>
                    <?= e($doc['clinic_hospital'] ?: 'Independent') ?>
                  </div>
                  <?php if (!empty($doc['address'])): ?>
                    <div class="text-muted text-truncate" style="max-width: 135px; font-size: 0.72rem;" title="<?= e($doc['address']) ?>">
                      <?= e($doc['address']) ?>
                    </div>
                  <?php endif; ?>
                </td>
                <td>
                  <div style="font-size: 0.78rem;">
                    <?php if (!empty($doc['phone'])): ?>
                      <div><i class="bi bi-telephone text-muted me-1"></i><?= e($doc['phone']) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($doc['email'])): ?>
                      <div class="text-truncate" style="max-width: 140px;" title="<?= e($doc['email']) ?>">
                        <i class="bi bi-envelope text-muted me-1"></i><span class="text-muted"><?= e($doc['email']) ?></span>
                      </div>
                    <?php endif; ?>
                    <?php if (empty($doc['phone']) && empty($doc['email'])): ?>
                      <span class="text-muted">N/A</span>
                    <?php endif; ?>
                  </div>
                </td>
                <td class="text-center">
                  <div class="d-flex flex-column align-items-center gap-1">
                    <span class="badge bg-primary-subtle text-primary-emphasis" style="font-size: 0.7rem;" title="Associated Patients">
                      <i class="bi bi-people me-1"></i><?= (int)$doc['patient_count'] ?> Pts
                    </span>
                    <span class="badge bg-info-subtle text-info-emphasis" style="font-size: 0.7rem;" title="Associated Orders">
                      <i class="bi bi-collection me-1"></i><?= (int)$doc['order_count'] ?> Orders
                    </span>
                  </div>
                </td>
                <td class="text-end">
                  <div class="fw-semibold text-dark small" title="Invoiced Amount">
                    <?= format_currency($doc['total_invoiced']) ?>
                  </div>
                  <div class="text-success small" title="Realized Collections">
                    <i class="bi bi-cash me-1"></i><?= format_currency($doc['total_collected']) ?>
                  </div>
                </td>
                <td class="text-center">
                  <?php if ((int)$doc['active_agreements_count'] > 0): ?>
                    <span class="badge bg-success-subtle text-success-emphasis">
                      <i class="bi bi-file-check me-1"></i><?= $doc['active_agreements_count'] ?> Active
                    </span>
                  <?php else: ?>
                    <span class="badge bg-light text-muted border">None</span>
                  <?php endif; ?>
                  <?php if ((int)$doc['pending_ledger_count'] > 0): ?>
                    <div class="mt-1">
                      <span class="badge bg-warning-subtle text-warning-emphasis" style="font-size:0.7rem;">
                        <?= $doc['pending_ledger_count'] ?> Pending Vouchers
                      </span>
                    </div>
                  <?php endif; ?>
                </td>
                <td class="text-center">
                  <span class="badge <?= $doc['is_active'] ? 'bg-success-subtle text-success-emphasis' : 'bg-danger-subtle text-danger-emphasis' ?>">
                    <?= $doc['is_active'] ? 'Active' : 'Inactive' ?>
                  </span>
                </td>
                <td class="text-end text-nowrap pe-3">
                  <div class="d-inline-flex align-items-center justify-content-end gap-1">
                    <!-- View Button -->
                    <a href="<?= ADMIN_URL ?>/doctors/view.php?id=<?= $doc['id'] ?>" 
                       class="btn btn-sm btn-outline-primary btn-action-icon" 
                       title="View Profile & Statements">
                      <i class="bi bi-eye"></i>
                    </a>

                    <!-- Edit Button -->
                    <?php if (Auth::can('doctors.edit')): ?>
                    <a href="<?= ADMIN_URL ?>/doctors/create_edit.php?id=<?= $doc['id'] ?>" 
                       class="btn btn-sm btn-outline-secondary btn-action-icon" 
                       title="Edit Doctor Profile">
                      <i class="bi bi-pencil"></i>
                    </a>
                    <?php endif; ?>

                    <!-- Delete Button -->
                    <?php if (Auth::can('doctors.edit')): ?>
                    <form method="POST" action="<?= ADMIN_URL ?>/doctors/index.php" class="d-inline m-0 p-0" 
                          onsubmit="return confirm('Delete or deactivate doctor <?= e(addslashes($doc['name'])) ?> (<?= e($doc['doctor_code']) ?>)?');">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="delete_doctor">
                      <input type="hidden" name="doctor_id" value="<?= $doc['id'] ?>">
                      <button type="submit" 
                              class="btn btn-sm btn-outline-danger btn-action-icon" 
                              title="Delete / Deactivate Doctor">
                        <i class="bi bi-trash"></i>
                      </button>
                    </form>
                    <?php endif; ?>

                    <!-- More Actions Dropdown -->
                    <?php if (Auth::can('reconciliation.create')): ?>
                    <div class="dropdown d-inline-block">
                      <button class="btn btn-sm btn-light border btn-action-icon" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="More Options">
                        <i class="bi bi-three-dots-vertical"></i>
                      </button>
                      <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                        <li>
                          <a class="dropdown-item small py-2" href="<?= ADMIN_URL ?>/doctors/agreement_create.php?doctor_id=<?= $doc['id'] ?>">
                            <i class="bi bi-file-earmark-plus text-info me-2"></i> New Service Agreement
                          </a>
                        </li>
                        <li>
                          <a class="dropdown-item small py-2" href="<?= ADMIN_URL ?>/doctors/reconciliation_create.php?doctor_id=<?= $doc['id'] ?>">
                            <i class="bi bi-journal-plus text-success me-2"></i> Record Reconciliation Item
                          </a>
                        </li>
                      </ul>
                    </div>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
      <div class="p-3 border-top d-flex justify-content-between align-items-center">
        <div class="small text-muted">
          Showing <?= $offset + 1 ?> to <?= min($totalMatching, $offset + $perPage) ?> of <?= $totalMatching ?> doctors
        </div>
        <nav aria-label="Doctors pagination">
          <ul class="pagination pagination-sm mb-0">
            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
              <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">Previous</a>
            </li>
            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
              <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $p])) ?>"><?= $p ?></a>
              </li>
            <?php endfor; ?>
            <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
              <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">Next</a>
            </li>
          </ul>
        </nav>
      </div>
    <?php endif; ?>

  </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>

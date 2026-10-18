<?php
/**
 * LabMaster - Professional Service Agreements & Payment Policy Management
 * Lawful arrangements review, approval workflow, and compliance auditing
 */

declare(strict_types=1);

$pageTitle = 'Service Agreements & Policies';
$activeNav = 'doctors';

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';

Auth::requirePermission('reconciliation.view');

// Handle Review / Status Action BEFORE sending headers
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? '';
    $agrId = (int)($_POST['agreement_id'] ?? 0);
    $reviewNotes = trim($_POST['review_notes'] ?? '');
    $reviewerId = Auth::id();

    if ($agrId > 0 && Auth::can('agreements.review')) {
        $targetAgr = Database::fetch("SELECT * FROM `doctor_service_agreements` WHERE `id` = ?", [$agrId]);
        if ($targetAgr) {
            if ($action === 'approve') {
                Database::execute(
                    "UPDATE `doctor_service_agreements` 
                     SET `approval_status` = 'Approved', `reviewer_id` = ?, `reviewed_at` = NOW(), `review_notes` = ?
                     WHERE `id` = ?",
                    [$reviewerId, $reviewNotes ?: 'Service agreement reviewed and approved in compliance with clinical ethics.', $agrId]
                );
                log_audit('Service Agreement Approved', 'Reconciliation', $targetAgr['agreement_code'], "Authorized payment policy and active status for {$targetAgr['agreement_code']}.");
                set_flash('success', "Agreement {$targetAgr['agreement_code']} has been approved and activated.");
            } elseif ($action === 'reject') {
                if (empty($reviewNotes)) {
                    set_flash('danger', 'Please provide a clear reason or note for rejecting this agreement.');
                } else {
                    Database::execute(
                        "UPDATE `doctor_service_agreements` 
                         SET `approval_status` = 'Rejected', `reviewer_id` = ?, `reviewed_at` = NOW(), `review_notes` = ?
                         WHERE `id` = ?",
                        [$reviewerId, $reviewNotes, $agrId]
                    );
                    log_audit('Service Agreement Rejected', 'Reconciliation', $targetAgr['agreement_code'], "Rejected agreement {$targetAgr['agreement_code']}: {$reviewNotes}");
                    set_flash('warning', "Agreement {$targetAgr['agreement_code']} has been rejected.");
                }
            } elseif ($action === 'terminate') {
                Database::execute(
                    "UPDATE `doctor_service_agreements` 
                     SET `approval_status` = 'Terminated', `notes` = CONCAT(COALESCE(`notes`,''), ' | Terminated: ', ?)
                     WHERE `id` = ?",
                    [$reviewNotes ?: 'Terminated by lab management', $agrId]
                );
                log_audit('Service Agreement Terminated', 'Reconciliation', $targetAgr['agreement_code'], "Terminated agreement {$targetAgr['agreement_code']}.");
                set_flash('info', "Agreement {$targetAgr['agreement_code']} has been terminated.");
            }
        }
    }
    header('Location: ' . ADMIN_URL . '/doctors/agreements.php');
    exit;
}

require_once dirname(__DIR__) . '/includes/header.php';

// Filters & Search
$search = trim($_GET['search'] ?? '');
$filterStatus = trim($_GET['status'] ?? '');
$filterType = trim($_GET['type'] ?? '');
$filterDoctorId = (int)($_GET['doctor_id'] ?? 0);

$whereClauses = ['1=1'];
$params = [];

if ($search !== '') {
    $whereClauses[] = "(a.agreement_code LIKE ? OR a.counterparty_name LIKE ? OR d.name LIKE ? OR a.service_description LIKE ?)";
    $like = '%' . $search . '%';
    $params = array_merge($params, [$like, $like, $like, $like]);
}

if ($filterStatus !== '') {
    $whereClauses[] = "a.approval_status = ?";
    $params[] = $filterStatus;
}

if ($filterType !== '') {
    $whereClauses[] = "a.agreement_type = ?";
    $params[] = $filterType;
}

if ($filterDoctorId > 0) {
    $whereClauses[] = "a.doctor_id = ?";
    $params[] = $filterDoctorId;
}

$whereSql = implode(' AND ', $whereClauses);

$agreements = Database::fetchAll(
    "SELECT a.*, d.name as doctor_name, d.doctor_code, d.specialization,
            u.full_name as creator_name, r.full_name as reviewer_name,
            (SELECT COUNT(*) FROM `financial_reconciliation_ledger` l WHERE l.agreement_id = a.id) as transaction_count,
            (SELECT COALESCE(SUM(l.amount), 0) FROM `financial_reconciliation_ledger` l WHERE l.agreement_id = a.id AND l.payment_status = 'Paid') as total_disbursed
     FROM `doctor_service_agreements` a
     JOIN `doctors` d ON a.doctor_id = d.id
     LEFT JOIN `users` u ON a.created_by = u.id
     LEFT JOIN `users` r ON a.reviewer_id = r.id
     WHERE {$whereSql}
     ORDER BY a.id DESC",
    $params
);

$allDoctors = Database::fetchAll("SELECT id, name, doctor_code FROM `doctors` ORDER BY name ASC");
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
  <div>
    <h2 class="h4 fw-bold mb-1">
      <i class="bi bi-file-earmark-text text-primary me-2"></i>Service Agreements & Payment Policies
    </h2>
    <p class="text-muted small mb-0">Configurable professional arrangements, consultation policies, and regulatory review lifecycle</p>
  </div>
  <div class="d-flex flex-wrap gap-2">
    <?php if (Auth::can('reconciliation.create')): ?>
      <a href="<?= ADMIN_URL ?>/doctors/agreement_create.php" class="btn btn-primary-lm d-inline-flex align-items-center gap-2">
        <i class="bi bi-plus-circle-fill"></i> New Service Agreement
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

<!-- Legal Compliance Banner -->
<div class="alert alert-light border border-info border-opacity-50 shadow-sm p-3 mb-4 rounded-3 d-flex align-items-start gap-3">
  <i class="bi bi-shield-check text-info fs-4 mt-1"></i>
  <div class="small text-secondary">
    <strong class="text-dark">Regulatory Compliance Safeguard (Section 3):</strong>
    All payments to physicians must be justified by bona fide, documented medical services and an approved agreement.
    Percentage-based referral calculations are strictly blocked across the application. Authorized review (<span class="badge bg-light text-dark border">agreements.review</span>) is required before any agreement can be marked <em>Approved</em> for ledger disbursement.
  </div>
</div>

<!-- Filters Bar -->
<div class="admin-card mb-4">
  <div class="admin-card-body p-3">
    <form method="GET" action="" class="row g-2 align-items-center">
      <div class="col-md-4">
        <div class="input-group">
          <span class="input-group-text bg-white text-muted border-end-0"><i class="bi bi-search"></i></span>
          <input type="text" name="search" class="form-control border-start-0" 
                 placeholder="Search by code, doctor, counterparty, description..." 
                 value="<?= e($search) ?>">
        </div>
      </div>

      <div class="col-md-2">
        <select name="status" class="form-select">
          <option value="">All Statuses</option>
          <option value="Draft" <?= $filterStatus === 'Draft' ? 'selected' : '' ?>>Draft</option>
          <option value="Under Review" <?= $filterStatus === 'Under Review' ? 'selected' : '' ?>>Under Review</option>
          <option value="Approved" <?= $filterStatus === 'Approved' ? 'selected' : '' ?>>Approved (Active)</option>
          <option value="Rejected" <?= $filterStatus === 'Rejected' ? 'selected' : '' ?>>Rejected</option>
          <option value="Terminated" <?= $filterStatus === 'Terminated' ? 'selected' : '' ?>>Terminated</option>
        </select>
      </div>

      <div class="col-md-3">
        <select name="doctor_id" class="form-select">
          <option value="">All Counterparty Doctors</option>
          <?php foreach ($allDoctors as $doc): ?>
            <option value="<?= $doc['id'] ?>" <?= $filterDoctorId === (int)$doc['id'] ? 'selected' : '' ?>>
              <?= e($doc['name']) ?> (<?= e($doc['doctor_code']) ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-md-2">
        <select name="type" class="form-select">
          <option value="">All Agreement Types</option>
          <option value="Retainership / Consultant Pathologist" <?= $filterType === 'Retainership / Consultant Pathologist' ? 'selected' : '' ?>>Retainership</option>
          <option value="Consulting & Second Opinion" <?= $filterType === 'Consulting & Second Opinion' ? 'selected' : '' ?>>Consulting & Second Opinion</option>
          <option value="Preventive Camp Supervision" <?= $filterType === 'Preventive Camp Supervision' ? 'selected' : '' ?>>Camp Supervision</option>
          <option value="Institutional Service Level Agreement" <?= $filterType === 'Institutional Service Level Agreement' ? 'selected' : '' ?>>Institutional SLA</option>
        </select>
      </div>

      <div class="col-md-1 d-flex gap-1">
        <button type="submit" class="btn btn-primary w-100" title="Apply Filter">
          <i class="bi bi-funnel"></i>
        </button>
        <?php if ($search !== '' || $filterStatus !== '' || $filterType !== '' || $filterDoctorId > 0): ?>
          <a href="<?= ADMIN_URL ?>/doctors/agreements.php" class="btn btn-outline-secondary" title="Reset Filters">
            <i class="bi bi-x-lg"></i>
          </a>
        <?php endif; ?>
      </div>
    </form>
  </div>
</div>

<!-- Agreements List -->
<div class="admin-card">
  <div class="admin-card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>Agreement Code</th>
            <th>Counterparty / Doctor</th>
            <th>Agreement Type & Scope</th>
            <th>Payment Basis & Rate</th>
            <th>Effective Dates</th>
            <th>Document</th>
            <th>Approval Status</th>
            <th>Reviewer Details</th>
            <th class="text-end text-nowrap pe-3" style="width: 160px;">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($agreements)): ?>
            <tr>
              <td colspan="9" class="text-center py-5 text-muted">
                <i class="bi bi-folder-x display-6 d-block mb-2 text-secondary"></i>
                No service agreements matching the specified criteria.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($agreements as $agr): ?>
              <tr>
                <td>
                  <span class="badge bg-light text-dark border font-monospace fw-bold">
                    <?= e($agr['agreement_code']) ?>
                  </span>
                  <div class="text-muted small mt-1">ID #<?= $agr['id'] ?></div>
                </td>
                <td>
                  <a href="<?= ADMIN_URL ?>/doctors/view.php?id=<?= $agr['doctor_id'] ?>" class="fw-bold text-dark text-decoration-none">
                    <?= e($agr['doctor_name']) ?>
                  </a>
                  <div class="text-secondary small"><?= e($agr['counterparty_name']) ?></div>
                </td>
                <td>
                  <div class="fw-semibold text-dark small"><?= e($agr['agreement_type']) ?></div>
                  <div class="text-muted small text-truncate" style="max-width: 260px;" title="<?= e($agr['service_description']) ?>">
                    <?= e($agr['service_description']) ?>
                  </div>
                </td>
                <td>
                  <div class="fw-bold text-primary"><?= format_currency($agr['rate_amount']) ?></div>
                  <div class="text-secondary small"><?= e($agr['payment_basis']) ?></div>
                </td>
                <td>
                  <div class="small">From: <strong><?= format_date($agr['effective_date']) ?></strong></div>
                  <div class="small text-muted">
                    To: <?= $agr['expiry_date'] ? format_date($agr['expiry_date']) : '<span class="badge bg-light text-dark border">Indefinite</span>' ?>
                  </div>
                </td>
                <td>
                  <?php if (!empty($agr['supporting_document_path'])): ?>
                    <a href="<?= BASE_URL ?>/<?= e($agr['supporting_document_path']) ?>" target="_blank" class="btn btn-sm btn-light border text-primary btn-action-icon" title="View Uploaded Contract PDF">
                      <i class="bi bi-file-earmark-pdf"></i>
                    </a>
                  <?php else: ?>
                    <span class="text-muted small">None</span>
                  <?php endif; ?>
                </td>
                <td>
                  <?php
                  $badgeClass = match($agr['approval_status']) {
                      'Approved' => 'bg-success',
                      'Under Review' => 'bg-warning text-dark',
                      'Draft' => 'bg-secondary',
                      'Rejected' => 'bg-danger',
                      'Terminated' => 'bg-dark',
                      default => 'bg-light text-dark'
                  };
                  ?>
                  <span class="badge <?= $badgeClass ?>"><?= e($agr['approval_status']) ?></span>
                </td>
                <td>
                  <?php if (!empty($agr['reviewer_name'])): ?>
                    <div class="small fw-semibold text-dark"><?= e($agr['reviewer_name']) ?></div>
                    <div class="text-muted" style="font-size:0.7rem;"><?= format_date($agr['reviewed_at']) ?></div>
                  <?php else: ?>
                    <span class="text-muted small">Pending Review</span>
                  <?php endif; ?>
                </td>
                <td class="text-end text-nowrap pe-3">
                  <div class="d-inline-flex align-items-center justify-content-end gap-1">
                    <!-- View Agreement Doctor Profile -->
                    <a href="<?= ADMIN_URL ?>/doctors/view.php?id=<?= $agr['doctor_id'] ?>#agreements" class="btn btn-sm btn-outline-primary btn-action-icon" title="View Agreement & Doctor History">
                      <i class="bi bi-eye"></i>
                    </a>

                    <!-- Edit Agreement -->
                    <?php if (Auth::can('reconciliation.create') && in_array($agr['approval_status'], ['Draft', 'Under Review'])): ?>
                      <a href="<?= ADMIN_URL ?>/doctors/agreement_create.php?id=<?= $agr['id'] ?>" class="btn btn-sm btn-outline-secondary btn-action-icon" title="Edit Agreement">
                        <i class="bi bi-pencil"></i>
                      </a>
                    <?php endif; ?>

                    <!-- Delete / Reject / Terminate -->
                    <?php if (Auth::can('agreements.review') && in_array($agr['approval_status'], ['Draft', 'Under Review'])): ?>
                      <button class="btn btn-sm btn-outline-danger btn-action-icon" type="button" 
                              data-bs-toggle="modal" data-bs-target="#reviewModal" 
                              data-id="<?= $agr['id'] ?>" data-code="<?= e($agr['agreement_code']) ?>" data-action="reject"
                              title="Reject / Delete Agreement">
                        <i class="bi bi-trash"></i>
                      </button>
                    <?php elseif (Auth::can('agreements.review') && $agr['approval_status'] === 'Approved'): ?>
                      <button class="btn btn-sm btn-outline-dark btn-action-icon" type="button" 
                              data-bs-toggle="modal" data-bs-target="#reviewModal" 
                              data-id="<?= $agr['id'] ?>" data-code="<?= e($agr['agreement_code']) ?>" data-action="terminate"
                              title="Terminate Agreement">
                        <i class="bi bi-slash-circle"></i>
                      </button>
                    <?php endif; ?>

                    <!-- More Options Dropdown -->
                    <div class="dropdown d-inline-block">
                      <button class="btn btn-sm btn-light border btn-action-icon" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="More Options">
                        <i class="bi bi-three-dots-vertical"></i>
                      </button>
                      <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                        <?php if (Auth::can('agreements.review') && in_array($agr['approval_status'], ['Draft', 'Under Review'])): ?>
                          <li>
                            <button class="dropdown-item text-success" type="button" 
                                    data-bs-toggle="modal" data-bs-target="#reviewModal" 
                                    data-id="<?= $agr['id'] ?>" data-code="<?= e($agr['agreement_code']) ?>" data-action="approve">
                              <i class="bi bi-check-circle-fill me-2"></i> Authorize & Approve
                            </button>
                          </li>
                        <?php endif; ?>

                        <?php if ($agr['approval_status'] === 'Approved' && Auth::can('reconciliation.create')): ?>
                          <li>
                            <a class="dropdown-item text-success" href="<?= ADMIN_URL ?>/doctors/reconciliation_create.php?agreement_id=<?= $agr['id'] ?>&doctor_id=<?= $agr['doctor_id'] ?>">
                              <i class="bi bi-wallet-fill me-2"></i> Record Reconciliation
                            </a>
                          </li>
                        <?php endif; ?>

                        <li>
                          <a class="dropdown-item" href="<?= ADMIN_URL ?>/doctors/view.php?id=<?= $agr['doctor_id'] ?>#agreements">
                            <i class="bi bi-person-badge text-primary me-2"></i> Doctor History
                          </a>
                        </li>
                      </ul>
                    </div>
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

<!-- Review & Authorization Action Modal -->
<div class="modal fade" id="reviewModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" action="">
        <?= csrf_field() ?>
        <input type="hidden" name="agreement_id" id="modalAgrId" value="">
        <input type="hidden" name="action" id="modalAction" value="approve">

        <div class="modal-header">
          <h5 class="modal-title fw-bold" id="modalTitle">Review Service Agreement</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="alert alert-info py-2 small" id="modalPrompt">
            Reviewing agreement policy.
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold">Reviewer Notes & Authorization Rationale <span class="text-danger">*</span></label>
            <textarea name="review_notes" class="form-control" rows="3" required
                      placeholder="Specify verification of medical council credentials, documented service arrangement, and compliance justification..."></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary" id="modalSubmitBtn">Confirm Authorization</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
const reviewModal = document.getElementById('reviewModal');
if (reviewModal) {
  reviewModal.addEventListener('show.bs.modal', function(event) {
    const button = event.relatedTarget;
    const agrId = button.getAttribute('data-id');
    const agrCode = button.getAttribute('data-code');
    const action = button.getAttribute('data-action');

    document.getElementById('modalAgrId').value = agrId;
    document.getElementById('modalAction').value = action;

    const modalTitle = document.getElementById('modalTitle');
    const modalPrompt = document.getElementById('modalPrompt');
    const modalSubmitBtn = document.getElementById('modalSubmitBtn');

    if (action === 'approve') {
      modalTitle.innerText = 'Authorize & Approve Agreement (' + agrCode + ')';
      modalPrompt.innerHTML = 'You are approving <strong>' + agrCode + '</strong>. This will activate the payment policy and allow reconciliation vouchers to be drafted and disbursed against this contract.';
      modalSubmitBtn.className = 'btn btn-success';
      modalSubmitBtn.innerText = 'Approve & Activate Agreement';
    } else if (action === 'reject') {
      modalTitle.innerText = 'Reject Service Agreement (' + agrCode + ')';
      modalPrompt.innerHTML = 'You are rejecting <strong>' + agrCode + '</strong>. Rejection blocks any financial transactions from being associated with this arrangement. A justification is required.';
      modalSubmitBtn.className = 'btn btn-danger';
      modalSubmitBtn.innerText = 'Reject Agreement';
    } else if (action === 'terminate') {
      modalTitle.innerText = 'Terminate Service Agreement (' + agrCode + ')';
      modalPrompt.innerHTML = 'You are terminating <strong>' + agrCode + '</strong>. No further vouchers can be approved or disbursed under this agreement.';
      modalSubmitBtn.className = 'btn btn-dark';
      modalSubmitBtn.innerText = 'Terminate Agreement';
    }
  });
}
</script>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>

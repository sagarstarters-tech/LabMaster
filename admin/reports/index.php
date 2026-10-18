<?php
/**
 * LabMaster - Pathology Reports Directory & Approvals
 */

declare(strict_types=1);

$pageTitle = 'Pathology Reports';
$activeNav = 'reports';

require_once dirname(__DIR__) . '/includes/auth_guard.php';
Auth::requirePermission('reports.view');

$statusFilter = trim($_GET['status'] ?? '');
$search = trim($_GET['search'] ?? '');

$sql = "SELECT r.*, p.full_name as patient_name, p.patient_id as patient_code, p.phone, p.gender, p.age,
               o.accession_number, o.order_date,
               u.full_name as pathologist_name,
               (SELECT COUNT(*) FROM `whatsapp_logs` wl WHERE wl.report_id = r.id AND wl.status IN ('Sent', 'Delivered')) as wa_sent
        FROM `reports` r
        JOIN `patients` p ON r.patient_id = p.id
        JOIN `test_orders` o ON r.order_id = o.id
        LEFT JOIN `users` u ON r.approved_by = u.id
        WHERE 1=1";
$params = [];

if (!empty($statusFilter)) {
    $sql .= " AND r.report_status = ?";
    $params[] = $statusFilter;
}
if (!empty($search)) {
    $sql .= " AND (r.report_number LIKE ? OR p.full_name LIKE ? OR p.patient_id LIKE ? OR o.accession_number LIKE ?)";
    $like = "%{$search}%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_report') {
    require_csrf();
    Auth::requirePermission('reports.amend');
    $repId = (int)($_POST['report_id'] ?? 0);
    $rep = Database::fetch("SELECT * FROM `reports` WHERE `id` = ?", [$repId]);
    if ($rep) {
        if ($rep['report_status'] === 'Approved') {
            set_flash('danger', 'Approved reports cannot be deleted for clinical audit integrity.');
        } else {
            Database::execute("DELETE FROM `reports` WHERE `id` = ?", [$repId]);
            log_audit('Report Deleted', 'Reports', $rep['report_number'], "Deleted draft report {$rep['report_number']}.");
            set_flash('success', "Report record \"{$rep['report_number']}\" deleted successfully.");
        }
    } else {
        set_flash('danger', 'Report record not found.');
    }
    header('Location: ' . ADMIN_URL . '/reports/index.php');
    exit;
}

$reports = Database::fetchAll($sql, $params);

require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
  <div>
    <h2 class="h4 fw-bold mb-1">Pathology Clinical Reports</h2>
    <p class="text-muted small mb-0">Review, digitally sign, approve, print, and securely dispatch medical reports</p>
  </div>
</div>

<!-- Status Filter Pills -->
<div class="d-flex flex-wrap gap-2 mb-3">
  <a href="?status=" class="btn btn-sm <?= empty($statusFilter) ? 'btn-primary-lm' : 'btn-outline-secondary' ?>">
    All Reports (<?= count($reports) ?>)
  </a>
  <a href="?status=Pending Approval" class="btn btn-sm <?= $statusFilter === 'Pending Approval' ? 'btn-danger text-white' : 'btn-outline-secondary' ?>">
    <i class="bi bi-shield-exclamation me-1"></i> Pending Approval
  </a>
  <a href="?status=Approved" class="btn btn-sm <?= $statusFilter === 'Approved' ? 'btn-success text-white' : 'btn-outline-secondary' ?>">
    <i class="bi bi-check-circle me-1"></i> Approved & Signed
  </a>
  <a href="?status=Draft" class="btn btn-sm <?= $statusFilter === 'Draft' ? 'btn-warning text-dark' : 'btn-outline-secondary' ?>">
    Drafts
  </a>
  <a href="?status=Amended" class="btn btn-sm <?= $statusFilter === 'Amended' ? 'btn-info text-dark' : 'btn-outline-secondary' ?>">
    Amended Versions
  </a>
</div>

<!-- Reports Table -->
<div class="admin-card">
  <div class="admin-card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>Report Number</th>
            <th>Patient Details</th>
            <th>Accession No</th>
            <th>Version</th>
            <th>Status</th>
            <th>Pathologist / Signatory</th>
            <th>Approved At</th>
            <th class="text-end text-nowrap pe-3" style="width: 170px;">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($reports)): ?>
            <tr><td colspan="8" class="text-center text-muted py-5">No pathology reports found.</td></tr>
          <?php else: ?>
            <?php foreach ($reports as $rep): ?>
              <tr>
                <td>
                  <a href="<?= ADMIN_URL ?>/reports/view.php?id=<?= $rep['id'] ?>" class="fw-bold font-monospace text-primary text-decoration-none">
                    <?= e($rep['report_number']) ?>
                  </a>
                </td>
                <td>
                  <div class="fw-bold text-dark"><?= e($rep['patient_name']) ?></div>
                  <div class="small text-muted font-monospace"><?= e($rep['patient_code']) ?> (<?= $rep['age'] ?> Y / <?= e($rep['gender']) ?>)</div>
                </td>
                <td>
                  <span class="badge bg-light text-dark border font-monospace"><?= e($rep['accession_number']) ?></span>
                </td>
                <td>
                  <span class="badge bg-secondary-subtle text-secondary-emphasis">v<?= $rep['version_number'] ?></span>
                </td>
                <td>
                  <?php if ($rep['report_status'] === 'Approved'): ?>
                    <span class="badge bg-success-subtle text-success-emphasis border border-success">
                      <i class="bi bi-patch-check-fill me-1"></i> Approved
                    </span>
                  <?php elseif ($rep['report_status'] === 'Pending Approval'): ?>
                    <span class="badge bg-danger pulse-alert">
                      <i class="bi bi-clock-history me-1"></i> Pending Approval
                    </span>
                  <?php elseif ($rep['report_status'] === 'Amended'): ?>
                    <span class="badge bg-info text-dark">Amended</span>
                  <?php else: ?>
                    <span class="badge bg-warning text-dark">Draft</span>
                  <?php endif; ?>
                </td>
                <td>
                  <?= e($rep['pathologist_name'] ?: 'Awaiting Review') ?>
                </td>
                <td class="small text-muted">
                  <?= format_date($rep['approved_at'] ?: $rep['created_at'], true) ?>
                </td>
                <td class="text-end text-nowrap pe-3">
                  <div class="d-inline-flex align-items-center justify-content-end gap-1">
                    <a href="<?= ADMIN_URL ?>/reports/view.php?id=<?= $rep['id'] ?>" class="btn btn-sm btn-outline-primary btn-action-icon" title="View Full Report">
                      <i class="bi bi-eye"></i>
                    </a>
                    <?php if (Auth::can('reports.review') && $rep['report_status'] !== 'Approved'): ?>
                      <a href="<?= ADMIN_URL ?>/reports/review.php?id=<?= $rep['id'] ?>" class="btn btn-sm btn-outline-warning btn-action-icon" title="Review & Sign Report">
                        <i class="bi bi-pencil-square"></i>
                      </a>
                    <?php endif; ?>
                    <?php if (Auth::can('reports.print')): ?>
                    <a href="<?= ADMIN_URL ?>/reports/print.php?id=<?= $rep['id'] ?>" target="_blank" class="btn btn-sm btn-outline-secondary btn-action-icon" title="Direct Print">
                      <i class="bi bi-printer"></i>
                    </a>
                    <a href="<?= ADMIN_URL ?>/reports/pdf.php?id=<?= $rep['id'] ?>" target="_blank" class="btn btn-sm btn-outline-danger btn-action-icon" title="Download PDF">
                      <i class="bi bi-file-earmark-pdf"></i>
                    </a>
                    <?php endif; ?>
                    <?php if (Auth::can('reports.whatsapp') && $rep['report_status'] === 'Approved'): ?>
                      <a href="<?= ADMIN_URL ?>/reports/share_whatsapp.php?id=<?= $rep['id'] ?>" class="btn btn-sm btn-outline-success btn-action-icon" title="Share via WhatsApp">
                        <i class="bi bi-whatsapp"></i>
                      </a>
                    <?php endif; ?>
                    <?php if ($rep['report_status'] !== 'Approved' && Auth::can('reports.amend')): ?>
                      <form method="POST" action="<?= ADMIN_URL ?>/reports/index.php" class="d-inline m-0 p-0" onsubmit="return confirm('Are you sure you want to delete this unapproved report?');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete_report">
                        <input type="hidden" name="report_id" value="<?= $rep['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger btn-action-icon" title="Delete Draft Report">
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

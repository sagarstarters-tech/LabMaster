<?php
/**
 * LabMaster - WhatsApp PDF Report Sharing Workflow
 */

declare(strict_types=1);

$reportId = (int)($_GET['id'] ?? 0);
$pageTitle = 'Share Report via WhatsApp';
$activeNav = 'reports';

require_once dirname(__DIR__) . '/includes/auth_guard.php';
require_once dirname(dirname(__DIR__)) . '/includes/whatsapp_service.php';

Auth::requirePermission('reports.whatsapp');

$report = Database::fetch(
    "SELECT r.*, p.full_name as patient_name, p.phone as patient_phone, p.patient_id as patient_code,
            o.accession_number
     FROM `reports` r
     JOIN `patients` p ON r.patient_id = p.id
     JOIN `test_orders` o ON r.order_id = o.id
     WHERE r.id = ?",
    [$reportId]
);

if (!$report) {
    set_flash('danger', 'Report record not found.');
    header('Location: ' . ADMIN_URL . '/reports/index.php');
    exit;
}

if ($report['report_status'] !== 'Approved') {
    set_flash('warning', 'Only approved and signed pathology reports can be shared with patients.');
    header('Location: ' . ADMIN_URL . '/reports/view.php?id=' . $reportId);
    exit;
}

$recipientPhone = $report['patient_phone'];
$dispatchResult = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $recipientPhone = trim($_POST['recipient_phone'] ?? $report['patient_phone']);

    if (empty($recipientPhone)) {
        set_flash('danger', 'Please specify a valid patient WhatsApp phone number.');
    } else {
        $dispatchResult = WhatsAppService::sendReportNotification(
            $recipientPhone,
            $report['patient_name'],
            $report['report_number'],
            $report['verification_token'],
            (int)$report['patient_id'],
            $reportId
        );

        if ($dispatchResult['success']) {
            log_audit('WhatsApp Report Shared', 'Reports', $report['report_number'], "Dispatched report {$report['report_number']} to {$recipientPhone}.");
            set_flash('success', "Report notification dispatched to {$recipientPhone}!");
        } else {
            set_flash('danger', "WhatsApp dispatch failed: " . ($dispatchResult['api_response'] ?? 'Unknown error'));
        }
    }
}

// Fetch past WhatsApp logs for this report
$pastLogs = Database::fetchAll(
    "SELECT wl.*, u.full_name as sender_name 
     FROM `whatsapp_logs` wl 
     LEFT JOIN `users` u ON wl.sent_by = u.id 
     WHERE wl.report_id = ? 
     ORDER BY wl.id DESC",
    [$reportId]
);

require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="row justify-content-center">
  <div class="col-lg-8">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <div>
        <h2 class="h4 fw-bold mb-1">Share Pathology Report on WhatsApp</h2>
        <p class="text-muted small mb-0">Direct dispatch of verified clinical report to patient's registered mobile number</p>
      </div>
      <a href="<?= ADMIN_URL ?>/reports/view.php?id=<?= $reportId ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i> Back to Report View
      </a>
    </div>

    <!-- Report Details Summary Card -->
    <div class="admin-card mb-4 bg-light">
      <div class="admin-card-body p-3">
        <div class="row g-2 small">
          <div class="col-sm-6">
            <span class="text-muted">Patient:</span> <strong class="text-dark ms-1"><?= e($report['patient_name']) ?></strong> (<?= e($report['patient_code']) ?>)
          </div>
          <div class="col-sm-6 text-sm-end">
            <span class="text-muted">Report No:</span> <strong class="font-monospace text-primary ms-1"><?= e($report['report_number']) ?></strong>
          </div>
          <div class="col-sm-6">
            <span class="text-muted">Accession:</span> <span class="font-monospace ms-1"><?= e($report['accession_number']) ?></span>
          </div>
          <div class="col-sm-6 text-sm-end">
            <span class="badge bg-success-subtle text-success-emphasis border border-success"><i class="bi bi-check-circle me-1"></i> Approved & Signed</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Dispatch Form -->
    <div class="admin-card mb-4">
      <div class="admin-card-header bg-white">
        <h3 class="admin-card-title text-success">
          <i class="bi bi-whatsapp"></i> WhatsApp Dispatch Channel
        </h3>
      </div>
      <div class="admin-card-body p-4">
        <form method="POST" action="">
          <?= csrf_field() ?>

          <div class="mb-3">
            <label class="form-label small fw-semibold">Patient WhatsApp Mobile Number <span class="text-danger">*</span></label>
            <div class="input-group">
              <span class="input-group-text bg-light text-muted"><i class="bi bi-telephone"></i></span>
              <input type="tel" name="recipient_phone" class="form-control" value="<?= e($recipientPhone) ?>" required>
            </div>
            <div class="form-text">Include 10-digit mobile or international code (e.g. 9812345670 or +919812345670).</div>
          </div>

          <div class="mb-4">
            <label class="form-label small fw-semibold">Message Preview</label>
            <div class="p-3 bg-light rounded border font-monospace small" style="white-space: pre-wrap;">Dear <?= e($report['patient_name']) ?>,

Your verified pathology report (<?= e($report['report_number']) ?>) from *<?= e(Settings::get('lab_name', 'LabMaster Diagnostics')) ?>* is now ready.

You can view and download your signed PDF report securely here:
<?= BASE_URL ?>/verify-report.php?token=<?= e($report['verification_token']) ?>

For assistance or home collection, contact our helpline at <?= e(Settings::get('lab_phone', '+91 98765 43210')) ?>.

_Thank you for choosing LabMaster._</div>
          </div>

          <div class="d-flex justify-content-between align-items-center">
            <a href="<?= ADMIN_URL ?>/reports/view.php?id=<?= $reportId ?>" class="btn btn-light">Cancel</a>
            <button type="submit" class="btn btn-success px-4 py-2">
              <i class="bi bi-whatsapp me-1"></i> Send Report on WhatsApp
            </button>
          </div>
        </form>

        <?php if ($dispatchResult && !empty($dispatchResult['direct_url'])): ?>
          <div class="alert alert-success mt-4 d-flex align-items-center justify-content-between">
            <div>
              <i class="bi bi-check-circle-fill fs-5 me-2"></i>
              <strong>Dispatch Triggered:</strong> You can also open the chat directly in WhatsApp Web:
            </div>
            <a href="<?= e($dispatchResult['direct_url']) ?>" target="_blank" class="btn btn-sm btn-success">
              <i class="bi bi-box-arrow-up-right me-1"></i> Open Web WhatsApp
            </a>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- WhatsApp Logs for This Report -->
    <div class="admin-card">
      <div class="admin-card-header bg-light">
        <h3 class="admin-card-title text-secondary">
          <i class="bi bi-clock-history"></i> Transmission Audit History
        </h3>
      </div>
      <div class="admin-card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0 small">
            <thead class="table-light">
              <tr>
                <th>Recipient Phone</th>
                <th>Status</th>
                <th>API Response / Details</th>
                <th>Sent By</th>
                <th>Timestamp</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($pastLogs)): ?>
                <tr><td colspan="5" class="text-center text-muted py-3">No WhatsApp messages dispatched yet for this report.</td></tr>
              <?php else: ?>
                <?php foreach ($pastLogs as $log): ?>
                  <tr>
                    <td class="fw-bold font-monospace"><?= e($log['recipient_phone']) ?></td>
                    <td>
                      <span class="badge <?= $log['status'] === 'Sent' || $log['status'] === 'Delivered' ? 'bg-success' : 'bg-danger' ?>">
                        <?= e($log['status']) ?>
                      </span>
                    </td>
                    <td class="text-muted text-truncate" style="max-width: 250px;"><?= e($log['api_response'] ?: '-') ?></td>
                    <td><?= e($log['sender_name'] ?: 'System') ?></td>
                    <td><?= format_date($log['sent_at'], true) ?></td>
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

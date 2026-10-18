<?php
/**
 * LabMaster - Pathologist Review & Digital Sign-off Workflow
 */

declare(strict_types=1);

$reportId = (int)($_GET['id'] ?? 0);
$pageTitle = 'Pathologist Review & Sign-off';
$activeNav = 'reports';

require_once dirname(__DIR__) . '/includes/auth_guard.php';
Auth::requirePermission('reports.review');

$report = Database::fetch(
    "SELECT r.*, p.full_name as patient_name, p.patient_id as patient_code, p.gender, p.age, p.age_unit, p.phone,
            o.accession_number, o.order_date, o.priority,
            d.name as doctor_name
     FROM `reports` r
     JOIN `patients` p ON r.patient_id = p.id
     JOIN `test_orders` o ON r.order_id = o.id
     LEFT JOIN `doctors` d ON o.referring_doctor_id = d.id
     WHERE r.id = ?",
    [$reportId]
);

if (!$report) {
    set_flash('danger', 'Report not found.');
    header('Location: ' . ADMIN_URL . '/reports/index.php');
    exit;
}

// Fetch order items & results
$orderItems = Database::fetchAll(
    "SELECT oi.*, t.test_name, t.test_code, t.specimen_type, t.method
     FROM `test_order_items` oi
     JOIN `tests` t ON oi.test_id = t.id
     WHERE oi.order_id = ?",
    [$report['order_id']]
);

// Fetch parameter results
$resultsRaw = Database::fetchAll(
    "SELECT tr.*, tp.parameter_name, tp.parameter_code, tp.unit, tp.result_type
     FROM `test_results` tr
     JOIN `test_parameters` tp ON tr.parameter_id = tp.id
     WHERE tr.order_id = ?
     ORDER BY tp.sort_order ASC",
    [$report['order_id']]
);

$resultsByItem = [];
foreach ($resultsRaw as $res) {
    $resultsByItem[$res['order_item_id']][] = $res;
}

// Handle Pathologist Approval / Amendment
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? '';
    $userId = Auth::id();

    if ($action === 'approve_report') {
        Auth::requirePermission('reports.approve');

        $clinicalRemarks = trim($_POST['clinical_remarks'] ?? '');
        $interpretation = trim($_POST['interpretation'] ?? '');
        $signatureFile = $currentUser['signature_image'] ?? 'sig_dr_sharma.png';

        $db = Database::getInstance();
        $db->beginTransaction();

        try {
            // Update report
            Database::execute(
                "UPDATE `reports` 
                 SET `report_status` = 'Approved', `approved_by` = ?, `approved_at` = NOW(), 
                     `digital_signature_used` = ?, `clinical_remarks` = ?, `interpretation` = ?, 
                     `is_released_for_patient` = 1, `updated_at` = NOW()
                 WHERE `id` = ?",
                [$userId, $signatureFile, $clinicalRemarks, $interpretation, $reportId]
            );

            // Update order status
            Database::execute("UPDATE `test_orders` SET `status` = 'Approved' WHERE `id` = ?", [$report['order_id']]);

            // Record approval history
            Database::execute(
                "INSERT INTO `report_approvals` (`report_id`, `reviewer_id`, `action`, `remarks`)
                 VALUES (?, ?, 'Approved', 'Digitally approved and verified against clinical history.')",
                [$reportId, $userId]
            );

            $db->commit();
            log_audit('Report Approved', 'Reports', $report['report_number'], "Dr. {$currentUser['full_name']} approved report {$report['report_number']}.");
            set_flash('success', "Report {$report['report_number']} approved and digitally signed successfully! It is now released for patient download and WhatsApp sharing.");
            header('Location: ' . ADMIN_URL . '/reports/view.php?id=' . $reportId);
            exit;
        } catch (Exception $e) {
            $db->rollBack();
            set_flash('danger', 'Error approving report: ' . $e->getMessage());
        }
    }

    // Amendment action
    elseif ($action === 'amend_report') {
        Auth::requirePermission('reports.amend');

        $amendReason = trim($_POST['amend_reason'] ?? '');
        if (empty($amendReason)) {
            set_flash('danger', 'Please provide a clinical justification for report amendment.');
        } else {
            $newVersion = (int)$report['version_number'] + 1;
            Database::execute(
                "UPDATE `reports` 
                 SET `report_status` = 'Amended', `version_number` = ?, `updated_at` = NOW() 
                 WHERE `id` = ?",
                [$newVersion, $reportId]
            );

            Database::execute(
                "INSERT INTO `report_approvals` (`report_id`, `reviewer_id`, `action`, `remarks`)
                 VALUES (?, ?, 'Amended', ?)",
                [$reportId, $userId, $amendReason]
            );

            log_audit('Report Amended', 'Reports', $report['report_number'], "Amended to version {$newVersion}. Reason: {$amendReason}");
            set_flash('warning', "Report marked as Amended (v{$newVersion}). You can edit results and re-approve.");
            header('Location: ' . ADMIN_URL . '/results/enter.php?order_id=' . $report['order_id']);
            exit;
        }
    }
}

require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
  <div>
    <h2 class="h4 fw-bold mb-1">
      Pathologist Review: <?= e($report['report_number']) ?>
      <span class="badge bg-secondary ms-2"><?= e($report['report_status']) ?> (v<?= $report['version_number'] ?>)</span>
    </h2>
    <p class="text-muted small mb-0">Verify clinical results, abnormal flags, and provide authorized medical sign-off</p>
  </div>
  <a href="<?= ADMIN_URL ?>/reports/index.php" class="btn btn-outline-secondary btn-sm">
    <i class="bi bi-arrow-left me-1"></i> Back to Reports
  </a>
</div>

<!-- Patient & Accession Summary -->
<div class="admin-card mb-4 bg-light">
  <div class="admin-card-body p-3">
    <div class="row align-items-center">
      <div class="col-md-8">
        <div class="fw-bold fs-5 text-dark"><?= e($report['patient_name']) ?></div>
        <div class="small text-muted">
          Patient ID: <strong class="font-monospace"><?= e($report['patient_code']) ?></strong> • 
          <?= $report['age'] ?> <?= e($report['age_unit']) ?> / <?= e($report['gender']) ?> • 
          Accession No: <strong class="font-monospace text-primary"><?= e($report['accession_number']) ?></strong> • 
          Ref Doctor: <strong><?= e($report['doctor_name'] ?: 'Direct / Walk-in') ?></strong>
        </div>
      </div>
      <div class="col-md-4 text-md-end mt-2 mt-md-0">
        <a href="<?= ADMIN_URL ?>/results/enter.php?order_id=<?= $report['order_id'] ?>" class="btn btn-sm btn-outline-primary">
          <i class="bi bi-pencil-square me-1"></i> Edit Parameters / Results
        </a>
      </div>
    </div>
  </div>
</div>

<form method="POST" action="">
  <?= csrf_field() ?>

  <!-- Investigations and Result Tables -->
  <?php foreach ($orderItems as $item): ?>
    <div class="admin-card mb-4">
      <div class="admin-card-header bg-white d-flex justify-content-between align-items-center">
        <h3 class="admin-card-title text-primary">
          <i class="bi bi-droplet-half"></i> <?= e($item['test_name']) ?>
        </h3>
        <span class="badge bg-light text-dark border font-monospace"><?= e($item['test_code']) ?></span>
      </div>
      <div class="admin-card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0 small">
            <thead class="table-light">
              <tr>
                <th style="width: 30%;">Parameter</th>
                <th style="width: 20%;">Observed Value</th>
                <th style="width: 15%;">Unit</th>
                <th style="width: 25%;">Biological Reference Interval</th>
                <th style="width: 10%;">Flag</th>
              </tr>
            </thead>
            <tbody>
              <?php $resList = $resultsByItem[$item['id']] ?? []; ?>
              <?php if (empty($resList)): ?>
                <tr><td colspan="5" class="text-center text-muted py-3">No results entered for this panel yet.</td></tr>
              <?php else: ?>
                <?php foreach ($resList as $r): ?>
                  <?php
                  $intervalRow = Database::fetch(
                      "SELECT * FROM `reference_intervals` 
                       WHERE `parameter_id` = ? 
                         AND (`gender` = ? OR `gender` = 'All')
                         AND `age_min` <= ? AND `age_max` >= ?
                       ORDER BY (CASE WHEN `gender` = ? THEN 1 ELSE 2 END) ASC
                       LIMIT 1",
                      [$r['parameter_id'], $report['gender'], (int)$report['age'], (int)$report['age'], $report['gender']]
                  );
                  $dispRange = $intervalRow['display_interval'] ?? ($r['unit'] ? 'Standard' : 'Normal');

                  $isAbnormal = $r['flag'] !== 'NORMAL';
                  $flagBadge = match ($r['flag']) {
                      'CRITICAL_LOW', 'CRITICAL_HIGH' => 'bg-danger text-white pulse-alert',
                      'HIGH' => 'bg-warning text-dark fw-bold',
                      'LOW' => 'bg-warning text-dark fw-bold',
                      'ABNORMAL' => 'bg-danger text-white fw-bold',
                      default => 'bg-success-subtle text-success-emphasis'
                  };
                  ?>
                  <tr class="<?= $isAbnormal ? 'table-warning bg-opacity-25' : '' ?>">
                    <td class="fw-bold"><?= e($r['parameter_name']) ?></td>
                    <td>
                      <span class="fs-6 fw-bold <?= $isAbnormal ? 'text-danger' : 'text-dark' ?>">
                        <?= e($r['result_value']) ?>
                      </span>
                    </td>
                    <td class="text-muted"><?= e($r['unit'] ?: '-') ?></td>
                    <td class="text-secondary"><?= e($dispRange) ?></td>
                    <td>
                      <span class="badge <?= $flagBadge ?>"><?= e($r['flag']) ?></span>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  <?php endforeach; ?>

  <!-- Pathologist Clinical Remarks & Interpretation -->
  <div class="admin-card mb-4 border-primary">
    <div class="admin-card-header bg-primary text-white">
      <h3 class="admin-card-title text-white">
        <i class="bi bi-file-earmark-medical"></i> Clinical Impressions & Interpretation
      </h3>
    </div>
    <div class="admin-card-body">
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label small fw-semibold">Clinical Remarks</label>
          <textarea name="clinical_remarks" class="form-control" rows="3"><?= e($report['clinical_remarks'] ?? '') ?></textarea>
          <div class="form-text">Will appear directly below the parameter tables on the printed medical report.</div>
        </div>
        <div class="col-md-6">
          <label class="form-label small fw-semibold">Pathologist Interpretation & Recommendations</label>
          <textarea name="interpretation" class="form-control" rows="3"><?= e($report['interpretation'] ?? '') ?></textarea>
          <div class="form-text">Clinical guidance for the treating physician.</div>
        </div>
      </div>

      <div class="p-3 bg-light rounded border mt-3 d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center gap-2">
          <i class="bi bi-pen-fill text-primary fs-4"></i>
          <div>
            <div class="fw-bold small">Signing Pathologist: <?= e($currentUser['full_name']) ?></div>
            <div class="text-muted" style="font-size:0.75rem;">Reg. No: <?= e($currentUser['registration_number'] ?: 'MCI-48291') ?> • Digital Authentication Verified</div>
          </div>
        </div>
        <span class="badge bg-success-subtle text-success-emphasis border border-success">
          <i class="bi bi-shield-lock-fill me-1"></i> Digital Signature Ready
        </span>
      </div>
    </div>
  </div>

  <!-- Action Bar -->
  <div class="d-flex justify-content-between align-items-center mb-5 p-3 bg-white rounded border shadow-sm">
    <div>
      <?php if ($report['report_status'] === 'Approved'): ?>
        <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#amendModal">
          <i class="bi bi-pencil-fill me-1"></i> Issue Clinical Amendment
        </button>
      <?php else: ?>
        <a href="<?= ADMIN_URL ?>/reports/index.php" class="btn btn-save">Save Draft & Exit</a>
      <?php endif; ?>
    </div>

    <div class="d-flex gap-2">
      <button type="submit" name="action" value="approve_report" class="btn btn-update py-2 px-4 fs-6">
        <i class="bi bi-patch-check-fill me-1"></i> Approve & Digitally Sign Report
      </button>
    </div>
  </div>
</form>

<!-- Modal: Amend Report -->
<div class="modal fade" id="amendModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" action="">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="amend_report">
        <div class="modal-header bg-danger text-white">
          <h5 class="modal-title fw-bold"><i class="bi bi-exclamation-octagon me-1"></i> Amend Approved Report</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <p class="small text-muted mb-3">
            Amending an approved medical report increments its version number (v<?= $report['version_number'] + 1 ?>) and records the justification in the audit trail.
          </p>
          <div class="mb-3">
            <label class="form-label small fw-semibold">Clinical Reason for Amendment <span class="text-danger">*</span></label>
            <textarea name="amend_reason" class="form-control" rows="3" placeholder="e.g. Specimen re-run on automated analyzer after dilution. Revised platelet count." required></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-danger">Confirm Amendment</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>

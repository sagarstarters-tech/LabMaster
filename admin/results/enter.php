<?php
/**
 * LabMaster - Advanced Clinical Result Entry & Range Validation Screen
 */

declare(strict_types=1);

$orderId = (int)($_GET['order_id'] ?? 0);
$pageTitle = 'Enter Clinical Results';
$activeNav = 'results';

require_once dirname(__DIR__) . '/includes/auth_guard.php';
Auth::requirePermission('results.entry');

$order = Database::fetch(
    "SELECT o.*, p.full_name as patient_name, p.patient_id as patient_code, p.gender, p.age, p.age_unit, p.blood_group,
            d.name as doctor_name
     FROM `test_orders` o
     JOIN `patients` p ON o.patient_id = p.id
     LEFT JOIN `doctors` d ON o.referring_doctor_id = d.id
     WHERE o.id = ?",
    [$orderId]
);

if (!$order) {
    set_flash('danger', 'Accession order not found.');
    header('Location: ' . ADMIN_URL . '/results/index.php');
    exit;
}

// Fetch Tests & Parameters for this Order
$orderItems = Database::fetchAll(
    "SELECT oi.*, t.test_name, t.test_code, t.specimen_type, t.method
     FROM `test_order_items` oi
     JOIN `tests` t ON oi.test_id = t.id
     WHERE oi.order_id = ?",
    [$orderId]
);

// Fetch existing results if any
$existingResults = [];
$resRows = Database::fetchAll("SELECT * FROM `test_results` WHERE `order_id` = ?", [$orderId]);
foreach ($resRows as $r) {
    $existingResults[$r['parameter_id']] = $r;
}

// Handle Result Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $submittedResults = $_POST['results'] ?? [];
    $remarks = $_POST['remarks'] ?? [];
    $clinicalRemarks = trim($_POST['clinical_remarks'] ?? '');
    $interpretation = trim($_POST['interpretation'] ?? '');
    $userId = Auth::id();

    $hasAbnormal = false;
    $hasCritical = false;

    $db = Database::getInstance();
    $db->beginTransaction();

    try {
        foreach ($orderItems as $item) {
            $params = Database::fetchAll(
                "SELECT * FROM `test_parameters` WHERE `test_id` = ? ORDER BY `sort_order` ASC",
                [$item['test_id']]
            );

            foreach ($params as $p) {
                $pid = (int)$p['id'];
                $val = isset($submittedResults[$pid]) ? trim((string)$submittedResults[$pid]) : null;
                $rem = isset($remarks[$pid]) ? trim((string)$remarks[$pid]) : null;

                if ($val !== null && $val !== '') {
                    // Evaluate flag using medical evaluator
                    $eval = evaluate_result_flag($pid, $val, $order['gender'], (int)$order['age'], $order['age_unit']);
                    $flag = $eval['flag'];

                    if (in_array($flag, ['CRITICAL_LOW', 'CRITICAL_HIGH'], true)) {
                        $hasCritical = true;
                    } elseif ($flag !== 'NORMAL') {
                        $hasAbnormal = true;
                    }

                    Database::execute(
                        "INSERT INTO `test_results` (`order_id`, `order_item_id`, `parameter_id`, `result_value`, `flag`, `remarks`, `entered_by`, `entered_at`)
                         VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                         ON DUPLICATE KEY UPDATE `result_value` = VALUES(`result_value`), `flag` = VALUES(`flag`), `remarks` = VALUES(`remarks`), `entered_by` = VALUES(`entered_by`), `entered_at` = NOW()",
                        [$orderId, $item['id'], $pid, $val, $flag, $rem, $userId]
                    );
                }
            }
        }

        // Update Test Order Status
        Database::execute(
            "UPDATE `test_orders` SET `status` = 'Results Entered' WHERE `id` = ?",
            [$orderId]
        );

        // Update or Insert Report Header
        $existingReport = Database::fetch("SELECT `id`, `report_number` FROM `reports` WHERE `order_id` = ?", [$orderId]);
        if ($existingReport) {
            Database::execute(
                "UPDATE `reports` SET `report_status` = 'Pending Approval', `clinical_remarks` = ?, `interpretation` = ?, `updated_at` = NOW() WHERE `id` = ?",
                [$clinicalRemarks, $interpretation, $existingReport['id']]
            );
            $reportId = (int)$existingReport['id'];
            $reportNo = $existingReport['report_number'];
        } else {
            $reportNo = generate_report_number();
            $token = bin2hex(random_bytes(32));
            Database::execute(
                "INSERT INTO `reports` (`report_number`, `order_id`, `patient_id`, `version_number`, `report_status`, `clinical_remarks`, `interpretation`, `verification_token`)
                 VALUES (?, ?, ?, 1, 'Pending Approval', ?, ?, ?)",
                [$reportNo, $orderId, $order['patient_id'], $clinicalRemarks, $interpretation, $token]
            );
            $reportId = (int)Database::lastInsertId();
        }

        // Add Notification for Pathologist
        $alertTitle = $hasCritical ? 'CRITICAL Report Pending Approval' : 'New Report Ready for Review';
        $alertType = $hasCritical ? 'danger' : ($hasAbnormal ? 'warning' : 'info');
        add_notification(
            $alertTitle,
            "Accession {$order['accession_number']} ({$order['patient_name']}) results entered. Requires pathologist sign-off.",
            $alertType,
            "reports/review.php?id={$reportId}"
        );

        $db->commit();
        log_audit('Results Entered', 'Results', $order['accession_number'], "Entered results for accession {$order['accession_number']}. Status set to Pending Approval.");
        set_flash('success', "Results saved successfully! Report {$reportNo} is now queued for Pathologist review and approval.");
        header('Location: ' . ADMIN_URL . '/reports/view.php?id=' . $reportId);
        exit;
    } catch (Exception $e) {
        $db->rollBack();
        set_flash('danger', 'Error saving results: ' . $e->getMessage());
    }
}

require_once dirname(__DIR__) . '/includes/header.php';
?>

<!-- Accession Header Banner -->
<div class="admin-card mb-4 bg-light">
  <div class="admin-card-body p-3">
    <div class="row align-items-center">
      <div class="col-md-7">
        <div class="d-flex align-items-center gap-2">
          <span class="badge bg-primary font-monospace fs-6"><?= e($order['accession_number']) ?></span>
          <h2 class="h5 fw-bold mb-0"><?= e($order['patient_name']) ?></h2>
          <span class="badge bg-light text-dark border"><?= $order['age'] ?> <?= e($order['age_unit']) ?> / <?= e($order['gender']) ?></span>
        </div>
        <div class="small text-muted mt-1">
          Patient ID: <strong class="font-monospace"><?= e($order['patient_code']) ?></strong> • 
          Ref Doctor: <strong><?= e($order['doctor_name'] ?: 'Direct Walk-in') ?></strong> • 
          Order Date: <strong><?= format_date($order['order_date']) ?></strong>
        </div>
      </div>
      <div class="col-md-5 text-md-end mt-2 mt-md-0">
        <a href="<?= ADMIN_URL ?>/orders/view.php?id=<?= $orderId ?>" class="btn btn-outline-secondary btn-sm">
          <i class="bi bi-arrow-left me-1"></i> Back to Accession
        </a>
      </div>
    </div>
  </div>
</div>

<form method="POST" action="" id="resultEntryForm">
  <?= csrf_field() ?>

  <!-- Loop through each test panel -->
  <?php foreach ($orderItems as $item): ?>
    <?php
    $params = Database::fetchAll(
        "SELECT * FROM `test_parameters` WHERE `test_id` = ? ORDER BY `sort_order` ASC",
        [$item['test_id']]
    );
    ?>
    <div class="admin-card mb-4">
      <div class="admin-card-header bg-white d-flex justify-content-between align-items-center">
        <div>
          <h3 class="admin-card-title text-primary">
            <i class="bi bi-file-earmark-medical"></i> <?= e($item['test_name']) ?>
          </h3>
          <div class="text-muted small">
            Specimen: <strong><?= e($item['specimen_type']) ?></strong>
            <?php if (!empty($item['method'])): ?>
              • Method: <strong><?= e($item['method']) ?></strong>
            <?php endif; ?>
          </div>
        </div>
        <span class="badge bg-light text-dark border font-monospace"><?= e($item['test_code']) ?></span>
      </div>

      <div class="admin-card-body p-0">
        <div class="table-responsive">
          <table class="table table-result-entry table-hover align-middle mb-0">
            <thead>
              <tr>
                <th style="width: 25%;">Investigation Parameter</th>
                <th style="width: 20%;">Observed Result Value</th>
                <th style="width: 12%;">Unit</th>
                <th style="width: 23%;">Biological Reference Interval</th>
                <th style="width: 10%;">Flag</th>
                <th style="width: 10%;">Remarks</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($params as $p): ?>
                <?php
                $pid = (int)$p['id'];
                $existingVal = $existingResults[$pid]['result_value'] ?? '';
                $existingRemarks = $existingResults[$pid]['remarks'] ?? '';
                $existingFlag = $existingResults[$pid]['flag'] ?? 'NORMAL';

                // Find matching reference interval for this patient
                $intervalRow = Database::fetch(
                    "SELECT * FROM `reference_intervals` 
                     WHERE `parameter_id` = ? 
                       AND (`gender` = ? OR `gender` = 'All')
                       AND `age_min` <= ? AND `age_max` >= ?
                     ORDER BY (CASE WHEN `gender` = ? THEN 1 ELSE 2 END) ASC
                     LIMIT 1",
                    [$pid, $order['gender'], (int)$order['age'], (int)$order['age'], $order['gender']]
                );

                $displayRange = $intervalRow['display_interval'] ?? ($p['unit'] ? 'Standard' : 'Normal');
                $minVal = $intervalRow['min_val'] ?? '';
                $maxVal = $intervalRow['max_val'] ?? '';
                $critLow = $p['critical_low'] ?? '';
                $critHigh = $p['critical_high'] ?? '';
                ?>
                <tr>
                  <td>
                    <div class="fw-bold"><?= e($p['parameter_name']) ?></div>
                    <span class="text-muted font-monospace small" style="font-size:0.75rem;"><?= e($p['parameter_code']) ?></span>
                    <?php if (!empty($p['formula'])): ?>
                      <span class="badge bg-light text-secondary border ms-1" title="Automated Formula: <?= e($p['formula']) ?>">
                        <i class="bi bi-calculator"></i> Formula
                      </span>
                    <?php endif; ?>
                  </td>

                  <td>
                    <?php if ($p['result_type'] === 'dropdown'): ?>
                      <?php $options = array_map('trim', explode(',', (string)$p['dropdown_options'])); ?>
                      <select name="results[<?= $pid ?>]" class="form-select form-select-sm result-input" id="input_<?= $pid ?>">
                        <option value="">-- Select Result --</option>
                        <?php foreach ($options as $opt): ?>
                          <option value="<?= e($opt) ?>" <?= $existingVal === $opt ? 'selected' : '' ?>><?= e($opt) ?></option>
                        <?php endforeach; ?>
                      </select>
                    <?php elseif ($p['result_type'] === 'text'): ?>
                      <input type="text" name="results[<?= $pid ?>]" class="form-control form-control-sm result-input" value="<?= e($existingVal) ?>" placeholder="e.g. 0-2 /HPF or Not Seen">
                    <?php else: ?>
                      <!-- Numeric or Formula -->
                      <input type="text" 
                             name="results[<?= $pid ?>]" 
                             id="input_<?= $pid ?>" 
                             class="form-control form-control-sm result-input param-result-input" 
                             data-param-id="<?= $pid ?>"
                             data-param-code="<?= e($p['parameter_code']) ?>"
                             data-min="<?= e((string)$minVal) ?>"
                             data-max="<?= e((string)$maxVal) ?>"
                             data-crit-low="<?= e((string)$critLow) ?>"
                             data-crit-high="<?= e((string)$critHigh) ?>"
                             value="<?= e($existingVal) ?>" 
                             placeholder="Enter numeric value"
                             autocomplete="off">
                    <?php endif; ?>
                  </td>

                  <td class="text-muted fw-semibold small">
                    <?= e($p['unit'] ?: '-') ?>
                  </td>

                  <td>
                    <span class="fw-semibold text-secondary small"><?= e($displayRange) ?></span>
                    <?php if (!empty($intervalRow['interpretation'])): ?>
                      <div class="text-muted" style="font-size: 0.72rem;"><?= e($intervalRow['interpretation']) ?></div>
                    <?php endif; ?>
                  </td>

                  <td>
                    <select name="flags[<?= $pid ?>]" id="flag_<?= $pid ?>" class="form-select form-select-sm" style="font-size: 0.75rem;">
                      <option value="NORMAL" <?= $existingFlag === 'NORMAL' ? 'selected' : '' ?>>NORMAL</option>
                      <option value="LOW" <?= $existingFlag === 'LOW' ? 'selected' : '' ?>>LOW</option>
                      <option value="HIGH" <?= $existingFlag === 'HIGH' ? 'selected' : '' ?>>HIGH</option>
                      <option value="CRITICAL_LOW" <?= $existingFlag === 'CRITICAL_LOW' ? 'selected' : '' ?>>CRIT LOW</option>
                      <option value="CRITICAL_HIGH" <?= $existingFlag === 'CRITICAL_HIGH' ? 'selected' : '' ?>>CRIT HIGH</option>
                      <option value="ABNORMAL" <?= $existingFlag === 'ABNORMAL' ? 'selected' : '' ?>>ABNORMAL</option>
                    </select>
                  </td>

                  <td>
                    <input type="text" name="remarks[<?= $pid ?>]" class="form-control form-control-sm" placeholder="Notes" value="<?= e($existingRemarks) ?>">
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  <?php endforeach; ?>

  <!-- Report Notes & Clinical Summary -->
  <div class="admin-card mb-4">
    <div class="admin-card-header bg-light">
      <h3 class="admin-card-title text-secondary">
        <i class="bi bi-chat-left-text"></i> Clinical Remarks & Pathologist Interpretation
      </h3>
    </div>
    <div class="admin-card-body">
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label small fw-semibold">Clinical Remarks (Printed on final report)</label>
          <textarea name="clinical_remarks" class="form-control" rows="3" placeholder="e.g. Mild normocytic normochromic anemia. Repeat after hematinic therapy."></textarea>
        </div>
        <div class="col-md-6">
          <label class="form-label small fw-semibold">Pathologist Interpretation & Advice</label>
          <textarea name="interpretation" class="form-control" rows="3" placeholder="e.g. Please correlate clinically with patient presentation and treating physician."></textarea>
        </div>
      </div>
    </div>
  </div>

  <!-- Submit Bar -->
  <div class="d-flex justify-content-between align-items-center mb-5 p-3 bg-white rounded border shadow-sm">
    <a href="<?= ADMIN_URL ?>/orders/view.php?id=<?= $orderId ?>" class="btn btn-light">Cancel</a>
    <div class="d-flex gap-2">
      <button type="submit" class="btn btn-save py-2 px-4 fs-6">
        <i class="bi bi-shield-check me-1"></i> Save Results & Submit for Pathologist Approval
      </button>
    </div>
  </div>
</form>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>

<?php
/**
 * LabMaster - Pathology Report Viewer & Verification Interface
 */

declare(strict_types=1);

$reportId = (int)($_GET['id'] ?? 0);
$pageTitle = 'View Pathology Report';
$activeNav = 'reports';

require_once dirname(__DIR__) . '/includes/auth_guard.php';
Auth::requirePermission('reports.view');

$report = Database::fetch(
    "SELECT r.*, p.full_name as patient_name, p.patient_id as patient_code, p.gender, p.age, p.age_unit, p.phone,
            o.accession_number, o.order_date, o.priority,
            d.name as doctor_name, d.specialization as doctor_spec,
            u.full_name as pathologist_name, u.qualification as pathologist_qual, u.registration_number as pathologist_reg, u.signature_image
     FROM `reports` r
     JOIN `patients` p ON r.patient_id = p.id
     JOIN `test_orders` o ON r.order_id = o.id
     LEFT JOIN `doctors` d ON o.referring_doctor_id = d.id
     LEFT JOIN `users` u ON r.approved_by = u.id
     WHERE r.id = ?",
    [$reportId]
);

if (!$report) {
    set_flash('danger', 'Report not found.');
    header('Location: ' . ADMIN_URL . '/reports/index.php');
    exit;
}

// Fetch test items
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

$labName = Settings::get('lab_name', 'LabMaster Diagnostic & Research Center');
$labTagline = Settings::get('lab_tagline', 'Precision Diagnostics & Clinical Pathology Excellence');
$labAddress = Settings::get('lab_address', 'Healthcare Square, Central Avenue, Metro City, India');
$labPhone = Settings::get('lab_phone', '+91 98765 43210');
$labEmail = Settings::get('lab_email', 'info@labmasterdiagnostics.com');
$accreditation = Settings::get('accreditation_text', 'ISO 15189:2022 Compliant & NABL Accredited Reference Laboratory');
$qrUrl = BASE_URL . '/verify-report.php?token=' . urlencode($report['verification_token']);

require_once dirname(__DIR__) . '/includes/header.php';
?>

<!-- Action Bar -->
<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
  <div class="d-flex align-items-center gap-2">
    <span class="badge bg-light text-dark border font-monospace fs-6"><?= e($report['report_number']) ?></span>
    <?php if ($report['report_status'] === 'Approved'): ?>
      <span class="badge bg-success-subtle text-success-emphasis border border-success"><i class="bi bi-patch-check-fill me-1"></i> Approved</span>
    <?php else: ?>
      <span class="badge bg-warning text-dark"><?= e($report['report_status']) ?> (v<?= $report['version_number'] ?>)</span>
    <?php endif; ?>
  </div>

  <div class="d-flex gap-2">
    <?php if (Auth::can('reports.review')): ?>
      <a href="<?= ADMIN_URL ?>/reports/review.php?id=<?= $report['id'] ?>" class="btn btn-outline-primary btn-sm">
        <i class="bi bi-pen me-1"></i> Review / Amend
      </a>
    <?php endif; ?>
    <?php if (Auth::can('reports.print')): ?>
    <a href="<?= ADMIN_URL ?>/reports/print.php?id=<?= $report['id'] ?>" target="_blank" class="btn btn-outline-secondary btn-sm">
      <i class="bi bi-printer me-1"></i> Print Report
    </a>
    <a href="<?= ADMIN_URL ?>/reports/pdf.php?id=<?= $report['id'] ?>" target="_blank" class="btn btn-danger btn-sm">
      <i class="bi bi-file-earmark-pdf me-1"></i> Download PDF
    </a>
    <?php endif; ?>
    <?php if (Auth::can('reports.whatsapp') && $report['report_status'] === 'Approved'): ?>
      <a href="<?= ADMIN_URL ?>/reports/share_whatsapp.php?id=<?= $report['id'] ?>" class="btn btn-success btn-sm">
        <i class="bi bi-whatsapp me-1"></i> Share on WhatsApp
      </a>
    <?php endif; ?>
  </div>
</div>

<!-- Medical Clinical Report Sheet Container -->
<div class="card shadow-sm border mx-auto mb-5 print-sheet" style="max-width: 900px; background: #ffffff;">
  <div class="card-body p-4 p-md-5">

    <!-- Lab Letterhead Header -->
    <div class="border-bottom pb-3 mb-4">
      <div class="row align-items-center">
        <div class="col-sm-8">
          <div class="d-flex align-items-center gap-2 mb-1">
            <i class="bi bi-heart-pulse-fill text-primary fs-2"></i>
            <h1 class="h4 fw-bold text-dark mb-0"><?= e($labName) ?></h1>
          </div>
          <p class="text-muted small mb-1"><?= e($labTagline) ?></p>
          <div class="small text-secondary">
            <?= e($labAddress) ?> • Tel: <?= e($labPhone) ?> • <?= e($labEmail) ?>
          </div>
        </div>
        <div class="col-sm-4 text-sm-end mt-2 mt-sm-0">
          <span class="badge bg-light text-dark border p-2 small">
            <i class="bi bi-award-fill text-warning me-1"></i> <?= e($accreditation) ?>
          </span>
        </div>
      </div>
    </div>

    <!-- Patient Demographics & Accession Header -->
    <div class="p-3 bg-light rounded border mb-4">
      <div class="row g-2 small">
        <div class="col-sm-6">
          <span class="text-muted">Patient Name:</span> <strong class="fs-6 text-dark ms-1"><?= e($report['patient_name']) ?></strong>
        </div>
        <div class="col-sm-6 text-sm-end">
          <span class="text-muted">Report No:</span> <strong class="font-monospace text-primary ms-1"><?= e($report['report_number']) ?></strong>
        </div>

        <div class="col-sm-6">
          <span class="text-muted">Patient ID:</span> <span class="font-monospace fw-semibold ms-1"><?= e($report['patient_code']) ?></span>
          <span class="ms-2">• Age/Sex:</span> <strong class="ms-1"><?= $report['age'] ?> <?= e($report['age_unit']) ?> / <?= e($report['gender']) ?></strong>
        </div>
        <div class="col-sm-6 text-sm-end">
          <span class="text-muted">Accession No:</span> <span class="font-monospace ms-1"><?= e($report['accession_number']) ?></span>
        </div>

        <div class="col-sm-6">
          <span class="text-muted">Ref. Doctor:</span> <strong class="ms-1"><?= e($report['doctor_name'] ?: 'Direct / Walk-in') ?></strong>
        </div>
        <div class="col-sm-6 text-sm-end">
          <span class="text-muted">Order Date:</span> <span class="ms-1"><?= format_date($report['order_date']) ?></span>
        </div>

        <div class="col-sm-6">
          <span class="text-muted">Contact Phone:</span> <span class="ms-1"><?= e($report['phone']) ?></span>
        </div>
        <div class="col-sm-6 text-sm-end">
          <span class="text-muted">Report Date:</span> <strong class="ms-1"><?= format_date($report['approved_at'] ?: $report['created_at'], true) ?></strong>
        </div>
      </div>
    </div>

    <!-- Investigation Results -->
    <?php foreach ($orderItems as $item): ?>
      <div class="mb-4">
        <div class="d-flex justify-content-between align-items-center bg-light p-2 px-3 border border-bottom-0 rounded-top">
          <span class="fw-bold text-primary small text-uppercase letter-spacing"><?= e($item['test_name']) ?></span>
          <span class="text-muted" style="font-size:0.75rem;">Specimen: <?= e($item['specimen_type']) ?></span>
        </div>

        <div class="table-responsive">
          <table class="table table-bordered align-middle mb-0" style="font-size: 0.88rem;">
            <thead class="table-light">
              <tr class="text-uppercase" style="font-size: 0.75rem; letter-spacing: 0.04em;">
                <th style="width: 32%;">Test Parameter</th>
                <th style="width: 20%;" class="text-center">Observed Value</th>
                <th style="width: 14%;" class="text-center">Unit</th>
                <th style="width: 24%;" class="text-center">Biological Reference Range</th>
                <th style="width: 10%;" class="text-center">Flag</th>
              </tr>
            </thead>
            <tbody>
              <?php $resList = $resultsByItem[$item['id']] ?? []; ?>
              <?php if (empty($resList)): ?>
                <tr><td colspan="5" class="text-center text-muted py-3">Results in progress...</td></tr>
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
                  ?>
                  <tr>
                    <td>
                      <div class="fw-semibold text-dark"><?= e($r['parameter_name']) ?></div>
                    </td>
                    <td class="text-center">
                      <span class="fw-bold fs-6 <?= $isAbnormal ? 'text-danger' : 'text-dark' ?>">
                        <?= e($r['result_value']) ?>
                      </span>
                    </td>
                    <td class="text-center text-muted"><?= e($r['unit'] ?: '-') ?></td>
                    <td class="text-center text-secondary"><?= e($dispRange) ?></td>
                    <td class="text-center">
                      <?php if ($isAbnormal): ?>
                        <span class="badge bg-danger-subtle text-danger fw-bold" style="font-size:0.7rem;"><?= e($r['flag']) ?></span>
                      <?php else: ?>
                        <span class="badge bg-success-subtle text-success" style="font-size:0.7rem;">Normal</span>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php endforeach; ?>

    <!-- Clinical Remarks & Interpretation -->
    <?php if (!empty($report['clinical_remarks']) || !empty($report['interpretation'])): ?>
    <div class="p-3 bg-light rounded border mb-4 small">
      <?php if (!empty($report['clinical_remarks'])): ?>
        <div class="mb-2">
          <strong>Clinical Remarks:</strong>
          <span class="text-dark ms-1"><?= nl2br(e($report['clinical_remarks'])) ?></span>
        </div>
      <?php endif; ?>
      <?php if (!empty($report['interpretation'])): ?>
        <div>
          <strong>Interpretation & Advice:</strong>
          <span class="text-dark ms-1"><?= nl2br(e($report['interpretation'])) ?></span>
        </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Signatures & Verification QR Code Footer -->
    <div class="row align-items-end pt-4 border-top mt-4">
      <!-- QR Code for Verification -->
      <div class="col-sm-4 text-center text-sm-start">
        <div class="d-inline-block p-1 border rounded bg-white shadow-sm mb-1">
          <img src="https://api.qrserver.com/v1/create-qr-code/?size=85x85&data=<?= urlencode($qrUrl) ?>" alt="QR Code" width="85" height="85">
        </div>
        <div class="text-muted" style="font-size: 0.68rem;">
          Scan with mobile to verify authentic online report
        </div>
      </div>

      <!-- Technician Sign-off -->
      <div class="col-sm-4 text-center">
        <div class="small fw-semibold text-secondary">Checked by:</div>
        <div class="fw-bold small text-dark mt-3">Laboratory Technologist</div>
        <div class="text-muted" style="font-size: 0.7rem;">Automated Analyzer Cell</div>
      </div>

      <!-- Pathologist Digital Signature -->
      <div class="col-sm-4 text-center text-sm-end mt-3 mt-sm-0">
        <?php if ($report['report_status'] === 'Approved'): ?>
          <div class="d-inline-block text-center">
            <div class="font-monospace text-primary fw-bold" style="font-size: 1.1rem; letter-spacing: -1px; font-style: italic;">
              Dr. Rajesh Sharma
            </div>
            <div class="fw-bold small text-dark"><?= e($report['pathologist_name'] ?: 'Dr. Rajesh Sharma') ?></div>
            <div class="text-muted" style="font-size: 0.72rem;"><?= e($report['pathologist_qual'] ?: 'MBBS, MD (Pathology)') ?></div>
            <div class="text-muted" style="font-size: 0.7rem;">Reg: <?= e($report['pathologist_reg'] ?: 'MCI-48291') ?></div>
          </div>
        <?php else: ?>
          <div class="badge bg-warning text-dark p-2">
            <i class="bi bi-clock-history me-1"></i> Awaiting Medical Approval
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Legal Disclaimer & End of Report -->
    <div class="text-center border-top pt-3 mt-4 text-muted" style="font-size: 0.7rem;">
      <div class="fw-bold mb-1">*** END OF PATHOLOGY REPORT ***</div>
      <div><?= e(Settings::get('pdf_disclaimer', 'This medical report is based on the specimen received and processed using calibrated clinical laboratory standards.')) ?></div>
    </div>

  </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>

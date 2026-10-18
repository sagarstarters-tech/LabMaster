<?php
/**
 * LabMaster - Clean Print View for Pathology Report
 */

declare(strict_types=1);

require_once dirname(dirname(__DIR__)) . '/config/config.php';
require_once dirname(dirname(__DIR__)) . '/includes/auth.php';

Auth::requirePermission('reports.view');

$reportId = (int)($_GET['id'] ?? 0);
$report = Database::fetch(
    "SELECT r.*, p.full_name as patient_name, p.patient_id as patient_code, p.gender, p.age, p.age_unit, p.phone,
            o.accession_number, o.order_date,
            d.name as doctor_name,
            u.full_name as pathologist_name, u.qualification as pathologist_qual, u.registration_number as pathologist_reg
     FROM `reports` r
     JOIN `patients` p ON r.patient_id = p.id
     JOIN `test_orders` o ON r.order_id = o.id
     LEFT JOIN `doctors` d ON o.referring_doctor_id = d.id
     LEFT JOIN `users` u ON r.approved_by = u.id
     WHERE r.id = ?",
    [$reportId]
);

if (!$report) {
    die('Report not found.');
}

$orderItems = Database::fetchAll(
    "SELECT oi.*, t.test_name, t.test_code, t.specimen_type, t.method
     FROM `test_order_items` oi
     JOIN `tests` t ON oi.test_id = t.id
     WHERE oi.order_id = ?",
    [$report['order_id']]
);

$resultsRaw = Database::fetchAll(
    "SELECT tr.*, tp.parameter_name, tp.parameter_code, tp.unit 
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Report - <?= e($report['report_number']) ?> - <?= e($report['patient_name']) ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body {
      background: #ffffff;
      color: #000000;
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
      font-size: 13px;
      line-height: 1.4;
      padding: 20px;
    }
    .print-container {
      max-width: 800px;
      margin: 0 auto;
    }
    .table-sm th, .table-sm td {
      padding: 4px 8px;
    }
    @media print {
      body { padding: 0; }
      .no-print { display: none !important; }
      .print-container { width: 100%; max-width: 100%; }
    }
  </style>
</head>
<body>

<div class="print-container">
  <!-- Print Controls -->
  <div class="no-print d-flex justify-content-between align-items-center mb-3 p-2 bg-light rounded border">
    <span class="small text-muted">Laboratory Medical Report Print Engine</span>
    <button onclick="window.print()" class="btn btn-primary btn-sm">
      Print Report Now
    </button>
  </div>

  <!-- Header -->
  <div class="border-bottom pb-2 mb-3">
    <div class="d-flex justify-content-between align-items-center">
      <div>
        <h2 class="h5 fw-bold mb-0 text-primary"><?= e($labName) ?></h2>
        <div class="small text-muted"><?= e($labTagline) ?></div>
        <div style="font-size: 11px;" class="text-secondary"><?= e($labAddress) ?> • Tel: <?= e($labPhone) ?></div>
      </div>
      <div class="text-end" style="font-size: 11px;">
        <span class="badge bg-light text-dark border"><?= e($accreditation) ?></span>
      </div>
    </div>
  </div>

  <!-- Demographics -->
  <div class="p-2 bg-light border rounded mb-3" style="font-size: 12px;">
    <div class="row g-1">
      <div class="col-6"><strong>Patient Name:</strong> <?= e($report['patient_name']) ?></div>
      <div class="col-6 text-end"><strong>Report No:</strong> <?= e($report['report_number']) ?></div>
      <div class="col-6"><strong>Age / Sex:</strong> <?= $report['age'] ?> <?= e($report['age_unit']) ?> / <?= e($report['gender']) ?> (ID: <?= e($report['patient_code']) ?>)</div>
      <div class="col-6 text-end"><strong>Accession No:</strong> <?= e($report['accession_number']) ?></div>
      <div class="col-6"><strong>Referring Doctor:</strong> <?= e($report['doctor_name'] ?: 'Direct / Walk-in') ?></div>
      <div class="col-6 text-end"><strong>Report Date:</strong> <?= format_date($report['approved_at'] ?: $report['created_at'], true) ?></div>
    </div>
  </div>

  <!-- Test Results -->
  <?php foreach ($orderItems as $item): ?>
    <div class="mb-3">
      <div class="fw-bold text-uppercase border-bottom pb-1 mb-1 text-primary" style="font-size: 12px;">
        <?= e($item['test_name']) ?> <span class="text-muted fw-normal" style="font-size: 10px;">(Specimen: <?= e($item['specimen_type']) ?>)</span>
      </div>

      <table class="table table-bordered table-sm mb-2" style="font-size: 12px;">
        <thead class="table-light">
          <tr>
            <th style="width: 35%;">Investigation</th>
            <th style="width: 20%;" class="text-center">Observed Value</th>
            <th style="width: 15%;" class="text-center">Unit</th>
            <th style="width: 30%;" class="text-center">Reference Range</th>
          </tr>
        </thead>
        <tbody>
          <?php $resList = $resultsByItem[$item['id']] ?? []; ?>
          <?php foreach ($resList as $r): ?>
            <?php
            $intervalRow = Database::fetch(
                "SELECT * FROM `reference_intervals` 
                 WHERE `parameter_id` = ? AND (`gender` = ? OR `gender` = 'All') AND `age_min` <= ? AND `age_max` >= ?
                 ORDER BY (CASE WHEN `gender` = ? THEN 1 ELSE 2 END) ASC LIMIT 1",
                [$r['parameter_id'], $report['gender'], (int)$report['age'], (int)$report['age'], $report['gender']]
            );
            $dispRange = $intervalRow['display_interval'] ?? ($r['unit'] ? 'Standard' : 'Normal');
            $isAbnormal = $r['flag'] !== 'NORMAL';
            ?>
            <tr>
              <td><?= e($r['parameter_name']) ?></td>
              <td class="text-center <?= $isAbnormal ? 'fw-bold text-danger' : 'fw-bold' ?>">
                <?= e($r['result_value']) ?>
                <?= $isAbnormal ? ' *' : '' ?>
              </td>
              <td class="text-center text-muted"><?= e($r['unit'] ?: '-') ?></td>
              <td class="text-center"><?= e($dispRange) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endforeach; ?>

  <!-- Remarks -->
  <?php if (!empty($report['clinical_remarks']) || !empty($report['interpretation'])): ?>
    <div class="p-2 border rounded bg-light mb-3" style="font-size: 11px;">
      <?php if (!empty($report['clinical_remarks'])): ?>
        <div><strong>Clinical Remarks:</strong> <?= nl2br(e($report['clinical_remarks'])) ?></div>
      <?php endif; ?>
      <?php if (!empty($report['interpretation'])): ?>
        <div><strong>Interpretation:</strong> <?= nl2br(e($report['interpretation'])) ?></div>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <!-- Signature and QR -->
  <div class="row align-items-end pt-3 border-top mt-3">
    <div class="col-4">
      <img src="https://api.qrserver.com/v1/create-qr-code/?size=70x70&data=<?= urlencode($qrUrl) ?>" alt="QR" width="70" height="70">
      <div class="text-muted" style="font-size: 9px;">Scan to verify authentic report</div>
    </div>
    <div class="col-4 text-center">
      <div style="font-size: 11px;">Checked by:</div>
      <div class="fw-bold" style="font-size: 11px;">Laboratory Technologist</div>
    </div>
    <div class="col-4 text-end">
      <div class="text-primary font-monospace" style="font-size: 12px; font-style: italic;">Dr. Rajesh Sharma</div>
      <div class="fw-bold" style="font-size: 11px;"><?= e($report['pathologist_name'] ?: 'Dr. Rajesh Sharma') ?></div>
      <div class="text-muted" style="font-size: 10px;">MBBS, MD (Pathology) • Reg: <?= e($report['pathologist_reg'] ?: 'MCI-48291') ?></div>
    </div>
  </div>

  <div class="text-center text-muted border-top pt-2 mt-3" style="font-size: 9px;">
    *** END OF REPORT *** • <?= e(Settings::get('pdf_disclaimer', 'This medical report is based on the specimen received and processed using calibrated clinical laboratory standards.')) ?>
  </div>
</div>

</body>
</html>

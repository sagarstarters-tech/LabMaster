<?php
/**
 * LabMaster - High-Fidelity Medical PDF Report Generator
 * Powered by Dompdf
 */

declare(strict_types=1);

require_once dirname(dirname(__DIR__)) . '/config/config.php';
require_once dirname(dirname(__DIR__)) . '/includes/auth.php';
require_once dirname(dirname(__DIR__)) . '/vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$reportId = (int)($_GET['id'] ?? 0);
$token = trim($_GET['token'] ?? '');

// Allow access either if staff is logged in with permission, or via verified public QR token
if (!Auth::check() && empty($token)) {
    die('Unauthorized access.');
}

if (empty($token) && !Auth::can('reports.view') && !Auth::can('reports.print')) {
    die('Unauthorized: You do not possess permission to view or print clinical reports.');
}

if (!empty($token)) {
    $report = Database::fetch("SELECT * FROM `reports` WHERE `verification_token` = ?", [$token]);
} else {
    $report = Database::fetch("SELECT * FROM `reports` WHERE `id` = ?", [$reportId]);
}

if (!$report) {
    die('Report record not found.');
}

$patient = Database::fetch("SELECT * FROM `patients` WHERE `id` = ?", [$report['patient_id']]);
$order = Database::fetch(
    "SELECT o.*, d.name as doctor_name FROM `test_orders` o LEFT JOIN `doctors` d ON o.referring_doctor_id = d.id WHERE o.id = ?",
    [$report['order_id']]
);
$pathologist = Database::fetch("SELECT * FROM `users` WHERE `id` = ?", [$report['approved_by']]);

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

// Build HTML for Dompdf
ob_start();
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Report - <?= htmlspecialchars($report['report_number']) ?></title>
<style>
  @page { margin: 25px 30px 40px 30px; }
  body { font-family: Helvetica, Arial, sans-serif; font-size: 11px; color: #1e293b; line-height: 1.35; }
  .header-table { width: 100%; border-bottom: 2px solid #0284c7; padding-bottom: 10px; margin-bottom: 12px; }
  .lab-title { font-size: 16px; font-weight: bold; color: #0284c7; margin: 0; }
  .lab-sub { font-size: 10px; color: #64748b; margin-top: 2px; }
  .demo-box { width: 100%; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 4px; padding: 8px; margin-bottom: 15px; }
  .demo-table { width: 100%; font-size: 10.5px; }
  .demo-table td { padding: 2px 4px; }
  .section-header { background: #e0f2fe; color: #0369a1; font-weight: bold; font-size: 11px; padding: 4px 8px; margin-top: 10px; border-left: 3px solid #0284c7; }
  .results-table { width: 100%; border-collapse: collapse; margin-top: 4px; margin-bottom: 10px; font-size: 10px; }
  .results-table th { background: #f1f5f9; color: #334155; text-align: left; padding: 5px; border-bottom: 1px solid #cbd5e1; font-size: 9.5px; text-transform: uppercase; }
  .results-table td { padding: 5px; border-bottom: 1px solid #e2e8f0; }
  .abnormal-val { font-weight: bold; color: #dc2626; }
  .normal-val { font-weight: bold; color: #0f172a; }
  .remarks-box { background: #f8fafc; border: 1px solid #e2e8f0; padding: 6px 10px; margin-top: 10px; font-size: 10px; }
  .footer-table { width: 100%; margin-top: 20px; border-top: 1px solid #cbd5e1; padding-top: 10px; font-size: 9.5px; }
  .end-marker { text-align: center; font-size: 8.5px; color: #94a3b8; margin-top: 15px; border-top: 1px dashed #cbd5e1; padding-top: 5px; }
</style>
</head>
<body>

  <!-- Header -->
  <table class="header-table">
    <tr>
      <td style="width: 70%;">
        <div class="lab-title"><?= htmlspecialchars($labName) ?></div>
        <div class="lab-sub"><?= htmlspecialchars($labTagline) ?></div>
        <div style="font-size: 9px; color: #475569; margin-top: 3px;">
          <?= htmlspecialchars($labAddress) ?> | Tel: <?= htmlspecialchars($labPhone) ?> | <?= htmlspecialchars($labEmail) ?>
        </div>
      </td>
      <td style="width: 30%; text-align: right; vertical-align: top;">
        <div style="font-size: 9px; font-weight: bold; color: #0369a1;"><?= htmlspecialchars($accreditation) ?></div>
        <div style="font-size: 8px; color: #64748b; margin-top: 2px;">ISO 15189:2022 Certified</div>
      </td>
    </tr>
  </table>

  <!-- Patient Demographic Header -->
  <div class="demo-box">
    <table class="demo-table">
      <tr>
        <td style="width: 50%;"><strong>Patient Name:</strong> <span style="font-size: 12px; font-weight: bold;"><?= htmlspecialchars($patient['full_name']) ?></span></td>
        <td style="width: 50%; text-align: right;"><strong>Report Number:</strong> <span style="font-family: monospace; font-weight: bold; color: #0284c7;"><?= htmlspecialchars($report['report_number']) ?></span></td>
      </tr>
      <tr>
        <td><strong>Patient ID:</strong> <?= htmlspecialchars($patient['patient_id']) ?> | <strong>Age/Sex:</strong> <?= $patient['age'] ?> <?= htmlspecialchars($patient['age_unit']) ?> / <?= htmlspecialchars($patient['gender']) ?></td>
        <td style="text-align: right;"><strong>Accession No:</strong> <?= htmlspecialchars($order['accession_number']) ?></td>
      </tr>
      <tr>
        <td><strong>Referring Doctor:</strong> <?= htmlspecialchars($order['doctor_name'] ?: 'Direct Walk-in / Self') ?></td>
        <td style="text-align: right;"><strong>Order Date:</strong> <?= date('d M Y', strtotime($order['order_date'])) ?></td>
      </tr>
      <tr>
        <td><strong>Mobile:</strong> <?= htmlspecialchars($patient['phone']) ?></td>
        <td style="text-align: right;"><strong>Report Date:</strong> <?= date('d M Y, h:i A', strtotime($report['approved_at'] ?: $report['created_at'])) ?></td>
      </tr>
    </table>
  </div>

  <!-- Investigation Results -->
  <?php foreach ($orderItems as $item): ?>
    <div class="section-header">
      <?= htmlspecialchars($item['test_name']) ?>
      <span style="font-size: 9px; font-weight: normal; color: #64748b;">(Specimen: <?= htmlspecialchars($item['specimen_type']) ?>)</span>
    </div>

    <table class="results-table">
      <thead>
        <tr>
          <th style="width: 38%;">Investigation Parameter</th>
          <th style="width: 18%; text-align: center;">Observed Value</th>
          <th style="width: 14%; text-align: center;">Unit</th>
          <th style="width: 30%; text-align: center;">Reference Interval</th>
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
              [$r['parameter_id'], $patient['gender'], (int)$patient['age'], (int)$patient['age'], $patient['gender']]
          );
          $dispRange = $intervalRow['display_interval'] ?? ($r['unit'] ? 'Standard' : 'Normal');
          $isAbnormal = $r['flag'] !== 'NORMAL';
          ?>
          <tr>
            <td><?= htmlspecialchars($r['parameter_name']) ?></td>
            <td style="text-align: center;" class="<?= $isAbnormal ? 'abnormal-val' : 'normal-val' ?>">
              <?= htmlspecialchars($r['result_value']) ?>
              <?= $isAbnormal ? ' *' : '' ?>
            </td>
            <td style="text-align: center; color: #64748b;"><?= htmlspecialchars($r['unit'] ?: '-') ?></td>
            <td style="text-align: center; color: #334155;"><?= htmlspecialchars($dispRange) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endforeach; ?>

  <!-- Remarks -->
  <?php if (!empty($report['clinical_remarks']) || !empty($report['interpretation'])): ?>
    <div class="remarks-box">
      <?php if (!empty($report['clinical_remarks'])): ?>
        <div><strong>Clinical Remarks:</strong> <?= nl2br(htmlspecialchars($report['clinical_remarks'])) ?></div>
      <?php endif; ?>
      <?php if (!empty($report['interpretation'])): ?>
        <div style="margin-top: 3px;"><strong>Interpretation:</strong> <?= nl2br(htmlspecialchars($report['interpretation'])) ?></div>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <!-- Signatures & Verification -->
  <table class="footer-table">
    <tr>
      <td style="width: 35%; vertical-align: bottom;">
        <div style="font-weight: bold; color: #0369a1;">Verified Online Medical Report</div>
        <div style="font-size: 8.5px; color: #64748b;">Verification Token: <?= substr($report['verification_token'], 0, 16) ?>...</div>
        <div style="font-size: 8px; color: #94a3b8;"><?= htmlspecialchars($qrUrl) ?></div>
      </td>
      <td style="width: 30%; text-align: center; vertical-align: bottom;">
        <div style="font-size: 9.5px; color: #64748b;">Checked & Processed by:</div>
        <div style="font-weight: bold; font-size: 10px;">Laboratory Technologist</div>
      </td>
      <td style="width: 35%; text-align: right; vertical-align: bottom;">
        <?php if ($report['report_status'] === 'Approved'): ?>
          <div style="font-family: Georgia, serif; font-size: 13px; font-style: italic; color: #0284c7; font-weight: bold;">
            Dr. Rajesh Sharma
          </div>
          <div style="font-weight: bold; font-size: 10.5px;"><?= htmlspecialchars($pathologist['full_name'] ?? 'Dr. Rajesh Sharma') ?></div>
          <div style="font-size: 8.5px; color: #64748b;"><?= htmlspecialchars($pathologist['qualification'] ?? 'MBBS, MD (Pathology)') ?></div>
          <div style="font-size: 8.5px; color: #64748b;">Reg. No: <?= htmlspecialchars($pathologist['registration_number'] ?? 'MCI-48291') ?></div>
        <?php else: ?>
          <div style="color: #f59e0b; font-weight: bold;">* PENDING MEDICAL APPROVAL *</div>
        <?php endif; ?>
      </td>
    </tr>
  </table>

  <div class="end-marker">
    *** END OF REPORT *** • <?= htmlspecialchars(Settings::get('pdf_disclaimer', 'This medical report is based on the specimen received and processed using calibrated clinical laboratory standards.')) ?>
  </div>

</body>
</html>
<?php
$html = ob_get_clean();

// Initialize Dompdf
$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true);
$options->set('defaultFont', 'Helvetica');

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

// Stream PDF
$filename = $report['report_number'] . '_' . preg_replace('/[^a-zA-Z0-9]/', '_', $patient['full_name']) . '.pdf';
$dompdf->stream($filename, ['Attachment' => false]);
exit;

<?php
/**
 * LabMaster - Patient Health Portal Login
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';

// Redirect if already logged in as patient
if (!empty($_SESSION['patient']['id'])) {
    header('Location: ' . PORTAL_URL . '/index.php');
    exit;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $phone = trim($_POST['phone'] ?? '');
    $patientCode = trim($_POST['patient_id'] ?? '');

    if (empty($phone) || empty($patientCode)) {
        $error = 'Please enter your registered mobile number and Patient ID.';
    } else {
        $patient = Database::fetch(
            "SELECT * FROM `patients` WHERE `phone` = ? AND `patient_id` = ? LIMIT 1",
            [$phone, $patientCode]
        );

        if ($patient) {
            session_regenerate_id(true);
            $_SESSION['patient'] = [
                'id'         => (int)$patient['id'],
                'patient_id' => $patient['patient_id'],
                'full_name'  => $patient['full_name'],
                'phone'      => $patient['phone'],
                'gender'     => $patient['gender'],
                'age'        => $patient['age']
            ];

            log_audit('Patient Logged In', 'Portal', $patient['patient_id'], "Patient {$patient['full_name']} logged in to patient portal.");
            set_flash('success', "Welcome, {$patient['full_name']}! You can now access your diagnostic reports and invoices.");
            header('Location: ' . PORTAL_URL . '/index.php');
            exit;
        } else {
            $error = 'No patient record matched this Mobile Number and Patient ID combination. Please check your billing slip or SMS.';
        }
    }
}

$labName = Settings::get('lab_name', 'LabMaster Diagnostic & Research Center');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Patient Health Portal Login | <?= e($labName) ?></title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/style.css">

  <style>
    body {
      background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 1.5rem;
    }
    .portal-card {
      background: #ffffff;
      border-radius: 16px;
      box-shadow: 0 15px 35px rgba(2, 132, 199, 0.12);
      width: 100%;
      max-width: 440px;
      overflow: hidden;
      border: 1px solid #bae6fd;
    }
    .portal-header {
      background: linear-gradient(135deg, #0284c7 0%, #06b6d4 100%);
      color: #ffffff;
      padding: 2.25rem 2rem;
      text-align: center;
    }
  </style>
</head>
<body>

<div class="portal-card">
  <div class="portal-header">
    <div class="d-inline-flex p-3 rounded-circle bg-white text-primary mb-2 shadow-sm">
      <i class="bi bi-person-heart fs-3"></i>
    </div>
    <h2 class="h5 fw-bold mb-1">Patient Health Portal</h2>
    <p class="small text-white-50 mb-0"><?= e($labName) ?></p>
  </div>

  <div class="p-4">
    <?= render_flash() ?>

    <?php if ($error): ?>
      <div class="alert alert-danger d-flex align-items-center gap-2 small mb-3">
        <i class="bi bi-exclamation-triangle-fill fs-5"></i>
        <div><?= e($error) ?></div>
      </div>
    <?php endif; ?>

    <p class="small text-muted mb-4 text-center">
      Enter your registered mobile number and Patient ID (from your receipt or SMS) to access your diagnostic health records.
    </p>

    <form method="POST" action="">
      <?= csrf_field() ?>

      <div class="mb-3">
        <label class="form-label small fw-semibold">Registered Mobile Number <span class="text-danger">*</span></label>
        <div class="input-group">
          <span class="input-group-text bg-light"><i class="bi bi-phone"></i></span>
          <input type="tel" name="phone" class="form-control" placeholder="e.g. 9812345670" required autofocus>
        </div>
      </div>

      <div class="mb-3">
        <label class="form-label small fw-semibold">Patient ID <span class="text-danger">*</span></label>
        <div class="input-group">
          <span class="input-group-text bg-light"><i class="bi bi-upc"></i></span>
          <input type="text" name="patient_id" class="form-control font-monospace" placeholder="e.g. LM-P-2026-0001" required>
        </div>
      </div>

      <div class="d-grid mt-4">
        <button type="submit" class="btn btn-primary-lm py-2">
          <i class="bi bi-shield-lock me-1"></i> Access My Health Records
        </button>
      </div>
    </form>

    <div class="mt-4 pt-3 border-top text-center">
      <div class="small text-muted mb-2">Need to download a report without login?</div>
      <a href="<?= BASE_URL ?>/download-report.php" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-file-earmark-pdf me-1"></i> Quick Report Download
      </a>
    </div>

    <div class="text-center mt-3">
      <a href="<?= BASE_URL ?>/index.php" class="small text-decoration-none text-muted">
        &larr; Back to Laboratory Home
      </a>
    </div>
  </div>
</div>

</body>
</html>

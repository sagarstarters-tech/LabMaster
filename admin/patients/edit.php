<?php
/**
 * LabMaster - Edit Patient Information
 */

declare(strict_types=1);

$pageTitle = 'Edit Patient Record';
$activeNav = 'patients';

require_once dirname(__DIR__) . '/includes/auth_guard.php';
Auth::requirePermission('patients.edit');

$patientId = (int)($_GET['id'] ?? 0);
$patient = Database::fetch("SELECT * FROM `patients` WHERE `id` = ?", [$patientId]);

if (!$patient) {
    set_flash('danger', 'Patient record not found.');
    header('Location: ' . ADMIN_URL . '/patients/index.php');
    exit;
}

$doctors = Database::fetchAll("SELECT * FROM `doctors` WHERE `is_active` = 1 ORDER BY `name` ASC");
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $fullName = trim($_POST['full_name'] ?? '');
    $gender = trim($_POST['gender'] ?? '');
    $age = (int)($_POST['age'] ?? 0);
    $ageUnit = trim($_POST['age_unit'] ?? 'Years');
    $dob = !empty($_POST['dob']) ? trim($_POST['dob']) : null;
    $phone = trim($_POST['phone'] ?? '');
    $email = !empty($_POST['email']) ? trim($_POST['email']) : null;
    $bloodGroup = !empty($_POST['blood_group']) ? trim($_POST['blood_group']) : null;
    $address = !empty($_POST['address']) ? trim($_POST['address']) : null;
    $referringDoctorId = !empty($_POST['referring_doctor_id']) ? (int)$_POST['referring_doctor_id'] : null;
    $medicalHistory = !empty($_POST['medical_history']) ? trim($_POST['medical_history']) : null;

    if (empty($fullName)) {
        $errors[] = 'Patient full name is required.';
    }
    if (empty($gender)) {
        $errors[] = 'Please select a valid gender.';
    }
    if ($age <= 0 && empty($dob)) {
        $errors[] = 'Please provide valid patient age or date of birth.';
    }
    if (empty($phone)) {
        $errors[] = 'A valid mobile number is required.';
    }

    if (empty($errors)) {
        Database::execute(
            "UPDATE `patients` 
             SET `full_name` = ?, `gender` = ?, `dob` = ?, `age` = ?, `age_unit` = ?, 
                 `phone` = ?, `email` = ?, `address` = ?, `blood_group` = ?, 
                 `referring_doctor_id` = ?, `medical_history` = ?
             WHERE `id` = ?",
            [$fullName, $gender, $dob, $age, $ageUnit, $phone, $email, $address, $bloodGroup, $referringDoctorId, $medicalHistory, $patientId]
        );

        $oldDocId = (int)($patient['referring_doctor_id'] ?? 0);
        $newDocId = (int)($referringDoctorId ?? 0);
        if ($oldDocId !== $newDocId) {
            $oldDocName = $oldDocId > 0 ? (Database::fetch("SELECT name FROM `doctors` WHERE `id` = ?", [$oldDocId])['name'] ?? 'Doctor #' . $oldDocId) : 'Direct Walk-in';
            $newDocName = $newDocId > 0 ? (Database::fetch("SELECT name FROM `doctors` WHERE `id` = ?", [$newDocId])['name'] ?? 'Doctor #' . $newDocId) : 'Direct Walk-in';
            log_audit('Doctor Association Changed', 'Patients', $patient['patient_id'], "Referring doctor changed from {$oldDocName} to {$newDocName}.");
        }

        log_audit('Patient Updated', 'Patients', $patient['patient_id'], "Updated details for patient {$fullName}.");
        set_flash('success', "Patient profile updated successfully.");
        header('Location: ' . ADMIN_URL . '/patients/view.php?id=' . $patientId);
        exit;
    }
}

require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="row justify-content-center">
  <div class="col-lg-10">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <div>
        <h2 class="h4 fw-bold mb-1">Edit Patient: <?= e($patient['full_name']) ?></h2>
        <p class="text-muted small mb-0">Record ID: <?= e($patient['patient_id']) ?></p>
      </div>
      <a href="<?= ADMIN_URL ?>/patients/view.php?id=<?= $patientId ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i> Back to Patient Record
      </a>
    </div>

    <?php if (!empty($errors)): ?>
      <div class="alert alert-danger shadow-sm mb-4">
        <ul class="mb-0 small ps-3">
          <?php foreach ($errors as $err): ?>
            <li><?= e($err) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <div class="admin-card">
      <div class="admin-card-body p-4">
        <form method="POST" action="">
          <?= csrf_field() ?>

          <div class="row g-3 mb-4">
            <div class="col-md-6">
              <label class="form-label small fw-semibold">Patient Full Name <span class="text-danger">*</span></label>
              <input type="text" name="full_name" class="form-control" value="<?= e($_POST['full_name'] ?? $patient['full_name']) ?>" required>
            </div>

            <div class="col-md-3">
              <label class="form-label small fw-semibold">Gender <span class="text-danger">*</span></label>
              <select name="gender" class="form-select" required>
                <?php $selGen = $_POST['gender'] ?? $patient['gender']; ?>
                <option value="Male" <?= $selGen === 'Male' ? 'selected' : '' ?>>Male</option>
                <option value="Female" <?= $selGen === 'Female' ? 'selected' : '' ?>>Female</option>
                <option value="Other" <?= $selGen === 'Other' ? 'selected' : '' ?>>Other</option>
              </select>
            </div>

            <div class="col-md-3">
              <label class="form-label small fw-semibold">Blood Group</label>
              <select name="blood_group" class="form-select">
                <option value="">Unknown</option>
                <?php 
                $selBg = $_POST['blood_group'] ?? $patient['blood_group'];
                foreach (['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'] as $bg): ?>
                  <option value="<?= $bg ?>" <?= $selBg === $bg ? 'selected' : '' ?>><?= $bg ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-md-3">
              <label class="form-label small fw-semibold">Age <span class="text-danger">*</span></label>
              <input type="number" name="age" class="form-control" value="<?= e((string)($_POST['age'] ?? $patient['age'])) ?>" required>
            </div>

            <div class="col-md-3">
              <label class="form-label small fw-semibold">Age Unit</label>
              <select name="age_unit" class="form-select">
                <?php $selUnit = $_POST['age_unit'] ?? $patient['age_unit']; ?>
                <option value="Years" <?= $selUnit === 'Years' ? 'selected' : '' ?>>Years</option>
                <option value="Months" <?= $selUnit === 'Months' ? 'selected' : '' ?>>Months</option>
                <option value="Days" <?= $selUnit === 'Days' ? 'selected' : '' ?>>Days</option>
              </select>
            </div>

            <div class="col-md-6">
              <label class="form-label small fw-semibold">Date of Birth</label>
              <input type="date" name="dob" class="form-control" value="<?= e($_POST['dob'] ?? $patient['dob']) ?>">
            </div>

            <div class="col-md-6">
              <label class="form-label small fw-semibold">Mobile Number <span class="text-danger">*</span></label>
              <input type="tel" name="phone" class="form-control" value="<?= e($_POST['phone'] ?? $patient['phone']) ?>" required>
            </div>

            <div class="col-md-6">
              <label class="form-label small fw-semibold">Email Address</label>
              <input type="email" name="email" class="form-control" value="<?= e($_POST['email'] ?? $patient['email']) ?>">
            </div>

            <div class="col-md-6">
              <label class="form-label small fw-semibold">Referring Doctor</label>
              <select name="referring_doctor_id" class="form-select">
                <option value="">Direct Walk-in / Self</option>
                <?php 
                $selDoc = (int)($_POST['referring_doctor_id'] ?? $patient['referring_doctor_id']);
                foreach ($doctors as $doc): ?>
                  <option value="<?= $doc['id'] ?>" <?= $selDoc === (int)$doc['id'] ? 'selected' : '' ?>>
                    <?= e($doc['name']) ?> (<?= e($doc['specialization'] ?: 'Physician') ?>)
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-md-6">
              <label class="form-label small fw-semibold">Residential Address</label>
              <textarea name="address" class="form-control" rows="2"><?= e($_POST['address'] ?? $patient['address']) ?></textarea>
            </div>

            <div class="col-12">
              <label class="form-label small fw-semibold">Clinical Notes / Known Medical History</label>
              <textarea name="medical_history" class="form-control" rows="2"><?= e($_POST['medical_history'] ?? $patient['medical_history']) ?></textarea>
            </div>
          </div>

          <div class="d-flex justify-content-between align-items-center">
            <a href="<?= ADMIN_URL ?>/patients/view.php?id=<?= $patientId ?>" class="btn btn-light">Cancel</a>
            <button type="submit" class="btn btn-update">
              <i class="bi bi-check2 me-1"></i> Update Patient Details
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>

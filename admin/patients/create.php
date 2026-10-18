<?php
/**
 * LabMaster - Register New Patient
 */

declare(strict_types=1);

$pageTitle = 'Register Patient';
$activeNav = 'patients';

require_once dirname(__DIR__) . '/includes/auth_guard.php';
Auth::requirePermission('patients.create');

$doctors = Database::fetchAll("SELECT * FROM `doctors` WHERE `is_active` = 1 ORDER BY `name` ASC");
$newPatientId = generate_patient_id();

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

    // Basic Validations
    if (empty($fullName)) {
        $errors[] = 'Patient full name is required.';
    }
    if (empty($gender) || !in_array($gender, ['Male', 'Female', 'Other'], true)) {
        $errors[] = 'Please select a valid gender.';
    }
    if ($age <= 0 && empty($dob)) {
        $errors[] = 'Please provide either patient age or date of birth.';
    }
    if (empty($phone) || strlen($phone) < 7) {
        $errors[] = 'A valid mobile contact number is required.';
    }

    // Duplicate Check: Check phone + name
    if (empty($errors)) {
        $existing = Database::fetch(
            "SELECT `id`, `patient_id`, `full_name` FROM `patients` WHERE `phone` = ? AND `full_name` = ? LIMIT 1",
            [$phone, $fullName]
        );

        if ($existing) {
            $errors[] = "A patient with the same name and mobile number already exists (ID: {$existing['patient_id']}).";
        }
    }

    if (empty($errors)) {
        // Calculate age from DOB if DOB provided and age not set
        if (!empty($dob) && $age <= 0) {
            $diff = (new DateTime())->diff(new DateTime($dob));
            $age = $diff->y > 0 ? $diff->y : ($diff->m > 0 ? $diff->m : $diff->d);
            $ageUnit = $diff->y > 0 ? 'Years' : ($diff->m > 0 ? 'Months' : 'Days');
        }

        // Generate secure patient portal password hash (default: phone number or temporary)
        $defaultPassword = password_hash($phone, PASSWORD_BCRYPT);

        Database::execute(
            "INSERT INTO `patients` (`patient_id`, `full_name`, `gender`, `dob`, `age`, `age_unit`, `phone`, `email`, `address`, `blood_group`, `referring_doctor_id`, `medical_history`, `password_hash`)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [$newPatientId, $fullName, $gender, $dob, $age, $ageUnit, $phone, $email, $address, $bloodGroup, $referringDoctorId, $medicalHistory, $defaultPassword]
        );

        $insertedId = Database::lastInsertId();
        log_audit('Patient Registered', 'Patients', $newPatientId, "Registered patient {$fullName} ({$age} {$ageUnit}/{$gender}).");

        set_flash('success', "Patient {$fullName} registered successfully with ID: {$newPatientId}");

        // Option to proceed immediately to order creation
        if (!empty($_POST['create_order'])) {
            header('Location: ' . ADMIN_URL . '/orders/create.php?patient_id=' . $insertedId);
        } else {
            header('Location: ' . ADMIN_URL . '/patients/view.php?id=' . $insertedId);
        }
        exit;
    }
}

require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="row justify-content-center">
  <div class="col-lg-10">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <div>
        <h2 class="h4 fw-bold mb-1">Register New Patient</h2>
        <p class="text-muted small mb-0">Record patient demographics and clinical history</p>
      </div>
      <a href="<?= ADMIN_URL ?>/patients/index.php" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i> Back to Directory
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
      <div class="admin-card-header bg-light">
        <h3 class="admin-card-title text-primary">
          <i class="bi bi-person-vcard-fill"></i> Patient Profile Form
        </h3>
        <span class="badge bg-primary fs-6 font-monospace"><?= e($newPatientId) ?></span>
      </div>

      <div class="admin-card-body p-4">
        <form method="POST" action="">
          <?= csrf_field() ?>

          <div class="row g-3 mb-4">
            <!-- Full Name -->
            <div class="col-md-6">
              <label class="form-label small fw-semibold">Patient Full Name <span class="text-danger">*</span></label>
              <input type="text" name="full_name" class="form-control" placeholder="e.g. Ramesh Kumar Patel" value="<?= e($_POST['full_name'] ?? '') ?>" required>
            </div>

            <!-- Gender -->
            <div class="col-md-3">
              <label class="form-label small fw-semibold">Gender <span class="text-danger">*</span></label>
              <select name="gender" class="form-select" required>
                <option value="">Select Gender</option>
                <option value="Male" <?= ($_POST['gender'] ?? '') === 'Male' ? 'selected' : '' ?>>Male</option>
                <option value="Female" <?= ($_POST['gender'] ?? '') === 'Female' ? 'selected' : '' ?>>Female</option>
                <option value="Other" <?= ($_POST['gender'] ?? '') === 'Other' ? 'selected' : '' ?>>Other</option>
              </select>
            </div>

            <!-- Blood Group -->
            <div class="col-md-3">
              <label class="form-label small fw-semibold">Blood Group</label>
              <select name="blood_group" class="form-select">
                <option value="">Unknown / Not Tested</option>
                <?php foreach (['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'] as $bg): ?>
                  <option value="<?= $bg ?>" <?= ($_POST['blood_group'] ?? '') === $bg ? 'selected' : '' ?>><?= $bg ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <!-- Age & Unit -->
            <div class="col-md-3">
              <label class="form-label small fw-semibold">Age <span class="text-danger">*</span></label>
              <input type="number" name="age" class="form-control" placeholder="e.g. 35" min="0" max="130" value="<?= e($_POST['age'] ?? '') ?>" required>
            </div>

            <div class="col-md-3">
              <label class="form-label small fw-semibold">Age Unit</label>
              <select name="age_unit" class="form-select">
                <option value="Years" <?= ($_POST['age_unit'] ?? 'Years') === 'Years' ? 'selected' : '' ?>>Years</option>
                <option value="Months" <?= ($_POST['age_unit'] ?? '') === 'Months' ? 'selected' : '' ?>>Months</option>
                <option value="Days" <?= ($_POST['age_unit'] ?? '') === 'Days' ? 'selected' : '' ?>>Days</option>
              </select>
            </div>

            <!-- Date of Birth -->
            <div class="col-md-6">
              <label class="form-label small fw-semibold">Date of Birth (Optional)</label>
              <input type="date" name="dob" class="form-control" value="<?= e($_POST['dob'] ?? '') ?>">
            </div>

            <!-- Mobile Phone -->
            <div class="col-md-6">
              <label class="form-label small fw-semibold">Mobile Number <span class="text-danger">*</span></label>
              <div class="input-group">
                <span class="input-group-text bg-light"><i class="bi bi-phone"></i></span>
                <input type="tel" name="phone" class="form-control" placeholder="e.g. 9876543210" value="<?= e($_POST['phone'] ?? '') ?>" required>
              </div>
              <div class="form-text">Used for WhatsApp report delivery & patient login.</div>
            </div>

            <!-- Email Address -->
            <div class="col-md-6">
              <label class="form-label small fw-semibold">Email Address (Optional)</label>
              <div class="input-group">
                <span class="input-group-text bg-light"><i class="bi bi-envelope"></i></span>
                <input type="email" name="email" class="form-control" placeholder="e.g. patient@example.com" value="<?= e($_POST['email'] ?? '') ?>">
              </div>
            </div>

            <!-- Referring Doctor -->
            <div class="col-md-6">
              <label class="form-label small fw-semibold">Referring Doctor</label>
              <select name="referring_doctor_id" class="form-select">
                <option value="">Direct Walk-in / Self</option>
                <?php foreach ($doctors as $doc): ?>
                  <option value="<?= $doc['id'] ?>" <?= ((int)($_POST['referring_doctor_id'] ?? 0) === (int)$doc['id']) ? 'selected' : '' ?>>
                    <?= e($doc['name']) ?> (<?= e($doc['specialization'] ?: 'Physician') ?>)
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <!-- Residential Address -->
            <div class="col-md-6">
              <label class="form-label small fw-semibold">Residential Address</label>
              <textarea name="address" class="form-control" rows="2" placeholder="Street, Flat No, City, Pincode"><?= e($_POST['address'] ?? '') ?></textarea>
            </div>

            <!-- Clinical Notes / History -->
            <div class="col-12">
              <label class="form-label small fw-semibold">Clinical Notes / Known Medical History</label>
              <textarea name="medical_history" class="form-control" rows="2" placeholder="e.g. Known diabetic, hypertensive, pregnancy, current medications"><?= e($_POST['medical_history'] ?? '') ?></textarea>
            </div>
          </div>

          <hr class="my-4">

          <div class="d-flex justify-content-between align-items-center">
            <a href="<?= ADMIN_URL ?>/patients/index.php" class="btn btn-light">Cancel</a>
            <div class="d-flex gap-2">
              <button type="submit" name="save_only" value="1" class="btn btn-save">
                <i class="bi bi-save me-1"></i> Save Patient Profile
              </button>
              <button type="submit" name="create_order" value="1" class="btn btn-save">
                <i class="bi bi-file-earmark-plus me-1"></i> Save & Create Test Order &rarr;
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>

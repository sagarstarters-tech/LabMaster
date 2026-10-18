<?php
/**
 * LabMaster - Add / Edit Referring Doctor
 * Comprehensive validation, duplicate prevention, and compliance controls
 */

declare(strict_types=1);

$doctorId = (int)($_GET['id'] ?? 0);
$isEdit = $doctorId > 0;

$pageTitle = $isEdit ? 'Edit Referring Doctor' : 'Add Referring Doctor';
$activeNav = 'doctors';

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';

Auth::requirePermission($isEdit ? 'doctors.edit' : 'doctors.create');

$doctor = null;
if ($isEdit) {
    $doctor = Database::fetch("SELECT * FROM `doctors` WHERE `id` = ?", [$doctorId]);
    if (!$doctor) {
        set_flash('danger', 'Doctor profile not found in master registry.');
        header('Location: ' . ADMIN_URL . '/doctors/index.php');
        exit;
    }
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $name = trim($_POST['name'] ?? '');
    $doctorCode = strtoupper(trim($_POST['doctor_code'] ?? ''));
    $qualification = trim($_POST['qualification'] ?? '');
    $specialization = trim($_POST['specialization'] ?? '');
    $medicalRegNo = trim($_POST['medical_registration_number'] ?? '');
    $clinicHospital = trim($_POST['clinic_hospital'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    $notes = trim($_POST['notes'] ?? '');

    // Validation
    if (empty($name)) {
        $errors[] = 'Doctor full name is required.';
    }

    if (empty($doctorCode)) {
        $errors[] = 'Unique Doctor Code is required.';
    }

    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please provide a valid email address.';
    }

    if (!empty($phone) && !preg_match('/^[0-9+\s\-]{7,20}$/', $phone)) {
        $errors[] = 'Please provide a valid contact phone or mobile number.';
    }

    // Duplicate Prevention Checks
    // 1. Check duplicate doctor_code
    $codeCheckSql = "SELECT id, name FROM `doctors` WHERE `doctor_code` = ?" . ($isEdit ? " AND `id` != ?" : "");
    $codeCheckParams = $isEdit ? [$doctorCode, $doctorId] : [$doctorCode];
    $codeDuplicate = Database::fetch($codeCheckSql, $codeCheckParams);
    if ($codeDuplicate) {
        $errors[] = "Doctor Code '{$doctorCode}' is already registered to Dr. {$codeDuplicate['name']}. Please assign a unique code.";
    }

    // 2. Check duplicate Medical Registration Number (if supplied)
    if (!empty($medicalRegNo)) {
        $regCheckSql = "SELECT id, name, doctor_code FROM `doctors` WHERE `medical_registration_number` = ?" . ($isEdit ? " AND `id` != ?" : "");
        $regCheckParams = $isEdit ? [$medicalRegNo, $doctorId] : [$medicalRegNo];
        $regDuplicate = Database::fetch($regCheckSql, $regCheckParams);
        if ($regDuplicate) {
            $errors[] = "Medical Registration Number '{$medicalRegNo}' is already associated with Dr. {$regDuplicate['name']} ({$regDuplicate['doctor_code']}). Accidental duplicate registration prevented.";
        }
    }

    // 3. Check duplicate Phone / Mobile Number (if supplied)
    if (!empty($phone)) {
        $phoneCheckSql = "SELECT id, name, doctor_code FROM `doctors` WHERE `phone` = ?" . ($isEdit ? " AND `id` != ?" : "");
        $phoneCheckParams = $isEdit ? [$phone, $doctorId] : [$phone];
        $phoneDuplicate = Database::fetch($phoneCheckSql, $phoneCheckParams);
        if ($phoneDuplicate) {
            $errors[] = "Phone number '{$phone}' is already registered to Dr. {$phoneDuplicate['name']} ({$phoneDuplicate['doctor_code']}). Please verify physician identity to prevent duplicate records.";
        }
    }

    // 4. Check duplicate Name + Clinic/Hospital combination
    if (!empty($name) && !empty($clinicHospital)) {
        $nameClinicSql = "SELECT id, doctor_code FROM `doctors` WHERE LOWER(TRIM(name)) = LOWER(?) AND LOWER(TRIM(clinic_hospital)) = LOWER(?)" . ($isEdit ? " AND `id` != ?" : "");
        $nameClinicParams = $isEdit ? [$name, $clinicHospital, $doctorId] : [$name, $clinicHospital];
        $nameClinicDuplicate = Database::fetch($nameClinicSql, $nameClinicParams);
        if ($nameClinicDuplicate) {
            $errors[] = "A doctor record for '{$name}' at '{$clinicHospital}' already exists with code ({$nameClinicDuplicate['doctor_code']}). Duplicate creation prevented.";
        }
    }

    if (empty($errors)) {
        if ($isEdit) {
            Database::execute(
                "UPDATE `doctors` 
                 SET `doctor_code` = ?, `name` = ?, `degree` = ?, `qualification` = ?, 
                     `specialization` = ?, `medical_registration_number` = ?, 
                     `clinic_hospital` = ?, `address` = ?, `phone` = ?, `email` = ?, 
                     `commission_rate` = 0.00, `notes` = ?, `is_active` = ?
                 WHERE `id` = ?",
                [
                    $doctorCode, $name, $qualification, $qualification,
                    $specialization, $medicalRegNo,
                    $clinicHospital, $address, $phone, $email,
                    $notes, $isActive, $doctorId
                ]
            );
            log_audit('Doctor Profile Updated', 'Doctors', $doctorCode, "Updated doctor details for {$name} (Code: {$doctorCode}).");
            set_flash('success', "Doctor {$name} profile updated successfully.");
            header('Location: ' . ADMIN_URL . '/doctors/view.php?id=' . $doctorId);
            exit;
        } else {
            Database::execute(
                "INSERT INTO `doctors` 
                 (`doctor_code`, `name`, `degree`, `qualification`, `specialization`, `medical_registration_number`, 
                  `clinic_hospital`, `address`, `phone`, `email`, `commission_rate`, `notes`, `is_active`)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0.00, ?, ?)",
                [
                    $doctorCode, $name, $qualification, $qualification, $specialization, $medicalRegNo,
                    $clinicHospital, $address, $phone, $email, $notes, $isActive
                ]
            );
            $newId = (int)Database::getInstance()->lastInsertId();
            log_audit('Doctor Created', 'Doctors', $doctorCode, "Registered new physician {$name} (Code: {$doctorCode}).");
            set_flash('success', "Doctor {$name} registered successfully with code {$doctorCode}.");
            header('Location: ' . ADMIN_URL . '/doctors/view.php?id=' . $newId);
            exit;
        }
    }
}

// Generate unique code suggestion for new doctor
if (!$isEdit) {
    $nextNum = (int)(Database::fetch("SELECT MAX(id) as m FROM `doctors`")['m'] ?? 0) + 1;
    $defaultCode = sprintf('DOC-%03d', $nextNum);
} else {
    $defaultCode = $doctor['doctor_code'];
}

require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="row justify-content-center">
  <div class="col-lg-9 col-xl-8">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <div>
        <h2 class="h4 fw-bold mb-1">
          <i class="bi bi-person-badge text-primary me-2"></i><?= $isEdit ? 'Edit Referring Doctor Profile' : 'Register New Referring Doctor' ?>
        </h2>
        <p class="text-muted small mb-0">Manage doctor credentials, medical council registration, affiliations, and status</p>
      </div>
      <div class="d-flex gap-2">
        <?php if ($isEdit): ?>
          <a href="<?= ADMIN_URL ?>/doctors/view.php?id=<?= $doctorId ?>" class="btn btn-outline-info btn-sm">
            <i class="bi bi-eye me-1"></i> View Profile
          </a>
        <?php endif; ?>
        <a href="<?= ADMIN_URL ?>/doctors/index.php" class="btn btn-outline-secondary btn-sm">
          <i class="bi bi-arrow-left me-1"></i> Back to Doctors
        </a>
      </div>
    </div>

    <!-- Duplicate Detection & Error Alerts -->
    <?php if (!empty($errors)): ?>
      <div class="alert alert-danger shadow-sm mb-4 border-start border-4 border-danger">
        <div class="fw-bold mb-2 d-flex align-items-center gap-2">
          <i class="bi bi-exclamation-octagon-fill"></i> Validation & Duplicate Detection Notice:
        </div>
        <ul class="mb-0 small ps-3">
          <?php foreach ($errors as $err): ?>
            <li><?= e($err) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <!-- Legal Compliance Alert -->
    <div class="alert alert-light border border-secondary border-opacity-25 shadow-sm p-3 mb-4 rounded-3 d-flex align-items-start gap-3">
      <i class="bi bi-info-circle text-primary fs-4 mt-1"></i>
      <div class="small text-secondary">
        <strong class="text-dark">Lawful Payment Architecture:</strong>
        Per National Medical Commission (NMC) regulations, doctors cannot receive referral kickbacks or percentage cuts from patient billing. All lawful honorariums, second-opinion consultation fees, and retainerships must be governed under formal <strong>Service Agreements</strong> created in the Reconciliation module.
      </div>
    </div>

    <div class="admin-card">
      <div class="admin-card-body p-4">
        <form method="POST" action="">
          <?= csrf_field() ?>

          <h5 class="fw-bold text-dark border-bottom pb-2 mb-3">1. Identification & Credentials</h5>
          <div class="row g-3 mb-4">
            <div class="col-md-4">
              <label class="form-label small fw-semibold">Doctor Code <span class="text-danger">*</span></label>
              <input type="text" name="doctor_code" class="form-control font-monospace" 
                     placeholder="e.g. DOC-005" 
                     value="<?= e($_POST['doctor_code'] ?? $defaultCode) ?>" required>
              <small class="text-muted" style="font-size:0.75rem;">Unique alphanumeric identifier</small>
            </div>

            <div class="col-md-8">
              <label class="form-label small fw-semibold">Doctor Full Name <span class="text-danger">*</span></label>
              <input type="text" name="name" class="form-control" 
                     placeholder="e.g. Dr. Ramesh Chander Gupta" 
                     value="<?= e($_POST['name'] ?? ($doctor['name'] ?? '')) ?>" required>
            </div>

            <div class="col-md-6">
              <label class="form-label small fw-semibold">Qualifications & Degrees</label>
              <input type="text" name="qualification" class="form-control" 
                     placeholder="e.g. MBBS, MD (Pathology), DNB" 
                     value="<?= e($_POST['qualification'] ?? ($doctor['qualification'] ?? ($doctor['degree'] ?? ''))) ?>">
            </div>

            <div class="col-md-6">
              <label class="form-label small fw-semibold">Specialization</label>
              <input type="text" name="specialization" class="form-control" 
                     placeholder="e.g. Consultant Pathologist, Internal Medicine" 
                     value="<?= e($_POST['specialization'] ?? ($doctor['specialization'] ?? '')) ?>">
            </div>

            <div class="col-md-12">
              <label class="form-label small fw-semibold">Medical Registration Number</label>
              <div class="input-group">
                <span class="input-group-text bg-light text-muted"><i class="bi bi-shield-shaded"></i></span>
                <input type="text" name="medical_registration_number" class="form-control font-monospace" 
                       placeholder="e.g. MCI-2015-84920 or DMC-49210" 
                       value="<?= e($_POST['medical_registration_number'] ?? ($doctor['medical_registration_number'] ?? '')) ?>">
              </div>
              <small class="text-muted" style="font-size:0.75rem;">State Medical Council or National Medical Commission registration ID</small>
            </div>
          </div>

          <h5 class="fw-bold text-dark border-bottom pb-2 mb-3">2. Hospital Affiliation & Practice Location</h5>
          <div class="row g-3 mb-4">
            <div class="col-md-12">
              <label class="form-label small fw-semibold">Clinic / Hospital Name</label>
              <div class="input-group">
                <span class="input-group-text bg-light text-muted"><i class="bi bi-hospital"></i></span>
                <input type="text" name="clinic_hospital" class="form-control" 
                       placeholder="e.g. Max Healthcare / City Multi-Specialty Clinic" 
                       value="<?= e($_POST['clinic_hospital'] ?? ($doctor['clinic_hospital'] ?? '')) ?>">
              </div>
            </div>

            <div class="col-12">
              <label class="form-label small fw-semibold">Clinic / Practice Address</label>
              <textarea name="address" class="form-control" rows="2" 
                        placeholder="Street address, Suite/Room, City, State, PIN"><?= e($_POST['address'] ?? ($doctor['address'] ?? '')) ?></textarea>
            </div>
          </div>

          <h5 class="fw-bold text-dark border-bottom pb-2 mb-3">3. Communication & Contact Details</h5>
          <div class="row g-3 mb-4">
            <div class="col-md-6">
              <label class="form-label small fw-semibold">Contact Mobile Phone</label>
              <div class="input-group">
                <span class="input-group-text bg-light text-muted"><i class="bi bi-telephone"></i></span>
                <input type="tel" name="phone" class="form-control" 
                       placeholder="e.g. 9811122334" 
                       value="<?= e($_POST['phone'] ?? ($doctor['phone'] ?? '')) ?>">
              </div>
              <small class="text-muted" style="font-size:0.75rem;">Duplicate detection active on phone number</small>
            </div>

            <div class="col-md-6">
              <label class="form-label small fw-semibold">Email Address</label>
              <div class="input-group">
                <span class="input-group-text bg-light text-muted"><i class="bi bi-envelope"></i></span>
                <input type="email" name="email" class="form-control" 
                       placeholder="e.g. doctor@hospital.com" 
                       value="<?= e($_POST['email'] ?? ($doctor['email'] ?? '')) ?>">
              </div>
            </div>
          </div>

          <h5 class="fw-bold text-dark border-bottom pb-2 mb-3">4. Operational Status & Internal Notes</h5>
          <div class="row g-3 mb-4">
            <div class="col-md-6">
              <label class="form-label small fw-semibold">Referring Partner Status</label>
              <div class="form-check form-switch mt-2">
                <input class="form-check-input" type="checkbox" id="isActiveSwitch" name="is_active" value="1" 
                       <?= (!isset($doctor) || $doctor['is_active']) ? 'checked' : '' ?>>
                <label class="form-check-label fw-semibold small" for="isActiveSwitch">
                  Active Physician (Eligible for Patient Referrals & Service Agreements)
                </label>
              </div>
            </div>

            <div class="col-md-6">
              <label class="form-label small fw-semibold text-muted">Referral Commission Rate (%)</label>
              <div class="input-group">
                <input type="text" class="form-control bg-light text-muted" value="0.00% (Prohibited by Medical Ethics)" disabled>
                <span class="input-group-text bg-light text-danger"><i class="bi bi-lock-fill"></i></span>
              </div>
              <small class="text-muted" style="font-size:0.75rem;">Disabled per ethics regulations. Use Service Agreements for lawful payments.</small>
            </div>

            <div class="col-12">
              <label class="form-label small fw-semibold">Internal Practice Notes</label>
              <textarea name="notes" class="form-control" rows="2" 
                        placeholder="Internal communication notes, preferred sample timings, reporting instructions..."><?= e($_POST['notes'] ?? ($doctor['notes'] ?? '')) ?></textarea>
            </div>

            <?php if ($isEdit): ?>
              <div class="col-12">
                <div class="p-3 bg-light rounded border text-muted small d-flex flex-wrap gap-4">
                  <div><strong>Registered On:</strong> <?= format_date($doctor['created_at']) ?></div>
                  <div><strong>Last Updated:</strong> <?= !empty($doctor['updated_at']) ? format_date($doctor['updated_at']) : 'N/A' ?></div>
                  <div><strong>System ID:</strong> #<?= $doctor['id'] ?></div>
                </div>
              </div>
            <?php endif; ?>
          </div>

          <div class="d-flex justify-content-between align-items-center pt-3 border-top">
            <a href="<?= ADMIN_URL ?>/doctors/index.php" class="btn btn-light border">Cancel</a>
            <button type="submit" class="btn <?= $isEdit ? 'btn-update' : 'btn-save' ?>">
              <i class="bi bi-check2 me-1"></i> <?= $isEdit ? 'Save Changes' : 'Register Doctor' ?>
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>

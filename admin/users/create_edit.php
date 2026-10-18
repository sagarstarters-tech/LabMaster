<?php
/**
 * LabMaster - Create or Edit Staff User
 */

declare(strict_types=1);

$userId = (int)($_GET['id'] ?? 0);
$isEdit = $userId > 0;

$pageTitle = $isEdit ? 'Edit Staff Member' : 'Add Staff Member';
$activeNav = 'users';

require_once dirname(__DIR__) . '/includes/auth_guard.php';
Auth::requirePermission('users.manage');

$user = null;
if ($isEdit) {
    $user = Database::fetch("SELECT * FROM `users` WHERE `id` = ?", [$userId]);
    if (!$user) {
        set_flash('danger', 'Staff member not found.');
        header('Location: ' . ADMIN_URL . '/users/index.php');
        exit;
    }
}

$roles = Database::fetchAll("SELECT * FROM `roles` ORDER BY `id` ASC");
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $fullName = trim($_POST['full_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $roleId = (int)($_POST['role_id'] ?? 0);
    $phone = trim($_POST['phone'] ?? '');
    $designation = trim($_POST['designation'] ?? '');
    $qualification = trim($_POST['qualification'] ?? '');
    $regNumber = trim($_POST['registration_number'] ?? '');
    $password = $_POST['password'] ?? '';
    $isActive = isset($_POST['is_active']) ? 1 : 0;

    if (empty($fullName) || empty($username) || empty($email) || $roleId <= 0) {
        $errors[] = 'Full name, username, email, and role are required.';
    }

    if (!$isEdit && empty($password)) {
        $errors[] = 'Password is required for new staff accounts.';
    }

    // Check duplicate username or email
    if (empty($errors)) {
        $dupCheck = Database::fetch(
            "SELECT `id` FROM `users` WHERE (`username` = ? OR `email` = ?) AND `id` != ? LIMIT 1",
            [$username, $email, $userId]
        );
        if ($dupCheck) {
            $errors[] = 'Username or email already in use by another staff member.';
        }
    }

    if (empty($errors)) {
        if ($isEdit) {
            if (!empty($password)) {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                Database::execute(
                    "UPDATE `users` 
                     SET `full_name` = ?, `username` = ?, `email` = ?, `role_id` = ?, 
                         `phone` = ?, `designation` = ?, `qualification` = ?, `registration_number` = ?, 
                         `password_hash` = ?, `is_active` = ?
                     WHERE `id` = ?",
                    [$fullName, $username, $email, $roleId, $phone, $designation, $qualification, $regNumber, $hash, $isActive, $userId]
                );
            } else {
                Database::execute(
                    "UPDATE `users` 
                     SET `full_name` = ?, `username` = ?, `email` = ?, `role_id` = ?, 
                         `phone` = ?, `designation` = ?, `qualification` = ?, `registration_number` = ?, 
                         `is_active` = ?
                     WHERE `id` = ?",
                    [$fullName, $username, $email, $roleId, $phone, $designation, $qualification, $regNumber, $isActive, $userId]
                );
            }
            log_audit('Staff Updated', 'Staff', (string)$userId, "Updated profile for staff {$fullName}.");
            set_flash('success', "Staff member {$fullName} updated successfully.");
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            Database::execute(
                "INSERT INTO `users` (`role_id`, `username`, `email`, `password_hash`, `full_name`, `phone`, `designation`, `qualification`, `registration_number`, `is_active`)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [$roleId, $username, $email, $hash, $fullName, $phone, $designation, $qualification, $regNumber, $isActive]
            );
            $newId = Database::lastInsertId();
            log_audit('Staff Created', 'Staff', (string)$newId, "Added staff {$fullName} with username {$username}.");
            set_flash('success', "Staff member {$fullName} created successfully.");
        }

        header('Location: ' . ADMIN_URL . '/users/index.php');
        exit;
    }
}

require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="row justify-content-center">
  <div class="col-lg-8">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <div>
        <h2 class="h4 fw-bold mb-1"><?= $isEdit ? 'Edit Staff Member' : 'Add Staff Member' ?></h2>
        <p class="text-muted small mb-0">Manage credentials, medical qualifications, and access roles</p>
      </div>
      <a href="<?= ADMIN_URL ?>/users/index.php" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i> Back to Staff List
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
              <label class="form-label small fw-semibold">Staff Full Name <span class="text-danger">*</span></label>
              <input type="text" name="full_name" class="form-control" placeholder="e.g. Dr. Rajesh Sharma" value="<?= e($_POST['full_name'] ?? ($user['full_name'] ?? '')) ?>" required>
            </div>

            <div class="col-md-6">
              <label class="form-label small fw-semibold">Assigned Access Role <span class="text-danger">*</span></label>
              <select name="role_id" class="form-select" required>
                <option value="">-- Choose Role --</option>
                <?php foreach ($roles as $r): ?>
                  <option value="<?= $r['id'] ?>" <?= ((int)($_POST['role_id'] ?? ($user['role_id'] ?? 0)) === (int)$r['id']) ? 'selected' : '' ?>>
                    <?= e($r['name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-md-6">
              <label class="form-label small fw-semibold">Portal Username <span class="text-danger">*</span></label>
              <input type="text" name="username" class="form-control font-monospace" placeholder="e.g. dr.sharma" value="<?= e($_POST['username'] ?? ($user['username'] ?? '')) ?>" required>
            </div>

            <div class="col-md-6">
              <label class="form-label small fw-semibold">Email Address <span class="text-danger">*</span></label>
              <input type="email" name="email" class="form-control" placeholder="e.g. sharma@labmaster.com" value="<?= e($_POST['email'] ?? ($user['email'] ?? '')) ?>" required>
            </div>

            <div class="col-md-6">
              <label class="form-label small fw-semibold">Password <?= $isEdit ? '<span class="text-muted fw-normal">(Leave blank to keep unchanged)</span>' : '<span class="text-danger">*</span>' ?></label>
              <input type="password" name="password" class="form-control" placeholder="••••••••" <?= $isEdit ? '' : 'required' ?>>
            </div>

            <div class="col-md-6">
              <label class="form-label small fw-semibold">Contact Phone</label>
              <input type="tel" name="phone" class="form-control" placeholder="e.g. 9876543210" value="<?= e($_POST['phone'] ?? ($user['phone'] ?? '')) ?>">
            </div>

            <div class="col-md-6">
              <label class="form-label small fw-semibold">Official Designation</label>
              <input type="text" name="designation" class="form-control" placeholder="e.g. Consultant Pathologist" value="<?= e($_POST['designation'] ?? ($user['designation'] ?? '')) ?>">
            </div>

            <div class="col-md-6">
              <label class="form-label small fw-semibold">Medical Qualifications</label>
              <input type="text" name="qualification" class="form-control" placeholder="e.g. MBBS, MD (Pathology), DCP" value="<?= e($_POST['qualification'] ?? ($user['qualification'] ?? '')) ?>">
            </div>

            <div class="col-md-6">
              <label class="form-label small fw-semibold">Medical Council Reg. Number (for Signatories)</label>
              <input type="text" name="registration_number" class="form-control font-monospace" placeholder="e.g. MCI-48291" value="<?= e($_POST['registration_number'] ?? ($user['registration_number'] ?? '')) ?>">
            </div>

            <div class="col-md-6 d-flex align-items-center mt-4">
              <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" id="isActiveSwitch" name="is_active" value="1" <?= (!isset($user) || $user['is_active']) ? 'checked' : '' ?>>
                <label class="form-check-label fw-semibold small" for="isActiveSwitch">Active Account (Allowed login)</label>
              </div>
            </div>
          </div>

          <div class="d-flex justify-content-between align-items-center pt-3 border-top">
            <a href="<?= ADMIN_URL ?>/users/index.php" class="btn btn-light">Cancel</a>
            <button type="submit" class="btn <?= $isEdit ? 'btn-update' : 'btn-save' ?>">
              <i class="bi bi-check2 me-1"></i> <?= $isEdit ? 'Save Changes' : 'Create Staff Account' ?>
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>

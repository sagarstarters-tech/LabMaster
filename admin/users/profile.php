<?php
/**
 * LabMaster - User Profile & Digital Signature Upload
 */

declare(strict_types=1);

$pageTitle = 'My Profile & Signature';
$activeNav = '';

require_once dirname(__DIR__) . '/includes/auth_guard.php';

$userId = Auth::id();
$user = Database::fetch("SELECT * FROM `users` WHERE `id` = ?", [$userId]);

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $fullName = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (empty($fullName) || empty($email)) {
        $errors[] = 'Full name and email are required.';
    }

    if (!empty($newPassword)) {
        if ($newPassword !== $confirmPassword) {
            $errors[] = 'New passwords do not match.';
        } elseif (strlen($newPassword) < 6) {
            $errors[] = 'Password must be at least 6 characters.';
        }
    }

    // Handle Signature Image Upload if provided
    $signatureFilename = $user['signature_image'];
    if (isset($_FILES['signature_file']) && $_FILES['signature_file']['error'] === UPLOAD_ERR_OK) {
        $fileTmp = $_FILES['signature_file']['tmp_name'];
        $fileName = $_FILES['signature_file']['name'];
        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        if (!in_array($fileExt, ['png', 'jpg', 'jpeg'], true)) {
            $errors[] = 'Digital signature must be a PNG or JPG image file.';
        } else {
            $newFilename = 'sig_user_' . $userId . '_' . time() . '.' . $fileExt;
            $uploadTarget = BASE_PATH . '/storage/uploads/' . $newFilename;
            if (move_uploaded_file($fileTmp, $uploadTarget)) {
                $signatureFilename = $newFilename;
            } else {
                $errors[] = 'Failed to save digital signature file.';
            }
        }
    }

    if (empty($errors)) {
        if (!empty($newPassword)) {
            $hash = password_hash($newPassword, PASSWORD_BCRYPT);
            Database::execute(
                "UPDATE `users` SET `full_name` = ?, `email` = ?, `phone` = ?, `password_hash` = ?, `signature_image` = ? WHERE `id` = ?",
                [$fullName, $email, $phone, $hash, $signatureFilename, $userId]
            );
        } else {
            Database::execute(
                "UPDATE `users` SET `full_name` = ?, `email` = ?, `phone` = ?, `signature_image` = ? WHERE `id` = ?",
                [$fullName, $email, $phone, $signatureFilename, $userId]
            );
        }

        // Update session
        $_SESSION['user']['full_name'] = $fullName;
        $_SESSION['user']['email'] = $email;
        $_SESSION['user']['phone'] = $phone;
        $_SESSION['user']['signature_image'] = $signatureFilename;

        log_audit('Profile Updated', 'Staff', (string)$userId, "Updated profile credentials.");
        set_flash('success', 'Your profile and credentials have been updated.');
        header('Location: ' . ADMIN_URL . '/users/profile.php');
        exit;
    }
}

require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="row justify-content-center">
  <div class="col-lg-8">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <div>
        <h2 class="h4 fw-bold mb-1">My Staff Profile</h2>
        <p class="text-muted small mb-0">Role: <span class="badge bg-primary"><?= e($currentUser['role_name']) ?></span></p>
      </div>
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
        <form method="POST" action="" enctype="multipart/form-data">
          <?= csrf_field() ?>

          <div class="row g-3 mb-4">
            <div class="col-md-6">
              <label class="form-label small fw-semibold">Full Name <span class="text-danger">*</span></label>
              <input type="text" name="full_name" class="form-control" value="<?= e($user['full_name']) ?>" required>
            </div>

            <div class="col-md-6">
              <label class="form-label small fw-semibold">Username</label>
              <input type="text" class="form-control font-monospace bg-light" value="<?= e($user['username']) ?>" readonly>
            </div>

            <div class="col-md-6">
              <label class="form-label small fw-semibold">Email Address <span class="text-danger">*</span></label>
              <input type="email" name="email" class="form-control" value="<?= e($user['email']) ?>" required>
            </div>

            <div class="col-md-6">
              <label class="form-label small fw-semibold">Phone</label>
              <input type="tel" name="phone" class="form-control" value="<?= e($user['phone'] ?? '') ?>">
            </div>

            <div class="col-md-6">
              <label class="form-label small fw-semibold">New Password (Leave blank to keep current)</label>
              <input type="password" name="new_password" class="form-control" placeholder="••••••••">
            </div>

            <div class="col-md-6">
              <label class="form-label small fw-semibold">Confirm New Password</label>
              <input type="password" name="confirm_password" class="form-control" placeholder="••••••••">
            </div>

            <div class="col-12">
              <label class="form-label small fw-semibold">Digital Signature Image (PNG/JPG with transparent/white background)</label>
              <input type="file" name="signature_file" class="form-control" accept="image/png, image/jpeg">
              <div class="form-text">Used on approved medical reports if you are an authorized clinical pathologist.</div>
            </div>
          </div>

          <div class="text-end border-top pt-3">
            <button type="submit" class="btn btn-update">
              <i class="bi bi-check2 me-1"></i> Update Profile
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>

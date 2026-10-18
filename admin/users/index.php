<?php
/**
 * LabMaster - Staff & Role Management
 */

declare(strict_types=1);

$pageTitle = 'Staff Management';
$activeNav = 'users';

require_once dirname(__DIR__) . '/includes/auth_guard.php';
Auth::requirePermission('users.manage');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_user') {
    require_csrf();
    $userId = (int)($_POST['user_id'] ?? 0);
    if ($userId === (int)Auth::id()) {
        set_flash('danger', 'You cannot delete your own active administrator account.');
    } else {
        $uTarget = Database::fetch("SELECT * FROM `users` WHERE `id` = ?", [$userId]);
        if (!$uTarget) {
            set_flash('danger', 'Staff account not found.');
        } else {
            try {
                Database::execute("DELETE FROM `users` WHERE `id` = ?", [$userId]);
                log_audit('Staff Deleted', 'Users', $uTarget['username'], "Deleted staff account {$uTarget['username']} ({$uTarget['full_name']}).");
                set_flash('success', "Staff account \"{$uTarget['full_name']}\" deleted successfully.");
            } catch (\PDOException $e) {
                Database::execute("UPDATE `users` SET `is_active` = 0 WHERE `id` = ?", [$userId]);
                log_audit('Staff Deactivated', 'Users', $uTarget['username'], "Deactivated staff account {$uTarget['username']} (linked to clinical or audit records).");
                set_flash('warning', "Staff account \"{$uTarget['full_name']}\" is linked to clinical or audit records; account has been deactivated instead.");
            }
        }
    }
    header('Location: ' . ADMIN_URL . '/users/index.php');
    exit;
}

$users = Database::fetchAll(
    "SELECT u.*, r.name as role_name 
     FROM `users` u 
     JOIN `roles` r ON u.role_id = r.id 
     ORDER BY u.id ASC"
);

require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
  <div>
    <h2 class="h4 fw-bold mb-1">Laboratory Personnel & Access Roles</h2>
    <p class="text-muted small mb-0">Role-based access control (RBAC) for pathologists, technologists, and billing executives</p>
  </div>
  <div class="d-flex gap-2">
    <a href="<?= ADMIN_URL ?>/users/roles.php" class="btn btn-warning text-dark btn-sm fw-semibold shadow-sm">
      <i class="bi bi-shield-lock-fill me-1"></i> Role & Permissions Control
    </a>
    <a href="<?= ADMIN_URL ?>/users/create_edit.php" class="btn btn-primary-lm btn-sm">
      <i class="bi bi-person-plus-fill me-1"></i> Add Staff Member
    </a>
  </div>
</div>

<div class="admin-card">
  <div class="admin-card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>Staff Name & Username</th>
            <th>Role & Capabilities</th>
            <th>Designation & Qualification</th>
            <th>Contact Phone & Email</th>
            <th>Medical Reg. No</th>
            <th>Last Login</th>
            <th>Status</th>
            <th class="text-end text-nowrap pe-3" style="width: 140px;">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($users as $u): ?>
            <tr>
              <td>
                <div class="fw-bold text-dark"><?= e($u['full_name']) ?></div>
                <span class="badge bg-light text-muted border font-monospace">@<?= e($u['username']) ?></span>
              </td>
              <td>
                <a href="<?= ADMIN_URL ?>/users/roles.php?role=<?= $u['role_id'] ?>" class="text-decoration-none" title="View / Customize permissions for <?= e($u['role_name']) ?>">
                  <span class="badge bg-primary-subtle text-primary-emphasis border border-primary border-opacity-25">
                    <?= e($u['role_name']) ?> <i class="bi bi-sliders ms-1 small"></i>
                  </span>
                </a>
              </td>
              <td>
                <div class="text-dark small fw-semibold"><?= e($u['designation'] ?: 'N/A') ?></div>
                <div class="text-muted" style="font-size:0.75rem;"><?= e($u['qualification'] ?: '-') ?></div>
              </td>
              <td>
                <div class="small"><i class="bi bi-telephone me-1 text-muted"></i><?= e($u['phone'] ?: 'N/A') ?></div>
                <div class="text-muted" style="font-size:0.75rem;"><?= e($u['email']) ?></div>
              </td>
              <td>
                <span class="font-monospace small text-secondary"><?= e($u['registration_number'] ?: '-') ?></span>
              </td>
              <td class="small text-muted">
                <?= format_date($u['last_login'], true) ?>
              </td>
              <td>
                <span class="badge <?= $u['is_active'] ? 'bg-success-subtle text-success-emphasis' : 'bg-danger-subtle text-danger-emphasis' ?>">
                  <?= $u['is_active'] ? 'Active' : 'Deactivated' ?>
                </span>
              </td>
              <td class="text-end text-nowrap pe-3">
                <div class="d-inline-flex align-items-center justify-content-end gap-1">
                  <button type="button" class="btn btn-sm btn-outline-primary btn-action-icon" 
                          onclick="viewStaff(<?= htmlspecialchars(json_encode($u), ENT_QUOTES, 'UTF-8') ?>)" 
                          title="View Staff Profile">
                    <i class="bi bi-eye"></i>
                  </button>
                  <a href="<?= ADMIN_URL ?>/users/create_edit.php?id=<?= $u['id'] ?>" class="btn btn-sm btn-outline-secondary btn-action-icon" title="Edit Staff Profile">
                    <i class="bi bi-pencil"></i>
                  </a>
                  <?php if ((int)$u['id'] !== (int)Auth::id()): ?>
                    <form method="POST" action="<?= ADMIN_URL ?>/users/index.php" class="d-inline m-0 p-0" onsubmit="return confirm('Are you sure you want to delete or deactivate staff account <?= e(addslashes($u['full_name'])) ?>?');">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="delete_user">
                      <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                      <button type="submit" class="btn btn-sm btn-outline-danger btn-action-icon" title="Delete / Deactivate Account">
                        <i class="bi bi-trash"></i>
                      </button>
                    </form>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Modal: View Staff Member Details -->
<div class="modal fade" id="viewStaffModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content shadow border-0">
      <div class="modal-header bg-light">
        <h5 class="modal-title fw-bold" id="viewStaffName">Staff Profile</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <table class="table table-sm table-borderless mb-0">
          <tbody>
            <tr><th class="text-muted w-35">Role:</th><td id="viewStaffRole" class="fw-semibold"></td></tr>
            <tr><th class="text-muted">Username:</th><td id="viewStaffUsername" class="font-monospace"></td></tr>
            <tr><th class="text-muted">Designation:</th><td id="viewStaffDesignation"></td></tr>
            <tr><th class="text-muted">Qualification:</th><td id="viewStaffQualification"></td></tr>
            <tr><th class="text-muted">Phone:</th><td id="viewStaffPhone"></td></tr>
            <tr><th class="text-muted">Email:</th><td id="viewStaffEmail"></td></tr>
            <tr><th class="text-muted">Registration No:</th><td id="viewStaffReg"></td></tr>
            <tr><th class="text-muted">Status:</th><td id="viewStaffStatus"></td></tr>
            <tr><th class="text-muted">Last Login:</th><td id="viewStaffLastLogin"></td></tr>
          </tbody>
        </table>
      </div>
      <div class="modal-footer bg-light py-2">
        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<script>
function viewStaff(u) {
  document.getElementById('viewStaffName').innerText = u.full_name || 'Staff Profile';
  document.getElementById('viewStaffRole').innerText = u.role_name || '-';
  document.getElementById('viewStaffUsername').innerText = '@' + (u.username || '-');
  document.getElementById('viewStaffDesignation').innerText = u.designation || 'N/A';
  document.getElementById('viewStaffQualification').innerText = u.qualification || '-';
  document.getElementById('viewStaffPhone').innerText = u.phone || 'N/A';
  document.getElementById('viewStaffEmail').innerText = u.email || 'N/A';
  document.getElementById('viewStaffReg').innerText = u.registration_number || '-';
  document.getElementById('viewStaffStatus').innerHTML = u.is_active == 1 
    ? '<span class="badge bg-success-subtle text-success-emphasis">Active</span>' 
    : '<span class="badge bg-danger-subtle text-danger-emphasis">Deactivated</span>';
  document.getElementById('viewStaffLastLogin').innerText = u.last_login || 'Never';

  new bootstrap.Modal(document.getElementById('viewStaffModal')).show();
}
</script>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>

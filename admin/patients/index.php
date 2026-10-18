<?php
/**
 * LabMaster - Patients Master List & Search
 */

declare(strict_types=1);

$pageTitle = 'Patients Directory';
$activeNav = 'patients';

require_once dirname(__DIR__) . '/includes/auth_guard.php';
Auth::requirePermission('patients.view');

// Handle Search and Filter Query
$search = trim($_GET['search'] ?? '');
$gender = trim($_GET['gender'] ?? '');

$sql = "SELECT p.*, d.name as doctor_name, 
        (SELECT COUNT(*) FROM `test_orders` o WHERE o.patient_id = p.id) as total_orders
        FROM `patients` p 
        LEFT JOIN `doctors` d ON p.referring_doctor_id = d.id 
        WHERE 1=1";
$params = [];

if (!empty($search)) {
    $sql .= " AND (p.full_name LIKE ? OR p.patient_id LIKE ? OR p.phone LIKE ?)";
    $like = "%{$search}%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

if (!empty($gender) && in_array($gender, ['Male', 'Female', 'Other'], true)) {
    $sql .= " AND p.gender = ?";
    $params[] = $gender;
}

// Handle POST Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'delete_patient') {
        Auth::requirePermission('patients.edit');
        $patientId = (int)($_POST['patient_id'] ?? 0);
        $pat = Database::fetch("SELECT * FROM `patients` WHERE `id` = ?", [$patientId]);
        if (!$pat) {
            set_flash('danger', 'Patient not found.');
        } else {
            $orderCount = (int)Database::fetchColumn("SELECT COUNT(*) FROM `test_orders` WHERE `patient_id` = ?", [$patientId]);
            if ($orderCount > 0) {
                set_flash('warning', "Cannot delete patient \"{$pat['full_name']}\" because {$orderCount} clinical test orders exist. Records are protected under laboratory compliance.");
            } else {
                Database::execute("DELETE FROM `patients` WHERE `id` = ?", [$patientId]);
                log_audit('Patient Deleted', 'Patients', $pat['patient_id'], "Deleted patient {$pat['full_name']}.");
                set_flash('success', "Patient record for \"{$pat['full_name']}\" deleted successfully.");
            }
            header('Location: ' . ADMIN_URL . '/patients/index.php');
            exit;
        }
    }
}

$sql .= " ORDER BY p.id DESC";
$patients = Database::fetchAll($sql, $params);

require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
  <div>
    <h2 class="h4 fw-bold mb-1">Registered Patients</h2>
    <p class="text-muted small mb-0">Total <?= count($patients) ?> patients found in records</p>
  </div>
  <?php if (Auth::can('patients.create')): ?>
  <a href="<?= ADMIN_URL ?>/patients/create.php" class="btn btn-primary-lm d-inline-flex align-items-center gap-2">
    <i class="bi bi-person-plus-fill"></i> Register New Patient
  </a>
  <?php endif; ?>
</div>

<!-- Search & Filter Card -->
<div class="admin-card mb-4">
  <div class="admin-card-body p-3">
    <form method="GET" action="" class="row g-2 align-items-center">
      <div class="col-md-7">
        <div class="input-group">
          <span class="input-group-text bg-light text-muted"><i class="bi bi-search"></i></span>
          <input type="text" name="search" class="form-control" placeholder="Search by Patient Name, Phone, or Patient ID..." value="<?= e($search) ?>">
        </div>
      </div>
      <div class="col-md-3">
        <select name="gender" class="form-select">
          <option value="">All Genders</option>
          <option value="Male" <?= $gender === 'Male' ? 'selected' : '' ?>>Male</option>
          <option value="Female" <?= $gender === 'Female' ? 'selected' : '' ?>>Female</option>
          <option value="Other" <?= $gender === 'Other' ? 'selected' : '' ?>>Other</option>
        </select>
      </div>
      <div class="col-md-2 d-flex gap-2">
        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-funnel"></i> Filter</button>
        <?php if (!empty($search) || !empty($gender)): ?>
          <a href="<?= ADMIN_URL ?>/patients/index.php" class="btn btn-outline-secondary" title="Reset"><i class="bi bi-x-lg"></i></a>
        <?php endif; ?>
      </div>
    </form>
  </div>
</div>

<!-- Patients Data Table -->
<div class="admin-card">
  <div class="admin-card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>Patient ID</th>
            <th>Patient Name</th>
            <th>Age / Gender</th>
            <th>Contact Phone</th>
            <th>Referring Doctor</th>
            <th>Orders</th>
            <th>Reg. Date</th>
            <th class="text-end text-nowrap pe-3" style="width: 150px;">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($patients)): ?>
            <tr>
              <td colspan="8" class="text-center text-muted py-5">
                <i class="bi bi-person-x fs-1 d-block mb-2 text-secondary"></i>
                No matching patients found.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($patients as $p): ?>
              <tr>
                <td>
                  <span class="badge bg-light text-dark border font-monospace"><?= e($p['patient_id']) ?></span>
                </td>
                <td>
                  <a href="<?= ADMIN_URL ?>/patients/view.php?id=<?= $p['id'] ?>" class="fw-bold text-dark text-decoration-none">
                    <?= e($p['full_name']) ?>
                  </a>
                  <?php if (!empty($p['blood_group'])): ?>
                    <span class="badge bg-danger-subtle text-danger ms-1" style="font-size:0.7rem;"><?= e($p['blood_group']) ?></span>
                  <?php endif; ?>
                </td>
                <td>
                  <?= $p['age'] ?> <?= e($p['age_unit']) ?> / 
                  <span class="text-muted"><?= e($p['gender']) ?></span>
                </td>
                <td>
                  <i class="bi bi-telephone me-1 text-muted"></i><?= e($p['phone']) ?>
                </td>
                <td>
                  <?= e($p['doctor_name'] ?: 'Direct / Walk-in') ?>
                </td>
                <td>
                  <span class="badge bg-info-subtle text-info-emphasis rounded-pill"><?= $p['total_orders'] ?> Orders</span>
                </td>
                <td class="small text-muted">
                  <?= format_date($p['created_at']) ?>
                </td>
                <td class="text-end text-nowrap pe-3">
                  <div class="d-inline-flex align-items-center justify-content-end gap-1">
                    <a href="<?= ADMIN_URL ?>/patients/view.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-primary btn-action-icon" title="View Patient Profile">
                      <i class="bi bi-eye"></i>
                    </a>
                    <?php if (Auth::can('orders.create')): ?>
                    <a href="<?= ADMIN_URL ?>/orders/create.php?patient_id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-success btn-action-icon" title="New Test Order">
                      <i class="bi bi-plus-circle"></i>
                    </a>
                    <?php endif; ?>
                    <?php if (Auth::can('patients.edit')): ?>
                    <a href="<?= ADMIN_URL ?>/patients/edit.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-secondary btn-action-icon" title="Edit Patient Info">
                      <i class="bi bi-pencil"></i>
                    </a>
                    <form method="POST" action="<?= ADMIN_URL ?>/patients/index.php" class="d-inline m-0 p-0" onsubmit="return confirm('Delete patient <?= e(addslashes($p['full_name'])) ?> (<?= e($p['patient_id']) ?>)?');">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="delete_patient">
                      <input type="hidden" name="patient_id" value="<?= $p['id'] ?>">
                      <button type="submit" class="btn btn-sm btn-outline-danger btn-action-icon" title="Delete Patient">
                        <i class="bi bi-trash"></i>
                      </button>
                    </form>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>

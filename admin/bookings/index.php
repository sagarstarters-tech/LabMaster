<?php
/**
 * LabMaster - Quick Test Bookings Triage & Dispatch
 */

declare(strict_types=1);

$pageTitle = 'Test Bookings Triage';
$activeNav = 'bookings';

require_once dirname(__DIR__) . '/includes/auth_guard.php';
Auth::requirePermission('bookings.manage');

$statusFilter = trim($_GET['status'] ?? '');
$search = trim($_GET['search'] ?? '');

$sql = "SELECT b.*, p.patient_id as patient_code 
        FROM `bookings` b 
        LEFT JOIN `patients` p ON b.patient_id = p.id 
        WHERE 1=1";
$params = [];

if (!empty($statusFilter)) {
    $sql .= " AND b.status = ?";
    $params[] = $statusFilter;
}

if (!empty($search)) {
    $sql .= " AND (b.patient_name LIKE ? OR b.phone LIKE ? OR b.booking_reference LIKE ?)";
    $like = "%{$search}%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$sql .= " ORDER BY (CASE WHEN b.status = 'New' THEN 1 WHEN b.status = 'Confirmed' THEN 2 ELSE 3 END), b.id DESC";
$bookings = Database::fetchAll($sql, $params);

// Counts per status
$statusCounts = [];
$countsRaw = Database::fetchAll("SELECT `status`, COUNT(*) as c FROM `bookings` GROUP BY `status`");
foreach ($countsRaw as $r) {
    $statusCounts[$r['status']] = (int)$r['c'];
}

// Handle POST Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'delete_booking') {
        Auth::requirePermission('bookings.manage');
        $bookingId = (int)($_POST['booking_id'] ?? 0);
        $b = Database::fetch("SELECT * FROM `bookings` WHERE `id` = ?", [$bookingId]);
        if ($b) {
            Database::execute("DELETE FROM `bookings` WHERE `id` = ?", [$bookingId]);
            log_audit('Booking Removed', 'Bookings', $b['booking_reference'], "Removed booking request {$b['booking_reference']}.");
            set_flash('success', "Booking \"{$b['booking_reference']}\" removed successfully.");
        } else {
            set_flash('danger', 'Booking record not found.');
        }
        header('Location: ' . ADMIN_URL . '/bookings/index.php');
        exit;
    }
}

require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
  <div>
    <h2 class="h4 fw-bold mb-1">Quick Test Bookings & Home Phlebotomy</h2>
    <p class="text-muted small mb-0">Patient booking requests from online portal and website</p>
  </div>
</div>

<!-- Status Filter Pills -->
<div class="d-flex flex-wrap gap-2 mb-3">
  <a href="?status=" class="btn btn-sm <?= empty($statusFilter) ? 'btn-primary-lm' : 'btn-outline-secondary' ?>">
    All Bookings (<?= array_sum($statusCounts) ?>)
  </a>
  <a href="?status=New" class="btn btn-sm <?= $statusFilter === 'New' ? 'btn-warning text-dark fw-bold' : 'btn-outline-secondary' ?>">
    New Requests (<?= $statusCounts['New'] ?? 0 ?>)
  </a>
  <a href="?status=Confirmed" class="btn btn-sm <?= $statusFilter === 'Confirmed' ? 'btn-primary' : 'btn-outline-secondary' ?>">
    Confirmed (<?= $statusCounts['Confirmed'] ?? 0 ?>)
  </a>
  <a href="?status=Sample Collection Scheduled" class="btn btn-sm <?= $statusFilter === 'Sample Collection Scheduled' ? 'btn-info text-dark' : 'btn-outline-secondary' ?>">
    Phlebotomy Scheduled (<?= $statusCounts['Sample Collection Scheduled'] ?? 0 ?>)
  </a>
  <a href="?status=Completed" class="btn btn-sm <?= $statusFilter === 'Completed' ? 'btn-success' : 'btn-outline-secondary' ?>">
    Completed (<?= $statusCounts['Completed'] ?? 0 ?>)
  </a>
  <a href="?status=Cancelled" class="btn btn-sm <?= $statusFilter === 'Cancelled' ? 'btn-danger' : 'btn-outline-secondary' ?>">
    Cancelled (<?= $statusCounts['Cancelled'] ?? 0 ?>)
  </a>
</div>

<!-- Bookings Table Card -->
<div class="admin-card">
  <div class="admin-card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>Ref Number</th>
            <th>Patient Details</th>
            <th>Preferred Schedule</th>
            <th>Collection Type</th>
            <th>Tests Requested</th>
            <th>Status</th>
            <th>Created</th>
            <th class="text-end text-nowrap pe-3" style="width: 140px;">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($bookings)): ?>
            <tr><td colspan="8" class="text-center text-muted py-5">No booking requests found.</td></tr>
          <?php else: ?>
            <?php foreach ($bookings as $b): ?>
              <tr>
                <td>
                  <span class="badge bg-light text-dark border font-monospace"><?= e($b['booking_reference']) ?></span>
                </td>
                <td>
                  <div class="fw-bold"><?= e($b['patient_name']) ?></div>
                  <div class="small text-muted">
                    <?= $b['age'] ?> Y / <?= e($b['gender']) ?> • <i class="bi bi-telephone ms-1"></i><?= e($b['phone']) ?>
                  </div>
                </td>
                <td>
                  <div class="fw-semibold text-dark"><?= format_date($b['preferred_date']) ?></div>
                  <div class="small text-muted"><i class="bi bi-clock me-1"></i><?= e($b['preferred_time_slot']) ?></div>
                </td>
                <td>
                  <?php if ($b['is_home_collection']): ?>
                    <span class="badge bg-warning-subtle text-warning-emphasis border border-warning">
                      <i class="bi bi-house-door-fill me-1"></i> Home Visit
                    </span>
                  <?php else: ?>
                    <span class="badge bg-light text-secondary border">
                      <i class="bi bi-hospital me-1"></i> Center Walk-in
                    </span>
                  <?php endif; ?>
                </td>
                <td>
                  <?php
                  $rawIds = trim($b['test_ids'] ?? '');
                  if (str_starts_with($rawIds, '[')) {
                      $tArr = json_decode($rawIds, true);
                      $count = is_array($tArr) ? count(array_filter($tArr)) : 0;
                  } else {
                      $count = count(array_filter(explode(',', $rawIds)));
                  }
                  echo '<span class="badge bg-info-subtle text-info-emphasis">' . $count . ' Tests Selected</span>';
                  ?>
                </td>
                <td>
                  <?php
                  $badgeClass = match ($b['status']) {
                      'New' => 'bg-warning text-dark fw-bold pulse-alert',
                      'Confirmed' => 'bg-primary text-white',
                      'Sample Collection Scheduled' => 'bg-info text-dark',
                      'Sample Collected', 'Completed' => 'bg-success text-white',
                      'Cancelled' => 'bg-danger text-white',
                      default => 'bg-secondary text-white'
                  };
                  ?>
                  <span class="badge <?= $badgeClass ?>"><?= e($b['status']) ?></span>
                </td>
                <td class="small text-muted">
                  <?= format_date($b['created_at'], true) ?>
                </td>
                <td class="text-end text-nowrap pe-3">
                  <div class="d-inline-flex align-items-center justify-content-end gap-1">
                    <a href="<?= ADMIN_URL ?>/bookings/view.php?id=<?= $b['id'] ?>" class="btn btn-sm btn-outline-primary btn-action-icon" title="Review & Triage Booking">
                      <i class="bi bi-eye"></i>
                    </a>
                    <?php if (Auth::can('orders.create') && in_array($b['status'], ['New', 'Confirmed'])): ?>
                    <a href="<?= ADMIN_URL ?>/orders/create.php?booking_id=<?= $b['id'] ?>" class="btn btn-sm btn-outline-secondary btn-action-icon" title="Convert to Test Order">
                      <i class="bi bi-pencil-square"></i>
                    </a>
                    <?php endif; ?>
                    <?php if (Auth::can('bookings.manage')): ?>
                    <form method="POST" action="<?= ADMIN_URL ?>/bookings/index.php" class="d-inline m-0 p-0" onsubmit="return confirm('Delete booking <?= e($b['booking_reference']) ?>?');">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="delete_booking">
                      <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">
                      <button type="submit" class="btn btn-sm btn-outline-danger btn-action-icon" title="Delete Booking">
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

<?php
/**
 * LabMaster - Review & Triage Quick Booking
 */

declare(strict_types=1);

$bookingId = (int)($_POST['booking_id'] ?? ($_GET['id'] ?? 0));
$pageTitle = 'Review Booking Request';
$activeNav = 'bookings';

require_once dirname(__DIR__) . '/includes/auth_guard.php';
Auth::requirePermission('bookings.manage');

$booking = Database::fetch("SELECT * FROM `bookings` WHERE `id` = ?", [$bookingId]);
if (!$booking) {
    set_flash('danger', 'Booking record not found.');
    header('Location: ' . ADMIN_URL . '/bookings/index.php');
    exit;
}

// Fetch requested tests
$rawTestIds = trim($booking['test_ids'] ?? '');
if (str_starts_with($rawTestIds, '[')) {
    $decoded = json_decode($rawTestIds, true);
    $testIds = is_array($decoded) ? array_filter(array_map('intval', $decoded)) : [];
} else {
    $testIds = array_filter(array_map('intval', explode(',', $rawTestIds)));
}
$tests = [];
$totalEstimated = 0;
if (!empty($testIds)) {
    $placeholders = implode(',', array_fill(0, count($testIds), '?'));
    $tests = Database::fetchAll("SELECT * FROM `tests` WHERE `id` IN ({$placeholders})", $testIds);
    foreach ($tests as $t) {
        $totalEstimated += (float)$t['price'];
    }
}

// Check if patient already exists by phone
$existingPatient = Database::fetch("SELECT * FROM `patients` WHERE `phone` = ? LIMIT 1", [$booking['phone']]);

// Check if already converted into a test order
$existingOrder = Database::fetch("SELECT * FROM `test_orders` WHERE `booking_id` = ? LIMIT 1", [$bookingId]);

// Handle Status & Triage Notes Update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'update_status') {
        $newStatus = trim($_POST['status'] ?? $booking['status']);
        $triageNotes = trim($_POST['triage_notes'] ?? '');

        Database::execute(
            "UPDATE `bookings` SET `status` = ?, `triage_notes` = ? WHERE `id` = ?",
            [$newStatus, $triageNotes, $bookingId]
        );

        log_audit('Booking Status Updated', 'Bookings', $booking['booking_reference'], "Updated status to '{$newStatus}'.");
        set_flash('success', "Booking status updated to '{$newStatus}'.");
        header("Location: " . ADMIN_URL . "/bookings/view.php?id={$bookingId}");
        exit;
    }

    // Convert Booking to Patient Registration & Test Order
    elseif ($action === 'convert_to_order') {
        $db = Database::getInstance();
        $db->beginTransaction();

        try {
            $patientId = null;

            // 1. Get or create patient
            if ($existingPatient) {
                $patientId = (int)$existingPatient['id'];
            } else {
                $newPatientId = generate_patient_id();
                $defPass = password_hash($booking['phone'], PASSWORD_BCRYPT);

                Database::execute(
                    "INSERT INTO `patients` (`patient_id`, `full_name`, `gender`, `age`, `age_unit`, `phone`, `email`, `address`, `medical_history`, `password_hash`)
                     VALUES (?, ?, ?, ?, 'Years', ?, ?, ?, ?, ?)",
                    [$newPatientId, $booking['patient_name'], $booking['gender'], $booking['age'], $booking['phone'], $booking['email'], $booking['collection_address'], $booking['notes'], $defPass]
                );
                $patientId = (int)Database::lastInsertId();
            }

            // 2. Create Test Order
            $accessionNo = generate_accession_number();
            $orderDate = $booking['preferred_date'] ?: date('Y-m-d');
            $userId = Auth::id();

            Database::execute(
                "INSERT INTO `test_orders` (`accession_number`, `patient_id`, `booking_id`, `order_date`, `priority`, `clinical_notes`, `total_amount`, `discount_amount`, `net_amount`, `status`, `payment_status`, `created_by`)
                 VALUES (?, ?, ?, ?, 'Routine', ?, ?, 0.00, ?, 'Order Created', 'Unpaid', ?)",
                [$accessionNo, $patientId, $bookingId, $orderDate, $booking['notes'], $totalEstimated, $totalEstimated, $userId]
            );
            $orderId = (int)Database::lastInsertId();

            // 3. Insert Test Order Items
            foreach ($tests as $t) {
                $barcode = 'BAR-' . date('Ymd') . '-' . $t['test_code'];
                Database::execute(
                    "INSERT INTO `test_order_items` (`order_id`, `test_id`, `price`, `sample_barcode`, `sample_status`, `sample_type`)
                     VALUES (?, ?, ?, ?, 'Pending', ?)",
                    [$orderId, $t['id'], $t['price'], $barcode, $t['specimen_type']]
                );
            }

            // 4. Create Invoice & Invoice Items
            $invoiceNo = generate_invoice_number();
            Database::execute(
                "INSERT INTO `invoices` (`invoice_number`, `order_id`, `patient_id`, `subtotal`, `discount_amount`, `grand_total`, `paid_amount`, `balance_amount`, `payment_status`, `invoice_date`, `created_by`)
                 VALUES (?, ?, ?, ?, 0.00, ?, 0.00, ?, 'Unpaid', ?, ?)",
                [$invoiceNo, $orderId, $patientId, $totalEstimated, $totalEstimated, $totalEstimated, $orderDate, $userId]
            );
            $invoiceId = (int)Database::lastInsertId();

            foreach ($tests as $t) {
                Database::execute(
                    "INSERT INTO `invoice_items` (`invoice_id`, `item_description`, `unit_price`, `quantity`, `total_amount`)
                     VALUES (?, ?, ?, 1, ?)",
                    [$invoiceId, $t['test_name'], $t['price'], $t['price']]
                );
            }

            // 5. Update Booking status to Scheduled
            Database::execute(
                "UPDATE `bookings` SET `status` = 'Sample Collection Scheduled', `patient_id` = ? WHERE `id` = ?",
                [$patientId, $bookingId]
            );

            $db->commit();
            log_audit('Booking Converted', 'Bookings', $booking['booking_reference'], "Converted booking into Test Order {$accessionNo}.");
            set_flash('success', "Booking converted! Accession {$accessionNo} created successfully with Invoice {$invoiceNo}.");
            header('Location: ' . ADMIN_URL . '/orders/view.php?id=' . $orderId);
            exit;
        } catch (Exception $e) {
            $db->rollBack();
            set_flash('danger', 'Error converting booking: ' . $e->getMessage());
        }
    }
}

require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h2 class="h4 fw-bold mb-1">
      Booking: <?= e($booking['booking_reference']) ?>
    </h2>
    <p class="text-muted small mb-0">Submitted on <?= format_date($booking['created_at'], true) ?></p>
  </div>
  <a href="<?= ADMIN_URL ?>/bookings/index.php" class="btn btn-outline-secondary btn-sm">
    <i class="bi bi-arrow-left me-1"></i> Back to Bookings
  </a>
</div>

<div class="row g-4">
  <!-- Left Column: Patient & Booking Details -->
  <div class="col-lg-8">
    <div class="admin-card mb-4">
      <div class="admin-card-header bg-light">
        <h3 class="admin-card-title text-primary">
          <i class="bi bi-person-circle"></i> Patient & Schedule Information
        </h3>
        <span class="badge bg-secondary"><?= e($booking['status']) ?></span>
      </div>
      <div class="admin-card-body">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="text-muted small">Patient Name</label>
            <div class="fw-bold fs-5"><?= e($booking['patient_name']) ?></div>
          </div>
          <div class="col-md-3">
            <label class="text-muted small">Age & Gender</label>
            <div class="fw-semibold"><?= $booking['age'] ?> Years / <?= e($booking['gender']) ?></div>
          </div>
          <div class="col-md-3">
            <label class="text-muted small">Mobile Phone</label>
            <div class="fw-semibold text-primary">
              <i class="bi bi-telephone-fill me-1"></i><?= e($booking['phone']) ?>
            </div>
          </div>

          <div class="col-md-6">
            <label class="text-muted small">Preferred Booking Date</label>
            <div class="fw-semibold fs-6 text-dark">
              <i class="bi bi-calendar3 me-1 text-primary"></i> <?= format_date($booking['preferred_date']) ?>
            </div>
          </div>
          <div class="col-md-6">
            <label class="text-muted small">Preferred Time Slot</label>
            <div class="fw-semibold fs-6 text-dark">
              <i class="bi bi-clock me-1 text-primary"></i> <?= e($booking['preferred_time_slot']) ?>
            </div>
          </div>

          <div class="col-12">
            <div class="p-3 rounded border <?= $booking['is_home_collection'] ? 'bg-warning-subtle border-warning' : 'bg-light' ?>">
              <div class="fw-bold mb-1">
                <?php if ($booking['is_home_collection']): ?>
                  <i class="bi bi-house-door-fill text-warning me-1"></i> Home Sample Collection Requested
                <?php else: ?>
                  <i class="bi bi-hospital text-primary me-1"></i> Diagnostic Center Walk-in
                <?php endif; ?>
              </div>
              <?php if ($booking['is_home_collection'] && !empty($booking['collection_address'])): ?>
                <div class="small mt-1">
                  <strong>Collection Address:</strong> <?= nl2br(e($booking['collection_address'])) ?>
                </div>
              <?php endif; ?>
            </div>
          </div>

          <?php if (!empty($booking['referring_doctor'])): ?>
          <div class="col-md-6">
            <label class="text-muted small">Referring Doctor Mentioned</label>
            <div class="fw-semibold"><?= e($booking['referring_doctor']) ?></div>
          </div>
          <?php endif; ?>

          <?php if (!empty($booking['notes'])): ?>
          <div class="col-12">
            <label class="text-muted small">Patient Notes / Symptoms</label>
            <div class="p-2 bg-light rounded small"><?= nl2br(e($booking['notes'])) ?></div>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Requested Tests Table -->
    <div class="admin-card">
      <div class="admin-card-header">
        <h3 class="admin-card-title text-success">
          <i class="bi bi-card-checklist"></i> Requested Clinical Tests (<?= count($tests) ?>)
        </h3>
        <span class="fw-bold text-success fs-5">Est. <?= format_currency($totalEstimated) ?></span>
      </div>
      <div class="admin-card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0 small">
            <thead class="table-light">
              <tr>
                <th>Code</th>
                <th>Test Name</th>
                <th>Specimen</th>
                <th>Turnaround</th>
                <th class="text-end">Standard Price</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($tests as $t): ?>
                <tr>
                  <td><span class="badge bg-light text-dark border font-monospace"><?= e($t['test_code']) ?></span></td>
                  <td class="fw-bold"><?= e($t['test_name']) ?></td>
                  <td><?= e($t['specimen_type']) ?></td>
                  <td><?= e($t['turnaround_time']) ?></td>
                  <td class="text-end fw-bold"><?= format_currency($t['price']) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- Right Column: Status Update & Conversion Actions -->
  <div class="col-lg-4">
    <!-- Status & Triage Update -->
    <div class="admin-card mb-4">
      <div class="admin-card-header bg-light">
        <h3 class="admin-card-title">
          <i class="bi bi-sliders"></i> Triage & Phlebotomy
        </h3>
      </div>
      <div class="admin-card-body">
        <form method="POST" action="<?= ADMIN_URL ?>/bookings/view.php?id=<?= $bookingId ?>">
          <?= csrf_field() ?>
          <input type="hidden" name="booking_id" value="<?= $bookingId ?>">
          <input type="hidden" name="action" value="update_status">

          <div class="mb-3">
            <label class="form-label small fw-semibold">Booking Status</label>
            <select name="status" class="form-select">
              <?php
              $statuses = ['New', 'Pending Confirmation', 'Confirmed', 'Sample Collection Scheduled', 'Sample Collected', 'Completed', 'Cancelled'];
              foreach ($statuses as $st): ?>
                <option value="<?= $st ?>" <?= $booking['status'] === $st ? 'selected' : '' ?>><?= $st ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="mb-3">
            <label class="form-label small fw-semibold">Triage & Phlebotomist Notes</label>
            <textarea name="triage_notes" class="form-control small" rows="3" placeholder="e.g. Confirmed with patient, Phlebotomist Amit assigned..."><?= e($booking['triage_notes'] ?? '') ?></textarea>
          </div>

          <button type="submit" class="btn btn-update w-100">
            <i class="bi bi-save me-1"></i> Update Triage Status
          </button>
        </form>
      </div>
    </div>

    <!-- Convert to Test Order Card -->
    <div class="admin-card border-primary">
      <div class="admin-card-header bg-primary text-white">
        <h3 class="admin-card-title text-white">
          <i class="bi bi-arrow-repeat"></i> Order Conversion
        </h3>
      </div>
      <div class="admin-card-body">
        <?php if ($existingOrder): ?>
          <div class="alert alert-success small mb-3 py-2">
            <i class="bi bi-check-circle-fill me-1"></i> <strong>Already Converted to Test Order!</strong><br>
            Accession No: <strong><?= e($existingOrder['accession_number']) ?></strong>
          </div>
          <a href="<?= ADMIN_URL ?>/orders/view.php?id=<?= $existingOrder['id'] ?>" class="btn btn-primary-lm w-100 py-2">
            <i class="bi bi-eye me-1"></i> View Test Order & Accession
          </a>
        <?php else: ?>
          <?php if ($existingPatient): ?>
            <div class="alert alert-info small mb-3 py-2">
              <i class="bi bi-person-check-fill me-1"></i> <strong>Existing Patient:</strong>
              <?= e($existingPatient['full_name']) ?> (<?= e($existingPatient['patient_id']) ?>)
            </div>
          <?php else: ?>
            <div class="alert alert-secondary small mb-3 py-2">
              <i class="bi bi-person-plus me-1"></i> Patient is new. Will register automatically.
            </div>
          <?php endif; ?>

          <p class="small text-muted mb-3">
            Converts this booking into an active laboratory accession, creates test line items, and prepares sample barcoding.
          </p>

          <form method="POST" action="<?= ADMIN_URL ?>/bookings/view.php?id=<?= $bookingId ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="booking_id" value="<?= $bookingId ?>">
            <input type="hidden" name="action" value="convert_to_order">
            <button type="submit" class="btn btn-primary-lm w-100 py-2">
              <i class="bi bi-box-arrow-in-right me-1"></i> Convert to Active Test Order
            </button>
          </form>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>

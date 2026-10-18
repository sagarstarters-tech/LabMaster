<?php
/**
 * LabMaster - Create New Laboratory Accession / Test Order
 */

declare(strict_types=1);

$pageTitle = 'New Test Accession';
$activeNav = 'orders';

require_once dirname(__DIR__) . '/includes/auth_guard.php';
Auth::requirePermission('orders.create');

$preselectedPatientId = (int)($_GET['patient_id'] ?? 0);
$patients = Database::fetchAll("SELECT `id`, `patient_id`, `full_name`, `phone`, `gender`, `age` FROM `patients` ORDER BY `id` DESC LIMIT 200");
$doctors = Database::fetchAll("SELECT * FROM `doctors` WHERE `is_active` = 1 ORDER BY `name` ASC");
$tests = Database::fetchAll("SELECT t.*, c.name as category_name FROM `tests` t JOIN `test_categories` c ON t.category_id = c.id WHERE t.is_active = 1 ORDER BY c.sort_order ASC, t.test_name ASC");

$newAccessionNo = generate_accession_number();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $patientId = (int)($_POST['patient_id'] ?? 0);
    $referringDoctorId = !empty($_POST['referring_doctor_id']) ? (int)$_POST['referring_doctor_id'] : null;
    $orderDate = !empty($_POST['order_date']) ? trim($_POST['order_date']) : date('Y-m-d');
    $priority = in_array($_POST['priority'] ?? '', ['Routine', 'Urgent', 'Stat'], true) ? $_POST['priority'] : 'Routine';
    $clinicalNotes = trim($_POST['clinical_notes'] ?? '');
    $selectedTestIds = $_POST['test_ids'] ?? [];
    $discountAmount = (float)($_POST['discount_amount'] ?? 0);

    if ($patientId <= 0) {
        $errors[] = 'Please select a registered patient.';
    }
    if (empty($selectedTestIds) || !is_array($selectedTestIds)) {
        $errors[] = 'Please select at least one pathology test for this order.';
    }

    if (empty($errors)) {
        $db = Database::getInstance();
        $db->beginTransaction();

        try {
            // Calculate total price of selected tests
            $placeholders = implode(',', array_fill(0, count($selectedTestIds), '?'));
            $selectedTests = Database::fetchAll("SELECT * FROM `tests` WHERE `id` IN ({$placeholders})", array_map('intval', $selectedTestIds));

            $totalAmount = 0.0;
            foreach ($selectedTests as $t) {
                $totalAmount += (float)$t['price'];
            }
            $netAmount = max(0.0, $totalAmount - $discountAmount);

            // 1. Insert Test Order
            $userId = Auth::id();
            Database::execute(
                "INSERT INTO `test_orders` (`accession_number`, `patient_id`, `referring_doctor_id`, `order_date`, `priority`, `clinical_notes`, `total_amount`, `discount_amount`, `net_amount`, `status`, `payment_status`, `created_by`)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Order Created', 'Unpaid', ?)",
                [$newAccessionNo, $patientId, $referringDoctorId, $orderDate, $priority, $clinicalNotes, $totalAmount, $discountAmount, $netAmount, $userId]
            );
            $orderId = (int)Database::lastInsertId();

            // 2. Insert Test Order Items
            foreach ($selectedTests as $st) {
                $barcode = 'BAR-' . date('Ymd') . '-' . rand(1000, 9999);
                Database::execute(
                    "INSERT INTO `test_order_items` (`order_id`, `test_id`, `price`, `sample_barcode`, `sample_status`, `sample_type`)
                     VALUES (?, ?, ?, ?, 'Pending', ?)",
                    [$orderId, $st['id'], $st['price'], $barcode, $st['specimen_type']]
                );
            }

            // 3. Automatically Create Initial Draft Report & Unique Verification Token
            $reportNo = generate_report_number();
            $token = bin2hex(random_bytes(32));
            Database::execute(
                "INSERT INTO `reports` (`report_number`, `order_id`, `patient_id`, `version_number`, `report_status`, `verification_token`)
                 VALUES (?, ?, ?, 1, 'Draft', ?)",
                [$reportNo, $orderId, $patientId, $token]
            );

            // 4. Automatically Create Billing Invoice
            $invoiceNo = generate_invoice_number();
            Database::execute(
                "INSERT INTO `invoices` (`invoice_number`, `order_id`, `patient_id`, `subtotal`, `discount_amount`, `grand_total`, `paid_amount`, `balance_amount`, `payment_status`, `invoice_date`, `created_by`)
                 VALUES (?, ?, ?, ?, ?, ?, 0.00, ?, 'Unpaid', ?, ?)",
                [$invoiceNo, $orderId, $patientId, $totalAmount, $discountAmount, $netAmount, $netAmount, $orderDate, $userId]
            );
            $invoiceId = (int)Database::lastInsertId();

            foreach ($selectedTests as $st) {
                Database::execute(
                    "INSERT INTO `invoice_items` (`invoice_id`, `item_description`, `unit_price`, `quantity`, `total_amount`)
                     VALUES (?, ?, ?, 1, ?)",
                    [$invoiceId, $st['test_name'], $st['price'], $st['price']]
                );
            }

            $db->commit();
            log_audit('Order Created', 'Orders', $newAccessionNo, "Created accession {$newAccessionNo} for patient ID {$patientId} with " . count($selectedTests) . " tests.");
            set_flash('success', "Accession {$newAccessionNo} generated successfully. Invoice {$invoiceNo} created.");
            header('Location: ' . ADMIN_URL . '/orders/view.php?id=' . $orderId);
            exit;
        } catch (Exception $e) {
            $db->rollBack();
            $errors[] = 'Failed to create order: ' . $e->getMessage();
        }
    }
}

require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="row justify-content-center">
  <div class="col-lg-11">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <div>
        <h2 class="h4 fw-bold mb-1">Create Test Accession</h2>
        <p class="text-muted small mb-0">Select patient, choose clinical investigations, and configure order priorities</p>
      </div>
      <a href="<?= ADMIN_URL ?>/orders/index.php" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i> Back to Orders
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

    <form method="POST" action="" id="orderCreateForm">
      <?= csrf_field() ?>

      <div class="row g-4">
        <!-- Left: Patient & Meta Info -->
        <div class="col-lg-4">
          <div class="admin-card mb-4">
            <div class="admin-card-header bg-light">
              <h3 class="admin-card-title text-primary">
                <i class="bi bi-person-fill"></i> Patient & Accession
              </h3>
              <span class="badge bg-primary font-monospace"><?= e($newAccessionNo) ?></span>
            </div>
            <div class="admin-card-body">
              <div class="mb-3">
                <div class="d-flex justify-content-between align-items-center mb-1">
                  <label class="form-label small fw-semibold mb-0">Select Patient <span class="text-danger">*</span></label>
                  <a href="<?= ADMIN_URL ?>/patients/create.php" target="_blank" class="small text-decoration-none">
                    <i class="bi bi-person-plus"></i> New Patient
                  </a>
                </div>
                <select name="patient_id" class="form-select" required>
                  <option value="">-- Choose Patient --</option>
                  <?php foreach ($patients as $p): ?>
                    <option value="<?= $p['id'] ?>" <?= ($preselectedPatientId === (int)$p['id']) ? 'selected' : '' ?>>
                      <?= e($p['full_name']) ?> (<?= e($p['patient_id']) ?> - <?= e($p['phone']) ?>)
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="mb-3">
                <label class="form-label small fw-semibold">Referring Doctor</label>
                <select name="referring_doctor_id" class="form-select">
                  <option value="">Direct Walk-in / Self</option>
                  <?php foreach ($doctors as $doc): ?>
                    <option value="<?= $doc['id'] ?>">
                      <?= e($doc['name']) ?> (<?= e($doc['specialization'] ?: 'Physician') ?>)
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="row g-2 mb-3">
                <div class="col-6">
                  <label class="form-label small fw-semibold">Order Date</label>
                  <input type="date" name="order_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="col-6">
                  <label class="form-label small fw-semibold">Priority</label>
                  <select name="priority" class="form-select">
                    <option value="Routine">Routine</option>
                    <option value="Urgent">Urgent</option>
                    <option value="Stat">STAT (Emergency)</option>
                  </select>
                </div>
              </div>

              <div class="mb-3">
                <label class="form-label small fw-semibold">Clinical Notes / Symptoms</label>
                <textarea name="clinical_notes" class="form-control" rows="2" placeholder="e.g. Fever x 3 days, Rule out typhoid"></textarea>
              </div>

              <div class="p-3 bg-light rounded border">
                <div class="d-flex justify-content-between mb-1 small">
                  <span class="text-muted">Subtotal:</span>
                  <span class="fw-bold" id="summarySubtotal">₹ 0.00</span>
                </div>
                <div class="mb-2">
                  <label class="form-label small fw-semibold">Discount Amount (₹)</label>
                  <input type="number" step="0.01" name="discount_amount" id="orderDiscountInput" class="form-control form-control-sm" value="0.00">
                </div>
                <hr class="my-2">
                <div class="d-flex justify-content-between align-items-center">
                  <span class="fw-bold">Net Total:</span>
                  <span class="fw-bold fs-5 text-primary" id="summaryNetTotal">₹ 0.00</span>
                </div>
              </div>

              <div class="d-grid mt-3">
                <button type="submit" class="btn btn-save py-2">
                  <i class="bi bi-check-circle me-1"></i> Create Accession & Order
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Right: Multi-test selection directory -->
        <div class="col-lg-8">
          <div class="admin-card">
            <div class="admin-card-header bg-light d-flex justify-content-between align-items-center">
              <h3 class="admin-card-title text-success">
                <i class="bi bi-card-checklist"></i> Select Tests to Perform
              </h3>
              <span class="badge bg-success-subtle text-success-emphasis" id="selectedBadge">0 Selected</span>
            </div>
            <div class="admin-card-body p-3">
              <div class="row g-2" style="max-height: 580px; overflow-y: auto;">
                <?php foreach ($tests as $t): ?>
                  <div class="col-md-6">
                    <label class="p-2 border rounded d-flex align-items-center gap-3 w-100 test-select-label cursor-pointer hover-shadow" style="cursor: pointer;">
                      <input type="checkbox" name="test_ids[]" value="<?= $t['id'] ?>" data-price="<?= $t['price'] ?>" class="form-check-input order-test-checkbox m-0">
                      <div class="overflow-hidden flex-1">
                        <div class="fw-bold small text-truncate"><?= e($t['test_name']) ?></div>
                        <div class="text-muted" style="font-size: 0.72rem;">
                          <span class="badge bg-light text-dark border font-monospace"><?= e($t['test_code']) ?></span>
                          <span class="ms-1"><?= e($t['specimen_type']) ?></span>
                        </div>
                      </div>
                      <div class="fw-bold text-primary small ms-auto">
                        <?= format_currency($t['price']) ?>
                      </div>
                    </label>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
          </div>
        </div>
      </div>
    </form>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const checkboxes = document.querySelectorAll('.order-test-checkbox');
  const discountInput = document.getElementById('orderDiscountInput');
  const subtotalElem = document.getElementById('summarySubtotal');
  const netTotalElem = document.getElementById('summaryNetTotal');
  const selectedBadge = document.getElementById('selectedBadge');

  function calculateTotals() {
    let subtotal = 0;
    let count = 0;

    checkboxes.forEach(cb => {
      if (cb.checked) {
        subtotal += parseFloat(cb.dataset.price || '0');
        count++;
      }
    });

    const discount = parseFloat(discountInput.value || '0');
    const net = Math.max(0, subtotal - discount);

    subtotalElem.textContent = '₹ ' + subtotal.toFixed(2);
    netTotalElem.textContent = '₹ ' + net.toFixed(2);
    selectedBadge.textContent = count + ' Selected';
  }

  checkboxes.forEach(cb => cb.addEventListener('change', calculateTotals));
  discountInput.addEventListener('input', calculateTotals);
});
</script>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>

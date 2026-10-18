<?php
/**
 * LabMaster - Quick Test Booking System
 * Dynamic test selection, home collection toggle, live price calculator
 */

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Quick Pathology Test Booking | ' . Settings::get('lab_name', 'LabMaster Diagnostic & Research Center');
$pageDesc  = 'Schedule your laboratory tests or home sample collection in less than 2 minutes. Transparent prices and prompt confirmation.';
$activePage = 'booking';

// Pre-selected test ID from query string if any
$preselectedTestId = isset($_GET['test_id']) ? (int)$_GET['test_id'] : 0;

// Fetch all active tests grouped by category
$categories = Database::fetchAll("SELECT * FROM `test_categories` ORDER BY `name` ASC");
$tests = Database::fetchAll(
    "SELECT t.*, c.name as category_name 
     FROM `tests` t 
     JOIN `test_categories` c ON t.category_id = c.id 
     WHERE t.is_active = 1 
     ORDER BY c.name ASC, t.test_name ASC"
);

// Fetch doctors for optional referral dropdown
$doctors = Database::fetchAll("SELECT * FROM `doctors` WHERE `is_active` = 1 ORDER BY `name` ASC");

$successBooking = null;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $name = trim($_POST['patient_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $age = (int)($_POST['age'] ?? 0);
    $gender = trim($_POST['gender'] ?? 'Male');
    $preferredDate = trim($_POST['preferred_date'] ?? date('Y-m-d'));
    $preferredTime = trim($_POST['preferred_time_slot'] ?? '');
    $isHomeCollection = !empty($_POST['is_home_collection']) ? 1 : 0;
    $address = trim($_POST['collection_address'] ?? '');
    $referringDoctor = trim($_POST['referring_doctor'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $consent = !empty($_POST['consent']);
    $selectedTests = $_POST['test_ids'] ?? [];

    if (empty($name)) {
        $errors[] = 'Full patient name is required.';
    }
    if (empty($phone) || !preg_match('/^[0-9]{10,15}$/', $phone)) {
        $errors[] = 'A valid 10-digit mobile number is required.';
    }
    if ($age <= 0 || $age > 125) {
        $errors[] = 'Please enter a valid age.';
    }
    if (!in_array($gender, ['Male', 'Female', 'Other'], true)) {
        $errors[] = 'Please select a valid gender.';
    }
    if (empty($selectedTests) || !is_array($selectedTests)) {
        $errors[] = 'Please select at least one laboratory test or panel.';
    }
    if (empty($preferredDate) || strtotime($preferredDate) < strtotime(date('Y-m-d'))) {
        $errors[] = 'Please choose a booking date of today or a future date.';
    }
    if (empty($preferredTime)) {
        $errors[] = 'Please select a preferred time slot.';
    }
    if ($isHomeCollection && empty($address)) {
        $errors[] = 'Please enter your complete doorstep address for home sample collection.';
    }
    if (!$consent) {
        $errors[] = 'Please accept the privacy consent terms.';
    }

    if (empty($errors)) {
        // Sanitize test IDs to integers
        $sanitizedTestIds = array_map('intval', $selectedTests);
        $testIdsStr = implode(',', $sanitizedTestIds);

        // Check if patient already exists by phone
        $existingPatient = Database::fetch("SELECT `id` FROM `patients` WHERE `phone` = ? LIMIT 1", [$phone]);
        $patientId = $existingPatient ? (int)$existingPatient['id'] : null;

        $bookingRef = generate_booking_ref();

        $bookingId = Database::insert(
            "INSERT INTO `bookings` (
                `booking_reference`, `patient_id`, `patient_name`, `phone`, `email`, 
                `age`, `gender`, `test_ids`, `preferred_date`, `preferred_time_slot`, 
                `is_home_collection`, `collection_address`, `referring_doctor`, `notes`, `status`
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'New')",
            [
                $bookingRef,
                $patientId,
                $name,
                $phone,
                $email ?: null,
                $age,
                $gender,
                $testIdsStr,
                $preferredDate,
                $preferredTime,
                $isHomeCollection,
                $address ?: null,
                $referringDoctor ?: null,
                $notes ?: null
            ]
        );

        if ($bookingId) {
            // Fetch selected tests details for summary
            $inPlaceholders = implode(',', array_fill(0, count($sanitizedTestIds), '?'));
            $bookedDetails = Database::fetchAll(
                "SELECT * FROM `tests` WHERE `id` IN ($inPlaceholders)",
                $sanitizedTestIds
            );

            $estimatedTotal = 0.0;
            foreach ($bookedDetails as $bd) {
                $estimatedTotal += (float)$bd['price'];
            }

            // In-app alert for admin staff
            add_notification(
                'New Test Booking Received',
                "Booking {$bookingRef} submitted by {$name} ({$phone}) for " . count($sanitizedTestIds) . " test(s).",
                'booking',
                "bookings/view.php?id={$bookingId}"
            );

            log_audit(
                'Online Booking Created',
                'Booking',
                $bookingRef,
                "Patient: {$name}, Phone: {$phone}, Home Collection: " . ($isHomeCollection ? 'Yes' : 'No')
            );

            $successBooking = [
                'id' => $bookingId,
                'ref' => $bookingRef,
                'name' => $name,
                'phone' => $phone,
                'tests' => $bookedDetails,
                'total' => $estimatedTotal,
                'date' => $preferredDate,
                'time' => $preferredTime,
                'is_home' => $isHomeCollection,
                'address' => $address
            ];
        } else {
            $errors[] = 'A database error occurred while reserving your booking. Please call our helpline.';
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<!-- Header Banner -->
<section class="py-5 bg-light border-bottom">
  <div class="container text-center">
    <span class="badge bg-primary-subtle text-primary fw-semibold px-3 py-2 rounded-pill mb-2">Effortless Scheduling</span>
    <h1 class="fw-bold mb-3">Quick Pathology Test Booking</h1>
    <p class="text-muted max-w-700 mx-auto">
      Select your required tests, choose home collection or lab visit, and our patient support team will confirm your slot promptly.
    </p>
  </div>
</section>

<section class="py-5 bg-white">
  <div class="container">
    
    <?php if ($successBooking): ?>
      <!-- Booking Confirmation Screen -->
      <div class="max-w-700 mx-auto">
        <div class="card border-0 shadow-lg rounded-4 overflow-hidden">
          <div class="bg-success text-white p-4 text-center">
            <i class="bi bi-check-circle-fill display-3 mb-2"></i>
            <h3 class="fw-bold mb-1">Booking Confirmed!</h3>
            <p class="mb-0 text-white-50">Your test appointment request has been recorded in our laboratory system.</p>
          </div>
          
          <div class="p-4 p-md-5">
            <div class="d-flex justify-content-between align-items-center p-3 bg-light rounded-3 mb-4">
              <div>
                <span class="text-muted small">Booking Reference</span>
                <div class="fs-4 fw-bold font-monospace text-primary"><?= e($successBooking['ref']) ?></div>
              </div>
              <span class="badge bg-warning-subtle text-warning-emphasis px-3 py-2">Pending Triage</span>
            </div>

            <h5 class="fw-bold mb-3">Patient & Appointment Details</h5>
            <div class="row g-3 mb-4 small">
              <div class="col-sm-6">
                <span class="text-muted">Patient Name:</span>
                <div class="fw-semibold text-dark"><?= e($successBooking['name']) ?></div>
              </div>
              <div class="col-sm-6">
                <span class="text-muted">Mobile Number:</span>
                <div class="fw-semibold text-dark"><?= e($successBooking['phone']) ?></div>
              </div>
              <div class="col-sm-6">
                <span class="text-muted">Preferred Date:</span>
                <div class="fw-semibold text-dark"><?= date('D, d M Y', strtotime($successBooking['date'])) ?></div>
              </div>
              <div class="col-sm-6">
                <span class="text-muted">Preferred Slot:</span>
                <div class="fw-semibold text-dark"><?= e($successBooking['time']) ?></div>
              </div>
              <div class="col-12">
                <span class="text-muted">Collection Mode:</span>
                <div class="fw-semibold text-dark">
                  <?php if ($successBooking['is_home']): ?>
                    <span class="badge bg-info-subtle text-info-emphasis"><i class="bi bi-house-door me-1"></i> Home Sample Collection</span>
                    <div class="mt-1 text-muted"><?= e($successBooking['address']) ?></div>
                  <?php else: ?>
                    <span class="badge bg-secondary-subtle text-secondary-emphasis"><i class="bi bi-hospital me-1"></i> Laboratory Walk-In</span>
                  <?php endif; ?>
                </div>
              </div>
            </div>

            <h5 class="fw-bold mb-2">Selected Pathology Tests</h5>
            <div class="table-responsive border rounded-3 mb-4">
              <table class="table table-sm mb-0 align-middle">
                <thead class="table-light">
                  <tr>
                    <th>Test Name</th>
                    <th>Code</th>
                    <th class="text-end">Fee</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($successBooking['tests'] as $bt): ?>
                    <tr>
                      <td class="fw-semibold"><?= e($bt['test_name']) ?></td>
                      <td class="font-monospace text-muted small"><?= e($bt['test_code']) ?></td>
                      <td class="text-end fw-bold"><?= format_currency((float)$bt['price']) ?></td>
                    </tr>
                  <?php endforeach; ?>
                  <tr class="table-light fw-bold">
                    <td colspan="2">Estimated Order Total</td>
                    <td class="text-end text-primary fs-6"><?= format_currency($successBooking['total']) ?></td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div class="alert alert-info small mb-4">
              <i class="bi bi-info-circle-fill me-1"></i> Our laboratory staff will contact your phone number (<?= e($successBooking['phone']) ?>) within 15 minutes to confirm the appointment and phlebotomist dispatch.
            </div>

            <div class="d-flex flex-wrap gap-2 justify-content-center">
              <button onclick="window.print()" class="btn btn-outline-secondary">
                <i class="bi bi-printer me-1"></i> Print Slip
              </button>
              <a href="<?= BASE_URL ?>/booking.php" class="btn btn-outline-primary">
                <i class="bi bi-plus-circle me-1"></i> Book Another Test
              </a>
              <a href="<?= BASE_URL ?>/index.php" class="btn btn-primary-lm">
                <i class="bi bi-house me-1"></i> Return Home
              </a>
            </div>
          </div>
        </div>
      </div>

    <?php else: ?>

      <!-- Booking Form -->
      <div class="row g-5">
        <div class="col-lg-8">
          
          <?php if (!empty($errors)): ?>
            <div class="alert alert-danger shadow-sm mb-4">
              <div class="fw-bold mb-1"><i class="bi bi-exclamation-triangle-fill me-1"></i> Please resolve the following errors:</div>
              <ul class="mb-0 ps-3">
                <?php foreach ($errors as $err): ?>
                  <li><?= e($err) ?></li>
                <?php endforeach; ?>
              </ul>
            </div>
          <?php endif; ?>

          <form action="<?= BASE_URL ?>/booking.php" method="POST" id="bookingForm" class="needs-validation">
            <?= csrf_field() ?>

            <!-- Patient Demographics Card -->
            <div class="card border rounded-4 p-4 shadow-sm mb-4">
              <h5 class="fw-bold mb-3"><i class="bi bi-person-circle text-primary me-2"></i>Patient Information</h5>
              
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label fw-semibold">Patient Full Name <span class="text-danger">*</span></label>
                  <input type="text" name="patient_name" class="form-control" placeholder="e.g. Ramesh Kumar" value="<?= e($_POST['patient_name'] ?? '') ?>" required>
                </div>

                <div class="col-md-6">
                  <label class="form-label fw-semibold">Mobile Number <span class="text-danger">*</span></label>
                  <input type="tel" name="phone" class="form-control" placeholder="10-digit mobile number" maxlength="15" value="<?= e($_POST['phone'] ?? '') ?>" required>
                  <div class="form-text">We send booking updates and your digital report to this number.</div>
                </div>

                <div class="col-md-4">
                  <label class="form-label fw-semibold">Age (Years) <span class="text-danger">*</span></label>
                  <input type="number" name="age" class="form-control" min="1" max="120" placeholder="e.g. 35" value="<?= e($_POST['age'] ?? '') ?>" required>
                </div>

                <div class="col-md-4">
                  <label class="form-label fw-semibold">Sex / Biological Gender <span class="text-danger">*</span></label>
                  <select name="gender" class="form-select" required>
                    <option value="Male" <?= (($_POST['gender'] ?? '') === 'Male') ? 'selected' : '' ?>>Male</option>
                    <option value="Female" <?= (($_POST['gender'] ?? '') === 'Female') ? 'selected' : '' ?>>Female</option>
                    <option value="Other" <?= (($_POST['gender'] ?? '') === 'Other') ? 'selected' : '' ?>>Other</option>
                  </select>
                </div>

                <div class="col-md-4">
                  <label class="form-label fw-semibold">Email Address (Optional)</label>
                  <input type="email" name="email" class="form-control" placeholder="patient@example.com" value="<?= e($_POST['email'] ?? '') ?>">
                </div>

                <div class="col-md-12">
                  <label class="form-label fw-semibold">Referring Doctor (Optional)</label>
                  <input type="text" name="referring_doctor" class="form-control" placeholder="e.g. Dr. A. Gupta / Self" value="<?= e($_POST['referring_doctor'] ?? '') ?>">
                </div>
              </div>
            </div>

            <!-- Test Selection Card -->
            <div class="card border rounded-4 p-4 shadow-sm mb-4">
              <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="fw-bold mb-0"><i class="bi bi-eyedropper text-primary me-2"></i>Select Pathology Tests <span class="text-danger">*</span></h5>
                <span class="badge bg-light text-muted border">Multi-select enabled</span>
              </div>
              <p class="text-muted small mb-3">Check all tests you wish to book. Total price calculates automatically.</p>

              <!-- Search tests in dropdown/list -->
              <div class="mb-3">
                <input type="text" id="testListFilter" class="form-control form-control-sm" placeholder="Type to filter test list below..." onkeyup="filterTestsCheckboxList()">
              </div>

              <div class="test-selection-list border rounded-3 p-3 overflow-auto" style="max-height: 280px;" id="testCheckboxContainer">
                <?php 
                $selectedPostTests = $_POST['test_ids'] ?? [];
                if ($preselectedTestId > 0 && !in_array($preselectedTestId, $selectedPostTests, false)) {
                    $selectedPostTests[] = $preselectedTestId;
                }
                foreach ($tests as $t): 
                    $isChecked = in_array((int)$t['id'], array_map('intval', $selectedPostTests), true);
                ?>
                  <div class="form-check p-2 rounded mb-1 test-item-row" data-name="<?= strtolower($t['test_name'] . ' ' . $t['test_code']) ?>">
                    <input class="form-check-input test-checkbox" type="checkbox" name="test_ids[]" value="<?= (int)$t['id'] ?>" data-price="<?= (float)$t['price'] ?>" data-name="<?= e($t['test_name']) ?>" id="test_<?= (int)$t['id'] ?>" <?= $isChecked ? 'checked' : '' ?> onchange="calculateBookingTotal()">
                    <label class="form-check-label d-flex justify-content-between w-100 cursor-pointer" for="test_<?= (int)$t['id'] ?>">
                      <span>
                        <strong><?= e($t['test_name']) ?></strong> 
                        <span class="text-muted small">(<?= e($t['test_code']) ?> • <?= e($t['specimen_type']) ?>)</span>
                      </span>
                      <span class="fw-bold text-primary"><?= format_currency((float)$t['price']) ?></span>
                    </label>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>

            <!-- Schedule & Collection Preference -->
            <div class="card border rounded-4 p-4 shadow-sm mb-4">
              <h5 class="fw-bold mb-3"><i class="bi bi-calendar-event text-primary me-2"></i>Appointment & Collection Preference</h5>

              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label fw-semibold">Preferred Date <span class="text-danger">*</span></label>
                  <input type="date" name="preferred_date" class="form-control" min="<?= date('Y-m-d') ?>" value="<?= e($_POST['preferred_date'] ?? date('Y-m-d')) ?>" required>
                </div>

                <div class="col-md-6">
                  <label class="form-label fw-semibold">Preferred Time Slot <span class="text-danger">*</span></label>
                  <select name="preferred_time_slot" class="form-select" required>
                    <option value="">Select Time Slot...</option>
                    <option value="07:00 AM - 09:00 AM" <?= (($_POST['preferred_time_slot'] ?? '') === '07:00 AM - 09:00 AM') ? 'selected' : '' ?>>07:00 AM - 09:00 AM (Early Morning Fasting)</option>
                    <option value="09:00 AM - 11:00 AM" <?= (($_POST['preferred_time_slot'] ?? '') === '09:00 AM - 11:00 AM') ? 'selected' : '' ?>>09:00 AM - 11:00 AM</option>
                    <option value="11:00 AM - 01:00 PM" <?= (($_POST['preferred_time_slot'] ?? '') === '11:00 AM - 01:00 PM') ? 'selected' : '' ?>>11:00 AM - 01:00 PM</option>
                    <option value="02:00 PM - 04:00 PM" <?= (($_POST['preferred_time_slot'] ?? '') === '02:00 PM - 04:00 PM') ? 'selected' : '' ?>>02:00 PM - 04:00 PM</option>
                    <option value="04:00 PM - 06:00 PM" <?= (($_POST['preferred_time_slot'] ?? '') === '04:00 PM - 06:00 PM') ? 'selected' : '' ?>>04:00 PM - 06:00 PM</option>
                    <option value="06:00 PM - 08:00 PM" <?= (($_POST['preferred_time_slot'] ?? '') === '06:00 PM - 08:00 PM') ? 'selected' : '' ?>>06:00 PM - 08:00 PM (Evening Slot)</option>
                  </select>
                </div>

                <div class="col-12">
                  <div class="form-check form-switch p-3 bg-light rounded-3 border">
                    <input class="form-check-input ms-0 me-3" type="checkbox" role="switch" name="is_home_collection" value="1" id="homeCollectionSwitch" <?= (!empty($_POST['is_home_collection']) || isset($_GET['home'])) ? 'checked' : '' ?> onchange="toggleHomeAddressBox()">
                    <label class="form-check-label fw-bold" for="homeCollectionSwitch">
                      <i class="bi bi-house-door text-success me-1"></i> Request Home Sample Collection at Doorstep
                    </label>
                    <div class="text-muted small mt-1 ms-5">Our phlebotomist visits with cold-chain vacuum vacutainers.</div>
                  </div>
                </div>

                <div class="col-12" id="homeAddressContainer" style="<?= (!empty($_POST['is_home_collection']) || isset($_GET['home'])) ? '' : 'display: none;' ?>">
                  <label class="form-label fw-semibold">Doorstep Address & Landmarks <span class="text-danger">*</span></label>
                  <textarea name="collection_address" class="form-control" rows="2" placeholder="House/Flat No, Apartment, Street, Landmark, Pincode..."><?= e($_POST['collection_address'] ?? '') ?></textarea>
                </div>

                <div class="col-12">
                  <label class="form-label fw-semibold">Special Instructions / Fasting Notes (Optional)</label>
                  <textarea name="notes" class="form-control" rows="2" placeholder="e.g. Patient is diabetic / On blood thinner medication / Wheelchair needed..."><?= e($_POST['notes'] ?? '') ?></textarea>
                </div>
              </div>
            </div>

            <!-- Consent & Submit -->
            <div class="card border rounded-4 p-4 shadow-sm mb-4">
              <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" name="consent" id="consentCheck" value="1" <?= !empty($_POST['consent']) ? 'checked' : 'checked' ?> required>
                <label class="form-check-label small text-muted" for="consentCheck">
                  I consent to the collection of clinical specimens by authorized laboratory personnel and agree to the <a href="<?= BASE_URL ?>/privacy-policy.php" target="_blank">Medical Privacy Policy</a> and <a href="<?= BASE_URL ?>/terms-conditions.php" target="_blank">Terms of Service</a>.
                </label>
              </div>

              <button type="submit" class="btn btn-primary-lm btn-lg w-100">
                <i class="bi bi-calendar-check me-2"></i> Submit Test Booking
              </button>
            </div>
          </form>

        </div>

        <!-- Sidebar Summary -->
        <div class="col-lg-4">
          <div class="card border rounded-4 p-4 shadow-sm sticky-top" style="top: 100px;">
            <h5 class="fw-bold mb-3"><i class="bi bi-receipt text-primary me-2"></i>Order Summary</h5>
            
            <div id="selectedTestsSummary" class="mb-3 small">
              <p class="text-muted fst-italic">No tests selected yet. Choose from the list.</p>
            </div>

            <hr>

            <div class="d-flex justify-content-between align-items-center mb-2">
              <span class="text-muted">Total Tests:</span>
              <span class="fw-bold" id="summaryCount">0</span>
            </div>

            <div class="d-flex justify-content-between align-items-center mb-3">
              <span class="fw-bold">Estimated Total:</span>
              <span class="fs-4 fw-bold text-primary" id="summaryTotal"><?= format_currency(0.0) ?></span>
            </div>

            <div class="p-3 bg-light rounded-3 small text-muted mb-3">
              <i class="bi bi-shield-check text-success me-1"></i> <strong>Zero Advance Required:</strong> You can pay online via UPI or settle payment upon sample collection.
            </div>

            <div class="text-center">
              <a href="tel:<?= e(Settings::get('lab_phone', '+91 98765 43210')) ?>" class="text-decoration-none small text-secondary">
                <i class="bi bi-telephone me-1"></i> Need help? Call <?= e(Settings::get('lab_phone', '+91 98765 43210')) ?>
              </a>
            </div>
          </div>
        </div>
      </div>

    <?php endif; ?>

  </div>
</section>

<script>
function toggleHomeAddressBox() {
  const isChecked = document.getElementById('homeCollectionSwitch').checked;
  const container = document.getElementById('homeAddressContainer');
  container.style.display = isChecked ? 'block' : 'none';
}

function filterTestsCheckboxList() {
  const filter = document.getElementById('testListFilter').value.toLowerCase();
  const rows = document.querySelectorAll('.test-item-row');
  rows.forEach(r => {
    const text = r.getAttribute('data-name');
    r.style.display = text.includes(filter) ? '' : 'none';
  });
}

function calculateBookingTotal() {
  const checkboxes = document.querySelectorAll('.test-checkbox:checked');
  let total = 0;
  let count = checkboxes.length;
  let summaryHtml = '';

  if (count === 0) {
    summaryHtml = '<p class="text-muted fst-italic">No tests selected yet.</p>';
  } else {
    summaryHtml = '<ul class="list-unstyled mb-0">';
    checkboxes.forEach(cb => {
      const price = parseFloat(cb.getAttribute('data-price') || '0');
      const name = cb.getAttribute('data-name');
      total += price;
      summaryHtml += `<li class="d-flex justify-content-between py-1 border-bottom">
        <span class="text-truncate me-2" style="max-width: 180px;">${name}</span>
        <span class="fw-semibold">₹${price.toFixed(2)}</span>
      </li>`;
    });
    summaryHtml += '</ul>';
  }

  document.getElementById('selectedTestsSummary').innerHTML = summaryHtml;
  document.getElementById('summaryCount').innerText = count;
  document.getElementById('summaryTotal').innerText = '₹' + total.toFixed(2);
}

document.addEventListener('DOMContentLoaded', () => {
  calculateBookingTotal();
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

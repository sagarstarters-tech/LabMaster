<?php
/**
 * LabMaster - Transparent Pathology Test Price List
 * Clean, printable, and searchable table
 */

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Pathology Test Price List | ' . Settings::get('lab_name', 'LabMaster Diagnostic & Research Center');
$pageDesc  = 'Complete transparent pathology laboratory price list for all diagnostic tests, health packages, and organ profiles.';
$activePage = 'price-list';

// Fetch tests grouped by category
$categories = Database::fetchAll("SELECT * FROM `test_categories` ORDER BY `name` ASC");
$allTests = Database::fetchAll(
    "SELECT t.*, c.name as category_name,
     (SELECT COUNT(*) FROM `test_parameters` tp WHERE tp.test_id = t.id) as param_count
     FROM `tests` t
     JOIN `test_categories` c ON t.category_id = c.id
     WHERE t.is_active = 1
     ORDER BY c.name ASC, t.test_name ASC"
);

require_once __DIR__ . '/includes/header.php';
?>

<!-- Header Banner -->
<section class="py-5 bg-light border-bottom d-print-none">
  <div class="container text-center">
    <span class="badge bg-primary-subtle text-primary fw-semibold px-3 py-2 rounded-pill mb-2">Transparent Healthcare Pricing</span>
    <h1 class="fw-bold mb-3">Pathology Test Price Schedule</h1>
    <p class="text-muted max-w-700 mx-auto">
      No hidden costs. All prices are inclusive of sample collection consumables, barcoded vacutainer tubes, and digital verified reports.
    </p>

    <div class="d-flex justify-content-center gap-3 mt-4">
      <button onclick="window.print()" class="btn btn-outline-secondary">
        <i class="bi bi-printer me-1"></i> Print Price List
      </button>
      <a href="<?= BASE_URL ?>/booking.php" class="btn btn-primary-lm">
        <i class="bi bi-calendar-check me-1"></i> Quick Test Booking
      </a>
    </div>
  </div>
</section>

<!-- Price List Table -->
<section class="py-5 bg-white">
  <div class="container">
    
    <!-- Client-side Fast Search -->
    <div class="row mb-4 d-print-none">
      <div class="col-md-6 mx-auto">
        <div class="input-group">
          <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
          <input type="text" id="priceSearch" class="form-control" placeholder="Quick filter by test name or code..." onkeyup="filterPriceTable()">
        </div>
      </div>
    </div>

    <div class="table-responsive shadow-sm rounded-4 border">
      <table class="table table-hover align-middle mb-0" id="priceTable">
        <thead class="table-dark">
          <tr>
            <th class="ps-4">Code</th>
            <th>Test Name / Profile</th>
            <th>Category</th>
            <th>Specimen</th>
            <th>Parameters</th>
            <th>TAT</th>
            <th class="text-end">Standard Fee</th>
            <th class="text-center pe-4 d-print-none">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($allTests)): ?>
            <tr>
              <td colspan="8" class="text-center py-4 text-muted">No diagnostic tests currently listed.</td>
            </tr>
          <?php else: ?>
            <?php foreach ($allTests as $t): ?>
            <tr>
              <td class="ps-4 font-monospace fw-bold text-primary"><?= e($t['test_code']) ?></td>
              <td>
                <div class="fw-bold text-dark"><?= e($t['test_name']) ?></div>
                <?php if (!empty($t['instructions'])): ?>
                  <div class="small text-muted"><i class="bi bi-info-circle me-1"></i><?= e($t['instructions']) ?></div>
                <?php endif; ?>
              </td>
              <td><span class="badge bg-light text-dark border"><?= e($t['category_name']) ?></span></td>
              <td class="small text-secondary"><?= e($t['specimen_type']) ?></td>
              <td><span class="badge bg-info-subtle text-info-emphasis"><?= (int)$t['param_count'] ?></span></td>
              <td class="small text-muted"><?= e($t['turnaround_time'] ?? '4-6 Hrs') ?></td>
              <td class="text-end fw-bold text-dark fs-6"><?= format_currency((float)$t['price']) ?></td>
              <td class="text-center pe-4 d-print-none">
                <a href="<?= BASE_URL ?>/booking.php?test_id=<?= (int)$t['id'] ?>" class="btn btn-sm btn-outline-primary">
                  Book
                </a>
              </td>
            </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Pricing Policy Note -->
    <div class="p-4 bg-light rounded-4 border mt-4 small text-muted">
      <h6 class="fw-bold text-dark mb-2"><i class="bi bi-shield-check text-primary me-1"></i> Laboratory Pricing Policy</h6>
      <ul class="mb-0 ps-3">
        <li>Routine home sample collection fee may apply depending on delivery distance or order value.</li>
        <li>Specialized send-out genetic and biopsy tests are priced in accordance with reference referral labs.</li>
        <li>Emergency / STAT emergency processing is available upon request with prioritized 2-hour reporting.</li>
      </ul>
    </div>

  </div>
</section>

<script>
function filterPriceTable() {
  const input = document.getElementById("priceSearch");
  const filter = input.value.toUpperCase();
  const table = document.getElementById("priceTable");
  const tr = table.getElementsByTagName("tr");

  for (let i = 1; i < tr.length; i++) {
    const tdCode = tr[i].getElementsByTagName("td")[0];
    const tdName = tr[i].getElementsByTagName("td")[1];
    const tdCat  = tr[i].getElementsByTagName("td")[2];
    if (tdCode || tdName || tdCat) {
      const txt = (tdCode ? tdCode.textContent : "") + " " + 
                  (tdName ? tdName.textContent : "") + " " + 
                  (tdCat ? tdCat.textContent : "");
      if (txt.toUpperCase().indexOf(filter) > -1) {
        tr[i].style.display = "";
      } else {
        tr[i].style.display = "none";
      }
    }
  }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

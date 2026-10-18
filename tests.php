<?php
/**
 * LabMaster - Pathology Test Catalog
 * 100% dynamic test search, category filtering, and booking links
 */

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Pathology Tests Catalog | ' . Settings::get('lab_name', 'LabMaster Diagnostic & Research Center');
$pageDesc  = 'Browse our full pathology test catalog, clinical panels, turnaround times, specimen requirements, and transparent prices.';
$activePage = 'tests';

$categoryId = isset($_GET['cat']) ? (int)$_GET['cat'] : 0;
$search = trim($_GET['q'] ?? '');

// Fetch all categories for filter tabs
$categories = Database::fetchAll("SELECT * FROM `test_categories` ORDER BY `name` ASC");

// Build dynamic test query
$sql = "SELECT t.*, c.name as category_name,
        (SELECT COUNT(*) FROM `test_parameters` tp WHERE tp.test_id = t.id) as param_count
        FROM `tests` t
        JOIN `test_categories` c ON t.category_id = c.id
        WHERE t.is_active = 1";
$params = [];

if ($categoryId > 0) {
    $sql .= " AND t.category_id = ?";
    $params[] = $categoryId;
}

if (!empty($search)) {
    $sql .= " AND (t.test_name LIKE ? OR t.test_code LIKE ? OR t.specimen_type LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

$sql .= " ORDER BY c.name ASC, t.test_name ASC";
$tests = Database::fetchAll($sql, $params);

require_once __DIR__ . '/includes/header.php';
?>

<!-- Header Banner -->
<section class="py-5 bg-light border-bottom">
  <div class="container text-center">
    <span class="badge bg-primary-subtle text-primary fw-semibold px-3 py-2 rounded-pill mb-2">Diagnostic Directory</span>
    <h1 class="fw-bold mb-3">Pathology Test Catalog & Profiles</h1>
    <p class="text-muted max-w-700 mx-auto">
      Search and explore our clinical pathology menu with detailed preparation instructions, specimen specifications, and transparent fees.
    </p>

    <!-- Search Form -->
    <div class="max-w-700 mx-auto mt-4">
      <form action="<?= BASE_URL ?>/tests.php" method="GET" class="d-flex gap-2">
        <?php if ($categoryId > 0): ?>
          <input type="hidden" name="cat" value="<?= $categoryId ?>">
        <?php endif; ?>
        <div class="input-group input-group-lg shadow-sm">
          <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
          <input type="text" name="q" class="form-control border-start-0" placeholder="Search test name, code, or specimen (e.g. CBC, Lipid, Thyroid, EDTA)..." value="<?= e($search) ?>">
          <button class="btn btn-primary-lm" type="submit">Search</button>
        </div>
      </form>
    </div>
  </div>
</section>

<!-- Test Catalog Content -->
<section class="py-5 bg-white">
  <div class="container">
    
    <!-- Category Filter Tabs -->
    <div class="d-flex flex-wrap gap-2 mb-4 justify-content-center">
      <a href="<?= BASE_URL ?>/tests.php<?= !empty($search) ? '?q=' . urlencode($search) : '' ?>" class="btn btn-sm <?= $categoryId === 0 ? 'btn-primary' : 'btn-outline-secondary' ?> rounded-pill px-3">
        All Categories
      </a>
      <?php foreach ($categories as $cat): ?>
        <a href="<?= BASE_URL ?>/tests.php?cat=<?= (int)$cat['id'] ?><?= !empty($search) ? '&q=' . urlencode($search) : '' ?>" class="btn btn-sm <?= $categoryId === (int)$cat['id'] ? 'btn-primary' : 'btn-outline-secondary' ?> rounded-pill px-3">
          <?= e($cat['name']) ?>
        </a>
      <?php endforeach; ?>
    </div>

    <?php if (empty($tests)): ?>
      <div class="text-center py-5">
        <div class="display-1 text-muted"><i class="bi bi-clipboard-x"></i></div>
        <h4 class="fw-bold mt-3">No Pathology Tests Found</h4>
        <p class="text-muted">No clinical tests matched your search query. Try clearing your search or exploring other categories.</p>
        <a href="<?= BASE_URL ?>/tests.php" class="btn btn-outline-lm">View Full Catalog</a>
      </div>
    <?php else: ?>
      <div class="d-flex justify-content-between align-items-center mb-3 text-muted small">
        <span>Showing <strong><?= count($tests) ?></strong> clinical test<?= count($tests) !== 1 ? 's' : '' ?></span>
        <?php if (!empty($search) || $categoryId > 0): ?>
          <a href="<?= BASE_URL ?>/tests.php" class="text-danger text-decoration-none"><i class="bi bi-x-circle me-1"></i> Clear Filters</a>
        <?php endif; ?>
      </div>

      <div class="row g-4">
        <?php foreach ($tests as $test): ?>
        <div class="col-lg-4 col-md-6">
          <div class="card h-100 border shadow-sm rounded-4 test-card d-flex flex-column justify-content-between">
            <div>
              <div class="d-flex justify-content-between align-items-start mb-2">
                <span class="badge bg-primary-subtle text-primary"><?= e($test['category_name']) ?></span>
                <span class="test-code"><?= e($test['test_code']) ?></span>
              </div>
              <h5 class="fw-bold text-dark mb-2"><?= e($test['test_name']) ?></h5>
              
              <div class="small text-muted mb-3">
                <div><i class="bi bi-eyedropper text-primary me-1"></i> Specimen: <strong><?= e($test['specimen_type']) ?></strong></div>
                <div><i class="bi bi-clock-history text-primary me-1"></i> Turnaround: <strong><?= e($test['turnaround_time'] ?? 'Same Day (4-6 Hrs)') ?></strong></div>
                <div><i class="bi bi-list-check text-primary me-1"></i> Parameters: <strong><?= (int)$test['param_count'] ?> Clinical Parameter(s)</strong></div>
              </div>

              <?php if (!empty($test['instructions'])): ?>
                <div class="small bg-light p-2 rounded text-secondary mb-3">
                  <i class="bi bi-info-circle text-info me-1"></i> <strong>Prep:</strong> <?= e($test['instructions']) ?>
                </div>
              <?php endif; ?>
            </div>

            <div class="pt-3 border-top d-flex align-items-center justify-content-between">
              <div>
                <span class="text-muted small">Standard Fee</span>
                <div class="fs-4 fw-bold text-dark"><?= format_currency((float)$test['price']) ?></div>
              </div>
              <div class="d-flex gap-2">
                <a href="<?= BASE_URL ?>/booking.php?test_id=<?= (int)$test['id'] ?>" class="btn btn-primary-lm btn-sm">
                  <i class="bi bi-calendar-check me-1"></i> Book
                </a>
              </div>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

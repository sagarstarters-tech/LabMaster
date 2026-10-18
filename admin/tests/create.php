<?php
/**
 * LabMaster - Create New Pathology Test
 */

declare(strict_types=1);

$pageTitle = 'Create Pathology Test';
$activeNav = 'tests';

require_once dirname(__DIR__) . '/includes/auth_guard.php';
Auth::requirePermission('tests.manage');

$categories = Database::fetchAll("SELECT * FROM `test_categories` WHERE `is_active` = 1 ORDER BY `sort_order` ASC");
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $testName = trim($_POST['test_name'] ?? '');
    $testCode = strtoupper(trim($_POST['test_code'] ?? ''));
    $shortName = trim($_POST['short_name'] ?? '');
    $categoryId = (int)($_POST['category_id'] ?? 0);
    $specimenType = trim($_POST['specimen_type'] ?? 'Plain Serum');
    $department = trim($_POST['department'] ?? '');
    $price = (float)($_POST['price'] ?? 0);
    $costPrice = (float)($_POST['cost_price'] ?? 0);
    $turnaroundTime = trim($_POST['turnaround_time'] ?? 'Same Day (4-6 Hours)');
    $method = trim($_POST['method'] ?? '');
    $instructions = trim($_POST['instructions'] ?? '');
    $clinicalSignificance = trim($_POST['clinical_significance'] ?? '');
    $isPackage = isset($_POST['is_package']) ? 1 : 0;
    $isActive = isset($_POST['is_active']) ? 1 : 0;

    if (empty($testName)) {
        $errors[] = 'Test name is required.';
    }
    if (empty($testCode)) {
        $errors[] = 'Unique test code is required.';
    }
    if ($categoryId <= 0) {
        $errors[] = 'Please select a test category.';
    }
    if ($price < 0) {
        $errors[] = 'Price cannot be negative.';
    }

    // Check duplicate code
    if (empty($errors)) {
        $exists = Database::fetch("SELECT `id` FROM `tests` WHERE `test_code` = ? LIMIT 1", [$testCode]);
        if ($exists) {
            $errors[] = "A test with code '{$testCode}' already exists.";
        }
    }

    if (empty($errors)) {
        Database::execute(
            "INSERT INTO `tests` (`category_id`, `test_code`, `test_name`, `short_name`, `specimen_type`, `department`, `price`, `cost_price`, `turnaround_time`, `method`, `instructions`, `clinical_significance`, `is_package`, `is_active`)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [$categoryId, $testCode, $testName, $shortName, $specimenType, $department, $price, $costPrice, $turnaroundTime, $method, $instructions, $clinicalSignificance, $isPackage, $isActive]
        );

        $testId = Database::lastInsertId();
        log_audit('Test Created', 'Tests', $testCode, "Created new test {$testName} ({$testCode}).");
        set_flash('success', "Test {$testName} created! Now add test parameters and biological reference intervals.");
        header('Location: ' . ADMIN_URL . '/tests/edit.php?id=' . $testId . '#parameters');
        exit;
    }
}

require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="row justify-content-center">
  <div class="col-lg-9">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <div>
        <h2 class="h4 fw-bold mb-1">Create Pathology Test</h2>
        <p class="text-muted small mb-0">Add a new investigation to the laboratory diagnostic menu</p>
      </div>
      <a href="<?= ADMIN_URL ?>/tests/index.php" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i> Back to Test Catalog
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

          <div class="row g-3 mb-3">
            <div class="col-md-5">
              <label class="form-label small fw-semibold">Test Name <span class="text-danger">*</span></label>
              <input type="text" name="test_name" class="form-control" placeholder="e.g. Serum Ferritin Assay" value="<?= e($_POST['test_name'] ?? '') ?>" required>
            </div>

            <div class="col-md-3">
              <label class="form-label small fw-semibold">Test Code <span class="text-danger">*</span></label>
              <input type="text" name="test_code" class="form-control font-monospace text-uppercase" placeholder="e.g. FERRITIN" value="<?= e($_POST['test_code'] ?? '') ?>" required>
            </div>

            <div class="col-md-4">
              <label class="form-label small fw-semibold">Aliases & Common Abbreviations</label>
              <input type="text" name="short_name" class="form-control" placeholder="e.g. Ferritin, S. Ferritin" value="<?= e($_POST['short_name'] ?? '') ?>">
            </div>

            <div class="col-md-6">
              <label class="form-label small fw-semibold">Pathology Category <span class="text-danger">*</span></label>
              <select name="category_id" class="form-select" required>
                <option value="">Select Category</option>
                <?php foreach ($categories as $cat): ?>
                  <option value="<?= $cat['id'] ?>" <?= ((int)($_POST['category_id'] ?? 0) === (int)$cat['id']) ? 'selected' : '' ?>>
                    <?= e($cat['name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-md-6">
              <label class="form-label small fw-semibold">Department</label>
              <input type="text" name="department" class="form-control" placeholder="e.g. Clinical Biochemistry / Immunoassay" value="<?= e($_POST['department'] ?? '') ?>">
            </div>

            <div class="col-md-6">
              <label class="form-label small fw-semibold">Specimen Type <span class="text-danger">*</span></label>
              <input type="text" name="specimen_type" class="form-control" placeholder="e.g. Plain Serum (2ml) or EDTA Whole Blood" value="<?= e($_POST['specimen_type'] ?? 'Plain Serum') ?>" required>
            </div>

            <div class="col-md-3">
              <label class="form-label small fw-semibold">Patient Price (₹) <span class="text-danger">*</span></label>
              <input type="number" step="0.01" name="price" class="form-control" placeholder="0.00" value="<?= e((string)($_POST['price'] ?? '')) ?>" required>
            </div>

            <div class="col-md-3">
              <label class="form-label small fw-semibold">Internal Cost (₹)</label>
              <input type="number" step="0.01" name="cost_price" class="form-control" placeholder="0.00" value="<?= e((string)($_POST['cost_price'] ?? '0.00')) ?>">
            </div>

            <div class="col-md-6">
              <label class="form-label small fw-semibold">Turnaround Time (TAT)</label>
              <input type="text" name="turnaround_time" class="form-control" placeholder="e.g. 4-6 Hours or Same Day" value="<?= e($_POST['turnaround_time'] ?? 'Same Day (4-6 Hours)') ?>">
            </div>

            <div class="col-md-6">
              <label class="form-label small fw-semibold">Methodology / Analyzer</label>
              <input type="text" name="method" class="form-control" placeholder="e.g. Chemiluminescence Immunoassay (CLIA)" value="<?= e($_POST['method'] ?? '') ?>">
            </div>

            <div class="col-12">
              <label class="form-label small fw-semibold">Sample Preparation & Fasting Instructions</label>
              <textarea name="instructions" class="form-control" rows="2" placeholder="e.g. Overnight 10-12 hours fasting required. Drink water only."><?= e($_POST['instructions'] ?? '') ?></textarea>
            </div>

            <div class="col-12">
              <label class="form-label small fw-semibold">Clinical Significance / Notes</label>
              <textarea name="clinical_significance" class="form-control" rows="2" placeholder="Diagnostic value for physicians..."><?= e($_POST['clinical_significance'] ?? '') ?></textarea>
            </div>

            <div class="col-md-6">
              <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" id="isActiveSwitch" name="is_active" value="1" checked>
                <label class="form-check-label fw-semibold small" for="isActiveSwitch">Active Test (Available for public booking and order entry)</label>
              </div>
            </div>

            <div class="col-md-6">
              <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" id="isPackageSwitch" name="is_package" value="1">
                <label class="form-check-label fw-semibold small" for="isPackageSwitch">Multi-Parameter Profile / Panel (e.g. CBC, LFT, Lipid, KFT)</label>
              </div>
            </div>
          </div>

          <div class="d-flex justify-content-between align-items-center mt-4 pt-2 border-top">
            <a href="<?= ADMIN_URL ?>/tests/index.php" class="btn btn-light">Cancel</a>
            <button type="submit" class="btn btn-save">
              <i class="bi bi-arrow-right-circle me-1"></i> Save & Configure Parameters &rarr;
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>

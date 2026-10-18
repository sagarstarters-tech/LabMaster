<?php
/**
 * LabMaster - Advanced Pathology Test & Parameters Editor
 */

declare(strict_types=1);

$testId = (int)($_GET['id'] ?? 0);
$pageTitle = 'Configure Pathology Test';
$activeNav = 'tests';

require_once dirname(__DIR__) . '/includes/auth_guard.php';
Auth::requirePermission('tests.manage');

$test = Database::fetch("SELECT * FROM `tests` WHERE `id` = ?", [$testId]);
if (!$test) {
    set_flash('danger', 'Pathology test not found.');
    header('Location: ' . ADMIN_URL . '/tests/index.php');
    exit;
}

$categories = Database::fetchAll("SELECT * FROM `test_categories` WHERE `is_active` = 1 ORDER BY `sort_order` ASC");
$activeTab = $_GET['tab'] ?? 'details';

// Handle Form Submissions: Details, Add Parameter, Add Reference Interval
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? '';

    // 1. Update Test Details
    if ($action === 'update_test_details') {
        $testName = trim($_POST['test_name'] ?? '');
        $testCode = strtoupper(trim($_POST['test_code'] ?? ''));
        $shortName = trim($_POST['short_name'] ?? '');
        $categoryId = (int)($_POST['category_id'] ?? 0);
        $specimenType = trim($_POST['specimen_type'] ?? 'Plain Serum');
        $department = trim($_POST['department'] ?? '');
        $price = (float)($_POST['price'] ?? 0);
        $costPrice = (float)($_POST['cost_price'] ?? 0);
        $turnaroundTime = trim($_POST['turnaround_time'] ?? 'Same Day');
        $method = trim($_POST['method'] ?? '');
        $instructions = trim($_POST['instructions'] ?? '');
        $clinicalSignificance = trim($_POST['clinical_significance'] ?? '');
        $isPackage = isset($_POST['is_package']) ? 1 : 0;
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if (empty($testName) || empty($testCode)) {
            $errors[] = 'Test name and code are required.';
        } else {
            Database::execute(
                "UPDATE `tests` 
                 SET `category_id` = ?, `test_code` = ?, `test_name` = ?, `short_name` = ?, `specimen_type` = ?, 
                     `department` = ?, `price` = ?, `cost_price` = ?, `turnaround_time` = ?, 
                     `method` = ?, `instructions` = ?, `clinical_significance` = ?, `is_package` = ?, `is_active` = ?
                 WHERE `id` = ?",
                [$categoryId, $testCode, $testName, $shortName, $specimenType, $department, $price, $costPrice, $turnaroundTime, $method, $instructions, $clinicalSignificance, $isPackage, $isActive, $testId]
            );
            log_audit('Test Updated', 'Tests', $testCode, "Updated test information for {$testName}.");
            set_flash('success', 'Test details saved successfully.');
            header("Location: " . ADMIN_URL . "/tests/edit.php?id={$testId}&tab=details");
            exit;
        }
    }

    // 2. Add New Parameter
    elseif ($action === 'add_parameter') {
        $paramName = trim($_POST['param_name'] ?? '');
        $paramCode = strtoupper(trim($_POST['param_code'] ?? ''));
        $unit = trim($_POST['unit'] ?? '');
        $resultType = trim($_POST['result_type'] ?? 'numeric');
        $dropdownOptions = trim($_POST['dropdown_options'] ?? '');
        $formula = trim($_POST['formula'] ?? '');
        $critLow = $_POST['critical_low'] !== '' ? (float)$_POST['critical_low'] : null;
        $critHigh = $_POST['critical_high'] !== '' ? (float)$_POST['critical_high'] : null;
        $sortOrder = (int)($_POST['sort_order'] ?? 0);

        if (empty($paramName) || empty($paramCode)) {
            $errors[] = 'Parameter name and code are required.';
        } else {
            Database::execute(
                "INSERT INTO `test_parameters` (`test_id`, `parameter_name`, `parameter_code`, `unit`, `result_type`, `dropdown_options`, `formula`, `critical_low`, `critical_high`, `sort_order`)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [$testId, $paramName, $paramCode, $unit, $resultType, $dropdownOptions, $formula, $critLow, $critHigh, $sortOrder]
            );
            log_audit('Parameter Added', 'Tests', $paramCode, "Added parameter {$paramName} to test {$test['test_code']}.");
            set_flash('success', "Parameter {$paramName} added successfully.");
            header("Location: " . ADMIN_URL . "/tests/edit.php?id={$testId}&tab=parameters");
            exit;
        }
    }

    // 3. Delete Parameter
    elseif ($action === 'delete_parameter') {
        $paramId = (int)($_POST['param_id'] ?? 0);
        Database::execute("DELETE FROM `test_parameters` WHERE `id` = ? AND `test_id` = ?", [$paramId, $testId]);
        log_audit('Parameter Deleted', 'Tests', (string)$paramId, "Removed parameter ID {$paramId} from test {$test['test_code']}.");
        set_flash('warning', 'Parameter removed.');
        header("Location: " . ADMIN_URL . "/tests/edit.php?id={$testId}&tab=parameters");
        exit;
    }

    // 4. Add Reference Interval
    elseif ($action === 'add_interval') {
        $paramId = (int)($_POST['parameter_id'] ?? 0);
        $gender = trim($_POST['gender'] ?? 'All');
        $ageMin = (int)($_POST['age_min'] ?? 0);
        $ageMax = (int)($_POST['age_max'] ?? 120);
        $ageUnit = trim($_POST['age_unit'] ?? 'Years');
        $minVal = $_POST['min_val'] !== '' ? (float)$_POST['min_val'] : null;
        $maxVal = $_POST['max_val'] !== '' ? (float)$_POST['max_val'] : null;
        $displayInterval = trim($_POST['display_interval'] ?? '');
        $interpretation = trim($_POST['interpretation'] ?? '');

        if ($paramId <= 0 || empty($displayInterval)) {
            $errors[] = 'Parameter selection and display interval text are required.';
        } else {
            Database::execute(
                "INSERT INTO `reference_intervals` (`parameter_id`, `gender`, `age_min`, `age_max`, `age_unit`, `min_val`, `max_val`, `display_interval`, `interpretation`)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [$paramId, $gender, $ageMin, $ageMax, $ageUnit, $minVal, $maxVal, $displayInterval, $interpretation]
            );
            log_audit('Reference Range Added', 'Tests', (string)$paramId, "Added reference interval '{$displayInterval}' for param ID {$paramId}.");
            set_flash('success', 'Reference interval added successfully.');
            header("Location: " . ADMIN_URL . "/tests/edit.php?id={$testId}&tab=intervals");
            exit;
        }
    }

    // 5. Delete Reference Interval
    elseif ($action === 'delete_interval') {
        $intervalId = (int)($_POST['interval_id'] ?? 0);
        Database::execute("DELETE FROM `reference_intervals` WHERE `id` = ?", [$intervalId]);
        set_flash('warning', 'Reference interval removed.');
        header("Location: " . ADMIN_URL . "/tests/edit.php?id={$testId}&tab=intervals");
        exit;
    }
}

// Fetch Parameters and Intervals
$parameters = Database::fetchAll("SELECT * FROM `test_parameters` WHERE `test_id` = ? ORDER BY `sort_order` ASC, `id` ASC", [$testId]);

$paramIds = array_column($parameters, 'id');
$intervals = [];
if (!empty($paramIds)) {
    $placeholders = implode(',', array_fill(0, count($paramIds), '?'));
    $intervals = Database::fetchAll(
        "SELECT r.*, p.parameter_name, p.parameter_code, p.unit 
         FROM `reference_intervals` r 
         JOIN `test_parameters` p ON r.parameter_id = p.id 
         WHERE r.parameter_id IN ({$placeholders}) 
         ORDER BY p.sort_order ASC, r.id ASC",
        $paramIds
    );
}

require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
  <div>
    <h2 class="h4 fw-bold mb-1">
      <?= e($test['test_name']) ?>
      <span class="badge bg-light text-dark border font-monospace ms-2"><?= e($test['test_code']) ?></span>
    </h2>
    <p class="text-muted small mb-0">Specimen: <?= e($test['specimen_type']) ?> | Price: <?= format_currency($test['price']) ?></p>
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

<!-- Tabs Container -->
<div class="admin-card">
  <div class="admin-card-header p-0 border-bottom">
    <ul class="nav nav-tabs border-0" id="testConfigTabs" role="tablist">
      <li class="nav-item">
        <a class="nav-link <?= $activeTab === 'details' ? 'active' : '' ?> px-4 py-3 fw-semibold border-0" href="?id=<?= $testId ?>&tab=details">
          <i class="bi bi-info-circle me-1 text-primary"></i> 1. Test Details
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?= $activeTab === 'parameters' ? 'active' : '' ?> px-4 py-3 fw-semibold border-0" href="?id=<?= $testId ?>&tab=parameters">
          <i class="bi bi-list-columns-reverse me-1 text-success"></i> 2. Parameters (<?= count($parameters) ?>)
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?= $activeTab === 'intervals' ? 'active' : '' ?> px-4 py-3 fw-semibold border-0" href="?id=<?= $testId ?>&tab=intervals">
          <i class="bi bi-sliders me-1 text-info"></i> 3. Reference Intervals (<?= count($intervals) ?>)
        </a>
      </li>
    </ul>
  </div>

  <div class="admin-card-body p-4">
    <?php if ($activeTab === 'details'): ?>
      <!-- TAB 1: Test Details Form -->
      <form method="POST" action="">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="update_test_details">

        <div class="row g-3">
          <div class="col-md-5">
            <label class="form-label small fw-semibold">Test Name <span class="text-danger">*</span></label>
            <input type="text" name="test_name" class="form-control" value="<?= e($test['test_name']) ?>" required>
          </div>
          <div class="col-md-3">
            <label class="form-label small fw-semibold">Test Code <span class="text-danger">*</span></label>
            <input type="text" name="test_code" class="form-control font-monospace" value="<?= e($test['test_code']) ?>" required>
          </div>
          <div class="col-md-4">
            <label class="form-label small fw-semibold">Aliases & Common Abbreviations</label>
            <input type="text" name="short_name" class="form-control" placeholder="e.g. CBC, Hb, SGPT, BSF" value="<?= e($test['short_name'] ?? '') ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label small fw-semibold">Pathology Category</label>
            <select name="category_id" class="form-select" required>
              <?php foreach ($categories as $cat): ?>
                <option value="<?= $cat['id'] ?>" <?= ((int)$test['category_id'] === (int)$cat['id']) ? 'selected' : '' ?>>
                  <?= e($cat['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label small fw-semibold">Department</label>
            <input type="text" name="department" class="form-control" value="<?= e($test['department'] ?? '') ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label small fw-semibold">Specimen Type</label>
            <input type="text" name="specimen_type" class="form-control" value="<?= e($test['specimen_type']) ?>" required>
          </div>
          <div class="col-md-3">
            <label class="form-label small fw-semibold">Patient Price (₹)</label>
            <input type="number" step="0.01" name="price" class="form-control" value="<?= e((string)$test['price']) ?>" required>
          </div>
          <div class="col-md-3">
            <label class="form-label small fw-semibold">Internal Cost (₹)</label>
            <input type="number" step="0.01" name="cost_price" class="form-control" value="<?= e((string)$test['cost_price']) ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label small fw-semibold">Turnaround Time</label>
            <input type="text" name="turnaround_time" class="form-control" value="<?= e($test['turnaround_time']) ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label small fw-semibold">Methodology</label>
            <input type="text" name="method" class="form-control" value="<?= e($test['method'] ?? '') ?>">
          </div>
          <div class="col-12">
            <label class="form-label small fw-semibold">Fasting & Specimen Instructions</label>
            <textarea name="instructions" class="form-control" rows="2"><?= e($test['instructions'] ?? '') ?></textarea>
          </div>
          <div class="col-12">
            <label class="form-label small fw-semibold">Clinical Significance</label>
            <textarea name="clinical_significance" class="form-control" rows="2"><?= e($test['clinical_significance'] ?? '') ?></textarea>
          </div>
          <div class="col-md-6">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" id="isActiveSwitch" name="is_active" value="1" <?= $test['is_active'] ? 'checked' : '' ?>>
              <label class="form-check-label fw-semibold small" for="isActiveSwitch">Active Test (Available for orders and public display)</label>
            </div>
          </div>
          <div class="col-md-6">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" id="isPackageSwitch" name="is_package" value="1" <?= $test['is_package'] ? 'checked' : '' ?>>
              <label class="form-check-label fw-semibold small" for="isPackageSwitch">Multi-Parameter Profile / Panel (e.g. CBC, LFT, Lipid, KFT)</label>
            </div>
          </div>
        </div>

        <div class="mt-4 pt-3 border-top text-end">
          <button type="submit" class="btn btn-save">
            <i class="bi bi-check2 me-1"></i> Save Test Details
          </button>
        </div>
      </form>

    <?php elseif ($activeTab === 'parameters'): ?>
      <!-- TAB 2: Parameters List & Add Form -->
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="fw-bold mb-0">Test Parameters & Observed Value Fields</h5>
        <button class="btn btn-sm btn-primary-lm" data-bs-toggle="modal" data-bs-target="#addParamModal">
          <i class="bi bi-plus-lg me-1"></i> Add Parameter
        </button>
      </div>

      <div class="table-responsive">
        <table class="table table-bordered table-hover align-middle mb-0 small">
          <thead class="table-light">
            <tr>
              <th style="width: 50px;">#</th>
              <th>Parameter Name</th>
              <th>Code</th>
              <th>Unit</th>
              <th>Type</th>
              <th>Formula / Dropdown Options</th>
              <th>Critical Low</th>
              <th>Critical High</th>
              <th class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($parameters)): ?>
              <tr><td colspan="9" class="text-center text-muted py-4">No parameters defined yet. Click "Add Parameter" to begin.</td></tr>
            <?php else: ?>
              <?php foreach ($parameters as $idx => $p): ?>
                <tr>
                  <td><?= $idx + 1 ?></td>
                  <td class="fw-bold"><?= e($p['parameter_name']) ?></td>
                  <td><span class="badge bg-light text-dark border font-monospace"><?= e($p['parameter_code']) ?></span></td>
                  <td><?= e($p['unit'] ?: '-') ?></td>
                  <td><span class="badge bg-info-subtle text-info-emphasis"><?= e($p['result_type']) ?></span></td>
                  <td class="text-truncate" style="max-width: 200px;">
                    <?= e($p['formula'] ?: ($p['dropdown_options'] ?: '-')) ?>
                  </td>
                  <td class="text-danger fw-semibold"><?= $p['critical_low'] !== null ? $p['critical_low'] : '-' ?></td>
                  <td class="text-danger fw-semibold"><?= $p['critical_high'] !== null ? $p['critical_high'] : '-' ?></td>
                  <td class="text-end">
                    <form method="POST" action="<?= ADMIN_URL ?>/tests/edit.php?id=<?= $testId ?>&tab=parameters" onsubmit="return confirm('Remove parameter <?= e($p['parameter_name']) ?>?');" class="d-inline">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="delete_parameter">
                      <input type="hidden" name="param_id" value="<?= $p['id'] ?>">
                      <button type="submit" class="btn btn-sm btn-outline-danger py-0 px-2" title="Remove Parameter"><i class="bi bi-trash"></i></button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

    <?php elseif ($activeTab === 'intervals'): ?>
      <!-- TAB 3: Reference Intervals -->
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="fw-bold mb-0">Age & Gender Specific Biological Reference Intervals</h5>
        <button class="btn btn-sm btn-primary-lm" data-bs-toggle="modal" data-bs-target="#addIntervalModal">
          <i class="bi bi-plus-lg me-1"></i> Add Reference Interval
        </button>
      </div>

      <div class="table-responsive">
        <table class="table table-bordered table-hover align-middle mb-0 small">
          <thead class="table-light">
            <tr>
              <th>Parameter</th>
              <th>Gender</th>
              <th>Age Range</th>
              <th>Min Val</th>
              <th>Max Val</th>
              <th>Report Display Interval</th>
              <th>Interpretation / Notes</th>
              <th class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($intervals)): ?>
              <tr><td colspan="8" class="text-center text-muted py-4">No reference intervals configured. Click "Add Reference Interval".</td></tr>
            <?php else: ?>
              <?php foreach ($intervals as $iv): ?>
                <tr>
                  <td class="fw-semibold">
                    <?= e($iv['parameter_name']) ?>
                    <span class="text-muted font-monospace small">(<?= e($iv['parameter_code']) ?>)</span>
                  </td>
                  <td>
                    <span class="badge bg-light text-dark border"><?= e($iv['gender']) ?></span>
                  </td>
                  <td><?= $iv['age_min'] ?> - <?= $iv['age_max'] ?> <?= e($iv['age_unit']) ?></td>
                  <td><?= $iv['min_val'] !== null ? $iv['min_val'] : '-' ?></td>
                  <td><?= $iv['max_val'] !== null ? $iv['max_val'] : '-' ?></td>
                  <td class="fw-bold text-primary"><?= e($iv['display_interval']) ?> <?= e($iv['unit']) ?></td>
                  <td class="text-muted"><?= e($iv['interpretation'] ?: '-') ?></td>
                  <td class="text-end">
                    <form method="POST" action="<?= ADMIN_URL ?>/tests/edit.php?id=<?= $testId ?>&tab=intervals" onsubmit="return confirm('Remove this reference interval?');" class="d-inline">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="delete_interval">
                      <input type="hidden" name="interval_id" value="<?= $iv['id'] ?>">
                      <button type="submit" class="btn btn-sm btn-outline-danger py-0 px-2" title="Remove Interval"><i class="bi bi-trash"></i></button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- Modal 1: Add Parameter -->
<div class="modal fade" id="addParamModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="POST" action="">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add_parameter">
        <div class="modal-header">
          <h5 class="modal-title fw-bold">Add Test Parameter</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-8">
              <label class="form-label small fw-semibold">Parameter Name <span class="text-danger">*</span></label>
              <input type="text" name="param_name" class="form-control" placeholder="e.g. Hemoglobin or Serum Creatinine" required>
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-semibold">Short Code <span class="text-danger">*</span></label>
              <input type="text" name="param_code" class="form-control font-monospace text-uppercase" placeholder="e.g. HB" required>
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-semibold">Unit of Measurement</label>
              <input type="text" name="unit" class="form-control" placeholder="e.g. g/dL, mg/dL, U/L, %">
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-semibold">Result Input Type</label>
              <select name="result_type" class="form-select">
                <option value="numeric">Numeric (Standard)</option>
                <option value="text">Free Text</option>
                <option value="dropdown">Dropdown Options</option>
                <option value="formula">Calculated Formula</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-semibold">Sort Order</label>
              <input type="number" name="sort_order" class="form-control" value="<?= count($parameters) + 1 ?>">
            </div>
            <div class="col-12">
              <label class="form-label small fw-semibold">Dropdown Options (if Type is Dropdown, comma separated)</label>
              <input type="text" name="dropdown_options" class="form-control" placeholder="e.g. Pale Yellow, Amber, Turbid OR Negative, Positive">
            </div>
            <div class="col-12">
              <label class="form-label small fw-semibold">Mathematical Formula (if Type is Formula)</label>
              <input type="text" name="formula" class="form-control font-monospace" placeholder="e.g. TBIL - DBIL or TP - ALB">
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-semibold text-danger">Critical Panic Low Limit (Alerts staff)</label>
              <input type="number" step="any" name="critical_low" class="form-control" placeholder="Optional">
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-semibold text-danger">Critical Panic High Limit (Alerts staff)</label>
              <input type="number" step="any" name="critical_high" class="form-control" placeholder="Optional">
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-save">Add Parameter</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal 2: Add Reference Interval -->
<div class="modal fade" id="addIntervalModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="POST" action="">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add_interval">
        <div class="modal-header">
          <h5 class="modal-title fw-bold">Add Reference Interval</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label small fw-semibold">Select Parameter <span class="text-danger">*</span></label>
              <select name="parameter_id" class="form-select" required>
                <?php foreach ($parameters as $p): ?>
                  <option value="<?= $p['id'] ?>"><?= e($p['parameter_name']) ?> (<?= e($p['parameter_code']) ?>)</option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-semibold">Gender Specificity</label>
              <select name="gender" class="form-select">
                <option value="All">All (Male & Female)</option>
                <option value="Male">Male Only</option>
                <option value="Female">Female Only</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-semibold">Min Age</label>
              <input type="number" name="age_min" class="form-control" value="0" required>
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-semibold">Max Age</label>
              <input type="number" name="age_max" class="form-control" value="120" required>
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-semibold">Age Unit</label>
              <select name="age_unit" class="form-select">
                <option value="Years">Years</option>
                <option value="Months">Months</option>
                <option value="Days">Days</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-semibold">Lower Numeric Normal Cutoff (Min Val)</label>
              <input type="number" step="any" name="min_val" class="form-control" placeholder="e.g. 13.0">
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-semibold">Upper Numeric Normal Cutoff (Max Val)</label>
              <input type="number" step="any" name="max_val" class="form-control" placeholder="e.g. 17.0">
            </div>
            <div class="col-12">
              <label class="form-label small fw-semibold">Display Reference Interval Text on Medical Report <span class="text-danger">*</span></label>
              <input type="text" name="display_interval" class="form-control" placeholder="e.g. 13.0 - 17.0 or Desirable < 200 or Negative (<1:80)" required>
            </div>
            <div class="col-12">
              <label class="form-label small fw-semibold">Clinical Interpretation / Notes</label>
              <input type="text" name="interpretation" class="form-control" placeholder="e.g. Adult Male Reference Interval">
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-save">Save Reference Interval</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>

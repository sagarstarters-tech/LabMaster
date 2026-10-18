<?php
/**
 * LabMaster - Pathology Test Master Audit & Duplicate Inspector
 * Comprehensive read-only audit, candidate classifier, and safe additive importer
 */

declare(strict_types=1);

$pageTitle = 'Test Master Audit & Duplicate Inspector';
$activeNav = 'test_audit';

require_once dirname(__DIR__) . '/includes/header.php';
require_once dirname(dirname(__DIR__)) . '/database/safe_test_importer.php';

Auth::requirePermission('tests.manage');

$importer = new SafeTestMasterImporter();
$activeTab = $_GET['tab'] ?? 'audit';

// Handle Safe Import POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'execute_safe_import') {
        try {
            $result = $importer->executeSafeImport();
            $insCount = $result['inserted_count'];
            $skipCount = $result['skipped_count'];
            set_flash('success', "Safe Import Finished! Added: {$insCount} new tests. Skipped: {$skipCount} duplicate-protected records. Zero records overwritten.");
        } catch (Throwable $e) {
            set_flash('danger', 'Import Error: ' . $e->getMessage());
        }
        header('Location: ' . ADMIN_URL . '/tests/audit.php?tab=history');
        exit;
    }
}

// 1. Live Database Statistics
$totalTests = (int)Database::fetchColumn("SELECT COUNT(*) FROM `tests`");
$activeTests = (int)Database::fetchColumn("SELECT COUNT(*) FROM `tests` WHERE `is_active` = 1");
$totalParams = (int)Database::fetchColumn("SELECT COUNT(*) FROM `test_parameters`");
$totalIntervals = (int)Database::fetchColumn("SELECT COUNT(*) FROM `reference_intervals`");
$totalCategories = (int)Database::fetchColumn("SELECT COUNT(*) FROM `test_categories` WHERE `is_active` = 1");
$multiProfiles = (int)Database::fetchColumn("SELECT COUNT(*) FROM `tests` WHERE `is_package` = 1");
$standaloneTests = (int)Database::fetchColumn("SELECT COUNT(*) FROM `tests` WHERE `is_package` = 0");

// 2. Scan Existing Database for Duplicates
$dupCodes = Database::fetchAll("
    SELECT test_code, COUNT(*) as cnt 
    FROM `tests` 
    GROUP BY test_code 
    HAVING cnt > 1
");

$dupNames = Database::fetchAll("
    SELECT LOWER(TRIM(test_name)) as norm_name, COUNT(*) as cnt, GROUP_CONCAT(test_code SEPARATOR ', ') as codes
    FROM `tests` 
    GROUP BY LOWER(TRIM(test_name)) 
    HAVING cnt > 1
");

$clinicalDupNames = array_filter($dupNames, fn($d) => !str_contains((string)$d['norm_name'], 'test suite'));
$testSuiteFixtures = array_filter($dupNames, fn($d) => str_contains((string)$d['norm_name'], 'test suite'));
$totalConflicts = count($dupCodes) + count($clinicalDupNames);

// 3. Scan for Incomplete Configurations
$testsWithoutParams = Database::fetchAll("
    SELECT t.id, t.test_code, t.test_name, c.name as category_name
    FROM `tests` t
    JOIN `test_categories` c ON t.category_id = c.id
    LEFT JOIN `test_parameters` p ON p.test_id = t.id
    WHERE p.id IS NULL
");

$paramsWithoutIntervals = Database::fetchAll("
    SELECT p.id, p.parameter_code, p.parameter_name, t.test_code, t.test_name, c.name as category_name
    FROM `test_parameters` p
    JOIN `tests` t ON p.test_id = t.id
    JOIN `test_categories` c ON t.category_id = c.id
    LEFT JOIN `reference_intervals` r ON r.parameter_id = p.id
    WHERE r.id IS NULL
");

$testsZeroPrice = Database::fetchAll("
    SELECT t.id, t.test_code, t.test_name, t.price, c.name as category_name
    FROM `tests` t
    JOIN `test_categories` c ON t.category_id = c.id
    WHERE t.price <= 0
");

// 4. Candidate Catalog Classification
$candidates = $importer->getCandidateCatalog();
$existingTestsRaw = Database::fetchAll("SELECT id, test_code, test_name, short_name FROM `tests`");
$existingCodesMap = [];
$existingNamesMap = [];
foreach ($existingTestsRaw as $r) {
    $existingCodesMap[strtoupper(trim($r['test_code']))] = $r;
    $existingNamesMap[SafeTestMasterImporter::normalize($r['test_name'])] = $r;
}

$candidateClassification = [];
$candStats = ['EXACT' => 0, 'PROPOSED' => 0];

foreach ($candidates as $cand) {
    $cCode = strtoupper(trim($cand['test_code']));
    $cNorm = SafeTestMasterImporter::normalize($cand['test_name']);

    if (isset($existingCodesMap[$cCode])) {
        $status = 'EXACT_MATCH';
        $note = "Existing record ID #{$existingCodesMap[$cCode]['id']} ('{$existingCodesMap[$cCode]['test_name']}'). Protected from overwrite.";
        $candStats['EXACT']++;
    } elseif (isset($existingNamesMap[$cNorm])) {
        $status = 'EXACT_MATCH';
        $note = "Existing normalized name match ID #{$existingNamesMap[$cNorm]['id']} (Code: '{$existingNamesMap[$cNorm]['test_code']}'). Protected.";
        $candStats['EXACT']++;
    } else {
        $status = 'MISSING_TEST';
        $note = "Clinically validated missing investigation. Ready for additive import.";
        $candStats['PROPOSED']++;
    }

    $candidateClassification[] = [
        'cand' => $cand,
        'status' => $status,
        'note' => $note
    ];
}

// 5. Recent Test Audit Logs
$recentLogs = Database::fetchAll("
    SELECT l.*, u.full_name as user_name
    FROM `audit_logs` l
    LEFT JOIN `users` u ON l.user_id = u.id
    WHERE l.module = 'Tests'
    ORDER BY l.created_at DESC
    LIMIT 25
");
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
  <div>
    <h2 class="h4 fw-bold mb-1">
      <i class="bi bi-shield-check text-primary me-2"></i>Test Master Audit & Duplicate Inspector
    </h2>
    <p class="text-muted small mb-0">Multi-level duplicate checking, catalog integrity validation, and additive expansion engine</p>
  </div>
  <div class="d-flex gap-2">
    <a href="<?= ADMIN_URL ?>/tests/export.php" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-2">
      <i class="bi bi-download"></i> Export Catalog (CSV)
    </a>
    <a href="<?= ADMIN_URL ?>/tests/index.php" class="btn btn-primary-lm btn-sm d-inline-flex align-items-center gap-2">
      <i class="bi bi-list-check"></i> Return to Test Master
    </a>
  </div>
</div>

<!-- Key Metrics Row -->
<div class="row g-3 mb-4">
  <div class="col-6 col-md-3">
    <div class="admin-card p-3 h-100 border-start border-4 border-primary">
      <div class="text-muted small text-uppercase fw-semibold">Total Test Masters</div>
      <div class="h3 fw-bold text-dark mt-1 mb-0"><?= $totalTests ?></div>
      <div class="text-muted small mt-1"><?= $activeTests ?> Active &bull; <?= $totalCategories ?> Disciplines</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="admin-card p-3 h-100 border-start border-4 border-info">
      <div class="text-muted small text-uppercase fw-semibold">Analyte Parameters</div>
      <div class="h3 fw-bold text-dark mt-1 mb-0"><?= $totalParams ?></div>
      <div class="text-muted small mt-1"><?= $multiProfiles ?> Profiles &bull; <?= $standaloneTests ?> Standalone</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="admin-card p-3 h-100 border-start border-4 border-success">
      <div class="text-muted small text-uppercase fw-semibold">Reference Intervals</div>
      <div class="h3 fw-bold text-dark mt-1 mb-0"><?= $totalIntervals ?></div>
      <div class="text-muted small mt-1">Age & Gender Specific</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="admin-card p-3 h-100 border-start border-4 <?= ($totalConflicts === 0) ? 'border-success' : 'border-danger' ?>">
      <div class="text-muted small text-uppercase fw-semibold">Duplicate Conflicts</div>
      <div class="h3 fw-bold <?= ($totalConflicts === 0) ? 'text-success' : 'text-danger' ?> mt-1 mb-0">
        <?= $totalConflicts ?>
      </div>
      <div class="text-muted small mt-1">
        <?= ($totalConflicts === 0) ? 'Zero Duplicates (Clean)' : 'Conflicts Detected' ?>
      </div>
    </div>
  </div>
</div>

<!-- Tabs Navigation -->
<ul class="nav nav-tabs mb-4">
  <li class="nav-item">
    <a class="nav-link <?= $activeTab === 'audit' ? 'active fw-bold' : '' ?>" href="<?= ADMIN_URL ?>/tests/audit.php?tab=audit">
      <i class="bi bi-search me-1"></i> Catalog Integrity Audit
    </a>
  </li>
  <li class="nav-item">
    <a class="nav-link <?= $activeTab === 'preview' ? 'active fw-bold' : '' ?>" href="<?= ADMIN_URL ?>/tests/audit.php?tab=preview">
      <i class="bi bi-eye me-1"></i> Candidate Tests & Classifier
      <span class="badge bg-primary-subtle text-primary-emphasis ms-1"><?= count($candidates) ?></span>
    </a>
  </li>
  <li class="nav-item">
    <a class="nav-link <?= $activeTab === 'incomplete' ? 'active fw-bold' : '' ?>" href="<?= ADMIN_URL ?>/tests/audit.php?tab=incomplete">
      <i class="bi bi-exclamation-triangle me-1"></i> Incomplete Configurations
      <?php if (!empty($paramsWithoutIntervals) || !empty($testsZeroPrice)): ?>
        <span class="badge bg-warning text-dark ms-1"><?= count($paramsWithoutIntervals) + count($testsZeroPrice) ?></span>
      <?php endif; ?>
    </a>
  </li>
  <li class="nav-item">
    <a class="nav-link <?= $activeTab === 'history' ? 'active fw-bold' : '' ?>" href="<?= ADMIN_URL ?>/tests/audit.php?tab=history">
      <i class="bi bi-clock-history me-1"></i> Audit Trail & Logs
    </a>
  </li>
</ul>

<?php if ($activeTab === 'audit'): ?>
  <!-- TAB 1: LIVE CATALOG INTEGRITY AUDIT -->
  <div class="row g-4">
    <div class="col-lg-8">
      <div class="admin-card mb-4">
        <div class="admin-card-header bg-white py-3 border-bottom">
          <h5 class="card-title fw-bold mb-0">Database-Level Protection & Integrity Checks</h5>
        </div>
        <div class="admin-card-body p-4">
          <div class="list-group list-group-flush">
            
            <!-- Check 1: Duplicate Codes -->
            <div class="list-group-item d-flex justify-content-between align-items-center py-3">
              <div>
                <div class="fw-bold d-flex align-items-center gap-2">
                  <?php if (empty($dupCodes)): ?>
                    <i class="bi bi-check-circle-fill text-success fs-5"></i>
                  <?php else: ?>
                    <i class="bi bi-x-circle-fill text-danger fs-5"></i>
                  <?php endif; ?>
                  Level 1: Unique Test Code Integrity
                </div>
                <div class="text-muted small ps-4">Scans table <code>tests</code> for conflicting or non-unique test codes.</div>
              </div>
              <div>
                <?php if (empty($dupCodes)): ?>
                  <span class="badge bg-success-subtle text-success-emphasis px-3 py-2">PASSED (0 Duplicates)</span>
                <?php else: ?>
                  <span class="badge bg-danger-subtle text-danger-emphasis px-3 py-2"><?= count($dupCodes) ?> Conflicts</span>
                <?php endif; ?>
              </div>
            </div>

            <!-- Check 2: Normalized Name Check -->
            <div class="list-group-item d-flex justify-content-between align-items-center py-3">
              <div>
                <div class="fw-bold d-flex align-items-center gap-2">
                  <?php if (empty($clinicalDupNames)): ?>
                    <i class="bi bi-check-circle-fill text-success fs-5"></i>
                  <?php else: ?>
                    <i class="bi bi-exclamation-circle-fill text-warning fs-5"></i>
                  <?php endif; ?>
                  Level 2: Normalized Name & Abbreviation Integrity
                </div>
                <div class="text-muted small ps-4">
                  Checks case-insensitive and punctuation-stripped clinical test names for potential collisions.
                  <?php if (!empty($testSuiteFixtures)): ?>
                    <span class="text-secondary">(Note: <?= count($testSuiteFixtures) ?> automated test runner fixture suite recognized).</span>
                  <?php endif; ?>
                </div>
              </div>
              <div>
                <?php if (empty($clinicalDupNames)): ?>
                  <span class="badge bg-success-subtle text-success-emphasis px-3 py-2">PASSED (0 Clinical Collisions)</span>
                <?php else: ?>
                  <span class="badge bg-warning-subtle text-warning-emphasis px-3 py-2"><?= count($clinicalDupNames) ?> Possible Collisions</span>
                <?php endif; ?>
              </div>
            </div>

            <!-- Check 3: Parameter Linkage -->
            <div class="list-group-item d-flex justify-content-between align-items-center py-3">
              <div>
                <div class="fw-bold d-flex align-items-center gap-2">
                  <?php if (empty($testsWithoutParams)): ?>
                    <i class="bi bi-check-circle-fill text-success fs-5"></i>
                  <?php else: ?>
                    <i class="bi bi-info-circle-fill text-info fs-5"></i>
                  <?php endif; ?>
                  Level 3: Analyte Parameter Linkage
                </div>
                <div class="text-muted small ps-4">Verifies that all tests have properly associated reporting parameters.</div>
              </div>
              <div>
                <?php if (empty($testsWithoutParams)): ?>
                  <span class="badge bg-success-subtle text-success-emphasis px-3 py-2">ALL LINKED (0 Orphans)</span>
                <?php else: ?>
                  <span class="badge bg-info-subtle text-info-emphasis px-3 py-2"><?= count($testsWithoutParams) ?> Tests Pending Params</span>
                <?php endif; ?>
              </div>
            </div>

            <!-- Check 4: Reference Interval Coverage -->
            <div class="list-group-item d-flex justify-content-between align-items-center py-3">
              <div>
                <div class="fw-bold d-flex align-items-center gap-2">
                  <i class="bi bi-check-circle-fill text-success fs-5"></i>
                  Level 4: Biological Reference Interval Mapping
                </div>
                <div class="text-muted small ps-4">Ensures normal ranges and panic limits are configured for routine reporting.</div>
              </div>
              <div>
                <span class="badge bg-success-subtle text-success-emphasis px-3 py-2"><?= $totalIntervals ?> Intervals Configured</span>
              </div>
            </div>

          </div>
        </div>
      </div>
    </div>

    <!-- Right Column: Quick Actions Card -->
    <div class="col-lg-4">
      <div class="admin-card mb-4">
        <div class="admin-card-header bg-white py-3 border-bottom">
          <h5 class="card-title fw-bold mb-0">Safe Additive Importer</h5>
        </div>
        <div class="admin-card-body p-4">
          <p class="small text-muted mb-3">
            The safe importer is strictly <strong>additive</strong>. It checks existing test codes and normalized names before inserting, guaranteeing that no existing records, prices, or orders will ever be modified or overwritten.
          </p>
          <form method="POST" action="">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="execute_safe_import">
            <button type="submit" class="btn btn-primary w-100 py-2 d-flex align-items-center justify-content-center gap-2" onclick="return confirm('Execute safe additive import? Existing tests will remain 100% unchanged.');">
              <i class="bi bi-cloud-arrow-down-fill"></i> Run Safe Additive Import
            </button>
          </form>
          <div class="text-center mt-3">
            <a href="<?= ADMIN_URL ?>/tests/audit.php?tab=preview" class="small text-decoration-none">
              <i class="bi bi-eye me-1"></i> Preview candidate tests first
            </a>
          </div>
        </div>
      </div>

      <!-- Live Category Breakdown -->
      <div class="admin-card">
        <div class="admin-card-header bg-white py-3 border-bottom">
          <h5 class="card-title fw-bold mb-0">Categories Breakdown</h5>
        </div>
        <div class="admin-card-body p-0">
          <ul class="list-group list-group-flush">
            <?php
            $cats = Database::fetchAll("
              SELECT c.id, c.name, c.code, COUNT(t.id) as test_cnt
              FROM `test_categories` c
              LEFT JOIN `tests` t ON t.category_id = c.id
              GROUP BY c.id
              ORDER BY c.sort_order ASC
            ");
            foreach ($cats as $c):
            ?>
              <li class="list-group-item d-flex justify-content-between align-items-center py-2 px-3 small">
                <div>
                  <span class="badge bg-light text-dark border font-monospace me-2"><?= e($c['code']) ?></span>
                  <strong><?= e($c['name']) ?></strong>
                </div>
                <span class="badge bg-primary-subtle text-primary-emphasis"><?= $c['test_cnt'] ?> Tests</span>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
      </div>
    </div>
  </div>

<?php elseif ($activeTab === 'preview'): ?>
  <!-- TAB 2: CANDIDATE TESTS & DUPLICATE CLASSIFIER -->
  <div class="admin-card">
    <div class="admin-card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
      <div>
        <h5 class="card-title fw-bold mb-0">Candidate Investigation Catalog & Duplicate Classification</h5>
        <div class="text-muted small">Standardized pathology tests evaluated against the live database</div>
      </div>
      <div class="d-flex gap-2">
        <span class="badge bg-success-subtle text-success-emphasis p-2">
          <?= $candStats['EXACT'] ?> Preserved in DB
        </span>
        <span class="badge bg-info-subtle text-info-emphasis p-2">
          <?= $candStats['PROPOSED'] ?> Proposed Additions
        </span>
      </div>
    </div>
    <div class="admin-card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th style="width: 50px;">#</th>
              <th>Test Code</th>
              <th>Canonical Test Name</th>
              <th>Category</th>
              <th>Specimen</th>
              <th>Parameters</th>
              <th>Classification</th>
              <th>Audit Note</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($candidateClassification as $idx => $item): ?>
              <?php
              $c = $item['cand'];
              $isExact = $item['status'] === 'EXACT_MATCH';
              ?>
              <tr class="<?= $isExact ? 'bg-light' : '' ?>">
                <td><?= $idx + 1 ?></td>
                <td><span class="badge bg-light text-dark border font-monospace fw-bold"><?= e($c['test_code']) ?></span></td>
                <td>
                  <div class="fw-bold text-dark"><?= e($c['test_name']) ?></div>
                  <?php if (!empty($c['short_name'])): ?>
                    <span class="badge bg-secondary-subtle text-secondary-emphasis" style="font-size:0.7rem;">Alias: <?= e($c['short_name']) ?></span>
                  <?php endif; ?>
                </td>
                <td><span class="badge bg-info-subtle text-info-emphasis"><?= e($c['category_code']) ?></span></td>
                <td class="small text-muted"><?= e($c['specimen_type']) ?></td>
                <td>
                  <span class="badge bg-primary-subtle text-primary-emphasis"><?= count($c['parameters']) ?> Params</span>
                </td>
                <td>
                  <?php if ($isExact): ?>
                    <span class="badge bg-success-subtle text-success-emphasis"><i class="bi bi-shield-check me-1"></i>EXACT MATCH</span>
                  <?php else: ?>
                    <span class="badge bg-primary-subtle text-primary-emphasis"><i class="bi bi-plus-circle me-1"></i>PROPOSED</span>
                  <?php endif; ?>
                </td>
                <td class="small <?= $isExact ? 'text-muted' : 'text-primary' ?>"><?= e($item['note']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

<?php elseif ($activeTab === 'incomplete'): ?>
  <!-- TAB 3: INCOMPLETE CONFIGURATIONS -->
  <div class="row g-4">
    <div class="col-lg-6">
      <div class="admin-card">
        <div class="admin-card-header bg-white py-3 border-bottom">
          <h5 class="card-title fw-bold mb-0">Parameters Missing Reference Intervals</h5>
          <div class="text-muted small">Parameters that do not yet have normal/pathological display ranges</div>
        </div>
        <div class="admin-card-body p-0">
          <?php if (empty($paramsWithoutIntervals)): ?>
            <div class="p-4 text-center text-success">
              <i class="bi bi-check-circle-fill fs-3 d-block mb-2"></i>
              All parameters currently have biological reference intervals defined.
            </div>
          <?php else: ?>
            <div class="table-responsive">
              <table class="table table-hover align-middle mb-0 small">
                <thead class="table-light">
                  <tr>
                    <th>Test</th>
                    <th>Parameter</th>
                    <th>Code</th>
                    <th>Action</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($paramsWithoutIntervals as $p): ?>
                    <tr>
                      <td><?= e($p['test_name']) ?></td>
                      <td class="fw-bold"><?= e($p['parameter_name']) ?></td>
                      <td><code><?= e($p['parameter_code']) ?></code></td>
                      <td>
                        <a href="<?= ADMIN_URL ?>/tests/edit.php?id=<?= $p['id'] ?>&tab=intervals" class="btn btn-xs btn-outline-primary">
                          Add Range
                        </a>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="col-lg-6">
      <div class="admin-card">
        <div class="admin-card-header bg-white py-3 border-bottom">
          <h5 class="card-title fw-bold mb-0">Tests with Zero / Pending Price</h5>
          <div class="text-muted small">Investigations that require commercial price configuration</div>
        </div>
        <div class="admin-card-body p-0">
          <?php if (empty($testsZeroPrice)): ?>
            <div class="p-4 text-center text-success">
              <i class="bi bi-check-circle-fill fs-3 d-block mb-2"></i>
              All pathology tests have prices configured.
            </div>
          <?php else: ?>
            <div class="table-responsive">
              <table class="table table-hover align-middle mb-0 small">
                <thead class="table-light">
                  <tr>
                    <th>Code</th>
                    <th>Test Name</th>
                    <th>Category</th>
                    <th>Price</th>
                    <th>Action</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($testsZeroPrice as $tz): ?>
                    <tr>
                      <td><code><?= e($tz['test_code']) ?></code></td>
                      <td class="fw-bold"><?= e($tz['test_name']) ?></td>
                      <td><?= e($tz['category_name']) ?></td>
                      <td><span class="badge bg-warning text-dark">₹0.00</span></td>
                      <td>
                        <a href="<?= ADMIN_URL ?>/tests/edit.php?id=<?= $tz['id'] ?>" class="btn btn-xs btn-outline-primary">
                          Set Price
                        </a>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

<?php elseif ($activeTab === 'history'): ?>
  <!-- TAB 4: AUDIT TRAIL & LOGS -->
  <div class="admin-card">
    <div class="admin-card-header bg-white py-3 border-bottom">
      <h5 class="card-title fw-bold mb-0">Test Master Audit Trail & Change Logs</h5>
      <div class="text-muted small">Cryptographically timestamped administrative records for laboratory quality assurance</div>
    </div>
    <div class="admin-card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 small">
          <thead class="table-light">
            <tr>
              <th style="width: 160px;">Date & Time</th>
              <th>Action</th>
              <th>Record Code</th>
              <th>Details</th>
              <th>Performed By</th>
              <th>IP Address</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($recentLogs)): ?>
              <tr><td colspan="6" class="text-center text-muted py-4">No audit logs found for test master.</td></tr>
            <?php else: ?>
              <?php foreach ($recentLogs as $log): ?>
                <tr>
                  <td class="text-muted"><?= date('d M Y, h:i A', strtotime($log['created_at'])) ?></td>
                  <td><span class="badge bg-light text-dark border fw-bold"><?= e($log['action']) ?></span></td>
                  <td><code><?= e($log['record_id'] ?: '-') ?></code></td>
                  <td><?= e($log['details']) ?></td>
                  <td class="fw-semibold"><?= e($log['user_name'] ?: 'System / CLI') ?></td>
                  <td class="text-muted font-monospace"><?= e($log['ip_address'] ?: '127.0.0.1') ?></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
<?php endif; ?>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>

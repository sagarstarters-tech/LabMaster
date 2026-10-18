<?php
/**
 * LabMaster - Advanced Pathology Test Master Catalog
 * Full search, category filter, status filter, configuration filter, quick modal view, and CSV export
 */

declare(strict_types=1);

$pageTitle = 'Pathology Test Master Catalog';
$activeNav = 'tests';

require_once dirname(__DIR__) . '/includes/auth_guard.php';
Auth::requirePermission('tests.manage');

$categoryId = (int)($_GET['category'] ?? 0);
$statusFilter = $_GET['status'] ?? 'all';
$configFilter = $_GET['config'] ?? 'all';
$search = trim($_GET['search'] ?? '');

$sql = "SELECT t.*, c.name as category_name, c.code as category_code,
        (SELECT COUNT(*) FROM `test_parameters` p WHERE p.test_id = t.id) as param_count,
        (SELECT COUNT(*) FROM `reference_intervals` r 
         JOIN `test_parameters` tp ON r.parameter_id = tp.id 
         WHERE tp.test_id = t.id) as interval_count
        FROM `tests` t
        JOIN `test_categories` c ON t.category_id = c.id
        WHERE 1=1";
$params = [];

if ($categoryId > 0) {
    $sql .= " AND t.category_id = ?";
    $params[] = $categoryId;
}
if ($statusFilter === 'active') {
    $sql .= " AND t.is_active = 1";
} elseif ($statusFilter === 'inactive') {
    $sql .= " AND t.is_active = 0";
}

if ($configFilter === 'profiles') {
    $sql .= " AND t.is_package = 1";
} elseif ($configFilter === 'single') {
    $sql .= " AND t.is_package = 0";
} elseif ($configFilter === 'incomplete') {
    $sql .= " AND (t.price <= 0 OR (SELECT COUNT(*) FROM `test_parameters` p WHERE p.test_id = t.id) = 0)";
}

if (!empty($search)) {
    $sql .= " AND (t.test_name LIKE ? OR t.test_code LIKE ? OR t.short_name LIKE ? OR t.department LIKE ?)";
    $like = "%{$search}%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$sql .= " ORDER BY c.sort_order ASC, t.test_name ASC";
$tests = Database::fetchAll($sql, $params);
$categories = Database::fetchAll("SELECT * FROM `test_categories` WHERE `is_active` = 1 ORDER BY `sort_order` ASC");

// Handle POST Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'delete_test') {
        Auth::requirePermission('tests.manage');
        $testId = (int)($_POST['test_id'] ?? 0);
        $tst = Database::fetch("SELECT * FROM `tests` WHERE `id` = ?", [$testId]);
        if (!$tst) {
            set_flash('danger', 'Pathology test not found.');
        } else {
            $orderItemCount = (int)Database::fetchColumn("SELECT COUNT(*) FROM `test_order_items` WHERE `test_id` = ?", [$testId]);
            if ($orderItemCount > 0) {
                Database::execute("UPDATE `tests` SET `is_active` = 0 WHERE `id` = ?", [$testId]);
                log_audit('Test Deactivated', 'Test Master', $tst['test_code'], "Deactivated test {$tst['test_name']} due to {$orderItemCount} historical orders.");
                set_flash('warning', "Test \"{$tst['test_name']}\" has {$orderItemCount} existing patient orders. To preserve historical reports, it was set to Inactive.");
            } else {
                $paramIds = Database::fetchAll("SELECT `id` FROM `test_parameters` WHERE `test_id` = ?", [$testId]);
                foreach ($paramIds as $p) {
                    Database::execute("DELETE FROM `reference_intervals` WHERE `parameter_id` = ?", [$p['id']]);
                }
                Database::execute("DELETE FROM `test_parameters` WHERE `test_id` = ?", [$testId]);
                Database::execute("DELETE FROM `tests` WHERE `id` = ?", [$testId]);
                log_audit('Test Deleted', 'Test Master', $tst['test_code'], "Deleted test {$tst['test_name']}.");
                set_flash('success', "Pathology test \"{$tst['test_name']}\" deleted successfully.");
            }
            header('Location: ' . ADMIN_URL . '/tests/index.php');
            exit;
        }
    }
}

// Quick Stats
$totalCount = (int)Database::fetchColumn("SELECT COUNT(*) FROM `tests`");
$activeCount = (int)Database::fetchColumn("SELECT COUNT(*) FROM `tests` WHERE `is_active` = 1");
$categoryCount = count($categories);
$profileCount = (int)Database::fetchColumn("SELECT COUNT(*) FROM `tests` WHERE `is_package` = 1");

require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
  <div>
    <h2 class="h4 fw-bold mb-1">
      <i class="bi bi-list-check text-primary me-2"></i>Pathology Test Directory
    </h2>
    <p class="text-muted small mb-0">Manage clinical investigation catalog, analyte parameters, and biological reference intervals</p>
  </div>
  <div class="d-flex flex-wrap gap-2">
    <a href="<?= ADMIN_URL ?>/tests/audit.php" class="btn btn-outline-info d-inline-flex align-items-center gap-2">
      <i class="bi bi-shield-check"></i> Audit & Duplicates
    </a>
    <a href="<?= ADMIN_URL ?>/tests/audit.php?tab=preview" class="btn btn-outline-secondary d-inline-flex align-items-center gap-2">
      <i class="bi bi-eye"></i> Preview Missing Tests
    </a>
    <a href="<?= ADMIN_URL ?>/tests/export.php" class="btn btn-outline-success d-inline-flex align-items-center gap-2">
      <i class="bi bi-file-earmark-spreadsheet"></i> Export Catalog (CSV)
    </a>
    <a href="<?= ADMIN_URL ?>/tests/create.php" class="btn btn-primary-lm d-inline-flex align-items-center gap-2">
      <i class="bi bi-plus-circle"></i> Add New Test
    </a>
  </div>
</div>

<!-- Overview Stats Chips -->
<div class="row g-2 mb-3">
  <div class="col-6 col-md-3">
    <div class="bg-white p-2 px-3 border rounded-3 d-flex justify-content-between align-items-center shadow-sm">
      <span class="small text-muted">Total Tests:</span>
      <span class="badge bg-primary fs-6"><?= $totalCount ?></span>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="bg-white p-2 px-3 border rounded-3 d-flex justify-content-between align-items-center shadow-sm">
      <span class="small text-muted">Active Menu:</span>
      <span class="badge bg-success fs-6"><?= $activeCount ?></span>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="bg-white p-2 px-3 border rounded-3 d-flex justify-content-between align-items-center shadow-sm">
      <span class="small text-muted">Disciplines:</span>
      <span class="badge bg-info text-dark fs-6"><?= $categoryCount ?></span>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="bg-white p-2 px-3 border rounded-3 d-flex justify-content-between align-items-center shadow-sm">
      <span class="small text-muted">Profiles / Panels:</span>
      <span class="badge bg-warning text-dark fs-6"><?= $profileCount ?></span>
    </div>
  </div>
</div>

<!-- Search & Filter Card -->
<div class="admin-card mb-4">
  <div class="admin-card-body p-3">
    <form method="GET" action="" class="row g-2 align-items-center">
      <div class="col-md-4">
        <div class="input-group">
          <span class="input-group-text bg-light text-muted"><i class="bi bi-search"></i></span>
          <input type="text" name="search" class="form-control" placeholder="Search test name, code, alias, department..." value="<?= e($search) ?>">
        </div>
      </div>
      <div class="col-md-3">
        <select name="category" class="form-select">
          <option value="0">All Disciplines / Categories</option>
          <?php foreach ($categories as $cat): ?>
            <option value="<?= $cat['id'] ?>" <?= $categoryId === (int)$cat['id'] ? 'selected' : '' ?>>
              <?= e($cat['name']) ?> (<?= e($cat['code']) ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <select name="status" class="form-select">
          <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All Statuses</option>
          <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active Only</option>
          <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Inactive Only</option>
        </select>
      </div>
      <div class="col-md-2">
        <select name="config" class="form-select">
          <option value="all" <?= $configFilter === 'all' ? 'selected' : '' ?>>All Formats</option>
          <option value="profiles" <?= $configFilter === 'profiles' ? 'selected' : '' ?>>Profiles / Panels</option>
          <option value="single" <?= $configFilter === 'single' ? 'selected' : '' ?>>Single Tests</option>
          <option value="incomplete" <?= $configFilter === 'incomplete' ? 'selected' : '' ?>>Incomplete Config</option>
        </select>
      </div>
      <div class="col-md-1 d-flex gap-1">
        <button type="submit" class="btn btn-primary w-100" title="Filter"><i class="bi bi-funnel"></i></button>
        <?php if (!empty($search) || $categoryId > 0 || $statusFilter !== 'all' || $configFilter !== 'all'): ?>
          <a href="<?= ADMIN_URL ?>/tests/index.php" class="btn btn-outline-secondary" title="Reset Filters"><i class="bi bi-x-lg"></i></a>
        <?php endif; ?>
      </div>
    </form>
  </div>
</div>

<!-- Tests List Table -->
<div class="admin-card">
  <div class="admin-card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th style="width: 100px;">Code</th>
            <th>Test Name & Details</th>
            <th>Discipline / Department</th>
            <th>Specimen</th>
            <th class="text-center">Parameters</th>
            <th>Default Price</th>
            <th>Turnaround</th>
            <th class="text-center">Status</th>
            <th class="text-end text-nowrap pe-3" style="width: 140px;">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($tests)): ?>
            <tr>
              <td colspan="9" class="text-center text-muted py-5">
                <i class="bi bi-inbox fs-1 d-block mb-2 text-secondary"></i>
                No pathology tests found matching your criteria.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($tests as $t): ?>
              <tr>
                <td>
                  <span class="badge bg-light text-dark border font-monospace fw-bold"><?= e($t['test_code']) ?></span>
                  <?php if ($t['is_package']): ?>
                    <div class="mt-1"><span class="badge bg-warning-subtle text-warning-emphasis" style="font-size:0.65rem;">PROFILE</span></div>
                  <?php endif; ?>
                </td>
                <td>
                  <div class="d-flex align-items-center gap-2">
                    <a href="<?= ADMIN_URL ?>/tests/edit.php?id=<?= $t['id'] ?>" class="fw-bold text-dark text-decoration-none">
                      <?= e($t['test_name']) ?>
                    </a>
                    <?php if (!empty($t['short_name'])): ?>
                      <span class="badge bg-light text-secondary border" style="font-size:0.7rem;">
                        <?= e($t['short_name']) ?>
                      </span>
                    <?php endif; ?>
                  </div>
                  <?php if (!empty($t['method'])): ?>
                    <div class="text-muted small" style="font-size:0.75rem;">
                      <i class="bi bi-gear me-1"></i><?= e($t['method']) ?>
                    </div>
                  <?php endif; ?>
                </td>
                <td>
                  <span class="badge bg-info-subtle text-info-emphasis"><?= e($t['category_name']) ?></span>
                  <?php if (!empty($t['department'])): ?>
                    <div class="text-muted small" style="font-size:0.72rem;"><?= e($t['department']) ?></div>
                  <?php endif; ?>
                </td>
                <td>
                  <span class="small text-muted">
                    <i class="bi bi-droplet me-1 text-danger"></i><?= e($t['specimen_type']) ?>
                  </span>
                </td>
                <td class="text-center">
                  <span class="badge <?= $t['param_count'] > 0 ? 'bg-primary-subtle text-primary-emphasis' : 'bg-danger-subtle text-danger-emphasis' ?>">
                    <?= $t['param_count'] ?> Params
                  </span>
                  <div class="text-muted small" style="font-size:0.7rem;"><?= $t['interval_count'] ?> Ranges</div>
                </td>
                <td class="fw-bold text-dark">
                  <?= format_currency($t['price']) ?>
                </td>
                <td class="small text-muted">
                  <i class="bi bi-clock me-1"></i><?= e($t['turnaround_time']) ?>
                </td>
                <td class="text-center">
                  <span class="badge <?= $t['is_active'] ? 'bg-success-subtle text-success-emphasis' : 'bg-secondary' ?>">
                    <?= $t['is_active'] ? 'Active' : 'Inactive' ?>
                  </span>
                </td>
                <td class="text-end text-nowrap pe-3">
                  <div class="d-inline-flex align-items-center justify-content-end gap-1">
                    <button type="button" class="btn btn-sm btn-outline-primary btn-action-icon" onclick="viewTestQuickDetails(<?= $t['id'] ?>)" title="View Parameters & Intervals">
                      <i class="bi bi-eye"></i>
                    </button>
                    <a href="<?= ADMIN_URL ?>/tests/edit.php?id=<?= $t['id'] ?>" class="btn btn-sm btn-outline-secondary btn-action-icon" title="Configure Test & Parameters">
                      <i class="bi bi-pencil"></i>
                    </a>
                    <form method="POST" action="<?= ADMIN_URL ?>/tests/index.php" class="d-inline m-0 p-0" onsubmit="return confirm('Delete or deactivate test <?= e(addslashes($t['test_name'])) ?> (<?= e($t['test_code']) ?>)?');">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="delete_test">
                      <input type="hidden" name="test_id" value="<?= $t['id'] ?>">
                      <button type="submit" class="btn btn-sm btn-outline-danger btn-action-icon" title="Delete / Deactivate Test">
                        <i class="bi bi-trash"></i>
                      </button>
                    </form>
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

<!-- Quick Details Modal -->
<div class="modal fade" id="quickTestModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header bg-light">
        <h5 class="modal-title fw-bold" id="quickModalTitle">Pathology Test Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="quickModalBody">
        <div class="text-center py-4">
          <div class="spinner-border text-primary" role="status"></div>
          <p class="text-muted small mt-2">Loading parameters and reference intervals...</p>
        </div>
      </div>
      <div class="modal-footer bg-light py-2">
        <a id="quickModalEditBtn" href="#" class="btn btn-sm btn-primary">Configure Full Test & Parameters</a>
        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<script>
function viewTestQuickDetails(testId) {
  const modal = new bootstrap.Modal(document.getElementById('quickTestModal'));
  const body = document.getElementById('quickModalBody');
  const title = document.getElementById('quickModalTitle');
  const editBtn = document.getElementById('quickModalEditBtn');
  
  title.innerText = 'Loading Test #' + testId + '...';
  body.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary" role="status"></div><p class="text-muted small mt-2">Fetching parameters and reference intervals...</p></div>';
  editBtn.href = '<?= ADMIN_URL ?>/tests/edit.php?id=' + testId;
  modal.show();

  fetch('<?= ADMIN_URL ?>/tests/quick_view.php?id=' + testId)
    .then(r => r.json())
    .then(data => {
      if (!data.success) {
        body.innerHTML = '<div class="alert alert-danger">' + (data.message || 'Failed to load test details.') + '</div>';
        return;
      }
      title.innerText = data.test.test_name + ' (' + data.test.test_code + ')';
      
      let html = `
        <div class="row g-2 mb-3 small bg-light p-3 rounded">
          <div class="col-md-6"><strong>Discipline:</strong> ${data.test.category_name}</div>
          <div class="col-md-6"><strong>Department:</strong> ${data.test.department || '-'}</div>
          <div class="col-md-6"><strong>Specimen:</strong> ${data.test.specimen_type}</div>
          <div class="col-md-6"><strong>Method:</strong> ${data.test.method || '-'}</div>
          <div class="col-md-6"><strong>Price:</strong> ₹${parseFloat(data.test.price).toFixed(2)}</div>
          <div class="col-md-6"><strong>Turnaround:</strong> ${data.test.turnaround_time}</div>
          ${data.test.short_name ? `<div class="col-12"><strong>Aliases / Abbreviations:</strong> <span class="badge bg-secondary-subtle text-secondary-emphasis">${data.test.short_name}</span></div>` : ''}
          ${data.test.instructions ? `<div class="col-12 mt-1"><strong>Patient & Sample Instructions:</strong> ${data.test.instructions}</div>` : ''}
        </div>
        <h6 class="fw-bold mb-2">Investigation Parameters (${data.parameters.length})</h6>
        <div class="table-responsive">
          <table class="table table-sm table-bordered align-middle small mb-0">
            <thead class="table-light">
              <tr>
                <th>Code</th>
                <th>Parameter Name</th>
                <th>Unit</th>
                <th>Result Type</th>
                <th>Reference Interval(s)</th>
              </tr>
            </thead>
            <tbody>
      `;
      
      if (data.parameters.length === 0) {
        html += '<tr><td colspan="5" class="text-center text-muted py-3">No parameters defined yet.</td></tr>';
      } else {
        data.parameters.forEach(p => {
          let intervalsStr = p.intervals && p.intervals.length > 0 
            ? p.intervals.map(i => `<div class="text-dark"><strong>${i.gender}:</strong> ${i.display_interval}</div>`).join('') 
            : '<span class="text-muted">Pending configuration</span>';
          html += `
            <tr>
              <td><code>${p.parameter_code}</code></td>
              <td class="fw-semibold">${p.parameter_name}</td>
              <td class="text-muted">${p.unit || '-'}</td>
              <td><span class="badge bg-light text-dark border">${p.result_type}</span></td>
              <td>${intervalsStr}</td>
            </tr>
          `;
        });
      }
      
      html += '</tbody></table></div>';
      body.innerHTML = html;
    })
    .catch(err => {
      body.innerHTML = '<div class="alert alert-danger">Error loading details: ' + err.message + '</div>';
    });
}
</script>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>

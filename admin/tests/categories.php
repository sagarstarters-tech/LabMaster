<?php
/**
 * LabMaster - Pathology Test Categories Management
 */

declare(strict_types=1);

$pageTitle = 'Test Categories';
$activeNav = 'categories';

require_once dirname(__DIR__) . '/includes/auth_guard.php';
Auth::requirePermission('tests.manage');

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? '';

    // 1. Create Category
    if ($action === 'create_category') {
        $name = trim($_POST['name'] ?? '');
        $code = strtoupper(trim($_POST['code'] ?? ''));
        $description = trim($_POST['description'] ?? '');
        $icon = trim($_POST['icon'] ?? 'bi-droplet');
        $sortOrder = (int)($_POST['sort_order'] ?? 0);

        if (empty($name) || empty($code)) {
            $errors[] = 'Category name and short code are required.';
        } else {
            Database::execute(
                "INSERT INTO `test_categories` (`name`, `code`, `description`, `icon`, `sort_order`, `is_active`)
                 VALUES (?, ?, ?, ?, ?, 1)",
                [$name, $code, $description, $icon, $sortOrder]
            );
            log_audit('Category Created', 'Tests', $code, "Created test category {$name}.");
            set_flash('success', "Category '{$name}' created successfully.");
            header('Location: ' . ADMIN_URL . '/tests/categories.php');
            exit;
        }
    }

    // 2. Edit Category
    if ($action === 'edit_category') {
        $catId = (int)($_POST['category_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $code = strtoupper(trim($_POST['code'] ?? ''));
        $description = trim($_POST['description'] ?? '');
        $icon = trim($_POST['icon'] ?? 'bi-droplet');
        $sortOrder = (int)($_POST['sort_order'] ?? 0);
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if (empty($name) || empty($code)) {
            $errors[] = 'Category name and short code are required.';
        } else {
            Database::execute(
                "UPDATE `test_categories` 
                 SET `name` = ?, `code` = ?, `description` = ?, `icon` = ?, `sort_order` = ?, `is_active` = ?
                 WHERE `id` = ?",
                [$name, $code, $description, $icon, $sortOrder, $isActive, $catId]
            );
            log_audit('Category Updated', 'Tests', $code, "Updated test category #{$catId} ({$name}).");
            set_flash('success', "Category '{$name}' updated successfully.");
            header('Location: ' . ADMIN_URL . '/tests/categories.php');
            exit;
        }
    }

    // 3. Delete Category
    if ($action === 'delete_category') {
        $catId = (int)($_POST['category_id'] ?? 0);
        $cat = Database::fetch("SELECT * FROM `test_categories` WHERE `id` = ?", [$catId]);
        if ($cat) {
            $testCount = (int)Database::fetchColumn("SELECT COUNT(*) FROM `tests` WHERE `category_id` = ?", [$catId]);
            if ($testCount > 0) {
                Database::execute("UPDATE `test_categories` SET `is_active` = 0 WHERE `id` = ?", [$catId]);
                log_audit('Category Deactivated', 'Tests', $cat['code'], "Deactivated category {$cat['name']} due to {$testCount} associated tests.");
                set_flash('warning', "Category \"{$cat['name']}\" contains {$testCount} pathology tests. To preserve test mappings, it was set to Inactive.");
            } else {
                Database::execute("DELETE FROM `test_categories` WHERE `id` = ?", [$catId]);
                log_audit('Category Deleted', 'Tests', $cat['code'], "Deleted test category {$cat['name']}.");
                set_flash('success', "Category \"{$cat['name']}\" deleted successfully.");
            }
            header('Location: ' . ADMIN_URL . '/tests/categories.php');
            exit;
        }
    }
}

$categories = Database::fetchAll(
    "SELECT c.*, (SELECT COUNT(*) FROM `tests` t WHERE t.category_id = c.id) as test_count 
     FROM `test_categories` c 
     ORDER BY c.sort_order ASC, c.name ASC"
);

require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
  <div>
    <h2 class="h4 fw-bold mb-1">Pathology Test Categories</h2>
    <p class="text-muted small mb-0">Organize pathology investigations by clinical department and discipline</p>
  </div>
  <button class="btn btn-primary-lm d-inline-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#newCategoryModal">
    <i class="bi bi-folder-plus"></i> Add New Category
  </button>
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
  <div class="admin-card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th style="width: 70px;">Order</th>
            <th>Category Name</th>
            <th>Short Code</th>
            <th>Description</th>
            <th>Associated Tests</th>
            <th>Status</th>
            <th class="text-end text-nowrap pe-3" style="width: 140px;">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($categories as $cat): ?>
            <tr>
              <td><span class="badge bg-light text-dark border"><?= $cat['sort_order'] ?></span></td>
              <td>
                <div class="fw-bold d-flex align-items-center gap-2">
                  <i class="bi <?= e($cat['icon']) ?> text-primary fs-5"></i>
                  <?= e($cat['name']) ?>
                </div>
              </td>
              <td><span class="badge bg-light text-dark border font-monospace"><?= e($cat['code']) ?></span></td>
              <td class="text-muted small"><?= e($cat['description'] ?: '-') ?></td>
              <td>
                <a href="<?= ADMIN_URL ?>/tests/index.php?category=<?= $cat['id'] ?>" class="badge bg-primary-subtle text-primary-emphasis text-decoration-none">
                  <?= $cat['test_count'] ?> Tests
                </a>
              </td>
              <td>
                <span class="badge <?= $cat['is_active'] ? 'bg-success-subtle text-success-emphasis' : 'bg-secondary' ?>">
                  <?= $cat['is_active'] ? 'Active' : 'Inactive' ?>
                </span>
              </td>
              <td class="text-end text-nowrap pe-3">
                <div class="d-inline-flex align-items-center justify-content-end gap-1">
                  <!-- View Tests in Category -->
                  <a href="<?= ADMIN_URL ?>/tests/index.php?category=<?= $cat['id'] ?>" 
                     class="btn btn-sm btn-outline-primary btn-action-icon" 
                     title="View Tests in Category">
                    <i class="bi bi-eye"></i>
                  </a>

                  <!-- Edit Category Button -->
                  <button type="button" 
                          class="btn btn-sm btn-outline-secondary btn-action-icon" 
                          onclick='openEditCategoryModal(<?= htmlspecialchars(json_encode($cat), ENT_QUOTES, "UTF-8") ?>)' 
                          title="Edit Category">
                    <i class="bi bi-pencil"></i>
                  </button>

                  <!-- Delete Category Button -->
                  <form method="POST" action="<?= ADMIN_URL ?>/tests/categories.php" class="d-inline m-0 p-0" 
                        onsubmit="return confirm('Delete or deactivate category <?= e(addslashes($cat['name'])) ?>?');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete_category">
                    <input type="hidden" name="category_id" value="<?= $cat['id'] ?>">
                    <button type="submit" 
                            class="btn btn-sm btn-outline-danger btn-action-icon" 
                            title="Delete Category">
                      <i class="bi bi-trash"></i>
                    </button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Modal: New Category -->
<div class="modal fade" id="newCategoryModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="POST" action="">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="create_category">
        <div class="modal-header bg-light">
          <h5 class="modal-title fw-bold"><i class="bi bi-folder-plus text-primary me-2"></i>Add Test Category</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body p-4">
          <div class="mb-3">
            <label class="form-label small fw-semibold">Category Name <span class="text-danger">*</span></label>
            <input type="text" name="name" class="form-control" placeholder="e.g. Molecular Pathology" required>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold">Short Code <span class="text-danger">*</span></label>
            <input type="text" name="code" class="form-control font-monospace text-uppercase" placeholder="e.g. MOL" required>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold">Icon Class (Bootstrap Icons)</label>
            <input type="text" name="icon" class="form-control font-monospace" value="bi-droplet-half">
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold">Sort Order</label>
            <input type="number" name="sort_order" class="form-control" value="<?= count($categories) + 1 ?>">
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold">Description</label>
            <textarea name="description" class="form-control" rows="2" placeholder="Brief clinical purpose of tests in this category..."></textarea>
          </div>
        </div>
        <div class="modal-footer bg-light">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-save"><i class="bi bi-check-circle me-1"></i> Save Category</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal: Edit Category -->
<div class="modal fade" id="editCategoryModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="POST" action="">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="edit_category">
        <input type="hidden" name="category_id" id="edit_category_id">

        <div class="modal-header bg-light">
          <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square text-primary me-2"></i>Edit Test Category</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body p-4">
          <div class="mb-3">
            <label class="form-label small fw-semibold">Category Name <span class="text-danger">*</span></label>
            <input type="text" name="name" id="edit_name" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold">Short Code <span class="text-danger">*</span></label>
            <input type="text" name="code" id="edit_code" class="form-control font-monospace text-uppercase" required>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold">Icon Class (Bootstrap Icons)</label>
            <input type="text" name="icon" id="edit_icon" class="form-control font-monospace">
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold">Sort Order</label>
            <input type="number" name="sort_order" id="edit_sort_order" class="form-control">
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold">Description</label>
            <textarea name="description" id="edit_description" class="form-control" rows="2"></textarea>
          </div>
          <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" name="is_active" id="edit_is_active" value="1">
            <label class="form-check-label small fw-semibold" for="edit_is_active">Category Active in Catalog</label>
          </div>
        </div>
        <div class="modal-footer bg-light">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-save"><i class="bi bi-check-circle me-1"></i> Update Category</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function openEditCategoryModal(cat) {
  document.getElementById('edit_category_id').value = cat.id;
  document.getElementById('edit_name').value = cat.name || '';
  document.getElementById('edit_code').value = cat.code || '';
  document.getElementById('edit_icon').value = cat.icon || 'bi-droplet';
  document.getElementById('edit_sort_order').value = cat.sort_order || 0;
  document.getElementById('edit_description').value = cat.description || '';
  document.getElementById('edit_is_active').checked = parseInt(cat.is_active) === 1;

  const modal = new bootstrap.Modal(document.getElementById('editCategoryModal'));
  modal.show();
}
</script>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>

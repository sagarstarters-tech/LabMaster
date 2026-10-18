<?php
/**
 * LabMaster - Security & Clinical Audit Trail
 */

declare(strict_types=1);

$pageTitle = 'Audit Trail';
$activeNav = 'audit_logs';

require_once dirname(__DIR__) . '/includes/auth_guard.php';
Auth::requirePermission('audit.view');

$moduleFilter = trim($_GET['module'] ?? '');

$sql = "SELECT a.*, u.full_name as user_name, u.username 
        FROM `audit_logs` a 
        LEFT JOIN `users` u ON a.user_id = u.id 
        WHERE 1=1";
$params = [];

if (!empty($moduleFilter)) {
    $sql .= " AND a.module = ?";
    $params[] = $moduleFilter;
}

$sql .= " ORDER BY a.id DESC LIMIT 300";
$logs = Database::fetchAll($sql, $params);

require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
  <div>
    <h2 class="h4 fw-bold mb-1">Security & Clinical Audit Trail</h2>
    <p class="text-muted small mb-0">Immutable chronological record of logins, report approvals, amendments, and financial events</p>
  </div>
</div>

<!-- Filter Bar -->
<div class="admin-card mb-4">
  <div class="admin-card-body p-3">
    <form method="GET" action="" class="row g-2 align-items-center">
      <div class="col-md-4">
        <select name="module" class="form-select">
          <option value="">All System Modules</option>
          <option value="Auth" <?= $moduleFilter === 'Auth' ? 'selected' : '' ?>>Auth & Logins</option>
          <option value="Patients" <?= $moduleFilter === 'Patients' ? 'selected' : '' ?>>Patients</option>
          <option value="Orders" <?= $moduleFilter === 'Orders' ? 'selected' : '' ?>>Test Accessions</option>
          <option value="Results" <?= $moduleFilter === 'Results' ? 'selected' : '' ?>>Results Entry</option>
          <option value="Reports" <?= $moduleFilter === 'Reports' ? 'selected' : '' ?>>Pathology Reports</option>
          <option value="Billing" <?= $moduleFilter === 'Billing' ? 'selected' : '' ?>>Billing & Payments</option>
          <option value="Inventory" <?= $moduleFilter === 'Inventory' ? 'selected' : '' ?>>Reagents Inventory</option>
          <option value="Settings" <?= $moduleFilter === 'Settings' ? 'selected' : '' ?>>System Settings</option>
        </select>
      </div>
      <div class="col-md-2">
        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-funnel"></i> Filter</button>
      </div>
    </form>
  </div>
</div>

<div class="admin-card">
  <div class="admin-card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0 small">
        <thead class="table-light">
          <tr>
            <th>Timestamp</th>
            <th>Operator / Staff</th>
            <th>Module</th>
            <th>Action</th>
            <th>Record Ref</th>
            <th>Audit Details</th>
            <th>IP Address</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($logs)): ?>
            <tr><td colspan="7" class="text-center text-muted py-5">No audit events recorded.</td></tr>
          <?php else: ?>
            <?php foreach ($logs as $l): ?>
              <tr>
                <td class="text-muted"><?= format_date($l['created_at'], true) ?></td>
                <td>
                  <div class="fw-semibold text-dark"><?= e($l['user_name'] ?: 'System Routine') ?></div>
                  <?php if (!empty($l['username'])): ?>
                    <span class="text-muted" style="font-size:0.7rem;">@<?= e($l['username']) ?></span>
                  <?php endif; ?>
                </td>
                <td>
                  <span class="badge bg-light text-dark border"><?= e($l['module']) ?></span>
                </td>
                <td class="fw-bold text-dark"><?= e($l['action']) ?></td>
                <td>
                  <span class="badge bg-light text-secondary font-monospace"><?= e($l['record_id'] ?: '-') ?></span>
                </td>
                <td class="text-muted text-break" style="max-width: 380px;">
                  <?= e($l['details'] ?: '-') ?>
                </td>
                <td class="text-muted font-monospace" style="font-size: 0.75rem;"><?= e($l['ip_address'] ?: '127.0.0.1') ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>

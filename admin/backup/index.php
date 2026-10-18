<?php
/**
 * LabMaster - Database Backup & Restore Manager
 */

declare(strict_types=1);

$pageTitle = 'Database Backup & Restore';
$activeNav = 'backup';

require_once dirname(__DIR__) . '/includes/auth_guard.php';
Auth::requirePermission('backup.manage');

$backupDir = BASE_PATH . '/storage/backups';
if (!is_dir($backupDir)) {
    @mkdir($backupDir, 0777, true);
}

// 1. Handle Backup Creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_backup') {
    require_csrf();

    try {
        $timestamp = date('Y-m-d_H-i-s');
        $filename = "labmaster_db_backup_{$timestamp}.sql";
        $filepath = $backupDir . '/' . $filename;

        // Query all tables
        $tables = Database::fetchAll("SHOW TABLES");
        $tableKey = 'Tables_in_labmaster_db';

        $dump = "-- LabMaster Database Backup\n";
        $dump .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
        $dump .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";

        foreach ($tables as $tRow) {
            $table = reset($tRow);
            $createTable = Database::fetch("SHOW CREATE TABLE `{$table}`")['Create Table'] ?? '';
            $dump .= "DROP TABLE IF EXISTS `{$table}`;\n";
            $dump .= $createTable . ";\n\n";

            $rows = Database::fetchAll("SELECT * FROM `{$table}`");
            if (!empty($rows)) {
                $dump .= "INSERT INTO `{$table}` VALUES ";
                $rowStrings = [];
                foreach ($rows as $r) {
                    $escaped = array_map(function ($val) {
                        if ($val === null) return 'NULL';
                        return Database::getInstance()->quote((string)$val);
                    }, $r);
                    $rowStrings[] = "(" . implode(", ", $escaped) . ")";
                }
                $dump .= implode(",\n", $rowStrings) . ";\n\n";
            }
        }

        $dump .= "SET FOREIGN_KEY_CHECKS = 1;\n";

        file_put_contents($filepath, $dump);
        $fileSize = filesize($filepath);

        Database::execute(
            "INSERT INTO `backup_logs` (`filename`, `file_size`, `backup_type`, `created_by`) VALUES (?, ?, 'manual', ?)",
            [$filename, $fileSize, Auth::id()]
        );

        log_audit('Database Backup Created', 'Backup', $filename, "Generated manual backup {$filename} (" . round($fileSize / 1024, 2) . " KB).");
        set_flash('success', "Database backup created successfully: {$filename} (" . round($fileSize / 1024, 2) . " KB)");
        header('Location: ' . ADMIN_URL . '/backup/index.php');
        exit;
    } catch (Exception $e) {
        set_flash('danger', 'Backup error: ' . $e->getMessage());
    }
}

// 2. Handle Backup Download
if (isset($_GET['download'])) {
    $dlFile = basename($_GET['download']);
    $dlPath = $backupDir . '/' . $dlFile;
    if (file_exists($dlPath)) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $dlFile . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($dlPath));
        readfile($dlPath);
        exit;
    } else {
        set_flash('danger', 'Backup file not found.');
    }
}

// 3. Handle Backup Deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_backup') {
    require_csrf();
    $backupId = (int)($_POST['backup_id'] ?? 0);
    $bk = Database::fetch("SELECT * FROM `backup_logs` WHERE `id` = ?", [$backupId]);
    if ($bk) {
        $delPath = $backupDir . '/' . basename($bk['filename']);
        if (file_exists($delPath)) {
            @unlink($delPath);
        }
        Database::execute("DELETE FROM `backup_logs` WHERE `id` = ?", [$backupId]);
        log_audit('Database Backup Deleted', 'Backup', $bk['filename'], "Deleted backup {$bk['filename']}.");
        set_flash('success', "Backup archive {$bk['filename']} deleted successfully.");
    } else {
        set_flash('danger', 'Backup record not found.');
    }
    header('Location: ' . ADMIN_URL . '/backup/index.php');
    exit;
}

$backups = Database::fetchAll(
    "SELECT b.*, u.full_name as creator_name 
     FROM `backup_logs` b 
     LEFT JOIN `users` u ON b.created_by = u.id 
     ORDER BY b.id DESC"
);

require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
  <div>
    <h2 class="h4 fw-bold mb-1">Database Backup & Disaster Recovery</h2>
    <p class="text-muted small mb-0">Generate comprehensive MariaDB/MySQL SQL dumps and maintain secure snapshots</p>
  </div>
  <form method="POST" action="">
    <?= csrf_field() ?>
    <button type="submit" name="action" value="create_backup" class="btn btn-save d-inline-flex align-items-center gap-2">
      <i class="bi bi-database-fill-down"></i> Create Manual Backup Now
    </button>
  </form>
</div>

<div class="admin-card">
  <div class="admin-card-header bg-white">
    <h3 class="admin-card-title text-primary">
      <i class="bi bi-clock-history"></i> Backup Archive History
    </h3>
  </div>
  <div class="admin-card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0 small">
        <thead class="table-light">
          <tr>
            <th>Backup Filename</th>
            <th>Type</th>
            <th>File Size</th>
            <th>Created By</th>
            <th>Created Timestamp</th>
            <th class="text-end text-nowrap pe-3" style="width: 140px;">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($backups)): ?>
            <tr><td colspan="6" class="text-center text-muted py-5">No database backup archives found. Click "Create Manual Backup Now" to generate one.</td></tr>
          <?php else: ?>
            <?php foreach ($backups as $bk): ?>
              <tr>
                <td>
                  <span class="fw-bold font-monospace text-dark"><?= e($bk['filename']) ?></span>
                </td>
                <td><span class="badge bg-light text-dark border"><?= e($bk['backup_type']) ?></span></td>
                <td><?= round($bk['file_size'] / 1024, 2) ?> KB</td>
                <td><?= e($bk['creator_name'] ?: 'System') ?></td>
                <td><?= format_date($bk['created_at'], true) ?></td>
                <td class="text-end text-nowrap pe-3">
                  <div class="d-inline-flex align-items-center justify-content-end gap-1">
                    <button type="button" class="btn btn-sm btn-outline-primary btn-action-icon" 
                            onclick="viewBackup(<?= htmlspecialchars(json_encode($bk), ENT_QUOTES, 'UTF-8') ?>)" 
                            title="View Backup Information">
                      <i class="bi bi-eye"></i>
                    </button>
                    <a href="<?= ADMIN_URL ?>/backup/index.php?download=<?= urlencode($bk['filename']) ?>" 
                       class="btn btn-sm btn-outline-secondary btn-action-icon" 
                       title="Download SQL Dump">
                      <i class="bi bi-download"></i>
                    </a>
                    <form method="POST" action="<?= ADMIN_URL ?>/backup/index.php" class="d-inline m-0 p-0" onsubmit="return confirm('Are you sure you want to permanently delete backup archive <?= e(addslashes($bk['filename'])) ?>?');">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="delete_backup">
                      <input type="hidden" name="backup_id" value="<?= $bk['id'] ?>">
                      <button type="submit" class="btn btn-sm btn-outline-danger btn-action-icon" title="Delete Backup Archive">
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

<!-- Modal: View Backup Information -->
<div class="modal fade" id="viewBackupModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content shadow border-0">
      <div class="modal-header bg-light">
        <h5 class="modal-title fw-bold" id="viewBackupTitle">Backup Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <table class="table table-sm table-borderless mb-0">
          <tbody>
            <tr><th class="text-muted w-35">Filename:</th><td id="bkFilename" class="font-monospace fw-bold text-dark"></td></tr>
            <tr><th class="text-muted">Backup Type:</th><td id="bkType"></td></tr>
            <tr><th class="text-muted">File Size:</th><td id="bkSize"></td></tr>
            <tr><th class="text-muted">Created By:</th><td id="bkCreator"></td></tr>
            <tr><th class="text-muted">Timestamp:</th><td id="bkTime"></td></tr>
          </tbody>
        </table>
      </div>
      <div class="modal-footer bg-light py-2">
        <a href="#" id="bkDownloadLink" class="btn btn-sm btn-primary-lm">
          <i class="bi bi-download me-1"></i> Download
        </a>
        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<script>
function viewBackup(b) {
  document.getElementById('viewBackupTitle').innerText = 'Backup Archive #' + b.id;
  document.getElementById('bkFilename').innerText = b.filename || '';
  document.getElementById('bkType').innerHTML = '<span class="badge bg-light text-dark border">' + (b.backup_type || 'Manual') + '</span>';
  document.getElementById('bkSize').innerText = (Math.round(b.file_size / 1024 * 100) / 100) + ' KB';
  document.getElementById('bkCreator').innerText = b.creator_name || 'System';
  document.getElementById('bkTime').innerText = b.created_at || '';
  document.getElementById('bkDownloadLink').href = '<?= ADMIN_URL ?>/backup/index.php?download=' + encodeURIComponent(b.filename);

  new bootstrap.Modal(document.getElementById('viewBackupModal')).show();
}
</script>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>

<?php
/**
 * LabMaster - Admin Header & Topbar Component
 */

declare(strict_types=1);

require_once __DIR__ . '/auth_guard.php';

$pageTitle = $pageTitle ?? 'Admin Control Center';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($pageTitle) ?> | <?= e(Settings::get('lab_name', 'LabMaster Diagnostics')) ?></title>

  <!-- Favicon -->
  <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%230284c7'><path d='M4.5 10.5C3.67 10.5 3 11.17 3 12s.67 1.5 1.5 1.5h15c.83 0 1.5-.67 1.5-1.5s-.67-1.5-1.5-1.5h-15z'/><path d='M10.5 4.5C10.5 3.67 11.17 3 12 3s1.5.67 1.5 1.5v15c0 .83-.67 1.5-1.5 1.5s-1.5-.67-1.5-1.5v-15z'/></svg>">

  <!-- Bootstrap 5.3 CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Bootstrap Icons -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  
  <!-- Admin Theme CSS -->
  <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/admin.css?v=<?= APP_VERSION ?>">
</head>
<body class="admin-body">

  <!-- Include Sidebar -->
  <?php require_once __DIR__ . '/sidebar.php'; ?>

  <!-- Admin Main Wrapper -->
  <div class="admin-wrapper">

    <!-- Top Navigation Bar -->
    <header class="admin-topbar">
      <div class="d-flex align-items-center gap-3">
        <button type="button" class="btn btn-sm btn-light border" id="sidebarToggle" aria-label="Toggle Sidebar">
          <i class="bi bi-list fs-5"></i>
        </button>

        <h1 class="h5 mb-0 fw-bold d-none d-md-block text-truncate" style="max-width: 450px;">
          <?= e($pageTitle) ?>
        </h1>
      </div>

      <div class="d-flex align-items-center gap-3">
        <!-- Quick Action Add Button -->
        <?php if (Auth::can('orders.create')): ?>
        <a href="<?= ADMIN_URL ?>/orders/create.php" class="btn btn-sm btn-primary-lm d-none d-sm-flex align-items-center gap-1">
          <i class="bi bi-plus-circle"></i> New Accession
        </a>
        <?php endif; ?>

        <!-- Notifications Dropdown -->
        <div class="dropdown">
          <button class="btn btn-light position-relative p-2 rounded-circle" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="View Notifications">
            <i class="bi bi-bell-fill text-secondary fs-5"></i>
            <?php if ($unreadNotifCount > 0): ?>
            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.65rem;">
              <?= $unreadNotifCount ?>
            </span>
            <?php endif; ?>
          </button>
          <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 p-2" style="width: 300px;">
            <li class="d-flex justify-content-between align-items-center px-2 py-1 mb-2 border-bottom">
              <span class="fw-bold small">Notifications</span>
              <span class="badge bg-primary rounded-pill"><?= $unreadNotifCount ?> Unread</span>
            </li>
            <?php if (empty($unreadNotifications)): ?>
              <li class="text-center text-muted small py-3">No unread notifications</li>
            <?php else: ?>
              <?php foreach ($unreadNotifications as $notif): ?>
                <li class="mb-2 p-2 rounded bg-light border-start border-3 border-<?= e($notif['type']) ?>">
                  <div class="fw-semibold small text-truncate"><?= e($notif['title']) ?></div>
                  <div class="text-muted" style="font-size: 0.75rem;"><?= e($notif['message']) ?></div>
                </li>
              <?php endforeach; ?>
            <?php endif; ?>
          </ul>
        </div>

        <!-- User Profile Dropdown -->
        <div class="dropdown">
          <button class="btn btn-light border-0 d-flex align-items-center gap-2 p-1 pe-2 rounded-pill" type="button" data-bs-toggle="dropdown" aria-expanded="false">
            <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; font-size: 0.85rem; font-weight: 700;">
              <?= strtoupper(substr($currentUser['full_name'], 0, 1)) ?>
            </div>
            <span class="small fw-semibold text-dark d-none d-md-inline"><?= e($currentUser['full_name']) ?></span>
            <i class="bi bi-chevron-down text-muted small"></i>
          </button>
          <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0">
            <li class="px-3 py-2 border-bottom">
              <div class="fw-bold small"><?= e($currentUser['full_name']) ?></div>
              <div class="text-muted" style="font-size: 0.75rem;"><?= e($currentUser['email']) ?></div>
              <span class="badge bg-info-subtle text-info-emphasis mt-1"><?= e($currentUser['role_name']) ?></span>
            </li>
            <li><a class="dropdown-item py-2 small" href="<?= ADMIN_URL ?>/users/profile.php"><i class="bi bi-person me-2"></i> My Profile & Signature</a></li>
            <?php if (Auth::can('users.manage')): ?>
              <li><a class="dropdown-item py-2 small" href="<?= ADMIN_URL ?>/users/roles.php"><i class="bi bi-shield-lock me-2 text-warning"></i> Roles & Permissions</a></li>
            <?php endif; ?>
            <?php if (Auth::can('settings.manage')): ?>
              <li><a class="dropdown-item py-2 small" href="<?= ADMIN_URL ?>/settings/index.php"><i class="bi bi-gear me-2"></i> Settings</a></li>
            <?php endif; ?>
            <li><hr class="dropdown-divider my-1"></li>
            <li><a class="dropdown-item py-2 small text-danger" href="<?= ADMIN_URL ?>/logout.php"><i class="bi bi-box-arrow-right me-2"></i> Sign Out</a></li>
          </ul>
        </div>
      </div>
    </header>

    <!-- Main Content Container -->
    <main class="admin-content">
      <!-- Flash Alert Display -->
      <?= render_flash() ?>

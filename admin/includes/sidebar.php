<?php
/**
 * LabMaster - Admin Sidebar Navigation
 */

declare(strict_types=1);

$activeNav = $activeNav ?? 'dashboard';
?>
<!-- Admin Sidebar -->
<aside class="admin-sidebar">
  <a href="<?= ADMIN_URL ?>/index.php" class="sidebar-brand">
    <i class="bi bi-heart-pulse-fill"></i>
    <span>Lab<span class="text-info">Master</span></span>
  </a>

  <div class="px-3 py-2 border-bottom border-secondary border-opacity-25 d-flex align-items-center gap-2">
    <div class="rounded-circle bg-info bg-opacity-10 text-info p-2 d-flex align-items-center justify-content-center" style="width:36px; height:36px;">
      <i class="bi bi-person-fill"></i>
    </div>
    <div class="overflow-hidden">
      <div class="text-white text-truncate fw-semibold small"><?= e($currentUser['full_name']) ?></div>
      <div class="text-secondary text-truncate" style="font-size: 0.72rem;"><?= e($currentUser['role_name']) ?></div>
    </div>
  </div>

  <ul class="sidebar-nav">
    <li class="sidebar-header">Core Operations</li>
    
    <li class="sidebar-item">
      <a href="<?= ADMIN_URL ?>/index.php" class="sidebar-link <?= $activeNav === 'dashboard' ? 'active' : '' ?>">
        <i class="bi bi-speedometer2"></i>
        <span>Dashboard</span>
      </a>
    </li>

    <?php if (Auth::can('patients.view')): ?>
    <li class="sidebar-item">
      <a href="<?= ADMIN_URL ?>/patients/index.php" class="sidebar-link <?= $activeNav === 'patients' ? 'active' : '' ?>">
        <i class="bi bi-people-fill"></i>
        <span>Patients</span>
      </a>
    </li>
    <?php endif; ?>

    <?php if (Auth::can('bookings.manage')): ?>
    <li class="sidebar-item">
      <a href="<?= ADMIN_URL ?>/bookings/index.php" class="sidebar-link <?= $activeNav === 'bookings' ? 'active' : '' ?>">
        <i class="bi bi-calendar-event"></i>
        <span>Quick Bookings</span>
        <?php if (!empty($pendingBookingsCount)): ?>
          <span class="badge bg-warning text-dark ms-auto"><?= $pendingBookingsCount ?></span>
        <?php endif; ?>
      </a>
    </li>
    <?php endif; ?>

    <?php if (Auth::canAny(['orders.view', 'results.entry', 'reports.view'])): ?>
    <li class="sidebar-header">Clinical Laboratory</li>

    <?php if (Auth::can('orders.view')): ?>
    <li class="sidebar-item">
      <a href="<?= ADMIN_URL ?>/orders/index.php" class="sidebar-link <?= $activeNav === 'orders' ? 'active' : '' ?>">
        <i class="bi bi-collection-fill"></i>
        <span>Test Orders</span>
        <?php if (!empty($ordersProcessingCount)): ?>
          <span class="badge bg-info ms-auto"><?= $ordersProcessingCount ?></span>
        <?php endif; ?>
      </a>
    </li>
    <?php endif; ?>

    <?php if (Auth::can('results.entry')): ?>
    <li class="sidebar-item">
      <a href="<?= ADMIN_URL ?>/results/index.php" class="sidebar-link <?= $activeNav === 'results' ? 'active' : '' ?>">
        <i class="bi bi-pencil-square"></i>
        <span>Result Entry</span>
      </a>
    </li>
    <?php endif; ?>

    <?php if (Auth::can('reports.view')): ?>
    <li class="sidebar-item">
      <a href="<?= ADMIN_URL ?>/reports/index.php" class="sidebar-link <?= $activeNav === 'reports' ? 'active' : '' ?>">
        <i class="bi bi-file-earmark-medical-fill"></i>
        <span>Pathology Reports</span>
        <?php if (!empty($pendingReportsCount)): ?>
          <span class="badge bg-danger ms-auto"><?= $pendingReportsCount ?></span>
        <?php endif; ?>
      </a>
    </li>
    <?php endif; ?>
    <?php endif; ?>

    <?php if (Auth::can('tests.manage')): ?>
    <li class="sidebar-header">Master Catalog</li>

    <li class="sidebar-item">
      <a href="<?= ADMIN_URL ?>/tests/index.php" class="sidebar-link <?= $activeNav === 'tests' ? 'active' : '' ?>">
        <i class="bi bi-list-check"></i>
        <span>Test Master</span>
      </a>
    </li>
    <li class="sidebar-item">
      <a href="<?= ADMIN_URL ?>/tests/categories.php" class="sidebar-link <?= $activeNav === 'categories' ? 'active' : '' ?>">
        <i class="bi bi-tags-fill"></i>
        <span>Test Categories</span>
      </a>
    </li>
    <li class="sidebar-item">
      <a href="<?= ADMIN_URL ?>/tests/audit.php" class="sidebar-link <?= $activeNav === 'test_audit' ? 'active' : '' ?>">
        <i class="bi bi-shield-check"></i>
        <span>Test Master Audit</span>
      </a>
    </li>
    <?php endif; ?>

    <?php if (Auth::canAny(['doctors.view', 'reconciliation.view'])): ?>
    <li class="sidebar-header">Doctor & Financial Reconciliation</li>

    <?php if (Auth::can('doctors.view')): ?>
    <li class="sidebar-item">
      <a href="<?= ADMIN_URL ?>/doctors/index.php" class="sidebar-link <?= ($activeNav === 'doctors' && basename($_SERVER['PHP_SELF']) === 'index.php') ? 'active' : '' ?>">
        <i class="bi bi-hospital"></i>
        <span>Doctor Master</span>
      </a>
    </li>
    <?php endif; ?>

    <?php if (Auth::can('reconciliation.view')): ?>
    <li class="sidebar-item">
      <a href="<?= ADMIN_URL ?>/doctors/agreements.php" class="sidebar-link <?= ($activeNav === 'doctors' && strpos($_SERVER['PHP_SELF'], 'agreement') !== false) ? 'active' : '' ?>">
        <i class="bi bi-file-earmark-text"></i>
        <span>Service Agreements</span>
      </a>
    </li>
    <li class="sidebar-item">
      <a href="<?= ADMIN_URL ?>/doctors/reconciliation.php" class="sidebar-link <?= ($activeNav === 'doctors' && strpos($_SERVER['PHP_SELF'], 'reconciliation') !== false) ? 'active' : '' ?>">
        <i class="bi bi-wallet2"></i>
        <span>Financial Reconciliation</span>
      </a>
    </li>
    <li class="sidebar-item">
      <a href="<?= ADMIN_URL ?>/doctors/reports.php" class="sidebar-link <?= ($activeNav === 'doctors' && strpos($_SERVER['PHP_SELF'], 'reports.php') !== false) ? 'active' : '' ?>">
        <i class="bi bi-graph-up-arrow"></i>
        <span>Doctor Reports</span>
      </a>
    </li>
    <?php endif; ?>
    <?php endif; ?>

    <?php if (Auth::canAny(['billing.manage', 'accounting.manage'])): ?>
    <li class="sidebar-header">Finance & Billing</li>

    <?php if (Auth::can('billing.manage')): ?>
    <li class="sidebar-item">
      <a href="<?= ADMIN_URL ?>/billing/index.php" class="sidebar-link <?= $activeNav === 'billing' ? 'active' : '' ?>">
        <i class="bi bi-receipt"></i>
        <span>Invoices & Billing</span>
      </a>
    </li>
    <?php endif; ?>

    <?php if (Auth::can('accounting.manage')): ?>
    <li class="sidebar-item">
      <a href="<?= ADMIN_URL ?>/accounting/index.php" class="sidebar-link <?= $activeNav === 'accounting' ? 'active' : '' ?>">
        <i class="bi bi-wallet2"></i>
        <span>Daily Cashbook</span>
      </a>
    </li>
    <li class="sidebar-item">
      <a href="<?= ADMIN_URL ?>/accounting/expenses.php" class="sidebar-link <?= $activeNav === 'expenses' ? 'active' : '' ?>">
        <i class="bi bi-cash-stack"></i>
        <span>Expenses</span>
      </a>
    </li>
    <li class="sidebar-item">
      <a href="<?= ADMIN_URL ?>/accounting/reports.php" class="sidebar-link <?= $activeNav === 'financial_reports' ? 'active' : '' ?>">
        <i class="bi bi-graph-up"></i>
        <span>Revenue Analytics</span>
      </a>
    </li>
    <?php endif; ?>
    <?php endif; ?>

    <?php if (Auth::canAny(['inventory.manage', 'suppliers.manage'])): ?>
    <li class="sidebar-header">Reagents & Stock</li>

    <?php if (Auth::can('inventory.manage')): ?>
    <li class="sidebar-item">
      <a href="<?= ADMIN_URL ?>/inventory/index.php" class="sidebar-link <?= $activeNav === 'inventory' ? 'active' : '' ?>">
        <i class="bi bi-box-seam-fill"></i>
        <span>Inventory Items</span>
        <?php if (!empty($lowStockCount)): ?>
          <span class="badge bg-danger ms-auto" title="Low Stock"><?= $lowStockCount ?></span>
        <?php endif; ?>
      </a>
    </li>
    <li class="sidebar-item">
      <a href="<?= ADMIN_URL ?>/inventory/batches.php" class="sidebar-link <?= $activeNav === 'batches' ? 'active' : '' ?>">
        <i class="bi bi-calendar-x"></i>
        <span>Batches & Expiry</span>
        <?php if (!empty($nearExpiryCount)): ?>
          <span class="badge bg-warning text-dark ms-auto" title="Near Expiry"><?= $nearExpiryCount ?></span>
        <?php endif; ?>
      </a>
    </li>
    <li class="sidebar-item">
      <a href="<?= ADMIN_URL ?>/inventory/movements.php" class="sidebar-link <?= $activeNav === 'movements' ? 'active' : '' ?>">
        <i class="bi bi-arrow-left-right"></i>
        <span>Stock Movements</span>
      </a>
    </li>
    <?php endif; ?>

    <?php if (Auth::can('suppliers.manage')): ?>
    <li class="sidebar-item">
      <a href="<?= ADMIN_URL ?>/suppliers/index.php" class="sidebar-link <?= $activeNav === 'suppliers' ? 'active' : '' ?>">
        <i class="bi bi-truck"></i>
        <span>Suppliers</span>
      </a>
    </li>
    <li class="sidebar-item">
      <a href="<?= ADMIN_URL ?>/suppliers/purchases.php" class="sidebar-link <?= $activeNav === 'purchases' ? 'active' : '' ?>">
        <i class="bi bi-cart-check-fill"></i>
        <span>Purchases</span>
      </a>
    </li>
    <?php endif; ?>
    <?php endif; ?>

    <?php if (Auth::canAny(['users.manage', 'settings.manage', 'audit.view', 'backup.manage'])): ?>
    <li class="sidebar-header">Administration</li>

    <?php if (Auth::can('users.manage')): ?>
    <li class="sidebar-item">
      <a href="<?= ADMIN_URL ?>/users/index.php" class="sidebar-link <?= ($activeNav === 'users' && basename($_SERVER['PHP_SELF']) === 'index.php') ? 'active' : '' ?>">
        <i class="bi bi-people"></i>
        <span>Staff Directory</span>
      </a>
    </li>
    <li class="sidebar-item">
      <a href="<?= ADMIN_URL ?>/users/roles.php" class="sidebar-link <?= ($activeNav === 'users' && basename($_SERVER['PHP_SELF']) === 'roles.php') ? 'active' : '' ?>">
        <i class="bi bi-shield-lock-fill text-warning"></i>
        <span>Roles & Permissions</span>
      </a>
    </li>
    <?php endif; ?>

    <?php if (Auth::can('settings.manage')): ?>
    <li class="sidebar-item">
      <a href="<?= ADMIN_URL ?>/settings/index.php" class="sidebar-link <?= $activeNav === 'settings' ? 'active' : '' ?>">
        <i class="bi bi-gear-fill"></i>
        <span>System Settings</span>
      </a>
    </li>
    <?php endif; ?>

    <?php if (Auth::can('audit.view')): ?>
    <li class="sidebar-item">
      <a href="<?= ADMIN_URL ?>/audit_logs/index.php" class="sidebar-link <?= $activeNav === 'audit_logs' ? 'active' : '' ?>">
        <i class="bi bi-shield-shaded"></i>
        <span>Audit Trail</span>
      </a>
    </li>
    <?php endif; ?>

    <?php if (Auth::can('backup.manage')): ?>
    <li class="sidebar-item">
      <a href="<?= ADMIN_URL ?>/backup/index.php" class="sidebar-link <?= $activeNav === 'backup' ? 'active' : '' ?>">
        <i class="bi bi-database-down"></i>
        <span>Database Backup</span>
      </a>
    </li>
    <?php endif; ?>
    <?php endif; ?>

    <li class="sidebar-item mt-3 pt-2 border-top border-secondary border-opacity-25">
      <a href="<?= BASE_URL ?>/index.php" target="_blank" class="sidebar-link">
        <i class="bi bi-box-arrow-up-right text-info"></i>
        <span>View Public Website</span>
      </a>
    </li>
    <li class="sidebar-item">
      <a href="<?= ADMIN_URL ?>/logout.php" class="sidebar-link text-danger">
        <i class="bi bi-box-arrow-right text-danger"></i>
        <span>Sign Out</span>
      </a>
    </li>
  </ul>
</aside>

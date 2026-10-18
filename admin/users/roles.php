<?php
/**
 * LabMaster - Role-Based Access Control (RBAC) & Permissions Manager
 * Safely manage and customize operational capabilities for each staff role
 */

declare(strict_types=1);

$pageTitle = 'Role & Permissions Control';
$activeNav = 'users';

require_once dirname(__DIR__) . '/includes/auth_guard.php';
Auth::requirePermission('users.manage');

// 1. Standard Clinical Presets Definition
$rolePresets = [
    'receptionist' => [
        'name' => 'Receptionist (Front Desk)',
        'description' => 'Patient Registration, Test Booking, Billing & Payment, Report Status Check, and Report Delivery to Patient.',
        'permissions' => [
            'patients.view', 'patients.create', 'patients.edit',
            'bookings.manage',
            'orders.create', 'orders.view',
            'doctors.view',
            'billing.manage',
            'reports.view', 'reports.print', 'reports.whatsapp'
        ]
    ],
    'technician' => [
        'name' => 'Laboratory Technician',
        'description' => 'Sample Collection Management, Specimen Tracking, Testing & Result Entry, Reagents & Consumables, and Forwarding to Pathologist.',
        'permissions' => [
            'patients.view',
            'orders.view', 'orders.sample_collect',
            'results.entry',
            'inventory.manage', 'inventory.adjust',
            'reports.view', 'reports.print'
        ]
    ],
    'pathologist' => [
        'name' => 'Consultant Pathologist',
        'description' => 'Diagnostic Clinical Verification, Result Verification, Digital Sign-off & Medical Report Authorization.',
        'permissions' => [
            'patients.view', 'orders.view', 'doctors.view',
            'results.entry',
            'reports.view', 'reports.review', 'reports.approve', 'reports.amend',
            'reports.print', 'reports.whatsapp'
        ]
    ],
    'super_admin' => [
        'name' => 'Super Administrator',
        'description' => 'Full unrestricted administrative access to entire laboratory system, security settings, backups, and audits.',
        'permissions' => 'all'
    ],
    'lab_admin' => [
        'name' => 'Laboratory Administrator',
        'description' => 'Manages laboratory operations, finances, inventory, staff, tests, and clinical workflows.',
        'permissions' => [
            'patients.view', 'patients.create', 'patients.edit', 'patients.export',
            'bookings.manage', 'tests.manage', 'orders.create', 'orders.view', 'orders.sample_collect',
            'results.entry', 'reports.view', 'reports.review', 'reports.approve', 'reports.amend',
            'reports.print', 'reports.whatsapp', 'billing.manage', 'billing.refund', 'accounting.manage',
            'inventory.manage', 'inventory.adjust', 'suppliers.manage', 'users.manage', 'settings.manage',
            'audit.view', 'doctors.view', 'doctors.create', 'doctors.edit', 'reconciliation.view',
            'reconciliation.create', 'agreements.review', 'reconciliation.approve', 'reconciliation.pay',
            'reconciliation.reverse', 'reconciliation.export'
        ]
    ],
    'billing' => [
        'name' => 'Billing Operator',
        'description' => 'Patient invoicing, payment collection, receipts, and cashier counter.',
        'permissions' => [
            'patients.view', 'patients.create', 'orders.view', 'billing.manage'
        ]
    ],
    'inventory_manager' => [
        'name' => 'Inventory Manager',
        'description' => 'Reagent stock receipts, batch management, expiry tracking, and purchase orders.',
        'permissions' => [
            'inventory.manage', 'inventory.adjust', 'suppliers.manage'
        ]
    ],
    'accountant' => [
        'name' => 'Accountant',
        'description' => 'Daily cashbook, expense tracking, accounts receivables, supplier payables, and doctor reconciliation.',
        'permissions' => [
            'billing.manage', 'billing.refund', 'accounting.manage', 'suppliers.manage', 'doctors.view',
            'reconciliation.view', 'reconciliation.create', 'agreements.review', 'reconciliation.approve',
            'reconciliation.pay', 'reconciliation.reverse', 'reconciliation.export'
        ]
    ]
];

// Fetch all permissions and roles from database
$roles = Database::fetchAll("SELECT * FROM `roles` ORDER BY `id` ASC");
$allPermissions = Database::fetchAll("SELECT * FROM `permissions` ORDER BY `module` ASC, `id` ASC");

$permIdBySlug = [];
$permSlugById = [];
foreach ($allPermissions as $p) {
    $permIdBySlug[$p['slug']] = (int)$p['id'];
    $permSlugById[(int)$p['id']] = $p['slug'];
}

// 2. Handle POST Actions Safely
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? '';

    // Action A: Update Single Role Permissions
    if ($action === 'update_role_permissions') {
        $roleId = (int)($_POST['role_id'] ?? 0);
        $selectedPermIds = array_map('intval', $_POST['permissions'] ?? []);

        $targetRole = Database::fetch("SELECT * FROM `roles` WHERE `id` = ?", [$roleId]);
        if (!$targetRole) {
            set_flash('danger', 'Role not found.');
            header('Location: ' . ADMIN_URL . '/users/roles.php');
            exit;
        }

        // Safety Rule 1: Super Administrator is immutable to prevent system lockout
        if ($targetRole['slug'] === 'super_admin' || $roleId === 1) {
            set_flash('warning', 'Super Administrator permissions cannot be modified. Root system protection is permanently active.');
            header('Location: ' . ADMIN_URL . '/users/roles.php?role=' . $roleId);
            exit;
        }

        $db = Database::getInstance();
        $db->beginTransaction();

        try {
            // Delete current mapping for this role
            Database::execute("DELETE FROM `role_permissions` WHERE `role_id` = ?", [$roleId]);

            // Insert newly selected permissions
            $assignedCount = 0;
            foreach ($selectedPermIds as $pId) {
                if (isset($permSlugById[$pId])) {
                    Database::execute(
                        "INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (?, ?)",
                        [$roleId, $pId]
                    );
                    $assignedCount++;
                }
            }

            $db->commit();

            // Refresh current user session permissions immediately
            Auth::refreshPermissions();

            log_audit(
                'Role Permissions Updated',
                'Roles',
                $targetRole['slug'],
                "Updated permissions for role '{$targetRole['name']}'. Total {$assignedCount} capabilities enabled."
            );

            set_flash('success', "Role \"{$targetRole['name']}\" permissions updated successfully! ({$assignedCount} functions active). Changes are live immediately across all logged-in staff.");
            header('Location: ' . ADMIN_URL . '/users/roles.php?role=' . $roleId);
            exit;
        } catch (Exception $e) {
            $db->rollBack();
            set_flash('danger', 'Failed to update permissions: ' . $e->getMessage());
        }
    }

    // Action B: Apply Standard Recommended Preset
    elseif ($action === 'apply_preset') {
        $roleId = (int)($_POST['role_id'] ?? 0);
        $targetRole = Database::fetch("SELECT * FROM `roles` WHERE `id` = ?", [$roleId]);

        if (!$targetRole) {
            set_flash('danger', 'Role not found.');
            header('Location: ' . ADMIN_URL . '/users/roles.php');
            exit;
        }

        if ($targetRole['slug'] === 'super_admin' || $roleId === 1) {
            set_flash('warning', 'Super Administrator is already locked with full access.');
            header('Location: ' . ADMIN_URL . '/users/roles.php?role=' . $roleId);
            exit;
        }

        $preset = $rolePresets[$targetRole['slug']] ?? null;
        if (!$preset) {
            set_flash('danger', "No standard clinical preset defined for role {$targetRole['name']}.");
            header('Location: ' . ADMIN_URL . '/users/roles.php?role=' . $roleId);
            exit;
        }

        $db = Database::getInstance();
        $db->beginTransaction();

        try {
            Database::execute("DELETE FROM `role_permissions` WHERE `role_id` = ?", [$roleId]);

            $assignedCount = 0;
            if ($preset['permissions'] === 'all') {
                foreach ($allPermissions as $p) {
                    Database::execute(
                        "INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (?, ?)",
                        [$roleId, $p['id']]
                    );
                    $assignedCount++;
                }
            } else {
                foreach ($preset['permissions'] as $pSlug) {
                    if (isset($permIdBySlug[$pSlug])) {
                        Database::execute(
                            "INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (?, ?)",
                            [$roleId, $permIdBySlug[$pSlug]]
                        );
                        $assignedCount++;
                    }
                }
            }

            $db->commit();
            Auth::refreshPermissions();

            log_audit(
                'Role Preset Applied',
                'Roles',
                $targetRole['slug'],
                "Applied recommended clinical preset for '{$targetRole['name']}' ({$assignedCount} permissions)."
            );

            set_flash('success', "Standard recommended preset successfully applied to \"{$targetRole['name']}\"! ({$assignedCount} capabilities enabled).");
            header('Location: ' . ADMIN_URL . '/users/roles.php?role=' . $roleId);
            exit;
        } catch (Exception $e) {
            $db->rollBack();
            set_flash('danger', 'Failed to apply preset: ' . $e->getMessage());
        }
    }

    // Action C: Restore All Roles to Standard System Defaults (Master Safe Reset)
    elseif ($action === 'restore_all_defaults') {
        $db = Database::getInstance();
        $db->beginTransaction();

        try {
            foreach ($roles as $r) {
                $rId = (int)$r['id'];
                $rSlug = $r['slug'];

                if (!isset($rolePresets[$rSlug])) {
                    continue;
                }

                Database::execute("DELETE FROM `role_permissions` WHERE `role_id` = ?", [$rId]);

                $presetPerms = $rolePresets[$rSlug]['permissions'];
                if ($presetPerms === 'all') {
                    foreach ($allPermissions as $p) {
                        Database::execute(
                            "INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (?, ?)",
                            [$rId, $p['id']]
                        );
                    }
                } else {
                    foreach ($presetPerms as $pSlug) {
                        if (isset($permIdBySlug[$pSlug])) {
                            Database::execute(
                                "INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (?, ?)",
                                [$rId, $permIdBySlug[$pSlug]]
                            );
                        }
                    }
                }
            }

            $db->commit();
            Auth::refreshPermissions();

            log_audit(
                'All Roles Restored',
                'Roles',
                'SYSTEM_RESET',
                "Administrator {$_SESSION['user']['full_name']} restored all system roles to standard laboratory defaults."
            );

            set_flash('success', 'All laboratory staff roles have been safely restored to standard clinical best-practice defaults!');
            header('Location: ' . ADMIN_URL . '/users/roles.php');
            exit;
        } catch (Exception $e) {
            $db->rollBack();
            set_flash('danger', 'Failed to restore defaults: ' . $e->getMessage());
        }
    }
}

// 3. Prepare View Data
$activeTab = trim($_GET['tab'] ?? 'role'); // 'role' or 'matrix'
$selectedRoleId = (int)($_GET['role'] ?? 5); // Default to Receptionist (ID 5) or first non-super role

// Fetch existing permissions mapping
$rolePermsRaw = Database::fetchAll("SELECT `role_id`, `permission_id` FROM `role_permissions`");
$rolePermMap = [];
foreach ($rolePermsRaw as $rp) {
    $rolePermMap[$rp['role_id']][$rp['permission_id']] = true;
}

// Fetch active users count per role
$userCountsByRole = [];
$usersListByRole = [];
$allUsers = Database::fetchAll("SELECT id, role_id, full_name, username, email, designation, is_active FROM `users` ORDER BY full_name ASC");
foreach ($allUsers as $u) {
    $rId = (int)$u['role_id'];
    $userCountsByRole[$rId] = ($userCountsByRole[$rId] ?? 0) + 1;
    $usersListByRole[$rId][] = $u;
}

// Ensure selected role exists
$selectedRole = null;
foreach ($roles as $r) {
    if ((int)$r['id'] === $selectedRoleId) {
        $selectedRole = $r;
        break;
    }
}
if (!$selectedRole) {
    $selectedRole = $roles[0] ?? null;
    $selectedRoleId = (int)($selectedRole['id'] ?? 1);
}

// Group permissions by Clinical Module with clear icons and descriptions
$moduleMeta = [
    'Patients' => [
        'icon' => 'bi-people-fill text-primary',
        'title' => 'Patient Registration & Records',
        'hindi' => 'मरीज़ का नया पंजीकरण, प्रोफाइल अपडेट और मेडिकल हिस्ट्री देखना'
    ],
    'Bookings' => [
        'icon' => 'bi-calendar-check-fill text-warning',
        'title' => 'Quick Bookings & Appointments',
        'hindi' => 'ऑनलाइन व वॉक-इन टेस्ट बुकिंग और होम कलेक्शन डिस्पैच'
    ],
    'Orders' => [
        'icon' => 'bi-collection-fill text-info',
        'title' => 'Clinical Orders & Specimen Collection',
        'hindi' => 'टेस्ट ऑर्डर बुकिंग, बारकोड जनरेशन और सैंपल कलेक्शन ट्रैकिंग'
    ],
    'Results' => [
        'icon' => 'bi-pencil-square text-primary',
        'title' => 'Laboratory Results & Testing',
        'hindi' => 'एनालाइज़र रिजल्ट्स एंट्री और पैथोलॉजिस्ट को अप्रूवल के लिए फॉरवर्ड करना'
    ],
    'Reports' => [
        'icon' => 'bi-file-earmark-medical-fill text-danger',
        'title' => 'Pathology Reports & Medical Authorization',
        'hindi' => 'डायग्नोस्टिक वेरिफिकेशन, डिजिटल सिग्नेचर अप्रूवल, प्रिंट और व्हाट्सएप'
    ],
    'Billing' => [
        'icon' => 'bi-receipt text-success',
        'title' => 'Invoices, Billing & Payments',
        'hindi' => 'बिलिंग इनवॉइस, फीस कलेक्शन, रिसिप्ट प्रिंटिंग और पेमेंट ट्रैकिंग'
    ],
    'Accounting' => [
        'icon' => 'bi-wallet2 text-secondary',
        'title' => 'Cashbook, Expenses & Revenue',
        'hindi' => 'डेली कैशबुक, लैब खर्च और वित्तीय रेवेन्यू एनालिटिक्स'
    ],
    'Inventory' => [
        'icon' => 'bi-box-seam-fill text-warning',
        'title' => 'Reagents & Stock Consumables',
        'hindi' => 'रीएजेंट्स स्टॉक, लॉट/बैच एक्सपायरी ट्रैकिंग और यूसेज रिकॉर्ड्स'
    ],
    'Suppliers' => [
        'icon' => 'bi-truck text-dark',
        'title' => 'Suppliers & Purchases',
        'hindi' => 'सप्लायर मास्टर और रीएजेंट खरीद ऑर्डर्स'
    ],
    'Doctors' => [
        'icon' => 'bi-hospital text-info',
        'title' => 'Referring Doctors Directory',
        'hindi' => 'रेफरिंग डॉक्टर डायरेक्टरी और क्लिनिकल एसोसियेशन'
    ],
    'Reconciliation' => [
        'icon' => 'bi-graph-up-arrow text-primary',
        'title' => 'Doctor Reconciliation & Commissions',
        'hindi' => 'रेफरल एग्रीमेंट्स, कमीशन स्टेटमेंट और पेआउट्स'
    ],
    'Tests' => [
        'icon' => 'bi-list-check text-success',
        'title' => 'Test Master Catalog',
        'hindi' => 'टेस्ट प्राइसेज, नॉर्मल रेंज पैरामीटर्स और टेस्ट कैटलॉग प्रबंधन'
    ],
    'Staff' => [
        'icon' => 'bi-shield-lock-fill text-danger',
        'title' => 'Staff Directory & Access Roles',
        'hindi' => 'स्टाफ अकाउंट्स, पासवर्ड्स और रोल्स व परमिशन्स प्रबंधन'
    ],
    'Settings' => [
        'icon' => 'bi-gear-fill text-secondary',
        'title' => 'System & Lab Configuration',
        'hindi' => 'लैब नाम, एड्रेस, लेटरहेड और व्हाट्सएप एपीआई सेटिंग्स'
    ],
    'Audit' => [
        'icon' => 'bi-shield-shaded text-dark',
        'title' => 'Clinical Audit Trail',
        'hindi' => 'सिक्योरिटी ऑडिट लॉग्ज और संवेदनशील बदलावों का रिकॉर्ड'
    ],
    'Backup' => [
        'icon' => 'bi-database-down text-info',
        'title' => 'Database Backup & Recovery',
        'hindi' => 'डेटाबेस बैकअप डाउनलोड और सुरक्षित डेटा स्टोरेज'
    ]
];

// Descriptive Hindi + English translation notes for permissions
$permHelp = [
    'patients.view' => 'मरीज़ों की सूची और प्रोफाइल विवरण देखना',
    'patients.create' => 'नया पेशेंट रजिस्ट्रेशन फॉर्म भरना और Patient ID बनाना',
    'patients.edit' => 'पेशेंट का फोन, उम्र, लिंग और पता अपडेट करना',
    'patients.export' => 'पेशेंट लिस्ट CSV/Excel में डाउनलोड करना',
    'bookings.manage' => 'वेबसाइट से आई टेस्ट बुकिंग्स कन्फर्म व असाइन करना',
    'orders.create' => 'नया टेस्ट ऑर्डर (Accession) बुक करना और इनवॉइस जोड़ना',
    'orders.view' => 'टेस्ट ऑर्डर्स की सूची और स्टेटस देखना',
    'orders.sample_collect' => 'सैंपल कलेक्ट मार्क करना और बारकोड ट्रैक करना',
    'results.entry' => 'टेस्ट रिजल्ट वैल्यूज डालना और पैथोलॉजिस्ट को भेजना',
    'reports.view' => 'पैथोलॉजी रिपोर्ट्स का स्टेटस देखना और खोलना',
    'reports.review' => 'पैथोलॉजिस्ट द्वारा क्लिनिकल डेटा और रेंज वेरिफाई करना',
    'reports.approve' => 'रिपोर्ट को डिजिटली साइन और अंतिम रूप से अप्रूव करना',
    'reports.amend' => 'अप्रूव्ड रिपोर्ट में संशोधन (Amendment) रिकॉर्ड करना',
    'reports.print' => 'फाइनल रिपोर्ट PDF प्रिंट करना और पेशेंट को देना',
    'reports.whatsapp' => 'पेशेंट के मोबाइल पर व्हाट्सएप से रिपोर्ट सेंड करना',
    'billing.manage' => 'मरीज़ का बिल बनाना, कैश/UPI पेमेंट लेना और रसीद देना',
    'billing.refund' => 'पेमेंट रिफंड या कैंसलेशन अधिकृत करना',
    'accounting.manage' => 'डेली कैशबुक और लैब के खर्चे (Expenses) दर्ज करना',
    'inventory.manage' => 'रीएजेंट्स और कंज्यूमेबल्स का स्टॉक देखना व संभालना',
    'inventory.adjust' => 'स्टॉक एडजस्टमेंट और एक्सपायरी रीएजेंट हटाना',
    'suppliers.manage' => 'रीएजेंट सप्लायर्स और खरीद बिल मैनेज करना',
    'doctors.view' => 'रेफरिंग डॉक्टर्स की लिस्ट देखना (ऑर्डर में चुनने हेतु)',
    'doctors.create' => 'नया डॉक्टर मास्टर रिकॉर्ड जोड़ना',
    'doctors.edit' => 'डॉक्टर का विवरण और कमीशन प्रतिशत बदलना',
    'reconciliation.view' => 'डॉक्टर कमीशन और फाइनेंशियल रीकंसीलिएशन देखना',
    'reconciliation.create' => 'कमीशन स्टेटमेंट ड्राफ्ट बनाना',
    'agreements.review' => 'डॉक्टर शेयरिंग एग्रीमेंट्स रिव्यू करना',
    'reconciliation.approve' => 'डॉक्टर पेआउट्स अप्रूव करना',
    'reconciliation.pay' => 'डॉक्टर को पेमेंट भुगतान मार्क करना',
    'reconciliation.reverse' => 'लेनदेन रिवर्स करना',
    'reconciliation.export' => 'अकाउंटिंग रिपोर्ट एक्सपोर्ट करना',
    'tests.manage' => 'टेस्ट मास्टर, रेट लिस्ट और नॉर्मल रेंज बदलना',
    'users.manage' => 'स्टाफ अकाउंट्स, रोल्स और परमिशन्स कंट्रोल करना',
    'settings.manage' => 'लैब का नाम, फोन, लेटरहेड और सिस्टम सेटिंग्स बदलना',
    'backup.manage' => 'डेटाबेस बैकअप लेना और डाउनलोड करना',
    'audit.view' => 'सिस्टम एक्टिविटी और सिक्योरिटी ऑडिट ट्रेल देखना'
];

$modules = [];
foreach ($allPermissions as $p) {
    $modules[$p['module']][] = $p;
}

require_once dirname(__DIR__) . '/includes/header.php';
?>

<!-- Header & Quick Navigation -->
<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
  <div>
    <h2 class="h4 fw-bold mb-1">
      <i class="bi bi-shield-lock-fill text-warning me-2"></i> Role-Based Access Control (RBAC)
    </h2>
    <p class="text-muted small mb-0">
      Safely configure and restrict operational panel capabilities for each staff role according to their job duties.
    </p>
  </div>
  <div class="d-flex gap-2">
    <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#restoreDefaultsModal">
      <i class="bi bi-arrow-counterclockwise me-1"></i> Reset All Roles to Safe Defaults
    </button>
    <a href="<?= ADMIN_URL ?>/users/index.php" class="btn btn-outline-secondary btn-sm">
      <i class="bi bi-people me-1"></i> Staff Directory
    </a>
  </div>
</div>

<!-- Navigation Tabs: Role-Specific Control vs All-Roles Matrix -->
<ul class="nav nav-pills mb-4 gap-2 bg-light p-2 rounded border">
  <li class="nav-item">
    <a class="nav-link <?= $activeTab === 'role' ? 'active bg-primary text-white' : 'text-dark' ?> fw-semibold" href="?tab=role&role=<?= $selectedRoleId ?>">
      <i class="bi bi-person-gear me-1"></i> Role Permissions Manager
    </a>
  </li>
  <li class="nav-item">
    <a class="nav-link <?= $activeTab === 'matrix' ? 'active bg-primary text-white' : 'text-dark' ?> fw-semibold" href="?tab=matrix">
      <i class="bi bi-grid-3x3 me-1"></i> Full Comparison Matrix (Audit View)
    </a>
  </li>
</ul>

<?php if ($activeTab === 'matrix'): ?>
  <!-- ===================================================================== -->
  <!-- FULL COMPARISON MATRIX TAB (AUDIT VIEW) -->
  <!-- ===================================================================== -->
  <div class="admin-card">
    <div class="admin-card-header bg-light d-flex justify-content-between align-items-center">
      <div>
        <h3 class="admin-card-title mb-0">
          <i class="bi bi-shield-check text-success me-1"></i> Cross-Role Capabilities Comparison Grid
        </h3>
        <span class="text-muted small">Server-enforced matrix showing all permissions across all 8 laboratory roles</span>
      </div>
      <a href="?tab=role&role=<?= $selectedRoleId ?>" class="btn btn-sm btn-primary-lm">
        <i class="bi bi-pencil-square me-1"></i> Customize Permissions
      </a>
    </div>
    <div class="admin-card-body p-0">
      <div class="table-responsive">
        <table class="table table-bordered table-hover align-middle mb-0 small">
          <thead class="table-light">
            <tr>
              <th style="width: 320px;" class="ps-3">Clinical Module & Permission</th>
              <?php foreach ($roles as $r): ?>
                <th class="text-center" style="min-width: 110px;">
                  <a href="?tab=role&role=<?= $r['id'] ?>" class="text-decoration-none d-block">
                    <div class="fw-bold text-dark"><?= e($r['name']) ?></div>
                    <span class="badge bg-secondary-subtle text-secondary font-monospace" style="font-size:0.65rem;"><?= e($r['slug']) ?></span>
                  </a>
                </th>
              <?php endforeach; ?>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($modules as $modName => $perms): ?>
              <?php $meta = $moduleMeta[$modName] ?? ['icon' => 'bi-folder-fill text-primary', 'title' => $modName . ' Module', 'hindi' => '']; ?>
              <tr class="table-secondary bg-opacity-50">
                <td colspan="<?= count($roles) + 1 ?>" class="fw-bold text-uppercase py-2 ps-3" style="font-size: 0.75rem; letter-spacing: 0.05em;">
                  <i class="bi <?= $meta['icon'] ?> me-1"></i> <?= e($modName) ?> Module &mdash; <?= e($meta['title']) ?>
                  <?php if (!empty($meta['hindi'])): ?>
                    <span class="text-muted fw-normal text-capitalize ms-2" style="font-size:0.7rem;">(<?= e($meta['hindi']) ?>)</span>
                  <?php endif; ?>
                </td>
              </tr>
              <?php foreach ($perms as $p): ?>
                <tr>
                  <td class="ps-3">
                    <div class="fw-semibold text-dark"><?= e($p['name']) ?></div>
                    <span class="text-muted font-monospace" style="font-size: 0.7rem;"><?= e($p['slug']) ?></span>
                    <?php if (!empty($permHelp[$p['slug']])): ?>
                      <div class="text-muted" style="font-size: 0.68rem;"><?= e($permHelp[$p['slug']]) ?></div>
                    <?php endif; ?>
                  </td>
                  <?php foreach ($roles as $r): ?>
                    <?php 
                    $hasPerm = !empty($rolePermMap[$r['id']][$p['id']]) || $r['slug'] === 'super_admin'; 
                    ?>
                    <td class="text-center">
                      <?php if ($hasPerm): ?>
                        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 p-2 rounded-circle" title="Allowed">
                          <i class="bi bi-check-lg fw-bold"></i>
                        </span>
                      <?php else: ?>
                        <span class="text-muted opacity-50" title="Restricted"><i class="bi bi-dash"></i></span>
                      <?php endif; ?>
                    </td>
                  <?php endforeach; ?>
                </tr>
              <?php endforeach; ?>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

<?php else: ?>
  <!-- ===================================================================== -->
  <!-- ROLE PERMISSIONS MANAGER TAB (INTERACTIVE EDITOR) -->
  <!-- ===================================================================== -->

  <!-- Role Selector Pills -->
  <div class="d-flex flex-wrap gap-2 mb-4">
    <?php foreach ($roles as $r): ?>
      <?php 
      $isActive = (int)$r['id'] === $selectedRoleId;
      $countStaff = $userCountsByRole[$r['id']] ?? 0;
      $assignedPermCount = count($rolePermMap[$r['id']] ?? []);
      if ($r['slug'] === 'super_admin') {
          $assignedPermCount = count($allPermissions);
      }
      $btnClass = $isActive ? 'btn-primary' : 'btn-outline-secondary bg-white';
      
      // Icon mapping for primary roles
      $roleIcon = match ($r['slug']) {
          'receptionist' => 'bi-person-badge',
          'technician' => 'bi-eyedropper',
          'pathologist' => 'bi-clipboard2-pulse',
          'super_admin' => 'bi-shield-shaded',
          'lab_admin' => 'bi-hospital',
          'billing' => 'bi-receipt',
          'inventory_manager' => 'bi-box-seam',
          'accountant' => 'bi-cash-coin',
          default => 'bi-person'
      };
      ?>
      <a href="?tab=role&role=<?= $r['id'] ?>" class="btn <?= $btnClass ?> text-start d-flex align-items-center gap-2 px-3 py-2 shadow-sm">
        <i class="bi <?= $roleIcon ?> fs-5"></i>
        <div>
          <div class="fw-bold small"><?= e($r['name']) ?></div>
          <div class="small <?= $isActive ? 'text-white-50' : 'text-muted' ?>" style="font-size: 0.7rem;">
            <?= $countStaff ?> Staff • <?= $assignedPermCount ?> Capabilities
          </div>
        </div>
      </a>
    <?php endforeach; ?>
  </div>

  <!-- Role Focus Card Banner -->
  <div class="admin-card mb-4 border-start border-4 border-primary">
    <div class="admin-card-body p-3 p-md-4">
      <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
        <div>
          <div class="d-flex align-items-center gap-2 mb-1">
            <h3 class="h5 fw-bold mb-0 text-dark"><?= e($selectedRole['name']) ?></h3>
            <span class="badge bg-dark font-monospace"><?= e($selectedRole['slug']) ?></span>
            <?php if ($selectedRole['slug'] === 'super_admin'): ?>
              <span class="badge bg-danger"><i class="bi bi-shield-lock me-1"></i> Root Administrator (Immutable)</span>
            <?php else: ?>
              <span class="badge bg-success-subtle text-success-emphasis border border-success"><i class="bi bi-sliders me-1"></i> Customizable Role</span>
            <?php endif; ?>
          </div>
          <p class="text-muted small mb-2">
            <?= e($selectedRole['description'] ?: 'Staff member role governing laboratory functions and security permissions.') ?>
          </p>

          <!-- Assigned Personnel -->
          <div class="d-flex flex-wrap align-items-center gap-2 pt-1">
            <span class="text-muted small fw-semibold"><i class="bi bi-people me-1"></i> Assigned Staff:</span>
            <?php $staffMembers = $usersListByRole[$selectedRole['id']] ?? []; ?>
            <?php if (empty($staffMembers)): ?>
              <span class="badge bg-light text-muted border">No staff members currently assigned</span>
            <?php else: ?>
              <?php foreach ($staffMembers as $sm): ?>
                <span class="badge bg-light text-dark border d-inline-flex align-items-center gap-1">
                  <i class="bi bi-person-circle text-primary"></i> <?= e($sm['full_name']) ?>
                  <?php if (!$sm['is_active']): ?>
                    <span class="text-danger small">(Inactive)</span>
                  <?php endif; ?>
                </span>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>

        <!-- Quick Preset Action -->
        <?php if ($selectedRole['slug'] !== 'super_admin'): ?>
          <div class="text-md-end">
            <form method="POST" action="" onsubmit="return confirm('Apply standard recommended clinical preset to <?= e(addslashes($selectedRole['name'])) ?>? This will configure standard permissions.');">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="apply_preset">
              <input type="hidden" name="role_id" value="<?= $selectedRole['id'] ?>">
              <button type="submit" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-stars me-1"></i> Apply Recommended Preset
              </button>
            </form>
            <div class="text-muted small mt-1" style="font-size: 0.72rem;">
              Resets to standard clinical best-practice duties
            </div>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <?php if ($selectedRole['slug'] === 'super_admin'): ?>
    <!-- Super Admin Protected Alert -->
    <div class="alert alert-info d-flex align-items-center gap-3 mb-4 shadow-sm">
      <i class="bi bi-shield-check fs-2 text-primary"></i>
      <div>
        <div class="fw-bold">Super Administrator Access is System Protected</div>
        <div class="small">
          To ensure laboratory operational safety and prevent accidental lockout, the Super Administrator possesses all 36 permissions permanently.
          To configure specific restricted access, use <strong>Receptionist</strong>, <strong>Technician</strong>, <strong>Pathologist</strong>, or other operational roles above.
        </div>
      </div>
    </div>
  <?php endif; ?>

  <!-- Permissions Form -->
  <form method="POST" action="" id="permissionsForm">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="update_role_permissions">
    <input type="hidden" name="role_id" value="<?= $selectedRole['id'] ?>">

    <div class="d-flex justify-content-between align-items-center mb-3">
      <div>
        <h4 class="h6 fw-bold mb-0 text-dark">
          <i class="bi bi-check2-square text-primary me-1"></i> Available Capabilities & Functions
        </h4>
        <span class="text-muted small">Check or uncheck functions to control what this role can perform on the portal.</span>
      </div>
      <?php if ($selectedRole['slug'] !== 'super_admin'): ?>
        <div class="d-flex gap-2">
          <button type="button" class="btn btn-outline-secondary btn-sm" onclick="toggleAllCheckboxes(true)">
            <i class="bi bi-check-all me-1"></i> Select All
          </button>
          <button type="button" class="btn btn-outline-secondary btn-sm" onclick="toggleAllCheckboxes(false)">
            <i class="bi bi-x-lg me-1"></i> Deselect All
          </button>
        </div>
      <?php endif; ?>
    </div>

    <!-- Modules Grid -->
    <div class="row g-3">
      <?php foreach ($modules as $modName => $perms): ?>
        <?php 
        $meta = $moduleMeta[$modName] ?? ['icon' => 'bi-folder-fill text-primary', 'title' => $modName . ' Module', 'hindi' => ''];
        $modPermIds = array_column($perms, 'id');
        $modCheckedCount = 0;
        foreach ($modPermIds as $pid) {
            if (!empty($rolePermMap[$selectedRole['id']][$pid]) || $selectedRole['slug'] === 'super_admin') {
                $modCheckedCount++;
            }
        }
        $isAllChecked = ($modCheckedCount === count($perms));
        ?>
        <div class="col-lg-6">
          <div class="admin-card h-100 mb-0 shadow-sm">
            <div class="admin-card-header bg-light d-flex justify-content-between align-items-center py-2 px-3">
              <div class="d-flex align-items-center gap-2">
                <i class="bi <?= $meta['icon'] ?> fs-5"></i>
                <div>
                  <h4 class="h6 fw-bold mb-0 text-dark"><?= e($modName) ?></h4>
                  <div class="text-muted small" style="font-size: 0.72rem;"><?= e($meta['title']) ?></div>
                </div>
              </div>
              <div class="d-flex align-items-center gap-2">
                <span class="badge bg-secondary-subtle text-secondary module-count-badge" id="count_<?= e($modName) ?>">
                  <?= $modCheckedCount ?> / <?= count($perms) ?>
                </span>
                <?php if ($selectedRole['slug'] !== 'super_admin'): ?>
                  <button type="button" class="btn btn-link btn-sm text-decoration-none p-0 text-primary" style="font-size:0.75rem;" onclick="toggleModuleGroup('<?= e($modName) ?>')">
                    Toggle
                  </button>
                <?php endif; ?>
              </div>
            </div>
            <div class="admin-card-body p-3">
              <?php if (!empty($meta['hindi'])): ?>
                <div class="text-secondary small mb-3 p-2 bg-light rounded border-start border-3 border-info" style="font-size: 0.75rem;">
                  <i class="bi bi-info-circle me-1 text-info"></i> <?= e($meta['hindi']) ?>
                </div>
              <?php endif; ?>

              <div class="vstack gap-2">
                <?php foreach ($perms as $p): ?>
                  <?php 
                  $isChecked = !empty($rolePermMap[$selectedRole['id']][$p['id']]) || $selectedRole['slug'] === 'super_admin'; 
                  $isDisabled = ($selectedRole['slug'] === 'super_admin');
                  ?>
                  <div class="form-check p-2 rounded hover-bg border border-light">
                    <input 
                      class="form-check-input perm-checkbox perm-group-<?= e($modName) ?>" 
                      type="checkbox" 
                      name="permissions[]" 
                      value="<?= $p['id'] ?>" 
                      id="perm_<?= $p['id'] ?>"
                      data-module="<?= e($modName) ?>"
                      <?= $isChecked ? 'checked' : '' ?>
                      <?= $isDisabled ? 'disabled' : '' ?>
                      onchange="updateModuleCounter('<?= e($modName) ?>')"
                    >
                    <label class="form-check-label d-block ms-1 cursor-pointer" for="perm_<?= $p['id'] ?>">
                      <div class="d-flex justify-content-between align-items-center">
                        <span class="fw-semibold text-dark small"><?= e($p['name']) ?></span>
                        <span class="badge bg-light text-muted border font-monospace" style="font-size: 0.65rem;"><?= e($p['slug']) ?></span>
                      </div>
                      <?php if (!empty($permHelp[$p['slug']])): ?>
                        <div class="text-muted" style="font-size: 0.72rem; line-height: 1.3;">
                          <?= e($permHelp[$p['slug']]) ?>
                        </div>
                      <?php endif; ?>
                    </label>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- Sticky Bottom Save Bar -->
    <?php if ($selectedRole['slug'] !== 'super_admin'): ?>
      <div class="sticky-bottom mt-4 p-3 bg-white border rounded shadow d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div class="small text-muted">
          <i class="bi bi-shield-check text-success me-1"></i> Changes will immediately take effect for all active <strong><?= e($selectedRole['name']) ?></strong> sessions.
        </div>
        <div class="d-flex gap-2">
          <a href="?tab=role&role=<?= $selectedRoleId ?>" class="btn btn-light btn-sm">Discard Changes</a>
          <button type="submit" class="btn btn-save btn-sm px-4">
            <i class="bi bi-check-circle-fill me-1"></i> Save & Apply Role Permissions
          </button>
        </div>
      </div>
    <?php endif; ?>
  </form>

<?php endif; ?>

<!-- Master Reset Confirmation Modal -->
<div class="modal fade" id="restoreDefaultsModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" action="">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="restore_all_defaults">
        <div class="modal-header bg-danger text-white">
          <h5 class="modal-title h6 fw-bold">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> Restore All Roles to Standard Defaults?
          </h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body small">
          <p>
            This action will safely reset permissions for <strong>all laboratory roles</strong> back to clinical best-practice standard configuration:
          </p>
          <ul class="mb-3">
            <li><strong>Receptionist:</strong> Patient Registration, Quick Bookings, Order Accession, Billing & Payment, Report Status, Patient Report Delivery (Print & WhatsApp).</li>
            <li><strong>Laboratory Technician:</strong> Sample Collection, Accession Tracking, Result Entry, Reagents & Consumables, and Pathologist Forwarding.</li>
            <li><strong>Consultant Pathologist:</strong> Clinical Verification, Range Review, Report Approval & Digital Sign-off.</li>
            <li><strong>Administrator:</strong> Full unrestricted laboratory operations management.</li>
          </ul>
          <div class="alert alert-warning mb-0 p-2 small">
            <i class="bi bi-shield-lock me-1"></i> Active staff sessions will immediately reload the restored permissions.
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-danger btn-sm">
            <i class="bi bi-arrow-counterclockwise me-1"></i> Yes, Restore Standard Defaults
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function toggleAllCheckboxes(checked) {
  document.querySelectorAll('.perm-checkbox:not(:disabled)').forEach(cb => {
    cb.checked = checked;
  });
  updateAllModuleCounters();
}

function toggleModuleGroup(modName) {
  const cbs = document.querySelectorAll('.perm-group-' + modName + ':not(:disabled)');
  if (cbs.length === 0) return;
  const anyUnchecked = Array.from(cbs).some(cb => !cb.checked);
  cbs.forEach(cb => cb.checked = anyUnchecked);
  updateModuleCounter(modName);
}

function updateModuleCounter(modName) {
  const cbs = document.querySelectorAll('.perm-group-' + modName);
  const checked = Array.from(cbs).filter(cb => cb.checked).length;
  const badge = document.getElementById('count_' + modName);
  if (badge) {
    badge.textContent = checked + ' / ' + cbs.length;
    if (checked === cbs.length) {
      badge.className = 'badge bg-success text-white module-count-badge';
    } else if (checked === 0) {
      badge.className = 'badge bg-secondary-subtle text-secondary module-count-badge';
    } else {
      badge.className = 'badge bg-primary text-white module-count-badge';
    }
  }
}

function updateAllModuleCounters() {
  const modules = <?= json_encode(array_keys($modules)) ?>;
  modules.forEach(m => updateModuleCounter(m));
}

document.addEventListener('DOMContentLoaded', function() {
  updateAllModuleCounters();
});
</script>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>

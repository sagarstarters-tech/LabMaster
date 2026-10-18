<?php
/**
 * LabMaster - Admin Authentication Guard & Context Provider
 */

declare(strict_types=1);

require_once dirname(dirname(__DIR__)) . '/includes/auth.php';

// Enforce login
Auth::requireLogin();

// Dynamically refresh active permissions from database
Auth::refreshPermissions();

$currentUser = Auth::user();
$currentRole = Auth::role();

// Fetch badge counts dynamically from database
try {
    $pendingBookingsCount = (int)(Database::fetch("SELECT COUNT(*) AS c FROM `bookings` WHERE `status` = 'New'")['c'] ?? 0);
    $pendingReportsCount = (int)(Database::fetch("SELECT COUNT(*) AS c FROM `reports` WHERE `report_status` = 'Pending Approval'")['c'] ?? 0);
    $ordersProcessingCount = (int)(Database::fetch("SELECT COUNT(*) AS c FROM `test_orders` WHERE `status` IN ('Order Created', 'Sample Collected', 'Processing')")['c'] ?? 0);
    $lowStockCount = (int)(Database::fetch("SELECT COUNT(*) AS c FROM `inventory_items` WHERE `current_stock` <= `minimum_stock` AND `is_active` = 1")['c'] ?? 0);
    
    // Near expiry reagents (within 60 days)
    $nearExpiryCount = (int)(Database::fetch(
        "SELECT COUNT(*) AS c FROM `inventory_batches` 
         WHERE `status` IN ('Active', 'Near Expiry') 
           AND `expiry_date` <= DATE_ADD(CURDATE(), INTERVAL 60 DAY) 
           AND `current_quantity` > 0"
    )['c'] ?? 0);

    // Unread notifications
    $unreadNotifications = Database::fetchAll(
        "SELECT * FROM `notifications` WHERE `is_read` = 0 ORDER BY `id` DESC LIMIT 5"
    );
    $unreadNotifCount = count($unreadNotifications);
} catch (Exception $e) {
    $pendingBookingsCount = 0;
    $pendingReportsCount = 0;
    $ordersProcessingCount = 0;
    $lowStockCount = 0;
    $nearExpiryCount = 0;
    $unreadNotifications = [];
    $unreadNotifCount = 0;
}

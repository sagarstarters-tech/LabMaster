<?php
/**
 * LabMaster - Core Helper Functions
 * Security, sanitization, CSRF, clinical range evaluation, audit logging, and formatting
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';

/**
 * Safe HTML escaping
 */
function e(?string $value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * Trim and sanitize string
 */
function sanitize(?string $value): string
{
    return trim(strip_tags((string)($value ?? '')));
}

/**
 * Generate or get CSRF token
 */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Render hidden CSRF form field
 */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

/**
 * Verify CSRF token
 */
function verify_csrf(?string $token = null): bool
{
    $token = $token ?? $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], (string)$token);
}

/**
 * Require valid CSRF or die
 */
function require_csrf(): void
{
    if (!verify_csrf()) {
        http_response_code(403);
        die('Invalid or expired CSRF security token. Please refresh the page and try again.');
    }
}

/**
 * Generate Unique Patient ID (e.g. LM-P-2026-0006)
 */
function generate_patient_id(): string
{
    $prefix = Settings::get('patient_prefix', 'LM-P-');
    $year = date('Y');
    $likePattern = $prefix . $year . '-%';

    $row = Database::fetch(
        "SELECT `patient_id` FROM `patients` WHERE `patient_id` LIKE ? ORDER BY `id` DESC LIMIT 1",
        [$likePattern]
    );

    if ($row && preg_match('/-(\d+)$/', $row['patient_id'], $matches)) {
        $next = (int)$matches[1] + 1;
    } else {
        $count = (int)(Database::fetch("SELECT COUNT(*) as c FROM `patients`")['c'] ?? 0);
        $next = $count + 1;
    }

    return sprintf('%s%s-%04d', $prefix, $year, $next);
}

/**
 * Generate Accession / Order Number (e.g. LM-ACC-2026-0004)
 */
function generate_accession_number(): string
{
    $prefix = Settings::get('order_prefix', 'LM-ACC-');
    $year = date('Y');
    $likePattern = $prefix . $year . '-%';

    $row = Database::fetch(
        "SELECT `accession_number` FROM `test_orders` WHERE `accession_number` LIKE ? ORDER BY `id` DESC LIMIT 1",
        [$likePattern]
    );

    if ($row && preg_match('/-(\d+)$/', $row['accession_number'], $matches)) {
        $next = (int)$matches[1] + 1;
    } else {
        $count = (int)(Database::fetch("SELECT COUNT(*) as c FROM `test_orders`")['c'] ?? 0);
        $next = $count + 1;
    }

    return sprintf('%s%s-%04d', $prefix, $year, $next);
}

/**
 * Generate Pathology Report Number (e.g. LM-REP-2026-0003)
 */
function generate_report_number(): string
{
    $prefix = Settings::get('report_prefix', 'LM-REP-');
    $year = date('Y');
    $likePattern = $prefix . $year . '-%';

    $row = Database::fetch(
        "SELECT `report_number` FROM `reports` WHERE `report_number` LIKE ? ORDER BY `id` DESC LIMIT 1",
        [$likePattern]
    );

    if ($row && preg_match('/-(\d+)$/', $row['report_number'], $matches)) {
        $next = (int)$matches[1] + 1;
    } else {
        $count = (int)(Database::fetch("SELECT COUNT(*) as c FROM `reports`")['c'] ?? 0);
        $next = $count + 1;
    }

    return sprintf('%s%s-%04d', $prefix, $year, $next);
}

/**
 * Generate Invoice Number (e.g. LM-INV-2026-0004)
 */
function generate_invoice_number(): string
{
    $prefix = Settings::get('invoice_prefix', 'LM-INV-');
    $year = date('Y');
    $likePattern = $prefix . $year . '-%';

    $row = Database::fetch(
        "SELECT `invoice_number` FROM `invoices` WHERE `invoice_number` LIKE ? ORDER BY `id` DESC LIMIT 1",
        [$likePattern]
    );

    if ($row && preg_match('/-(\d+)$/', $row['invoice_number'], $matches)) {
        $next = (int)$matches[1] + 1;
    } else {
        $count = (int)(Database::fetch("SELECT COUNT(*) as c FROM `invoices`")['c'] ?? 0);
        $next = $count + 1;
    }

    return sprintf('%s%s-%04d', $prefix, $year, $next);
}

/**
 * Generate Payment Receipt Number (e.g. LM-REC-2026-0004)
 */
function generate_receipt_number(): string
{
    $prefix = Settings::get('receipt_prefix', 'LM-REC-');
    $year = date('Y');
    $likePattern = $prefix . $year . '-%';

    $row = Database::fetch(
        "SELECT `receipt_number` FROM `payments` WHERE `receipt_number` LIKE ? ORDER BY `id` DESC LIMIT 1",
        [$likePattern]
    );

    if ($row && preg_match('/-(\d+)$/', $row['receipt_number'], $matches)) {
        $next = (int)$matches[1] + 1;
    } else {
        $count = (int)(Database::fetch("SELECT COUNT(*) as c FROM `payments`")['c'] ?? 0);
        $next = $count + 1;
    }

    return sprintf('%s%s-%04d', $prefix, $year, $next);
}

/**
 * Generate Quick Booking Reference (e.g. LM-BK-2026-0004)
 */
function generate_booking_ref(): string
{
    $prefix = Settings::get('booking_prefix', 'LM-BK-');
    $year = date('Y');
    $likePattern = $prefix . $year . '-%';

    $row = Database::fetch(
        "SELECT `booking_reference` FROM `bookings` WHERE `booking_reference` LIKE ? ORDER BY `id` DESC LIMIT 1",
        [$likePattern]
    );

    if ($row && preg_match('/-(\d+)$/', $row['booking_reference'], $matches)) {
        $next = (int)$matches[1] + 1;
    } else {
        $count = (int)(Database::fetch("SELECT COUNT(*) as c FROM `bookings`")['c'] ?? 0);
        $next = $count + 1;
    }

    return sprintf('%s%s-%04d', $prefix, $year, $next);
}

/**
 * Generate Expense Number (e.g. LM-EXP-2026-0004)
 */
function generate_expense_number(): string
{
    $year = date('Y');
    $likePattern = 'LM-EXP-' . $year . '-%';

    $row = Database::fetch(
        "SELECT `expense_number` FROM `expenses` WHERE `expense_number` LIKE ? ORDER BY `id` DESC LIMIT 1",
        [$likePattern]
    );

    if ($row && preg_match('/-(\d+)$/', $row['expense_number'], $matches)) {
        $next = (int)$matches[1] + 1;
    } else {
        $count = (int)(Database::fetch("SELECT COUNT(*) as c FROM `expenses`")['c'] ?? 0);
        $next = $count + 1;
    }

    return sprintf('LM-EXP-%s-%04d', $year, $next);
}

/**
 * Generate Purchase Number (e.g. LM-PUR-2026-0002)
 */
function generate_purchase_number(): string
{
    $year = date('Y');
    $likePattern = 'LM-PUR-' . $year . '-%';

    $row = Database::fetch(
        "SELECT `purchase_number` FROM `purchases` WHERE `purchase_number` LIKE ? ORDER BY `id` DESC LIMIT 1",
        [$likePattern]
    );

    if ($row && preg_match('/-(\d+)$/', $row['purchase_number'], $matches)) {
        $next = (int)$matches[1] + 1;
    } else {
        $count = (int)(Database::fetch("SELECT COUNT(*) as c FROM `purchases`")['c'] ?? 0);
        $next = $count + 1;
    }

    return sprintf('LM-PUR-%s-%04d', $year, $next);
}

/**
 * Clinical Range Evaluation Engine
 * Evaluates observed parameter value against gender & age matched reference intervals
 * Returns: ['flag' => 'NORMAL'|'LOW'|'HIGH'|'CRITICAL_LOW'|'CRITICAL_HIGH'|'ABNORMAL', 'interval' => string, 'display_class' => string]
 */
function evaluate_result_flag(int $parameterId, ?string $value, string $gender = 'All', int $age = 30, string $ageUnit = 'Years'): array
{
    if ($value === null || trim($value) === '') {
        return [
            'flag' => 'NORMAL',
            'interval' => 'N/A',
            'display_class' => 'text-muted'
        ];
    }

    $trimmedVal = trim($value);

    // Fetch parameter info including critical cutoffs
    $param = Database::fetch(
        "SELECT * FROM `test_parameters` WHERE `id` = ?",
        [$parameterId]
    );

    if (!$param) {
        return ['flag' => 'NORMAL', 'interval' => '', 'display_class' => 'text-dark'];
    }

    // Fetch matching reference interval: priority to specific gender match, else 'All'
    $intervals = Database::fetchAll(
        "SELECT * FROM `reference_intervals` 
         WHERE `parameter_id` = ? 
           AND (`gender` = ? OR `gender` = 'All')
           AND `age_min` <= ? AND `age_max` >= ?
         ORDER BY (CASE WHEN `gender` = ? THEN 1 ELSE 2 END) ASC
         LIMIT 1",
        [$parameterId, $gender, $age, $age, $gender]
    );

    $interval = $intervals[0] ?? null;
    $displayInterval = $interval['display_interval'] ?? ($param['unit'] ? 'Standard' : 'Normal');

    // For numeric evaluation
    if (is_numeric($trimmedVal) && ($param['result_type'] === 'numeric' || $param['result_type'] === 'formula')) {
        $num = (float)$trimmedVal;

        // Check critical cutoffs first
        if ($param['critical_low'] !== null && $num < (float)$param['critical_low']) {
            return [
                'flag' => 'CRITICAL_LOW',
                'interval' => $displayInterval,
                'display_class' => 'badge bg-danger text-white pulse-alert'
            ];
        }

        if ($param['critical_high'] !== null && $num > (float)$param['critical_high']) {
            return [
                'flag' => 'CRITICAL_HIGH',
                'interval' => $displayInterval,
                'display_class' => 'badge bg-danger text-white pulse-alert'
            ];
        }

        // Check standard reference interval
        if ($interval) {
            $minVal = $interval['min_val'] !== null ? (float)$interval['min_val'] : null;
            $maxVal = $interval['max_val'] !== null ? (float)$interval['max_val'] : null;

            if ($minVal !== null && $num < $minVal) {
                return [
                    'flag' => 'LOW',
                    'interval' => $displayInterval,
                    'display_class' => 'badge bg-warning text-dark fw-bold'
                ];
            }

            if ($maxVal !== null && $num > $maxVal) {
                return [
                    'flag' => 'HIGH',
                    'interval' => $displayInterval,
                    'display_class' => 'badge bg-orange text-white fw-bold'
                ];
            }
        }

        return [
            'flag' => 'NORMAL',
            'interval' => $displayInterval,
            'display_class' => 'badge bg-success-subtle text-success-emphasis'
        ];
    }

    // For qualitative / dropdown / text tests (e.g. REACTIVE, POSITIVE, Nil)
    $upperVal = strtoupper($trimmedVal);
    if (str_contains($upperVal, 'REACTIVE') && !str_contains($upperVal, 'NON-REACTIVE')) {
        return [
            'flag' => 'ABNORMAL',
            'interval' => $displayInterval,
            'display_class' => 'badge bg-danger text-white fw-bold'
        ];
    }
    if (str_contains($upperVal, 'POSITIVE') && !str_contains($upperVal, 'NEGATIVE')) {
        return [
            'flag' => 'ABNORMAL',
            'interval' => $displayInterval,
            'display_class' => 'badge bg-danger text-white fw-bold'
        ];
    }
    if (str_contains($upperVal, '1+') || str_contains($upperVal, '2+') || str_contains($upperVal, '3+') || str_contains($upperVal, '4+')) {
        return [
            'flag' => 'ABNORMAL',
            'interval' => $displayInterval,
            'display_class' => 'badge bg-warning text-dark fw-bold'
        ];
    }

    return [
        'flag' => 'NORMAL',
        'interval' => $displayInterval,
        'display_class' => 'badge bg-success-subtle text-success-emphasis'
    ];
}

/**
 * Log System Audit Trail
 */
function log_audit(string $action, string $module, ?string $recordId = null, ?string $details = null): void
{
    try {
        $userId = $_SESSION['user']['id'] ?? null;
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        Database::execute(
            "INSERT INTO `audit_logs` (`user_id`, `action`, `module`, `record_id`, `details`, `ip_address`)
             VALUES (?, ?, ?, ?, ?, ?)",
            [$userId, $action, $module, $recordId, $details, $ip]
        );
    } catch (Exception $e) {
        error_log('Audit Log Error: ' . $e->getMessage());
    }
}

/**
 * Add In-App Notification
 */
function add_notification(string $title, string $message, string $type = 'info', string|int|null $link = null): void
{
    try {
        $linkStr = $link !== null ? (string)$link : null;
        Database::execute(
            "INSERT INTO `notifications` (`title`, `message`, `type`, `link`, `is_read`)
             VALUES (?, ?, ?, ?, 0)",
            [$title, $message, $type, $linkStr]
        );
    } catch (Exception $e) {
        error_log('Notification Insert Error: ' . $e->getMessage());
    }
}

/**
 * Format Currency (INR default ₹)
 */
function format_currency(float|int|string $amount): string
{
    $symbol = Settings::get('currency_symbol', '₹');
    $val = (float)$amount;
    return $symbol . ' ' . number_format($val, 2);
}

/**
 * Format Date & Time
 */
function format_date(?string $dateTime, bool $withTime = false): string
{
    if (!$dateTime || $dateTime === '0000-00-00' || $dateTime === '0000-00-00 00:00:00') {
        return 'N/A';
    }
    $timestamp = strtotime($dateTime);
    if (!$timestamp) {
        return 'N/A';
    }
    return date($withTime ? 'd M Y, h:i A' : 'd M Y', $timestamp);
}

/**
 * Update Inventory Stock and Record Ledger Movement
 */
function update_inventory_stock(
    int $itemId,
    ?int $batchId,
    int $qty,
    string $movementType,
    ?string $reason = null,
    ?string $referenceId = null,
    ?int $userId = null
): bool {
    $db = Database::getInstance();
    $db->beginTransaction();

    try {
        $item = Database::fetch("SELECT `current_stock` FROM `inventory_items` WHERE `id` = ?", [$itemId]);
        if (!$item) {
            $db->rollBack();
            return false;
        }

        $currentTotal = (int)$item['current_stock'];
        $newTotal = $currentTotal;

        // Determine if adding or deducting
        if (in_array($movementType, ['Purchase', 'Adjustment_Add', 'Return'], true)) {
            $newTotal += $qty;
        } else {
            // Deduct
            if ($currentTotal < $qty && $movementType !== 'Adjustment_Deduct') {
                $db->rollBack();
                return false; // Prevent negative stock
            }
            $newTotal = max(0, $currentTotal - $qty);
        }

        // Update item master stock
        Database::execute("UPDATE `inventory_items` SET `current_stock` = ? WHERE `id` = ?", [$newTotal, $itemId]);

        // If batch provided, update batch quantity
        if ($batchId) {
            $batch = Database::fetch("SELECT `current_quantity`, `status` FROM `inventory_batches` WHERE `id` = ?", [$batchId]);
            if ($batch) {
                $batchQty = (int)$batch['current_quantity'];
                if (in_array($movementType, ['Purchase', 'Adjustment_Add', 'Return'], true)) {
                    $newBatchQty = $batchQty + $qty;
                } else {
                    $newBatchQty = max(0, $batchQty - $qty);
                }

                $batchStatus = $newBatchQty <= 0 ? 'Exhausted' : $batch['status'];
                Database::execute(
                    "UPDATE `inventory_batches` SET `current_quantity` = ?, `status` = ? WHERE `id` = ?",
                    [$newBatchQty, $batchStatus, $batchId]
                );
            }
        }

        // Record stock movement ledger
        Database::execute(
            "INSERT INTO `stock_movements` (`item_id`, `batch_id`, `movement_type`, `quantity`, `quantity_before`, `quantity_after`, `reference_id`, `reason`, `performed_by`)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [$itemId, $batchId, $movementType, $qty, $currentTotal, $newTotal, $referenceId, $reason, $userId ?? ($_SESSION['user']['id'] ?? null)]
        );

        $db->commit();
        return true;
    } catch (Exception $e) {
        $db->rollBack();
        error_log('Stock Update Error: ' . $e->getMessage());
        return false;
    }
}

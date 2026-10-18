<?php
/**
 * LabMaster - Main System Configuration & Bootstrap
 */

declare(strict_types=1);

// Error reporting: Log to storage, suppress raw display on production
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// Ensure storage directory for logs
$storageLogDir = dirname(__DIR__) . '/storage/logs';
if (!is_dir($storageLogDir)) {
    @mkdir($storageLogDir, 0777, true);
}
ini_set('error_log', $storageLogDir . '/error.log');

// Timezone configuration (Asia/Kolkata by default)
date_default_timezone_set('Asia/Kolkata');

// Session security configuration
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_samesite', 'Lax');
    session_start();
}

// System Constants
define('APP_NAME', 'LabMaster');
define('APP_TAGLINE', 'Precision Pathology Laboratory Management System');
define('APP_VERSION', '2.5.1');
define('BASE_PATH', dirname(__DIR__));
define('STORAGE_PATH', BASE_PATH . '/storage');

// Determine Base URL dynamically
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));

// Normalize script dir to root project folder
$parts = explode('/', trim($scriptDir, '/'));
$baseFolder = '';
if (!empty($parts) && $parts[0] !== '') {
    $baseFolder = '/' . $parts[0]; // e.g. /LabMaster
}
define('BASE_URL', rtrim($protocol . $host . $baseFolder, '/'));
define('ADMIN_URL', BASE_URL . '/admin');
define('PORTAL_URL', BASE_URL . '/portal');
define('ASSETS_URL', BASE_URL . '/assets');

// Include Database
require_once __DIR__ . '/database.php';

// System Settings Cache
class Settings
{
    private static array $cache = [];
    private static bool $loaded = false;

    public static function load(): void
    {
        if (self::$loaded) {
            return;
        }

        try {
            $rows = Database::fetchAll("SELECT `setting_key`, `setting_value` FROM `system_settings`");
            foreach ($rows as $row) {
                self::$cache[$row['setting_key']] = $row['setting_value'];
            }
            self::$loaded = true;
        } catch (Exception $e) {
            // Fallback defaults if DB connection is unavailable
            self::$cache = [
                'lab_name' => 'LabMaster Diagnostic & Research Center',
                'lab_phone' => '+91 98765 43210',
                'lab_email' => 'info@labmasterdiagnostics.com',
                'currency_symbol' => '₹',
                'patient_prefix' => 'LM-P-',
                'order_prefix' => 'LM-ACC-',
                'report_prefix' => 'LM-REP-',
                'invoice_prefix' => 'LM-INV-',
                'whatsapp_floating_phone' => '919876543210',
                'whatsapp_floating_enabled' => '1',
            ];
        }
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        self::load();
        return self::$cache[$key] ?? $default;
    }

    public static function set(string $key, ?string $value): void
    {
        Database::execute(
            "UPDATE `system_settings` SET `setting_value` = ? WHERE `setting_key` = ?",
            [$value, $key]
        );
        self::$cache[$key] = $value;
    }

    public static function all(): array
    {
        self::load();
        return self::$cache;
    }
}

// Flash Message System
function set_flash(string $type, string $message): void
{
    $_SESSION['flash'] = [
        'type' => $type, // 'success', 'danger', 'warning', 'info'
        'message' => $message
    ];
}

function get_flash(): ?array
{
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

function render_flash(): string
{
    $flash = get_flash();
    if (!$flash) {
        return '';
    }

    $icon = match ($flash['type']) {
        'success' => 'bi-check-circle-fill',
        'danger'  => 'bi-exclamation-triangle-fill',
        'warning' => 'bi-exclamation-circle-fill',
        default   => 'bi-info-circle-fill',
    };

    return sprintf(
        '<div class="alert alert-%s alert-dismissible fade show d-flex align-items-center gap-2 shadow-sm my-3" role="alert">
            <i class="bi %s fs-5"></i>
            <div>%s</div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>',
        htmlspecialchars($flash['type'], ENT_QUOTES, 'UTF-8'),
        $icon,
        htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8')
    );
}

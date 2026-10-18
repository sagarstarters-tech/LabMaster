<?php
/**
 * LabMaster - Pathology Test Catalog CSV Exporter
 */

declare(strict_types=1);

require_once dirname(dirname(__DIR__)) . '/config/config.php';
require_once dirname(dirname(__DIR__)) . '/includes/auth.php';

Auth::requirePermission('tests.manage');

$filename = 'labmaster_pathology_catalog_' . date('Ymd_His') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$output = fopen('php://output', 'w');

// UTF-8 BOM for Excel compatibility
fputs($output, "\xEF\xBB\xBF");

// Header row
fputcsv($output, [
    'Test ID',
    'Test Code',
    'Test Name',
    'Short Name / Aliases',
    'Category',
    'Department',
    'Specimen Type',
    'Method',
    'Parameters Count',
    'Default Price (INR)',
    'Cost Price (INR)',
    'Turnaround Time',
    'Type',
    'Status'
]);

$sql = "
    SELECT t.*, c.name as category_name,
           (SELECT COUNT(*) FROM `test_parameters` p WHERE p.test_id = t.id) as param_count
    FROM `tests` t
    JOIN `test_categories` c ON t.category_id = c.id
    ORDER BY c.sort_order ASC, t.test_name ASC
";

$tests = Database::fetchAll($sql);

foreach ($tests as $t) {
    fputcsv($output, [
        $t['id'],
        $t['test_code'],
        $t['test_name'],
        $t['short_name'] ?? '-',
        $t['category_name'],
        $t['department'] ?? '-',
        $t['specimen_type'],
        $t['method'] ?? 'Standard Method',
        $t['param_count'],
        number_format((float)$t['price'], 2, '.', ''),
        number_format((float)$t['cost_price'], 2, '.', ''),
        $t['turnaround_time'],
        $t['is_package'] ? 'Multi-Parameter Profile' : 'Individual Test',
        $t['is_active'] ? 'Active' : 'Inactive'
    ]);
}

fclose($output);
exit;

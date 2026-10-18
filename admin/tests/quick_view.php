<?php
/**
 * LabMaster - Quick View JSON Endpoint for Pathology Test Details
 */

declare(strict_types=1);

require_once dirname(dirname(__DIR__)) . '/config/config.php';
require_once dirname(dirname(__DIR__)) . '/includes/auth.php';

header('Content-Type: application/json');

if (!Auth::check()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$testId = (int)($_GET['id'] ?? 0);
if ($testId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid test ID']);
    exit;
}

$test = Database::fetch("
    SELECT t.*, c.name as category_name, c.code as category_code
    FROM `tests` t
    JOIN `test_categories` c ON t.category_id = c.id
    WHERE t.id = ?
", [$testId]);

if (!$test) {
    echo json_encode(['success' => false, 'message' => 'Test not found']);
    exit;
}

$params = Database::fetchAll("
    SELECT * FROM `test_parameters` 
    WHERE `test_id` = ? 
    ORDER BY `sort_order` ASC, `id` ASC
", [$testId]);

foreach ($params as &$p) {
    $p['intervals'] = Database::fetchAll("
        SELECT * FROM `reference_intervals`
        WHERE `parameter_id` = ?
        ORDER BY `id` ASC
    ", [$p['id']]);
}

echo json_encode([
    'success' => true,
    'test' => $test,
    'parameters' => $params
]);
exit;

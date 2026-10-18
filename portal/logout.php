<?php
/**
 * LabMaster - Patient Health Portal Logout
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';

if (!empty($_SESSION['patient'])) {
    $patientCode = $_SESSION['patient']['patient_id'] ?? 'Unknown';
    $name = $_SESSION['patient']['full_name'] ?? 'Patient';
    log_audit('Patient Logged Out', 'Portal', $patientCode, "Patient {$name} logged out from portal.");
    unset($_SESSION['patient']);
}

set_flash('info', 'You have been safely signed out from your patient portal.');
header('Location: ' . PORTAL_URL . '/login.php');
exit;

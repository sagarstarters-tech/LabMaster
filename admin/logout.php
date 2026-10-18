<?php
/**
 * LabMaster - Admin Logout Handler
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/auth.php';

Auth::logout();
set_flash('info', 'You have been safely signed out.');
header('Location: ' . ADMIN_URL . '/login.php');
exit;

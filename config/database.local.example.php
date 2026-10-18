<?php
/**
 * LabMaster - Hostinger / Live Server Database Configuration
 * 
 * Instructions for Hostinger:
 * 1. Rename or copy this file to 'config/database.local.php' on your Hostinger server.
 * 2. Fill in the MySQL Database Name, Username, and Password from Hostinger hPanel.
 * 3. This file is git-ignored so your production passwords remain safe.
 */

return [
    'host'    => 'localhost',              // Usually 'localhost' on Hostinger
    'port'    => '3306',
    'name'    => 'u123456789_labmaster',    // Your Hostinger Database Name
    'user'    => 'u123456789_labuser',      // Your Hostinger Database Username
    'pass'    => 'YourHostingerDbPassword', // Your Hostinger Database Password
    'charset' => 'utf8mb4'
];

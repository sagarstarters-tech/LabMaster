<?php
/**
 * LabMaster - Public Website Header & Navigation
 */

declare(strict_types=1);

require_once __DIR__ . '/functions.php';

$pageTitle = $pageTitle ?? Settings::get('lab_name', 'LabMaster Diagnostic & Research Center');
$pageDesc = $pageDesc ?? 'Leading NABL-accredited diagnostic laboratory providing automated clinical pathology testing, home sample collection, and rapid digital reports.';
$activePage = $activePage ?? 'home';

$labName = Settings::get('lab_name', 'LabMaster Diagnostic & Research Center');
$labPhone = Settings::get('lab_phone', '+91 98765 43210');
$labEmail = Settings::get('lab_email', 'info@labmasterdiagnostics.com');
$workingHours = Settings::get('working_hours', 'Mon - Sat: 07:00 AM - 09:00 PM');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($pageTitle) ?></title>
  <meta name="description" content="<?= e($pageDesc) ?>">
  <meta name="author" content="LabMaster Diagnostics">

  <!-- Favicon -->
  <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%230284c7'><path d='M4.5 10.5C3.67 10.5 3 11.17 3 12s.67 1.5 1.5 1.5h15c.83 0 1.5-.67 1.5-1.5s-.67-1.5-1.5-1.5h-15z'/><path d='M10.5 4.5C10.5 3.67 11.17 3 12 3s1.5.67 1.5 1.5v15c0 .83-.67 1.5-1.5 1.5s-1.5-.67-1.5-1.5v-15z'/></svg>">

  <!-- Bootstrap 5.3 CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Bootstrap Icons -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  
  <!-- Custom Public Theme CSS -->
  <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/style.css?v=<?= APP_VERSION ?>">
</head>
<body>

  <!-- Top Info Bar -->
  <div class="top-bar d-none d-lg-block">
    <div class="container">
      <div class="d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-4">
          <span><i class="bi bi-clock me-1 text-info"></i> <?= e($workingHours) ?></span>
          <span><i class="bi bi-telephone-fill me-1 text-info"></i> <?= e($labPhone) ?></span>
          <span><i class="bi bi-envelope-fill me-1 text-info"></i> <?= e($labEmail) ?></span>
        </div>
        <div class="d-flex align-items-center gap-3">
          <a href="<?= BASE_URL ?>/verify-report.php"><i class="bi bi-shield-check me-1"></i> Verify QR Report</a>
          <span class="text-secondary">|</span>
          <a href="<?= PORTAL_URL ?>/login.php"><i class="bi bi-person-circle me-1"></i> Patient Portal</a>
          <span class="text-secondary">|</span>
          <a href="<?= ADMIN_URL ?>/login.php" class="text-info fw-semibold"><i class="bi bi-lock-fill me-1"></i> Staff Login</a>
        </div>
      </div>
    </div>
  </div>

  <!-- Main Navigation Bar -->
  <nav class="navbar navbar-expand-lg main-navbar">
    <div class="container">
      <a class="navbar-brand" href="<?= BASE_URL ?>/index.php">
        <i class="bi bi-heart-pulse-fill"></i>
        <span>Lab<span class="text-primary">Master</span></span>
      </a>

      <button class="navbar-toggler border-0 shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain" aria-controls="navbarMain" aria-expanded="false" aria-label="Toggle navigation">
        <i class="bi bi-list fs-2 text-dark"></i>
      </button>

      <div class="collapse navbar-collapse" id="navbarMain">
        <ul class="navbar-nav mx-auto mb-2 mb-lg-0">
          <li class="nav-item">
            <a class="nav-link <?= $activePage === 'home' ? 'active' : '' ?>" href="<?= BASE_URL ?>/index.php">Home</a>
          </li>
          <li class="nav-item">
            <a class="nav-link <?= $activePage === 'about' ? 'active' : '' ?>" href="<?= BASE_URL ?>/about.php">About</a>
          </li>
          <li class="nav-item">
            <a class="nav-link <?= $activePage === 'services' ? 'active' : '' ?>" href="<?= BASE_URL ?>/services.php">Services</a>
          </li>
          <li class="nav-item">
            <a class="nav-link <?= $activePage === 'tests' ? 'active' : '' ?>" href="<?= BASE_URL ?>/tests.php">Tests Catalog</a>
          </li>
          <li class="nav-item">
            <a class="nav-link <?= $activePage === 'price-list' ? 'active' : '' ?>" href="<?= BASE_URL ?>/price-list.php">Price List</a>
          </li>
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle <?= in_array($activePage, ['patient-instructions', 'sample-collection', 'faqs', 'contact'], true) ? 'active' : '' ?>" href="#" role="button" data-bs-toggle="dropdown">
              Patient Care
            </a>
            <ul class="dropdown-menu border-0 shadow-sm">
              <li><a class="dropdown-item" href="<?= BASE_URL ?>/patient-instructions.php"><i class="bi bi-clipboard2-pulse me-2 text-primary"></i> Patient Instructions</a></li>
              <li><a class="dropdown-item" href="<?= BASE_URL ?>/sample-collection.php"><i class="bi bi-droplet-half me-2 text-primary"></i> Sample Collection</a></li>
              <li><a class="dropdown-item" href="<?= BASE_URL ?>/faqs.php"><i class="bi bi-question-circle me-2 text-primary"></i> FAQs</a></li>
              <li><hr class="dropdown-divider"></li>
              <li><a class="dropdown-item" href="<?= BASE_URL ?>/contact.php"><i class="bi bi-geo-alt me-2 text-primary"></i> Contact & Location</a></li>
            </ul>
          </li>
        </ul>

        <div class="d-flex align-items-center gap-2">
          <a href="<?= BASE_URL ?>/download-report.php" class="btn btn-outline-lm btn-sm">
            <i class="bi bi-file-earmark-pdf me-1"></i> Download Report
          </a>
          <a href="<?= BASE_URL ?>/booking.php" class="btn btn-primary-lm btn-sm">
            <i class="bi bi-calendar-check me-1"></i> Quick Booking
          </a>
        </div>
      </div>
    </div>
  </nav>

  <!-- Flash Message Container -->
  <div class="container">
    <?= render_flash() ?>
  </div>

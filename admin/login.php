<?php
/**
 * LabMaster - Admin Staff & Pathologist Portal Login
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/auth.php';

// Redirect if already logged in
if (Auth::check()) {
    header('Location: ' . ADMIN_URL . '/index.php');
    exit;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Please enter both your username/email and password.';
    } else {
        $result = Auth::attempt($username, $password);
        if ($result['success']) {
            set_flash('success', 'Welcome back! Signed in to LabMaster Administrative Console.');
            header('Location: ' . ADMIN_URL . '/index.php');
            exit;
        } else {
            $error = $result['message'];
        }
    }
}

$labName = Settings::get('lab_name', 'LabMaster Diagnostic & Research Center');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Staff Portal Login | <?= e($labName) ?></title>

  <!-- Favicon -->
  <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%230284c7'><path d='M4.5 10.5C3.67 10.5 3 11.17 3 12s.67 1.5 1.5 1.5h15c.83 0 1.5-.67 1.5-1.5s-.67-1.5-1.5-1.5h-15z'/><path d='M10.5 4.5C10.5 3.67 11.17 3 12 3s1.5.67 1.5 1.5v15c0 .83-.67 1.5-1.5 1.5s-1.5-.67-1.5-1.5v-15z'/></svg>">

  <!-- Bootstrap 5.3 CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  
  <style>
    body {
      background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 1.5rem;
    }
    .login-card {
      background: #ffffff;
      border-radius: 16px;
      box-shadow: 0 20px 40px rgba(0, 0, 0, 0.25);
      width: 100%;
      max-width: 440px;
      overflow: hidden;
    }
    .login-header {
      background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%);
      padding: 2.25rem 2rem;
      color: #ffffff;
      text-align: center;
    }
    .login-body {
      padding: 2rem;
    }
    .demo-credential-pill {
      cursor: pointer;
      font-size: 0.75rem;
      padding: 4px 8px;
      border-radius: 6px;
      transition: all 0.2s;
    }
    .demo-credential-pill:hover {
      background-color: #e0f2fe !important;
      border-color: #0284c7 !important;
    }
  </style>
</head>
<body>

<div class="login-card">
  <div class="login-header">
    <div class="d-inline-flex p-3 rounded-circle bg-white text-primary mb-2 shadow-sm">
      <i class="bi bi-heart-pulse-fill fs-3"></i>
    </div>
    <h2 class="h5 fw-bold mb-1">LabMaster Staff Portal</h2>
    <p class="small text-white-50 mb-0"><?= e($labName) ?></p>
  </div>

  <div class="login-body">
    <?= render_flash() ?>

    <?php if ($error): ?>
      <div class="alert alert-danger d-flex align-items-center gap-2 small mb-3">
        <i class="bi bi-exclamation-triangle-fill fs-5"></i>
        <div><?= e($error) ?></div>
      </div>
    <?php endif; ?>

    <form method="POST" action="">
      <?= csrf_field() ?>

      <div class="mb-3">
        <label for="username" class="form-label small fw-semibold text-secondary">Username or Email</label>
        <div class="input-group">
          <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-person"></i></span>
          <input type="text" class="form-control border-start-0" id="username" name="username" placeholder="e.g. admin or dr.sharma" required autofocus>
        </div>
      </div>

      <div class="mb-3">
        <label for="password" class="form-label small fw-semibold text-secondary">Password</label>
        <div class="input-group">
          <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-lock"></i></span>
          <input type="password" class="form-control border-start-0" id="password" name="password" placeholder="••••••••" required>
        </div>
      </div>

      <div class="d-grid mt-4">
        <button type="submit" class="btn btn-primary fw-semibold py-2" style="background:#0284c7; border-color:#0284c7;">
          <i class="bi bi-box-arrow-in-right me-1"></i> Sign In to System
        </button>
      </div>
    </form>

    <!-- Quick Demo Accounts Switcher -->
    <div class="mt-4 pt-3 border-top">
      <div class="text-muted small fw-semibold mb-2 text-center">Quick Demo Testing Accounts:</div>
      <div class="d-flex flex-wrap gap-1 justify-content-center">
        <span class="demo-credential-pill bg-light border text-dark" onclick="fillCreds('admin', 'Admin@123')">
          <strong>Admin</strong>: admin / Admin@123
        </span>
        <span class="demo-credential-pill bg-light border text-dark" onclick="fillCreds('dr.sharma', 'Patho@123')">
          <strong>Pathologist</strong>: dr.sharma / Patho@123
        </span>
        <span class="demo-credential-pill bg-light border text-dark" onclick="fillCreds('tech.anil', 'Tech@123')">
          <strong>Technician</strong>: tech.anil / Tech@123
        </span>
        <span class="demo-credential-pill bg-light border text-dark" onclick="fillCreds('reception', 'Staff@123')">
          <strong>Reception</strong>: reception / Staff@123
        </span>
      </div>
    </div>

    <div class="text-center mt-3">
      <a href="<?= BASE_URL ?>/index.php" class="small text-decoration-none text-muted">
        &larr; Back to Public Laboratory Website
      </a>
    </div>
  </div>
</div>

<script>
function fillCreds(u, p) {
  document.getElementById('username').value = u;
  document.getElementById('password').value = p;
}
</script>

</body>
</html>

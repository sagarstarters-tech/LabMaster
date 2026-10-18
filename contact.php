<?php
/**
 * LabMaster - Contact Us & Location
 */

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Contact Us | ' . Settings::get('lab_name', 'LabMaster Diagnostic & Research Center');
$pageDesc  = 'Get in touch with our laboratory team for inquiries, home sample collection, and doctor partnerships.';
$activePage = 'contact';

$submitted = false;
$contactError = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? 'General Inquiry');
    $message = trim($_POST['message'] ?? '');

    if (empty($name) || empty($phone) || empty($message)) {
        $contactError = 'Please fill in your name, mobile number, and message.';
    } else {
        // Record as an in-app notification for admin
        add_notification(
            'New Website Inquiry: ' . $subject,
            "Inquiry from {$name} ({$phone}): " . substr($message, 0, 120),
            'inquiry',
            null
        );

        log_audit('Website Contact Inquiry', 'Contact', $phone, "Subject: {$subject}, Name: {$name}");
        $submitted = true;
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<!-- Header Banner -->
<section class="py-5 bg-light border-bottom">
  <div class="container text-center">
    <span class="badge bg-primary-subtle text-primary fw-semibold px-3 py-2 rounded-pill mb-2">We Are Here to Help</span>
    <h1 class="fw-bold mb-3">Contact LabMaster Diagnostics</h1>
    <p class="text-muted max-w-700 mx-auto">
      Have a question about a pathology test, doctor prescription, or home sample collection? Our laboratory support team is available 7 days a week.
    </p>
  </div>
</section>

<section class="py-5 bg-white">
  <div class="container">
    
    <div class="row g-5">
      <!-- Contact Info -->
      <div class="col-lg-5">
        <h4 class="fw-bold mb-4">Laboratory Headquarters</h4>

        <div class="d-flex align-items-start gap-3 mb-4">
          <div class="feature-icon-box flex-shrink-0">
            <i class="bi bi-geo-alt"></i>
          </div>
          <div>
            <h6 class="fw-bold mb-1">Center Address</h6>
            <p class="text-secondary small mb-0"><?= e(Settings::get('lab_address', 'Plot No. 42, Healthcare Square, Central Avenue, Metro City, 400001, India')) ?></p>
          </div>
        </div>

        <div class="d-flex align-items-start gap-3 mb-4">
          <div class="feature-icon-box flex-shrink-0">
            <i class="bi bi-telephone"></i>
          </div>
          <div>
            <h6 class="fw-bold mb-1">Direct Help Desk & Appointments</h6>
            <p class="text-secondary small mb-0">
              <?= e(Settings::get('lab_phone', '+91 98765 43210')) ?><br>
              <?= e(Settings::get('lab_alt_phone', '+91 91234 56789')) ?>
            </p>
          </div>
        </div>

        <div class="d-flex align-items-start gap-3 mb-4">
          <div class="feature-icon-box flex-shrink-0">
            <i class="bi bi-envelope"></i>
          </div>
          <div>
            <h6 class="fw-bold mb-1">Email Inquiries</h6>
            <p class="text-secondary small mb-0"><?= e(Settings::get('lab_email', 'info@labmasterdiagnostics.com')) ?></p>
          </div>
        </div>

        <div class="d-flex align-items-start gap-3 mb-4">
          <div class="feature-icon-box flex-shrink-0">
            <i class="bi bi-clock"></i>
          </div>
          <div>
            <h6 class="fw-bold mb-1">Sample Collection Hours</h6>
            <p class="text-secondary small mb-0">
              Monday - Saturday: 07:00 AM - 09:00 PM<br>
              Sunday: 08:00 AM - 01:00 PM (Emergency Fasting Samples)
            </p>
          </div>
        </div>

        <div class="p-3 bg-light rounded-4 border">
          <div class="d-flex align-items-center gap-3">
            <i class="bi bi-whatsapp text-success fs-2"></i>
            <div>
              <div class="fw-bold text-dark">Chat on WhatsApp</div>
              <div class="text-muted small">Connect instantly with our lab coordinator for booking inquiries.</div>
            </div>
            <a href="<?= WhatsAppService::buildChatUrl() ?>" target="_blank" class="btn btn-sm btn-success ms-auto">Chat</a>
          </div>
        </div>
      </div>

      <!-- Contact Form -->
      <div class="col-lg-7">
        <div class="card border rounded-4 p-4 p-md-5 shadow-sm">
          <h4 class="fw-bold mb-2">Send an Inquiry</h4>
          <p class="text-muted small mb-4">Fill out the form below and our medical coordinator will respond promptly.</p>

          <?php if ($submitted): ?>
            <div class="alert alert-success d-flex align-items-center gap-2 mb-4">
              <i class="bi bi-check-circle-fill fs-5 flex-shrink-0"></i>
              <div>
                <strong>Thank you!</strong> Your message has been received by our laboratory reception. We will contact you at <strong><?= e($_POST['phone'] ?? '') ?></strong> shortly.
              </div>
            </div>
          <?php endif; ?>

          <?php if (!empty($contactError)): ?>
            <div class="alert alert-danger mb-4"><?= e($contactError) ?></div>
          <?php endif; ?>

          <form action="<?= BASE_URL ?>/contact.php" method="POST">
            <?= csrf_field() ?>

            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label fw-semibold">Your Name <span class="text-danger">*</span></label>
                <input type="text" name="name" class="form-control" placeholder="e.g. Anand Deshmukh" value="<?= e($_POST['name'] ?? '') ?>" required>
              </div>

              <div class="col-md-6">
                <label class="form-label fw-semibold">Mobile Number <span class="text-danger">*</span></label>
                <input type="tel" name="phone" class="form-control" placeholder="10-digit mobile number" value="<?= e($_POST['phone'] ?? '') ?>" required>
              </div>

              <div class="col-md-6">
                <label class="form-label fw-semibold">Email Address (Optional)</label>
                <input type="email" name="email" class="form-control" placeholder="name@example.com" value="<?= e($_POST['email'] ?? '') ?>">
              </div>

              <div class="col-md-6">
                <label class="form-label fw-semibold">Subject</label>
                <select name="subject" class="form-select">
                  <option value="Home Sample Collection">Home Sample Collection</option>
                  <option value="Pathology Test Inquiry">Pathology Test Inquiry</option>
                  <option value="Doctor Referral Collaboration">Doctor Referral Collaboration</option>
                  <option value="Corporate Health Checkups">Corporate Health Checkups</option>
                  <option value="Other Feedback">Other Feedback</option>
                </select>
              </div>

              <div class="col-12">
                <label class="form-label fw-semibold">Message / Test Requirements <span class="text-danger">*</span></label>
                <textarea name="message" class="form-control" rows="4" placeholder="Tell us how we can help you..." required><?= e($_POST['message'] ?? '') ?></textarea>
              </div>

              <div class="col-12">
                <button type="submit" class="btn btn-primary-lm btn-lg">
                  <i class="bi bi-send me-1"></i> Send Inquiry
                </button>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Map Section -->
    <div class="mt-5 pt-3">
      <h4 class="fw-bold mb-3"><i class="bi bi-map text-primary me-2"></i>Laboratory Location Map</h4>
      <div class="rounded-4 overflow-hidden border shadow-sm ratio ratio-21x9">
        <iframe 
          src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3771.8028723321557!2d72.8561!3d19.027!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x0%3A0x0!2zMTnCsDAxJzM3LjIiTiA3MsKwNTEnMjIuMCJF!5e0!3m2!1sen!2sin!4v1620000000000!5m2!1sen!2sin" 
          style="border:0;" 
          allowfullscreen="" 
          loading="lazy" 
          referrerpolicy="no-referrer-when-downgrade">
        </iframe>
      </div>
    </div>

  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<?php
/**
 * LabMaster - System Settings & Lab Configuration
 */

declare(strict_types=1);

$pageTitle = 'System Settings';
$activeNav = 'settings';

require_once dirname(__DIR__) . '/includes/auth_guard.php';
Auth::requirePermission('settings.manage');

$activeTab = $_GET['tab'] ?? 'general';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $postedSettings = $_POST['settings'] ?? [];
    foreach ($postedSettings as $key => $val) {
        Settings::set((string)$key, trim((string)$val));
    }

    log_audit('Settings Updated', 'Settings', 'CONFIG', "Updated system configurations.");
    set_flash('success', 'Laboratory configuration settings saved successfully.');
    header("Location: " . ADMIN_URL . "/settings/index.php?tab=" . urlencode($activeTab));
    exit;
}

$allSettings = Settings::all();

require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
  <div>
    <h2 class="h4 fw-bold mb-1">Laboratory Configuration & Settings</h2>
    <p class="text-muted small mb-0">Customizable operational parameters, branding, WhatsApp API, and numbering prefixes</p>
  </div>
</div>

<div class="admin-card">
  <div class="admin-card-header p-0 border-bottom">
    <ul class="nav nav-tabs border-0" id="settingsTabs">
      <li class="nav-item">
        <a class="nav-link <?= $activeTab === 'general' ? 'active' : '' ?> px-4 py-3 fw-semibold border-0" href="?tab=general">
          <i class="bi bi-hospital me-1 text-primary"></i> 1. Laboratory Profile & Contacts
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?= $activeTab === 'prefixes' ? 'active' : '' ?> px-4 py-3 fw-semibold border-0" href="?tab=prefixes">
          <i class="bi bi-123 me-1 text-success"></i> 2. Numbering Sequences & Billing
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?= $activeTab === 'whatsapp' ? 'active' : '' ?> px-4 py-3 fw-semibold border-0" href="?tab=whatsapp">
          <i class="bi bi-whatsapp me-1 text-success"></i> 3. WhatsApp Integration
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?= $activeTab === 'pdf' ? 'active' : '' ?> px-4 py-3 fw-semibold border-0" href="?tab=pdf">
          <i class="bi bi-file-earmark-pdf me-1 text-danger"></i> 4. PDF Medical Reports
        </a>
      </li>
    </ul>
  </div>

  <div class="admin-card-body p-4">
    <form method="POST" action="">
      <?= csrf_field() ?>

      <?php if ($activeTab === 'general'): ?>
        <!-- TAB 1: General & Profile -->
        <h5 class="fw-bold mb-3">Laboratory Identity & Contact Channels</h5>
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label small fw-semibold">Laboratory Name</label>
            <input type="text" name="settings[lab_name]" class="form-control" value="<?= e($allSettings['lab_name'] ?? '') ?>" required>
          </div>
          <div class="col-md-6">
            <label class="form-label small fw-semibold">Clinical Tagline</label>
            <input type="text" name="settings[lab_tagline]" class="form-control" value="<?= e($allSettings['lab_tagline'] ?? '') ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label small fw-semibold">Primary Contact Phone</label>
            <input type="text" name="settings[lab_phone]" class="form-control" value="<?= e($allSettings['lab_phone'] ?? '') ?>" required>
          </div>
          <div class="col-md-6">
            <label class="form-label small fw-semibold">Alternate / Emergency Hotline</label>
            <input type="text" name="settings[lab_alt_phone]" class="form-control" value="<?= e($allSettings['lab_alt_phone'] ?? '') ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label small fw-semibold">Contact Email</label>
            <input type="email" name="settings[lab_email]" class="form-control" value="<?= e($allSettings['lab_email'] ?? '') ?>" required>
          </div>
          <div class="col-md-6">
            <label class="form-label small fw-semibold">Working Hours Display</label>
            <input type="text" name="settings[working_hours]" class="form-control" value="<?= e($allSettings['working_hours'] ?? '') ?>">
          </div>
          <div class="col-12">
            <label class="form-label small fw-semibold">Accreditation Text (Displayed on reports and website)</label>
            <input type="text" name="settings[accreditation_text]" class="form-control" value="<?= e($allSettings['accreditation_text'] ?? '') ?>">
          </div>
          <div class="col-12">
            <label class="form-label small fw-semibold">Full Physical Address</label>
            <textarea name="settings[lab_address]" class="form-control" rows="2"><?= e($allSettings['lab_address'] ?? '') ?></textarea>
          </div>
          <div class="col-12">
            <label class="form-label small fw-semibold">Google Maps Embed URL</label>
            <input type="text" name="settings[google_map_embed]" class="form-control font-monospace small" value="<?= e($allSettings['google_map_embed'] ?? '') ?>">
          </div>
        </div>

      <?php elseif ($activeTab === 'prefixes'): ?>
        <!-- TAB 2: Numbering & Currency -->
        <h5 class="fw-bold mb-3">Document Numbering Prefixes & Billing Defaults</h5>
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label small fw-semibold">Patient ID Prefix</label>
            <input type="text" name="settings[patient_prefix]" class="form-control font-monospace" value="<?= e($allSettings['patient_prefix'] ?? 'LM-P-') ?>">
            <div class="form-text">e.g. LM-P-2026-0001</div>
          </div>
          <div class="col-md-4">
            <label class="form-label small fw-semibold">Accession / Order Prefix</label>
            <input type="text" name="settings[order_prefix]" class="form-control font-monospace" value="<?= e($allSettings['order_prefix'] ?? 'LM-ACC-') ?>">
            <div class="form-text">e.g. LM-ACC-2026-0001</div>
          </div>
          <div class="col-md-4">
            <label class="form-label small fw-semibold">Report Number Prefix</label>
            <input type="text" name="settings[report_prefix]" class="form-control font-monospace" value="<?= e($allSettings['report_prefix'] ?? 'LM-REP-') ?>">
            <div class="form-text">e.g. LM-REP-2026-0001</div>
          </div>
          <div class="col-md-4">
            <label class="form-label small fw-semibold">Invoice Prefix</label>
            <input type="text" name="settings[invoice_prefix]" class="form-control font-monospace" value="<?= e($allSettings['invoice_prefix'] ?? 'LM-INV-') ?>">
            <div class="form-text">e.g. LM-INV-2026-0001</div>
          </div>
          <div class="col-md-4">
            <label class="form-label small fw-semibold">Payment Receipt Prefix</label>
            <input type="text" name="settings[receipt_prefix]" class="form-control font-monospace" value="<?= e($allSettings['receipt_prefix'] ?? 'LM-REC-') ?>">
            <div class="form-text">e.g. LM-REC-2026-0001</div>
          </div>
          <div class="col-md-4">
            <label class="form-label small fw-semibold">Booking Reference Prefix</label>
            <input type="text" name="settings[booking_prefix]" class="form-control font-monospace" value="<?= e($allSettings['booking_prefix'] ?? 'LM-BK-') ?>">
            <div class="form-text">e.g. LM-BK-2026-0001</div>
          </div>

          <div class="col-md-6 mt-4">
            <label class="form-label small fw-semibold">Currency Symbol</label>
            <input type="text" name="settings[currency_symbol]" class="form-control" value="<?= e($allSettings['currency_symbol'] ?? '₹') ?>">
          </div>
          <div class="col-md-6 mt-4">
            <label class="form-label small fw-semibold">Currency Code</label>
            <input type="text" name="settings[currency_code]" class="form-control" value="<?= e($allSettings['currency_code'] ?? 'INR') ?>">
          </div>
        </div>

      <?php elseif ($activeTab === 'whatsapp'): ?>
        <!-- TAB 3: WhatsApp Integration -->
        <h5 class="fw-bold mb-3">WhatsApp Live Chat & Official Cloud API</h5>

        <div class="p-3 bg-light rounded border mb-4">
          <h6 class="fw-bold text-dark"><i class="bi bi-chat-dots-fill text-success me-1"></i> Public Floating WhatsApp Live Chat</h6>
          <div class="row g-3 mt-1">
            <div class="col-md-6">
              <label class="form-label small fw-semibold">WhatsApp Live Chat Number (Include Country Code)</label>
              <input type="text" name="settings[whatsapp_floating_phone]" class="form-control font-monospace" value="<?= e($allSettings['whatsapp_floating_phone'] ?? '919876543210') ?>">
              <div class="form-text">Without '+' or spaces (e.g. 919876543210 for India).</div>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-semibold">Enable Floating Button</label>
              <select name="settings[whatsapp_floating_enabled]" class="form-select">
                <option value="1" <?= ($allSettings['whatsapp_floating_enabled'] ?? '1') === '1' ? 'selected' : '' ?>>Enabled</option>
                <option value="0" <?= ($allSettings['whatsapp_floating_enabled'] ?? '1') === '0' ? 'selected' : '' ?>>Disabled</option>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label small fw-semibold">Default Floating Greeting Message</label>
              <textarea name="settings[whatsapp_floating_text]" class="form-control" rows="2"><?= e($allSettings['whatsapp_floating_text'] ?? '') ?></textarea>
            </div>
          </div>
        </div>

        <div class="p-3 bg-light rounded border">
          <h6 class="fw-bold text-dark"><i class="bi bi-cloud-arrow-up-fill text-primary me-1"></i> Official Meta WhatsApp Cloud API (Automated Report Delivery)</h6>
          <p class="small text-muted mb-3">
            Configure Meta for Developers WhatsApp Business Cloud API credentials to enable automated PDF notification delivery.
          </p>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label small fw-semibold">Cloud API Enabled</label>
              <select name="settings[whatsapp_api_enabled]" class="form-select">
                <option value="0" <?= ($allSettings['whatsapp_api_enabled'] ?? '0') === '0' ? 'selected' : '' ?>>Disabled (Use Direct Web Dispatch Link Fallback)</option>
                <option value="1" <?= ($allSettings['whatsapp_api_enabled'] ?? '0') === '1' ? 'selected' : '' ?>>Enabled (Active Cloud API)</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-semibold">WhatsApp Phone Number ID</label>
              <input type="text" name="settings[whatsapp_cloud_phone_number_id]" class="form-control font-monospace" value="<?= e($allSettings['whatsapp_cloud_phone_number_id'] ?? '') ?>" placeholder="e.g. 1048291038192">
            </div>
            <div class="col-12">
              <label class="form-label small fw-semibold">System User Permanent Access Token</label>
              <input type="password" name="settings[whatsapp_cloud_access_token]" class="form-control font-monospace" value="<?= e($allSettings['whatsapp_cloud_access_token'] ?? '') ?>" placeholder="EAAGm0PX4ZC50...">
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-semibold">WhatsApp Business Account ID</label>
              <input type="text" name="settings[whatsapp_cloud_business_account_id]" class="form-control font-monospace" value="<?= e($allSettings['whatsapp_cloud_business_account_id'] ?? '') ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-semibold">Approved Message Template Name</label>
              <input type="text" name="settings[whatsapp_report_template]" class="form-control font-monospace" value="<?= e($allSettings['whatsapp_report_template'] ?? 'labmaster_report_ready') ?>">
            </div>
          </div>
        </div>

      <?php elseif ($activeTab === 'pdf'): ?>
        <!-- TAB 4: PDF Report Settings -->
        <h5 class="fw-bold mb-3">Medical Report Sheet Design & Disclaimer</h5>
        <div class="row g-3">
          <div class="col-md-8">
            <label class="form-label small fw-semibold">Report Header Title</label>
            <input type="text" name="settings[pdf_header_title]" class="form-control" value="<?= e($allSettings['pdf_header_title'] ?? 'LABMASTER DIAGNOSTIC & RESEARCH CENTER') ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label small fw-semibold">Show Verification QR Code</label>
            <select name="settings[pdf_show_qr_code]" class="form-select">
              <option value="1" <?= ($allSettings['pdf_show_qr_code'] ?? '1') === '1' ? 'selected' : '' ?>>Yes, Print QR Code</option>
              <option value="0" <?= ($allSettings['pdf_show_qr_code'] ?? '1') === '0' ? 'selected' : '' ?>>No QR Code</option>
            </select>
          </div>
          <div class="col-12">
            <label class="form-label small fw-semibold">Standard Clinical Disclaimer (Printed at bottom of reports)</label>
            <textarea name="settings[pdf_disclaimer]" class="form-control" rows="3"><?= e($allSettings['pdf_disclaimer'] ?? '') ?></textarea>
          </div>
        </div>
      <?php endif; ?>

      <div class="mt-4 pt-3 border-top text-end">
        <button type="submit" class="btn btn-save">
          <i class="bi bi-check2 me-1"></i> Save Configuration Settings
        </button>
      </div>
    </form>
  </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>

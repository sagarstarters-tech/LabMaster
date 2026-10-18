<?php
/**
 * LabMaster - Frequently Asked Questions (FAQs)
 */

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Frequently Asked Questions | ' . Settings::get('lab_name', 'LabMaster Diagnostic & Research Center');
$pageDesc  = 'Find answers to common questions about pathology tests, fasting instructions, home sample collection, and digital report retrieval.';
$activePage = 'faqs';

require_once __DIR__ . '/includes/header.php';
?>

<!-- Header Banner -->
<section class="py-5 bg-light border-bottom">
  <div class="container text-center">
    <span class="badge bg-primary-subtle text-primary fw-semibold px-3 py-2 rounded-pill mb-2">Help & Guidance</span>
    <h1 class="fw-bold mb-3">Frequently Asked Questions</h1>
    <p class="text-muted max-w-700 mx-auto">
      Clear clinical answers regarding test preparation, home phlebotomy visits, report delivery, and quality assurance.
    </p>
  </div>
</section>

<section class="py-5 bg-white">
  <div class="container">
    
    <div class="row justify-content-center">
      <div class="col-lg-9">

        <!-- Category: Test Preparation & Fasting -->
        <div class="mb-5">
          <div class="d-flex align-items-center gap-2 mb-3">
            <i class="bi bi-egg-fried text-primary fs-4"></i>
            <h4 class="fw-bold mb-0">Test Preparation & Fasting</h4>
          </div>

          <div class="accordion" id="faqPrep">
            <div class="accordion-item border rounded-3 mb-3 overflow-hidden shadow-sm">
              <h2 class="accordion-header">
                <button class="accordion-button fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#fp1">
                  Which pathology tests strictly require overnight fasting?
                </button>
              </h2>
              <div id="fp1" class="accordion-collapse collapse show" data-bs-parent="#faqPrep">
                <div class="accordion-body text-secondary small">
                  Fasting Blood Sugar (FBS), Lipid Profile (Total Cholesterol, Triglycerides, HDL, LDL), Fasting Insulin, and Iron Profile require 10 to 12 hours of overnight fasting. Routine Complete Blood Count (CBC), Kidney Function Tests (KFT), Liver Function Tests (LFT), and HbA1c do not strictly require fasting, although light meals are recommended.
                </div>
              </div>
            </div>

            <div class="accordion-item border rounded-3 mb-3 overflow-hidden shadow-sm">
              <h2 class="accordion-header">
                <button class="accordion-button collapsed fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#fp2">
                  Can I drink plain water while fasting for a blood test?
                </button>
              </h2>
              <div id="fp2" class="accordion-collapse collapse" data-bs-parent="#faqPrep">
                <div class="accordion-body text-secondary small">
                  Yes, drinking plain water is actively encouraged because proper hydration keeps your veins dilated and prevents artificial hemoconcentration. However, avoid coffee, tea, juices, milk, sugar, tobacco, and chewing gum.
                </div>
              </div>
            </div>

            <div class="accordion-item border rounded-3 mb-3 overflow-hidden shadow-sm">
              <h2 class="accordion-header">
                <button class="accordion-button collapsed fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#fp3">
                  Should I take my regular prescription medicines before the blood draw?
                </button>
              </h2>
              <div id="fp3" class="accordion-collapse collapse" data-bs-parent="#faqPrep">
                <div class="accordion-body text-secondary small">
                  Daily blood pressure and cardiovascular medications can usually be taken with a sip of water. However, diabetic medications (insulin or oral hypoglycemics) should be taken only after the fasting sample has been drawn and when your meal is consumed, unless specifically directed otherwise by your treating doctor.
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Category: Home Sample Collection -->
        <div class="mb-5">
          <div class="d-flex align-items-center gap-2 mb-3">
            <i class="bi bi-house-door text-primary fs-4"></i>
            <h4 class="fw-bold mb-0">Home Sample Collection</h4>
          </div>

          <div class="accordion" id="faqHome">
            <div class="accordion-item border rounded-3 mb-3 overflow-hidden shadow-sm">
              <h2 class="accordion-header">
                <button class="accordion-button fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#fh1">
                  How does home sample collection work?
                </button>
              </h2>
              <div id="fh1" class="accordion-collapse collapse show" data-bs-parent="#faqHome">
                <div class="accordion-body text-secondary small">
                  Once you submit a booking request through our website, our lab coordinator calls you to confirm the time slot and patient address. A certified phlebotomist arrives with sterile vacuum tubes and a cold-chain carry kit, draws the specimen, and delivers it to our main testing laboratory for automated analysis.
                </div>
              </div>
            </div>

            <div class="accordion-item border rounded-3 mb-3 overflow-hidden shadow-sm">
              <h2 class="accordion-header">
                <button class="accordion-button collapsed fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#fh2">
                  How are samples protected against heat and hemolysis during transport?
                </button>
              </h2>
              <div id="fh2" class="accordion-collapse collapse" data-bs-parent="#faqHome">
                <div class="accordion-body text-secondary small">
                  Every phlebotomy field kit is equipped with calibrated ice gel packs and insulated containers that maintain 2°C to 8°C cold-chain transport. Each tube is immediately labelled with an accession barcode to eliminate misidentification.
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Category: Reports & Verification -->
        <div class="mb-5">
          <div class="d-flex align-items-center gap-2 mb-3">
            <i class="bi bi-file-earmark-pdf text-primary fs-4"></i>
            <h4 class="fw-bold mb-0">Reports & Online Retrieval</h4>
          </div>

          <div class="accordion" id="faqRep">
            <div class="accordion-item border rounded-3 mb-3 overflow-hidden shadow-sm">
              <h2 class="accordion-header">
                <button class="accordion-button fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#fr1">
                  How can I download my approved report?
                </button>
              </h2>
              <div id="fr1" class="accordion-collapse collapse show" data-bs-parent="#faqRep">
                <div class="accordion-body text-secondary small">
                  Go to our <strong>Download Report</strong> page, enter your registered 10-digit mobile number, and provide your Patient ID or Accession Number. You will instantly get access to your digitally signed PDF report.
                </div>
              </div>
            </div>

            <div class="accordion-item border rounded-3 mb-3 overflow-hidden shadow-sm">
              <h2 class="accordion-header">
                <button class="accordion-button collapsed fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#fr2">
                  What is the QR code printed on my pathology report?
                </button>
              </h2>
              <div id="fr2" class="accordion-collapse collapse" data-bs-parent="#faqRep">
                <div class="accordion-body text-secondary small">
                  The QR code contains a tamper-evident digital verification token. When scanned by your doctor or hospital, it opens our secure verification portal to confirm that the report is genuine, unaltered, and approved by our registered pathologist.
                </div>
              </div>
            </div>

            <div class="accordion-item border rounded-3 mb-3 overflow-hidden shadow-sm">
              <h2 class="accordion-header">
                <button class="accordion-button collapsed fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#fr3">
                  Can I receive my pathology report on WhatsApp?
                </button>
              </h2>
              <div id="fr3" class="accordion-collapse collapse" data-bs-parent="#faqRep">
                <div class="accordion-body text-secondary small">
                  Yes! Once our pathologist approves and digitally signs your report, LabMaster can dispatch a secure notification with a direct PDF download link directly to your registered WhatsApp mobile number.
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="p-4 bg-light rounded-4 border text-center">
          <h5 class="fw-bold mb-2">Still have questions?</h5>
          <p class="text-muted small mb-3">Our medical laboratory technicians and patient counselors are available 7 days a week.</p>
          <div class="d-flex justify-content-center gap-3">
            <a href="<?= BASE_URL ?>/contact.php" class="btn btn-outline-lm">Contact Our Center</a>
            <a href="<?= WhatsAppService::buildChatUrl() ?>" target="_blank" class="btn btn-success">
              <i class="bi bi-whatsapp me-1"></i> WhatsApp Helpline
            </a>
          </div>
        </div>

      </div>
    </div>

  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

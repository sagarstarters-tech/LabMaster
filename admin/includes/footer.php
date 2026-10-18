<?php
/**
 * LabMaster - Admin Footer Component
 */

declare(strict_types=1);
?>
    </main>

    <!-- Admin Footer -->
    <footer class="bg-white border-top py-3 px-4 text-muted small d-flex flex-wrap justify-content-between align-items-center">
      <div>
        &copy; <?= date('Y') ?> <strong><?= e(Settings::get('lab_name', 'LabMaster Diagnostics')) ?></strong>. Version <?= APP_VERSION ?>
      </div>
      <div class="d-flex gap-3">
        <span>Standard: ISO 15189:2022</span>
        <span>•</span>
        <span class="text-success"><i class="bi bi-shield-check"></i> System Secure & Encrypted</span>
      </div>
    </footer>
  </div><!-- /.admin-wrapper -->

  <!-- Bootstrap 5.3 Bundle JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <!-- Chart.js for Admin Analytics -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <!-- Custom Admin JS -->
  <script src="<?= ASSETS_URL ?>/js/admin.js?v=<?= APP_VERSION ?>"></script>
</body>
</html>

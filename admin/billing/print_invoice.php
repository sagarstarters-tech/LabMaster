<?php
/**
 * LabMaster - Printable Medical Tax Invoice / Receipt
 */

declare(strict_types=1);

require_once dirname(dirname(__DIR__)) . '/config/config.php';
require_once dirname(dirname(__DIR__)) . '/includes/auth.php';

Auth::requirePermission('billing.manage');

$invoiceId = (int)($_GET['id'] ?? 0);
$invoice = Database::fetch(
    "SELECT i.*, p.full_name as patient_name, p.patient_id as patient_code, p.phone, p.address,
            o.accession_number, o.order_date,
            u.full_name as creator_name
     FROM `invoices` i
     JOIN `patients` p ON i.patient_id = p.id
     JOIN `test_orders` o ON i.order_id = o.id
     LEFT JOIN `users` u ON i.created_by = u.id
     WHERE i.id = ?",
    [$invoiceId]
);

if (!$invoice) {
    die('Invoice not found.');
}

$items = Database::fetchAll("SELECT * FROM `invoice_items` WHERE `invoice_id` = ?", [$invoiceId]);
$payments = Database::fetchAll("SELECT * FROM `payments` WHERE `invoice_id` = ? ORDER BY `id` ASC", [$invoiceId]);

$labName = Settings::get('lab_name', 'LabMaster Diagnostic & Research Center');
$labAddress = Settings::get('lab_address', 'Healthcare Square, Central Avenue, Metro City, India');
$labPhone = Settings::get('lab_phone', '+91 98765 43210');
$labEmail = Settings::get('lab_email', 'info@labmasterdiagnostics.com');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Invoice - <?= e($invoice['invoice_number']) ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { background: #ffffff; color: #000; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; font-size: 13px; padding: 25px; }
    .invoice-box { max-width: 800px; margin: 0 auto; border: 1px solid #ddd; padding: 25px; border-radius: 6px; }
    @media print {
      body { padding: 0; }
      .no-print { display: none !important; }
      .invoice-box { border: none; padding: 0; max-width: 100%; }
    }
  </style>
</head>
<body>

<div class="invoice-box">
  <div class="no-print d-flex justify-content-between align-items-center mb-4 p-2 bg-light rounded border">
    <span class="small text-muted">Laboratory Billing System</span>
    <button onclick="window.print()" class="btn btn-primary btn-sm">Print Invoice</button>
  </div>

  <div class="row align-items-center border-bottom pb-3 mb-3">
    <div class="col-7">
      <h3 class="h5 fw-bold text-primary mb-1"><?= e($labName) ?></h3>
      <div class="small text-muted"><?= e($labAddress) ?></div>
      <div class="small text-muted">Tel: <?= e($labPhone) ?> • <?= e($labEmail) ?></div>
    </div>
    <div class="col-5 text-end">
      <h4 class="h5 fw-bold text-dark mb-0">TAX INVOICE</h4>
      <div class="fw-bold font-monospace text-primary"><?= e($invoice['invoice_number']) ?></div>
      <div class="small text-muted">Date: <?= format_date($invoice['invoice_date']) ?></div>
    </div>
  </div>

  <div class="row mb-3 p-2 bg-light border rounded g-2" style="font-size: 12px;">
    <div class="col-6">
      <strong>Billed To:</strong> <?= e($invoice['patient_name']) ?> (<?= e($invoice['patient_code']) ?>)<br>
      <strong>Phone:</strong> <?= e($invoice['phone']) ?><br>
      <?= !empty($invoice['address']) ? '<strong>Address:</strong> ' . e($invoice['address']) : '' ?>
    </div>
    <div class="col-6 text-end">
      <strong>Accession No:</strong> <?= e($invoice['accession_number']) ?><br>
      <strong>Order Date:</strong> <?= format_date($invoice['order_date']) ?><br>
      <strong>Billed By:</strong> <?= e($invoice['creator_name'] ?: 'Billing Desk') ?>
    </div>
  </div>

  <table class="table table-bordered table-sm mb-3" style="font-size: 12px;">
    <thead class="table-light">
      <tr>
        <th>#</th>
        <th>Investigation Description</th>
        <th class="text-center">Qty</th>
        <th class="text-end">Unit Price</th>
        <th class="text-end">Amount</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($items as $idx => $it): ?>
        <tr>
          <td><?= $idx + 1 ?></td>
          <td><?= e($it['item_description']) ?></td>
          <td class="text-center"><?= $it['quantity'] ?></td>
          <td class="text-end"><?= format_currency($it['unit_price']) ?></td>
          <td class="text-end"><?= format_currency($it['total_amount']) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
    <tfoot>
      <tr>
        <td colspan="4" class="text-end fw-semibold">Subtotal:</td>
        <td class="text-end fw-bold"><?= format_currency($invoice['subtotal']) ?></td>
      </tr>
      <?php if ((float)$invoice['discount_amount'] > 0): ?>
      <tr>
        <td colspan="4" class="text-end fw-semibold text-success">Discount:</td>
        <td class="text-end fw-bold text-success">- <?= format_currency($invoice['discount_amount']) ?></td>
      </tr>
      <?php endif; ?>
      <tr>
        <td colspan="4" class="text-end fw-bold">Grand Total:</td>
        <td class="text-end fw-bold text-primary"><?= format_currency($invoice['grand_total']) ?></td>
      </tr>
      <tr>
        <td colspan="4" class="text-end fw-semibold">Amount Paid:</td>
        <td class="text-end fw-bold text-success"><?= format_currency($invoice['paid_amount']) ?></td>
      </tr>
      <tr>
        <td colspan="4" class="text-end fw-bold">Balance Due:</td>
        <td class="text-end fw-bold text-danger"><?= format_currency($invoice['balance_amount']) ?></td>
      </tr>
    </tfoot>
  </table>

  <?php if (!empty($payments)): ?>
    <div class="mb-3">
      <div class="fw-bold small border-bottom pb-1 mb-2">Payment Transactions History</div>
      <table class="table table-sm table-bordered" style="font-size: 11px;">
        <thead class="table-light">
          <tr>
            <th>Receipt No</th>
            <th>Mode</th>
            <th>Date</th>
            <th>Transaction Ref</th>
            <th class="text-end">Amount</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($payments as $pay): ?>
            <tr>
              <td><?= e($pay['receipt_number']) ?></td>
              <td><?= e($pay['payment_method']) ?></td>
              <td><?= format_date($pay['payment_date']) ?></td>
              <td><?= e($pay['transaction_reference'] ?: '-') ?></td>
              <td class="text-end fw-bold text-success"><?= format_currency($pay['amount']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

  <div class="row align-items-end pt-4 mt-4 border-top">
    <div class="col-8" style="font-size: 10px;">
      <strong>Terms & Conditions:</strong><br>
      1. Diagnostic test charges are inclusive of standard consumables and certified digital reporting.<br>
      2. Payment once made is non-refundable after sample processing has commenced.<br>
      <em>Thank you for trusting <?= e($labName) ?>.</em>
    </div>
    <div class="col-4 text-end">
      <div class="fw-bold" style="font-size: 11px;">Authorized Cashier</div>
      <div class="text-muted" style="font-size: 10px;">Billing Department</div>
    </div>
  </div>
</div>

</body>
</html>

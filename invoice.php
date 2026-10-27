<?php
declare(strict_types=1);

require __DIR__.'/includes/bootstrap.php';
require_once __DIR__.'/includes/pdf.php';
require_once __DIR__.'/includes/qrcode.php';

$u = user();
if (!$u) {
    redirect('login.php');
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(404);
    exit('Invalid invoice request.');
}

// Ensure invoice access control
try {
    $b = booking($id, $u);
} catch (DomainException $e) {
    http_response_code(403);
    exit('Access denied to this invoice.');
}

// Delivery role does not have general invoice access unless admin or customer
if ($u['role'] === 'delivery') {
    http_response_code(403);
    exit('Access denied.');
}

// Stable invoice identifier check & preservation
if (empty($b['invoice_number'])) {
    $invNum = 'INV-' . date('ymd', strtotime($b['created_at'])) . '-' . str_pad((string)$b['id'], 4, '0', STR_PAD_LEFT) . '-' . strtoupper(substr(md5($b['id'] . $b['booking_number']), 0, 4));
    query('UPDATE bookings SET invoice_number = ? WHERE id = ?', [$invNum, $b['id']]);
    $b['invoice_number'] = $invNum;
}

// Tracking token check
if (empty($b['tracking_token'])) {
    $token = hash('sha256', $b['id'] . $b['booking_number'] . 'smart_laundry_salt_2026');
    query('UPDATE bookings SET tracking_token = ? WHERE id = ?', [$token, $b['id']]);
    $b['tracking_token'] = $token;
}

$items = query('SELECT * FROM booking_items WHERE booking_id = ? ORDER BY id ASC', [$b['id']])->fetchAll();
$trackingUrl = app_base_url() . '/track.php?token=' . urlencode($b['tracking_token']);
$qrSvg = SmartLaundryQr::getSvg($trackingUrl, 130, '#0f766e', '#ffffff');

$download = isset($_GET['download']) || ($_GET['format'] ?? '') === 'pdf';

// --------------------------------------------------------------------------
// PDF STREAMING OUTPUT
// --------------------------------------------------------------------------
if ($download) {
    $pdf = new SmartLaundryPdf();
    
    // Header Bar
    $pdf->setFillColor(15, 118, 110); // Brand Teal #0f766e
    $pdf->rect(40, 40, 515.28, 65, 'F');
    
    $pdf->setFont('Helvetica-Bold', 18);
    $pdf->setDrawColor(255, 255, 255);
    $pdf->text(55, 68, 'SMART LAUNDRY');
    
    $pdf->setFont('Helvetica', 10);
    $pdf->text(55, 84, 'Fresh clothes. Fresh starts. Doorstep delivery.');
    
    $pdf->setFont('Helvetica-Bold', 14);
    $pdf->text(410, 68, 'TAX INVOICE');
    $pdf->setFont('Helvetica', 9);
    $pdf->text(410, 84, 'Date: ' . date('d M Y', strtotime($b['created_at'])));
    
    // Metadata Section
    $pdf->setXY(40, 120);
    $pdf->setFont('Helvetica-Bold', 10);
    $pdf->text(40, 130, 'INVOICE DETAILS:');
    $pdf->setFont('Helvetica', 9);
    $pdf->text(40, 145, 'Invoice Number: ' . $b['invoice_number']);
    $pdf->text(40, 160, 'Booking Reference: ' . $b['booking_number']);
    $pickupDateStr = date('d M Y', strtotime($b['pickup_date'])) . (!empty($b['slot_name']) ? ' (' . $b['slot_name'] . ')' : '');
    $pdf->text(40, 175, 'Pickup Date: ' . $pickupDateStr);
    $pdf->text(40, 190, 'Payment Method: Cash on Delivery (COD)');
    $pdf->text(40, 205, 'Payment Status: ' . ($b['payment_status'] === 'Paid' ? 'PAID (' . date('d M Y', strtotime($b['paid_at'] ?? 'now')) . ')' : 'UNPAID (Pending collection)'));
    
    $pdf->setFont('Helvetica-Bold', 10);
    $pdf->text(320, 130, 'BILLED TO:');
    $pdf->setFont('Helvetica', 9);
    $pdf->text(320, 145, 'Customer: ' . $b['customer_name']);
    $pdf->text(320, 160, 'Phone: ' . $b['pickup_phone']);
    $pdf->text(320, 175, 'Address: ' . substr($b['pickup_address'], 0, 40));
    if (strlen($b['pickup_address']) > 40) {
        $pdf->text(320, 187, substr($b['pickup_address'], 40, 45));
    }
    
    // Line separator
    $pdf->setDrawColor(226, 232, 240);
    $pdf->setLineWidth(1.0);
    $pdf->line(40, 220, 555.28, 220);
    
    // Items Table Header
    $pdf->setXY(40, 235);
    $pdf->setFillColor(241, 245, 249);
    $pdf->setFont('Helvetica-Bold', 9);
    $pdf->cell(240, 22, ' SERVICE DESCRIPTION', 1, 0, 'L', true);
    $pdf->cell(75, 22, 'QTY', 1, 0, 'C', true);
    $pdf->cell(100, 22, 'UNIT PRICE (LKR)', 1, 0, 'R', true);
    $pdf->cell(100.28, 22, 'SUBTOTAL (LKR)', 1, 1, 'R', true);
    
    // Items Table Rows
    $pdf->setFont('Helvetica', 9);
    $alt = false;
    foreach ($items as $it) {
        $pdf->setFillColor($alt ? 248 : 255, $alt ? 250 : 255, $alt ? 252 : 255);
        $pdf->cell(240, 20, ' ' . $it['service_name'], 1, 0, 'L', true);
        $pdf->cell(75, 20, (string)$it['quantity'], 1, 0, 'C', true);
        $pdf->cell(100, 20, number_format((float)$it['unit_price'], 2), 1, 0, 'R', true);
        $pdf->cell(100.28, 20, number_format((float)$it['subtotal'], 2), 1, 1, 'R', true);
        $alt = !$alt;
    }
    
    // Discount / Subtotal Rows if coupon applied
    if ((float)$b['discount_amount'] > 0) {
        $pdf->setFont('Helvetica', 9);
        $pdf->setFillColor(241, 245, 249);
        $pdf->cell(415, 20, ' Subtotal (LKR)', 1, 0, 'R', true);
        $pdf->cell(100.28, 20, number_format((float)$b['subtotal_amount'], 2), 1, 1, 'R', true);
        
        $pdf->cell(415, 20, ' Coupon Discount (' . ($b['coupon_code'] ?? 'PROMO') . ')', 1, 0, 'R', true);
        $pdf->cell(100.28, 20, '- ' . number_format((float)$b['discount_amount'], 2), 1, 1, 'R', true);
    }
    
    // Total Row
    $pdf->setFont('Helvetica-Bold', 10);
    $pdf->setFillColor(241, 245, 249);
    $pdf->cell(415, 24, ' GRAND TOTAL (LKR)', 1, 0, 'R', true);
    $pdf->setFillColor(209, 250, 229); // Soft Teal #d1fae5
    $pdf->cell(100.28, 24, 'LKR ' . number_format((float)$b['total_amount'], 2), 1, 1, 'R', true);
    
    // Tracking & Footer Section
    $currentY = $pdf->getY() + 30;
    $pdf->setDrawColor(226, 232, 240);
    $pdf->line(40, $currentY, 555.28, $currentY);
    
    $pdf->setFont('Helvetica-Bold', 9);
    $pdf->text(40, $currentY + 20, 'TRACK YOUR LAUNDRY ORDER:');
    $pdf->setFont('Helvetica', 8);
    $pdf->text(40, $currentY + 34, 'Online Tracking Link: ' . substr($trackingUrl, 0, 75));
    $pdf->text(40, $currentY + 48, 'Scan the QR code on your order label to see live washing & delivery progress.');
    $pdf->text(40, $currentY + 68, 'Thank you for choosing Smart Laundry! We treat every garment with premium care.');
    
    $pdf->setFont('Helvetica', 8);
    $pdf->text(40, 790, 'Smart Laundry System * Official Customer Invoice * Generated on ' . date('d M Y, H:i'));
    
    $pdf->output('D', 'Invoice-' . $b['invoice_number'] . '.pdf');
    exit;
}

// --------------------------------------------------------------------------
// HTML INVOICE PREVIEW VIEW
// --------------------------------------------------------------------------
$title = 'Invoice ' . $b['invoice_number'];
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title) ?> · Smart Laundry</title>
  <script>
  (function(){
    try{
      var t=localStorage.getItem('smart_laundry_theme');
      var theme=(t==='dark')?'dark':'light';
      document.documentElement.setAttribute('data-theme',theme);
      document.documentElement.setAttribute('data-bs-theme',theme);
    }catch(e){}
  })();
  </script>
  <link href="<?= e(url('assets/vendor/bootstrap.min.css')) ?>" rel="stylesheet">
  <link href="<?= e(url('assets/vendor/bootstrap-icons.min.css')) ?>" rel="stylesheet">
  <link href="<?= e(url('assets/css/style.css')) ?>" rel="stylesheet">
  <style>
    .invoice-wrapper { max-width: 860px; margin: 30px auto; }
    .invoice-card { background: var(--surface, #fff); border: 1px solid var(--line, #e8edf2); border-radius: 16px; padding: 40px; box-shadow: 0 4px 20px rgba(0,0,0,0.04); }
    .invoice-toolbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; gap: 12px; }
    .invoice-header { display: flex; justify-content: space-between; align-items: flex-start; padding-bottom: 24px; border-bottom: 2px solid var(--line, #e8edf2); margin-bottom: 28px; }
    .invoice-title { font-size: 26px; font-weight: 800; color: var(--primary, #0f766e); margin: 0 0 4px; }
    .invoice-meta-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-bottom: 30px; }
    .invoice-table th { background: var(--surface-subtle, #f8fafc); font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; }
    .invoice-table td, .invoice-table th { padding: 12px 14px; }
    .invoice-footer { margin-top: 35px; padding-top: 25px; border-top: 1px solid var(--line, #e8edf2); display: flex; justify-content: space-between; align-items: center; gap: 20px; }
    .qr-box { display: flex; align-items: center; gap: 14px; }
    .qr-box small { display: block; font-size: 11px; color: var(--muted, #64748b); max-width: 220px; }
    @media print {
      .invoice-toolbar, .topbar, .sidebar, .footer { display: none !important; }
      .invoice-wrapper { margin: 0; max-width: 100%; }
      .invoice-card { border: 0; box-shadow: none; padding: 0; }
      body { background: #fff !important; color: #000 !important; }
    }
  </style>
</head>
<body class="workspace">
  <div class="container invoice-wrapper">
    <div class="invoice-toolbar">
      <a href="<?= e(url(($u['role'] === 'admin' ? 'admin/booking_details.php' : 'customer/booking_details.php') . '?id=' . $b['id'])) ?>" class="btn btn-light btn-sm">
        <i class="bi bi-arrow-left"></i> Back to booking
      </a>
      <div class="d-flex align-items-center gap-2">
        <button type="button" class="btn-theme-toggle" id="theme-toggle" aria-label="Toggle dark and light mode" title="Switch theme" style="padding: 6px 11px; font-size: 12px;">
          <i class="bi bi-moon-stars" id="theme-icon"></i>
          <span class="theme-text" id="theme-text">Dark</span>
        </button>
        <button type="button" class="btn btn-light btn-sm" onclick="window.print()">
          <i class="bi bi-printer"></i> Print invoice
        </button>
        <a href="<?= e(url('invoice.php?id=' . $b['id'] . '&download=1')) ?>" class="btn btn-primary btn-sm">
          <i class="bi bi-file-earmark-pdf"></i> Download PDF
        </a>
      </div>
    </div>

    <div class="invoice-card">
      <div class="invoice-header">
        <div>
          <div class="d-flex align-items-center gap-2 mb-2">
            <span class="brand-icon"><i class="bi bi-droplet-half"></i></span>
            <span class="brand" style="font-size:22px;">smart<span>laundry</span><span class="brand-dot">.</span></span>
          </div>
          <p class="text-muted mb-0" style="font-size:12px;">Premium Laundry & Dry Cleaning Services</p>
          <p class="text-muted mb-0" style="font-size:12px;">Doorstep Pickup & Delivery * Cash on Delivery</p>
        </div>
        <div class="text-end">
          <h1 class="invoice-title">TAX INVOICE</h1>
          <p class="mb-1"><strong><?= e($b['invoice_number']) ?></strong></p>
          <p class="text-muted mb-0" style="font-size:12px;">Date: <?= date('d M Y', strtotime($b['created_at'])) ?></p>
        </div>
      </div>

      <div class="invoice-meta-grid">
        <div>
          <h6 class="text-muted text-uppercase mb-2" style="font-size:11px; letter-spacing:0.8px;">Customer Information</h6>
          <strong style="font-size:15px;"><?= e($b['customer_name']) ?></strong>
          <div class="text-muted mt-1" style="font-size:13px;"><?= e($b['pickup_phone']) ?></div>
          <div class="text-muted" style="font-size:13px;"><?= nl2br(e($b['pickup_address'])) ?></div>
        </div>
        <div class="text-md-end">
          <h6 class="text-muted text-uppercase mb-2" style="font-size:11px; letter-spacing:0.8px;">Order & Payment Info</h6>
          <div>Booking Ref: <strong><?= e($b['booking_number']) ?></strong></div>
          <div>Pickup Date: <strong><?= date('d M Y', strtotime($b['pickup_date'])) ?><?= !empty($b['slot_name']) ? ' (' . e($b['slot_name']) . ')' : '' ?></strong></div>
          <div>Payment Method: <strong>Cash on Delivery (COD)</strong></div>
          <div class="mt-1">
            Status: <?= badge($b['payment_status']) ?>
            <?php if ($b['paid_at']): ?>
              <small class="text-muted d-block">Collected on <?= date('d M Y, h:i A', strtotime($b['paid_at'])) ?></small>
            <?php endif ?>
          </div>
        </div>
      </div>

      <div class="table-responsive">
        <table class="table table-bordered invoice-table align-middle">
          <thead>
            <tr>
              <th>Service Item</th>
              <th class="text-center" style="width: 100px;">Quantity</th>
              <th class="text-end" style="width: 140px;">Unit Price</th>
              <th class="text-end" style="width: 150px;">Subtotal</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($items as $item): ?>
              <tr>
                <td>
                  <strong><?= e($item['service_name']) ?></strong>
                </td>
                <td class="text-center"><?= (int)$item['quantity'] ?></td>
                <td class="text-end"><?= money($item['unit_price']) ?></td>
                <td class="text-end"><strong><?= money($item['subtotal']) ?></strong></td>
              </tr>
            <?php endforeach ?>
          </tbody>
          <tfoot>
            <?php if ((float)$b['discount_amount'] > 0): ?>
              <tr>
                <th colspan="3" class="text-end">Subtotal</th>
                <th class="text-end"><?= money($b['subtotal_amount']) ?></th>
              </tr>
              <tr>
                <th colspan="3" class="text-end text-success">Coupon Discount (<?= e($b['coupon_code']) ?>)</th>
                <th class="text-end text-success">- <?= money($b['discount_amount']) ?></th>
              </tr>
            <?php endif ?>
            <tr>
              <th colspan="3" class="text-end">Grand Total</th>
              <th class="text-end" style="font-size:16px; color:var(--primary, #0f766e);">
                <?= money($b['total_amount']) ?>
              </th>
            </tr>
          </tfoot>
        </table>
      </div>

      <div class="invoice-footer">
        <div class="qr-box">
          <div style="width:84px; height:84px;"><?= $qrSvg ?></div>
          <div>
            <strong>Scan to track order</strong>
            <small>Scan this QR code with any smartphone to track live laundry status.</small>
          </div>
        </div>
        <div class="text-end">
          <p class="mb-1" style="font-weight:600; color:var(--primary, #0f766e);">Thank you for trusting Smart Laundry!</p>
          <small class="text-muted">For inquiries, present your booking reference: <?= e($b['booking_number']) ?></small>
        </div>
      </div>
    </div>
  </div>
  <script src="<?= e(url('assets/js/app.js')) ?>"></script>
</body>
</html>


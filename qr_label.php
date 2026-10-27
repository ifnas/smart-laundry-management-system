<?php
declare(strict_types=1);

require __DIR__.'/includes/bootstrap.php';
require_once __DIR__.'/includes/qrcode.php';

$u = protect();
$id = (int)($_GET['id'] ?? 0);
try {
    $b = booking($id, $u);
} catch (DomainException $e) {
    http_response_code(404);
    exit('Booking not found.');
}

if (empty($b['tracking_token'])) {
    $token = hash('sha256', $b['id'] . $b['booking_number'] . 'smart_laundry_salt_2026');
    query('UPDATE bookings SET tracking_token = ? WHERE id = ?', [$token, $b['id']]);
    $b['tracking_token'] = $token;
}

$items = query('SELECT * FROM booking_items WHERE booking_id = ?', [$b['id']])->fetchAll();
$trackingUrl = app_base_url() . '/track.php?token=' . urlencode($b['tracking_token']);
$qrSvg = SmartLaundryQr::getSvg($trackingUrl, 160, '#0f172a', '#ffffff');
$autoPrint = isset($_GET['print']);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Order Label - <?= e($b['booking_number']) ?></title>
  <link href="<?= e(url('assets/vendor/bootstrap.min.css')) ?>" rel="stylesheet">
  <link href="<?= e(url('assets/vendor/bootstrap-icons.min.css')) ?>" rel="stylesheet">
  <style>
    body { background: #f1f5f9; font-family: Inter, "Segoe UI", sans-serif; margin: 0; padding: 20px; color: #0f172a; }
    .label-box {
      max-width: 420px;
      margin: 20px auto;
      background: #fff;
      border: 2px dashed #94a3b8;
      border-radius: 12px;
      padding: 24px;
      box-shadow: 0 4px 15px rgba(0,0,0,0.05);
    }
    .label-header { border-bottom: 2px solid #0f172a; padding-bottom: 12px; margin-bottom: 14px; display: flex; justify-content: space-between; align-items: center; }
    .booking-large { font-size: 22px; font-weight: 800; letter-spacing: -0.5px; }
    .qr-container { display: flex; justify-content: center; margin: 16px 0; }
    .actions-bar { max-width: 420px; margin: 0 auto 15px; display: flex; justify-content: space-between; }
    @media print {
      body { background: #fff; padding: 0; }
      .actions-bar { display: none !important; }
      .label-box { border: 2px solid #000; box-shadow: none; margin: 0; width: 100%; max-width: 100%; }
    }
  </style>
</head>
<body <?= $autoPrint ? 'onload="window.print()"' : '' ?>>
  <div class="actions-bar">
    <a href="javascript:history.back()" class="btn btn-sm btn-outline-secondary">
      <i class="bi bi-arrow-left"></i> Back
    </a>
    <button type="button" class="btn btn-sm btn-primary" onclick="window.print()">
      <i class="bi bi-printer"></i> Print label
    </button>
  </div>

  <div class="label-box">
    <div class="label-header">
      <div>
        <h5 class="mb-0 fw-bold" style="color:#0f766e;"><i class="bi bi-droplet-half"></i> SMART LAUNDRY</h5>
        <small class="text-muted">Laundry Care Label</small>
      </div>
      <div class="text-end">
        <span class="badge bg-secondary"><?= e($b['booking_status']) ?></span>
      </div>
    </div>

    <div class="text-center">
      <div class="text-muted text-uppercase" style="font-size:10px; font-weight:700;">Booking Reference</div>
      <div class="booking-large"><?= e($b['booking_number']) ?></div>
      <div class="text-muted" style="font-size:12px;">Pickup Date: <?= date('d M Y', strtotime($b['pickup_date'])) ?></div>
    </div>

    <div class="qr-container">
      <div style="width:160px; height:160px;"><?= $qrSvg ?></div>
    </div>

    <div class="text-center mb-3">
      <small class="text-muted d-block">Scan with mobile camera to view order updates</small>
      <code style="font-size:10px; color:#64748b;"><?= substr($trackingUrl, 0, 48) ?>...</code>
    </div>

    <div class="border-top pt-2" style="font-size:12px;">
      <div class="d-flex justify-content-between">
        <span class="text-muted">Customer:</span>
        <strong><?= e($b['customer_name']) ?></strong>
      </div>
      <div class="d-flex justify-content-between mt-1">
        <span class="text-muted">Items:</span>
        <span>
          <?php foreach ($items as $it): ?>
            <?= e($it['service_name']) ?> (<?= $it['quantity'] ?>) 
          <?php endforeach ?>
        </span>
      </div>
      <?php if (!empty($b['special_instructions'])): ?>
        <div class="mt-1 text-muted" style="font-size:11px;">
          Note: <?= e(substr($b['special_instructions'], 0, 80)) ?>
        </div>
      <?php endif ?>
    </div>
  </div>
</body>
</html>


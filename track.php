<?php
declare(strict_types=1);

require __DIR__.'/includes/bootstrap.php';
require_once __DIR__.'/includes/qrcode.php';

$token = trim((string)($_GET['token'] ?? ''));
$b = null;
$events = [];

if ($token !== '') {
    $b = one('SELECT id, booking_number, booking_status, pickup_date, created_at, updated_at, customer_id FROM bookings WHERE tracking_token = ?', [$token]);
    if ($b) {
        $events = query('SELECT status, created_at FROM booking_status_history WHERE booking_id = ? ORDER BY id ASC', [$b['id']])->fetchAll();
    }
}

$u = user();
$title = $b ? 'Track Order ' . $b['booking_number'] : 'Order Tracking';
$steps = ['Pending', 'Accepted', 'Picked Up', 'Washing', 'Ironing', 'Ready for Delivery', 'Out for Delivery', 'Delivered'];
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
    .track-wrapper { max-width: 680px; margin: 40px auto; padding: 0 15px; }
    .track-card { background: var(--surface, #fff); border: 1px solid var(--line, #e8edf2); border-radius: 16px; padding: 35px; box-shadow: 0 4px 20px rgba(0,0,0,0.03); }
    .track-brand { display: flex; align-items: center; justify-content: space-between; margin-bottom: 25px; padding-bottom: 20px; border-bottom: 1px solid var(--line, #e8edf2); }
    .status-large { font-size: 20px; font-weight: 700; display: inline-flex; align-items: center; gap: 10px; }
  </style>
</head>
<body class="public-page">
  <div class="track-wrapper">
    <div class="track-card">
      <div class="track-brand">
        <a class="brand" href="<?= e(url('index.php')) ?>" style="font-size:20px;">
          <span class="brand-icon"><i class="bi bi-droplet-half"></i></span>smart<span>laundry</span><span class="brand-dot">.</span>
        </a>
        <button type="button" class="btn-theme-toggle" id="theme-toggle" aria-label="Toggle theme">
          <i class="bi bi-moon-stars" id="theme-icon"></i>
          <span class="theme-text" id="theme-text">Dark</span>
        </button>
      </div>

      <?php if (!$b): ?>
        <div class="text-center py-4">
          <i class="bi bi-search text-muted" style="font-size:42px;"></i>
          <h3 class="mt-3">Tracking reference not found</h3>
          <p class="text-muted">The tracking QR code or link you followed is invalid or has expired.</p>
          <a href="<?= e(url('login.php')) ?>" class="btn btn-primary mt-2">Sign in to check bookings</a>
        </div>
      <?php else: ?>
        <div class="d-flex justify-content-between align-items-center mb-3">
          <div>
            <div class="eyebrow">ORDER TRACKING</div>
            <h2 class="mb-0" style="font-size:22px; font-weight:750;"><?= e($b['booking_number']) ?></h2>
          </div>
          <div><?= badge($b['booking_status']) ?></div>
        </div>

        <p class="text-muted" style="font-size:13px;">
          Pickup Date: <strong><?= date('D, d M Y', strtotime($b['pickup_date'])) ?></strong> · 
          Last update: <strong><?= date('d M Y, h:i A', strtotime($b['updated_at'])) ?></strong>
        </p>

        <!-- Progress Tracker -->
        <div class="progress-track my-4">
          <?php 
          $currentIdx = array_search($b['booking_status'], $steps, true);
          $reached = array_column($events, 'status');
          foreach ($steps as $i => $s): 
              $isDone = in_array($s, $reached, true);
              $isCurrent = ($b['booking_status'] === $s);
          ?>
            <div class="track-step <?= $isDone ? 'done' : '' ?> <?= $isCurrent ? 'current' : '' ?>">
              <span><?= $isDone ? '<i class="bi bi-check-lg"></i>' : ($i + 1) ?></span>
              <small><?= e($s) ?></small>
            </div>
          <?php endforeach ?>
        </div>

        <?php if (in_array($b['booking_status'], ['Cancelled', 'Rejected'])): ?>
          <div class="alert alert-danger">
            <i class="bi bi-x-circle me-1"></i> This order was <?= strtolower($b['booking_status']) ?>.
          </div>
        <?php endif ?>

        <!-- Order Activity Timeline -->
        <h5 class="mt-4 mb-3" style="font-size:15px; font-weight:700;">Status Updates</h5>
        <?php if ($events): ?>
          <ol class="timeline">
            <?php foreach (array_reverse($events) as $ev): ?>
              <li>
                <div><strong><?= e($ev['status']) ?></strong></div>
                <small><?= date('d M Y, h:i A', strtotime($ev['created_at'])) ?></small>
              </li>
            <?php endforeach ?>
          </ol>
        <?php else: ?>
          <p class="text-muted" style="font-size:12px;">Order recorded and pending pickup schedule.</p>
        <?php endif ?>

        <!-- Access Controls -->
        <div class="mt-4 pt-3 border-top d-flex justify-content-between align-items-center">
          <?php if ($u && ($u['role'] === 'admin' || $u['id'] == $b['customer_id'])): ?>
            <a href="<?= e(url(($u['role'] === 'admin' ? 'admin/booking_details.php' : 'customer/booking_details.php') . '?id=' . $b['id'])) ?>" class="btn btn-outline-primary btn-sm">
              <i class="bi bi-arrow-up-right"></i> Open full booking details
            </a>
          <?php else: ?>
            <a href="<?= e(url('login.php')) ?>" class="text-link">Sign in for full order options →</a>
          <?php endif ?>
          <span class="text-muted" style="font-size:11px;">Protected by Smart Laundry</span>
        </div>
      <?php endif ?>
    </div>
  </div>
  <script src="<?= e(url('assets/js/app.js')) ?>"></script>
</body>
</html>


<?php
protect();
$stats = rating_stats();
$isAdmin = ($u['role'] === 'admin');

if ($isAdmin) {
    $title = 'Customer Reviews & Feedback';
    $subtitle = 'Monitor service quality, customer ratings, and direct feedback.';
    $reviews = query("SELECT r.*, u.name as customer_name, b.booking_number 
                      FROM reviews r 
                      JOIN users u ON u.id = r.customer_id 
                      JOIN bookings b ON b.id = r.booking_id 
                      ORDER BY r.id DESC")->fetchAll();
} else {
    $title = 'My Reviews & Feedback';
    $subtitle = 'Past ratings and feedback you shared for completed orders.';
    $reviews = query("SELECT r.*, b.booking_number 
                      FROM reviews r 
                      JOIN bookings b ON b.id = r.booking_id 
                      WHERE r.customer_id = ? 
                      ORDER BY r.id DESC", [$u['id']])->fetchAll();
}

require __DIR__ . '/header.php';
?>

<?php if ($isAdmin): ?>
  <!-- Admin Overview: Stats & Distribution -->
  <div class="row g-4 mb-4">
    <div class="col-md-4">
      <div class="panel text-center h-100 d-flex flex-column justify-content-center">
        <h6 class="text-muted text-uppercase mb-2" style="font-size:12px; letter-spacing:0.5px;">Average Service Rating</h6>
        <div style="font-size:48px; font-weight:800; color:var(--primary, #0f766e); line-height:1;">
          <?= number_format($stats['avg'], 1) ?>
        </div>
        <div class="my-2" style="color:#f59e0b; font-size:18px;">
          <?php for ($i = 1; $i <= 5; $i++): ?>
            <i class="bi bi-star<?= $i <= round($stats['avg']) ? '-fill' : '' ?>"></i>
          <?php endfor ?>
        </div>
        <small class="text-muted">Based on <?= $stats['total'] ?> verified order reviews</small>
      </div>
    </div>

    <div class="col-md-8">
      <div class="panel h-100">
        <div class="panel-heading mb-3">
          <div>
            <h2 class="h5 mb-1">Rating Distribution</h2>
            <p class="small text-muted mb-0">Star breakdown across all customer reviews</p>
          </div>
        </div>
        <?php for ($star = 5; $star >= 1; $star--): 
          $cnt = (int)($stats['dist'][$star] ?? 0);
          $pct = $stats['total'] > 0 ? round(($cnt / $stats['total']) * 100, 1) : 0;
        ?>
          <div class="d-flex align-items-center gap-3 mb-2">
            <span style="width:50px; font-weight:600; font-size:13px;"><?= $star ?> <i class="bi bi-star-fill text-warning"></i></span>
            <div class="progress flex-grow-1" style="height:8px;">
              <div class="progress-bar bg-warning" role="progressbar" style="width: <?= $pct ?>%"></div>
            </div>
            <span class="text-muted small" style="width:65px; text-align:right;"><?= $cnt ?> (<?= $pct ?>%)</span>
          </div>
        <?php endfor ?>
      </div>
    </div>
  </div>
<?php endif ?>

<section class="panel">
  <div class="panel-heading">
    <div>
      <h2><?= $isAdmin ? 'Customer Feedback Feed' : 'My Order Ratings' ?></h2>
      <p><?= $isAdmin ? 'Verified feedback from completed deliveries.' : 'Your ratings and comments on fresh laundry deliveries.' ?></p>
    </div>
  </div>

  <?php if (!$reviews): ?>
    <div class="empty py-4">
      <i class="bi bi-chat-square-quote text-muted" style="font-size:32px;"></i>
      <p class="mt-2 mb-0"><?= $isAdmin ? 'No customer reviews submitted yet.' : 'You have not reviewed any completed orders yet.' ?></p>
    </div>
  <?php else: ?>
    <div class="list-group list-group-flush">
      <?php foreach ($reviews as $rev): ?>
        <div class="list-group-item px-0 py-3 border-bottom">
          <div class="d-flex justify-content-between align-items-start mb-1">
            <div>
              <strong style="font-size:15px;"><?= e($rev['customer_name'] ?? $u['name']) ?></strong>
              <small class="text-muted ms-2">Booking <strong><?= e($rev['booking_number']) ?></strong></small>
            </div>
            <div class="text-warning">
              <?php for ($s = 1; $s <= 5; $s++): ?>
                <i class="bi bi-star<?= $s <= (int)$rev['rating'] ? '-fill' : '' ?>"></i>
              <?php endfor ?>
            </div>
          </div>
          <?php if (!empty($rev['review_text'])): ?>
            <p class="mb-1 text-secondary" style="font-size:14px;"><?= nl2br(e($rev['review_text'])) ?></p>
          <?php endif ?>
          <small class="text-muted"><?= date('d M Y, h:i A', strtotime($rev['created_at'])) ?></small>
        </div>
      <?php endforeach ?>
    </div>
  <?php endif ?>
</section>


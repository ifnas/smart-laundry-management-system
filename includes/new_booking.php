<?php 
$title='Book a pickup'; 
$subtitle='A few details now. Fresh laundry soon.'; 
$services=query('SELECT * FROM services WHERE is_active=1 ORDER BY id')->fetchAll(); 
$slots=query('SELECT * FROM pickup_slots WHERE is_active=1 ORDER BY start_time ASC')->fetchAll();
require __DIR__.'/header.php'; 
?>
<form method="post" action="<?= e(url('actions/handle.php')) ?>" id="booking-form">
<?= csrf() ?>
<input type="hidden" name="action" value="create_booking">
<div class="booking-layout">
<div>
<section class="panel">
  <div class="panel-heading">
    <div><span class="eyebrow">STEP 01</span><h2>Choose your services</h2><p>Prices are per item. Enter a quantity for each service.</p></div>
    <i class="bi bi-basket2"></i>
  </div>
<?php if(!$services): ?><p>No services are available right now. Please check back later.</p><?php endif ?>
<?php foreach($services as $s): ?>
  <div class="booking-service">
    <span class="service-icon"><i class="bi bi-droplet"></i></span>
    <div class="service-info">
      <strong><?= e($s['service_name']) ?></strong>
      <small><?= e($s['description']) ?></small>
      <span><?= money($s['price']) ?> / item</span>
    </div>
    <div class="qty-control">
      <label for="qty-<?= $s['id'] ?>">Quantity</label>
      <input id="qty-<?= $s['id'] ?>" class="form-control qty" name="quantity[<?= $s['id'] ?>]" type="number" min="0" max="100" step="1" value="0" data-price="<?= e($s['price']) ?>" data-service="<?= e($s['service_name']) ?>">
    </div>
    <strong class="line-subtotal">LKR 0.00</strong>
  </div>
<?php endforeach ?>
</section>

<section class="panel mt-4">
  <span class="eyebrow">STEP 02</span>
  <h2>Pickup details</h2>
  <div class="form-grid">
    <label>Phone number
      <input class="form-control" type="tel" name="pickup_phone" maxlength="25" value="<?= e($u['phone']) ?>" required>
    </label>
    <label>Pickup date
      <input class="form-control" type="date" name="pickup_date" id="pickup_date" min="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d',strtotime('+90 days')) ?>" required>
    </label>
  </div>
  <label class="mt-2">Pickup time window <span class="muted">(Optional)</span>
    <select class="form-select" name="pickup_slot_id" id="pickup_slot_id">
      <option value="">Any time (Standard collection)</option>
      <?php foreach($slots as $sl): ?>
        <option value="<?= $sl['id'] ?>"><?= e($sl['slot_name']) ?></option>
      <?php endforeach ?>
    </select>
  </label>
  <label class="mt-2">Pickup address
    <textarea class="form-control" name="pickup_address" maxlength="2000" rows="3" required><?= e($u['address']) ?></textarea>
  </label>
  <label class="mt-2">Special instructions <span class="muted">(optional)</span>
    <textarea class="form-control" name="special_instructions" maxlength="2000" rows="3" placeholder="Anything we should know about your garments or pickup?"></textarea>
  </label>
</section>
</div>

<aside class="panel order-summary">
  <span class="eyebrow">YOUR FRESH START</span>
  <h2>Booking summary</h2>
  <div id="summary-lines"><p class="muted">Choose a service to get started.</p></div>

  <!-- Coupon / Promo code section -->
  <div class="coupon-section my-3 pt-3 border-top">
    <label for="coupon_code" class="form-label small fw-bold mb-1">Promo / Coupon Code</label>
    <div class="input-group input-group-sm">
      <input type="text" name="coupon_code" id="coupon_code" class="form-control text-uppercase" placeholder="e.g. SUMMER25" style="letter-spacing:1px; font-weight:600;">
      <button type="button" class="btn btn-outline-primary" id="btn-apply-coupon">Apply</button>
    </div>
    <div id="coupon-feedback" class="small mt-1 d-none"></div>
  </div>

  <div id="subtotal-row" class="summary-line d-none">
    <span>Subtotal</span>
    <strong id="subtotal-amount">LKR 0.00</strong>
  </div>
  <div id="discount-row" class="summary-line text-success d-none">
    <span>Coupon Discount</span>
    <strong id="discount-amount">- LKR 0.00</strong>
  </div>

  <div class="summary-total mt-2">
    <span>Total</span>
    <strong id="grand-total">LKR 0.00</strong>
  </div>
  <div class="payment-note">
    <i class="bi bi-cash-coin"></i>
    <div>
      <strong>Cash on delivery</strong>
      <small>Pay your driver when your fresh laundry arrives.</small>
    </div>
  </div>
  <button class="btn btn-primary w-100" <?= !$services?'disabled':'' ?>>Confirm booking <i class="bi bi-arrow-right"></i></button>
  <p class="summary-caption">Your booking will be reviewed before pickup is arranged.</p>
</aside>
</div>
</form>

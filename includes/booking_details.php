<?php
require_once __DIR__.'/qrcode.php';
try { $b=booking((int)($_GET['id']??0),$u); } catch(DomainException $e) { http_response_code(404); $title='Booking not found'; require __DIR__.'/header.php'; echo '<div class="alert alert-danger">'.e($e->getMessage()).'</div>'; require __DIR__.'/footer.php'; exit; }
$title=$b['booking_number']; $subtitle='Booked on '.date('d M Y, h:i A',strtotime($b['created_at']));
$items=query('SELECT * FROM booking_items WHERE booking_id=?',[$b['id']])->fetchAll();
$events=query('SELECT h.*,u.name FROM booking_status_history h JOIN users u ON u.id=h.changed_by WHERE booking_id=? ORDER BY h.id',[$b['id']])->fetchAll();

if (empty($b['tracking_token'])) {
    $token = hash('sha256', $b['id'] . $b['booking_number'] . 'smart_laundry_salt_2026');
    query('UPDATE bookings SET tracking_token = ? WHERE id = ?', [$token, $b['id']]);
    $b['tracking_token'] = $token;
}
$trackUrl = app_base_url() . '/track.php?token=' . urlencode($b['tracking_token']);
$qrSvg = SmartLaundryQr::getSvg($trackUrl, 100, '#0f766e', '#ffffff');

require __DIR__.'/header.php'; ?>
<div class="detail-banner">
  <div><?= badge($b['booking_status']) ?> <span class="muted">We’ll keep you posted at every step.</span></div>
  <div class="d-flex align-items-center gap-3">
    <strong><?= money($b['total_amount']) ?></strong>
    <?php if ($role !== 'delivery'): ?>
      <div class="d-inline-flex gap-2">
        <a class="btn btn-sm btn-light" href="<?= e(url('invoice.php?id='.$b['id'])) ?>" target="_blank" title="View Official Invoice">
          <i class="bi bi-receipt"></i> Invoice
        </a>
        <a class="btn btn-sm btn-outline-primary" href="<?= e(url('invoice.php?id='.$b['id'].'&download=1')) ?>" title="Download Invoice as PDF">
          <i class="bi bi-file-earmark-pdf"></i> PDF
        </a>
      </div>
    <?php endif ?>
  </div>
</div>
<section class="panel mb-4"><h2>From your door, back to your door.</h2><div class="progress-track"><?php $steps=['Pending','Accepted','Picked Up','Washing','Ironing','Ready for Delivery','Out for Delivery','Delivered']; $current=array_search($b['booking_status'],$steps,true); $reached=array_column($events,'status'); foreach($steps as $i=>$s): ?><div class="track-step <?= in_array($s,$reached,true)?'done':'' ?> <?= $b['booking_status']===$s?'current':'' ?>"><span><?= in_array($s,$reached,true)?'<i class="bi bi-check-lg"></i>':($i+1) ?></span><small><?= e($s) ?></small></div><?php endforeach ?></div><?php if(in_array($b['booking_status'],['Cancelled','Rejected'])): ?><p class="text-danger mt-3">This booking was <?= e(strtolower($b['booking_status'])) ?>. No payment is due.</p><?php endif ?></section>
<div class="booking-layout"><div><section class="panel"><h2>What’s in your laundry?</h2><div class="table-responsive"><table class="table"><thead><tr><th>Service</th><th>Quantity</th><th>Unit price</th><th>Subtotal</th></tr></thead><tbody><?php foreach($items as $item): ?><tr><td><?= e($item['service_name']) ?></td><td><?= $item['quantity'] ?></td><td><?= money($item['unit_price']) ?></td><td><?= money($item['subtotal']) ?></td></tr><?php endforeach ?></tbody><tfoot>
<?php if ((float)$b['discount_amount'] > 0): ?>
  <tr><th colspan="3">Subtotal</th><th><?= money($b['subtotal_amount']) ?></th></tr>
  <tr><th colspan="3" class="text-success">Coupon Discount (<?= e($b['coupon_code']) ?>)</th><th class="text-success">- <?= money($b['discount_amount']) ?></th></tr>
<?php endif ?>
<tr><th colspan="3">Grand total</th><th><?= money($b['total_amount']) ?></th></tr>
</tfoot></table></div><div class="payment-note"><i class="bi bi-cash-coin"></i><div><strong>Cash on delivery <?= badge($b['payment_status']) ?></strong><small><?= $b['paid_at']?'Collected on '.e($b['paid_at']):'Payment is collected when delivery is completed.' ?></small></div></div></section><section class="panel mt-4"><h2>Order timeline</h2><ol class="timeline"><?php foreach(array_reverse($events) as $event): ?><li><div><strong><?= e($event['status']) ?></strong><span><?= e($event['name']) ?></span></div><small><?= e(date('d M Y, h:i A',strtotime($event['created_at']))) ?></small></li><?php endforeach ?></ol></section></div><aside><section class="panel"><h2>Pickup & delivery</h2><dl><dt>Customer</dt><dd><?= e($b['customer_name']) ?></dd><dt>Contact number</dt><dd><?= e($b['pickup_phone']) ?></dd><dt>Pickup date</dt><dd><?= e($b['pickup_date']) ?><?= !empty($b['slot_name']) ? ' (' . e($b['slot_name']) . ')' : '' ?></dd><?php if(!empty($b['slot_name'])): ?><dt>Pickup time slot</dt><dd><?= e($b['slot_name']) ?></dd><?php endif ?><dt>Address</dt><dd><?= nl2br(e($b['pickup_address'])) ?></dd><dt>Delivery person</dt><dd><?= e($b['driver_name']??'Not assigned yet') ?></dd><?php if($b['special_instructions']): ?><dt>Special instructions</dt><dd><?= nl2br(e($b['special_instructions'])) ?></dd><?php endif ?></dl></section>
<section class="panel mt-4">
  <div class="d-flex justify-content-between align-items-center mb-2">
    <h2 class="mb-0">QR Tracking</h2>
    <a href="<?= e(url('qr_label.php?id='.$b['id'])) ?>" target="_blank" class="btn btn-sm btn-light" title="Print Laundry Bag Label"><i class="bi bi-printer"></i> Label</a>
  </div>
  <div class="d-flex align-items-center gap-3">
    <div style="width:100px; height:100px; flex-shrink:0;"><?= $qrSvg ?></div>
    <div>
      <small class="text-muted d-block mb-1">Scan with camera to check live laundry progress.</small>
      <a href="<?= e($trackUrl) ?>" target="_blank" class="text-link" style="font-size:11px;">Open tracking page <i class="bi bi-arrow-up-right"></i></a>
    </div>
  </div>
</section>
<?php 
$existingReview = one("SELECT * FROM reviews WHERE booking_id=?", [$b['id']]);
?>
<?php if ($role === 'customer' && $b['booking_status'] === 'Delivered'): ?>
  <section class="panel mt-4">
    <h2><?= $existingReview ? 'Your Order Review' : 'Rate & Review Order' ?></h2>
    <form method="post" action="<?= e(url('actions/handle.php')) ?>">
      <?= csrf() ?>
      <input type="hidden" name="action" value="review_save">
      <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">
      <label>Rating
        <select name="rating" class="form-select" required>
          <?php for ($i = 5; $i >= 1; $i--): ?>
            <option value="<?= $i ?>" <?= ($existingReview['rating'] ?? 5) == $i ? 'selected' : '' ?>>
              <?= str_repeat('★', $i) . str_repeat('☆', 5 - $i) ?> (<?= $i ?> star<?= $i > 1 ? 's' : '' ?>)
            </option>
          <?php endfor ?>
        </select>
      </label>
      <label class="mt-2">Your feedback
        <textarea name="review_text" class="form-control" rows="3" placeholder="Tell us about the laundry quality and delivery service..."><?= e($existingReview['review_text'] ?? '') ?></textarea>
      </label>
      <button class="btn btn-primary w-100 mt-3"><?= $existingReview ? 'Update Review' : 'Submit Review' ?></button>
    </form>
  </section>
<?php elseif ($existingReview): ?>
  <section class="panel mt-4">
    <h2>Customer Review</h2>
    <div class="text-warning mb-1" style="font-size:18px;">
      <?php for ($s = 1; $s <= 5; $s++): ?>
        <i class="bi bi-star<?= $s <= (int)$existingReview['rating'] ? '-fill' : '' ?>"></i>
      <?php endfor ?>
    </div>
    <?php if (!empty($existingReview['review_text'])): ?>
      <p class="mb-0 text-muted"><?= nl2br(e($existingReview['review_text'])) ?></p>
    <?php endif ?>
  </section>
<?php endif ?>
<?php $next=next_statuses($role,$b['booking_status']); if($next): ?>
<section class="panel mt-4"><h2><?= $role==='customer'?'Manage booking':'Next step' ?></h2><?php foreach($next as $s): ?><form method="post" action="<?= e(url('actions/handle.php')) ?>" class="mb-2" <?= in_array($s,['Cancelled','Rejected'])?'data-confirm="Are you sure you want to '.strtolower($s==='Cancelled'?'cancel':'reject').' this booking?"':'' ?>><?= csrf() ?><input type="hidden" name="action" value="status"><input type="hidden" name="booking_id" value="<?= $b['id'] ?>"><input type="hidden" name="status" value="<?= e($s) ?>"><?php if($s==='Delivered'): ?><label class="check-label"><input type="checkbox" name="cod_confirm" value="1" required> I collected <?= money($b['total_amount']) ?> in cash.</label><?php endif ?><button class="btn <?= in_array($s,['Cancelled','Rejected'])?'btn-outline-danger':'btn-primary' ?> w-100"><?= $s==='Delivered'?'Complete delivery & record COD':($s==='Cancelled'?'Cancel booking':'Mark as '.$s) ?></button></form><?php endforeach ?></section><?php endif ?>
<?php if($role==='admin' && $b['booking_status']==='Accepted'): ?><section class="panel mt-4"><h2>Assign delivery</h2><form action="<?= e(url('actions/handle.php')) ?>" method="post"><?= csrf() ?><input type="hidden" name="action" value="assign"><input type="hidden" name="booking_id" value="<?= $b['id'] ?>"><label>Delivery person<select class="form-select" name="delivery_person_id" required><option value="">Select a driver</option><?php foreach(query("SELECT * FROM users WHERE role='delivery' AND is_active=1 ORDER BY name")->fetchAll() as $d): ?><option value="<?= $d['id'] ?>" <?= $b['delivery_person_id']==$d['id']?'selected':'' ?>><?= e($d['name']) ?></option><?php endforeach ?></select></label><button class="btn btn-primary w-100 mt-2">Save assignment</button></form></section><?php endif ?></aside></div>

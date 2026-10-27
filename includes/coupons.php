<?php
protect('admin');
$title = 'Discount Coupons';
$subtitle = 'Create, track, and manage promotional codes for customer pickups.';
require __DIR__.'/header.php';

// Fetch stats
$totalActive = (int)one("SELECT COUNT(*) n FROM coupons WHERE is_active=1 AND (expiry_date IS NULL OR expiry_date>=CURDATE())")['n'];
$redemptionStats = one("SELECT COUNT(*) cnt, COALESCE(SUM(discount_amount), 0) total_saved FROM coupon_redemptions");
$allCoupons = query("SELECT c.*, (SELECT COUNT(*) FROM coupon_redemptions r WHERE r.coupon_id=c.id) as real_uses FROM coupons c ORDER BY c.id DESC")->fetchAll();

$editId = (int)($_GET['edit'] ?? 0);
$editCoupon = $editId > 0 ? one("SELECT * FROM coupons WHERE id=?", [$editId]) : null;
?>

<div class="stat-grid">
  <div class="stat-card">
    <div class="stat-top"><span>Active Coupons</span><i class="bi bi-ticket-perforated"></i></div>
    <strong><?= $totalActive ?></strong>
    <small>Currently valid and redeemable</small>
  </div>
  <div class="stat-card">
    <div class="stat-top"><span>Total Redemptions</span><i class="bi bi-check2-circle"></i></div>
    <strong><?= (int)$redemptionStats['cnt'] ?></strong>
    <small>Customer uses recorded</small>
  </div>
  <div class="stat-card">
    <div class="stat-top"><span>Total Discounts Given</span><i class="bi bi-cash-stack"></i></div>
    <strong><?= money($redemptionStats['total_saved']) ?></strong>
    <small>Customer savings granted</small>
  </div>
</div>

<div class="row g-4">
  <!-- Coupons List Table -->
  <div class="col-lg-8">
    <section class="panel">
      <div class="panel-heading">
        <div>
          <h2>Promotional Coupon Codes</h2>
          <p>Discount codes can be applied by customers during booking checkout.</p>
        </div>
      </div>

      <?php if(!$allCoupons): ?>
        <div class="empty py-4">
          <i class="bi bi-ticket text-muted" style="font-size:32px;"></i>
          <p class="mt-2 mb-0">No coupons created yet. Use the form to create your first promotion.</p>
        </div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table align-middle">
            <thead>
              <tr>
                <th>Code</th>
                <th>Discount</th>
                <th>Min. Order</th>
                <th>Redemptions</th>
                <th>Validity</th>
                <th>Status</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach($allCoupons as $c): 
                $isExpired = !empty($c['expiry_date']) && $c['expiry_date'] < date('Y-m-d');
                $isLimitReached = $c['usage_limit'] !== null && (int)$c['times_used'] >= (int)$c['usage_limit'];
              ?>
                <tr>
                  <td>
                    <span class="badge bg-primary-subtle text-primary font-monospace" style="font-size:12px; letter-spacing:1px;"><?= e($c['code']) ?></span>
                  </td>
                  <td>
                    <?php if($c['discount_type'] === 'percent'): ?>
                      <strong><?= (float)$c['discount_value'] ?>%</strong>
                      <?php if((float)$c['max_discount_amount'] > 0): ?>
                        <small class="text-muted d-block">Max <?= money($c['max_discount_amount']) ?></small>
                      <?php endif ?>
                    <?php else: ?>
                      <strong><?= money($c['discount_value']) ?></strong>
                      <small class="text-muted d-block">Fixed discount</small>
                    <?php endif ?>
                  </td>
                  <td><?= (float)$c['min_booking_amount'] > 0 ? money($c['min_booking_amount']) : '<span class="text-muted">None</span>' ?></td>
                  <td>
                    <strong><?= (int)$c['times_used'] ?></strong>
                    <small class="text-muted">/ <?= $c['usage_limit'] !== null ? (int)$c['usage_limit'] : '∞' ?></small>
                  </td>
                  <td>
                    <?php if($isExpired): ?>
                      <span class="badge bg-danger-subtle text-danger">Expired</span>
                      <small class="text-muted d-block"><?= date('d M Y', strtotime($c['expiry_date'])) ?></small>
                    <?php elseif(!empty($c['expiry_date'])): ?>
                      <span><?= date('d M Y', strtotime($c['expiry_date'])) ?></span>
                    <?php else: ?>
                      <span class="text-muted">No expiry</span>
                    <?php endif ?>
                  </td>
                  <td>
                    <?php if(!$c['is_active']): ?>
                      <span class="status status-inactive">Inactive</span>
                    <?php elseif($isExpired || $isLimitReached): ?>
                      <span class="status status-rejected">Unavailable</span>
                    <?php else: ?>
                      <span class="status status-active">Active</span>
                    <?php endif ?>
                  </td>
                  <td>
                    <div class="table-actions">
                      <a href="<?= e(url('admin/coupons.php?edit='.$c['id'])) ?>" class="btn btn-sm btn-light" title="Edit Coupon">
                        <i class="bi bi-pencil"></i>
                      </a>
                      <form method="post" action="<?= e(url('actions/handle.php')) ?>" class="m-0">
                        <?= csrf() ?>
                        <input type="hidden" name="action" value="coupon_toggle">
                        <input type="hidden" name="id" value="<?= $c['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-light" title="<?= $c['is_active'] ? 'Deactivate' : 'Activate' ?>">
                          <i class="bi bi-<?= $c['is_active'] ? 'eye-slash' : 'eye' ?>"></i>
                        </button>
                      </form>
                    </div>
                  </td>
                </tr>
              <?php endforeach ?>
            </tbody>
          </table>
        </div>
      <?php endif ?>
    </section>
  </div>

  <!-- Add / Edit Coupon Form -->
  <div class="col-lg-4">
    <section class="panel">
      <div class="panel-heading">
        <div>
          <h2><?= $editCoupon ? 'Edit Coupon' : 'Create New Coupon' ?></h2>
          <p><?= $editCoupon ? 'Update coupon rules and settings' : 'Define discount terms and limits' ?></p>
        </div>
        <?php if($editCoupon): ?>
          <a href="<?= e(url('admin/coupons.php')) ?>" class="btn btn-sm btn-light">Cancel</a>
        <?php endif ?>
      </div>

      <form method="post" action="<?= e(url('actions/handle.php')) ?>">
        <?= csrf() ?>
        <input type="hidden" name="action" value="coupon_save">
        <?php if($editCoupon): ?>
          <input type="hidden" name="id" value="<?= $editCoupon['id'] ?>">
        <?php endif ?>

        <label>Coupon Code
          <input type="text" name="code" class="form-control text-uppercase" maxlength="50" placeholder="e.g. SUMMER25" value="<?= e($editCoupon['code'] ?? '') ?>" required style="letter-spacing:1px; font-weight:700;">
        </label>

        <label>Discount Type
          <select name="discount_type" class="form-select" id="coupon-type" required>
            <option value="fixed" <?= ($editCoupon['discount_type'] ?? '') === 'fixed' ? 'selected' : '' ?>>Fixed Cash Discount (LKR)</option>
            <option value="percent" <?= ($editCoupon['discount_type'] ?? '') === 'percent' ? 'selected' : '' ?>>Percentage Discount (%)</option>
          </select>
        </label>

        <label>Discount Value
          <input type="number" name="discount_value" class="form-control" step="0.01" min="0.01" placeholder="e.g. 200 or 15" value="<?= e($editCoupon['discount_value'] ?? '') ?>" required>
          <small class="text-muted" id="discount-hint">For fixed: amount in LKR. For percentage: 1 to 100.</small>
        </label>

        <label>Minimum Booking Amount (LKR)
          <input type="number" name="min_booking_amount" class="form-control" step="0.01" min="0" placeholder="0 for no minimum" value="<?= e($editCoupon['min_booking_amount'] ?? '0.00') ?>">
        </label>

        <label id="max-discount-group">Maximum Discount Cap (LKR) <span class="text-muted">(Optional)</span>
          <input type="number" name="max_discount_amount" class="form-control" step="0.01" min="0" placeholder="Leave empty for uncapped" value="<?= e($editCoupon['max_discount_amount'] ?? '') ?>">
        </label>

        <label>Total Usage Limit <span class="text-muted">(Optional)</span>
          <input type="number" name="usage_limit" class="form-control" min="1" step="1" placeholder="Leave empty for unlimited" value="<?= e($editCoupon['usage_limit'] ?? '') ?>">
        </label>

        <label>Expiry Date <span class="text-muted">(Optional)</span>
          <input type="date" name="expiry_date" class="form-control" value="<?= e($editCoupon['expiry_date'] ?? '') ?>">
        </label>

        <div class="form-check mt-3 mb-3">
          <input class="form-check-input" type="checkbox" name="is_active" id="coupon_active" <?= (!isset($editCoupon) || $editCoupon['is_active']) ? 'checked' : '' ?>>
          <label class="form-check-label m-0" for="coupon_active">
            Active and ready for customer redemptions
          </label>
        </div>

        <button type="submit" class="btn btn-primary w-100">
          <i class="bi bi-check-circle"></i> <?= $editCoupon ? 'Update Coupon' : 'Create Coupon' ?>
        </button>
      </form>
    </section>
  </div>
</div>


<?php
declare(strict_types=1);

require __DIR__.'/includes/bootstrap.php';
$u = protect();
$role = $u['role'];
$title = 'Notifications';
$subtitle = 'Stay informed on all your bookings, operations and delivery milestones.';

$allNotifs = query('SELECT * FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT 50', [$u['id']])->fetchAll();
$unreadCount = unread_notifications_count((int)$u['id']);

require __DIR__.'/includes/header.php';
?>
<div class="narrow-panel" style="max-width: 800px; margin: 0 auto;">
  <div class="panel">
    <div class="panel-heading">
      <div>
        <h2 class="mb-1">Notification Center</h2>
        <p class="text-muted mb-0"><?= $unreadCount ?> unread message<?= $unreadCount === 1 ? '' : 's' ?></p>
      </div>
      <?php if ($unreadCount > 0): ?>
        <form action="<?= e(url('actions/handle.php')) ?>" method="post" class="m-0">
          <?= csrf() ?>
          <input type="hidden" name="action" value="mark_all_notifications_read">
          <input type="hidden" name="redirect_to" value="notifications.php">
          <button type="submit" class="btn btn-sm btn-light">
            <i class="bi bi-check2-all"></i> Mark all as read
          </button>
        </form>
      <?php endif ?>
    </div>

    <?php if (!$allNotifs): ?>
      <div class="empty">
        <i class="bi bi-bell-slash"></i>
        <h3>No notifications</h3>
        <p>You have no activity notifications at this time.</p>
      </div>
    <?php else: ?>
      <div class="list-group list-group-flush">
        <?php foreach ($allNotifs as $n): ?>
          <div class="list-group-item d-flex justify-content-between align-items-start py-3 px-3 border-bottom <?= $n['is_read'] ? 'opacity-75' : 'bg-light-subtle' ?>" style="background:transparent;">
            <div class="d-flex gap-3 align-items-start">
              <div class="service-icon" style="width:36px; height:36px; font-size:16px;">
                <i class="bi bi-<?= $n['type'] === 'success' ? 'check-circle' : ($n['type'] === 'warning' ? 'exclamation-circle' : ($n['type'] === 'danger' ? 'x-circle' : 'bell')) ?>"></i>
              </div>
              <div>
                <div class="d-flex align-items-center gap-2">
                  <strong style="font-size:14px;"><?= e($n['title']) ?></strong>
                  <?php if (!$n['is_read']): ?>
                    <span class="badge bg-primary" style="font-size:9px;">NEW</span>
                  <?php endif ?>
                </div>
                <p class="mb-1 mt-1 text-muted" style="font-size:13px;"><?= e($n['message']) ?></p>
                <div class="d-flex align-items-center gap-3">
                  <small class="text-muted"><?= date('d M Y, h:i A', strtotime($n['created_at'])) ?> (<?= time_ago($n['created_at']) ?>)</small>
                  <?php if (!empty($n['booking_id'])): ?>
                    <?php 
                      $linkPath = ($u['role'] === 'delivery' ? 'delivery/order_details.php' : ($u['role'] === 'admin' ? 'admin/booking_details.php' : 'customer/booking_details.php')) . '?id=' . $n['booking_id'];
                    ?>
                    <a href="<?= e(url($linkPath)) ?>" class="text-link" style="font-size:12px;">Open order <i class="bi bi-arrow-up-right"></i></a>
                  <?php endif ?>
                </div>
              </div>
            </div>
            <?php if (!$n['is_read']): ?>
              <form action="<?= e(url('actions/handle.php')) ?>" method="post" class="m-0 ms-2">
                <?= csrf() ?>
                <input type="hidden" name="action" value="mark_notification_read">
                <input type="hidden" name="notification_id" value="<?= $n['id'] ?>">
                <input type="hidden" name="redirect_to" value="notifications.php">
                <button type="submit" class="btn btn-sm btn-light" title="Mark as read" style="padding:4px 8px; font-size:11px;">
                  <i class="bi bi-check2"></i>
                </button>
              </form>
            <?php endif ?>
          </div>
        <?php endforeach ?>
      </div>
    <?php endif ?>
  </div>
</div>
<?php require __DIR__.'/includes/footer.php'; ?>


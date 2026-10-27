<?php
protect('admin');
$title = 'Pickup Time Slots';
$subtitle = 'Configure daily collection windows and booking capacity thresholds.';
require __DIR__.'/header.php';

$allSlots = query("SELECT s.*, 
  (SELECT COUNT(*) FROM bookings b WHERE b.pickup_slot_id=s.id AND b.pickup_date=CURDATE() AND b.booking_status NOT IN ('Cancelled', 'Rejected')) as today_booked
  FROM pickup_slots s ORDER BY s.start_time ASC")->fetchAll();

$totalSlots = count($allSlots);
$activeSlots = count(array_filter($allSlots, fn($s) => (bool)$s['is_active']));
$dailyCapacity = array_reduce(array_filter($allSlots, fn($s) => (bool)$s['is_active']), fn($c, $s) => $c + (int)$s['daily_capacity'], 0);

$editId = (int)($_GET['edit'] ?? 0);
$editSlot = $editId > 0 ? one("SELECT * FROM pickup_slots WHERE id=?", [$editId]) : null;
?>

<div class="stat-grid">
  <div class="stat-card">
    <div class="stat-top"><span>Active Slots</span><i class="bi bi-clock-history"></i></div>
    <strong><?= $activeSlots ?> / <?= $totalSlots ?></strong>
    <small>Operating collection windows</small>
  </div>
  <div class="stat-card">
    <div class="stat-top"><span>Daily Total Capacity</span><i class="bi bi-speedometer2"></i></div>
    <strong><?= $dailyCapacity ?></strong>
    <small>Maximum pickup orders per day</small>
  </div>
  <div class="stat-card">
    <div class="stat-top"><span>Schedule Overview</span><i class="bi bi-calendar3"></i></div>
    <strong><a href="<?= e(url('admin/schedules.php')) ?>" class="text-decoration-none text-primary" style="font-size:18px;">View Daily Schedule <i class="bi bi-arrow-right"></i></a></strong>
    <small>Monitor today's pickups & slot utilization</small>
  </div>
</div>

<div class="row g-4">
  <div class="col-lg-8">
    <section class="panel">
      <div class="panel-heading">
        <div>
          <h2>Configured Time Windows</h2>
          <p>Customers can select from active slots during booking checkout up to the capacity limit.</p>
        </div>
      </div>

      <?php if(!$allSlots): ?>
        <div class="empty py-4">
          <i class="bi bi-clock text-muted" style="font-size:32px;"></i>
          <p class="mt-2 mb-0">No pickup slots created yet. Use the form to configure your first time window.</p>
        </div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table align-middle">
            <thead>
              <tr>
                <th>Slot Name</th>
                <th>Time Window</th>
                <th>Daily Capacity</th>
                <th>Today's Bookings</th>
                <th>Status</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach($allSlots as $s): 
                $startFmt = date('h:i A', strtotime($s['start_time']));
                $endFmt = date('h:i A', strtotime($s['end_time']));
              ?>
                <tr>
                  <td>
                    <strong><?= e($s['slot_name']) ?></strong>
                  </td>
                  <td>
                    <span class="badge bg-light text-dark font-monospace" style="font-size:12px;">
                      <?= $startFmt ?> - <?= $endFmt ?>
                    </span>
                  </td>
                  <td>
                    <strong><?= (int)$s['daily_capacity'] ?></strong> <span class="text-muted">orders</span>
                  </td>
                  <td>
                    <span class="badge <?= (int)$s['today_booked'] >= (int)$s['daily_capacity'] ? 'bg-danger-subtle text-danger' : 'bg-primary-subtle text-primary' ?>">
                      <?= (int)$s['today_booked'] ?> / <?= (int)$s['daily_capacity'] ?>
                    </span>
                  </td>
                  <td>
                    <?php if($s['is_active']): ?>
                      <span class="status status-active">Active</span>
                    <?php else: ?>
                      <span class="status status-inactive">Inactive</span>
                    <?php endif ?>
                  </td>
                  <td>
                    <div class="table-actions">
                      <a href="<?= e(url('admin/slots.php?edit='.$s['id'])) ?>" class="btn btn-sm btn-light" title="Edit Time Slot">
                        <i class="bi bi-pencil"></i>
                      </a>
                      <form method="post" action="<?= e(url('actions/handle.php')) ?>" class="m-0">
                        <?= csrf() ?>
                        <input type="hidden" name="action" value="slot_toggle">
                        <input type="hidden" name="id" value="<?= $s['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-light" title="<?= $s['is_active'] ? 'Deactivate' : 'Activate' ?>">
                          <i class="bi bi-<?= $s['is_active'] ? 'eye-slash' : 'eye' ?>"></i>
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

  <div class="col-lg-4">
    <section class="panel">
      <div class="panel-heading">
        <div>
          <h2><?= $editSlot ? 'Edit Time Slot' : 'Create Time Slot' ?></h2>
          <p><?= $editSlot ? 'Update slot schedule and capacity limit' : 'Add a new pickup window' ?></p>
        </div>
        <?php if($editSlot): ?>
          <a href="<?= e(url('admin/slots.php')) ?>" class="btn btn-sm btn-light">Cancel</a>
        <?php endif ?>
      </div>

      <form method="post" action="<?= e(url('actions/handle.php')) ?>">
        <?= csrf() ?>
        <input type="hidden" name="action" value="slot_save">
        <?php if($editSlot): ?>
          <input type="hidden" name="id" value="<?= $editSlot['id'] ?>">
        <?php endif ?>

        <label>Slot Name / Label
          <input type="text" name="slot_name" class="form-control" maxlength="100" placeholder="e.g. Morning Collection" value="<?= e($editSlot['slot_name'] ?? '') ?>" required>
        </label>

        <div class="form-grid">
          <label>Start Time
            <input type="time" name="start_time" class="form-control" value="<?= e(substr($editSlot['start_time'] ?? '08:00', 0, 5)) ?>" required>
          </label>
          <label>End Time
            <input type="time" name="end_time" class="form-control" value="<?= e(substr($editSlot['end_time'] ?? '10:00', 0, 5)) ?>" required>
          </label>
        </div>

        <label class="mt-2">Daily Booking Capacity
          <input type="number" name="daily_capacity" class="form-control" min="1" max="1000" step="1" value="<?= e($editSlot['daily_capacity'] ?? '8') ?>" required>
          <small class="text-muted">Maximum number of accepted/active customer bookings permitted per calendar day.</small>
        </label>

        <div class="form-check mt-3 mb-3">
          <input class="form-check-input" type="checkbox" name="is_active" id="slot_active" <?= (!isset($editSlot) || $editSlot['is_active']) ? 'checked' : '' ?>>
          <label class="form-check-label m-0" for="slot_active">
            Active for customer checkout selection
          </label>
        </div>

        <button type="submit" class="btn btn-primary w-100">
          <i class="bi bi-check-circle"></i> <?= $editSlot ? 'Update Time Slot' : 'Save Time Slot' ?>
        </button>
      </form>
    </section>
  </div>
</div>


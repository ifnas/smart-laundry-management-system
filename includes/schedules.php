<?php
protect('admin');
$date = $_GET['date'] ?? date('Y-m-d');
$dt = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
if (!$dt || $dt->format('Y-m-d') !== $date) {
    $date = date('Y-m-d');
}

$prevDate = date('Y-m-d', strtotime($date . ' -1 day'));
$nextDate = date('Y-m-d', strtotime($date . ' +1 day'));

$title = 'Daily Pickup Schedule';
$subtitle = 'Collections scheduled for ' . date('l, d F Y', strtotime($date));
require __DIR__.'/header.php';

// Fetch active slots
$slots = query("SELECT * FROM pickup_slots WHERE is_active=1 ORDER BY start_time ASC")->fetchAll();

// Fetch all bookings for this pickup date
$bookings = query("SELECT b.*, u.name as customer_name, a.delivery_person_id, d.name as driver_name 
                   FROM bookings b 
                   JOIN users u ON u.id=b.customer_id 
                   LEFT JOIN delivery_assignments a ON a.booking_id=b.id 
                   LEFT JOIN users d ON d.id=a.delivery_person_id 
                   WHERE b.pickup_date=? AND b.booking_status NOT IN ('Cancelled', 'Rejected') 
                   ORDER BY b.id ASC", [$date])->fetchAll();

// Group by slot id (null => 0)
$grouped = [];
foreach ($bookings as $b) {
    $sid = (int)($b['pickup_slot_id'] ?? 0);
    $grouped[$sid][] = $b;
}

$drivers = query("SELECT id, name FROM users WHERE role='delivery' AND is_active=1 ORDER BY name ASC")->fetchAll();
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
  <div class="d-flex align-items-center gap-2">
    <a href="<?= e(url('admin/schedules.php?date='.$prevDate)) ?>" class="btn btn-light btn-sm" title="Previous Day">
      <i class="bi bi-chevron-left"></i> Previous
    </a>
    <form method="get" action="<?= e(url('admin/schedules.php')) ?>" class="d-inline-flex align-items-center gap-2 m-0">
      <input type="date" name="date" class="form-control form-control-sm" value="<?= e($date) ?>" onchange="this.form.submit()">
    </form>
    <a href="<?= e(url('admin/schedules.php?date='.$nextDate)) ?>" class="btn btn-light btn-sm" title="Next Day">
      Next <i class="bi bi-chevron-right"></i>
    </a>
    <?php if ($date !== date('Y-m-d')): ?>
      <a href="<?= e(url('admin/schedules.php')) ?>" class="btn btn-outline-primary btn-sm">Today</a>
    <?php endif ?>
  </div>
  <div>
    <span class="badge bg-primary-subtle text-primary p-2" style="font-size:13px;">
      <i class="bi bi-calendar-check"></i> Total Pickups: <strong><?= count($bookings) ?></strong>
    </span>
  </div>
</div>

<?php if (!$slots && !$bookings): ?>
  <div class="empty py-5">
    <i class="bi bi-calendar-x text-muted" style="font-size:36px;"></i>
    <p class="mt-2">No pickup time slots configured and no bookings for this date.</p>
    <a href="<?= e(url('admin/slots.php')) ?>" class="btn btn-primary btn-sm mt-2">Manage Time Slots</a>
  </div>
<?php else: ?>
  <div class="schedule-slots-grid d-flex flex-column gap-4">
    <?php foreach ($slots as $slot): 
      $sid = (int)$slot['id'];
      $slotBookings = $grouped[$sid] ?? [];
      $count = count($slotBookings);
      $cap = (int)$slot['daily_capacity'];
      $isFull = $count >= $cap;
      $startFmt = date('h:i A', strtotime($slot['start_time']));
      $endFmt = date('h:i A', strtotime($slot['end_time']));
    ?>
      <section class="panel">
        <div class="d-flex flex-wrap justify-content-between align-items-center border-bottom pb-3 mb-3 gap-2">
          <div>
            <h3 class="mb-0" style="font-size:18px; font-weight:700;">
              <i class="bi bi-clock text-primary"></i> <?= e($slot['slot_name']) ?>
              <span class="text-muted font-monospace" style="font-size:14px; font-weight:normal;">(<?= $startFmt ?> - <?= $endFmt ?>)</span>
            </h3>
          </div>
          <div>
            <span class="badge <?= $isFull ? 'bg-danger text-white' : ($count > 0 ? 'bg-primary text-white' : 'bg-light text-muted border') ?>" style="font-size:12px;">
              <?= $count ?> / <?= $cap ?> Booked <?= $isFull ? '(FULL)' : '' ?>
            </span>
          </div>
        </div>

        <?php if (!$slotBookings): ?>
          <p class="text-muted small mb-0 py-2">No pickups scheduled in this time window.</p>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table align-middle table-hover mb-0">
              <thead>
                <tr>
                  <th>Booking Ref</th>
                  <th>Customer & Contact</th>
                  <th>Address</th>
                  <th>Status</th>
                  <th>Driver Assigned</th>
                  <th>Total</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($slotBookings as $b): ?>
                  <tr>
                    <td>
                      <strong><?= e($b['booking_number']) ?></strong>
                    </td>
                    <td>
                      <div><?= e($b['customer_name']) ?></div>
                      <small class="text-muted"><?= e($b['pickup_phone']) ?></small>
                    </td>
                    <td>
                      <small style="max-width:240px; display:inline-block;" class="text-truncate"><?= e($b['pickup_address']) ?></small>
                    </td>
                    <td><?= badge($b['booking_status']) ?></td>
                    <td>
                      <?php if (!empty($b['driver_name'])): ?>
                        <span class="badge bg-success-subtle text-success">
                          <i class="bi bi-bicycle"></i> <?= e($b['driver_name']) ?>
                        </span>
                      <?php elseif ($b['booking_status'] === 'Accepted'): ?>
                        <form method="post" action="<?= e(url('actions/handle.php')) ?>" class="d-flex gap-1 m-0">
                          <?= csrf() ?>
                          <input type="hidden" name="action" value="assign">
                          <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">
                          <select name="delivery_person_id" class="form-select form-select-sm" required style="max-width:140px;">
                            <option value="">Assign...</option>
                            <?php foreach ($drivers as $drv): ?>
                              <option value="<?= $drv['id'] ?>"><?= e($drv['name']) ?></option>
                            <?php endforeach ?>
                          </select>
                          <button class="btn btn-sm btn-primary py-0 px-2" title="Assign driver"><i class="bi bi-check"></i></button>
                        </form>
                      <?php else: ?>
                        <span class="text-muted small">Unassigned</span>
                      <?php endif ?>
                    </td>
                    <td><strong><?= money($b['total_amount']) ?></strong></td>
                    <td>
                      <a href="<?= e(url('admin/booking_details.php?id='.$b['id'])) ?>" class="btn btn-sm btn-light">
                        View <i class="bi bi-arrow-up-right"></i>
                      </a>
                    </td>
                  </tr>
                <?php endforeach ?>
              </tbody>
            </table>
          </div>
        <?php endif ?>
      </section>
    <?php endforeach ?>

    <!-- Flexible / Unassigned Slot Bookings -->
    <?php $flexBookings = $grouped[0] ?? []; if ($flexBookings): ?>
      <section class="panel border-dashed">
        <div class="d-flex justify-content-between align-items-center border-bottom pb-3 mb-3">
          <h3 class="mb-0" style="font-size:18px; font-weight:700;">
            <i class="bi bi-hourglass-split text-muted"></i> Flexible / Unspecified Time Slot
          </h3>
          <span class="badge bg-secondary"><?= count($flexBookings) ?> Orders</span>
        </div>
        <div class="table-responsive">
          <table class="table align-middle table-hover mb-0">
            <thead>
              <tr>
                <th>Booking Ref</th>
                <th>Customer & Contact</th>
                <th>Address</th>
                <th>Status</th>
                <th>Driver Assigned</th>
                <th>Total</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($flexBookings as $b): ?>
                <tr>
                  <td><strong><?= e($b['booking_number']) ?></strong></td>
                  <td>
                    <div><?= e($b['customer_name']) ?></div>
                    <small class="text-muted"><?= e($b['pickup_phone']) ?></small>
                  </td>
                  <td>
                    <small style="max-width:240px; display:inline-block;" class="text-truncate"><?= e($b['pickup_address']) ?></small>
                  </td>
                  <td><?= badge($b['booking_status']) ?></td>
                  <td>
                    <?php if (!empty($b['driver_name'])): ?>
                      <span class="badge bg-success-subtle text-success">
                        <i class="bi bi-bicycle"></i> <?= e($b['driver_name']) ?>
                      </span>
                    <?php else: ?>
                      <span class="text-muted small">Unassigned</span>
                    <?php endif ?>
                  </td>
                  <td><strong><?= money($b['total_amount']) ?></strong></td>
                  <td>
                    <a href="<?= e(url('admin/booking_details.php?id='.$b['id'])) ?>" class="btn btn-sm btn-light">
                      View <i class="bi bi-arrow-up-right"></i>
                    </a>
                  </td>
                </tr>
              <?php endforeach ?>
            </tbody>
          </table>
        </div>
      </section>
    <?php endif ?>
  </div>
<?php endif ?>


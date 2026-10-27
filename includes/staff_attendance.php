<?php
$title = 'My Attendance';
$subtitle = 'Record shift check-ins, check-outs, and review your daily working hours.';
require __DIR__.'/header.php';

$today = date('Y-m-d');
$now = date('h:i A');

// Find active attendance session (if checked in and not checked out)
$activeSession = get_staff_active_attendance((int)$u['id']);

// Today's latest attendance record
$todayRecord = get_staff_today_attendance((int)$u['id']);

// Staff's personal attendance history (last 30 records)
$history = query(
    "SELECT * FROM staff_attendance WHERE user_id = ? ORDER BY attendance_date DESC, id DESC LIMIT 30",
    [$u['id']]
)->fetchAll();

// Calculate month-to-date stats
$monthStats = one(
    "SELECT COUNT(*) shifts, COALESCE(SUM(working_hours), 0) total_hours 
     FROM staff_attendance 
     WHERE user_id = ? AND attendance_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01')",
    [$u['id']]
);
?>

<!-- Month-to-date Stats -->
<div class="stat-grid">
  <div class="stat-card">
    <div class="stat-top"><span>Current Shift Status</span><i class="bi bi-clock"></i></div>
    <strong class="<?= $activeSession ? 'text-success' : 'text-secondary' ?>">
      <?= $activeSession ? 'On Duty' : 'Off Duty' ?>
    </strong>
    <small><?= $activeSession ? 'Checked in at ' . date('h:i A', strtotime($activeSession['check_in'])) : 'Not actively on shift' ?></small>
  </div>
  <div class="stat-card">
    <div class="stat-top"><span>Shifts This Month</span><i class="bi bi-calendar3"></i></div>
    <strong><?= (int)$monthStats['shifts'] ?></strong>
    <small>Days recorded on duty</small>
  </div>
  <div class="stat-card">
    <div class="stat-top"><span>Total Hours This Month</span><i class="bi bi-hourglass-split"></i></div>
    <strong><?= number_format((float)$monthStats['total_hours'], 2) ?> hrs</strong>
    <small>Calculated from verified timestamps</small>
  </div>
</div>

<div class="row g-4 mb-4">
  <!-- Today's Attendance Action Card -->
  <div class="col-lg-5">
    <section class="panel">
      <div class="panel-heading">
        <div>
          <h2>Shift Action</h2>
          <p>Timezone: Asia/Colombo (<?= date('T, P') ?>)</p>
        </div>
        <span class="badge bg-light text-dark border"><i class="bi bi-calendar-event me-1"></i> <?= date('D, d M Y') ?></span>
      </div>

      <div class="p-4 text-center">
        <div class="display-6 fw-bold mb-1 text-primary"><?= $now ?></div>
        <p class="text-muted small mb-4">Official Server Time</p>

        <?php if ($activeSession): ?>
          <!-- Active Shift: Ready to Check Out -->
          <div class="alert alert-success py-3 mb-4 text-start">
            <div class="d-flex align-items-center mb-1">
              <i class="bi bi-check-circle-fill fs-5 text-success me-2"></i>
              <strong>Active Session in Progress</strong>
            </div>
            <div class="small">
              Checked in at <strong><?= date('h:i:s A', strtotime($activeSession['check_in'])) ?></strong> on <?= date('d M Y', strtotime($activeSession['check_in'])) ?>.
            </div>
          </div>

          <form action="<?= e(url('actions/handle.php')) ?>" method="post">
            <?= csrf() ?>
            <input type="hidden" name="action" value="attendance_checkout">
            <div class="mb-3 text-start">
              <label class="form-label small fw-bold">End-of-shift notes (optional)</label>
              <input type="text" name="notes" class="form-control form-control-sm" placeholder="e.g. Completed all afternoon deliveries">
            </div>
            <button type="submit" class="btn btn-danger btn-lg w-100 py-3 shadow-sm">
              <i class="bi bi-box-arrow-right me-2"></i> Check Out Now
            </button>
          </form>

        <?php else: ?>
          <!-- No Active Shift: Ready to Check In -->
          <?php if ($todayRecord && $todayRecord['check_out']): ?>
            <div class="alert alert-info py-2 mb-3 text-start small">
              <i class="bi bi-info-circle me-1"></i> You previously completed a shift today (<?= number_format((float)$todayRecord['working_hours'], 2) ?> hrs). You may start an additional session if required.
            </div>
          <?php endif ?>

          <form action="<?= e(url('actions/handle.php')) ?>" method="post">
            <?= csrf() ?>
            <input type="hidden" name="action" value="attendance_checkin">
            <div class="mb-3 text-start">
              <label class="form-label small fw-bold">Check-in memo (optional)</label>
              <input type="text" name="notes" class="form-control form-control-sm" placeholder="e.g. Starting morning route">
            </div>
            <button type="submit" class="btn btn-success btn-lg w-100 py-3 shadow-sm">
              <i class="bi bi-box-arrow-in-right me-2"></i> Record Shift Check In
            </button>
          </form>
        <?php endif ?>
      </div>
    </section>
  </div>

  <!-- Attendance History Table -->
  <div class="col-lg-7">
    <section class="panel">
      <div class="panel-heading">
        <div>
          <h2>Recent Attendance History</h2>
          <p>Verified check-in and check-out logs for your account.</p>
        </div>
      </div>

      <?php if (!$history): ?>
        <div class="empty py-4">
          <i class="bi bi-calendar-x text-muted" style="font-size:32px;"></i>
          <p class="mt-2 mb-0">No past attendance logs found.</p>
        </div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table align-middle">
            <thead>
              <tr>
                <th>Date</th>
                <th>Check In</th>
                <th>Check Out</th>
                <th>Hours</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($history as $h): ?>
                <tr>
                  <td><strong><?= date('d M Y', strtotime($h['attendance_date'])) ?></strong></td>
                  <td><?= date('h:i A', strtotime($h['check_in'])) ?></td>
                  <td>
                    <?php if ($h['check_out']): ?>
                      <?= date('h:i A', strtotime($h['check_out'])) ?>
                    <?php else: ?>
                      <span class="badge bg-warning text-dark"><i class="bi bi-hourglass-split"></i> In Progress</span>
                    <?php endif ?>
                  </td>
                  <td>
                    <?php if ($h['working_hours'] !== null): ?>
                      <strong><?= number_format((float)$h['working_hours'], 2) ?> hrs</strong>
                    <?php else: ?>
                      <span class="text-muted">—</span>
                    <?php endif ?>
                  </td>
                  <td>
                    <span class="badge bg-<?= $h['check_out'] ? 'success-subtle text-success border border-success-subtle' : 'primary-subtle text-primary border border-primary-subtle' ?>">
                      <?= $h['check_out'] ? 'Completed' : 'Active' ?>
                    </span>
                  </td>
                </tr>
              <?php endforeach ?>
            </tbody>
          </table>
        </div>
      <?php endif ?>
    </section>
  </div>
</div>


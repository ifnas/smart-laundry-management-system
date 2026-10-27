<?php
protect('admin');
$title = 'Staff Attendance & Working Hours';
$subtitle = 'Monitor daily driver check-ins, verify shift durations, and export payroll-ready attendance reports.';
require __DIR__.'/header.php';

$today = date('Y-m-d');
$from = $_GET['from'] ?? date('Y-m-01');
$to = $_GET['to'] ?? $today;
$selectedStaff = (int)($_GET['user_id'] ?? 0);

foreach ([$from, $to] as $d) {
    $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $d);
    if (!$dt || $dt->format('Y-m-d') !== $d) {
        $from = date('Y-m-01'); $to = $today; break;
    }
}
if ($from > $to) [$from, $to] = [$to, $from];

// Get staff list (delivery personnel and any operational staff)
$staffMembers = query("SELECT id, name, email, role FROM users WHERE role IN ('delivery', 'admin') ORDER BY role ASC, name ASC")->fetchAll();

// Summary stats for period
$statsSql = "SELECT 
    COUNT(*) as total_shifts,
    COALESCE(SUM(working_hours), 0) as total_hours,
    COALESCE(SUM(check_out IS NULL), 0) as open_shifts,
    COALESCE(AVG(working_hours), 0) as avg_shift_hours
  FROM staff_attendance 
  WHERE attendance_date >= ? AND attendance_date <= ?";
$statsParams = [$from, $to];
if ($selectedStaff > 0) {
    $statsSql .= " AND user_id = ?";
    $statsParams[] = $selectedStaff;
}
$summary = one($statsSql, $statsParams);

// Main attendance list
$sql = "SELECT a.*, u.name as staff_name, u.role as staff_role, u.email as staff_email 
        FROM staff_attendance a 
        JOIN users u ON u.id = a.user_id 
        WHERE a.attendance_date >= ? AND a.attendance_date <= ?";
$params = [$from, $to];
if ($selectedStaff > 0) {
    $sql .= " AND a.user_id = ?";
    $params[] = $selectedStaff;
}
$sql .= " ORDER BY a.attendance_date DESC, a.check_in DESC";
$records = query($sql, $params)->fetchAll();
?>

<!-- KPI Stat Cards -->
<div class="stat-grid">
  <div class="stat-card">
    <div class="stat-top"><span>Recorded Shifts</span><i class="bi bi-calendar-check"></i></div>
    <strong><?= (int)$summary['total_shifts'] ?></strong>
    <small>From <?= date('d M', strtotime($from)) ?> to <?= date('d M Y', strtotime($to)) ?></small>
  </div>
  <div class="stat-card">
    <div class="stat-top"><span>Total Working Hours</span><i class="bi bi-clock-history"></i></div>
    <strong class="text-primary"><?= number_format((float)$summary['total_hours'], 2) ?> hrs</strong>
    <small>Sum of completed shifts</small>
  </div>
  <div class="stat-card">
    <div class="stat-top"><span>Average Shift Duration</span><i class="bi bi-hourglass-split"></i></div>
    <strong><?= number_format((float)$summary['avg_shift_hours'], 2) ?> hrs</strong>
    <small>Per completed attendance shift</small>
  </div>
  <div class="stat-card">
    <div class="stat-top"><span>Active / Missing Checkouts</span><i class="bi bi-exclamation-circle"></i></div>
    <strong class="<?= (int)$summary['open_shifts'] > 0 ? 'text-warning' : '' ?>"><?= (int)$summary['open_shifts'] ?></strong>
    <small>Currently open shift sessions</small>
  </div>
</div>

<!-- Filter and Export Actions -->
<div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-4">
  <!-- Filter Form -->
  <form method="get" class="d-flex flex-wrap gap-2 align-items-center m-0">
    <div class="d-flex align-items-center gap-1">
      <label class="small text-muted mb-0">From:</label>
      <input type="date" name="from" class="form-control form-control-sm" value="<?= e($from) ?>">
    </div>
    <div class="d-flex align-items-center gap-1">
      <label class="small text-muted mb-0">To:</label>
      <input type="date" name="to" class="form-control form-control-sm" value="<?= e($to) ?>">
    </div>

    <select name="user_id" class="form-select form-select-sm" style="width: auto;">
      <option value="0">All Staff Members</option>
      <?php foreach ($staffMembers as $sm): ?>
        <option value="<?= $sm['id'] ?>" <?= $selectedStaff === (int)$sm['id'] ? 'selected' : '' ?>>
          <?= e($sm['name']) ?> (<?= e(ucfirst($sm['role'])) ?>)
        </option>
      <?php endforeach ?>
    </select>

    <button type="submit" class="btn btn-sm btn-outline-secondary">
      <i class="bi bi-filter"></i> Apply
    </button>
  </form>

  <!-- Export CSV Button -->
  <form action="<?= e(url('actions/handle.php')) ?>" method="post" class="m-0">
    <?= csrf() ?>
    <input type="hidden" name="action" value="export_attendance_csv">
    <input type="hidden" name="from" value="<?= e($from) ?>">
    <input type="hidden" name="to" value="<?= e($to) ?>">
    <input type="hidden" name="user_id" value="<?= $selectedStaff ?>">
    <button type="submit" class="btn btn-sm btn-success">
      <i class="bi bi-file-earmark-spreadsheet me-1"></i> Export Attendance CSV
    </button>
  </form>
</div>

<!-- Attendance Records Table -->
<section class="panel">
  <div class="panel-heading">
    <div>
      <h2>Attendance Records Log</h2>
      <p>Timezone: Asia/Colombo (+05:30). Official server logged timestamps.</p>
    </div>
  </div>

  <?php if (!$records): ?>
    <div class="empty py-5">
      <i class="bi bi-calendar-x text-muted" style="font-size:36px;"></i>
      <p class="mt-2 mb-0">No attendance records found for the selected period.</p>
    </div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table align-middle">
        <thead>
          <tr>
            <th>Staff Member</th>
            <th>Date</th>
            <th>Check In</th>
            <th>Check Out</th>
            <th>Duration</th>
            <th>Status</th>
            <th>Notes</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($records as $r): 
            $isOverdue = (!$r['check_out'] && $r['attendance_date'] < $today);
          ?>
            <tr class="<?= $isOverdue ? 'table-warning' : '' ?>">
              <td>
                <strong><?= e($r['staff_name']) ?></strong>
                <small class="d-block text-muted">
                  <span class="badge bg-light text-dark border"><?= e(ucfirst($r['staff_role'])) ?></span>
                  <?= e($r['staff_email']) ?>
                </small>
              </td>
              <td><strong><?= date('d M Y', strtotime($r['attendance_date'])) ?></strong></td>
              <td><?= date('h:i A', strtotime($r['check_in'])) ?></td>
              <td>
                <?php if ($r['check_out']): ?>
                  <?= date('h:i A', strtotime($r['check_out'])) ?>
                <?php elseif ($isOverdue): ?>
                  <span class="badge bg-danger"><i class="bi bi-exclamation-triangle"></i> Missing Check-out</span>
                <?php else: ?>
                  <span class="badge bg-warning text-dark"><i class="bi bi-clock"></i> Active Shift</span>
                <?php endif ?>
              </td>
              <td>
                <?php if ($r['working_hours'] !== null): ?>
                  <strong class="text-primary"><?= number_format((float)$r['working_hours'], 2) ?> hrs</strong>
                <?php else: ?>
                  <span class="text-muted">—</span>
                <?php endif ?>
              </td>
              <td>
                <span class="badge bg-<?= $r['check_out'] ? 'success-subtle text-success border border-success-subtle' : 'primary-subtle text-primary border border-primary-subtle' ?>">
                  <?= $r['check_out'] ? 'Completed' : 'On Duty' ?>
                </span>
              </td>
              <td>
                <small class="text-secondary"><?= e($r['notes'] ?: '—') ?></small>
              </td>
            </tr>
          <?php endforeach ?>
        </tbody>
      </table>
    </div>
  <?php endif ?>
</section>


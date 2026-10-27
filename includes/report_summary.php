<?php
$today = date('Y-m-d');
if (!isset($from, $to)) {
    $range = $_GET['range'] ?? '';
    if ($range === 'today') {
        $from = $today; $to = $today;
    } elseif ($range === '7d') {
        $from = date('Y-m-d', strtotime('-6 days')); $to = $today;
    } elseif ($range === '30d') {
        $from = date('Y-m-d', strtotime('-29 days')); $to = $today;
    } elseif ($range === 'month') {
        $from = date('Y-m-01'); $to = $today;
    } elseif (isset($_GET['from'], $_GET['to']) && is_string($_GET['from']) && is_string($_GET['to'])) {
        $from = $_GET['from']; $to = $_GET['to']; $range = 'custom';
    } else {
        $range = 'month';
        $from = date('Y-m-01'); $to = $today;
    }

    foreach ([$from, $to] as $d) {
        $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $d);
        if (!$dt || $dt->format('Y-m-d') !== $d) {
            $from = date('Y-m-01'); $to = $today; $range = 'month'; break;
        }
    }
    if ($from > $to) [$from, $to] = [$to, $from];
    $end = date('Y-m-d', strtotime($to . ' +1 day'));
} else {
    $range = $_GET['range'] ?? 'custom';
    if ($from === $today && $to === $today) $range = 'today';
    elseif ($from === date('Y-m-d', strtotime('-6 days')) && $to === $today) $range = '7d';
    elseif ($from === date('Y-m-d', strtotime('-29 days')) && $to === $today) $range = '30d';
    elseif ($from === date('Y-m-01') && $to === $today) $range = 'month';
}
if (!isset($end)) {
    $end = date('Y-m-d', strtotime($to . ' +1 day'));
}

$currentScript = basename($_SERVER['SCRIPT_NAME'] ?? 'dashboard.php');
$filterUrl = url('admin/' . ($currentScript === 'reports.php' ? 'reports.php' : 'dashboard.php'));

// 1. Chart 1: Revenue Trend (Collected COD Payments only)
$revDailyRows = query(
    "SELECT DATE(paid_at) as d, SUM(total_amount) as t 
     FROM bookings 
     WHERE payment_status = 'Paid' AND paid_at >= ? AND paid_at < ? 
     GROUP BY DATE(paid_at) ORDER BY d ASC",
    [$from, $end]
)->fetchAll(PDO::FETCH_KEY_PAIR);

$revLabels = [];
$revValues = [];
$dtStart = new DateTimeImmutable($from);
$dtEnd = new DateTimeImmutable($to);
$interval = new DateInterval('P1D');
$period = new DatePeriod($dtStart, $interval, $dtEnd->modify('+1 day'));
$countDays = iterator_count($period);
foreach ($period as $dt) {
    $k = $dt->format('Y-m-d');
    $revLabels[] = $countDays <= 14 ? $dt->format('D, d M') : $dt->format('d M');
    $revValues[] = (float)($revDailyRows[$k] ?? 0);
}

// 2. Chart 2: Booking Status Distribution (by booking creation date)
$statusRows = query(
    "SELECT booking_status, COUNT(*) as c 
     FROM bookings 
     WHERE created_at >= ? AND created_at < ? 
     GROUP BY booking_status",
    [$from, $end]
)->fetchAll(PDO::FETCH_KEY_PAIR);

$statusLabels = ['Pending', 'Accepted', 'Processing', 'Out for Delivery', 'Completed', 'Cancelled / Rejected'];
$statusValues = [
    (int)($statusRows['Pending'] ?? 0),
    (int)($statusRows['Accepted'] ?? 0),
    (int)($statusRows['Picked Up'] ?? 0) + (int)($statusRows['Washing'] ?? 0) + (int)($statusRows['Ironing'] ?? 0) + (int)($statusRows['Ready for Delivery'] ?? 0),
    (int)($statusRows['Out for Delivery'] ?? 0),
    (int)($statusRows['Delivered'] ?? 0),
    (int)($statusRows['Cancelled'] ?? 0) + (int)($statusRows['Rejected'] ?? 0)
];
$statusColors = ['#f59e0b', '#3b82f6', '#06b6d4', '#8b5cf6', '#10b981', '#ef4444'];
$totalStatusBookings = array_sum($statusValues);

// 3. Chart 3: Top 5 Laundry Services
$serviceRows = query(
    "SELECT bi.service_name, SUM(bi.quantity) as qty 
     FROM booking_items bi 
     JOIN bookings b ON b.id = bi.booking_id 
     WHERE b.created_at >= ? AND b.created_at < ? 
     GROUP BY bi.service_name 
     ORDER BY qty DESC 
     LIMIT 5",
    [$from, $end]
)->fetchAll();

$serviceLabels = array_column($serviceRows, 'service_name');
$serviceValues = array_map('intval', array_column($serviceRows, 'qty'));

// 4. Chart 4: Delivery Performance
$driverRows = query(
    "SELECT u.name, COUNT(*) as delivered_count 
     FROM bookings b 
     JOIN delivery_assignments da ON da.booking_id = b.id 
     JOIN users u ON u.id = da.delivery_person_id 
     WHERE b.booking_status = 'Delivered' AND b.created_at >= ? AND b.created_at < ? 
     GROUP BY u.id, u.name 
     ORDER BY delivered_count DESC",
    [$from, $end]
)->fetchAll();

$driverLabels = array_column($driverRows, 'name');
$driverValues = array_map('intval', array_column($driverRows, 'delivered_count'));
?>

<!-- Analytics Filter Bar -->
<div class="analytics-filter-bar">
  <div class="analytics-presets">
    <span class="d-none d-md-inline text-muted me-1" style="font-size:12px; font-weight:600;">Time Period:</span>
    <a href="<?= e($filterUrl) ?>?range=today" class="preset-pill <?= $range === 'today' ? 'active' : '' ?>">Today</a>
    <a href="<?= e($filterUrl) ?>?range=7d" class="preset-pill <?= $range === '7d' ? 'active' : '' ?>">Last 7 days</a>
    <a href="<?= e($filterUrl) ?>?range=30d" class="preset-pill <?= $range === '30d' ? 'active' : '' ?>">Last 30 days</a>
    <a href="<?= e($filterUrl) ?>?range=month" class="preset-pill <?= $range === 'month' ? 'active' : '' ?>">This month</a>
  </div>
  <form method="get" action="<?= e($filterUrl) ?>" class="analytics-custom-form">
    <label class="d-flex align-items-center gap-1 m-0 text-muted" style="font-size:12px;">From:
      <input type="date" name="from" value="<?= e($from) ?>" class="form-control" required>
    </label>
    <label class="d-flex align-items-center gap-1 m-0 text-muted" style="font-size:12px;">To:
      <input type="date" name="to" value="<?= e($to) ?>" class="form-control" required>
    </label>
    <button type="submit" class="btn btn-primary btn-sm">Filter</button>
  </form>
</div>

<?php $fin = financial_summary($from, $end); ?>
<div class="row g-3 mb-4">
  <div class="col-md-4">
    <div class="p-3 rounded border" style="background:var(--surface, #fff);">
      <small class="text-muted d-block text-uppercase" style="font-size:11px; letter-spacing:0.5px;">Collected COD Revenue</small>
      <strong class="text-success" style="font-size:22px;"><?= money($fin['revenue']) ?></strong>
    </div>
  </div>
  <div class="col-md-4">
    <div class="p-3 rounded border" style="background:var(--surface, #fff);">
      <small class="text-muted d-block text-uppercase" style="font-size:11px; letter-spacing:0.5px;">Operating Expenses</small>
      <strong class="text-danger" style="font-size:22px;"><?= money($fin['expenses']) ?></strong>
      <a href="<?= e(url('admin/expenses.php?from='.$from.'&to='.$to)) ?>" class="small text-muted float-end">Details <i class="bi bi-arrow-right"></i></a>
    </div>
  </div>
  <div class="col-md-4">
    <div class="p-3 rounded border" style="background:var(--surface, #fff);">
      <small class="text-muted d-block text-uppercase" style="font-size:11px; letter-spacing:0.5px;">Estimated Operating Profit</small>
      <strong class="<?= $fin['profit'] >= 0 ? 'text-primary' : 'text-danger' ?>" style="font-size:22px;"><?= money($fin['profit']) ?></strong>
    </div>
  </div>
</div>

<!-- 4 Interactive Charts Grid -->
<div class="analytics-charts-grid">
  <!-- Chart 1: Revenue Trend -->
  <div class="chart-box">
    <div class="chart-box-header">
      <div>
        <h3>Collected Revenue Trend</h3>
        <p>Daily COD collected from <?= e(date('d M Y', strtotime($from))) ?> to <?= e(date('d M Y', strtotime($to))) ?></p>
      </div>
      <span class="badge bg-primary-subtle text-primary" style="font-size:11px;">LKR</span>
    </div>
    <div class="chart-canvas-container">
      <canvas id="revenue-chart" aria-label="Daily collected COD revenue chart" role="img"></canvas>
    </div>
    <div class="chart-fallback">
      <?php foreach(array_slice($revLabels, -7, 7, true) as $idx => $lbl): ?>
        <span><?= e($lbl) ?>: <?= money($revValues[$idx] ?? 0) ?></span>
      <?php endforeach ?>
    </div>
  </div>

  <!-- Chart 2: Booking Status Distribution -->
  <div class="chart-box">
    <div class="chart-box-header">
      <div>
        <h3>Booking Status Distribution</h3>
        <p>Breakdown of <?= $totalStatusBookings ?> booking<?= $totalStatusBookings === 1 ? '' : 's' ?> created in period</p>
      </div>
      <i class="bi bi-pie-chart text-muted" style="font-size:18px;"></i>
    </div>
    <?php if ($totalStatusBookings === 0): ?>
      <div class="empty py-4">
        <i class="bi bi-basket text-muted" style="font-size:24px;"></i>
        <p class="mb-0 mt-2">No bookings placed in this date range.</p>
      </div>
    <?php else: ?>
      <div class="chart-canvas-container">
        <canvas id="chart-status-dist" aria-label="Booking status distribution doughnut chart" role="img"></canvas>
      </div>
      <div class="chart-fallback">
        <?php foreach($statusLabels as $i => $sName): ?>
          <span><?= e($sName) ?>: <?= $statusValues[$i] ?></span>
        <?php endforeach ?>
      </div>
    <?php endif ?>
  </div>

  <!-- Chart 3: Most Popular Services -->
  <div class="chart-box">
    <div class="chart-box-header">
      <div>
        <h3>Most Popular Services</h3>
        <p>Top laundry services by booked item volume</p>
      </div>
      <i class="bi bi-tag text-muted" style="font-size:18px;"></i>
    </div>
    <?php if (empty($serviceLabels)): ?>
      <div class="empty py-4">
        <i class="bi bi-tag text-muted" style="font-size:24px;"></i>
        <p class="mb-0 mt-2">No service orders recorded in this date range.</p>
      </div>
    <?php else: ?>
      <div class="chart-canvas-container">
        <canvas id="chart-top-services" aria-label="Top laundry services horizontal bar chart" role="img"></canvas>
      </div>
      <div class="chart-fallback">
        <?php foreach($serviceLabels as $i => $sName): ?>
          <span><?= e($sName) ?>: <?= $serviceValues[$i] ?> items</span>
        <?php endforeach ?>
      </div>
    <?php endif ?>
  </div>

  <!-- Chart 4: Delivery Performance -->
  <div class="chart-box">
    <div class="chart-box-header">
      <div>
        <h3>Delivery Performance</h3>
        <p>Delivered orders completed per delivery partner</p>
      </div>
      <i class="bi bi-truck text-muted" style="font-size:18px;"></i>
    </div>
    <?php if (empty($driverLabels)): ?>
      <div class="empty py-4">
        <i class="bi bi-truck text-muted" style="font-size:24px;"></i>
        <p class="mb-0 mt-2">No deliveries completed in this date range.</p>
      </div>
    <?php else: ?>
      <div class="chart-canvas-container">
        <canvas id="chart-delivery-perf" aria-label="Delivery performance bar chart" role="img"></canvas>
      </div>
      <div class="chart-fallback">
        <?php foreach($driverLabels as $i => $dName): ?>
          <span><?= e($dName) ?>: <?= $driverValues[$i] ?> delivered</span>
        <?php endforeach ?>
      </div>
    <?php endif ?>
  </div>
</div>

<script src="<?= e(url('assets/vendor/chart.umd.min.js')) ?>"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
  if (typeof Chart === 'undefined') return;

  function getThemeColors() {
    const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
    return {
      text: isDark ? '#cbd5e1' : '#64748b',
      grid: isDark ? '#334155' : '#eef2f6',
      tooltipBg: isDark ? '#1e293b' : '#0f172a'
    };
  }

  const tc = getThemeColors();
  const charts = [];

  // Chart 1: Revenue Trend
  const ctxRev = document.getElementById('revenue-chart');
  if (ctxRev) {
    charts.push(new Chart(ctxRev, {
      type: 'bar',
      data: {
        labels: <?= json_encode($revLabels) ?>,
        datasets: [{
          label: 'Collected Revenue',
          data: <?= json_encode($revValues) ?>,
          backgroundColor: '#0f766e',
          hoverBackgroundColor: '#14b8a6',
          borderRadius: 6,
          maxBarThickness: 32
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: {
            callbacks: {
              label: (ctx) => ' LKR ' + Number(ctx.raw).toLocaleString('en-LK', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
            }
          }
        },
        scales: {
          y: {
            beginAtZero: true,
            ticks: {
              color: tc.text,
              callback: (v) => 'LKR ' + v
            },
            grid: { color: tc.grid }
          },
          x: {
            ticks: { color: tc.text },
            grid: { display: false }
          }
        }
      }
    }));
  }

  // Chart 2: Status Distribution
  const ctxStatus = document.getElementById('chart-status-dist');
  if (ctxStatus) {
    charts.push(new Chart(ctxStatus, {
      type: 'doughnut',
      data: {
        labels: <?= json_encode($statusLabels) ?>,
        datasets: [{
          data: <?= json_encode($statusValues) ?>,
          backgroundColor: <?= json_encode($statusColors) ?>,
          borderWidth: 2,
          borderColor: document.documentElement.getAttribute('data-theme') === 'dark' ? '#1e293b' : '#ffffff'
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            position: 'right',
            labels: {
              color: tc.text,
              font: { size: 11 },
              boxWidth: 12,
              padding: 10
            }
          }
        },
        cutout: '65%'
      }
    }));
  }

  // Chart 3: Top 5 Services
  const ctxService = document.getElementById('chart-top-services');
  if (ctxService && <?= count($serviceLabels) ?> > 0) {
    charts.push(new Chart(ctxService, {
      type: 'bar',
      indexAxis: 'y',
      data: {
        labels: <?= json_encode($serviceLabels) ?>,
        datasets: [{
          label: 'Total Items',
          data: <?= json_encode($serviceValues) ?>,
          backgroundColor: '#14b8a6',
          borderRadius: 6,
          maxBarThickness: 24
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
          x: {
            beginAtZero: true,
            ticks: { color: tc.text, stepSize: 1 },
            grid: { color: tc.grid }
          },
          y: {
            ticks: { color: tc.text },
            grid: { display: false }
          }
        }
      }
    }));
  }

  // Chart 4: Delivery Performance
  const ctxDriver = document.getElementById('chart-delivery-perf');
  if (ctxDriver && <?= count($driverLabels) ?> > 0) {
    charts.push(new Chart(ctxDriver, {
      type: 'bar',
      data: {
        labels: <?= json_encode($driverLabels) ?>,
        datasets: [{
          label: 'Delivered Orders',
          data: <?= json_encode($driverValues) ?>,
          backgroundColor: '#3b82f6',
          borderRadius: 6,
          maxBarThickness: 36
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
          y: {
            beginAtZero: true,
            ticks: { color: tc.text, stepSize: 1 },
            grid: { color: tc.grid }
          },
          x: {
            ticks: { color: tc.text },
            grid: { display: false }
          }
        }
      }
    }));
  }

  // Theme change dynamic listener
  window.addEventListener('themeChanged', function () {
    const next = getThemeColors();
    const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
    charts.forEach(ch => {
      if (ch.options.scales) {
        if (ch.options.scales.x && ch.options.scales.x.ticks) ch.options.scales.x.ticks.color = next.text;
        if (ch.options.scales.x && ch.options.scales.x.grid) ch.options.scales.x.grid.color = next.grid;
        if (ch.options.scales.y && ch.options.scales.y.ticks) ch.options.scales.y.ticks.color = next.text;
        if (ch.options.scales.y && ch.options.scales.y.grid) ch.options.scales.y.grid.color = next.grid;
      }
      if (ch.options.plugins && ch.options.plugins.legend && ch.options.plugins.legend.labels) {
        ch.options.plugins.legend.labels.color = next.text;
      }
      if (ch.config.type === 'doughnut') {
        ch.data.datasets.forEach(ds => {
          ds.borderColor = isDark ? '#1e293b' : '#ffffff';
        });
      }
      ch.update();
    });
  });
});
</script>

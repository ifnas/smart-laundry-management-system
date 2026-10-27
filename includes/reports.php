<?php
$title='Sales reports'; $subtitle='A clear view of bookings and cash actually collected.';
$range = $_GET['range'] ?? '';
$today = date('Y-m-d');
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

foreach([$from,$to] as $date) { 
    $dt=DateTimeImmutable::createFromFormat('!Y-m-d',$date); 
    if(!$dt || $dt->format('Y-m-d')!==$date) { 
        $from=date('Y-m-01'); $to=$today; $range='month'; break; 
    } 
}
if($from>$to) [$from,$to]=[$to,$from];
$end=date('Y-m-d',strtotime($to.' +1 day'));
$rows=query("SELECT b.*,u.name customer_name FROM bookings b JOIN users u ON u.id=b.customer_id WHERE b.payment_status='Paid' AND b.paid_at>=? AND b.paid_at<? ORDER BY b.paid_at DESC",[$from,$end])->fetchAll();
$bookingCount=one('SELECT COUNT(*) n FROM bookings WHERE created_at>=? AND created_at<?',[$from,$end])['n'];
$finSummary = financial_summary($from, $end);
require __DIR__.'/header.php'; 
require __DIR__.'/report_summary.php'; 
?>
<section class="panel mt-4">
  <div class="panel-heading">
    <div>
      <h2>Daily sales & bookings</h2>
      <p>Filter by date to review collected COD payments and operating profit.</p>
    </div>
    <button type="button" class="btn btn-light" id="print-report"><i class="bi bi-printer"></i> Print report</button>
  </div>
  <div class="row g-3 mb-4">
    <div class="col-md-4">
      <div class="p-3 rounded border" style="background:var(--surface-subtle, #f8fafc);">
        <small class="text-muted d-block mb-1">Collected Revenue</small>
        <strong class="text-success h5 mb-0"><?= money($finSummary['revenue']) ?></strong>
      </div>
    </div>
    <div class="col-md-4">
      <div class="p-3 rounded border" style="background:var(--surface-subtle, #f8fafc);">
        <small class="text-muted d-block mb-1">Operating Expenses</small>
        <strong class="text-danger h5 mb-0"><?= money($finSummary['expenses']) ?></strong>
      </div>
    </div>
    <div class="col-md-4">
      <div class="p-3 rounded border" style="background:var(--surface-subtle, #f8fafc);">
        <small class="text-muted d-block mb-1">Estimated Operating Profit</small>
        <strong class="<?= $finSummary['profit'] >= 0 ? 'text-success' : 'text-danger' ?> h5 mb-0"><?= money($finSummary['profit']) ?></strong>
      </div>
    </div>
  </div>
  <div class="report-totals">
    <span>Bookings created: <strong><?= $bookingCount ?></strong></span>
    <span>Payments collected: <strong><?= count($rows) ?></strong></span>
    <span>Collected revenue: <strong><?= money(array_sum(array_column($rows,'total_amount'))) ?></strong></span>
  </div>
  <?php orders_table($rows) ?>
  <p class="muted mt-3 mb-0">Booking counts use the creation date. Revenue uses the payment collection date (Asia/Colombo). Unpaid, rejected and cancelled orders do not contribute to revenue.</p>
</section>

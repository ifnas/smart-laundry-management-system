<?php
$title='Overview'; $subtitle='Welcome back, '.$u['name'].'. Here’s what’s happening today.';
require __DIR__.'/header.php';
if($role==='admin') {
    $stats=one("SELECT 
      COUNT(*) total, 
      COALESCE(SUM(booking_status='Pending'),0) pending, 
      COALESCE(SUM(booking_status NOT IN ('Delivered','Cancelled','Rejected')),0) active,
      COALESCE(SUM(booking_status='Delivered'),0) completed, 
      COALESCE(SUM(CASE WHEN payment_status='Paid' THEN total_amount ELSE 0 END),0) revenue,
      COALESCE(SUM(CASE WHEN payment_status='Paid' AND DATE(paid_at)=CURDATE() THEN total_amount ELSE 0 END),0) rev_today,
      COALESCE(SUM(CASE WHEN payment_status='Paid' AND paid_at>=DATE_FORMAT(CURDATE(),'%Y-%m-01') THEN total_amount ELSE 0 END),0) rev_month
    FROM bookings");
    $customers=(int)one("SELECT COUNT(*) n FROM users WHERE role='customer'")['n'];
    $lowStock=low_stock_items_count();
    $cards=[
      ['basket','Total bookings',$stats['total']],
      ['hourglass-split','Pending bookings',$stats['pending']],
      ['clock','Active orders',$stats['active']],
      ['check2-circle','Completed orders',$stats['completed']],
      ['cash-stack','Total COD revenue',money($stats['revenue'])],
      ['sun','Collected today',money($stats['rev_today'])],
      ['calendar-check','Collected this month',money($stats['rev_month'])],
      ['people','Registered customers',$customers],
      ['exclamation-triangle','Low stock items',$lowStock]
    ];
    $rows=query('SELECT b.*,u.name customer_name FROM bookings b JOIN users u ON u.id=b.customer_id ORDER BY b.id DESC LIMIT 8')->fetchAll();
} elseif($role==='customer') {
    $stats=one("SELECT COUNT(*) total, COALESCE(SUM(booking_status NOT IN ('Delivered','Rejected','Cancelled')),0) active, COALESCE(SUM(booking_status='Delivered'),0) completed FROM bookings WHERE customer_id=?",[$u['id']]);
    $cards=[['basket','Total bookings',$stats['total']],['clock','In progress',$stats['active']],['check2-circle','Freshly delivered',$stats['completed']]];
    $rows=query('SELECT * FROM bookings WHERE customer_id=? ORDER BY id DESC LIMIT 6',[$u['id']])->fetchAll();
    echo '<div class="welcome-banner"><div><span class="eyebrow">LET US TAKE IT FROM HERE</span><h2>Your next fresh start is a pickup away.</h2><p>Choose your services. Pick a date. We’ll handle the rest.</p><a class="btn btn-white" href="'.e(url('customer/new_booking.php')).'">Book a pickup <i class="bi bi-plus-lg"></i></a></div><i class="bi bi-basket2"></i></div>';
} else {
    $stats=one("SELECT COUNT(*) total, COALESCE(SUM(b.booking_status='Delivered'),0) completed, COALESCE(SUM(b.booking_status NOT IN ('Delivered','Cancelled','Rejected')),0) active FROM bookings b JOIN delivery_assignments a ON a.booking_id=b.id WHERE a.delivery_person_id=?",[$u['id']]);
    $cards=[['truck','Assigned orders',$stats['total']],['clock','Active deliveries',$stats['active']],['check2-circle','Completed deliveries',$stats['completed']]];
    $rows=query("SELECT b.*,u.name customer_name FROM bookings b JOIN users u ON u.id=b.customer_id JOIN delivery_assignments a ON a.booking_id=b.id WHERE a.delivery_person_id=? AND b.booking_status NOT IN ('Delivered','Cancelled','Rejected') ORDER BY b.pickup_date,b.id LIMIT 8",[$u['id']])->fetchAll();
}
?>
<?php if($role==='admin' && $lowStock > 0): ?>
  <div class="alert alert-warning d-flex align-items-center justify-content-between mb-4">
    <div>
      <i class="bi bi-exclamation-triangle-fill me-2"></i>
      <strong>Low Stock Alert:</strong> <?= $lowStock ?> inventory <?= $lowStock === 1 ? 'item is' : 'items are' ?> at or below minimum threshold.
    </div>
    <a href="<?= e(url('admin/inventory.php?filter=low_stock')) ?>" class="btn btn-sm btn-dark">Review Stock <i class="bi bi-arrow-right"></i></a>
  </div>
<?php endif ?>
<div class="stat-grid"><?php foreach($cards as [$icon,$label,$value]): ?><div class="stat-card"><div class="stat-top"><span><?= $label ?></span><i class="bi bi-<?= $icon ?>"></i></div><strong><?= e($value) ?></strong></div><?php endforeach ?></div>
<?php if($role==='admin'): require __DIR__.'/report_summary.php'; endif ?>
<section class="panel"><div class="panel-heading"><div><h2><?= $role==='delivery'?'Your active route':'Recent bookings' ?></h2><p>All the details, at a glance.</p></div><a class="text-link" href="<?= e(url($role.'/'.($role==='delivery'?'orders.php':'bookings.php'))) ?>">View all <i class="bi bi-arrow-right"></i></a></div><?php orders_table($rows) ?></section>

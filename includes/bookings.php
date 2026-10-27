<?php
$title=$role==='delivery'?($page==='completed'?'Completed deliveries':'Assigned orders'):($role==='admin'?'All bookings':'My bookings');
$subtitle='Follow every order from pickup to fresh delivery.';
$filter=is_string($_GET['status']??null)?$_GET['status']:'';
$params=[]; $where=[];
if($role==='customer') { $where[]='b.customer_id=?'; $params[]=$u['id']; }
if($role==='delivery') { $where[]='a.delivery_person_id=?'; $params[]=$u['id']; $where[]=$page==='completed'?"b.booking_status='Delivered'":"b.booking_status NOT IN ('Delivered','Rejected','Cancelled')"; }
if($filter!=='') { $where[]='b.booking_status=?'; $params[]=$filter; }
$rows=query('SELECT b.*,u.name customer_name FROM bookings b JOIN users u ON u.id=b.customer_id LEFT JOIN delivery_assignments a ON a.booking_id=b.id'.($where?' WHERE '.implode(' AND ',$where):'').' ORDER BY b.id DESC',$params)->fetchAll();
require __DIR__.'/header.php'; ?>
<section class="panel"><div class="panel-heading"><form method="get" class="filter-form"><label class="visually-hidden" for="status-filter">Booking status</label><select id="status-filter" name="status" class="form-select"><option value="">All statuses</option><?php foreach(['Pending','Accepted','Picked Up','Washing','Ironing','Ready for Delivery','Out for Delivery','Delivered','Cancelled','Rejected'] as $s): ?><option <?= $filter===$s?'selected':'' ?>><?= e($s) ?></option><?php endforeach ?></select><button class="btn btn-light">Filter</button><a href="<?= e(url($role.'/'.basename($_SERVER['SCRIPT_NAME']))) ?>" class="text-link">Reset</a></form><?php if($role==='customer'): ?><a class="btn btn-primary" href="<?= e(url('customer/new_booking.php')) ?>"><i class="bi bi-plus-lg"></i> New booking</a><?php endif ?></div><?php orders_table($rows) ?></section>

<?php
require __DIR__.'/bootstrap.php';
$u=protect($role);
$views=['dashboard'=>'dashboard','new_booking'=>'new_booking','bookings'=>'bookings','orders'=>'bookings','completed'=>'bookings','booking_details'=>'booking_details','order_details'=>'booking_details','assign_delivery'=>'booking_details','services'=>'manage_services','customers'=>'people','delivery_staff'=>'people','reports'=>'reports','profile'=>'profile','coupons'=>'coupons','slots'=>'slots','schedules'=>'schedules','expenses'=>'expenses','reviews'=>'reviews','inventory'=>'inventory','inventory_item'=>'inventory_item','stock_movements'=>'stock_movements','suppliers'=>'suppliers','attendance'=>($role==='delivery'?'staff_attendance':'attendance'),'backups'=>'backups','restore_preview'=>'restore_preview'];
require __DIR__.'/'.$views[$page].'.php';
require __DIR__.'/footer.php';

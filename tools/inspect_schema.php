<?php
require __DIR__ . '/../config/database.php';
$tables = ['bookings','coupons','coupon_redemptions','expenses','expense_categories','pickup_slots','reviews'];
foreach ($tables as $t) {
    echo "=== CREATE TABLE $t ===\n";
    $row = db()->query("SHOW CREATE TABLE $t")->fetch(PDO::FETCH_NUM);
    echo $row[1] . "\n\n";
}


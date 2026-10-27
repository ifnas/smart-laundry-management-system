<?php
require __DIR__.'/../includes/bootstrap.php';
verify_post();
$action=field('action',40);
$back='login.php';
try {
    if($action==='login') {
        $back='login.php';
        if(($_SESSION['login_after']??0)>time()) throw new DomainException('Please wait a moment before trying again.');
        $u=one('SELECT * FROM users WHERE email=? AND is_active=1',[email(field('email',190))]);
        if(!$u || !password_verify(field('password',200),$u['password'])) { $_SESSION['login_after']=time()+2; throw new DomainException('Incorrect email or password.'); }
        session_regenerate_id(true); $_SESSION['uid']=$u['id']; $_SESSION['csrf']=bin2hex(random_bytes(32)); unset($_SESSION['login_after']);
        redirect($u['role'].'/dashboard.php');
    }
    if($action==='register') {
        $back='register.php'; $name=field('name',100); $email=email(field('email',190)); $phone=phone(field('phone',25)); $address=field('address',2000); $password=password_input();
        if(one('SELECT id FROM users WHERE email=?',[$email])) throw new DomainException('This email is already registered.');
        query('INSERT INTO users(name,email,phone,password,role,address) VALUES(?,?,?,?,?,?)',[$name,$email,$phone,password_hash($password,PASSWORD_DEFAULT),'customer',$address]);
        flash('Account created. You can now sign in.'); redirect('login.php');
    }
    $u=protect(); $back=$u['role'].'/dashboard.php';
    if($action==='logout') {
        $_SESSION=[];
        if(ini_get("session.use_cookies")) {
            $params=session_get_cookie_params();
            setcookie(session_name(),'',time()-42000,$params["path"],$params["domain"],$params["secure"],$params["httponly"]);
        }
        session_destroy();
        redirect('login.php');
    }
    if($action==='profile') {
        protect('customer'); $back='customer/profile.php';
        $name=field('name',100); $email=email(field('email',190)); $phone=phone(field('phone',25)); $address=field('address',2000);
        if(one('SELECT id FROM users WHERE email=? AND id<>?',[$email,$u['id']])) throw new DomainException('This email is already in use.');
        query('UPDATE users SET name=?,email=?,phone=?,address=? WHERE id=?',[$name,$email,$phone,$address,$u['id']]);
        flash('Profile updated.'); redirect($back);
    }
    if($action==='mark_notification_read') {
        $nid = (int)field('notification_id', 20);
        query('UPDATE notifications SET is_read=1 WHERE id=? AND user_id=?', [$nid, $u['id']]);
        $target = field('redirect_to', 255, false);
        if ($target && !str_starts_with($target, 'http://') && !str_starts_with($target, 'https://')) {
            redirect($target);
        }
        redirect($u['role'].'/dashboard.php');
    }
    if($action==='mark_all_notifications_read') {
        query('UPDATE notifications SET is_read=1 WHERE user_id=?', [$u['id']]);
        flash('All notifications marked as read.');
        $target = field('redirect_to', 255, false);
        if ($target && !str_starts_with($target, 'http://') && !str_starts_with($target, 'https://')) {
            redirect($target);
        }
        redirect($u['role'].'/dashboard.php');
    }
    if($action==='validate_coupon') {
        $code = (string)($_POST['coupon_code'] ?? '');
        $subtotal = (float)($_POST['subtotal'] ?? 0);
        $res = validate_coupon($code, $subtotal);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($res);
        exit;
    }
    if($action==='get_slots') {
        $date = field('date', 10);
        $slots = get_available_slots($date);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => true, 'slots' => $slots]);
        exit;
    }
    if($action==='create_booking') {
        protect('customer'); $back='customer/new_booking.php';
        $address=field('pickup_address',2000); $phone=phone(field('pickup_phone',25)); $date=field('pickup_date',10); $notes=field('special_instructions',2000,false);
        $dt=DateTimeImmutable::createFromFormat('!Y-m-d',$date);
        if(!$dt || $dt->format('Y-m-d')!==$date || $date<date('Y-m-d') || $date>date('Y-m-d',strtotime('+90 days'))) throw new DomainException('Select a pickup date within the next 90 days.');
        $quantities=$_POST['quantity']??[]; if(!is_array($quantities)) throw new DomainException('Invalid quantities.');
        
        $slotId = isset($_POST['pickup_slot_id']) && (int)$_POST['pickup_slot_id'] > 0 ? (int)$_POST['pickup_slot_id'] : null;

        db()->beginTransaction();

        if ($slotId !== null) {
            $slot = one('SELECT * FROM pickup_slots WHERE id=? AND is_active=1 FOR UPDATE', [$slotId]);
            if (!$slot) throw new DomainException('Selected pickup time window is not available.');
            $bookedCount = (int)one("SELECT COUNT(*) cnt FROM bookings WHERE pickup_date=? AND pickup_slot_id=? AND booking_status NOT IN ('Cancelled', 'Rejected') FOR UPDATE", [$date, $slotId])['cnt'];
            if ($bookedCount >= (int)$slot['daily_capacity']) {
                throw new DomainException('The selected pickup time window is fully booked for this date. Please choose another slot.');
            }
        }

        $items=[]; $total=0;
        foreach($quantities as $id=>$raw) {
            if(!is_scalar($raw) || filter_var($raw,FILTER_VALIDATE_INT)===false || (int)$raw<0 || (int)$raw>100) throw new DomainException('Quantities must be whole numbers from 0 to 100.');
            $qty=(int)$raw; if(!$qty) continue;
            $s=one('SELECT * FROM services WHERE id=? AND is_active=1 FOR UPDATE',[(int)$id]);
            if(!$s) throw new DomainException('A selected service is no longer available.');
            $cents=(int)round((float)$s['price']*100); $subtotalCents=$cents*$qty; $total+=$subtotalCents;
            $items[]=[$s,$qty,$subtotalCents];
        }
        if(!$items) throw new DomainException('Choose at least one service.');

        $subtotal = $total / 100;
        $couponRaw = trim((string)($_POST['coupon_code'] ?? ''));
        $couponCode = null;
        $couponId = null;
        $discountAmount = 0.00;
        $finalTotal = $subtotal;

        if ($couponRaw !== '') {
            $cRes = validate_coupon($couponRaw, $subtotal);
            if (!$cRes['ok']) {
                throw new DomainException($cRes['message']);
            }
            $coupon = $cRes['coupon'];
            $couponId = (int)$coupon['id'];
            $couponCode = (string)$coupon['code'];
            $discountAmount = (float)$cRes['discount'];
            $finalTotal = (float)$cRes['final_total'];

            $lockedCoupon = one('SELECT id, usage_limit, times_used FROM coupons WHERE id=? AND is_active=1 FOR UPDATE', [$couponId]);
            if ($lockedCoupon && $lockedCoupon['usage_limit'] !== null && (int)$lockedCoupon['times_used'] >= (int)$lockedCoupon['usage_limit']) {
                throw new DomainException('This coupon has reached its maximum usage limit.');
            }
        }

        $bnum = 'SL-'.date('ymd').'-'.strtoupper(bin2hex(random_bytes(4)));
        $invNum = 'INV-'.date('ymd').'-'.strtoupper(bin2hex(random_bytes(4)));
        $trackToken = bin2hex(random_bytes(16));
        query('INSERT INTO bookings(booking_number,invoice_number,tracking_token,customer_id,pickup_address,pickup_phone,pickup_date,subtotal_amount,discount_amount,coupon_id,coupon_code,pickup_slot_id,total_amount,special_instructions) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?)',[$bnum,$invNum,$trackToken,$u['id'],$address,$phone,$date,$subtotal,$discountAmount,$couponId,$couponCode,$slotId,$finalTotal,$notes]);
        $id=(int)db()->lastInsertId();
        foreach($items as [$s,$qty,$subtotalCents]) query('INSERT INTO booking_items(booking_id,service_id,service_name,quantity,unit_price,subtotal) VALUES(?,?,?,?,?,?)',[$id,$s['id'],$s['service_name'],$qty,$s['price'],$subtotalCents/100]);
        
        if ($couponId !== null && $discountAmount > 0) {
            query('INSERT INTO coupon_redemptions(coupon_id, booking_id, customer_id, discount_amount) VALUES(?,?,?,?)', [$couponId, $id, $u['id'], $discountAmount]);
            query('UPDATE coupons SET times_used = times_used + 1 WHERE id=?', [$couponId]);
        }

        history($id,'Pending',(int)$u['id']);

        // In-App Notification triggers
        notify((int)$u['id'], 'Booking Placed', 'Your pickup request for ' . date('d M Y', strtotime($date)) . ' has been placed. Reference: ' . $bnum, $id, 'success');
        $admins = query("SELECT id FROM users WHERE role='admin' AND is_active=1")->fetchAll();
        foreach ($admins as $adm) {
            notify((int)$adm['id'], 'New Booking Received', 'Customer ' . $u['name'] . ' placed order ' . $bnum . ' for ' . date('d M Y', strtotime($date)) . '.', $id, 'info');
        }

        db()->commit(); flash('Booking placed! We will review your pickup request.'); redirect('customer/booking_details.php?id='.$id);
    }
    if($action==='review_save') {
        protect('customer');
        $bId = (int)field('booking_id', 20);
        $back = 'customer/booking_details.php?id=' . $bId;
        $b = one('SELECT * FROM bookings WHERE id=? AND customer_id=?', [$bId, $u['id']]);
        if (!$b) throw new DomainException('Booking not found.');
        if ($b['booking_status'] !== 'Delivered') {
            throw new DomainException('You can only rate and review orders that have been successfully delivered.');
        }
        $rating = (int)field('rating', 2);
        if ($rating < 1 || $rating > 5) throw new DomainException('Rating must be between 1 and 5 stars.');
        $reviewText = field('review_text', 2000, false);

        query(
            'INSERT INTO reviews(booking_id, customer_id, rating, review_text) VALUES(?,?,?,?) 
             ON DUPLICATE KEY UPDATE rating=VALUES(rating), review_text=VALUES(review_text), updated_at=NOW()',
            [$bId, $u['id'], $rating, $reviewText]
        );
        flash('Thank you for your rating and review!');
        redirect($back);
    }
    if($action==='status') {
        $id=(int)field('booking_id',20); $back=$u['role'].'/'.($u['role']==='delivery'?'order_details.php':'booking_details.php').'?id='.$id;
        $next=field('status',30); db()->beginTransaction(); $b=booking($id,$u,true);
        if(!in_array($next,next_statuses($u['role'],$b['booking_status']),true)) throw new DomainException('This status change is not permitted. Reload the booking.');
        if($next==='Delivered') {
            if(($_POST['cod_confirm']??'')!=='1') throw new DomainException('Confirm that you collected the full COD amount.');
            if($b['payment_status']!=='Unpaid') throw new DomainException('Payment has already been collected.');
            query("UPDATE bookings SET booking_status='Delivered',payment_status='Paid',paid_at=NOW(),collected_by=? WHERE id=?",[$u['id'],$id]);
        } else query('UPDATE bookings SET booking_status=? WHERE id=?',[$next,$id]);
        history($id,$next,(int)$u['id']);

        // In-App Notification triggers on status changes
        $cust = (int)$b['customer_id'];
        $bRef = $b['booking_number'];
        if ($next === 'Cancelled') {
            notify($cust, 'Booking Cancelled', 'Your booking ' . $bRef . ' has been cancelled.', $id, 'warning');
            $admins = query("SELECT id FROM users WHERE role='admin' AND is_active=1")->fetchAll();
            foreach ($admins as $adm) notify((int)$adm['id'], 'Booking Cancelled', 'Booking ' . $bRef . ' was cancelled by customer.', $id, 'warning');
        } elseif ($next === 'Accepted') {
            notify($cust, 'Booking Accepted', 'Your laundry booking ' . $bRef . ' has been accepted and scheduled for pickup.', $id, 'success');
        } elseif ($next === 'Rejected') {
            notify($cust, 'Booking Declined', 'Your booking ' . $bRef . ' could not be accepted.', $id, 'danger');
        } elseif ($next === 'Picked Up') {
            notify($cust, 'Laundry Picked Up', 'Your laundry for order ' . $bRef . ' has been collected by our delivery driver.', $id, 'info');
        } elseif ($next === 'Washing') {
            notify($cust, 'Washing Started', 'Your clothes for order ' . $bRef . ' are now being washed with care.', $id, 'info');
        } elseif ($next === 'Ironing') {
            notify($cust, 'Ironing Started', 'Your clothes for order ' . $bRef . ' are being pressed and folded.', $id, 'info');
        } elseif ($next === 'Ready for Delivery') {
            notify($cust, 'Ready for Delivery', 'Your laundry is clean, packed and ready for delivery.', $id, 'success');
            if (!empty($b['delivery_person_id'])) {
                notify((int)$b['delivery_person_id'], 'Order Ready', 'Assigned order ' . $bRef . ' is ready for doorstep delivery.', $id, 'info');
            }
        } elseif ($next === 'Out for Delivery') {
            notify($cust, 'Out for Delivery', 'Your assigned driver is on the way to deliver your fresh laundry.', $id, 'info');
        } elseif ($next === 'Delivered') {
            notify($cust, 'Order Delivered', 'Your laundry has been delivered. Thank you for choosing Smart Laundry!', $id, 'success');
            notify($cust, 'Payment Received', 'Cash on Delivery payment of ' . money($b['total_amount']) . ' has been recorded.', $id, 'success');
            $admins = query("SELECT id FROM users WHERE role='admin' AND is_active=1")->fetchAll();
            foreach ($admins as $adm) notify((int)$adm['id'], 'Order Delivered & Paid', 'Order ' . $bRef . ' completed and COD collected by ' . $u['name'] . '.', $id, 'success');
        }

        db()->commit(); flash('Booking updated to '.$next.'.'); redirect($back);
    }
    if($action==='attendance_checkin') {
        if (!in_array($u['role'], ['delivery', 'admin'], true)) {
            throw new DomainException('Only authorized staff members can record attendance.');
        }
        $back = ($u['role'] === 'delivery') ? 'delivery/attendance.php' : 'admin/attendance.php';
        $notes = field('notes', 255, false);
        $res = record_staff_checkin((int)$u['id'], $notes);
        flash('Checked in successfully at ' . date('h:i A', strtotime($res['check_in'])) . '. Have a productive shift!');
        redirect($back);
    }
    if($action==='attendance_checkout') {
        if (!in_array($u['role'], ['delivery', 'admin'], true)) {
            throw new DomainException('Only authorized staff members can record attendance.');
        }
        $back = ($u['role'] === 'delivery') ? 'delivery/attendance.php' : 'admin/attendance.php';
        $notes = field('notes', 255, false);
        $res = record_staff_checkout((int)$u['id'], $notes);
        flash('Checked out successfully at ' . date('h:i A', strtotime($res['check_out'])) . '. Recorded shift: ' . $res['working_hours'] . ' hours.');
        redirect($back);
    }
    protect('admin');
    if($action==='assign') {
        $id=(int)field('booking_id',20); $back='admin/booking_details.php?id='.$id; $driver=(int)field('delivery_person_id',20);
        db()->beginTransaction(); $b=booking($id,$u,true);
        if($b['booking_status']!=='Accepted') throw new DomainException('Delivery can only be assigned or reassigned before pickup, after acceptance.');
        $drv = one("SELECT id, name FROM users WHERE id=? AND role='delivery' AND is_active=1 FOR UPDATE",[$driver]);
        if(!$drv) throw new DomainException('Select an active delivery person.');
        query('INSERT INTO delivery_assignments(booking_id,delivery_person_id) VALUES(?,?) ON DUPLICATE KEY UPDATE delivery_person_id=VALUES(delivery_person_id),assigned_at=NOW()',[$id,$driver]);
        
        // Notifications on assignment
        notify($driver, 'New Delivery Assignment', 'You have been assigned to order ' . $b['booking_number'] . ' for customer ' . $b['customer_name'] . '.', $id, 'info');
        notify((int)$b['customer_id'], 'Driver Assigned', 'Delivery person ' . $drv['name'] . ' has been assigned to your order ' . $b['booking_number'] . '.', $id, 'info');

        db()->commit(); flash('Delivery person assigned.'); redirect($back);
    }
    if($action==='service_save' || $action==='service_delete') {
        $back='admin/services.php'; $id=(int)($_POST['id']??0);
        if($action==='service_delete') {
            query('UPDATE services SET is_active=0 WHERE id=?',[$id]); flash('Service removed from the catalogue. Historical bookings are preserved.'); redirect($back);
        }
        $name=field('service_name',100); $desc=field('description',500); $price=field('price',12);
        if(!preg_match('/^\d{1,6}(\.\d{1,2})?$/',$price) || (float)$price<=0) throw new DomainException('Enter a positive price with up to two decimal places.');
        $active=isset($_POST['is_active'])?1:0;
        if($id) query('UPDATE services SET service_name=?,description=?,price=?,is_active=? WHERE id=?',[$name,$desc,$price,$active,$id]);
        else query('INSERT INTO services(service_name,description,price,is_active) VALUES(?,?,?,?)',[$name,$desc,$price,$active]);
        flash('Service saved.'); redirect($back);
    }
    if($action==='staff_save') {
        $back='admin/delivery_staff.php'; $id=(int)($_POST['id']??0);
        $name=field('name',100); $email=email(field('email',190)); $phone=phone(field('phone',25)); $password=password_input($id>0); $active=isset($_POST['is_active'])?1:0;
        db()->beginTransaction();
        if($id && !one("SELECT id FROM users WHERE id=? AND role='delivery' FOR UPDATE",[$id])) throw new DomainException('Delivery account not found.');
        if(one('SELECT id FROM users WHERE email=? AND id<>?',[$email,$id])) throw new DomainException('This email is already in use.');
        if($id && !$active && one("SELECT a.id FROM delivery_assignments a JOIN bookings b ON b.id=a.booking_id WHERE a.delivery_person_id=? AND b.booking_status NOT IN ('Delivered','Cancelled','Rejected')",[$id])) throw new DomainException('Reassign or complete this driver’s active orders before deactivating the account.');
        if($id) {
            query("UPDATE users SET name=?,email=?,phone=?,is_active=? WHERE id=? AND role='delivery'",[$name,$email,$phone,$active,$id]);
            if($password!=='') query('UPDATE users SET password=? WHERE id=?',[password_hash($password,PASSWORD_DEFAULT),$id]);
        } else query("INSERT INTO users(name,email,phone,password,role,address,is_active) VALUES(?,?,?,?,'delivery','',?)",[$name,$email,$phone,password_hash($password,PASSWORD_DEFAULT),$active]);
        db()->commit(); flash('Delivery account saved.'); redirect($back);
    }
    if($action==='coupon_save') {
        $back = 'admin/coupons.php';
        $id = (int)($_POST['id'] ?? 0);
        $code = strtoupper(field('code', 50));
        $existing = one('SELECT id FROM coupons WHERE code=? AND id<>?', [$code, $id]);
        if ($existing) throw new DomainException('This coupon code is already taken. Duplicate codes are prevented.');
        $type = field('discount_type', 10);
        if (!in_array($type, ['fixed', 'percent'], true)) throw new DomainException('Invalid discount type.');
        $val = (float)field('discount_value', 12);
        if ($val <= 0) throw new DomainException('Discount value must be greater than zero.');
        if ($type === 'percent' && $val > 100) throw new DomainException('Percentage discount cannot exceed 100%.');
        $minAmount = isset($_POST['min_booking_amount']) && trim((string)$_POST['min_booking_amount']) !== '' ? (float)$_POST['min_booking_amount'] : 0.00;
        if ($minAmount < 0) $minAmount = 0.00;
        $maxDiscount = isset($_POST['max_discount_amount']) && trim((string)$_POST['max_discount_amount']) !== '' ? (float)$_POST['max_discount_amount'] : null;
        $usageLimit = isset($_POST['usage_limit']) && trim((string)$_POST['usage_limit']) !== '' ? (int)$_POST['usage_limit'] : null;
        $expiry = !empty($_POST['expiry_date']) ? field('expiry_date', 10) : null;
        if ($expiry !== null) {
            $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $expiry);
            if (!$dt || $dt->format('Y-m-d') !== $expiry) throw new DomainException('Invalid expiry date.');
        }
        $active = isset($_POST['is_active']) ? 1 : 0;
        if ($id > 0) {
            query('UPDATE coupons SET code=?, discount_type=?, discount_value=?, min_booking_amount=?, max_discount_amount=?, usage_limit=?, expiry_date=?, is_active=? WHERE id=?', [$code, $type, $val, $minAmount, $maxDiscount, $usageLimit, $expiry, $active, $id]);
            flash('Coupon updated.');
        } else {
            query('INSERT INTO coupons(code, discount_type, discount_value, min_booking_amount, max_discount_amount, usage_limit, expiry_date, is_active) VALUES(?,?,?,?,?,?,?,?)', [$code, $type, $val, $minAmount, $maxDiscount, $usageLimit, $expiry, $active]);
            flash('Coupon created.');
        }
        redirect($back);
    }
    if($action==='coupon_toggle') {
        $back = 'admin/coupons.php';
        $id = (int)field('id', 20);
        query('UPDATE coupons SET is_active = IF(is_active=1, 0, 1) WHERE id=?', [$id]);
        flash('Coupon status updated.');
        redirect($back);
    }
    if($action==='expense_category_save') {
        $back = 'admin/expenses.php';
        $name = field('name', 100);
        $desc = field('description', 255, false);
        if (one('SELECT id FROM expense_categories WHERE name=?', [$name])) throw new DomainException('An expense category with this name already exists.');
        query('INSERT INTO expense_categories(name, description) VALUES(?, ?)', [$name, $desc]);
        flash('Expense category added.');
        redirect($back);
    }
    if($action==='expense_save') {
        $back = 'admin/expenses.php';
        $id = (int)($_POST['id'] ?? 0);
        $catId = (int)field('category_id', 20);
        if (!one('SELECT id FROM expense_categories WHERE id=? AND is_active=1', [$catId])) throw new DomainException('Please select a valid expense category.');
        $title = field('title', 200);
        $amount = (float)field('amount', 12);
        if ($amount <= 0) throw new DomainException('Enter a positive expense amount.');
        $expDate = field('expense_date', 10);
        $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $expDate);
        if (!$dt || $dt->format('Y-m-d') !== $expDate) throw new DomainException('Invalid expense date.');
        $notes = field('notes', 2000, false);
        if ($id > 0) {
            query('UPDATE expenses SET category_id=?, title=?, amount=?, expense_date=?, notes=? WHERE id=?', [$catId, $title, $amount, $expDate, $notes, $id]);
            flash('Expense updated.');
        } else {
            query('INSERT INTO expenses(category_id, title, amount, expense_date, notes, created_by) VALUES(?,?,?,?,?,?)', [$catId, $title, $amount, $expDate, $notes, $u['id']]);
            flash('Expense recorded.');
        }
        redirect($back);
    }
    if($action==='expense_delete') {
        $back = 'admin/expenses.php';
        $id = (int)field('id', 20);
        query('UPDATE expenses SET is_active=0 WHERE id=?', [$id]);
        flash('Expense removed.');
        redirect($back);
    }
    if($action==='export_expenses_csv') {
        $from = field('from', 10);
        $to = field('to', 10);
        $catId = (int)($_POST['category_id'] ?? 0);
        $sql = "SELECT e.id, e.expense_date, c.name as category, e.title, e.amount, u.name as recorded_by, e.notes 
                FROM expenses e 
                JOIN expense_categories c ON c.id=e.category_id 
                JOIN users u ON u.id=e.created_by 
                WHERE e.is_active=1 AND e.expense_date >= ? AND e.expense_date <= ?";
        $params = [$from, $to];
        if ($catId > 0) {
            $sql .= " AND e.category_id = ?";
            $params[] = $catId;
        }
        $sql .= " ORDER BY e.expense_date DESC, e.id DESC";
        $rows = query($sql, $params)->fetchAll();

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="expenses_' . $from . '_to_' . $to . '.csv"');
        echo "ID,Expense Date,Category,Title,Amount (LKR),Recorded By,Notes\r\n";
        $out = fopen('php://output', 'w');
        foreach ($rows as $r) {
            fputcsv($out, [
                $r['id'],
                $r['expense_date'],
                $r['category'],
                $r['title'],
                number_format((float)$r['amount'], 2, '.', ''),
                $r['recorded_by'],
                $r['notes'] ?? ''
            ]);
        }
        fclose($out);
        exit;
    }
    if($action==='slot_save') {
        $back = 'admin/slots.php';
        $id = (int)($_POST['id'] ?? 0);
        $name = field('slot_name', 100);
        $start = field('start_time', 10);
        $end = field('end_time', 10);
        $cap = (int)field('daily_capacity', 10);
        if ($cap < 1) throw new DomainException('Daily capacity must be at least 1.');
        $active = isset($_POST['is_active']) ? 1 : 0;
        if ($id > 0) {
            query('UPDATE pickup_slots SET slot_name=?, start_time=?, end_time=?, daily_capacity=?, is_active=? WHERE id=?', [$name, $start, $end, $cap, $active, $id]);
            flash('Pickup slot updated.');
        } else {
            query('INSERT INTO pickup_slots(slot_name, start_time, end_time, daily_capacity, is_active) VALUES(?,?,?,?,?)', [$name, $start, $end, $cap, $active]);
            flash('Pickup slot created.');
        }
        redirect($back);
    }
    if($action==='inventory_category_save') {
        $back = 'admin/inventory.php';
        $id = (int)($_POST['id'] ?? 0);
        $name = field('name', 100);
        $desc = field('description', 255, false);
        $active = isset($_POST['is_active']) ? 1 : 0;

        $dup = one('SELECT id FROM inventory_categories WHERE name=? AND id<>?', [$name, $id]);
        if ($dup) throw new DomainException('An inventory category with this name already exists.');

        if ($id > 0) {
            query('UPDATE inventory_categories SET name=?, description=?, is_active=? WHERE id=?', [$name, $desc, $active, $id]);
            flash('Inventory category updated.');
        } else {
            query('INSERT INTO inventory_categories (name, description, is_active) VALUES (?, ?, ?)', [$name, $desc, $active]);
            flash('Inventory category created.');
        }
        redirect($back);
    }
    if($action==='inventory_supplier_save') {
        $back = 'admin/suppliers.php';
        $id = (int)($_POST['id'] ?? 0);
        $name = field('name', 150);
        $contact = field('contact_person', 100, false);
        $emailVal = !empty($_POST['email']) ? email(field('email', 190)) : null;
        $phoneVal = !empty($_POST['phone']) ? phone(field('phone', 30)) : null;
        $address = field('address', 1000, false);
        $active = isset($_POST['is_active']) ? 1 : 0;

        if ($id > 0) {
            query('UPDATE inventory_suppliers SET name=?, contact_person=?, email=?, phone=?, address=?, is_active=? WHERE id=?', [$name, $contact, $emailVal, $phoneVal, $address, $active, $id]);
            flash('Supplier updated.');
        } else {
            query('INSERT INTO inventory_suppliers (name, contact_person, email, phone, address, is_active) VALUES (?, ?, ?, ?, ?, ?)', [$name, $contact, $emailVal, $phoneVal, $address, $active]);
            flash('Supplier added.');
        }
        redirect($back);
    }
    if($action==='inventory_item_save') {
        $back = 'admin/inventory.php';
        $id = (int)($_POST['id'] ?? 0);
        $catId = (int)field('category_id', 20);
        if (!one('SELECT id FROM inventory_categories WHERE id=? AND is_active=1', [$catId])) {
            throw new DomainException('Please select a valid inventory category.');
        }
        $suppId = isset($_POST['supplier_id']) && (int)$_POST['supplier_id'] > 0 ? (int)$_POST['supplier_id'] : null;
        if ($suppId !== null && !one('SELECT id FROM inventory_suppliers WHERE id=?', [$suppId])) {
            $suppId = null;
        }

        $sku = strtoupper(field('sku', 50));
        $dup = one('SELECT id FROM inventory_items WHERE sku=? AND id<>?', [$sku, $id]);
        if ($dup) throw new DomainException('An item with this SKU already exists.');

        $name = field('name', 150);
        $unit = field('unit', 30);
        $minStock = (float)field('min_stock', 12);
        if ($minStock < 0) throw new DomainException('Minimum stock cannot be negative.');
        $cost = (float)field('purchase_cost', 12);
        if ($cost < 0) throw new DomainException('Purchase cost cannot be negative.');
        $active = isset($_POST['is_active']) ? 1 : 0;

        if ($id > 0) {
            query(
                'UPDATE inventory_items SET category_id=?, supplier_id=?, sku=?, name=?, unit=?, min_stock=?, purchase_cost=?, is_active=? WHERE id=?',
                [$catId, $suppId, $sku, $name, $unit, $minStock, $cost, $active, $id]
            );
            $item = one('SELECT * FROM inventory_items WHERE id=?', [$id]);
            update_inventory_alert_state($id, (float)$item['current_stock'], $minStock, $name, $sku, $unit);
            flash('Inventory item updated.');
        } else {
            $initialStock = isset($_POST['initial_stock']) && trim((string)$_POST['initial_stock']) !== '' ? (float)$_POST['initial_stock'] : 0.0;
            if ($initialStock < 0) throw new DomainException('Initial stock cannot be negative.');

            query(
                'INSERT INTO inventory_items (category_id, supplier_id, sku, name, unit, min_stock, purchase_cost, current_stock, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$catId, $suppId, $sku, $name, $unit, $minStock, $cost, $initialStock, $active]
            );
            $newId = (int)db()->lastInsertId();

            if ($initialStock > 0) {
                query(
                    'INSERT INTO inventory_movements (item_id, movement_type, quantity, balance_after, unit, reference, notes, supplier_id, performed_by) VALUES (?, "IN", ?, ?, ?, "Initial Stock", "Initial inventory setup", ?, ?)',
                    [$newId, $initialStock, $initialStock, $unit, $suppId, $u['id']]
                );
            }
            update_inventory_alert_state($newId, $initialStock, $minStock, $name, $sku, $unit);
            flash('Inventory item created.');
        }
        redirect($back);
    }
    if($action==='inventory_item_toggle') {
        $back = 'admin/inventory.php';
        $id = (int)field('id', 20);
        query('UPDATE inventory_items SET is_active = IF(is_active=1, 0, 1) WHERE id=?', [$id]);
        flash('Item status updated.');
        redirect($back);
    }
    if($action==='inventory_movement_save') {
        $back = 'admin/stock_movements.php';
        $itemId = (int)field('item_id', 20);
        $type = strtoupper(field('movement_type', 10));
        $qty = (float)field('quantity', 12);
        $ref = field('reference', 100, false);
        $notes = field('notes', 1000, false);
        $suppId = isset($_POST['supplier_id']) && (int)$_POST['supplier_id'] > 0 ? (int)$_POST['supplier_id'] : null;

        $res = record_inventory_movement($itemId, $type, $qty, $ref, $notes, (int)$u['id'], $suppId);
        flash('Stock movement recorded. New balance: ' . number_format($res['new_stock'], 2) . '.');
        redirect($back);
    }
    if($action==='export_attendance_csv') {
        $from = field('from', 10);
        $to = field('to', 10);
        $staffId = (int)($_POST['user_id'] ?? 0);

        $sql = "SELECT a.id, u.name as staff_name, u.role, a.attendance_date, a.check_in, a.check_out, a.working_hours, a.status, a.notes 
                FROM staff_attendance a 
                JOIN users u ON u.id=a.user_id 
                WHERE a.attendance_date >= ? AND a.attendance_date <= ?";
        $params = [$from, $to];
        if ($staffId > 0) {
            $sql .= " AND a.user_id = ?";
            $params[] = $staffId;
        }
        $sql .= " ORDER BY a.attendance_date DESC, a.check_in DESC";
        $rows = query($sql, $params)->fetchAll();

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="attendance_' . $from . '_to_' . $to . '.csv"');
        echo "ID,Staff Member,Role,Date,Check In,Check Out,Working Hours,Status,Notes\r\n";
        $out = fopen('php://output', 'w');
        foreach ($rows as $r) {
            fputcsv($out, [
                $r['id'],
                $r['staff_name'],
                $r['role'],
                $r['attendance_date'],
                $r['check_in'] ? date('Y-m-d H:i:s', strtotime($r['check_in'])) : '',
                $r['check_out'] ? date('Y-m-d H:i:s', strtotime($r['check_out'])) : 'Active / Pending',
                $r['working_hours'] !== null ? number_format((float)$r['working_hours'], 2) : '0.00',
                $r['status'],
                $r['notes'] ?? ''
            ]);
        }
        fclose($out);
        exit;
    }
    if($action==='backup_create') {
        $back = 'admin/backups.php';
        $res = generate_database_backup((int)$u['id']);
        flash('Database backup created successfully: ' . $res['filename'] . ' (' . number_format($res['file_size'] / 1024, 1) . ' KB, ' . $res['table_count'] . ' tables).');
        redirect($back);
    }
    if($action==='backup_download') {
        $filename = field('filename', 190);
        if (!preg_match('/^backup_smart_laundry_db_\d{8}_\d{6}\.sql$/', $filename)) {
            throw new DomainException('Invalid backup filename.');
        }

        $backupDir = get_backup_directory();
        $filepath = $backupDir . '/' . $filename;

        if (!file_exists($filepath)) {
            throw new DomainException('Requested backup file was not found on server.');
        }

        $realDir = realpath($backupDir);
        $realFile = realpath($filepath);
        if ($realFile === false || !str_starts_with($realFile, $realDir)) {
            throw new DomainException('Unauthorized path access.');
        }

        header('Content-Type: application/sql');
        header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
        header('Content-Length: ' . filesize($filepath));
        header('Pragma: no-cache');
        header('Expires: 0');
        readfile($filepath);
        exit;
    }
    throw new DomainException('Unknown action.');
} catch(DomainException $e) {
    if(db()->inTransaction()) db()->rollBack(); flash($e->getMessage(),'danger'); redirect($back);
} catch(PDOException $e) {
    if(db()->inTransaction()) db()->rollBack(); error_log((string)$e); flash('Unable to save. Please check for duplicate details and try again.','danger'); redirect($back);
}

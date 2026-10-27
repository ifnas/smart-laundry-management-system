<?php
function e($v): string { return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8'); }
function url(string $path=''): string {
    $script=str_replace('\\','/',$_SERVER['SCRIPT_NAME']);
    $base=dirname($script);
    if(in_array(basename($base),['admin','customer','delivery','actions'])) $base=dirname($base);
    return rtrim(str_replace('\\','/',$base),'/.').'/'.$path;
}
function app_base_url(): string {
    if ($custom = getenv('APP_BASE_URL')) {
        return rtrim($custom, '/');
    }
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $base = dirname($script);
    if (in_array(basename($base), ['admin', 'customer', 'delivery', 'actions'])) {
        $base = dirname($base);
    }
    return rtrim($protocol . $host . rtrim(str_replace('\\', '/', $base), '/.'), '/');
}
function redirect(string $path): never { header('Location: '.url($path)); exit; }
function query(string $sql,array $args=[]): PDOStatement { $s=db()->prepare($sql); $s->execute($args); return $s; }
function one(string $sql,array $args=[]): ?array { return query($sql,$args)->fetch() ?: null; }
function user(): ?array {
    static $loaded=false,$u=null;
    if(!$loaded) { $loaded=true; if(isset($_SESSION['uid'])) $u=one('SELECT * FROM users WHERE id=? AND is_active=1',[$_SESSION['uid']]); }
    return $u;
}
function protect(?string $role=null): array {
    $u=user(); if(!$u) redirect('login.php');
    if($role && $u['role']!==$role) { http_response_code(403); exit('Access denied.'); }
    return $u;
}
function csrf(): string { return '<input type="hidden" name="csrf" value="'.e($_SESSION['csrf']).'">'; }
function verify_post(): void {
    if($_SERVER['REQUEST_METHOD']!=='POST') { http_response_code(405); exit('POST required.'); }
    if(!is_string($_POST['csrf']??null) || !hash_equals($_SESSION['csrf'],$_POST['csrf'])) {
        header(($_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.1').' 419 Session Expired');
        http_response_code(419);
        exit('Session expired. Reload the form and try again.');
    }
}
function flash(string $message,string $type='success'): void { $_SESSION['flash']=[$message,$type]; }
function money($n): string { return 'LKR '.number_format((float)$n,2); }
function field(string $key,int $max=255,bool $required=true): string {
    $v=$_POST[$key]??''; if(!is_string($v)) throw new DomainException('Invalid form value.');
    $v=trim($v); if(($required && $v==='') || strlen($v)>$max) throw new DomainException('Please check '.str_replace('_',' ',$key).'.'); return $v;
}
function phone(string $p): string { if(!preg_match('/^[+0-9 ()-]{7,25}$/',$p)) throw new DomainException('Enter a valid phone number.'); return $p; }
function email(string $s): string { if(!filter_var($s,FILTER_VALIDATE_EMAIL)) throw new DomainException('Enter a valid email address.'); return strtolower($s); }
function password_input(bool $optional=false): string { $p=field('password',200,!$optional); if($p!=='' && (strlen($p)<8 || strlen($p)>72)) throw new DomainException('Passwords must contain 8 to 72 characters.'); return $p; }
function badge(string $s): string { return '<span class="status status-'.strtolower(str_replace(' ','-',$s)).'">'.e($s).'</span>'; }
function booking(int $id,array $u,bool $lock=false): array {
    $b=one('SELECT b.*,u.name customer_name,u.email customer_email,a.delivery_person_id,d.name driver_name,ps.slot_name,ps.start_time,ps.end_time FROM bookings b JOIN users u ON u.id=b.customer_id LEFT JOIN delivery_assignments a ON a.booking_id=b.id LEFT JOIN users d ON d.id=a.delivery_person_id LEFT JOIN pickup_slots ps ON ps.id=b.pickup_slot_id WHERE b.id=?'.($lock?' FOR UPDATE':''),[$id]);
    if(!$b || ($u['role']==='customer' && $b['customer_id']!=$u['id']) || ($u['role']==='delivery' && $b['delivery_person_id']!=$u['id'])) throw new DomainException('Booking not found or access denied.');
    return $b;
}
function next_statuses(string $role,string $status): array {
    $map=['customer'=>['Pending'=>['Cancelled']], 'admin'=>['Pending'=>['Accepted','Rejected'],'Picked Up'=>['Washing'],'Washing'=>['Ironing','Ready for Delivery'],'Ironing'=>['Ready for Delivery']], 'delivery'=>['Accepted'=>['Picked Up'],'Ready for Delivery'=>['Out for Delivery'],'Out for Delivery'=>['Delivered']]];
    return $map[$role][$status]??[];
}
function history(int $id,string $status,int $uid): void { query('INSERT INTO booking_status_history(booking_id,status,changed_by) VALUES(?,?,?)',[$id,$status,$uid]); }
function order_link(array $b): string { $role=user()['role']; return url($role.'/'.($role==='delivery'?'order_details.php':'booking_details.php').'?id='.$b['id']); }
function orders_table(array $rows): void {
    if(!$rows) { echo '<div class="empty"><i class="bi bi-basket"></i><h3>No bookings yet</h3><p>Your bookings will appear here.</p></div>'; return; }
    echo '<div class="table-responsive"><table class="table align-middle"><thead><tr><th>Booking</th><th>Customer / pickup</th><th>Pickup date</th><th>Status</th><th>Total</th><th>Payment</th><th></th></tr></thead><tbody>';
    foreach($rows as $b) {
        $invBtn = user()['role'] !== 'delivery' ? '<a class="btn btn-sm btn-light" href="'.e(url('invoice.php?id='.$b['id'])).'" title="View Invoice" target="_blank"><i class="bi bi-receipt"></i></a> ' : '';
        echo '<tr><td><strong>'.e($b['booking_number']).'</strong><small>'.e(date('d M Y',strtotime($b['created_at']))).'</small></td><td>'.e($b['customer_name']??'Your pickup').'<small>'.e($b['pickup_phone']).'</small></td><td>'.e($b['pickup_date']).'</td><td>'.badge($b['booking_status']).'</td><td>'.money($b['total_amount']).'</td><td>'.badge($b['payment_status']).'</td><td><div class="table-actions">'.$invBtn.'<a class="btn btn-sm btn-light" href="'.e(order_link($b)).'">View <i class="bi bi-arrow-up-right"></i></a></div></td></tr>';
    }
    echo '</tbody></table></div>';
}

function notify(int $userId, string $title, string $message, ?int $bookingId = null, string $type = 'info'): void {
    $recent = one('SELECT id FROM notifications WHERE user_id=? AND title=? AND created_at >= NOW() - INTERVAL 10 SECOND', [$userId, $title]);
    if ($recent) return;
    query('INSERT INTO notifications(user_id,booking_id,title,message,type) VALUES(?,?,?,?,?)', [$userId, $bookingId, $title, $message, $type]);
}

function unread_notifications_count(int $userId): int {
    return (int)(one('SELECT COUNT(*) n FROM notifications WHERE user_id=? AND is_read=0', [$userId])['n'] ?? 0);
}

function recent_notifications(int $userId, int $limit = 8): array {
    $s = db()->prepare('SELECT * FROM notifications WHERE user_id=? ORDER BY id DESC LIMIT ' . (int)$limit);
    $s->execute([$userId]);
    return $s->fetchAll();
}

function time_ago(string $datetime): string {
    $time = strtotime($datetime);
    $diff = max(1, time() - $time);
    if ($diff < 60) return $diff . 's ago';
    if ($diff < 3600) return (int)($diff / 60) . 'm ago';
    if ($diff < 86400) return (int)($diff / 3600) . 'h ago';
    if ($diff < 604800) return (int)($diff / 86400) . 'd ago';
    return date('d M', $time);
}

// ============================================================================
// PHASE 2 BUSINESS MANAGEMENT HELPERS
// ============================================================================

function validate_coupon(string $code, float $subtotal): array {
    $code = strtoupper(trim($code));
    if ($code === '') {
        return ['ok' => false, 'message' => 'Please enter a coupon code.'];
    }
    $c = one('SELECT * FROM coupons WHERE code = ?', [$code]);
    if (!$c) {
        return ['ok' => false, 'message' => 'Invalid coupon code.'];
    }
    if (!$c['is_active']) {
        return ['ok' => false, 'message' => 'This coupon is currently inactive.'];
    }
    if (!empty($c['valid_from']) && $c['valid_from'] > date('Y-m-d')) {
        return ['ok' => false, 'message' => 'This coupon is not yet valid.'];
    }
    if (!empty($c['expiry_date']) && $c['expiry_date'] < date('Y-m-d')) {
        return ['ok' => false, 'message' => 'This coupon has expired.'];
    }
    if ($c['usage_limit'] !== null && (int)$c['times_used'] >= (int)$c['usage_limit']) {
        return ['ok' => false, 'message' => 'This coupon has reached its maximum usage limit.'];
    }
    if ((float)$c['min_booking_amount'] > 0 && $subtotal < (float)$c['min_booking_amount']) {
        return ['ok' => false, 'message' => 'Minimum booking amount for this coupon is ' . money($c['min_booking_amount']) . '.'];
    }

    $discount = 0.0;
    if ($c['discount_type'] === 'percent') {
        $val = (float)$c['discount_value'];
        $discount = round($subtotal * ($val / 100), 2);
        if ($c['max_discount_amount'] !== null && (float)$c['max_discount_amount'] > 0) {
            $discount = min($discount, (float)$c['max_discount_amount']);
        }
    } else {
        $discount = (float)$c['discount_value'];
    }
    $discount = min($subtotal, max(0.0, $discount));
    $finalTotal = max(0.0, round($subtotal - $discount, 2));

    return [
        'ok' => true,
        'coupon' => $c,
        'discount' => $discount,
        'final_total' => $finalTotal,
        'message' => 'Coupon applied! You save ' . money($discount) . '.'
    ];
}

function get_available_slots(string $date): array {
    $slots = query('SELECT * FROM pickup_slots WHERE is_active = 1 ORDER BY start_time ASC')->fetchAll();
    if (!$slots) return [];

    $occupied = query(
        "SELECT pickup_slot_id, COUNT(*) as cnt 
         FROM bookings 
         WHERE pickup_date = ? AND pickup_slot_id IS NOT NULL AND booking_status NOT IN ('Cancelled', 'Rejected') 
         GROUP BY pickup_slot_id",
        [$date]
    )->fetchAll(PDO::FETCH_KEY_PAIR);

    foreach ($slots as &$s) {
        $used = (int)($occupied[$s['id']] ?? 0);
        $capacity = (int)$s['daily_capacity'];
        $remaining = max(0, $capacity - $used);
        $s['booked_count'] = $used;
        $s['remaining'] = $remaining;
        $s['is_full'] = ($remaining <= 0);
    }
    unset($s);
    return $slots;
}

function rating_stats(): array {
    $row = one("SELECT COUNT(*) as total_reviews, COALESCE(AVG(rating), 0) as avg_rating FROM reviews");
    $dist = query("SELECT rating, COUNT(*) as cnt FROM reviews GROUP BY rating ORDER BY rating DESC")->fetchAll(PDO::FETCH_KEY_PAIR);
    return [
        'total' => (int)($row['total_reviews'] ?? 0),
        'avg' => round((float)($row['avg_rating'] ?? 0), 1),
        'dist' => $dist
    ];
}

function financial_summary(string $from, string $end): array {
    $rev = (float)one(
        "SELECT COALESCE(SUM(total_amount), 0) as rev 
         FROM bookings 
         WHERE payment_status = 'Paid' AND paid_at >= ? AND paid_at < ?",
        [$from, $end]
    )['rev'];

    $exp = (float)one(
        "SELECT COALESCE(SUM(amount), 0) as exp 
         FROM expenses 
         WHERE is_active = 1 AND expense_date >= ? AND expense_date < ?",
        [$from, $end]
    )['exp'];

    return [
        'revenue' => $rev,
        'expenses' => $exp,
        'profit' => $rev - $exp
    ];
}

// ============================================================================
// PHASE 3 ADVANCED MANAGEMENT HELPERS
// ============================================================================

function get_backup_directory(): string {
    // Stored strictly outside the web-accessible htdocs directory
    $dir = 'C:/xampp/backups/smart-laundry';
    if (!is_dir($dir)) {
        @mkdir($dir, 0777, true);
    }
    return $dir;
}

function update_inventory_alert_state(int $itemId, float $stock, float $minStock, string $name, string $sku, string $unit): void {
    $state = one('SELECT * FROM inventory_alert_state WHERE item_id = ?', [$itemId]);
    if (!$state) {
        query('INSERT INTO inventory_alert_state (item_id, is_alerted, alert_level) VALUES (?, 0, "NONE")', [$itemId]);
        $state = ['item_id' => $itemId, 'is_alerted' => 0, 'alert_level' => 'NONE'];
    }

    $currentLevel = 'NONE';
    if ($stock <= 0.0) {
        $currentLevel = 'OUT_OF_STOCK';
    } elseif ($stock <= $minStock) {
        $currentLevel = 'LOW';
    }

    $wasAlerted = (bool)$state['is_alerted'];
    $prevLevel = $state['alert_level'];

    if ($currentLevel !== 'NONE') {
        // Trigger alert only when not already alerted or alert level worsened
        if (!$wasAlerted || $prevLevel !== $currentLevel) {
            query(
                'UPDATE inventory_alert_state SET is_alerted = 1, alert_level = ?, last_alerted_at = NOW() WHERE item_id = ?',
                [$currentLevel, $itemId]
            );

            $admins = query("SELECT id FROM users WHERE role='admin' AND is_active=1")->fetchAll();
            $title = ($currentLevel === 'OUT_OF_STOCK') ? "Out of Stock: $name" : "Low Stock Alert: $name";
            $type = ($currentLevel === 'OUT_OF_STOCK') ? 'danger' : 'warning';
            $stockFmt = number_format($stock, 2);
            $minFmt = number_format($minStock, 2);
            $msg = "Inventory item '$name' (SKU: $sku) has reached $stockFmt $unit (min threshold: $minFmt $unit).";

            foreach ($admins as $adm) {
                notify((int)$adm['id'], $title, $msg, null, $type);
            }
        }
    } else {
        // Current level is NONE - check if previously alerted (replenished)
        if ($wasAlerted) {
            query(
                'UPDATE inventory_alert_state SET is_alerted = 0, alert_level = "NONE", last_resolved_at = NOW() WHERE item_id = ?',
                [$itemId]
            );

            $admins = query("SELECT id FROM users WHERE role='admin' AND is_active=1")->fetchAll();
            $title = "Stock Replenished: $name";
            $stockFmt = number_format($stock, 2);
            $minFmt = number_format($minStock, 2);
            $msg = "Inventory item '$name' (SKU: $sku) is now replenished at $stockFmt $unit (above threshold of $minFmt $unit).";

            foreach ($admins as $adm) {
                notify((int)$adm['id'], $title, $msg, null, 'success');
            }
        }
    }
}

function record_inventory_movement(int $itemId, string $type, float $qty, ?string $ref, ?string $notes, int $userId, ?int $supplierId = null): array {
    $type = strtoupper(trim($type));
    if (!in_array($type, ['IN', 'OUT', 'ADJUST'], true)) {
        throw new DomainException('Invalid stock movement type.');
    }
    if ($type !== 'ADJUST' && $qty <= 0) {
        throw new DomainException('Quantity must be greater than zero.');
    }
    if ($type === 'ADJUST' && $qty < 0) {
        throw new DomainException('Adjustment balance cannot be negative.');
    }

    $inTx = db()->inTransaction();
    if (!$inTx) db()->beginTransaction();

    try {
        $item = one('SELECT * FROM inventory_items WHERE id=? FOR UPDATE', [$itemId]);
        if (!$item) {
            throw new DomainException('Inventory item not found.');
        }

        $currentStock = (float)$item['current_stock'];
        $newStock = $currentStock;

        if ($type === 'IN') {
            $newStock = round($currentStock + $qty, 2);
        } elseif ($type === 'OUT') {
            if ($currentStock < $qty) {
                throw new DomainException("Insufficient stock. Available: " . number_format($currentStock, 2) . " {$item['unit']}, requested: " . number_format($qty, 2) . " {$item['unit']}.");
            }
            $newStock = round($currentStock - $qty, 2);
        } elseif ($type === 'ADJUST') {
            $newStock = round($qty, 2);
        }

        if ($newStock < 0.0) {
            throw new DomainException('Negative stock is not permitted.');
        }

        query('UPDATE inventory_items SET current_stock=?, updated_at=NOW() WHERE id=?', [$newStock, $itemId]);

        query(
            'INSERT INTO inventory_movements (item_id, movement_type, quantity, balance_after, unit, reference, notes, supplier_id, performed_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$itemId, $type, $qty, $newStock, $item['unit'], $ref, $notes, $supplierId, $userId]
        );
        $movId = (int)db()->lastInsertId();

        update_inventory_alert_state($itemId, $newStock, (float)$item['min_stock'], $item['name'], $item['sku'], $item['unit']);

        if (!$inTx) db()->commit();

        return [
            'ok' => true,
            'movement_id' => $movId,
            'item_id' => $itemId,
            'previous_stock' => $currentStock,
            'new_stock' => $newStock
        ];
    } catch (Throwable $e) {
        if (!$inTx && db()->inTransaction()) db()->rollBack();
        throw $e;
    }
}

function low_stock_items_count(): int {
    return (int)(one('SELECT COUNT(*) cnt FROM inventory_items WHERE is_active=1 AND current_stock <= min_stock')['cnt'] ?? 0);
}

function out_of_stock_items_count(): int {
    return (int)(one('SELECT COUNT(*) cnt FROM inventory_items WHERE is_active=1 AND current_stock <= 0')['cnt'] ?? 0);
}

function get_staff_today_attendance(int $userId): ?array {
    return one('SELECT * FROM staff_attendance WHERE user_id=? AND attendance_date=CURDATE() ORDER BY id DESC LIMIT 1', [$userId]);
}

function get_staff_active_attendance(int $userId): ?array {
    return one('SELECT * FROM staff_attendance WHERE user_id=? AND check_out IS NULL ORDER BY id DESC LIMIT 1', [$userId]);
}

function record_staff_checkin(int $userId, ?string $notes = null): array {
    $inTx = db()->inTransaction();
    if (!$inTx) db()->beginTransaction();

    try {
        $active = one('SELECT * FROM staff_attendance WHERE user_id=? AND check_out IS NULL FOR UPDATE', [$userId]);
        if ($active) {
            throw new DomainException('You already have an active check-in session started at ' . date('h:i A', strtotime($active['check_in'])) . '. Please check out first.');
        }

        $now = date('Y-m-d H:i:s');
        $today = date('Y-m-d');

        query(
            'INSERT INTO staff_attendance (user_id, attendance_date, check_in, status, notes) VALUES (?, ?, ?, "present", ?)',
            [$userId, $today, $now, $notes]
        );
        $attId = (int)db()->lastInsertId();

        if (!$inTx) db()->commit();

        return [
            'ok' => true,
            'id' => $attId,
            'check_in' => $now,
            'attendance_date' => $today
        ];
    } catch (Throwable $e) {
        if (!$inTx && db()->inTransaction()) db()->rollBack();
        throw $e;
    }
}

function record_staff_checkout(int $userId, ?string $notes = null): array {
    $inTx = db()->inTransaction();
    if (!$inTx) db()->beginTransaction();

    try {
        $active = one('SELECT * FROM staff_attendance WHERE user_id=? AND check_out IS NULL ORDER BY id DESC LIMIT 1 FOR UPDATE', [$userId]);
        if (!$active) {
            throw new DomainException('No active check-in session found to check out.');
        }

        $now = date('Y-m-d H:i:s');
        $inTime = strtotime($active['check_in']);
        $outTime = strtotime($now);
        $diffSeconds = max(0, $outTime - $inTime);
        $hours = round($diffSeconds / 3600, 2);

        query(
            'UPDATE staff_attendance SET check_out=?, working_hours=?, notes=COALESCE(?, notes), updated_at=NOW() WHERE id=?',
            [$now, $hours, $notes, $active['id']]
        );

        if (!$inTx) db()->commit();

        return [
            'ok' => true,
            'id' => $active['id'],
            'check_in' => $active['check_in'],
            'check_out' => $now,
            'working_hours' => $hours
        ];
    } catch (Throwable $e) {
        if (!$inTx && db()->inTransaction()) db()->rollBack();
        throw $e;
    }
}

function generate_database_backup(int $userId): array {
    $backupDir = get_backup_directory();
    $timestamp = date('Ymd_His');
    $filename = 'backup_smart_laundry_db_' . $timestamp . '.sql';
    $filepath = $backupDir . '/' . $filename;

    $pdo = db();
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    $tableCount = count($tables);

    $sql = "-- Smart Laundry Automated Database Backup\n";
    $sql .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
    $sql .= "-- Database: smart_laundry_db\n\n";
    $sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

    foreach ($tables as $t) {
        $create = $pdo->query("SHOW CREATE TABLE `$t`")->fetch(PDO::FETCH_NUM)[1];
        $sql .= "DROP TABLE IF EXISTS `$t`;\n" . $create . ";\n\n";

        $rows = $pdo->query("SELECT * FROM `$t`")->fetchAll(PDO::FETCH_ASSOC);
        if ($rows) {
            $cols = array_keys($rows[0]);
            $escapedCols = array_map(fn($c) => "`$c`", $cols);
            $colList = implode(', ', $escapedCols);

            $sql .= "INSERT INTO `$t` ($colList) VALUES\n";
            $valLines = [];
            foreach ($rows as $r) {
                $vals = [];
                foreach ($r as $v) {
                    if ($v === null) {
                        $vals[] = 'NULL';
                    } else {
                        $vals[] = $pdo->quote((string)$v);
                    }
                }
                $valLines[] = '(' . implode(', ', $vals) . ')';
            }
            $sql .= implode(",\n", $valLines) . ";\n\n";
        }
    }
    $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";

    $bytes = file_put_contents($filepath, $sql);
    if ($bytes === false || $bytes <= 0) {
        throw new DomainException('Failed to write database backup file.');
    }

    query(
        'INSERT INTO database_backup_history (filename, file_path, file_size, table_count, status, created_by) VALUES (?, ?, ?, ?, "verified", ?)',
        [$filename, $filepath, $bytes, $tableCount, $userId]
    );

    return [
        'ok' => true,
        'filename' => $filename,
        'file_path' => $filepath,
        'file_size' => $bytes,
        'table_count' => $tableCount
    ];
}


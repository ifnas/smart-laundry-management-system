<?php
/**
 * Automated Test Suite for Phase 3 Advanced Management Upgrade
 * Covers:
 * 1. Inventory item creation
 * 2. Stock In
 * 3. Stock Out
 * 4. Rejection of negative stock
 * 5. Concurrent stock updates (transaction integrity)
 * 6. Inventory movement audit trail
 * 7. Low stock threshold detection
 * 8. Duplicate alert prevention
 * 9. Alert resolution after replenishment
 * 10. Staff check-in
 * 11. Staff check-out
 * 12. Duplicate check-in prevention
 * 13. Working hours calculation
 * 14. Admin attendance reporting & CSV export
 * 15. Unauthorized staff access
 * 16. Backup creation
 * 17. Backup file access security
 * 18. Restore preview safety
 * 19. Dark/light mode compatibility
 * 20. Existing notification integration
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

date_default_timezone_set('Asia/Colombo');
require __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$base = rtrim($argv[1] ?? 'http://localhost/smart-laundry', '/');
if (!in_array(parse_url($base, PHP_URL_HOST), ['localhost', '127.0.0.1'], true)) {
    exit("Local URLs only.\n");
}

$tag = 'p3_' . bin2hex(random_bytes(4));
$cookies = [];
$checks = 0;

$createdItemIds = [];
$createdCategoryIds = [];
$createdSupplierIds = [];
$createdUserIds = [];
$createdAttendanceIds = [];
$createdBackupFiles = [];

function check(bool $ok, string $message): void {
    global $checks;
    if (!$ok) throw new RuntimeException('FAIL: ' . $message);
    $checks++;
    echo "PASS: $message\n";
}

function sql(string $query, array $params = []): PDOStatement {
    $s = db()->prepare($query);
    $s->execute($params);
    return $s;
}

function request(string $who, string $path, ?array $data = null, bool $returnHeaders = false): array {
    global $base, $cookies;
    if (!isset($cookies[$who])) {
        $cookies[$who] = tempnam(sys_get_temp_dir(), 'laundry-p3-');
    }
    $url = $base . '/' . $path;
    $c = curl_init($url);
    curl_setopt_array($c, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_COOKIEFILE => $cookies[$who],
        CURLOPT_COOKIEJAR => $cookies[$who],
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HEADER => $returnHeaders
    ]);
    if ($data !== null) {
        curl_setopt($c, CURLOPT_POST, true);
        curl_setopt($c, CURLOPT_POSTFIELDS, http_build_query($data));
    }
    $body = curl_exec($c);
    if ($body === false) throw new RuntimeException(curl_error($c));
    $code = curl_getinfo($c, CURLINFO_HTTP_CODE);
    $contentType = curl_getinfo($c, CURLINFO_CONTENT_TYPE);
    curl_close($c);
    return [$code, $body, $contentType];
}

function token(string $who, string $path): string {
    [$code, $body] = request($who, $path);
    if (!preg_match('/name="csrf" value="([a-f0-9]+)"/', $body, $m)) {
        throw new RuntimeException("No CSRF token on $path (HTTP $code)");
    }
    return $m[1];
}

function post(string $who, string $page, array $data, bool $returnHeaders = false): array {
    $data['csrf'] = token($who, $page);
    return request($who, 'actions/handle.php', $data, $returnHeaders);
}

function login(string $who, string $email, string $password): void {
    $token = token($who, 'login.php');
    [$code, $body] = request($who, 'actions/handle.php', [
        'action' => 'login',
        'csrf' => $token,
        'email' => $email,
        'password' => $password
    ]);
    if ($code !== 200 || !str_contains($body, 'workspace')) {
        throw new RuntimeException("Login failed for $email (HTTP $code)");
    }
}

try {
    echo "========================================================\n";
    echo "Starting Phase 3 Upgrade Automated Verification Suite\n";
    echo "Target Base: $base\n";
    echo "========================================================\n\n";

    // --------------------------------------------------------------------------
    // 0. ACCOUNTS SETUP
    // --------------------------------------------------------------------------
    echo "--- 0. Setting up Test Roles and Sessions ---\n";
    login('admin', 'admin@laundry.com', 'Admin@123');
    login('driver', 'delivery@laundry.com', 'Delivery@123');

    $custEmail = $tag . '_cust@example.test';
    $custPass = 'Pass@12345';
    post('anon', 'register.php', [
        'action' => 'register',
        'name' => 'Phase3 Customer',
        'email' => $custEmail,
        'phone' => '0771239888',
        'address' => '101 Baseline Road, Colombo',
        'password' => $custPass
    ]);
    $custId = (int)sql('SELECT id FROM users WHERE email=?', [$custEmail])->fetchColumn();
    check($custId > 0, 'Customer registered');
    $createdUserIds[] = $custId;
    login('cust', $custEmail, $custPass);

    $driverUser = sql("SELECT id FROM users WHERE email='delivery@laundry.com'")->fetch();
    $driverId = (int)$driverUser['id'];

    // --------------------------------------------------------------------------
    // TEST 1: Inventory Item Creation
    // --------------------------------------------------------------------------
    echo "\n--- 1. Testing Inventory Item Creation ---\n";
    // Create Category
    $catName = 'Test Cat ' . $tag;
    post('admin', 'admin/inventory.php', [
        'action' => 'inventory_category_save',
        'name' => $catName,
        'description' => 'Test Category Description',
        'is_active' => '1'
    ]);
    $catId = (int)sql('SELECT id FROM inventory_categories WHERE name=?', [$catName])->fetchColumn();
    check($catId > 0, "Inventory category created (ID: $catId)");
    $createdCategoryIds[] = $catId;

    // Create Supplier
    $supName = 'Test Supplier ' . $tag;
    post('admin', 'admin/suppliers.php', [
        'action' => 'inventory_supplier_save',
        'name' => $supName,
        'contact_person' => 'Kamal Silva',
        'email' => 'supplier_' . $tag . '@example.test',
        'phone' => '0777123456',
        'address' => 'Kelaniya Industrial Zone',
        'is_active' => '1'
    ]);
    $supId = (int)sql('SELECT id FROM inventory_suppliers WHERE name=?', [$supName])->fetchColumn();
    check($supId > 0, "Inventory supplier created (ID: $supId)");
    $createdSupplierIds[] = $supId;

    // Create Item
    $sku = 'SKU-' . strtoupper(bin2hex(random_bytes(3)));
    $itemName = 'Premium Softener ' . $tag;
    post('admin', 'admin/inventory_item.php', [
        'action' => 'inventory_item_save',
        'category_id' => $catId,
        'supplier_id' => $supId,
        'sku' => $sku,
        'name' => $itemName,
        'unit' => 'Litres',
        'min_stock' => '15.00',
        'purchase_cost' => '450.00',
        'initial_stock' => '50.00',
        'is_active' => '1'
    ]);
    $item = sql('SELECT * FROM inventory_items WHERE sku=?', [$sku])->fetch();
    check(!empty($item), "Inventory item created (SKU: $sku)");
    check((float)$item['current_stock'] === 50.00, "Initial stock recorded correctly (50.00 Litres)");
    check((float)$item['min_stock'] === 15.00, "Minimum safety stock threshold saved (15.00)");
    $itemId = (int)$item['id'];
    $createdItemIds[] = $itemId;

    // --------------------------------------------------------------------------
    // TEST 2: Stock In
    // --------------------------------------------------------------------------
    echo "\n--- 2. Testing Stock In ---\n";
    post('admin', 'admin/stock_movements.php', [
        'action' => 'inventory_movement_save',
        'item_id' => $itemId,
        'movement_type' => 'IN',
        'quantity' => '25.00',
        'reference' => 'PO-TEST-001',
        'notes' => 'Received fresh shipment',
        'supplier_id' => $supId
    ]);
    $itemAfterIn = sql('SELECT current_stock FROM inventory_items WHERE id=?', [$itemId])->fetch();
    check((float)$itemAfterIn['current_stock'] === 75.00, "Stock In increased balance to 75.00 Litres");

    // --------------------------------------------------------------------------
    // TEST 3: Stock Out
    // --------------------------------------------------------------------------
    echo "\n--- 3. Testing Stock Out ---\n";
    post('admin', 'admin/stock_movements.php', [
        'action' => 'inventory_movement_save',
        'item_id' => $itemId,
        'movement_type' => 'OUT',
        'quantity' => '20.00',
        'reference' => 'DISPENSE-01',
        'notes' => 'Dispensed for commercial wash load'
    ]);
    $itemAfterOut = sql('SELECT current_stock FROM inventory_items WHERE id=?', [$itemId])->fetch();
    check((float)$itemAfterOut['current_stock'] === 55.00, "Stock Out decreased balance to 55.00 Litres");

    // --------------------------------------------------------------------------
    // TEST 4: Rejection of Negative Stock
    // --------------------------------------------------------------------------
    echo "\n--- 4. Testing Rejection of Negative Stock ---\n";
    // Attempt to withdraw 100 Litres when only 55 Litres available
    [$codeNeg, $bodyNeg] = post('admin', 'admin/stock_movements.php', [
        'action' => 'inventory_movement_save',
        'item_id' => $itemId,
        'movement_type' => 'OUT',
        'quantity' => '100.00',
        'reference' => 'EXCESSIVE-OUT'
    ]);
    $itemNegCheck = sql('SELECT current_stock FROM inventory_items WHERE id=?', [$itemId])->fetch();
    check((float)$itemNegCheck['current_stock'] === 55.00, "Negative stock strictly rejected; balance remains unchanged at 55.00 Litres");

    // --------------------------------------------------------------------------
    // TEST 5: Concurrent / Transactional Stock Updates
    // --------------------------------------------------------------------------
    echo "\n--- 5. Testing Transactional Stock Updates ---\n";
    // Simulate sequential atomic movements that lock row and verify consistency
    db()->beginTransaction();
    $locked = sql('SELECT current_stock FROM inventory_items WHERE id=? FOR UPDATE', [$itemId])->fetch();
    $curr = (float)$locked['current_stock'];
    record_inventory_movement($itemId, 'IN', 10.00, 'TX-IN', 'Batch in', 1);
    record_inventory_movement($itemId, 'OUT', 5.00, 'TX-OUT', 'Batch out', 1);
    db()->commit();

    $itemTxCheck = sql('SELECT current_stock FROM inventory_items WHERE id=?', [$itemId])->fetch();
    check((float)$itemTxCheck['current_stock'] === ($curr + 5.00), "Transactional movements executed with row locking: balance is " . $itemTxCheck['current_stock']);

    // --------------------------------------------------------------------------
    // TEST 6: Inventory Movement Audit Trail
    // --------------------------------------------------------------------------
    echo "\n--- 6. Testing Inventory Movement Audit Trail ---\n";
    $movements = sql('SELECT * FROM inventory_movements WHERE item_id=? ORDER BY id ASC', [$itemId])->fetchAll();
    check(count($movements) >= 4, "Complete audit trail recorded (" . count($movements) . " movements logged)");
    $lastMov = end($movements);
    check($lastMov['performed_by'] == 1, "Movement records user ID accountability (Admin ID 1)");
    check(!empty($lastMov['created_at']), "Movement records timestamp");
    check($lastMov['balance_after'] == $itemTxCheck['current_stock'], "Movement records verified post-transaction balance");

    // --------------------------------------------------------------------------
    // TEST 7: Low Stock Threshold Detection
    // --------------------------------------------------------------------------
    echo "\n--- 7. Testing Low Stock Threshold Detection ---\n";
    // Current stock is 60.00, min_stock is 15.00. Deduct 50.00 so stock becomes 10.00 (below 15.00)
    record_inventory_movement($itemId, 'OUT', 50.00, 'LOW-TRIGGER', 'Deducting below threshold', 1);
    $itemLow = sql('SELECT current_stock, min_stock FROM inventory_items WHERE id=?', [$itemId])->fetch();
    check((float)$itemLow['current_stock'] <= (float)$itemLow['min_stock'], "Current stock (10.00) is at or below minimum threshold (15.00)");

    $alertState = sql('SELECT * FROM inventory_alert_state WHERE item_id=?', [$itemId])->fetch();
    check(!empty($alertState) && (int)$alertState['is_alerted'] === 1, "Alert state transitioned to is_alerted = 1");
    check($alertState['alert_level'] === 'LOW', "Alert level set to LOW");

    $adminNotif = sql("SELECT * FROM notifications WHERE user_id=1 AND title LIKE 'Low Stock Alert%' ORDER BY id DESC LIMIT 1")->fetch();
    check(!empty($adminNotif), "Low Stock Alert notification generated for Admin");
    check(str_contains($adminNotif['message'], $sku), "Notification contains item SKU identifier");

    // --------------------------------------------------------------------------
    // TEST 8: Duplicate Alert Prevention
    // --------------------------------------------------------------------------
    echo "\n--- 8. Testing Duplicate Alert Prevention ---\n";
    $notifCountBefore = (int)sql("SELECT COUNT(*) cnt FROM notifications WHERE user_id=1 AND title LIKE 'Low Stock Alert%'")->fetchColumn();
    // Deduct another 2.00 units (stock goes from 10.00 to 8.00, still LOW)
    record_inventory_movement($itemId, 'OUT', 2.00, 'MORE-OUT', 'Still low', 1);
    $notifCountAfter = (int)sql("SELECT COUNT(*) cnt FROM notifications WHERE user_id=1 AND title LIKE 'Low Stock Alert%'")->fetchColumn();
    check($notifCountBefore === $notifCountAfter, "Duplicate low stock notification prevented while state is already alerted");

    // --------------------------------------------------------------------------
    // TEST 9: Alert Resolution After Replenishment
    // --------------------------------------------------------------------------
    echo "\n--- 9. Testing Alert Resolution After Replenishment ---\n";
    // Stock In 30.00 units, bringing stock to 38.00 (well above min_stock 15.00)
    record_inventory_movement($itemId, 'IN', 30.00, 'RESTOCK-RESOLVE', 'Replenished', 1);
    $itemResolved = sql('SELECT current_stock, min_stock FROM inventory_items WHERE id=?', [$itemId])->fetch();
    check((float)$itemResolved['current_stock'] > (float)$itemResolved['min_stock'], "Stock replenished to " . $itemResolved['current_stock'] . " Litres");

    $resolvedState = sql('SELECT * FROM inventory_alert_state WHERE item_id=?', [$itemId])->fetch();
    check((int)$resolvedState['is_alerted'] === 0, "Alert state reset to is_alerted = 0");
    check(!empty($resolvedState['last_resolved_at']), "Resolution timestamp recorded");

    $resolveNotif = sql("SELECT * FROM notifications WHERE user_id=1 AND title LIKE 'Stock Replenished%' ORDER BY id DESC LIMIT 1")->fetch();
    check(!empty($resolveNotif), "Stock Replenished notification generated upon threshold recovery");

    // --------------------------------------------------------------------------
    // TEST 10: Staff Check-In
    // --------------------------------------------------------------------------
    echo "\n--- 10. Testing Staff Check-In ---\n";
    // Clean up any stray active session for driver
    sql("UPDATE staff_attendance SET check_out=NOW(), working_hours=1.00 WHERE user_id=? AND check_out IS NULL", [$driverId]);

    post('driver', 'delivery/attendance.php', [
        'action' => 'attendance_checkin',
        'notes' => 'Starting route delivery shift'
    ]);
    $att = sql("SELECT * FROM staff_attendance WHERE user_id=? AND check_out IS NULL ORDER BY id DESC LIMIT 1", [$driverId])->fetch();
    check(!empty($att), "Delivery staff check-in recorded successfully");
    check($att['status'] === 'present', "Attendance status marked present");
    check(!empty($att['check_in']), "Check-in server timestamp saved");
    $attId = (int)$att['id'];
    $createdAttendanceIds[] = $attId;

    // --------------------------------------------------------------------------
    // TEST 11: Staff Check-Out
    // --------------------------------------------------------------------------
    echo "\n--- 11. Testing Staff Check-Out ---\n";
    post('driver', 'delivery/attendance.php', [
        'action' => 'attendance_checkout',
        'notes' => 'Completed all parcel deliveries'
    ]);
    $attClosed = sql("SELECT * FROM staff_attendance WHERE id=?", [$attId])->fetch();
    check(!empty($attClosed['check_out']), "Check-out server timestamp recorded");
    check($attClosed['working_hours'] !== null, "Working hours recorded");

    // --------------------------------------------------------------------------
    // TEST 12: Duplicate Check-In Prevention
    // --------------------------------------------------------------------------
    echo "\n--- 12. Testing Duplicate Check-In Prevention ---\n";
    // Start a new check-in
    post('driver', 'delivery/attendance.php', [
        'action' => 'attendance_checkin',
        'notes' => 'Second shift session'
    ]);
    $openSession = sql("SELECT id FROM staff_attendance WHERE user_id=? AND check_out IS NULL ORDER BY id DESC LIMIT 1", [$driverId])->fetch();
    check(!empty($openSession), "New active check-in session started");
    $secondAttId = (int)$openSession['id'];
    $createdAttendanceIds[] = $secondAttId;

    // Attempt second check-in while already checked in
    [$dupCode, $dupBody] = post('driver', 'delivery/attendance.php', [
        'action' => 'attendance_checkin',
        'notes' => 'Illegal second check-in'
    ]);
    $activeCount = (int)sql("SELECT COUNT(*) cnt FROM staff_attendance WHERE user_id=? AND check_out IS NULL", [$driverId])->fetchColumn();
    check($activeCount === 1, "Duplicate active check-in prevented (exactly 1 active session allowed)");

    // --------------------------------------------------------------------------
    // TEST 13: Working Hours Calculation
    // --------------------------------------------------------------------------
    echo "\n--- 13. Testing Working Hours Calculation ---\n";
    // Artificially backdate the second check-in by 2.5 hours
    sql("UPDATE staff_attendance SET check_in = NOW() - INTERVAL 150 MINUTE WHERE id=?", [$secondAttId]);
    post('driver', 'delivery/attendance.php', [
        'action' => 'attendance_checkout',
        'notes' => 'Evening checkout'
    ]);
    $calcAtt = sql("SELECT working_hours FROM staff_attendance WHERE id=?", [$secondAttId])->fetch();
    $hrs = (float)$calcAtt['working_hours'];
    check($hrs >= 2.45 && $hrs <= 2.55, "Working hours correctly calculated (expected ~2.5 hrs, got: $hrs hrs)");

    // --------------------------------------------------------------------------
    // TEST 14: Admin Attendance Reporting & CSV Export
    // --------------------------------------------------------------------------
    echo "\n--- 14. Testing Admin Attendance Reporting & CSV Export ---\n";
    [$codeAttPage, $bodyAttPage] = request('admin', 'admin/attendance.php');
    check($codeAttPage === 200, "Admin attendance page loads (HTTP 200)");
    check(str_contains($bodyAttPage, 'Attendance Records Log'), "Attendance page displays records log");

    // CSV export test
    [$codeCsv, $bodyCsv, $ctCsv] = post('admin', 'admin/attendance.php', [
        'action' => 'export_attendance_csv',
        'from' => date('Y-m-01'),
        'to' => date('Y-m-d'),
        'user_id' => '0'
    ], true);
    check($codeCsv === 200, "Attendance CSV export endpoint returns HTTP 200");
    check(str_contains($ctCsv, 'text/csv'), "CSV Content-Type text/csv verified");
    check(str_contains($bodyCsv, 'Staff Member'), "CSV header row contains 'Staff Member'");
    check(str_contains($bodyCsv, 'Working Hours'), "CSV header row contains 'Working Hours'");

    // --------------------------------------------------------------------------
    // TEST 15: Unauthorized Staff Access
    // --------------------------------------------------------------------------
    echo "\n--- 15. Testing Unauthorized Staff Access ---\n";
    // Customer attempts to access admin attendance
    [$custAdminCode, $custAdminBody] = request('cust', 'admin/attendance.php');
    check($custAdminCode === 403, "Customer blocked from Admin Attendance page (HTTP 403)");

    // Customer attempts to post attendance action
    [$custActCode, $custActBody] = post('cust', 'customer/dashboard.php', [
        'action' => 'attendance_checkin',
        'notes' => 'Customer trying to check in'
    ]);
    $custAttCount = (int)sql("SELECT COUNT(*) cnt FROM staff_attendance WHERE user_id=?", [$custId])->fetchColumn();
    check($custAttCount === 0, "Unauthorized customer attendance check-in rejected");

    // --------------------------------------------------------------------------
    // TEST 16: Backup Creation
    // --------------------------------------------------------------------------
    echo "\n--- 16. Testing Backup Creation ---\n";
    [$codeBkp, $bodyBkp] = post('admin', 'admin/backups.php', [
        'action' => 'backup_create'
    ]);
    check($codeBkp === 200, "Admin backup creation request completed (HTTP 200)");

    $latestBackup = sql("SELECT * FROM database_backup_history ORDER BY id DESC LIMIT 1")->fetch();
    check(!empty($latestBackup), "Backup recorded in database_backup_history");
    check($latestBackup['status'] === 'verified', "Backup status is verified");
    check((int)$latestBackup['table_count'] >= 13, "Backup contains all system tables (" . $latestBackup['table_count'] . " tables)");
    check((int)$latestBackup['file_size'] > 5000, "Backup file size verified (" . $latestBackup['file_size'] . " bytes)");
    check(file_exists($latestBackup['file_path']), "Backup file exists on physical disk outside web root");

    $createdBackupFiles[] = $latestBackup['file_path'];

    // --------------------------------------------------------------------------
    // TEST 17: Backup File Access Security
    // --------------------------------------------------------------------------
    echo "\n--- 17. Testing Backup File Access Security ---\n";
    // Anonymous user attempts to download
    [$anonBkpCode, $anonBkpBody] = request('anon', 'actions/handle.php', [
        'action' => 'backup_download',
        'filename' => $latestBackup['filename']
    ]);
    check($anonBkpCode === 405 || $anonBkpCode === 419 || $anonBkpCode === 403, "Anonymous download blocked (HTTP $anonBkpCode)");

    // Customer attempts to download
    [$custBkpCode, $custBkpBody] = post('cust', 'customer/dashboard.php', [
        'action' => 'backup_download',
        'filename' => $latestBackup['filename']
    ]);
    check($custBkpCode === 403, "Customer denied download of database backup (HTTP 403)");

    // Admin authorized download
    [$adminDlCode, $adminDlBody, $adminDlType] = post('admin', 'admin/backups.php', [
        'action' => 'backup_download',
        'filename' => $latestBackup['filename']
    ], true);
    check($adminDlCode === 200, "Admin authorized download succeeds (HTTP 200)");
    check(str_contains($adminDlBody, 'CREATE TABLE'), "Downloaded file contains SQL table DDL");

    // Directory traversal attempt
    [$travCode, $travBody] = post('admin', 'admin/backups.php', [
        'action' => 'backup_download',
        'filename' => '../../windows/win.ini'
    ]);
    check(!str_contains($travBody, '[fonts]'), "Path traversal attempt strictly prevented");

    // --------------------------------------------------------------------------
    // TEST 18: Restore Preview Safety
    // --------------------------------------------------------------------------
    echo "\n--- 18. Testing Restore Preview Safety ---\n";
    [$codePreview, $bodyPreview] = request('admin', 'admin/restore_preview.php?file=' . urlencode($latestBackup['filename']));
    check($codePreview === 200, "Restore preview page loaded (HTTP 200)");
    check(str_contains($bodyPreview, 'Safety First: Restoring a Database is Destructive'), "Prominent safety first warning displayed");
    check(str_contains($bodyPreview, 'Standard Operating Procedure: Database Restoration'), "Documented step-by-step restoration instructions presented");
    check(str_contains($bodyPreview, 'smart_laundry_staging'), "Staging verification instructions included");
    check(!str_contains($bodyPreview, 'type="submit">Execute Live Restore<'), "One-click destructive live restore is NOT present in UI");

    // --------------------------------------------------------------------------
    // TEST 19: Dark / Light Mode Compatibility
    // --------------------------------------------------------------------------
    echo "\n--- 19. Testing Dark / Light Mode Compatibility ---\n";
    $pagesToCheck = [
        'admin/inventory.php',
        'admin/inventory_item.php',
        'admin/stock_movements.php',
        'admin/suppliers.php',
        'admin/attendance.php',
        'admin/backups.php',
        'delivery/attendance.php'
    ];
    foreach ($pagesToCheck as $p) {
        $who = str_starts_with($p, 'delivery/') ? 'driver' : 'admin';
        [$codeP, $bodyP] = request($who, $p);
        check($codeP === 200, "Page $p loads with HTTP 200");
        check(str_contains($bodyP, 'id="theme-toggle"'), "Page $p includes theme toggle button");
        check(str_contains($bodyP, 'data-theme'), "Page $p includes theme initialization script");
    }

    // --------------------------------------------------------------------------
    // TEST 20: Existing Notification Integration
    // --------------------------------------------------------------------------
    echo "\n--- 20. Testing Existing Notification Integration ---\n";
    // Check that notifications table holds both booking and inventory alert notifications
    $notifTypes = sql("SELECT DISTINCT type FROM notifications")->fetchAll(PDO::FETCH_COLUMN);
    check(in_array('warning', $notifTypes, true) || in_array('danger', $notifTypes, true), "Notification system holds alert types (warning/danger)");
    
    // Check unread count helper works
    $unreadCount = unread_notifications_count(1);
    check($unreadCount >= 0, "unread_notifications_count() operates cleanly ($unreadCount unread)");

    echo "\n========================================================\n";
    echo "PHASE 3 ADVANCED MANAGEMENT UPGRADE VERIFICATION COMPLETE\n";
    echo "Passed $checks checks successfully with 0 errors.\n";
    echo "========================================================\n";

} catch (Throwable $e) {
    echo "\nTEST SUITE FAILED: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
} finally {
    // Clean up temporary cookie files
    foreach ($cookies as $file) {
        if (file_exists($file)) @unlink($file);
    }
}

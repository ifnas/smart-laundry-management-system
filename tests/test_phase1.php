<?php
/**
 * Automated Test Suite for Phase 1 Professional Upgrade
 * Tests PDF Invoice, QR Tracking, Notification Center, and Analytics Dashboard.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

date_default_timezone_set('Asia/Colombo');
require __DIR__ . '/../config/database.php';

$base = rtrim($argv[1] ?? 'http://localhost/smart-laundry', '/');
if (!in_array(parse_url($base, PHP_URL_HOST), ['localhost', '127.0.0.1'], true)) {
    exit("Local URLs only.\n");
}

$tag = 'p1test' . bin2hex(random_bytes(4));
$cookies = [];
$checks = 0;
$userIds = [];
$bookingIds = [];

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

function request(string $who, string $path, ?array $data = null): array {
    global $base, $cookies;
    if (!isset($cookies[$who])) {
        $cookies[$who] = tempnam(sys_get_temp_dir(), 'laundry-p1-');
    }
    $url = $base . '/' . $path;
    $c = curl_init($url);
    curl_setopt_array($c, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_COOKIEFILE => $cookies[$who],
        CURLOPT_COOKIEJAR => $cookies[$who],
        CURLOPT_TIMEOUT => 20
    ]);
    if ($data !== null) {
        curl_setopt($c, CURLOPT_POST, true);
        curl_setopt($c, CURLOPT_POSTFIELDS, http_build_query($data));
    }
    $body = curl_exec($c);
    if ($body === false) throw new RuntimeException(curl_error($c));
    $code = curl_getinfo($c, CURLINFO_HTTP_CODE);
    curl_close($c);
    return [$code, $body];
}

function token(string $who, string $path): string {
    [$code, $body] = request($who, $path);
    if (!preg_match('/name="csrf" value="([a-f0-9]+)"/', $body, $m)) {
        throw new RuntimeException("No CSRF token on $path (HTTP $code)");
    }
    return $m[1];
}

function post(string $who, string $page, array $data): array {
    $data['csrf'] = token($who, $page);
    return request($who, 'actions/handle.php', $data);
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
    echo "Starting Phase 1 Upgrade Verification Suite\n";
    echo "Target: $base\n";
    echo "========================================================\n\n";

    // 1. Setup test accounts
    $custEmail = $tag . '_cust@example.test';
    $custPass = 'TestPass@123';
    post('anon', 'register.php', [
        'action' => 'register',
        'name' => 'Phase1 Customer',
        'email' => $custEmail,
        'phone' => '0771122334',
        'address' => '123 Test Avenue, Colombo 03',
        'password' => $custPass,
        'password_confirmation' => $custPass
    ]);
    $custId = (int)sql('SELECT id FROM users WHERE email = ?', [$custEmail])->fetchColumn();
    $userIds[] = $custId;
    check($custId > 0, "Test customer registered ($custEmail)");

    // Customer 2 for unauthorized checks
    $cust2Email = $tag . '_cust2@example.test';
    post('anon2', 'register.php', [
        'action' => 'register',
        'name' => 'Other Customer',
        'email' => $cust2Email,
        'phone' => '0779988776',
        'address' => '456 Other Lane, Kandy',
        'password' => $custPass,
        'password_confirmation' => $custPass
    ]);
    $cust2Id = (int)sql('SELECT id FROM users WHERE email = ?', [$cust2Email])->fetchColumn();
    $userIds[] = $cust2Id;
    check($cust2Id > 0, "Second test customer registered ($cust2Email)");

    // Logins
    login('customer1', $custEmail, $custPass);
    login('customer2', $cust2Email, $custPass);
    login('admin', 'admin@laundry.com', 'Admin@123');
    login('driver', 'delivery@laundry.com', 'Delivery@123');
    $driverId = (int)sql("SELECT id FROM users WHERE email = 'delivery@laundry.com'")->fetchColumn();

    // 2. Booking creation & verification of invoice_number & tracking_token
    $service = sql('SELECT id, price FROM services WHERE is_active = 1 LIMIT 1')->fetch();
    $serviceId = (int)$service['id'];
    $bookingData = [
        'action' => 'create_booking',
        'pickup_address' => '123 Test Avenue, Colombo 03',
        'pickup_phone' => '0771122334',
        'pickup_date' => date('Y-m-d', strtotime('+1 day')),
        'special_instructions' => 'Handle with care - Phase 1 test',
        'quantity' => [$serviceId => 2]
    ];
    post('customer1', 'customer/new_booking.php', $bookingData);
    $bRow = sql('SELECT * FROM bookings WHERE customer_id = ? ORDER BY id DESC LIMIT 1', [$custId])->fetch();
    $bId = (int)$bRow['id'];
    $bookingIds[] = $bId;
    check($bId > 0, "Booking created successfully (ID: $bId, No: {$bRow['booking_number']})");
    check(!empty($bRow['invoice_number']), "Booking has stable invoice number ({$bRow['invoice_number']})");
    check(!empty($bRow['tracking_token']) && strlen($bRow['tracking_token']) >= 32, "Booking has secure tracking token");

    // 3. Feature One: PDF Invoice Tests
    echo "\n--- Feature One: PDF Invoices ---\n";
    [$codeHtml, $bodyHtml] = request('customer1', "invoice.php?id=$bId");
    check($codeHtml === 200, "Customer can view HTML invoice (HTTP 200)");
    check(str_contains($bodyHtml, $bRow['invoice_number']), "HTML invoice contains correct invoice number");
    check(str_contains($bodyHtml, $bRow['booking_number']), "HTML invoice contains correct booking reference");
    check(str_contains($bodyHtml, 'LKR'), "HTML invoice displays currency in LKR");

    [$codePdf, $bodyPdf] = request('customer1', "invoice.php?id=$bId&download=pdf");
    check($codePdf === 200, "Customer can download PDF invoice (HTTP 200)");
    check(str_starts_with($bodyPdf, '%PDF-1.4'), "Downloaded PDF has valid %PDF-1.4 header");
    check(strlen($bodyPdf) > 500, "PDF document body is not empty (" . strlen($bodyPdf) . " bytes)");

    // Admin can view/download
    [$codeAdminInv, $bodyAdminInv] = request('admin', "invoice.php?id=$bId");
    check($codeAdminInv === 200, "Admin can view customer invoice (HTTP 200)");
    [$codeAdminPdf, $bodyAdminPdf] = request('admin', "invoice.php?id=$bId&download=pdf");
    check($codeAdminPdf === 200 && str_starts_with($bodyAdminPdf, '%PDF-1.4'), "Admin can download customer PDF invoice");

    // Other customer cannot view
    [$codeOther, $bodyOther] = request('customer2', "invoice.php?id=$bId");
    check($codeOther === 403 || $codeOther === 404, "Other customer denied access to invoice (HTTP $codeOther)");

    // Driver cannot view invoice
    [$codeDriverInv, $bodyDriverInv] = request('driver', "invoice.php?id=$bId");
    check($codeDriverInv === 403 || $codeDriverInv === 404, "Driver denied general invoice access (HTTP $codeDriverInv)");

    // Ensure payment status remains Unpaid after PDF download
    $postDownloadStatus = sql('SELECT payment_status FROM bookings WHERE id = ?', [$bId])->fetchColumn();
    check($postDownloadStatus === 'Unpaid', "Downloading invoice does NOT change payment status (still Unpaid)");

    // 4. Feature Two: QR Code Order Tracking Tests
    echo "\n--- Feature Two: QR Code Order Tracking ---\n";
    $token = $bRow['tracking_token'];
    [$codeTrack, $bodyTrack] = request('anon', "track.php?token=$token");
    check($codeTrack === 200, "Public order tracking accessible by token without authentication (HTTP 200)");
    check(str_contains($bodyTrack, $bRow['booking_number']), "Tracking page displays booking reference");
    check(str_contains($bodyTrack, 'Pending'), "Tracking page shows current status");
    // Ensure privacy - NO PII
    check(!str_contains($bodyTrack, 'Phase1 Customer'), "Tracking page does NOT leak customer name");
    check(!str_contains($bodyTrack, '0771122334'), "Tracking page does NOT leak customer phone");
    check(!str_contains($bodyTrack, '123 Test Avenue'), "Tracking page does NOT leak customer address");
    check(!str_contains($bodyTrack, 'LKR'), "Tracking page does NOT leak invoice / payment amount");

    // Invalid token returns not found cleanly
    [$codeInvalidTrack, $bodyInvalidTrack] = request('anon', "track.php?token=invalid_token_99999");
    check($codeInvalidTrack === 200 && (str_contains($bodyInvalidTrack, 'not found') || str_contains($bodyInvalidTrack, 'Tracking reference not found')), "Invalid tracking token handled safely with friendly notice");

    // Print-friendly QR label
    [$codeLabel, $bodyLabel] = request('customer1', "qr_label.php?id=$bId");
    check($codeLabel === 200, "Authorized customer can access print-friendly QR label (HTTP 200)");
    check(str_contains($bodyLabel, '<svg'), "QR label contains valid vector SVG QR code");

    [$codeOtherLabel, $bodyOtherLabel] = request('customer2', "qr_label.php?id=$bId");
    check($codeOtherLabel === 403 || $codeOtherLabel === 404, "Unauthorized customer blocked from QR label (HTTP $codeOtherLabel)");

    // 5. Feature Three: In-App Notification Center Tests
    echo "\n--- Feature Three: In-App Notifications ---\n";
    // Check admin received notification for the new booking
    $adminNotif = sql("SELECT * FROM notifications WHERE user_id = 1 AND booking_id = ? ORDER BY id DESC LIMIT 1", [$bId])->fetch();
    check(!empty($adminNotif), "Admin received notification on new booking creation");
    check(str_contains($adminNotif['title'], 'New Booking'), "Admin notification has correct title");

    // Advance booking: Admin accepts
    post('admin', "admin/booking_details.php?id=$bId", [
        'action' => 'status',
        'booking_id' => $bId,
        'status' => 'Accepted'
    ]);
    $custNotif = sql("SELECT * FROM notifications WHERE user_id = ? AND booking_id = ? ORDER BY id DESC LIMIT 1", [$custId, $bId])->fetch();
    check(!empty($custNotif), "Customer received notification when booking was Accepted");
    check(str_contains($custNotif['message'], 'accepted'), "Customer notification message is accurate");

    // Assign driver -> driver receives notification
    post('admin', "admin/booking_details.php?id=$bId", [
        'action' => 'assign',
        'booking_id' => $bId,
        'delivery_person_id' => $driverId
    ]);
    $driverNotif = sql("SELECT * FROM notifications WHERE user_id = ? AND booking_id = ? ORDER BY id DESC LIMIT 1", [$driverId, $bId])->fetch();
    check(!empty($driverNotif), "Driver received notification on new delivery assignment");

    // Notification UI and Mark as read
    [$codeNotifPage, $bodyNotifPage] = request('customer1', 'notifications.php');
    check($codeNotifPage === 200, "Notification history page accessible (HTTP 200)");
    check(str_contains($bodyNotifPage, 'Notification Center'), "Notification Center rendered properly");

    $notifId = (int)$custNotif['id'];
    post('customer1', 'notifications.php', [
        'action' => 'mark_notification_read',
        'notification_id' => $notifId
    ]);
    $isRead = (int)sql('SELECT is_read FROM notifications WHERE id = ?', [$notifId])->fetchColumn();
    check($isRead === 1, "Customer can mark single notification as read");

    // Test mark all as read
    post('customer1', 'notifications.php', [
        'action' => 'mark_all_notifications_read'
    ]);
    $unreadRemaining = (int)sql('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0', [$custId])->fetchColumn();
    check($unreadRemaining === 0, "Mark all notifications read clears unread count for user");

    // 6. Feature Four: Advanced Analytics Dashboard Tests
    echo "\n--- Feature Four: Advanced Analytics Dashboard ---\n";
    [$codeAdminDash, $bodyAdminDash] = request('admin', 'admin/dashboard.php');
    check($codeAdminDash === 200, "Admin dashboard loads successfully (HTTP 200)");
    check(str_contains($bodyAdminDash, 'Total bookings'), "Admin dashboard displays 'Total bookings' KPI");
    check(str_contains($bodyAdminDash, 'Pending bookings'), "Admin dashboard displays 'Pending bookings' KPI");
    check(str_contains($bodyAdminDash, 'Active orders'), "Admin dashboard displays 'Active orders' KPI");
    check(str_contains($bodyAdminDash, 'Completed orders'), "Admin dashboard displays 'Completed orders' KPI");
    check(str_contains($bodyAdminDash, 'Total COD revenue'), "Admin dashboard displays 'Total COD revenue' KPI");
    check(str_contains($bodyAdminDash, 'Collected today'), "Admin dashboard displays 'Collected today' KPI");
    check(str_contains($bodyAdminDash, 'Collected this month'), "Admin dashboard displays 'Collected this month' KPI");
    check(str_contains($bodyAdminDash, 'Registered customers'), "Admin dashboard displays 'Registered customers' KPI");

    // Check all 4 charts exist
    check(str_contains($bodyAdminDash, 'revenue-chart'), "Revenue trend chart canvas rendered");
    check(str_contains($bodyAdminDash, 'chart-status-dist'), "Status distribution chart canvas rendered");
    check(str_contains($bodyAdminDash, 'chart-top-services'), "Top services chart canvas rendered");
    check(str_contains($bodyAdminDash, 'chart-delivery-perf'), "Delivery performance chart canvas rendered");

    // Test date filters on Sales reports
    [$codeRep7d, $bodyRep7d] = request('admin', 'admin/reports.php?range=7d');
    check($codeRep7d === 200, "Sales report with 7d preset filter returns HTTP 200");

    [$codeRep30d, $bodyRep30d] = request('admin', 'admin/reports.php?range=30d');
    check($codeRep30d === 200, "Sales report with 30d preset filter returns HTTP 200");

    [$codeRepToday, $bodyRepToday] = request('admin', 'admin/reports.php?range=today');
    check($codeRepToday === 200, "Sales report with today filter returns HTTP 200");

    [$codeRepCustom, $bodyRepCustom] = request('admin', 'admin/reports.php?range=custom&from=' . date('Y-m-01') . '&to=' . date('Y-m-d'));
    check($codeRepCustom === 200, "Sales report with valid custom date range returns HTTP 200");

    [$codeRepInvalid, $bodyRepInvalid] = request('admin', 'admin/reports.php?from=invalid-date&to=bad');
    check($codeRepInvalid === 200, "Sales report with invalid date falls back gracefully without 500 error");

    // 7. Dark / Light Mode Verification
    echo "\n--- Dark / Light Mode System Verification ---\n";
    [$codeIndex, $bodyIndex] = request('anon', 'index.php');
    check($codeIndex === 200 && str_contains($bodyIndex, 'id="theme-toggle"'), "Public page renders theme toggle button");
    check(str_contains($bodyIndex, "localStorage.getItem('smart_laundry_theme')"), "Public page contains head script preventing theme flicker");

    [$codeLogin, $bodyLogin] = request('anon', 'login.php');
    check($codeLogin === 200 && str_contains($bodyLogin, 'id="theme-toggle"'), "Login page renders theme toggle button");

    [$codeCustDash, $bodyCustDash] = request('customer1', 'customer/dashboard.php');
    check($codeCustDash === 200 && str_contains($bodyCustDash, 'id="theme-toggle"'), "Customer workspace renders theme toggle button");

    [$codeDriverDash, $bodyDriverDash] = request('driver', 'delivery/dashboard.php');
    check($codeDriverDash === 200 && str_contains($bodyDriverDash, 'id="theme-toggle"'), "Delivery workspace renders theme toggle button");

    check(str_contains($bodyAdminDash, 'id="theme-toggle"'), "Admin workspace renders theme toggle button");
    check(str_contains($bodyHtml, 'id="theme-toggle"'), "Invoice HTML view renders theme toggle button");
    check(str_contains($bodyTrack, 'id="theme-toggle"'), "QR tracking page renders theme toggle button");

    // Verify app.js de-duplication
    $appJsContent = file_get_contents(__DIR__ . '/../assets/js/app.js');
    $themeListeners = substr_count($appJsContent, "applyTheme(");
    check($themeListeners <= 3, "app.js does not contain duplicate theme toggle blocks");
    check(str_contains($appJsContent, "e.target.closest('#theme-toggle, .btn-theme-toggle')"), "app.js implements single robust delegated click listener");
    check(str_contains($appJsContent, "new CustomEvent('themeChanged'"), "app.js dispatches themeChanged for Chart.js real-time adaptation");

    echo "\n========================================================\n";
    echo "All $checks Phase 1 upgrade checks PASSED successfully!\n";
    echo "========================================================\n";

} catch (Throwable $e) {
    echo "\nTEST SUITE FAILED: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    $failed = true;
} finally {
    // Clean up test data safely
    if (!empty($bookingIds)) {
        $inB = implode(',', array_map('intval', $bookingIds));
        sql("DELETE FROM notifications WHERE booking_id IN ($inB)");
        sql("DELETE FROM booking_items WHERE booking_id IN ($inB)");
        sql("DELETE FROM booking_status_history WHERE booking_id IN ($inB)");
        sql("DELETE FROM delivery_assignments WHERE booking_id IN ($inB)");
        sql("DELETE FROM bookings WHERE id IN ($inB)");
    }
    if (!empty($userIds)) {
        $inU = implode(',', array_map('intval', $userIds));
        sql("DELETE FROM notifications WHERE user_id IN ($inU)");
        sql("DELETE FROM users WHERE id IN ($inU)");
    }
    foreach ($cookies as $file) {
        if (is_file($file)) @unlink($file);
    }
}

exit(isset($failed) ? 1 : 0);


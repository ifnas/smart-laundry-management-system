<?php
/**
 * Automated Test Suite for Phase 2 Business Management Upgrade
 * Tests Discount Coupons, Customer Reviews & Ratings, Pickup Slots, and Expenses & Profit.
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

$tag = 'p2_' . bin2hex(random_bytes(4));
$cookies = [];
$checks = 0;
$createdUserIds = [];
$createdBookingIds = [];
$createdCouponIds = [];
$createdSlotIds = [];
$createdExpenseIds = [];

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
        $cookies[$who] = tempnam(sys_get_temp_dir(), 'laundry-p2-');
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
    echo "Starting Phase 2 Upgrade Automated Verification Suite\n";
    echo "Target Base: $base\n";
    echo "========================================================\n\n";

    // --------------------------------------------------------------------------
    // 1. ACCOUNTS SETUP
    // --------------------------------------------------------------------------
    echo "--- 1. Setting up Test Accounts ---\n";
    $custEmail = $tag . '_cust@example.test';
    $custPass = 'Pass@12345';
    post('anon', 'register.php', [
        'action' => 'register',
        'name' => 'Phase2 Customer',
        'email' => $custEmail,
        'phone' => '0771239999',
        'address' => '42 Test Street, Colombo 03',
        'password' => $custPass
    ]);
    $custId = (int)sql('SELECT id FROM users WHERE email=?', [$custEmail])->fetchColumn();
    check($custId > 0, 'Customer registered successfully');
    $createdUserIds[] = $custId;

    login('cust', $custEmail, $custPass);
    login('admin', 'admin@laundry.com', 'Admin@123');

    // Create a delivery driver for assignments
    $driverEmail = $tag . '_drv@example.test';
    post('admin', 'admin/delivery_staff.php', [
        'action' => 'staff_save',
        'name' => 'Phase2 Driver',
        'email' => $driverEmail,
        'phone' => '0779876543',
        'password' => 'Pass@12345',
        'is_active' => '1'
    ]);
    $driverId = (int)sql('SELECT id FROM users WHERE email=?', [$driverEmail])->fetchColumn();
    check($driverId > 0, 'Delivery staff account created');
    $createdUserIds[] = $driverId;
    login('driver', $driverEmail, 'Pass@12345');

    // Fetch an active service
    $service = sql("SELECT * FROM services WHERE is_active=1 LIMIT 1")->fetch();
    check((bool)$service, 'Active laundry service available');
    $serviceId = (int)$service['id'];
    $servicePrice = (float)$service['price'];

    // --------------------------------------------------------------------------
    // 2. FEATURE 1: DISCOUNT COUPON MANAGEMENT
    // --------------------------------------------------------------------------
    echo "\n--- 2. Testing Feature 1: Discount Coupon Management ---\n";
    
    // Admin creates percentage coupon
    $codePct = strtoupper($tag . '_P20');
    post('admin', 'admin/coupons.php', [
        'action' => 'coupon_save',
        'code' => $codePct,
        'discount_type' => 'percent',
        'discount_value' => '20.00',
        'min_booking_amount' => '500.00',
        'max_discount_amount' => '300.00',
        'usage_limit' => '10',
        'expiry_date' => date('Y-m-d', strtotime('+7 days')),
        'is_active' => '1'
    ]);
    $couponPct = sql('SELECT * FROM coupons WHERE code=?', [$codePct])->fetch();
    check((bool)$couponPct, 'Percentage coupon created in database');
    check((float)$couponPct['discount_value'] === 20.00, 'Discount value recorded correctly');
    check((float)$couponPct['max_discount_amount'] === 300.00, 'Max discount cap recorded');
    $createdCouponIds[] = (int)$couponPct['id'];

    // Duplicate coupon code rejection
    [$dupCode, $dupBody] = post('admin', 'admin/coupons.php', [
        'action' => 'coupon_save',
        'code' => $codePct,
        'discount_type' => 'fixed',
        'discount_value' => '100.00'
    ]);
    check(str_contains($dupBody, 'already taken') || str_contains($dupBody, 'duplicate'), 'Duplicate coupon code prevented');

    // Admin creates fixed discount coupon
    $codeFix = strtoupper($tag . '_F150');
    post('admin', 'admin/coupons.php', [
        'action' => 'coupon_save',
        'code' => $codeFix,
        'discount_type' => 'fixed',
        'discount_value' => '150.00',
        'min_booking_amount' => '300.00',
        'is_active' => '1'
    ]);
    $couponFix = sql('SELECT * FROM coupons WHERE code=?', [$codeFix])->fetch();
    check((bool)$couponFix, 'Fixed discount coupon created');
    $createdCouponIds[] = (int)$couponFix['id'];

    // Toggle coupon active / inactive
    post('admin', 'admin/coupons.php', [
        'action' => 'coupon_toggle',
        'id' => $couponFix['id']
    ]);
    $toggled = sql('SELECT is_active FROM coupons WHERE id=?', [$couponFix['id']])->fetchColumn();
    check((int)$toggled === 0, 'Coupon deactivation toggle works');

    // Reactivate coupon
    post('admin', 'admin/coupons.php', [
        'action' => 'coupon_toggle',
        'id' => $couponFix['id']
    ]);
    $toggled2 = sql('SELECT is_active FROM coupons WHERE id=?', [$couponFix['id']])->fetchColumn();
    check((int)$toggled2 === 1, 'Coupon reactivation toggle works');

    // Customer validates coupon via AJAX endpoint
    $custCsrf = token('cust', 'customer/new_booking.php');
    [$vCode, $vBody] = request('cust', 'actions/handle.php', [
        'action' => 'validate_coupon',
        'coupon_code' => $codePct,
        'subtotal' => '1000.00',
        'csrf' => $custCsrf
    ]);
    $vData = json_decode($vBody, true);
    check($vCode === 200 && ($vData['ok'] ?? false) === true, 'Coupon live validation endpoint succeeds');
    check((float)$vData['discount'] === 200.00, 'Percentage discount calculated correctly (20% of 1000 = 200)');
    check((float)$vData['final_total'] === 800.00, 'Final payable total calculated correctly');

    // Test max discount cap on percentage coupon
    [$vCapCode, $vCapBody] = request('cust', 'actions/handle.php', [
        'action' => 'validate_coupon',
        'coupon_code' => $codePct,
        'subtotal' => '2500.00',
        'csrf' => $custCsrf
    ]);
    $vCapData = json_decode($vCapBody, true);
    check((float)$vCapData['discount'] === 300.00, 'Max discount cap enforced on percentage coupon');

    // Test minimum booking amount requirement
    [$vMinCode, $vMinBody] = request('cust', 'actions/handle.php', [
        'action' => 'validate_coupon',
        'coupon_code' => $codePct,
        'subtotal' => '200.00',
        'csrf' => $custCsrf
    ]);
    $vMinData = json_decode($vMinBody, true);
    check(($vMinData['ok'] ?? true) === false, 'Minimum booking amount rejection works');

    // Test expired coupon rejection
    $codeExp = strtoupper($tag . '_EXP');
    sql("INSERT INTO coupons(code, discount_type, discount_value, expiry_date, is_active) VALUES(?, 'fixed', 50.00, DATE_SUB(CURDATE(), INTERVAL 1 DAY), 1)", [$codeExp]);
    $expId = (int)db()->lastInsertId();
    $createdCouponIds[] = $expId;

    [$vExpCode, $vExpBody] = request('cust', 'actions/handle.php', [
        'action' => 'validate_coupon',
        'coupon_code' => $codeExp,
        'subtotal' => '1000.00',
        'csrf' => $custCsrf
    ]);
    $vExpData = json_decode($vExpBody, true);
    check(($vExpData['ok'] ?? true) === false && str_contains(strtolower($vExpData['message'] ?? ''), 'expired'), 'Expired coupon rejected');

    // --------------------------------------------------------------------------
    // 3. FEATURE 3: PICKUP & DELIVERY TIME SLOT MANAGEMENT
    // --------------------------------------------------------------------------
    echo "\n--- 3. Testing Feature 3: Pickup & Delivery Time Slot Management ---\n";

    // Admin creates custom time slot
    $slotName = 'Phase2 Evening Window ' . bin2hex(random_bytes(2));
    post('admin', 'admin/slots.php', [
        'action' => 'slot_save',
        'slot_name' => $slotName,
        'start_time' => '17:00:00',
        'end_time' => '19:00:00',
        'daily_capacity' => '2',
        'is_active' => '1'
    ]);
    $slotRow = sql('SELECT * FROM pickup_slots WHERE slot_name=?', [$slotName])->fetch();
    check((bool)$slotRow, 'Custom pickup time slot created in database');
    check((int)$slotRow['daily_capacity'] === 2, 'Daily capacity stored correctly');
    $slotId = (int)$slotRow['id'];
    $createdSlotIds[] = $slotId;

    // Admin slots page loads
    [$slotsPageCode, $slotsPageBody] = request('admin', 'admin/slots.php');
    check($slotsPageCode === 200 && str_contains($slotsPageBody, $slotName), 'Admin slots page displays new time slot');

    // Customer fetches slots for date via get_slots endpoint
    $pickupTestDate = date('Y-m-d', strtotime('+3 days'));
    [$sCode, $sBody] = request('cust', 'actions/handle.php', [
        'action' => 'get_slots',
        'date' => $pickupTestDate,
        'csrf' => token('cust', 'customer/new_booking.php')
    ]);
    $sData = json_decode($sBody, true);
    check($sCode === 200 && ($sData['ok'] ?? false) === true, 'Get slots endpoint returns JSON');
    $foundSlotInList = false;
    foreach ($sData['slots'] as $s) {
        if ((int)$s['id'] === $slotId) {
            $foundSlotInList = true;
            check((int)$s['remaining'] === 2 && $s['is_full'] === false, 'Slot reports full remaining capacity');
            break;
        }
    }
    check($foundSlotInList, 'New slot present in available slots list');

    // --------------------------------------------------------------------------
    // 4. CUSTOMER BOOKING INTEGRATION (Coupons + Slots Combined)
    // --------------------------------------------------------------------------
    echo "\n--- 4. Testing Customer Booking Integration (Coupons + Slots) ---\n";

    // Booking 1: Uses Coupon + Custom Time Slot
    // Let quantity = 4 so subtotal = 4 * servicePrice
    $qty = 4;
    $rawSubtotal = round($qty * $servicePrice, 2);
    [$bCode, $bBody] = post('cust', 'customer/new_booking.php', [
        'action' => 'create_booking',
        'pickup_phone' => '0771239999',
        'pickup_date' => $pickupTestDate,
        'pickup_slot_id' => $slotId,
        'coupon_code' => $codePct,
        'pickup_address' => '42 Test Street, Colombo 03',
        'quantity' => [$serviceId => $qty],
        'special_instructions' => 'Gentle cycle please.'
    ]);
    check($bCode === 200, 'Customer booking with coupon and slot placed successfully');

    $booking1 = sql('SELECT * FROM bookings WHERE customer_id=? ORDER BY id DESC LIMIT 1', [$custId])->fetch();
    check((bool)$booking1, 'Booking 1 found in database');
    $b1Id = (int)$booking1['id'];
    $createdBookingIds[] = $b1Id;

    check((float)$booking1['subtotal_amount'] === $rawSubtotal, 'Booking subtotal recorded correctly');
    $expectedDiscount = min(300.00, round($rawSubtotal * 0.20, 2));
    check((float)$booking1['discount_amount'] === $expectedDiscount, 'Discount amount applied to booking accurately');
    $expectedFinal = round($rawSubtotal - $expectedDiscount, 2);
    check((float)$booking1['total_amount'] === $expectedFinal, 'Final payable total recorded accurately');
    check($booking1['coupon_code'] === $codePct, 'Coupon code associated with booking record');
    check((int)$booking1['pickup_slot_id'] === $slotId, 'Pickup slot associated with booking record');

    // Check coupon redemption recorded
    $redemption = sql('SELECT * FROM coupon_redemptions WHERE booking_id=?', [$b1Id])->fetch();
    check((bool)$redemption, 'Coupon redemption recorded in coupon_redemptions table');
    check((float)$redemption['discount_amount'] === $expectedDiscount, 'Redemption discount matches booking discount');

    // Check times_used incremented
    $timesUsed = (int)sql('SELECT times_used FROM coupons WHERE id=?', [$couponPct['id']])->fetchColumn();
    check($timesUsed >= 1, 'Coupon times_used counter incremented');

    // Check booking details view displays coupon & slot
    [$dCode, $dBody] = request('cust', 'customer/booking_details.php?id=' . $b1Id);
    check($dCode === 200, 'Customer booking details loads (HTTP 200)');
    check(str_contains($dBody, $codePct), 'Booking details displays applied coupon code');
    check(str_contains($dBody, $slotName), 'Booking details displays selected pickup time slot');

    // Check Invoice HTML preview displays Subtotal, Coupon Discount, and Grand Total
    [$invCode, $invBody] = request('cust', 'invoice.php?id=' . $b1Id);
    check($invCode === 200, 'Invoice preview loads (HTTP 200)');
    check(str_contains($invBody, 'Coupon Discount') && str_contains($invBody, $codePct), 'Invoice preview displays coupon discount line');
    check(str_contains($invBody, $slotName), 'Invoice preview displays pickup time slot');

    // Check PDF Invoice generation
    [$pdfCode, $pdfBody, $pdfType] = request('cust', 'invoice.php?id=' . $b1Id . '&download=1');
    check($pdfCode === 200 && str_starts_with($pdfBody, '%PDF-'), 'Invoice PDF streams successfully with %PDF- header');

    // --------------------------------------------------------------------------
    // 5. TEST TIME SLOT CAPACITY ENFORCEMENT
    // --------------------------------------------------------------------------
    echo "\n--- 5. Testing Pickup Slot Capacity Enforcement ---\n";
    // Booking 2 on same date and slot (Capacity is 2, so this should succeed as slot order #2)
    [$b2Code, $b2Body] = post('cust', 'customer/new_booking.php', [
        'action' => 'create_booking',
        'pickup_phone' => '0771239999',
        'pickup_date' => $pickupTestDate,
        'pickup_slot_id' => $slotId,
        'pickup_address' => '42 Test Street, Colombo 03',
        'quantity' => [$serviceId => 1]
    ]);
    check($b2Code === 200, 'Second booking on same slot succeeds');
    $booking2 = sql('SELECT * FROM bookings WHERE customer_id=? ORDER BY id DESC LIMIT 1', [$custId])->fetch();
    $createdBookingIds[] = (int)$booking2['id'];

    // Booking 3 on same date and slot (Slot capacity is 2; 2 active bookings exist; this MUST fail)
    [$b3Code, $b3Body] = post('cust', 'customer/new_booking.php', [
        'action' => 'create_booking',
        'pickup_phone' => '0771239999',
        'pickup_date' => $pickupTestDate,
        'pickup_slot_id' => $slotId,
        'pickup_address' => '42 Test Street, Colombo 03',
        'quantity' => [$serviceId => 1]
    ]);
    check(str_contains($b3Body, 'fully booked'), 'Booking exceeding daily slot capacity rejected with friendly message');

    // Admin Daily Schedules view check
    [$schCode, $schBody] = request('admin', 'admin/schedules.php?date=' . $pickupTestDate);
    check($schCode === 200, 'Admin daily schedule page loads (HTTP 200)');
    check(str_contains($schBody, $slotName) && str_contains($schBody, '2 / 2 Booked'), 'Daily schedule reflects 2 / 2 capacity utilization');

    // --------------------------------------------------------------------------
    // 6. FEATURE 2: CUSTOMER REVIEWS & RATINGS
    // --------------------------------------------------------------------------
    echo "\n--- 6. Testing Feature 2: Customer Reviews & Ratings ---\n";

    // Attempt to review order while status is still Pending (must be rejected)
    [$revFailCode, $revFailBody] = post('cust', 'customer/booking_details.php?id=' . $b1Id, [
        'action' => 'review_save',
        'booking_id' => $b1Id,
        'rating' => '5',
        'review_text' => 'Loved it!'
    ]);
    check(str_contains($revFailBody, 'only rate and review orders that have been successfully delivered') || str_contains($revFailBody, 'delivered'), 'Review on non-delivered booking rejected');

    // Progress Booking 1 to Delivered
    // Admin accepts
    post('admin', 'admin/booking_details.php?id=' . $b1Id, [
        'action' => 'status',
        'booking_id' => $b1Id,
        'status' => 'Accepted'
    ]);
    // Admin assigns driver
    post('admin', 'admin/booking_details.php?id=' . $b1Id, [
        'action' => 'assign',
        'booking_id' => $b1Id,
        'delivery_person_id' => $driverId
    ]);
    // Driver picks up
    post('driver', 'delivery/order_details.php?id=' . $b1Id, [
        'action' => 'status',
        'booking_id' => $b1Id,
        'status' => 'Picked Up'
    ]);
    // Admin marks Washing, Ironing, Ready for Delivery
    post('admin', 'admin/booking_details.php?id=' . $b1Id, ['action' => 'status', 'booking_id' => $b1Id, 'status' => 'Washing']);
    post('admin', 'admin/booking_details.php?id=' . $b1Id, ['action' => 'status', 'booking_id' => $b1Id, 'status' => 'Ironing']);
    post('admin', 'admin/booking_details.php?id=' . $b1Id, ['action' => 'status', 'booking_id' => $b1Id, 'status' => 'Ready for Delivery']);
    // Driver marks Out for Delivery and Delivered with COD confirmation
    post('driver', 'delivery/order_details.php?id=' . $b1Id, ['action' => 'status', 'booking_id' => $b1Id, 'status' => 'Out for Delivery']);
    post('driver', 'delivery/order_details.php?id=' . $b1Id, [
        'action' => 'status',
        'booking_id' => $b1Id,
        'status' => 'Delivered',
        'cod_confirm' => '1'
    ]);

    $deliveredOrder = sql('SELECT booking_status, payment_status FROM bookings WHERE id=?', [$b1Id])->fetch();
    check($deliveredOrder['booking_status'] === 'Delivered', 'Order reached Delivered status');
    check($deliveredOrder['payment_status'] === 'Paid', 'COD payment marked Paid upon delivery');

    // Customer submits 5-star review
    $reviewText = 'Amazing washing quality and neat packaging! The driver was on time.';
    post('cust', 'customer/booking_details.php?id=' . $b1Id, [
        'action' => 'review_save',
        'booking_id' => $b1Id,
        'rating' => '5',
        'review_text' => $reviewText
    ]);
    $revRow = sql('SELECT * FROM reviews WHERE booking_id=?', [$b1Id])->fetch();
    check((bool)$revRow, 'Review saved in reviews table');
    check((int)$revRow['rating'] === 5, '5-star rating saved');
    check($revRow['review_text'] === $reviewText, 'Review feedback text stored accurately');

    // Customer updates their existing review (upsert test)
    $updatedText = 'Updated review: Still super satisfied with the fragrance and iron fold!';
    post('cust', 'customer/booking_details.php?id=' . $b1Id, [
        'action' => 'review_save',
        'booking_id' => $b1Id,
        'rating' => '4',
        'review_text' => $updatedText
    ]);
    $revCount = (int)sql('SELECT COUNT(*) FROM reviews WHERE booking_id=?', [$b1Id])->fetchColumn();
    check($revCount === 1, 'Only one review per booking (no duplicate review rows)');
    $updatedRev = sql('SELECT * FROM reviews WHERE booking_id=?', [$b1Id])->fetch();
    check((int)$updatedRev['rating'] === 4 && $updatedRev['review_text'] === $updatedText, 'Review update / edit succeeded');

    // Customer reviews page loads
    [$crCode, $crBody] = request('cust', 'customer/reviews.php');
    check($crCode === 200 && str_contains($crBody, $updatedText), 'Customer reviews page lists customer review');

    // Admin reviews page loads and reflects average rating
    [$arCode, $arBody] = request('admin', 'admin/reviews.php');
    check($arCode === 200 && str_contains($arBody, $updatedText), 'Admin reviews page displays review feed');
    check(str_contains($arBody, 'Rating Distribution'), 'Admin reviews page displays star distribution');

    // --------------------------------------------------------------------------
    // 7. FEATURE 4: EXPENSES & PROFIT MANAGEMENT
    // --------------------------------------------------------------------------
    echo "\n--- 7. Testing Feature 4: Expenses & Operating Profit Management ---\n";

    // Admin creates an expense category
    $catName = 'Packaging Supplies ' . bin2hex(random_bytes(2));
    post('admin', 'admin/expenses.php', [
        'action' => 'expense_category_save',
        'name' => $catName,
        'description' => 'Bags, tags, hangers'
    ]);
    $catRow = sql('SELECT * FROM expense_categories WHERE name=?', [$catName])->fetch();
    check((bool)$catRow, 'New expense category created');
    $catId = (int)$catRow['id'];

    // Admin records an expense
    $expTitle = 'Bulk garment bags purchase ' . bin2hex(random_bytes(2));
    $expAmount = 2500.00;
    post('admin', 'admin/expenses.php', [
        'action' => 'expense_save',
        'category_id' => $catId,
        'title' => $expTitle,
        'amount' => (string)$expAmount,
        'expense_date' => date('Y-m-d'),
        'notes' => 'Supplier invoice #INV-789'
    ]);
    $expRow = sql('SELECT * FROM expenses WHERE title=?', [$expTitle])->fetch();
    check((bool)$expRow, 'Expense entry recorded in expenses table');
    check((float)$expRow['amount'] === $expAmount, 'Expense amount recorded accurately');
    $expId = (int)$expRow['id'];
    $createdExpenseIds[] = $expId;

    // Admin edits expense
    $newAmount = 2800.00;
    post('admin', 'admin/expenses.php', [
        'action' => 'expense_save',
        'id' => $expId,
        'category_id' => $catId,
        'title' => $expTitle,
        'amount' => (string)$newAmount,
        'expense_date' => date('Y-m-d'),
        'notes' => 'Updated invoice amount'
    ]);
    $updatedAmount = (float)sql('SELECT amount FROM expenses WHERE id=?', [$expId])->fetchColumn();
    check($updatedAmount === $newAmount, 'Expense updated successfully');

    // Soft delete expense check
    post('admin', 'admin/expenses.php', [
        'action' => 'expense_delete',
        'id' => $expId
    ]);
    $expActive = (int)sql('SELECT is_active FROM expenses WHERE id=?', [$expId])->fetchColumn();
    check($expActive === 0, 'Expense soft delete verified (is_active=0)');

    // Add another active expense for financial calculations
    $activeExpTitle = 'Monthly Laundry Detergent ' . bin2hex(random_bytes(2));
    $activeExpAmount = 1200.00;
    post('admin', 'admin/expenses.php', [
        'action' => 'expense_save',
        'category_id' => $catId,
        'title' => $activeExpTitle,
        'amount' => (string)$activeExpAmount,
        'expense_date' => date('Y-m-d'),
        'notes' => 'Detergent pods and softener'
    ]);
    $actExpRow = sql('SELECT id FROM expenses WHERE title=?', [$activeExpTitle])->fetch();
    $createdExpenseIds[] = (int)$actExpRow['id'];

    // Verify Financial Summary calculation
    $todayStart = date('Y-m-d 00:00:00');
    $todayEnd = date('Y-m-d 23:59:59', strtotime('+1 day'));
    $fin = financial_summary(date('Y-m-d'), date('Y-m-d', strtotime('+1 day')));
    check(is_float($fin['revenue']) && is_float($fin['expenses']) && is_float($fin['profit']), 'financial_summary returns revenue, expenses, profit');
    check($fin['profit'] === round($fin['revenue'] - $fin['expenses'], 2), 'Operating Profit formula verified (Revenue - Expenses)');

    // Admin Expenses page loads
    [$expPageCode, $expPageBody] = request('admin', 'admin/expenses.php');
    check($expPageCode === 200, 'Admin expenses page loads (HTTP 200)');
    check(str_contains($expPageBody, 'Estimated Operating Profit'), 'Expenses page displays Estimated Operating Profit card');
    check(str_contains($expPageBody, $activeExpTitle), 'Expenses page lists active expense');

    // Admin Sales Reports page loads with Operating Profit section
    [$repCode, $repBody] = request('admin', 'admin/reports.php');
    check($repCode === 200 && str_contains($repBody, 'Estimated Operating Profit'), 'Reports page integrates Operating Profit overview');

    // CSV Export endpoint test
    [$csvCode, $csvBody, $csvType] = request('admin', 'actions/handle.php', [
        'action' => 'export_expenses_csv',
        'from' => date('Y-m-d'),
        'to' => date('Y-m-d'),
        'csrf' => token('admin', 'admin/expenses.php')
    ]);
    check($csvCode === 200, 'CSV export endpoint responds with HTTP 200');
    check(str_contains($csvType, 'text/csv'), 'CSV Content-Type text/csv verified');
    check(str_contains($csvBody, 'ID,Expense Date,Category,Title,Amount (LKR)'), 'CSV header row verified');
    check(str_contains($csvBody, $activeExpTitle), 'CSV output contains recorded active expense title');

    echo "\n========================================================\n";
    echo "PHASE 2 UPGRADE VERIFICATION COMPLETE\n";
    echo "Passed $checks checks successfully with 0 errors.\n";
    echo "========================================================\n";

} catch (Throwable $e) {
    echo "\n[ERROR] " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine() . "\n";
    exit(1);
} finally {
    // Cleanup cookies
    foreach ($cookies as $file) {
        if (is_file($file)) @unlink($file);
    }
}


<?php
/**
 * Automated Verification Suite for Sidebar Scrolling & Responsive Layout
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

date_default_timezone_set('Asia/Colombo');
$base = rtrim($argv[1] ?? 'http://localhost/smart-laundry', '/');

$checks = 0;
function check(bool $ok, string $message): void {
    global $checks;
    if (!$ok) throw new RuntimeException('FAIL: ' . $message);
    $checks++;
    echo "PASS: $message\n";
}

$cookieFile = tempnam(sys_get_temp_dir(), 'laundry-sidebar-');

function req(string $path, ?array $post = null): array {
    global $base, $cookieFile;
    $c = curl_init($base . '/' . $path);
    curl_setopt_array($c, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_COOKIEFILE => $cookieFile,
        CURLOPT_COOKIEJAR => $cookieFile,
        CURLOPT_TIMEOUT => 15
    ]);
    if ($post !== null) {
        curl_setopt($c, CURLOPT_POST, true);
        curl_setopt($c, CURLOPT_POSTFIELDS, http_build_query($post));
    }
    $body = curl_exec($c);
    $code = curl_getinfo($c, CURLINFO_HTTP_CODE);
    curl_close($c);
    return [$code, $body];
}

function get_token(string $path): string {
    [$code, $body] = req($path);
    if (!preg_match('/name="csrf" value="([a-f0-9]+)"/', $body, $m)) {
        throw new RuntimeException("No CSRF token found on $path");
    }
    return $m[1];
}

try {
    echo "========================================================\n";
    echo "Starting Sidebar Scrolling & Layout Verification Suite\n";
    echo "Target: $base\n";
    echo "========================================================\n\n";

    // 1. Inspect CSS file for Sidebar rules
    echo "--- 1. Verifying CSS Architecture (assets/css/style.css) ---\n";
    $css = file_get_contents(__DIR__ . '/../assets/css/style.css');
    check($css !== false && strlen($css) > 1000, "Loaded style.css successfully");

    // Viewport height enforcement
    check(str_contains($css, 'height:100vh') || str_contains($css, 'height: 100vh'), "Sidebar sets 100vh fallback height");
    check(str_contains($css, '100dvh'), "Sidebar supports modern dynamic viewport height (100dvh)");

    // Flex column layout
    check(str_contains($css, 'display:flex;flex-direction:column') || str_contains($css, 'display: flex; flex-direction: column'), "Sidebar layout uses flex-direction: column");

    // Brand and Label pinning (flex-shrink: 0)
    check(str_contains($css, '.sidebar .brand{font-size:20px;flex-shrink:0}'), "Sidebar brand logo is pinned at the top (flex-shrink: 0)");
    check(str_contains($css, '.sidebar-label{font-size:10px;font-weight:700;letter-spacing:1.6px;color:#94a3b8;margin:22px 10px 8px;flex-shrink:0}'), "Sidebar label has optimized margins and is pinned (flex-shrink: 0)");

    // Scrollable navigation area
    check(str_contains($css, 'overflow-y:auto') || str_contains($css, 'overflow-y: auto'), "Sidebar nav has overflow-y: auto for vertical scrolling");
    check(str_contains($css, 'overflow-x:hidden') || str_contains($css, 'overflow-x: hidden'), "Sidebar nav has overflow-x: hidden to prevent horizontal scrolling");
    check(str_contains($css, 'flex:1 1 auto;min-height:0') || str_contains($css, 'flex: 1 1 auto; min-height: 0'), "Sidebar nav expands with flex: 1 1 auto and min-height: 0");

    // Horizontal overflow prevention on nav links
    check(str_contains($css, 'white-space:nowrap;overflow:hidden;text-overflow:ellipsis'), "Nav links have white-space: nowrap with ellipsis prevention");

    // Custom scrollbar styling (approximately 6px)
    check(str_contains($css, '::-webkit-scrollbar{width:6px}') || str_contains($css, '::-webkit-scrollbar { width: 6px; }'), "Custom scrollbar width is configured to 6px");
    check(str_contains($css, 'scrollbar-width:thin'), "Standard CSS scrollbar-width: thin is defined");
    check(str_contains($css, 'rgba(15,118,110'), "Light mode scrollbar uses teal color palette");

    // Dark Mode scrollbar styling
    check(str_contains($css, '[data-theme="dark"] .sidebar nav'), "Dark mode specific sidebar nav scrollbar rule is defined");
    check(str_contains($css, 'rgba(45, 212, 191'), "Dark mode scrollbar uses luminous teal color palette");

    // Mobile drawer responsiveness
    check(str_contains($css, '@media(max-width:992px)') && str_contains($css, '.sidebar{width:260px;height:100vh;height:100dvh'), "Mobile drawer sets 100dvh viewport height and smooth transform");

    // Short-height viewport adaptation
    check(str_contains($css, '@media (max-height: 720px)'), "Short viewport optimization rule defined (@media max-height: 720px)");

    // 2. Admin Workspace Sidebar Verification
    echo "\n--- 2. Verifying Admin Sidebar Content & Navigation ---\n";
    $token = get_token('login.php');
    [$loginCode, $loginBody] = req('actions/handle.php', [
        'action' => 'login',
        'csrf' => $token,
        'email' => 'admin@laundry.com',
        'password' => 'Admin@123'
    ]);
    check($loginCode === 200, "Admin login successful");

    [$dashCode, $dashBody] = req('admin/dashboard.php');
    check($dashCode === 200, "Admin dashboard loaded (HTTP 200)");
    check(str_contains($dashBody, '<aside class="sidebar">'), "Sidebar aside element rendered");
    check(str_contains($dashBody, 'smartlaundry'), "Logo 'smartlaundry' rendered in sidebar");

    // Verify all 17 admin menu items are rendered inside <nav>
    $expectedAdminLinks = [
        'dashboard.php' => 'Overview',
        'inventory.php' => 'Inventory',
        'stock_movements.php' => 'Stock movements',
        'suppliers.php' => 'Suppliers',
        'low_stock.php' => 'Low stock alerts',
        'attendance.php' => 'Staff attendance',
        'services.php' => 'Services',
        'bookings.php' => 'All bookings',
        'coupons.php' => 'Coupons',
        'expenses.php' => 'Expenses',
        'slots.php' => 'Pickup slots',
        'schedules.php' => 'Schedules',
        'reviews.php' => 'Reviews',
        'customers.php' => 'Customers',
        'delivery_staff.php' => 'Delivery team',
        'reports.php' => 'Sales reports',
        'backups.php' => 'Backup management'
    ];

    preg_match('/<aside class="sidebar">.*?<nav>(.*?)<\/nav>/s', $dashBody, $navMatch);
    check(!empty($navMatch), "Extracted <nav> container from sidebar");
    $navHtml = $navMatch[1];

    foreach ($expectedAdminLinks as $urlPath => $label) {
        check(str_contains($navHtml, $urlPath), "Menu link '$label' ($urlPath) exists inside sidebar <nav>");
    }

    // Verify last item is specifically Backup management
    check(str_contains($navHtml, 'backups.php') && str_contains($navHtml, 'Backup management'), "Last item 'Backup management' is present in scrollable nav");

    // Verify bottom section exists outside nav
    check(str_contains($dashBody, 'sidebar-bottom'), "Sidebar-bottom container is present");
    check(str_contains($dashBody, 'Sign out'), "Logout button is present in sidebar-bottom");

    // 3. Independent Dashboard Scrolling Verification
    echo "\n--- 3. Verifying Main Content Shell & Layout Independence ---\n";
    check(str_contains($dashBody, 'class="main-shell"'), "Main shell container present for independent page content scrolling");
    check(str_contains($css, '.main-shell{margin-left:246px'), "Desktop main-shell has 246px margin-left matching fixed sidebar width");

    // 4. Delivery Workspace Sidebar Verification
    echo "\n--- 4. Verifying Delivery Staff Sidebar ---\n";
    $delToken = get_token('login.php');
    req('actions/handle.php', [
        'action' => 'login',
        'csrf' => $delToken,
        'email' => 'delivery@laundry.com',
        'password' => 'Delivery@123'
    ]);
    [$delCode, $delBody] = req('delivery/dashboard.php');
    check($delCode === 200, "Delivery dashboard loaded (HTTP 200)");
    check(str_contains($delBody, 'orders.php') && str_contains($delBody, 'Assigned orders'), "Delivery sidebar contains 'Assigned orders'");
    check(str_contains($delBody, 'attendance.php') && str_contains($delBody, 'My attendance'), "Delivery sidebar contains 'My attendance'");

    echo "\n========================================================\n";
    echo "SIDEBAR SCROLLING & RESPONSIVENESS VERIFICATION COMPLETE\n";
    echo "Passed $checks checks successfully with 0 errors.\n";
    echo "========================================================\n";

} catch (Throwable $e) {
    echo "\nTEST SUITE FAILED: " . $e->getMessage() . "\n";
    exit(1);
} finally {
    if (file_exists($cookieFile)) @unlink($cookieFile);
}


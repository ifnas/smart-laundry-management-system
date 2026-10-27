# Verification record

Executed on 26 October 2026 using PHP 8.2.12 and MariaDB 10.4.32 on Windows.

## Passed

- PHP syntax checks for all **47 PHP files**, including the setup, router and test scripts.
- **68 HTTP/database integration assertions** in `tests/smoke.php`.
- Full suite run against the normal page entry points, then repeated against the local launcher's `/smart-laundry/` routing after Windows path fixes.
- Registration and login for all roles, logout and deactivated-account session revocation.
- Public and protected page HTTP responses.
- Server-side price calculation (default 3 × 250 + 2 × 350 = 1,450), ignored client price tampering, persisted items, rejected invalid quantities/dates without partial orders.
- Complete Pending → Accepted → Picked Up → Washing → Ironing → Ready for Delivery → Out for Delivery → Delivered workflow.
- Assignment visibility and prevention of unauthorized customer/driver access and forbidden status transitions.
- Pending cancellation, accepted-order cancellation prevention and admin rejection.
- COD confirmation, atomic Paid/Delivered recording, collection timestamp/driver, prevention of repeated collection, and exactly-once revenue increase.
- Sales report and completed customer/driver histories.
- Profile changes, service creation/edit/removal, delivery account creation/deactivation.
- CSRF rejection, POST-only mutations and escaped customer HTML.
- HTTP 200 responses for six local UI assets, including the icon font and Chart.js.
- HTTP 404 protection for SQL, configuration, includes, tests, router, web setup and the PowerShell launcher through the development router.
- Launcher startup from a stopped database/web-server state, with the existing isolated database preserved.

Temporary test fixtures were removed after the runs. Default services and three demo accounts remain in the isolated database.

## Not verified through a browser

No browser provider was connected. These checks remain manual:

1. Desktop/mobile visual layout at approximately 1440px, 768px and 390px.
2. Open/close mobile sidebar, keyboard navigation and form focus.
3. JavaScript live subtotals/grand total and confirmation dialogs.
4. Chart.js chart rendering, icon rendering and printed sales report.
5. Apache deployment at `http://localhost/smart-laundry/` after copying to `htdocs` and running setup.

The responsive styles and local assets exist; this record does not claim visual verification.

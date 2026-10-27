<?php
$links = [
    'admin' => [
        ['dashboard.php', 'grid', 'Overview'],
        ['inventory.php', 'boxes', 'Inventory'],
        ['stock_movements.php', 'arrow-left-right', 'Stock movements'],
        ['suppliers.php', 'building', 'Suppliers'],
        ['low_stock.php', 'exclamation-triangle', 'Low stock alerts'],
        ['attendance.php', 'calendar-check', 'Staff attendance'],
        ['services.php', 'grid-3x3-gap', 'Services'],
        ['bookings.php', 'basket', 'All bookings'],
        ['coupons.php', 'ticket-perforated', 'Coupons'],
        ['expenses.php', 'wallet2', 'Expenses'],
        ['slots.php', 'clock-history', 'Pickup slots'],
        ['schedules.php', 'calendar3', 'Schedules'],
        ['reviews.php', 'star', 'Reviews'],
        ['customers.php', 'people', 'Customers'],
        ['delivery_staff.php', 'truck', 'Delivery team'],
        ['reports.php', 'bar-chart', 'Sales reports'],
        ['backups.php', 'database-down', 'Backup management']
    ],
    'customer' => [
        ['dashboard.php', 'grid', 'Overview'],
        ['new_booking.php', 'plus-circle', 'Book a pickup'],
        ['bookings.php', 'basket', 'My bookings'],
        ['reviews.php', 'star', 'My reviews'],
        ['profile.php', 'person', 'My profile']
    ],
    'delivery' => [
        ['dashboard.php', 'grid', 'Overview'],
        ['orders.php', 'truck', 'Assigned orders'],
        ['completed.php', 'check2-circle', 'Completed deliveries'],
        ['attendance.php', 'clock-history', 'My attendance']
    ]
];
?>
<aside class="sidebar">
  <a class="brand" href="<?= e(url($u['role'].'/dashboard.php')) ?>">
    <span class="brand-icon"><i class="bi bi-droplet-half"></i></span>smartlaundry<span class="brand-dot">.</span>
  </a>
  <p class="sidebar-label">YOUR WORKSPACE</p>
  <nav>
    <?php foreach($links[$u['role']] as [$file,$icon,$label]): ?>
      <a class="<?= basename($_SERVER['SCRIPT_NAME'])===$file?'active':'' ?>" href="<?= e(url($u['role'].'/'.$file)) ?>">
        <i class="bi bi-<?= $icon ?>"></i><?= $label ?>
      </a>
    <?php endforeach ?>
  </nav>
  <div class="sidebar-bottom">
    <div class="sidebar-note">
      <i class="bi bi-stars"></i>
      <strong>Fresh clothes. Fresh starts.</strong>
      <p>Every order, handled with care.</p>
    </div>
    <form action="<?= e(url('actions/handle.php')) ?>" method="post">
      <?= csrf() ?>
      <input type="hidden" name="action" value="logout">
      <button class="logout"><i class="bi bi-box-arrow-left"></i> Sign out</button>
    </form>
  </div>
</aside>

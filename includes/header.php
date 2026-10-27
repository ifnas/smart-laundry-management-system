<?php 
$u=user(); 
$private=isset($role); 
$title=$title??'Smart Laundry'; 
$unreadCount = $u ? unread_notifications_count((int)$u['id']) : 0;
$latestNotifs = $u ? recent_notifications((int)$u['id'], 6) : [];
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?= e($title) ?> · Smart Laundry</title>
<script>
(function(){
  try{
    var t=localStorage.getItem('smart_laundry_theme');
    var theme=(t==='dark')?'dark':'light';
    document.documentElement.setAttribute('data-theme',theme);
    document.documentElement.setAttribute('data-bs-theme',theme);
  }catch(e){}
})();
</script>
<link href="<?= e(url('assets/vendor/bootstrap.min.css')) ?>" rel="stylesheet"><link href="<?= e(url('assets/vendor/bootstrap-icons.min.css')) ?>" rel="stylesheet"><link href="<?= e(url('assets/css/style.css')) ?>" rel="stylesheet"></head>
<body class="<?= $private?'workspace':'public-page' ?>">
<?php if($private): require __DIR__.'/sidebar.php'; endif; ?>
<div class="<?= $private?'main-shell':'' ?>"><header class="topbar"><a class="brand" href="<?= e(url('index.php')) ?>"><span class="brand-icon"><i class="bi bi-droplet-half"></i></span>smart<span>laundry</span><span class="brand-dot">.</span></a>
<?php if($private): ?>
<div class="topbar-actions">
  <!-- Notifications Bell & Dropdown -->
  <div class="notif-wrapper position-relative">
    <button type="button" class="btn-theme-toggle notif-btn" id="notif-toggle" aria-expanded="false" title="Notifications">
      <i class="bi bi-bell"></i>
      <?php if ($unreadCount > 0): ?>
        <span class="notif-badge"><?= $unreadCount > 9 ? '9+' : $unreadCount ?></span>
      <?php endif ?>
    </button>
    <div class="notif-menu d-none" id="notif-menu">
      <div class="notif-menu-header">
        <strong>Notifications</strong>
        <?php if ($unreadCount > 0): ?>
          <form action="<?= e(url('actions/handle.php')) ?>" method="post" class="m-0">
            <?= csrf() ?>
            <input type="hidden" name="action" value="mark_all_notifications_read">
            <input type="hidden" name="redirect_to" value="<?= e(basename($_SERVER['SCRIPT_NAME']) . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '')) ?>">
            <button type="submit" class="notif-mark-btn">Mark all read</button>
          </form>
        <?php endif ?>
      </div>
      <div class="notif-menu-list">
        <?php if (!$latestNotifs): ?>
          <div class="p-3 text-center text-muted" style="font-size:12px;">No notifications yet.</div>
        <?php else: ?>
          <?php foreach ($latestNotifs as $n): ?>
            <div class="notif-item <?= $n['is_read'] ? 'read' : 'unread' ?>">
              <div class="d-flex justify-content-between align-items-baseline mb-1">
                <span class="notif-item-title"><?= e($n['title']) ?></span>
                <span class="notif-time"><?= time_ago($n['created_at']) ?></span>
              </div>
              <p class="notif-item-msg"><?= e($n['message']) ?></p>
              <?php if (!empty($n['booking_id'])): ?>
                <?php 
                  $linkPath = ($u['role'] === 'delivery' ? 'delivery/order_details.php' : ($u['role'] === 'admin' ? 'admin/booking_details.php' : 'customer/booking_details.php')) . '?id=' . $n['booking_id'];
                ?>
                <a href="<?= e(url($linkPath)) ?>" class="notif-item-link">View Order <i class="bi bi-chevron-right"></i></a>
              <?php endif ?>
            </div>
          <?php endforeach ?>
        <?php endif ?>
      </div>
      <div class="notif-menu-footer">
        <a href="<?= e(url('notifications.php')) ?>">View all activity</a>
      </div>
    </div>
  </div>

  <!-- Theme Toggle -->
  <button type="button" class="btn-theme-toggle" id="theme-toggle" aria-label="Toggle dark and light mode" title="Switch theme">
    <i class="bi bi-moon-stars" id="theme-icon"></i>
    <span class="theme-text" id="theme-text">Dark</span>
  </button>

  <!-- User Profile Section -->
  <div class="topbar-user">
    <span class="avatar"><?= e(strtoupper(substr($u['name'],0,1))) ?></span>
    <span class="user-meta"><?= e($u['name']) ?><small><?= e(ucfirst($u['role'])) ?> account</small></span>
  </div>

  <!-- Logout Button -->
  <form action="<?= e(url('actions/handle.php')) ?>" method="post" class="topbar-logout-form m-0">
    <?= csrf() ?>
    <input type="hidden" name="action" value="logout">
    <button type="submit" class="btn btn-logout" id="topbar-logout-btn" title="Sign out of your account">
      <i class="bi bi-box-arrow-right"></i>
      <span>Logout</span>
    </button>
  </form>

  <!-- Mobile Drawer Toggle -->
  <button class="btn btn-light mobile-toggle" type="button" aria-label="Toggle navigation" aria-expanded="false"><i class="bi bi-list"></i></button>
</div>
<?php else: ?>
<nav class="public-nav">
  <a href="<?= e(url('services.php')) ?>">Services & pricing</a>
  <a href="<?= e(url('about.php')) ?>">About us</a>
  <button type="button" class="btn-theme-toggle" id="theme-toggle" aria-label="Toggle dark and light mode" title="Switch theme">
    <i class="bi bi-moon-stars" id="theme-icon"></i>
    <span class="theme-text" id="theme-text">Dark</span>
  </button>
  <?php if($u): ?>
    <a class="btn btn-primary" href="<?= e(url($u['role'].'/dashboard.php')) ?>">My dashboard</a>
    <form action="<?= e(url('actions/handle.php')) ?>" method="post" class="topbar-logout-form m-0 d-inline-block">
      <?= csrf() ?>
      <input type="hidden" name="action" value="logout">
      <button type="submit" class="btn btn-sm btn-logout" title="Sign out">
        <i class="bi bi-box-arrow-right"></i>
        <span>Logout</span>
      </button>
    </form>
  <?php else: ?>
    <a href="<?= e(url('login.php')) ?>">Sign in</a>
    <a class="btn btn-primary" href="<?= e(url('register.php')) ?>">Get started <i class="bi bi-arrow-up-right"></i></a>
  <?php endif ?>
</nav>
<?php endif ?></header>
<main class="<?= $private?'page-content':'container public-content' ?>">
<?php if(isset($_SESSION['flash'])): [$msg,$type]=$_SESSION['flash']; unset($_SESSION['flash']); ?><div class="alert alert-<?= e($type) ?>" role="alert"><?= e($msg) ?></div><?php endif ?>
<?php if($private): ?><div class="page-heading"><div><div class="eyebrow"><?= e($u['role']) ?> workspace</div><h1><?= e($title) ?></h1><p><?= e($subtitle??'A little less laundry. A little more life.') ?></p></div><div class="date-pill"><i class="bi bi-calendar3"></i> <?= date('D, d M Y') ?></div></div><?php endif ?>

<?php
$currentRole = currentRole();
$user = currentUser();
$userInitial = mb_substr($user['fullname'] ?? $user['username'] ?? 'U', 0, 1, 'UTF-8');
?>
<aside class="sidebar">
  <a class="brand sidebar-brand" href="index.php?page=<?= getDefaultPageForRole($currentRole) ?>">
    <span class="brand-mark">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8h1a4 4 0 0 1 0 8h-1"></path><path d="M2 8h16v9a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V8z"></path><line x1="6" y1="1" x2="6" y2="4"></line><line x1="10" y1="1" x2="10" y2="4"></line><line x1="14" y1="1" x2="14" y2="4"></line></svg>
    </span>
    <span>SmartDine</span>
  </a>

  <!-- NHÓM MENU: VẬN HÀNH -->
  <div class="side-label">QUẢN LÝ VẬN HÀNH</div>

  <?php if (canRoleAccessPage($currentRole, 'admin')): ?>
    <a class="<?= active('admin', $page) ?>" href="index.php?page=admin">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
      <span>Tổng quan</span>
    </a>
  <?php endif; ?>

  <?php if (canRoleAccessPage($currentRole, 'staff')): ?>
    <a class="<?= active('staff', $page) ?>" href="index.php?page=staff">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"></rect><line x1="3" y1="9" x2="21" y2="9"></line><line x1="9" y1="21" x2="9" y2="9"></line></svg>
      <span>Sơ đồ bàn</span>
    </a>
    <a class="<?= active('table-order', $page === 'payment' ? 'table-order' : $page) ?>" href="index.php?page=table-order">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
      <span>Đơn hàng / Bàn</span>
    </a>
  <?php endif; ?>

  <?php if (canRoleAccessPage($currentRole, 'kitchen')): ?>
    <?php
    require_once __DIR__ . '/kitchen.php';
    try {
        $kitchenCounts    = countKitchenTickets(fetchKitchenTickets(getOrdersDb()));
        $kitchenOpenCount = $kitchenCounts['pending'];
    } catch (RuntimeException | PDOException $e) {
        error_log('sidebar kitchen counter: ' . $e->getMessage());
        $kitchenOpenCount = 0;
    }
    ?>
    <a class="<?= active('kitchen', $page) ?>" href="index.php?page=kitchen">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 13.8V4a2 2 0 1 1 4 0v9.8"></path><path d="M14 13.8V4a2 2 0 1 1 4 0v9.8"></path><path d="M3 14h18a2 2 0 0 1 2 2v1a4 4 0 0 1-4 4H5a4 4 0 0 1-4-4v-1a2 2 0 0 1 2-2z"></path></svg>
      <span>Màn hình bếp</span>
      <span class="nav-badge kitchen-counter"><?= $kitchenOpenCount ?></span>
    </a>
  <?php endif; ?>

  <?php if (canRoleAccessPage($currentRole, 'bills')): ?>
    <a class="<?= active('bills', $page) ?>" href="index.php?page=bills">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
      <span>Lịch sử bill</span>
    </a>
  <?php endif; ?>

  <!-- NHÓM MENU: QUẢN TRỊ (CHỈ DÀNH CHO ADMIN) -->
  <?php if (isAdmin()): ?>
    <div class="side-label">QUẢN TRỊ HỆ THỐNG</div>
    
    <a class="<?= active('users', $page) ?>" href="index.php?page=users">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
      <span>Quản lý nhân viên</span>
      <span class="nav-badge role-badge-count">Admin</span>
    </a>

    <a class="<?= active('dishes', $page) ?>" href="index.php?page=dishes">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 2v7c0 1.1.9 2 2 2h4a2 2 0 0 0 2-2V2"></path><path d="M7 2v20"></path><path d="M21 15V2v0a5 5 0 0 0-5 5v6c0 1.1.9 2 2 2h3Zm0 0v7"></path></svg>
      <span>Quản lý thực đơn</span>
    </a>

    <a class="<?= active('tables', $page) ?>" href="index.php?page=tables">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"></rect><path d="M3 9h18M9 21V9"></path></svg>
      <span>Quản lý bàn & khu vực</span>
    </a>
  <?php endif; ?>

  <!-- NHÓM MENU: KHÁCH HÀNG -->
  <div class="side-label">KHÁCH HÀNG</div>
  <a href="index.php?page=menu" target="_blank" class="client-view-link">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line></svg>
    <span>Xem trang gọi món</span>
  </a>

  <!-- USER FOOTER -->
  <div class="side-bottom">
    <div class="avatar avatar-role-<?= htmlspecialchars($currentRole) ?>" title="<?= htmlspecialchars($user['fullname']) ?>">
      <?= htmlspecialchars($userInitial) ?>
    </div>
    <div class="grow user-info-meta">
      <b class="user-fullname" title="<?= htmlspecialchars($user['fullname']) ?>">
        <?= htmlspecialchars($user['fullname'] ?: $user['username']) ?>
      </b>
      <div class="user-role-line">
        <?= getRoleBadgeHtml($currentRole) ?>
      </div>
    </div>
    <a href="index.php?action=logout" class="btn-sidebar-logout" title="Đăng xuất khỏi hệ thống">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
    </a>
  </div>
</aside>
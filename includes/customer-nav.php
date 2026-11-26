<header class="topbar customer-topbar">
  <div class="topbar-left">
    <a class="brand" href="homepage/index.php">
      <span class="brand-mark">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8h1a4 4 0 0 1 0 8h-1"></path><path d="M2 8h16v9a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V8z"></path><line x1="6" y1="1" x2="6" y2="4"></line><line x1="10" y1="1" x2="10" y2="4"></line><line x1="14" y1="1" x2="14" y2="4"></line></svg>
      </span>
      <span>SmartDine</span>
    </a>
    <div class="table-chip" title="Bàn đang kết nối">
      <span class="dot green"></span>
      <b>Bàn B05</b>
      <span class="chip-status">Đang gọi món</span>
    </div>
  </div>

  <nav class="top-links">
    <a href="homepage/index.php">Trang chủ</a>
    <a class="<?=active('menu',$page)?>" href="index.php?page=menu">Thực đơn</a>
    <a href="homepage/reservation.php">Đặt bàn</a>
    <a href="homepage/booking-history.php">Tra cứu đặt bàn</a>
  </nav>

  <div class="top-actions">
    <a class="home-nav-reserve" href="homepage/reservation.php">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="17" rx="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
      <span>Đặt bàn</span>
    </a>
    <?php if (isLoggedIn()): ?>
      <a class="workspace-switch-btn" href="index.php?page=<?= getDefaultPageForRole(currentRole()) ?>" title="Quay lại khu vực vận hành">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
        <span><?= htmlspecialchars(currentUser()['fullname'] ?: currentUser()['username']) ?> (<?= getRoleName(currentRole()) ?>)</span>
      </a>
      <a class="text-link" href="index.php?logout=1" style="color: #ef4444;" title="Đăng xuất">Đăng xuất</a>
    <?php else: ?>
      <a class="text-link <?=active('login',$page)?>" href="index.php?page=login">Đăng nhập</a>
    <?php endif; ?>
    <a class="btn btn-cart <?=active('cart',$page)?>" href="index.php?page=cart">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path></svg>
      <span>Giỏ hàng</span>
      <span class="cart-count">0</span>
    </a>
  </div>
</header>


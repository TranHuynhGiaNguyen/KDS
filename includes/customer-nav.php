<header class="topbar customer-topbar">
  <div class="topbar-left">
    <a class="brand" href="index.php?page=menu">
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
    <a class="<?=active('menu',$page)?>" href="index.php?page=menu">Thực đơn</a>
    <a class="<?=active('reservation',$page)?>" href="index.php?page=reservation">Đặt bàn</a>
    <a class="<?=active('tracking',$page)?>" href="index.php?page=tracking">Theo dõi đơn</a>
  </nav>

  <div class="top-actions">
    <a class="workspace-switch-btn" href="index.php?page=kitchen" title="Chuyển sang màn hình bếp">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 13.8V4a2 2 0 1 1 4 0v9.8"></path><path d="M14 13.8V4a2 2 0 1 1 4 0v9.8"></path><path d="M3 14h18a2 2 0 0 1 2 2v1a4 4 0 0 1-4 4H5a4 4 0 0 1-4-4v-1a2 2 0 0 1 2-2z"></path></svg>
      <span>Màn hình bếp (KDS)</span>
    </a>
    <a class="text-link <?=active('login',$page)?>" href="index.php?page=login">Đăng nhập</a>
    <a class="btn btn-cart <?=active('cart',$page)?>" href="index.php?page=cart">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path></svg>
      <span>Giỏ hàng</span>
      <span class="cart-count">0</span>
    </a>
  </div>
</header>


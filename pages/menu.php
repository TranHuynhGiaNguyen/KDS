<section class="hero">
  <div class="hero-copy">
    <span class="eyebrow">HƯƠNG VỊ ĐƯỢC YÊU THÍCH</span>
    <h1>Món ngon hôm nay,<br><em>trọn vị niềm vui.</em></h1>
    <p>Khám phá thực đơn phong phú từ các nguyên liệu tươi sạch, chế biến công phu bởi các đầu bếp hàng đầu tại SmartDine.</p>
    <div class="hero-actions">
      <a class="btn btn-hero" href="#menu">
        <span>Khám phá thực đơn</span>
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
      </a>
      <a class="btn btn-hero-outline" href="index.php?page=reservation">
        <span>Đặt bàn trước</span>
      </a>
    </div>
  </div>
  <div class="hero-art">
    <img src="https://images.unsplash.com/photo-1546069901-ba9599a7e63c?auto=format&fit=crop&w=900&q=85" alt="Món ăn nổi bật tại SmartDine">
  </div>
  <div class="hero-badge">
    <span class="badge-star">★ 4.9</span>
    <small>Hơn 1.200 đánh giá 5 sao từ thực khách</small>
  </div>
</section>

<section class="section-head" id="menu">
  <div>
    <span class="eyebrow">THỰC ĐƠN NHÀ HÀNG</span>
    <h2>Khám phá món ngon</h2>
  </div>
  <label class="searchbox">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
    <input placeholder="Tìm món ăn, đồ uống..." data-filter-input autocomplete="off">
  </label>
</section>

<?php
$menuCategories = getAllCategories(true);
$menuDishes = getAllDishes('', 'visible', '');
?>
<div class="menu-layout">
  <aside class="category-list">
    <button class="category active" data-category="all">
      <span class="cat-icon">✦</span>
      <span>Tất cả món</span>
    </button>
    <?php foreach ($menuCategories as $cat): ?>
      <button class="category" data-category="<?= htmlspecialchars($cat['slug']) ?>">
        <span class="cat-icon"><?= htmlspecialchars($cat['icon']) ?></span>
        <span><?= htmlspecialchars($cat['name']) ?></span>
      </button>
    <?php endforeach; ?>
  </aside>

  <div class="food-grid">
    <?php if (empty($menuDishes)): ?>
      <div style="grid-column: 1 / -1; text-align: center; padding: 40px; color: var(--muted);">
        <p>Hiện chưa có món ăn nào đang mở bán. Vui lòng quay lại sau!</p>
      </div>
    <?php else: ?>
      <?php foreach ($menuDishes as $dish): 
        $isOutOfStock = ($dish['status'] === 'out_of_stock');
        $imgUrl = getDishImageUrl($dish['image']);
      ?>
        <article class="food-card <?= $isOutOfStock ? 'dish-out-of-stock' : '' ?>" data-food data-type="<?= htmlspecialchars($dish['category_slug']) ?>">
          <div class="food-image">
            <img src="<?= $imgUrl ?>" alt="<?= htmlspecialchars($dish['name']) ?>" loading="lazy" onerror="this.src='https://images.unsplash.com/photo-1546069901-ba9599a7e63c?auto=format&fit=crop&w=700&q=80'">
            <?php if (!empty($dish['tag'])): ?>
              <span class="tag tag-hot"><?= htmlspecialchars($dish['tag']) ?></span>
            <?php endif; ?>
            <?php if ($isOutOfStock): ?>
              <span class="tag tag-out" style="background:#fee2e2; color:#b91c1c;">Tạm hết</span>
            <?php endif; ?>

            <?php if (!$isOutOfStock): ?>
              <button class="add-btn" data-add="<?= htmlspecialchars($dish['name']) ?>" data-price="<?= (int)$dish['price'] ?>" title="Thêm vào giỏ hàng">+</button>
            <?php else: ?>
              <button class="add-btn disabled" disabled title="Món này tạm hết" style="opacity: 0.5; cursor: not-allowed; background: #9ca3af;">✕</button>
            <?php endif; ?>
          </div>
          <div class="food-info">
            <div class="food-head-row">
              <h3><?= htmlspecialchars($dish['name']) ?></h3>
              <span class="rating">★ <?= number_format((float)$dish['rating'], 1) ?></span>
            </div>
            <p><?= htmlspecialchars($dish['description'] ?: 'Món ngon chế biến từ nguyên liệu tươi sạch mỗi ngày.') ?></p>
            <div class="food-foot-row">
              <b class="price"><?= formatPrice($dish['price']) ?></b>
              <?php if (!$isOutOfStock): ?>
                <button class="btn btn-add-text" data-add="<?= htmlspecialchars($dish['name']) ?>" data-price="<?= (int)$dish['price'] ?>">+ Chọn món</button>
              <?php else: ?>
                <button class="btn btn-add-text btn-outline" disabled style="opacity: 0.6; cursor: not-allowed;">Tạm hết</button>
              <?php endif; ?>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<!-- Thanh giỏ hàng nổi thông minh (Tự ẩn khi rỗng, xuất hiện mượt mà khi có món) -->
<div class="floating-cart" id="floating-cart-bar">
  <div class="cart-floating-meta">
    <div class="cart-icon-bubble">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path></svg>
      <span class="cart-count">0</span>
    </div>
    <div>
      <b id="cart-summary">Giỏ hàng (0 món)</b>
      <small id="cart-total">0đ</small>
    </div>
  </div>
  <a class="btn btn-floating-checkout" href="index.php?page=cart">
    <span>Xem giỏ hàng</span>
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"></polyline></svg>
  </a>
</div>

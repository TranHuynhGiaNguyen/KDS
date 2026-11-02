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

<div class="menu-layout">
  <aside class="category-list">
    <button class="category active" data-category="all">
      <span class="cat-icon">✦</span>
      <span>Tất cả món</span>
    </button>
    <button class="category" data-category="main">
      <span class="cat-icon">♨</span>
      <span>Món chính</span>
    </button>
    <button class="category" data-category="side">
      <span class="cat-icon">❋</span>
      <span>Món phụ / Khai vị</span>
    </button>
    <button class="category" data-category="drink">
      <span class="cat-icon">◉</span>
      <span>Đồ uống</span>
    </button>
    <button class="category" data-category="dessert">
      <span class="cat-icon">✧</span>
      <span>Tráng miệng</span>
    </button>
  </aside>

  <div class="food-grid">
    <article class="food-card" data-food data-type="main">
      <div class="food-image">
        <img src="https://images.unsplash.com/photo-1603133872878-684f208fb84b?auto=format&fit=crop&w=700&q=80" alt="Cơm gà Hải Nam" loading="lazy">
        <span class="tag tag-hot">Bán chạy nhất</span>
        <button class="add-btn" data-add="Cơm gà Hải Nam" data-price="75000" title="Thêm vào giỏ hàng">+</button>
      </div>
      <div class="food-info">
        <div class="food-head-row">
          <h3>Cơm gà Hải Nam</h3>
          <span class="rating">★ 4.9</span>
        </div>
        <p>Gà ta mềm ngọt, cơm niêu thơm nấu cùng nước cốt luộc gà và sốt gừng đặc trưng.</p>
        <div class="food-foot-row">
          <b class="price">75.000đ</b>
          <button class="btn btn-add-text" data-add="Cơm gà Hải Nam" data-price="75000">+ Chọn món</button>
        </div>
      </div>
    </article>

    <article class="food-card" data-food data-type="main">
      <div class="food-image">
        <img src="https://images.unsplash.com/photo-1569718212165-3a8278d5f624?auto=format&fit=crop&w=700&q=80" alt="Mì xào bò" loading="lazy">
        <span class="tag tag-chef">Gợi ý bếp trưởng</span>
        <button class="add-btn" data-add="Mì xào bò" data-price="85000" title="Thêm vào giỏ hàng">+</button>
      </div>
      <div class="food-info">
        <div class="food-head-row">
          <h3>Mì xào bò</h3>
          <span class="rating">★ 4.8</span>
        </div>
        <p>Mì trứng dai xào cùng thăn bò mềm, nấm hương tươi và rau cải giòn thơm.</p>
        <div class="food-foot-row">
          <b class="price">85.000đ</b>
          <button class="btn btn-add-text" data-add="Mì xào bò" data-price="85000">+ Chọn món</button>
        </div>
      </div>
    </article>

    <article class="food-card" data-food data-type="side">
      <div class="food-image">
        <img src="https://images.unsplash.com/photo-1512621776951-a57141f2eefd?auto=format&fit=crop&w=700&q=80" alt="Salad trộn" loading="lazy">
        <span class="tag tag-healthy">Healthy</span>
        <button class="add-btn" data-add="Salad trộn" data-price="55000" title="Thêm vào giỏ hàng">+</button>
      </div>
      <div class="food-info">
        <div class="food-head-row">
          <h3>Salad trộn đặc biệt</h3>
          <span class="rating">★ 4.7</span>
        </div>
        <p>Rau xanh thủy canh tươi giòn, cà chua bi organic với sốt mè rang béo ngậy thanh mát.</p>
        <div class="food-foot-row">
          <b class="price">55.000đ</b>
          <button class="btn btn-add-text" data-add="Salad trộn" data-price="55000">+ Chọn món</button>
        </div>
      </div>
    </article>

    <!-- SỬA LỖI BROKEN IMAGE: Thay URL Unsplash chuẩn không có ký tự lỗi Unicode -->
    <article class="food-card" data-food data-type="drink">
      <div class="food-image">
        <img src="https://images.unsplash.com/photo-1556679343-c7306c1976bc?auto=format&fit=crop&w=700&q=80" alt="Trà đào cam sả" loading="lazy">
        <span class="tag tag-refresh">Best Seller</span>
        <button class="add-btn" data-add="Trà đào cam sả" data-price="35000" title="Thêm vào giỏ hàng">+</button>
      </div>
      <div class="food-info">
        <div class="food-head-row">
          <h3>Trà đào cam sả</h3>
          <span class="rating">★ 4.9</span>
        </div>
        <p>Hương trà ướp lạnh thanh mát, đào miếng giòn ngọt hòa quyện tinh dầu cam vàng và sả tươi.</p>
        <div class="food-foot-row">
          <b class="price">35.000đ</b>
          <button class="btn btn-add-text" data-add="Trà đào cam sả" data-price="35000">+ Chọn món</button>
        </div>
      </div>
    </article>

    <article class="food-card" data-food data-type="main">
      <div class="food-image">
        <img src="https://images.unsplash.com/photo-1544025162-d76694265947?auto=format&fit=crop&w=700&q=80" alt="Bò lúc lắc" loading="lazy">
        <button class="add-btn" data-add="Bò lúc lắc" data-price="120000" title="Thêm vào giỏ hàng">+</button>
      </div>
      <div class="food-info">
        <div class="food-head-row">
          <h3>Bò lúc lắc hạt tiêu</h3>
          <span class="rating">★ 4.8</span>
        </div>
        <p>Thịt bò Úc áp chảo xì xèo xém cạnh, sốt tiêu đen đậm đà ăn kèm khoai tây chiên giòn.</p>
        <div class="food-foot-row">
          <b class="price">120.000đ</b>
          <button class="btn btn-add-text" data-add="Bò lúc lắc" data-price="120000">+ Chọn món</button>
        </div>
      </div>
    </article>

    <article class="food-card" data-food data-type="dessert">
      <div class="food-image">
        <img src="https://images.unsplash.com/photo-1488477181946-6428a0291777?auto=format&fit=crop&w=700&q=80" alt="Sữa chua trái cây" loading="lazy">
        <button class="add-btn" data-add="Sữa chua trái cây" data-price="40000" title="Thêm vào giỏ hàng">+</button>
      </div>
      <div class="food-info">
        <div class="food-head-row">
          <h3>Sữa chua trái cây</h3>
          <span class="rating">★ 4.7</span>
        </div>
        <p>Sữa chua Hy Lạp sánh mịn lên men tự nhiên, kèm dâu tây tươi và sốt việt quất ngọt dịu.</p>
        <div class="food-foot-row">
          <b class="price">40.000đ</b>
          <button class="btn btn-add-text" data-add="Sữa chua trái cây" data-price="40000">+ Chọn món</button>
        </div>
      </div>
    </article>
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

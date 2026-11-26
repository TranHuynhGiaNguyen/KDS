<?php
require_once __DIR__ . '/../includes/restaurant.php';
$restaurant = getRestaurantInfo();
$featuredDishes = array_slice(getAllDishes('', 'visible'), 0, 6);
$menuCategories = getAllCategories(true);
?>

<div class="homepage" id="top">
  <section class="home-hero" aria-label="Giới thiệu SmartDine">
    <div class="home-hero-overlay"></div>
    <div class="home-hero-content">
      <span class="home-kicker">Trải nghiệm ẩm thực hiện đại</span>
      <h1>Hương vị quen thuộc<br><em>trong không gian trọn vẹn</em></h1>
      <p>Đặt bàn dễ dàng, thưởng thức thực đơn đa dạng và tận hưởng từng khoảnh khắc cùng người thân yêu.</p>
      <div class="home-hero-actions">
        <a class="home-btn home-btn-primary" href="index.php?page=reservation">Đặt bàn ngay</a>
        <a class="home-btn home-btn-light" href="#menu">Xem thực đơn</a>
      </div>
    </div>
    <div class="home-hero-note">
      <span>OPEN DAILY</span>
      <strong>10:00 – 22:00</strong>
    </div>
  </section>

  <section class="home-about" id="gioi-thieu">
    <div class="home-about-image">
      <img src="https://images.unsplash.com/photo-1414235077428-338989a2e8c0?auto=format&fit=crop&w=1200&q=85" alt="Không gian nhà hàng SmartDine">
      <div class="home-about-badge"><strong>12+</strong><span>Năm phục vụ</span></div>
    </div>
    <div class="home-about-copy">
      <span class="home-section-kicker">Về SmartDine</span>
      <h2>Nơi ẩm thực gặp gỡ<br>những phút giây sẻ chia</h2>
      <p><?= htmlspecialchars($restaurant['name']) ?> mang đến trải nghiệm ẩm thực cân bằng giữa hương vị truyền thống và phong cách bếp hiện đại.</p>
      <p>Chúng tôi chọn nguyên liệu tươi trong ngày, chế biến cẩn trọng và phục vụ với sự tận tâm trong từng chi tiết.</p>
      <div class="home-about-stats">
        <div><strong>100+</strong><span>Món ngon</span></div>
        <div><strong>4.9★</strong><span>Đánh giá</span></div>
        <div><strong>12+</strong><span>Năm kinh nghiệm</span></div>
      </div>
    </div>
  </section>

  <section class="home-featured" id="mon-noi-bat">
    <div class="home-section-heading">
      <div>
        <span class="home-section-kicker">Được yêu thích nhất</span>
        <h2>Món nổi bật</h2>
      </div>
      <a class="home-text-link" href="index.php?page=menu">Xem toàn bộ thực đơn <span>→</span></a>
    </div>

    <?php if (empty($featuredDishes)): ?>
      <div class="home-empty">Thực đơn đang được cập nhật. Vui lòng quay lại sau.</div>
    <?php else: ?>
      <div class="home-dish-grid">
        <?php foreach ($featuredDishes as $index => $dish):
          $imgUrl = getDishImageUrl($dish['image']);
          $isOut = $dish['status'] === 'out_of_stock';
        ?>
          <article class="home-dish-card">
            <div class="home-dish-image">
              <img src="<?= $imgUrl ?>" alt="<?= htmlspecialchars($dish['name']) ?>" loading="lazy" onerror="this.src='https://images.unsplash.com/photo-1546069901-ba9599a7e63c?auto=format&fit=crop&w=700&q=80'">
              <?php if (!empty($dish['tag'])): ?><span><?= htmlspecialchars($dish['tag']) ?></span><?php endif; ?>
            </div>
            <div class="home-dish-body">
              <small><?= htmlspecialchars($dish['category_name']) ?></small>
              <h3><?= htmlspecialchars($dish['name']) ?></h3>
              <p><?= htmlspecialchars($dish['description'] ?: 'Nguyên liệu tươi, chế biến ngay khi bạn gọi món.') ?></p>
              <div class="home-dish-foot">
                <strong><?= formatPrice($dish['price']) ?></strong>
                <?php if (!$isOut): ?>
                  <button type="button" class="home-add-btn" data-add="<?= htmlspecialchars($dish['name']) ?>" data-price="<?= (int)$dish['price'] ?>" title="Thêm món vào giỏ">+</button>
                <?php else: ?>
                  <span class="home-out-stock">Tạm hết</span>
                <?php endif; ?>
              </div>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <section class="home-menu" id="menu">
    <div class="home-section-heading home-menu-heading">
      <div>
        <span class="home-section-kicker">Thực đơn công khai</span>
        <h2>Chọn món yêu thích</h2>
      </div>
      <a class="home-btn home-btn-outline" href="index.php?page=menu">Xem tất cả món</a>
    </div>
    <div class="home-category-grid">
      <?php if (empty($menuCategories)): ?>
        <div class="home-empty">Chưa có danh mục món ăn.</div>
      <?php else: ?>
        <?php foreach ($menuCategories as $category): ?>
          <a class="home-category" href="index.php?page=menu#category-bar">
            <span><?= htmlspecialchars($category['icon']) ?></span>
            <div><strong><?= htmlspecialchars($category['name']) ?></strong><small><?= (int)$category['dish_count'] ?> món</small></div>
            <b>→</b>
          </a>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </section>

  <section class="home-reservation" id="dat-ban">
    <div>
      <span class="home-section-kicker">Đặt bàn</span>
      <h2>Bạn đã sẵn sàng<br>cho bữa tiệc ngon miệng?</h2>
    </div>
    <p>Chọn ngày, giờ và số lượng khách. SmartDine sẽ chuẩn bị không gian phù hợp nhất cho bạn.</p>
    <a class="home-btn home-btn-primary" href="index.php?page=reservation">Đặt bàn ngay</a>
  </section>

  <footer class="home-footer" id="lien-he">
    <div class="home-footer-brand">
      <div class="home-footer-logo"><span>SD</span><strong>SmartDine</strong></div>
      <p>Không gian ấm cúng, hương vị tinh tế và những phút giây đáng nhớ.</p>
    </div>
    <div>
      <h3>Liên hệ</h3>
      <p><?= htmlspecialchars($restaurant['address']) ?></p>
      <p><a href="tel:<?= preg_replace('/[^0-9+]/', '', $restaurant['phone']) ?>"><?= htmlspecialchars($restaurant['phone']) ?></a></p>
    </div>
    <div>
      <h3>Giờ mở cửa</h3>
      <p>Thứ 2 – Chủ nhật</p>
      <p>10:00 – 22:00</p>
    </div>
    <div>
      <h3>Khám phá</h3>
      <a href="index.php?page=menu">Thực đơn</a>
      <a href="index.php?page=reservation">Đặt bàn</a>
    </div>
  </footer>
</div>

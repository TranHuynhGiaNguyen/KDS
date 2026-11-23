<?php
$restaurant = require __DIR__ . '/../config/restaurant.php';
$homeDishes = array_slice(getAllDishes('', 'active', ''), 0, 3);
?>

<div class="home-page">
  <section class="home-hero">
    <div class="home-hero-copy">
      <div class="home-kicker">
        <span class="home-kicker-mark" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v2"></path><path d="M5.64 5.64 7.05 7.05"></path><path d="M3 12h2"></path><path d="M19 12h2"></path><path d="m16.95 7.05 1.41-1.41"></path><circle cx="12" cy="14" r="7"></circle><path d="M9 14h6M10 17h4"></path></svg>
        </span>
        <span class="eyebrow">ẨM THỰC TINH TẾ · KHOẢNH KHẮC TRỌN VẸN</span>
      </div>
      <h1>Hẹn nhau bên<br>bàn ăn <em>ấm áp.</em></h1>
      <p>Nguyên liệu tươi ngon, món ăn chỉn chu và không gian thân tình cho những cuộc gặp gỡ đáng nhớ.</p>
      <div class="home-hero-actions">
        <a class="btn" href="index.php?page=reservation">Đặt bàn ngay <span aria-hidden="true">→</span></a>
        <a class="btn btn-outline" href="index.php?page=menu">Khám phá thực đơn</a>
      </div>
      <div class="home-hero-note">
        <span class="live-dot" aria-hidden="true"></span>
        <span>Đặt bàn trực tuyến nhanh chóng, không cần tài khoản</span>
      </div>
    </div>

    <div class="home-hero-visual" role="img" aria-label="Không gian dùng bữa ấm cúng tại SmartDine">
      <div class="home-visual-topline">
        <span>SMARTDINE <i></i> NHÀ HÀNG</span>
        <span class="home-visual-pill"><b></b> MỞ CỬA HẰNG NGÀY</span>
      </div>
      <div class="home-visual-note">
        <span class="home-visual-note-icon" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 10h18"></path><path d="M5 10v9M19 10v9M3 19h18"></path><path d="M7 10a5 5 0 0 1 10 0"></path><path d="M12 3v2"></path></svg>
        </span>
        <span><b>Một chiếc bàn dành riêng cho bạn</b><small>Chọn giờ, khu vực và bàn phù hợp</small></span>
      </div>
      <div class="home-visual-caption">
        <span>HƯƠNG VỊ THÂN QUEN · KHÔNG GIAN THÂN TÌNH</span>
        <b>Gói trọn khoảnh khắc.</b>
      </div>
    </div>
  </section>

  <section class="home-benefits" aria-label="Điểm nổi bật">
    <article class="home-benefit-card">
      <span class="home-benefit-icon" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19h16"></path><path d="M6 19V9a6 6 0 0 1 12 0v10"></path><path d="M9 9h.01M15 9h.01M10 14h4"></path></svg>
      </span>
      <span><b>Món ngon mỗi ngày</b><small>Thực đơn phong phú, dễ dàng khám phá</small></span>
      <span class="home-benefit-arrow" aria-hidden="true">↗</span>
    </article>
    <article class="home-benefit-card">
      <span class="home-benefit-icon" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="16" rx="2"></rect><path d="M16 3v4M8 3v4M3 11h18M8 15h3"></path></svg>
      </span>
      <span><b>Đặt bàn thật tiện</b><small>Chọn thời gian và bàn còn trống trực tuyến</small></span>
      <span class="home-benefit-arrow" aria-hidden="true">↗</span>
    </article>
    <article class="home-benefit-card">
      <span class="home-benefit-icon" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21s7-4.4 7-11V5l-7-3-7 3v5c0 6.6 7 11 7 11Z"></path><path d="m9 12 2 2 4-4"></path></svg>
      </span>
      <span><b>Không cần tài khoản</b><small>Đặt bàn nhanh gọn, không cần đăng nhập</small></span>
      <span class="home-benefit-arrow" aria-hidden="true">↗</span>
    </article>
  </section>

  <section class="home-about" id="about">
    <div class="home-about-copy">
      <span class="eyebrow">CÂU CHUYỆN SMARTDINE</span>
      <h2>Mỗi món ăn là một lời mời sẻ chia</h2>
      <p>SmartDine kết hợp hương vị thân quen với cách chế biến hiện đại. Chúng tôi chăm chút từ nguyên liệu, cách phục vụ đến từng chiếc bàn để bạn luôn cảm thấy thoải mái như ở nhà.</p>
      <a class="home-about-link" href="index.php?page=menu">Khám phá thực đơn <span aria-hidden="true">→</span></a>
    </div>
    <div class="home-about-points">
      <article><span>01</span><div><b>Nguyên liệu tươi</b><small>Chuẩn bị mỗi ngày</small></div></article>
      <article><span>02</span><div><b>Không gian đa dạng</b><small>Trong nhà, sân vườn, phòng riêng</small></div></article>
      <article><span>03</span><div><b>Phục vụ tận tâm</b><small>Sẵn sàng cho những dịp gặp gỡ</small></div></article>
    </div>
  </section>

  <section class="home-featured" id="featured">
    <div class="section-head home-section-head">
      <div>
        <span class="eyebrow">ĐƯỢC YÊU THÍCH</span>
        <h2>Món ngon nổi bật</h2>
        <p>Gợi ý vài món ngon để cuộc hẹn thêm trọn vẹn.</p>
      </div>
      <a class="home-menu-link" href="index.php?page=menu">Xem toàn bộ thực đơn <span aria-hidden="true">→</span></a>
    </div>
    <div class="home-dish-grid">
      <?php foreach ($homeDishes as $index => $dish): ?>
        <article class="home-dish-card" style="--dish-index: <?= (int) $index ?>">
          <a class="home-dish-image" href="index.php?page=menu" aria-label="Khám phá món <?= htmlspecialchars($dish['name'], ENT_QUOTES, 'UTF-8') ?>">
            <img src="<?= getDishImageUrl($dish['image']) ?>" alt="<?= htmlspecialchars($dish['name'], ENT_QUOTES, 'UTF-8') ?>" loading="lazy">
            <span class="home-dish-category"><?= htmlspecialchars($dish['category_name'] ?: 'Món ngon', ENT_QUOTES, 'UTF-8') ?></span>
            <span class="home-dish-image-arrow" aria-hidden="true">↗</span>
          </a>
          <div class="home-dish-content">
            <div class="home-dish-title">
              <h3><?= htmlspecialchars($dish['name'], ENT_QUOTES, 'UTF-8') ?></h3>
              <span aria-hidden="true">✦</span>
            </div>
            <p><?= htmlspecialchars($dish['description'] ?: 'Món ngon được chuẩn bị từ nguyên liệu tươi mỗi ngày.', ENT_QUOTES, 'UTF-8') ?></p>
            <div class="home-dish-footer">
              <strong><?= formatPrice($dish['price']) ?></strong>
              <a href="index.php?page=menu">Xem món <span aria-hidden="true">→</span></a>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
      <?php if (!$homeDishes): ?>
        <p class="muted">Thực đơn nổi bật đang được cập nhật. Mời bạn xem <a class="text-link" href="index.php?page=menu">thực đơn công khai</a>.</p>
      <?php endif; ?>
    </div>
  </section>

  <section class="home-contact" id="contact">
    <div class="home-contact-copy">
      <span class="eyebrow">CHÚNG TÔI Ở ĐÂY</span>
      <h2>Cuộc hẹn tiếp theo, mình gặp nhau nhé?</h2>
      <div class="home-contact-detail">
        <span class="home-contact-icon" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 5-8 12-8 12S4 15 4 10a8 8 0 1 1 16 0Z"></path><circle cx="12" cy="10" r="2.5"></circle></svg>
        </span>
        <span><?= htmlspecialchars($restaurant['address'], ENT_QUOTES, 'UTF-8') ?></span>
      </div>
      <a class="home-phone" href="tel:<?= preg_replace('/\D+/', '', $restaurant['phone']) ?>"><?= htmlspecialchars($restaurant['phone'], ENT_QUOTES, 'UTF-8') ?></a>
    </div>
    <div class="home-contact-actions">
      <a class="btn" href="index.php?page=reservation">Chọn bàn phù hợp <span aria-hidden="true">→</span></a>
      <span class="home-hours"><i aria-hidden="true"></i> Phục vụ mỗi ngày · 10:00 – 22:00</span>
    </div>
  </section>
</div>

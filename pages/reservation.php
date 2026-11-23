<?php
require_once __DIR__ . '/../includes/tables.php';

if (empty($_SESSION['reservation_csrf'])) {
    $_SESSION['reservation_csrf'] = bin2hex(random_bytes(32));
}
$reservationAreas = getAllAreas('active');
$reservationNow = new DateTime('now', new DateTimeZone('Asia/Ho_Chi_Minh'));
?>

<section class="page-heading reservation-heading">
  <span class="eyebrow">HẸN GẶP BẠN TẠI SMARTDINE</span>
  <h1>Đặt bàn trực tuyến</h1>
  <p>Chọn thời gian, khu vực và bàn còn trống phù hợp với nhóm của bạn.</p>
</section>

<div class="reservation-layout">
  <section class="reservation-art">
    <img src="https://images.unsplash.com/photo-1414235077428-338989a2e8c0?auto=format&fit=crop&w=1000&q=85" alt="Không gian nhà hàng" loading="lazy">
    <div class="reservation-art-copy">
      <h2>Một chiếc bàn,<br>nhiều khoảnh khắc đẹp.</h2>
      <p>Chúng tôi giữ bàn trong 2 giờ để bạn thoải mái tận hưởng bữa ăn.</p>
    </div>
  </section>

  <form class="panel reservation-form" id="reservation-form" data-api="api/reservations.php">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['reservation_csrf'], ENT_QUOTES, 'UTF-8') ?>">
    <h2>Thông tin đặt bàn</h2>
    <div class="form-grid">
      <label>Ngày đến<input type="date" name="date" id="reservation-date" min="<?= $reservationNow->format('Y-m-d') ?>" data-today="<?= $reservationNow->format('Y-m-d') ?>" data-current-time="<?= $reservationNow->format('H:i') ?>" required></label>
      <label>Giờ đến
        <select name="time" id="reservation-time" required>
          <option value="">Chọn giờ</option>
          <?php for ($minutes = 600; $minutes <= 1290; $minutes += 30): ?>
            <?php $time = sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60); ?>
            <option value="<?= $time ?>"><?= $time ?></option>
          <?php endfor; ?>
        </select>
      </label>
      <label>Số người
        <select name="guests" id="reservation-guests" required>
          <?php for ($guests = 1; $guests <= 12; $guests++): ?>
            <option value="<?= $guests ?>" <?= $guests === 2 ? 'selected' : '' ?>><?= $guests ?> người</option>
          <?php endfor; ?>
        </select>
      </label>
      <label>Khu vực
        <select name="area" id="reservation-area" required>
          <option value="">Tất cả khu vực</option>
          <?php foreach ($reservationAreas as $area): ?>
            <option value="<?= htmlspecialchars($area['code'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($area['name'], ENT_QUOTES, 'UTF-8') ?></option>
          <?php endforeach; ?>
        </select>
      </label>
    </div>

    <section class="reservation-table-picker" aria-labelledby="reservation-tables-title">
      <div class="reservation-picker-heading">
        <div><h3 id="reservation-tables-title">Chọn bàn</h3><p class="muted">Bàn được kiểm tra theo khung giờ bạn chọn.</p></div>
        <span class="table-legend"><i></i> Còn trống</span>
      </div>
      <div class="reservation-tables" id="reservation-tables" aria-live="polite">
        <p class="reservation-picker-empty">Chọn ngày và giờ để xem bàn còn trống.</p>
      </div>
    </section>

    <div class="form-grid">
      <label>Họ và tên<input name="name" autocomplete="name" maxlength="100" required placeholder="Tên người đặt"></label>
      <label>Số điện thoại<input name="phone" type="tel" autocomplete="tel" maxlength="20" required placeholder="09xx xxx xxx"></label>
    </div>
    <label>Ghi chú <span class="muted">(không bắt buộc)</span><textarea name="note" maxlength="500" placeholder="Yêu cầu đặc biệt, dị ứng thực phẩm..."></textarea></label>
    <p class="reservation-message" id="reservation-message" role="status" aria-live="polite"></p>
    <button class="btn btn-block" id="reservation-submit" type="submit" disabled>Xác nhận đặt bàn →</button>
  </form>
</div>

<section class="reservation-history" id="history">
  <?php include __DIR__ . '/reservation-history.php'; ?>
</section>

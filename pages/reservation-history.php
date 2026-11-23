<?php
if (empty($_SESSION['reservation_csrf'])) {
    $_SESSION['reservation_csrf'] = bin2hex(random_bytes(32));
}
?>
<div class="reservation-history-heading">
  <div><span class="eyebrow">QUẢN LÝ LỊCH HẸN</span><h2>Lịch đặt bàn của bạn</h2><p class="muted">Nhập số điện thoại và mã xác nhận để xem lịch sử hoặc hủy lịch sắp tới.</p></div>
</div>
<form class="reservation-history-form" id="reservation-history-form" data-api="api/reservations.php">
  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['reservation_csrf'], ENT_QUOTES, 'UTF-8') ?>">
  <label>Số điện thoại<input name="phone" type="tel" autocomplete="tel" required placeholder="Số điện thoại đã đặt bàn"></label>
  <label>Mã xác nhận<input name="reference" required placeholder="Ví dụ: RSV-A1B2C3D4E5F60718"></label>
  <button class="btn btn-outline" type="submit">Tra cứu lịch đặt</button>
</form>
<p class="reservation-message" id="reservation-history-message" role="status" aria-live="polite"></p>
<div class="reservation-history-results" id="reservation-history-results" aria-live="polite"></div>

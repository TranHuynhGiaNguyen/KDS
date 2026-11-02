<section class="page-heading">
  <span class="eyebrow">XÁC NHẬN GỌI MÓN</span>
  <h1>Giỏ hàng của bạn</h1>
  <p>Kiểm tra danh sách món ăn, số lượng và thêm ghi chú đặc biệt trước khi gửi phiếu đến Bếp (KDS)</p>
</section>

<div class="two-col cart-layout">
  <section class="panel">
    <div class="panel-title">
      <div>
        <h2>Món đã chọn <span class="muted font-normal" id="cart-items-count">(0)</span></h2>
        <span class="table-chip-inline">Đang gọi tại: <b>Bàn B05</b></span>
      </div>
      <a class="btn btn-small btn-outline" href="index.php?page=menu">+ Thêm món khác</a>
    </div>

    <div id="cart-lines">
      <div class="empty-state">
        <div class="empty-state-icon">🛒</div>
        <h3>Giỏ hàng đang trống</h3>
        <p class="muted">Quý khách chưa chọn món nào từ thực đơn nhà hàng.</p>
        <div class="empty-state-actions">
          <a class="btn" href="index.php?page=menu">Khám phá thực đơn ngay →</a>
          <button class="btn btn-outline" id="btn-add-sample-cart" title="Tự động thêm vài món để trải nghiệm nhanh">Thêm món mẫu để test</button>
        </div>
      </div>
    </div>

    <div class="cart-notes-group">
      <label class="field-label">
        <div class="label-row">
          <span>Ghi chú chung cho nhà bếp (KDS)</span>
          <small class="muted">Không bắt buộc</small>
        </div>
        <textarea id="cart-order-note" placeholder="Ví dụ: Bàn có trẻ nhỏ, xin chế biến ít cay, mang nước trước..."></textarea>
      </label>
    </div>
  </section>

  <aside class="panel order-summary">
    <h2>Tóm tắt chi phí</h2>
    <div class="summary-row">
      <span>Tạm tính tiền món</span>
      <b id="cart-subtotal">0đ</b>
    </div>
    <div class="summary-row">
      <span>Phí phục vụ & bảo vệ môi trường (5%)</span>
      <b id="service-fee">0đ</b>
    </div>
    <div class="summary-row total">
      <span>Tổng thanh toán</span>
      <b id="cart-grand-total">0đ</b>
    </div>

    <div class="cart-confirm-box">
      <a class="btn btn-block btn-confirm-cart" id="btn-confirm-order" href="index.php?page=tracking">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>
        <span>Gửi đơn sang Nhà bếp</span>
      </a>
      <p class="muted center font-xs">Sau khi xác nhận, đơn sẽ ngay lập tức xuất hiện trên Màn hình bếp (KDS)</p>
    </div>
  </aside>
</div>

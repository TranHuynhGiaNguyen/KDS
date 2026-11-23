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

    <!-- MỤC THEO DÕI TIẾN ĐỘ & THỜI GIAN RA MÓN (BẾP KDS) -->
    <div class="cart-kds-tracker-box">
      <div class="tracker-header">
        <div class="tracker-title-group">
          <span class="tracker-badge">⏱ Bếp KDS Thông Minh</span>
          <h3>Dự báo thời gian ra món</h3>
        </div>
        <div class="tracker-kitchen-load">
          <span class="kitchen-status-dot"></span>
          <span class="kitchen-status-text">Bếp sẵn sàng · Tải nhẹ</span>
        </div>
      </div>

      <div class="tracker-estimate-card">
        <div class="estimate-time-val">
          <span class="estimate-clock-icon">🕒</span>
          <div>
            <span class="estimate-label">Thời gian dự kiến ra bàn</span>
            <b class="estimate-mins" id="cart-estimated-time">10 - 15 phút</b>
          </div>
        </div>
        <p class="estimate-sub">Được tính toán theo thời gian thực dựa trên số món trong giỏ và tốc độ chế biến hiện tại của bếp KDS.</p>
      </div>

      <div class="tracker-flow-steps">
        <div class="flow-step done">
          <span class="flow-num">1</span>
          <div class="flow-info">
            <b>Chọn món & Gửi bếp</b>
            <small>Đơn tự động chuyển đến màn hình KDS</small>
          </div>
        </div>
        <div class="flow-step active">
          <span class="flow-num">2</span>
          <div class="flow-info">
            <b>Bếp chế biến</b>
            <small>Chia line theo trạm: Chảo, Nướng, Bar</small>
          </div>
        </div>
        <div class="flow-step">
          <span class="flow-num">3</span>
          <div class="flow-info">
            <b>Phục vụ tại bàn</b>
            <small>Nhân viên bưng món tới Bàn B05</small>
          </div>
        </div>
      </div>
    </div>
  </section>

  <aside class="panel order-summary">
    <h2>Tóm tắt chi phí</h2>

    <!-- Khối nhập mã ưu đãi / Voucher cho thực khách -->
    <div style="background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 10px; padding: 12px; margin-bottom: 14px;">
      <label style="display: block; font-size: 13px; font-weight: 600; color: var(--ink); margin-bottom: 6px;">
        Mã ưu đãi / Voucher
      </label>
      <div style="display: flex; gap: 6px;">
        <input type="text" id="cart-voucher-input" placeholder="Mã giảm (VD: GIAM50K)" style="flex: 1; padding: 7px 10px; font-size: 13px; text-transform: uppercase; border: 1px solid #cbd5e1; border-radius: 6px;">
        <button type="button" class="btn btn-outline" id="btn-apply-cart-voucher" style="padding: 0 12px; font-size: 13px; white-space: nowrap;">Áp dụng</button>
      </div>
      <div id="cart-voucher-status" style="display: none; font-size: 12px; margin-top: 6px;"></div>
    </div>

    <div class="summary-row">
      <span>Tạm tính tiền món</span>
      <b id="cart-subtotal">0đ</b>
    </div>
    <div class="summary-row" id="cart-voucher-row" style="display: none; color: #16a34a;">
      <span>Giảm giá (<span id="cart-voucher-code-label"></span>)</span>
      <b id="cart-voucher-discount">-0đ</b>
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

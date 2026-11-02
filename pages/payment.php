<section class="page-heading">
  <div class="back-nav-row">
    <a class="back-link" href="index.php?page=table-order&table=B05">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
      <span>Quay lại chi tiết đơn bàn</span>
    </a>
  </div>
  <span class="eyebrow">THANH TOÁN HÓA ĐƠN</span>
  <h1>Thanh toán bữa ăn — Bàn B05</h1>
  <p>Kiểm tra lại toàn bộ hóa đơn và lựa chọn phương thức thanh toán thuận tiện nhất</p>
</section>

<div class="two-col payment-layout">
  <section class="panel">
    <div class="panel-title">
      <div>
        <h2>Chi tiết hóa đơn VAT</h2>
        <p class="muted">Mã phiếu: #BILL-20261102-05</p>
      </div>
      <span class="status status-pending">Chờ thanh toán</span>
    </div>

    <div class="bill-line-items">
      <div class="line-item">
        <div>
          <b>Cơm gà Hải Nam</b>
          <small>1 phần × 75.000đ</small>
        </div>
        <b>75.000đ</b>
      </div>
      <div class="line-item">
        <div>
          <b>Mì xào bò</b>
          <small>1 phần × 85.000đ</small>
        </div>
        <b>85.000đ</b>
      </div>
      <div class="line-item">
        <div>
          <b>Trà đào cam sả</b>
          <small>1 ly × 35.000đ</small>
        </div>
        <b>35.000đ</b>
      </div>
    </div>

    <div class="bill-summary-box">
      <div class="summary-row"><span>Tạm tính tiền món</span><b>195.000đ</b></div>
      <div class="summary-row"><span>Phí phục vụ & Thuế GTGT (5%)</span><b>9.750đ</b></div>
      <div class="summary-row total"><span>Tổng thanh toán</span><b class="highlight-price">204.750đ</b></div>
    </div>
  </section>

  <form class="panel payment-methods-panel" data-demo-form>
    <h2>Phương thức thanh toán</h2>
    
    <label class="payment-option">
      <input type="radio" name="pay" value="qr" checked>
      <div class="pay-content">
        <div class="pay-title-row">
          <b>Chuyển khoản QR (VietQR / MoMo / VNPay)</b>
          <span class="badge-recommend">Khuyên dùng</span>
        </div>
        <small class="muted">Quét mã thanh toán tức thì không cần tiền mặt</small>
        
        <div class="qr-mock-box">
          <div class="qr-code-art">
            <svg width="120" height="120" viewBox="0 0 24 24" fill="currentColor"><path d="M2 2h8v8H2zm2 2v4h4V4zm10-2h8v8h-8zm2 2v4h4V4zM2 14h8v8H2zm2 2v4h4v-4zm14-2h4v2h-4zm-4 0h2v4h-2zm2 4h2v4h-2zm2 0h2v2h-2zm0 2h2v2h-2zm-6-2h2v4h-2zm4-6h4v2h-4z"/></svg>
          </div>
          <div class="qr-info-meta">
            <b>SMARTDINE RESTAURANT</b>
            <span>STK: 190382910398</span>
            <span>Ngân hàng Quân Đội (MB Bank)</span>
            <span class="qr-amount">Số tiền: <b>204.750đ</b></span>
          </div>
        </div>
      </div>
    </label>

    <label class="payment-option">
      <input type="radio" name="pay" value="cash">
      <div class="pay-content">
        <div class="pay-title-row">
          <b>Tiền mặt / Thẻ POS tại quầy</b>
        </div>
        <small class="muted">Nhân viên sẽ mang máy POS hoặc nhận tiền mặt tại bàn</small>
      </div>
    </label>

    <button class="btn btn-block btn-pay-now" type="submit">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>
      <span>Xác nhận đã thanh toán 204.750đ</span>
    </button>
    <p class="muted center font-xs">Giao diện mẫu dành cho hệ thống demo nhà hàng SmartDine</p>
  </form>
</div>

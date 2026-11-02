<section class="page-heading">
  <span class="eyebrow">THEO DÕI ĐƠN HÀNG THỜI GIAN THỰC</span>
  <h1>Tiến độ phục vụ món ăn</h1>
  <p>Hệ thống tự động đồng bộ trạng thái trực tiếp từ Màn hình bếp (KDS) tại bàn B05</p>
</section>

<div class="panel tracking-panel">
  <div class="tracking-top">
    <div>
      <span class="muted">Mã đơn gọi món</span>
      <h2>#OD1250 · Bàn B05</h2>
    </div>
    <div class="tracking-status-badge">
      <span class="live-dot pulse"></span>
      <span class="status status-cooking" id="current-order-status">Đang chế biến tại bếp</span>
    </div>
  </div>

  <div class="stepper">
    <div class="step done" id="step-1">
      <span class="step-icon">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"></polyline></svg>
      </span>
      <b>Đã nhận đơn</b>
      <small>18:35</small>
    </div>
    <div class="step current" id="step-2">
      <span class="step-icon">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M6 13.8V4a2 2 0 1 1 4 0v9.8"></path><path d="M14 13.8V4a2 2 0 1 1 4 0v9.8"></path><path d="M3 14h18a2 2 0 0 1 2 2v1a4 4 0 0 1-4 4H5a4 4 0 0 1-4-4v-1a2 2 0 0 1 2-2z"></path></svg>
      </span>
      <b>Đang chế biến</b>
      <small>Bếp đang nấu</small>
    </div>
    <div class="step" id="step-3">
      <span class="step-icon">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8h1a4 4 0 0 1 0 8h-1"></path><path d="M2 8h16v9a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V8z"></path></svg>
      </span>
      <b>Ra đồ phục vụ</b>
      <small>Nhân viên bưng ra</small>
    </div>
    <div class="step" id="step-4">
      <span class="step-icon">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 14 14"></polyline></svg>
      </span>
      <b>Hoàn thành</b>
      <small>Chúc ngon miệng!</small>
    </div>
  </div>

  <div class="tracking-section-title">
    <h3>Chi tiết món trong đơn</h3>
    <button class="btn btn-small btn-outline" id="simulate-step-btn" title="Thử nghiệm chuyển trạng thái tiến độ">
      <span>▶ Mô phỏng bước tiếp</span>
    </button>
  </div>

  <div class="tracking-food-items">
    <div class="line-item">
      <div class="line-name-wrap">
        <b>Cơm gà Hải Nam</b>
        <small>1 phần · 75.000đ · Thịt đùi mềm</small>
      </div>
      <span class="status status-cooking">Bếp đang làm</span>
    </div>

    <div class="line-item">
      <div class="line-name-wrap">
        <b>Mì xào bò</b>
        <small>1 phần · 85.000đ · Ít dầu mỡ</small>
      </div>
      <span class="status status-cooking">Bếp đang làm</span>
    </div>

    <div class="line-item">
      <div class="line-name-wrap">
        <b>Trà đào cam sả</b>
        <small>2 ly · 70.000đ · 70% đường, 50% đá</small>
      </div>
      <span class="status status-ready">Đã ra bàn</span>
    </div>
  </div>

  <div class="help-box">
    <div>
      <b>Bạn cần hỗ trợ thêm món hoặc yêu cầu gia vị?</b>
      <p class="muted">Nhân viên trực bàn B05 luôn sẵn sàng phục vụ quý khách.</p>
    </div>
    <button class="btn btn-outline" data-toast="Nhân viên phục vụ bàn B05 đang đến ngay!">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
      <span>Gọi nhân viên</span>
    </button>
  </div>
</div>

<div class="dash-header">
  <div>
    <span class="eyebrow">SMARTDINE MANAGEMENT</span>
    <h1>Tổng quan Báo cáo & Quản trị</h1>
    <p>Chào buổi sáng! Tình hình kinh doanh và tiến độ chế biến nhà hàng hôm nay.</p>
  </div>
  <div class="filter-row">
    <label class="date-picker-wrap">
      <input type="date" value="2026-11-02">
    </label>
    <a class="btn btn-outline" href="index.php?page=users">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
      <span>Quản lý nhân viên</span>
    </a>
    <a class="btn btn-outline" href="index.php?page=kitchen">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 13.8V4a2 2 0 1 1 4 0v9.8"></path><path d="M14 13.8V4a2 2 0 1 1 4 0v9.8"></path><path d="M3 14h18a2 2 0 0 1 2 2v1a4 4 0 0 1-4 4H5a4 4 0 0 1-4-4v-1a2 2 0 0 1 2-2z"></path></svg>
      <span>Màn hình bếp KDS</span>
    </a>
    <button class="btn btn-primary-tool" data-toast="Đã xuất tệp báo cáo doanh thu PDF">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
      <span>Xuất báo cáo</span>
    </button>
  </div>
</div>

<div class="stat-grid admin-stats">
  <div class="stat-card">
    <div class="stat-card-head">
      <span>Doanh thu hôm nay</span>
      <span class="stat-badge positive">+12.5%</span>
    </div>
    <b>25.430.000đ</b>
    <small class="positive">↗ Tăng 2.800.000đ so với hôm qua</small>
  </div>

  <div class="stat-card">
    <div class="stat-card-head">
      <span>Tổng số lượt gọi món</span>
      <span class="stat-badge positive">+8.2%</span>
    </div>
    <b>235 đơn</b>
    <small class="positive">↗ Trung bình 108.000đ/đơn</small>
  </div>

  <div class="stat-card">
    <div class="stat-card-head">
      <span>Lượt khách dùng bữa</span>
      <span class="stat-badge positive">+5.1%</span>
    </div>
    <b>186 khách</b>
    <small class="positive">↗ Thời gian ăn trung bình: 52 phút</small>
  </div>

  <div class="stat-card">
    <div class="stat-card-head">
      <span>Công suất phục vụ bàn</span>
      <span class="stat-badge neutral">37.5%</span>
    </div>
    <b>12 / 32 bàn</b>
    <small>Còn trống 20 bàn sẵn sàng</small>
  </div>
</div>

<div class="admin-grid">
  <section class="panel chart-panel" id="reports">
    <div class="panel-title">
      <div>
        <h2>Doanh thu theo khung giờ</h2>
        <p class="muted">Rê chuột vào từng cột để xem chi tiết doanh số</p>
      </div>
      <select class="period-select">
        <option>Hôm nay (2/11)</option>
        <option>7 ngày qua</option>
        <option>Tháng này</option>
      </select>
    </div>

    <div class="bar-chart">
      <?php 
      $hourly = [
        ['8h', 22, '780.000đ'],
        ['9h', 34, '1.250.000đ'],
        ['10h', 28, '980.000đ'],
        ['11h', 68, '2.450.000đ'],
        ['12h', 95, '3.820.000đ'],
        ['13h', 75, '2.950.000đ'],
        ['14h', 45, '1.620.000đ'],
        ['15h', 38, '1.340.000đ'],
        ['16h', 55, '1.980.000đ'],
        ['17h', 78, '2.870.000đ'],
        ['18h', 100, '4.150.000đ'],
        ['19h', 92, '3.710.000đ'],
        ['20h', 65, '2.380.000đ'],
        ['21h', 35, '1.180.000đ']
      ];
      foreach ($hourly as $h): 
      ?>
        <div class="bar-wrap" title="<?=$h[0]?>: <?=$h[2]?>">
          <div class="bar-value-pop"><?=$h[2]?></div>
          <div class="bar" style="height:<?=$h[1]?>%"></div>
          <small><?=$h[0]?></small>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="panel top-dishes-panel">
    <div class="panel-title">
      <div>
        <h2>Top 5 món bán chạy nhất</h2>
        <p class="muted">Dựa trên số lượt order thực tế</p>
      </div>
      <span class="badge-pill">Hôm nay</span>
    </div>

    <div class="rank-list">
      <?php 
      $topDishes = [
        ['Cơm gà Hải Nam', 68, 100],
        ['Mì xào bò', 52, 76],
        ['Bò lúc lắc', 45, 66],
        ['Trà đào cam sả', 41, 60],
        ['Salad trộn đặc biệt', 32, 47]
      ];
      foreach ($topDishes as $i => $f): 
      ?>
        <div class="rank-row">
          <span class="rank-number"><?=$i+1?></span>
          <div class="rank-meta grow">
            <div class="rank-text-row">
              <b><?=$f[0]?></b>
              <span class="rank-qty"><b><?=$f[1]?></b> phần</span>
            </div>
            <div class="progress-track">
              <div class="progress-fill" style="width: <?=$f[2]?>%"></div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="panel" id="menu-management">
    <div class="panel-title">
      <div>
        <h2>Quản lý thực đơn & Giá bán</h2>
        <p class="muted">Điều chỉnh nhanh trạng thái bán hoặc giá món</p>
      </div>
      <button class="btn btn-small" data-toast="Mở hộp thoại tạo món mới demo">+ Thêm món mới</button>
    </div>

    <div class="menu-manage-list">
      <div class="line-item">
        <div class="manage-dish-info">
          <b>Cơm gà Hải Nam</b>
          <small>75.000đ · Phân loại: Món chính · Bếp nóng</small>
        </div>
        <span class="status status-ready">Đang mở bán</span>
      </div>
      <div class="line-item">
        <div class="manage-dish-info">
          <b>Mì xào bò</b>
          <small>85.000đ · Phân loại: Món chính · Bếp nóng</small>
        </div>
        <span class="status status-ready">Đang mở bán</span>
      </div>
      <div class="line-item">
        <div class="manage-dish-info">
          <b>Bò lúc lắc hạt tiêu</b>
          <small>120.000đ · Phân loại: Món chính · Bếp nóng</small>
        </div>
        <span class="status status-pending">Tạm hết tại bếp</span>
      </div>
      <div class="line-item">
        <div class="manage-dish-info">
          <b>Trà đào cam sả</b>
          <small>35.000đ · Phân loại: Đồ uống · Quầy bar</small>
        </div>
        <span class="status status-ready">Đang mở bán</span>
      </div>
    </div>
  </section>

  <section class="panel" id="inventory">
    <div class="panel-title">
      <div>
        <h2>Tồn kho nguyên liệu bếp</h2>
        <p class="muted">Cảnh báo nguyên liệu sắp hết để bếp trưởng nhập hàng</p>
      </div>
      <button class="btn btn-small btn-outline" data-toast="Mở phiếu nhập kho demo">+ Tạo phiếu nhập</button>
    </div>

    <div class="inventory-list">
      <?php 
      $inventory = [
        ['Thịt thăn bò Úc', '2.5 kg', 'low', 'Cần nhập thêm cho ca tối', 'Thực phẩm tươi'],
        ['Cá hồi Nauy', '8.0 kg', 'ok', 'Đủ phục vụ 2 ngày', 'Hải sản'],
        ['Phô mai Mozzarella', '0.4 kg', 'low', 'Sắp hết trong ca', 'Chế phẩm sữa'],
        ['Rau xà lách thủy canh', '6.2 kg', 'ok', 'Tươi mới nhập sáng nay', 'Rau củ']
      ];
      foreach ($inventory as $item): 
      ?>
        <div class="inventory-row">
          <div class="inventory-icon-box">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path></svg>
          </div>
          <div class="grow">
            <b><?=$item[0]?></b>
            <small><?=$item[1]?> còn lại · <?=$item[3]?></small>
          </div>
          <span class="status status-<?=$item[2] === 'low' ? 'urgent' : 'ready'?>">
            <?=$item[2] === 'low' ? '⚠ Sắp hết' : 'Ổn định'?>
          </span>
        </div>
      <?php endforeach; ?>
    </div>
  </section>
</div>

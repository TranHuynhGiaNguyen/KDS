<div class="dash-header">
  <div>
    <span class="eyebrow">SMARTDINE MANAGEMENT</span>
    <h1>Tổng quan Quản trị Nhà hàng</h1>
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
    <a class="btn btn-outline" href="index.php?page=dishes">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 2v7c0 1.1.9 2 2 2h4a2 2 0 0 0 2-2V2"></path><path d="M7 2v20"></path><path d="M21 15V2v0a5 5 0 0 0-5 5v6c0 1.1.9 2 2 2h3Zm0 0v7"></path></svg>
      <span>Quản lý thực đơn</span>
    </a>
    <a class="btn btn-primary-tool" href="index.php?page=kitchen">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 13.8V4a2 2 0 1 1 4 0v9.8"></path><path d="M14 13.8V4a2 2 0 1 1 4 0v9.8"></path><path d="M3 14h18a2 2 0 0 1 2 2v1a4 4 0 0 1-4 4H5a4 4 0 0 1-4-4v-1a2 2 0 0 1 2-2z"></path></svg>
      <span>Màn hình bếp KDS</span>
    </a>
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
  <?php 
  $adminDishesPreview = getAllDishes('', '', '', '');
  ?>
  <section class="panel" id="menu-management">
    <div class="panel-title">
      <div>
        <h2>Quản lý thực đơn & Giá bán</h2>
        <p class="muted">Điều chỉnh nhanh trạng thái bán hoặc giá món</p>
      </div>
      <div style="display: flex; gap: 8px;">
        <a class="btn btn-small btn-primary" href="index.php?page=dishes&action=add">+ Thêm món mới</a>
        <a class="btn btn-small btn-outline" href="index.php?page=dishes">Xem tất cả (<?= count($adminDishesPreview) ?>) →</a>
      </div>
    </div>

    <div class="menu-manage-list">
      <?php if (empty($adminDishesPreview)): ?>
        <p class="muted" style="padding: 12px;">Chưa có món ăn nào trong thực đơn.</p>
      <?php else: ?>
        <?php foreach (array_slice($adminDishesPreview, 0, 5) as $d): ?>
          <div class="line-item">
            <div class="manage-dish-info">
              <b><?= htmlspecialchars($d['name']) ?></b>
              <small><?= formatPrice($d['price']) ?> · Phân loại: <?= htmlspecialchars($d['category_name']) ?> · <?= getStationName($d['station']) ?></small>
            </div>
            <span class="status status-<?= $d['status'] === 'active' ? 'ready' : ($d['status'] === 'out_of_stock' ? 'pending' : 'muted') ?>">
              <?= $d['status'] === 'active' ? 'Đang mở bán' : ($d['status'] === 'out_of_stock' ? 'Tạm hết tại bếp' : 'Đang ẩn') ?>
            </span>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
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
</div>

<div class="dash-header">
  <div>
    <span class="eyebrow">VẬN HÀNH PHỤC VỤ</span>
    <h1>Sơ đồ bàn & Trạng thái phục vụ</h1>
    <p>Theo dõi tình trạng bàn theo thời gian thực và quản lý điều phối khách hàng</p>
  </div>
  <div class="header-action-group">
    <a class="btn btn-outline" href="index.php?page=kitchen">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 13.8V4a2 2 0 1 1 4 0v9.8"></path><path d="M14 13.8V4a2 2 0 1 1 4 0v9.8"></path><path d="M3 14h18a2 2 0 0 1 2 2v1a4 4 0 0 1-4 4H5a4 4 0 0 1-4-4v-1a2 2 0 0 1 2-2z"></path></svg>
      <span>Màn hình bếp</span>
    </a>
    <button class="btn btn-primary-tool" data-toast="Đã đồng bộ lại sơ đồ bàn">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"></polyline><polyline points="1 20 1 14 7 14"></polyline><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path></svg>
      <span>Làm mới</span>
    </button>
  </div>
</div>

<section class="panel floor-panel">
  <div class="panel-title floor-panel-head">
    <div>
      <h2>Sơ đồ không gian nhà hàng</h2>
      <p class="muted">Bấm vào bàn đang phục vụ để xem chi tiết đơn hàng hoặc cập nhật trạng thái</p>
    </div>
    <div class="filter-row">
      <label class="select-wrapper">
        <select id="floor-zone-select">
          <option value="all">Tất cả khu vực</option>
          <option value="indoor">Trong nhà (Máy lạnh)</option>
          <option value="garden">Sân vườn (Ngoài trời)</option>
          <option value="vip">Phòng VIP</option>
        </select>
      </label>
      <label class="select-wrapper">
        <select id="floor-status-select">
          <option value="all">Tất cả bàn</option>
          <option value="empty">Bàn chưa có khách</option>
          <option value="occupied">Bàn đang có khách</option>
        </select>
      </label>
    </div>
  </div>

  <div class="legend">
    <span class="legend-item"><i class="legend-dot empty"></i> Chưa có khách (Chỉ hiển thị số bàn)</span>
    <span class="legend-item"><i class="legend-dot occupied"></i> Đang có khách (Hiển thị số tiền & số khách)</span>
  </div>

  <div class="floor-grid" id="restaurant-floor-grid">
    <?php
    $tables = [
      ['id' => 'B01', 'zone' => 'indoor', 'has_guests' => false],
      ['id' => 'B02', 'zone' => 'indoor', 'has_guests' => false],
      ['id' => 'B03', 'zone' => 'indoor', 'has_guests' => true,  'guests' => 4, 'amount' => 299250],
      ['id' => 'B04', 'zone' => 'indoor', 'has_guests' => false],
      ['id' => 'B05', 'zone' => 'garden', 'has_guests' => true,  'guests' => 3, 'amount' => 485000],
      ['id' => 'B06', 'zone' => 'garden', 'has_guests' => false],
      ['id' => 'B07', 'zone' => 'garden', 'has_guests' => false],
      ['id' => 'B08', 'zone' => 'garden', 'has_guests' => false],
      ['id' => 'B09', 'zone' => 'vip',    'has_guests' => false],
      ['id' => 'B10', 'zone' => 'vip',    'has_guests' => true,  'guests' => 6, 'amount' => 1250000],
      ['id' => 'B11', 'zone' => 'indoor', 'has_guests' => true,  'guests' => 2, 'amount' => 185000],
      ['id' => 'B12', 'zone' => 'indoor', 'has_guests' => false]
    ];

    foreach ($tables as $t):
      $hasGuests = $t['has_guests'];
      $status = $hasGuests ? 'occupied' : 'empty';
    ?>
      <?php if (!$hasGuests): ?>
        <!-- Bàn chưa có khách: chỉ hiển thị số bàn -->
        <a href="index.php?page=table-order&table=<?=$t['id']?>" class="table-tile table-empty" data-table="<?=$t['id']?>" data-status="empty" data-zone="<?=$t['zone']?>" title="Bàn <?=$t['id']?>: Chưa có khách (Bấm để mở bàn)">
          <div class="table-empty-body">
            <span class="table-number-only">Bàn <?=$t['id']?></span>
          </div>
        </a>
      <?php else: ?>
        <!-- Bàn có khách: hiển thị số tiền của bàn đó và số khách -->
        <a href="index.php?page=table-order&table=<?=$t['id']?>" class="table-tile table-occupied" data-table="<?=$t['id']?>" data-status="occupied" data-zone="<?=$t['zone']?>" title="Bàn <?=$t['id']?>: Có khách (Bấm để xem đơn)">
          <div class="table-tile-top">
            <b class="table-name">Bàn <?=$t['id']?></b>
            <span class="table-guest-pill">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
              <?=$t['guests']?> khách
            </span>
          </div>
          <div class="table-tile-bottom">
            <span class="table-amount-label">Số tiền</span>
            <b class="table-amount-value"><?=number_format($t['amount'], 0, ',', '.')?>đ</b>
          </div>
        </a>
      <?php endif; ?>
    <?php endforeach; ?>
  </div>
</section>

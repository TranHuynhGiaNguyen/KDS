<?php
require_once __DIR__ . '/../includes/tables.php';

$occupiedTables = [];
$dbError        = '';

try {
    $occupiedTables = fetchOccupiedTables(getOrdersDb());
} catch (RuntimeException | PDOException $e) {
    error_log('staff page: ' . $e->getMessage());
    $dbError = 'Không tải được trạng thái bàn, vui lòng thử lại.';
}
?>
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

<?php if ($dbError !== ''): ?>
  <div class="flash-alert flash-error"><span><?= escapeHtml($dbError) ?></span></div>
<?php endif; ?>

<section class="panel floor-panel">
  <div class="panel-title floor-panel-head">
    <div>
      <h2>Sơ đồ không gian nhà hàng</h2>
      <p class="muted">Bấm vào bàn đang phục vụ để xem chi tiết đơn hàng hoặc cập nhật trạng thái</p>
    </div>
    <div class="filter-row">
      <!-- CHUYỂN ĐỔI CHẾ ĐỘ XEM: Ô LƯỚI HOẶC 2D THỰC TẾ -->
      <div class="view-mode-toggle">
        <button type="button" class="view-toggle-btn active" id="btn-toggle-grid" title="Chế độ ô lưới truyền thống">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
          <span>Ô lưới</span>
        </button>
        <button type="button" class="view-toggle-btn" id="btn-toggle-visual" title="Sơ đồ 2D theo bố cục không gian thực tế">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 2 7 12 12 22 7 12 2"></polygon><polyline points="2 17 12 22 22 17"></polyline><polyline points="2 12 12 17 22 12"></polyline></svg>
          <span>Sơ đồ 2D Thực tế</span>
          <span class="badge-pill" style="font-size: 10px; background: #fee2e2; color: #dc2626; padding: 1px 5px;">Mới</span>
        </button>
      </div>

      <label class="select-wrapper">
        <select id="floor-zone-select">
          <option value="all">Tất cả khu vực</option>
          <?php 
          $activeAreas = getAllAreas('active');
          foreach ($activeAreas as $area): 
          ?>
            <option value="<?= htmlspecialchars($area['code']) ?>"><?= htmlspecialchars($area['name']) ?></option>
          <?php endforeach; ?>
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
    <span class="legend-item"><i class="legend-dot occupied"></i> Đang có khách (Hiển thị số tiền & số món)</span>
  </div>

  <!-- CHẾ ĐỘ 1: Ô LƯỚI HIỆN TẠI (GIỮ NGUYÊN 100%) -->
  <div class="floor-grid" id="restaurant-floor-grid">
    <?php
    $tables = getAllDiningTables('', 'not_hidden');

    foreach ($tables as $t):
      $isMaintenance = ($t['status'] === 'maintenance');
      $hasGuests     = $t['is_occupied'] && !$isMaintenance;
    ?>
      <?php if ($isMaintenance): ?>
        <!-- Bàn đang tạm bảo trì -->
        <div class="table-tile table-cleaning" data-table="<?=$t['code']?>" data-status="empty" data-zone="<?=$t['area_code']?>" title="Bàn <?=$t['code']?>: Đang bảo trì / không khả dụng" style="opacity: 0.65; cursor: not-allowed;">
          <div class="table-tile-top">
            <b class="table-name" style="color: var(--muted);"><?=$t['code']?></b>
            <span class="badge-pill" style="background: #e2e8f0; color: #64748b; font-size: 11px;">Bảo trì</span>
          </div>
          <div class="table-tile-bottom" style="background: transparent;">
            <small style="color: #64748b;">Tạm ngưng phục vụ</small>
          </div>
        </div>
      <?php elseif (!$hasGuests): ?>
        <!-- Bàn chưa có khách: chỉ hiển thị số bàn -->
        <a href="index.php?page=table-order&table=<?=$t['code']?>" class="table-tile table-empty" data-table="<?=$t['code']?>" data-status="empty" data-zone="<?=$t['area_code']?>" title="Bàn <?=$t['code']?>: Chưa có khách (Bấm để mở bàn)">
          <div class="table-empty-body">
            <span class="table-number-only">Bàn <?=$t['code']?></span>
          </div>
        </a>
      <?php else: ?>
        <!-- Bàn có khách: hiển thị số tiền của bàn đó và số khách -->
        <a href="index.php?page=table-order&table=<?=$t['code']?>" class="table-tile table-occupied" data-table="<?=$t['code']?>" data-status="occupied" data-zone="<?=$t['area_code']?>" title="Bàn <?=$t['code']?>: Có khách (Bấm để xem đơn)">
          <div class="table-tile-top">
            <b class="table-name">Bàn <?=$t['code']?></b>
            <span class="table-guest-pill">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
              <?=$t['occupied_items']?> món
            </span>
          </div>
          <div class="table-tile-bottom">
            <span class="table-amount-label">Số tiền</span>
            <b class="table-amount-value"><?=number_format($t['occupied_total'], 0, ',', '.')?>đ</b>
          </div>
        </a>
      <?php endif; ?>
    <?php endforeach; ?>
  </div>

  <!-- CHẾ ĐỘ 2 (MỚI): SƠ ĐỒ MẶT BẰNG 2D THEO TỌA ĐỘ VÀ BỐ CỤC KHÔNG GIAN THỰC TẾ -->
  <link rel="stylesheet" href="<?= assetUrl('assets/css/visual-floor.css') ?>">
  <script>
    window.SMARTDINE_AREAS = <?= json_encode(getAllAreas(), JSON_UNESCAPED_UNICODE) ?>;
  </script>
  <div class="visual-floor-container" id="restaurant-floor-canvas" style="position: relative; width: 100%; min-height: 580px; background-color: #f8fafc; background-image: radial-gradient(#cbd5e1 1.2px, transparent 1.2px); background-size: 24px 24px; border: 2px dashed #cbd5e1; border-radius: var(--radius-lg); overflow: hidden; margin-top: 14px; user-select: none; display: none;">
    <!-- Các mốc kiến trúc không gian -->
    <div class="arch-landmark landmark-cashier" id="staff-cashier-counter" style="position: absolute; transform: translate(-50%, -50%); left: 14%; top: 6%; z-index: 4;">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="4" width="20" height="16" rx="2"></rect><line x1="2" y1="10" x2="22" y2="10"></line></svg>
      <span>QUẦY THU NGÂN / PHA CHẾ</span>
    </div>
    <div class="arch-landmark landmark-window" style="position: absolute; top: 0; left: 30%; right: 30%; height: 4px; background: #38bdf8; opacity: 0.6; border-radius: 2px; z-index: 1;" title="Vách kính nhìn ra ngoài"></div>
    <div class="landmark-entrance" id="staff-entrance-door" style="position: absolute; transform: translate(-50%, -50%); left: 50%; top: 96%; z-index: 5;">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M3 21h18M6 21V5a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v16M14 11h1"></path></svg>
      <span id="staff-entrance-label">▲ LỐI VÀO CHÍNH ▲</span>
    </div>

    <!-- Danh sách bàn ăn 2D trực quan -->
    <?php foreach ($tables as $t): 
      $isMaintenance = ($t['status'] === 'maintenance');
      $hasGuests     = $t['is_occupied'] && !$isMaintenance;
      $shape         = $t['shape'] ?? 'square';
      $w             = (int)($t['width'] ?? 85);
      $h             = (int)($t['height'] ?? 85);
      $posX          = (float)($t['pos_x'] ?? 20);
      $posY          = (float)($t['pos_y'] ?? 25);
      $radius        = ($shape === 'round') ? '50%' : (($shape === 'rectangle') ? '16px' : '14px');
    ?>
      <?php if ($isMaintenance): ?>
        <div class="visual-table-node shape-<?= $shape ?> status-maintenance"
             data-table="<?= $t['code'] ?>" data-status="empty" data-zone="<?= $t['area_code'] ?>"
             style="position: absolute; transform: translate(-50%, -50%); left: <?= $posX ?>%; top: <?= $posY ?>%; width: <?= $w ?>px; height: <?= $h ?>px; border-radius: <?= $radius ?>;"
             title="Bàn <?= $t['code'] ?>: Đang bảo trì">
          <span class="visual-table-code"><?= $t['code'] ?></span>
          <small style="font-size: 10px; color: #64748b; font-weight: 700;">Bảo trì</small>
        </div>
      <?php elseif (!$hasGuests): ?>
        <a href="index.php?page=table-order&table=<?= $t['code'] ?>" 
           class="visual-table-node shape-<?= $shape ?> status-empty"
           data-table="<?= $t['code'] ?>" data-status="empty" data-zone="<?= $t['area_code'] ?>"
           style="position: absolute; transform: translate(-50%, -50%); left: <?= $posX ?>%; top: <?= $posY ?>%; width: <?= $w ?>px; height: <?= $h ?>px; border-radius: <?= $radius ?>;"
           title="Bàn <?= $t['code'] ?> (<?= (int)$t['capacity'] ?> chỗ): Bàn trống - Bấm để mở bàn">
          <span class="visual-table-code"><?= $t['code'] ?></span>
          <span class="visual-table-seats"><?= (int)$t['capacity'] ?> chỗ</span>
        </a>
      <?php else: ?>
        <a href="index.php?page=table-order&table=<?= $t['code'] ?>" 
           class="visual-table-node shape-<?= $shape ?> status-occupied"
           data-table="<?= $t['code'] ?>" data-status="occupied" data-zone="<?= $t['area_code'] ?>"
           style="position: absolute; transform: translate(-50%, -50%); left: <?= $posX ?>%; top: <?= $posY ?>%; width: <?= $w ?>px; height: <?= $h ?>px; border-radius: <?= $radius ?>;"
           title="Bàn <?= $t['code'] ?>: Có khách - Bấm xem đơn">
          <span class="visual-table-code"><?= $t['code'] ?></span>
          <span class="visual-table-items"><?= (int)$t['occupied_items'] ?> món</span>
          <span class="visual-table-bill"><?= number_format($t['occupied_total'], 0, ',', '.') ?>đ</span>
        </a>
      <?php endif; ?>
    <?php endforeach; ?>
  </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const btnGrid = document.getElementById('btn-toggle-grid');
  const btnVisual = document.getElementById('btn-toggle-visual');
  const gridView = document.getElementById('restaurant-floor-grid');
  const visualView = document.getElementById('restaurant-floor-canvas');

  const zoneSelect = document.getElementById('floor-zone-select');
  const statusSelect = document.getElementById('floor-status-select');

  function setViewMode(mode) {
    if (mode === 'visual') {
      if (btnVisual) btnVisual.classList.add('active');
      if (btnGrid) btnGrid.classList.remove('active');
      if (gridView) gridView.style.display = 'none';
      if (visualView) visualView.style.display = 'block';
      localStorage.setItem('smartdine_floor_view_mode', 'visual');
    } else {
      if (btnGrid) btnGrid.classList.add('active');
      if (btnVisual) btnVisual.classList.remove('active');
      if (visualView) visualView.style.display = 'none';
      if (gridView) gridView.style.display = 'grid';
      localStorage.setItem('smartdine_floor_view_mode', 'grid');
    }
    filterBothViews();
  }

  if (btnGrid) btnGrid.addEventListener('click', () => setViewMode('grid'));
  if (btnVisual) btnVisual.addEventListener('click', () => setViewMode('visual'));

  // Nhớ lựa chọn trước đó của người dùng
  const savedMode = localStorage.getItem('smartdine_floor_view_mode');
  if (savedMode === 'visual') {
    setViewMode('visual');
  }

  // Bộ lọc hoạt động đồng thời cho cả 2 giao diện (Grid & Visual)
  function filterBothViews() {
    const zone = zoneSelect ? zoneSelect.value : 'all';
    const status = statusSelect ? statusSelect.value : 'all';

    // Lọc trên grid
    document.querySelectorAll('.table-tile').forEach(table => {
      const matchZone = (zone === 'all' || table.dataset.zone === zone);
      const matchStatus = (status === 'all' || table.dataset.status === status);
      table.style.display = (matchZone && matchStatus) ? 'flex' : 'none';
    });

    // Lọc trên 2D canvas
    document.querySelectorAll('.visual-table-node').forEach(table => {
      const matchZone = (zone === 'all' || table.dataset.zone === zone);
      const matchStatus = (status === 'all' || table.dataset.status === status);
      table.style.display = (matchZone && matchStatus) ? 'flex' : 'none';
    });

    // Cập nhật vị trí cửa ra vào & quầy thu ngân theo phân khu đang chọn
    const staffDoorEl    = document.getElementById('staff-entrance-door');
    const staffDoorText  = document.getElementById('staff-entrance-label');
    const staffCashierEl = document.getElementById('staff-cashier-counter');

    if (staffDoorEl || staffCashierEl) {
      const targetCode = (zone === 'all') ? 'indoor' : zone;
      const areaData   = (window.SMARTDINE_AREAS || []).find(a => a.code === targetCode);
      if (areaData) {
        // Cửa ra vào
        if (staffDoorEl) {
          const x = parseFloat(areaData.entrance_x) || 50.0;
          const y = parseFloat(areaData.entrance_y) || 96.0;
          staffDoorEl.style.left = x.toFixed(2) + '%';
          staffDoorEl.style.top  = y.toFixed(2) + '%';

          if (staffDoorText) {
            const pos = areaData.entrance_pos || 'bottom_center';
            if (pos.startsWith('top')) {
              staffDoorText.textContent = '▼ CỬA VÀO (' + areaData.name + ') ▼';
            } else if (pos.startsWith('left')) {
              staffDoorText.textContent = '▶ CỬA VÀO (' + areaData.name + ') ▶';
            } else if (pos.startsWith('right')) {
              staffDoorText.textContent = '◀ CỬA VÀO (' + areaData.name + ') ◀';
            } else {
              staffDoorText.textContent = '▲ CỬA VÀO (' + areaData.name + ') ▲';
            }
          }
        }

        // Quầy thu ngân
        if (staffCashierEl) {
          const cx = parseFloat(areaData.cashier_x) || 14.0;
          const cy = parseFloat(areaData.cashier_y) || 6.0;
          staffCashierEl.style.left = cx.toFixed(2) + '%';
          staffCashierEl.style.top  = cy.toFixed(2) + '%';
        }
      }
    }
  }

  if (zoneSelect) zoneSelect.addEventListener('change', filterBothViews);
  if (statusSelect) statusSelect.addEventListener('change', filterBothViews);
});
</script>
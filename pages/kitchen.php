<div class="kds-topbar">
  <div class="kds-title-group">
    <div class="kds-brand-badge">
      <span class="live-dot pulse"></span> KDS LIVE
    </div>
    <div>
      <h1 class="kds-page-title">Màn hình Bếp & Pha chế</h1>
      <p class="kds-page-sub">Theo dõi thời gian thực, điều phối chế biến và báo ra đồ</p>
    </div>
  </div>

  <div class="kds-controls">
    <div class="kds-clock-box">
      <span class="kds-clock-label">GIỜ HỆ THỐNG</span>
      <b class="kds-digital-clock" id="kds-clock">18:45:00</b>
    </div>
    
    <div class="kds-control-buttons">
      <button class="kds-btn-tool" id="kds-theme-toggle" title="Chuyển chế độ tương phản cao (Dark Mode)">
        <span class="theme-icon">🌙</span>
        <span class="btn-text">Bếp Tối</span>
      </button>

      <button class="kds-btn-tool" id="kds-fullscreen-toggle" title="Toàn màn hình cho tablet / TV bếp">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 3H5a2 2 0 0 0-2 2v3m18 0V5a2 2 0 0 0-2-2h-3m0 18h3a2 2 0 0 0 2-2v-3M3 16v3a2 2 0 0 0 2 2h3"></path></svg>
        <span class="btn-text">Toàn màn hình</span>
      </button>

      <button class="kds-btn-tool" id="kds-sound-toggle" title="Bật/Tắt chuông báo đơn">
        <span class="sound-icon">🔔</span>
        <span class="btn-text">Chuông</span>
      </button>

      <button class="kds-btn-tool btn-primary-tool" data-toast="Đã đồng bộ phiếu gọi món mới nhất">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"></polyline><polyline points="1 20 1 14 7 14"></polyline><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path></svg>
        <span class="btn-text">Làm mới</span>
      </button>
    </div>
  </div>
</div>

<div class="kds-filter-bar">
  <div class="kitchen-tabs" id="kds-status-filters">
    <button class="active" data-kfilter="all">Tất cả phiếu <span class="tab-badge">6</span></button>
    <button data-kfilter="new">Chờ tiếp nhận <span class="tab-badge new-badge">2</span></button>
    <button data-kfilter="cooking">Đang nấu <span class="tab-badge cooking-badge">3</span></button>
    <button data-kfilter="ready">Đã xong / Chờ bưng <span class="tab-badge ready-badge">1</span></button>
  </div>

  <div class="kds-station-filter">
    <span class="filter-label">Trạm bếp:</span>
    <select id="kds-station-select">
      <option value="all">Tất cả trạm</option>
      <option value="hot">Bếp nóng (Xào/Nướng)</option>
      <option value="soup">Bếp súp / Lẩu</option>
      <option value="cold">Bếp lạnh / Salad</option>
      <option value="bar">Quầy Bar / Nước</option>
    </select>
  </div>
</div>

<div class="kitchen-grid">
<?php
$orders = [
  [
    'table' => 'Bàn B03',
    'id' => '#1250',
    'time' => '18:22',
    'elapsed' => '23 phút',
    'urgency' => 'urgent',
    'status' => 'cooking',
    'type' => 'Tại bàn',
    'note' => 'Khách ăn ít cay, mang nước ngọt trước',
    'station' => 'hot',
    'items' => [
      ['name' => 'Cơm gà Hải Nam', 'qty' => 1, 'note' => 'Thịt đùi mềm', 'done' => true],
      ['name' => 'Mì xào bò', 'qty' => 1, 'note' => 'Ít dầu mỡ', 'done' => false],
      ['name' => 'Salad trộn', 'qty' => 1, 'note' => 'Sốt riêng', 'done' => false],
      ['name' => 'Trà đào cam sả', 'qty' => 2, 'note' => '70% đường, 50% đá', 'done' => true]
    ]
  ],
  [
    'table' => 'Bàn B05',
    'id' => '#1251',
    'time' => '18:32',
    'elapsed' => '13 phút',
    'urgency' => 'warning',
    'status' => 'cooking',
    'type' => 'Tại bàn',
    'note' => 'Bò chín vừa (Medium rare)',
    'station' => 'hot',
    'items' => [
      ['name' => 'Bò lúc lắc', 'qty' => 2, 'note' => 'Medium rare, kèm khoai', 'done' => false],
      ['name' => 'Cơm chiên hải sản', 'qty' => 1, 'note' => 'Không bỏ hành lá', 'done' => false]
    ]
  ],
  [
    'table' => 'Bàn B07',
    'id' => '#1252',
    'time' => '18:40',
    'elapsed' => '5 phút',
    'urgency' => 'normal',
    'status' => 'new',
    'type' => 'Tại bàn',
    'note' => 'Nước lẩu cay vừa',
    'station' => 'soup',
    'items' => [
      ['name' => 'Lẩu thái hải sản', 'qty' => 1, 'note' => 'Thêm rau nấm', 'done' => false],
      ['name' => 'Mì thêm', 'qty' => 2, 'note' => 'Mì gói trứng', 'done' => false]
    ]
  ],
  [
    'table' => 'Mang về #M04',
    'id' => '#1253',
    'time' => '18:42',
    'elapsed' => '3 phút',
    'urgency' => 'normal',
    'status' => 'new',
    'type' => 'Mang về',
    'note' => 'Đóng gói hộp giấy bảo vệ môi trường',
    'station' => 'hot',
    'items' => [
      ['name' => 'Cơm chiên dương châu', 'qty' => 1, 'note' => '', 'done' => false],
      ['name' => 'Salad trộn', 'qty' => 1, 'note' => '', 'done' => false],
      ['name' => 'Sữa chua trái cây', 'qty' => 1, 'note' => 'Để đá ngoài', 'done' => false]
    ]
  ],
  [
    'table' => 'Bàn B02',
    'id' => '#1249',
    'time' => '18:28',
    'elapsed' => '17 phút',
    'urgency' => 'warning',
    'status' => 'cooking',
    'type' => 'Tại bàn',
    'note' => '',
    'station' => 'soup',
    'items' => [
      ['name' => 'Phở bò đặc biệt', 'qty' => 1, 'note' => 'Nhiều hành tây trần', 'done' => true],
      ['name' => 'Nước khoáng có ga', 'qty' => 2, 'note' => 'Kèm 2 ly đá lát chanh', 'done' => true]
    ]
  ],
  [
    'table' => 'Bàn B01',
    'id' => '#1248',
    'time' => '18:15',
    'elapsed' => '30 phút',
    'urgency' => 'normal',
    'status' => 'ready',
    'type' => 'Tại bàn',
    'note' => 'Đã báo nhân viên chạy bàn mang ra',
    'station' => 'hot',
    'items' => [
      ['name' => 'Mỳ Ý bò bằm', 'qty' => 1, 'note' => 'Sốt kem phô mai', 'done' => true],
      ['name' => 'Trà chanh mật ong', 'qty' => 1, 'note' => 'Ít ngọt', 'done' => true]
    ]
  ]
];

foreach ($orders as $o):
?>
  <article class="kitchen-card kds-card urgency-<?=$o['urgency']?>" data-kcard="<?=$o['status']?>" data-station="<?=$o['station']?>">
    <div class="kitchen-card-head">
      <div class="card-meta-left">
        <div class="card-table-title">
          <b><?=$o['table']?></b>
          <span class="order-code"><?=$o['id']?></span>
          <span class="type-pill"><?=$o['type']?></span>
        </div>
        <div class="card-time-row">
          <span class="time-created">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
            <?=$o['time']?>
          </span>
          <span class="time-elapsed badge-<?=$o['urgency']?>">
            Chờ: <?=$o['elapsed']?>
          </span>
        </div>
      </div>
      
      <div class="card-meta-right">
        <span class="status status-<?=$o['status']?>">
          <?=['new'=>'Chờ nhận','cooking'=>'Đang nấu','ready'=>'Hoàn thành'][$o['status']]?>
        </span>
      </div>
    </div>

    <?php if (!empty($o['note'])): ?>
      <div class="kds-order-note">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
        <span><?=$o['note']?></span>
      </div>
    <?php endif; ?>

    <div class="kitchen-items-list">
      <?php foreach ($o['items'] as $it): ?>
        <div class="kitchen-item <?=!empty($it['done']) ? 'item-done' : ''?>" data-bump-item title="Nhấn vào món để gạch bỏ / đánh dấu xong">
          <label class="item-checkbox-wrap">
            <input type="checkbox" class="kds-item-checkbox" <?=!empty($it['done']) ? 'checked' : ''?>>
            <span class="item-check-custom"></span>
          </label>
          <div class="item-content grow">
            <span class="item-name"><?=$it['name']?></span>
            <?php if (!empty($it['note'])): ?>
              <small class="item-note"><?=$it['note']?></small>
            <?php endif; ?>
          </div>
          <b class="item-qty">× <?=$it['qty']?></b>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="kitchen-actions">
      <?php if ($o['status'] === 'new'): ?>
        <button class="btn btn-kds btn-start-cooking" data-change-status>
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polygon points="5 3 19 12 5 21 5 3"></polygon></svg>
          <span>Nhận đơn & Bắt đầu nấu</span>
        </button>
      <?php elseif ($o['status'] === 'cooking'): ?>
        <button class="btn btn-kds btn-ready-cooking" data-change-status>
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
          <span>Báo ra đồ (Hoàn thành)</span>
        </button>
      <?php else: ?>
        <button class="btn btn-kds btn-done-cooking" disabled>
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
          <span>Đã ra đồ xong ✓</span>
        </button>
      <?php endif; ?>
    </div>
  </article>
<?php endforeach; ?>
</div>

<section class="panel unavailable-panel">
  <div class="unavailable-head">
    <div>
      <h2>Quản lý tình trạng nguyên liệu & món tại bếp</h2>
      <p class="muted">Tạm dừng nhận món khi bếp hết nguyên liệu hoặc đột xuất quá tải</p>
    </div>
    <span class="badge-pill">Đồng bộ tự động với Menu QR</span>
  </div>

  <div class="unavailable-grid">
    <div class="unavailable-row">
      <div class="un-info">
        <b>Mì xào bò</b>
        <span class="status status-ready">Đang phục vụ bình thường</span>
      </div>
      <button class="btn btn-small btn-outline-danger" data-toggle-food>Tạm ngừng bán</button>
    </div>

    <div class="unavailable-row">
      <div class="un-info">
        <b>Bò lúc lắc</b>
        <span class="status status-pending">Đã tạm hết nguyên liệu</span>
      </div>
      <button class="btn btn-small btn-success" data-toggle-food>Mở bán lại</button>
    </div>

    <div class="unavailable-row">
      <div class="un-info">
        <b>Lẩu thái hải sản</b>
        <span class="status status-ready">Đang phục vụ bình thường</span>
      </div>
      <button class="btn btn-small btn-outline-danger" data-toggle-food>Tạm ngừng bán</button>
    </div>

    <div class="unavailable-row">
      <div class="un-info">
        <b>Salad trộn đặc biệt</b>
        <span class="status status-ready">Đang phục vụ bình thường</span>
      </div>
      <button class="btn btn-small btn-outline-danger" data-toggle-food>Tạm ngừng bán</button>
    </div>
  </div>
</section>

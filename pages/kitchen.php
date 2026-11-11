<?php
require_once __DIR__ . '/../includes/kitchen-view.php';

$tickets = [];
$error   = '';

try {
    $tickets = fetchKitchenTickets(getOrdersDb());
} catch (RuntimeException | PDOException $e) {
    error_log('kitchen page: ' . $e->getMessage());
    $error = 'Không tải được danh sách món của bếp, vui lòng thử lại.';
}

$counts = countKitchenTickets($tickets);
?>
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

      <button class="kds-btn-tool btn-primary-tool" data-kds-refresh>
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"></polyline><polyline points="1 20 1 14 7 14"></polyline><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path></svg>
        <span class="btn-text">Làm mới</span>
      </button>
    </div>
  </div>
</div>

<?php if ($error !== ''): ?>
  <div class="flash-alert flash-error"><span><?= escapeHtml($error) ?></span></div>
<?php endif; ?>

<div class="kds-filter-bar">
  <div class="kds-summary">
    <span><b data-kds-count="tables"><?= $counts['tables'] ?></b> bàn</span>
    <span><b data-kds-count="pending"><?= $counts['pending'] ?></b> món chờ làm</span>
    <a class="kds-history-link" href="index.php?page=bills">Lịch sử bill</a>
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

<div class="kitchen-grid" id="kds-grid" data-poll-url="api/kitchen-items.php" data-action-url="api/kitchen-status.php">
  <?= renderKitchenGrid($tickets) ?>
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
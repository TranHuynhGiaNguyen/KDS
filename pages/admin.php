<?php

/**
 * SmartDine - Bảng điều khiển Quản trị & Dashboard Doanh thu
 * Thống kê doanh thu theo ngày, tháng, khu vực, phương thức thanh toán, top món ăn
 * và Báo cáo Hiệu suất Bếp (Kitchen Performance Metrics).
 */

require_once __DIR__ . '/../includes/tables.php';
require_once __DIR__ . '/../includes/revenue.php';
require_once __DIR__ . '/../includes/kitchen-metrics.php';
require_once __DIR__ . '/../includes/vouchers.php';
require_once __DIR__ . '/../includes/admin-orders-view.php';

// Xử lý xuất file CSV nếu có yêu cầu
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
  $pdo = getRevenueDb();
  $viewMode = $_GET['mode'] ?? 'day';
  if (!in_array($viewMode, ['day', 'month', 'range'], true)) {
    $viewMode = 'day';
  }
  $selectedDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date'] ?? '') ? $_GET['date'] : date('Y-m-d');
  $selectedMonth = preg_match('/^\d{4}-\d{2}$/', $_GET['month'] ?? '') ? $_GET['month'] : date('Y-m');
  $startDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['start_date'] ?? '') ? $_GET['start_date'] : date('Y-m-d', strtotime('-6 days'));
  $endDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['end_date'] ?? '') ? $_GET['end_date'] : date('Y-m-d');

  if ($viewMode === 'range') {
    $report = getRevenueByRange($pdo, $startDate, $endDate);
  } elseif ($viewMode === 'month') {
    $report = getRevenueByMonth($pdo, $selectedMonth);
  } else {
    $report = getRevenueByDate($pdo, $selectedDate);
  }
  exportRevenueCsv($report);
  exit;
}

// Xử lý bật / tắt trạng thái hết món nhanh
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_dish_status') {
  $dishId = (int) ($_POST['id'] ?? 0);
  $res = toggleDishStatus($dishId);
  if ($res['success']) {
    $_SESSION['flash_success'] = $res['message'];
  } else {
    $_SESSION['flash_error'] = $res['message'];
  }
  $redirectMode = $_GET['mode'] ?? 'day';
  header('Location: index.php?page=admin&mode=' . urlencode($redirectMode));
  exit;
}

$pdo = getRevenueDb();
$adminTableStats = getTableStats();
$todayKitchenStats = getKitchenPerformanceReport(date('Y-m-d'), date('Y-m-d'));
$adminOpenTables = [];
try {
  $adminOpenTables = fetchOpenOrdersSummary(getOrdersDb());
} catch (RuntimeException | PDOException $e) {
  error_log('admin active orders: ' . $e->getMessage());
}

// Xác định chế độ xem: theo ngày (day), theo tháng (month), theo khoảng ngày (range) hay hiệu suất bếp (kitchen)
$viewMode = $_GET['mode'] ?? 'day';
if (!in_array($viewMode, ['day', 'month', 'range', 'kitchen'], true)) {
  $viewMode = 'day';
}
$selectedDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date'] ?? '') ? $_GET['date'] : date('Y-m-d');
$selectedMonth = preg_match('/^\d{4}-\d{2}$/', $_GET['month'] ?? '') ? $_GET['month'] : date('Y-m');
$startDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['start_date'] ?? '') ? $_GET['start_date'] : date('Y-m-d', strtotime('-6 days'));
$endDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['end_date'] ?? '') ? $_GET['end_date'] : date('Y-m-d');
if ($startDate > $endDate) {
  $tmp = $startDate;
  $startDate = $endDate;
  $endDate = $tmp;
}

// Lấy dữ liệu báo cáo tương ứng
if ($viewMode === 'kitchen') {
  $kitchenReport = getKitchenPerformanceReport($startDate, $endDate);
  $report = getRevenueByRange($pdo, $startDate, $endDate);
} elseif ($viewMode === 'range') {
  $report = getRevenueByRange($pdo, $startDate, $endDate);
} elseif ($viewMode === 'month') {
  $report = getRevenueByMonth($pdo, $selectedMonth);
} else {
  $report = getRevenueByDate($pdo, $selectedDate);
}

// Các mốc ngày nhanh
$todayStr = date('Y-m-d');
$yesterdayStr = date('Y-m-d', strtotime('-1 day'));
$sevenDaysAgoStr = date('Y-m-d', strtotime('-7 days'));
$thisMonthStr = date('Y-m');
$prevMonthStr = date('Y-m', strtotime('-1 month'));
$range7Start = date('Y-m-d', strtotime('-6 days'));
$range30Start = date('Y-m-d', strtotime('-29 days'));
$thisMonthStart = date('Y-m-01');
?>

<!-- Header in ấn A4 (Chỉ hiển thị khi in hoặc lưu PDF) -->
<div class="print-header">
  <h1 style="margin: 0 0 6px; font-size: 20pt;">SMARTDINE RESTAURANT — <?= $viewMode === 'kitchen' ? 'BÁO CÁO HIỆU SUẤT BẾP (KDS)' : 'BÁO CÁO DOANH THU' ?></h1>
  <p style="margin: 0; color: #555;">
    Thời gian thống kê: <b><?= $viewMode === 'kitchen' ? 'Từ ' . date('d/m/Y', strtotime($startDate)) . ' đến ' . date('d/m/Y', strtotime($endDate)) : ($viewMode === 'month' ? 'Tháng ' . $report['month_display'] : ($viewMode === 'range' ? 'Khoảng ' . $report['date_display'] : 'Ngày ' . $report['date_display'] . ' (' . $report['day_name'] . ')')) ?></b>
    · Xuất lúc: <?= date('d/m/Y H:i:s') ?> · Người lập: Quản trị viên
  </p>
</div>

<!-- HEADER CHÍNH CỦA DASHBOARD -->
<div class="dash-header">
  <div>
    <span class="eyebrow"><?= $viewMode === 'kitchen' ? 'KITCHEN PERFORMANCE & EFFICIENCY DASHBOARD' : 'SMARTDINE MANAGEMENT & REVENUE DASHBOARD' ?></span>
    <h1><?= $viewMode === 'kitchen' ? 'Báo cáo Hiệu suất Bếp (Kitchen Metrics)' : 'Tổng quan & Báo cáo Doanh thu' ?></h1>
    <p><?= $viewMode === 'kitchen' ? 'Thống kê thời gian chế biến trung bình, cảnh báo các món bị trễ giờ và phân tích tải theo từng quầy bếp.' : 'Theo dõi chi tiết hiệu quả kinh doanh, doanh thu theo ngày / tháng / khoảng ngày và phân bổ vận hành nhà hàng.' ?></p>
  </div>
  <div class="filter-row">
    <a class="btn btn-outline" href="index.php?page=users">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
        <circle cx="9" cy="7" r="4"></circle>
        <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
        <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
      </svg>
      <span>Quản lý nhân viên</span>
    </a>
    <a class="btn btn-outline" href="index.php?page=dishes">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M3 2v7c0 1.1.9 2 2 2h4a2 2 0 0 0 2-2V2"></path>
        <path d="M7 2v20"></path>
        <path d="M21 15V2v0a5 5 0 0 0-5 5v6c0 1.1.9 2 2 2h3Zm0 0v7"></path>
      </svg>
      <span>Quản lý thực đơn</span>
    </a>
    <a class="btn btn-outline" href="index.php?page=tables">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <rect x="3" y="3" width="18" height="18" rx="2"></rect>
        <path d="M3 9h18M9 21V9"></path>
      </svg>
      <span>Quản lý bàn & khu vực</span>
    </a>
    <a class="btn btn-outline" href="index.php?page=vouchers">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path>
        <line x1="7" y1="7" x2="7.01" y2="7"></line>
      </svg>
      <span>Mã khuyến mãi</span>
    </a>
    <a class="btn btn-primary-tool" href="index.php?page=kitchen">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M6 13.8V4a2 2 0 1 1 4 0v9.8"></path>
        <path d="M14 13.8V4a2 2 0 1 1 4 0v9.8"></path>
        <path d="M3 14h18a2 2 0 0 1 2 2v1a4 4 0 0 1-4 4H5a4 4 0 0 1-4-4v-1a2 2 0 0 1 2-2z"></path>
      </svg>
      <span>Màn hình bếp KDS</span>
    </a>
  </div>
</div>

<!-- ==========================================================
     BỘ LỌC DOANH THU THEO NGÀY / THÁNG / KHOẢNG NGÀY & CÔNG CỤ
     ========================================================== -->
<section class="revenue-filter-toolbar">
  <!-- Tabs chuyển chế độ: Theo ngày vs Theo tháng vs Khoảng ngày vs Hiệu suất bếp -->
  <div class="revenue-view-tabs">
    <a class="revenue-tab-btn <?= $viewMode === 'day' ? 'active' : '' ?>" href="index.php?page=admin&mode=day&date=<?= htmlspecialchars($selectedDate) ?>">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
        <line x1="16" y1="2" x2="16" y2="6"></line>
        <line x1="8" y1="2" x2="8" y2="6"></line>
        <line x1="3" y1="10" x2="21" y2="10"></line>
      </svg>
      <span>Theo ngày</span>
    </a>
    <a class="revenue-tab-btn <?= $viewMode === 'month' ? 'active' : '' ?>" href="index.php?page=admin&mode=month&month=<?= htmlspecialchars($selectedMonth) ?>">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <line x1="18" y1="20" x2="18" y2="10"></line>
        <line x1="12" y1="20" x2="12" y2="4"></line>
        <line x1="6" y1="20" x2="6" y2="14"></line>
      </svg>
      <span>Theo tháng</span>
    </a>
    <a class="revenue-tab-btn <?= $viewMode === 'range' ? 'active' : '' ?>" href="index.php?page=admin&mode=range&start_date=<?= htmlspecialchars($startDate) ?>&end_date=<?= htmlspecialchars($endDate) ?>">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <circle cx="12" cy="12" r="10"></circle>
        <polyline points="12 6 12 12 16 14"></polyline>
      </svg>
      <span>Theo khoảng ngày</span>
    </a>
    <a class="revenue-tab-btn <?= $viewMode === 'kitchen' ? 'active' : '' ?>" href="index.php?page=admin&mode=kitchen&start_date=<?= htmlspecialchars($startDate) ?>&end_date=<?= htmlspecialchars($endDate) ?>">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M6 13.8V4a2 2 0 1 1 4 0v9.8"></path>
        <path d="M14 13.8V4a2 2 0 1 1 4 0v9.8"></path>
        <path d="M3 14h18a2 2 0 0 1 2 2v1a4 4 0 0 1-4 4H5a4 4 0 0 1-4-4v-1a2 2 0 0 1 2-2z"></path>
      </svg>
      <span>Hiệu suất bếp</span>
    </a>
  </div>

  <!-- Form chọn ngày / tháng / khoảng cụ thể -->
  <form class="revenue-filter-form" method="get" action="index.php">
    <input type="hidden" name="page" value="admin">
    <input type="hidden" name="mode" value="<?= htmlspecialchars($viewMode) ?>">

    <?php if ($viewMode === 'day'): ?>
      <input type="date" name="date" value="<?= htmlspecialchars($selectedDate) ?>" aria-label="Chọn ngày xem doanh thu">
      <div class="quick-filter-chips">
        <a class="quick-chip <?= $selectedDate === $todayStr ? 'active' : '' ?>" href="index.php?page=admin&mode=day&date=<?= $todayStr ?>">Hôm nay</a>
        <a class="quick-chip <?= $selectedDate === $yesterdayStr ? 'active' : '' ?>" href="index.php?page=admin&mode=day&date=<?= $yesterdayStr ?>">Hôm qua</a>
        <a class="quick-chip <?= $selectedDate === $sevenDaysAgoStr ? 'active' : '' ?>" href="index.php?page=admin&mode=day&date=<?= $sevenDaysAgoStr ?>">7 ngày trước (<?= date('d/m', strtotime('-7 days')) ?>)</a>
      </div>
    <?php elseif ($viewMode === 'month'): ?>
      <input type="month" name="month" value="<?= htmlspecialchars($selectedMonth) ?>" aria-label="Chọn tháng xem doanh thu">
      <div class="quick-filter-chips">
        <a class="quick-chip <?= $selectedMonth === $thisMonthStr ? 'active' : '' ?>" href="index.php?page=admin&mode=month&month=<?= $thisMonthStr ?>">Tháng này (<?= date('m/Y') ?>)</a>
        <a class="quick-chip <?= $selectedMonth === $prevMonthStr ? 'active' : '' ?>" href="index.php?page=admin&mode=month&month=<?= $prevMonthStr ?>">Tháng trước (<?= date('m/Y', strtotime('-1 month')) ?>)</a>
      </div>
    <?php elseif ($viewMode === 'kitchen'): ?>
      <div style="display: flex; align-items: center; gap: 6px;">
        <input type="date" name="start_date" value="<?= htmlspecialchars($startDate) ?>" aria-label="Từ ngày">
        <span style="color: #64748b; font-size: 13px;">đến</span>
        <input type="date" name="end_date" value="<?= htmlspecialchars($endDate) ?>" aria-label="Đến ngày">
      </div>
      <div class="quick-filter-chips">
        <a class="quick-chip <?= ($startDate === $todayStr && $endDate === $todayStr) ? 'active' : '' ?>" href="index.php?page=admin&mode=kitchen&start_date=<?= $todayStr ?>&end_date=<?= $todayStr ?>">Hôm nay</a>
        <a class="quick-chip <?= ($startDate === $range7Start && $endDate === $todayStr) ? 'active' : '' ?>" href="index.php?page=admin&mode=kitchen&start_date=<?= $range7Start ?>&end_date=<?= $todayStr ?>">7 ngày qua</a>
        <a class="quick-chip <?= ($startDate === $range30Start && $endDate === $todayStr) ? 'active' : '' ?>" href="index.php?page=admin&mode=kitchen&start_date=<?= $range30Start ?>&end_date=<?= $todayStr ?>">30 ngày qua</a>
        <a class="quick-chip <?= ($startDate === $thisMonthStart && $endDate === $todayStr) ? 'active' : '' ?>" href="index.php?page=admin&mode=kitchen&start_date=<?= $thisMonthStart ?>&end_date=<?= $todayStr ?>">Tháng này</a>
      </div>
    <?php else: ?>
      <div style="display: flex; align-items: center; gap: 6px;">
        <input type="date" name="start_date" value="<?= htmlspecialchars($startDate) ?>" aria-label="Từ ngày">
        <span style="color: #64748b; font-size: 13px;">đến</span>
        <input type="date" name="end_date" value="<?= htmlspecialchars($endDate) ?>" aria-label="Đến ngày">
      </div>
      <div class="quick-filter-chips">
        <a class="quick-chip <?= ($startDate === $range7Start && $endDate === $todayStr) ? 'active' : '' ?>" href="index.php?page=admin&mode=range&start_date=<?= $range7Start ?>&end_date=<?= $todayStr ?>">7 ngày qua</a>
        <a class="quick-chip <?= ($startDate === $range30Start && $endDate === $todayStr) ? 'active' : '' ?>" href="index.php?page=admin&mode=range&start_date=<?= $range30Start ?>&end_date=<?= $todayStr ?>">30 ngày qua</a>
        <a class="quick-chip <?= ($startDate === $thisMonthStart && $endDate === $todayStr) ? 'active' : '' ?>" href="index.php?page=admin&mode=range&start_date=<?= $thisMonthStart ?>&end_date=<?= $todayStr ?>">Tháng này</a>
      </div>
    <?php endif; ?>

    <button type="submit" class="btn btn-small btn-primary">Lọc dữ liệu</button>
  </form>

  <!-- Nút Thao tác: Xuất CSV & In báo cáo -->
  <div class="revenue-actions">
    <?php
    $exportParams = 'mode=' . urlencode($viewMode);
    if ($viewMode === 'range') {
      $exportParams .= '&start_date=' . urlencode($startDate) . '&end_date=' . urlencode($endDate);
    } elseif ($viewMode === 'month') {
      $exportParams .= '&month=' . urlencode($selectedMonth);
    } else {
      $exportParams .= '&date=' . urlencode($selectedDate);
    }
    ?>
    <a class="btn btn-small btn-outline" href="index.php?page=admin&export=csv&<?= $exportParams ?>" title="Xuất báo cáo sang file Excel CSV">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
        <polyline points="7 10 12 15 17 10"></polyline>
        <line x1="12" y1="15" x2="12" y2="3"></line>
      </svg>
      <span>Xuất CSV (Excel)</span>
    </a>
    <button type="button" class="btn btn-small btn-outline" onclick="window.print()" title="In báo cáo doanh thu ra máy in hoặc lưu PDF">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <polyline points="6 9 6 2 18 2 18 9"></polyline>
        <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
        <rect x="6" y="14" width="12" height="8"></rect>
      </svg>
      <span>In báo cáo</span>
    </button>
  </div>
</section>

<!-- ==========================================================
     THEO DÕI MÓN ĐANG PHỤC VỤ TẠI BÀN (ĐỒNG BỘ QR · BẾP · STAFF)
     ========================================================== -->
<section class="panel" id="admin-live-orders-panel" style="margin-bottom: 24px;" data-poll-url="api/admin-orders.php" data-cancel-url="api/order-item.php">
  <div class="panel-title">
    <div>
      <h2>Theo dõi món đang phục vụ tại bàn</h2>
      <p class="muted">Đồng bộ tức thì khi khách gọi QR, Bếp bấm In phiếu bếp (Đã nấu xong) hoặc Admin hủy món</p>
    </div>
    <div style="display: flex; align-items: center; gap: 10px;">
      <span class="badge-pill" id="admin-live-orders-badge"><?= count($adminOpenTables) ?> bàn đang mở</span>
      <button type="button" class="btn btn-small btn-outline" data-admin-orders-refresh>Làm mới</button>
    </div>
  </div>
  <div id="admin-live-orders-body">
    <?= renderAdminActiveOrders($adminOpenTables) ?>
  </div>
</section>

<?php if ($viewMode === 'kitchen'): ?>
  <!-- ==========================================================
       BÁO CÁO HIỆU SUẤT BẾP (KITCHEN PERFORMANCE METRICS)
       ========================================================== -->
  <div class="revenue-stats-grid">
    <!-- Thẻ 1: Thời gian chế biến trung bình -->
    <div class="revenue-stat-card" style="border-top: 4px solid var(--brand-orange);">
      <div class="revenue-stat-head">
        <span>Thời gian chế biến TB</span>
        <span class="stat-trend-badge <?= $kitchenReport['avg_cook_minutes'] <= 12 ? 'positive' : 'urgent' ?>">
          <?= $kitchenReport['avg_cook_minutes'] <= 12 ? '✓ Nhanh' : '⚠ Chậm' ?>
        </span>
      </div>
      <div class="revenue-stat-value"><?= $kitchenReport['avg_cook_minutes'] ?> <span style="font-size: 16px; font-weight: 600; color: #64748b;">phút / món</span></div>
      <div class="revenue-stat-sub">
        Từ lúc Bếp nhận phiếu/bấm nấu đến khi bấm Hoàn thành
      </div>
      <small style="color: #64748b; font-size: 11px;">
        Đo lường tốc độ đứng bếp thực tế của các quầy
      </small>
    </div>

    <!-- Thẻ 2: Thời gian chờ trung bình của thực khách -->
    <div class="revenue-stat-card" style="border-top: 4px solid #0284c7;">
      <div class="revenue-stat-head">
        <span>Thời gian chờ phục vụ TB</span>
        <span class="stat-trend-badge <?= $kitchenReport['avg_wait_minutes'] <= 15 ? 'positive' : 'warning' ?>">
          TB <?= $kitchenReport['avg_wait_minutes'] ?> phút
        </span>
      </div>
      <div class="revenue-stat-value" style="color: #0284c7;"><?= $kitchenReport['avg_wait_minutes'] ?> <span style="font-size: 16px; font-weight: 600; color: #64748b;">phút</span></div>
      <div class="revenue-stat-sub">
        Từ lúc khách/phục vụ gửi đơn đến khi Bếp hoàn tất
      </div>
      <small style="color: #64748b; font-size: 11px;">
        Bao gồm thời gian chờ xếp hàng phiếu order
      </small>
    </div>

    <!-- Thẻ 3: Tỷ lệ hoàn thành đúng giờ (< 15 phút) -->
    <div class="revenue-stat-card" style="border-top: 4px solid #16a34a;">
      <div class="revenue-stat-head">
        <span>Tỷ lệ ra món đúng giờ</span>
        <span class="stat-trend-badge positive">Chuẩn &lt; 15p</span>
      </div>
      <div class="revenue-stat-value" style="color: #16a34a;"><?= $kitchenReport['on_time_rate'] ?>%</div>
      <div class="revenue-stat-sub">
        Đạt chuẩn: <b><?= number_format($kitchenReport['on_time_count']) ?></b> / <?= number_format($kitchenReport['total_completed']) ?> phần món
      </div>
      <small style="color: #16a34a; font-size: 11px; font-weight: 600;">
        ✓ Khách hàng hài lòng về tốc độ phục vụ
      </small>
    </div>

    <!-- Thẻ 4: Món trễ giờ (>= 15 phút) -->
    <div class="revenue-stat-card" style="border-top: 4px solid #ef4444;">
      <div class="revenue-stat-head">
        <span>Món bị trễ giờ (&ge; 15p)</span>
        <span class="stat-trend-badge <?= $kitchenReport['delayed_count'] > 0 ? 'urgent' : 'positive' ?>">
          <?= $kitchenReport['delayed_rate'] ?>% tổng món
        </span>
      </div>
      <div class="revenue-stat-value" style="color: #ef4444;"><?= number_format($kitchenReport['delayed_count']) ?> <span style="font-size: 16px; font-weight: 600; color: #64748b;">phần món</span></div>
      <div class="revenue-stat-sub">
        Các món vượt ngưỡng chuẩn phục vụ của nhà hàng
      </div>
      <small style="color: #ef4444; font-size: 11px;">
        <?= $kitchenReport['delayed_count'] > 0 ? '⚠ Cần kiểm tra quầy bếp quá tải bên dưới' : '✓ Không có món trễ' ?>
      </small>
    </div>
  </div>

  <!-- CẢNH BÁO MÓN THƯỜNG XUYÊN BỊ TRỄ GIỜ PHỤC VỤ -->
  <section class="panel" style="margin-bottom: 24px; border-left: 5px solid <?= !empty($kitchenReport['delayed_alerts']) ? '#ef4444' : '#16a34a' ?>;">
    <div class="panel-title">
      <div>
        <h2 style="display: flex; align-items: center; gap: 8px;">
          <span><?= !empty($kitchenReport['delayed_alerts']) ? '🚨' : '✅' ?></span>
          <span>Cảnh báo món thường xuyên bị trễ giờ phục vụ (&ge; 15 phút)</span>
        </h2>
        <p class="muted">Hệ thống tự động phát hiện các món chế biến lâu hoặc thường xuyên quá tải để tối ưu quy trình bếp</p>
      </div>
    </div>

    <?php if (empty($kitchenReport['delayed_alerts'])): ?>
      <div style="background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; padding: 16px 20px; border-radius: 8px; font-size: 14px;">
        <b>✓ Xuất sắc!</b> Trong khoảng thời gian này không có món nào bị trễ giờ chế biến quá 15 phút. Toàn bộ các quầy bếp đều hoạt động hiệu quả và đúng tiến độ.
      </div>
    <?php else: ?>
      <div style="overflow-x: auto;">
        <table class="data-table" style="width: 100%; border-collapse: collapse; text-align: left; font-size: 13.5px;">
          <thead>
            <tr style="background: #fff1f2; border-bottom: 1px solid #fecdd3; color: #9f1239;">
              <th style="padding: 12px 16px;">Tên món ăn</th>
              <th style="padding: 12px 16px;">Quầy phụ trách</th>
              <th style="padding: 12px 16px; text-align: center;">Số lần trễ / Tổng làm</th>
              <th style="padding: 12px 16px; text-align: center;">Tỷ lệ trễ</th>
              <th style="padding: 12px 16px; text-align: right;">Khách chờ TB</th>
              <th style="padding: 12px 16px; text-align: right;">Bếp nấu TB</th>
              <th style="padding: 12px 16px; text-align: right;">Chậm nhất</th>
              <th style="padding: 12px 16px; text-align: center;">Mức độ cảnh báo</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($kitchenReport['delayed_alerts'] as $alert): ?>
              <tr style="border-bottom: 1px solid #f1f5f9;">
                <td style="padding: 12px 16px;">
                  <b><?= htmlspecialchars($alert['item_name']) ?></b>
                </td>
                <td style="padding: 12px 16px;">
                  <span class="role-badge role-kitchen" style="font-size: 11px;"><?= htmlspecialchars($alert['station_name']) ?></span>
                </td>
                <td style="padding: 12px 16px; text-align: center;">
                  <b style="color: #ef4444;"><?= $alert['delayed_count'] ?></b> / <?= $alert['total_cooked'] ?> lần
                </td>
                <td style="padding: 12px 16px; text-align: center;">
                  <span style="font-weight: 700; color: <?= $alert['delayed_rate'] >= 30 ? '#ef4444' : '#f59e0b' ?>;">
                    <?= $alert['delayed_rate'] ?>%
                  </span>
                </td>
                <td style="padding: 12px 16px; text-align: right; font-weight: 600; color: #b91c1c;">
                  <?= $alert['avg_wait_minutes'] ?> phút
                </td>
                <td style="padding: 12px 16px; text-align: right; color: #475569;">
                  <?= $alert['avg_cook_minutes'] ?> phút
                </td>
                <td style="padding: 12px 16px; text-align: right; color: #ef4444; font-weight: 700;">
                  <?= $alert['max_wait_minutes'] ?> phút
                </td>
                <td style="padding: 12px 16px; text-align: center;">
                  <?php if ($alert['severity'] === 'urgent'): ?>
                    <span class="stat-trend-badge urgent" style="padding: 3px 8px; font-size: 11px;">🚨 Nguy cơ cao (&ge; 20p)</span>
                  <?php else: ?>
                    <span class="stat-trend-badge warning" style="padding: 3px 8px; font-size: 11px;">⚠️ Cần lưu ý (&ge; 15p)</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div style="margin-top: 12px; font-size: 12px; color: #64748b; background: #f8fafc; padding: 10px 14px; border-radius: 6px;">
        💡 <b>Khuyến nghị quản trị:</b> Với các món có tỷ lệ trễ &ge; 30%, Bếp trưởng nên bố trí thêm bếp phụ hoặc chuẩn bị sẵn nguyên liệu sơ chế trước giờ cao điểm.
      </div>
    <?php endif; ?>
  </section>

  <!-- PHÂN BỔ HIỆU SUẤT THEO QUẦY BẾP (STATION PERFORMANCE) -->
  <section class="panel" style="margin-bottom: 24px;">
    <div class="panel-title">
      <div>
        <h2>Hiệu suất theo Quầy chế biến</h2>
        <p class="muted">Đo lường thời gian ra món và tải công việc của từng khu vực bếp</p>
      </div>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px;">
      <?php foreach ($kitchenReport['station_breakdown'] as $st): ?>
        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 16px;">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
            <b style="font-size: 14px; color: var(--ink);"><?= htmlspecialchars($st['name']) ?></b>
            <span class="stat-trend-badge <?= $st['on_time_rate'] >= 85 ? 'positive' : 'warning' ?>" style="font-size: 11px;">
              <?= $st['on_time_rate'] ?>% đúng giờ
            </span>
          </div>
          <div style="font-size: 20px; font-weight: 700; color: var(--ink); margin-bottom: 4px;">
            <?= number_format($st['total_cooked']) ?> <span style="font-size: 13px; font-weight: normal; color: #64748b;">phần hoàn thành</span>
          </div>
          <div style="font-size: 12.5px; color: #475569;">
            Thời gian nấu TB: <b><?= $st['avg_cook_minutes'] ?> phút</b>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

  <!-- BẢNG CHI TIẾT TẤT CẢ MÓN ĂN -->
  <section class="panel" style="margin-bottom: 24px;">
    <div class="panel-title">
      <div>
        <h2>Chi tiết thời gian chế biến từng món ăn</h2>
        <p class="muted">Xếp hạng tốc độ chế biến, thời gian chờ và tỷ lệ hoàn thành đúng chuẩn của toàn bộ thực đơn</p>
      </div>
    </div>

    <div style="overflow-x: auto;">
      <table class="data-table" style="width: 100%; border-collapse: collapse; text-align: left; font-size: 13.5px;">
        <thead>
          <tr style="background: #f8fafc; border-bottom: 1px solid #e2e8f0; color: #475569; font-weight: 600;">
            <th style="padding: 12px 16px;">Tên món ăn</th>
            <th style="padding: 12px 16px;">Quầy bếp</th>
            <th style="padding: 12px 16px; text-align: center;">Số lượng ra món</th>
            <th style="padding: 12px 16px; text-align: right;">Nhanh nhất</th>
            <th style="padding: 12px 16px; text-align: right;">Chế biến TB</th>
            <th style="padding: 12px 16px; text-align: right;">Lâu nhất</th>
            <th style="padding: 12px 16px; text-align: right;">Khách chờ TB</th>
            <th style="padding: 12px 16px; text-align: center;">Đúng giờ (&lt; 15p)</th>
            <th style="padding: 12px 16px; text-align: center;">Đánh giá</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($kitchenReport['dish_breakdown'])): ?>
            <tr>
              <td colspan="9" style="padding: 30px; text-align: center; color: #94a3b8;">
                Chưa có dữ liệu chế biến món ăn nào hoàn thành trong khoảng thời gian này.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($kitchenReport['dish_breakdown'] as $dish): ?>
              <tr style="border-bottom: 1px solid #f1f5f9;">
                <td style="padding: 12px 16px;">
                  <b><?= htmlspecialchars($dish['item_name']) ?></b>
                </td>
                <td style="padding: 12px 16px;">
                  <span class="role-badge role-kitchen" style="font-size: 11px;"><?= htmlspecialchars($dish['station_name']) ?></span>
                </td>
                <td style="padding: 12px 16px; text-align: center;">
                  <b><?= number_format($dish['total_cooked']) ?></b> phần (<?= $dish['total_qty'] ?> đĩa/bát)
                </td>
                <td style="padding: 12px 16px; text-align: right; color: #16a34a; font-weight: 600;">
                  <?= $dish['min_cook_minutes'] ?>p
                </td>
                <td style="padding: 12px 16px; text-align: right; font-weight: 700; color: #0284c7;">
                  <?= $dish['avg_cook_minutes'] ?> phút
                </td>
                <td style="padding: 12px 16px; text-align: right; color: #ef4444;">
                  <?= $dish['max_cook_minutes'] ?>p
                </td>
                <td style="padding: 12px 16px; text-align: right; font-weight: 600; color: #475569;">
                  <?= $dish['avg_wait_minutes'] ?> phút
                </td>
                <td style="padding: 12px 16px; text-align: center;">
                  <span style="font-weight: 700; color: <?= $dish['on_time_rate'] >= 85 ? '#16a34a' : ($dish['on_time_rate'] >= 60 ? '#f59e0b' : '#ef4444') ?>;">
                    <?= $dish['on_time_rate'] ?>%
                  </span>
                </td>
                <td style="padding: 12px 16px; text-align: center;">
                  <span class="stat-trend-badge <?= $dish['badge']['class'] ?>" style="font-size: 11px; padding: 2px 8px;">
                    <?= $dish['badge']['text'] ?>
                  </span>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>

<?php else: ?>
  <!-- ==========================================================
     4 THẺ CHỈ SỐ KPI DOANH THU CỐT LÕI
     ========================================================== -->
  <div class="revenue-stats-grid">
    <!-- Thẻ 1: Tổng doanh thu thực nhận -->
    <div class="revenue-stat-card">
      <div class="revenue-stat-head">
        <span><?= $viewMode === 'month' ? 'Doanh thu tháng ' . $report['month_display'] : 'Doanh thu ngày ' . $report['date_display'] ?></span>
        <?php
        $diff = $report['comparison']['diff_percent'];
        $isPos = $report['comparison']['direction'] === 'positive';
        ?>
        <span class="stat-trend-badge <?= $isPos ? 'positive' : 'negative' ?>">
          <?= $isPos ? '↗ +' : '↘ ' ?><?= abs($diff) ?>%
        </span>
      </div>
      <div class="revenue-stat-value"><?= formatVnd($report['total_revenue']) ?></div>
      <div class="revenue-stat-sub">
        Tạm tính: <b><?= formatVnd($report['total_subtotal']) ?></b> · Giảm giá: -<b><?= formatVnd($report['total_discount']) ?></b>
      </div>
      <small style="color: #94a3b8; font-size: 11px;">
        VAT & Phí DV: +<?= formatVnd($report['total_service_fee'] + $report['total_vat']) ?>
      </small>
    </div>

    <!-- Thẻ 2: Tổng số lượt hóa đơn & Giá trị TB/đơn -->
    <div class="revenue-stat-card">
      <div class="revenue-stat-head">
        <span>Số hóa đơn thanh toán</span>
        <span class="stat-trend-badge positive">TB <?= formatVnd($report['avg_order_value']) ?>/đơn</span>
      </div>
      <div class="revenue-stat-value"><?= number_format($report['total_invoices']) ?> <span style="font-size: 16px; font-weight: 600; color: #64748b;">hóa đơn</span></div>
      <div class="revenue-stat-sub">
        So với kỳ trước: <b><?= $report['comparison']['prev_invoices'] ?> đơn</b> (<?= $viewMode === 'month' ? 'Tháng trước' : ($viewMode === 'range' ? 'Kỳ trước' : 'Hôm trước') ?>)
      </div>
      <small style="color: #16a34a; font-size: 11px; font-weight: 600;">
        ✓ 100% đơn thanh toán hợp lệ
      </small>
    </div>

    <!-- Thẻ 3: Cơ cấu phương thức thanh toán -->
    <div class="revenue-stat-card">
      <div class="revenue-stat-head">
        <span>Cơ cấu thanh toán</span>
        <span style="font-size: 11px; font-weight: 700; color: #0284c7;">QR <?= $report['payment_methods']['qr']['percent'] ?>% · TM <?= $report['payment_methods']['cash']['percent'] ?>%</span>
      </div>
      <div class="revenue-stat-value" style="font-size: 20px;">
        <span style="color: #0284c7;"><?= formatVnd($report['payment_methods']['qr']['total']) ?></span>
        <span style="font-size: 13px; font-weight: normal; color: #94a3b8;">/</span>
        <span style="color: #ea580c; font-size: 16px;"><?= formatVnd($report['payment_methods']['cash']['total']) ?></span>
      </div>
      <div class="split-bar-wrap">
        <div class="split-bar-track">
          <div class="split-bar-qr" style="width: <?= $report['payment_methods']['qr']['percent'] ?>%;" title="Chuyển khoản QR: <?= $report['payment_methods']['qr']['percent'] ?>%"></div>
          <div class="split-bar-cash" style="width: <?= $report['payment_methods']['cash']['percent'] ?>%;" title="Tiền mặt: <?= $report['payment_methods']['cash']['percent'] ?>%"></div>
        </div>
        <div class="split-bar-labels">
          <span style="color: #0284c7;">● QR: <?= $report['payment_methods']['qr']['count'] ?> đơn</span>
          <span style="color: #ea580c;">● Tiền mặt: <?= $report['payment_methods']['cash']['count'] ?> đơn</span>
        </div>
      </div>
    </div>

    <!-- Thẻ 4: Món ăn phục vụ & Công suất bàn -->
    <div class="revenue-stat-card">
      <div class="revenue-stat-head">
        <span>Món bán & Công suất bàn</span>
        <span class="stat-trend-badge <?= $adminTableStats['occupancy_rate'] > 70 ? 'positive' : 'neutral' ?>">
          Bàn: <?= $adminTableStats['occupancy_rate'] ?>%
        </span>
      </div>
      <div class="revenue-stat-value"><?= number_format($report['total_items_sold']) ?> <span style="font-size: 16px; font-weight: 600; color: #64748b;">phần món</span></div>
      <div class="revenue-stat-sub">
        Hiện tại: <b><?= $adminTableStats['occupied_tables'] ?> / <?= $adminTableStats['active_tables'] ?></b> bàn đang có khách
      </div>
      <small style="color: #64748b; font-size: 11px;">
        <?= $adminTableStats['empty_tables'] ?> bàn trống sẵn sàng đón khách
      </small>
    </div>
  </div>

  <!-- BANNER LIÊN KẾT NHANH HIỆU SUẤT BẾP -->
  <div style="background: linear-gradient(135deg, #fff7ed 0%, #fef3c7 100%); border: 1px solid #fed7aa; border-radius: 10px; padding: 12px 18px; margin-bottom: 24px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
    <div style="display: flex; align-items: center; gap: 10px;">
      <span style="font-size: 20px;">🍳</span>
      <div>
        <b style="font-size: 13.5px; color: #9a3412;">Hiệu suất Nhà bếp hôm nay:</b>
        <span style="font-size: 13px; color: #7c2d12; margin-left: 6px;">
          Chế biến TB <b><?= $todayKitchenStats['avg_cook_minutes'] ?> phút/món</b> · <b><?= $todayKitchenStats['on_time_rate'] ?>% đúng giờ</b>
          <?php if ($todayKitchenStats['delayed_count'] > 0): ?>
            · <span style="color: #b91c1c; font-weight: 700;">(<?= $todayKitchenStats['delayed_count'] ?> món trễ &ge; 15p)</span>
          <?php endif; ?>
        </span>
      </div>
    </div>
    <a href="index.php?page=admin&mode=kitchen&start_date=<?= $todayStr ?>&end_date=<?= $todayStr ?>" class="btn btn-small btn-primary" style="background: #ea580c; border-color: #ea580c; font-size: 12px;">
      Xem báo cáo Bếp (KDS) →
    </a>
  </div>

  <!-- ==========================================================
     BIỂU ĐỒ SVG DOANH THU TRỰC QUAN (REVENUE VISUAL CHART)
     ========================================================== -->
  <section class="revenue-chart-panel">
    <div class="chart-header">
      <div class="chart-title">
        <h2>Biểu đồ Doanh thu <?= $viewMode === 'month' ? 'các ngày trong Tháng ' . $report['month_display'] : ($viewMode === 'range' ? 'các ngày trong ' . $report['date_display'] : 'theo khung giờ Ngày ' . $report['date_display'] . ' (' . $report['day_name'] . ')') ?></h2>
        <p>Cột biểu thị doanh thu thực nhận sau chiết khấu và thuế phí · Di chuột vào từng cột để xem chi tiết</p>
      </div>
      <div class="chart-legend">
        <div class="legend-item">
          <span class="legend-color" style="background: linear-gradient(180deg, #f97316 0%, #ea580c 100%);"></span>
          <span>Doanh thu</span>
        </div>
        <div class="legend-item">
          <span style="display: inline-block; width: 14px; border-bottom: 2px dashed #f59e0b;"></span>
          <span>Mức trung bình</span>
        </div>
      </div>
    </div>

    <?php
    // Tính toán tọa độ và mốc trục Y cho biểu đồ SVG
    $chartItems = ($viewMode === 'month' || $viewMode === 'range') ? $report['daily_data'] : $report['hourly_data'];
    $maxRevenue = 0;
    $sumRevenue = 0;
    $countWithRevenue = 0;

    foreach ($chartItems as $ci) {
      if ($ci['revenue'] > $maxRevenue) {
        $maxRevenue = $ci['revenue'];
      }
      if ($ci['revenue'] > 0) {
        $sumRevenue += $ci['revenue'];
        $countWithRevenue++;
      }
    }

    // Nếu không có doanh thu, gán mốc tối thiểu 1.000.000đ để hiển thị lưới trục Y đẹp
    if ($maxRevenue <= 0) {
      $maxRevenue = 1000000;
    }
    // Làm tròn mốc trần lên đẹp mắt (bội số)
    $magnitude = pow(10, max(0, strlen((string) ((int) $maxRevenue)) - 1));
    $ceilingRevenue = (int) (ceil($maxRevenue / $magnitude) * $magnitude);
    if ($ceilingRevenue <= 0) $ceilingRevenue = 1000000;

    $avgRev = $countWithRevenue > 0 ? (int) round($sumRevenue / $countWithRevenue) : 0;

    // Kích thước SVG
    $svgWidth = 840;
    $svgHeight = 250;
    $padLeft = 65;
    $padRight = 20;
    $padTop = 25;
    $padBottom = 40;
    $plotW = $svgWidth - $padLeft - $padRight;
    $plotH = $svgHeight - $padTop - $padBottom;

    $nItems = count($chartItems);
    $slotW = $plotW / max(1, $nItems);
    $barW = max(6, min(28, $slotW * 0.68));

    $avgY = $ceilingRevenue > 0 ? ($padTop + $plotH) - ($avgRev / $ceilingRevenue) * $plotH : ($padTop + $plotH);
    ?>

    <div class="chart-svg-container">
      <svg class="revenue-svg-chart" viewBox="0 0 <?= $svgWidth ?> <?= $svgHeight ?>" preserveAspectRatio="xMidYMid meet">
        <defs>
          <linearGradient id="barGradient" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0%" stop-color="#fb923c" />
            <stop offset="100%" stop-color="#ea580c" />
          </linearGradient>
          <linearGradient id="barGradientToday" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0%" stop-color="#38bdf8" />
            <stop offset="100%" stop-color="#0284c7" />
          </linearGradient>
        </defs>

        <!-- Đường lưới ngang (Gridlines) & Nhãn trục Y -->
        <?php for ($step = 0; $step <= 4; $step++):
          $stepVal = (int) round(($ceilingRevenue / 4) * $step);
          $yPos = ($padTop + $plotH) - ($step / 4) * $plotH;
          $labelVnd = $stepVal >= 1000000 ? round($stepVal / 1000000, 1) . 'tr' : ($stepVal > 0 ? ($stepVal / 1000) . 'k' : '0đ');
        ?>
          <line x1="<?= $padLeft ?>" y1="<?= $yPos ?>" x2="<?= $padLeft + $plotW ?>" y2="<?= $yPos ?>" stroke="#e2e8f0" stroke-dasharray="<?= $step > 0 ? '4 3' : 'none' ?>" stroke-width="1" />
          <text x="<?= $padLeft - 8 ?>" y="<?= $yPos + 4 ?>" text-anchor="end" font-size="11" font-weight="600" fill="#94a3b8"><?= $labelVnd ?></text>
        <?php endfor; ?>

        <!-- Đường trung bình (Average Reference Line) -->
        <?php if ($avgRev > 0 && $avgY >= $padTop && $avgY <= ($padTop + $plotH)): ?>
          <line x1="<?= $padLeft ?>" y1="<?= $avgY ?>" x2="<?= $padLeft + $plotW ?>" y2="<?= $avgY ?>" stroke="#f59e0b" stroke-dasharray="5 3" stroke-width="1.5" />
          <text x="<?= $padLeft + $plotW ?>" y="<?= $avgY - 5 ?>" text-anchor="end" font-size="10" font-weight="700" fill="#d97706">TB: <?= formatVnd($avgRev) ?></text>
        <?php endif; ?>

        <!-- Các cột doanh thu (Revenue Bars) -->
        <?php foreach ($chartItems as $i => $item):
          $val = $item['revenue'];
          $barH = $ceilingRevenue > 0 ? max(2, ($val / $ceilingRevenue) * $plotH) : 0;
          if ($val === 0) $barH = 0;
          $barX = $padLeft + $i * $slotW + ($slotW - $barW) / 2;
          $barY = ($padTop + $plotH) - $barH;

          $isHighlight = (($viewMode === 'month' || $viewMode === 'range') && isset($item['date']) && $item['date'] === $todayStr);
          $grad = $isHighlight ? 'url(#barGradientToday)' : 'url(#barGradient)';
          $invCount = $item['invoices'];

          $tooltipText = ($viewMode === 'month' || $viewMode === 'range')
            ? "Ngày {$item['label']} ({$item['day_name']}): " . formatVnd($val) . " ({$invCount} hóa đơn)"
            : "Khung giờ {$item['label']}: " . formatVnd($val) . " ({$invCount} hóa đơn)";
        ?>
          <?php if ($barH > 0): ?>
            <rect class="revenue-bar" x="<?= round($barX, 1) ?>" y="<?= round($barY, 1) ?>" width="<?= round($barW, 1) ?>" height="<?= round($barH, 1) ?>" rx="4" fill="<?= $grad ?>">
              <title><?= htmlspecialchars($tooltipText) ?></title>
            </rect>
          <?php else: ?>
            <!-- Vạch đánh dấu ngày 0 doanh thu -->
            <circle cx="<?= round($barX + $barW / 2, 1) ?>" cy="<?= $padTop + $plotH ?>" r="2" fill="#cbd5e1">
              <title><?= htmlspecialchars($tooltipText) ?></title>
            </circle>
          <?php endif; ?>

          <!-- Nhãn trục X dưới đáy -->
          <?php
          // Hiển thị nhãn thưa ra nếu có quá nhiều cột
          $showLabel = true;
          if (($viewMode === 'month' || $viewMode === 'range') && $nItems > 20) {
            $showLabel = ($item['day_num'] === 1 || $item['day_num'] % 3 === 0 || $item['day_num'] === $nItems);
          }
          ?>
          <?php if ($showLabel): ?>
            <text x="<?= round($barX + $barW / 2, 1) ?>" y="<?= $padTop + $plotH + 18 ?>" text-anchor="middle" font-size="10" font-weight="<?= $isHighlight ? '800' : '600' ?>" fill="<?= $isHighlight ? '#0284c7' : '#64748b' ?>">
              <?= htmlspecialchars($item['label']) ?>
            </text>
          <?php endif; ?>
        <?php endforeach; ?>
      </svg>
    </div>
  </section>
<?php endif; ?>

<!-- ==========================================================
     QUẢN LÝ TÌNH TRẠNG NGUYÊN LIỆU & BÁO HẾT MÓN (OUT OF STOCK MANAGEMENT)
     ========================================================== -->
<div class="admin-grid" style="margin-top: 24px;">
  <?php
  // Lấy danh sách các món ăn đang hiển thị (active & out_of_stock)
  $allManageDishes = getAllDishes('', 'visible', '', '');
  ?>
  <section class="panel unavailable-panel" id="menu-stock-management" style="grid-column: 1 / -1; margin-bottom: 24px;">
    <div class="unavailable-head" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; margin-bottom: 20px;">
      <div>
        <h2 style="font-size: 18px; margin-bottom: 4px;">Quản lý tình trạng nguyên liệu & món tại quán</h2>
        <p class="muted" style="margin: 0; font-size: 13.5px;">Tạm dừng nhận món khi bếp hết nguyên liệu hoặc đột xuất quá tải (Đồng bộ tức thì với Menu QR của thực khách)</p>
      </div>
      <div style="display: flex; align-items: center; gap: 10px;">
        <span class="badge-pill" style="background: #fff7ed; color: #ea580c; border: 1px solid #fed7aa; font-weight: 600;">Đồng bộ tự động với Menu QR</span>
        <a class="btn btn-small btn-outline" href="index.php?page=dishes">Quản lý thực đơn đầy đủ →</a>
      </div>
    </div>

    <div class="unavailable-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 14px;">
      <?php if (empty($allManageDishes)): ?>
        <p class="muted" style="padding: 16px;">Chưa có món ăn nào trong thực đơn.</p>
      <?php else: ?>
        <?php foreach ($allManageDishes as $dish): ?>
          <?php
          $isOut = ($dish['status'] === 'out_of_stock');
          ?>
          <div class="unavailable-row" style="background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 14px 18px; display: flex; align-items: center; justify-content: space-between; gap: 12px; box-shadow: 0 1px 2px rgba(0,0,0,0.03);">
            <div class="un-info" style="display: flex; flex-direction: column; gap: 4px;">
              <b style="font-size: 15px; color: var(--ink);"><?= htmlspecialchars($dish['name']) ?></b>
              <div style="display: flex; align-items: center; gap: 8px;">
                <span class="status status-<?= $isOut ? 'pending' : 'ready' ?>" style="font-size: 11.5px; padding: 2px 8px;">
                  <?= $isOut ? 'Đã tạm hết nguyên liệu' : 'Đang phục vụ bình thường' ?>
                </span>
                <small style="color: #64748b; font-size: 11.5px;"><?= formatPrice($dish['price']) ?> · <?= htmlspecialchars($dish['category_name']) ?></small>
              </div>
            </div>

            <form method="POST" action="index.php?page=admin&mode=<?= urlencode($viewMode) ?>" style="margin: 0;">
              <input type="hidden" name="action" value="toggle_dish_status">
              <input type="hidden" name="id" value="<?= $dish['id'] ?>">
              <?php if ($isOut): ?>
                <button type="submit" class="btn btn-small btn-success" data-toggle-dish-ajax="<?= $dish['id'] ?>" style="white-space: nowrap; font-size: 12.5px; padding: 6px 14px;">
                  Mở bán lại
                </button>
              <?php else: ?>
                <button type="submit" class="btn btn-small btn-outline-danger" data-toggle-dish-ajax="<?= $dish['id'] ?>" style="white-space: nowrap; font-size: 12.5px; padding: 6px 14px; border: 1px solid #fca5a5; color: #dc2626;">
                  Tạm ngừng bán
                </button>
              <?php endif; ?>
            </form>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </section>
</div>

<script>
  (() => {
    const panel = document.getElementById('admin-live-orders-panel');
    if (!panel) return;

    const bodyEl = document.getElementById('admin-live-orders-body');
    const badgeEl = document.getElementById('admin-live-orders-badge');
    const pollUrl = panel.dataset.pollUrl;
    const cancelUrl = panel.dataset.cancelUrl;

    const showMsg = (msg) => {
      if (typeof notify === 'function') notify(msg);
    };

    const refreshLiveOrders = async () => {
      try {
        const res = await fetch(pollUrl, {
          headers: {
            Accept: 'application/json'
          },
          cache: 'no-store'
        });
        if (!res.ok) return false;
        const data = await res.json();
        if (bodyEl && typeof data.html === 'string') {
          bodyEl.innerHTML = data.html;
        }
        if (badgeEl && typeof data.table_count === 'number') {
          badgeEl.textContent = `${data.table_count} bàn đang mở`;
        }
        return true;
      } catch (e) {
        return false;
      }
    };

    panel.addEventListener('click', async (event) => {
      if (event.target.closest('[data-admin-orders-refresh]')) {
        const ok = await refreshLiveOrders();
        showMsg(ok ? 'Đã cập nhật danh sách món tại bàn' : 'Không tải được dữ liệu mới');
        return;
      }

      const cancelBtn = event.target.closest('[data-admin-cancel-item]');
      if (!cancelBtn || cancelBtn.disabled) return;

      const dishName = cancelBtn.dataset.itemName || 'món này';
      if (!confirm(`Hủy món "${dishName}"?\nMón sẽ lập tức biến mất khỏi Admin, Staff và Bếp.`)) return;

      cancelBtn.disabled = true;
      const body = new FormData();
      body.set('action', 'cancel');
      body.set('item_id', cancelBtn.dataset.adminCancelItem);

      try {
        const res = await fetch(cancelUrl, {
          method: 'POST',
          body
        });
        const data = await res.json();
        if (!res.ok) {
          showMsg(data.error || 'Không hủy được món');
        } else {
          showMsg(`Đã hủy món "${dishName}"`);
        }
      } catch (e) {
        showMsg('Không thể kết nối máy chủ.');
      }

      await refreshLiveOrders();
    });

    setInterval(() => {
      if (!document.hidden) refreshLiveOrders();
    }, 5000);
  })();
</script>
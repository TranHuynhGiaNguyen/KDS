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
require_once __DIR__ . '/../includes/shift-reconciliation.php';
require_once __DIR__ . '/../includes/excel-report.php';
require_once __DIR__ . '/../includes/print-bill-report.php';

// Xử lý xuất file Excel (bảng kẻ .xlsx) hoặc CSV thuần nếu có yêu cầu
$exportFormat = $_GET['export'] ?? '';
if (in_array($exportFormat, ['csv', 'excel', 'xlsx'], true)) {
  $pdo = getRevenueDb();
  $viewMode = $_GET['mode'] ?? 'day';
  if (!in_array($viewMode, ['day', 'month', 'range', 'kitchen', 'reconcile'], true)) {
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

  $payload = ['start_date' => $startDate, 'end_date' => $endDate];
  if ($viewMode === 'reconcile') {
    $payload['reconcile'] = getShiftReconciliationReport($pdo, $selectedDate);
  } elseif ($viewMode === 'kitchen') {
    $payload['kitchen'] = getKitchenPerformanceReport($startDate, $endDate);
  } elseif ($viewMode === 'range') {
    $payload['report'] = getRevenueByRange($pdo, $startDate, $endDate);
  } elseif ($viewMode === 'month') {
    $payload['report'] = getRevenueByMonth($pdo, $selectedMonth);
    $payload['month_display'] = $payload['report']['month_display'] ?? '';
  } else {
    $payload['report'] = getRevenueByDate($pdo, $selectedDate);
    $payload['date_display'] = $payload['report']['date_display'] ?? '';
  }

  if (isset($payload['report']) && !isset($payload['date_display'])) {
    $payload['date_display'] = $payload['report']['date_display'] ?? '';
  }

  if ($exportFormat === 'excel' || $exportFormat === 'xlsx') {
    exportAdminReportExcel($viewMode, $payload);
  } elseif ($viewMode === 'reconcile') {
    exportShiftReconciliationCsv($payload['reconcile']);
  } elseif ($viewMode === 'kitchen') {
    exportKitchenCsv($payload['kitchen'], $startDate, $endDate);
  } else {
    exportRevenueCsv($payload['report']);
  }
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

// Xác định chế độ xem: theo ngày (day), theo tháng (month), khoảng ngày (range), hiệu suất bếp (kitchen) hay đối chiếu thực xuất kết ca (reconcile)
$viewMode = $_GET['mode'] ?? 'day';
if (!in_array($viewMode, ['day', 'month', 'range', 'kitchen', 'reconcile'], true)) {
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
if ($viewMode === 'reconcile') {
  $reconcileReport = getShiftReconciliationReport($pdo, $selectedDate);
} elseif ($viewMode === 'kitchen') {
  $kitchenReport = getKitchenPerformanceReport($startDate, $endDate);
  $report = getRevenueByRange($pdo, $startDate, $endDate);
} elseif ($viewMode === 'range') {
  $report = getRevenueByRange($pdo, $startDate, $endDate);
} elseif ($viewMode === 'month') {
  $report = getRevenueByMonth($pdo, $selectedMonth);
} else {
  $report = getRevenueByDate($pdo, $selectedDate);
}

$currentActor = currentUser();
$printerName = trim((string) ($currentActor['fullname'] ?? ($currentActor['username'] ?? ''))) ?: 'Quản trị viên';

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

<div class="screen-report-ui">
<!-- HEADER CHÍNH CỦA DASHBOARD -->
<div class="dash-header">
  <div>
    <span class="eyebrow">
      <?= $viewMode === 'reconcile' ? 'END-OF-DAY SHIFT CLOSING & DAILY WAREHOUSE STOCK' : ($viewMode === 'kitchen' ? 'KITCHEN PERFORMANCE & EFFICIENCY DASHBOARD' : 'SMARTDINE MANAGEMENT & REVENUE DASHBOARD') ?>
    </span>
    <h1>
      <?= $viewMode === 'reconcile' ? 'Báo cáo Món thực xuất cuối ngày (Kết ca)' : ($viewMode === 'kitchen' ? 'Báo cáo Hiệu suất Bếp (Kitchen Metrics)' : 'Tổng quan & Báo cáo Doanh thu') ?>
    </h1>
    <p>
      <?= $viewMode === 'reconcile' ? 'Tổng hợp lượng món thực xuất trên KDS cuối ngày để đối chiếu với số hàng tồn lại theo ngày trong Warehouse.' : ($viewMode === 'kitchen' ? 'Thống kê thời gian chế biến trung bình, cảnh báo các món bị trễ giờ và phân tích tải theo từng quầy bếp.' : 'Theo dõi chi tiết hiệu quả kinh doanh, doanh thu theo ngày / tháng / khoảng ngày và phân bổ vận hành nhà hàng.') ?>
    </p>
  </div>
</div>

<!-- ==========================================================
     BỘ LỌC DOANH THU THEO NGÀY / THÁNG / KHOẢNG NGÀY & CÔNG CỤ
     ========================================================== -->
<section class="revenue-filter-toolbar">
  <!-- Tabs chuyển chế độ -->
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
    <a class="revenue-tab-btn <?= $viewMode === 'reconcile' ? 'active' : '' ?>" href="index.php?page=admin&mode=reconcile&date=<?= htmlspecialchars($selectedDate) ?>">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M9 11l3 3L22 4"></path>
        <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path>
      </svg>
      <span>Đối chiếu thực xuất</span>
    </a>
  </div>

  <!-- Form chọn ngày / tháng / khoảng cụ thể -->
  <form class="revenue-filter-form" method="get" action="index.php">
    <input type="hidden" name="page" value="admin">
    <input type="hidden" name="mode" value="<?= htmlspecialchars($viewMode) ?>">

    <?php if ($viewMode === 'day' || $viewMode === 'reconcile'): ?>
      <input type="date" name="date" value="<?= htmlspecialchars($selectedDate) ?>" aria-label="Chọn ngày báo cáo">
      <div class="quick-filter-chips">
        <a class="quick-chip <?= $selectedDate === $todayStr ? 'active' : '' ?>" href="index.php?page=admin&mode=<?= htmlspecialchars($viewMode) ?>&date=<?= $todayStr ?>">Hôm nay</a>
        <a class="quick-chip <?= $selectedDate === $yesterdayStr ? 'active' : '' ?>" href="index.php?page=admin&mode=<?= htmlspecialchars($viewMode) ?>&date=<?= $yesterdayStr ?>">Hôm qua</a>
        <a class="quick-chip <?= $selectedDate === $sevenDaysAgoStr ? 'active' : '' ?>" href="index.php?page=admin&mode=<?= htmlspecialchars($viewMode) ?>&date=<?= $sevenDaysAgoStr ?>">7 ngày trước (<?= date('d/m', strtotime('-7 days')) ?>)</a>
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

  <!-- Nút Thao tác: Xuất CSV, In báo cáo & Báo cáo thực xuất kết ca -->
  <div class="revenue-actions">
    <?php
    $exportParams = 'mode=' . urlencode($viewMode);
    if ($viewMode === 'range' || $viewMode === 'kitchen') {
      $exportParams .= '&start_date=' . urlencode($startDate) . '&end_date=' . urlencode($endDate);
    } elseif ($viewMode === 'month') {
      $exportParams .= '&month=' . urlencode($selectedMonth);
    } else {
      $exportParams .= '&date=' . urlencode($selectedDate);
    }
    ?>
    <a class="btn btn-small btn-outline" href="index.php?page=admin&export=excel&<?= $exportParams ?>" title="Xuất báo cáo dạng bảng Excel (.xlsx) có viền kẻ rõ ràng">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
        <polyline points="7 10 12 15 17 10"></polyline>
        <line x1="12" y1="15" x2="12" y2="3"></line>
      </svg>
      <span>Xuất Excel (Bảng kẻ)</span>
    </a>
    <button type="button" class="btn btn-small btn-outline" onclick="window.print()" title="In báo cáo ra máy in hoặc lưu PDF">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <polyline points="6 9 6 2 18 2 18 9"></polyline>
        <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
        <rect x="6" y="14" width="12" height="8"></rect>
      </svg>
      <span>In báo cáo</span>
    </button>
    <a class="btn btn-small btn-primary-tool" href="index.php?page=admin&mode=reconcile&date=<?= htmlspecialchars($selectedDate) ?>" title="Báo cáo món thực xuất cuối ngày lúc kết ca để đối chiếu với lượng bán ra ở trong kho">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M9 11l3 3L22 4"></path>
        <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path>
      </svg>
      <span>Báo cáo thực xuất (Kết ca)</span>
    </a>
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

<?php elseif ($viewMode === 'reconcile'): ?>
  <!-- ==========================================================
       BÁO CÁO MÓN THỰC XUẤT ĐÃ BÁN TRONG NGÀY THEO DANH MỤC & TỒN KHO
       ========================================================== -->
  <?php if (!$reconcileReport['warehouse_ready']): ?>
    <div class="flash-alert flash-error">
      Chưa phát hiện bảng Kho hàng (<code>products</code> / <code>stock_transactions</code>) trong cơ sở dữ liệu để đối chiếu.
    </div>
  <?php elseif (empty($reconcileReport['groups'])): ?>
    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 14px 18px; margin-bottom: 20px; color: #475569; font-size: 13.5px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
      <span>Chưa có món nào bán được trong ngày <b><?= htmlspecialchars($reconcileReport['date_display']) ?></b>.</span>
      <a href="warehouse/inventory/daily_stock.php?date=<?= htmlspecialchars($selectedDate) ?>" class="btn btn-small btn-outline">Mở Tồn theo ngày (Kho) →</a>
    </div>
  <?php elseif ($reconcileReport['is_fully_matched']): ?>
    <div style="background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; border-radius: 10px; padding: 14px 18px; margin-bottom: 20px; font-size: 13.5px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
      <span><b>✓ Đã chốt tồn &amp; khớp hoàn toàn!</b> Tất cả <b><?= count($reconcileReport['items']) ?></b> món bán được trong ngày đã chốt tồn trong Kho khớp với lượng bán ra KDS.</span>
      <a href="warehouse/inventory/daily_stock.php?date=<?= htmlspecialchars($selectedDate) ?>" class="btn btn-small btn-outline">Xem Tồn theo ngày (Kho) →</a>
    </div>
  <?php else: ?>
    <div style="background: #fffbeb; border: 1px solid #fde68a; color: #92400e; border-radius: 10px; padding: 14px 18px; margin-bottom: 20px; font-size: 13.5px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
      <span>
        <b>📋 Đối chiếu kết ca theo món đã bán hôm nay:</b>
        Tổng <b><?= count($reconcileReport['items']) ?></b> món bán được ·
        Đã chốt tồn Kho <b><?= $reconcileReport['closed_snapshot_count'] ?> / <?= $reconcileReport['tracked_in_warehouse_count'] ?></b> món có trong Kho.
      </span>
      <a href="warehouse/inventory/daily_stock.php?date=<?= htmlspecialchars($selectedDate) ?>" class="btn btn-small btn-primary">Mở Tồn theo ngày &amp; Chốt tồn →</a>
    </div>
  <?php endif; ?>

  <?php if ($reconcileReport['total_kds_pending_open_qty'] > 0): ?>
    <div style="background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; border-radius: 10px; padding: 12px 18px; margin-bottom: 20px; font-size: 13px;">
      <b>⏳ Lưu ý trước khi chốt ca:</b> Vẫn còn <b><?= number_format($reconcileReport['total_kds_pending_open_qty']) ?></b> phần món đang ở trạng thái chờ/đang nấu trên bàn mở (chưa tính vào mục đã bán).
    </div>
  <?php endif; ?>

  <div class="revenue-stats-grid">
    <div class="revenue-stat-card" style="border-top: 4px solid #ea580c;">
      <div class="revenue-stat-head">
        <span>Tổng món đã bán hôm nay</span>
        <span class="stat-trend-badge neutral"><?= htmlspecialchars($reconcileReport['date_display']) ?></span>
      </div>
      <div class="revenue-stat-value"><?= number_format($reconcileReport['total_kds_actual_qty']) ?> <span style="font-size: 16px; font-weight: 600; color: #64748b;">phần</span></div>
      <div class="revenue-stat-sub">
        Gồm <b><?= count($reconcileReport['items']) ?></b> món chia theo <b><?= count($reconcileReport['groups']) ?></b> danh mục
      </div>
      <small style="color: #64748b; font-size: 11px;">Chỉ thống kê những món thực sự bán được trong ngày</small>
    </div>

    <div class="revenue-stat-card" style="border-top: 4px solid #0284c7;">
      <div class="revenue-stat-head">
        <span>Tồn đầu + Nhập (Kho)</span>
        <span class="stat-trend-badge neutral">Warehouse</span>
      </div>
      <div class="revenue-stat-value" style="color: #0284c7;"><?= number_format($reconcileReport['total_warehouse_available_qty']) ?> <span style="font-size: 16px; font-weight: 600; color: #64748b;">đơn vị</span></div>
      <div class="revenue-stat-sub">
        Tổng nguồn kho của các món đã bán hôm nay
      </div>
      <small style="color: #64748b; font-size: 11px;">Sản phẩm khớp danh mục kho: <?= number_format($reconcileReport['tracked_in_warehouse_count']) ?></small>
    </div>

    <div class="revenue-stat-card" style="border-top: 4px solid #16a34a;">
      <div class="revenue-stat-head">
        <span>Tồn lại cuối ngày (Kho)</span>
        <span class="stat-trend-badge positive">Hàng tồn</span>
      </div>
      <div class="revenue-stat-value" style="color: #16a34a;"><?= number_format($reconcileReport['total_warehouse_closing_qty']) ?> <span style="font-size: 16px; font-weight: 600; color: #64748b;">đơn vị</span></div>
      <div class="revenue-stat-sub">
        Số hàng tồn lại trong ngày để đối chiếu lượng bán ra
      </div>
      <small style="color: #64748b; font-size: 11px;">Đã chốt cuối ngày: <?= number_format($reconcileReport['closed_snapshot_count']) ?> món</small>
    </div>

    <div class="revenue-stat-card" style="border-top: 4px solid #64748b;">
      <div class="revenue-stat-head">
        <span>Tổng hợp theo danh mục</span>
        <span class="stat-trend-badge <?= $reconcileReport['mismatch_count'] === 0 ? 'positive' : 'warning' ?>">
          <?= $reconcileReport['matched_count'] ?> khớp
        </span>
      </div>
      <div class="revenue-stat-sub" style="display: flex; flex-wrap: wrap; gap: 6px; margin-top: 6px;">
        <?php if (empty($reconcileReport['groups'])): ?>
          <span>Chưa có dữ liệu nhóm món</span>
        <?php else: ?>
          <?php foreach ($reconcileReport['groups'] as $grp): ?>
            <span class="badge-method qr" style="font-size: 11px;"><?= htmlspecialchars($grp['group_label']) ?>: <?= number_format((int) $grp['total_kds_qty']) ?></span>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
      <small style="color: #64748b; font-size: 11px; margin-top: 8px; display: block;">Chưa có trong Kho: <?= number_format($reconcileReport['unmapped_count']) ?> món</small>
    </div>
  </div>

  <section class="panel" style="margin-bottom: 24px;">
    <div class="panel-title">
      <div>
        <h2>Bảng báo cáo món đã bán hôm nay theo danh mục (<?= htmlspecialchars($reconcileReport['date_display']) ?>)</h2>
        <p class="muted">Chỉ hiển thị những món đã bán được trong ngày, phân nhóm theo Bếp nóng, Rau / Salad, Bếp súp, Quầy Bar và đối chiếu tồn cuối ngày</p>
      </div>
    </div>

    <div class="table-responsive">
      <table class="revenue-table">
        <thead>
          <tr>
            <th>#</th>
            <th>Tên món đã bán</th>
            <th>Danh mục / SKU</th>
            <th class="num-col">Thực xuất KDS</th>
            <th class="num-col">Tồn đầu + Nhập (Kho)</th>
            <th class="num-col">Tồn lại cuối ngày (Kho)</th>
            <th>Trạng thái đối chiếu</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($reconcileReport['groups'])): ?>
            <tr>
              <td colspan="7" style="padding: 30px; text-align: center; color: #94a3b8;">
                Hôm nay (<?= htmlspecialchars($reconcileReport['date_display']) ?>) chưa có món nào bán được trên KDS.
              </td>
            </tr>
          <?php else: ?>
            <?php $rowIdx = 1; ?>
            <?php foreach ($reconcileReport['groups'] as $group): ?>
              <tr style="background: #f8fafc;">
                <td colspan="7" style="font-weight: 800; color: #0f172a; padding: 10px 14px; border-top: 2px solid #e2e8f0;">
                  📂 <?= htmlspecialchars($group['group_label']) ?>
                  <span style="font-weight: 600; color: #64748b; font-size: 12px; margin-left: 8px;">(<?= count($group['items']) ?> món · Tổng bán: <?= number_format((int) $group['total_kds_qty']) ?> phần)</span>
                </td>
              </tr>
              <?php foreach ($group['items'] as $item): ?>
                <tr>
                  <td><?= $rowIdx++ ?></td>
                  <td>
                    <b><?= htmlspecialchars($item['item_name']) ?></b>
                    <?php if ((int) $item['pending_open_qty'] > 0): ?>
                      <div style="font-size: 11px; color: #b91c1c;">⏳ Còn <?= (int) $item['pending_open_qty'] ?> phần đang chờ trên bàn mở</div>
                    <?php endif; ?>
                  </td>
                  <td>
                    <?php if ($item['sku'] !== ''): ?>
                      <span class="badge-method qr"><?= htmlspecialchars($item['sku']) ?></span>
                    <?php endif; ?>
                    <span style="font-size: 12px; color: #64748b;">
                      <?= htmlspecialchars($item['category_name'] !== '' ? $item['category_name'] : $group['group_label']) ?>
                    </span>
                  </td>
                  <td class="num-col"><b><?= number_format($item['kds_actual_qty']) ?></b></td>
                  <td class="num-col">
                    <?php if ($item['has_product']): ?>
                      <b><?= number_format((int) $item['warehouse_available_qty']) ?></b>
                      <div style="font-size: 11px; color: #64748b;">Đầu: <?= (int) $item['warehouse_opening_qty'] ?> · Nhập: +<?= (int) $item['warehouse_in_qty'] ?></div>
                    <?php else: ?>
                      <span style="color: #94a3b8;">—</span>
                    <?php endif; ?>
                  </td>
                  <td class="num-col">
                    <?php if ($item['warehouse_closing_qty'] !== null): ?>
                      <b><?= number_format((int) $item['warehouse_closing_qty']) ?></b>
                      <?php if (!$item['is_snapshot_closed']): ?>
                        <div style="font-size: 11px; color: #64748b;">Dự kiến sau bán: <?= number_format((int) $item['warehouse_expected_qty']) ?></div>
                      <?php else: ?>
                        <div style="font-size: 11px; color: #16a34a;">Đã chốt tồn (Tiêu hao: <?= number_format((int) $item['warehouse_consumed_qty']) ?>)</div>
                      <?php endif; ?>
                    <?php else: ?>
                      <span style="color: #94a3b8;">—</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <span class="stat-trend-badge <?= htmlspecialchars($item['badge_class']) ?>">
                      <?= htmlspecialchars($item['status_label']) ?>
                    </span>
                    <?php if ($item['has_product'] && !$item['is_snapshot_closed']): ?>
                      <a href="warehouse/inventory/daily_stock.php?date=<?= htmlspecialchars($selectedDate) ?>" style="margin-left: 6px; font-size: 11.5px; color: #ea580c; font-weight: 700; text-decoration: none;">Chốt tồn →</a>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
              <tr style="background: #fff7ed; font-weight: 700;">
                <td colspan="3" style="text-align: right; color: #9a3412;">Cộng nhóm <?= htmlspecialchars($group['group_label']) ?></td>
                <td class="num-col" style="color: #9a3412;"><?= number_format((int) $group['total_kds_qty']) ?></td>
                <td class="num-col" style="color: #9a3412;"><?= number_format((int) $group['total_available_qty']) ?></td>
                <td class="num-col" style="color: #9a3412;"><?= number_format((int) $group['total_closing_qty']) ?></td>
                <td></td>
              </tr>
            <?php endforeach; ?>
            <tr class="total-row">
              <td colspan="3">TỔNG CỘNG MÓN ĐÃ BÁN HÔM NAY</td>
              <td class="num-col"><?= number_format($reconcileReport['total_kds_actual_qty']) ?></td>
              <td class="num-col"><?= number_format($reconcileReport['total_warehouse_available_qty']) ?></td>
              <td class="num-col"><?= number_format($reconcileReport['total_warehouse_closing_qty']) ?></td>
              <td><?= $reconcileReport['is_fully_matched'] ? 'Khớp toàn bộ' : 'Theo dõi cuối ngày' ?></td>
            </tr>
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
</div>

<?= renderPureTextPrintBillReport($viewMode, [
  'report'        => $report ?? [],
  'kitchen'       => $kitchenReport ?? [],
  'reconcile'     => $reconcileReport ?? [],
  'selected_date' => $selectedDate,
  'start_date'    => $startDate,
  'end_date'      => $endDate,
  'printer_name'  => $printerName,
]) ?>

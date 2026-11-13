<?php
/**
 * SmartDine - Bảng điều khiển Quản trị & Dashboard Doanh thu
 * Thống kê doanh thu theo ngày, tháng, khu vực, phương thức thanh toán và top món ăn.
 */

require_once __DIR__ . '/../includes/tables.php';
require_once __DIR__ . '/../includes/revenue.php';

// Xử lý xuất file CSV nếu có yêu cầu
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $pdo = getRevenueDb();
    $viewMode = ($_GET['mode'] ?? 'day') === 'month' ? 'month' : 'day';
    $selectedDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date'] ?? '') ? $_GET['date'] : date('Y-m-d');
    $selectedMonth = preg_match('/^\d{4}-\d{2}$/', $_GET['month'] ?? '') ? $_GET['month'] : date('Y-m');
    $report = $viewMode === 'month' ? getRevenueByMonth($pdo, $selectedMonth) : getRevenueByDate($pdo, $selectedDate);
    exportRevenueCsv($report);
    exit;
}

$pdo = getRevenueDb();
$adminTableStats = getTableStats();

// Xác định chế độ xem: theo ngày (day) hay theo tháng (month)
$viewMode = ($_GET['mode'] ?? 'day') === 'month' ? 'month' : 'day';
$selectedDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date'] ?? '') ? $_GET['date'] : date('Y-m-d');
$selectedMonth = preg_match('/^\d{4}-\d{2}$/', $_GET['month'] ?? '') ? $_GET['month'] : date('Y-m');

// Lấy dữ liệu báo cáo tương ứng
$report = $viewMode === 'month' ? getRevenueByMonth($pdo, $selectedMonth) : getRevenueByDate($pdo, $selectedDate);

// Các mốc ngày nhanh
$todayStr = date('Y-m-d');
$yesterdayStr = date('Y-m-d', strtotime('-1 day'));
$thisMonthStr = date('Y-m');
$prevMonthStr = date('Y-m', strtotime('-1 month'));
?>

<!-- Header in ấn A4 (Chỉ hiển thị khi in hoặc lưu PDF) -->
<div class="print-header">
  <h1 style="margin: 0 0 6px; font-size: 20pt;">SMARTDINE RESTAURANT — BÁO CÁO DOANH THU</h1>
  <p style="margin: 0; color: #555;">
    Thời gian thống kê: <b><?= $viewMode === 'month' ? 'Tháng ' . $report['month_display'] : 'Ngày ' . $report['date_display'] . ' (' . $report['day_name'] . ')' ?></b>
    · Xuất lúc: <?= date('d/m/Y H:i:s') ?> · Người lập: Quản trị viên
  </p>
</div>

<!-- HEADER CHÍNH CỦA DASHBOARD -->
<div class="dash-header">
  <div>
    <span class="eyebrow">SMARTDINE MANAGEMENT & REVENUE DASHBOARD</span>
    <h1>Tổng quan & Báo cáo Doanh thu</h1>
    <p>Theo dõi chi tiết hiệu quả kinh doanh, doanh thu theo ngày / tháng và phân bổ vận hành nhà hàng.</p>
  </div>
  <div class="filter-row">
    <a class="btn btn-outline" href="index.php?page=users">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
      <span>Quản lý nhân viên</span>
    </a>
    <a class="btn btn-outline" href="index.php?page=dishes">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 2v7c0 1.1.9 2 2 2h4a2 2 0 0 0 2-2V2"></path><path d="M7 2v20"></path><path d="M21 15V2v0a5 5 0 0 0-5 5v6c0 1.1.9 2 2 2h3Zm0 0v7"></path></svg>
      <span>Quản lý thực đơn</span>
    </a>
    <a class="btn btn-outline" href="index.php?page=tables">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"></rect><path d="M3 9h18M9 21V9"></path></svg>
      <span>Quản lý bàn & khu vực</span>
    </a>
    <a class="btn btn-primary-tool" href="index.php?page=kitchen">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 13.8V4a2 2 0 1 1 4 0v9.8"></path><path d="M14 13.8V4a2 2 0 1 1 4 0v9.8"></path><path d="M3 14h18a2 2 0 0 1 2 2v1a4 4 0 0 1-4 4H5a4 4 0 0 1-4-4v-1a2 2 0 0 1 2-2z"></path></svg>
      <span>Màn hình bếp KDS</span>
    </a>
  </div>
</div>

<!-- ==========================================================
     BỘ LỌC DOANH THU THEO NGÀY / THÁNG & CÔNG CỤ
     ========================================================== -->
<section class="revenue-filter-toolbar">
  <!-- Tabs chuyển chế độ: Theo ngày vs Theo tháng -->
  <div class="revenue-view-tabs">
    <a class="revenue-tab-btn <?= $viewMode === 'day' ? 'active' : '' ?>" href="index.php?page=admin&mode=day&date=<?= htmlspecialchars($selectedDate) ?>">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
      <span>Theo ngày</span>
    </a>
    <a class="revenue-tab-btn <?= $viewMode === 'month' ? 'active' : '' ?>" href="index.php?page=admin&mode=month&month=<?= htmlspecialchars($selectedMonth) ?>">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line></svg>
      <span>Theo tháng</span>
    </a>
  </div>

  <!-- Form chọn ngày / tháng cụ thể -->
  <form class="revenue-filter-form" method="get" action="index.php">
    <input type="hidden" name="page" value="admin">
    <input type="hidden" name="mode" value="<?= htmlspecialchars($viewMode) ?>">

    <?php if ($viewMode === 'day'): ?>
      <input type="date" name="date" value="<?= htmlspecialchars($selectedDate) ?>" aria-label="Chọn ngày xem doanh thu">
      <div class="quick-filter-chips">
        <a class="quick-chip <?= $selectedDate === $todayStr ? 'active' : '' ?>" href="index.php?page=admin&mode=day&date=<?= $todayStr ?>">Hôm nay</a>
        <a class="quick-chip <?= $selectedDate === $yesterdayStr ? 'active' : '' ?>" href="index.php?page=admin&mode=day&date=<?= $yesterdayStr ?>">Hôm qua</a>
        <a class="quick-chip <?= $selectedDate === '2026-11-10' ? 'active' : '' ?>" href="index.php?page=admin&mode=day&date=2026-11-10">10/11</a>
      </div>
    <?php else: ?>
      <input type="month" name="month" value="<?= htmlspecialchars($selectedMonth) ?>" aria-label="Chọn tháng xem doanh thu">
      <div class="quick-filter-chips">
        <a class="quick-chip <?= $selectedMonth === $thisMonthStr ? 'active' : '' ?>" href="index.php?page=admin&mode=month&month=<?= $thisMonthStr ?>">Tháng này (11/2026)</a>
        <a class="quick-chip <?= $selectedMonth === $prevMonthStr ? 'active' : '' ?>" href="index.php?page=admin&mode=month&month=<?= $prevMonthStr ?>">Tháng trước (10/2026)</a>
      </div>
    <?php endif; ?>

    <button type="submit" class="btn btn-small btn-primary">Lọc dữ liệu</button>
  </form>

  <!-- Nút Thao tác: Xuất CSV & In báo cáo -->
  <div class="revenue-actions">
    <a class="btn btn-small btn-outline" href="index.php?page=admin&export=csv&mode=<?= $viewMode ?>&<?= $viewMode === 'month' ? 'month=' . $selectedMonth : 'date=' . $selectedDate ?>" title="Xuất báo cáo sang file Excel CSV">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
      <span>Xuất CSV (Excel)</span>
    </a>
    <button type="button" class="btn btn-small btn-outline" onclick="window.print()" title="In báo cáo doanh thu ra máy in hoặc lưu PDF">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
      <span>In báo cáo</span>
    </button>
  </div>
</section>

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
      So với kỳ trước: <b><?= $report['comparison']['prev_invoices'] ?> đơn</b> (<?= $viewMode === 'month' ? 'Tháng trước' : 'Hôm trước' ?>)
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

<!-- ==========================================================
     BIỂU ĐỒ SVG DOANH THU TRỰC QUAN (REVENUE VISUAL CHART)
     ========================================================== -->
<section class="revenue-chart-panel">
  <div class="chart-header">
    <div class="chart-title">
      <h2>Biểu đồ Doanh thu <?= $viewMode === 'month' ? 'các ngày trong Tháng ' . $report['month_display'] : 'theo khung giờ Ngày ' . $report['date_display'] . ' (' . $report['day_name'] . ')' ?></h2>
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
  $chartItems = $viewMode === 'month' ? $report['daily_data'] : $report['hourly_data'];
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

        $isHighlight = ($viewMode === 'month' && isset($item['date']) && $item['date'] === $todayStr);
        $grad = $isHighlight ? 'url(#barGradientToday)' : 'url(#barGradient)';
        $invCount = $item['invoices'];

        $tooltipText = $viewMode === 'month'
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
        // Hiển thị nhãn thưa ra nếu có quá nhiều cột trong tháng
        $showLabel = true;
        if ($viewMode === 'month' && $nItems > 20) {
            $showLabel = ($item['day_num'] === 1 || $item['day_num'] % 3 === 0 || $item['day_num'] === $nItems);
        }
        ?>
        <?php if ($showLabel): ?>
          <text x="<?= round($barX + $barW / 2, 1) ?>" y="<?= $padTop + $plotH + 18 ?>" text-anchor="middle" font-size="10" font-weight="<?= $isHighlight ? '800' : '600' ?>" fill="<?= $isHighlight ? '#0284c7' : '#64748b' ?>">
            <?= htmlspecialchars($viewMode === 'month' ? sprintf('%02d', $item['day_num']) : $item['label']) ?>
          </text>
        <?php endif; ?>
      <?php endforeach; ?>
    </svg>
  </div>
</section>

<!-- ==========================================================
     HAI CỘT PHÂN TÍCH: TOP MÓN BÁN CHẠY & THEO KHU VỰC
     ========================================================== -->
<div class="revenue-two-col">
  <!-- Cột 1: Top 5 món bán chạy nhất -->
  <section class="panel">
    <div class="panel-title">
      <div>
        <h2>Top 5 món bán chạy nhất</h2>
        <p class="muted">Dựa trên doanh thu và số lượt gọi món trong <?= $viewMode === 'month' ? 'tháng ' . $report['month_display'] : 'ngày ' . $report['date_display'] ?></p>
      </div>
      <span class="badge-pill"><?= $viewMode === 'month' ? 'Tháng này' : 'Ngày này' ?></span>
    </div>

    <div class="rank-list">
      <?php if (empty($report['top_dishes'])): ?>
        <p class="muted" style="padding: 16px;">Chưa có dữ liệu món bán trong khoảng thời gian này.</p>
      <?php else: ?>
        <?php foreach ($report['top_dishes'] as $idx => $dish): ?>
          <div class="rank-row">
            <span class="rank-number"><?= $idx + 1 ?></span>
            <div class="rank-meta grow">
              <div class="rank-text-row">
                <b><?= htmlspecialchars($dish['item_name']) ?></b>
                <span class="rank-qty"><b><?= $dish['total_qty'] ?></b> phần · <b style="color: #ea580c;"><?= formatVnd($dish['total_amount']) ?></b></span>
              </div>
              <div class="progress-track">
                <div class="progress-fill" style="width: <?= $dish['percentage'] ?>%;"></div>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </section>

  <!-- Cột 2: Phân bổ Doanh thu theo Khu vực Bàn -->
  <section class="panel">
    <div class="panel-title">
      <div>
        <h2>Doanh thu theo Khu vực Nhà hàng</h2>
        <p class="muted">Tỷ lệ đóng góp doanh thu giữa các khu vực phục vụ</p>
      </div>
      <span class="badge-pill"><?= count($report['area_breakdown']) ?> khu vực</span>
    </div>

    <div class="area-breakdown-list" style="padding-top: 6px;">
      <?php if (empty($report['area_breakdown'])): ?>
        <p class="muted" style="padding: 16px;">Chưa có dữ liệu doanh thu khu vực.</p>
      <?php else: ?>
        <?php foreach ($report['area_breakdown'] as $ar): ?>
          <div class="area-list-item">
            <div class="area-meta-row">
              <div class="area-name-wrap">
                <span class="area-dot <?= htmlspecialchars($ar['area_code']) ?>"></span>
                <b><?= htmlspecialchars($ar['area_name']) ?></b>
                <small class="muted">(<?= $ar['invoice_count'] ?> đơn)</small>
              </div>
              <div>
                <b><?= formatVnd($ar['total_revenue']) ?></b>
                <span style="font-weight: 700; color: #ea580c; margin-left: 6px;"><?= $ar['percentage'] ?>%</span>
              </div>
            </div>
            <div class="progress-track" style="height: 6px;">
              <div class="progress-fill" style="width: <?= $ar['percentage'] ?>%; background: <?= $ar['area_code'] === 'garden' ? '#16a34a' : ($ar['area_code'] === 'vip' ? '#9333ea' : 'var(--brand-orange)') ?>;"></div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </section>
</div>

<!-- ==========================================================
     BẢNG CHI TIẾT DOANH THU (BREAKDOWN TABLE)
     ========================================================== -->
<section class="panel" style="margin-bottom: 28px;">
  <div class="panel-title">
    <div>
      <h2><?= $viewMode === 'month' ? 'Bảng kê Doanh thu từng ngày — Tháng ' . $report['month_display'] : 'Danh sách Hóa đơn thanh toán — Ngày ' . $report['date_display'] ?></h2>
      <p class="muted"><?= $viewMode === 'month' ? 'Tổng hợp 30 ngày trong tháng kèm phân tách tiền mặt và chuyển khoản QR' : 'Chi tiết tất cả hóa đơn đã thanh toán thành công trong ngày' ?></p>
    </div>
    <div>
      <span class="badge-pill"><?= $viewMode === 'month' ? count($report['daily_data']) . ' ngày' : count($report['invoices_list']) . ' hóa đơn' ?></span>
    </div>
  </div>

  <div class="table-responsive">
    <?php if ($viewMode === 'month'): ?>
      <!-- Bảng doanh thu từng ngày trong tháng -->
      <table class="revenue-table">
        <thead>
          <tr>
            <th>Ngày</th>
            <th>Thứ</th>
            <th class="num-col">Số HĐ</th>
            <th class="num-col">Tạm tính món</th>
            <th class="num-col">Giảm giá</th>
            <th class="num-col">VAT & Phí DV</th>
            <th class="num-col">Doanh thu thực thu</th>
            <th class="num-col">Tiền mặt</th>
            <th class="num-col">Chuyển khoản QR</th>
            <th class="num-col">TB/Đơn</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($report['daily_data'] as $dayRow): ?>
            <tr style="<?= $dayRow['date'] === $todayStr ? 'background: #eff6ff; font-weight: 600;' : '' ?>">
              <td>
                <a href="index.php?page=admin&mode=day&date=<?= $dayRow['date'] ?>" style="color: #0284c7; text-decoration: none; font-weight: 700;">
                  <?= $dayRow['label'] ?> <?= $dayRow['date'] === $todayStr ? '<span class="stat-trend-badge positive" style="font-size: 9px; padding: 1px 5px;">Hôm nay</span>' : '' ?>
                </a>
              </td>
              <td><?= $dayRow['day_name'] ?></td>
              <td class="num-col"><?= $dayRow['invoices'] ?></td>
              <td class="num-col"><?= formatVnd($dayRow['subtotal']) ?></td>
              <td class="num-col" style="color: #dc2626;"><?= $dayRow['discount'] > 0 ? '-' . formatVnd($dayRow['discount']) : '0đ' ?></td>
              <td class="num-col"><?= formatVnd($dayRow['tax_fee']) ?></td>
              <td class="num-col" style="font-weight: 700; color: #ea580c;"><?= formatVnd($dayRow['revenue']) ?></td>
              <td class="num-col"><?= formatVnd($dayRow['cash_total']) ?></td>
              <td class="num-col"><?= formatVnd($dayRow['qr_total']) ?></td>
              <td class="num-col"><?= formatVnd($dayRow['avg_order']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr class="total-row">
            <td colspan="2">TỔNG CỘNG THÁNG <?= $report['month_display'] ?></td>
            <td class="num-col"><?= number_format($report['total_invoices']) ?></td>
            <td class="num-col"><?= formatVnd($report['total_subtotal']) ?></td>
            <td class="num-col" style="color: #b91c1c;">-<?= formatVnd($report['total_discount']) ?></td>
            <td class="num-col"><?= formatVnd($report['total_service_fee'] + $report['total_vat']) ?></td>
            <td class="num-col" style="font-size: 14px;"><?= formatVnd($report['total_revenue']) ?></td>
            <td class="num-col"><?= formatVnd($report['payment_methods']['cash']['total']) ?></td>
            <td class="num-col"><?= formatVnd($report['payment_methods']['qr']['total']) ?></td>
            <td class="num-col"><?= formatVnd($report['avg_order_value']) ?></td>
          </tr>
        </tfoot>
      </table>
    <?php else: ?>
      <!-- Bảng danh sách hóa đơn theo ngày -->
      <?php if (empty($report['invoices_list'])): ?>
        <p class="muted" style="padding: 24px; text-align: center;">Chưa có hóa đơn nào được thanh toán trong ngày <?= htmlspecialchars($report['date_display']) ?>.</p>
      <?php else: ?>
        <table class="revenue-table">
          <thead>
            <tr>
              <th>Giờ</th>
              <th>Số HĐ</th>
              <th>Bàn</th>
              <th>Thu ngân</th>
              <th>Phương thức</th>
              <th class="num-col">Tạm tính</th>
              <th class="num-col">Giảm giá</th>
              <th class="num-col">Thuế & Phí</th>
              <th class="num-col">Tổng thanh toán</th>
              <th style="text-align: center;">Thao tác</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($report['invoices_list'] as $inv): ?>
              <tr>
                <td><b><?= $inv['paid_at'] ?></b></td>
                <td><span style="font-family: monospace; font-weight: 700;"><?= $inv['code'] ?></span></td>
                <td><b><?= htmlspecialchars($inv['table_code']) ?></b></td>
                <td><?= htmlspecialchars($inv['cashier_name']) ?></td>
                <td>
                  <span class="badge-method <?= $inv['method'] ?>">
                    <?= $inv['method_label'] ?>
                  </span>
                </td>
                <td class="num-col"><?= formatVnd($inv['subtotal']) ?></td>
                <td class="num-col" style="color: #dc2626;"><?= $inv['discount_amount'] > 0 ? '-' . formatVnd($inv['discount_amount']) : '0đ' ?></td>
                <td class="num-col"><?= formatVnd($inv['service_fee'] + $inv['vat']) ?></td>
                <td class="num-col" style="font-weight: 800; color: #ea580c;"><?= formatVnd($inv['total']) ?></td>
                <td style="text-align: center;">
                  <a class="btn btn-small btn-outline" href="index.php?page=payment&table=<?= urlencode($inv['table_code']) ?>&invoice=<?= $inv['id'] ?>" title="Xem lại chi tiết hóa đơn hoặc in lại bill">
                    Xem bill
                  </a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
          <tfoot>
            <tr class="total-row">
              <td colspan="5">TỔNG CỘNG NGÀY <?= $report['date_display'] ?> (<?= count($report['invoices_list']) ?> hóa đơn)</td>
              <td class="num-col"><?= formatVnd($report['total_subtotal']) ?></td>
              <td class="num-col" style="color: #b91c1c;">-<?= formatVnd($report['total_discount']) ?></td>
              <td class="num-col"><?= formatVnd($report['total_service_fee'] + $report['total_vat']) ?></td>
              <td class="num-col" style="font-size: 14px;"><?= formatVnd($report['total_revenue']) ?></td>
              <td></td>
            </tr>
          </tfoot>
        </table>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</section>

<!-- ==========================================================
     WIDGET QUẢN LÝ THỰC ĐƠN NHANH (MENU MANAGEMENT PREVIEW)
     ========================================================== -->
<div class="admin-grid">
  <?php 
  $adminDishesPreview = getAllDishes('', '', '', '');
  ?>
  <section class="panel" id="menu-management" style="grid-column: 1 / -1;">
    <div class="panel-title">
      <div>
        <h2>Thực đơn & Giá bán món ăn</h2>
        <p class="muted">Điều chỉnh nhanh trạng thái mở bán hoặc giá niêm yết</p>
      </div>
      <div style="display: flex; gap: 8px;">
        <a class="btn btn-small btn-primary" href="index.php?page=dishes&action=add">+ Thêm món mới</a>
        <a class="btn btn-small btn-outline" href="index.php?page=dishes">Xem tất cả (<?= count($adminDishesPreview) ?> món) →</a>
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
</div>

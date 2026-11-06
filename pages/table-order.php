<?php
require_once __DIR__ . '/../includes/orders.php';

$currentTable = htmlspecialchars($_GET['table'] ?? 'B03');

// Dữ liệu mô phỏng cho từng bàn ăn
$tableData = [
  'B03' => [
    'has_guests' => true,
    'guests' => 4,
    'time' => '18:22 (43 phút trước)',
    'zone' => 'Tầng 1 (Trong nhà)',
    'staff' => 'Nguyễn Văn A (Nhân viên 1)',
    'note' => 'Khách ăn ít cay, 2 ly trà đào đem lên cùng lúc với cơm gà.'
  ],
  'B05' => [
    'has_guests' => true,
    'guests' => 3,
    'time' => '18:45 (20 phút trước)',
    'zone' => 'Sân vườn (Ngoài trời)',
    'staff' => 'Nguyễn Văn A (Nhân viên 1)',
    'note' => 'Bàn gần tiểu cảnh sân vườn, khách yêu cầu quạt phụ.'
  ],
  'B10' => [
    'has_guests' => true,
    'guests' => 6,
    'time' => '17:50 (75 phút trước)',
    'zone' => 'Phòng VIP Hoàng Gia',
    'staff' => 'Nguyễn Văn A (Nhân viên 1)',
    'note' => 'Khách VIP thành viên Diamond, áp dụng voucher giảm 200.000đ.'
  ],
  'B11' => [
    'has_guests' => true,
    'guests' => 2,
    'time' => '19:10 (12 phút trước)',
    'zone' => 'Tầng 1 (Trong nhà)',
    'staff' => 'Nguyễn Văn A (Nhân viên 1)',
    'note' => 'Khách gọi thanh toán nhanh khi đồ ăn ra.'
  ]
];

$cur = $tableData[$currentTable] ?? [
  'has_guests' => false,
  'guests' => 0,
  'time' => 'Chưa mở bàn',
  'zone' => 'Khu vực chính',
  'staff' => 'Chưa phân công',
  'note' => 'Bàn đang trống sẵn sàng đón khách mới.'
];

$dbError    = '';
$orderItems = [];

try {
  $orderItems = fetchTableItems(getOrdersDb(), $currentTable);
} catch (RuntimeException | PDOException $e) {
  error_log('table-order page: ' . $e->getMessage());
  $dbError = 'Không tải được danh sách món, vui lòng thử lại.';
}

$bill = calculateBill($orderItems);
?>
<div class="dash-header">
  <div>
    <div class="back-nav-row">
      <a class="back-link" href="index.php?page=staff">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
        <span>Quay lại Sơ đồ bàn</span>
      </a>
    </div>
    <div class="title-with-badge">
      <span class="eyebrow">CHI TIẾT BÀN ĂN & ĐƠN HÀNG</span>
      <div class="heading-badge-wrap">
        <h1>Bàn <?=$currentTable?></h1>
        <?php if ($cur['has_guests']): ?>
          <span class="status status-cooking">Đang phục vụ (<?=$cur['guests']?> khách)</span>
        <?php else: ?>
          <span class="status status-ready">Bàn trống (Chưa có khách)</span>
        <?php endif; ?>
      </div>
      <p class="muted">
        <?php if ($cur['has_guests']): ?>
          Thời gian vào bàn: <?=$cur['time']?> · <?=$cur['guests']?> khách · Nhân viên: <?=$cur['staff']?>
        <?php else: ?>
          Bàn đang trống sẵn sàng đón khách. Quý khách hoặc nhân viên có thể mở bàn để chọn món.
        <?php endif; ?>
      </p>
    </div>
  </div>
  <div class="header-action-group">
    <a class="btn btn-outline" href="index.php?page=kitchen">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 13.8V4a2 2 0 1 1 4 0v9.8"></path><path d="M14 13.8V4a2 2 0 1 1 4 0v9.8"></path><path d="M3 14h18a2 2 0 0 1 2 2v1a4 4 0 0 1-4 4H5a4 4 0 0 1-4-4v-1a2 2 0 0 1 2-2z"></path></svg>
      <span>Xem trên KDS Bếp</span>
    </a>
    <button class="btn btn-primary-tool" data-toast="Đã đồng bộ đơn hàng bàn <?=$currentTable?>">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"></polyline><polyline points="1 20 1 14 7 14"></polyline><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path></svg>
      <span>Cập nhật</span>
    </button>
  </div>
</div>

<div class="two-col staff-detail">
  <section class="panel">
    <div class="panel-title">
      <div>
        <h2>Danh sách món ăn</h2>
        <p class="muted"><?= $cur['has_guests'] ? 'Theo dõi món đã lên bàn hoặc còn trong bếp chế biến' : 'Bàn chưa có danh sách món' ?></p>
      </div>
      <a class="btn btn-small btn-outline" href="index.php?page=menu">+ Thêm món vào bàn</a>
    </div>

    <?php if ($dbError !== ''): ?>
      <div class="flash-alert flash-error"><span><?= escapeHtml($dbError) ?></span></div>
    <?php endif; ?>

    <?php if ($cur['has_guests'] && !empty($orderItems)): ?>
      <div class="order-food-list">
        <?php foreach ($orderItems as $item): ?>
          <?php $statusInfo = ORDER_ITEM_STATUSES[$item['status']]; ?>
          <div class="order-food order-food-<?= escapeHtml($item['status']) ?>" data-item-id="<?= (int) $item['id'] ?>" data-item-name="<?= escapeHtml($item['name']) ?>">
            <div class="food-icon-box">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8h1a4 4 0 0 1 0 8h-1"></path><path d="M2 8h16v9a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V8z"></path><line x1="6" y1="1" x2="6" y2="4"></line><line x1="10" y1="1" x2="10" y2="4"></line><line x1="14" y1="1" x2="14" y2="4"></line></svg>
            </div>
            <div class="food-info-text grow">
              <b><?= escapeHtml($item['name']) ?></b>
              <small><?= (int) $item['qty'] ?> × <?= formatMoney((int) $item['price']) ?></small>
            </div>
            <span class="status status-<?= $statusInfo['class'] ?>"><?= $statusInfo['label'] ?></span>
            <b class="food-amount"><?= formatMoney((int) $item['price'] * (int) $item['qty']) ?></b>
            <div class="order-food-actions">
              <?php foreach (ORDER_ITEM_ACTIONS[$item['status']] as $action): ?>
                <button class="btn btn-small <?= ORDER_ACTION_BUTTONS[$action]['class'] ?>" type="button" data-item-action="<?= $action ?>"><?= ORDER_ACTION_BUTTONS[$action]['label'] ?></button>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="bill-summary-box">
        <div class="summary-row"><span>Tạm tính (<?= count($bill['lines']) ?> món)</span><b><?= formatMoney($bill['subtotal']) ?></b></div>
        <div class="summary-row"><span>Phí phục vụ & VAT</span><b><?= formatMoney($bill['service_fee'] + $bill['vat']) ?></b></div>
        <div class="summary-row total"><span>Tổng cộng thanh toán</span><b><?= formatMoney($bill['total']) ?></b></div>
      </div>

      <div class="button-row">
        <button class="btn btn-outline" data-toast="Đã gửi thông báo in tạm tính cho thu ngân">In tạm tính</button>
        <a class="btn btn-primary" href="index.php?page=payment&table=<?= $currentTable ?>">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect><line x1="1" y1="10" x2="23" y2="10"></line></svg>
          <span>Tiến hành thanh toán</span>
        </a>
      </div>
    <?php else: ?>
      <div class="empty-table-state" style="text-align: center; padding: 40px 20px;">
        <div style="font-size: 48px; margin-bottom: 12px;">🍽️</div>
        <h3>Bàn <?=$currentTable?> hiện chưa có khách</h3>
        <p class="muted" style="max-width: 400px; margin: 0 auto 20px;">Bạn có thể mở bàn mới để phục vụ khách, chọn số lượng khách và thêm món từ thực đơn.</p>
        <div style="display: flex; gap: 12px; justify-content: center;">
          <a class="btn btn-primary" href="index.php?page=menu">+ Mở bàn & Chọn món ngay</a>
          <a class="btn btn-outline" href="index.php?page=staff">Trở về sơ đồ bàn</a>
        </div>
      </div>
    <?php endif; ?>
  </section>

  <aside class="panel table-info-aside">
    <h2>Thông tin bàn ăn</h2>
    <div class="info-row"><span>Mã bàn</span><b><?=$currentTable?></b></div>
    <div class="info-row"><span>Khu vực</span><b><?=$cur['zone']?></b></div>
    <div class="info-row"><span>Trạng thái</span><b><?=$cur['has_guests'] ? 'Đang phục vụ' : 'Chưa có khách'?></b></div>
    <div class="info-row"><span>Số lượng khách</span><b><?=$cur['has_guests'] ? ($cur['guests'].' khách') : '0 người'?></b></div>
    <div class="info-row"><span>Thời gian</span><b><?=$cur['time']?></b></div>
    <div class="info-row"><span>Nhân viên</span><b><?=$cur['staff']?></b></div>

    <hr>
    <div class="note-box">
      <h3>Ghi chú bàn</h3>
      <p class="muted"><?=$cur['note']?></p>
    </div>

    <?php if ($cur['has_guests']): ?>
      <button class="btn btn-outline btn-block" data-toast="Đã gửi tín hiệu hỗ trợ tới ca trưởng">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
        <span>Yêu cầu hỗ trợ từ ca trưởng</span>
      </button>
    <?php endif; ?>
  </aside>
</div>
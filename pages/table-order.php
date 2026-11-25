<?php

/**
 * SmartDine - Chi tiết bàn, gán bàn, chuyển bàn và danh sách món cho nhân viên phục vụ.
 * Nhân viên chỉ hủy món khi bếp chưa in phiếu; món bếp đã nấu xong hiện nhãn "Hoàn thành" thay cho nút hủy.
 * Xem hoặc in lại phiếu món ăn ở mục Lịch sử bill.
 */

require_once __DIR__ . '/../includes/orders.php';
require_once __DIR__ . '/../includes/tables.php';

$rawTable     = strtoupper(trim((string) ($_GET['table'] ?? '')));
$currentTable = $rawTable !== '' ? preg_replace('/[^A-Z0-9_-]/', '', $rawTable) : 'B03';
if ($currentTable === '') {
  $currentTable = 'B03';
}

$dbError    = '';
$orderItems = [];
$tableRow   = null;
$assignment = null;

try {
  $pdo        = getOrdersDb();
  $tableRow   = getDiningTableByCode($currentTable);
  $assignment = getActiveTableAssignment($currentTable, $pdo);
  $orderItems = fetchTableItems($pdo, $currentTable);
} catch (RuntimeException | PDOException $e) {
  error_log('table-order page: ' . $e->getMessage());
  $dbError = 'Không tải được danh sách món, vui lòng thử lại.';
}

$bill         = calculateBill($orderItems);
$visibleItems = array_values(array_filter(
  $orderItems,
  static fn(array $item): bool => $item['status'] !== 'cancelled'
));

$hasOrderItems = ($bill['lines'] !== []);
$hasGuests     = ($hasOrderItems || $assignment !== null);
$isMaintenance = ($tableRow && ($tableRow['status'] ?? '') === 'maintenance');

$openedAtRaw = $assignment['opened_at'] ?? null;
if (!$openedAtRaw && $hasOrderItems) {
  try {
    $openedAtRaw = fetchOpenOrderCreatedAt(getOrdersDb(), $currentTable);
  } catch (Throwable $e) {
    $openedAtRaw = null;
  }
}

$cur = [
  'has_guests'     => $hasGuests,
  'guests'         => (int) ($assignment['guest_count'] ?? 0),
  'customer_name'  => trim((string) ($assignment['customer_name'] ?? '')),
  'customer_phone' => trim((string) ($assignment['customer_phone'] ?? '')),
  'time'           => $openedAtRaw ? date('H:i', strtotime((string) $openedAtRaw)) : ($hasGuests ? 'Đang mở' : 'Chưa mở bàn'),
  'zone'           => (string) ($tableRow['area_name'] ?? 'Khu vực chính'),
  'capacity'       => (int) ($tableRow['capacity'] ?? 4),
  'staff_id'       => (int) ($assignment['staff_id'] ?? 0),
  'staff'          => trim((string) ($assignment['staff_name'] ?? '')) ?: 'Chưa phân công',
  'note'           => trim((string) ($assignment['note'] ?? '')) ?: ($hasGuests ? 'Không có ghi chú đặc biệt.' : 'Bàn đang trống sẵn sàng đón khách mới.'),
];
?>
<div class="dash-header">
  <div>
    <div class="back-nav-row">
      <a class="back-link" href="index.php?page=staff">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <line x1="19" y1="12" x2="5" y2="12"></line>
          <polyline points="12 19 5 12 12 5"></polyline>
        </svg>
        <span>Quay lại Sơ đồ bàn</span>
      </a>
    </div>
    <div class="title-with-badge">
      <span class="eyebrow">CHI TIẾT BÀN ĂN &amp; ĐƠN HÀNG</span>
      <div class="heading-badge-wrap">
        <h1>Bàn <?= htmlspecialchars($currentTable) ?></h1>
        <?php if ($isMaintenance): ?>
          <span class="status status-cancelled">Đang bảo trì</span>
        <?php elseif ($cur['has_guests']): ?>
          <span class="status status-cooking">Đang phục vụ<?= $cur['guests'] > 0 ? ' (' . $cur['guests'] . ' khách)' : '' ?></span>
        <?php else: ?>
          <span class="status status-ready">Bàn trống (Chưa có khách)</span>
        <?php endif; ?>
      </div>
      <p class="muted">
        <?php if ($cur['has_guests']): ?>
          Thời gian vào bàn: <?= htmlspecialchars($cur['time']) ?><?= $cur['guests'] > 0 ? ' · ' . $cur['guests'] . ' khách' : '' ?> · Nhân viên: <?= htmlspecialchars($cur['staff']) ?>
        <?php else: ?>
          Bàn đang trống sẵn sàng đón khách (<?= $cur['capacity'] ?> chỗ · <?= htmlspecialchars($cur['zone']) ?>).
        <?php endif; ?>
      </p>
    </div>
  </div>
  <div class="header-action-group">
    <?php if (!$isMaintenance): ?>
      <?php if ($cur['has_guests']): ?>
        <button type="button" class="btn btn-outline"
                data-open-assign-table="<?= htmlspecialchars($currentTable) ?>"
                data-assign-update="1"
                data-guest-count="<?= $cur['guests'] > 0 ? $cur['guests'] : 2 ?>"
                data-staff-id="<?= $cur['staff_id'] ?>"
                data-customer-name="<?= htmlspecialchars($cur['customer_name']) ?>"
                data-customer-phone="<?= htmlspecialchars($cur['customer_phone']) ?>"
                data-assign-note="<?= htmlspecialchars($assignment['note'] ?? '') ?>">
          <span>Cập nhật gán bàn</span>
        </button>
        <button type="button" class="btn btn-outline" data-open-transfer-table="<?= htmlspecialchars($currentTable) ?>">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <polyline points="17 1 21 5 17 9"></polyline>
            <path d="M3 11V9a4 4 0 0 1 4-4h14"></path>
            <polyline points="7 23 3 19 7 15"></polyline>
            <path d="M21 13v2a4 4 0 0 1-4 4H3"></path>
          </svg>
          <span>Chuyển bàn</span>
        </button>
        <button type="button" class="btn btn-outline" data-open-merge-table="<?= htmlspecialchars($currentTable) ?>">
          <span>Gộp bàn</span>
        </button>
      <?php else: ?>
        <button type="button" class="btn btn-outline"
                data-open-assign-table="<?= htmlspecialchars($currentTable) ?>"
                data-assign-update="0">
          <span>Gán bàn / Mở bàn</span>
        </button>
      <?php endif; ?>
    <?php endif; ?>
    <a class="btn btn-outline" href="index.php?page=kitchen">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M6 13.8V4a2 2 0 1 1 4 0v9.8"></path>
        <path d="M14 13.8V4a2 2 0 1 1 4 0v9.8"></path>
        <path d="M3 14h18a2 2 0 0 1 2 2v1a4 4 0 0 1-4 4H5a4 4 0 0 1-4-4v-1a2 2 0 0 1 2-2z"></path>
      </svg>
      <span>Xem trên KDS Bếp</span>
    </a>
    <button class="btn btn-primary-tool" type="button" data-refresh-items>
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <polyline points="23 4 23 10 17 10"></polyline>
        <polyline points="1 20 1 14 7 14"></polyline>
        <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path>
      </svg>
      <span>Cập nhật</span>
    </button>
  </div>
</div>

<div class="two-col staff-detail">
  <section class="panel">
    <div class="panel-title">
      <div>
        <h2>Danh sách món ăn</h2>
        <p class="muted"><?= $hasOrderItems ? 'Theo dõi món đã lên bàn hoặc còn trong bếp chế biến' : ($cur['has_guests'] ? 'Khách đã nhận bàn, chưa gọi món' : 'Bàn chưa có danh sách món') ?></p>
      </div>
      <a class="btn btn-small btn-outline" href="index.php?page=menu">+ Thêm món vào bàn</a>
    </div>

    <?php if ($dbError !== ''): ?>
      <div class="flash-alert flash-error"><span><?= escapeHtml($dbError) ?></span></div>
    <?php endif; ?>

    <?php if ($hasOrderItems && $visibleItems !== []): ?>
      <div class="order-food-list">
        <?php foreach ($visibleItems as $item): ?>
          <?php $statusInfo = ORDER_ITEM_STATUSES[$item['status']]; ?>
          <div class="order-food order-food-<?= escapeHtml($item['status']) ?>" data-item-id="<?= (int) $item['id'] ?>" data-item-name="<?= escapeHtml($item['name']) ?>">
            <div class="food-icon-box">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M18 8h1a4 4 0 0 1 0 8h-1"></path>
                <path d="M2 8h16v9a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V8z"></path>
                <line x1="6" y1="1" x2="6" y2="4"></line>
                <line x1="10" y1="1" x2="10" y2="4"></line>
                <line x1="14" y1="1" x2="14" y2="4"></line>
              </svg>
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
              <?php foreach (ORDER_ITEM_LOCKED_ACTIONS[$item['status']] ?? [] as $action): ?>
                <button class="btn btn-small <?= ORDER_ACTION_BUTTONS[$action]['class'] ?> is-locked" type="button" disabled aria-disabled="true" title="<?= escapeHtml(ORDER_CANCEL_LOCKED_MESSAGE) ?>"><?= ORDER_ACTION_BUTTONS[$action]['label'] ?></button>
              <?php endforeach; ?>
              <?php if (isset(ORDER_ITEM_MARKERS[$item['status']])): ?>
                <span class="btn btn-small <?= ORDER_ITEM_MARKERS[$item['status']]['class'] ?> is-static"><?= ORDER_ITEM_MARKERS[$item['status']]['label'] ?></span>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <p class="muted order-food-hint">Cần xem hoặc in lại phiếu món ăn? Vào <a href="index.php?page=bills&amp;tab=items&amp;table=<?= escapeHtml($currentTable) ?>">Lịch sử bill</a>.</p>

      <div class="bill-summary-box">
        <div class="summary-row"><span>Tạm tính (<?= count($bill['lines']) ?> món)</span><b><?= formatMoney($bill['subtotal']) ?></b></div>
        <div class="summary-row"><span>Phí phục vụ (<?= escapeHtml($bill['service_rate'] * 100) ?>%)</span><b><?= formatMoney($bill['service_fee']) ?></b></div>
        <div class="summary-row"><span>Thuế VAT (<?= escapeHtml($bill['vat_rate'] * 100) ?>%)</span><b><?= formatMoney($bill['vat']) ?></b></div>
        <div class="summary-row total"><span>Tổng cộng thanh toán</span><b><?= formatMoney($bill['total']) ?></b></div>
      </div>

      <div class="button-row">
        <button class="btn btn-outline" type="button" data-print-provisional data-table="<?= escapeHtml($currentTable) ?>">In tạm tính</button>
        <a class="btn btn-primary" href="index.php?page=payment&amp;table=<?= escapeHtml($currentTable) ?>">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect>
            <line x1="1" y1="10" x2="23" y2="10"></line>
          </svg>
          <span>Tiến hành thanh toán</span>
        </a>
      </div>
    <?php elseif ($cur['has_guests']): ?>
      <div class="empty-table-state" style="text-align: center; padding: 40px 20px;">
        <div style="font-size: 48px; margin-bottom: 12px;">🛎️</div>
        <h3>Bàn <?= htmlspecialchars($currentTable) ?> đã gán cho <?= $cur['guests'] ?> khách</h3>
        <p class="muted" style="max-width: 420px; margin: 0 auto 20px;">Khách đã nhận bàn (<?= htmlspecialchars($cur['time']) ?>) nhưng chưa có món ăn nào được gọi.</p>
        <div style="display: flex; gap: 12px; justify-content: center; flex-wrap: wrap;">
          <a class="btn btn-primary" href="index.php?page=menu">+ Thêm món ngay</a>
          <button type="button" class="btn btn-outline" data-open-transfer-table="<?= htmlspecialchars($currentTable) ?>">Chuyển bàn</button>
          <button type="button" class="btn btn-outline" style="color: #dc2626; border-color: #fca5a5;" data-release-table="<?= htmlspecialchars($currentTable) ?>">Trả bàn trống</button>
        </div>
      </div>
    <?php else: ?>
      <div class="empty-table-state" style="text-align: center; padding: 40px 20px;">
        <div style="font-size: 48px; margin-bottom: 12px;">🍽️</div>
        <h3>Bàn <?= htmlspecialchars($currentTable) ?> hiện chưa có khách</h3>
        <p class="muted" style="max-width: 400px; margin: 0 auto 20px;">Bạn có thể gán bàn cho khách mới vào, chọn số lượng khách, nhân viên phụ trách hoặc mở thực đơn gọi món.</p>
        <div style="display: flex; gap: 12px; justify-content: center; flex-wrap: wrap;">
          <?php if (!$isMaintenance): ?>
            <button type="button" class="btn btn-primary" data-open-assign-table="<?= htmlspecialchars($currentTable) ?>" data-assign-update="0">Gán bàn cho khách</button>
          <?php endif; ?>
          <a class="btn btn-outline" href="index.php?page=menu">+ Chọn món từ thực đơn</a>
          <a class="btn btn-outline" href="index.php?page=staff">Trở về sơ đồ bàn</a>
        </div>
      </div>
    <?php endif; ?>
  </section>

  <aside class="panel table-info-aside">
    <h2>Thông tin bàn ăn</h2>
    <div class="info-row"><span>Mã bàn</span><b><?= htmlspecialchars($currentTable) ?></b></div>
    <div class="info-row"><span>Khu vực</span><b><?= htmlspecialchars($cur['zone']) ?></b></div>
    <div class="info-row"><span>Sức chứa</span><b><?= $cur['capacity'] ?> chỗ</b></div>
    <div class="info-row"><span>Trạng thái</span><b><?= $isMaintenance ? 'Bảo trì' : ($cur['has_guests'] ? 'Đang phục vụ' : 'Chưa có khách') ?></b></div>
    <div class="info-row"><span>Số lượng khách</span><b><?= $cur['has_guests'] ? (($cur['guests'] > 0 ? $cur['guests'] : 'Chưa rõ') . ($cur['guests'] > 0 ? ' khách' : '')) : '0 người' ?></b></div>
    <?php if ($cur['customer_name'] !== ''): ?>
      <div class="info-row"><span>Khách hàng</span><b><?= htmlspecialchars($cur['customer_name']) ?><?= $cur['customer_phone'] !== '' ? ' · ' . htmlspecialchars($cur['customer_phone']) : '' ?></b></div>
    <?php endif; ?>
    <div class="info-row"><span>Thời gian</span><b><?= htmlspecialchars($cur['time']) ?></b></div>
    <div class="info-row"><span>Nhân viên</span><b><?= htmlspecialchars($cur['staff']) ?></b></div>

    <hr>
    <div class="note-box">
      <h3>Ghi chú bàn</h3>
      <p class="muted"><?= htmlspecialchars($cur['note']) ?></p>
    </div>

    <?php if ($cur['has_guests']): ?>
      <div style="display: flex; flex-direction: column; gap: 8px; margin-top: 12px;">
        <button type="button" class="btn btn-outline btn-block" data-open-transfer-table="<?= htmlspecialchars($currentTable) ?>">
          <span>⇄ Chuyển sang bàn khác</span>
        </button>
        <button type="button" class="btn btn-outline btn-block" data-open-merge-table="<?= htmlspecialchars($currentTable) ?>">
          <span>⊞ Gộp với bàn khác</span>
        </button>
        <button class="btn btn-outline btn-block" data-toast="Đã gửi tín hiệu hỗ trợ tới ca trưởng">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
            <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
          </svg>
          <span>Yêu cầu hỗ trợ từ ca trưởng</span>
        </button>
      </div>
    <?php endif; ?>
  </aside>
</div>

<?php include __DIR__ . '/../includes/table-modals.php'; ?>

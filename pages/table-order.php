<?php

/**
 * SmartDine - Chi tiết bàn và danh sách món cho nhân viên phục vụ & quản trị viên.
 * Quản trị viên và Nhân viên phục vụ đều thêm món vào bàn; chỉ Quản trị viên được hủy món
 * (dù món chưa nấu hay đã nấu xong). Lược bỏ thông tin tính tiền rườm rà tại trang này.
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

$role      = currentRole();
$canAdd    = canRoleDoOrderAction($role, 'add');
$canCancel = canRoleDoOrderAction($role, 'cancel');

$dishesByCategory = [];
if ($canAdd) {
  try {
    foreach (fetchOrderableDishes(getOrdersDb()) as $dish) {
      $dishesByCategory[$dish['category']][] = $dish;
    }
  } catch (RuntimeException | PDOException $e) {
    error_log('table-order dishes: ' . $e->getMessage());
    $dbError = $dbError ?: 'Không tải được thực đơn để thêm món, vui lòng thử lại.';
  }
}

$visibleItems = array_values(array_filter(
  $orderItems,
  static fn(array $item): bool => $item['status'] !== 'cancelled'
));
$cur['has_guests'] = $visibleItems !== [];
if ($cur['has_guests'] && $cur['guests'] === 0) {
  $createdAt   = fetchOpenOrderCreatedAt(getOrdersDb(), $currentTable);
  $cur['time'] = $createdAt ? date('H:i', strtotime($createdAt)) : '—';
}
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
      <?php if ($canAdd && $dishesByCategory !== []): ?>
        <button class="btn btn-small btn-primary" type="button" data-open-add-item>+ Thêm món</button>
      <?php endif; ?>
    </div>

    <?php if ($canAdd && $dishesByCategory !== []): ?>
      <dialog class="add-item-dialog" data-add-item-dialog>
        <form class="add-item-form" data-add-item-form data-table="<?= $currentTable ?>" novalidate>
          <div class="add-item-head">
            <h3>Thêm món · Bàn <?= $currentTable ?></h3>
            <button class="add-item-close" type="button" data-add-close aria-label="Đóng">✕</button>
          </div>

          <div class="add-item-body">
            <div>
              <label class="add-item-label" for="dish-search">Món</label>
              <input id="dish-search" type="search" data-dish-search placeholder="Tìm món" autocomplete="off">
              <div class="dish-picker" data-dish-picker>
                <?php foreach ($dishesByCategory as $category => $dishes): ?>
                  <div class="dish-group" data-dish-group>
                    <h4><?= escapeHtml($category) ?></h4>
                    <?php foreach ($dishes as $dish): ?>
                      <?php $isOut = $dish['status'] !== 'active'; ?>
                      <label class="dish-option<?= $isOut ? ' is-out' : '' ?>" data-dish-name="<?= escapeHtml(mb_strtolower($dish['name'], 'UTF-8')) ?>">
                        <input type="radio" name="dish_id" value="<?= (int) $dish['id'] ?>" data-dish-label="<?= escapeHtml($dish['name']) ?>" data-dish-price="<?= (int) $dish['price'] ?>" <?= $isOut ? ' disabled' : '' ?>>
                        <span class="dish-row">
                          <span class="dish-name"><?= escapeHtml($dish['name']) ?><?= $isOut ? ' (tạm hết)' : '' ?></span>
                          <span class="dish-price"><?= formatMoney((int) $dish['price']) ?></span>
                        </span>
                      </label>
                    <?php endforeach; ?>
                  </div>
                <?php endforeach; ?>
                <p class="dish-empty" data-dish-empty hidden>Không tìm thấy món phù hợp.</p>
              </div>
            </div>

            <div>
              <span class="add-item-label">Số lượng</span>
              <div class="qty-stepper">
                <button type="button" data-qty-step="-1" aria-label="Giảm">−</button>
                <input type="number" name="qty" value="1" min="1" max="<?= ORDER_ADD_MAX_QTY ?>" aria-label="Số lượng">
                <button type="button" data-qty-step="1" aria-label="Tăng">+</button>
              </div>
            </div>

            <div>
              <label class="add-item-label" for="add-item-note">Ghi chú</label>
              <input id="add-item-note" type="text" name="note" maxlength="255" placeholder="Nhập ghi chú" autocomplete="off">
              <div class="note-chips">
                <?php foreach (['Ít cay', 'Không hành', 'Mang ra trước', 'Không đá'] as $chip): ?>
                  <button class="note-chip" type="button" data-note-chip="<?= escapeHtml($chip) ?>"><?= escapeHtml($chip) ?></button>
                <?php endforeach; ?>
              </div>
            </div>
          </div>

          <div class="add-item-foot">
            <span class="add-item-summary" data-add-summary>Chưa chọn món</span>
            <div class="add-item-actions">
              <button class="btn btn-outline" type="button" data-add-close>Hủy</button>
              <button class="btn btn-primary" type="submit" data-add-submit disabled>Thêm món</button>
            </div>
          </div>
        </form>
      </dialog>
    <?php endif; ?>

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
              <small>Số lượng: <?= (int) $item['qty'] ?><?= !empty($item['note']) ? ' · Ghi chú: ' . escapeHtml($item['note']) : '' ?></small>
            </div>
            <span class="status status-<?= $statusInfo['class'] ?>"><?= $statusInfo['label'] ?></span>
            <div class="order-food-actions">
              <?php foreach (availableItemActions($item, $role) as $action): ?>
                <button class="btn btn-small <?= ORDER_ACTION_BUTTONS[$action]['class'] ?>" type="button" data-item-action="<?= $action ?>"><?= ORDER_ACTION_BUTTONS[$action]['label'] ?></button>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <?php if (!$canCancel): ?>
        <p class="muted order-food-hint">Chỉ Quản trị viên mới được hủy món. Cần hủy món, vui lòng báo quản lý.</p>
      <?php endif; ?>

      <div class="button-row">
        <a class="btn btn-primary" href="index.php?page=payment&table=<?= $currentTable ?>">
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
        <h3>Bàn <?= $currentTable ?> hiện chưa có khách</h3>
        <p class="muted" style="max-width: 400px; margin: 0 auto 20px;">Bấm "Mở bàn & Chọn món ngay" để chọn món, bàn sẽ được mở và món gửi xuống bếp.</p>
        <div style="display: flex; gap: 12px; justify-content: center;">
          <?php if ($canAdd): ?>
            <button class="btn btn-primary" type="button" data-open-add-item>+ Mở bàn & Chọn món ngay</button>
          <?php endif; ?>
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
<?php
/**
 * SmartDine - Giao diện Khách Quét QR Gọi Món Tại Bàn (Buffet & Gọi món lẻ)
 * - Nhận diện bàn từ mã QR gán cứng trong `qr_table_codes` (?qr=<token> hoặc ?table=<code>)
 * - Đọc trực tiếp cấu hình Thực đơn Admin (Gói Buffet, Danh mục, Món ăn)
 * - Gửi món trực tiếp xuống Bếp KDS & đồng bộ giỏ/trạng thái thời gian thực qua MySQL (`api/qr-order.php`)
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/tables.php';
require_once __DIR__ . '/includes/qr-tables.php';

$pdo = getDbConnection();
if ($pdo instanceof PDO) {
    ensureMenuConfigSchema($pdo);
    ensureQrTableCodesSchema($pdo);
}

$requestedQrToken   = trim((string) ($_GET['qr'] ?? ''));
$requestedTableCode = strtoupper(trim((string) ($_GET['table'] ?? '')));
$allTables          = getAllDiningTables('', 'active', '');

$currentTable = null;
$qrRecord     = null;
$qrError      = '';

if ($requestedQrToken !== '' && $pdo instanceof PDO) {
    $qrRow = findTableByQrToken($pdo, $requestedQrToken);
    if (!$qrRow) {
        $qrError = 'Mã QR không hợp lệ hoặc không thuộc hệ thống SmartDine.';
    } elseif ((int) ($qrRow['qr_active'] ?? 0) !== 1 || ($qrRow['table_status'] ?? '') !== 'active') {
        $qrError = 'Bàn ' . ($qrRow['table_code'] ?? '') . ' hiện đang tạm ngưng phục vụ.';
    } else {
        $qrRecord     = $qrRow;
        $currentTable = getDiningTableByCode((string) $qrRow['table_code']);
    }
}

if ($qrError === '' && !$currentTable && $requestedTableCode !== '') {
    $foundTable = getDiningTableByCode($requestedTableCode);
    if (!$foundTable) {
        $qrError = 'Không tìm thấy Bàn ' . $requestedTableCode . ' trong hệ thống.';
    } elseif (($foundTable['status'] ?? '') !== 'active') {
        $qrError = 'Bàn ' . $requestedTableCode . ' hiện đang bảo trì hoặc tạm ẩn.';
    } else {
        $currentTable = $foundTable;
    }
}

if ($qrError === '' && !$currentTable && $requestedQrToken === '' && $requestedTableCode === '' && !empty($allTables)) {
    $currentTable = $allTables[0];
}

if ($currentTable && !$qrRecord && $pdo instanceof PDO) {
    $qrRecord = getOrCreateTableQrCode($pdo, (string) $currentTable['code']);
}

$tableCode = $currentTable
    ? (string) $currentTable['code']
    : ($requestedTableCode !== '' ? $requestedTableCode : 'B01');
$qrToken = (string) ($qrRecord['qr_token'] ?? ($requestedQrToken !== '' ? $requestedQrToken : generateFixedTableQrToken($tableCode)));

// Tìm liên kết đánh giá gần nhất chưa chấm của bàn (nếu bàn vừa có hóa đơn)
$pendingRatingUrl = '';
if ($qrError === '' && $pdo instanceof PDO) {
    try {
        $rStmt = $pdo->prepare("
            SELECT sr.`token`
            FROM `service_ratings` sr
            JOIN `invoices` inv ON inv.`id` = sr.`invoice_id`
            WHERE inv.`table_code` = :code AND sr.`rating` IS NULL
            ORDER BY sr.`id` DESC
            LIMIT 1
        ");
        $rStmt->execute([':code' => $tableCode]);
        $rToken = $rStmt->fetchColumn();
        if ($rToken) {
            $pendingRatingUrl = 'rate.php?t=' . urlencode((string) $rToken);
        }
    } catch (Throwable $e) {
        // Bỏ qua nếu bảng service_ratings chưa tồn tại
    }
}

$buffetPackages   = $qrError === '' ? getAllBuffetPackages(true) : [];
$allVisibleDishes = $qrError === '' ? getAllDishes('', 'visible', '') : [];

$buffetDishes   = [];
$alacarteDishes = [];

foreach ($allVisibleDishes as $dish) {
    if (($dish['menu_type'] ?? 'alacarte') === 'buffet') {
        $buffetDishes[] = $dish;
    } else {
        $alacarteDishes[] = $dish;
    }
}

if (!function_exists('groupDishesByCategory')) {
    /**
     * Nhóm danh sách món theo danh mục giữ nguyên thứ tự hiển thị.
     */
    function groupDishesByCategory(array $dishes): array
    {
        $groups = [];
        foreach ($dishes as $dish) {
            $catId = (int) ($dish['category_id'] ?? 0);
            if (!isset($groups[$catId])) {
                $groups[$catId] = [
                    'id'     => $catId,
                    'name'   => (string) ($dish['category_name'] ?? 'Khác'),
                    'slug'   => (string) ($dish['category_slug'] ?? 'khac'),
                    'dishes' => [],
                ];
            }
            $groups[$catId]['dishes'][] = $dish;
        }
        return array_values($groups);
    }
}

$packagesPayload = [];
foreach ($buffetPackages as $pkg) {
    $pkgId     = (int) $pkg['id'];
    $pkgDishes = array_values(array_filter(
        $buffetDishes,
        static fn(array $d): bool => empty($d['buffet_package_id']) || (int) $d['buffet_package_id'] === $pkgId
    ));
    $packagesPayload[$pkgId] = [
        'id'               => $pkgId,
        'name'             => (string) $pkg['name'],
        'price_per_person' => (float) $pkg['price_per_person'],
        'price_label'      => formatPrice($pkg['price_per_person']) . ' /Người',
        'image'            => getDishImageUrl($pkg['image']),
        'description'      => (string) ($pkg['description'] ?? ''),
        'groups'           => groupDishesByCategory($pkgDishes),
    ];
}

$alacarteGroups  = groupDishesByCategory($alacarteDishes);
$buffetGroupsAll = groupDishesByCategory($buffetDishes);
$alacarteCover   = 'https://images.unsplash.com/photo-1555939594-58d7cb561ad1?auto=format&fit=crop&w=900&q=80';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <title>QR Order Bàn <?= htmlspecialchars($tableCode) ?> — SmartDine</title>
  <link rel="stylesheet" href="<?= htmlspecialchars(assetUrl('assets/css/qr-order.css')) ?>">
  <link rel="stylesheet" href="<?= htmlspecialchars(assetUrl('assets/css/ai-ordering.css')) ?>">
  <script src="<?= htmlspecialchars(assetUrl('assets/js/vendor/qrcode.js')) ?>"></script>
</head>
<body class="qr-order-body">

<div class="qr-app-shell">

  <!-- TOP BAR CHUNG -->
  <header class="qr-topbar" id="qr-main-topbar">
    <button type="button" class="qr-brand-btn" id="btn-back-pack-select" title="Về chọn hình thức Menu">
      <span class="qr-brand-circle">SD</span>
      <span>SmartDine</span>
    </button>

    <div class="qr-topbar-right">
      <button type="button" class="qr-btn-call-staff" id="btn-call-staff" <?= $qrError !== '' ? 'disabled' : '' ?>>
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
        <span>Gọi NV</span>
      </button>

      <span class="qr-table-pill">Bàn <?= htmlspecialchars($tableCode) ?></span>

      <button type="button" class="qr-icon-btn" id="btn-open-cart-view" title="Gọi món của bạn" <?= $qrError !== '' ? 'disabled' : '' ?>>
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path></svg>
        <span class="qr-cart-badge" id="qr-cart-badge">0</span>
      </button>

      <?php if ($pendingRatingUrl !== ''): ?>
        <a class="qr-icon-btn star-btn" href="<?= htmlspecialchars($pendingRatingUrl) ?>" title="Đánh giá trải nghiệm phục vụ">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
        </a>
      <?php else: ?>
        <button type="button" class="qr-icon-btn star-btn" id="btn-qr-rating-info" title="Đánh giá phục vụ">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
        </button>
      <?php endif; ?>
    </div>
  </header>

<?php if ($qrError !== ''): ?>
  <main class="qr-select-screen">
    <div class="qr-empty-cart" style="background:#fff;border-radius:16px;padding:42px 20px;margin-top:20px;border:1px solid #fecaca;">
      <div style="font-size:42px;margin-bottom:12px;">⚠️</div>
      <h2 style="margin:0 0 8px;font-size:18px;color:#b91c1c;">Không thể mở bàn gọi món</h2>
      <p style="margin:0 0 18px;color:#4b5563;font-size:14px;"><?= htmlspecialchars($qrError) ?></p>
      <p style="margin:0;color:#6b7280;font-size:12px;">Vui lòng quét lại mã QR đặt tại bàn hoặc liên hệ nhân viên phục vụ.</p>
    </div>
  </main>
<?php else: ?>

  <!-- MÀN HÌNH 1: CHỌN GÓI BUFFET HOẶC MENU GỌI MÓN LẺ -->
  <main id="screen-pack-select" class="qr-select-screen">
    <div class="qr-notice-bar">
      <span>Quét QR tại <b>Bàn <?= htmlspecialchars($tableCode) ?></b> · Chọn gói Buffet hoặc Menu gọi món</span>
      <button type="button" class="qr-live-orders-pill" id="btn-quick-view-ordered" style="display:none;"></button>
    </div>

    <?php foreach ($packagesPayload as $pkg): ?>
      <article class="qr-menu-pack-card">
        <img class="qr-pack-cover" src="<?= $pkg['image'] ?>" alt="<?= htmlspecialchars($pkg['name']) ?>" onerror="this.src='https://images.unsplash.com/photo-1555939594-58d7cb561ad1?auto=format&fit=crop&w=900&q=80'">
        <div class="qr-pack-body">
          <div class="qr-pack-head">
            <h2 class="qr-pack-title"><?= htmlspecialchars($pkg['name']) ?></h2>
            <span class="qr-pack-price"><?= formatPrice($pkg['price_per_person']) ?>/Người</span>
          </div>
          <?php if ($pkg['description'] !== ''): ?>
            <p class="qr-pack-sub"><?= htmlspecialchars($pkg['description']) ?></p>
          <?php endif; ?>
          <div class="qr-pack-actions">
            <button type="button" class="qr-btn-outline btn-preview-pack" data-pack-type="buffet" data-pack-id="<?= $pkg['id'] ?>">Xem menu</button>
            <button type="button" class="qr-btn-solid btn-choose-pack" data-pack-type="buffet" data-pack-id="<?= $pkg['id'] ?>">Chọn menu</button>
          </div>
        </div>
      </article>
    <?php endforeach; ?>

    <!-- MENU GỌI MÓN LẺ -->
    <article class="qr-menu-pack-card">
      <img class="qr-pack-cover" src="<?= $alacarteCover ?>" alt="MENU GỌI MÓN">
      <div class="qr-pack-body">
        <div class="qr-pack-head">
          <h2 class="qr-pack-title">MENU GỌI MÓN</h2>
          <span class="qr-pack-price" style="font-size:13px;color:#6b7280;"><?= count($alacarteDishes) ?> món lẻ</span>
        </div>
        <p class="qr-pack-sub">Gọi món lẻ theo nhu cầu và thanh toán theo đơn giá từng món niêm yết.</p>
        <div class="qr-pack-actions">
          <button type="button" class="qr-btn-outline btn-preview-pack" data-pack-type="alacarte" data-pack-id="0">Xem menu</button>
          <button type="button" class="qr-btn-solid btn-choose-pack" data-pack-type="alacarte" data-pack-id="0">Chọn menu</button>
        </div>
      </div>
    </article>
  </main>

  <!-- MÀN HÌNH 2: THỰC ĐƠN GỌI MÓN (Món buffet | Món tính tiền) -->
  <section id="screen-menu-order" style="display: none;">
    <div class="qr-primary-tabs">
      <button type="button" class="qr-tab-btn active" data-menu-tab="buffet">Món buffet</button>
      <button type="button" class="qr-tab-btn" data-menu-tab="alacarte">Món tính tiền</button>
    </div>

    <div class="qr-cat-bar-wrap" id="qr-cat-bar">
      <button type="button" class="qr-search-toggle" id="btn-toggle-qr-search" title="Tìm món ăn">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
      </button>
      <div id="qr-cat-pills" style="display:flex;gap:8px;align-items:center;"></div>
    </div>

    <div class="qr-search-drawer" id="qr-search-drawer">
      <input type="text" id="qr-dish-search" placeholder="Tìm nhanh tên món ăn trong mục này..." autocomplete="off">
    </div>

    <div class="qr-menu-sections" id="qr-menu-sections"></div>

    <!-- Thanh nổi dưới màn hình khi đang có món trong giỏ -->
    <div class="qr-sticky-cartbar" id="qr-sticky-cartbar" style="display:none;">
      <div>
        <div style="font-size:12px;opacity:.85;"><b id="qr-sticky-qty">0</b> món trong giỏ đồ ăn</div>
        <div style="font-size:15px;font-weight:800;" id="qr-sticky-subtotal">0đ</div>
      </div>
      <button type="button" class="qr-sticky-cta" id="btn-sticky-open-cart">Xem giỏ &amp; Gửi đơn ›</button>
    </div>
  </section>

  <!-- MÀN HÌNH 3: GỌI MÓN CỦA BẠN (Giỏ đồ ăn | Món đã gọi) -->
  <section id="screen-cart-view" class="qr-cart-screen" style="display: none;">
    <header class="qr-cart-header">
      <button type="button" class="qr-back-btn" id="btn-close-cart-view" aria-label="Quay lại">‹</button>
      <h2>Gọi món của bạn</h2>
    </header>

    <div class="qr-primary-tabs">
      <button type="button" class="qr-tab-btn active" data-cart-tab="draft">Giỏ đồ ăn</button>
      <button type="button" class="qr-tab-btn" data-cart-tab="ordered">Món đã gọi</button>
    </div>

    <div id="qr-cart-content" style="flex:1;display:flex;flex-direction:column;"></div>
  </section>

<?php endif; ?>

</div>

<!-- MODAL XEM MENU CHI TIẾT GÓI -->
<div class="qr-modal-overlay" id="qr-preview-modal">
  <div class="qr-modal-sheet">
    <div class="qr-modal-head">
      <button type="button" class="qr-modal-close" id="btn-close-preview-modal">&times;</button>
      <h3 class="qr-modal-title" id="qr-modal-pack-title">BUFFET XÈO XÈO CLASSIC</h3>
    </div>

    <div class="qr-modal-body" id="qr-modal-pack-body"></div>

    <div class="qr-modal-foot">
      <span class="qr-modal-foot-price" id="qr-modal-pack-price">529.000 đ /Người</span>
      <button type="button" class="qr-modal-foot-cta" id="btn-modal-choose-pack">Chọn menu</button>
    </div>
  </div>
</div>

<!-- MODAL CHI TIẾT & GHI CHÚ MÓN TRƯỚC KHI THÊM GIỎ -->
<div class="qr-modal-overlay" id="qr-dish-detail-modal">
  <div class="qr-modal-sheet" style="height:auto;max-height:85vh;">
    <div class="qr-modal-head">
      <button type="button" class="qr-modal-close" id="btn-close-dish-modal">&times;</button>
      <h3 class="qr-modal-title" id="qr-dish-modal-title">Chi tiết món</h3>
    </div>
    <div class="qr-modal-body" style="padding:16px;">
      <div style="display:flex;gap:12px;align-items:center;margin-bottom:14px;">
        <img id="qr-dish-modal-img" src="" alt="" style="width:76px;height:76px;border-radius:12px;object-fit:cover;background:#f3f4f6;">
        <div style="flex:1;min-width:0;">
          <div id="qr-dish-modal-name" style="font-size:16px;font-weight:800;color:#111827;"></div>
          <div id="qr-dish-modal-price" style="font-size:14px;font-weight:700;color:#d92d20;margin-top:4px;"></div>
          <div id="qr-dish-modal-desc" style="font-size:12px;color:#6b7280;margin-top:4px;line-height:1.4;"></div>
        </div>
      </div>

      <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-top:1px solid #e5e7eb;border-bottom:1px solid #e5e7eb;">
        <span style="font-size:14px;font-weight:700;">Số lượng</span>
        <div class="qr-qty-control">
          <button type="button" id="qr-detail-dec">-</button>
          <b id="qr-detail-qty" style="min-width:26px;text-align:center;font-size:15px;">1</b>
          <button type="button" id="qr-detail-inc">+</button>
        </div>
      </div>

      <div style="margin-top:14px;">
        <label for="qr-detail-note" style="display:block;font-size:13px;font-weight:700;margin-bottom:6px;">Ghi chú cho bếp (không bắt buộc)</label>
        <input type="text" id="qr-detail-note" maxlength="200" placeholder="Ví dụ: ít cay, không hành, mang ra sau..." style="width:100%;height:40px;border:1px solid #d1d5db;border-radius:10px;padding:0 12px;font-size:13px;outline:none;">
        <div style="display:flex;flex-wrap:wrap;gap:6px;margin-top:8px;">
          <button type="button" class="qr-note-chip" data-qr-note-chip="Ít cay">Ít cay</button>
          <button type="button" class="qr-note-chip" data-qr-note-chip="Không hành">Không hành</button>
          <button type="button" class="qr-note-chip" data-qr-note-chip="Mang ra trước">Mang ra trước</button>
          <button type="button" class="qr-note-chip" data-qr-note-chip="Không đá">Không đá</button>
        </div>
      </div>

      <button type="button" class="qr-btn-solid" id="btn-confirm-dish-detail" style="width:100%;margin-top:18px;background:#d92d20;">
        Thêm vào giỏ đồ ăn
      </button>
    </div>
  </div>
</div>

<?php if ($qrError === '') include __DIR__ . '/includes/customer-ai-widget.php'; ?>

<div class="qr-toast" id="qr-toast"></div>
<script src="<?= htmlspecialchars(assetUrl('assets/js/app.js')) ?>" defer></script>

<script>
const QR_DATA = {
  hasError: <?= json_encode($qrError !== '') ?>,
  apiUrl: 'api/qr-order.php',
  tableCode: <?= json_encode($tableCode, JSON_UNESCAPED_UNICODE) ?>,
  qrToken: <?= json_encode($qrToken, JSON_UNESCAPED_UNICODE) ?>,
  maxQty: <?= defined('ORDER_ADD_MAX_QTY') ? (int) ORDER_ADD_MAX_QTY : 20 ?>,
  packages: <?= json_encode(array_values($packagesPayload), JSON_UNESCAPED_UNICODE) ?>,
  buffetAllGroups: <?= json_encode($buffetGroupsAll, JSON_UNESCAPED_UNICODE) ?>,
  alacarteGroups: <?= json_encode($alacarteGroups, JSON_UNESCAPED_UNICODE) ?>
};

(function() {
  if (QR_DATA.hasError) return;

  const storageKey = 'smartdine_qr_cart_' + QR_DATA.tableCode + '_' + (QR_DATA.qrToken || '').slice(0, 12);

  const screenPack = document.getElementById('screen-pack-select');
  const screenMenu = document.getElementById('screen-menu-order');
  const screenCart = document.getElementById('screen-cart-view');
  const mainTopbar = document.getElementById('qr-main-topbar');

  const previewModal = document.getElementById('qr-preview-modal');
  const modalTitle = document.getElementById('qr-modal-pack-title');
  const modalBody = document.getElementById('qr-modal-pack-body');
  const modalPrice = document.getElementById('qr-modal-pack-price');
  const btnModalChoose = document.getElementById('btn-modal-choose-pack');

  const dishModal = document.getElementById('qr-dish-detail-modal');
  const detailImg = document.getElementById('qr-dish-modal-img');
  const detailName = document.getElementById('qr-dish-modal-name');
  const detailPrice = document.getElementById('qr-dish-modal-price');
  const detailDesc = document.getElementById('qr-dish-modal-desc');
  const detailQtyEl = document.getElementById('qr-detail-qty');
  const detailNoteEl = document.getElementById('qr-detail-note');
  const btnConfirmDetail = document.getElementById('btn-confirm-dish-detail');

  const catPillsEl = document.getElementById('qr-cat-pills');
  const menuSectionsEl = document.getElementById('qr-menu-sections');
  const searchDrawer = document.getElementById('qr-search-drawer');
  const searchInput = document.getElementById('qr-dish-search');
  const cartContentEl = document.getElementById('qr-cart-content');
  const cartBadgeEl = document.getElementById('qr-cart-badge');
  const stickyCartBar = document.getElementById('qr-sticky-cartbar');
  const stickyQtyEl = document.getElementById('qr-sticky-qty');
  const stickySubtotalEl = document.getElementById('qr-sticky-subtotal');
  const quickOrderedPill = document.getElementById('btn-quick-view-ordered');
  const toastEl = document.getElementById('qr-toast');

  let activeScreen = 'pack';
  let activeMenuTab = 'buffet';
  let activeCartTab = 'draft';
  let selectedPack = { type: 'buffet', id: QR_DATA.packages[0]?.id || 0 };
  let activeCatFilter = 'all';
  let searchQuery = '';
  let previewTarget = null;
  let detailDish = null;
  let detailQty = 1;
  let submitting = false;

  let draftCart = [];
  try {
    const saved = JSON.parse(localStorage.getItem(storageKey) || '[]');
    if (Array.isArray(saved)) draftCart = saved;
  } catch (e) {}

  let serverState = {
    order_id: 0,
    qr_payload: '',
    transfer_content: '',
    bank: null,
    auto_confirm_configured: false,
    ordered_items: [],
    dish_statuses: {},
    bill: { subtotal: 0, discount_amount: 0, service_fee: 0, vat: 0, total: 0 }
  };
  let lastKnownOrderId = 0;
  let paidReceiptInfo = null;
  const paymentStatusUrl = 'api/payment-status.php';

  function findDishInCatalog(dishId, dishName) {
    const targetId = Number(dishId || 0);
    const targetName = String(dishName || '').trim().toLowerCase();
    const pools = [
      ...getCurrentTabGroups(),
      ...(QR_DATA.packages || []).flatMap(p => p.groups || []),
      ...(QR_DATA.buffetAllGroups || []),
      ...(QR_DATA.alacarteGroups || [])
    ];
    let byNameMatch = null;
    for (const g of pools) {
      for (const d of (g.dishes || [])) {
        if (targetId > 0 && Number(d.id) === targetId) {
          return d;
        }
        if (!byNameMatch && targetName && String(d.name || '').trim().toLowerCase() === targetName) {
          byNameMatch = d;
        }
      }
    }
    return byNameMatch;
  }

  window.getAiCartSummary = () => {
    const combined = [];
    draftCart.forEach(item => {
      combined.push({ name: item.name, price: Number(item.price || 0), qty: Number(item.qty || 1) });
    });
    (serverState.ordered_items || []).forEach(item => {
      const existing = combined.find(x => x.name === item.name);
      if (existing) existing.qty += Number(item.qty || 1);
      else combined.push({ name: item.name, price: Number(item.price || 0), qty: Number(item.qty || 1) });
    });
    return combined;
  };

  window.onAiChatbotAddDish = ({ id, name, price }) => {
    const matched = findDishInCatalog(id, name);
    if (matched) {
      const liveStatus = serverState.dish_statuses?.[matched.id] || matched.status;
      if (liveStatus === 'disabled') {
        showToast(`Món "${matched.name}" hiện tạm hết.`);
        return true;
      }
      addDishToDraft(matched, 1, '');
      return true;
    }
    if (Number(id || 0) > 0 && name) {
      addDishToDraft({
        id: Number(id),
        name: String(name),
        price: Number(price || 0),
        menu_type: Number(price || 0) <= 0 ? 'buffet' : 'alacarte',
        image: ''
      }, 1, '');
      return true;
    }
    showToast(`Không tìm thấy món "${name || ''}" trên thực đơn khả dụng.`);
    return true;
  };

  function persistCart() {
    try {
      localStorage.setItem(storageKey, JSON.stringify(draftCart));
    } catch (e) {}
  }

  function showToast(msg) {
    toastEl.textContent = msg;
    toastEl.classList.add('show');
    clearTimeout(showToast._t);
    showToast._t = setTimeout(() => toastEl.classList.remove('show'), 2500);
  }

  function formatMoney(n) {
    return new Intl.NumberFormat('vi-VN').format(Number(n || 0)) + 'đ';
  }

  function escHtml(s) {
    return String(s || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function removeAccents(str) {
    return String(str || '')
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .replace(/đ/gi, 'd')
      .toLowerCase();
  }

  function dishLiveStatus(dish) {
    const live = serverState.dish_statuses && serverState.dish_statuses[Number(dish.id)];
    return live || dish.status || 'active';
  }

  function getPackObject(type, id) {
    if (type === 'alacarte') {
      return {
        id: 0,
        name: 'MENU GỌI MÓN',
        price_label: 'Gọi theo món lẻ',
        groups: QR_DATA.alacarteGroups
      };
    }
    const found = QR_DATA.packages.find(p => Number(p.id) === Number(id));
    if (found) return found;
    return {
      id: 0,
      name: 'MÓN BUFFET',
      price_label: 'Theo gói Buffet',
      groups: QR_DATA.buffetAllGroups
    };
  }

  function getCurrentTabGroups() {
    if (activeMenuTab === 'alacarte') {
      return QR_DATA.alacarteGroups;
    }
    const pkg = getPackObject('buffet', selectedPack.id);
    return pkg.groups && pkg.groups.length ? pkg.groups : QR_DATA.buffetAllGroups;
  }

  function switchMainScreen(target) {
    activeScreen = target;
    screenPack.style.display = target === 'pack' ? 'flex' : 'none';
    screenMenu.style.display = target === 'menu' ? 'block' : 'none';
    screenCart.style.display = target === 'cart' ? 'flex' : 'none';
    mainTopbar.style.display = target === 'cart' ? 'none' : 'flex';
  }

  function updateCartBadge() {
    const totalQty = draftCart.reduce((sum, item) => sum + Number(item.qty || 0), 0);
    const totalAmt = draftCart.reduce((sum, item) => sum + (Number(item.price || 0) * Number(item.qty || 0)), 0);
    cartBadgeEl.textContent = String(totalQty);
    cartBadgeEl.classList.toggle('has-items', totalQty > 0);
    if (stickyCartBar) {
      stickyCartBar.style.display = (totalQty > 0 && activeScreen === 'menu') ? 'flex' : 'none';
      if (stickyQtyEl) stickyQtyEl.textContent = String(totalQty);
      if (stickySubtotalEl) stickySubtotalEl.textContent = formatMoney(totalAmt);
    }
  }

  function addDishToDraft(dish, qtyToAdd, noteText) {
    const status = dishLiveStatus(dish);
    if (status === 'out_of_stock' || status === 'hidden') {
      showToast('Món "' + dish.name + '" hiện tạm hết.');
      return;
    }

    const cleanNote = String(noteText || '').trim().slice(0, 200);
    const existing = draftCart.find(i => Number(i.id) === Number(dish.id) && (i.note || '') === cleanNote);
    if (existing) {
      existing.qty = Math.min(QR_DATA.maxQty, Number(existing.qty) + Number(qtyToAdd || 1));
    } else {
      draftCart.push({
        id: Number(dish.id),
        name: dish.name,
        price: Number(dish.price || 0),
        menu_type: dish.menu_type || activeMenuTab,
        image: dish.image || '',
        qty: Math.min(QR_DATA.maxQty, Math.max(1, Number(qtyToAdd || 1))),
        note: cleanNote
      });
    }
    persistCart();
    updateCartBadge();
    showToast('Đã thêm "' + dish.name + '" vào giỏ');
  }

  function renderDishGridCards(dishes, isBuffetMode, interactive) {
    if (!dishes || !dishes.length) {
      return '<p style="color:#6b7280;font-size:13px;">Chưa có món ăn nào trong mục này.</p>';
    }
    return '<div class="qr-dish-grid">' + dishes.map(d => {
      const isOut = dishLiveStatus(d) !== 'active';
      const metaText = isBuffetMode && Number(d.price) <= 0 ? 'Buffet' : formatMoney(d.price);
      const metaClass = (!isBuffetMode || Number(d.price) > 0) ? 'qr-dish-meta is-price' : 'qr-dish-meta';
      const img = d.image ? d.image : 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?auto=format&fit=crop&w=700&q=80';
      return `
        <article class="qr-dish-card ${isOut ? 'is-out' : ''}" ${interactive && !isOut ? `data-open-dish-detail="${d.id}" style="cursor:pointer;"` : ''}>
          <div class="qr-dish-img-wrap">
            <img src="${escHtml(img)}" alt="${escHtml(d.name)}" loading="lazy" onerror="this.src='https://images.unsplash.com/photo-1546069901-ba9599a7e63c?auto=format&fit=crop&w=700&q=80'">
            ${isOut ? '<span class="qr-dish-tag" style="background:#4b5563;">Tạm hết</span>' : (d.tag ? `<span class="qr-dish-tag">${escHtml(d.tag)}</span>` : '')}
            ${interactive && !isOut ? `<button type="button" class="qr-dish-add-btn" data-add-dish-id="${d.id}" title="Chọn nhanh 1 phần">+</button>` : ''}
          </div>
          <div class="qr-dish-info">
            <h4 class="qr-dish-name">${escHtml(d.name)}</h4>
            <div class="${metaClass}">${metaText}</div>
          </div>
        </article>
      `;
    }).join('') + '</div>';
  }

  function renderMenuOrderScreen() {
    document.querySelectorAll('#screen-menu-order .qr-tab-btn').forEach(btn => {
      btn.classList.toggle('active', btn.dataset.menuTab === activeMenuTab);
    });

    const groups = getCurrentTabGroups();
    const pills = ['<button type="button" class="qr-cat-pill ' + (activeCatFilter === 'all' ? 'active' : '') + '" data-cat-id="all">TẤT CẢ</button>'];
    groups.forEach(g => {
      pills.push('<button type="button" class="qr-cat-pill ' + (String(activeCatFilter) === String(g.id) ? 'active' : '') + '" data-cat-id="' + g.id + '">' + escHtml(g.name) + '</button>');
    });
    catPillsEl.innerHTML = pills.join('');

    const words = removeAccents(searchQuery).split(/\s+/).filter(Boolean);
    const filteredGroups = groups
      .filter(g => activeCatFilter === 'all' || String(g.id) === String(activeCatFilter))
      .map(g => ({
        ...g,
        dishes: (g.dishes || []).filter(d => {
          if (!words.length) return true;
          const hay = removeAccents(d.name || '');
          return words.every(w => hay.includes(w));
        })
      }))
      .filter(g => g.dishes.length > 0);

    if (!filteredGroups.length) {
      menuSectionsEl.innerHTML = '<div style="text-align:center;padding:40px 16px;color:#6b7280;font-size:13px;">Chưa có món ăn phù hợp trong mục này. Admin có thể cấu hình thêm món tại Quản lý thực đơn.</div>';
    } else {
      const isBuffetMode = activeMenuTab === 'buffet';
      menuSectionsEl.innerHTML = filteredGroups.map(g => `
        <div class="qr-cat-section">
          <div class="qr-cat-section-head">
            <h3>${escHtml(g.name)}</h3>
            <span class="qr-cat-count">${g.dishes.length} món</span>
          </div>
          ${renderDishGridCards(g.dishes, isBuffetMode, true)}
        </div>
      `).join('');
    }
    updateCartBadge();
  }

  function openPreviewModal(type, id) {
    previewTarget = { type, id };
    const pack = getPackObject(type, id);
    modalTitle.textContent = pack.name;
    modalPrice.textContent = pack.price_label;

    const groups = pack.groups || [];
    if (!groups.length) {
      modalBody.innerHTML = '<div style="text-align:center;padding:40px 16px;color:#6b7280;font-size:13px;">Chưa có món ăn nào thuộc thực đơn này.</div>';
    } else {
      modalBody.innerHTML = groups.map(g => `
        <div class="qr-cat-section">
          <div class="qr-cat-section-head">
            <h3>${escHtml(g.name)}</h3>
            <span class="qr-cat-count">${g.dishes.length} món</span>
          </div>
          ${renderDishGridCards(g.dishes, type === 'buffet', false)}
        </div>
      `).join('');
    }
    previewModal.classList.add('open');
  }

  function openDishDetailModal(dish) {
    detailDish = dish;
    detailQty = 1;
    detailNoteEl.value = '';
    detailQtyEl.textContent = '1';
    document.getElementById('qr-dish-modal-title').textContent = 'Chọn món';
    detailName.textContent = dish.name;
    detailPrice.textContent = (dish.menu_type === 'buffet' && Number(dish.price) <= 0) ? 'Trong gói Buffet' : formatMoney(dish.price);
    detailDesc.textContent = dish.description || 'Món ăn phục vụ tại bàn từ thực đơn SmartDine.';
    detailImg.src = dish.image || 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?auto=format&fit=crop&w=400&q=80';
    dishModal.querySelectorAll('[data-qr-note-chip]').forEach(ch => ch.classList.remove('active'));
    dishModal.classList.add('open');
  }

  function selectMenuPack(type, id) {
    selectedPack = { type, id };
    activeMenuTab = (type === 'alacarte') ? 'alacarte' : 'buffet';
    activeCatFilter = 'all';
    searchQuery = '';
    searchInput.value = '';
    previewModal.classList.remove('open');
    switchMainScreen('menu');
    renderMenuOrderScreen();
  }

  function orderedStatusBadge(status) {
    switch (status) {
      case 'done':
      case 'served':
        return '<span class="qr-status-pill is-done">Đã ra món</span>';
      case 'cooking':
        return '<span class="qr-status-pill is-cooking">Đang chế biến</span>';
      default:
        return '<span class="qr-status-pill is-pending">Chờ bếp tiếp nhận</span>';
    }
  }

  function renderCartView() {
    document.querySelectorAll('#screen-cart-view .qr-tab-btn').forEach(btn => {
      const tabName = btn.dataset.cartTab;
      btn.classList.toggle('active', tabName === activeCartTab);
      if (tabName === 'ordered') {
        const cnt = (serverState.ordered_items || []).reduce((s, x) => s + Number(x.qty || 0), 0);
        btn.textContent = cnt > 0 ? `Món đã gọi (${cnt})` : 'Món đã gọi';
      }
    });

    if (activeCartTab === 'ordered') {
      if (paidReceiptInfo) {
        cartContentEl.innerHTML = `
          <div class="qr-payment-success-card">
            <div style="font-size:38px;line-height:1;">✅</div>
            <h3>Thanh toán thành công!</h3>
            <p>
              Hóa đơn Bàn ${escHtml(QR_DATA.tableCode)} · <b>${formatMoney(paidReceiptInfo.total || 0)}</b> đã được xác nhận hoàn tất. Cảm ơn quý khách!
            </p>
            <div style="display:flex;flex-direction:column;gap:8px;max-width:260px;margin:0 auto;">
              ${paidReceiptInfo.rating_url ? `<a class="qr-btn-solid" style="display:block;text-decoration:none;text-align:center;" href="${escHtml(paidReceiptInfo.rating_url)}">⭐ Đánh giá phục vụ</a>` : ''}
              <button type="button" class="qr-btn-outline" id="btn-dismiss-paid-banner">+ Tiếp tục gọi món mới</button>
            </div>
          </div>
        `;
        return;
      }

      const ordered = serverState.ordered_items || [];
      if (!ordered.length) {
        cartContentEl.innerHTML = `
          <div class="qr-empty-cart">
            <div class="qr-empty-basket">
              <svg width="62" height="62" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M9 11l3 3L22 4"></path><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path></svg>
            </div>
            <p>Chưa có món nào được gửi xuống bếp từ bàn ${escHtml(QR_DATA.tableCode)}.</p>
            <button type="button" class="qr-empty-cta" id="btn-empty-add-more">Thêm món ngay</button>
          </div>
        `;
        return;
      }

      const bill = serverState.bill || { subtotal: 0, discount_amount: 0, service_fee: 0, vat: 0, total: 0 };
      const bank = serverState.bank || { name: 'VietinBank', account_no: '109881125028', account_name: 'TRAN HUYNH GIA NGUYEN' };
      const showPayQr = Number(bill.total || 0) > 0 && Boolean(serverState.qr_payload);

      cartContentEl.innerHTML = `
        <div class="qr-cart-list">
          ${ordered.map(item => `
            <div class="qr-cart-row">
              <img src="${escHtml(item.image || 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?auto=format&fit=crop&w=200&q=80')}" alt="${escHtml(item.name)}" onerror="this.src='https://images.unsplash.com/photo-1546069901-ba9599a7e63c?auto=format&fit=crop&w=200&q=80'">
              <div style="flex:1;min-width:0;">
                <div style="font-size:14px;font-weight:700;">${item.qty} × ${escHtml(item.name)}</div>
                <div style="font-size:12px;color:#6b7280;margin-top:3px;">
                  ${Number(item.price) <= 0 ? 'Buffet' : formatMoney(Number(item.price) * Number(item.qty))}
                  ${item.sent_at ? ' · ' + escHtml(item.sent_at) : ''}
                </div>
                ${item.note ? `<div style="font-size:11px;color:#b45309;margin-top:2px;">Ghi chú: ${escHtml(item.note)}</div>` : ''}
              </div>
              <div>${orderedStatusBadge(item.status)}</div>
            </div>
          `).join('')}

          <div class="qr-bill-summary-card">
            <div class="qr-bill-row"><span>Tạm tính món đã gọi</span><b>${formatMoney(bill.subtotal)}</b></div>
            ${Number(bill.discount_amount || 0) > 0 ? `<div class="qr-bill-row" style="color:#059669;"><span>Giảm giá / Voucher</span><b>-${formatMoney(bill.discount_amount)}</b></div>` : ''}
            <div class="qr-bill-row"><span>Phí phục vụ (5%)</span><b>${formatMoney(bill.service_fee)}</b></div>
            <div class="qr-bill-row"><span>Thuế VAT (8%)</span><b>${formatMoney(bill.vat)}</b></div>
            <div class="qr-bill-row total"><span>Tổng cộng thanh toán</span><b>${formatMoney(bill.total)}</b></div>
          </div>

          ${showPayQr ? `
            <div class="qr-payment-card" id="qr-customer-payment-card">
              <div class="qr-payment-card-head">
                <h3>Quét QR thanh toán tại bàn</h3>
                <span class="qr-bank-badge">${escHtml(bank.name || 'VietinBank')}</span>
              </div>
              <div class="qr-payment-qrbox" id="qr-customer-pay-svg"></div>
              <div class="qr-payment-status-pill ${serverState.auto_confirm_configured ? 'is-auto' : ''}">
                ${serverState.auto_confirm_configured
                  ? '🔄 Hệ thống tự động xác nhận ngay sau khi chuyển khoản thành công.'
                  : 'ℹ️ Quét mã đúng số tiền và nội dung bên dưới để hoàn tất thanh toán.'}
              </div>
              <dl class="qr-payment-dl">
                <div><dt>Ngân hàng</dt><dd>${escHtml(bank.name || 'VietinBank')}</dd></div>
                <div><dt>Số tài khoản</dt><dd>${escHtml(bank.account_no || '109881125028')}</dd></div>
                <div><dt>Chủ tài khoản</dt><dd>${escHtml(bank.account_name || 'TRAN HUYNH GIA NGUYEN')}</dd></div>
                <div><dt>Số tiền</dt><dd style="color:#d92d20;">${formatMoney(bill.total)}</dd></div>
                <div><dt>Nội dung CK</dt><dd>${escHtml(serverState.transfer_content || '')}</dd></div>
              </dl>
            </div>
          ` : ''}

          <button type="button" class="qr-btn-solid" id="btn-empty-add-more" style="margin-top:6px;">+ Gọi thêm món</button>
        </div>
      `;

      if (showPayQr && typeof qrcode === 'function') {
        const paySvgBox = document.getElementById('qr-customer-pay-svg');
        if (paySvgBox) {
          const qr = qrcode(0, 'M');
          qr.addData(serverState.qr_payload);
          qr.make();
          paySvgBox.innerHTML = qr.createSvgTag({ cellSize: 5, margin: 2, scalable: true });
        }
      }
      return;
    }

    if (!draftCart.length) {
      cartContentEl.innerHTML = `
        <div class="qr-empty-cart">
          <div class="qr-empty-basket">
            <svg width="68" height="68" viewBox="0 0 24 24" fill="currentColor"><path d="M17.21 9l-4.38-6.56c-.19-.28-.51-.42-.83-.42-.32 0-.64.14-.83.43L6.79 9H2c-.55 0-1 .45-1 1 0 .09.01.18.04.27l2.54 9.27c.23.84 1 1.46 1.92 1.46h13c.92 0 1.69-.62 1.93-1.46l2.54-9.27L23 10c0-.55-.45-1-1-1h-4.79zM9 9l3-4.4L15 9H9zm3 8c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2z"></path></svg>
          </div>
          <p>Bạn chưa thêm món nào vào giỏ đồ ăn.</p>
          <button type="button" class="qr-empty-cta" id="btn-empty-add-more">Thêm món ngay</button>
        </div>
      `;
      return;
    }

    const totalAmount = draftCart.reduce((sum, i) => sum + (Number(i.price) * Number(i.qty)), 0);
    cartContentEl.innerHTML = `
      <div class="qr-cart-list">
        ${draftCart.map((item, idx) => `
          <div class="qr-cart-row">
            <img src="${escHtml(item.image || 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?auto=format&fit=crop&w=200&q=80')}" alt="${escHtml(item.name)}" onerror="this.src='https://images.unsplash.com/photo-1546069901-ba9599a7e63c?auto=format&fit=crop&w=200&q=80'">
            <div style="flex:1;min-width:0;">
              <div style="font-size:14px;font-weight:700;">${escHtml(item.name)}</div>
              <div style="font-size:12px;color:#6b7280;margin-top:3px;">${item.menu_type === 'buffet' && Number(item.price) <= 0 ? 'Buffet' : formatMoney(item.price)}</div>
              ${item.note ? `<div style="font-size:11px;color:#b45309;margin-top:2px;">Ghi chú: ${escHtml(item.note)}</div>` : ''}
            </div>
            <div class="qr-qty-control">
              <button type="button" data-cart-dec-idx="${idx}">-</button>
              <b style="min-width:18px;text-align:center;font-size:13px;">${item.qty}</b>
              <button type="button" data-cart-inc-idx="${idx}">+</button>
            </div>
          </div>
        `).join('')}

        <div style="margin-top:8px;">
          <input type="text" id="qr-order-global-note" maxlength="120" placeholder="Ghi chú chung cho đơn (không bắt buộc)..." style="width:100%;height:40px;border:1px solid #d1d5db;border-radius:10px;padding:0 12px;font-size:13px;outline:none;">
        </div>

        <div style="margin-top:6px;padding:12px;border-radius:12px;background:#f9fafb;display:flex;justify-content:space-between;align-items:center;">
          <span style="font-size:13px;color:#6b7280;">Tạm tính giỏ đồ ăn</span>
          <b style="font-size:16px;color:#d92d20;">${formatMoney(totalAmount)}</b>
        </div>
        <button type="button" class="qr-btn-solid" id="btn-submit-qr-order" style="margin-top:6px;background:#d92d20;" ${submitting ? 'disabled' : ''}>
          ${submitting ? 'Đang gửi đơn xuống bếp...' : 'Xác nhận gọi món · Gửi xuống bếp'}
        </button>
      </div>
    `;
  }

  async function refreshServerState() {
    try {
      const qs = new URLSearchParams({
        action: 'state',
        qr: QR_DATA.qrToken,
        table: QR_DATA.tableCode
      });
      const res = await fetch(QR_DATA.apiUrl + '?' + qs.toString(), { cache: 'no-store' });
      if (!res.ok) return;
      const data = await res.json();
      if (!data || !data.success) return;

      const prevOrderId = lastKnownOrderId;
      serverState = {
        order_id: Number(data.order_id || 0),
        qr_payload: data.qr_payload || '',
        transfer_content: data.transfer_content || '',
        bank: data.bank || null,
        auto_confirm_configured: Boolean(data.auto_confirm_configured),
        ordered_items: data.ordered_items || [],
        dish_statuses: data.dish_statuses || {},
        bill: data.bill || { subtotal: 0, discount_amount: 0, service_fee: 0, vat: 0, total: 0 }
      };

      if (serverState.order_id > 0) {
        lastKnownOrderId = serverState.order_id;
        paidReceiptInfo = null;
      } else if (prevOrderId > 0) {
        await checkPaidOrderStatus(prevOrderId);
      }

      const orderedCount = serverState.ordered_items.reduce((s, i) => s + Number(i.qty || 0), 0);
      if (quickOrderedPill) {
        if (orderedCount > 0) {
          quickOrderedPill.style.display = 'inline-flex';
          quickOrderedPill.textContent = `Đã gọi ${orderedCount} món · Xem`;
        } else {
          quickOrderedPill.style.display = 'none';
        }
      }

      if (activeScreen === 'menu') renderMenuOrderScreen();
      if (activeScreen === 'cart') renderCartView();
    } catch (e) {}
  }

  async function checkPaidOrderStatus(targetOrderId) {
    if (!targetOrderId || targetOrderId <= 0) return;
    try {
      const qs = new URLSearchParams({
        qr: QR_DATA.qrToken,
        table: QR_DATA.tableCode,
        order_id: String(targetOrderId)
      });
      const res = await fetch(paymentStatusUrl + '?' + qs.toString(), { cache: 'no-store' });
      if (!res.ok) return;
      const st = await res.json();
      if (st && st.paid) {
        paidReceiptInfo = st;
        lastKnownOrderId = 0;
        activeCartTab = 'ordered';
        switchMainScreen('cart');
        renderCartView();
        showToast('Thanh toán thành công Bàn ' + QR_DATA.tableCode + '!');
      }
    } catch (e) {}
  }

  async function submitQrOrder() {
    if (submitting || !draftCart.length) return;
    const btn = document.getElementById('btn-submit-qr-order');
    const noteEl = document.getElementById('qr-order-global-note');
    const orderNote = noteEl ? noteEl.value.trim() : '';

    submitting = true;
    if (btn) {
      btn.disabled = true;
      btn.textContent = 'Đang gửi đơn xuống bếp...';
    }

    try {
      const res = await fetch(QR_DATA.apiUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          action: 'submit',
          qr: QR_DATA.qrToken,
          table: QR_DATA.tableCode,
          order_note: orderNote,
          items: draftCart.map(i => ({
            dish_id: Number(i.id),
            qty: Number(i.qty),
            note: i.note || ''
          }))
        })
      });
      const data = await res.json();
      if (!res.ok || !data.success) {
        showToast(data.message || 'Không gửi được đơn, vui lòng thử lại.');
        return;
      }

      draftCart = [];
      persistCart();
      updateCartBadge();

      const prevOrderId = lastKnownOrderId;
      serverState = {
        order_id: Number(data.order_id || 0),
        qr_payload: data.qr_payload || '',
        transfer_content: data.transfer_content || '',
        bank: data.bank || null,
        auto_confirm_configured: Boolean(data.auto_confirm_configured),
        ordered_items: data.ordered_items || [],
        dish_statuses: data.dish_statuses || {},
        bill: data.bill || { subtotal: 0, discount_amount: 0, service_fee: 0, vat: 0, total: 0 }
      };

      if (serverState.order_id > 0) {
        lastKnownOrderId = serverState.order_id;
        paidReceiptInfo = null;
      } else if (prevOrderId > 0) {
        await checkPaidOrderStatus(prevOrderId);
      }

      activeCartTab = 'ordered';
      renderCartView();
      showToast(data.message || 'Đã gửi đơn xuống bếp!');
    } catch (e) {
      showToast('Mất kết nối máy chủ, vui lòng thử lại.');
    } finally {
      submitting = false;
      const b = document.getElementById('btn-submit-qr-order');
      if (b) {
        b.disabled = false;
        b.textContent = 'Xác nhận gọi món · Gửi xuống bếp';
      }
    }
  }

  function findDishInCurrentGroups(dishId) {
    let found = null;
    getCurrentTabGroups().forEach(g => {
      (g.dishes || []).forEach(d => {
        if (Number(d.id) === Number(dishId)) found = d;
      });
    });
    return found;
  }

  // Events
  document.getElementById('btn-call-staff')?.addEventListener('click', async () => {
    try {
      const res = await fetch(QR_DATA.apiUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          action: 'call_staff',
          qr: QR_DATA.qrToken,
          table: QR_DATA.tableCode
        })
      });
      const data = await res.json();
      showToast(data.message || ('Đã báo nhân viên hỗ trợ Bàn ' + QR_DATA.tableCode));
    } catch (e) {
      showToast('Đã báo nhân viên hỗ trợ Bàn ' + QR_DATA.tableCode);
    }
  });

  document.getElementById('btn-qr-rating-info')?.addEventListener('click', () => {
    showToast('Quý khách có thể quét mã QR trên hóa đơn sau khi thanh toán để đánh giá!');
  });

  quickOrderedPill?.addEventListener('click', () => {
    activeCartTab = 'ordered';
    switchMainScreen('cart');
    renderCartView();
  });

  document.getElementById('btn-back-pack-select')?.addEventListener('click', () => {
    switchMainScreen('pack');
    updateCartBadge();
  });

  document.getElementById('btn-open-cart-view')?.addEventListener('click', () => {
    switchMainScreen('cart');
    renderCartView();
    refreshServerState();
  });

  document.getElementById('btn-sticky-open-cart')?.addEventListener('click', () => {
    activeCartTab = 'draft';
    switchMainScreen('cart');
    renderCartView();
  });

  document.getElementById('btn-close-cart-view')?.addEventListener('click', () => {
    switchMainScreen(screenMenu.dataset.entered === '1' ? 'menu' : 'pack');
    updateCartBadge();
  });

  document.querySelectorAll('.btn-preview-pack').forEach(btn => {
    btn.addEventListener('click', () => openPreviewModal(btn.dataset.packType, btn.dataset.packId));
  });

  document.querySelectorAll('.btn-choose-pack').forEach(btn => {
    btn.addEventListener('click', () => {
      screenMenu.dataset.entered = '1';
      selectMenuPack(btn.dataset.packType, btn.dataset.packId);
    });
  });

  document.getElementById('btn-close-preview-modal')?.addEventListener('click', () => {
    previewModal.classList.remove('open');
  });

  previewModal?.addEventListener('click', (e) => {
    if (e.target === previewModal) previewModal.classList.remove('open');
  });

  btnModalChoose?.addEventListener('click', () => {
    if (previewTarget) {
      screenMenu.dataset.entered = '1';
      selectMenuPack(previewTarget.type, previewTarget.id);
    }
  });

  document.getElementById('btn-close-dish-modal')?.addEventListener('click', () => {
    dishModal.classList.remove('open');
  });
  dishModal?.addEventListener('click', (e) => {
    if (e.target === dishModal) dishModal.classList.remove('open');
  });
  document.getElementById('qr-detail-dec')?.addEventListener('click', () => {
    detailQty = Math.max(1, detailQty - 1);
    detailQtyEl.textContent = String(detailQty);
  });
  document.getElementById('qr-detail-inc')?.addEventListener('click', () => {
    detailQty = Math.min(QR_DATA.maxQty, detailQty + 1);
    detailQtyEl.textContent = String(detailQty);
  });
  dishModal?.querySelectorAll('[data-qr-note-chip]').forEach(chip => {
    chip.addEventListener('click', () => {
      const val = chip.dataset.qrNoteChip;
      const parts = detailNoteEl.value.split(',').map(s => s.trim()).filter(Boolean);
      const exists = parts.includes(val);
      detailNoteEl.value = (exists ? parts.filter(s => s !== val) : [...parts, val]).join(', ');
      chip.classList.toggle('active', !exists);
    });
  });
  btnConfirmDetail?.addEventListener('click', () => {
    if (!detailDish) return;
    addDishToDraft(detailDish, detailQty, detailNoteEl.value);
    dishModal.classList.remove('open');
  });

  document.querySelectorAll('#screen-menu-order .qr-tab-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      activeMenuTab = btn.dataset.menuTab;
      activeCatFilter = 'all';
      renderMenuOrderScreen();
    });
  });

  catPillsEl?.addEventListener('click', (e) => {
    const pill = e.target.closest('.qr-cat-pill');
    if (!pill) return;
    activeCatFilter = pill.dataset.catId;
    renderMenuOrderScreen();
  });

  document.getElementById('btn-toggle-qr-search')?.addEventListener('click', () => {
    searchDrawer.classList.toggle('open');
    if (searchDrawer.classList.contains('open')) searchInput.focus();
  });

  searchInput?.addEventListener('input', () => {
    searchQuery = searchInput.value;
    renderMenuOrderScreen();
  });

  menuSectionsEl?.addEventListener('click', (e) => {
    const addBtn = e.target.closest('[data-add-dish-id]');
    if (addBtn) {
      e.stopPropagation();
      const dish = findDishInCurrentGroups(addBtn.dataset.addDishId);
      if (dish) addDishToDraft(dish, 1, '');
      return;
    }

    const card = e.target.closest('[data-open-dish-detail]');
    if (card) {
      const dish = findDishInCurrentGroups(card.dataset.openDishDetail);
      if (dish) openDishDetailModal(dish);
    }
  });

  document.querySelectorAll('#screen-cart-view .qr-tab-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      activeCartTab = btn.dataset.cartTab;
      renderCartView();
      if (activeCartTab === 'ordered') refreshServerState();
    });
  });

  cartContentEl?.addEventListener('click', (e) => {
    if (e.target.id === 'btn-dismiss-paid-banner') {
      paidReceiptInfo = null;
      renderCartView();
      return;
    }
    if (e.target.id === 'btn-empty-add-more') {
      screenMenu.dataset.entered = '1';
      switchMainScreen('menu');
      renderMenuOrderScreen();
      return;
    }
    if (e.target.id === 'btn-submit-qr-order') {
      submitQrOrder();
      return;
    }
    const incIdx = e.target.getAttribute('data-cart-inc-idx');
    const decIdx = e.target.getAttribute('data-cart-dec-idx');
    if (incIdx !== null) {
      const item = draftCart[Number(incIdx)];
      if (item && item.qty < QR_DATA.maxQty) item.qty += 1;
      persistCart();
      updateCartBadge();
      renderCartView();
    } else if (decIdx !== null) {
      const idx = Number(decIdx);
      if (draftCart[idx]) {
        draftCart[idx].qty -= 1;
        if (draftCart[idx].qty <= 0) draftCart.splice(idx, 1);
      }
      persistCart();
      updateCartBadge();
      renderCartView();
    }
  });

  updateCartBadge();
  refreshServerState();
  setInterval(() => {
    if (!document.hidden) {
      refreshServerState();
    }
  }, 3000);
})();
</script>
</body>
</html>

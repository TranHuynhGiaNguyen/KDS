<?php
/**
 * SmartDine - Giao diện Khách Quét QR Gọi Món Tại Bàn (Buffet & Gọi món lẻ)
 * Đọc trực tiếp cấu hình Thực đơn Admin (Gói Buffet, Danh mục, Món ăn).
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/tables.php';

$pdo = getDbConnection();
if ($pdo instanceof PDO) {
    ensureMenuConfigSchema($pdo);
}

$requestedTableCode = strtoupper(trim((string) ($_GET['table'] ?? '')));
$allTables = getAllDiningTables('', 'active', '');
$currentTable = null;

if ($requestedTableCode !== '') {
    $currentTable = getDiningTableByCode($requestedTableCode);
}
if (!$currentTable && !empty($allTables)) {
    $currentTable = $allTables[0];
}
$tableCode = $currentTable ? (string) $currentTable['code'] : ($requestedTableCode !== '' ? $requestedTableCode : 'B01');

$buffetPackages = getAllBuffetPackages(true);
$allVisibleDishes = getAllDishes('', 'visible', '');

// Phân tách món Buffet và Món tính tiền (Gọi món lẻ)
$buffetDishes = [];
$alacarteDishes = [];

foreach ($allVisibleDishes as $dish) {
    if (($dish['menu_type'] ?? 'alacarte') === 'buffet') {
        $buffetDishes[] = $dish;
    } else {
        $alacarteDishes[] = $dish;
    }
}

/**
 * Nhóm danh sách món theo danh mục giữ nguyên thứ tự hiển thị.
 */
function groupDishesByCategory(array $dishes): array {
    $groups = [];
    foreach ($dishes as $dish) {
        $catId = (int) ($dish['category_id'] ?? 0);
        if (!isset($groups[$catId])) {
            $groups[$catId] = [
                'id'   => $catId,
                'name' => (string) ($dish['category_name'] ?? 'Khác'),
                'slug' => (string) ($dish['category_slug'] ?? 'khac'),
                'dishes' => [],
            ];
        }
        $groups[$catId]['dishes'][] = $dish;
    }
    return array_values($groups);
}

// Chuẩn bị dữ liệu cho từng gói Buffet (lấy món gán cho gói hoặc món buffet dùng chung)
$packagesPayload = [];
foreach ($buffetPackages as $pkg) {
    $pkgId = (int) $pkg['id'];
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

$alacarteGroups = groupDishesByCategory($alacarteDishes);
$buffetGroupsAll = groupDishesByCategory($buffetDishes);
$alacarteCover = 'https://images.unsplash.com/photo-1555939594-58d7cb561ad1?auto=format&fit=crop&w=900&q=80';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <title>QR Order Bàn <?= htmlspecialchars($tableCode) ?> — SmartDine</title>
  <link rel="stylesheet" href="assets/css/qr-order.css?v=<?= time() ?>">
</head>
<body class="qr-order-body">

<div class="qr-app-shell">

  <!-- =========================================================
       TOP BAR CHUNG (Ảnh 2 & 4)
       ========================================================= -->
  <header class="qr-topbar" id="qr-main-topbar">
    <button type="button" class="qr-brand-btn" id="btn-back-pack-select" title="Về chọn hình thức Menu">
      <span class="qr-brand-circle">SD</span>
      <span>SmartDine</span>
    </button>

    <div class="qr-topbar-right">
      <button type="button" class="qr-btn-call-staff" id="btn-call-staff">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
        <span>Gọi NV</span>
      </button>

      <span class="qr-table-pill">Bàn <?= htmlspecialchars($tableCode) ?></span>

      <button type="button" class="qr-icon-btn" id="btn-open-cart-view" title="Gọi món của bạn">
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path></svg>
        <span class="qr-cart-badge" id="qr-cart-badge">0</span>
      </button>

      <a class="qr-icon-btn star-btn" href="rate.php?table=<?= urlencode($tableCode) ?>" title="Đánh giá trải nghiệm">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
      </a>
    </div>
  </header>

  <!-- =========================================================
       MÀN HÌNH 1: CHỌN GÓI BUFFET HOẶC MENU GỌI MÓN LẺ (Ảnh 4)
       ========================================================= -->
  <main id="screen-pack-select" class="qr-select-screen">
    <div class="qr-notice-bar">
      <span>Quét QR tại <b>Bàn <?= htmlspecialchars($tableCode) ?></b> · Chọn gói Buffet hoặc Menu gọi món</span>
      <span style="font-weight:700;opacity:.8;">UI Preview</span>
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

  <!-- =========================================================
       MÀN HÌNH 2: THỰC ĐƠN GỌI MÓN (Món buffet | Món tính tiền - Ảnh 2)
       ========================================================= -->
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
  </section>

  <!-- =========================================================
       MÀN HÌNH 3: GỌI MÓN CỦA BẠN (Giỏ đồ ăn | Món đã gọi - Ảnh 1)
       ========================================================= -->
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

</div>

<!-- =========================================================
     MODAL XEM MENU CHI TIẾT (Ảnh 3)
     ========================================================= -->
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

<div class="qr-toast" id="qr-toast"></div>

<script>
const QR_DATA = {
  tableCode: <?= json_encode($tableCode, JSON_UNESCAPED_UNICODE) ?>,
  packages: <?= json_encode(array_values($packagesPayload), JSON_UNESCAPED_UNICODE) ?>,
  buffetAllGroups: <?= json_encode($buffetGroupsAll, JSON_UNESCAPED_UNICODE) ?>,
  alacarteGroups: <?= json_encode($alacarteGroups, JSON_UNESCAPED_UNICODE) ?>
};

(function() {
  const screenPack = document.getElementById('screen-pack-select');
  const screenMenu = document.getElementById('screen-menu-order');
  const screenCart = document.getElementById('screen-cart-view');
  const mainTopbar = document.getElementById('qr-main-topbar');

  const previewModal = document.getElementById('qr-preview-modal');
  const modalTitle = document.getElementById('qr-modal-pack-title');
  const modalBody = document.getElementById('qr-modal-pack-body');
  const modalPrice = document.getElementById('qr-modal-pack-price');
  const btnModalChoose = document.getElementById('btn-modal-choose-pack');

  const catPillsEl = document.getElementById('qr-cat-pills');
  const menuSectionsEl = document.getElementById('qr-menu-sections');
  const searchDrawer = document.getElementById('qr-search-drawer');
  const searchInput = document.getElementById('qr-dish-search');
  const cartContentEl = document.getElementById('qr-cart-content');
  const cartBadgeEl = document.getElementById('qr-cart-badge');
  const toastEl = document.getElementById('qr-toast');

  let activeMenuTab = 'buffet';
  let activeCartTab = 'draft';
  let selectedPack = { type: 'buffet', id: QR_DATA.packages[0]?.id || 0 };
  let activeCatFilter = 'all';
  let searchQuery = '';
  let previewTarget = null;
  let draftCart = []; // UI demo cart only

  function showToast(msg) {
    toastEl.textContent = msg;
    toastEl.classList.add('show');
    clearTimeout(showToast._t);
    showToast._t = setTimeout(() => toastEl.classList.remove('show'), 2300);
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
    screenPack.style.display = target === 'pack' ? 'flex' : 'none';
    screenMenu.style.display = target === 'menu' ? 'block' : 'none';
    screenCart.style.display = target === 'cart' ? 'flex' : 'none';
    mainTopbar.style.display = target === 'cart' ? 'none' : 'flex';
  }

  function renderDishGridCards(dishes, isBuffetMode, interactive) {
    if (!dishes || !dishes.length) {
      return '<p style="color:#6b7280;font-size:13px;">Chưa có món ăn nào trong mục này.</p>';
    }
    return '<div class="qr-dish-grid">' + dishes.map(d => {
      const isOut = d.status === 'out_of_stock';
      const metaText = isBuffetMode && Number(d.price) <= 0 ? 'Buffet' : formatMoney(d.price);
      const metaClass = (!isBuffetMode || Number(d.price) > 0) ? 'qr-dish-meta is-price' : 'qr-dish-meta';
      const img = d.image ? d.image : 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?auto=format&fit=crop&w=700&q=80';
      return `
        <article class="qr-dish-card ${isOut ? 'is-out' : ''}">
          <div class="qr-dish-img-wrap">
            <img src="${escHtml(img)}" alt="${escHtml(d.name)}" loading="lazy" onerror="this.src='https://images.unsplash.com/photo-1546069901-ba9599a7e63c?auto=format&fit=crop&w=700&q=80'">
            ${isOut ? '<span class="qr-dish-tag" style="background:#4b5563;">Tạm hết</span>' : (d.tag ? `<span class="qr-dish-tag">${escHtml(d.tag)}</span>` : '')}
            ${interactive && !isOut ? `<button type="button" class="qr-dish-add-btn" data-add-dish-id="${d.id}" title="Chọn món">+</button>` : ''}
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

    const kw = searchQuery.trim().toLowerCase();
    const filteredGroups = groups
      .filter(g => activeCatFilter === 'all' || String(g.id) === String(activeCatFilter))
      .map(g => ({
        ...g,
        dishes: (g.dishes || []).filter(d => !kw || (d.name || '').toLowerCase().includes(kw))
      }))
      .filter(g => g.dishes.length > 0);

    if (!filteredGroups.length) {
      menuSectionsEl.innerHTML = '<div style="text-align:center;padding:40px 16px;color:#6b7280;font-size:13px;">Chưa có món ăn phù hợp trong mục này. Admin có thể cấu hình thêm món tại Quản lý thực đơn.</div>';
      return;
    }

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

  function updateCartBadge() {
    const totalQty = draftCart.reduce((sum, item) => sum + item.qty, 0);
    cartBadgeEl.textContent = String(totalQty);
    cartBadgeEl.classList.toggle('has-items', totalQty > 0);
  }

  function renderCartView() {
    document.querySelectorAll('#screen-cart-view .qr-tab-btn').forEach(btn => {
      btn.classList.toggle('active', btn.dataset.cartTab === activeCartTab);
    });

    if (activeCartTab === 'ordered') {
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

    const totalAmount = draftCart.reduce((sum, i) => sum + (Number(i.price) * i.qty), 0);
    cartContentEl.innerHTML = `
      <div class="qr-cart-list">
        ${draftCart.map(item => `
          <div class="qr-cart-row">
            <img src="${escHtml(item.image)}" alt="${escHtml(item.name)}" onerror="this.src='https://images.unsplash.com/photo-1546069901-ba9599a7e63c?auto=format&fit=crop&w=200&q=80'">
            <div style="flex:1;min-width:0;">
              <div style="font-size:14px;font-weight:700;">${escHtml(item.name)}</div>
              <div style="font-size:12px;color:#6b7280;margin-top:3px;">${item.menu_type === 'buffet' && Number(item.price) <= 0 ? 'Buffet' : formatMoney(item.price)}</div>
            </div>
            <div class="qr-qty-control">
              <button type="button" data-cart-dec="${item.id}">-</button>
              <b style="min-width:18px;text-align:center;font-size:13px;">${item.qty}</b>
              <button type="button" data-cart-inc="${item.id}">+</button>
            </div>
          </div>
        `).join('')}
        <div style="margin-top:12px;padding:12px;border-radius:12px;background:#f9fafb;display:flex;justify-content:space-between;align-items:center;">
          <span style="font-size:13px;color:#6b7280;">Tạm tính món lẻ</span>
          <b style="font-size:16px;color:#d92d20;">${formatMoney(totalAmount)}</b>
        </div>
        <button type="button" class="qr-btn-solid" id="btn-demo-submit-cart" style="margin-top:6px;">Xác nhận gọi món (Giao diện demo)</button>
      </div>
    `;
  }

  // Events
  document.getElementById('btn-call-staff').addEventListener('click', () => {
    showToast('Đã báo nhân viên hỗ trợ Bàn ' + QR_DATA.tableCode);
  });

  document.getElementById('btn-back-pack-select').addEventListener('click', () => {
    switchMainScreen('pack');
  });

  document.getElementById('btn-open-cart-view').addEventListener('click', () => {
    switchMainScreen('cart');
    renderCartView();
  });

  document.getElementById('btn-close-cart-view').addEventListener('click', () => {
    switchMainScreen(screenMenu.dataset.entered === '1' ? 'menu' : 'pack');
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

  document.getElementById('btn-close-preview-modal').addEventListener('click', () => {
    previewModal.classList.remove('open');
  });

  previewModal.addEventListener('click', (e) => {
    if (e.target === previewModal) previewModal.classList.remove('open');
  });

  btnModalChoose.addEventListener('click', () => {
    if (previewTarget) {
      screenMenu.dataset.entered = '1';
      selectMenuPack(previewTarget.type, previewTarget.id);
    }
  });

  document.querySelectorAll('#screen-menu-order .qr-tab-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      activeMenuTab = btn.dataset.menuTab;
      activeCatFilter = 'all';
      renderMenuOrderScreen();
    });
  });

  catPillsEl.addEventListener('click', (e) => {
    const pill = e.target.closest('.qr-cat-pill');
    if (!pill) return;
    activeCatFilter = pill.dataset.catId;
    renderMenuOrderScreen();
  });

  document.getElementById('btn-toggle-qr-search').addEventListener('click', () => {
    searchDrawer.classList.toggle('open');
    if (searchDrawer.classList.contains('open')) searchInput.focus();
  });

  searchInput.addEventListener('input', () => {
    searchQuery = searchInput.value;
    renderMenuOrderScreen();
  });

  menuSectionsEl.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-add-dish-id]');
    if (!btn) return;
    const dishId = Number(btn.dataset.addDishId);
    let foundDish = null;
    getCurrentTabGroups().forEach(g => {
      (g.dishes || []).forEach(d => {
        if (Number(d.id) === dishId) foundDish = d;
      });
    });
    if (!foundDish) return;

    const existing = draftCart.find(i => Number(i.id) === dishId);
    if (existing) {
      existing.qty += 1;
    } else {
      draftCart.push({
        id: dishId,
        name: foundDish.name,
        price: foundDish.price,
        menu_type: foundDish.menu_type || activeMenuTab,
        image: foundDish.image || '',
        qty: 1
      });
    }
    updateCartBadge();
    showToast('Đã thêm "' + foundDish.name + '" vào giỏ');
  });

  document.querySelectorAll('#screen-cart-view .qr-tab-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      activeCartTab = btn.dataset.cartTab;
      renderCartView();
    });
  });

  cartContentEl.addEventListener('click', (e) => {
    if (e.target.id === 'btn-empty-add-more') {
      screenMenu.dataset.entered = '1';
      switchMainScreen('menu');
      renderMenuOrderScreen();
      return;
    }
    if (e.target.id === 'btn-demo-submit-cart') {
      showToast('Giao diện dựng sẵn: chức năng gửi QR Order sẽ triển khai sau.');
      return;
    }
    const incId = e.target.getAttribute('data-cart-inc');
    const decId = e.target.getAttribute('data-cart-dec');
    if (incId) {
      const it = draftCart.find(i => String(i.id) === incId);
      if (it) it.qty += 1;
      updateCartBadge();
      renderCartView();
    } else if (decId) {
      const idx = draftCart.findIndex(i => String(i.id) === decId);
      if (idx !== -1) {
        draftCart[idx].qty -= 1;
        if (draftCart[idx].qty <= 0) draftCart.splice(idx, 1);
      }
      updateCartBadge();
      renderCartView();
    }
  });
})();
</script>
</body>
</html>

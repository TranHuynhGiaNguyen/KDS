<?php if ($dbError !== ''): ?>
  <div class="flash-alert flash-error" style="margin-bottom: 12px;"><span><?= escapeHtml($dbError) ?></span></div>
<?php endif; ?>

<div class="pos-shell pos-tablet-shell"
     data-pos-shell
     data-table="<?= htmlspecialchars($currentTable) ?>"
     data-max-qty="<?= ORDER_ADD_MAX_QTY ?>"
     data-can-cancel="<?= $canCancel ? '1' : '0' ?>"
     data-can-add="<?= $canAdd ? '1' : '0' ?>"
     data-is-manager="<?= $isManagerOrAdmin ? '1' : '0' ?>"
     data-locked-menu-type="<?= htmlspecialchars((string) ($tableMenuLock['menu_type'] ?? '')) ?>"
     data-locked-package-id="<?= (int) ($tableMenuLock['buffet_package_id'] ?? 0) ?>">

  <!-- ======================================================================
       MÀN HÌNH 1: GIAO DIỆN CHỌN MÓN & GỬI ORDER (THEO ẢNH MẪU BÀN XXX)
       ====================================================================== -->
  <div class="pos-screen-order" id="pos-screen-order">

    <!-- CỘT TRÁI: HEADER BÀN + THANH CÔNG CỤ + GIỎ NHÁP + XÓA TẤT CẢ / TỔNG TIỀN -->
    <section class="pos-left-pane">
      <div class="pos-left-topbar">
        <a href="index.php?page=staff" class="pos-back-arrow" title="Quay lại Sơ đồ bàn" aria-label="Quay lại Sơ đồ bàn">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
        </a>

        <div class="pos-table-title-wrap">
          <h1 class="pos-table-title">Bàn <?= htmlspecialchars($currentTable) ?></h1>
          <span class="pos-table-subpill" data-pos-guest-pill>
            <?= $isMaintenance ? 'Bảo trì' : ($cur['guests'] > 0 ? ($cur['guests'] . ' khách') : ($hasOrderItems ? 'Đang phục vụ' : 'Bàn trống')) ?>
          </span>
        </div>

        <div class="pos-more-wrap">
          <button type="button" class="pos-more-dots-btn" data-pos-more-toggle title="Thao tác bàn">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><circle cx="5" cy="12" r="2"></circle><circle cx="12" cy="12" r="2"></circle><circle cx="19" cy="12" r="2"></circle></svg>
          </button>

          <div class="pos-more-menu" data-pos-more-menu hidden>
            <?php if (!$isMaintenance): ?>
              <button type="button"
                      data-open-assign-table="<?= htmlspecialchars($currentTable) ?>"
                      data-assign-update="<?= $cur['has_guests'] ? '1' : '0' ?>"
                      data-guest-count="<?= $cur['guests'] > 0 ? $cur['guests'] : 2 ?>"
                      data-staff-id="<?= $cur['staff_id'] ?>"
                      data-customer-name="<?= htmlspecialchars($cur['customer_name']) ?>"
                      data-customer-phone="<?= htmlspecialchars($cur['customer_phone']) ?>"
                      data-assign-note="<?= htmlspecialchars($cur['note']) ?>">
                <?= $cur['has_guests'] ? 'Cập nhật thông tin bàn' : 'Gán / Mở bàn đón khách' ?>
              </button>
            <?php endif; ?>

            <?php if (!$isMaintenance && $cur['has_guests']): ?>
              <button type="button" data-open-transfer-table="<?= htmlspecialchars($currentTable) ?>">Chuyển bàn</button>
              <button type="button" data-open-merge-table="<?= htmlspecialchars($currentTable) ?>">Gộp bàn</button>
              <?php if (!$hasOrderItems): ?>
                <button type="button" class="is-danger" data-release-table="<?= htmlspecialchars($currentTable) ?>">Trả bàn trống</button>
              <?php endif; ?>
            <?php endif; ?>

            <a href="index.php?page=kitchen">Màn hình Bếp KDS</a>
            <a href="<?= htmlspecialchars($tableQrUrl) ?>" target="_blank" rel="noopener">Mở trang QR gọi món</a>
          </div>
        </div>
      </div>

      <?php if ($pendingCall): ?>
        <div class="pos-staff-call-banner" data-pos-call-banner>
          <span>🔔 Khách vừa bấm <b>Gọi nhân viên</b> (<?= htmlspecialchars(date('H:i', strtotime($pendingCall['created_at']))) ?>)</span>
          <button type="button" data-dismiss-call="<?= htmlspecialchars($currentTable) ?>">Đã tiếp nhận</button>
        </div>
      <?php endif; ?>

      <div class="pos-menu-lock-bar<?= $tableMenuLock ? ' is-locked' : '' ?>" data-pos-menu-lock-bar>
        <div>
          <?php if ($tableMenuLock): ?>
            <b>🔒 Đã khóa: <?= escapeHtml(formatTableMenuLockLabel($tableMenuLock)) ?></b>
            <small>Chỉ Quản lý ca / Admin được quyền đổi vé</small>
          <?php else: ?>
            <b>🔓 Bàn chưa khóa menu</b>
            <small>Thêm Vé Buffet bên phải hoặc khách quét QR để khóa</small>
          <?php endif; ?>
        </div>
        <span class="pos-lock-role-tag" title="Chỉ Quản lý ca hoặc Admin mới có quyền đổi vé">Khóa vé</span>
      </div>

      <!-- THANH HÀNH ĐỘNG: KIỂM ĐỒ | RA MÓN | IN QRCODE | GỬI ORDER -->
      <div class="pos-action-strip">
        <button type="button" class="pos-icon-action" data-pos-open-kiemdo title="Kiểm đồ & món đã gọi của bàn">
          <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="8" y1="13" x2="16" y2="13"></line><line x1="8" y1="17" x2="16" y2="17"></line></svg>
          <span>Kiểm đồ</span>
          <em class="pos-action-badge" data-pos-kiemdo-badge <?= $activeItemQtySum > 0 ? '' : 'hidden' ?>><?= $activeItemQtySum ?></em>
        </button>

        <button type="button" class="pos-icon-action" data-pos-open-ramon title="Xem trạng thái ra món từ Bếp">
          <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"></path><rect x="9" y="3" width="6" height="4" rx="1"></rect><path d="m9 14 2 2 4-4"></path></svg>
          <span>Ra món</span>
        </button>

        <button type="button" class="pos-icon-action" data-pos-open-qrcode title="Xem & in mã QR cố định của bàn">
          <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect><path d="M14 14h3v3h-3zM18 18h3v3h-3zM14 20h2"></path></svg>
          <span>In QRCode</span>
        </button>

        <button type="button" class="pos-submit-order-btn" data-pos-send-order disabled>
          GỬI ORDER
        </button>
      </div>

      <!-- KHU VỰC GIỎ MÓN ĐANG CHỌN (TRƯỚC KHI GỬI ORDER) -->
      <div class="pos-draft-stage" data-pos-draft-stage>
        <div class="pos-draft-list" data-pos-draft-list></div>

        <div class="pos-draft-empty" data-pos-draft-empty>
          <?php if ($isMaintenance): ?>
            <p style="color:#b91c1c;font-weight:700;">Bàn <?= htmlspecialchars($currentTable) ?> đang bảo trì — không thể gọi món.</p>
          <?php else: ?>
            <p>Chạm vào món ăn bên phải để thêm vào đơn.</p>
          <?php endif; ?>
        </div>
      </div>

      <!-- THÔNG BÁO MÓN ĐÃ GỌI TRONG BÀN (NẾU CÓ) -->
      <div class="pos-committed-banner" data-pos-committed-banner <?= $hasOrderItems ? '' : 'hidden' ?>>
        <button type="button" class="pos-committed-banner-btn" data-pos-open-kiemdo>
          <span>Đã gọi: <b data-pos-committed-count><?= $activeItemQtySum ?></b> món · <b data-pos-committed-total><?= formatMoney((int) $bill['total']) ?></b></span>
          <span>Kiểm đồ ›</span>
        </button>
      </div>

      <!-- CHÂN CỘT TRÁI: XÓA TẤT CẢ | TỔNG TIỀN -->
      <div class="pos-left-footer">
        <button type="button" class="pos-clear-all-btn" data-pos-clear-draft disabled>
          XÓA TẤT CẢ
        </button>

        <div class="pos-draft-total-wrap">
          <span>Tổng tiền</span>
          <b data-pos-draft-total>0 đ</b>
        </div>
      </div>
    </section>

    <!-- CỘT PHẢI: TAB MENU CHÍNH + SUB-CATEGORIES + LƯỚI MÓN 3 CỘT -->
    <section class="pos-right-pane">
      <!-- TOP BAR MENU: Buffet | Alacarte | Đồ uống | Tìm kiếm -->
      <div class="pos-menu-topbar">
        <div class="pos-primary-menu-tabs" role="tablist">
          <button type="button" class="pos-pmenu-tab <?= $initialTab === 'buffet' ? 'active' : '' ?>" data-pos-primary-tab="buffet">Buffet</button>
          <button type="button" class="pos-pmenu-tab <?= $initialTab === 'alacarte' ? 'active' : '' ?>" data-pos-primary-tab="alacarte">Alacarte</button>
          <button type="button" class="pos-pmenu-tab <?= $initialTab === 'drink' ? 'active' : '' ?>" data-pos-primary-tab="drink">Đồ uống</button>
        </div>

        <div class="pos-menu-search-wrap">
          <button type="button" class="pos-search-trigger" data-pos-toggle-search>
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
            <span>Tìm kiếm</span>
          </button>
        </div>
      </div>

      <!-- THANH TÌM KIẾM MỞ RỘNG KHI BẤM TÌM KIẾM -->
      <div class="pos-search-bar" data-pos-search-bar>
        <input type="search" data-pos-search-input placeholder="Tìm nhanh tên món ăn (vd: ba chỉ, coca, salad...)" autocomplete="off">
      </div>

      <!-- SUB-CATEGORY TABS GRID -->
      <div class="pos-subcat-strip" data-pos-subcat-strip></div>

      <!-- LƯỚI DANH SÁCH MÓN THEO TỪNG DANH MỤC -->
      <div class="pos-menu-scroll" data-pos-menu-scroll>
        <?php foreach (['buffet', 'alacarte', 'drink'] as $tabKey): ?>
          <?php $catGroups = array_values($primaryMenu[$tabKey]); ?>
          <div class="pos-tab-panel" data-pos-tab-panel="<?= $tabKey ?>" <?= $tabKey === $initialTab ? '' : 'hidden' ?>>
            <?php if ($tabKey === 'buffet' && $activeBuffetPackages !== []): ?>
              <div class="pos-dish-section" data-pos-section-key="buffet-tickets" data-pos-section-name="VÉ BUFFET">
                <h3 class="pos-dish-section-title">🎫 VÉ BUFFET (THEO NGƯỜI)</h3>
                <div class="pos-dish-card-grid">
                  <?php foreach ($activeBuffetPackages as $pkg): ?>
                    <?php
                      $pkgId         = (int) $pkg['id'];
                      $pkgPrice      = (int) round((float) ($pkg['price_per_person'] ?? 0));
                      $ticketLabel   = formatBuffetTicketName((string) $pkg['name']);
                      $lockedByOther = $tableMenuLock !== null
                          && (($tableMenuLock['menu_type'] ?? '') !== 'buffet'
                              || (int) ($tableMenuLock['buffet_package_id'] ?? 0) !== $pkgId);
                      $ticketDisabled = !$canAdd || $lockedByOther;
                    ?>
                    <button type="button"
                            class="pos-dish-card pos-ticket-card<?= $ticketDisabled ? ' is-disabled' : '' ?>"
                            data-pos-menu-dish
                            data-is-ticket="1"
                            data-package-id="<?= $pkgId ?>"
                            data-dish-id="ticket-<?= $pkgId ?>"
                            data-dish-name="<?= escapeHtml($ticketLabel) ?>"
                            data-dish-price="<?= $pkgPrice ?>"
                            data-dish-menu-type="ticket"
                            data-cat-key="buffet-tickets"
                            data-search-text="<?= escapeHtml(mb_strtolower($ticketLabel . ' vé buffet', 'UTF-8')) ?>"
                            <?= $ticketDisabled ? 'disabled title="Bàn đang khóa menu khác — Chỉ Quản lý ca / Admin được đổi vé"' : '' ?>>
                      <span class="pos-dish-card-name">🎫 <?= escapeHtml($ticketLabel) ?></span>
                      <span class="pos-dish-card-meta">
                        <?= $lockedByOther ? 'Đã khóa gói khác' : ($pkgPrice > 0 ? (formatMoney($pkgPrice) . ' / vé') : 'Miễn phí vé') ?>
                      </span>
                      <span class="pos-dish-card-badge" data-dish-badge-id="ticket-<?= $pkgId ?>" hidden>0</span>
                    </button>
                  <?php endforeach; ?>
                </div>
              </div>
            <?php endif; ?>

            <?php if ($catGroups === [] && ($tabKey !== 'buffet' || $activeBuffetPackages === [])): ?>
              <div class="pos-menu-empty-note">
                Chưa có món ăn nào trong mục <b><?= $tabKey === 'buffet' ? 'Buffet' : ($tabKey === 'drink' ? 'Đồ uống' : 'Alacarte') ?></b>.
                Admin có thể thêm món trong mục Quản lý thực đơn.
              </div>
            <?php else: ?>
              <?php foreach ($catGroups as $cat): ?>
                <div class="pos-dish-section" data-pos-section-key="<?= htmlspecialchars($cat['key']) ?>" data-pos-section-name="<?= escapeHtml($cat['name']) ?>">
                  <h3 class="pos-dish-section-title"><?= escapeHtml($cat['name']) ?></h3>
                  <div class="pos-dish-card-grid">
                    <?php foreach ($cat['dishes'] as $dish): ?>
                      <?php
                        $isOut = ($dish['status'] ?? '') !== 'active';
                        $dishPkgId = !empty($dish['buffet_package_id']) ? (int) $dish['buffet_package_id'] : 0;
                        $dishBlockedByLock = false;
                        if ($tabKey === 'buffet' && $tableMenuLock !== null) {
                          if (($tableMenuLock['menu_type'] ?? '') === 'alacarte') {
                            $dishBlockedByLock = true;
                          } elseif (($tableMenuLock['menu_type'] ?? '') === 'buffet'
                              && $dishPkgId > 0
                              && (int) ($tableMenuLock['buffet_package_id'] ?? 0) > 0
                              && $dishPkgId !== (int) $tableMenuLock['buffet_package_id']) {
                            $dishBlockedByLock = true;
                          }
                        }
                        $cardDisabled = !$canAdd || $isOut || $dishBlockedByLock;
                      ?>
                      <button type="button"
                              class="pos-dish-card<?= $cardDisabled ? ' is-disabled' : '' ?>"
                              data-pos-menu-dish
                              data-dish-id="<?= (int) $dish['id'] ?>"
                              data-dish-name="<?= escapeHtml($dish['name']) ?>"
                              data-dish-price="<?= (int) $dish['price'] ?>"
                              data-dish-menu-type="<?= htmlspecialchars((string) ($dish['dish_menu_type'] ?? 'alacarte')) ?>"
                              data-dish-package-id="<?= $dishPkgId ?>"
                              data-cat-key="<?= htmlspecialchars($cat['key']) ?>"
                              data-search-text="<?= escapeHtml(mb_strtolower($dish['name'] . ' ' . $cat['name'], 'UTF-8')) ?>"
                              <?= $cardDisabled ? 'disabled' : '' ?>>
                        <span class="pos-dish-card-name"><?= escapeHtml($dish['name']) ?></span>
                        <span class="pos-dish-card-meta">
                          <?= $isOut ? 'Tạm hết' : ($dishBlockedByLock ? 'Khác gói đang khóa' : ((int) $dish['price'] > 0 ? formatMoney((int) $dish['price']) : 'Buffet')) ?>
                        </span>
                        <span class="pos-dish-card-badge" data-dish-badge-id="<?= (int) $dish['id'] ?>" hidden>0</span>
                      </button>
                    <?php endforeach; ?>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
        <div class="pos-menu-empty-note" data-pos-search-empty hidden>Không tìm thấy món ăn phù hợp.</div>
      </div>
    </section>

  </div>

  <!-- ======================================================================
       MÀN HÌNH 2: KIỂM ĐỒ - BÀN XXX (THEO ẢNH MẪU KIỂM ĐỒ - BÀN 106)
       ====================================================================== -->
  <div class="pos-screen-kiemdo" id="pos-screen-kiemdo" hidden>
    <!-- HEADER BAR -->
    <header class="kd-topbar">
      <button type="button" class="kd-back-btn" data-pos-close-kiemdo aria-label="Quay lại gọi món">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"></polyline></svg>
      </button>

      <h2 class="kd-title">Kiểm đồ - bàn <?= htmlspecialchars($currentTable) ?></h2>

      <label class="kd-show-cancelled-toggle">
        <input type="checkbox" data-kd-toggle-cancelled>
        <span>Hiện món đã hủy</span>
      </label>
    </header>

    <!-- PROGRESS / TRẠM BẾP STRIP -->
    <div class="kd-progress-strip" data-pos-kiemdo-pills>
      <span class="kd-strip-label">Tiến độ món</span>
      <div class="kd-pills-wrap">
        <span class="kd-green-pill">Tổng món <?= $totalDoneCount ?>/<?= $totalOrderedCount ?></span>
        <?php foreach ($stationStats as $st): ?>
          <?php if ($st['total'] > 0): ?>
            <span class="kd-green-pill"><?= escapeHtml($st['label']) ?> <?= $st['done'] ?>/<?= $st['total'] ?></span>
          <?php endif; ?>
        <?php endforeach; ?>
      </div>
      <button type="button" class="kd-detail-link" data-kd-toggle-bill>Chi tiết ▸</button>
    </div>

    <!-- CHI TIẾT TẠM TÍNH (MỞ KHI BẤM "Chi tiết ▸") -->
    <div class="kd-bill-drawer" data-pos-kiemdo-bill hidden>
      <div class="kd-bill-drawer-grid">
        <div><span>Tạm tính:</span><b><?= formatMoney((int) $bill['subtotal']) ?></b></div>
        <div><span>Phí phục vụ (5%):</span><b><?= formatMoney((int) $bill['service_fee']) ?></b></div>
        <div><span>Thuế VAT (8%):</span><b><?= formatMoney((int) $bill['vat']) ?></b></div>
        <div class="is-total"><span>Tổng cộng:</span><b><?= formatMoney((int) $bill['total']) ?></b></div>
      </div>
    </div>

    <!-- DANH SÁCH MÓN ĐÃ GỌI -->
    <div class="kd-items-scroll" data-pos-kiemdo-rows>
      <?php if ($orderItems === []): ?>
        <div class="kd-empty-state">
          <p>Chưa có món nào được gửi xuống bếp cho Bàn <?= htmlspecialchars($currentTable) ?>.</p>
          <button type="button" class="btn btn-outline" data-pos-close-kiemdo>Quay lại chọn món</button>
        </div>
      <?php else: ?>
        <?php foreach ($orderItems as $item): ?>
          <?php
            $isCancelled   = ($item['status'] ?? '') === 'cancelled';
            $statusInfo    = ORDER_ITEM_STATUSES[$item['status']] ?? ORDER_ITEM_STATUSES['pending'];
            $lineAmount    = (int) $item['price'] * (int) $item['qty'];
            $itemCanCancel = availableItemActions($item, $role) !== [];
            $isDoneItem    = in_array($item['status'] ?? '', ['done', 'served'], true);
          ?>
          <div class="kd-item-row<?= $isCancelled ? ' is-cancelled' : '' ?>"
               data-item-id="<?= (int) $item['id'] ?>"
               data-item-name="<?= escapeHtml($item['name']) ?>"
               data-item-qty="<?= (int) $item['qty'] ?>"
               data-item-status="<?= escapeHtml($item['status']) ?>"
               data-can-cancel="<?= $itemCanCancel ? '1' : '0' ?>"
               data-is-cancelled="<?= $isCancelled ? '1' : '0' ?>"
               <?= $isCancelled ? 'hidden' : '' ?>>
            <div class="kd-item-left">
              <span class="kd-item-qty"><?= (int) $item['qty'] ?> x</span>
              <div class="kd-item-info">
                <b class="kd-item-name"><?= escapeHtml($item['name']) ?></b>
                <div class="kd-item-meta">
                  <span class="kd-item-status-tag status-<?= $statusInfo['class'] ?>"><?= $statusInfo['label'] ?></span>
                  <?php if (!empty($item['note'])): ?>
                    <span>· <?= escapeHtml($item['note']) ?></span>
                  <?php endif; ?>
                  <?php if ($lineAmount > 0): ?>
                    <span>· <?= formatMoney($lineAmount) ?></span>
                  <?php else: ?>
                    <span>· Buffet</span>
                  <?php endif; ?>
                </div>
              </div>
            </div>

            <div class="kd-item-right">
              <span class="kd-flag-badge" title="<?= escapeHtml($statusInfo['label']) ?>">
                <?= $isCancelled ? 'H' : ($isDoneItem ? '✓' : 'F') ?>
              </span>
              <?php if (!$isCancelled): ?>
                <button type="button"
                        class="kd-cancel-circle<?= !$canCancel ? ' is-locked' : '' ?>"
                        data-kd-cancel-item
                        title="<?= $canCancel ? 'Hủy / giảm số lượng món' : 'Chỉ Quản trị viên / Quản lý ca mới được hủy món' ?>"
                        aria-label="Hủy món <?= escapeHtml($item['name']) ?>">✕</button>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <!-- THANH 4 NÚT ĐÁY MÀN HÌNH KIỂM ĐỒ -->
    <div class="kd-bottom-actions" data-pos-kiemdo-footer>
      <button type="button"
              class="kd-action-btn is-purple"
              data-kd-staff-discount
              <?= !$hasOrderItems ? 'disabled' : '' ?>>
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="16" rx="2"></rect><circle cx="9" cy="10" r="2"></circle><path d="M15 8h2M15 12h2M7 16h10"></path></svg>
        <span>Giảm giá nhân viên</span>
      </button>

      <button type="button"
              class="kd-action-btn is-green"
              data-kd-print-provisional
              <?= !$hasOrderItems ? 'disabled' : '' ?>>
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
        <span>Kiểm đồ</span>
      </button>

      <button type="button"
              class="kd-action-btn is-blue"
              data-kd-open-voucher
              <?= !$hasOrderItems ? 'disabled' : '' ?>>
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path><line x1="7" y1="7" x2="7.01" y2="7"></line></svg>
        <span>Voucher</span>
      </button>

      <a class="kd-action-btn is-red<?= !$hasOrderItems ? ' is-disabled' : '' ?>"
         href="<?= $hasOrderItems ? ('index.php?page=payment&table=' . urlencode($currentTable)) : '#' ?>"
         data-kd-go-payment>
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="9" y1="15" x2="15" y2="15"></line></svg>
        <span>Phiếu thanh toán</span>
      </a>
    </div>
  </div>
</div>

<!-- ======================================================================
     HỘP THOẠI HỦY / GIẢM SỐ LƯỢNG MÓN (ADMIN / QUẢN LÝ CA)
     ====================================================================== -->
<dialog class="add-item-dialog cancel-qty-dialog" data-cancel-qty-dialog>
  <form class="add-item-form" data-cancel-qty-form novalidate>
    <div class="add-item-head">
      <div>
        <h3 style="margin:0;">Xác nhận hủy / giảm món</h3>
        <small style="color:#64748b;" data-cancel-item-title>...</small>
      </div>
      <button class="add-item-close" type="button" data-cancel-close aria-label="Đóng">✕</button>
    </div>

    <div class="add-item-body">
      <div>
        <span class="add-item-label">Số lượng muốn hủy (tối đa <b data-cancel-max-label>1</b>)</span>
        <div class="qty-stepper" style="margin-top:8px;">
          <button type="button" data-cancel-step="-1" aria-label="Giảm">−</button>
          <input type="number" name="cancel_qty" value="1" min="1" max="1" data-cancel-qty-input aria-label="Số lượng cần hủy">
          <button type="button" data-cancel-step="1" aria-label="Tăng">+</button>
        </div>
        <div class="pos-cancel-quick-row" data-cancel-quick-row></div>
      </div>
    </div>

    <div class="add-item-foot">
      <span class="add-item-summary" data-cancel-summary>Sẽ hủy 1 phần</span>
      <div class="add-item-actions">
        <button class="btn btn-outline" type="button" data-cancel-close>Đóng</button>
        <button class="btn btn-danger" type="submit" data-cancel-submit>Xác nhận hủy</button>
      </div>
    </div>
  </form>
</dialog>

<!-- ======================================================================
     HỘP THOẠI GHI CHÚ MÓN NHÁP (TRƯỚC KHI GỬI ORDER)
     ====================================================================== -->
<dialog class="add-item-dialog" data-draft-note-dialog>
  <form class="add-item-form" data-draft-note-form novalidate>
    <div class="add-item-head">
      <div>
        <h3 style="margin:0;">Ghi chú món</h3>
        <small style="color:#64748b;" data-draft-note-title>...</small>
      </div>
      <button class="add-item-close" type="button" data-draft-note-close aria-label="Đóng">✕</button>
    </div>
    <div class="add-item-body">
      <div>
        <label class="add-item-label" for="pos-draft-note-input">Nội dung ghi chú gửi bếp</label>
        <input id="pos-draft-note-input" type="text" maxlength="255" placeholder="Nhập ghi chú..." autocomplete="off" data-draft-note-input>
        <div class="note-chips" style="margin-top:10px;">
          <?php foreach (['Ít cay', 'Không hành', 'Mang ra trước', 'Không đá', 'Ít đường'] as $chip): ?>
            <button class="note-chip" type="button" data-draft-note-chip="<?= escapeHtml($chip) ?>"><?= escapeHtml($chip) ?></button>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
    <div class="add-item-foot">
      <span></span>
      <div class="add-item-actions">
        <button class="btn btn-outline" type="button" data-draft-note-close>Hủy</button>
        <button class="btn btn-primary" type="submit">Lưu ghi chú</button>
      </div>
    </div>
  </form>
</dialog>

<!-- ======================================================================
     HỘP THOẠI IN QRCODE CỐ ĐỊNH CỦA BÀN
     ====================================================================== -->
<dialog class="add-item-dialog qr-print-dialog" data-qr-print-dialog>
  <div class="add-item-form">
    <div class="add-item-head no-print">
      <h3 style="margin:0;">QR Code gán cứng — Bàn <?= htmlspecialchars($currentTable) ?></h3>
      <button class="add-item-close" type="button" data-qr-dialog-close aria-label="Đóng">✕</button>
    </div>
    <div class="add-item-body" style="text-align:center;">
      <div class="pos-qr-print-card" id="pos-qr-print-card">
        <div style="font-size:13px;font-weight:800;letter-spacing:.6px;color:#ea580c;">SMARTDINE RESTAURANT</div>
        <h2 style="margin:6px 0 4px;font-size:24px;color:#0f172a;">BÀN <?= htmlspecialchars($currentTable) ?></h2>
        <p style="margin:0 0 14px;font-size:13px;color:#64748b;">Quét mã QR để xem thực đơn &amp; gọi món tại bàn</p>
        <div class="pos-qr-svg-box" data-pos-qr-box data-qr-url="<?= htmlspecialchars($tableQrUrl) ?>"></div>
        <div style="margin-top:10px;font-size:11px;color:#64748b;font-family:monospace;word-break:break-all;">
          <?= htmlspecialchars($tableQrUrl) ?>
        </div>
      </div>
    </div>
    <div class="add-item-foot no-print">
      <button class="btn btn-outline" type="button" data-copy-qr-url="<?= htmlspecialchars($tableQrUrl) ?>">Sao chép link</button>
      <div class="add-item-actions">
        <a class="btn btn-outline" href="<?= htmlspecialchars($tableQrUrl) ?>" target="_blank" rel="noopener">Mở qr-order.php</a>
        <button class="btn btn-primary" type="button" data-print-qr-btn>In QR Code</button>
      </div>
    </div>
  </div>
</dialog>

<!-- ======================================================================
     HỘP THOẠI RA MÓN / TRẠNG THÁI CHẾ BIẾN BẾP
     ====================================================================== -->
<dialog class="add-item-dialog" data-ramon-dialog>
  <div class="add-item-form">
    <div class="add-item-head">
      <h3 style="margin:0;">Tiến độ ra món — Bàn <?= htmlspecialchars($currentTable) ?></h3>
      <button class="add-item-close" type="button" data-ramon-close aria-label="Đóng">✕</button>
    </div>
    <div class="add-item-body" data-pos-ramon-body>
      <?php if ($visibleItems === []): ?>
        <p style="text-align:center;color:#64748b;margin:24px 0;">Chưa có món nào gửi xuống bếp.</p>
      <?php else: ?>
        <?php foreach ($visibleItems as $it): ?>
          <?php $stInfo = ORDER_ITEM_STATUSES[$it['status']] ?? ORDER_ITEM_STATUSES['pending']; ?>
          <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid #e2e8f0;gap:10px;">
            <div>
              <b><?= (int) $it['qty'] ?> × <?= escapeHtml($it['name']) ?></b>
              <?php if (!empty($it['note'])): ?>
                <div style="font-size:12px;color:#64748b;">Ghi chú: <?= escapeHtml($it['note']) ?></div>
              <?php endif; ?>
            </div>
            <span class="kd-item-status-tag status-<?= $stInfo['class'] ?>"><?= $stInfo['label'] ?></span>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
    <div class="add-item-foot">
      <a class="btn btn-outline" href="index.php?page=kitchen">Mở màn hình Bếp</a>
      <button class="btn btn-primary" type="button" data-ramon-close>Đóng</button>
    </div>
  </div>
</dialog>

<!-- ======================================================================
     HỘP THOẠI PHIẾU KIỂM ĐỒ / TẠM TÍNH (IN)
     ====================================================================== -->
<dialog class="receipt-dialog" data-provisional-dialog>
  <div class="receipt-dialog-body" data-provisional-body></div>
  <div class="bill-ticket-actions">
    <button class="btn btn-outline" type="button" data-provisional-close>Đóng</button>
    <button class="btn" type="button" data-provisional-print>In phiếu kiểm đồ</button>
  </div>
</dialog>

<!-- ======================================================================
     HỘP THOẠI GIẢM GIÁ NHÂN VIÊN
     ====================================================================== -->
<dialog class="add-item-dialog" data-staff-discount-dialog>
  <div class="add-item-form">
    <div class="add-item-head">
      <h3 style="margin:0;">Giảm giá nhân viên — Bàn <?= htmlspecialchars($currentTable) ?></h3>
      <button class="add-item-close" type="button" data-staff-discount-close aria-label="Đóng">✕</button>
    </div>
    <div class="add-item-body">
      <p style="margin:0 0 10px;font-size:13px;color:#64748b;">Chọn mức giảm giá (%) để áp dụng sang phiếu thanh toán của bàn:</p>
      <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:8px;margin-bottom:14px;">
        <?php foreach ([5, 10, 15, 20] as $pct): ?>
          <button type="button" class="btn btn-outline" data-preset-discount="<?= $pct ?>"><?= $pct ?>%</button>
        <?php endforeach; ?>
      </div>
      <label class="add-item-label" for="kd-staff-discount-pct">Hoặc nhập % giảm tùy chỉnh</label>
      <input id="kd-staff-discount-pct" type="number" min="0" max="100" value="10" data-staff-discount-input style="width:100%;height:42px;border:1px solid #cbd5e1;border-radius:10px;padding:0 12px;">
    </div>
    <div class="add-item-foot">
      <button class="btn btn-outline" type="button" data-staff-discount-close>Hủy</button>
      <button class="btn btn-primary" type="button" data-apply-staff-discount>Áp dụng &amp; Sang thanh toán</button>
    </div>
  </div>
</dialog>

<!-- ======================================================================
     HỘP THOẠI CHỌN / ÁP DỤNG VOUCHER
     ====================================================================== -->
<dialog class="add-item-dialog" data-kd-voucher-dialog>
  <div class="add-item-form">
    <div class="add-item-head">
      <h3 style="margin:0;">Áp dụng Voucher — Bàn <?= htmlspecialchars($currentTable) ?></h3>
      <button class="add-item-close" type="button" data-kd-voucher-close aria-label="Đóng">✕</button>
    </div>
    <div class="add-item-body">
      <label class="add-item-label" for="kd-voucher-code-input">Nhập mã voucher</label>
      <div style="display:flex;gap:8px;margin-bottom:12px;">
        <input id="kd-voucher-code-input" type="text" placeholder="VD: WELCOME10" style="text-transform:uppercase;" data-kd-voucher-input>
        <button type="button" class="btn btn-outline" data-kd-check-voucher>Kiểm tra</button>
      </div>
      <div data-kd-voucher-feedback style="font-size:13px;font-weight:600;margin-bottom:10px;"></div>

      <?php if ($activeVouchers !== []): ?>
        <div class="add-item-label">Voucher đang hoạt động</div>
        <div style="display:flex;flex-direction:column;gap:8px;max-height:200px;overflow-y:auto;">
          <?php foreach ($activeVouchers as $v): ?>
            <button type="button"
                    class="kd-voucher-pick-item"
                    data-pick-voucher="<?= escapeHtml($v['code']) ?>">
              <div>
                <b><?= escapeHtml($v['code']) ?></b>
                <small style="display:block;color:#64748b;"><?= escapeHtml($v['name']) ?></small>
              </div>
              <span style="color:#ea580c;font-weight:800;">
                <?= $v['discount_type'] === 'percent' ? ($v['discount_value'] . '%') : formatMoney((int) $v['discount_value']) ?>
              </span>
            </button>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
    <div class="add-item-foot">
      <button class="btn btn-outline" type="button" data-kd-voucher-close>Đóng</button>
      <button class="btn btn-primary" type="button" data-kd-go-voucher-pay>Sang thanh toán với Voucher</button>
    </div>
  </div>
</dialog>

<?php include __DIR__ . '/table-modals.php'; ?>

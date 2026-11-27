<?php if ($dbError !== ''): ?>
  <div class="flash-alert flash-error" style="margin-bottom: 12px;"><span><?= escapeHtml($dbError) ?></span></div>
<?php endif; ?>

<div class="pos-shell"
     data-pos-shell
     data-table="<?= htmlspecialchars($currentTable) ?>"
     data-max-qty="<?= ORDER_ADD_MAX_QTY ?>"
     data-can-cancel="<?= $canCancel ? '1' : '0' ?>">
  <div class="pos-grid-3">

    <!-- ==========================================================
         CỘT 1 (TRÁI): CHI TIẾT BILL & MÓN ĐÃ GỌI
         ========================================================== -->
    <section class="pos-left-bill">
      <div class="pos-bill-head">
        <div class="pos-brand-tag">
          SMARTDINE
          <span>KDS POS</span>
        </div>
        <div class="pos-bill-meta-col">
          <b><?= $cur['customer_name'] !== '' ? htmlspecialchars($cur['customer_name']) : 'Khách lẻ' ?></b>
          <small style="color:#475569;"><?= htmlspecialchars($cur['time'] !== '—' ? ('Vào: ' . $cur['time']) : $cur['zone']) ?></small>
        </div>
        <div class="pos-bill-meta-right">
          <div class="pos-table-chip-badge">Bàn <?= htmlspecialchars($currentTable) ?></div>
          <div><small style="color:#475569;font-weight:600;"><?= $cur['guests'] > 0 ? ($cur['guests'] . ' khách') : ($cur['capacity'] . ' chỗ') ?></small></div>
        </div>
      </div>

      <?php if ($pendingCall): ?>
        <div class="pos-staff-call-banner" data-pos-call-banner>
          <span>🔔 Khách vừa bấm <b>Gọi nhân viên</b> (<?= htmlspecialchars(date('H:i', strtotime($pendingCall['created_at']))) ?>)</span>
          <button type="button" data-dismiss-call="<?= htmlspecialchars($currentTable) ?>">Đã tiếp nhận</button>
        </div>
      <?php endif; ?>

      <div class="pos-bill-items" data-pos-bill-scroll>
        <?php if ($hasOrderItems): ?>
          <div class="order-food-list">
            <?php foreach ($visibleItems as $item): ?>
              <?php
                $statusInfo = ORDER_ITEM_STATUSES[$item['status']] ?? ORDER_ITEM_STATUSES['pending'];
                $lineAmount = (int) $item['price'] * (int) $item['qty'];
                $itemCanCancel = availableItemActions($item, $role) !== [];
              ?>
              <div class="order-food order-food-<?= escapeHtml($item['status']) ?>"
                   data-item-id="<?= (int) $item['id'] ?>"
                   data-item-name="<?= escapeHtml($item['name']) ?>"
                   data-item-qty="<?= (int) $item['qty'] ?>"
                   data-item-status="<?= escapeHtml($item['status']) ?>"
                   data-can-cancel="<?= $itemCanCancel ? '1' : '0' ?>">
                <div class="pos-bill-row-main">
                  <span class="pos-bill-qty"><?= (int) $item['qty'] ?> ×</span>
                  <div class="pos-bill-name-wrap">
                    <b><?= escapeHtml($item['name']) ?></b>
                    <div class="pos-bill-sub">
                      <span class="pos-status-mini status-<?= $statusInfo['class'] ?>"><?= $statusInfo['label'] ?></span>
                      <?php if (!empty($item['note'])): ?>
                        <span>· <?= escapeHtml($item['note']) ?></span>
                      <?php endif; ?>
                    </div>
                  </div>
                </div>

                <span class="pos-bill-amount"><?= $lineAmount > 0 ? number_format($lineAmount, 0, ',', '.') : '0' ?></span>
              </div>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div class="pos-bill-empty">
            <div class="pos-bill-empty-icon"><?= $isMaintenance ? '🛠️' : ($cur['has_guests'] ? '🛎️' : '🍽️') ?></div>
            <?php if ($isMaintenance): ?>
              <b>Bàn <?= htmlspecialchars($currentTable) ?> đang bảo trì</b>
              <p style="margin:6px 0 0;">Không thể gọi món tại bàn này.</p>
            <?php elseif ($cur['has_guests']): ?>
              <b>Bàn <?= htmlspecialchars($currentTable) ?> đã gán <?= $cur['guests'] ?> khách</b>
              <p style="margin:6px 0 0;">Chọn món ở bảng bên phải để gửi xuống bếp.</p>
            <?php else: ?>
              <b>Bàn <?= htmlspecialchars($currentTable) ?> hiện đang trống</b>
              <p style="margin:6px 0 0;">Chọn món bên cạnh hoặc bấm "Gán bàn đón khách" để mở bàn.</p>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>

      <div class="pos-bill-scroll-bar">
        <div class="pos-scroll-btns">
          <button type="button" class="pos-scroll-btn" data-pos-scroll="up" title="Cuộn lên">▲</button>
          <button type="button" class="pos-scroll-btn" data-pos-scroll="down" title="Cuộn xuống">▼</button>
        </div>
        <div class="pos-guest-badge">
          <?= $cur['guests'] > 0 ? ($cur['guests'] . ' Khách') : ($cur['has_guests'] ? 'Đang phục vụ' : 'Chưa có khách') ?>
          <?php if ($isMaintenance): ?> · Bảo trì<?php endif; ?>
        </div>
      </div>

      <div class="pos-bill-totals">
        <div class="pos-total-row">
          <span>Tạm tính:</span>
          <b><?= formatMoney((int) $bill['subtotal']) ?></b>
        </div>
        <div class="pos-total-row">
          <span>Phí phục vụ (5%):</span>
          <b><?= formatMoney((int) $bill['service_fee']) ?></b>
        </div>
        <div class="pos-total-row">
          <span>Thuế VAT (8%):</span>
          <b><?= formatMoney((int) $bill['vat']) ?></b>
        </div>
        <div class="pos-total-row grand">
          <span>Thanh toán:</span>
          <span><?= formatMoney((int) $bill['total']) ?></span>
        </div>
      </div>
    </section>

    <!-- ==========================================================
         CỘT 2 (GIỮA): LƯỚI DANH MỤC & MÓN ĂN TỪ DB
         ========================================================== -->
    <section class="pos-center-menu">
      <div class="pos-center-toolbar">
        <button type="button" class="pos-tool-btn active" data-pos-home title="Về danh sách nhóm thực đơn">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M12 3l9 8h-3v9h-5v-6H11v6H6v-9H3l9-8z"/></svg>
          <span>Menu</span>
        </button>
        <button type="button" class="pos-tool-btn" data-pos-toggle-search title="Tìm món ăn">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
          <span>Tìm món</span>
        </button>
        <a class="pos-tool-btn" href="index.php?page=kitchen" title="Màn hình Bếp KDS">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 13.8V4a2 2 0 1 1 4 0v9.8"></path><path d="M14 13.8V4a2 2 0 1 1 4 0v9.8"></path><path d="M3 14h18a2 2 0 0 1 2 2v1a4 4 0 0 1-4 4H5a4 4 0 0 1-4-4v-1a2 2 0 0 1 2-2z"></path></svg>
          <span>KDS Bếp</span>
        </a>
        <button type="button" class="pos-tool-btn" data-refresh-items title="Cập nhật danh sách món">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"></polyline><polyline points="1 20 1 14 7 14"></polyline><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path></svg>
          <span>Làm mới</span>
        </button>
      </div>

      <div class="pos-search-bar" data-pos-search-bar>
        <input type="search" data-pos-search-input placeholder="Gõ tên món để tìm nhanh (vd: cơm gà, ba chỉ...)" autocomplete="off">
      </div>

      <div class="pos-tile-stage">
        <!-- CẤP 1: Ô DANH MỤC (GIỐNG ẢNH 3) -->
        <div data-pos-screen="categories">
          <?php if (empty($categoriesMeta)): ?>
            <p style="text-align:center;color:#475569;padding:30px;">Chưa có món ăn khả dụng trong thực đơn.</p>
          <?php else: ?>
            <?php
              $alacarteCats = array_filter($categoriesMeta, static fn(array $c): bool => ($c['menu_type'] ?? 'alacarte') !== 'buffet');
              $buffetCats   = array_filter($categoriesMeta, static fn(array $c): bool => ($c['menu_type'] ?? 'alacarte') === 'buffet');
            ?>

            <?php if (!empty($alacarteCats)): ?>
              <div class="pos-menu-section-tag">ALACARTE / GỌI MÓN LẺ</div>
              <div class="pos-tile-grid">
                <?php $ci = 0; foreach ($alacarteCats as $cat): ?>
                  <button type="button"
                          class="pos-tile <?= $tileColors[$ci % count($tileColors)] ?>"
                          data-pos-cat-tile="<?= htmlspecialchars($cat['key']) ?>">
                    <span><?= escapeHtml($cat['name']) ?></span>
                    <span class="pos-tile-sub"><?= count($cat['dishes']) ?> món</span>
                  </button>
                  <?php $ci++; ?>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>

            <?php if (!empty($buffetCats)): ?>
              <div class="pos-menu-section-tag">MENU BUFFET</div>
              <div class="pos-tile-grid">
                <?php $bi = 1; foreach ($buffetCats as $cat): ?>
                  <button type="button"
                          class="pos-tile <?= $tileColors[$bi % count($tileColors)] ?>"
                          data-pos-cat-tile="<?= htmlspecialchars($cat['key']) ?>">
                    <span><?= escapeHtml($cat['name']) ?></span>
                    <span class="pos-tile-sub"><?= count($cat['dishes']) ?> món</span>
                  </button>
                  <?php $bi++; ?>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          <?php endif; ?>
        </div>

        <!-- CẤP 2: Ô MÓN ĂN TRONG DANH MỤC (GIỐNG ẢNH 2) -->
        <div data-pos-screen="dishes" hidden>
          <div class="pos-tile-grid">
            <button type="button" class="pos-tile pos-tile-back" data-pos-back-cats title="Quay lại nhóm danh mục">
              <span>← Quay lại</span>
              <span class="pos-tile-sub" style="background:rgba(255,255,255,.18);color:#fff;">Nhóm menu</span>
            </button>
            <?php $di = 2; foreach ($categoriesMeta as $cat): ?>
              <?php foreach ($cat['dishes'] as $dish): ?>
                <?php $isOut = ($dish['status'] ?? '') !== 'active'; ?>
                <button type="button"
                        class="pos-tile <?= $tileColors[$di % count($tileColors)] ?><?= (!$canAdd || $isOut) ? ' is-disabled' : '' ?>"
                        data-pos-dish-tile
                        data-cat-key="<?= htmlspecialchars($cat['key']) ?>"
                        data-dish-name="<?= escapeHtml(mb_strtolower($dish['name'], 'UTF-8')) ?>"
                        data-pos-dish-id="<?= (int) $dish['id'] ?>"
                        data-dish-label="<?= escapeHtml($dish['name']) ?>"
                        <?= (!$canAdd || $isOut) ? 'disabled' : '' ?>>
                  <span><?= escapeHtml($dish['name']) ?></span>
                  <span class="pos-tile-sub">
                    <?= $isOut ? 'Tạm hết' : ((int) $dish['price'] > 0 ? formatMoney((int) $dish['price']) : 'Buffet') ?>
                  </span>
                </button>
                <?php $di++; ?>
              <?php endforeach; ?>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </section>

    <!-- ==========================================================
         CỘT 3 (PHẢI): THÔNG TIN + NUMPAD SỐ LƯỢNG + THAO TÁC THẬT
         ========================================================== -->
    <aside class="pos-right-panel">
      <div class="pos-info-dark">
        <div class="pos-info-dark-row"><span>Bàn:</span><b><?= htmlspecialchars($currentTable) ?> · <?= htmlspecialchars($cur['zone']) ?></b></div>
        <div class="pos-info-dark-row"><span>Mở bàn:</span><b><?= htmlspecialchars($cur['time']) ?></b></div>
        <div class="pos-info-dark-row"><span>Đăng nhập:</span><b><?= htmlspecialchars($operatorName) ?></b></div>
        <div class="pos-info-dark-row"><span>Phụ trách:</span><b><?= htmlspecialchars($cur['staff']) ?></b></div>
        <?php if ($cur['note'] !== ''): ?>
          <div class="pos-info-dark-row"><span>Ghi chú bàn:</span><b title="<?= htmlspecialchars($cur['note']) ?>"><?= htmlspecialchars($cur['note']) ?></b></div>
        <?php endif; ?>
      </div>

      <div class="pos-numpad-box">
        <div class="pos-selected-pill" data-pos-selected-banner hidden>
          <span data-pos-selected-label>Đang chọn: ...</span>
          <button type="button" data-pos-clear-select title="Bỏ chọn món">✕</button>
        </div>
        <div class="pos-qty-display">
          <small>Số lượng gọi:</small>
          <span data-pos-qty-value>1</span>
        </div>
        <div class="pos-numpad-grid">
          <?php foreach (['7', '8', '9', '4', '5', '6', '1', '2', '3', '0'] as $digit): ?>
            <button type="button" class="pos-num-btn" data-pos-num="<?= $digit ?>"><?= $digit ?></button>
          <?php endforeach; ?>
          <button type="button" class="pos-num-btn is-clear" data-pos-num="clear" data-pos-delete-key>Xóa</button>
        </div>
      </div>

      <?php if ($canAdd): ?>
        <div class="pos-note-strip">
          <input type="text" data-pos-note-input maxlength="255" placeholder="Ghi chú món sắp gọi..." autocomplete="off">
          <div class="pos-note-chips">
            <?php foreach (['Ít cay', 'Không hành', 'Mang ra trước', 'Không đá'] as $chip): ?>
              <button type="button" class="pos-note-chip" data-pos-note-chip="<?= escapeHtml($chip) ?>"><?= escapeHtml($chip) ?></button>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>

      <div class="pos-fn-grid">
        <?php if ($canAdd && $dishesByCategory !== []): ?>
          <button type="button" class="pos-fn-btn" data-open-add-item>
            <span>+ Tìm &amp; Thêm món</span>
          </button>
        <?php endif; ?>

        <?php if (!$isMaintenance): ?>
          <button type="button"
                  class="pos-fn-btn"
                  data-open-assign-table="<?= htmlspecialchars($currentTable) ?>"
                  data-assign-update="<?= $cur['has_guests'] ? '1' : '0' ?>"
                  data-guest-count="<?= $cur['guests'] > 0 ? $cur['guests'] : 2 ?>"
                  data-staff-id="<?= $cur['staff_id'] ?>"
                  data-customer-name="<?= htmlspecialchars($cur['customer_name']) ?>"
                  data-customer-phone="<?= htmlspecialchars($cur['customer_phone']) ?>"
                  data-assign-note="<?= htmlspecialchars($cur['note']) ?>">
            <span><?= $cur['has_guests'] ? 'Cập nhật bàn' : 'Gán / Mở bàn' ?></span>
          </button>
        <?php endif; ?>

        <button type="button" class="pos-fn-btn" data-refresh-items>
          <span>Cập nhật Bill</span>
        </button>

        <a class="pos-fn-btn" href="index.php?page=kitchen">
          <span>Xem KDS Bếp</span>
        </a>
      </div>
    </aside>

  </div>

  <!-- ==========================================================
       THANH THAO TÁC ĐÁY (CHỈ CÁC NÚT CÓ CHỨC NĂNG THẬT TRONG KDS)
       ========================================================== -->
  <div class="pos-bottom-bar">
    <div class="pos-bottom-left">
      <?php if (!$isMaintenance && $cur['has_guests']): ?>
        <button type="button" class="pos-bot-btn" data-open-transfer-table="<?= htmlspecialchars($currentTable) ?>">
          <span>⇄ Chuyển bàn</span>
        </button>

        <button type="button" class="pos-bot-btn" data-open-merge-table="<?= htmlspecialchars($currentTable) ?>">
          <span>⊞ Gộp bàn</span>
        </button>

        <?php if (!$hasOrderItems): ?>
          <button type="button" class="pos-bot-btn is-danger" data-release-table="<?= htmlspecialchars($currentTable) ?>">
            <span>Trả bàn trống</span>
          </button>
        <?php endif; ?>
      <?php elseif (!$isMaintenance): ?>
        <button type="button"
                class="pos-bot-btn"
                data-open-assign-table="<?= htmlspecialchars($currentTable) ?>"
                data-assign-update="0">
          <span>+ Gán bàn đón khách</span>
        </button>
      <?php endif; ?>

      <a class="pos-bot-btn" href="index.php?page=staff">
        <span>Sơ đồ bàn</span>
      </a>
    </div>

    <div class="pos-bottom-right">
      <a class="pos-confirm-green<?= !$hasOrderItems ? ' is-disabled' : '' ?>"
         href="<?= $hasOrderItems ? ('index.php?page=payment&table=' . urlencode($currentTable)) : '#' ?>">
        <span>✓ Thanh toán</span>
      </a>
      <a class="pos-exit-red" href="index.php?page=staff">
        <span>✕ Thoát</span>
      </a>
    </div>
  </div>
</div>

<!-- HỘP THOẠI TÌM & THÊM MÓN (TƯƠNG THÍCH ORDER-ITEMS.JS) -->
<?php if ($canAdd && $dishesByCategory !== []): ?>
  <dialog class="add-item-dialog" data-add-item-dialog>
    <form class="add-item-form" data-add-item-form data-table="<?= htmlspecialchars($currentTable) ?>" novalidate>
      <div class="add-item-head">
        <h3>Thêm món · Bàn <?= htmlspecialchars($currentTable) ?></h3>
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
                      <span class="dish-price"><?= (int) $dish['price'] > 0 ? formatMoney((int) $dish['price']) : 'Buffet' ?></span>
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

<!-- HỘP THOẠI CHỌN SỐ LƯỢNG CẦN HỦY MÓN (KHI BẤM CHỌN MÓN + NÚT XÓA) -->
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

<?php include __DIR__ . '/table-modals.php'; ?>

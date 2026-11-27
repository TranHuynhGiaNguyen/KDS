/**
 * SmartDine - Trang chi tiết bàn (POS 2 màn hình theo chuẩn tablet):
 *   - Màn 1 (Gọi món): Giỏ món nháp bên trái | Menu Buffet / Alacarte / Đồ uống + Tìm kiếm bên phải
 *   - Màn 2 (Kiểm đồ): Tiến độ bếp, danh sách món đã gọi (ẩn/hiện món đã hủy),
 *     hủy món theo quyền (Admin / Quản lý ca), in phiếu kiểm đồ, voucher, giảm giá & thanh toán.
 */
(() => {
    const posShell = document.querySelector('[data-pos-shell]');
    if (!posShell) return;

    const apiUrl = 'api/order-item.php';
    const POLL_INTERVAL_MS = 5000;

    const tableCode = posShell.dataset.table || '';
    const maxQty = Number(posShell.dataset.maxQty) || 20;
    const canCancelPage = posShell.dataset.canCancel === '1';
    const canAddPage = posShell.dataset.canAdd === '1';

    let busy = false;
    let draftItems = []; // [{ id, name, price, menuType, qty, note }]
    let activePrimaryTab = posShell.querySelector('[data-pos-primary-tab].active')?.dataset.posPrimaryTab || 'buffet';
    let activeSubCatKey = 'all';
    let editingNoteIndex = -1;
    let selectedCancelItem = null; // { id, name, qty, status, canCancel }

    const screenOrder = posShell.querySelector('#pos-screen-order');
    const screenKiemdo = posShell.querySelector('#pos-screen-kiemdo');

    const draftListEl = posShell.querySelector('[data-pos-draft-list]');
    const draftEmptyEl = posShell.querySelector('[data-pos-draft-empty]');
    const draftTotalEl = posShell.querySelector('[data-pos-draft-total]');
    const clearDraftBtn = posShell.querySelector('[data-pos-clear-draft]');
    const sendOrderBtn = posShell.querySelector('[data-pos-send-order]');

    const searchBar = posShell.querySelector('[data-pos-search-bar]');
    const searchInput = posShell.querySelector('[data-pos-search-input]');
    const subcatStrip = posShell.querySelector('[data-pos-subcat-strip]');
    const searchEmptyNote = posShell.querySelector('[data-pos-search-empty]');

    const moreMenu = posShell.querySelector('[data-pos-more-menu]');
    const showCancelledToggle = posShell.querySelector('[data-kd-toggle-cancelled]');

    // Dialogs
    const cancelDialog = document.querySelector('[data-cancel-qty-dialog]');
    const cancelForm = document.querySelector('[data-cancel-qty-form]');
    const cancelTitle = document.querySelector('[data-cancel-item-title]');
    const cancelMaxLabel = document.querySelector('[data-cancel-max-label]');
    const cancelQtyInput = document.querySelector('[data-cancel-qty-input]');
    const cancelQuickRow = document.querySelector('[data-cancel-quick-row]');
    const cancelSummary = document.querySelector('[data-cancel-summary]');
    const cancelSubmitBtn = document.querySelector('[data-cancel-submit]');

    const draftNoteDialog = document.querySelector('[data-draft-note-dialog]');
    const draftNoteForm = document.querySelector('[data-draft-note-form]');
    const draftNoteTitle = document.querySelector('[data-draft-note-title]');
    const draftNoteInput = document.querySelector('[data-draft-note-input]');

    const qrDialog = document.querySelector('[data-qr-print-dialog]');
    const ramonDialog = document.querySelector('[data-ramon-dialog]');
    const provDialog = document.querySelector('[data-provisional-dialog]');
    const provBody = document.querySelector('[data-provisional-body]');
    const discountDialog = document.querySelector('[data-staff-discount-dialog]');
    const voucherDialog = document.querySelector('[data-kd-voucher-dialog]');

    const removeAccents = (text) => String(text || '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/đ/gi, 'd')
        .toLowerCase();

    const formatMoney = (amount) => `${new Intl.NumberFormat('vi-VN').format(Number(amount || 0))} đ`;

    const escHtml = (s) => String(s || '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');

    const showMessage = (message) => {
        if (typeof notify === 'function') {
            notify(message);
        } else {
            alert(message);
        }
    };

    const callApi = async (action, fields) => {
        const body = new FormData();
        body.set('action', action);
        Object.entries(fields).forEach(([k, v]) => body.set(k, v));

        try {
            const response = await fetch(apiUrl, { method: 'POST', body });
            const result = await response.json();
            if (!response.ok) {
                showMessage(result.error || 'Không xử lý được yêu cầu.');
                return null;
            }
            return result;
        } catch (err) {
            showMessage('Không thể kết nối máy chủ. Vui lòng thử lại.');
            return null;
        }
    };

    // ==========================================================
    // ĐỒNG BỘ DỮ LIỆU ĐÃ ĐẶT TỪ SERVER (KHÔNG MẤT GIỎ NHÁP)
    // ==========================================================
    const applyCancelledVisibility = () => {
        const showCancelled = Boolean(showCancelledToggle?.checked);
        posShell.querySelectorAll('[data-pos-kiemdo-rows] .kd-item-row[data-is-cancelled="1"]').forEach((row) => {
            row.hidden = !showCancelled;
        });
    };

    const refreshList = async () => {
        try {
            const response = await fetch(window.location.href, { cache: 'no-store' });
            if (!response.ok) return false;

            const doc = new DOMParser().parseFromString(await response.text(), 'text/html');
            const selectorsToSync = [
                '[data-pos-kiemdo-rows]',
                '[data-pos-kiemdo-pills]',
                '[data-pos-kiemdo-bill]',
                '[data-pos-kiemdo-footer]',
                '[data-pos-kiemdo-badge]',
                '[data-pos-committed-banner]',
                '[data-pos-guest-pill]',
                '[data-pos-more-menu]',
            ];

            selectorsToSync.forEach((sel) => {
                const curEl = posShell.querySelector(sel);
                const freshEl = doc.querySelector(sel);
                if (!curEl || !freshEl) return;

                if (curEl.hasAttribute('hidden') !== freshEl.hasAttribute('hidden')) {
                    if (sel !== '[data-pos-more-menu]' && sel !== '[data-pos-kiemdo-bill]') {
                        if (freshEl.hasAttribute('hidden')) curEl.setAttribute('hidden', '');
                        else curEl.removeAttribute('hidden');
                    }
                }
                if (curEl.innerHTML !== freshEl.innerHTML) {
                    curEl.innerHTML = freshEl.innerHTML;
                }
            });

            const curRamon = document.querySelector('[data-pos-ramon-body]');
            const freshRamon = doc.querySelector('[data-pos-ramon-body]');
            if (curRamon && freshRamon && curRamon.innerHTML !== freshRamon.innerHTML) {
                curRamon.innerHTML = freshRamon.innerHTML;
            }

            applyCancelledVisibility();
            return true;
        } catch (err) {
            return false;
        }
    };

    // ==========================================================
    // RENDER GIỎ MÓN NHÁP (CỘT TRÁI MÀN 1) & BADGE TRÊN MENU
    // ==========================================================
    const renderDraftPane = () => {
        const hasDraft = draftItems.length > 0;
        if (draftEmptyEl) draftEmptyEl.hidden = hasDraft;
        if (clearDraftBtn) clearDraftBtn.disabled = !hasDraft || busy;
        if (sendOrderBtn) sendOrderBtn.disabled = !hasDraft || busy || !canAddPage;

        const subtotal = draftItems.reduce((sum, it) => sum + (Number(it.price) * Number(it.qty)), 0);
        if (draftTotalEl) draftTotalEl.textContent = formatMoney(subtotal);

        if (draftListEl) {
            draftListEl.innerHTML = draftItems.map((it, idx) => {
                const lineAmt = Number(it.price) * Number(it.qty);
                return `
                  <div class="pos-draft-row" data-draft-idx="${idx}">
                    <div class="pos-draft-row-top">
                      <div class="pos-draft-info">
                        <b>${escHtml(it.name)}</b>
                        <div class="pos-draft-meta">
                          <span>${Number(it.price) <= 0 ? 'Buffet' : formatMoney(it.price)}</span>
                          ${it.note ? `<span class="pos-draft-note-tag">· ${escHtml(it.note)}</span>` : ''}
                        </div>
                      </div>
                      <div class="pos-draft-price">${lineAmt > 0 ? formatMoney(lineAmt) : '0 đ'}</div>
                    </div>
                    <div class="pos-draft-row-controls">
                      <div class="pos-draft-stepper">
                        <button type="button" data-draft-dec="${idx}" aria-label="Giảm">−</button>
                        <span>${it.qty}</span>
                        <button type="button" data-draft-inc="${idx}" aria-label="Tăng">+</button>
                      </div>
                      <button type="button" class="pos-draft-note-btn" data-draft-edit-note="${idx}">
                        ${it.note ? 'Sửa ghi chú' : '+ Ghi chú'}
                      </button>
                      <button type="button" class="pos-draft-remove-btn" data-draft-remove="${idx}" title="Xóa khỏi đơn nháp">✕</button>
                    </div>
                  </div>
                `;
            }).join('');
        }

        // Cập nhật badge số lượng đang chọn trên ô món ăn bên cột phải
        const qtyByDishId = new Map();
        draftItems.forEach((it) => {
            const id = String(it.id);
            qtyByDishId.set(id, (qtyByDishId.get(id) || 0) + Number(it.qty));
        });
        posShell.querySelectorAll('[data-dish-badge-id]').forEach((badge) => {
            const qty = qtyByDishId.get(badge.dataset.dishBadgeId) || 0;
            badge.hidden = qty <= 0;
            badge.textContent = qty > 0 ? `×${qty}` : '0';
        });
    };

    // ==========================================================
    // CỘT PHẢI MÀN 1: TAB CHÍNH (BUFFET / ALACARTE / ĐỒ UỐNG), SUBCATS & TÌM KIẾM
    // ==========================================================
    const renderSubCategories = () => {
        if (!subcatStrip) return;
        const activePanel = posShell.querySelector(`[data-pos-tab-panel="${activePrimaryTab}"]`);
        if (!activePanel) {
            subcatStrip.innerHTML = '';
            return;
        }

        const sections = [...activePanel.querySelectorAll('.pos-dish-section')];
        if (!sections.length) {
            subcatStrip.innerHTML = '';
            return;
        }

        const btns = [`
          <button type="button" class="pos-subcat-pill${activeSubCatKey === 'all' ? ' active' : ''}" data-pos-subcat="all">
            TẤT CẢ
          </button>
        `];
        sections.forEach((sec) => {
            const key = sec.dataset.posSectionKey;
            const name = sec.dataset.posSectionName || '';
            btns.push(`
              <button type="button" class="pos-subcat-pill${activeSubCatKey === key ? ' active' : ''}" data-pos-subcat="${escHtml(key)}">
                ${escHtml(name)}
              </button>
            `);
        });
        subcatStrip.innerHTML = btns.join('');
    };

    const filterRightMenu = () => {
        const activePanel = posShell.querySelector(`[data-pos-tab-panel="${activePrimaryTab}"]`);
        if (!activePanel) return;

        const words = removeAccents(searchInput?.value || '').split(/\s+/).filter(Boolean);
        const sections = [...activePanel.querySelectorAll('.pos-dish-section')];
        let totalVisibleDishes = 0;

        sections.forEach((sec) => {
            const matchCat = (activeSubCatKey === 'all' || sec.dataset.posSectionKey === activeSubCatKey);
            let visibleInSection = 0;

            sec.querySelectorAll('[data-pos-menu-dish]').forEach((card) => {
                const hay = removeAccents(card.dataset.searchText || '');
                const matchSearch = !words.length || words.every((w) => hay.includes(w));
                const showCard = matchCat && matchSearch;
                card.hidden = !showCard;
                if (showCard) {
                    visibleInSection++;
                    totalVisibleDishes++;
                }
            });

            sec.hidden = visibleInSection === 0;
        });

        if (searchEmptyNote) {
            searchEmptyNote.hidden = !(sections.length > 0 && totalVisibleDishes === 0);
        }
    };

    const switchPrimaryTab = (tabKey) => {
        activePrimaryTab = tabKey;
        activeSubCatKey = 'all';

        posShell.querySelectorAll('[data-pos-primary-tab]').forEach((btn) => {
            btn.classList.toggle('active', btn.dataset.posPrimaryTab === tabKey);
        });
        posShell.querySelectorAll('[data-pos-tab-panel]').forEach((panel) => {
            panel.hidden = panel.dataset.posTabPanel !== tabKey;
        });

        renderSubCategories();
        filterRightMenu();
    };

    // ==========================================================
    // CHUYỂN MÀN HÌNH ORDER <-> KIỂM ĐỒ
    // ==========================================================
    const switchScreen = (target) => {
        if (target === 'kiemdo') {
            if (screenOrder) screenOrder.hidden = true;
            if (screenKiemdo) screenKiemdo.hidden = false;
            refreshList();
        } else {
            if (screenKiemdo) screenKiemdo.hidden = true;
            if (screenOrder) screenOrder.hidden = false;
        }
    };

    // ==========================================================
    // SỰ KIỆN TRÊN POS SHELL
    // ==========================================================
    posShell.addEventListener('click', async (event) => {
        // 1. Chuyển màn Kiểm đồ / Quay lại
        if (event.target.closest('[data-pos-open-kiemdo]')) {
            switchScreen('kiemdo');
            return;
        }
        if (event.target.closest('[data-pos-close-kiemdo]')) {
            switchScreen('order');
            return;
        }

        // 2. Toggle menu '...' ở góc phải header cột trái
        if (event.target.closest('[data-pos-more-toggle]')) {
            if (moreMenu) moreMenu.hidden = !moreMenu.hidden;
            return;
        }
        if (moreMenu && !moreMenu.hidden && !event.target.closest('.pos-more-wrap')) {
            moreMenu.hidden = true;
        }
        if (event.target.closest('.pos-more-menu button, .pos-more-menu a')) {
            if (moreMenu) moreMenu.hidden = true;
        }

        // 3. Tiếp nhận thông báo khách Gọi NV
        const dismissCallBtn = event.target.closest('[data-dismiss-call]');
        if (dismissCallBtn) {
            const code = dismissCallBtn.dataset.dismissCall;
            try {
                await fetch('api/table-action.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'resolve_call', table_code: code })
                });
            } catch (e) {}
            posShell.querySelector('[data-pos-call-banner]')?.remove();
            return;
        }

        // 4. Chọn tab menu chính (Buffet | Alacarte | Đồ uống)
        const pTabBtn = event.target.closest('[data-pos-primary-tab]');
        if (pTabBtn) {
            switchPrimaryTab(pTabBtn.dataset.posPrimaryTab);
            return;
        }

        // 5. Chọn danh mục con (Sub-category pill)
        const subPill = event.target.closest('[data-pos-subcat]');
        if (subPill) {
            activeSubCatKey = subPill.dataset.posSubcat;
            renderSubCategories();
            filterRightMenu();
            return;
        }

        // 6. Toggle Tìm kiếm món
        if (event.target.closest('[data-pos-toggle-search]')) {
            if (searchBar) {
                searchBar.classList.toggle('open');
                if (searchBar.classList.contains('open') && searchInput) {
                    searchInput.focus();
                } else if (searchInput) {
                    searchInput.value = '';
                    filterRightMenu();
                }
            }
            return;
        }

        // 7. Chạm vào ô món ở Cột Phải -> Thêm vào giỏ nháp cột Trái
        const dishCard = event.target.closest('[data-pos-menu-dish]');
        if (dishCard) {
            if (dishCard.disabled) return;
            const dishId = Number(dishCard.dataset.dishId);
            const dishName = dishCard.dataset.dishName || 'Món';
            const dishPrice = Number(dishCard.dataset.dishPrice || 0);
            const menuType = dishCard.dataset.dishMenuType || 'alacarte';

            const existing = draftItems.find((it) => it.id === dishId && (it.note || '') === '');
            if (existing) {
                if (existing.qty >= maxQty) {
                    showMessage(`Mỗi dòng tối đa ${maxQty} phần.`);
                    return;
                }
                existing.qty += 1;
            } else {
                draftItems.push({
                    id: dishId,
                    name: dishName,
                    price: dishPrice,
                    menuType,
                    qty: 1,
                    note: '',
                });
            }
            renderDraftPane();
            return;
        }

        // 8. Tăng / giảm / xóa / sửa ghi chú trong Giỏ Nháp
        const decBtn = event.target.closest('[data-draft-dec]');
        if (decBtn) {
            const idx = Number(decBtn.dataset.draftDec);
            if (draftItems[idx]) {
                draftItems[idx].qty -= 1;
                if (draftItems[idx].qty <= 0) draftItems.splice(idx, 1);
                renderDraftPane();
            }
            return;
        }

        const incBtn = event.target.closest('[data-draft-inc]');
        if (incBtn) {
            const idx = Number(incBtn.dataset.draftInc);
            if (draftItems[idx]) {
                if (draftItems[idx].qty >= maxQty) {
                    showMessage(`Tối đa ${maxQty} phần mỗi dòng.`);
                    return;
                }
                draftItems[idx].qty += 1;
                renderDraftPane();
            }
            return;
        }

        const remBtn = event.target.closest('[data-draft-remove]');
        if (remBtn) {
            const idx = Number(remBtn.dataset.draftRemove);
            draftItems.splice(idx, 1);
            renderDraftPane();
            return;
        }

        const noteBtn = event.target.closest('[data-draft-edit-note]');
        if (noteBtn && draftNoteDialog) {
            const idx = Number(noteBtn.dataset.draftEditNote);
            if (!draftItems[idx]) return;
            editingNoteIndex = idx;
            if (draftNoteTitle) draftNoteTitle.textContent = draftItems[idx].name;
            if (draftNoteInput) draftNoteInput.value = draftItems[idx].note || '';
            draftNoteDialog.querySelectorAll('[data-draft-note-chip]').forEach((c) => {
                c.classList.toggle('is-active', (draftNoteInput?.value || '').includes(c.dataset.draftNoteChip));
            });
            draftNoteDialog.showModal();
            return;
        }

        // 9. XÓA TẤT CẢ giỏ nháp
        if (event.target.closest('[data-pos-clear-draft]')) {
            if (!draftItems.length || busy) return;
            if (confirm('Xóa toàn bộ món đang chọn ở đơn nháp?')) {
                draftItems = [];
                renderDraftPane();
            }
            return;
        }

        // 10. GỬI ORDER xuống Bếp KDS & MySQL
        if (event.target.closest('[data-pos-send-order]')) {
            if (!draftItems.length || busy || !canAddPage) return;

            busy = true;
            if (sendOrderBtn) {
                sendOrderBtn.disabled = true;
                sendOrderBtn.textContent = 'ĐANG GỬI...';
            }

            let sentCount = 0;
            let failedCount = 0;
            const remaining = [];

            for (const item of draftItems) {
                const res = await callApi('add', {
                    table: tableCode,
                    dish_id: item.id,
                    qty: item.qty,
                    note: item.note || '',
                });
                if (res && res.ok) {
                    sentCount += item.qty;
                } else {
                    failedCount += 1;
                    remaining.push(item);
                }
            }

            draftItems = remaining;
            busy = false;
            if (sendOrderBtn) sendOrderBtn.textContent = 'GỬI ORDER';
            renderDraftPane();
            await refreshList();

            if (sentCount > 0 && failedCount === 0) {
                showMessage(`Đã gửi ${sentCount} phần món xuống Bếp KDS cho Bàn ${tableCode}!`);
            } else if (sentCount > 0) {
                showMessage(`Đã gửi ${sentCount} phần, còn ${failedCount} dòng lỗi.`);
            }
            return;
        }

        // 11. In QRCode cố định của bàn
        if (event.target.closest('[data-pos-open-qrcode]') && qrDialog) {
            const box = qrDialog.querySelector('[data-pos-qr-box]');
            if (box && typeof qrcode === 'function') {
                const qr = qrcode(0, 'M');
                qr.addData(box.dataset.qrUrl || '');
                qr.make();
                box.innerHTML = qr.createSvgTag({ cellSize: 5, margin: 2, scalable: true });
            }
            qrDialog.showModal();
            return;
        }

        // 12. Xem Ra món (tiến độ bếp)
        if (event.target.closest('[data-pos-open-ramon]') && ramonDialog) {
            await refreshList();
            ramonDialog.showModal();
            return;
        }

        // 13. Toggle Chi tiết bill trong Kiểm đồ
        if (event.target.closest('[data-kd-toggle-bill]')) {
            const billBox = posShell.querySelector('[data-pos-kiemdo-bill]');
            const trigger = event.target.closest('[data-kd-toggle-bill]');
            if (billBox) {
                billBox.hidden = !billBox.hidden;
                if (trigger) {
                    trigger.textContent = billBox.hidden ? 'Chi tiết ▸' : 'Thu gọn ▾';
                }
            }
            return;
        }

        // 14. Nút (X) Hủy món trên màn hình Kiểm đồ
        const cancelCircleBtn = event.target.closest('[data-kd-cancel-item]');
        if (cancelCircleBtn) {
            const row = cancelCircleBtn.closest('.kd-item-row');
            if (!row) return;

            if (!canCancelPage) {
                showMessage('Chỉ Quản trị viên / Quản lý ca mới được hủy món.');
                return;
            }
            if (row.dataset.canCancel !== '1') {
                showMessage('Món này đã phục vụ hoặc không thể hủy.');
                return;
            }

            selectedCancelItem = {
                id: row.dataset.itemId,
                name: row.dataset.itemName,
                qty: Math.max(1, parseInt(row.dataset.itemQty || '1', 10)),
                status: row.dataset.itemStatus || 'pending',
                canCancel: true,
            };
            openCancelDialog();
            return;
        }

        // 15. Nút In Kiểm đồ / Phiếu tạm tính
        if (event.target.closest('[data-kd-print-provisional]')) {
            try {
                const res = await fetch(`api/provisional-bill.php?table=${encodeURIComponent(tableCode)}`, { cache: 'no-store' });
                const data = await res.json();
                if (!res.ok) {
                    showMessage(data.error || 'Không tải được phiếu kiểm đồ.');
                    return;
                }
                if (provBody && provDialog) {
                    provBody.innerHTML = data.html;
                    provDialog.showModal();
                }
            } catch (e) {
                showMessage('Không kết nối được máy chủ.');
            }
            return;
        }

        // 16. Nút Giảm giá nhân viên
        if (event.target.closest('[data-kd-staff-discount]') && discountDialog) {
            discountDialog.showModal();
            return;
        }

        // 17. Nút Voucher
        if (event.target.closest('[data-kd-open-voucher]') && voucherDialog) {
            const feedback = voucherDialog.querySelector('[data-kd-voucher-feedback]');
            if (feedback) feedback.textContent = '';
            voucherDialog.showModal();
            return;
        }

        // 18. Nút Phiếu thanh toán disabled check
        const goPay = event.target.closest('[data-kd-go-payment]');
        if (goPay && goPay.classList.contains('is-disabled')) {
            event.preventDefault();
            showMessage(`Bàn ${tableCode} chưa có món nào để thanh toán.`);
        }
    });

    searchInput?.addEventListener('input', filterRightMenu);
    showCancelledToggle?.addEventListener('change', applyCancelledVisibility);

    // ==========================================================
    // SỰ KIỆN CÁC DIALOG HỖ TRỢ
    // ==========================================================
    const updateCancelDialogState = () => {
        if (!selectedCancelItem || !cancelQtyInput) return;
        const max = selectedCancelItem.qty;
        const val = Math.min(max, Math.max(1, parseInt(cancelQtyInput.value || '1', 10)));
        cancelQtyInput.value = String(val);
        if (cancelSummary) {
            cancelSummary.textContent = val >= max
                ? `Sẽ hủy toàn bộ ${max} phần "${selectedCancelItem.name}"`
                : `Sẽ hủy ${val}/${max} phần (còn lại ${max - val} phần)`;
        }
        if (cancelQuickRow) {
            cancelQuickRow.querySelectorAll('.pos-cancel-quick-btn').forEach((b) => {
                b.classList.toggle('active', Number(b.dataset.quickCancelQty) === val);
            });
        }
    };

    function openCancelDialog() {
        if (!selectedCancelItem || !cancelDialog) return;
        const max = selectedCancelItem.qty;
        if (cancelTitle) cancelTitle.textContent = `${selectedCancelItem.name} · Hiện có ${max} phần`;
        if (cancelMaxLabel) cancelMaxLabel.textContent = String(max);
        if (cancelQtyInput) {
            cancelQtyInput.max = String(max);
            cancelQtyInput.value = '1';
        }
        if (cancelQuickRow) {
            const picks = [...new Set([1, 2, 3, max].filter((n) => n >= 1 && n <= max))];
            cancelQuickRow.innerHTML = picks.map((n) => `
              <button type="button" class="pos-cancel-quick-btn" data-quick-cancel-qty="${n}">
                ${n === max && max > 1 ? `Tất cả (${n})` : `${n} phần`}
              </button>
            `).join('');
        }
        updateCancelDialogState();
        cancelDialog.showModal();
    }

    cancelDialog?.addEventListener('click', (e) => {
        if (e.target === cancelDialog || e.target.closest('[data-cancel-close]')) {
            cancelDialog.close();
            return;
        }
        const quickBtn = e.target.closest('[data-quick-cancel-qty]');
        if (quickBtn && cancelQtyInput) {
            cancelQtyInput.value = quickBtn.dataset.quickCancelQty;
            updateCancelDialogState();
        }
        const stepBtn = e.target.closest('[data-cancel-step]');
        if (stepBtn && cancelQtyInput && selectedCancelItem) {
            const max = selectedCancelItem.qty;
            const cur = parseInt(cancelQtyInput.value || '1', 10);
            cancelQtyInput.value = String(Math.min(max, Math.max(1, cur + Number(stepBtn.dataset.cancelStep))));
            updateCancelDialogState();
        }
    });
    cancelQtyInput?.addEventListener('input', updateCancelDialogState);

    cancelForm?.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (!selectedCancelItem || !cancelQtyInput) return;

        const qtyToCancel = Math.min(selectedCancelItem.qty, Math.max(1, parseInt(cancelQtyInput.value || '1', 10)));
        const targetName = selectedCancelItem.name;

        if (cancelSubmitBtn) cancelSubmitBtn.disabled = true;
        busy = true;
        const res = await callApi('cancel', { item_id: selectedCancelItem.id, qty: qtyToCancel });
        busy = false;
        if (cancelSubmitBtn) cancelSubmitBtn.disabled = false;

        if (res) {
            cancelDialog.close();
            selectedCancelItem = null;
            await refreshList();
            showMessage(`Đã hủy ${qtyToCancel} phần "${targetName}"`);
        }
    });

    // Draft note dialog
    draftNoteDialog?.addEventListener('click', (e) => {
        if (e.target === draftNoteDialog || e.target.closest('[data-draft-note-close]')) {
            draftNoteDialog.close();
            return;
        }
        const chip = e.target.closest('[data-draft-note-chip]');
        if (chip && draftNoteInput) {
            const val = chip.dataset.draftNoteChip;
            const parts = draftNoteInput.value.split(',').map((s) => s.trim()).filter(Boolean);
            const exists = parts.includes(val);
            draftNoteInput.value = (exists ? parts.filter((s) => s !== val) : [...parts, val]).join(', ');
            chip.classList.toggle('is-active', !exists);
        }
    });
    draftNoteForm?.addEventListener('submit', (e) => {
        e.preventDefault();
        if (editingNoteIndex >= 0 && draftItems[editingNoteIndex]) {
            draftItems[editingNoteIndex].note = (draftNoteInput?.value || '').trim().slice(0, 255);
            renderDraftPane();
        }
        draftNoteDialog.close();
    });

    // QR dialog
    qrDialog?.addEventListener('click', async (e) => {
        if (e.target === qrDialog || e.target.closest('[data-qr-dialog-close]')) {
            qrDialog.close();
            return;
        }
        const copyBtn = e.target.closest('[data-copy-qr-url]');
        if (copyBtn) {
            const url = copyBtn.dataset.copyQrUrl || '';
            try {
                await navigator.clipboard.writeText(url);
                showMessage('Đã sao chép liên kết QR bàn ' + tableCode);
            } catch (err) {
                showMessage(url);
            }
            return;
        }
        if (e.target.closest('[data-print-qr-btn]')) {
            window.print();
        }
    });

    // Ra món dialog
    ramonDialog?.addEventListener('click', (e) => {
        if (e.target === ramonDialog || e.target.closest('[data-ramon-close]')) {
            ramonDialog.close();
        }
    });

    // Provisional bill dialog
    provDialog?.addEventListener('click', (e) => {
        if (e.target === provDialog || e.target.closest('[data-provisional-close]')) {
            provDialog.close();
            return;
        }
        if (e.target.closest('[data-provisional-print]')) {
            window.print();
        }
    });

    // Staff discount dialog
    discountDialog?.addEventListener('click', (e) => {
        if (e.target === discountDialog || e.target.closest('[data-staff-discount-close]')) {
            discountDialog.close();
            return;
        }
        const preset = e.target.closest('[data-preset-discount]');
        if (preset) {
            const input = discountDialog.querySelector('[data-staff-discount-input]');
            if (input) input.value = preset.dataset.presetDiscount;
            return;
        }
        if (e.target.closest('[data-apply-staff-discount]')) {
            const input = discountDialog.querySelector('[data-staff-discount-input]');
            const pct = Math.min(100, Math.max(0, parseInt(input?.value || '0', 10)));
            window.location.href = `index.php?page=payment&table=${encodeURIComponent(tableCode)}&discount_type=percent&discount_value=${pct}`;
        }
    });

    // Voucher dialog
    voucherDialog?.addEventListener('click', async (e) => {
        if (e.target === voucherDialog || e.target.closest('[data-kd-voucher-close]')) {
            voucherDialog.close();
            return;
        }
        const vInput = voucherDialog.querySelector('[data-kd-voucher-input]');
        const vFeedback = voucherDialog.querySelector('[data-kd-voucher-feedback]');

        const pick = e.target.closest('[data-pick-voucher]');
        if (pick && vInput) {
            vInput.value = pick.dataset.pickVoucher;
        }

        if (pick || e.target.closest('[data-kd-check-voucher]')) {
            const code = (vInput?.value || '').trim().toUpperCase();
            if (!code) return;
            try {
                const res = await fetch(`api/validate-voucher.php?code=${encodeURIComponent(code)}&subtotal=99999999`, { cache: 'no-store' });
                const data = await res.json();
                if (vFeedback) {
                    vFeedback.style.color = data.valid ? '#15803d' : '#dc2626';
                    vFeedback.textContent = data.message || '';
                }
            } catch (err) {}
            return;
        }

        if (e.target.closest('[data-kd-go-voucher-pay]')) {
            const code = (vInput?.value || '').trim().toUpperCase();
            if (!code) {
                showMessage('Vui lòng nhập hoặc chọn mã voucher.');
                return;
            }
            window.location.href = `index.php?page=payment&table=${encodeURIComponent(tableCode)}&voucher=${encodeURIComponent(code)}`;
        }
    });

    // Khởi tạo ban đầu
    renderSubCategories();
    filterRightMenu();
    renderDraftPane();

    // Hỗ trợ mở thẳng Kiểm đồ nếu có ?view=kiemdo
    if (new URLSearchParams(window.location.search).get('view') === 'kiemdo') {
        switchScreen('kiemdo');
    }

    setInterval(() => {
        if (document.hidden || busy || document.querySelector('dialog[open]')) return;
        refreshList();
    }, POLL_INTERVAL_MS);
})();

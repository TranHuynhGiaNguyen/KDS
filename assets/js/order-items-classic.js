/**
 * SmartDine - Trang chi tiết bàn: thêm món (admin, phục vụ), hủy món (chỉ admin),
 * và tự cập nhật trạng thái món khi bếp thao tác.
 */
(() => {
    const list = document.querySelector('.order-food-list');
    const addForm = document.querySelector('[data-add-item-form]');

    const apiUrl = 'api/order-item.php';
    const POLL_INTERVAL_MS = 5000;
    let busy = false;

    // "Cơm gà" -> "com ga": tìm món không cần gõ dấu tiếng Việt
    const removeAccents = (text) => text
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/đ/gi, 'd')
        .toLowerCase();

    const formatMoney = (amount) => `${new Intl.NumberFormat('vi-VN').format(amount)}đ`;

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
        Object.entries(fields).forEach(([name, value]) => body.set(name, value));

        try {
            const response = await fetch(apiUrl, { method: 'POST', body });
            const result = await response.json();

            if (!response.ok) {
                showMessage(result.error);
                return null;
            }
            return result;
        } catch (error) {
            showMessage('Không thể kết nối máy chủ. Vui lòng thử lại.');
            return null;
        }
    };

    const refreshList = async () => {
        const currentPosShell = document.querySelector('[data-pos-shell]');

        try {
            const response = await fetch(window.location.href, { cache: 'no-store' });
            if (!response.ok) return false;

            const doc = new DOMParser().parseFromString(await response.text(), 'text/html');

            if (currentPosShell) {
                const leftBill = currentPosShell.querySelector('.pos-left-bill');
                const freshLeftBill = doc.querySelector('.pos-left-bill');
                const bottomBar = currentPosShell.querySelector('.pos-bottom-bar');
                const freshBottomBar = doc.querySelector('.pos-bottom-bar');
                const rightInfo = currentPosShell.querySelector('.pos-info-dark');
                const freshRightInfo = doc.querySelector('.pos-info-dark');

                if (leftBill && freshLeftBill && freshLeftBill.innerHTML !== leftBill.innerHTML) {
                    leftBill.innerHTML = freshLeftBill.innerHTML;
                }
                if (bottomBar && freshBottomBar && freshBottomBar.innerHTML !== bottomBar.innerHTML) {
                    bottomBar.innerHTML = freshBottomBar.innerHTML;
                }
                if (rightInfo && freshRightInfo && freshRightInfo.innerHTML !== rightInfo.innerHTML) {
                    rightInfo.innerHTML = freshRightInfo.innerHTML;
                }
                currentPosShell.dispatchEvent(new CustomEvent('pos:bill-refreshed'));
                return true;
            }

            if (!list) {
                location.reload();
                return true;
            }

            const freshList = doc.querySelector('.order-food-list');
            if (!freshList) {
                location.reload();
                return true;
            }

            if (freshList.innerHTML !== list.innerHTML) {
                list.innerHTML = freshList.innerHTML;
            }
            return true;
        } catch (error) {
            return false;
        }
    };

    if (list) {
        list.addEventListener('click', async (event) => {
            const button = event.target.closest('[data-item-action]');
            if (!button || button.disabled) return;

            const row = button.closest('.order-food');
            const action = button.dataset.itemAction;

            if (action === 'cancel') {
                const confirmed = confirm(`Hủy món "${row.dataset.itemName}"?\nMón sẽ bị loại khỏi hóa đơn và biến mất khỏi màn hình bếp.`);
                if (!confirmed) return;
            }

            busy = true;
            button.disabled = true;
            await callApi(action, { item_id: row.dataset.itemId });
            busy = false;
            button.disabled = false;

            await refreshList();
        });
    }

    const addDialog = document.querySelector('[data-add-item-dialog]');

    if (addForm && addDialog) {
        const dishOptions = [...addForm.querySelectorAll('.dish-option')];
        const dishGroups = [...addForm.querySelectorAll('[data-dish-group]')];
        const emptyNotice = addForm.querySelector('[data-dish-empty]');
        const searchInput = addForm.querySelector('[data-dish-search]');
        const qtyInput = addForm.elements.qty;
        const noteInput = addForm.elements.note;
        const summary = addForm.querySelector('[data-add-summary]');
        const submitButton = addForm.querySelector('[data-add-submit]');
        const chips = [...addForm.querySelectorAll('[data-note-chip]')];
        const maxQty = Number(qtyInput.max) || 20;

        const selectedDish = () => addForm.querySelector('input[name="dish_id"]:checked');
        const clampQty = () => Math.min(maxQty, Math.max(1, Number(qtyInput.value) || 1));

        const updateSummary = () => {
            const dish = selectedDish();
            submitButton.disabled = !dish;

            if (!dish) {
                summary.textContent = 'Chưa chọn món';
                return;
            }

            const qty = clampQty();
            summary.textContent = `${dish.dataset.dishLabel} × ${qty} = ${formatMoney(Number(dish.dataset.dishPrice) * qty)}`;
        };

        const updateChips = () => {
            chips.forEach((chip) => chip.classList.toggle('is-active', noteInput.value.includes(chip.dataset.noteChip)));
        };

        const searchableText = new Map(dishOptions.map((option) => [
            option,
            removeAccents(`${option.dataset.dishName} ${option.closest('[data-dish-group]').querySelector('h4').textContent}`),
        ]));

        const filterDishes = () => {
            const words = removeAccents(searchInput.value).split(/\s+/).filter(Boolean);
            let visible = 0;

            dishOptions.forEach((option) => {
                const text = searchableText.get(option);
                option.hidden = !words.every((word) => text.includes(word));
                if (!option.hidden) visible++;
            });
            dishGroups.forEach((group) => {
                group.hidden = !group.querySelector('.dish-option:not([hidden])');
            });
            emptyNotice.hidden = visible > 0;
        };

        const openAddDialog = () => {
            addForm.reset();
            filterDishes();
            updateSummary();
            updateChips();
            addDialog.showModal();
            searchInput.focus();
        };

        document.addEventListener('click', (event) => {
            if (event.target.closest('[data-open-add-item]')) openAddDialog();
        });

        addDialog.addEventListener('click', (event) => {
            if (event.target === addDialog || event.target.closest('[data-add-close]')) addDialog.close();
        });

        searchInput.addEventListener('input', filterDishes);
        addForm.addEventListener('change', updateSummary);

        addForm.querySelectorAll('[data-qty-step]').forEach((button) => {
            button.addEventListener('click', () => {
                qtyInput.value = Math.min(maxQty, Math.max(1, clampQty() + Number(button.dataset.qtyStep)));
                updateSummary();
            });
        });
        qtyInput.addEventListener('input', updateSummary);

        chips.forEach((chip) => {
            chip.addEventListener('click', () => {
                const text = chip.dataset.noteChip;
                const parts = noteInput.value.split(',').map((part) => part.trim()).filter(Boolean);
                const next = parts.includes(text) ? parts.filter((part) => part !== text) : [...parts, text];

                noteInput.value = next.join(', ');
                updateChips();
            });
        });
        noteInput.addEventListener('input', updateChips);

        addForm.addEventListener('submit', async (event) => {
            event.preventDefault();

            const dish = selectedDish();
            if (!dish) {
                showMessage('Vui lòng chọn món cần thêm.');
                return;
            }

            submitButton.disabled = true;

            const result = await callApi('add', {
                table: addForm.dataset.table,
                dish_id: dish.value,
                qty: clampQty(),
                note: noteInput.value,
            });

            if (result) {
                addDialog.close();
                submitButton.disabled = false;
                await refreshList();
                showMessage(`Đã thêm ${dish.dataset.dishLabel} (×${clampQty()})`);
                return;
            }

            submitButton.disabled = false;
        });
    }

    document.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-refresh-items]');
        if (!button) return;

        const ok = await refreshList();
        showMessage(ok ? 'Đã cập nhật danh sách món' : 'Không cập nhật được, vui lòng thử lại.');
    });

    if (list || document.querySelector('[data-pos-shell]')) {
        setInterval(() => {
            if (document.hidden || busy || document.querySelector('dialog[open]')) return;
            refreshList();
        }, POLL_INTERVAL_MS);
    }

    // ==========================================================
    // TƯƠNG TÁC POS 3 CỘT: CHỌN MÓN BILL + NÚT XÓA NUMPAD & TAP NHIỀU LẦN
    // ==========================================================
    const posShell = document.querySelector('[data-pos-shell]');
    if (posShell) {
        const tableCode = posShell.dataset.table || '';
        const canCancelPage = posShell.dataset.canCancel === '1';
        const leftBillSection = posShell.querySelector('.pos-left-bill');
        const catScreen = posShell.querySelector('[data-pos-screen="categories"]');
        const dishScreen = posShell.querySelector('[data-pos-screen="dishes"]');
        const searchBar = posShell.querySelector('[data-pos-search-bar]');
        const searchInput = posShell.querySelector('[data-pos-search-input]');
        const qtyValueEl = posShell.querySelector('[data-pos-qty-value]');
        const noteInput = posShell.querySelector('[data-pos-note-input]');
        const selectedBanner = posShell.querySelector('[data-pos-selected-banner]');
        const selectedLabel = posShell.querySelector('[data-pos-selected-label]');
        const deleteKeyBtn = posShell.querySelector('[data-pos-delete-key]');
        const maxQty = Number(posShell.dataset.maxQty) || 20;

        // Dialog chọn số lượng cần hủy
        const cancelDialog = document.querySelector('[data-cancel-qty-dialog]');
        const cancelForm = document.querySelector('[data-cancel-qty-form]');
        const cancelTitle = document.querySelector('[data-cancel-item-title]');
        const cancelMaxLabel = document.querySelector('[data-cancel-max-label]');
        const cancelQtyInput = document.querySelector('[data-cancel-qty-input]');
        const cancelQuickRow = document.querySelector('[data-cancel-quick-row]');
        const cancelSummary = document.querySelector('[data-cancel-summary]');
        const cancelSubmitBtn = document.querySelector('[data-cancel-submit]');

        let activeCatKey = null;
        let qtyDigits = '';
        let selectedItem = null; // { id, name, qty, status, canCancel }
        let tapChain = Promise.resolve();

        const getCurrentQty = () => {
            const parsed = parseInt(qtyDigits || '1', 10);
            return Math.min(maxQty, Math.max(1, Number.isNaN(parsed) ? 1 : parsed));
        };

        const renderQty = () => {
            if (qtyValueEl) {
                qtyValueEl.textContent = String(getCurrentQty());
            }
        };

        const syncSelectedUI = () => {
            posShell.querySelectorAll('.pos-bill-items .order-food').forEach((row) => {
                const isMatch = selectedItem && row.dataset.itemId === selectedItem.id;
                row.classList.toggle('is-selected', Boolean(isMatch));
            });

            if (selectedBanner && selectedLabel) {
                if (selectedItem) {
                    selectedBanner.hidden = false;
                    selectedLabel.textContent = `Đang chọn: ${selectedItem.name} (SL: ${selectedItem.qty})`;
                } else {
                    selectedBanner.hidden = true;
                }
            }

            if (deleteKeyBtn) {
                if (selectedItem) {
                    deleteKeyBtn.textContent = 'Xóa món';
                    deleteKeyBtn.classList.add('is-delete-mode');
                } else {
                    deleteKeyBtn.textContent = 'Xóa';
                    deleteKeyBtn.classList.remove('is-delete-mode');
                }
            }
        };

        const clearSelection = () => {
            selectedItem = null;
            syncSelectedUI();
        };

        posShell.addEventListener('pos:bill-refreshed', () => {
            if (selectedItem) {
                const matchingRow = posShell.querySelector(`.pos-bill-items .order-food[data-item-id="${selectedItem.id}"]`);
                if (matchingRow) {
                    selectedItem = {
                        id: matchingRow.dataset.itemId,
                        name: matchingRow.dataset.itemName,
                        qty: parseInt(matchingRow.dataset.itemQty || '1', 10),
                        status: matchingRow.dataset.itemStatus || 'pending',
                        canCancel: matchingRow.dataset.canCancel === '1',
                    };
                } else {
                    selectedItem = null;
                }
            }
            syncSelectedUI();
        });

        const showCategories = () => {
            activeCatKey = null;
            if (catScreen) catScreen.hidden = false;
            if (dishScreen) dishScreen.hidden = true;
            if (searchInput) searchInput.value = '';
        };

        const showCategoryDishes = (catKey) => {
            activeCatKey = catKey;
            if (catScreen) catScreen.hidden = true;
            if (dishScreen) dishScreen.hidden = false;
            posShell.querySelectorAll('[data-pos-dish-tile]').forEach((tile) => {
                tile.hidden = tile.dataset.catKey !== catKey;
            });
        };

        const filterDishesByText = (query) => {
            const words = removeAccents(query).split(/\s+/).filter(Boolean);
            if (!words.length) {
                if (activeCatKey) {
                    showCategoryDishes(activeCatKey);
                } else {
                    showCategories();
                }
                return;
            }
            if (catScreen) catScreen.hidden = true;
            if (dishScreen) dishScreen.hidden = false;
            posShell.querySelectorAll('[data-pos-dish-tile]').forEach((tile) => {
                const nameText = removeAccents(tile.dataset.dishName || '');
                tile.hidden = !words.every((w) => nameText.includes(w));
            });
        };

        // Ủy quyền sự kiện trên cột bill (cuộn lên/xuống & chọn dòng món để xóa)
        leftBillSection?.addEventListener('click', (event) => {
            if (event.target.closest('[data-pos-scroll="up"]')) {
                const box = posShell.querySelector('[data-pos-bill-scroll]');
                if (box) box.scrollBy({ top: -140, behavior: 'smooth' });
                return;
            }
            if (event.target.closest('[data-pos-scroll="down"]')) {
                const box = posShell.querySelector('[data-pos-bill-scroll]');
                if (box) box.scrollBy({ top: 140, behavior: 'smooth' });
                return;
            }

            const row = event.target.closest('.pos-bill-items .order-food');
            if (!row) return;

            if (selectedItem && selectedItem.id === row.dataset.itemId) {
                clearSelection();
            } else {
                selectedItem = {
                    id: row.dataset.itemId,
                    name: row.dataset.itemName,
                    qty: parseInt(row.dataset.itemQty || '1', 10),
                    status: row.dataset.itemStatus || 'pending',
                    canCancel: row.dataset.canCancel === '1',
                };
                syncSelectedUI();
            }
        });

        posShell.querySelector('[data-pos-clear-select]')?.addEventListener('click', clearSelection);
        posShell.querySelector('[data-pos-home]')?.addEventListener('click', showCategories);
        posShell.querySelector('[data-pos-back-cats]')?.addEventListener('click', showCategories);

        posShell.querySelector('[data-pos-toggle-search]')?.addEventListener('click', () => {
            if (!searchBar) return;
            searchBar.classList.toggle('open');
            if (searchBar.classList.contains('open') && searchInput) {
                searchInput.focus();
            } else if (searchInput) {
                searchInput.value = '';
                showCategories();
            }
        });

        searchInput?.addEventListener('input', () => filterDishesByText(searchInput.value));

        posShell.querySelectorAll('[data-pos-cat-tile]').forEach((btn) => {
            btn.addEventListener('click', () => showCategoryDishes(btn.dataset.posCatTile));
        });

        const updateCancelDialogState = () => {
            if (!selectedItem || !cancelQtyInput) return;
            const max = Math.max(1, selectedItem.qty);
            const val = Math.min(max, Math.max(1, parseInt(cancelQtyInput.value || '1', 10)));
            cancelQtyInput.value = String(val);
            if (cancelSummary) {
                cancelSummary.textContent = val >= max
                    ? `Sẽ hủy toàn bộ ${max} phần "${selectedItem.name}"`
                    : `Sẽ hủy ${val}/${max} phần (còn lại ${max - val} phần)`;
            }
            if (cancelQuickRow) {
                cancelQuickRow.querySelectorAll('.pos-cancel-quick-btn').forEach((b) => {
                    b.classList.toggle('active', Number(b.dataset.quickCancelQty) === val);
                });
            }
        };

        const openCancelDialogForSelected = () => {
            if (!selectedItem || !cancelDialog) return;

            if (!canCancelPage) {
                showMessage('Chỉ Quản trị viên mới được hủy món. Cần hủy món, vui lòng báo quản lý.');
                return;
            }
            if (!selectedItem.canCancel) {
                showMessage('Món này đã phục vụ hoặc không thể hủy.');
                return;
            }

            const max = Math.max(1, selectedItem.qty);
            const prefill = qtyDigits !== '' ? Math.min(max, Math.max(1, parseInt(qtyDigits, 10))) : 1;

            if (cancelTitle) cancelTitle.textContent = `${selectedItem.name} · Hiện có ${max} phần`;
            if (cancelMaxLabel) cancelMaxLabel.textContent = String(max);
            if (cancelQtyInput) {
                cancelQtyInput.max = String(max);
                cancelQtyInput.value = String(prefill);
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
        };

        cancelDialog?.addEventListener('click', (event) => {
            if (event.target === cancelDialog || event.target.closest('[data-cancel-close]')) {
                cancelDialog.close();
            }
            const quickBtn = event.target.closest('[data-quick-cancel-qty]');
            if (quickBtn && cancelQtyInput) {
                cancelQtyInput.value = quickBtn.dataset.quickCancelQty;
                updateCancelDialogState();
            }
            const stepBtn = event.target.closest('[data-cancel-step]');
            if (stepBtn && cancelQtyInput && selectedItem) {
                const max = Math.max(1, selectedItem.qty);
                const cur = parseInt(cancelQtyInput.value || '1', 10);
                cancelQtyInput.value = String(Math.min(max, Math.max(1, cur + Number(stepBtn.dataset.cancelStep))));
                updateCancelDialogState();
            }
        });

        cancelQtyInput?.addEventListener('input', updateCancelDialogState);

        cancelForm?.addEventListener('submit', async (event) => {
            event.preventDefault();
            if (!selectedItem || !cancelQtyInput) return;

            const qtyToCancel = Math.min(selectedItem.qty, Math.max(1, parseInt(cancelQtyInput.value || '1', 10)));
            const targetName = selectedItem.name;

            if (cancelSubmitBtn) cancelSubmitBtn.disabled = true;
            busy = true;
            const res = await callApi('cancel', {
                item_id: selectedItem.id,
                qty: qtyToCancel,
            });
            busy = false;
            if (cancelSubmitBtn) cancelSubmitBtn.disabled = false;

            if (res) {
                cancelDialog.close();
                qtyDigits = '';
                renderQty();
                clearSelection();
                await refreshList();
                showMessage(`Đã hủy ${qtyToCancel} phần "${targetName}"`);
            }
        });

        // Bàn phím số: vừa nhập số lượng gọi món, vừa kích hoạt Hủy món khi đang chọn món trên bill
        posShell.querySelectorAll('[data-pos-num]').forEach((btn) => {
            btn.addEventListener('click', () => {
                const val = btn.dataset.posNum;
                if (val === 'clear') {
                    if (selectedItem) {
                        openCancelDialogForSelected();
                        return;
                    }
                    qtyDigits = '';
                } else {
                    const nextStr = (qtyDigits + val).slice(0, 2);
                    const nextNum = parseInt(nextStr, 10);
                    qtyDigits = nextNum > maxQty ? String(maxQty) : (nextNum > 0 ? String(nextNum) : '');
                }
                renderQty();
            });
        });

        posShell.querySelectorAll('[data-pos-note-chip]').forEach((chip) => {
            chip.addEventListener('click', () => {
                if (!noteInput) return;
                const txt = chip.dataset.posNoteChip;
                const parts = noteInput.value.split(',').map((s) => s.trim()).filter(Boolean);
                const exists = parts.includes(txt);
                noteInput.value = (exists ? parts.filter((s) => s !== txt) : [...parts, txt]).join(', ');
                chip.classList.toggle('active', !exists);
            });
        });

        // Tap nhiều lần liên tục vào ô món -> xếp hàng gọi tuần tự, KHÔNG thoát khỏi danh mục hiện tại
        posShell.querySelectorAll('[data-pos-dish-id]').forEach((btn) => {
            btn.addEventListener('click', () => {
                if (btn.disabled) return;
                const dishId = btn.dataset.posDishId;
                const dishLabel = btn.dataset.dishLabel || 'Món';
                const qty = getCurrentQty();
                const note = noteInput ? noteInput.value.trim() : '';

                qtyDigits = '';
                renderQty();

                tapChain = tapChain.then(async () => {
                    busy = true;
                    const result = await callApi('add', {
                        table: tableCode,
                        dish_id: dishId,
                        qty,
                        note,
                    });
                    busy = false;
                    if (result) {
                        await refreshList();
                        showMessage(`Đã thêm ${dishLabel} (×${qty})`);
                    }
                });
            });
        });
    }

    document.addEventListener('click', async (event) => {
        const dismissBtn = event.target.closest('[data-dismiss-call]');
        if (!dismissBtn) return;
        const tbl = dismissBtn.dataset.dismissCall || '';
        if (!tbl) return;
        const fd = new FormData();
        fd.set('action', 'resolve_call');
        fd.set('table_code', tbl);
        try {
            const r = await fetch('api/table-action.php', { method: 'POST', body: fd });
            if (r.ok) {
                dismissBtn.closest('[data-pos-call-banner]')?.remove();
                showMessage('Đã xác nhận tiếp nhận yêu cầu Bàn ' + tbl);
            }
        } catch (e) {}
    });
})();

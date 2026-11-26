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
        if (!list) {
            location.reload();
            return true;
        }

        try {
            const response = await fetch(window.location.href, { cache: 'no-store' });
            if (!response.ok) return false;

            const doc = new DOMParser().parseFromString(await response.text(), 'text/html');
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
                location.reload();
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

    if (list) {
        setInterval(() => {
            if (document.hidden || busy || document.querySelector('dialog[open]')) return;
            refreshList();
        }, POLL_INTERVAL_MS);
    }
})();
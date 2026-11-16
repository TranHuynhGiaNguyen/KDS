/**
 * SmartDine - Trang chi tiết bàn của nhân viên: hủy món (khi bếp chưa in phiếu),
 * in phiếu tạm tính và tự cập nhật trạng thái món khi bếp thao tác.
 */
(() => {
    const list = document.querySelector('.order-food-list');
    if (!list) return;

    const apiUrl = 'api/order-item.php';
    const POLL_INTERVAL_MS = 5000;
    let busy = false;

    const provisionalDialog = document.createElement('dialog');
    provisionalDialog.className = 'receipt-dialog';
    provisionalDialog.innerHTML = `
    <div class="receipt-dialog-body" data-receipt></div>
    <div class="bill-ticket-actions">
      <button class="btn btn-primary" type="button" data-receipt-print>In tạm tính</button>
      <button class="btn btn-outline" type="button" data-receipt-close>Đóng</button>
    </div>`;
    document.body.append(provisionalDialog);

    const showMessage = (message) => {
        if (typeof notify === 'function') {
            notify(message);
        } else {
            alert(message);
        }
    };

    const callApi = async (action, itemId) => {
        const body = new FormData();
        body.set('action', action);
        body.set('item_id', itemId);

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

            const summary = document.querySelector('.bill-summary-box');
            const freshSummary = doc.querySelector('.bill-summary-box');
            if (summary && freshSummary && freshSummary.innerHTML !== summary.innerHTML) {
                summary.innerHTML = freshSummary.innerHTML;
            }
            return true;
        } catch (error) {
            return false;
        }
    };

    list.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-item-action]');
        if (!button || button.disabled) return;

        const row = button.closest('.order-food');
        const action = button.dataset.itemAction;
        const itemId = row.dataset.itemId;

        if (action === 'cancel') {
            const confirmed = confirm(`Hủy món "${row.dataset.itemName}"?\nMón sẽ bị loại khỏi hóa đơn và biến mất khỏi màn hình bếp.`);
            if (!confirmed) return;
        }

        busy = true;
        button.disabled = true;
        await callApi(action, itemId);
        busy = false;
        button.disabled = false;

        await refreshList();
    });

    document.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-refresh-items]');
        if (!button) return;

        const ok = await refreshList();
        showMessage(ok ? 'Đã cập nhật danh sách món' : 'Không cập nhật được, vui lòng thử lại.');
    });

    document.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-print-provisional]');
        if (!button) return;

        button.disabled = true;

        try {
            const response = await fetch(`api/provisional-bill.php?table=${encodeURIComponent(button.dataset.table)}`);
            const result = await response.json();

            if (!response.ok) {
                showMessage(result.error);
                return;
            }

            provisionalDialog.querySelector('[data-receipt]').innerHTML = result.html;
            provisionalDialog.showModal();
        } catch (error) {
            showMessage('Không thể kết nối máy chủ. Vui lòng thử lại.');
        } finally {
            button.disabled = false;
        }
    });

    provisionalDialog.addEventListener('click', (event) => {
        if (event.target.closest('[data-receipt-print]')) window.print();
        if (event.target.closest('[data-receipt-close]')) provisionalDialog.close();
    });

    setInterval(() => {
        if (document.hidden || busy || document.querySelector('dialog[open]')) return;
        refreshList();
    }, POLL_INTERVAL_MS);
})();
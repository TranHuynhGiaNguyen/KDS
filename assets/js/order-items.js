(() => {
    const list = document.querySelector('.order-food-list');
    if (!list) return;

    const apiUrl = 'api/order-item.php';
    const dialog = document.createElement('dialog');
    dialog.className = 'bill-ticket-dialog';
    dialog.innerHTML = `
    <div class="bill-ticket">
      <p data-ticket="staff"></p>
      <p data-ticket="table"></p>
      <p data-ticket="dish"></p>
    </div>
    <div class="bill-ticket-actions">
      <button class="btn btn-primary" type="button" data-ticket-print>In bill</button>
      <button class="btn btn-outline" type="button" data-ticket-close>Đóng</button>
    </div>`;
    document.body.append(dialog);

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

    const showTicket = (ticket) => {
        dialog.querySelector('[data-ticket="staff"]').textContent = `Nhân viên: ${ticket.staff}`;
        dialog.querySelector('[data-ticket="table"]').textContent = `Bàn ${ticket.table} - ${ticket.time}`;
        dialog.querySelector('[data-ticket="dish"]').textContent = `${ticket.name} - ${ticket.qty}`;
        dialog.showModal();
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

    list.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-item-action]');
        if (!button) return;

        const row = button.closest('.order-food');
        const action = button.dataset.itemAction;
        const itemId = row.dataset.itemId;

        if (action === 'cancel') {
            const confirmed = confirm(`Hủy món "${row.dataset.itemName}"?\nMón sẽ bị loại khỏi hóa đơn và bếp không làm nữa.`);
            if (!confirmed) return;
        }

        button.disabled = true;
        const result = await callApi(action, itemId);
        button.disabled = false;

        if (!result) return;

        if (action === 'send' || action === 'cancel' || action === 'serve') {
            location.reload();
            return;
        }

        showTicket(result.ticket);
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

    dialog.addEventListener('click', (event) => {
        if (event.target.closest('[data-ticket-print]')) window.print();
        if (event.target.closest('[data-ticket-close]')) dialog.close();
    });

})();
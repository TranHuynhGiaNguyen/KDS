(() => {
    if (!document.querySelector('.bills-panel')) return;

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

    const showMessage = (message) => {
        if (typeof notify === 'function') {
            notify(message);
        } else {
            alert(message);
        }
    };

    document.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-bill-print]');
        if (!button) return;

        button.disabled = true;

        try {
            const response = await fetch(`api/bill-ticket.php?item_id=${encodeURIComponent(button.dataset.billPrint)}`);
            const result = await response.json();

            if (!response.ok) {
                showMessage(result.error);
                return;
            }

            const ticket = result.ticket;
            dialog.querySelector('[data-ticket="staff"]').textContent = `Nhân viên: ${ticket.staff}`;
            dialog.querySelector('[data-ticket="table"]').textContent = `Bàn ${ticket.table} - ${ticket.time}`;
            dialog.querySelector('[data-ticket="dish"]').textContent = `${ticket.name} - ${ticket.qty}`;
            dialog.showModal();
        } catch (error) {
            showMessage('Không thể kết nối máy chủ. Vui lòng thử lại.');
        } finally {
            button.disabled = false;
        }
    });

    dialog.addEventListener('click', (event) => {
        if (event.target.closest('[data-ticket-print]')) window.print();
        if (event.target.closest('[data-ticket-close]')) dialog.close();
    });
})();
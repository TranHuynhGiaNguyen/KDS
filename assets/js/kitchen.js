(() => {
    const grid = document.querySelector('#kds-grid');
    if (!grid) return;

    const pollUrl = grid.dataset.pollUrl;
    const actionUrl = grid.dataset.actionUrl;
    const POLL_INTERVAL_MS = 5000;
    const stationSelect = document.querySelector('#kds-station-select');
    const sidebarCounter = document.querySelector('.kitchen-counter');

    let knownPendingIds = new Set(
        [...grid.querySelectorAll('[data-kds-item]')].map((row) => row.dataset.itemId)
    );

    const ticketDialog = document.createElement('dialog');
    ticketDialog.className = 'bill-ticket-dialog';
    ticketDialog.innerHTML = `
    <div class="bill-ticket">
      <p data-ticket="staff"></p>
      <p data-ticket="table"></p>
      <p data-ticket="dish"></p>
    </div>
    <div class="bill-ticket-actions">
      <button class="btn btn-primary" type="button" data-ticket-print>In lại</button>
      <button class="btn btn-outline" type="button" data-ticket-close>Đóng</button>
    </div>`;
    document.body.append(ticketDialog);

    const showMessage = (message) => {
        if (typeof notify === 'function') notify(message);
    };

    const applyFilters = () => {
        const station = stationSelect ? stationSelect.value : 'all';
        let visibleCards = 0;

        grid.querySelectorAll('[data-kds-table]').forEach((card) => {
            let visibleRows = 0;
            card.querySelectorAll('.kds-item').forEach((row) => {
                row.hidden = !(station === 'all' || row.dataset.station === station);
                if (!row.hidden) visibleRows++;
            });
            card.hidden = visibleRows === 0;
            if (!card.hidden) visibleCards++;
        });

        const emptyNotice = grid.querySelector('.kds-empty-filter');
        if (visibleCards === 0 && grid.querySelector('[data-kds-table]')) {
            if (!emptyNotice) {
                grid.insertAdjacentHTML('beforeend', '<p class="kds-empty kds-empty-filter">Không có món nào khớp bộ lọc.</p>');
            }
        } else if (emptyNotice) {
            emptyNotice.remove();
        }
    };

    const updateCounts = (counts) => {
        document.querySelectorAll('[data-kds-count]').forEach((badge) => {
            badge.textContent = counts[badge.dataset.kdsCount];
        });
        if (sidebarCounter) sidebarCounter.textContent = counts.pending;
    };

    const alertOnNewItems = (pendingIds) => {
        const incoming = pendingIds.map(String);
        const hasNew = incoming.some((id) => !knownPendingIds.has(id));
        knownPendingIds = new Set(incoming);

        if (hasNew) {
            if (typeof playKitchenBeep === 'function') playKitchenBeep();
            showMessage('Có món mới gửi xuống bếp');
        }
    };

    const refresh = async () => {
        try {
            const response = await fetch(pollUrl, { headers: { Accept: 'application/json' } });
            const result = await response.json();

            if (!response.ok) {
                showMessage(result.error);
                return;
            }

            grid.innerHTML = result.html;
            updateCounts(result.counts);
            alertOnNewItems(result.pending_ids);
            applyFilters();
        } catch (error) {
            showMessage('Mất kết nối với máy chủ, đang thử lại...');
        }
    };

    const printTicket = (ticket) => {
        ticketDialog.querySelector('[data-ticket="staff"]').textContent = `Nhân viên: ${ticket.staff}`;
        ticketDialog.querySelector('[data-ticket="table"]').textContent = `Bàn ${ticket.table} - ${ticket.time}`;
        ticketDialog.querySelector('[data-ticket="dish"]').textContent = `${ticket.name} - ${ticket.qty}`;
        ticketDialog.showModal();

        window.addEventListener('afterprint', () => ticketDialog.close(), { once: true });
        window.print();
    };

    const takeItem = async (row) => {
        const body = new FormData();
        body.set('action', 'start');
        body.set('item_id', row.dataset.itemId);

        row.disabled = true;

        try {
            const response = await fetch(actionUrl, { method: 'POST', body });
            const result = await response.json();

            if (!response.ok) {
                showMessage(result.error);
            } else if (result.ticket) {
                row.hidden = true;
                printTicket(result.ticket);
            }
        } catch (error) {
            showMessage('Không thể kết nối máy chủ. Vui lòng thử lại.');
        }

        await refresh();
    };

    grid.addEventListener('click', (event) => {
        const row = event.target.closest('[data-kds-item]');
        if (row) takeItem(row);
    });

    ticketDialog.addEventListener('click', (event) => {
        if (event.target.closest('[data-ticket-print]')) window.print();
        if (event.target.closest('[data-ticket-close]')) ticketDialog.close();
    });

    document.addEventListener('click', (event) => {
        if (event.target.closest('[data-kds-refresh]')) {
            refresh().then(() => showMessage('Đã cập nhật danh sách món mới nhất'));
        }
    });

    if (stationSelect) stationSelect.addEventListener('change', applyFilters);

    setInterval(() => {
        if (!document.hidden) refresh();
    }, POLL_INTERVAL_MS);
})();
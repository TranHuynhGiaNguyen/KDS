(() => {
    const grid = document.querySelector('#kds-grid');
    if (!grid) return;

    const pollUrl = grid.dataset.pollUrl;
    const actionUrl = grid.dataset.actionUrl;
    const POLL_INTERVAL_MS = 5000;
    const stationSelect = document.querySelector('#kds-station-select');
    const sidebarCounter = document.querySelector('.kitchen-counter');

    let statusFilter = 'all';
    let knownPendingIds = new Set(
        [...grid.querySelectorAll('[data-kds-card="pending"]')].map((card) => card.dataset.itemId)
    );

    const showMessage = (message) => {
        if (typeof notify === 'function') notify(message);
    };

    const applyFilters = () => {
        const station = stationSelect ? stationSelect.value : 'all';
        let visible = 0;

        grid.querySelectorAll('[data-kds-card]').forEach((card) => {
            const matchStatus = statusFilter === 'all' || card.dataset.kdsCard === statusFilter;
            const matchStation = station === 'all' || card.dataset.station === station;
            card.hidden = !(matchStatus && matchStation);
            if (!card.hidden) visible++;
        });

        const emptyNotice = grid.querySelector('.kds-empty-filter');
        if (visible === 0 && grid.querySelector('[data-kds-card]')) {
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
        if (sidebarCounter) sidebarCounter.textContent = counts.pending + counts.cooking;
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

    const changeStatus = async (button) => {
        const card = button.closest('[data-kds-card]');
        const body = new FormData();
        body.set('action', button.dataset.kdsAction);
        body.set('item_id', card.dataset.itemId);

        button.disabled = true;

        try {
            const response = await fetch(actionUrl, { method: 'POST', body });
            const result = await response.json();

            if (!response.ok) showMessage(result.error);
        } catch (error) {
            showMessage('Không thể kết nối máy chủ. Vui lòng thử lại.');
        }

        await refresh();
    };

    grid.addEventListener('click', (event) => {
        const button = event.target.closest('[data-kds-action]');
        if (button) changeStatus(button);
    });

    document.addEventListener('click', (event) => {
        const filter = event.target.closest('[data-kds-filter]');
        if (filter) {
            statusFilter = filter.dataset.kdsFilter;
            document.querySelectorAll('[data-kds-filter]').forEach((tab) => {
                tab.classList.toggle('active', tab === filter);
            });
            applyFilters();
        }

        if (event.target.closest('[data-kds-refresh]')) {
            refresh().then(() => showMessage('Đã cập nhật danh sách món mới nhất'));
        }
    });

    if (stationSelect) stationSelect.addEventListener('change', applyFilters);

    setInterval(() => {
        if (!document.hidden) refresh();
    }, POLL_INTERVAL_MS);
})();
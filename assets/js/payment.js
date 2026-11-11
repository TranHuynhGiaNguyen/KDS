(() => {
    document.addEventListener('click', (event) => {
        if (event.target.closest('[data-print-invoice]')) window.print();
    });

    const form = document.querySelector('[data-payment-form]');
    if (!form) return;

    const alerts = document.querySelector('#payment-alerts');
    const summary = document.querySelector('#bill-summary');
    const qrBox = form.querySelector('[data-qr-box]');
    const cashInput = form.querySelector('[name="cash_received"]');
    const changeBox = form.querySelector('[data-cash-change]');
    const changeLabel = form.querySelector('[data-cash-change-label]');
    const changeValue = form.querySelector('[data-cash-change-value]');
    const applyButton = form.querySelector('[data-apply-discount]');
    const discountInputs = form.querySelectorAll('[name="discount_type"], [name="discount_value"]');

    const showError = (message) => {
        alerts.replaceChildren();
        if (!message) return;

        const alert = document.createElement('div');
        alert.className = 'flash-alert flash-error';
        const text = document.createElement('span');
        text.textContent = message;
        alert.append(text);
        alerts.append(alert);
    };

    const formatMoney = (amount) => `${new Intl.NumberFormat('vi-VN').format(amount)}đ`;

    const updateChange = () => {
        if (cashInput.value === '') {
            changeBox.hidden = true;
            return;
        }

        const difference = Number(cashInput.value) - Number(form.dataset.total);
        changeBox.hidden = false;
        changeBox.classList.toggle('is-short', difference < 0);
        changeLabel.textContent = difference < 0 ? 'Còn thiếu' : 'Tiền thừa trả khách';
        changeValue.textContent = formatMoney(Math.abs(difference));
    };

    const renderQr = (payload) => {
        if (!qrBox) return;

        if (!payload) {
            qrBox.replaceChildren();
            return;
        }

        const qr = qrcode(0, 'M');
        qr.addData(payload);
        qr.make();
        qrBox.innerHTML = qr.createSvgTag({ cellSize: 5, margin: 2, scalable: true });
    };

    renderQr(form.dataset.qrPayload);

    const applyDiscount = async () => {
        const body = new FormData();
        body.set('table', form.dataset.table);
        discountInputs.forEach((input) => body.set(input.name, input.value));

        try {
            const response = await fetch(form.dataset.calculateUrl, { method: 'POST', body });
            const result = await response.json();

            if (!response.ok) {
                showError(result.error);
                return;
            }

            showError('');
            summary.innerHTML = result.summary;
            form.dataset.total = result.total_raw;
            updateChange();
            renderQr(result.qr_payload);
            document.querySelectorAll('[data-bill-total]').forEach((element) => {
                element.textContent = result.total;
            });
        } catch (error) {
            showError('Không thể kết nối máy chủ. Vui lòng thử lại.');
        }
    };

    applyButton.addEventListener('click', applyDiscount);
    cashInput.addEventListener('input', updateChange);
    updateChange();

    discountInputs.forEach((input) => {
        input.addEventListener('keydown', (event) => {
            if (event.key !== 'Enter') return;
            event.preventDefault();
            applyDiscount();
        });
    });
})();
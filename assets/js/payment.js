(() => {
    document.addEventListener('click', (event) => {
        if (event.target.closest('[data-print-invoice]')) window.print();
    });

    // Mã QR mời khách đánh giá phục vụ (màn hình thanh toán thành công và hóa đơn in)
    document.querySelectorAll('[data-rate-qr]').forEach((box) => {
        const qr = qrcode(0, 'M');
        qr.addData(box.dataset.rateQr);
        qr.make();
        box.innerHTML = qr.createSvgTag({ cellSize: 4, margin: 1, scalable: true });
    });

    const form = document.querySelector('[data-payment-form]');
    if (!form) return;

    const alerts = document.querySelector('#payment-alerts');
    const summary = document.querySelector('#bill-summary');
    const qrBox = form.querySelector('[data-qr-box]');
    const qrStatus = form.querySelector('[data-qr-status]');
    const qrContentEl = form.querySelector('[data-qr-content]');
    const cashInput = form.querySelector('[name="cash_received"]');
    const changeBox = form.querySelector('[data-cash-change]');
    const changeLabel = form.querySelector('[data-cash-change-label]');
    const changeValue = form.querySelector('[data-cash-change-value]');
    const applyButton = form.querySelector('[data-apply-discount]');
    const discountInputs = form.querySelectorAll('[name="discount_type"], [name="discount_value"], [name="voucher_code"]');
    const voucherSelect = form.querySelector('#select-active-voucher');
    const voucherInput = form.querySelector('#input-payment-voucher');
    const ratingLabel = form.querySelector('[data-pay-rating-label]');
    const ratingRadios = form.querySelectorAll('input[name="rating"]');
    const payRadios = form.querySelectorAll('input[name="pay"]');
    const payButton = form.querySelector('[data-pay-button]');
    const payBtnCashLabel = form.querySelector('[data-pay-btn-cash]');
    const payBtnQrLabel = form.querySelector('[data-pay-btn-qr]');

    const syncPayMethodState = () => {
        const selected = form.querySelector('input[name="pay"]:checked')?.value || 'cash';
        const isQr = selected === 'qr';
        if (payButton) payButton.disabled = isQr;
        if (payBtnCashLabel) payBtnCashLabel.hidden = isQr;
        if (payBtnQrLabel) payBtnQrLabel.hidden = !isQr;
    };

    payRadios.forEach((radio) => {
        radio.addEventListener('change', syncPayMethodState);
    });
    syncPayMethodState();

    form.addEventListener('submit', (event) => {
        const selected = form.querySelector('input[name="pay"]:checked')?.value || 'cash';
        if (selected === 'qr') {
            event.preventDefault();
            showError('Chuyển khoản QR chỉ tự động đóng bàn khi Webhook xác nhận tiền về tài khoản. Chọn Tiền mặt nếu thu tiền mặt.');
        }
    });

    if (ratingLabel && ratingRadios.length > 0) {
        ratingRadios.forEach((radio) => {
            radio.addEventListener('change', () => {
                ratingLabel.textContent = radio.dataset.ratingText || '';
            });
        });
    }

    if (voucherSelect && voucherInput) {
        voucherSelect.addEventListener('change', () => {
            if (voucherSelect.value) {
                voucherInput.value = voucherSelect.value;
                applyDiscount();
            }
        });
    }

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
            qrBox.hidden = true;
            qrBox.replaceChildren();
            if (qrStatus) qrStatus.hidden = true;
            return;
        }

        qrBox.hidden = false;
        if (qrStatus) qrStatus.hidden = false;
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
            if (qrContentEl && result.transfer_content) {
                qrContentEl.textContent = result.transfer_content;
            }
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

    // Tự động kiểm tra trạng thái thanh toán (webhook ngân hàng hoặc xác nhận từ thiết bị khác)
    const statusUrl = form.dataset.statusUrl || '';
    const orderId = Number(form.dataset.orderId || 0);
    let paymentCompleted = false;

    const pollPaymentStatus = async () => {
        if (paymentCompleted || !statusUrl || orderId <= 0 || document.hidden) return;

        try {
            const url = `${statusUrl}?table=${encodeURIComponent(form.dataset.table || '')}&order_id=${orderId}`;
            const response = await fetch(url, { cache: 'no-store' });
            if (!response.ok) return;

            const status = await response.json();
            if (status && status.paid) {
                paymentCompleted = true;
                if (qrStatus) {
                    qrStatus.textContent = 'Đã nhận chuyển khoản thành công! Đang chuyển sang hóa đơn...';
                    qrStatus.classList.remove('is-error');
                    qrStatus.classList.add('is-paid');
                }
                const targetUrl = status.redirect_url
                    || `index.php?page=payment&table=${encodeURIComponent(form.dataset.table || '')}&invoice=${encodeURIComponent(status.invoice_id || 0)}`;
                setTimeout(() => {
                    window.location.href = targetUrl;
                }, 600);
            }
        } catch (e) {
            // Bỏ qua lỗi mạng tạm thời khi polling
        }
    };

    if (statusUrl && orderId > 0) {
        setInterval(pollPaymentStatus, 2500);
    }
})();
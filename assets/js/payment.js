(() => {
    const form = document.querySelector('[data-payment-form]');
    if (!form) return;

    const alerts = document.querySelector('#payment-alerts');
    const summary = document.querySelector('#bill-summary');
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
            document.querySelectorAll('[data-bill-total]').forEach((element) => {
                element.textContent = result.total;
            });
        } catch (error) {
            showError('Không thể kết nối máy chủ. Vui lòng thử lại.');
        }
    };

    applyButton.addEventListener('click', applyDiscount);

    discountInputs.forEach((input) => {
        input.addEventListener('keydown', (event) => {
            if (event.key !== 'Enter') return;
            event.preventDefault();
            applyDiscount();
        });
    });
})();
(() => {
  const form = document.querySelector('#reservation-form');
  const historyForm = document.querySelector('#reservation-history-form');
  const csrfToken = document.querySelector('[name="csrf_token"]')?.value || '';
  const reservationStatusLabels = {
    pending: 'Chờ xác nhận',
    confirmed: 'Đã xác nhận',
    seated: 'Đang phục vụ',
    completed: 'Hoàn thành',
    cancelled: 'Đã hủy',
    no_show: 'Vắng mặt'
  };

  async function sendRequest(url, method, data) {
    const options = { method, headers: { Accept: 'application/json' } };
    if (method === 'POST') {
      options.headers['Content-Type'] = 'application/json';
      options.body = JSON.stringify({ ...data, csrf_token: csrfToken });
    }

    const response = await fetch(url, options);
    const result = await response.json();
    if (!response.ok || !result.success) {
      throw new Error(result.message || 'Không thể xử lý yêu cầu. Vui lòng thử lại.');
    }
    return result;
  }

  if (form) {
    const tableList = document.querySelector('#reservation-tables');
    const submitButton = document.querySelector('#reservation-submit');
    const message = document.querySelector('#reservation-message');
    const fields = {
      date: document.querySelector('#reservation-date'),
      time: document.querySelector('#reservation-time'),
      guests: document.querySelector('#reservation-guests'),
      area: document.querySelector('#reservation-area')
    };
    let selectedTable = null;
    let requestNumber = 0;

    function showReservationMessage(text, kind) {
      message.textContent = text;
      message.className = `reservation-message ${kind || ''}`;
    }

    function updatePastTimeOptions() {
      const isToday = fields.date.value === fields.date.dataset.today;
      const currentTime = fields.date.dataset.currentTime || '';
      let selectedTimeIsPast = false;

      Array.from(fields.time.options).forEach(option => {
        const isPast = isToday && option.value !== '' && option.value <= currentTime;
        option.disabled = isPast;
        if (isPast && option.selected) selectedTimeIsPast = true;
      });

      if (selectedTimeIsPast) fields.time.value = '';
    }

    async function loadAvailableTables() {
      selectedTable = null;
      submitButton.disabled = true;
      const requestId = ++requestNumber;
      showReservationMessage('', '');
      if (!fields.date.value || !fields.time.value) {
        tableList.innerHTML = '<p class="reservation-picker-empty">Chọn ngày và giờ để xem bàn còn trống.</p>';
        return;
      }

      tableList.innerHTML = '<p class="reservation-picker-empty">Đang kiểm tra bàn trống…</p>';
      const params = new URLSearchParams({
        action: 'availability',
        date: fields.date.value,
        time: fields.time.value,
        guests: fields.guests.value,
        area: fields.area.value
      });

      try {
        const result = await sendRequest(`${form.dataset.api}?${params}`, 'GET');
        if (requestId !== requestNumber) return;
        if (!result.tables.length) {
          tableList.innerHTML = '<p class="reservation-picker-empty">Không còn bàn phù hợp trong khung giờ này. Vui lòng chọn giờ hoặc khu vực khác.</p>';
          return;
        }

        tableList.replaceChildren(...result.tables.map(table => {
          const button = document.createElement('button');
          button.type = 'button';
          button.className = `reservation-table${table.shape === 'round' ? ' table-round' : ''}`;
          button.dataset.tableId = table.id;
          button.setAttribute('aria-pressed', 'false');
          button.innerHTML = `<span class="reservation-table-icon" aria-hidden="true"><svg viewBox="0 0 48 48" fill="none"><path d="M24 13v6M20 19h8v8h-8zM15 19v8M33 19v8M20 27v6M28 27v6M17 33h14" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg></span><b></b><small></small>`;
          button.querySelector('b').textContent = table.code;
          button.querySelector('small').textContent = `${table.area_name} · ${table.capacity} chỗ`;
          button.addEventListener('click', () => {
            tableList.querySelectorAll('.reservation-table').forEach(item => {
              item.classList.remove('selected');
              item.setAttribute('aria-pressed', 'false');
            });
            button.classList.add('selected');
            button.setAttribute('aria-pressed', 'true');
            selectedTable = table;
            submitButton.disabled = false;
          });
          return button;
        }));
      } catch (error) {
        if (requestId !== requestNumber) return;
        tableList.innerHTML = '<p class="reservation-picker-empty reservation-error">Không thể kiểm tra bàn. Vui lòng thử lại.</p>';
        showReservationMessage(error.message, 'error');
      }
    }

    fields.date.addEventListener('change', () => {
      updatePastTimeOptions();
      loadAvailableTables();
    });
    fields.time.addEventListener('change', loadAvailableTables);
    [fields.guests, fields.area].forEach(field => field.addEventListener('change', loadAvailableTables));
    updatePastTimeOptions();

    form.addEventListener('submit', async event => {
      event.preventDefault();
      if (!selectedTable) {
        showReservationMessage('Vui lòng chọn một bàn còn trống.', 'error');
        return;
      }

      submitButton.disabled = true;
      submitButton.textContent = 'Đang xác nhận…';
      showReservationMessage('', '');
      const data = Object.fromEntries(new FormData(form).entries());
      data.table_id = selectedTable.id;

      try {
        const result = await sendRequest(form.dataset.api, 'POST', { ...data, action: 'create' });
        showReservationMessage('Đặt bàn thành công! Mã xác nhận của bạn:', 'success');
        const confirmation = document.createElement('strong');
        confirmation.className = 'reservation-confirmation-code';
        confirmation.textContent = result.reservation.reference;
        message.appendChild(document.createElement('br'));
        message.appendChild(confirmation);

        if (historyForm) {
          historyForm.elements.phone.value = data.phone;
          historyForm.elements.reference.value = result.reservation.reference;
        }
        form.reset();
        selectedTable = null;
        tableList.innerHTML = '<p class="reservation-picker-empty">Chọn ngày và giờ để xem bàn còn trống.</p>';
      } catch (error) {
        showReservationMessage(error.message, 'error');
        await loadAvailableTables();
      } finally {
        submitButton.textContent = 'Xác nhận đặt bàn →';
        submitButton.disabled = !selectedTable;
      }
    });
  }

  if (historyForm) {
    const results = document.querySelector('#reservation-history-results');
    const message = document.querySelector('#reservation-history-message');

    async function lookupHistory() {
      const data = {
        action: 'history',
        phone: historyForm.elements.phone.value,
        reference: historyForm.elements.reference.value
      };
      message.textContent = '';
      results.replaceChildren();

      try {
        const result = await sendRequest(historyForm.dataset.api, 'POST', data);
        if (!result.reservations.length) {
          message.textContent = 'Chưa có lịch đặt bàn.';
          return;
        }

        result.reservations.forEach(reservation => {
          const card = document.createElement('article');
          card.className = 'reservation-history-card';
          const details = document.createElement('div');
          const title = document.createElement('h3');
          title.textContent = `${reservation.reference_code} · Bàn ${reservation.table_code}`;
          const date = document.createElement('p');
          date.textContent = `${reservation.starts_at} · ${reservation.guest_count} người · ${reservation.area_name}`;
          const status = document.createElement('span');
          status.className = `reservation-status reservation-status-${reservation.status}`;
          status.textContent = reservationStatusLabels[reservation.status] || reservation.status;
          details.append(title, date, status);
          if (reservation.note) {
            const note = document.createElement('p');
            note.className = 'muted';
            note.textContent = `Ghi chú: ${reservation.note}`;
            details.appendChild(note);
          }
          card.appendChild(details);

          if (['pending', 'confirmed'].includes(reservation.status) && new Date(reservation.starts_at.replace(' ', 'T')) > new Date()) {
            const cancelButton = document.createElement('button');
            cancelButton.type = 'button';
            cancelButton.className = 'btn btn-outline-danger btn-small';
            cancelButton.textContent = 'Hủy lịch';
            cancelButton.addEventListener('click', async () => {
              cancelButton.disabled = true;
              try {
                const cancelResult = await sendRequest(historyForm.dataset.api, 'POST', {
                  action: 'cancel',
                  phone: historyForm.elements.phone.value,
                  reference: reservation.reference_code
                });
                message.textContent = cancelResult.message;
                await lookupHistory();
              } catch (error) {
                cancelButton.disabled = false;
                message.textContent = error.message;
                message.className = 'reservation-message error';
              }
            });
            card.appendChild(cancelButton);
          }
          results.appendChild(card);
        });
      } catch (error) {
        message.textContent = error.message;
        message.className = 'reservation-message error';
      }
    }

    historyForm.addEventListener('submit', event => {
      event.preventDefault();
      lookupHistory();
    });
  }
})();

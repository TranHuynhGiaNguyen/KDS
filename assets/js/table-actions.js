/**
 * SmartDine - Điều khiển modal Gán bàn, Chuyển bàn & Trả bàn trống
 */
(() => {
  const API_URL = 'api/table-action.php';

  const openModal = (id) => {
    const el = document.getElementById(id);
    if (el) el.style.display = 'flex';
  };

  const closeModal = (id) => {
    const el = document.getElementById(id);
    if (el) el.style.display = 'none';
  };

  const showError = (boxId, msg) => {
    const box = document.getElementById(boxId);
    if (!box) return;
    if (msg) {
      box.textContent = msg;
      box.style.display = 'block';
    } else {
      box.textContent = '';
      box.style.display = 'none';
    }
  };

  document.addEventListener('click', async (e) => {
    const closeBtn = e.target.closest('[data-close-table-modal]');
    if (closeBtn) {
      closeModal(closeBtn.dataset.closeTableModal);
      return;
    }

    if (e.target.classList.contains('custom-modal-backdrop') && (e.target.id === 'modal-assign-table' || e.target.id === 'modal-transfer-table' || e.target.id === 'modal-merge-table')) {
      closeModal(e.target.id);
      return;
    }

    const assignTrigger = e.target.closest('[data-open-assign-table]');
    if (assignTrigger) {
      showError('assign-error-box', '');
      const tableCode = assignTrigger.dataset.openAssignTable || '';
      const isUpdate = assignTrigger.dataset.assignUpdate === '1';

      const codeSelect = document.getElementById('assign-table-code');
      const allowUpdateInput = document.getElementById('assign-allow-update');
      const titleEl = document.getElementById('assign-modal-title');
      const submitBtn = document.getElementById('btn-submit-assign');
      const guestInput = document.getElementById('assign-guest-count');
      const staffSelect = document.getElementById('assign-staff-id');
      const cnameInput = document.getElementById('assign-customer-name');
      const cphoneInput = document.getElementById('assign-customer-phone');
      const noteInput = document.getElementById('assign-note');

      if (allowUpdateInput) allowUpdateInput.value = isUpdate ? '1' : '0';
      if (titleEl) titleEl.textContent = isUpdate ? `Cập nhật thông tin Bàn ${tableCode}` : 'Gán bàn cho khách';
      if (submitBtn) submitBtn.textContent = isUpdate ? 'Lưu thông tin bàn' : 'Xác nhận gán bàn';

      if (codeSelect && tableCode) {
        codeSelect.value = tableCode;
      }
      if (guestInput && assignTrigger.dataset.guestCount) {
        guestInput.value = assignTrigger.dataset.guestCount;
      } else if (guestInput && !isUpdate) {
        guestInput.value = '2';
      }
      if (staffSelect && assignTrigger.dataset.staffId && Number(assignTrigger.dataset.staffId) > 0) {
        staffSelect.value = assignTrigger.dataset.staffId;
      }
      if (cnameInput) cnameInput.value = assignTrigger.dataset.customerName || '';
      if (cphoneInput) cphoneInput.value = assignTrigger.dataset.customerPhone || '';
      if (noteInput) noteInput.value = assignTrigger.dataset.assignNote || '';

      openModal('modal-assign-table');
      return;
    }

    const transferTrigger = e.target.closest('[data-open-transfer-table]');
    if (transferTrigger) {
      showError('transfer-error-box', '');
      const fromCode = transferTrigger.dataset.openTransferTable || '';
      const fromSelect = document.getElementById('transfer-from-table');
      const toSelect = document.getElementById('transfer-to-table');
      const noteInput = document.getElementById('transfer-note');

      if (fromSelect && fromCode) {
        fromSelect.value = fromCode;
      }
      if (toSelect) {
        toSelect.value = '';
      }
      if (noteInput) {
        noteInput.value = '';
      }
      openModal('modal-transfer-table');
      return;
    }

    const mergeTrigger = e.target.closest('[data-open-merge-table]');
    if (mergeTrigger) {
      showError('merge-error-box', '');
      const fromCode = mergeTrigger.dataset.openMergeTable || '';
      const fromSelect = document.getElementById('merge-from-table');
      const toSelect = document.getElementById('merge-to-table');
      const noteInput = document.getElementById('merge-note');

      if (fromSelect && fromCode) {
        fromSelect.value = fromCode;
      }
      if (toSelect) {
        toSelect.value = '';
        if (fromCode) {
          const otherOpt = Array.from(toSelect.options).find(opt => opt.value && opt.value !== fromCode);
          if (otherOpt) toSelect.value = otherOpt.value;
        }
      }
      if (noteInput) noteInput.value = '';
      openModal('modal-merge-table');
      return;
    }

    const releaseBtn = e.target.closest('[data-release-table]');
    if (releaseBtn) {
      const code = releaseBtn.dataset.releaseTable || '';
      if (!code) return;
      if (!confirm(`Xác nhận trả bàn ${code} về trạng thái trống?`)) return;

      releaseBtn.disabled = true;
      try {
        const res = await fetch(API_URL, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: 'release', table_code: code })
        });
        const data = await res.json();
        if (res.ok && data.success) {
          if (typeof notify === 'function') notify(data.message);
          setTimeout(() => location.reload(), 450);
        } else {
          alert(data.message || 'Không thể trả bàn.');
          releaseBtn.disabled = false;
        }
      } catch (err) {
        alert('Lỗi kết nối máy chủ.');
        releaseBtn.disabled = false;
      }
    }
  });

  const assignForm = document.getElementById('form-assign-table');
  if (assignForm) {
    assignForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      showError('assign-error-box', '');

      const submitBtn = document.getElementById('btn-submit-assign');
      const formData = new FormData(assignForm);
      const selectedOpt = document.getElementById('assign-table-code')?.selectedOptions?.[0];
      const isTargetOccupied = selectedOpt?.dataset?.occupied === '1';

      const payload = {
        action: 'assign',
        table_code: formData.get('table_code'),
        guest_count: Number(formData.get('guest_count') || 2),
        staff_id: Number(formData.get('staff_id') || 0),
        customer_name: formData.get('customer_name') || '',
        customer_phone: formData.get('customer_phone') || '',
        note: formData.get('note') || '',
        allow_update: formData.get('allow_update') === '1' || isTargetOccupied
      };

      if (submitBtn) submitBtn.disabled = true;
      try {
        const res = await fetch(API_URL, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload)
        });
        const data = await res.json();
        if (res.ok && data.success) {
          closeModal('modal-assign-table');
          if (typeof notify === 'function') notify(data.message);
          setTimeout(() => location.reload(), 450);
        } else {
          showError('assign-error-box', data.message || 'Không thể gán bàn.');
          if (submitBtn) submitBtn.disabled = false;
        }
      } catch (err) {
        showError('assign-error-box', 'Lỗi kết nối máy chủ, vui lòng thử lại.');
        if (submitBtn) submitBtn.disabled = false;
      }
    });
  }

  const transferForm = document.getElementById('form-transfer-table');
  if (transferForm) {
    transferForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      showError('transfer-error-box', '');

      const submitBtn = document.getElementById('btn-submit-transfer');
      const formData = new FormData(transferForm);
      const payload = {
        action: 'transfer',
        from_table: formData.get('from_table'),
        to_table: formData.get('to_table'),
        note: formData.get('note') || ''
      };

      if (!payload.from_table || !payload.to_table) {
        showError('transfer-error-box', 'Vui lòng chọn đầy đủ bàn nguồn và bàn đích.');
        return;
      }

      if (submitBtn) submitBtn.disabled = true;
      try {
        const res = await fetch(API_URL, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload)
        });
        const data = await res.json();
        if (res.ok && data.success) {
          closeModal('modal-transfer-table');
          if (typeof notify === 'function') notify(data.message);
          setTimeout(() => {
            if (window.location.search.includes('page=table-order') && data.redirect_url) {
              window.location.href = data.redirect_url;
            } else {
              location.reload();
            }
          }, 450);
        } else {
          showError('transfer-error-box', data.message || 'Không thể chuyển bàn.');
          if (submitBtn) submitBtn.disabled = false;
        }
      } catch (err) {
        showError('transfer-error-box', 'Lỗi kết nối máy chủ, vui lòng thử lại.');
        if (submitBtn) submitBtn.disabled = false;
      }
    });
  }

  const mergeForm = document.getElementById('form-merge-table');
  if (mergeForm) {
    mergeForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      showError('merge-error-box', '');

      const submitBtn = document.getElementById('btn-submit-merge');
      const formData = new FormData(mergeForm);
      const payload = {
        action: 'merge',
        from_table: formData.get('from_table'),
        to_table: formData.get('to_table'),
        note: formData.get('note') || ''
      };

      if (!payload.from_table || !payload.to_table) {
        showError('merge-error-box', 'Vui lòng chọn đầy đủ bàn cần gộp và bàn đích.');
        return;
      }
      if (payload.from_table === payload.to_table) {
        showError('merge-error-box', 'Bàn cần gộp và bàn đích phải khác nhau.');
        return;
      }

      if (submitBtn) submitBtn.disabled = true;
      try {
        const res = await fetch(API_URL, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload)
        });
        const data = await res.json();
        if (res.ok && data.success) {
          closeModal('modal-merge-table');
          if (typeof notify === 'function') notify(data.message);
          setTimeout(() => {
            if (window.location.search.includes('page=table-order') && data.redirect_url) {
              window.location.href = data.redirect_url;
            } else {
              location.reload();
            }
          }, 450);
        } else {
          showError('merge-error-box', data.message || 'Không thể gộp bàn.');
          if (submitBtn) submitBtn.disabled = false;
        }
      } catch (err) {
        showError('merge-error-box', 'Lỗi kết nối máy chủ, vui lòng thử lại.');
        if (submitBtn) submitBtn.disabled = false;
      }
    });
  }
})();

// SmartDine & KDS Core Application Logic
const money = n => new Intl.NumberFormat('vi-VN').format(n) + 'đ';

// Khởi tạo giỏ hàng từ LocalStorage
let cart = JSON.parse(localStorage.getItem('smartdine_cart') || '[]');
let appliedVoucher = JSON.parse(localStorage.getItem('smartdine_voucher') || 'null');

function saveCart() {
  localStorage.setItem('smartdine_cart', JSON.stringify(cart));
  renderCartSummary();
  renderCartPage();
}

function renderCartSummary() {
  const count = cart.reduce((s, i) => s + i.qty, 0);
  const total = cart.reduce((s, i) => s + i.price * i.qty, 0);

  const a = document.querySelector('#cart-summary');
  const b = document.querySelector('#cart-total');
  if (a) a.textContent = `Giỏ hàng (${count} món)`;
  if (b) b.textContent = money(total);

  document.querySelectorAll('.cart-count').forEach(el => {
    el.textContent = count;
  });

  // Tự động ẩn/hiện thanh giỏ hàng nổi
  const floating = document.querySelector('#floating-cart-bar');
  if (floating) {
    floating.classList.toggle('has-items', count > 0);
  }

  // Khóa nút gửi đơn nếu giỏ hàng trống
  const confirmBtn = document.querySelector('#btn-confirm-order');
  if (confirmBtn) {
    if (count === 0) {
      confirmBtn.classList.add('disabled');
      confirmBtn.style.opacity = '0.5';
      confirmBtn.style.pointerEvents = 'none';
    } else {
      confirmBtn.classList.remove('disabled');
      confirmBtn.style.opacity = '1';
      confirmBtn.style.pointerEvents = 'auto';
    }
  }
}

function renderCartPage() {
  const holder = document.querySelector('#cart-lines');
  if (!holder) return;

  const count = cart.reduce((s, i) => s + i.qty, 0);
  const subtotal = cart.reduce((s, i) => s + i.price * i.qty, 0);

  // Tính giảm giá nếu có voucher áp dụng
  let discountAmount = 0;
  if (appliedVoucher && count > 0) {
    if (appliedVoucher.discount_type === 'percent') {
      discountAmount = Math.round(subtotal * (appliedVoucher.discount_value / 100));
      if (appliedVoucher.max_discount_amount > 0 && discountAmount > appliedVoucher.max_discount_amount) {
        discountAmount = appliedVoucher.max_discount_amount;
      }
    } else {
      discountAmount = Math.min(subtotal, appliedVoucher.discount_value);
    }
  }

  const afterDiscount = Math.max(0, subtotal - discountAmount);
  const fee = Math.round(afterDiscount * 0.05);

  const countEl = document.querySelector('#cart-items-count');
  if (countEl) countEl.textContent = `(${count} món)`;

  if (cart.length === 0) {
    appliedVoucher = null;
    localStorage.removeItem('smartdine_voucher');
    holder.innerHTML = `
      <div class="empty-state">
        <div style="font-size: 40px; margin-bottom: 12px;">🛒</div>
        <h3>Giỏ hàng đang trống</h3>
        <p class="muted">Quý khách chưa chọn món nào từ thực đơn nhà hàng.</p>
        <div style="display:flex; gap:10px; justify-content:center; margin-top:16px;">
          <a class="btn" href="index.php?page=menu">Khám phá thực đơn ngay →</a>
          <button class="btn btn-outline" id="btn-add-sample-cart">Thêm món mẫu để test</button>
        </div>
      </div>
    `;
  } else {
    holder.innerHTML = cart.map((i, idx) => `
      <div class="cart-line">
        <div class="food-thumb">🍽</div>
        <div class="grow">
          <b>${i.name}</b>
          <small>${money(i.price)} / phần</small>
        </div>
        <div class="quantity">
          <button data-qty="${idx}" data-delta="-1" title="Giảm số lượng">−</button>
          <b>${i.qty}</b>
          <button data-qty="${idx}" data-delta="1" title="Tăng số lượng">+</button>
        </div>
        <b style="min-width: 80px; text-align: right;">${money(i.price * i.qty)}</b>
        <button class="remove" data-remove="${idx}" title="Xóa món">×</button>
      </div>
    `).join('');
  }

  const set = (id, v) => {
    const el = document.querySelector(id);
    if (el) el.textContent = money(v);
  };
  set('#cart-subtotal', subtotal);
  set('#service-fee', fee);
  set('#cart-grand-total', afterDiscount + fee);

  // Hiển thị / ẩn dòng voucher trong tóm tắt
  const voucherRow = document.querySelector('#cart-voucher-row');
  const voucherCodeLabel = document.querySelector('#cart-voucher-code-label');
  const voucherDiscount = document.querySelector('#cart-voucher-discount');
  const voucherInput = document.querySelector('#cart-voucher-input');
  const voucherStatus = document.querySelector('#cart-voucher-status');

  if (voucherRow && appliedVoucher && discountAmount > 0) {
    voucherRow.style.display = 'flex';
    if (voucherCodeLabel) voucherCodeLabel.innerHTML = `${appliedVoucher.code} <button type="button" id="btn-remove-cart-voucher" style="border:none; background:none; color:#ef4444; cursor:pointer; font-size:11px; font-weight:700; margin-left:4px;">[Hủy]</button>`;
    if (voucherDiscount) voucherDiscount.textContent = `-${money(discountAmount)}`;
    if (voucherInput) voucherInput.value = appliedVoucher.code;
    if (voucherStatus) {
      voucherStatus.style.display = 'block';
      voucherStatus.style.color = '#16a34a';
      voucherStatus.textContent = `✓ Đã áp dụng mã "${appliedVoucher.code}": Giảm ${money(discountAmount)}`;
    }
  } else if (voucherRow) {
    voucherRow.style.display = 'none';
    if (voucherStatus && !appliedVoucher) {
      voucherStatus.style.display = 'none';
    }
  }
}

// ==========================================================
// KDS: ĐỒNG HỒ THỜI GIAN THỰC & DARK MODE & ÂM THANH BẾP
// ==========================================================
function initKDS() {
  // Cập nhật đồng hồ số mỗi giây
  const clockEl = document.querySelector('#kds-clock');
  if (clockEl) {
    const tick = () => {
      const now = new Date();
      const pad = n => String(n).padStart(2, '0');
      clockEl.textContent = `${pad(now.getHours())}:${pad(now.getMinutes())}:${pad(now.getSeconds())}`;
    };
    tick();
    setInterval(tick, 1000);
  }

  // Khôi phục giao diện tối nếu đã lưu
  const savedTheme = localStorage.getItem('kds_theme');
  if (savedTheme === 'dark') {
    document.body.classList.add('kds-dark');
  }

  // Toggle Dark Mode
  const themeToggle = document.querySelector('#kds-theme-toggle');
  if (themeToggle) {
    themeToggle.addEventListener('click', () => {
      document.body.classList.toggle('kds-dark');
      const isDark = document.body.classList.contains('kds-dark');
      localStorage.setItem('kds_theme', isDark ? 'dark' : 'light');
      notify(isDark ? 'Đã bật chế độ Bếp tối (High Contrast)' : 'Đã bật chế độ Bếp sáng');
    });
  }

  // Toggle Fullscreen
  const fsToggle = document.querySelector('#kds-fullscreen-toggle');
  if (fsToggle) {
    fsToggle.addEventListener('click', () => {
      if (!document.fullscreenElement) {
        document.documentElement.requestFullscreen?.().catch(() => { });
        notify('Đã bật chế độ Toàn màn hình KDS');
      } else {
        document.exitFullscreen?.().catch(() => { });
        notify('Đã thoát toàn màn hình');
      }
    });
  }

  // Beep Audio Alert (Web Audio Synthesizer)
  const soundToggle = document.querySelector('#kds-sound-toggle');
  if (soundToggle) {
    soundToggle.addEventListener('click', () => {
      playKitchenBeep();
      notify('🔔 Chuông báo KDS đã kích hoạt thử nghiệm');
    });
  }
}

function playKitchenBeep() {
  try {
    const ctx = new (window.AudioContext || window.webkitAudioContext)();
    const osc = ctx.createOscillator();
    const gain = ctx.createGain();
    osc.connect(gain);
    gain.connect(ctx.destination);
    osc.type = 'sine';
    osc.frequency.setValueAtTime(880, ctx.currentTime);
    osc.frequency.setValueAtTime(1100, ctx.currentTime + 0.1);
    gain.gain.setValueAtTime(0.3, ctx.currentTime);
    gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.3);
    osc.start();
    osc.stop(ctx.currentTime + 0.35);
  } catch (e) { }
}

// ==========================================================
// SƠ ĐỒ BÀN (STAFF FLOOR MAP) LỌC ĐỘNG
// ==========================================================
function initFloorFilters() {
  const zoneSelect = document.querySelector('#floor-zone-select');
  const statusSelect = document.querySelector('#floor-status-select');

  function filterTables() {
    const zone = zoneSelect ? zoneSelect.value : 'all';
    const status = statusSelect ? statusSelect.value : 'all';

    document.querySelectorAll('.table-tile').forEach(table => {
      const matchZone = (zone === 'all' || table.dataset.zone === zone);
      const matchStatus = (status === 'all' || table.dataset.status === status);
      table.style.display = (matchZone && matchStatus) ? 'flex' : 'none';
    });
  }

  if (zoneSelect) zoneSelect.addEventListener('change', filterTables);
  if (statusSelect) statusSelect.addEventListener('change', filterTables);
}

// ==========================================================
// THEO DÕI ĐƠN HÀNG (SIMULATE TRACKING STEP)
// ==========================================================
function initTrackingSimulator() {
  const btn = document.querySelector('#simulate-step-btn');
  if (!btn) return;

  let currentStep = 2;
  const statusLabels = {
    1: 'Đã nhận đơn',
    2: 'Đang chế biến tại bếp',
    3: 'Đã ra đồ phục vụ',
    4: 'Hoàn thành'
  };

  btn.addEventListener('click', () => {
    currentStep = currentStep >= 4 ? 1 : currentStep + 1;
    for (let i = 1; i <= 4; i++) {
      const s = document.querySelector(`#step-${i}`);
      if (s) {
        s.classList.remove('done', 'current');
        if (i < currentStep) s.classList.add('done');
        else if (i === currentStep) s.classList.add('current');
      }
    }
    const statusEl = document.querySelector('#current-order-status');
    if (statusEl) {
      statusEl.textContent = statusLabels[currentStep];
      statusEl.className = `status status-${currentStep === 4 ? 'ready' : (currentStep === 3 ? 'ready' : 'cooking')}`;
    }
    notify(`Tiến độ chuyển sang: ${statusLabels[currentStep]}`);
  });
}

// ==========================================================
// GLOBAL EVENT DELEGATION
// ==========================================================
document.addEventListener('click', e => {
  // Thêm vào giỏ
  const add = e.target.closest('[data-add]');
  if (add) {
    const name = add.dataset.add;
    const price = Number(add.dataset.price);
    const found = cart.find(x => x.name === name);
    if (found) found.qty++;
    else cart.push({ name, price, qty: 1 });
    saveCart();

    // Phản hồi nút bấm
    const originalText = add.textContent;
    add.textContent = '✓ Đã thêm';
    setTimeout(() => { add.textContent = originalText; }, 900);
    notify(`Đã thêm "${name}" vào giỏ hàng`);
  }

  // Tăng giảm số lượng giỏ
  const qty = e.target.closest('[data-qty]');
  if (qty) {
    const i = Number(qty.dataset.qty);
    cart[i].qty += Number(qty.dataset.delta);
    if (cart[i].qty <= 0) cart.splice(i, 1);
    saveCart();
  }

  // Xóa món
  const rem = e.target.closest('[data-remove]');
  if (rem) {
    cart.splice(Number(rem.dataset.remove), 1);
    saveCart();
    notify('Đã xóa món khỏi giỏ');
  }

  // Thêm món mẫu test
  const sampleBtn = e.target.closest('#btn-add-sample-cart');
  if (sampleBtn) {
    cart = [
      { name: 'Cơm gà Hải Nam', price: 75000, qty: 1 },
      { name: 'Mì xào bò', price: 85000, qty: 1 },
      { name: 'Trà đào cam sả', price: 35000, qty: 2 }
    ];
    saveCart();
    notify('Đã thêm 3 món mẫu vào giỏ hàng');
  }

  // Áp dụng voucher trong giỏ hàng
  const applyVoucherBtn = e.target.closest('#btn-apply-cart-voucher');
  if (applyVoucherBtn) {
    const input = document.querySelector('#cart-voucher-input');
    const statusEl = document.querySelector('#cart-voucher-status');
    const code = input ? input.value.trim().toUpperCase() : '';
    const subtotal = cart.reduce((s, i) => s + i.price * i.qty, 0);

    if (!code) {
      if (statusEl) {
        statusEl.style.display = 'block';
        statusEl.style.color = '#ef4444';
        statusEl.textContent = 'Vui lòng nhập mã giảm giá!';
      }
      return;
    }

    if (cart.length === 0) {
      if (statusEl) {
        statusEl.style.display = 'block';
        statusEl.style.color = '#ef4444';
        statusEl.textContent = 'Giỏ hàng đang trống, vui lòng chọn món trước!';
      }
      return;
    }

    fetch(`api/validate-voucher.php?code=${encodeURIComponent(code)}&subtotal=${subtotal}`)
      .then(res => res.json())
      .then(data => {
        if (data.valid) {
          appliedVoucher = {
            code: data.code,
            name: data.name,
            discount_type: data.discount_type,
            discount_value: data.discount_value,
            max_discount_amount: data.max_discount_amount || 0
          };
          localStorage.setItem('smartdine_voucher', JSON.stringify(appliedVoucher));
          renderCartPage();
          notify(`Áp dụng mã ${data.code} thành công: -${money(data.discount_amount)}`);
        } else {
          if (statusEl) {
            statusEl.style.display = 'block';
            statusEl.style.color = '#ef4444';
            statusEl.textContent = data.message || 'Mã không hợp lệ!';
          }
        }
      })
      .catch(() => {
        if (statusEl) {
          statusEl.style.display = 'block';
          statusEl.style.color = '#ef4444';
          statusEl.textContent = 'Lỗi kết nối kiểm tra mã!';
        }
      });
  }

  // Hủy voucher trong giỏ hàng
  const removeVoucherBtn = e.target.closest('#btn-remove-cart-voucher');
  if (removeVoucherBtn) {
    appliedVoucher = null;
    localStorage.removeItem('smartdine_voucher');
    const input = document.querySelector('#cart-voucher-input');
    if (input) input.value = '';
    renderCartPage();
    notify('Đã hủy áp dụng mã giảm giá');
  }

  // Lọc danh mục thực đơn
  const cat = e.target.closest('[data-category]');
  if (cat) {
    document.querySelectorAll('[data-category]').forEach(x => x.classList.toggle('active', x === cat));
    const targetCat = cat.dataset.category;
    document.querySelectorAll('[data-food]').forEach(x => {
      x.hidden = targetCat !== 'all' && x.dataset.type !== targetCat;
    });
  }

  // Bật / Tắt tạm ngừng món (Đồng bộ Menu QR)
  const toggleDishAjax = e.target.closest('[data-toggle-dish-ajax]');
  if (toggleDishAjax) {
    e.preventDefault();
    const id = toggleDishAjax.dataset.toggleDishAjax;
    const btn = toggleDishAjax;
    const row = btn.closest('.unavailable-row');
    const statusSpan = row ? row.querySelector('.status') : null;

    btn.disabled = true;
    btn.style.opacity = '0.6';

    fetch('api/toggle-dish-status.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'id=' + encodeURIComponent(id)
    })
    .then(r => r.json())
    .then(data => {
      btn.disabled = false;
      btn.style.opacity = '1';
      if (data.success) {
        if (data.new_status === 'active') {
          btn.className = 'btn btn-small btn-outline-danger';
          btn.textContent = 'Tạm ngừng bán';
          btn.style.border = '1px solid #fca5a5';
          btn.style.color = '#dc2626';
          if (statusSpan) {
            statusSpan.className = 'status status-ready';
            statusSpan.textContent = 'Đang phục vụ bình thường';
          }
          notify('Đã mở bán lại món thành công');
        } else {
          btn.className = 'btn btn-small btn-success';
          btn.textContent = 'Mở bán lại';
          btn.style.border = '';
          btn.style.color = '';
          if (statusSpan) {
            statusSpan.className = 'status status-pending';
            statusSpan.textContent = 'Đã tạm hết nguyên liệu';
          }
          notify('Đã tạm ngừng bán món (Đã đồng bộ Menu QR)');
        }
      } else {
        alert(data.message || 'Lỗi cập nhật trạng thái');
      }
    })
    .catch(() => {
      btn.disabled = false;
      btn.style.opacity = '1';
      btn.closest('form')?.submit();
    });
  }

  // Toast chung
  const toast = e.target.closest('[data-toast]');
  if (toast) notify(toast.dataset.toast);
});

// Tìm kiếm món ăn
document.addEventListener('input', e => {
  if (e.target.matches('[data-filter-input]')) {
    const q = e.target.value.toLowerCase().trim();
    document.querySelectorAll('[data-food]').forEach(x => {
      x.hidden = !x.textContent.toLowerCase().includes(q);
    });
  }
});

// Chặn submit biểu mẫu demo
document.addEventListener('submit', e => {
  if (e.target.matches('[data-demo-form]')) {
    e.preventDefault();
    notify('Đã gửi thông tin biểu mẫu thành công (Chế độ mô phỏng)');
  }
});

// Toast Notification
function notify(msg) {
  let n = document.querySelector('.toast');
  if (!n) {
    n = document.createElement('div');
    n.className = 'toast';
    document.body.appendChild(n);
  }
  n.innerHTML = `<span>⚡</span> <span>${msg}</span>`;
  n.classList.add('show');
  clearTimeout(window.toastTimer);
  window.toastTimer = setTimeout(() => n.classList.remove('show'), 2800);
}

// Khởi chạy khi tài liệu sẵn sàng
document.addEventListener('DOMContentLoaded', () => {
  renderCartSummary();
  renderCartPage();
  initKDS();
  initFloorFilters();
  initTrackingSimulator();
});

// Fallback nếu DOMContentLoaded đã bắn trước khi script chạy
renderCartSummary();
renderCartPage();
initKDS();
initFloorFilters();
initTrackingSimulator();


// ==========================================================
// PASSWORD REVEAL TOGGLE
// ==========================================================
document.addEventListener('DOMContentLoaded', () => {
  const pwBtn = document.querySelector('#btn-toggle-pw');
  const pwInput = document.querySelector('#password');
  if (pwBtn && pwInput) {
    pwBtn.addEventListener('click', () => {
      const isPw = pwInput.type === 'password';
      pwInput.type = isPw ? 'text' : 'password';
      pwBtn.innerHTML = isPw
        ? '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>'
        : '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>';
    });
  }
});
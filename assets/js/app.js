// SmartDine & KDS Core Application Logic
const money = n => new Intl.NumberFormat('vi-VN').format(n) + 'đ';

// Khởi tạo giỏ hàng từ LocalStorage
let cart = JSON.parse(localStorage.getItem('smartdine_cart') || '[]');

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
  const fee = Math.round(subtotal * 0.05);

  const countEl = document.querySelector('#cart-items-count');
  if (countEl) countEl.textContent = `(${count} món)`;

  if (cart.length === 0) {
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
  set('#cart-grand-total', subtotal + fee);
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
        document.documentElement.requestFullscreen?.().catch(() => {});
        notify('Đã bật chế độ Toàn màn hình KDS');
      } else {
        document.exitFullscreen?.().catch(() => {});
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

  // Station Filter
  const stationSelect = document.querySelector('#kds-station-select');
  if (stationSelect) {
    stationSelect.addEventListener('change', () => {
      const val = stationSelect.value;
      document.querySelectorAll('.kitchen-card').forEach(card => {
        card.hidden = val !== 'all' && card.dataset.station !== val;
      });
      notify(`Đã lọc trạm bếp: ${stationSelect.options[stationSelect.selectedIndex].text}`);
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
  } catch (e) {}
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

  // Lọc danh mục thực đơn
  const cat = e.target.closest('[data-category]');
  if (cat) {
    document.querySelectorAll('[data-category]').forEach(x => x.classList.toggle('active', x === cat));
    const targetCat = cat.dataset.category;
    document.querySelectorAll('[data-food]').forEach(x => {
      x.hidden = targetCat !== 'all' && x.dataset.type !== targetCat;
    });
  }

  // Gạch món riêng lẻ trong thẻ KDS
  const bump = e.target.closest('[data-bump-item]');
  if (bump && !e.target.matches('.kds-item-checkbox')) {
    const cb = bump.querySelector('.kds-item-checkbox');
    if (cb) {
      cb.checked = !cb.checked;
      bump.classList.toggle('item-done', cb.checked);
    }
  } else if (e.target.matches('.kds-item-checkbox')) {
    const bumpItem = e.target.closest('[data-bump-item]');
    if (bumpItem) bumpItem.classList.toggle('item-done', e.target.checked);
  }

  // Chuyển trạng thái phiếu KDS
  const change = e.target.closest('[data-change-status]');
  if (change) {
    const card = change.closest('.kitchen-card');
    const state = card.dataset.kcard;

    if (state === 'new') {
      card.dataset.kcard = 'cooking';
      const st = card.querySelector('.status');
      st.className = 'status status-cooking';
      st.textContent = 'Đang nấu';
      change.className = 'btn btn-kds btn-ready-cooking';
      change.innerHTML = `
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
        <span>Báo ra đồ (Hoàn thành)</span>
      `;
      notify('Đã nhận đơn và chuyển sang trạng thái đang nấu');
    } else {
      card.dataset.kcard = 'ready';
      const st = card.querySelector('.status');
      st.className = 'status status-ready';
      st.textContent = 'Hoàn thành';
      change.disabled = true;
      change.className = 'btn btn-kds btn-done-cooking';
      change.innerHTML = `
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
        <span>Đã ra đồ xong ✓</span>
      `;
      // Đánh dấu xong toàn bộ món
      card.querySelectorAll('.kitchen-item').forEach(it => {
        it.classList.add('item-done');
        const cb = it.querySelector('.kds-item-checkbox');
        if (cb) cb.checked = true;
      });
      playKitchenBeep();
      notify('Đã hoàn tất phiếu chế biến và báo nhân viên ra món');
    }
  }

  // KDS Filter Tabs
  const kf = e.target.closest('[data-kfilter]');
  if (kf) {
    document.querySelectorAll('[data-kfilter]').forEach(x => x.classList.toggle('active', x === kf));
    const filter = kf.dataset.kfilter;
    document.querySelectorAll('[data-kcard]').forEach(x => {
      x.hidden = filter !== 'all' && x.dataset.kcard !== filter;
    });
  }

  // Bật / Tắt tạm ngừng món
  const toggle = e.target.closest('[data-toggle-food]');
  if (toggle) {
    const isStopping = toggle.textContent.includes('ngừng');
    toggle.textContent = isStopping ? 'Mở bán lại' : 'Tạm ngừng bán';
    toggle.className = 'btn btn-small ' + (isStopping ? 'btn-success' : 'btn-outline-danger');
    const statusLabel = toggle.closest('.unavailable-row')?.querySelector('.status');
    if (statusLabel) {
      statusLabel.className = 'status ' + (isStopping ? 'status-pending' : 'status-ready');
      statusLabel.textContent = isStopping ? 'Đã tạm hết nguyên liệu' : 'Đang phục vụ bình thường';
    }
    notify(isStopping ? 'Đã tạm dừng phục vụ món trên Menu QR' : 'Đã mở bán lại món trên hệ thống');
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

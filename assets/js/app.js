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

  // Cập nhật ước tính thời gian ra món của Bếp KDS
  const waitTimeEl = document.querySelector('#cart-estimated-time');
  if (waitTimeEl) {
    if (count === 0) {
      waitTimeEl.textContent = '0 phút (Giỏ đang trống)';
    } else if (count <= 2) {
      waitTimeEl.textContent = '8 - 12 phút';
    } else if (count <= 4) {
      waitTimeEl.textContent = '12 - 15 phút';
    } else {
      waitTimeEl.textContent = '15 - 20 phút';
    }
  }

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

    // Kích hoạt AI Upsell gợi ý món dùng kèm
    if (typeof triggerAiUpsell === 'function') {
      triggerAiUpsell(name);
    }
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

// ==========================================================
// AI SMART ORDERING: COMBOS & UPSELL ENGINE
// ==========================================================
const SMART_COMBOS = [
  {
    id: 'combo-2p',
    type: '2p',
    name: 'Combo Cặp Đôi (2 Người)',
    sub: 'Cơm gà + Mì bò + 2 Trà đào cam sả',
    avatar: '👥',
    tag: 'Tiết kiệm 10%',
    tagClass: 'best',
    originalPrice: 230000,
    comboPrice: 210000,
    items: [
      { name: 'Cơm gà Hải Nam', price: 75000, qty: 1, thumb: 'https://images.unsplash.com/photo-1603133872878-684f208fb84b?auto=format&fit=crop&w=200&q=80' },
      { name: 'Mì xào bò', price: 85000, qty: 1, thumb: 'https://images.unsplash.com/photo-1569718212165-3a8278d5f624?auto=format&fit=crop&w=200&q=80' },
      { name: 'Trà đào cam sả', price: 35000, qty: 2, thumb: 'https://images.unsplash.com/photo-1556679343-c7306c1976bc?auto=format&fit=crop&w=200&q=80' }
    ]
  },
  {
    id: 'combo-4p',
    type: '4p',
    name: 'Combo Gia Đình (4 Người)',
    sub: 'Bò lúc lắc + 2 Cơm gà + Salad + 2 Trà đào',
    avatar: '👨‍👩‍👧‍👦',
    tag: 'Phổ biến nhất 🔥',
    tagClass: 'popular',
    originalPrice: 395000,
    comboPrice: 355000,
    items: [
      { name: 'Bò lúc lắc hạt tiêu', price: 120000, qty: 1, thumb: 'https://images.unsplash.com/photo-1544025162-d76694265947?auto=format&fit=crop&w=200&q=80' },
      { name: 'Cơm gà Hải Nam', price: 75000, qty: 2, thumb: 'https://images.unsplash.com/photo-1603133872878-684f208fb84b?auto=format&fit=crop&w=200&q=80' },
      { name: 'Salad trộn đặc biệt', price: 55000, qty: 1, thumb: 'https://images.unsplash.com/photo-1512621776951-a57141f2eefd?auto=format&fit=crop&w=200&q=80' },
      { name: 'Trà đào cam sả', price: 35000, qty: 2, thumb: 'https://images.unsplash.com/photo-1556679343-c7306c1976bc?auto=format&fit=crop&w=200&q=80' }
    ]
  },
  {
    id: 'combo-500k',
    type: 'budget',
    name: 'Mâm Tiệc Thịnh Soạn ~500k',
    sub: '2 Bò lúc lắc + Mì bò + Salad + 2 Sữa chua + 2 Trà đào',
    avatar: '💎',
    tag: 'Trọn vị 500k 🌟',
    tagClass: 'best',
    originalPrice: 530000,
    comboPrice: 485000,
    items: [
      { name: 'Bò lúc lắc hạt tiêu', price: 120000, qty: 2, thumb: 'https://images.unsplash.com/photo-1544025162-d76694265947?auto=format&fit=crop&w=200&q=80' },
      { name: 'Mì xào bò', price: 85000, qty: 1, thumb: 'https://images.unsplash.com/photo-1569718212165-3a8278d5f624?auto=format&fit=crop&w=200&q=80' },
      { name: 'Salad trộn đặc biệt', price: 55000, qty: 1, thumb: 'https://images.unsplash.com/photo-1512621776951-a57141f2eefd?auto=format&fit=crop&w=200&q=80' },
      { name: 'Sữa chua trái cây', price: 40000, qty: 2, thumb: 'https://images.unsplash.com/photo-1488477181946-6428a0291777?auto=format&fit=crop&w=200&q=80' },
      { name: 'Trà đào cam sả', price: 35000, qty: 2, thumb: 'https://images.unsplash.com/photo-1556679343-c7306c1976bc?auto=format&fit=crop&w=200&q=80' }
    ]
  }
];

const AI_UPSELL_RULES = {
  'Cơm gà Hải Nam': {
    name: 'Trà đào cam sả',
    price: 35000,
    thumb: 'https://images.unsplash.com/photo-1556679343-c7306c1976bc?auto=format&fit=crop&w=200&q=80',
    hint: 'Trà thảo mộc thanh mát giúp giải ngấy cho cơm gà'
  },
  'Mì xào bò': {
    name: 'Salad trộn đặc biệt',
    price: 55000,
    thumb: 'https://images.unsplash.com/photo-1512621776951-a57141f2eefd?auto=format&fit=crop&w=200&q=80',
    hint: 'Rau xanh tươi mát sốt mè rang ăn kèm mì xào cực cuốn'
  },
  'Bò lúc lắc hạt tiêu': {
    name: 'Trà đào cam sả',
    price: 35000,
    thumb: 'https://images.unsplash.com/photo-1556679343-c7306c1976bc?auto=format&fit=crop&w=200&q=80',
    hint: 'Sốt tiêu đen cay nồng rất hợp cùng nước mát lạnh'
  },
  'Lẩu thái hải sản': {
    name: 'Trà đào cam sả',
    price: 35000,
    thumb: 'https://images.unsplash.com/photo-1556679343-c7306c1976bc?auto=format&fit=crop&w=200&q=80',
    hint: 'Giải nhiệt tức thì sau khi thưởng thức lẩu Thái chua cay'
  },
  'Salad trộn đặc biệt': {
    name: 'Mì xào bò',
    price: 85000,
    thumb: 'https://images.unsplash.com/photo-1569718212165-3a8278d5f624?auto=format&fit=crop&w=200&q=80',
    hint: 'Kết hợp salad khai vị cùng món mì xào thăn bò thơm ngon'
  },
  'Trà đào cam sả': {
    name: 'Sữa chua trái cây',
    price: 40000,
    thumb: 'https://images.unsplash.com/photo-1488477181946-6428a0291777?auto=format&fit=crop&w=200&q=80',
    hint: 'Tráng miệng ngọt dịu thanh mát sau đồ uống'
  }
};

let upsellTimer = null;
let currentUpsellDish = null;

function triggerAiUpsell(addedDishName) {
  const drawer = document.querySelector('#ai-upsell-drawer');
  if (!drawer) return;

  const rec = AI_UPSELL_RULES[addedDishName] || {
    name: 'Trà đào cam sả',
    price: 35000,
    thumb: 'https://images.unsplash.com/photo-1556679343-c7306c1976bc?auto=format&fit=crop&w=200&q=80',
    hint: 'Khách thường gọi kèm đồ uống mát lạnh cho bữa ăn'
  };

  if (rec.name === addedDishName) return;

  currentUpsellDish = rec;

  const thumbEl = document.querySelector('#ai-upsell-thumb');
  const nameEl = document.querySelector('#ai-upsell-name');
  const descEl = document.querySelector('#ai-upsell-desc');
  const hintEl = document.querySelector('#ai-upsell-hint');
  const addBtn = document.querySelector('#btn-upsell-add');

  if (thumbEl) thumbEl.src = rec.thumb;
  if (nameEl) nameEl.textContent = `${rec.name} (${money(rec.price)})`;
  if (descEl) descEl.textContent = rec.hint;
  if (hintEl) hintEl.innerHTML = `✨ AI GỢI Ý KÈM: ${addedDishName}`;
  if (addBtn) {
    addBtn.textContent = `+ Thêm ngay (${money(rec.price)})`;
    addBtn.style.background = '';
  }

  drawer.classList.add('active');

  clearTimeout(upsellTimer);
  upsellTimer = setTimeout(() => {
    drawer.classList.remove('active');
  }, 9000);
}

function hideAiUpsell() {
  const drawer = document.querySelector('#ai-upsell-drawer');
  if (drawer) drawer.classList.remove('active');
  clearTimeout(upsellTimer);
}

function addComboToCart(comboId) {
  const combo = SMART_COMBOS.find(c => c.id === comboId);
  if (!combo) return;

  combo.items.forEach(item => {
    const found = cart.find(x => x.name === item.name);
    if (found) {
      found.qty += item.qty;
    } else {
      cart.push({ name: item.name, price: item.price, qty: item.qty });
    }
  });

  saveCart();
  notify(`✓ Đã thêm toàn bộ ${combo.name} vào giỏ hàng!`);
}

function openComboModal(comboId) {
  const combo = SMART_COMBOS.find(c => c.id === comboId);
  if (!combo) return;

  const modal = document.querySelector('#ai-combo-modal');
  const title = document.querySelector('#ai-modal-combo-title');
  const body = document.querySelector('#ai-modal-combo-body');
  const price = document.querySelector('#ai-modal-combo-price');
  const addAllBtn = document.querySelector('#btn-modal-add-all-combo');

  if (title) title.textContent = `${combo.name}`;
  if (price) price.textContent = `${money(combo.comboPrice)} (Tiết kiệm ${money(combo.originalPrice - combo.comboPrice)})`;

  if (body) {
    body.innerHTML = `
      <p style="font-size:13px; color:#64748b; margin-bottom:14px;">${combo.sub}</p>
      <div style="display:flex; flex-direction:column; gap:8px;">
        ${combo.items.map(it => `
          <div class="ai-modal-dish-row">
            <div class="ai-modal-dish-info">
              <img src="${it.thumb}" alt="${it.name}" class="ai-modal-dish-thumb">
              <div>
                <b style="font-size:14px; color:#1e293b; display:block;">${it.name}</b>
                <small style="color:#64748b;">${money(it.price)} / phần</small>
              </div>
            </div>
            <span class="ai-modal-dish-qty">x${it.qty} phần</span>
          </div>
        `).join('')}
      </div>
    `;
  }

  if (addAllBtn) {
    addAllBtn.onclick = () => {
      addComboToCart(comboId);
      modal.classList.remove('active');
    };
  }

  if (modal) modal.classList.add('active');
}

let lastAiToggleTime = 0;
window.toggleAiChat = function(force) {
  const now = Date.now();
  if (now - lastAiToggleTime < 250 && force === undefined) return;
  lastAiToggleTime = now;

  const chatWin = document.querySelector('#ai-chat-window');
  if (!chatWin) return;
  const isCurrentlyActive = chatWin.classList.contains('active');
  const shouldOpen = typeof force === 'boolean' ? force : !isCurrentlyActive;
  if (shouldOpen) {
    chatWin.classList.add('active');
    chatWin.setAttribute('aria-hidden', 'false');
    const input = document.querySelector('#ai-chat-input');
    if (input) setTimeout(() => input.focus(), 150);
  } else {
    chatWin.classList.remove('active');
    chatWin.setAttribute('aria-hidden', 'true');
  }
};

let aiSmartOrderingInitialized = false;
function initAiSmartOrdering() {
  if (aiSmartOrderingInitialized) return;
  aiSmartOrderingInitialized = true;
  const filterGroup = document.querySelector('#ai-combo-filter-group');
  if (filterGroup) {
    filterGroup.addEventListener('click', e => {
      const btn = e.target.closest('[data-combo-filter]');
      if (!btn) return;

      filterGroup.querySelectorAll('.ai-filter-btn').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');

      const filter = btn.dataset.comboFilter;
      document.querySelectorAll('#ai-combo-container .ai-combo-card').forEach(card => {
        if (filter === 'all' || card.dataset.comboCard === filter) {
          card.style.display = 'flex';
        } else {
          card.style.display = 'none';
        }
      });
    });
  }

  document.addEventListener('click', e => {
    const addComboBtn = e.target.closest('[data-add-combo]');
    if (addComboBtn) {
      const comboId = addComboBtn.dataset.addCombo;
      addComboToCart(comboId);

      const oldText = addComboBtn.textContent;
      addComboBtn.textContent = '✓ Đã chọn';
      setTimeout(() => { addComboBtn.textContent = oldText; }, 1000);
    }

    const prevComboBtn = e.target.closest('[data-preview-combo]');
    if (prevComboBtn) {
      openComboModal(prevComboBtn.dataset.previewCombo);
    }

    if (e.target.closest('#btn-close-combo-modal') || e.target.id === 'ai-combo-modal') {
      const modal = document.querySelector('#ai-combo-modal');
      if (modal) modal.classList.remove('active');
    }

    if (e.target.closest('#btn-upsell-add') && currentUpsellDish) {
      const found = cart.find(x => x.name === currentUpsellDish.name);
      if (found) found.qty++;
      else cart.push({ name: currentUpsellDish.name, price: currentUpsellDish.price, qty: 1 });
      saveCart();

      const btn = document.querySelector('#btn-upsell-add');
      if (btn) {
        btn.textContent = '✓ Đã thêm';
        btn.style.background = '#10b981';
      }
      notify(`Đã thêm "${currentUpsellDish.name}" vào giỏ hàng`);

      setTimeout(() => {
        hideAiUpsell();
      }, 1000);
    }

    if (e.target.closest('#btn-upsell-close')) {
      hideAiUpsell();
    }

    // Mở / Đóng cửa sổ Chatbot AI từ nút bong bóng
    if (e.target.closest('#ai-assistant-bubble')) {
      window.toggleAiChat();
    }

    if (e.target.closest('#btn-close-chat')) {
      window.toggleAiChat(false);
    }

    // Xóa lịch sử cuộc trò chuyện
    if (e.target.closest('#btn-clear-chat')) {
      const chatBody = document.querySelector('#ai-chat-body');
      if (chatBody) {
        chatBody.innerHTML = `
          <div class="ai-msg bot">
            <div class="ai-msg-avatar">🤖</div>
            <div class="ai-msg-content">
              <p>Xin chào! Tôi là Trợ lý AI của nhà hàng SmartDine. Bạn có thể gõ hoặc bấm <b>Micro 🎤</b> để hỏi tôi món ăn theo sở thích, số lượng người hoặc khẩu vị nhé!</p>
              <div class="ai-quick-tags">
                <button type="button" class="ai-quick-tag" data-quick-text="Tìm món cho trẻ nhỏ ăn được">👶 Món cho trẻ nhỏ</button>
                <button type="button" class="ai-quick-tag" data-quick-text="Tìm món thanh đạm không cay">🥗 Thanh đạm, không cay</button>
                <button type="button" class="ai-quick-tag" data-quick-text="Bàn 2 người nên gọi gì?">👥 Bàn 2 người</button>
                <button type="button" class="ai-quick-tag" data-quick-text="Gợi ý mâm tiệc khoảng 500k">💎 Mâm tiệc 500k</button>
              </div>
            </div>
          </div>
        `;
      }
    }

    // Bấm câu hỏi gợi ý nhanh trong khung chat
    const quickTag = e.target.closest('.ai-quick-tag');
    if (quickTag) {
      const qText = quickTag.dataset.quickText;
      if (qText) sendAiChatMessage(qText);
    }

    // Thêm món vào giỏ hàng ngay từ thẻ món trong chat
    const chatAdd = e.target.closest('[data-chat-add]');
    if (chatAdd) {
      const name = chatAdd.dataset.chatAdd;
      const price = Number(chatAdd.dataset.chatPrice);
      const found = cart.find(x => x.name === name);
      if (found) found.qty++;
      else cart.push({ name, price, qty: 1 });
      saveCart();

      chatAdd.textContent = '✓ Đã thêm';
      chatAdd.style.background = '#10b981';
      setTimeout(() => {
        chatAdd.textContent = '+ Thêm vào giỏ';
        chatAdd.style.background = '';
      }, 1500);

      notify(`Đã thêm "${name}" vào giỏ hàng`);
      if (typeof triggerAiUpsell === 'function') {
        triggerAiUpsell(name);
      }
    }
  });

  // Khởi tạo xử lý Form chat và Nhận diện giọng nói
  initAiChatForm();
}

// ==========================================================
// CHATBOT AI VÀ NHẬN DIỆN GIỌNG NÓI (VOICE RECOGNITION)
// ==========================================================
let speechRecognition = null;
let isVoiceRecording = false;

function initAiChatForm() {
  const form = document.querySelector('#ai-chat-form');
  const input = document.querySelector('#ai-chat-input');
  const micBtn = document.querySelector('#btn-chat-mic');
  const voiceBar = document.querySelector('#ai-voice-bar');
  const voiceCancelBtn = document.querySelector('#btn-cancel-voice');

  if (form && input) {
    form.addEventListener('submit', e => {
      e.preventDefault();
      const text = input.value.trim();
      if (!text) return;
      input.value = '';
      sendAiChatMessage(text);
    });
  }

  // Khởi tạo Web Speech API (nhận diện giọng nói tiếng Việt)
  const SpeechRec = window.SpeechRecognition || window.webkitSpeechRecognition;
  if (SpeechRec && micBtn) {
    try {
      speechRecognition = new SpeechRec();
      speechRecognition.lang = 'vi-VN';
      speechRecognition.continuous = false;
      speechRecognition.interimResults = false;

      speechRecognition.onstart = () => {
        isVoiceRecording = true;
        micBtn.classList.add('recording');
        if (voiceBar) voiceBar.style.display = 'flex';
      };

      speechRecognition.onresult = event => {
        const transcript = event.results[0][0].transcript;
        if (transcript) {
          if (input) input.value = transcript;
          sendAiChatMessage(transcript);
        }
      };

      speechRecognition.onerror = event => {
        notify('Không nhận được giọng nói hoặc micro bị từ chối');
        stopVoiceRecognition();
      };

      speechRecognition.onend = () => {
        stopVoiceRecognition();
      };

      micBtn.addEventListener('click', () => {
        if (!isVoiceRecording) {
          try {
            speechRecognition.start();
          } catch (err) {
            speechRecognition.stop();
          }
        } else {
          stopVoiceRecognition();
        }
      });

      if (voiceCancelBtn) {
        voiceCancelBtn.addEventListener('click', stopVoiceRecognition);
      }
    } catch (e) {
      console.warn('Speech recognition init error:', e);
    }
  } else if (micBtn) {
    micBtn.addEventListener('click', () => {
      notify('Trình duyệt chưa hỗ trợ Micro, bạn hãy gõ câu hỏi vào ô chat nhé!');
    });
  }
}

function stopVoiceRecognition() {
  if (speechRecognition && isVoiceRecording) {
    try { speechRecognition.stop(); } catch (e) {}
  }
  isVoiceRecording = false;
  const micBtn = document.querySelector('#btn-chat-mic');
  const voiceBar = document.querySelector('#ai-voice-bar');
  if (micBtn) micBtn.classList.remove('recording');
  if (voiceBar) voiceBar.style.display = 'none';
}

async function sendAiChatMessage(text) {
  const chatBody = document.querySelector('#ai-chat-body');
  if (!chatBody) return;

  // 1. Thêm tin nhắn của khách vào khung chat
  const userMsgEl = document.createElement('div');
  userMsgEl.className = 'ai-msg user';
  userMsgEl.innerHTML = `
    <div class="ai-msg-content">
      <p>${escapeHtml(text)}</p>
    </div>
  `;
  chatBody.appendChild(userMsgEl);

  // 2. Thêm bong bóng đang suy nghĩ của Bot
  const typingId = 'ai-typing-' + Date.now();
  const typingEl = document.createElement('div');
  typingEl.className = 'ai-msg bot';
  typingEl.id = typingId;
  typingEl.innerHTML = `
    <div class="ai-msg-avatar">🤖</div>
    <div class="ai-msg-content">
      <p style="color: #64748b; font-style: italic;">
        <span style="display:inline-block; animation: ai-pulse 1s infinite;">●</span> AI đang phân tích thực đơn...
      </p>
    </div>
  `;
  chatBody.appendChild(typingEl);
  chatBody.scrollTop = chatBody.scrollHeight;

  try {
    const res = await fetch('api/ai-chat.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ message: text, cart: cart })
    });

    const data = await res.json();
    const currentTyping = document.getElementById(typingId);
    if (currentTyping) currentTyping.remove();

    if (data.success) {
      const botMsgEl = document.createElement('div');
      botMsgEl.className = 'ai-msg bot';

      let dishesHtml = '';
      if (Array.isArray(data.dishes) && data.dishes.length > 0) {
        dishesHtml = `
          <div class="ai-chat-dishes-wrap">
            ${data.dishes.map(dish => `
              <div class="ai-chat-dish-card">
                <div class="ai-chat-dish-left">
                  <img src="${dish.image || 'assets/images/dish-placeholder.svg'}" alt="${dish.name}" class="ai-chat-dish-img" onerror="this.src='assets/images/dish-placeholder.svg'">
                  <div class="ai-chat-dish-text">
                    <span class="ai-chat-dish-name">${escapeHtml(dish.name)}</span>
                    <span class="ai-chat-dish-price">${money(dish.price)}</span>
                  </div>
                </div>
                <button type="button" class="btn-chat-add-dish" data-chat-add="${escapeHtml(dish.name)}" data-chat-price="${dish.price}">
                  + Thêm vào giỏ
                </button>
              </div>
            `).join('')}
          </div>
        `;
      }

      botMsgEl.innerHTML = `
        <div class="ai-msg-avatar">🤖</div>
        <div class="ai-msg-content">
          <p>${escapeHtml(data.reply)}</p>
          ${dishesHtml}
        </div>
      `;
      chatBody.appendChild(botMsgEl);
    } else {
      showBotError(chatBody, typingId, data.message || 'Chưa nhận diện được món ăn phù hợp.');
    }
  } catch (err) {
    showBotError(chatBody, typingId, 'Không thể kết nối đến máy chủ AI, bạn thử lại sau nhé!');
  }

  chatBody.scrollTop = chatBody.scrollHeight;
}

function showBotError(chatBody, typingId, errText) {
  const currentTyping = document.getElementById(typingId);
  if (currentTyping) currentTyping.remove();

  const errEl = document.createElement('div');
  errEl.className = 'ai-msg bot';
  errEl.innerHTML = `
    <div class="ai-msg-avatar">🤖</div>
    <div class="ai-msg-content">
      <p style="color: #ef4444;">${escapeHtml(errText)}</p>
    </div>
  `;
  chatBody.appendChild(errEl);
  chatBody.scrollTop = chatBody.scrollHeight;
}

function escapeHtml(str) {
  if (!str) return '';
  return String(str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}

// Khởi chạy khi tài liệu sẵn sàng
document.addEventListener('DOMContentLoaded', () => {
  renderCartSummary();
  renderCartPage();
  initKDS();
  initFloorFilters();
  initTrackingSimulator();
  initAiSmartOrdering();
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
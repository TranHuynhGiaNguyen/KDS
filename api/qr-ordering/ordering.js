(() => {
  'use strict';

  const boot = JSON.parse(
    document.querySelector('#qo-boot').textContent
  );

  const content = document.querySelector('#content');
  const dialog = document.querySelector('#dish-dialog');

  const money = value =>
    new Intl.NumberFormat('vi-VN').format(Number(value)) + 'đ';

  const labels = {
    submitted: 'Đã gửi',
    pending: 'Chờ chế biến',
    preparing: 'Đang chế biến',
    ready: 'Chờ phục vụ',
    served: 'Đã phục vụ',
    cancelled: 'Đã hủy'
  };

  const categories = {
    all: 'Tất cả',
    side: 'Món khai vị',
    main: 'Món chính',
    drink: 'Đồ uống',
    dessert: 'Tráng miệng'
  };

  let state = null;
  let orders = [];
  let selected = null;
  let quantity = 1;
  let busy = false;
  let category = 'all';
  let search = '';
  let history = false;
  let tableFilter = '';

  const pendingKey = 'qo_pending_' + boot.ctx;
  const noteKey = 'qo_note_' + boot.ctx;

  let draftNote = '';

  try {
    draftNote = sessionStorage.getItem(noteKey) || '';
  } catch {}

  function el(tag, text = '', cls = '') {
    const element = document.createElement(tag);
    element.textContent = text;
    element.className = cls;
    return element;
  }

  function button(text, handler, cls = 'secondary') {
    const element = el('button', text, cls);
    element.type = 'button';
    element.addEventListener('click', handler);
    return element;
  }

  function link(text, view, cls = '') {
    const element = el('a', text, cls);
    element.href = url(view);
    return element;
  }

  function url(view) {
    const value = new URL('ordering.php', location.href);

    value.searchParams.set('view', view);

    if (boot.ctx) {
      value.searchParams.set('ctx', boot.ctx);
    }

    return value.href;
  }

  function notify(text, error = false) {
    const box = document.querySelector('#notice');

    box.textContent = text;
    box.hidden = !text;
    box.className = error ? 'notice error' : 'notice';
  }

  function photo(dish, cls = 'food-photo') {
    const box = el('div', '🍽', cls);
    const src = dish?.image_url;

    if (!src || typeof src !== 'string') {
      return box;
    }

    try {
      const value = new URL(src, location.href);

      if (!['http:', 'https:'].includes(value.protocol)) {
        return box;
      }

      const image = document.createElement('img');

      image.src = value.href;
      image.alt = dish.name || dish.item_name || 'Món ăn';
      image.loading = 'lazy';
      image.referrerPolicy = 'no-referrer';
      image.onerror = () => image.remove();

      box.append(image);
    } catch {}

    return box;
  }

  async function api(action, data) {
    const target = new URL('ordering.php', location.href);

    target.searchParams.set('api', action);

    if (boot.ctx) {
      target.searchParams.set('ctx', boot.ctx);
    }

    const options = {
      credentials: 'same-origin',
      cache: 'no-store'
    };

    if (data !== undefined) {
      options.method = 'POST';

      options.headers = {
        'Content-Type': 'application/json'
      };

      options.body = JSON.stringify({
        ...data,
        csrf: boot.csrf
      });
    }

    const response = await fetch(target, options);

    let result;

    try {
      result = await response.json();
    } catch {
      throw new Error(
        'Không đọc được phản hồi máy chủ. Kiểm tra PHP/MySQL.'
      );
    }

    if (!response.ok || !result.success) {
      const error = new Error(
        result.message || 'Không xử lý được yêu cầu.'
      );

      error.status = response.status;

      throw error;
    }

    return result;
  }

  function updateCartBar() {
    const cart = state?.cart || {
      items: [],
      subtotal: 0
    };

    const count = cart.items.reduce(
      (sum, item) => sum + item.qty,
      0
    );

    const bar = document.querySelector('#floating-cart');

    if (!bar) return;

    bar.href = url('cart');
    bar.hidden = boot.view !== 'menu' || !count;

    document.querySelector('#floating-count').textContent = count;

    document.querySelector('#floating-total').textContent =
      money(cart.subtotal);

    const clear = document.querySelector('#clear-cart');

    if (clear) {
      clear.disabled =
        busy ||
        !count ||
        state?.context.session_status !== 'open';
    }
  }

  function summary(total) {
    const box = el('section', '', 'summary');
    const fee = Math.round(total * 0.05);

    const rows = [
      ['Tạm tính', total],
      ['Phí phục vụ (5%)', fee],
      ['Tổng cộng', total + fee]
    ];

    rows.forEach(([name, value]) => {
      const row = el('div', '', 'summary-row');

      row.append(
        el('span', name),
        el('strong', money(value))
      );

      box.append(row);
    });

    return box;
  }

  function renderMenu() {
    content.replaceChildren();

    const searchBox = el('label', '', 'search-box');
    const input = document.createElement('input');

    input.type = 'search';
    input.placeholder = 'Tìm món ăn…';
    input.setAttribute('aria-label', 'Tìm món ăn');
    input.value = search;

    searchBox.append(
      el('span', '⌕'),
      input
    );

    const layout = el('div', '', 'menu-layout');
    const aside = el('aside', '', 'categories');

    Object.entries(categories).forEach(([key, name]) => {
      aside.append(
        button(
          name,
          () => {
            category = key;
            renderMenu();
          },
          'category' + (key === category ? ' active' : '')
        )
      );
    });

    const section = el('section', '', 'menu-products');
    const grid = el('div', '', 'food-grid');

    section.append(
      el('h2', 'Món nổi bật'),
      grid
    );

    function renderCards() {
      grid.replaceChildren();

      const list = state.menu.filter(dish =>
        (
          category === 'all' ||
          dish.category === category
        ) &&
        dish.name
          .toLocaleLowerCase('vi')
          .includes(search.toLocaleLowerCase('vi'))
      );

      if (!list.length) {
        grid.append(
          el('p', 'Chưa có món phù hợp.', 'empty')
        );
      }

      list.forEach(dish => {
        const card = el('article', '', 'food-card');

        const open = button(
          '',
          () => openDish(dish),
          'food-open'
        );

        open.setAttribute(
          'aria-label',
          'Xem chi tiết ' + dish.name
        );

        open.append(
          photo(dish),
          el('h3', dish.name)
        );

        const footer = el('div', '', 'food-footer');

        const add = button(
          dish.is_available ? '+' : 'Hết món',
          () => openDish(dish),
          'add-food'
        );

        add.disabled =
          !dish.is_available ||
          state.context.session_status !== 'open';

        add.setAttribute(
          'aria-label',
          'Chọn ' + dish.name
        );

        footer.append(
          el('strong', money(dish.price)),
          add
        );

        card.append(open, footer);
        grid.append(card);
      });
    }

    input.addEventListener('input', () => {
      search = input.value;
      renderCards();
    });

    layout.append(aside, section);
    content.append(searchBox, layout);

    renderCards();
  }

  function renderCart() {
    content.replaceChildren();

    const cart = state.cart;
    const list = el('section', '', 'cart-items');

    if (!cart.items.length) {
      list.append(
        el('p', 'Giỏ hàng đang trống.', 'empty')
      );
    }

    cart.items.forEach(item => {
      const dish = state.menu.find(
        d => d.id === item.menu_item_id
      );

      const row = el('article', '', 'cart-row');
      const info = el('div', '', 'cart-info');

      info.append(
        el('h3', item.name),
        el('small', money(item.price))
      );

      if (item.toppings.length) {
        info.append(
          el(
            'small',
            item.toppings.map(t => t.name).join(', '),
            'muted'
          )
        );
      }

      if (item.note) {
        info.append(
          el('small', item.note, 'muted')
        );
      }

      const controls = el('div', '', 'qty');

      const minus = button(
        '−',
        () => change(
          item.qty === 1 ? 'remove' : 'quantity',
          {
            item_id: item.id,
            quantity: item.qty - 1
          }
        ),
        ''
      );

      const plus = button(
        '+',
        () => change('quantity', {
          item_id: item.id,
          quantity: item.qty + 1
        }),
        ''
      );

      minus.setAttribute(
        'aria-label',
        'Giảm ' + item.name
      );

      plus.setAttribute(
        'aria-label',
        'Tăng ' + item.name
      );

      minus.disabled =
        busy ||
        state.context.session_status !== 'open';

      plus.disabled =
        minus.disabled ||
        item.qty >= 99;

      controls.append(
        minus,
        el('b', item.qty),
        plus
      );

      row.append(
        photo(dish || item, 'cart-photo'),
        info,
        controls
      );

      list.append(row);
    });

    const label = el(
      'label',
      'Ghi chú cho nhà hàng',
      'field'
    );

    const note = document.createElement('textarea');

    note.id = 'cart-order-note';
    note.maxLength = 1000;
    note.placeholder = 'Ví dụ: Không cay, ít đường…';
    note.value = draftNote;

    note.addEventListener('input', () => {
      draftNote = note.value;

      try {
        sessionStorage.setItem(noteKey, draftNote);
      } catch {}
    });

    label.append(note);

    let pending = false;

    try {
      pending = !!sessionStorage.getItem(pendingKey);
    } catch {}

    const submit = button(
      pending ? 'Xác minh lần gửi trước' : 'Xác nhận đơn',
      sendOrder,
      'primary wide'
    );

    submit.disabled =
      busy ||
      (!cart.items.length && !pending) ||
      (
        state.context.session_status !== 'open' &&
        !pending
      );

    content.append(
      list,
      label,
      summary(cart.subtotal),
      submit
    );
  }

  function availableToppings(dish) {
    if (Array.isArray(dish.toppings)) {
      return dish.toppings;
    }

    if (!['main', 'side'].includes(dish.category)) {
      return [];
    }

    return Object.entries(state.toppings || {}).map(
      ([name, price]) => ({name, price})
    );
  }

  function openDish(dish) {
    if (busy) return;

    selected = dish;
    quantity = 1;

    document.querySelector('#dish-title').textContent =
      dish.name;

    document.querySelector('#dish-description').textContent =
      dish.description ||
      'Nhà hàng đang cập nhật mô tả món ăn.';

    document.querySelector('#dish-price').textContent =
      money(dish.price);

    document.querySelector('#dish-note').value = '';

    document.querySelector('#dish-photo').replaceChildren(
      photo(dish)
    );

    const toppings = document.querySelector('#dish-toppings');

    toppings.replaceChildren(
      el('legend', 'Topping thêm')
    );

    const list = availableToppings(dish);

    toppings.hidden = !list.length;

    list.forEach(topping => {
      const label = el('label', '', 'topping-row');
      const input = document.createElement('input');

      input.type = 'checkbox';
      input.value = topping.name;
      input.dataset.price = topping.price;

      input.addEventListener('change', dishTotal);

      label.append(
        input,
        el('span', topping.name),
        el('strong', '+' + money(topping.price))
      );

      toppings.append(label);
    });

    dishTotal();
    dialog.showModal();
  }

  function dishTotal() {
    if (!selected) return;

    const extra = [
      ...document.querySelectorAll(
        '#dish-toppings input:checked'
      )
    ].reduce(
      (sum, input) => sum + Number(input.dataset.price),
      0
    );

    document.querySelector('#dish-qty').textContent =
      quantity;

    const add = document.querySelector('#dish-add');

    add.disabled =
      busy ||
      !selected.is_available ||
      state.context.session_status !== 'open';

    add.textContent = selected.is_available
      ? 'Thêm vào giỏ · ' +
        money((selected.price + extra) * quantity)
      : 'Món tạm hết';
  }

  async function change(action, payload = {}) {
    if (busy || !state) return;

    busy = true;

    try {
      const result = await api(action, {
        ...payload,
        revision: state.cart.revision
      });

      state = result.state;

      if (action === 'add') {
        dialog.close();
      }

      notify('');

    } catch (error) {
      notify(error.message, true);

      try {
        state = (await api('cart')).state;
      } catch {}

    } finally {
      busy = false;
      render();
    }
  }

  async function sendOrder() {
    if (busy || !state) return;

    busy = true;

    try {
      let pending = JSON.parse(
        sessionStorage.getItem(pendingKey) || 'null'
      );

      if (!pending) {
        if (!state.cart.items.length) {
          throw new Error('Giỏ hàng đang trống.');
        }

        const bytes = crypto.getRandomValues(
          new Uint8Array(16)
        );

        pending = {
          request_id: Array.from(
            bytes,
            b => b.toString(16).padStart(2, '0')
          ).join(''),

          revision: state.cart.revision,
          order_note: draftNote.trim()
        };

        sessionStorage.setItem(
          pendingKey,
          JSON.stringify(pending)
        );
      }

      await api('submit', pending);

      sessionStorage.removeItem(pendingKey);
      sessionStorage.removeItem(noteKey);

      location.href = url('tracking');

    } catch (error) {
      if ([400, 409, 413, 415, 422].includes(error.status)) {
        try {
          sessionStorage.removeItem(pendingKey);
        } catch {}
      }

      notify(
        error.message +
        (
          !error.status || error.status >= 500
            ? ' Nhấn xác minh lần gửi trước nếu bị mất phản hồi.'
            : ''
        ),
        true
      );

      try {
        state = (await api('cart')).state;
      } catch {}

    } finally {
      busy = false;
      render();
    }
  }

  function orderItem(item) {
    const dish = state?.menu.find(
      d => d.id === Number(item.menu_item_id)
    );

    const row = el('article', '', 'order-item');
    const info = el('div', '', 'order-item-info');

    info.append(
      el('h3', item.item_name),
      el(
        'small',
        item.quantity + ' × ' + money(item.unit_price)
      )
    );

    if (item.toppings.length) {
      info.append(
        el(
          'small',
          item.toppings.map(t => t.name).join(', '),
          'muted'
        )
      );
    }

    if (item.note) {
      info.append(
        el('small', item.note, 'muted')
      );
    }

    row.append(
      photo(dish || item, 'cart-photo'),
      info,
      el(
        'small',
        labels[item.status] || item.status,
        'status ' + item.status
      )
    );

    return row;
  }

  function renderDetails() {
    content.replaceChildren();

    const head = el('section', '', 'table-heading');
    const line = el('div', '', 'table-title');

    line.append(
      el('h1', 'Bàn ' + state.context.table_code),
      el(
        'span',
        state.context.session_status === 'open'
          ? 'Đang phục vụ'
          : 'Đã kết thúc',
        'muted'
      )
    );

    head.append(
      line,
      el(
        'small',
        'Thời gian vào: ' +
          (state.context.opened_at || '—'),
        'muted'
      )
    );

    const tabs = el('div', '', 'tabs');

    tabs.append(
      button(
        'Đơn hàng hiện tại',
        () => {
          history = false;
          renderDetails();
        },
        history ? '' : 'active'
      ),

      button(
        'Lịch sử gọi món',
        () => {
          history = true;
          renderDetails();
        },
        history ? 'active' : ''
      )
    );

    const list = el('section', '', 'order-list');

    if (!orders.length) {
      list.append(
        el('p', 'Bạn chưa gửi đơn hàng.', 'empty')
      );
    }

    const display = history
      ? orders
      : [...orders].reverse();

    display.forEach(order => {
      if (history) {
        list.append(
          el(
            'h2',
            order.order_code + ' · ' + order.created_at,
            'history-heading'
          )
        );
      }

      if (order.order_note) {
        list.append(
          el('p', order.order_note, 'order-note')
        );
      }

      order.items.forEach(item => {
        list.append(orderItem(item));
      });
    });

    const total = orders.reduce(
      (sum, order) => sum + Number(order.subtotal),
      0
    );

    const actions = el('div', '', 'two-actions');

    actions.append(
      link('Gọi thêm món', 'menu', 'secondary'),
      button('Thanh toán', payment, 'primary')
    );

    content.append(
      head,
      tabs,
      list,
      summary(total),
      actions
    );
  }

  function renderTracking() {
    content.replaceChildren();

    if (!orders.length) {
      content.append(
        el('p', 'Bạn chưa gửi đơn hàng.', 'empty')
      );
    }

    orders.forEach(order => {
      const card = el('section', '', 'tracking-card');
      const progress = el('ol', '', 'progress');

      const stage = {
        submitted: 0,
        preparing: 1,
        ready: 2,
        served: 3
      }[order.status];

      if (order.status !== 'cancelled') {
        [
          'Đã gửi',
          'Đang chế biến',
          'Đang phục vụ',
          'Hoàn thành'
        ].forEach((name, index) => {
          const step = el(
            'li',
            name,
            index <= stage ? 'done' : ''
          );

          step.prepend(
            el('span', String(index + 1))
          );

          progress.append(step);
        });
      }

      const info = el('div', '', 'tracking-info');

      info.append(
        el('strong', 'Mã đơn: ' + order.order_code),
        el(
          'small',
          'Thời gian: ' + order.created_at,
          'muted'
        )
      );

      if (order.status === 'cancelled') {
        info.append(
          el('small', 'Đơn đã hủy', 'status cancelled')
        );
      }

      if (order.order_note) {
        info.append(
          el('p', order.order_note, 'order-note')
        );
      }

      order.items.forEach(item => {
        info.append(orderItem(item));
      });

      card.append(progress, info);
      content.append(card);
    });

    content.append(
      link(
        'Xem chi tiết đơn hàng',
        'details',
        'secondary wide'
      )
    );
  }

  function itemActions(item) {
    const result = [];

    if (['admin', 'kitchen'].includes(boot.role)) {
      if (item.status === 'pending') {
        result.push(['preparing', 'Bắt đầu chế biến']);
      }

      if (item.status === 'preparing') {
        result.push(['ready', 'Báo món đã xong']);
      }
    }

    if (['admin', 'staff'].includes(boot.role)) {
      if (item.status === 'ready') {
        result.push(['served', 'Đã phục vụ']);
      }

      if (item.status === 'pending') {
        result.push(['cancelled', 'Hủy món']);
      }
    }

    return result;
  }

  async function changeStatus(item, next) {
    if (busy) return;

    if (
      next === 'cancelled' &&
      !confirm('Hủy món chưa chế biến này?')
    ) {
      return;
    }

    busy = true;

    try {
      const result = await api('status', {
        item_id: item.id,
        expected_status: item.status,
        status: next
      });

      orders = result.orders;

    } catch (error) {
      notify(error.message, true);

    } finally {
      busy = false;
      renderOperations();
    }
  }

  function renderOperations() {
    content.replaceChildren(
      el('h1', 'Đơn gọi món QR')
    );

    const tools = el('div', '', 'operations-tools');
    const select = document.createElement('select');

    const all = el('option', 'Tất cả bàn');
    all.value = '';

    select.append(all);

    [
      ...new Set(orders.map(order => order.table_code))
    ].sort().forEach(table => {
      const option = el('option', table);

      option.value = table;
      select.append(option);
    });

    select.value = tableFilter;

    select.onchange = () => {
      tableFilter = select.value;
      renderOperations();
    };

    tools.append(select);

    if (['admin', 'staff'].includes(boot.role)) {
      const qr = el('a', 'QR theo bàn', 'secondary');

      qr.href = 'qr-codes.php';
      tools.append(qr);
    }

    const grid = el('div', '', 'operations-grid');

    orders.filter(order =>
      !tableFilter ||
      order.table_code === tableFilter
    ).forEach(order => {
      const card = el('section', '', 'tracking-info');

      card.append(
        el('h2', 'Bàn ' + order.table_code),
        el(
          'small',
          order.order_code + ' · ' + order.created_at,
          'muted'
        )
      );

      if (order.order_note) {
        card.append(
          el('p', order.order_note, 'order-note')
        );
      }

      order.items.forEach(item => {
        card.append(orderItem(item));

        const row = el('div', '', 'action-row');

        if (order.session_status === 'open') {
          itemActions(item).forEach(([next, label]) => {
            row.append(
              button(
                label,
                () => changeStatus(item, next)
              )
            );
          });
        }

        card.append(row);
      });

      card.append(
        el('strong', money(order.subtotal))
      );

      grid.append(card);
    });

    content.append(tools, grid);
  }

  function payment() {
    notify(
      'Vui lòng liên hệ nhân viên phục vụ để thanh toán hóa đơn của bàn.'
    );
  }

  function render() {
    if (boot.view === 'operations') {
      renderOperations();

    } else if (!state) {
      content.replaceChildren(
        el(
          'p',
          'Chưa đọc được dữ liệu. Vui lòng tải lại trang.',
          'empty'
        )
      );

    } else if (boot.view === 'cart') {
      renderCart();

    } else if (boot.view === 'details') {
      renderDetails();

    } else if (boot.view === 'tracking') {
      renderTracking();

    } else {
      renderMenu();
    }

    updateCartBar();
    content.setAttribute('aria-busy', 'false');
  }

  async function refresh() {
    if (
      busy ||
      document.hidden ||
      dialog.open ||
      ['INPUT', 'TEXTAREA', 'SELECT'].includes(
        document.activeElement?.tagName
      )
    ) {
      return;
    }

    busy = true;

    try {
      if (boot.view === 'operations') {
        orders = (await api('queue')).orders;

      } else {
        state = (await api('cart')).state;

        if (['tracking', 'details'].includes(boot.view)) {
          orders = (await api('orders')).orders;
        }
      }

      notify('');

    } catch (error) {
      notify(error.message, true);

    } finally {
      busy = false;
      render();
    }
  }

  document.querySelectorAll('[data-view]').forEach(a => {
    a.href = url(a.dataset.view);

    a.classList.toggle(
      'active',
      !a.hasAttribute('data-add-more') &&
      (
        a.dataset.view === boot.view ||
        (
          a.dataset.view === 'details' &&
          boot.view === 'tracking'
        )
      )
    );
  });

  document.querySelectorAll('[data-payment]').forEach(b => {
    b.addEventListener('click', payment);
  });

  document.querySelector('#close-dialog').onclick = () => {
    dialog.close();
  };

  document.querySelector('#dish-minus').onclick = () => {
    quantity = Math.max(1, quantity - 1);
    dishTotal();
  };

  document.querySelector('#dish-plus').onclick = () => {
    quantity = Math.min(99, quantity + 1);
    dishTotal();
  };

  document.querySelector('#dish-form').onsubmit = event => {
    event.preventDefault();

    if (!selected) return;

    void change('add', {
      menu_item_id: selected.id,
      quantity,
      note: document.querySelector('#dish-note').value.trim(),

      toppings: [
        ...document.querySelectorAll(
          '#dish-toppings input:checked'
        )
      ].map(input => input.value)
    });
  };

  document.querySelector('#clear-cart')
    ?.addEventListener('click', () => {
      if (
        state?.cart.items.length &&
        confirm('Xóa toàn bộ món trong giỏ?')
      ) {
        void change('clear');
      }
    });

  void refresh();

  setInterval(() => {
    void refresh();
  }, 4000);
})();
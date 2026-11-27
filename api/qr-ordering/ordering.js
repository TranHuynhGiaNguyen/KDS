(() => {
  'use strict';

  const boot = JSON.parse(
    document.querySelector('#qo-boot').textContent
  );

  const content = document.querySelector('#content');
  const dialog = document.querySelector('#dish-dialog');

  const money = value =>
    new Intl.NumberFormat('vi-VN').format(Number(value)) + '\u0111';

  const labels = {
    submitted: '\u0110\xe3 g\u1eedi',
    pending: '\u0110ang ch\u1ebf bi\u1ebfn',
    preparing: '\u0110ang ch\u1ebf bi\u1ebfn',
    ready: 'Ho\u00e0n th\u00e0nh',
    served: 'Ho\u00e0n th\u00e0nh',
    cancelled: '\u0110\xe3 h\u1ee7y'
  };

  const categories = {
    all: 'T\u1ea5t c\u1ea3',
    side: 'M\xf3n khai v\u1ecb',
    main: 'M\xf3n ch\xednh',
    drink: '\u0110\u1ed3 u\u1ed1ng',
    dessert: 'Tr\xe1ng mi\u1ec7ng'
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
    const box = el('div', '\u25c7', cls);
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
      image.alt = dish.name || dish.item_name || 'M\xf3n \u0103n';
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
        'Kh\xf4ng \u0111\u1ecdc \u0111\u01b0\u1ee3c ph\u1ea3n h\u1ed3i m\xe1y ch\u1ee7. Ki\u1ec3m tra PHP/MySQL.'
      );
    }

    if (!response.ok || !result.success) {
      const error = new Error(
        result.message || 'Kh\xf4ng x\u1eed l\xfd \u0111\u01b0\u1ee3c y\xeau c\u1ea7u.'
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
    content.classList.toggle('has-cart', !bar.hidden);

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
      ['T\u1ea1m t\xednh', total],
      ['Ph\xed ph\u1ee5c v\u1ee5 (5%)', fee],
      ['T\u1ed5ng c\u1ed9ng', total + fee]
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

  function renderMenu(resetScroll = false) {
    const scrollTop = resetScroll
      ? 0
      : content.querySelector('.food-grid')?.scrollTop || 0;
    content.replaceChildren();

    const searchBox = el('label', '', 'search-box');
    const input = document.createElement('input');

    input.type = 'search';
    input.placeholder = 'T\xecm m\xf3n \u0103n\u2026';
    input.setAttribute('aria-label', 'T\xecm m\xf3n \u0103n');
    input.value = search;

    searchBox.append(
      el('span', '\u2315'),
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
            renderMenu(true);
          },
          'category' + (key === category ? ' active' : '')
        )
      );
    });

    const section = el('section', '', 'menu-products');
    const grid = el('div', '', 'food-grid');

    section.append(
      el('h2', 'M\xf3n n\u1ed5i b\u1eadt'),
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
          el('p', 'Ch\u01b0a c\xf3 m\xf3n ph\xf9 h\u1ee3p.', 'empty')
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
          'Xem chi ti\u1ebft ' + dish.name
        );

        open.append(
          photo(dish),
          el('h3', dish.name)
        );

        const price = el('strong', money(dish.price), 'food-price');
        open.append(price);
        if (!dish.is_available) {
          open.append(el('small', 'H\u1ebft m\u00f3n', 'muted'));
        }
        card.append(open);
        grid.append(card);
      });
    }

    input.addEventListener('input', () => {
      search = input.value;
      renderCards();
    });

    layout.append(aside, section);
    // Banner ch\xe0o m\u1eebng d\u01b0\u1edbi \xf4 t\xecm ki\u1ebfm.
const banner = el('section', '', 'qr-banner');

const bannerDish = state.menu.find(
  dish => dish.is_available && dish.image_url
) || state.menu[0];

const bannerText = el('div', '', 'qr-banner-text');

bannerText.append(
  el('h2', 'Welcome!'),
  el('p', 'Ch\xfac b\u1ea1n ngon mi\u1ec7ng c\xf9ng SmartDine')
);

banner.append(
  photo(bannerDish, 'qr-banner-image'),
  bannerText
);

// Th\u1ee9 t\u1ef1: t\xecm ki\u1ebfm \u2192 banner \u2192 danh m\u1ee5c v\xe0 menu.
content.append(searchBox, banner, layout);

    renderCards();
    grid.scrollTop = scrollTop;
  }

  function renderCart() {
    content.replaceChildren();

    const cart = state.cart;
    const list = el('section', '', 'cart-items');

    if (!cart.items.length) {
      list.append(
        el('p', 'Gi\u1ecf h\xe0ng \u0111ang tr\u1ed1ng.', 'empty')
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

      const controls = el('div', '', 'qty');

      const minus = button(
        '\u2212',
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
        'Gi\u1ea3m ' + item.name
      );

      plus.setAttribute(
        'aria-label',
        'T\u0103ng ' + item.name
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

    const notedItems = cart.items.filter(item => item.note);
    const notes = el('section', '', 'cart-dish-notes');
    if (notedItems.length) {
      notes.append(el('h3', 'Ghi ch\xfa t\u1eebng m\xf3n'));
      notedItems.forEach(item => {
        const row = el('div', '', 'cart-dish-note');
        row.append(
          el('strong', item.name + ' \xd7 ' + item.qty),
          el('p', item.note)
        );
        notes.append(row);
      });
    }

    let pending = false;

    try {
      pending = !!sessionStorage.getItem(pendingKey);
    } catch {}

    const submit = button(
      pending ? 'X\xe1c minh l\u1ea7n g\u1eedi tr\u01b0\u1edbc' : 'X\xe1c nh\u1eadn \u0111\u01a1n',
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
      ...(notedItems.length ? [notes] : []),
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
      'Nh\xe0 h\xe0ng \u0111ang c\u1eadp nh\u1eadt m\xf4 t\u1ea3 m\xf3n \u0103n.';

    document.querySelector('#dish-price').textContent =
      money(dish.price);

    document.querySelector('#dish-note').value = '';

    document.querySelector('#dish-photo').replaceChildren(
      photo(dish)
    );

    const toppings = document.querySelector('#dish-toppings');

    toppings.replaceChildren(
      el('legend', 'Topping th\xeam')
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
      ? 'Th\xeam v\xe0o gi\u1ecf \xb7 ' +
        money((selected.price + extra) * quantity)
      : 'M\xf3n t\u1ea1m h\u1ebft';
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
          throw new Error('Gi\u1ecf h\xe0ng \u0111ang tr\u1ed1ng.');
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
          order_note: ''
        };

        sessionStorage.setItem(
          pendingKey,
          JSON.stringify(pending)
        );
      }

      await api('submit', pending);

      sessionStorage.removeItem(pendingKey);


      location.href = url('details');

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
            ? ' Nh\u1ea5n x\xe1c minh l\u1ea7n g\u1eedi tr\u01b0\u1edbc n\u1ebfu b\u1ecb m\u1ea5t ph\u1ea3n h\u1ed3i.'
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
        item.quantity + ' \xd7 ' + money(item.unit_price)
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
      el('h1', 'B\xe0n ' + state.context.table_code),
      el(
        'span',
        state.context.session_status === 'open'
          ? '\u0110ang ph\u1ee5c v\u1ee5'
          : '\u0110\xe3 k\u1ebft th\xfac',
        'muted'
      )
    );

    head.append(
      line,
      el(
        'small',
        'Th\u1eddi gian v\xe0o: ' +
          (state.context.opened_at || '\u2014'),
        'muted'
      )
    );

    const tabs = el('div', '', 'tabs');

    tabs.append(
      button(
        '\u0110\u01a1n h\xe0ng hi\u1ec7n t\u1ea1i',
        () => {
          history = false;
          renderDetails();
        },
        history ? '' : 'active'
      ),

      button(
        'L\u1ecbch s\u1eed g\u1ecdi m\xf3n',
        () => {
          history = true;
          renderDetails();
        },
        history ? 'active' : ''
      )
    );

    const list = el('section', '', 'order-list');

    const display = history ? orders : [...orders].reverse();
    let visibleItems = 0;
    display.forEach(order => {
      const items = history ? order.items : order.items.filter(
        item => order.session_status === 'open' && item.kitchen_linked && item.status !== 'cancelled'
      );
      if (!items.length) return;
      visibleItems += items.length;
      if (history) {
        list.append(el('h2', order.order_code + ' \xb7 ' + order.created_at, 'history-heading'));
      }
      items.forEach(item => list.append(orderItem(item)));
    });
    if (!visibleItems) {
      list.append(el('p', history ? 'Ch\u01b0a c\xf3 l\u1ecbch s\u1eed g\u1ecdi m\xf3n.' : 'Hi\u1ec7n kh\xf4ng c\xf3 m\xf3n ch\u01b0a thanh to\xe1n.', 'empty'));
    }

    const actions = el('div', '', 'order-actions');
    if (state.context.session_status === 'open') {
      actions.append(link('G\u1ecdi th\xeam m\xf3n', 'menu', 'primary wide'));
    }

    content.append(head, tabs, list, actions);
  }

  function renderTracking() {
    content.replaceChildren();

    if (!orders.length) {
      content.append(
        el('p', 'B\u1ea1n ch\u01b0a g\u1eedi \u0111\u01a1n h\xe0ng.', 'empty')
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
          '\u0110\xe3 g\u1eedi',
          '\u0110ang ch\u1ebf bi\u1ebfn',
          '\u0110ang ph\u1ee5c v\u1ee5',
          'Ho\xe0n th\xe0nh'
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
        el('strong', 'M\xe3 \u0111\u01a1n: ' + order.order_code),
        el(
          'small',
          'Th\u1eddi gian: ' + order.created_at,
          'muted'
        )
      );

      if (order.status === 'cancelled') {
        info.append(
          el('small', '\u0110\u01a1n \u0111\xe3 h\u1ee7y', 'status cancelled')
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
        'Xem chi ti\u1ebft \u0111\u01a1n h\xe0ng',
        'details',
        'secondary wide'
      )
    );
  }

  function itemActions(item) {
    const result = [];

    if (['admin', 'kitchen'].includes(boot.role)) {
      if (item.status === 'pending') {
        result.push(['preparing', 'B\u1eaft \u0111\u1ea7u ch\u1ebf bi\u1ebfn']);
      }

      if (item.status === 'preparing') {
        result.push(['ready', 'B\xe1o m\xf3n \u0111\xe3 xong']);
      }
    }

    if (['admin', 'staff'].includes(boot.role)) {
      if (item.status === 'ready') {
        result.push(['served', '\u0110\xe3 ph\u1ee5c v\u1ee5']);
      }

      if (item.status === 'pending') {
        result.push(['cancelled', 'H\u1ee7y m\xf3n']);
      }
    }

    return result;
  }

  async function changeStatus(item, next) {
    if (busy) return;

    if (
      next === 'cancelled' &&
      !confirm('H\u1ee7y m\xf3n ch\u01b0a ch\u1ebf bi\u1ebfn n\xe0y?')
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
      el('h1', '\u0110\u01a1n g\u1ecdi m\xf3n QR')
    );

    const tools = el('div', '', 'operations-tools');
    const select = document.createElement('select');

    const all = el('option', 'T\u1ea5t c\u1ea3 b\xe0n');
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
      const qr = el('a', 'QR theo b\xe0n', 'secondary');

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
        el('h2', 'B\xe0n ' + order.table_code),
        el(
          'small',
          order.order_code + ' \xb7 ' + order.created_at,
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
      'Vui l\xf2ng li\xean h\u1ec7 nh\xe2n vi\xean ph\u1ee5c v\u1ee5 \u0111\u1ec3 thanh to\xe1n h\xf3a \u0111\u01a1n c\u1ee7a b\xe0n.'
    );
  }

  function render() {
    if (boot.view === 'operations') {
      renderOperations();

    } else if (!state) {
      content.replaceChildren(
        el(
          'p',
          'Ch\u01b0a \u0111\u1ecdc \u0111\u01b0\u1ee3c d\u1eef li\u1ec7u. Vui l\xf2ng t\u1ea3i l\u1ea1i trang.',
          'empty'
        )
      );

    } else if (boot.view === 'cart') {
      renderCart();

    } else if (boot.view === 'details') {
      renderDetails();

    } else if (boot.view === 'tracking') {
      renderDetails();

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
        confirm('X\xf3a to\xe0n b\u1ed9 m\xf3n trong gi\u1ecf?')
      ) {
        void change('clear');
      }
    });

  void refresh();

  setInterval(() => {
    void refresh();
  }, 2000);
})();
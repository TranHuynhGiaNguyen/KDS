(() => {
  const grid = document.querySelector('#restaurant-floor-grid');
  if (!grid) return;
  let busy = false;
  let lastSnapshot = '';
  function el(tag, cls, text) {
    const node = document.createElement(tag);
    node.className = cls;
    node.textContent = text;
    return node;
  }
  function updateTable(node, table) {
    if (table.status === 'maintenance') return;
    const occupied = !!table.is_occupied;
    const visual = node.classList.contains('visual-table-node');
    node.dataset.status = occupied ? 'occupied' : 'empty';
    const prefix = visual ? 'status-' : 'table-';
    node.classList.toggle(prefix + 'occupied', occupied);
    node.classList.toggle(prefix + 'empty', !occupied);
    const amount = new Intl.NumberFormat('vi-VN').format(Number(table.occupied_total)) + '\u0111';
    const count = table.occupied_items + ' m\u00f3n';
    const guests = Number(table.guest_count) > 0 ? Number(table.guest_count) + ' kh\u00e1ch \xb7 ' : '';
    if (visual) {
      node.replaceChildren(el('span', 'visual-table-code', table.code));
      node.append(el('span', occupied ? 'visual-table-items' : 'visual-table-seats', occupied ? count : table.capacity + ' ch\u1ed7'));
      if (occupied) node.append(el('span', 'visual-table-bill', amount));
    } else if (occupied) {
      const top = el('div', 'table-tile-top', '');
      top.append(el('b', 'table-name', 'B\u00e0n ' + table.code), el('span', 'table-guest-pill', guests + count));
      const bottom = el('div', 'table-tile-bottom', '');
      bottom.append(el('span', 'table-amount-label', 'S\u1ed1 ti\u1ec1n'), el('b', 'table-amount-value', amount));
      node.replaceChildren(top, bottom);
    } else {
      const body = el('div', 'table-empty-body', '');
      body.append(el('span', 'table-number-only', 'B\u00e0n ' + table.code));
      node.replaceChildren(body);
    }
  }
  async function refresh() {
    if (busy || document.hidden || document.querySelector('dialog[open]')) return;
    busy = true;
    try {
      const response = await fetch('api/table-live.php', {cache: 'no-store'});
      if (!response.ok) return;
      const data = await response.json();
      const snapshot = JSON.stringify(data.tables);
      if (snapshot === lastSnapshot) return;
      data.tables.forEach(table => {
        document.querySelectorAll('[data-table]').forEach(node => {
          if (node.dataset.table === table.code && (node.classList.contains('table-tile') || node.classList.contains('visual-table-node'))) updateTable(node, table);
        });
      });
      lastSnapshot = snapshot;
      document.querySelector('#floor-status-select')?.dispatchEvent(new Event('change'));
    } catch (error) {
      console.warn('table-live', error);
    } finally {
      busy = false;
    }
  }
  void refresh();
  setInterval(refresh, 2000);
})();

<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(WAREHOUSE_ALLOWED_ROLES);
require_once __DIR__ . '/../config.php';

$products = [];
$in_stock_count = 0;
$out_of_stock_count = 0;
$total_quantity = 0;
$errorMsg = get_flash('inventory_error') ?? get_flash('products_error') ?? '';
$successMsg = get_flash('inventory_success') ?? get_flash('products_success') ?? '';

$successParam = $_GET['success'] ?? '';
if ($successMsg === '' && $successParam === 'stock_in') {
    $successMsg = '✅ Nhập kho thành công!';
}

try {
    $result = $conn->query('SELECT id, name, sku, quantity FROM products ORDER BY id DESC');
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $qty = (int) $row['quantity'];
            if ($qty > 0) {
                $in_stock_count++;
            } else {
                $out_of_stock_count++;
            }
            $total_quantity += $qty;
            $products[] = $row;
        }
    }
} catch (Throwable $e) {
    error_log('inventory list error: ' . $e->getMessage());
    $errorMsg = 'Không thể tải dữ liệu tồn kho.';
}

$allowedFilters = ['all', 'in_stock', 'out_of_stock'];
$rawFilter = (string) ($_GET['filter'] ?? 'all');
$filter = in_array($rawFilter, $allowedFilters, true) ? $rawFilter : 'all';

$allowedSorts = ['', 'sku', 'name', 'quantity'];
$rawSort = (string) ($_GET['sort'] ?? '');
$sort = in_array($rawSort, $allowedSorts, true) ? $rawSort : '';

$search = trim((string) ($_GET['search'] ?? ''));

$filtered_products = array_values(array_filter($products, static function (array $product) use ($filter, $search): bool {
    $qty = (int) $product['quantity'];
    $type_match = true;
    if ($filter === 'in_stock') {
        $type_match = ($qty > 0);
    } elseif ($filter === 'out_of_stock') {
        $type_match = ($qty <= 0);
    }

    $search_match = true;
    if ($search !== '') {
        $search_match = (
            stripos((string) ($product['name'] ?? ''), $search) !== false ||
            stripos((string) ($product['sku'] ?? ''), $search) !== false
        );
    }

    return $type_match && $search_match;
}));

if ($sort !== '') {
    usort($filtered_products, static function (array $a, array $b) use ($sort): int {
        switch ($sort) {
            case 'sku':
                return strcmp((string) $a['sku'], (string) $b['sku']);
            case 'name':
                return strcmp((string) $a['name'], (string) $b['name']);
            case 'quantity':
                return (int) $b['quantity'] <=> (int) $a['quantity'];
            default:
                return 0;
        }
    });
}

$perPage = 50;
$totalFiltered = count($filtered_products);
$totalPages = max(1, (int) ceil($totalFiltered / $perPage));
$page = max(1, filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT) ?: 1);
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;
$paged_products = array_slice($filtered_products, $offset, $perPage);

$clearSearchQuery = http_build_query(array_filter([
    'filter' => $filter !== 'all' ? $filter : null,
    'sort'   => $sort !== '' ? $sort : null,
], static fn($v) => $v !== null));
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Tồn kho &amp; Sản phẩm — SmartDine Kho</title>
  <link rel="stylesheet" href="<?= e(asset_url('assets/css/style.css')) ?>">
  <link rel="stylesheet" href="<?= e(asset_url('assets/css/header.css')) ?>">
  <link rel="stylesheet" href="<?= e(asset_url('assets/css/products.css')) ?>">
  <link rel="stylesheet" href="<?= e(asset_url('assets/css/inventory.css')) ?>">
</head>
<body class="dashboard-body">

<?php include __DIR__ . '/../includes/sidebar.php'; ?>

<main class="dashboard-main">
  <?php include __DIR__ . '/../includes/header.php'; ?>

  <div class="dash-header">
    <div>
      <span class="eyebrow">SMARTDINE INVENTORY CONTROL</span>
      <h1>Quản lý Tồn kho &amp; Sản phẩm</h1>
      <p>Danh sách mặt hàng, tồn kho hiện tại và thao tác nhập kho / import Excel nhanh gọn cho bếp và quầy.</p>
    </div>
    <div class="header-action-group">
      <a href="<?= e(warehouse_url('inventory/daily_stock.php')) ?>" class="btn btn-primary-tool">📅 Tồn theo ngày</a>
      <a href="<?= e(warehouse_url('products/product_add.php')) ?>" class="btn btn-outline">➕ Thêm SP</a>
      <a href="<?= e(warehouse_url('inventory/inventory_import.php')) ?>" class="btn btn-outline">📄 Import Excel</a>
      <a href="<?= e(warehouse_url('inventory/stock_in.php')) ?>" class="btn btn-success">📥 Nhập kho</a>
    </div>
  </div>

  <?php if ($successMsg !== ''): ?>
    <div class="flash-alert flash-success"><?= e($successMsg) ?></div>
  <?php endif; ?>

  <?php if ($errorMsg !== ''): ?>
    <div class="flash-alert flash-error"><?= e($errorMsg) ?></div>
  <?php endif; ?>

  <div class="revenue-stats-grid">
    <a href="?filter=all" class="revenue-stat-card <?= $filter === 'all' ? 'active' : '' ?>">
      <div class="revenue-stat-head">
        <span>Tổng mặt hàng</span>
        <span class="stat-trend-badge info">Tất cả</span>
      </div>
      <div class="revenue-stat-value"><?= count($products) ?></div>
      <div class="revenue-stat-sub">Nhấn để xem toàn bộ</div>
    </a>

    <a href="?filter=in_stock" class="revenue-stat-card <?= $filter === 'in_stock' ? 'active' : '' ?>">
      <div class="revenue-stat-head">
        <span>Đang còn hàng</span>
        <span class="stat-trend-badge positive">Tồn &gt; 0</span>
      </div>
      <div class="revenue-stat-value"><?= $in_stock_count ?></div>
      <div class="revenue-stat-sub">Mặt hàng sẵn có</div>
    </a>

    <a href="?filter=out_of_stock" class="revenue-stat-card <?= $filter === 'out_of_stock' ? 'active' : '' ?>">
      <div class="revenue-stat-head">
        <span>Đã hết hàng</span>
        <span class="stat-trend-badge negative">Tồn = 0</span>
      </div>
      <div class="revenue-stat-value"><?= $out_of_stock_count ?></div>
      <div class="revenue-stat-sub">Cần nhập bổ sung</div>
    </a>

    <div class="revenue-stat-card">
      <div class="revenue-stat-head">
        <span>Tổng số lượng tồn</span>
        <span class="stat-trend-badge neutral">Đơn vị</span>
      </div>
      <div class="revenue-stat-value"><?= number_format($total_quantity) ?></div>
      <div class="revenue-stat-sub">Tổng lượng hàng hiện có</div>
    </div>
  </div>

  <div class="panel">
    <div class="panel-title">
      <h2>📋 Danh sách Tồn kho (<?= $totalFiltered ?> sản phẩm)</h2>
    </div>

    <form method="get" class="user-filter-bar">
      <input type="hidden" name="filter" value="<?= e($filter) ?>">

      <div class="search-input-wrap">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="11" cy="11" r="8"></circle>
          <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
        </svg>
        <input type="text" name="search" placeholder="Tìm theo tên sản phẩm, mã..." value="<?= e($search) ?>">
      </div>

      <div class="filter-controls-group">
        <select name="sort" id="sort" onchange="this.form.submit()">
          <option value="">-- Sắp xếp theo --</option>
          <option value="sku" <?= $sort === 'sku' ? 'selected' : '' ?>>Mã SKU</option>
          <option value="name" <?= $sort === 'name' ? 'selected' : '' ?>>Tên sản phẩm</option>
          <option value="quantity" <?= $sort === 'quantity' ? 'selected' : '' ?>>Số lượng giảm dần</option>
        </select>

        <button type="submit" class="btn btn-primary btn-small">Tìm kiếm</button>
        <?php if ($search !== ''): ?>
          <a href="?<?= e($clearSearchQuery) ?>" class="btn btn-outline btn-small">Xóa lọc</a>
        <?php endif; ?>
      </div>
    </form>

    <div class="table-responsive">
      <table class="inventory-table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Tên sản phẩm</th>
            <th>Mã</th>
            <th>Tồn kho hiện tại</th>
            <th>Hành động</th>
          </tr>
        </thead>
        <tbody>
        <?php if (!empty($paged_products)): ?>
          <?php foreach ($paged_products as $product): ?>
            <?php $qty = (int) $product['quantity']; ?>
            <tr class="<?= $qty <= 0 ? 'low-stock' : '' ?>">
              <td>#<?= (int) $product['id'] ?></td>
              <td class="searchable-cell"><strong><?= e($product['name']) ?></strong></td>
              <td class="searchable-cell"><span class="sku-badge"><?= e($product['sku']) ?></span></td>
              <td>
                <?php if ($qty <= 0): ?>
                  <span class="status status-danger">0 · Hết hàng</span>
                <?php else: ?>
                  <strong><?= number_format($qty) ?></strong>
                <?php endif; ?>
              </td>
              <td>
                <div class="product-actions" style="flex-wrap:wrap;">
                  <a class="btn-link-action" style="color:var(--green);" href="<?= e(warehouse_url('inventory/stock_in.php?product_id=' . (int) $product['id'])) ?>">📥 Nhập</a>
                  <a class="btn-link-action" href="<?= e(warehouse_url('products/product_edit.php?id=' . (int) $product['id'])) ?>">✏️ Sửa</a>
                  <form method="POST" action="<?= e(warehouse_url('products/product_delete.php')) ?>" style="display:inline;" onsubmit="return confirm('Xóa sản phẩm này khỏi kho?');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int) $product['id'] ?>">
                    <button type="submit" class="btn-link-action btn-link-danger">🗑️ Xóa</button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr>
            <td colspan="5" style="text-align: center; color: var(--muted); padding: 36px;">
              <?php if ($search !== ''): ?>
                🔍 Không tìm thấy sản phẩm nào với từ khóa "<?= e($search) ?>"
              <?php elseif ($filter === 'out_of_stock'): ?>
                ✅ Không có sản phẩm nào hết hàng
              <?php else: ?>
                📦 Chưa có sản phẩm nào trong kho
              <?php endif; ?>
            </td>
          </tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>

    <?php if ($totalPages > 1): ?>
      <div class="pagination">
        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
          <?php
          $pageQuery = http_build_query([
              'filter' => $filter,
              'sort'   => $sort,
              'search' => $search,
              'page'   => $p,
          ]);
          ?>
          <a href="?<?= e($pageQuery) ?>" class="<?= $p === $page ? 'current' : '' ?>">
            <?= $p ?>
          </a>
        <?php endfor; ?>
      </div>
      <div style="text-align: center; margin-top: 10px; color: var(--muted); font-size: 13px;">
        Hiển thị <?= ($offset + 1) ?> - <?= min($offset + $perPage, $totalFiltered) ?> / <?= $totalFiltered ?> sản phẩm
      </div>
    <?php endif; ?>
  </div>

  <?php include __DIR__ . '/../includes/footer.php'; ?>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchTerm = <?= json_encode($search, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
    if (searchTerm) {
        const escaped = searchTerm.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        const regex = new RegExp(`(${escaped})`, 'gi');
        const cells = document.querySelectorAll('.inventory-table td.searchable-cell');

        cells.forEach(cell => {
            const walker = document.createTreeWalker(cell, NodeFilter.SHOW_TEXT, null);
            const textNodes = [];
            let node;
            while ((node = walker.nextNode())) {
                if (node.nodeValue && regex.test(node.nodeValue)) {
                    regex.lastIndex = 0;
                    textNodes.push(node);
                }
            }

            textNodes.forEach(textNode => {
                const frag = document.createDocumentFragment();
                const parts = textNode.nodeValue.split(regex);
                parts.forEach(part => {
                    if (!part) return;
                    if (part.toLowerCase() === searchTerm.toLowerCase()) {
                        const mark = document.createElement('mark');
                        mark.style.background = '#fff3e6';
                        mark.style.color = '#c2410c';
                        mark.style.padding = '1px 4px';
                        mark.style.borderRadius = '4px';
                        mark.style.fontWeight = '700';
                        mark.textContent = part;
                        frag.appendChild(mark);
                    } else {
                        frag.appendChild(document.createTextNode(part));
                    }
                });
                textNode.parentNode.replaceChild(frag, textNode);
            });
        });
    }
});
</script>

</body>
</html>

<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(WAREHOUSE_ALLOWED_ROLES);
require_once __DIR__ . '/../config.php';

function parse_valid_ymd(?string $raw): string
{
    $val = trim((string) ($raw ?? ''));
    if ($val === '') {
        return '';
    }
    $dt = DateTime::createFromFormat('Y-m-d', $val);
    return ($dt && $dt->format('Y-m-d') === $val) ? $val : '';
}

$page = max(1, filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT) ?: 1);
$limit = 20;

$product_filter = max(0, filter_var($_GET['product_id'] ?? 0, FILTER_VALIDATE_INT) ?: 0);
$type_filter = in_array($_GET['type'] ?? '', ['in', 'out'], true) ? (string) $_GET['type'] : '';
$date_from = parse_valid_ymd($_GET['date_from'] ?? '');
$date_to = parse_valid_ymd($_GET['date_to'] ?? '');

$where_conditions = [];
$params = [];
$param_types = '';

if ($product_filter > 0) {
    $where_conditions[] = 'st.product_id = ?';
    $params[] = $product_filter;
    $param_types .= 'i';
}
if ($type_filter !== '') {
    $where_conditions[] = 'st.transaction_type = ?';
    $params[] = $type_filter;
    $param_types .= 's';
}
if ($date_from !== '') {
    $where_conditions[] = 'DATE(st.created_at) >= ?';
    $params[] = $date_from;
    $param_types .= 's';
}
if ($date_to !== '') {
    $where_conditions[] = 'DATE(st.created_at) <= ?';
    $params[] = $date_to;
    $param_types .= 's';
}

$where_clause = count($where_conditions) > 0 ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

$transactions = [];
$total_records = 0;
$total_pages = 1;
$offset = 0;
$summary = ['in' => ['count' => 0, 'quantity' => 0], 'out' => ['count' => 0, 'quantity' => 0]];
$productsList = [];
$queryError = '';

try {
    $count_sql = "SELECT COUNT(*) AS total
                  FROM stock_transactions st
                  JOIN products p ON st.product_id = p.id
                  $where_clause";
    if ($param_types !== '') {
        $count_stmt = $conn->prepare($count_sql);
        $count_stmt->bind_param($param_types, ...$params);
        $count_stmt->execute();
        $total_records = (int) ($count_stmt->get_result()->fetch_assoc()['total'] ?? 0);
        $count_stmt->close();
    } else {
        $total_records = (int) ($conn->query($count_sql)->fetch_assoc()['total'] ?? 0);
    }

    $total_pages = max(1, (int) ceil($total_records / $limit));
    if ($page > $total_pages) {
        $page = $total_pages;
    }
    $offset = ($page - 1) * $limit;

    $sql = "SELECT st.*, p.name, p.sku
            FROM stock_transactions st
            JOIN products p ON st.product_id = p.id
            $where_clause
            ORDER BY st.created_at DESC, st.id DESC
            LIMIT ? OFFSET ?";

    $listParams = array_merge($params, [$limit, $offset]);
    $listTypes = $param_types . 'ii';

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($listTypes, ...$listParams);
    $stmt->execute();
    $transactions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $summary_sql = "SELECT st.transaction_type, COUNT(*) AS cnt, COALESCE(SUM(st.quantity), 0) AS total_quantity
                    FROM stock_transactions st
                    JOIN products p ON st.product_id = p.id
                    $where_clause
                    GROUP BY st.transaction_type";
    if ($param_types !== '') {
        $summary_stmt = $conn->prepare($summary_sql);
        $summary_stmt->bind_param($param_types, ...$params);
        $summary_stmt->execute();
        $summary_res = $summary_stmt->get_result();
        while ($row = $summary_res->fetch_assoc()) {
            $t = (string) ($row['transaction_type'] ?? '');
            if (isset($summary[$t])) {
                $summary[$t] = [
                    'count'    => (int) $row['cnt'],
                    'quantity' => (int) $row['total_quantity'],
                ];
            }
        }
        $summary_stmt->close();
    } else {
        $summary_res = $conn->query($summary_sql);
        while ($row = $summary_res->fetch_assoc()) {
            $t = (string) ($row['transaction_type'] ?? '');
            if (isset($summary[$t])) {
                $summary[$t] = [
                    'count'    => (int) $row['cnt'],
                    'quantity' => (int) $row['total_quantity'],
                ];
            }
        }
    }

    $products_res = $conn->query('SELECT id, name, sku FROM products ORDER BY name');
    $productsList = $products_res ? $products_res->fetch_all(MYSQLI_ASSOC) : [];
} catch (Throwable $e) {
    error_log('stock_history error: ' . $e->getMessage());
    $queryError = 'Không thể tải lịch sử giao dịch.';
}

$baseQuery = [
    'product_id' => $product_filter > 0 ? $product_filter : null,
    'type'       => $type_filter !== '' ? $type_filter : null,
    'date_from'  => $date_from !== '' ? $date_from : null,
    'date_to'    => $date_to !== '' ? $date_to : null,
];
$baseQuery = array_filter($baseQuery, static fn($v) => $v !== null);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Lịch sử xuất nhập kho — SmartDine Kho</title>
  <link rel="stylesheet" href="<?= e(asset_url('assets/css/style.css')) ?>">
  <link rel="stylesheet" href="<?= e(asset_url('assets/css/header.css')) ?>">
  <link rel="stylesheet" href="<?= e(asset_url('assets/css/stock_history.css')) ?>">
</head>
<body class="dashboard-body">

<?php include __DIR__ . '/../includes/sidebar.php'; ?>

<main class="dashboard-main">
  <?php include __DIR__ . '/../includes/header.php'; ?>

  <div class="dash-header">
    <div>
      <span class="eyebrow">SMARTDINE STOCK LEDGER</span>
      <h1>📊 Lịch sử Xuất Nhập Kho</h1>
      <p>Tra cứu chi tiết toàn bộ giao dịch nhập xuất kho theo sản phẩm và thời gian.</p>
    </div>
    <div class="header-action-group">
      <a href="<?= e(warehouse_url('inventory/inventory.php')) ?>" class="btn btn-outline">← Quay lại tồn kho</a>
    </div>
  </div>

  <?php if ($queryError !== ''): ?>
    <div class="flash-alert flash-error"><?= e($queryError) ?></div>
  <?php endif; ?>

  <div class="revenue-stats-grid">
    <div class="revenue-stat-card">
      <div class="revenue-stat-head">
        <span>Lần nhập kho</span>
        <span class="stat-trend-badge positive">Nhập</span>
      </div>
      <div class="revenue-stat-value"><?= (int) $summary['in']['count'] ?></div>
      <div class="revenue-stat-sub">Số phiếu nhập khớp bộ lọc</div>
    </div>

    <div class="revenue-stat-card">
      <div class="revenue-stat-head">
        <span>Tổng số lượng nhập</span>
        <span class="stat-trend-badge positive">+ SL</span>
      </div>
      <div class="revenue-stat-value"><?= number_format((int) $summary['in']['quantity']) ?></div>
      <div class="revenue-stat-sub">Tổng đơn vị đã nhập</div>
    </div>

    <div class="revenue-stat-card">
      <div class="revenue-stat-head">
        <span>Lần xuất kho</span>
        <span class="stat-trend-badge negative">Xuất</span>
      </div>
      <div class="revenue-stat-value"><?= (int) $summary['out']['count'] ?></div>
      <div class="revenue-stat-sub">Số phiếu xuất khớp bộ lọc</div>
    </div>

    <div class="revenue-stat-card">
      <div class="revenue-stat-head">
        <span>Tổng số lượng xuất</span>
        <span class="stat-trend-badge negative">- SL</span>
      </div>
      <div class="revenue-stat-value"><?= number_format((int) $summary['out']['quantity']) ?></div>
      <div class="revenue-stat-sub">Tổng đơn vị đã xuất</div>
    </div>
  </div>

  <div class="panel">
    <div class="panel-title">
      <h2>📜 Sổ chi tiết giao dịch</h2>
    </div>

    <form method="GET" class="filters">
      <div class="filter-group">
        <label>Sản phẩm:</label>
        <select name="product_id">
          <option value="">Tất cả sản phẩm</option>
          <?php foreach ($productsList as $product): ?>
            <option value="<?= (int) $product['id'] ?>" <?= $product_filter === (int) $product['id'] ? 'selected' : '' ?>>
              <?= e($product['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="filter-group">
        <label>Loại giao dịch:</label>
        <select name="type">
          <option value="">Tất cả</option>
          <option value="in" <?= $type_filter === 'in' ? 'selected' : '' ?>>Nhập kho</option>
          <option value="out" <?= $type_filter === 'out' ? 'selected' : '' ?>>Xuất kho</option>
        </select>
      </div>

      <div class="filter-group">
        <label>Từ ngày:</label>
        <input type="date" name="date_from" value="<?= e($date_from) ?>">
      </div>

      <div class="filter-group">
        <label>Đến ngày:</label>
        <input type="date" name="date_to" value="<?= e($date_to) ?>">
      </div>

      <div class="filter-group" style="flex-direction:row;gap:8px;align-items:center;">
        <button type="submit" class="btn btn-primary" style="flex:1;">🔍 Lọc</button>
        <a href="<?= e(warehouse_url('inventory/stock_history.php')) ?>" class="btn btn-outline">Reset</a>
      </div>
    </form>

    <div class="table-responsive">
      <table class="inventory-table">
        <thead>
          <tr>
            <th>Thời gian</th>
            <th>Sản phẩm</th>
            <th>Loại</th>
            <th>Số lượng</th>
            <th>Lý do</th>
            <th>Tham chiếu</th>
            <th>Người thực hiện</th>
          </tr>
        </thead>
        <tbody>
        <?php if (!empty($transactions)): ?>
          <?php foreach ($transactions as $row): ?>
            <tr>
              <td><?= e(date('d/m/Y H:i', strtotime((string) $row['created_at']))) ?></td>
              <td>
                <strong><?= e($row['name']) ?></strong><br>
                <span class="sku-badge" style="margin-top:4px;"><?= e($row['sku']) ?></span>
              </td>
              <td>
                <?php if ($row['transaction_type'] === 'in'): ?>
                  <span class="status status-ok">📥 Nhập kho</span>
                <?php else: ?>
                  <span class="status status-danger">📤 Xuất kho</span>
                <?php endif; ?>
              </td>
              <td class="<?= $row['transaction_type'] === 'in' ? 'transaction-in' : 'transaction-out' ?>">
                <strong><?= $row['transaction_type'] === 'in' ? '+' : '-' ?><?= number_format((int) $row['quantity']) ?></strong>
              </td>
              <td><?= e($row['reason'] ?? '') ?></td>
              <td>
                <?= !empty($row['reference']) ? '<span class="sku-badge">' . e($row['reference']) . '</span>' : '<span style="color: var(--muted);">-</span>' ?>
              </td>
              <td><strong><?= e($row['created_by'] ?? 'system') ?></strong></td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr>
            <td colspan="7" style="text-align: center; color: var(--muted); padding: 28px;">
              <?= $total_records === 0 ? 'Không có giao dịch nào' : 'Không có dữ liệu phù hợp với bộ lọc' ?>
            </td>
          </tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>

    <?php if ($total_records > 0 && $total_pages > 1): ?>
      <div class="pagination">
        <?php if ($page > 1): ?>
          <a href="?<?= e(http_build_query(array_merge($baseQuery, ['page' => 1]))) ?>">« Đầu</a>
          <a href="?<?= e(http_build_query(array_merge($baseQuery, ['page' => $page - 1]))) ?>">‹ Trước</a>
        <?php endif; ?>

        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
          <?php if ($i === $page): ?>
            <span class="current"><?= $i ?></span>
          <?php else: ?>
            <a href="?<?= e(http_build_query(array_merge($baseQuery, ['page' => $i]))) ?>"><?= $i ?></a>
          <?php endif; ?>
        <?php endfor; ?>

        <?php if ($page < $total_pages): ?>
          <a href="?<?= e(http_build_query(array_merge($baseQuery, ['page' => $page + 1]))) ?>">Sau ›</a>
          <a href="?<?= e(http_build_query(array_merge($baseQuery, ['page' => $total_pages]))) ?>">Cuối »</a>
        <?php endif; ?>
      </div>

      <div style="text-align: center; margin-top: 10px; color: var(--muted); font-size: 13px;">
        Hiển thị <?= min($offset + 1, $total_records) ?> - <?= min($offset + $limit, $total_records) ?> của <?= number_format($total_records) ?> kết quả
      </div>
    <?php endif; ?>
  </div>

  <?php include __DIR__ . '/../includes/footer.php'; ?>
</main>

</body>
</html>

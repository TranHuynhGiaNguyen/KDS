<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(WAREHOUSE_ALLOWED_ROLES);
require_once __DIR__ . '/../config.php';

$message = '';
$selected_product = null;
$importDate = date('Y-m-d');

$products_result = $conn->query('SELECT id, name, sku, quantity FROM products ORDER BY name');
$productsList = $products_result ? $products_result->fetch_all(MYSQLI_ASSOC) : [];

$product_id = isset($_GET['product_id']) ? (int) $_GET['product_id'] : 0;
if ($product_id > 0) {
    $stmt = $conn->prepare('SELECT id, name, sku, quantity FROM products WHERE id = ?');
    $stmt->bind_param('i', $product_id);
    $stmt->execute();
    $selected_product = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $postProductId = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
    $quantity = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT);
    $importDateInput = trim((string) ($_POST['import_date'] ?? date('Y-m-d')));
    $reason = trim((string) ($_POST['reason'] ?? 'Nhập kho'));
    if ($reason === '') {
        $reason = 'Nhập kho';
    }

    $dtValid = DateTimeImmutable::createFromFormat('!Y-m-d', $importDateInput);
    $validDate = ($dtValid && $dtValid->format('Y-m-d') === $importDateInput);

    if (!$postProductId || $postProductId <= 0 || !$quantity || $quantity <= 0 || !$validDate) {
        $message = '⚠️ Vui lòng chọn sản phẩm, ngày nhập hợp lệ và số lượng (> 0)!';
    } elseif (mb_strlen($reason) > 255) {
        $message = '⚠️ Ghi chú vượt quá 255 ký tự!';
    } else {
        $conn->begin_transaction();
        try {
            $lockStmt = $conn->prepare('SELECT id FROM products WHERE id = ? FOR UPDATE');
            $lockStmt->bind_param('i', $postProductId);
            $lockStmt->execute();
            $exists = $lockStmt->get_result()->fetch_assoc();
            $lockStmt->close();

            if (!$exists) {
                $conn->rollback();
                $message = '❌ Sản phẩm không tồn tại!';
            } else {
                $updStmt = $conn->prepare('UPDATE products SET quantity = quantity + ? WHERE id = ?');
                $updStmt->bind_param('ii', $quantity, $postProductId);
                $updStmt->execute();
                if ($updStmt->affected_rows !== 1) {
                    throw new RuntimeException('Không thể cập nhật tồn kho sản phẩm.');
                }
                $updStmt->close();

                record_stock_transaction(
                    $conn,
                    (int) $postProductId,
                    'in',
                    (int) $quantity,
                    $reason,
                    null,
                    current_username(),
                    $importDateInput . ' ' . date('H:i:s')
                );

                $conn->commit();
                set_flash('inventory_success', '✅ Đã nhập kho ' . (int) $quantity . ' sản phẩm ngày ' . date('d/m/Y', strtotime($importDateInput)) . '!');
                redirect_to('inventory/inventory.php');
            }
        } catch (Throwable $e) {
            $conn->rollback();
            error_log('stock_in error: ' . $e->getMessage());
            $message = '❌ Lỗi khi nhập kho. Vui lòng thử lại.';
        }
    }
    $importDate = $validDate ? $importDateInput : date('Y-m-d');
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Nhập kho — SmartDine Kho</title>
  <link rel="stylesheet" href="<?= e(asset_url('assets/css/style.css')) ?>">
  <link rel="stylesheet" href="<?= e(asset_url('assets/css/header.css')) ?>">
  <link rel="stylesheet" href="<?= e(asset_url('assets/css/stock_in.css')) ?>">
</head>
<body class="dashboard-body">

<?php include __DIR__ . '/../includes/sidebar.php'; ?>

<main class="dashboard-main">
  <?php include __DIR__ . '/../includes/header.php'; ?>

  <div class="dash-header">
    <div>
      <span class="eyebrow">SMARTDINE STOCK INBOUND</span>
      <h1>📥 Tạo Phiếu Nhập kho</h1>
      <p>Cộng thêm số lượng hàng hóa nhập vào kho theo ngày thực nhập.</p>
    </div>
    <div class="header-action-group">
      <a href="<?= e(warehouse_url('inventory/inventory.php')) ?>" class="btn btn-outline">← Quay lại tồn kho</a>
    </div>
  </div>

  <?php if ($message !== ''): ?>
    <div class="flash-alert <?= strpos($message, '❌') !== false ? 'flash-error' : 'flash-warning' ?>">
      <?= e($message) ?>
    </div>
  <?php endif; ?>

  <div class="panel">
    <div class="panel-title">
      <h2>📝 Thông tin phiếu nhập</h2>
    </div>

    <form method="POST" action="" class="form">
      <?= csrf_field() ?>
      <div>
        <label>Chọn sản phẩm:</label>
        <select name="product_id" id="product_id" required onchange="updateProductInfo()">
          <option value="">-- Chọn sản phẩm --</option>
          <?php foreach ($productsList as $product): ?>
            <option value="<?= (int) $product['id'] ?>"
                    data-name="<?= e($product['name']) ?>"
                    data-sku="<?= e($product['sku']) ?>"
                    data-quantity="<?= (int) $product['quantity'] ?>"
                    <?= ($selected_product && (int) $selected_product['id'] === (int) $product['id']) ? 'selected' : '' ?>>
              <?= e($product['name']) ?> (<?= e($product['sku']) ?>) - Tồn: <?= (int) $product['quantity'] ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div id="product-info" class="product-info" style="display: <?= $selected_product ? 'block' : 'none' ?>;">
        <h4>Thông tin sản phẩm</h4>
        <p><strong>Tên:</strong> <span id="info-name"><?= e($selected_product['name'] ?? '') ?></span></p>
        <p><strong>Mã:</strong> <span id="info-sku" class="sku-badge"><?= e($selected_product['sku'] ?? '') ?></span></p>
        <p class="current-stock"><strong>Tồn hiện tại:</strong> <span id="info-quantity"><?= isset($selected_product['quantity']) ? (int) $selected_product['quantity'] : '' ?></span></p>
      </div>

      <div>
        <label>Ngày nhập:</label>
        <input type="date" name="import_date" value="<?= e($importDate) ?>" required>
      </div>

      <div>
        <label>Số lượng nhập:</label>
        <input type="number" name="quantity" min="1" step="1" required placeholder="Nhập số lượng cần nhập kho">
      </div>

      <div>
        <label>Ghi chú (tùy chọn):</label>
        <input type="text" name="reason" maxlength="255" placeholder="VD: Nhập hàng sáng nay...">
      </div>

      <div style="display:flex;gap:10px;margin-top:6px;">
        <button type="submit" class="btn btn-success">✅ Xác nhận nhập kho</button>
        <a href="<?= e(warehouse_url('inventory/inventory.php')) ?>" class="btn btn-outline">Hủy</a>
      </div>
    </form>
  </div>

  <?php include __DIR__ . '/../includes/footer.php'; ?>
</main>

<script>
function updateProductInfo() {
    const select = document.getElementById('product_id');
    const selectedOption = select.options[select.selectedIndex];
    const infoDiv = document.getElementById('product-info');

    if (select.value) {
        document.getElementById('info-name').textContent = selectedOption.getAttribute('data-name') || '';
        document.getElementById('info-sku').textContent = selectedOption.getAttribute('data-sku') || '';
        document.getElementById('info-quantity').textContent = selectedOption.getAttribute('data-quantity') || '0';
        infoDiv.style.display = 'block';
    } else {
        infoDiv.style.display = 'none';
    }
}
</script>

</body>
</html>

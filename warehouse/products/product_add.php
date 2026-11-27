<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(WAREHOUSE_MANAGE_ROLES);
require_once __DIR__ . '/../config.php';

$message = '';
$name = '';
$sku = '';
$quantity = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $name = trim((string) ($_POST['name'] ?? ''));
    $sku = trim((string) ($_POST['sku'] ?? ''));
    $rawQty = trim((string) ($_POST['quantity'] ?? '0'));
    $validQty = filter_var($rawQty, FILTER_VALIDATE_INT);

    if ($name === '' || $sku === '') {
        $message = '⚠️ Vui lòng nhập đầy đủ Mã và Tên sản phẩm!';
    } elseif (mb_strlen($name) > 100 || mb_strlen($sku) > 50) {
        $message = '⚠️ Tên sản phẩm (tối đa 100 ký tự) hoặc Mã (tối đa 50 ký tự) vượt quá độ dài cho phép!';
    } elseif ($validQty === false || $validQty < 0) {
        $message = '⚠️ Số lượng phải là số nguyên không âm!';
    } else {
        $quantity = (int) $validQty;
        $zeroPrice = 0.0;

        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare('INSERT INTO products (name, sku, quantity, price) VALUES (?, ?, ?, ?)');
            $stmt->bind_param('ssid', $name, $sku, $quantity, $zeroPrice);
            $stmt->execute();
            $newProductId = (int) $conn->insert_id;
            $stmt->close();

            if ($quantity > 0) {
                record_stock_transaction(
                    $conn,
                    $newProductId,
                    'in',
                    $quantity,
                    'Tạo sản phẩm mới',
                    null,
                    current_username()
                );
            }

            $conn->commit();
            set_flash('inventory_success', '✅ Đã thêm sản phẩm "' . $name . '" thành công!');
            redirect_to('inventory/inventory.php');
        } catch (mysqli_sql_exception $e) {
            $conn->rollback();
            if ((int) $e->getCode() === 1062) {
                $message = '❌ Mã sản phẩm đã tồn tại trong kho!';
            } else {
                error_log('product_add error: ' . $e->getMessage());
                $message = '❌ Không thể thêm sản phẩm. Vui lòng thử lại.';
            }
        } catch (Throwable $e) {
            $conn->rollback();
            error_log('product_add unexpected error: ' . $e->getMessage());
            $message = '❌ Có lỗi xảy ra khi thêm sản phẩm.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Thêm sản phẩm — SmartDine Kho</title>
  <link rel="stylesheet" href="<?= e(asset_url('assets/css/style.css')) ?>">
  <link rel="stylesheet" href="<?= e(asset_url('assets/css/header.css')) ?>">
  <link rel="stylesheet" href="<?= e(asset_url('assets/css/products.css')) ?>">
</head>
<body class="dashboard-body">

<?php include __DIR__ . '/../includes/sidebar.php'; ?>

<main class="dashboard-main">
  <?php include __DIR__ . '/../includes/header.php'; ?>

  <div class="dash-header">
    <div>
      <span class="eyebrow">SMARTDINE PRODUCT CATALOG</span>
      <h1>➕ Thêm sản phẩm mới</h1>
      <p>Khai báo nhanh mặt hàng với Mã, Tên SP và số lượng tồn ban đầu.</p>
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
      <h2>📝 Thông tin sản phẩm</h2>
    </div>

    <form method="POST" action="" class="form">
      <?= csrf_field() ?>
      <div>
        <label>Mã sản phẩm:</label>
        <input type="text" name="sku" maxlength="50" value="<?= e($sku) ?>" placeholder="VD: BO01" required>
      </div>

      <div>
        <label>Tên sản phẩm:</label>
        <input type="text" name="name" maxlength="100" value="<?= e($name) ?>" placeholder="VD: Bò áp chảo" required>
      </div>

      <div>
        <label>Số lượng:</label>
        <input type="number" name="quantity" min="0" step="1" value="<?= (int) $quantity ?>" required>
      </div>

      <div style="display:flex;gap:10px;margin-top:6px;">
        <button type="submit" class="btn btn-primary">Lưu sản phẩm</button>
        <a href="<?= e(warehouse_url('inventory/inventory.php')) ?>" class="btn btn-outline">Hủy</a>
      </div>
    </form>
  </div>

  <?php include __DIR__ . '/../includes/footer.php'; ?>
</main>

</body>
</html>

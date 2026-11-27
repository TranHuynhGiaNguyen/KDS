<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(WAREHOUSE_MANAGE_ROLES);
require_once __DIR__ . '/../config.php';

$message = '';
$product = null;
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id > 0) {
    $stmt = $conn->prepare('SELECT id, name, sku, quantity FROM products WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $product = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

if (!$product) {
    set_flash('inventory_error', 'Không tìm thấy sản phẩm cần chỉnh sửa.');
    redirect_to('inventory/inventory.php');
}

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

        $conn->begin_transaction();
        try {
            $lockStmt = $conn->prepare('SELECT quantity FROM products WHERE id = ? FOR UPDATE');
            $lockStmt->bind_param('i', $id);
            $lockStmt->execute();
            $locked = $lockStmt->get_result()->fetch_assoc();
            $lockStmt->close();

            if (!$locked) {
                $conn->rollback();
                set_flash('inventory_error', 'Sản phẩm không còn tồn tại.');
                redirect_to('inventory/inventory.php');
            }

            $oldQty = (int) $locked['quantity'];

            $stmt = $conn->prepare('UPDATE products SET name = ?, sku = ?, quantity = ? WHERE id = ?');
            $stmt->bind_param('ssii', $name, $sku, $quantity, $id);
            $stmt->execute();
            $stmt->close();

            $diff = $quantity - $oldQty;
            if ($diff !== 0) {
                record_stock_transaction(
                    $conn,
                    $id,
                    $diff > 0 ? 'in' : 'out',
                    abs($diff),
                    'Điều chỉnh khi cập nhật sản phẩm',
                    null,
                    current_username()
                );
            }

            $conn->commit();
            set_flash('inventory_success', '✅ Đã cập nhật sản phẩm "' . $name . '" thành công!');
            redirect_to('inventory/inventory.php');
        } catch (mysqli_sql_exception $e) {
            $conn->rollback();
            if ((int) $e->getCode() === 1062) {
                $message = '❌ Mã sản phẩm đã tồn tại!';
            } else {
                error_log('product_edit error: ' . $e->getMessage());
                $message = '❌ Lỗi khi cập nhật sản phẩm.';
            }
        } catch (Throwable $e) {
            $conn->rollback();
            error_log('product_edit unexpected error: ' . $e->getMessage());
            $message = '❌ Có lỗi xảy ra khi cập nhật sản phẩm.';
        }
    }

    $product['name'] = $name;
    $product['sku'] = $sku;
    $product['quantity'] = $validQty !== false ? (int) $validQty : $product['quantity'];
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sửa sản phẩm — SmartDine Kho</title>
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
      <h1>✏️ Cập nhật sản phẩm #<?= (int) $product['id'] ?></h1>
      <p>Sửa Mã, Tên SP hoặc số lượng tồn hiện tại.</p>
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
      <h2>📝 Chi tiết sản phẩm</h2>
    </div>

    <form method="POST" action="" class="form">
      <?= csrf_field() ?>
      <div>
        <label>Mã sản phẩm:</label>
        <input type="text" name="sku" maxlength="50" value="<?= e($product['sku']) ?>" required>
      </div>

      <div>
        <label>Tên sản phẩm:</label>
        <input type="text" name="name" maxlength="100" value="<?= e($product['name']) ?>" required>
      </div>

      <div>
        <label>Số lượng:</label>
        <input type="number" name="quantity" min="0" step="1" value="<?= (int) $product['quantity'] ?>" required>
      </div>

      <div style="display:flex;gap:10px;margin-top:6px;">
        <button type="submit" class="btn btn-primary">Cập nhật</button>
        <a href="<?= e(warehouse_url('inventory/inventory.php')) ?>" class="btn btn-outline">Hủy</a>
      </div>
    </form>
  </div>

  <?php include __DIR__ . '/../includes/footer.php'; ?>
</main>

</body>
</html>

<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(WAREHOUSE_MANAGE_ROLES);
require_once __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    set_flash('inventory_error', 'Yêu cầu xóa sản phẩm không hợp lệ.');
    redirect_to('inventory/inventory.php');
}

require_csrf();

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
if ($id === false || $id === null || $id <= 0) {
    set_flash('inventory_error', 'ID sản phẩm không hợp lệ.');
    redirect_to('inventory/inventory.php');
}

try {
    $stmt = $conn->prepare('DELETE FROM products WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $deleted = $stmt->affected_rows;
    $stmt->close();

    if ($deleted === 1) {
        set_flash('inventory_success', '✅ Đã xóa sản phẩm thành công.');
    } else {
        set_flash('inventory_error', '⚠️ Không tìm thấy sản phẩm để xóa.');
    }
} catch (mysqli_sql_exception $e) {
    error_log('product_delete error: ' . $e->getMessage());
    if ((int) $e->getCode() === 1451) {
        set_flash('inventory_error', '❌ Không thể xóa sản phẩm vì vẫn còn dữ liệu tham chiếu.');
    } else {
        set_flash('inventory_error', '❌ Lỗi khi xóa sản phẩm.');
    }
}

redirect_to('inventory/inventory.php');

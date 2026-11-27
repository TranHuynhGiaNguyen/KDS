<?php
$currentScript = basename($_SERVER['PHP_SELF'] ?? 'index.php');
$pageLabels = [
    'index.php'            => 'Tồn theo ngày',
    'daily_stock.php'      => 'Tồn theo ngày',
    'inventory.php'        => 'Tồn kho & Sản phẩm',
    'product_add.php'      => 'Thêm sản phẩm',
    'product_edit.php'     => 'Cập nhật sản phẩm',
    'stock_in.php'         => 'Nhập kho',
    'stock_history.php'    => 'Lịch sử nhập kho',
    'inventory_import.php' => 'Import Excel nhập kho',
];
$currentPageLabel = $pageLabels[$currentScript] ?? 'Quản lý kho';
$roleMeta = warehouse_role_meta();
?>
<div class="wh-topbar">
  <div class="wh-breadcrumb">
    <span>SmartDine Kho</span>
    <span>/</span>
    <strong><?= e($currentPageLabel) ?></strong>
  </div>
  <div class="wh-topbar-right">
    <div class="wh-user-pill">
      <span>Xin chào, <strong><?= e(current_display_name()) ?></strong></span>
      <span>·</span>
      <span><?= e($roleMeta['label']) ?></span>
    </div>
    <form method="POST" action="<?= e(warehouse_url('logout.php')) ?>" class="logout-form">
      <?= csrf_field() ?>
      <button type="submit" class="logout-btn">Đăng xuất</button>
    </form>
  </div>
</div>

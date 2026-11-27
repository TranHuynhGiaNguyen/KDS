<?php
$currentScript = basename($_SERVER['PHP_SELF'] ?? '');
$requestUri = $_SERVER['REQUEST_URI'] ?? '';
$displayName = current_display_name();
$role = current_user_role();
$roleMeta = warehouse_role_meta($role);
$userInitial = mb_strtoupper(mb_substr($displayName, 0, 1, 'UTF-8'), 'UTF-8');

$isDailyStock = ($currentScript === 'daily_stock.php' || $currentScript === 'index.php');
$isInventoryMain = (
    $currentScript === 'inventory.php'
    || strpos($requestUri, '/products/') !== false
    || strpos($currentScript, 'product') === 0
);
$isStockIn = ($currentScript === 'stock_in.php');
$isImportPage = ($currentScript === 'inventory_import.php');
$isStockHistory = ($currentScript === 'stock_history.php');
?>
<aside class="sidebar">
  <a class="brand sidebar-brand" href="<?= e(warehouse_url('inventory/daily_stock.php')) ?>">
    <span class="brand-mark">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
        <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
        <polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline>
        <line x1="12" y1="22.08" x2="12" y2="12"></line>
      </svg>
    </span>
    <span>SmartDine Kho</span>
  </a>

  <div class="side-label">QUẢN LÝ KHO HÀNG</div>

  <a class="<?= $isDailyStock ? 'active' : '' ?>" href="<?= e(warehouse_url('inventory/daily_stock.php')) ?>">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
      <line x1="16" y1="2" x2="16" y2="6"></line>
      <line x1="8" y1="2" x2="8" y2="6"></line>
      <line x1="3" y1="10" x2="21" y2="10"></line>
    </svg>
    <span>Tồn theo ngày</span>
  </a>

  <a class="<?= $isInventoryMain ? 'active' : '' ?>" href="<?= e(warehouse_url('inventory/inventory.php')) ?>">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"></path>
      <rect x="8" y="2" width="8" height="4" rx="1" ry="1"></rect>
    </svg>
    <span>Tồn kho &amp; Sản phẩm</span>
  </a>

  <a class="<?= $isStockIn ? 'active' : '' ?>" href="<?= e(warehouse_url('inventory/stock_in.php')) ?>">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
      <polyline points="7 10 12 15 17 10"></polyline>
      <line x1="12" y1="15" x2="12" y2="3"></line>
    </svg>
    <span>Nhập kho</span>
  </a>

  <a class="<?= $isImportPage ? 'active' : '' ?>" href="<?= e(warehouse_url('inventory/inventory_import.php')) ?>">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
      <polyline points="14 2 14 8 20 8"></polyline>
      <line x1="12" y1="18" x2="12" y2="12"></line>
      <line x1="9" y1="15" x2="15" y2="15"></line>
    </svg>
    <span>Import Excel</span>
  </a>

  <a class="<?= $isStockHistory ? 'active' : '' ?>" href="<?= e(warehouse_url('inventory/stock_history.php')) ?>">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <circle cx="12" cy="12" r="10"></circle>
      <polyline points="12 6 12 12 16 14"></polyline>
    </svg>
    <span>Lịch sử nhập kho</span>
  </a>

  <div class="side-label">HỆ THỐNG LIÊN KẾT</div>

  <a href="<?= e(dirname(warehouse_base_url()) . '/index.php?page=admin') ?>" class="client-view-link">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <path d="M18 8h1a4 4 0 0 1 0 8h-1"></path>
      <path d="M2 8h16v9a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V8z"></path>
    </svg>
    <span>Về SmartDine KDS</span>
  </a>

  <div class="side-bottom">
    <div class="avatar <?= e($roleMeta['avatar']) ?>">
      <?= e($userInitial) ?>
    </div>
    <div class="grow">
      <b><?= e($displayName) ?></b>
      <small><?= e($roleMeta['label']) ?></small>
    </div>
  </div>
</aside>

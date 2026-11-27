<?php

/**
 * SmartDine - Trang chi tiết bàn & Gọi món tại bàn phân tách theo vai trò:
 *   - Role `staff` (Phục vụ): Giao diện POS Tablet 2 màn hình (Gọi món + Kiểm đồ).
 *   - Role `admin` & `shift_manager`: Giao diện POS 3 cột cổ điển (KDS POS + Numpad).
 */

require_once __DIR__ . '/../includes/orders.php';
require_once __DIR__ . '/../includes/tables.php';
require_once __DIR__ . '/../includes/billing.php';
require_once __DIR__ . '/../includes/qr-tables.php';
require_once __DIR__ . '/../includes/vouchers.php';

$rawTable     = strtoupper(trim((string) ($_GET['table'] ?? '')));
$currentTable = $rawTable !== '' ? preg_replace('/[^A-Z0-9_-]/', '', $rawTable) : 'B03';
if ($currentTable === '') {
  $currentTable = 'B03';
}

$dbError        = '';
$orderItems     = [];
$tableRow       = null;
$assignment     = null;
$tableQrUrl     = '';
$tableQrToken   = '';
$pendingCall    = null;
$activeVouchers = [];

try {
  $pdo            = getOrdersDb();
  $tableRow       = getDiningTableByCode($currentTable);
  $assignment     = getActiveTableAssignment($currentTable, $pdo);
  $orderItems     = fetchTableItems($pdo, $currentTable);
  $activeVouchers = getAllVouchers('', 'active');

  $qrRecord = getOrCreateTableQrCode($pdo, $currentTable);
  if ($qrRecord && !empty($qrRecord['qr_token'])) {
    $tableQrToken = (string) $qrRecord['qr_token'];
    $tableQrUrl   = buildTableQrOrderUrl($tableQrToken);
  } else {
    $tableQrToken = generateFixedTableQrToken($currentTable);
    $tableQrUrl   = buildTableQrOrderUrl($tableQrToken, $currentTable);
  }

  if (isset($_GET['resolve_call'])) {
    resolvePendingStaffCalls($pdo, $currentTable);
  }
  $pendingCalls = fetchPendingStaffCalls($pdo);
  $pendingCall  = $pendingCalls[$currentTable] ?? null;
} catch (RuntimeException | PDOException $e) {
  error_log('table-order page: ' . $e->getMessage());
  $dbError = 'Không tải được danh sách món, vui lòng thử lại.';
}

$isMaintenance = ($tableRow['status'] ?? '') === 'maintenance';
$openedAtRaw   = $assignment['opened_at'] ?? ($assignment['created_at'] ?? null);
$cur = [
  'has_guests'     => $assignment !== null,
  'guests'         => (int) ($assignment['guest_count'] ?? ($assignment['guests'] ?? 0)),
  'time'           => $openedAtRaw ? date('H:i', strtotime($openedAtRaw)) : '—',
  'staff'          => ($assignment['staff_name'] ?? '') !== '' ? $assignment['staff_name'] : 'Chưa gán',
  'staff_id'       => (int) ($assignment['staff_id'] ?? 0),
  'customer_name'  => (string) ($assignment['customer_name'] ?? ''),
  'customer_phone' => (string) ($assignment['customer_phone'] ?? ''),
  'zone'           => (string) ($tableRow['area_name'] ?? ($tableRow['zone'] ?? 'Khu vực chính')),
  'capacity'       => (int) ($tableRow['capacity'] ?? 4),
  'note'           => (string) ($assignment['note'] ?? ''),
];

$role      = currentRole();
$canAdd    = canRoleDoOrderAction($role, 'add') && !$isMaintenance;
$canCancel = canRoleDoOrderAction($role, 'cancel');

$visibleItems = array_values(array_filter(
  $orderItems,
  static fn(array $item): bool => ($item['status'] ?? '') !== 'cancelled'
));
$hasOrderItems    = $visibleItems !== [];
$activeItemQtySum = array_sum(array_map(static fn(array $it): int => (int) ($it['qty'] ?? 0), $visibleItems));

if ($hasOrderItems) {
  $cur['has_guests'] = true;
}
if ($cur['has_guests'] && $cur['time'] === '—') {
  $createdAt   = fetchOpenOrderCreatedAt(getOrdersDb(), $currentTable);
  $cur['time'] = $createdAt ? date('H:i', strtotime($createdAt)) : '—';
}

$bill = [
  'subtotal'    => 0,
  'service_fee' => 0,
  'vat'         => 0,
  'total'       => 0,
];
try {
  $bill = calculateBill($visibleItems);
} catch (Throwable $e) {
  error_log('table-order bill calc: ' . $e->getMessage());
}

$allDishes         = [];
$dishStationByName = [];
$dishesByCategory  = [];
$categoriesMeta    = [];
$primaryMenu = [
  'buffet'   => [],
  'alacarte' => [],
  'drink'    => [],
];

try {
  $allDishes = fetchOrderableDishes(getOrdersDb());
  foreach ($allDishes as $dish) {
    $dishNameKey = mb_strtolower(trim((string) $dish['name']), 'UTF-8');
    $dishStationByName[$dishNameKey] = (string) ($dish['station'] ?? 'hot');

    $catId        = (int) ($dish['category_id'] ?? 0);
    $catName      = (string) ($dish['category'] ?? 'Khác');
    $catSlug      = (string) ($dish['category_slug'] ?? '');
    $dishMenuType = (string) ($dish['dish_menu_type'] ?? ($dish['category_menu_type'] ?? 'alacarte'));
    $station      = (string) ($dish['station'] ?? 'hot');

    // Nhóm cho giao diện POS 3 cột cổ điển (Admin & Quản lý ca)
    $dishesByCategory[$catName][] = $dish;
    $classicCatKey = 'cat-' . ($catId ?: md5($catName));
    if (!isset($categoriesMeta[$classicCatKey])) {
      $categoriesMeta[$classicCatKey] = [
        'key'       => $classicCatKey,
        'name'      => $catName,
        'menu_type' => (string) ($dish['category_menu_type'] ?? $dishMenuType),
        'dishes'    => [],
      ];
    }
    $categoriesMeta[$classicCatKey]['dishes'][] = $dish;

    // Nhóm cho giao diện Tablet 2 màn hình (Role Staff)
    $isDrink = ($catSlug === 'drink') || ($station === 'bar');
    $bucket  = $isDrink ? 'drink' : ($dishMenuType === 'buffet' ? 'buffet' : 'alacarte');
    $tabKey  = $bucket . '-cat-' . ($catId ?: md5($catName));
    if (!isset($primaryMenu[$bucket][$tabKey])) {
      $primaryMenu[$bucket][$tabKey] = [
        'key'    => $tabKey,
        'name'   => $catName,
        'dishes' => [],
      ];
    }
    $primaryMenu[$bucket][$tabKey]['dishes'][] = $dish;
  }
} catch (RuntimeException | PDOException $e) {
  error_log('table-order dishes: ' . $e->getMessage());
  $dbError = $dbError ?: 'Không tải được thực đơn để thêm món, vui lòng thử lại.';
}

$initialTab = 'buffet';
if ($primaryMenu['buffet'] === []) {
  $initialTab = ($primaryMenu['alacarte'] !== []) ? 'alacarte' : 'drink';
}

$stationLabels = [
  'hot'  => 'Bếp nóng',
  'soup' => 'Lẩu / Súp',
  'cold' => 'Salad / Lạnh',
  'bar'  => 'Quầy Bar',
];
$stationStats = [];
foreach ($stationLabels as $stKey => $stLabel) {
  $stationStats[$stKey] = ['label' => $stLabel, 'done' => 0, 'total' => 0];
}
$totalOrderedCount = 0;
$totalDoneCount    = 0;
foreach ($visibleItems as $it) {
  $qty = max(1, (int) ($it['qty'] ?? 1));
  $k   = mb_strtolower(trim((string) ($it['name'] ?? '')), 'UTF-8');
  $st  = $dishStationByName[$k] ?? 'hot';
  if (!isset($stationStats[$st])) {
    $st = 'hot';
  }
  $isDone = in_array($it['status'] ?? '', ['done', 'served'], true);
  $stationStats[$st]['total'] += $qty;
  $totalOrderedCount += $qty;
  if ($isDone) {
    $stationStats[$st]['done'] += $qty;
    $totalDoneCount += $qty;
  }
}

$loggedUser   = currentUser();
$operatorName = $loggedUser ? ($loggedUser['fullname'] ?: $loggedUser['username']) : 'Nhân viên';

$tileColors = [
  'pos-tile-pink',
  'pos-tile-lilac',
  'pos-tile-cyan',
  'pos-tile-green',
  'pos-tile-orange',
  'pos-tile-coral',
  'pos-tile-lime',
];

if ($role === 'staff') {
  include __DIR__ . '/../includes/table-order-staff-pos.php';
} else {
  include __DIR__ . '/../includes/table-order-classic-pos.php';
}

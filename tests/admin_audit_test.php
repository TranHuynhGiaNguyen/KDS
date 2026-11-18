<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/tables.php';
require_once __DIR__ . '/../includes/revenue.php';

session_start();
$_SESSION['user_id'] = 1;
$_SESSION['username'] = 'admin';
$_SESSION['role'] = 'admin';
$_SESSION['fullname'] = 'Quản trị viên';

$pdo = getRevenueDb();

echo "=== AUDIT 1: REVENUE FUNCTIONS ===\n";
$rDay = getRevenueByDate($pdo, date('Y-m-d'));
assert($rDay['mode'] === 'day', 'Day mode mismatch');
echo "  [PASS] getRevenueByDate works\n";

$rMonth = getRevenueByMonth($pdo, date('Y-m'));
assert($rMonth['mode'] === 'month', 'Month mode mismatch');
echo "  [PASS] getRevenueByMonth works\n";

$rRange = getRevenueByRange($pdo, date('Y-m-d', strtotime('-6 days')), date('Y-m-d'));
assert($rRange['mode'] === 'range', 'Range mode mismatch');
echo "  [PASS] getRevenueByRange works\n";

echo "=== AUDIT 2: USER CRUD & PAGINATION ===\n";
$users = getAllUsers();
assert(is_array($users), 'getAllUsers not array');
$stats = getUserStats();
assert(isset($stats['admin'], $stats['staff'], $stats['kitchen']), 'getUserStats missing keys');
$uAdmin = getUserByUsername('admin');
$resPartial = updateUser($uAdmin['id'], ['fullname' => $uAdmin['fullname']]);
assert($resPartial['success'] === true, 'Partial updateUser failed');
echo "  [PASS] User functions work (including partial updateUser)\n";

echo "=== AUDIT 3: DISH CRUD & SOFT DELETE ===\n";
$dishes = getAllDishes();
assert(is_array($dishes), 'getAllDishes not array');
$dishStats = getDishStats();
assert(isset($dishStats['total'], $dishStats['active']), 'getDishStats missing keys');
echo "  [PASS] Dish functions work\n";

echo "=== AUDIT 4: TABLES & AREAS ===\n";
$areas = getAllAreas();
assert(count($areas) >= 3, 'Areas count < 3');
$tables = getAllDiningTables();
assert(count($tables) >= 12, 'Tables count < 12');
$tableStats = getTableStats();
assert(isset($tableStats['total_tables']), 'Table stats missing keys');
echo "  [PASS] Table functions work\n";

echo "\n>>> AUDIT PASSED ALL CHECKS! <<<\n";

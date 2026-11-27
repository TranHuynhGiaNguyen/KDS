<?php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/../warehouse/includes/helpers.php';
require_once __DIR__ . '/../warehouse/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$_SERVER['DOCUMENT_ROOT'] = str_replace('\\', '/', realpath(__DIR__ . '/../../') ?: '');
warehouse_session_start();

echo "=== KIỂM THỬ MODULE WAREHOUSE TINH GỌN ===\n";

function wcheck(string $label, bool $cond, string $detail = ''): void
{
    if (!$cond) {
        fwrite(STDERR, "  [FAIL] $label" . ($detail !== '' ? " ($detail)" : '') . "\n");
        exit(1);
    }
    echo "  [PASS] $label\n";
}

// 1. Bảo mật & Helper
wcheck('warehouse_url sinh đúng đường dẫn', warehouse_url('login.php') === '/KDS/warehouse/login.php');
wcheck('e() thoát HTML quotes & tags chuẩn ENT_QUOTES', e("<script>'\"&") === '&lt;script&gt;&#039;&quot;&amp;');
wcheck('session_name tách biệt WAREHOUSE_SESSID', session_name() === 'WAREHOUSE_SESSID');
wcheck('excel_safe chặn formula injection', excel_safe('=CMD()') === "'=CMD()" && excel_safe(150) === 150);
wcheck('parse_warehouse_import_date hỗ trợ dd/mm/yyyy & chặn ngày sai', parse_warehouse_import_date('27/11/2026') === '2026-11-27' && parse_warehouse_import_date('2026-11-27') === '2026-11-27' && parse_warehouse_import_date('31/02/2026') === null);

// 2. CSRF
$tok = csrf_token();
wcheck('csrf_token dài 64 ký tự hex', strlen($tok) === 64 && ctype_xdigit($tok));
wcheck('verify_csrf_token chuẩn', verify_csrf_token($tok) === true && verify_csrf_token('wrong') === false);

// 3. Rà soát lược bỏ tính năng trùng lặp & RBAC chỉ cho Admin / Shift Manager
$removedFiles = [
    'warehouse/report/report.php',
    'warehouse/report/export_excel.php',
    'warehouse/includes/report_data.php',
    'warehouse/inventory/stock_alert.php',
    'warehouse/inventory/stock_out.php',
    'warehouse/inventory/inventory_export.php',
    'warehouse/products/products.php',
];
foreach ($removedFiles as $rf) {
    wcheck("Đã xóa tính năng thừa/trùng: $rf", !file_exists(__DIR__ . '/../' . $rf));
}

require_once __DIR__ . '/../warehouse/config.php';
$activeDb = $conn->query('SELECT DATABASE()')->fetch_row()[0] ?? '';
wcheck('warehouse dùng chung database smartdine_kds', $activeDb === 'smartdine_kds' && $conn === $authConn);

$_SESSION['user_id'] = 999;
$_SESSION['username'] = 'qa_tester';
$_SESSION['role'] = 'admin';
$_SESSION['loggedin'] = true;

require_once __DIR__ . '/../warehouse/includes/auth.php';

$_SESSION['role'] = 'admin';
wcheck('RBAC admin được truy cập & quản lý kho', has_role(WAREHOUSE_ALLOWED_ROLES) === true && can_manage_warehouse() === true);
$_SESSION['role'] = 'shift_manager';
wcheck('RBAC shift_manager được truy cập & quản lý kho', has_role(WAREHOUSE_ALLOWED_ROLES) === true && can_manage_warehouse() === true);
$_SESSION['role'] = 'staff';
wcheck('RBAC staff bị chặn khỏi kho', has_role(WAREHOUSE_ALLOWED_ROLES) === false && can_manage_warehouse() === false);
$_SESSION['role'] = 'kitchen';
wcheck('RBAC kitchen bị chặn khỏi kho', has_role(WAREHOUSE_ALLOWED_ROLES) === false && can_manage_warehouse() === false);
$_SESSION['role'] = 'admin';

// 4. Kiểm tra mẫu template Excel đúng 4 cột: Mã, Tên SP, Ngày tháng năm nhập, Số lượng
$tplPath = realpath(__DIR__ . '/../warehouse/inventory/download_template.php');
$runner = "<?php\n" . sprintf(
    'error_reporting(E_ALL); ini_set(\'display_errors\', \'1\'); session_name(\'WAREHOUSE_SESSID\'); session_start(); $_SESSION[\'user_id\']=999; $_SESSION[\'username\']=\'qa\'; $_SESSION[\'role\']=\'admin\'; $_SESSION[\'loggedin\']=true; require %s;',
    var_export($tplPath, true)
);
$proc = proc_open('php', [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
fwrite($pipes[0], $runner);
fclose($pipes[0]);
$tplBin = (string) stream_get_contents($pipes[1]);
$tplErr = (string) stream_get_contents($pipes[2]);
fclose($pipes[1]);
fclose($pipes[2]);
$tplExit = proc_close($proc);
wcheck('download_template.php xuất file .xlsx hợp lệ', $tplExit === 0 && trim($tplErr) === '' && substr($tplBin, 0, 2) === 'PK');

$tmpTpl = tempnam(sys_get_temp_dir(), 'wh_tpl_');
file_put_contents($tmpTpl, $tplBin);
try {
    $tplRows = IOFactory::load($tmpTpl)->getActiveSheet()->toArray();
    $headers = array_slice($tplRows[0] ?? [], 0, 4);
    wcheck('File mẫu Excel chỉ gồm đúng 4 cột (Mã, Tên SP, Ngày tháng năm nhập, Số lượng)', $headers === ['Mã', 'Tên SP', 'Ngày tháng năm nhập', 'Số lượng'] && count(array_filter($tplRows[0] ?? [], fn($v) => $v !== null && $v !== '')) === 4);
} finally {
    @unlink($tmpTpl);
}

// 5. Kiểm tra xử lý import 4 cột & ghi nhận đúng ngày nhập + dọn dữ liệu test
$testSku = 'IMP4-' . bin2hex(random_bytes(3));
$createdPid = 0;

try {
    $pagesToRender = [
        'inventory/daily_stock.php'      => __DIR__ . '/../warehouse/inventory/daily_stock.php',
        'inventory/inventory.php'        => __DIR__ . '/../warehouse/inventory/inventory.php',
        'inventory/stock_in.php'         => __DIR__ . '/../warehouse/inventory/stock_in.php',
        'inventory/stock_history.php'    => __DIR__ . '/../warehouse/inventory/stock_history.php',
        'inventory/inventory_import.php' => __DIR__ . '/../warehouse/inventory/inventory_import.php',
        'products/product_add.php'       => __DIR__ . '/../warehouse/products/product_add.php',
    ];

    foreach ($pagesToRender as $label => $filePath) {
        $_GET = ['page' => '-5', 'search' => "';alert(1);//<b>", 'date' => date('Y-m-d')];
        $_POST = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['PHP_SELF'] = '/KDS/warehouse/' . $label;
        $_SERVER['REQUEST_URI'] = '/KDS/warehouse/' . $label;

        ob_start();
        include $filePath;
        $html = (string) ob_get_clean();
        wcheck("$label render sạch (1 DOCTYPE, UI KDS, về KDS)", substr_count(strtolower($html), '<!doctype html') === 1 && strpos($html, 'dashboard-main') !== false && strpos($html, 'Về SmartDine KDS') !== false);
    }

    // Kiểm tra logic import 4 cột
    $rCreate = warehouse_process_import_row($conn, [$testSku, 'Nguyên Liệu 4 Cột', '15/11/2026', '25'], 2, 'add', 'admin');
    wcheck('Import 4 cột tạo sản phẩm mới thành công', $rCreate['status'] === 'created');

    $q = $conn->prepare('SELECT id, quantity, name FROM products WHERE sku = ?');
    $q->bind_param('s', $testSku);
    $q->execute();
    $pRow = $q->get_result()->fetch_assoc();
    $q->close();
    $createdPid = (int) ($pRow['id'] ?? 0);
    wcheck('Import ghi đúng số lượng = 25', $createdPid > 0 && (int) $pRow['quantity'] === 25);

    $txQ = $conn->prepare('SELECT DATE(created_at) AS d, quantity FROM stock_transactions WHERE product_id = ? ORDER BY id DESC LIMIT 1');
    $txQ->bind_param('i', $createdPid);
    $txQ->execute();
    $txRow = $txQ->get_result()->fetch_assoc();
    $txQ->close();
    wcheck('Import ghi đúng Ngày tháng năm nhập (2026-11-15)', ($txRow['d'] ?? '') === '2026-11-15' && (int) $txRow['quantity'] === 25);

    $rInvalid = warehouse_process_import_row($conn, [$testSku, 'Test sai ngày', '99/99/2026', '10'], 3, 'add', 'admin');
    wcheck('Import chặn ngày không hợp lệ', $rInvalid['status'] === 'error');
} finally {
    if ($createdPid > 0) {
        $del = $conn->prepare('DELETE FROM products WHERE id = ?');
        $del->bind_param('i', $createdPid);
        $del->execute();
        $del->close();
    }
}

echo "\n>>> TẤT CẢ KIỂM THỬ MODULE WAREHOUSE TINH GỌN ĐẠT 100%! <<<\n";

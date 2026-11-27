<?php
require_once __DIR__ . '/includes/helpers.php';

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
if (function_exists('date_default_timezone_set') && date_default_timezone_get() !== 'Asia/Ho_Chi_Minh') {
    date_default_timezone_set('Asia/Ho_Chi_Minh');
}

$dbConfig = [
    'host'      => getenv('WAREHOUSE_DB_HOST') ?: 'localhost',
    'user'      => getenv('WAREHOUSE_DB_USER') ?: 'root',
    'pass'      => getenv('WAREHOUSE_DB_PASS') !== false ? (string) getenv('WAREHOUSE_DB_PASS') : '',
    'name'      => getenv('WAREHOUSE_DB_NAME') ?: 'smartdine_kds',
    'auth_name' => getenv('WAREHOUSE_AUTH_DB_NAME') ?: 'smartdine_kds',
];

$localConfigFile = __DIR__ . '/config.local.php';
if (is_file($localConfigFile)) {
    $override = require $localConfigFile;
    if (is_array($override)) {
        $dbConfig = array_merge($dbConfig, $override);
    }
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli($dbConfig['host'], $dbConfig['user'], $dbConfig['pass'], $dbConfig['name']);
    $conn->set_charset('utf8mb4');
    $conn->query("SET time_zone = '+07:00'");

    if ($dbConfig['auth_name'] === $dbConfig['name']) {
        $authConn = $conn;
    } else {
        $authConn = new mysqli($dbConfig['host'], $dbConfig['user'], $dbConfig['pass'], $dbConfig['auth_name']);
        $authConn->set_charset('utf8mb4');
        $authConn->query("SET time_zone = '+07:00'");
    }
} catch (mysqli_sql_exception $e) {
    error_log('Warehouse DB connection failed: ' . $e->getMessage());
    http_response_code(500);
    die('Không thể kết nối cơ sở dữ liệu. Vui lòng thử lại sau.');
}

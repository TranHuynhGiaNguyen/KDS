<?php
/**
 * Warehouse Management System - Các hàm dùng chung cho bảo mật, URL, session và dữ liệu.
 */

if (!function_exists('warehouse_base_url')) {
    function warehouse_base_url(): string
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }

        $docRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
        $appRoot = realpath(__DIR__ . '/..');
        $docReal = $docRoot !== '' ? realpath($docRoot) : false;

        if ($appRoot !== false && $docReal !== false) {
            $normApp = rtrim(str_replace('\\', '/', $appRoot), '/');
            $normDoc = rtrim(str_replace('\\', '/', $docReal), '/');

            if (stripos($normApp, $normDoc) === 0) {
                $rel = substr($normApp, strlen($normDoc));
                $cached = $rel === '' ? '' : '/' . ltrim($rel, '/');
                return $cached;
            }
        }

        $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
        if ($scriptName !== '' && preg_match('#^(.*?/warehouse)(?:/|$)#i', $scriptName, $m)) {
            $cached = $m[1];
            return $cached;
        }

        $cached = '/KDS/warehouse';
        return $cached;
    }
}

if (!function_exists('warehouse_url')) {
    function warehouse_url(string $path = ''): string
    {
        $base = rtrim(warehouse_base_url(), '/');
        $clean = ltrim($path, '/');
        if ($clean === '') {
            return $base === '' ? '/' : $base . '/';
        }
        return ($base === '' ? '' : $base) . '/' . $clean;
    }
}

if (!function_exists('asset_url')) {
    function asset_url(string $relativePath): string
    {
        $clean = ltrim($relativePath, '/\\');
        $fullPath = __DIR__ . '/../' . $clean;
        $ver = is_file($fullPath) ? (string) filemtime($fullPath) : '1';
        return warehouse_url($clean) . '?v=' . $ver;
    }
}

if (!function_exists('e')) {
    function e($value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('warehouse_session_start')) {
    function warehouse_session_start(): void
    {
        if (session_status() !== PHP_SESSION_NONE || headers_sent()) {
            return;
        }

        $isHttps = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
            || (($_SERVER['SERVER_PORT'] ?? '') === '443');

        $cookiePath = warehouse_base_url();
        $cookiePath = ($cookiePath === '' ? '/' : rtrim($cookiePath, '/') . '/');

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');

        session_name('WAREHOUSE_SESSID');
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => PHP_SAPI === 'cli' ? '/' : $cookiePath,
            'domain'   => '',
            'secure'   => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        session_start();
    }
}

if (!function_exists('warehouse_try_kds_sso')) {
    /**
     * Đồng bộ đăng nhập tự động từ phiên PHPSESSID của hệ thống KDS nếu có.
     */
    function warehouse_try_kds_sso(mysqli $authConn): bool
    {
        if (PHP_SAPI === 'cli' || headers_sent()) {
            return false;
        }
        if (!empty($_SESSION['user_id']) && !empty($_SESSION['loggedin'])) {
            return true;
        }
        $kdsSid = $_COOKIE['PHPSESSID'] ?? '';
        if (!is_string($kdsSid) || !preg_match('/^[A-Za-z0-9,-]{16,128}$/', $kdsSid)) {
            return false;
        }

        $whSid = session_id();
        session_write_close();

        // Đọc phiên KDS ở chế độ chỉ đọc để không khóa hoặc sửa session của KDS
        session_name('PHPSESSID');
        session_id($kdsSid);
        @session_start(['read_and_close' => true]);
        $kdsUserId = (int) ($_SESSION['user_id'] ?? 0);
        $_SESSION = [];

        // Khôi phục lại phiên riêng của Warehouse
        $isHttps = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
            || (($_SERVER['SERVER_PORT'] ?? '') === '443');
        $cookiePath = warehouse_base_url();
        $cookiePath = ($cookiePath === '' ? '/' : rtrim($cookiePath, '/') . '/');

        session_name('WAREHOUSE_SESSID');
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => $cookiePath,
            'domain'   => '',
            'secure'   => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        if ($whSid !== '' && preg_match('/^[A-Za-z0-9,-]{16,128}$/', $whSid)) {
            session_id($whSid);
        } else {
            session_id(bin2hex(random_bytes(16)));
        }
        session_start();

        if ($kdsUserId <= 0) {
            return false;
        }

        $stmt = $authConn->prepare('SELECT id, username, fullname, role, status FROM users WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $kdsUserId);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $role = $user ? strtolower(trim((string) ($user['role'] ?? ''))) : '';
        $allowedRoles = ['admin', 'shift_manager'];
        if (!$user || ($user['status'] ?? '') !== 'active' || !in_array($role, $allowedRoles, true)) {
            return false;
        }

        session_regenerate_id(true);
        $_SESSION['user_id']    = (int) $user['id'];
        $_SESSION['username']   = (string) $user['username'];
        $_SESSION['fullname']   = (string) ($user['fullname'] ?? $user['username']);
        $_SESSION['role']       = $role;
        $_SESSION['loggedin']   = true;
        $_SESSION['auth_src']   = 'smartdine_kds';
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

        return true;
    }
}

if (!function_exists('redirect_to')) {
    function redirect_to(string $relativePath): void
    {
        header('Location: ' . warehouse_url($relativePath));
        exit;
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        warehouse_session_start();
        if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
    }
}

if (!function_exists('verify_csrf_token')) {
    function verify_csrf_token(?string $token): bool
    {
        warehouse_session_start();
        $sessionToken = $_SESSION['csrf_token'] ?? '';
        if (!is_string($sessionToken) || $sessionToken === '' || !is_string($token) || $token === '') {
            return false;
        }
        return hash_equals($sessionToken, $token);
    }
}

if (!function_exists('require_csrf')) {
    function require_csrf(): void
    {
        $token = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
        if (!verify_csrf_token(is_string($token) ? $token : null)) {
            http_response_code(403);
            die('Phiên làm việc không hợp lệ hoặc đã hết hạn (CSRF). Vui lòng tải lại trang và thử lại.');
        }
    }
}

if (!function_exists('set_flash')) {
    function set_flash(string $key, string $message): void
    {
        warehouse_session_start();
        $_SESSION['_flash'][$key] = $message;
    }
}

if (!function_exists('get_flash')) {
    function get_flash(string $key): ?string
    {
        warehouse_session_start();
        if (!isset($_SESSION['_flash'][$key])) {
            return null;
        }
        $msg = (string) $_SESSION['_flash'][$key];
        unset($_SESSION['_flash'][$key]);
        return $msg;
    }
}

if (!function_exists('clear_output_buffers')) {
    function clear_output_buffers(): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
    }
}

if (!function_exists('excel_safe')) {
    /**
     * Ngăn ngừa Spreadsheet/CSV Formula Injection khi xuất dữ liệu chuỗi.
     */
    function excel_safe($value)
    {
        if (is_int($value) || is_float($value) || $value === null) {
            return $value;
        }
        $str = (string) $value;
        if ($str === '') {
            return '';
        }
        $firstChar = substr($str, 0, 1);
        if (in_array($firstChar, ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'" . $str;
        }
        return $str;
    }
}

if (!function_exists('content_disposition_header')) {
    function content_disposition_header(string $filename): string
    {
        $asciiFallback = preg_replace('/[^A-Za-z0-9._-]/', '_', $filename);
        if ($asciiFallback === '' || $asciiFallback === '.xlsx') {
            $asciiFallback = 'export.xlsx';
        }
        return 'Content-Disposition: attachment; filename="' . $asciiFallback . '"; filename*=UTF-8\'\'' . rawurlencode($filename);
    }
}

if (!function_exists('parse_vnd_price')) {
    /**
     * Chuyển chuỗi giá VNĐ về số nguyên không âm; trả về null nếu không hợp lệ hoặc âm.
     */
    function parse_vnd_price($val): ?int
    {
        $s = trim((string) ($val ?? ''));
        if ($s === '') {
            return 0;
        }
        if (strpos($s, '-') !== false) {
            return null;
        }
        $s = str_ireplace(['đ', 'vnd', ' '], '', $s);

        if (preg_match('/k$/i', $s)) {
            $num = preg_replace('/[^0-9.,]/', '', rtrim($s, 'kK'));
            $num = str_replace(',', '.', $num);
            if ($num === '' || !is_numeric($num)) {
                return null;
            }
            $parsed = (int) round(((float) $num) * 1000);
            return $parsed >= 0 ? $parsed : null;
        }

        if (preg_match('/[a-zA-Z]/', $s)) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $s);
        if ($digits === '') {
            return null;
        }
        return (int) $digits;
    }
}

if (!function_exists('table_has_column')) {
    function table_has_column(mysqli $conn, string $table, string $column): bool
    {
        static $cache = [];
        $key = $table . '.' . $column;
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }

        if (!preg_match('/^[A-Za-z0-9_]+$/', $table) || !preg_match('/^[A-Za-z0-9_]+$/', $column)) {
            return $cache[$key] = false;
        }

        $colEsc = $conn->real_escape_string($column);
        $res = $conn->query("SHOW COLUMNS FROM `{$table}` LIKE '{$colEsc}'");
        return $cache[$key] = ($res && $res->num_rows > 0);
    }
}

if (!function_exists('record_stock_transaction')) {
    function record_stock_transaction(
        mysqli $conn,
        int $productId,
        string $type,
        int $quantity,
        string $reason,
        ?string $reference,
        string $createdBy,
        ?string $createdAt = null
    ): void {
        if (!in_array($type, ['in', 'out'], true) || $quantity <= 0) {
            throw new InvalidArgumentException('Loại giao dịch hoặc số lượng không hợp lệ.');
        }

        $refVal = ($reference !== null && trim($reference) !== '') ? trim($reference) : null;
        $reasonVal = trim($reason) !== '' ? trim($reason) : ($type === 'in' ? 'Nhập kho' : 'Xuất kho');
        $actor = trim($createdBy) !== '' ? trim($createdBy) : 'system';

        if ($createdAt !== null && preg_match('/^\d{4}-\d{2}-\d{2}(?: \d{2}:\d{2}:\d{2})?$/', $createdAt) === 1) {
            $ts = strlen($createdAt) === 10 ? ($createdAt . ' ' . date('H:i:s')) : $createdAt;
            $stmt = $conn->prepare(
                "INSERT INTO stock_transactions (product_id, transaction_type, quantity, reason, reference, created_by, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->bind_param('isissss', $productId, $type, $quantity, $reasonVal, $refVal, $actor, $ts);
        } else {
            $stmt = $conn->prepare(
                "INSERT INTO stock_transactions (product_id, transaction_type, quantity, reason, reference, created_by, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, NOW())"
            );
            $stmt->bind_param('isisss', $productId, $type, $quantity, $reasonVal, $refVal, $actor);
        }
        $stmt->execute();
        $stmt->close();
    }
}

if (!function_exists('parse_warehouse_import_date')) {
    /**
     * Phân tích cột Ngày tháng năm nhập từ Excel (serial number, dd/mm/yyyy, dd-mm-yyyy, yyyy-mm-dd).
     */
    function parse_warehouse_import_date($rawCell): ?string
    {
        if (is_int($rawCell) || is_float($rawCell) || (is_string($rawCell) && is_numeric(trim($rawCell)) && (float) trim($rawCell) > 1000)) {
            $num = (float) trim((string) $rawCell);
            if ($num >= 1 && $num <= 100000 && class_exists(\PhpOffice\PhpSpreadsheet\Shared\Date::class)) {
                try {
                    return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($num)->format('Y-m-d');
                } catch (Throwable $e) {
                    return null;
                }
            }
        }

        $str = trim((string) ($rawCell ?? ''));
        if ($str === '') {
            return null;
        }

        foreach (['d/m/Y', 'd-m-Y', 'Y-m-d', 'Y/m/d', 'j/n/Y', 'j-n-Y'] as $fmt) {
            $dt = DateTimeImmutable::createFromFormat('!' . $fmt, $str);
            if ($dt !== false && $dt->format($fmt) === $str) {
                return $dt->format('Y-m-d');
            }
        }

        return null;
    }
}

if (!function_exists('upsert_stock_alert')) {
    /**
     * Tạo hoặc cập nhật ngưỡng cảnh báo, đồng thời gộp sạch bản ghi trùng lặp nếu chưa có UNIQUE KEY.
     */
    function upsert_stock_alert(mysqli $conn, int $productId, int $minQuantity, int $isActive = 1): void
    {
        if ($minQuantity < 0) {
            throw new InvalidArgumentException('Ngưỡng cảnh báo tối thiểu phải >= 0.');
        }

        $stmt = $conn->prepare("SELECT id FROM stock_alerts WHERE product_id = ? ORDER BY id ASC");
        $stmt->bind_param('i', $productId);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        if (!empty($rows)) {
            $primaryId = (int) $rows[0]['id'];
            $upd = $conn->prepare("UPDATE stock_alerts SET min_quantity = ?, is_active = ? WHERE id = ?");
            $upd->bind_param('iii', $minQuantity, $isActive, $primaryId);
            $upd->execute();
            $upd->close();

            if (count($rows) > 1) {
                $del = $conn->prepare("DELETE FROM stock_alerts WHERE product_id = ? AND id <> ?");
                $del->bind_param('ii', $productId, $primaryId);
                $del->execute();
                $del->close();
            }
            return;
        }

        $ins = $conn->prepare("INSERT INTO stock_alerts (product_id, min_quantity, is_active) VALUES (?, ?, ?)");
        $ins->bind_param('iii', $productId, $minQuantity, $isActive);
        $ins->execute();
        $ins->close();
    }
}

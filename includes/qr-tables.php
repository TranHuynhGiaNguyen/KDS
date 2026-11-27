<?php

/**
 * SmartDine - Quản lý mã QR gán cứng theo từng bàn ăn & yêu cầu gọi nhân viên.
 * Mỗi bàn trong `dining_tables` được gán duy nhất 1 `qr_token` cố định trong `qr_table_codes`,
 * trỏ trực tiếp tới `qr-order.php?qr=<token>`.
 */

require_once __DIR__ . '/../config/database.php';

/**
 * Sinh token QR 32 ký tự hex cố định từ mã bàn.
 */
function generateFixedTableQrToken(string $tableCode): string
{
    $normalized = strtoupper(trim($tableCode));
    return substr(hash('sha256', 'SMARTDINE_FIXED_QR::' . $normalized), 0, 32);
}

/**
 * Đảm bảo bảng `qr_table_codes` & `qr_staff_calls` tồn tại và mọi bàn đều có 1 QR cố định.
 */
function ensureQrTableCodesSchema(PDO $pdo): void
{
    static $synced = false;

    if ($pdo->inTransaction()) {
        return;
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `qr_table_codes` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `table_code` VARCHAR(20) NOT NULL,
            `qr_token` VARCHAR(64) NOT NULL,
            `is_active` TINYINT(1) NOT NULL DEFAULT 1,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_qr_table_code` (`table_code`),
            UNIQUE KEY `uq_qr_token` (`qr_token`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `qr_staff_calls` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `table_code` VARCHAR(20) NOT NULL,
            `note` VARCHAR(255) NOT NULL DEFAULT '',
            `status` ENUM('pending', 'handled') NOT NULL DEFAULT 'pending',
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `handled_at` DATETIME DEFAULT NULL,
            PRIMARY KEY (`id`),
            INDEX `idx_qr_call_table_status` (`table_code`, `status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    if ($synced) {
        return;
    }

    // Đồng bộ: đảm bảo mọi bàn trong dining_tables chưa có mã QR sẽ được cấp 1 QR gán cứng
    $tables = $pdo->query("SELECT `code`, `status` FROM `dining_tables` ORDER BY `id` ASC")->fetchAll(PDO::FETCH_ASSOC);
    $existingStmt = $pdo->query("SELECT `table_code`, `qr_token` FROM `qr_table_codes`");
    $existingByCode = [];
    foreach ($existingStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $existingByCode[strtoupper(trim((string) $row['table_code']))] = (string) $row['qr_token'];
    }

    $insStmt = $pdo->prepare("
        INSERT IGNORE INTO `qr_table_codes` (`table_code`, `qr_token`, `is_active`)
        VALUES (:table_code, :qr_token, :is_active)
    ");

    foreach ($tables as $t) {
        $code = strtoupper(trim((string) $t['code']));
        if ($code === '' || isset($existingByCode[$code])) {
            continue;
        }
        $token = generateFixedTableQrToken($code);
        $isActive = (($t['status'] ?? 'active') === 'active') ? 1 : 0;
        $insStmt->execute([
            ':table_code' => $code,
            ':qr_token'   => $token,
            ':is_active'  => $isActive,
        ]);
        $existingByCode[$code] = $token;
    }

    $synced = true;
}

/**
 * Lấy bản ghi QR gán cứng của 1 bàn (tự tạo nếu bàn tồn tại trong dining_tables nhưng chưa có dòng QR).
 */
function getOrCreateTableQrCode(PDO $pdo, string $tableCode): ?array
{
    $code = strtoupper(trim($tableCode));
    if ($code === '') {
        return null;
    }

    ensureQrTableCodesSchema($pdo);

    $stmt = $pdo->prepare("
        SELECT q.`id`, q.`table_code`, q.`qr_token`, q.`is_active`, q.`is_active` AS `qr_active`, q.`created_at`,
               t.`name` AS `table_name`, t.`status` AS `table_status`, t.`capacity`
        FROM `qr_table_codes` q
        LEFT JOIN `dining_tables` t ON t.`code` COLLATE utf8mb4_unicode_ci = q.`table_code` COLLATE utf8mb4_unicode_ci
        WHERE q.`table_code` = :code
        LIMIT 1
    ");
    $stmt->execute([':code' => $code]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        return $row;
    }

    $tblStmt = $pdo->prepare("SELECT `code`, `name`, `status`, `capacity` FROM `dining_tables` WHERE `code` = :code LIMIT 1");
    $tblStmt->execute([':code' => $code]);
    $tbl = $tblStmt->fetch(PDO::FETCH_ASSOC);
    if (!$tbl) {
        return null;
    }

    $token = generateFixedTableQrToken($code);
    $isActive = (($tbl['status'] ?? 'active') === 'active') ? 1 : 0;

    $pdo->prepare("
        INSERT IGNORE INTO `qr_table_codes` (`table_code`, `qr_token`, `is_active`)
        VALUES (:table_code, :qr_token, :is_active)
    ")->execute([
        ':table_code' => $code,
        ':qr_token'   => $token,
        ':is_active'  => $isActive,
    ]);

    $stmt->execute([':code' => $code]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

/**
 * Đồng bộ mã QR khi đổi mã bàn hoặc đổi trạng thái bàn (gán cứng theo table_code).
 */
function syncTableQrCodeRecord(PDO $pdo, string $oldCode, string $newCode, string $tableStatus = 'active'): void
{
    if ($pdo->inTransaction()) {
        return;
    }
    ensureQrTableCodesSchema($pdo);

    $oldCode = strtoupper(trim($oldCode));
    $newCode = strtoupper(trim($newCode));
    $isActive = ($tableStatus === 'active') ? 1 : 0;

    if ($oldCode !== '' && $newCode !== '' && $oldCode !== $newCode) {
        $pdo->prepare("UPDATE `qr_table_codes` SET `table_code` = :new_code, `is_active` = :active WHERE `table_code` = :old_code")
            ->execute([':new_code' => $newCode, ':active' => $isActive, ':old_code' => $oldCode]);
    } elseif ($newCode !== '') {
        $pdo->prepare("UPDATE `qr_table_codes` SET `is_active` = :active WHERE `table_code` = :code")
            ->execute([':active' => $isActive, ':code' => $newCode]);
    }

    getOrCreateTableQrCode($pdo, $newCode);
}

/**
 * Tra cứu thông tin bàn từ token QR quét được.
 */
function findTableByQrToken(PDO $pdo, string $token): ?array
{
    $token = strtolower(trim($token));
    if (!preg_match('/^[a-f0-9]{16,64}$/', $token)) {
        return null;
    }

    ensureQrTableCodesSchema($pdo);

    $stmt = $pdo->prepare("
        SELECT q.`id` AS `qr_id`, q.`table_code`, q.`qr_token`, q.`is_active` AS `qr_active`,
               t.`id` AS `table_id`, t.`name` AS `table_name`, t.`status` AS `table_status`,
               t.`capacity`, a.`name` AS `area_name`, a.`code` AS `area_code`
        FROM `qr_table_codes` q
        LEFT JOIN `dining_tables` t ON t.`code` COLLATE utf8mb4_unicode_ci = q.`table_code` COLLATE utf8mb4_unicode_ci
        LEFT JOIN `restaurant_areas` a ON a.`id` = t.`area_id`
        WHERE q.`qr_token` = :token
        LIMIT 1
    ");
    $stmt->execute([':token' => $token]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

/**
 * Lấy danh sách toàn bộ bàn kèm mã QR gán cứng tương ứng.
 */
function getAllTableQrCodes(PDO $pdo): array
{
    ensureQrTableCodesSchema($pdo);

    $stmt = $pdo->query("
        SELECT t.`id`, t.`code` AS `table_code`, t.`name` AS `table_name`, t.`capacity`,
               t.`status` AS `table_status`, a.`name` AS `area_name`, a.`code` AS `area_code`,
               q.`qr_token`, q.`is_active` AS `qr_active`
        FROM `dining_tables` t
        JOIN `restaurant_areas` a ON a.`id` = t.`area_id`
        LEFT JOIN `qr_table_codes` q ON q.`table_code` COLLATE utf8mb4_unicode_ci = t.`code` COLLATE utf8mb4_unicode_ci
        WHERE t.`status` != 'hidden'
        ORDER BY a.`sort_order` ASC, t.`sort_order` ASC, t.`code` ASC
    ");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Xác định đường dẫn web gốc của thư mục dự án SmartDine (vd: '/KDS' hoặc '').
 */
function getSmartDineWebBasePath(): string
{
    $projectRoot = str_replace('\\', '/', (string) realpath(__DIR__ . '/..'));
    $docRoot     = str_replace('\\', '/', (string) realpath((string) ($_SERVER['DOCUMENT_ROOT'] ?? '')));

    if ($projectRoot !== '' && $docRoot !== '' && stripos($projectRoot, $docRoot) === 0) {
        $rel = substr($projectRoot, strlen($docRoot));
        return '/' . trim($rel, '/');
    }

    $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/KDS/index.php'));
    $dir = rtrim(dirname($scriptName), '/');
    if (preg_match('#^(.*)/(pages|api|api/qr-ordering|includes)$#i', $dir, $m)) {
        return $m[1];
    }
    return $dir === '/' ? '' : $dir;
}

/**
 * Tạo URL đầy đủ cho QR Code cố định của bàn trỏ về qr-order.php.
 * Hỗ trợ override 'public_url' trong config/restaurant.php và reverse proxy HTTPS (Cloudflare/Nginx).
 */
function buildTableQrOrderUrl(string $qrToken, string $tableCode = ''): string
{
    $query = ['qr' => $qrToken];
    if ($tableCode !== '') {
        $query['table'] = strtoupper(trim($tableCode));
    }

    $restaurantFile = __DIR__ . '/../config/restaurant.php';
    $publicUrl = trim((string) (getenv('APP_PUBLIC_URL') ?: ''));
    if ($publicUrl === '' && is_file($restaurantFile)) {
        $cfg = require $restaurantFile;
        if (is_array($cfg)) {
            $publicUrl = trim((string) ($cfg['public_url'] ?? ''));
        }
    }
    if ($publicUrl !== '') {
        return rtrim($publicUrl, '/') . '/qr-order.php?' . http_build_query($query);
    }

    $forwardedProto = strtolower(trim(explode(',', (string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''))[0]));
    $cfVisitor      = (string) ($_SERVER['HTTP_CF_VISITOR'] ?? '');
    $https = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? '') === '443')
        || ($forwardedProto === 'https')
        || (strtolower((string) ($_SERVER['HTTP_X_FORWARDED_SSL'] ?? '')) === 'on')
        || (strpos($cfVisitor, '"https"') !== false);

    $host = (string) ($_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? 'localhost');
    $host = trim(explode(',', $host)[0]);
    if (!preg_match('/^[a-zA-Z0-9.\-:\[\]]+$/', $host)) {
        $host = 'localhost';
    }

    $basePath = rtrim(getSmartDineWebBasePath(), '/');

    return ($https ? 'https://' : 'http://') . $host . $basePath . '/qr-order.php?' . http_build_query($query);
}

/**
 * Ghi nhận yêu cầu gọi nhân viên hỗ trợ tại bàn từ khách quét QR.
 */
function createTableStaffCall(PDO $pdo, string $tableCode, string $note = ''): array
{
    ensureQrTableCodesSchema($pdo);
    $code = strtoupper(trim($tableCode));
    if ($code === '') {
        return ['success' => false, 'message' => 'Mã bàn không hợp lệ.'];
    }

    // Tránh spam: nếu có yêu cầu đang chờ trong vòng 15 giây gần nhất thì giữ nguyên
    $recent = $pdo->prepare("
        SELECT `id`, `created_at`
        FROM `qr_staff_calls`
        WHERE `table_code` = :code AND `status` = 'pending'
          AND `created_at` >= DATE_SUB(NOW(), INTERVAL 15 SECOND)
        ORDER BY `id` DESC
        LIMIT 1
    ");
    $recent->execute([':code' => $code]);
    $existing = $recent->fetch(PDO::FETCH_ASSOC);
    if ($existing) {
        return [
            'success' => true,
            'id'      => (int) $existing['id'],
            'message' => 'Đã gửi thông báo gọi nhân viên cho Bàn ' . $code . '.',
        ];
    }

    $cleanNote = mb_substr(trim($note), 0, 255, 'UTF-8');
    $stmt = $pdo->prepare("
        INSERT INTO `qr_staff_calls` (`table_code`, `note`, `status`, `created_at`)
        VALUES (:code, :note, 'pending', NOW())
    ");
    $stmt->execute([
        ':code' => $code,
        ':note' => $cleanNote !== '' ? $cleanNote : 'Khách quét QR yêu cầu hỗ trợ tại bàn',
    ]);

    return [
        'success' => true,
        'id'      => (int) $pdo->lastInsertId(),
        'message' => 'Đã gửi thông báo gọi nhân viên cho Bàn ' . $code . '!',
    ];
}

/**
 * Danh sách bàn đang có yêu cầu gọi nhân viên (keyed theo table_code).
 */
function fetchPendingStaffCalls(PDO $pdo): array
{
    ensureQrTableCodesSchema($pdo);

    $stmt = $pdo->query("
        SELECT `id`, `table_code`, `note`, `created_at`
        FROM `qr_staff_calls`
        WHERE `status` = 'pending'
        ORDER BY `id` DESC
    ");
    $map = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $code = strtoupper(trim((string) $row['table_code']));
        if (!isset($map[$code])) {
            $map[$code] = [
                'id'         => (int) $row['id'],
                'table_code' => $code,
                'note'       => (string) $row['note'],
                'created_at' => (string) $row['created_at'],
            ];
        }
    }
    return $map;
}

/**
 * Đánh dấu đã tiếp nhận hỗ trợ bàn gọi nhân viên.
 */
function resolvePendingStaffCalls(PDO $pdo, string $tableCode): void
{
    ensureQrTableCodesSchema($pdo);
    $code = strtoupper(trim($tableCode));
    if ($code === '') {
        return;
    }

    $pdo->prepare("
        UPDATE `qr_staff_calls`
        SET `status` = 'handled', `handled_at` = NOW()
        WHERE `table_code` = :code AND `status` = 'pending'
    ")->execute([':code' => $code]);
}

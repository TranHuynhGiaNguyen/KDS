<?php

/**
 * SmartDine - Khách đánh giá nhân viên phục vụ sau khi thanh toán.
 *
 * Luồng:
 *   1. Thu ngân chọn nhân viên phục vụ bàn khi thanh toán (có thể bỏ trống).
 *   2. Hóa đơn được cấp một đường dẫn đánh giá riêng (token ngẫu nhiên), in thành mã QR trên hóa đơn.
 *   3. Khách quét QR, mở trang rate.php (không cần đăng nhập) và chấm 1-5 sao, kèm nhận xét.
 *   Mỗi hóa đơn chỉ được đánh giá một lần.
 */

require_once __DIR__ . '/orders.php';
require_once __DIR__ . '/restaurant.php';

const RATING_MIN = 1;
const RATING_MAX = 5;

const RATING_LABELS = [
    1 => 'Rất tệ',
    2 => 'Chưa tốt',
    3 => 'Bình thường',
    4 => 'Tốt',
    5 => 'Xuất sắc',
];

/** Nhân viên phục vụ đang hoạt động, dùng cho ô chọn nhân viên. */
function fetchWaiters(PDO $pdo): array
{
    return $pdo->query("
        SELECT `id`, `fullname` FROM `users`
        WHERE `role` = 'staff' AND `status` = 'active'
        ORDER BY `fullname`
    ")->fetchAll();
}

/**
 * Gợi ý nhân viên phục vụ của bàn: người gọi nhiều món nhất trong đơn đang mở.
 * Nếu chưa có thì lấy chính người đang thu ngân khi họ là nhân viên phục vụ.
 */
function suggestWaiterId(PDO $pdo, string $tableCode, ?int $currentUserId, ?string $currentRole): ?int
{
    $stmt = $pdo->prepare("
        SELECT i.`sent_by`
        FROM `order_items` i
        JOIN `orders` o ON o.`id` = i.`order_id`
        JOIN `users` u ON u.`id` = i.`sent_by` AND u.`role` = 'staff' AND u.`status` = 'active'
        WHERE o.`table_code` = ? AND o.`status` = 'open' AND i.`status` <> 'cancelled'
        GROUP BY i.`sent_by`
        ORDER BY COUNT(*) DESC, MAX(i.`id`) DESC
        LIMIT 1
    ");
    $stmt->execute([$tableCode]);
    $staffId = $stmt->fetchColumn();

    if ($staffId !== false) {
        return (int) $staffId;
    }

    return $currentRole === 'staff' ? $currentUserId : null;
}

function isValidRatingToken(string $token): bool
{
    return preg_match('/^[a-f0-9]{32}$/', $token) === 1;
}

function isActiveWaiter(PDO $pdo, int $staffId): bool
{
    $stmt = $pdo->prepare("SELECT 1 FROM `users` WHERE `id` = ? AND `role` = 'staff' AND `status` = 'active'");
    $stmt->execute([$staffId]);

    return $stmt->fetchColumn() !== false;
}

/**
 * Lưu thông tin đánh giá cho hóa đơn (gọi trong transaction thanh toán). Trả về token.
 * Nếu thu ngân đã chọn số sao trực tiếp lúc thanh toán, lưu luôn điểm sao và ghi chú.
 *
 * @param int|null $waiterId nhân viên phục vụ bàn, null nếu chưa chọn
 * @param int|null $rating   điểm 1-5 sao (tùy chọn)
 * @param string   $comment  ghi chú đánh giá (không bắt buộc)
 * @throws InvalidArgumentException khi nhân viên hoặc số sao không hợp lệ
 */
function createRatingLink(PDO $pdo, int $invoiceId, ?int $waiterId, ?int $rating = null, string $comment = ''): string
{
    if ($waiterId !== null && !isActiveWaiter($pdo, $waiterId)) {
        throw new InvalidArgumentException('Nhân viên phục vụ được chọn không hợp lệ.');
    }

    if ($rating !== null && ($rating < RATING_MIN || $rating > RATING_MAX)) {
        throw new InvalidArgumentException('Số sao đánh giá phải từ ' . RATING_MIN . ' đến ' . RATING_MAX . '.');
    }

    $cleanComment = mb_substr(trim($comment), 0, 255);
    $token        = bin2hex(random_bytes(16));
    $now          = currentTimeForDb();

    $stmt = $pdo->prepare('
        INSERT INTO `service_ratings` (`invoice_id`, `token`, `staff_id`, `rating`, `comment`, `created_at`, `rated_at`)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ');
    $stmt->execute([
        $invoiceId,
        $token,
        $waiterId,
        $rating,
        $cleanComment === '' ? null : $cleanComment,
        $now,
        $rating !== null ? $now : null,
    ]);

    return $token;
}

/** Địa chỉ gốc của hệ thống, dùng để tạo link trong mã QR. Có thể cố định bằng 'public_url' trong config/restaurant.php. */
function appBaseUrl(): string
{
    $configured = trim((string) (getRestaurantInfo()['public_url'] ?? ''));
    if ($configured !== '') {
        return rtrim($configured, '/');
    }

    $scheme  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host    = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $root    = str_replace('\\', '/', (string) realpath(__DIR__ . '/..'));
    $docRoot = rtrim(str_replace('\\', '/', (string) ($_SERVER['DOCUMENT_ROOT'] ?? '')), '/');
    $path    = ($docRoot !== '' && stripos($root, $docRoot) === 0)
        ? substr($root, strlen($docRoot))
        : rtrim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? ''))), '/');

    return $scheme . '://' . $host . $path;
}

function buildRatingUrl(string $token): string
{
    return appBaseUrl() . '/rate.php?t=' . $token;
}

/** Thông tin đánh giá của một hóa đơn (cho màn hình thanh toán), null nếu chưa có link. */
function fetchInvoiceRating(PDO $pdo, int $invoiceId): ?array
{
    $stmt = $pdo->prepare("
        SELECT r.`token`, r.`rating`, r.`comment`, u.`fullname`
        FROM `service_ratings` r
        LEFT JOIN `users` u ON u.`id` = r.`staff_id`
        WHERE r.`invoice_id` = ?
    ");
    $stmt->execute([$invoiceId]);
    $row = $stmt->fetch();

    if (!$row) {
        return null;
    }

    return [
        'token'   => $row['token'],
        'url'     => buildRatingUrl($row['token']),
        'rating'  => $row['rating'] === null ? null : (int) $row['rating'],
        'comment' => (string) $row['comment'],
        'staff'   => $row['fullname'],
    ];
}

/** Dữ liệu cho trang khách đánh giá, tìm theo token. Null nếu token không tồn tại. */
function findRatingByToken(PDO $pdo, string $token): ?array
{
    if (!isValidRatingToken($token)) {
        return null;
    }

    $stmt = $pdo->prepare("
        SELECT r.`staff_id`, r.`rating`, r.`comment`, u.`fullname` AS `staff`,
               v.`order_id`, v.`table_code`, v.`created_at` AS `invoice_time`
        FROM `service_ratings` r
        JOIN `invoices` v ON v.`id` = r.`invoice_id`
        LEFT JOIN `users` u ON u.`id` = r.`staff_id`
        WHERE r.`token` = ?
    ");
    $stmt->execute([$token]);
    $row = $stmt->fetch();

    if (!$row) {
        return null;
    }

    return [
        'staff_id'     => $row['staff_id'] === null ? null : (int) $row['staff_id'],
        'staff'        => $row['staff'],
        'rating'       => $row['rating'] === null ? null : (int) $row['rating'],
        'comment'      => (string) $row['comment'],
        'invoice_code' => formatInvoiceCode((int) $row['order_id']),
        'table'        => $row['table_code'],
        'time'         => $row['invoice_time'],
    ];
}

/**
 * Đọc dữ liệu khách gửi từ trang đánh giá.
 *
 * @return array{rating:int, staff_id:?int, comment:string}
 * @throws InvalidArgumentException khi dữ liệu không hợp lệ
 */
function parseRatingSubmission(array $input): array
{
    $rating = (int) ($input['rating'] ?? 0);

    if ($rating < RATING_MIN || $rating > RATING_MAX) {
        throw new InvalidArgumentException('Vui lòng chọn số sao từ ' . RATING_MIN . ' đến ' . RATING_MAX . '.');
    }

    $staffId = (int) ($input['staff_id'] ?? 0);

    return [
        'rating'   => $rating,
        'staff_id' => $staffId > 0 ? $staffId : null,
        'comment'  => mb_substr(trim((string) ($input['comment'] ?? '')), 0, 255),
    ];
}

/**
 * Lưu đánh giá của khách. Mỗi hóa đơn chỉ đánh giá được một lần.
 * Nếu thu ngân đã chọn nhân viên thì dùng nhân viên đó, nếu chưa thì khách phải chọn.
 *
 * @throws InvalidArgumentException khi token sai, đã đánh giá, hoặc thiếu nhân viên
 */
function submitRating(PDO $pdo, string $token, array $submission): void
{
    $link = findRatingByToken($pdo, $token);

    if ($link === null) {
        throw new InvalidArgumentException('Liên kết đánh giá không hợp lệ.');
    }

    if ($link['rating'] !== null) {
        throw new InvalidArgumentException('Hóa đơn này đã được đánh giá rồi. Cảm ơn quý khách!');
    }

    $staffId = $link['staff_id'] ?? $submission['staff_id'];

    if ($staffId === null || !isActiveWaiter($pdo, $staffId)) {
        throw new InvalidArgumentException('Vui lòng chọn nhân viên phục vụ bạn.');
    }

    $stmt = $pdo->prepare("
        UPDATE `service_ratings`
        SET `staff_id` = ?, `rating` = ?, `comment` = ?, `rated_at` = ?
        WHERE `token` = ? AND `rating` IS NULL
    ");
    $stmt->execute([
        $staffId,
        $submission['rating'],
        $submission['comment'] === '' ? null : $submission['comment'],
        currentTimeForDb(),
        $token,
    ]);

    if ($stmt->rowCount() !== 1) {
        throw new InvalidArgumentException('Hóa đơn này đã được đánh giá rồi. Cảm ơn quý khách!');
    }
}

function renderStars(int $rating): string
{
    return str_repeat('★', $rating) . str_repeat('☆', RATING_MAX - $rating);
}

<?php

require_once __DIR__ . '/../config/database.php';

const RESERVATION_TIMEZONE = 'Asia/Ho_Chi_Minh';
const RESERVATION_DURATION_MINUTES = 120;

function validateReservationSlot(array $data): array
{
    $date = trim((string) ($data['date'] ?? ''));
    $time = trim((string) ($data['time'] ?? ''));
    $guestCount = filter_var($data['guests'] ?? null, FILTER_VALIDATE_INT);
    $areaCode = trim((string) ($data['area'] ?? ''));
    $timezone = new DateTimeZone(RESERVATION_TIMEZONE);

    $dateValue = DateTime::createFromFormat('!Y-m-d', $date, $timezone);
    $timeValue = DateTime::createFromFormat('!H:i', $time, $timezone);
    if (!$dateValue || $dateValue->format('Y-m-d') !== $date || !$timeValue || $timeValue->format('H:i') !== $time) {
        throw new InvalidArgumentException('Vui lòng chọn ngày và giờ hợp lệ.');
    }
    if ($guestCount === false || $guestCount < 1 || $guestCount > 12) {
        throw new InvalidArgumentException('Số người phải từ 1 đến 12.');
    }
    if ($time < '10:00' || $time > '21:30') {
        throw new InvalidArgumentException('Nhà hàng nhận đặt bàn từ 10:00 đến 21:30.');
    }

    $start = DateTime::createFromFormat('!Y-m-d H:i', $date . ' ' . $time, $timezone);
    $now = new DateTime('now', $timezone);
    if (!$start || $start <= $now) {
        throw new InvalidArgumentException('Thời gian đặt bàn phải ở trong tương lai.');
    }

    return [
        'start' => $start,
        'end' => (clone $start)->modify('+' . RESERVATION_DURATION_MINUTES . ' minutes'),
        'guests' => $guestCount,
        'area' => $areaCode,
    ];
}

function reservationSlotValues(array $slot): array
{
    return [
        $slot['start']->format('Y-m-d H:i:s'),
        $slot['end']->format('Y-m-d H:i:s'),
    ];
}

function getAvailableReservationTables(array $slot): array
{
    $pdo = getDbConnection();
    if (!$pdo) {
        throw new RuntimeException('Không thể kết nối cơ sở dữ liệu.');
    }

    [$startAt, $endAt] = reservationSlotValues($slot);
    $sql = "
        SELECT t.`id`, t.`code`, t.`name`, t.`capacity`, t.`shape`, a.`code` AS `area_code`, a.`name` AS `area_name`
        FROM `dining_tables` t
        JOIN `restaurant_areas` a ON a.`id` = t.`area_id` AND a.`status` = 'active'
        WHERE t.`status` = 'active' AND t.`capacity` >= :guests
          AND NOT EXISTS (
              SELECT 1 FROM `reservations` r
              WHERE r.`table_id` = t.`id` AND r.`status` IN ('pending', 'confirmed', 'seated')
                AND TIMESTAMP(r.`reservation_date`, r.`reservation_time`) < :end_at
                AND DATE_ADD(TIMESTAMP(r.`reservation_date`, r.`reservation_time`), INTERVAL r.`duration_minutes` MINUTE) > :start_at
          )
    ";
    $params = [':guests' => $slot['guests'], ':start_at' => $startAt, ':end_at' => $endAt];
    if ($slot['area'] !== '') {
        $sql .= " AND a.`code` = :area_code";
        $params[':area_code'] = $slot['area'];
    }
    $sql .= " ORDER BY a.`sort_order`, t.`sort_order`, t.`code`";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function createReservation(array $data): array
{
    $pdo = getDbConnection();
    if (!$pdo) {
        throw new RuntimeException('Không thể kết nối cơ sở dữ liệu.');
    }

    $slot = validateReservationSlot($data);
    $name = trim((string) ($data['name'] ?? ''));
    $phone = preg_replace('/\D+/', '', (string) ($data['phone'] ?? ''));
    $note = trim((string) ($data['note'] ?? ''));
    $tableId = filter_var($data['table_id'] ?? null, FILTER_VALIDATE_INT);

    if ($name === '' || mb_strlen($name, 'UTF-8') > 100) {
        throw new InvalidArgumentException('Vui lòng nhập họ tên (tối đa 100 ký tự).');
    }
    if (strlen($phone) < 9 || strlen($phone) > 15) {
        throw new InvalidArgumentException('Số điện thoại không hợp lệ.');
    }
    if (mb_strlen($note, 'UTF-8') > 500) {
        throw new InvalidArgumentException('Ghi chú không được vượt quá 500 ký tự.');
    }
    if ($tableId === false || $tableId < 1) {
        throw new InvalidArgumentException('Vui lòng chọn một bàn còn trống.');
    }

    [$startAt, $endAt] = reservationSlotValues($slot);
    try {
        $pdo->beginTransaction();
        $tableStmt = $pdo->prepare("
            SELECT t.`id`, t.`code`, t.`capacity`, a.`id` AS `area_id`, a.`code` AS `area_code`, a.`name` AS `area_name`
            FROM `dining_tables` t
            JOIN `restaurant_areas` a ON a.`id` = t.`area_id`
            WHERE t.`id` = ? AND t.`status` = 'active' AND a.`status` = 'active'
            FOR UPDATE
        ");
        $tableStmt->execute([$tableId]);
        $table = $tableStmt->fetch(PDO::FETCH_ASSOC);
        if (!$table || (int) $table['capacity'] < $slot['guests'] || ($slot['area'] !== '' && $table['area_code'] !== $slot['area'])) {
            throw new InvalidArgumentException('Bàn đã chọn không phù hợp với số người hoặc khu vực.');
        }

        $conflictStmt = $pdo->prepare("
            SELECT 1 FROM `reservations`
            WHERE `table_id` = ? AND `status` IN ('pending', 'confirmed', 'seated')
              AND TIMESTAMP(`reservation_date`, `reservation_time`) < ?
              AND DATE_ADD(TIMESTAMP(`reservation_date`, `reservation_time`), INTERVAL `duration_minutes` MINUTE) > ?
            LIMIT 1
        ");
        $conflictStmt->execute([$tableId, $endAt, $startAt]);
        if ($conflictStmt->fetchColumn()) {
            throw new DomainException('Bàn vừa được khách khác đặt. Vui lòng chọn bàn khác.');
        }

        $reference = 'RSV-' . strtoupper(bin2hex(random_bytes(8)));
        $insert = $pdo->prepare("
            INSERT INTO `reservations`
                (`code`, `customer_name`, `phone`, `reservation_date`, `reservation_time`, `guests`, `area_id`, `table_id`,
                 `note`, `status`, `reservation_code`, `guest_name`, `guest_phone`, `duration_minutes`, `party_size`,
                 `area_name`, `table_code`, `notes`)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'confirmed', ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $insert->execute([
            $reference, $name, $phone, $slot['start']->format('Y-m-d'), $slot['start']->format('H:i:s'),
            $slot['guests'], $table['area_id'], $tableId, $note ?: null, $reference, $name, $phone,
            RESERVATION_DURATION_MINUTES, $slot['guests'], $table['area_name'], $table['code'], $note ?: null,
        ]);
        $pdo->commit();

        return [
            'reference' => $reference,
            'name' => $name,
            'phone' => $phone,
            'table_code' => $table['code'],
            'area_name' => $table['area_name'],
            'starts_at' => $startAt,
            'guests' => $slot['guests'],
        ];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function getReservationHistory(string $phone, string $reference): array
{
    $pdo = getDbConnection();
    if (!$pdo) {
        throw new RuntimeException('Không thể kết nối cơ sở dữ liệu.');
    }

    $normalizedPhone = preg_replace('/\D+/', '', $phone);
    $reference = strtoupper(trim($reference));
    if (strlen($normalizedPhone) < 9 || strlen($normalizedPhone) > 15 || !preg_match('/^RSV-[A-F0-9]{16}$/', $reference)) {
        throw new InvalidArgumentException('Vui lòng nhập số điện thoại và mã xác nhận hợp lệ.');
    }

    $verify = $pdo->prepare("
        SELECT 1 FROM `reservations`
        WHERE (`guest_phone` = ? OR `phone` = ?) AND `reservation_code` = ?
        LIMIT 1
    ");
    $verify->execute([$normalizedPhone, $normalizedPhone, $reference]);
    if (!$verify->fetchColumn()) {
        throw new InvalidArgumentException('Không tìm thấy lịch đặt phù hợp với số điện thoại và mã xác nhận.');
    }

    $stmt = $pdo->prepare("
        SELECT r.`reservation_code` AS `reference_code`, r.`party_size` AS `guest_count`,
               TIMESTAMP(r.`reservation_date`, r.`reservation_time`) AS `starts_at`,
               COALESCE(NULLIF(r.`notes`, ''), r.`note`) AS `note`, r.`status`,
               COALESCE(r.`table_code`, t.`code`) AS `table_code`,
               COALESCE(NULLIF(r.`area_name`, ''), a.`name`) AS `area_name`
        FROM `reservations` r
        LEFT JOIN `dining_tables` t ON t.`id` = r.`table_id`
        LEFT JOIN `restaurant_areas` a ON a.`id` = COALESCE(r.`area_id`, t.`area_id`)
        WHERE r.`guest_phone` = ? OR r.`phone` = ?
        ORDER BY r.`reservation_date` DESC, r.`reservation_time` DESC
        LIMIT 50
    ");
    $stmt->execute([$normalizedPhone, $normalizedPhone]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function cancelReservation(string $phone, string $reference): void
{
    $pdo = getDbConnection();
    if (!$pdo) {
        throw new RuntimeException('Không thể kết nối cơ sở dữ liệu.');
    }

    $normalizedPhone = preg_replace('/\D+/', '', $phone);
    $reference = strtoupper(trim($reference));
    if (strlen($normalizedPhone) < 9 || strlen($normalizedPhone) > 15 || !preg_match('/^RSV-[A-F0-9]{16}$/', $reference)) {
        throw new InvalidArgumentException('Thông tin hủy đặt bàn không hợp lệ.');
    }

    $stmt = $pdo->prepare("
        UPDATE `reservations`
        SET `status` = 'cancelled'
        WHERE (`guest_phone` = ? OR `phone` = ?) AND `reservation_code` = ?
          AND `status` IN ('pending', 'confirmed')
          AND TIMESTAMP(`reservation_date`, `reservation_time`) > ?
    ");
    $now = (new DateTime('now', new DateTimeZone(RESERVATION_TIMEZONE)))->format('Y-m-d H:i:s');
    $stmt->execute([$normalizedPhone, $normalizedPhone, $reference, $now]);
    if ($stmt->rowCount() !== 1) {
        throw new InvalidArgumentException('Không thể hủy lịch này. Lịch có thể đã hủy hoặc đã đến giờ.');
    }
}

function getReservationStatusTransitions(array $reservation, ?DateTimeImmutable $now = null): array
{
    $timezone = new DateTimeZone(RESERVATION_TIMEZONE);
    $startAt = DateTimeImmutable::createFromFormat(
        '!Y-m-d H:i:s',
        $reservation['reservation_date'] . ' ' . $reservation['reservation_time'],
        $timezone
    );
    if (!$startAt) {
        throw new RuntimeException('Lịch đặt bàn có ngày hoặc giờ không hợp lệ.');
    }

    $duration = (int) ($reservation['duration_minutes'] ?? RESERVATION_DURATION_MINUTES);
    $endAt = $startAt->modify('+' . max(1, $duration) . ' minutes');
    $now = $now ?? new DateTimeImmutable('now', $timezone);
    $status = (string) ($reservation['status'] ?? '');

    if ($status === 'pending') {
        return $now < $endAt
            ? ['confirmed' => 'Xác nhận', 'cancelled' => 'Hủy lịch']
            : ['no_show' => 'Đánh dấu vắng'];
    }

    if ($status === 'confirmed') {
        if ($now >= $endAt) {
            return ['no_show' => 'Đánh dấu vắng'];
        }

        $transitions = ['cancelled' => 'Hủy lịch'];
        if ($now >= $startAt->modify('-30 minutes')) {
            $transitions = ['seated' => 'Khách đã đến'] + $transitions;
        }
        return $transitions;
    }

    if ($status === 'seated') {
        return ['completed' => 'Hoàn tất'];
    }

    return [];
}

function updateReservationStatus(int $reservationId, string $targetStatus): void
{
    if ($reservationId < 1) {
        throw new InvalidArgumentException('Lịch đặt bàn không hợp lệ.');
    }

    $statusLabels = [
        'confirmed' => 'đã xác nhận',
        'seated' => 'đang phục vụ',
        'completed' => 'hoàn tất',
        'cancelled' => 'đã hủy',
        'no_show' => 'vắng mặt',
    ];
    if (!isset($statusLabels[$targetStatus])) {
        throw new InvalidArgumentException('Trạng thái cập nhật không hợp lệ.');
    }

    $pdo = getDbConnection();
    if (!$pdo) {
        throw new RuntimeException('Không thể kết nối cơ sở dữ liệu.');
    }

    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("
            SELECT `status`, `reservation_date`, `reservation_time`, `duration_minutes`
            FROM `reservations`
            WHERE `id` = ?
            FOR UPDATE
        ");
        $stmt->execute([$reservationId]);
        $reservation = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$reservation) {
            throw new DomainException('Không tìm thấy lịch đặt bàn.');
        }

        $transitions = getReservationStatusTransitions($reservation);
        if (!isset($transitions[$targetStatus])) {
            $currentLabel = $statusLabels[$reservation['status']] ?? (string) $reservation['status'];
            throw new DomainException('Không thể chuyển lịch từ "' . $currentLabel . '" sang "' . $statusLabels[$targetStatus] . '" vào thời điểm hiện tại.');
        }

        $update = $pdo->prepare("UPDATE `reservations` SET `status` = ? WHERE `id` = ?");
        $update->execute([$targetStatus, $reservationId]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function getReservationSchedule(array $filters = []): array
{
    $pdo = getDbConnection();
    if (!$pdo) {
        throw new RuntimeException('Không thể kết nối cơ sở dữ liệu.');
    }

    $conditions = [];
    $params = [];
    $date = trim((string) ($filters['date'] ?? ''));
    $status = trim((string) ($filters['status'] ?? ''));
    $area = trim((string) ($filters['area'] ?? ''));
    $search = trim((string) ($filters['search'] ?? ''));

    if ($date !== '') {
        $dateValue = DateTime::createFromFormat('!Y-m-d', $date, new DateTimeZone(RESERVATION_TIMEZONE));
        if (!$dateValue || $dateValue->format('Y-m-d') !== $date) {
            throw new InvalidArgumentException('Ngày lọc không hợp lệ.');
        }
        $conditions[] = 'r.`reservation_date` = ?';
        $params[] = $date;
    }

    $validStatuses = ['pending', 'confirmed', 'seated', 'completed', 'cancelled', 'no_show'];
    if ($status !== '' && $status !== 'all') {
        if (!in_array($status, $validStatuses, true)) {
            throw new InvalidArgumentException('Trạng thái lọc không hợp lệ.');
        }
        $conditions[] = 'r.`status` = ?';
        $params[] = $status;
    }

    if ($area !== '') {
        $conditions[] = 'a.`code` = ?';
        $params[] = $area;
    }

    if ($search !== '') {
        $conditions[] = "(
            r.`customer_name` LIKE ? OR r.`guest_name` LIKE ?
            OR r.`phone` LIKE ? OR r.`guest_phone` LIKE ?
            OR r.`reservation_code` LIKE ? OR r.`code` LIKE ?
            OR r.`table_code` LIKE ? OR t.`code` LIKE ?
        )";
        $term = '%' . $search . '%';
        array_push($params, $term, $term, $term, $term, $term, $term, $term, $term);
    }

    $sql = "
        SELECT r.`id`,
               COALESCE(NULLIF(r.`reservation_code`, ''), NULLIF(r.`code`, ''), CONCAT('RSV-', r.`id`)) AS `reference`,
               COALESCE(NULLIF(r.`guest_name`, ''), r.`customer_name`, '') AS `customer_name`,
               COALESCE(NULLIF(r.`guest_phone`, ''), r.`phone`, '') AS `customer_phone`,
               r.`reservation_date`, r.`reservation_time`,
               CASE
                   WHEN r.`party_size` = 1 AND r.`guests` > 1 THEN r.`guests`
                   ELSE COALESCE(NULLIF(r.`party_size`, 0), r.`guests`, 1)
               END AS `guest_count`,
               COALESCE(NULLIF(r.`table_code`, ''), t.`code`, '—') AS `table_code`,
               COALESCE(NULLIF(r.`area_name`, ''), a.`name`, '—') AS `area_name`,
               COALESCE(NULLIF(r.`notes`, ''), r.`note`, '') AS `note`,
               r.`duration_minutes`,
               r.`status`
        FROM `reservations` r
        LEFT JOIN `dining_tables` t ON t.`id` = r.`table_id`
        LEFT JOIN `restaurant_areas` a ON a.`id` = COALESCE(r.`area_id`, t.`area_id`)
    ";

    if ($conditions !== []) {
        $sql .= ' WHERE ' . implode(' AND ', $conditions);
    }
    $sql .= "
        ORDER BY (r.`reservation_date` >= CURRENT_DATE) DESC,
                 r.`reservation_date` ASC, r.`reservation_time` ASC, r.`id` DESC
        LIMIT 200
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

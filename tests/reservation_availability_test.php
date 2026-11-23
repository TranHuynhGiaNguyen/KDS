<?php

require_once __DIR__ . '/../includes/reservations.php';

$pdo = getDbConnection();
if (!$pdo) {
    fwrite(STDERR, "Cannot connect to the test database.\n");
    exit(1);
}

$date = (new DateTime('tomorrow', new DateTimeZone(RESERVATION_TIMEZONE)))->format('Y-m-d');
$slot = validateReservationSlot([
    'date' => $date,
    'time' => '19:00',
    'guests' => 2,
    'area' => '',
]);
$tables = getAvailableReservationTables($slot);
if (!$tables) {
    fwrite(STDERR, "No available table to test reservation conflicts.\n");
    exit(1);
}

$table = $tables[0];
$reference = 'RSV-' . strtoupper(bin2hex(random_bytes(4)));
$pdo->beginTransaction();
try {
    $insert = $pdo->prepare("
        INSERT INTO `reservations`
            (`code`, `reservation_code`, `guest_name`, `guest_phone`, `customer_name`, `phone`,
             `reservation_date`, `reservation_time`, `duration_minutes`, `party_size`, `guests`,
             `table_id`, `table_code`, `area_id`, `area_name`, `status`)
        SELECT ?, ?, 'Availability Test', '0900000000', 'Availability Test', '0900000000',
               ?, '19:00:00', 120, 2, 2, t.`id`, t.`code`, a.`id`, a.`name`, 'confirmed'
        FROM `dining_tables` t
        JOIN `restaurant_areas` a ON a.`id` = t.`area_id`
        WHERE t.`id` = ?
    ");
    $insert->execute([$reference, $reference, $date, $table['id']]);

    $availableIds = array_map('intval', array_column(getAvailableReservationTables($slot), 'id'));
    if (in_array((int) $table['id'], $availableIds, true)) {
        throw new RuntimeException('An overlapping confirmed reservation did not block the table.');
    }

    $cancel = $pdo->prepare("UPDATE `reservations` SET `status` = 'cancelled' WHERE `reservation_code` = ?");
    $cancel->execute([$reference]);
    $availableIds = array_map('intval', array_column(getAvailableReservationTables($slot), 'id'));
    if (!in_array((int) $table['id'], $availableIds, true)) {
        throw new RuntimeException('A cancelled reservation continued to block the table.');
    }
    echo "[PASS] Availability blocks overlapping confirmed reservations and releases cancelled ones.\n";
} finally {
    $pdo->rollBack();
}

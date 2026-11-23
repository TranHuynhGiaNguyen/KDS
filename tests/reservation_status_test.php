<?php

require_once __DIR__ . '/../includes/reservations.php';

function assertReservationStatusTest(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
    echo "[PASS] {$message}\n";
}

$timezone = new DateTimeZone(RESERVATION_TIMEZONE);
$now = new DateTimeImmutable('2026-11-23 17:10:00', $timezone);
$reservation = [
    'reservation_date' => '2026-11-23',
    'reservation_time' => '18:00:00',
    'duration_minutes' => 120,
    'status' => 'pending',
];
$pendingTransitions = getReservationStatusTransitions($reservation, $now);
assertReservationStatusTest(
    isset($pendingTransitions['confirmed'], $pendingTransitions['cancelled']) && !isset($pendingTransitions['no_show']),
    'Pending future reservations can be confirmed or cancelled.'
);

$reservation['status'] = 'confirmed';
assertReservationStatusTest(
    !isset(getReservationStatusTransitions($reservation, $now)['seated']),
    'Guests cannot be marked as arrived more than 30 minutes early.'
);
$reservation['reservation_time'] = '17:30:00';
assertReservationStatusTest(
    isset(getReservationStatusTransitions($reservation, $now)['seated']),
    'Confirmed reservations can be marked as arrived within 30 minutes of the booking.'
);
$reservation['reservation_time'] = '14:00:00';
assertReservationStatusTest(
    array_keys(getReservationStatusTransitions($reservation, $now)) === ['no_show'],
    'Missed reservations can only be marked no-show after their slot ends.'
);
$reservation['status'] = 'seated';
assertReservationStatusTest(
    array_keys(getReservationStatusTransitions($reservation, $now)) === ['completed'],
    'Seated reservations can be completed.'
);

$pdo = getDbConnection();
if (!$pdo) {
    fwrite(STDERR, "Cannot connect to the test database.\n");
    exit(1);
}

$reference = 'RSV-' . strtoupper(bin2hex(random_bytes(8)));
$insert = $pdo->prepare("
    INSERT INTO `reservations`
        (`code`, `reservation_code`, `guest_name`, `guest_phone`, `customer_name`, `phone`,
         `reservation_date`, `reservation_time`, `duration_minutes`, `party_size`, `guests`, `status`)
    VALUES (?, ?, 'Status Test', '0900000000', 'Status Test', '0900000000', ?, '19:00:00', 120, 2, 2, 'pending')
");
$insert->execute([
    $reference,
    $reference,
    (new DateTimeImmutable('tomorrow', $timezone))->format('Y-m-d'),
]);
$reservationId = (int) $pdo->lastInsertId();

try {
    updateReservationStatus($reservationId, 'confirmed');
    $statusStmt = $pdo->prepare("SELECT `status` FROM `reservations` WHERE `id` = ?");
    $statusStmt->execute([$reservationId]);
    assertReservationStatusTest($statusStmt->fetchColumn() === 'confirmed', 'Admin status updates persist to the reservation record.');

    $invalidTransitionRejected = false;
    try {
        updateReservationStatus($reservationId, 'completed');
    } catch (DomainException $e) {
        $invalidTransitionRejected = true;
    }
    assertReservationStatusTest($invalidTransitionRejected, 'Invalid status transitions are rejected.');
} finally {
    $delete = $pdo->prepare("DELETE FROM `reservations` WHERE `id` = ?");
    $delete->execute([$reservationId]);
}

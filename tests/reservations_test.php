<?php

require_once __DIR__ . '/../includes/reservations.php';
require_once __DIR__ . '/../includes/orders.php';

$failures = 0;

function expectReservation(bool $condition, string $message): void
{
    global $failures;
    if (!$condition) {
        $failures++;
        echo "[FAIL] $message\n";
        return;
    }

    echo "[PASS] $message\n";
}

$tomorrow = (new DateTime('tomorrow', new DateTimeZone(RESERVATION_TIMEZONE)))->format('Y-m-d');
$slot = validateReservationSlot([
    'date' => $tomorrow,
    'time' => '19:30',
    'guests' => '4',
    'area' => 'indoor',
]);
expectReservation($slot['guests'] === 4, 'Valid reservation slot preserves guest count');
expectReservation($slot['end']->getTimestamp() - $slot['start']->getTimestamp() === 7200, 'Reservations occupy the table for two hours');

foreach ([
    ['date' => $tomorrow, 'time' => '19:30', 'guests' => '13'],
    ['date' => $tomorrow, 'time' => '09:30', 'guests' => '2'],
    ['date' => '2026-02-31', 'time' => '19:30', 'guests' => '2'],
] as $invalidSlot) {
    try {
        validateReservationSlot($invalidSlot);
        expectReservation(false, 'Invalid reservation slot is rejected');
    } catch (InvalidArgumentException $e) {
        expectReservation(true, 'Invalid reservation slot is rejected');
    }
}

expectReservation(normalizeTableCode('B12') === 'B12', 'Valid table code is preserved');
try {
    normalizeTableCode(null);
    expectReservation(false, 'Missing table code is not silently assigned to B05');
} catch (InvalidArgumentException $e) {
    expectReservation(true, 'Missing table code is not silently assigned to B05');
}

exit($failures === 0 ? 0 : 1);

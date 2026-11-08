<?php
/**
 * SmartDine - Đọc thông tin quán từ config/restaurant.php.
 */

function getRestaurantInfo(): array {
    return require __DIR__ . '/../config/restaurant.php';
}
<?php

/**
 * Kiểm thử phân quyền món trên bàn và đọc đánh giá nhân viên (không cần database).
 * Chạy: php tests/order_permissions_test.php
 */
require __DIR__ . '/../includes/ratings.php';
require __DIR__ . '/../includes/kitchen.php';

$failed = 0;

/**
 * @param mixed $actual
 * @param mixed $expected
 */
function check(string $name, $actual, $expected): void
{
    global $failed;
    if ($actual === $expected) {
        echo "  OK   $name\n";
    } else {
        $failed++;
        echo "  FAIL $name: nhận " . var_export($actual, true) . ", mong đợi " . var_export($expected, true) . "\n";
    }
}

function throwsInvalid(callable $fn): bool
{
    try {
        $fn();
    } catch (InvalidArgumentException $e) {
        return true;
    }
    return false;
}

echo "Quyền thêm / hủy món theo vai trò\n";
check('admin được thêm món', canRoleDoOrderAction('admin', 'add'), true);
check('admin được hủy món', canRoleDoOrderAction('admin', 'cancel'), true);
check('phục vụ được thêm món', canRoleDoOrderAction('staff', 'add'), true);
check('phục vụ KHÔNG được hủy món', canRoleDoOrderAction('staff', 'cancel'), false);
check('bếp không được thêm món', canRoleDoOrderAction('kitchen', 'add'), false);
check('chưa đăng nhập không có quyền', canRoleDoOrderAction(null, 'add'), false);

echo "Nút Hủy món: chỉ admin, cả khi chưa nấu lẫn đã nấu xong\n";
foreach (['unsent', 'pending', 'cooking', 'done'] as $status) {
    check("admin bấm được Hủy món khi $status", availableItemActions(['status' => $status], 'admin'), ['cancel']);
}
foreach (['served', 'cancelled'] as $status) {
    check("admin KHÔNG hủy được món $status", availableItemActions(['status' => $status], 'admin'), []);
}
foreach (['unsent', 'pending', 'cooking', 'done', 'served'] as $status) {
    check("phục vụ không có nút hủy món $status", availableItemActions(['status' => $status], 'staff'), []);
    check("phục vụ không thấy nút khóa món $status", lockedItemActions(['status' => $status], 'staff'), []);
    check("admin không có nút khóa món $status", lockedItemActions(['status' => $status], 'admin'), []);
}

echo "Luồng bếp 1 bước & bỏ nhãn Hoàn thành\n";
check('In phiếu bếp chuyển thẳng sang done', KITCHEN_TRANSITIONS['start']['to'], 'done');
check('Không còn transition finish riêng', isset(KITCHEN_TRANSITIONS['finish']), false);
check('Không còn nhãn Hoàn thành tĩnh', ORDER_ITEM_MARKERS, []);

echo "Đọc đánh giá khách gửi từ trang rate.php\n";
check(
    'đánh giá hợp lệ',
    parseRatingSubmission(['rating' => '5', 'staff_id' => '3', 'comment' => '  Phục vụ tốt  ']),
    ['rating' => 5, 'staff_id' => 3, 'comment' => 'Phục vụ tốt']
);
check('không chọn nhân viên -> staff_id null', parseRatingSubmission(['rating' => 4])['staff_id'], null);
check('nhận xét cắt còn 255 ký tự', mb_strlen(parseRatingSubmission(['rating' => 4, 'comment' => str_repeat('ă', 300)])['comment']), 255);
check('chưa chấm sao bị từ chối', throwsInvalid(fn() => parseRatingSubmission([])), true);
check('0 sao bị từ chối', throwsInvalid(fn() => parseRatingSubmission(['rating' => 0])), true);
check('6 sao bị từ chối', throwsInvalid(fn() => parseRatingSubmission(['rating' => 6])), true);
check('hiển thị sao', renderStars(4), '★★★★☆');

echo "Token đánh giá\n";
check('token đúng định dạng', isValidRatingToken(str_repeat('a1', 16)), true);
check('token sai độ dài', isValidRatingToken('abc'), false);
check('token có ký tự lạ', isValidRatingToken(str_repeat('g', 32)), false);
check('token chèn SQL', isValidRatingToken("' OR 1=1 --"), false);

echo $failed === 0 ? "\nTẤT CẢ ĐỀU ĐẠT\n" : "\n$failed LỖI\n";
exit($failed === 0 ? 0 : 1);

<?php

/**
 * SmartDine - Trang khách đánh giá nhân viên phục vụ.
 * Khách quét mã QR trên hóa đơn để mở trang này (rate.php?t=...), không cần đăng nhập.
 * Mỗi hóa đơn có một token riêng và chỉ đánh giá được một lần.
 */

require_once __DIR__ . '/includes/ratings.php';

$token      = (string) ($_GET['t'] ?? '');
$restaurant = getRestaurantInfo();
$error      = '';
$link       = null;
$waiters    = [];

$postedRating  = (int) ($_POST['rating'] ?? 0);
$postedStaffId = (int) ($_POST['staff_id'] ?? 0);
$postedComment = (string) ($_POST['comment'] ?? '');

try {
    $pdo  = getOrdersDb();
    $link = findRatingByToken($pdo, $token);

    if ($link !== null && $_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            submitRating($pdo, $token, parseRatingSubmission($_POST));
            redirectTo('rate.php?t=' . $token . '&done=1');
        } catch (InvalidArgumentException $e) {
            $error = $e->getMessage();
            $link  = findRatingByToken($pdo, $token);
        }
    }

    if ($link !== null && $link['staff_id'] === null) {
        $waiters = fetchWaiters($pdo);
    }
} catch (RuntimeException | PDOException $e) {
    error_log('rate page: ' . $e->getMessage());
    $error = 'Hệ thống đang bận, vui lòng thử lại sau ít phút.';
}

$isRated = $link !== null && $link['rating'] !== null;
?>
<!doctype html>
<html lang="vi">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Đánh giá phục vụ — <?= escapeHtml($restaurant['name']) ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/rating.css">
</head>

<body class="rating-body">
    <main class="rating-card">
        <p class="rating-brand"><?= escapeHtml($restaurant['name']) ?></p>

        <?php if ($link === null): ?>
            <h1>Liên kết không hợp lệ</h1>
            <p class="rating-muted"><?= $error !== '' ? escapeHtml($error) : 'Liên kết đánh giá không tồn tại hoặc đã bị thay đổi. Quý khách vui lòng quét lại mã QR trên hóa đơn.' ?></p>

        <?php elseif ($isRated): ?>
            <div class="rating-done">
                <div class="rating-done-icon">✓</div>
                <h1>Cảm ơn quý khách!</h1>
                <p class="rating-muted">Đánh giá của quý khách đã được ghi nhận<?= $link['staff'] !== null ? ' cho nhân viên <b>' . escapeHtml($link['staff']) . '</b>' : '' ?>.</p>
                <div class="rating-stars-static" title="<?= escapeHtml(RATING_LABELS[$link['rating']]) ?>"><?= escapeHtml(renderStars($link['rating'])) ?></div>
                <?php if ($link['comment'] !== ''): ?>
                    <p class="rating-quote">“<?= escapeHtml($link['comment']) ?>”</p>
                <?php endif; ?>
            </div>

        <?php else: ?>
            <h1>Quý khách thấy phục vụ thế nào?</h1>
            <p class="rating-muted">
                Hóa đơn <b><?= escapeHtml($link['invoice_code']) ?></b> · Bàn <b><?= escapeHtml($link['table']) ?></b>
                <?php if ($link['staff'] !== null): ?><br>Nhân viên phục vụ: <b><?= escapeHtml($link['staff']) ?></b><?php endif; ?>
            </p>

            <?php if ($error !== ''): ?>
                <div class="rating-error"><?= escapeHtml($error) ?></div>
            <?php endif; ?>

            <form method="post" action="rate.php?t=<?= escapeHtml($token) ?>">
                <div class="star-rating" role="radiogroup" aria-label="Điểm đánh giá">
                    <?php for ($star = RATING_MAX; $star >= RATING_MIN; $star--): ?>
                        <input type="radio" id="star-<?= $star ?>" name="rating" value="<?= $star ?>" data-label="<?= escapeHtml(RATING_LABELS[$star]) ?>" <?= $postedRating === $star ? 'checked' : '' ?> required>
                        <label for="star-<?= $star ?>" title="<?= escapeHtml(RATING_LABELS[$star]) ?>">★</label>
                    <?php endfor; ?>
                </div>
                <p class="rating-label" data-rating-label><?= $postedRating >= RATING_MIN && $postedRating <= RATING_MAX ? escapeHtml(RATING_LABELS[$postedRating]) : 'Chạm vào ngôi sao để chấm điểm' ?></p>

                <?php if ($link['staff_id'] === null): ?>
                    <label class="rating-field-label" for="rating-staff">Nhân viên phục vụ bạn</label>
                    <select class="rating-input" id="rating-staff" name="staff_id" required>
                        <option value="">Chọn nhân viên</option>
                        <?php foreach ($waiters as $waiter): ?>
                            <option value="<?= (int) $waiter['id'] ?>" <?= $postedStaffId === (int) $waiter['id'] ? 'selected' : '' ?>><?= escapeHtml($waiter['fullname']) ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>

                <label class="rating-field-label" for="rating-comment">Nhận xét</label>
                <textarea class="rating-input" id="rating-comment" name="comment" rows="3" maxlength="255" placeholder="Nhập nhận xét (không bắt buộc)"><?= escapeHtml($postedComment) ?></textarea>

                <button class="rating-submit" type="submit">Gửi đánh giá</button>
            </form>
        <?php endif; ?>
    </main>

    <script>
        document.querySelectorAll('.star-rating input').forEach((input) => {
            input.addEventListener('change', () => {
                document.querySelector('[data-rating-label]').textContent = input.dataset.label;
            });
        });
    </script>
</body>

</html>
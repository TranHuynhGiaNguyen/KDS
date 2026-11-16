<?php
declare(strict_types=1);

require_once __DIR__ . '/backend.php';

header('Cache-Control: no-store');
header('Referrer-Policy: same-origin');

$_SESSION['qo_csrf'] =
    $_SESSION['qo_csrf'] ?? bin2hex(random_bytes(32));

function qoEscape($value): string
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}

try {
    if (isset($_GET['api'])) {
        qoApi();
    }

    $pdo = qoDb();

    if (isset($_GET['qr'])) {
        if (!is_string($_GET['qr'])) {
            qoFail(400, 'Mã QR không hợp lệ.');
        }

        $key = qoEnter($pdo, $_GET['qr']);

        header(
            'Location: ordering.php?view=menu&ctx=' .
            rawurlencode($key)
        );

        exit;
    }

    $view = $_GET['view'] ?? 'menu';

    if (
        !is_string($view) ||
        !in_array(
            $view,
            ['menu', 'cart', 'tracking', 'details', 'operations'],
            true
        )
    ) {
        qoFail(404, 'Trang không tồn tại.');
    }

    $ctx = $_GET['ctx'] ?? '';

    if (!is_string($ctx)) {
        qoFail(403, 'Ngữ cảnh bàn không hợp lệ.');
    }

    $role = null;
    $context = null;

    if ($view === 'operations') {
        $role = qoRole($pdo);
    } else {
        $context = qoContext($pdo, $ctx);
    }

    $boot = [
        'view' => $view,
        'ctx' => $ctx,
        'csrf' => $_SESSION['qo_csrf'],
        'role' => $role,
        'context' => $context,
    ];

} catch (Throwable $error) {
    $status = $error instanceof RuntimeException
        ? $error->getCode()
        : 0;

    if (!in_array($status, [400, 403, 404, 409, 503], true)) {
        error_log('QR page: ' . $error->getMessage());

        $status = 500;
        $message =
            'Không mở được trang. Kiểm tra kết nối MySQL và SQL cài đặt.';
    } else {
        $message = $error->getMessage();
    }

    http_response_code($status);

    exit(
        '<meta charset="utf-8"><p>' .
        qoEscape($message) .
        '</p>'
    );
}

$back = 'ordering.php?view=menu&ctx=' . rawurlencode($ctx);
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1, viewport-fit=cover"
    >

    <title>
        SmartDine —
        <?= qoEscape(
            $context ? 'Bàn ' . $context['table_code'] : 'Đơn QR'
        ) ?>
    </title>

    <link rel="stylesheet" href="ordering.css?v=2">

    <script id="qo-boot" type="application/json"><?=
        json_encode(
            $boot,
            JSON_UNESCAPED_UNICODE |
            JSON_HEX_TAG |
            JSON_HEX_AMP |
            JSON_HEX_APOS |
            JSON_HEX_QUOT
        )
    ?></script>

    <script defer src="ordering.js?v=2"></script>
</head>

<body class="<?= $view === 'operations' ? 'operations' : '' ?>">
<div class="app-shell">

    <header class="app-header">

        <?php if ($view === 'cart'): ?>

            <a
                href="<?= qoEscape($back) ?>"
                class="icon-button"
                aria-label="Quay lại thực đơn"
            >‹</a>

            <strong>
                Giỏ hàng - Bàn <?= qoEscape($context['table_code']) ?>
            </strong>

            <button
                type="button"
                id="clear-cart"
                class="icon-button"
                aria-label="Xóa toàn bộ giỏ hàng"
            >⌫</button>

        <?php elseif ($view === 'operations'): ?>

            <a href="../../index.php" class="brand">SmartDine</a>

            <strong>
                <?= qoEscape([
                    'admin' => 'Quản trị',
                    'staff' => 'Phục vụ',
                    'kitchen' => 'Bếp',
                ][$role]) ?>
            </strong>

        <?php elseif ($view === 'menu'): ?>

            <a href="<?= qoEscape($back) ?>" class="brand">
                SmartDine
            </a>

            <span>
                Bàn <?= qoEscape($context['table_code']) ?>
            </span>

            <span class="language-label">VI</span>

        <?php else: ?>

            <a
                href="<?= qoEscape($back) ?>"
                class="icon-button"
                aria-label="Quay lại thực đơn"
            >‹</a>

            <strong>
                Đơn hàng của bạn -
                Bàn <?= qoEscape($context['table_code']) ?>
            </strong>

        <?php endif; ?>

    </header>

    <div
        id="notice"
        role="status"
        aria-live="polite"
        hidden
    ></div>

    <main id="content" aria-busy="true">
        <p class="empty">Đang tải dữ liệu…</p>
    </main>

    <?php if ($view !== 'operations'): ?>

        <a id="floating-cart" class="floating-cart" hidden>
            <span>
                Giỏ hàng (<b id="floating-count">0</b>)
            </span>

            <strong id="floating-total">0đ</strong>
        </a>

        <nav class="bottom-nav" aria-label="Điều hướng khách hàng">
            <a data-view="menu">
                <span>⌂</span>
                Menu
            </a>

            <a data-view="menu" data-add-more>
                <span>♧</span>
                Gọi thêm
            </a>

            <a data-view="details">
                <span>▤</span>
                Đơn hàng
            </a>

            <button type="button" data-payment>
                <span>▣</span>
                Thanh toán
            </button>
        </nav>

    <?php endif; ?>

</div>

<dialog id="dish-dialog" aria-labelledby="dish-title">
    <form id="dish-form">

        <button
            type="button"
            id="close-dialog"
            class="dialog-close"
            aria-label="Đóng chi tiết"
        >×</button>

        <div id="dish-photo"></div>

        <div class="dialog-content">
            <h2 id="dish-title"></h2>

            <p id="dish-description" class="description"></p>

            <strong id="dish-price" class="orange"></strong>

            <div class="quantity-field">
                <span>Số lượng</span>

                <div class="qty">
                    <button
                        type="button"
                        id="dish-minus"
                        aria-label="Giảm số lượng"
                    >−</button>

                    <output id="dish-qty">1</output>

                    <button
                        type="button"
                        id="dish-plus"
                        aria-label="Tăng số lượng"
                    >+</button>
                </div>
            </div>

            <fieldset id="dish-toppings">
                <legend>Topping thêm</legend>
            </fieldset>

            <label class="field">
                Ghi chú cho món

                <textarea
                    id="dish-note"
                    maxlength="500"
                    placeholder="Ví dụ: ít cay, không hành…"
                ></textarea>
            </label>

            <button
                type="submit"
                id="dish-add"
                class="primary wide"
            >Thêm vào giỏ</button>
        </div>

    </form>
</dialog>

</body>
</html>
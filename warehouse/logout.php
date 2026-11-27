<?php
require_once __DIR__ . '/includes/helpers.php';

warehouse_session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if (empty($_SESSION['user_id']) || empty($_SESSION['loggedin'])) {
        redirect_to('login.php');
    }
    ?>
    <!DOCTYPE html>
    <html lang="vi">
    <head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <title>Xác nhận đăng xuất — SmartDine Kho</title>
      <link rel="stylesheet" href="<?= e(asset_url('assets/css/login.css')) ?>">
    </head>
    <body class="login-page-body">
      <div class="login-page-wrapper">
        <div class="login-card-container" style="text-align:center;">
          <div class="login-brand-badge">Xác nhận phiên</div>
          <h2 class="login-title" style="margin-bottom:8px;">Đăng xuất khỏi Kho hàng?</h2>
          <p class="login-subtitle" style="margin-bottom:24px;">Nhấn xác nhận để kết thúc phiên làm việc an toàn.</p>
          <form method="POST" action="" style="display:flex;flex-direction:column;gap:10px;">
            <?= csrf_field() ?>
            <button type="submit" class="btn-login-submit">Xác nhận đăng xuất</button>
            <a href="<?= e(warehouse_url('index.php')) ?>" class="login-back-link" style="justify-content:center;padding:8px;">Hủy, quay lại</a>
          </form>
        </div>
      </div>
    </body>
    </html>
    <?php
    exit;
}

require_csrf();

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

if (session_status() === PHP_SESSION_ACTIVE) {
    session_destroy();
}

redirect_to('login.php?logged_out=1');

<?php
require_once __DIR__ . '/config.php';

warehouse_session_start();

if (!empty($_SESSION['user_id']) && !empty($_SESSION['loggedin'])) {
    redirect_to('index.php');
}

// Tự động nhận phiên đăng nhập KDS nếu đang đăng nhập sẵn (trừ khi vừa bấm đăng xuất)
if (!isset($_GET['logged_out']) && warehouse_try_kds_sso($authConn)) {
    redirect_to('index.php');
}

$error = '';
$usernameValue = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $usernameValue = $username;

    $now = time();
    $attempts = $_SESSION['login_attempts'] ?? ['count' => 0, 'locked_until' => 0];

    if (($attempts['locked_until'] ?? 0) > $now) {
        $waitSec = (int) ($attempts['locked_until'] - $now);
        $error = "Bạn đã thử đăng nhập sai quá nhiều lần. Vui lòng thử lại sau {$waitSec} giây.";
    } elseif ($username === '' || $password === '') {
        $error = 'Vui lòng nhập đầy đủ tên đăng nhập và mật khẩu.';
    } else {
        $stmt = $authConn->prepare('SELECT id, username, password, fullname, role, status FROM users WHERE username = ? LIMIT 1');
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $storedPassword = (string) ($user['password'] ?? '');
        $validPassword = $user && $storedPassword !== '' && password_verify($password, $storedPassword);
        $role = $user ? strtolower(trim((string) ($user['role'] ?? ''))) : '';
        $isActive = $user && (($user['status'] ?? '') === 'active');
        $validRole = in_array($role, ['admin', 'shift_manager'], true);

        if ($validPassword && !$isActive) {
            $error = 'Tài khoản này đã bị khóa. Vui lòng liên hệ Quản trị viên!';
        } elseif ($validPassword && !$validRole) {
            $error = 'Chỉ Quản trị viên (Admin) và Quản lý ca mới có quyền truy cập Quản lý kho!';
        } elseif ($validPassword && $isActive && $validRole) {
            unset($_SESSION['login_attempts']);
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_regenerate_id(true);
            }
            $_SESSION['user_id']    = (int) $user['id'];
            $_SESSION['username']   = (string) $user['username'];
            $_SESSION['fullname']   = (string) ($user['fullname'] ?? $user['username']);
            $_SESSION['role']       = $role;
            $_SESSION['loggedin']   = true;
            $_SESSION['auth_src']   = 'smartdine_kds';
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

            redirect_to('index.php');
        } else {
            $count = (int) ($attempts['count'] ?? 0) + 1;
            $lockedUntil = $count >= 5 ? ($now + 60) : 0;
            $_SESSION['login_attempts'] = [
                'count'        => $count >= 5 ? 0 : $count,
                'locked_until' => $lockedUntil,
            ];
            $error = 'Tên đăng nhập hoặc mật khẩu không chính xác!';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Đăng nhập Kho hàng — SmartDine</title>
  <link rel="stylesheet" href="<?= e(asset_url('assets/css/login.css')) ?>">
</head>
<body class="login-page-body">
  <div class="login-page-wrapper">
    <div class="login-card-container">
      <div class="login-brand-header">
        <div class="login-brand-badge">Hệ thống Quản lý Kho &amp; Tồn kho SmartDine</div>

        <div class="login-brand-logo">
          <span class="brand-mark">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
              <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
              <polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline>
              <line x1="12" y1="22.08" x2="12" y2="12"></line>
            </svg>
          </span>
          <h2 class="login-title">SmartDine Warehouse</h2>
        </div>

        <p class="login-subtitle">Đăng nhập bằng tài khoản SmartDine KDS (Chỉ dành cho Admin và Quản lý ca)</p>
      </div>

      <?php if (isset($_GET['logged_out']) && $error === ''): ?>
        <div style="background:#e6f7ee;border:1px solid #a4e1bc;color:#15803d;padding:12px 14px;border-radius:12px;font-size:13px;font-weight:600;margin-bottom:18px;">
          ✅ Bạn đã đăng xuất an toàn khỏi hệ thống Kho hàng.
        </div>
      <?php endif; ?>

      <?php if ($error !== ''): ?>
        <div class="login-error-alert">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="10"></circle>
            <line x1="12" y1="8" x2="12" y2="12"></line>
            <line x1="12" y1="16" x2="12.01" y2="16"></line>
          </svg>
          <span><?= e($error) ?></span>
        </div>
      <?php endif; ?>

      <form class="login-form" method="POST" action="">
        <?= csrf_field() ?>

        <div class="login-form-group">
          <label for="username">Tên đăng nhập</label>
          <div class="login-input-wrap">
            <span class="input-icon">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                <circle cx="12" cy="7" r="4"></circle>
              </svg>
            </span>
            <input type="text" id="username" name="username" placeholder="Nhập tài khoản KDS (vd: admin, staff...)" value="<?= e($usernameValue) ?>" autocomplete="username" autofocus required>
          </div>
        </div>

        <div class="login-form-group">
          <label for="password">Mật khẩu</label>
          <div class="login-input-wrap">
            <span class="input-icon">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
              </svg>
            </span>
            <input type="password" id="password" name="password" placeholder="Nhập mật khẩu" autocomplete="current-password" required>
            <button type="button" class="btn-toggle-eye" id="btn-toggle-pw" title="Ẩn / Hiện mật khẩu">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                <circle cx="12" cy="12" r="3"></circle>
              </svg>
            </button>
          </div>
        </div>

        <button type="submit" class="btn-login-submit">
          <span>Đăng nhập hệ thống</span>
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <line x1="5" y1="12" x2="19" y2="12"></line>
            <polyline points="12 5 19 12 12 19"></polyline>
          </svg>
        </button>
      </form>

      <div class="login-footer-links">
        <a href="<?= e(dirname(warehouse_base_url()) . '/index.php') ?>" class="login-back-link">
          ← Quay về hệ thống SmartDine KDS
        </a>
      </div>
    </div>
  </div>

  <script>
    document.getElementById('btn-toggle-pw').addEventListener('click', function () {
      const pw = document.getElementById('password');
      pw.type = pw.type === 'password' ? 'text' : 'password';
    });
  </script>
</body>
</html>

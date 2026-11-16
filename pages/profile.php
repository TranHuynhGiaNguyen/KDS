<?php
/**
 * SmartDine - Hồ sơ Cá nhân & Đổi Mật khẩu
 * Dành cho người dùng đang đăng nhập (Admin, Staff, Kitchen)
 */

if (!isLoggedIn()) {
    header('Location: index.php?page=login');
    exit;
}

$userId = (int) $_SESSION['user_id'];
$currUser = getUserById($userId);

if (!$currUser) {
    echo "<div class='flash-alert flash-error'>Không tìm thấy thông tin tài khoản người dùng!</div>";
    return;
}

$msgSuccess = $_SESSION['flash_success'] ?? '';
$msgError = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// XỬ LÝ POST
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = $_POST['action'] ?? '';

    // 1. CẬP NHẬT THÔNG TIN LIÊN HỆ
    if ($action === 'update_profile') {
        $fullname = trim($_POST['fullname'] ?? '');
        $phone    = trim($_POST['phone'] ?? '');
        $email    = trim($_POST['email'] ?? '');

        if (empty($fullname)) {
            $msgError = 'Họ và tên không được để trống!';
        } else {
            $updateData = [
                'fullname' => $fullname,
                'phone'    => $phone,
                'email'    => $email,
            ];

            $res = updateUser($userId, $updateData);
            if ($res['success']) {
                $_SESSION['fullname']   = $fullname;
                $_SESSION['staff_name'] = $fullname;
                $_SESSION['phone']      = $phone;
                $_SESSION['email']      = $email;
                $_SESSION['flash_success'] = 'Cập nhật thông tin cá nhân thành công!';
                header('Location: index.php?page=profile');
                exit;
            } else {
                $msgError = $res['message'];
            }
        }
    }

    // 2. ĐỔI MẬT KHẨU
    if ($action === 'change_password') {
        $currentPassword = trim($_POST['current_password'] ?? '');
        $newPassword     = trim($_POST['new_password'] ?? '');
        $confirmPassword = trim($_POST['confirm_password'] ?? '');

        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            $msgError = 'Vui lòng điền đầy đủ mật khẩu hiện tại và mật khẩu mới!';
        } elseif (strlen($newPassword) < 4) {
            $msgError = 'Mật khẩu mới phải có tối thiểu 4 ký tự!';
        } elseif ($newPassword !== $confirmPassword) {
            $msgError = 'Mật khẩu mới và xác nhận mật khẩu không trùng khớp!';
        } else {
            // Xác thực mật khẩu cũ
            $matched = false;
            if (password_verify($currentPassword, $currUser['password'])) {
                $matched = true;
            } elseif ($currentPassword === $currUser['password']) {
                $matched = true;
            }

            if (!$matched) {
                $msgError = 'Mật khẩu hiện tại không chính xác!';
            } else {
                $res = updateUserPassword($userId, $newPassword);
                if ($res['success']) {
                    $_SESSION['flash_success'] = 'Đổi mật khẩu thành công! Hãy ghi nhớ mật khẩu mới cho lần đăng nhập sau.';
                    header('Location: index.php?page=profile');
                    exit;
                } else {
                    $msgError = $res['message'];
                }
            }
        }
    }
}

// Làm mới thông tin sau cập nhật
$currUser = getUserById($userId);
$userInitial = mb_substr($currUser['fullname'] ?: $currUser['username'], 0, 1, 'UTF-8');
?>

<!-- TIÊU ĐỀ TRANG -->
<div class="dash-header">
  <div>
    <span class="eyebrow">TÀI KHOẢN NGƯỜI DÙNG</span>
    <h1>Hồ sơ Cá nhân & Bảo mật</h1>
    <p>Quản lý thông tin liên hệ cá nhân và đổi mật khẩu đăng nhập hệ thống SmartDine.</p>
  </div>
  <div class="filter-row">
    <a class="btn btn-outline" href="index.php?page=<?= getDefaultPageForRole(currentRole()) ?>">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
      <span>Về trang làm việc</span>
    </a>
  </div>
</div>

<!-- THÔNG BÁO FLASH -->
<?php if (!empty($msgSuccess)): ?>
  <div class="flash-alert flash-success" style="margin-bottom: 20px;">
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
    <span><?= htmlspecialchars($msgSuccess) ?></span>
  </div>
<?php endif; ?>

<?php if (!empty($msgError)): ?>
  <div class="flash-alert flash-error" style="margin-bottom: 20px;">
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
    <span><?= htmlspecialchars($msgError) ?></span>
  </div>
<?php endif; ?>

<!-- THẺ TỔNG QUAN TÀI KHOẢN (PROFILE CARD) -->
<section class="panel" style="margin-bottom: 24px; padding: 24px;">
  <div style="display: flex; align-items: center; gap: 20px; flex-wrap: wrap;">
    <div class="avatar avatar-role-<?= htmlspecialchars($currUser['role']) ?>" style="width: 72px; height: 72px; font-size: 28px; border-radius: 50%; font-weight: 700; display: flex; align-items: center; justify-content: center; background: #ea580c; color: #ffffff; box-shadow: 0 4px 14px rgba(234, 88, 12, 0.25);">
      <?= htmlspecialchars($userInitial) ?>
    </div>
    <div style="flex-grow: 1;">
      <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
        <h2 style="margin: 0; font-size: 20px; font-weight: 700; color: #0f172a;"><?= htmlspecialchars($currUser['fullname']) ?></h2>
        <span style="color: #64748b; font-size: 14px; font-family: monospace;">@<?= htmlspecialchars($currUser['username']) ?></span>
        <?= getRoleBadgeHtml($currUser['role']) ?>
        <?= getStatusBadgeHtml($currUser['status']) ?>
      </div>
      <div style="display: flex; gap: 20px; margin-top: 8px; color: #64748b; font-size: 13px; flex-wrap: wrap;">
        <span>📅 Ngày tạo: <b><?= !empty($currUser['created_at']) ? date('d/m/Y H:i', strtotime($currUser['created_at'])) : 'Ban đầu' ?></b></span>
        <span>⏱ Đăng nhập gần nhất: <b><?= !empty($currUser['last_login']) ? date('d/m/Y H:i', strtotime($currUser['last_login'])) : 'Hiện tại' ?></b></span>
        <?php if (!empty($currUser['phone'])): ?>
          <span>📞 SĐT: <b><?= htmlspecialchars($currUser['phone']) ?></b></span>
        <?php endif; ?>
        <?php if (!empty($currUser['email'])): ?>
          <span>✉ Email: <b><?= htmlspecialchars($currUser['email']) ?></b></span>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>

<!-- GRID 2 CỘT: CẬP NHẬT THÔNG TIN & ĐỔI MẬT KHẨU -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(360px, 1fr)); gap: 24px; margin-bottom: 32px;">
  
  <!-- CỘT 1: FORM CẬP NHẬT THÔNG TIN -->
  <section class="panel" style="padding: 24px;">
    <div class="panel-title" style="margin-bottom: 20px; padding-bottom: 12px; border-bottom: 1px solid #f1f5f9;">
      <div>
        <h3 style="margin: 0 0 4px; font-size: 16px; font-weight: 700; color: #0f172a;">Thông tin cá nhân</h3>
        <p class="muted" style="margin: 0; font-size: 13px;">Cập nhật họ tên hiển thị và thông tin liên hệ của bạn</p>
      </div>
    </div>

    <form method="POST" action="index.php?page=profile">
      <input type="hidden" name="action" value="update_profile">

      <div class="form-group" style="margin-bottom: 16px;">
        <label style="display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 6px;">Tên đăng nhập (Username)</label>
        <input type="text" value="<?= htmlspecialchars($currUser['username']) ?>" disabled style="width: 100%; padding: 10px 12px; background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 6px; font-family: monospace; color: #64748b; font-size: 14px;">
        <small style="color: #94a3b8; font-size: 12px; margin-top: 4px; display: block;">Tên đăng nhập được cố định bởi Quản trị viên hệ thống.</small>
      </div>

      <div class="form-group" style="margin-bottom: 16px;">
        <label style="display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 6px;">Họ và tên hiển thị <span style="color: #dc2626;">*</span></label>
        <input type="text" name="fullname" value="<?= htmlspecialchars($currUser['fullname']) ?>" required style="width: 100%; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px;">
      </div>

      <div class="form-group" style="margin-bottom: 16px;">
        <label style="display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 6px;">Số điện thoại</label>
        <input type="text" name="phone" value="<?= htmlspecialchars($currUser['phone'] ?? '') ?>" placeholder="VD: 0912 345 678" style="width: 100%; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px;">
      </div>

      <div class="form-group" style="margin-bottom: 24px;">
        <label style="display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 6px;">Email liên hệ</label>
        <input type="email" name="email" value="<?= htmlspecialchars($currUser['email'] ?? '') ?>" placeholder="VD: user@smartdine.vn" style="width: 100%; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px;">
      </div>

      <button type="submit" class="btn btn-primary" style="padding: 10px 20px;">
        <span>Lưu thay đổi thông tin</span>
      </button>
    </form>
  </section>

  <!-- CỘT 2: FORM ĐỔI MẬT KHẨU -->
  <section class="panel" style="padding: 24px;">
    <div class="panel-title" style="margin-bottom: 20px; padding-bottom: 12px; border-bottom: 1px solid #f1f5f9;">
      <div>
        <h3 style="margin: 0 0 4px; font-size: 16px; font-weight: 700; color: #0f172a;">Đổi mật khẩu tài khoản</h3>
        <p class="muted" style="margin: 0; font-size: 13px;">Tăng cường bảo mật bằng mật khẩu an toàn và duy nhất</p>
      </div>
    </div>

    <form method="POST" action="index.php?page=profile">
      <input type="hidden" name="action" value="change_password">

      <div class="form-group" style="margin-bottom: 16px;">
        <label style="display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 6px;">Mật khẩu hiện tại <span style="color: #dc2626;">*</span></label>
        <input type="password" name="current_password" required placeholder="Nhập mật khẩu đang dùng" style="width: 100%; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px;">
      </div>

      <div class="form-group" style="margin-bottom: 16px;">
        <label style="display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 6px;">Mật khẩu mới <span style="color: #dc2626;">*</span></label>
        <input type="password" name="new_password" required minlength="4" placeholder="Tối thiểu 4 ký tự" style="width: 100%; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px;">
      </div>

      <div class="form-group" style="margin-bottom: 24px;">
        <label style="display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 6px;">Xác nhận mật khẩu mới <span style="color: #dc2626;">*</span></label>
        <input type="password" name="confirm_password" required minlength="4" placeholder="Nhập lại mật khẩu mới" style="width: 100%; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px;">
      </div>

      <button type="submit" class="btn btn-primary" style="padding: 10px 20px;">
        <span>Cập nhật mật khẩu mới</span>
      </button>
    </form>
  </section>

</div>

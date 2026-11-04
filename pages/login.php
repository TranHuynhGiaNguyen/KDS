<div class="login-page-wrapper">
  <div class="login-card-container">
    <div class="login-brand-header">
      <div class="login-brand-badge">Hệ thống quản lý nhà hàng & Vận hành KDS</div>
      <div class="login-brand-logo">
        <span class="brand-mark">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8h1a4 4 0 0 1 0 8h-1"></path><path d="M2 8h16v9a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V8z"></path><line x1="6" y1="1" x2="6" y2="4"></line><line x1="10" y1="1" x2="10" y2="4"></line><line x1="14" y1="1" x2="14" y2="4"></line></svg>
        </span>
        <h2 class="login-title">SmartDine System</h2>
      </div>
      <p class="login-subtitle">Đăng nhập để truy cập theo phân quyền: Quản trị, Phục vụ hoặc Màn hình Bếp</p>
    </div>

    <!-- Thông báo đăng xuất an toàn -->
    <?php if (isset($_GET['logged_out'])): ?>
      <div class="login-success-alert">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
        <span>Bạn đã đăng xuất an toàn khỏi hệ thống!</span>
      </div>
    <?php endif; ?>

    <!-- Thông báo lỗi đăng nhập -->
    <?php if (!empty($loginError)): ?>
      <div class="login-error-alert">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
        <span><?= htmlspecialchars($loginError) ?></span>
      </div>
    <?php endif; ?>

    <!-- Chọn nhanh tài khoản theo vai trò để trải nghiệm -->
    <div class="login-roles-guide">
      <div class="roles-guide-header">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
        <span>Chọn nhanh tài khoản mẫu theo vai trò:</span>
      </div>
      <div class="roles-preset-grid">
        <button type="button" class="role-preset-btn active" data-user="admin" data-pass="admin123" title="Tài khoản Quản lý toàn quyền">
          <span class="preset-role-badge role-admin">Admin</span>
          <span class="preset-name">Quản trị viên</span>
          <small class="preset-cred">admin / admin123</small>
        </button>
        <button type="button" class="role-preset-btn" data-user="staff" data-pass="staff123" title="Tài khoản Nhân viên phục vụ bàn">
          <span class="preset-role-badge role-staff">Staff</span>
          <span class="preset-name">Phục vụ</span>
          <small class="preset-cred">staff / staff123</small>
        </button>
        <button type="button" class="role-preset-btn" data-user="kitchen" data-pass="kitchen123" title="Tài khoản Nhân viên bếp KDS">
          <span class="preset-role-badge role-kitchen">Kitchen</span>
          <span class="preset-name">Đầu bếp</span>
          <small class="preset-cred">kitchen / kitchen123</small>
        </button>
        <button type="button" class="role-preset-btn" data-user="1" data-pass="1" title="Tài khoản test nhanh">
          <span class="preset-role-badge role-demo">Test</span>
          <span class="preset-name">Demo nhanh</span>
          <small class="preset-cred">1 / 1</small>
        </button>
      </div>
    </div>

    <form method="POST" action="index.php?page=login" class="login-form" id="login-form">
      <div class="login-form-group">
        <label for="username">Tên đăng nhập</label>
        <div class="login-input-wrap">
          <span class="input-icon">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
          </span>
          <input type="text" id="username" name="username" value="admin" placeholder="Nhập tên đăng nhập" required autofocus autocomplete="username">
        </div>
      </div>

      <div class="login-form-group">
        <label for="password">Mật khẩu</label>
        <div class="login-input-wrap">
          <span class="input-icon">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
          </span>
          <input type="password" id="password" name="password" value="admin123" placeholder="Nhập mật khẩu" required autocomplete="current-password">
          <button type="button" class="btn-toggle-eye" id="btn-toggle-pw" title="Ẩn / Hiện mật khẩu">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
          </button>
        </div>
      </div>

      <div class="login-form-extras">
        <label class="remember-check">
          <input type="checkbox" name="remember" checked>
          <span>Ghi nhớ phiên đăng nhập</span>
        </label>
        <span class="role-hint" id="login-role-hint">Vai trò: <strong>Quản trị viên (Toàn quyền)</strong></span>
      </div>

      <button type="submit" name="login_submit" value="1" class="btn btn-login-submit" id="btn-submit-login">
        <span>Đăng nhập hệ thống</span>
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
      </button>
    </form>

    <div class="login-card-footer">
      <span>SmartDine Restaurant Management System & KDS • v2.0 RBAC</span>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const userInp = document.getElementById('username');
  const passInp = document.getElementById('password');
  const roleHint = document.getElementById('login-role-hint');
  const presetBtns = document.querySelectorAll('.role-preset-btn');

  const roleDescMap = {
    'admin': 'Vai trò: <strong>Quản trị viên (Toàn quyền quản lý & báo cáo)</strong>',
    'staff': 'Vai trò: <strong>Nhân viên phục vụ (Sơ đồ bàn & Đơn hàng)</strong>',
    'kitchen': 'Vai trò: <strong>Đầu bếp (Màn hình bếp KDS)</strong>',
    '1': 'Vai trò: <strong>Nhân viên Demo #1</strong>'
  };

  presetBtns.forEach(btn => {
    btn.addEventListener('click', function() {
      presetBtns.forEach(b => b.classList.remove('active'));
      this.classList.add('active');
      const u = this.getAttribute('data-user');
      const p = this.getAttribute('data-pass');
      if (userInp) userInp.value = u;
      if (passInp) passInp.value = p;
      if (roleHint && roleDescMap[u]) {
        roleHint.innerHTML = roleDescMap[u];
      }
    });
  });
});
</script>

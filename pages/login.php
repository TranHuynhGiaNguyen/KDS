<div class="login-page-wrapper">
  <div class="login-card-container">
    <div class="login-brand-header">
      <div class="login-brand-badge">Hệ thống quản lý nhà hàng & Vận hành</div>
      <div class="login-brand-logo">
        <span class="brand-mark">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8h1a4 4 0 0 1 0 8h-1"></path><path d="M2 8h16v9a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V8z"></path><line x1="6" y1="1" x2="6" y2="4"></line><line x1="10" y1="1" x2="10" y2="4"></line><line x1="14" y1="1" x2="14" y2="4"></line></svg>
        </span>
        <h2 class="login-title">SmartDine Staff</h2>
      </div>
      <p class="login-subtitle">Bắt buộc đăng nhập để truy cập trang Staff & sơ đồ bàn phục vụ</p>
    </div>

    <!-- Thông báo tài khoản tạm thời -->
    <div class="login-cred-callout">
      <div class="cred-callout-header">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
        <span>Thông tin đăng nhập tạm thời:</span>
      </div>
      <div class="cred-callout-body">
        <div class="cred-chip">Tài khoản: <strong>1</strong></div>
        <div class="cred-chip">Mật khẩu: <strong>1</strong></div>
      </div>
    </div>

    <?php if (!empty($loginError)): ?>
      <div class="login-error-alert">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
        <span><?=htmlspecialchars($loginError)?></span>
      </div>
    <?php endif; ?>

    <form method="POST" action="index.php?page=login" class="login-form">
      <div class="login-form-group">
        <label for="username">Tài khoản</label>
        <div class="login-input-wrap">
          <span class="input-icon">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
          </span>
          <input type="text" id="username" name="username" value="1" placeholder="Nhập tài khoản" required autofocus autocomplete="username">
        </div>
      </div>

      <div class="login-form-group">
        <label for="password">Mật khẩu</label>
        <div class="login-input-wrap">
          <span class="input-icon">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
          </span>
          <input type="password" id="password" name="password" value="1" placeholder="Nhập mật khẩu" required autocomplete="current-password">
          <button type="button" class="btn-toggle-eye" id="btn-toggle-pw" title="Ẩn / Hiện mật khẩu">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
          </button>
        </div>
      </div>

      <div class="login-form-extras">
        <label class="remember-check">
          <input type="checkbox" name="remember" checked>
          <span>Ghi nhớ đăng nhập</span>
        </label>
      </div>

      <button type="submit" name="login_submit" value="1" class="btn btn-login-submit">
        <span>Đăng nhập vào trang Staff</span>
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
      </button>
    </form>

    <div class="login-card-footer">
      <span>SmartDine Restaurant Management System</span>
    </div>
  </div>
</div>

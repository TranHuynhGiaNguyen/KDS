<?php
// Kiểm tra quyền Admin
if (!isAdmin()) {
    echo "<div class='flash-alert flash-error'>Bạn không có quyền truy cập trang quản lý nhân viên!</div>";
    return;
}

// Xử lý các thao tác CRUD
$msgSuccess = '';
$msgError = '';

// 1. Thêm nhân viên mới
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_user') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $fullname = trim($_POST['fullname'] ?? '');
    $role     = trim($_POST['role'] ?? 'staff');
    $phone    = trim($_POST['phone'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $status   = trim($_POST['status'] ?? 'active');

    if (empty($username) || empty($password) || empty($fullname)) {
        $msgError = 'Vui lòng điền đầy đủ họ tên, tên đăng nhập và mật khẩu!';
    } elseif (strlen($password) < 4) {
        $msgError = 'Mật khẩu phải chứa ít nhất 4 ký tự!';
    } else {
        $res = createUser([
            'username' => $username,
            'password' => $password,
            'fullname' => $fullname,
            'role'     => $role,
            'phone'    => $phone,
            'email'    => $email,
            'status'   => $status
        ]);
        if ($res['success']) {
            $_SESSION['flash_success'] = 'Đã thêm tài khoản nhân viên "' . htmlspecialchars($fullname) . '" thành công!';
            header('Location: index.php?page=users');
            exit;
        } else {
            $msgError = $res['message'];
        }
    }
}

// 2. Sửa thông tin nhân viên
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_user') {
    $userId   = (int) ($_POST['user_id'] ?? 0);
    $username = trim($_POST['username'] ?? '');
    $fullname = trim($_POST['fullname'] ?? '');
    $role     = trim($_POST['role'] ?? 'staff');
    $phone    = trim($_POST['phone'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $status   = trim($_POST['status'] ?? 'active');
    $password = trim($_POST['password'] ?? '');

    // Bảo vệ không cho tự khóa hoặc tự đổi quyền của chính mình khi đang đăng nhập
    if ($userId === (int) $_SESSION['user_id']) {
        if ($status !== 'active') {
            $msgError = 'Bạn không thể tự khóa tài khoản của chính mình!';
        } elseif ($role !== 'admin') {
            $msgError = 'Bạn không thể tự hạ quyền Admin của chính mình!';
        }
    }

    if (empty($msgError)) {
        if (empty($username) || empty($fullname)) {
            $msgError = 'Họ tên và tên đăng nhập không được để trống!';
        } else {
            $updateData = [
                'username' => $username,
                'fullname' => $fullname,
                'role'     => $role,
                'phone'    => $phone,
                'email'    => $email,
                'status'   => $status
            ];
            if (!empty($password)) {
                $updateData['password'] = $password;
            }

            $res = updateUser($userId, $updateData);
            if ($res['success']) {
                // Cập nhật lại session nếu đang sửa chính mình
                if ($userId === (int) $_SESSION['user_id']) {
                    $_SESSION['username'] = $username;
                    $_SESSION['fullname'] = $fullname;
                    $_SESSION['staff_user'] = $username;
                    $_SESSION['staff_name'] = $fullname;
                }
                $_SESSION['flash_success'] = 'Đã cập nhật thông tin tài khoản "' . htmlspecialchars($fullname) . '" thành công!';
                header('Location: index.php?page=users');
                exit;
            } else {
                $msgError = $res['message'];
            }
        }
    }
}

// 3. Đổi mật khẩu
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reset_password') {
    $userId = (int) ($_POST['user_id'] ?? 0);
    $newPassword = trim($_POST['new_password'] ?? '');

    if (empty($newPassword) || strlen($newPassword) < 4) {
        $msgError = 'Mật khẩu mới phải có tối thiểu 4 ký tự!';
    } else {
        $res = updateUserPassword($userId, $newPassword);
        if ($res['success']) {
            $_SESSION['flash_success'] = 'Đã đặt lại mật khẩu mới thành công!';
            header('Location: index.php?page=users');
            exit;
        } else {
            $msgError = $res['message'];
        }
    }
}

// 4. Khóa / Mở khóa nhanh
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_status') {
    $userId = (int) ($_POST['user_id'] ?? 0);
    if ($userId === (int) $_SESSION['user_id']) {
        $msgError = 'Bạn không thể khóa tài khoản đang đăng nhập của chính mình!';
    } else {
        $res = toggleUserStatus($userId);
        if ($res['success']) {
            $_SESSION['flash_success'] = $res['message'];
            header('Location: index.php?page=users');
            exit;
        } else {
            $msgError = $res['message'];
        }
    }
}

// 5. Xóa nhân viên
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_user') {
    $userId = (int) ($_POST['user_id'] ?? 0);
    if ($userId === (int) $_SESSION['user_id']) {
        $msgError = 'Bạn không thể tự xóa tài khoản của chính mình!';
    } else {
        // Kiểm tra xem có phải tài khoản admin duy nhất không
        $stats = getUserStats();
        $targetUser = getUserById($userId);
        if ($targetUser && $targetUser['role'] === 'admin' && $stats['admin'] <= 1) {
            $msgError = 'Không thể xóa tài khoản Quản trị viên (Admin) duy nhất của hệ thống!';
        } else {
            $res = deleteUser($userId);
            if ($res['success']) {
                $_SESSION['flash_success'] = 'Đã xóa tài khoản nhân viên thành công!';
                header('Location: index.php?page=users');
                exit;
            } else {
                $msgError = $res['message'];
            }
        }
    }
}

// Lọc và Tìm kiếm
$filterRole   = trim($_GET['role'] ?? '');
$filterStatus = trim($_GET['status'] ?? '');
$search       = trim($_GET['q'] ?? '');

$users = getAllUsers($filterRole, $filterStatus, $search);
$stats = getUserStats();
?>

<div class="dash-header">
  <div>
    <span class="eyebrow">QUẢN TRỊ NHÂN SỰ & HỆ THỐNG</span>
    <h1>Quản lý Tài khoản & Phân quyền</h1>
    <p>Quản lý danh sách nhân viên, thiết lập vai trò (Admin, Phục vụ, Đầu bếp) và kiểm soát quyền truy cập hệ thống SmartDine.</p>
  </div>
  <div class="filter-row">
    <button class="btn btn-primary-tool" id="btn-open-add-user">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
      <span>Thêm nhân viên mới</span>
    </button>
  </div>
</div>

<?php if (!empty($msgError)): ?>
  <div class="flash-alert flash-error" style="margin-bottom: 20px;">
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
    <span><?= htmlspecialchars($msgError) ?></span>
  </div>
<?php endif; ?>

<!-- Thống kê tài khoản theo vai trò -->
<div class="stat-grid user-stats-grid">
  <div class="stat-card">
    <div class="stat-card-head">
      <span>Tổng nhân sự</span>
      <span class="stat-badge neutral"><?= $stats['total'] ?> NV</span>
    </div>
    <b><?= $stats['total'] ?> tài khoản</b>
    <small class="positive">● <?= $stats['active'] ?> đang kích hoạt hoạt động</small>
  </div>

  <div class="stat-card">
    <div class="stat-card-head">
      <span>Quản trị viên (Admin)</span>
      <span class="stat-badge stat-badge-admin">Toàn quyền</span>
    </div>
    <b><?= $stats['admin'] ?> tài khoản</b>
    <small>Quản lý hệ thống, menu, kho, nhân sự</small>
  </div>

  <div class="stat-card">
    <div class="stat-card-head">
      <span>Nhân viên phục vụ (Staff)</span>
      <span class="stat-badge stat-badge-staff">Sơ đồ bàn</span>
    </div>
    <b><?= $stats['staff'] ?> tài khoản</b>
    <small>Vận hành bàn ăn, tạo đơn, tính tiền</small>
  </div>

  <div class="stat-card">
    <div class="stat-card-head">
      <span>Đầu bếp (Kitchen)</span>
      <span class="stat-badge stat-badge-kitchen">Màn hình KDS</span>
    </div>
    <b><?= $stats['kitchen'] ?> tài khoản</b>
    <small>Chế biến món ăn & đổi trạng thái món</small>
  </div>
</div>

<!-- Khối danh sách và bộ lọc -->
<section class="panel users-panel">
  <div class="panel-title users-panel-header">
    <div class="grow">
      <h2>Danh sách tài khoản nhân viên</h2>
      <p class="muted">Hiển thị thông tin phân quyền và tình trạng hoạt động của từng nhân sự</p>
    </div>
  </div>

  <!-- Thanh tìm kiếm và bộ lọc nhanh -->
  <form method="GET" action="index.php" class="user-filter-bar">
    <input type="hidden" name="page" value="users">
    
    <div class="search-input-wrap">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
      <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Tìm theo họ tên, username, SĐT, email...">
    </div>

    <div class="filter-controls-group">
      <label class="select-wrapper">
        <select name="role" onchange="this.form.submit()">
          <option value="">Tất cả vai trò</option>
          <option value="admin" <?= $filterRole === 'admin' ? 'selected' : '' ?>>Quản trị viên (Admin)</option>
          <option value="staff" <?= $filterRole === 'staff' ? 'selected' : '' ?>>Phục vụ (Staff)</option>
          <option value="kitchen" <?= $filterRole === 'kitchen' ? 'selected' : '' ?>>Đầu bếp (Kitchen)</option>
        </select>
      </label>

      <label class="select-wrapper">
        <select name="status" onchange="this.form.submit()">
          <option value="">Tất cả trạng thái</option>
          <option value="active" <?= $filterStatus === 'active' ? 'selected' : '' ?>>Đang hoạt động</option>
          <option value="inactive" <?= $filterStatus === 'inactive' ? 'selected' : '' ?>>Đang bị khóa</option>
        </select>
      </label>

      <button type="submit" class="btn btn-small btn-primary">Tìm kiếm</button>
      
      <?php if (!empty($search) || !empty($filterRole) || !empty($filterStatus)): ?>
        <a href="index.php?page=users" class="btn btn-small btn-outline" title="Xóa bộ lọc">Xóa lọc ✕</a>
      <?php endif; ?>
    </div>
  </form>

  <!-- Bảng danh sách nhân viên -->
  <div class="table-responsive">
    <table class="users-table">
      <thead>
        <tr>
          <th style="width: 70px;">Mã NV</th>
          <th>Nhân viên</th>
          <th>Liên hệ</th>
          <th>Vai trò</th>
          <th>Trạng thái</th>
          <th>Lần đăng nhập cuối</th>
          <th style="text-align: right; width: 180px;">Thao tác</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($users)): ?>
          <tr>
            <td colspan="7" class="table-empty-cell">
              <div class="empty-state-table">
                <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="10"></circle><line x1="8" y1="12" x2="16" y2="12"></line></svg>
                <h4>Không tìm thấy nhân viên nào</h4>
                <p class="muted">Hãy thử thay đổi điều kiện tìm kiếm hoặc thêm nhân viên mới.</p>
              </div>
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($users as $u): 
            $isSelf = ((int)$u['id'] === (int)$_SESSION['user_id']);
            $initial = mb_substr($u['fullname'], 0, 1, 'UTF-8');
            $code = 'NV' . str_pad($u['id'], 3, '0', STR_PAD_LEFT);
            $lastLoginFormatted = !empty($u['last_login']) ? date('H:i d/m/Y', strtotime($u['last_login'])) : 'Chưa đăng nhập';
          ?>
            <tr class="<?= $u['status'] === 'inactive' ? 'row-inactive' : '' ?>">
              <td>
                <span class="user-code-badge"><?= $code ?></span>
              </td>
              <td>
                <div class="user-profile-cell">
                  <div class="avatar avatar-role-<?= htmlspecialchars($u['role']) ?>">
                    <?= htmlspecialchars($initial) ?>
                  </div>
                  <div class="user-profile-meta">
                    <b class="user-profile-name">
                      <?= htmlspecialchars($u['fullname']) ?>
                      <?php if ($isSelf): ?>
                        <span class="badge-self" title="Tài khoản đang đăng nhập">Bạn</span>
                      <?php endif; ?>
                    </b>
                    <small class="user-profile-username">@<?= htmlspecialchars($u['username']) ?></small>
                  </div>
                </div>
              </td>
              <td>
                <div class="contact-meta">
                  <span>
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>
                    <?= htmlspecialchars($u['phone'] ?: 'Chưa cập nhật') ?>
                  </span>
                  <small class="text-secondary">
                    <?= htmlspecialchars($u['email'] ?: 'Chưa cập nhật') ?>
                  </small>
                </div>
              </td>
              <td>
                <?= getRoleBadgeHtml($u['role']) ?>
              </td>
              <td>
                <?= getStatusBadgeHtml($u['status']) ?>
              </td>
              <td>
                <span class="last-login-text" title="<?= htmlspecialchars($u['last_login'] ?? '') ?>">
                  <?= $lastLoginFormatted ?>
                </span>
              </td>
              <td style="text-align: right;">
                <div class="action-buttons-group">
                  <!-- Nút Sửa -->
                  <button type="button" class="btn-icon btn-edit-user" 
                          data-id="<?= $u['id'] ?>"
                          data-username="<?= htmlspecialchars($u['username']) ?>"
                          data-fullname="<?= htmlspecialchars($u['fullname']) ?>"
                          data-role="<?= htmlspecialchars($u['role']) ?>"
                          data-phone="<?= htmlspecialchars($u['phone'] ?? '') ?>"
                          data-email="<?= htmlspecialchars($u['email'] ?? '') ?>"
                          data-status="<?= htmlspecialchars($u['status']) ?>"
                          data-isself="<?= $isSelf ? '1' : '0' ?>"
                          title="Chỉnh sửa thông tin">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"></path><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path></svg>
                  </button>

                  <!-- Nút Đổi mật khẩu -->
                  <button type="button" class="btn-icon btn-reset-pw"
                          data-id="<?= $u['id'] ?>"
                          data-fullname="<?= htmlspecialchars($u['fullname']) ?>"
                          title="Đặt lại mật khẩu">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                  </button>

                  <!-- Nút Khóa / Kích hoạt nhanh -->
                  <?php if (!$isSelf): ?>
                    <form method="POST" action="index.php?page=users" style="display:inline;" onsubmit="return confirm('Bạn có chắc muốn <?= $u['status'] === 'active' ? 'KHÓA' : 'MỞ KHÓA' ?> tài khoản nhân viên này?')">
                      <input type="hidden" name="action" value="toggle_status">
                      <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                      <button type="submit" class="btn-icon <?= $u['status'] === 'active' ? 'btn-toggle-lock' : 'btn-toggle-unlock' ?>" title="<?= $u['status'] === 'active' ? 'Khóa tài khoản' : 'Mở khóa tài khoản' ?>">
                        <?php if ($u['status'] === 'active'): ?>
                          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                        <?php else: ?>
                          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 9.9-1"></path></svg>
                        <?php endif; ?>
                      </button>
                    </form>
                  <?php endif; ?>

                  <!-- Nút Xóa -->
                  <?php if (!$isSelf): ?>
                    <button type="button" class="btn-icon btn-delete-user"
                            data-id="<?= $u['id'] ?>"
                            data-fullname="<?= htmlspecialchars($u['fullname']) ?>"
                            title="Xóa tài khoản">
                      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                    </button>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>

<!-- ==========================================================
     MODAL 1: THÊM NHÂN VIÊN MỚI
     ========================================================== -->
<div class="custom-modal-backdrop" id="modal-add-user" style="display: none;">
  <div class="custom-modal-dialog">
    <div class="custom-modal-header">
      <div class="modal-title-wrap">
        <div class="modal-icon-badge">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="8.5" cy="7" r="4"></circle><line x1="20" y1="8" x2="20" y2="14"></line><line x1="23" y1="11" x2="17" y2="11"></line></svg>
        </div>
        <div>
          <h3>Thêm nhân viên mới</h3>
          <p class="muted">Tạo tài khoản và phân quyền vai trò làm việc trong hệ thống</p>
        </div>
      </div>
      <button type="button" class="btn-close-modal" data-close="modal-add-user">✕</button>
    </div>

    <form method="POST" action="index.php?page=users" class="modal-form">
      <input type="hidden" name="action" value="add_user">

      <div class="form-grid-2">
        <div class="form-group">
          <label for="add-fullname">Họ và tên nhân viên <span class="required">*</span></label>
          <input type="text" id="add-fullname" name="fullname" placeholder="VD: Nguyễn Văn An" required>
        </div>

        <div class="form-group">
          <label for="add-username">Tên đăng nhập <span class="required">*</span></label>
          <input type="text" id="add-username" name="username" placeholder="VD: an.nv" required pattern="[a-zA-Z0-9_\.]+" title="Chỉ chữ cái không dấu, số, dấu gạch dưới hoặc chấm">
        </div>
      </div>

      <div class="form-grid-2">
        <div class="form-group">
          <label for="add-password">Mật khẩu khởi tạo <span class="required">*</span></label>
          <input type="password" id="add-password" name="password" placeholder="Tối thiểu 4 ký tự" required minlength="4">
        </div>

        <div class="form-group">
          <label for="add-role">Vai trò & Phân quyền <span class="required">*</span></label>
          <select id="add-role" name="role" required>
            <option value="staff" selected>Nhân viên phục vụ (Sơ đồ bàn, gọi món)</option>
            <option value="kitchen">Đầu bếp (Màn hình bếp KDS)</option>
            <option value="admin">Quản trị viên (Toàn quyền quản trị)</option>
          </select>
        </div>
      </div>

      <div class="form-grid-2">
        <div class="form-group">
          <label for="add-phone">Số điện thoại</label>
          <input type="tel" id="add-phone" name="phone" placeholder="VD: 0912345678">
        </div>

        <div class="form-group">
          <label for="add-email">Email</label>
          <input type="email" id="add-email" name="email" placeholder="VD: an.nguyen@smartdine.vn">
        </div>
      </div>

      <div class="form-group">
        <label for="add-status">Trạng thái tài khoản</label>
        <select id="add-status" name="status">
          <option value="active" selected>Hoạt động (Có thể đăng nhập)</option>
          <option value="inactive">Tạm khóa (Chưa cho phép đăng nhập)</option>
        </select>
      </div>

      <div class="modal-actions-footer">
        <button type="button" class="btn btn-outline" data-close="modal-add-user">Hủy bỏ</button>
        <button type="submit" class="btn btn-primary">Xác nhận tạo tài khoản</button>
      </div>
    </form>
  </div>
</div>

<!-- ==========================================================
     MODAL 2: CHỈNH SỬA THÔNG TIN NHÂN VIÊN
     ========================================================== -->
<div class="custom-modal-backdrop" id="modal-edit-user" style="display: none;">
  <div class="custom-modal-dialog">
    <div class="custom-modal-header">
      <div class="modal-title-wrap">
        <div class="modal-icon-badge">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M12 20h9"></path><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path></svg>
        </div>
        <div>
          <h3>Chỉnh sửa tài khoản nhân viên</h3>
          <p class="muted">Cập nhật thông tin cá nhân và quyền hạn vai trò</p>
        </div>
      </div>
      <button type="button" class="btn-close-modal" data-close="modal-edit-user">✕</button>
    </div>

    <form method="POST" action="index.php?page=users" class="modal-form" id="form-edit-user">
      <input type="hidden" name="action" value="edit_user">
      <input type="hidden" name="user_id" id="edit-user-id">

      <div class="form-grid-2">
        <div class="form-group">
          <label for="edit-fullname">Họ và tên nhân viên <span class="required">*</span></label>
          <input type="text" id="edit-fullname" name="fullname" required>
        </div>

        <div class="form-group">
          <label for="edit-username">Tên đăng nhập <span class="required">*</span></label>
          <input type="text" id="edit-username" name="username" required pattern="[a-zA-Z0-9_\.]+" title="Chữ cái không dấu, số hoặc chấm">
        </div>
      </div>

      <div class="form-grid-2">
        <div class="form-group">
          <label for="edit-role">Vai trò & Phân quyền <span class="required">*</span></label>
          <select id="edit-role" name="role" required>
            <option value="staff">Nhân viên phục vụ (Sơ đồ bàn, gọi món)</option>
            <option value="kitchen">Đầu bếp (Màn hình bếp KDS)</option>
            <option value="admin">Quản trị viên (Toàn quyền quản trị)</option>
          </select>
          <small class="muted" id="edit-role-note" style="display:none; color: var(--yellow);">* Bạn không thể tự hạ quyền của chính mình</small>
        </div>

        <div class="form-group">
          <label for="edit-status">Trạng thái hoạt động <span class="required">*</span></label>
          <select id="edit-status" name="status" required>
            <option value="active">Đang hoạt động</option>
            <option value="inactive">Đã tạm khóa</option>
          </select>
          <small class="muted" id="edit-status-note" style="display:none; color: var(--yellow);">* Bạn không thể tự khóa tài khoản của chính mình</small>
        </div>
      </div>

      <div class="form-grid-2">
        <div class="form-group">
          <label for="edit-phone">Số điện thoại</label>
          <input type="tel" id="edit-phone" name="phone">
        </div>

        <div class="form-group">
          <label for="edit-email">Email</label>
          <input type="email" id="edit-email" name="email">
        </div>
      </div>

      <div class="form-group">
        <label for="edit-password">Mật khẩu mới (Bỏ trống nếu không muốn đổi)</label>
        <input type="password" id="edit-password" name="password" placeholder="Để trống nếu giữ nguyên mật khẩu hiện tại" minlength="4">
      </div>

      <div class="modal-actions-footer">
        <button type="button" class="btn btn-outline" data-close="modal-edit-user">Hủy bỏ</button>
        <button type="submit" class="btn btn-primary">Lưu thay đổi</button>
      </div>
    </form>
  </div>
</div>

<!-- ==========================================================
     MODAL 3: ĐẶT LẠI MẬT KHẨU
     ========================================================== -->
<div class="custom-modal-backdrop" id="modal-reset-pw" style="display: none;">
  <div class="custom-modal-dialog custom-modal-sm">
    <div class="custom-modal-header">
      <div class="modal-title-wrap">
        <div class="modal-icon-badge" style="background: var(--yellow-light); color: var(--yellow);">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
        </div>
        <div>
          <h3>Đặt lại mật khẩu</h3>
          <p class="muted" id="reset-pw-user-name">Nhân viên: ...</p>
        </div>
      </div>
      <button type="button" class="btn-close-modal" data-close="modal-reset-pw">✕</button>
    </div>

    <form method="POST" action="index.php?page=users" class="modal-form">
      <input type="hidden" name="action" value="reset_password">
      <input type="hidden" name="user_id" id="reset-pw-user-id">

      <div class="form-group">
        <label for="reset-new-password">Mật khẩu mới <span class="required">*</span></label>
        <input type="password" id="reset-new-password" name="new_password" placeholder="Nhập tối thiểu 4 ký tự" required minlength="4">
      </div>

      <div class="modal-actions-footer">
        <button type="button" class="btn btn-outline" data-close="modal-reset-pw">Hủy</button>
        <button type="submit" class="btn btn-primary">Xác nhận đổi mật khẩu</button>
      </div>
    </form>
  </div>
</div>

<!-- ==========================================================
     MODAL 4: XÁC NHẬN XÓA TÀI KHOẢN
     ========================================================== -->
<div class="custom-modal-backdrop" id="modal-delete-user" style="display: none;">
  <div class="custom-modal-dialog custom-modal-sm">
    <div class="custom-modal-header">
      <div class="modal-title-wrap">
        <div class="modal-icon-badge" style="background: var(--red-light); color: var(--red);">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
        </div>
        <div>
          <h3>Xác nhận xóa tài khoản</h3>
          <p class="muted">Hành động này không thể hoàn tác</p>
        </div>
      </div>
      <button type="button" class="btn-close-modal" data-close="modal-delete-user">✕</button>
    </div>

    <form method="POST" action="index.php?page=users" class="modal-form">
      <input type="hidden" name="action" value="delete_user">
      <input type="hidden" name="user_id" id="delete-user-id">

      <div style="padding: 10px 0 20px;">
        <p>Bạn có chắc chắn muốn xóa vĩnh viễn tài khoản nhân viên <strong id="delete-user-name">...</strong> khỏi hệ thống?</p>
      </div>

      <div class="modal-actions-footer">
        <button type="button" class="btn btn-outline" data-close="modal-delete-user">Hủy</button>
        <button type="submit" class="btn btn-danger">Xóa tài khoản vĩnh viễn</button>
      </div>
    </form>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  // Đóng mở modal
  const openModal = id => {
    const m = document.getElementById(id);
    if (m) m.style.display = 'flex';
  };
  const closeModal = id => {
    const m = document.getElementById(id);
    if (m) m.style.display = 'none';
  };

  // Nút mở modal thêm
  const btnAdd = document.getElementById('btn-open-add-user');
  if (btnAdd) {
    btnAdd.addEventListener('click', () => openModal('modal-add-user'));
  }

  // Nút đóng tất cả modal
  document.querySelectorAll('[data-close]').forEach(btn => {
    btn.addEventListener('click', function() {
      const targetId = this.getAttribute('data-close');
      closeModal(targetId);
    });
  });

  // Bấm ra ngoài backdrop để đóng modal
  document.querySelectorAll('.custom-modal-backdrop').forEach(modal => {
    modal.addEventListener('click', function(e) {
      if (e.target === this) {
        this.style.display = 'none';
      }
    });
  });

  // Nút Sửa nhân viên
  document.querySelectorAll('.btn-edit-user').forEach(btn => {
    btn.addEventListener('click', function() {
      const id = this.getAttribute('data-id');
      const username = this.getAttribute('data-username');
      const fullname = this.getAttribute('data-fullname');
      const role = this.getAttribute('data-role');
      const phone = this.getAttribute('data-phone');
      const email = this.getAttribute('data-email');
      const status = this.getAttribute('data-status');
      const isSelf = this.getAttribute('data-isself') === '1';

      document.getElementById('edit-user-id').value = id;
      document.getElementById('edit-username').value = username;
      document.getElementById('edit-fullname').value = fullname;
      document.getElementById('edit-role').value = role;
      document.getElementById('edit-phone').value = phone;
      document.getElementById('edit-email').value = email;
      document.getElementById('edit-status').value = status;
      document.getElementById('edit-password').value = '';

      const roleSelect = document.getElementById('edit-role');
      const statusSelect = document.getElementById('edit-status');
      const roleNote = document.getElementById('edit-role-note');
      const statusNote = document.getElementById('edit-status-note');

      if (isSelf) {
        roleSelect.disabled = true;
        statusSelect.disabled = true;
        if (roleNote) roleNote.style.display = 'block';
        if (statusNote) statusNote.style.display = 'block';
      } else {
        roleSelect.disabled = false;
        statusSelect.disabled = false;
        if (roleNote) roleNote.style.display = 'none';
        if (statusNote) statusNote.style.display = 'none';
      }

      openModal('modal-edit-user');
    });
  });

  // Nút Đặt lại mật khẩu
  document.querySelectorAll('.btn-reset-pw').forEach(btn => {
    btn.addEventListener('click', function() {
      const id = this.getAttribute('data-id');
      const fullname = this.getAttribute('data-fullname');

      document.getElementById('reset-pw-user-id').value = id;
      document.getElementById('reset-pw-user-name').textContent = 'Nhân viên: ' + fullname;
      document.getElementById('reset-new-password').value = '';

      openModal('modal-reset-pw');
    });
  });

  // Nút Xóa nhân viên
  document.querySelectorAll('.btn-delete-user').forEach(btn => {
    btn.addEventListener('click', function() {
      const id = this.getAttribute('data-id');
      const fullname = this.getAttribute('data-fullname');

      document.getElementById('delete-user-id').value = id;
      document.getElementById('delete-user-name').textContent = fullname;

      openModal('modal-delete-user');
    });
  });
});
</script>

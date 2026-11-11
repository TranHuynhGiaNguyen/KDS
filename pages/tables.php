<?php
/**
 * SmartDine - Quản lý Bàn ăn & Khu vực Nhà hàng
 * Dành riêng cho Quản trị viên (Admin)
 */

if (!isAdmin()) {
    $_SESSION['flash_error'] = 'Chỉ Quản trị viên (Admin) mới có quyền truy cập trang Quản lý bàn & khu vực!';
    header('Location: index.php?page=' . getDefaultPageForRole(currentRole()));
    exit;
}

require_once __DIR__ . '/../includes/tables.php';

// ==========================================================
// XỬ LÝ CÁC HÀNH ĐỘNG POST (CRUD)
// ==========================================================
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = $_POST['action'] ?? '';

    // 1. THÊM BÀN ĂN MỚI
    if ($action === 'add_table') {
        $code      = trim($_POST['code'] ?? '');
        $name      = trim($_POST['name'] ?? '');
        $areaId    = (int) ($_POST['area_id'] ?? 0);
        $capacity  = (int) ($_POST['capacity'] ?? 4);
        $shape     = trim($_POST['shape'] ?? 'square');
        $status    = trim($_POST['status'] ?? 'active');
        $sortOrder = (int) ($_POST['sort_order'] ?? 0);

        $res = createDiningTable([
            'code'       => $code,
            'name'       => $name,
            'area_id'    => $areaId,
            'capacity'   => $capacity,
            'shape'      => $shape,
            'status'     => $status,
            'sort_order' => $sortOrder
        ]);

        if ($res['success']) {
            $_SESSION['flash_success'] = $res['message'];
        } else {
            $_SESSION['flash_error'] = $res['message'];
        }
        header('Location: index.php?page=tables&tab=tables');
        exit;
    }

    // 2. CHỈNH SỬA THÔNG TIN BÀN ĂN
    if ($action === 'edit_table') {
        $id        = (int) ($_POST['id'] ?? 0);
        $code      = trim($_POST['code'] ?? '');
        $name      = trim($_POST['name'] ?? '');
        $areaId    = (int) ($_POST['area_id'] ?? 0);
        $capacity  = (int) ($_POST['capacity'] ?? 4);
        $shape     = trim($_POST['shape'] ?? 'square');
        $status    = trim($_POST['status'] ?? 'active');
        $sortOrder = (int) ($_POST['sort_order'] ?? 0);

        $res = updateDiningTable($id, [
            'code'       => $code,
            'name'       => $name,
            'area_id'    => $areaId,
            'capacity'   => $capacity,
            'shape'      => $shape,
            'status'     => $status,
            'sort_order' => $sortOrder
        ]);

        if ($res['success']) {
            $_SESSION['flash_success'] = $res['message'];
        } else {
            $_SESSION['flash_error'] = $res['message'];
        }
        header('Location: index.php?page=tables&tab=tables');
        exit;
    }

    // 3. XÓA BÀN ĂN
    if ($action === 'delete_table') {
        $id = (int) ($_POST['id'] ?? 0);
        $res = deleteDiningTable($id);

        if ($res['success']) {
            $_SESSION['flash_success'] = $res['message'];
        } else {
            $_SESSION['flash_error'] = $res['message'];
        }
        header('Location: index.php?page=tables&tab=tables');
        exit;
    }

    // 4. CHUYỂN NHANH TRẠNG THÁI BÀN
    if ($action === 'toggle_table_status') {
        $id     = (int) ($_POST['id'] ?? 0);
        $status = trim($_POST['status'] ?? 'active');

        $res = updateDiningTableStatus($id, $status);
        if ($res['success']) {
            $_SESSION['flash_success'] = $res['message'];
        } else {
            $_SESSION['flash_error'] = $res['message'];
        }
        header('Location: index.php?page=tables&tab=tables');
        exit;
    }

    // 5. THÊM KHU VỰC MỚI
    if ($action === 'add_area') {
        $code        = trim($_POST['code'] ?? '');
        $name        = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $sortOrder   = (int) ($_POST['sort_order'] ?? 0);
        $status      = trim($_POST['status'] ?? 'active');

        $res = createArea([
            'code'        => $code,
            'name'        => $name,
            'description' => $description,
            'sort_order'  => $sortOrder,
            'status'      => $status
        ]);

        if ($res['success']) {
            $_SESSION['flash_success'] = $res['message'];
        } else {
            $_SESSION['flash_error'] = $res['message'];
        }
        header('Location: index.php?page=tables&tab=areas');
        exit;
    }

    // 6. CHỈNH SỬA KHU VỰC
    if ($action === 'edit_area') {
        $id          = (int) ($_POST['id'] ?? 0);
        $code        = trim($_POST['code'] ?? '');
        $name        = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $sortOrder   = (int) ($_POST['sort_order'] ?? 0);
        $status      = trim($_POST['status'] ?? 'active');

        $res = updateArea($id, [
            'code'        => $code,
            'name'        => $name,
            'description' => $description,
            'sort_order'  => $sortOrder,
            'status'      => $status
        ]);

        if ($res['success']) {
            $_SESSION['flash_success'] = $res['message'];
        } else {
            $_SESSION['flash_error'] = $res['message'];
        }
        header('Location: index.php?page=tables&tab=areas');
        exit;
    }

    // 7. XÓA KHU VỰC
    if ($action === 'delete_area') {
        $id = (int) ($_POST['id'] ?? 0);
        $res = deleteArea($id);

        if ($res['success']) {
            $_SESSION['flash_success'] = $res['message'];
        } else {
            $_SESSION['flash_error'] = $res['message'];
        }
        header('Location: index.php?page=tables&tab=areas');
        exit;
    }
}

// ==========================================================
// DỮ LIỆU HIỂN THỊ TRANG
// ==========================================================
$currentTab   = $_GET['tab'] ?? 'tables';
$filterArea   = trim($_GET['area'] ?? '');
$filterStatus = trim($_GET['status'] ?? '');
$search       = trim($_GET['search'] ?? '');

$allAreas   = getAllAreas();
$allTables  = getAllDiningTables($filterArea, $filterStatus, $search);
$tableStats = getTableStats();
?>

<!-- TIÊU ĐỀ TRANG VÀ NÚT TÁC VỤ NHANH -->
<div class="dash-header">
  <div>
    <span class="eyebrow">SMARTDINE MANAGEMENT • SƠ ĐỒ & BÀN ĂN</span>
    <h1>Quản lý Bàn & Khu vực Nhà hàng</h1>
    <p>Tổ chức không gian nhà hàng, quản lý danh sách bàn ăn, sức chứa và phân bổ khu vực phục vụ.</p>
  </div>
  <div class="filter-row">
    <a class="btn btn-outline" href="index.php?page=staff" title="Xem sơ đồ bàn thực tế">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"></rect><line x1="3" y1="9" x2="21" y2="9"></line><line x1="9" y1="21" x2="9" y2="9"></line></svg>
      <span>Xem sơ đồ bàn phục vụ</span>
    </a>
    <button type="button" class="btn btn-outline" id="btn-open-add-area">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"></path></svg>
      <span>+ Thêm khu vực</span>
    </button>
    <button type="button" class="btn btn-primary-tool" id="btn-open-add-table">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
      <span>+ Thêm bàn mới</span>
    </button>
  </div>
</div>

<!-- THỐNG KÊ TỔNG QUAN -->
<div class="stat-grid admin-stats">
  <div class="stat-card">
    <div class="stat-card-head">
      <span>Tổng số bàn ăn</span>
      <span class="stat-badge neutral"><?= $tableStats['total_tables'] ?> bàn</span>
    </div>
    <b><?= $tableStats['total_tables'] ?> bàn</b>
    <small>Phân bổ trên <?= $tableStats['total_areas'] ?> khu vực không gian</small>
  </div>

  <div class="stat-card">
    <div class="stat-card-head">
      <span>Sẵn sàng phục vụ</span>
      <span class="stat-badge positive"><?= $tableStats['active_tables'] ?> bàn</span>
    </div>
    <b><?= $tableStats['active_tables'] ?> bàn hoạt động</b>
    <small class="positive">✓ <?= $tableStats['empty_tables'] ?> bàn trống đang chờ đón khách</small>
  </div>

  <div class="stat-card">
    <div class="stat-card-head">
      <span>Đang có khách</span>
      <span class="stat-badge <?= $tableStats['occupancy_rate'] > 70 ? 'urgent' : 'positive' ?>"><?= $tableStats['occupancy_rate'] ?>% công suất</span>
    </div>
    <b><?= $tableStats['occupied_tables'] ?> bàn có khách</b>
    <small class="positive">↗ Đang dùng bữa hoặc chờ ra món</small>
  </div>

  <div class="stat-card">
    <div class="stat-card-head">
      <span>Tổng sức chứa</span>
      <span class="stat-badge neutral"><?= $tableStats['total_capacity'] ?> khách</span>
    </div>
    <b><?= $tableStats['total_capacity'] ?> chỗ ngồi</b>
    <small>Công suất phục vụ tối đa cùng lúc</small>
  </div>
</div>

<!-- THÔNG BÁO FLASH (NẾU CÓ) -->
<?php if (!empty($_SESSION['flash_success'])): ?>
  <div class="flash-alert flash-success" style="margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between; padding: 12px 18px; border-radius: var(--radius-md); background: #eaf8f0; color: #116838; border: 1px solid #a4e1bc;">
    <div style="display: flex; align-items: center; gap: 10px;">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
      <span><?= htmlspecialchars($_SESSION['flash_success']) ?></span>
    </div>
    <button type="button" onclick="this.parentElement.remove()" style="background: none; border: none; font-size: 16px; cursor: pointer; color: inherit;">✕</button>
  </div>
  <?php unset($_SESSION['flash_success']); ?>
<?php endif; ?>

<?php if (!empty($_SESSION['flash_error'])): ?>
  <div class="flash-alert flash-error" style="margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between; padding: 12px 18px; border-radius: var(--radius-md); background: #feeff0; color: #a5181c; border: 1px solid #f8afb1;">
    <div style="display: flex; align-items: center; gap: 10px;">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
      <span><?= htmlspecialchars($_SESSION['flash_error']) ?></span>
    </div>
    <button type="button" onclick="this.parentElement.remove()" style="background: none; border: none; font-size: 16px; cursor: pointer; color: inherit;">✕</button>
  </div>
  <?php unset($_SESSION['flash_error']); ?>
<?php endif; ?>

<!-- THANH TAB ĐIỀU HƯỚNG QUẢN LÝ -->
<div class="dishes-nav-tabs" style="margin-bottom: 20px; display: flex; gap: 12px; border-bottom: 1px solid var(--line); padding-bottom: 14px; flex-wrap: wrap;">
  <a href="index.php?page=tables&tab=tables" class="tab-item <?= $currentTab === 'tables' ? 'active' : '' ?>">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"></rect><path d="M3 9h18M9 21V9"></path></svg>
    <span>Danh sách Bàn ăn</span>
    <span class="badge-pill" style="font-size: 11px;"><?= $tableStats['total_tables'] ?></span>
  </a>
  <a href="index.php?page=tables&tab=areas" class="tab-item <?= $currentTab === 'areas' ? 'active' : '' ?>">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 2 7 12 12 22 7 12 2"></polygon><polyline points="2 17 12 22 22 17"></polyline><polyline points="2 12 12 17 22 12"></polyline></svg>
    <span>Danh mục Khu vực</span>
    <span class="badge-pill" style="font-size: 11px;"><?= count($allAreas) ?></span>
  </a>
  <a href="index.php?page=tables&tab=layout" class="tab-item <?= $currentTab === 'layout' ? 'active' : '' ?>">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"></path><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path></svg>
    <span>Thiết kế Sơ đồ Bàn (2D Layout)</span>
    <span class="badge-pill" style="font-size: 11px; background: #fee2e2; color: #dc2626;">Kéo thả</span>
  </a>
</div>

<?php if ($currentTab === 'tables'): ?>
  <!-- ==========================================================
       TAB 1: QUẢN LÝ BÀN ĂN
       ========================================================== -->
  <section class="panel">
    <div class="panel-title" style="flex-wrap: wrap; gap: 14px;">
      <div>
        <h2>Danh sách Bàn ăn Nhà hàng</h2>
        <p class="muted">Hiển thị toàn bộ bàn ăn, sức chứa, khu vực phân bổ và trạng thái vận hành hiện tại</p>
      </div>

      <!-- BỘ LỌC VÀ TÌM KIẾM -->
      <form method="GET" action="index.php" class="filter-row" style="flex-wrap: wrap;">
        <input type="hidden" name="page" value="tables">
        <input type="hidden" name="tab" value="tables">

        <div class="search-box">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
          <input type="text" name="search" placeholder="Tìm theo mã bàn, tên bàn..." value="<?= htmlspecialchars($search) ?>">
        </div>

        <label class="select-wrapper">
          <select name="area" onchange="this.form.submit()">
            <option value="">Tất cả khu vực</option>
            <?php foreach ($allAreas as $a): ?>
              <option value="<?= htmlspecialchars($a['code']) ?>" <?= $filterArea === $a['code'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($a['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </label>

        <label class="select-wrapper">
          <select name="status" onchange="this.form.submit()">
            <option value="">Tất cả trạng thái</option>
            <option value="active" <?= $filterStatus === 'active' ? 'selected' : '' ?>>Đang hoạt động</option>
            <option value="maintenance" <?= $filterStatus === 'maintenance' ? 'selected' : '' ?>>Đang bảo trì</option>
            <option value="hidden" <?= $filterStatus === 'hidden' ? 'selected' : '' ?>>Đang ẩn</option>
          </select>
        </label>

        <button type="submit" class="btn btn-outline btn-small">Lọc</button>
        <?php if (!empty($search) || !empty($filterArea) || !empty($filterStatus)): ?>
          <a href="index.php?page=tables&tab=tables" class="btn btn-outline btn-small" title="Xóa tất cả bộ lọc">✕ Xóa lọc</a>
        <?php endif; ?>
      </form>
    </div>

    <!-- BẢNG DỮ LIỆU BÀN ĂN -->
    <div class="table-wrap" style="overflow-x: auto;">
      <table class="data-table" style="width: 100%; border-collapse: collapse;">
        <thead>
          <tr style="border-bottom: 2px solid var(--line); text-align: left;">
            <th style="padding: 12px 14px; width: 90px;">Mã bàn</th>
            <th style="padding: 12px 14px;">Tên bàn</th>
            <th style="padding: 12px 14px;">Khu vực</th>
            <th style="padding: 12px 14px; width: 100px;">Hình dạng</th>
            <th style="padding: 12px 14px; width: 100px;">Sức chứa</th>
            <th style="padding: 12px 14px; width: 150px;">Tình trạng khách</th>
            <th style="padding: 12px 14px; width: 120px;">Trạng thái</th>
            <th style="padding: 12px 14px; text-align: right; width: 170px;">Thao tác</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($allTables)): ?>
            <tr>
              <td colspan="8" style="padding: 30px; text-align: center;" class="muted">
                Không tìm thấy bàn ăn nào phù hợp với bộ lọc tìm kiếm.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($allTables as $t): ?>
              <tr style="border-bottom: 1px solid var(--line);">
                <!-- Cột: Mã bàn -->
                <td style="padding: 12px 14px;">
                  <span style="display: inline-block; font-family: monospace; font-size: 15px; font-weight: 800; padding: 4px 10px; border-radius: var(--radius-sm); background: #f1f5f9; color: var(--navy); border: 1px solid #cbd5e1;">
                    <?= htmlspecialchars($t['code']) ?>
                  </span>
                </td>

                <!-- Cột: Tên bàn -->
                <td style="padding: 12px 14px;">
                  <b><?= htmlspecialchars($t['name']) ?></b>
                  <div class="muted" style="font-size: 12px;">Thứ tự: #<?= (int)$t['sort_order'] ?></div>
                </td>

                <!-- Cột: Khu vực -->
                <td style="padding: 12px 14px;">
                  <span class="badge-pill" style="font-size: 12px;">
                    <?= htmlspecialchars($t['area_name']) ?>
                  </span>
                </td>

                <!-- Cột: Hình dạng -->
                <td style="padding: 12px 14px;">
                  <?php if (($t['shape'] ?? 'square') === 'round'): ?>
                    <span class="badge-pill" style="font-size: 11px; background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0;">● Tròn</span>
                  <?php elseif (($t['shape'] ?? 'square') === 'rectangle'): ?>
                    <span class="badge-pill" style="font-size: 11px; background: #f0f9ff; color: #0369a1; border: 1px solid #bae6fd;">▬ Chữ nhật</span>
                  <?php else: ?>
                    <span class="badge-pill" style="font-size: 11px; background: #f8fafc; color: #475569; border: 1px solid #e2e8f0;">■ Vuông</span>
                  <?php endif; ?>
                </td>

                <!-- Cột: Sức chứa -->
                <td style="padding: 12px 14px;">
                  <span style="display: inline-flex; align-items: center; gap: 6px; font-weight: 600; color: #475569;">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                    <?= (int)$t['capacity'] ?> chỗ
                  </span>
                </td>

                <!-- Cột: Tình trạng khách -->
                <td style="padding: 12px 14px;">
                  <?php if ($t['status'] === 'maintenance'): ?>
                    <span class="status-badge" style="background: #f1f5f9; color: #64748b; border: 1px solid #cbd5e1;">
                      <span class="status-indicator" style="background: #94a3b8;"></span> Bảo trì
                    </span>
                  <?php elseif ($t['is_occupied']): ?>
                    <a href="index.php?page=table-order&table=<?= urlencode($t['code']) ?>" title="Xem đơn hàng bàn này" style="text-decoration: none;">
                      <span class="status-badge status-urgent" style="background: #feeff0; color: #a5181c; border: 1px solid #f8afb1;">
                        <span class="status-indicator" style="background: #ef4444;"></span> Đang có khách
                      </span>
                      <div style="font-size: 11px; font-weight: 700; color: #b91c1c; margin-top: 3px;">
                        <?= (int)$t['occupied_items'] ?> món · <?= number_format($t['occupied_total'], 0, ',', '.') ?>đ
                      </div>
                    </a>
                  <?php else: ?>
                    <span class="status-badge status-active" style="background: #eaf8f0; color: #116838; border: 1px solid #a4e1bc;">
                      <span class="status-indicator" style="background: #10b981;"></span> Bàn trống
                    </span>
                  <?php endif; ?>
                </td>

                <!-- Cột: Trạng thái cấu hình -->
                <td style="padding: 12px 14px;">
                  <?php if ($t['status'] === 'active'): ?>
                    <span class="status status-ready">Hoạt động</span>
                  <?php elseif ($t['status'] === 'maintenance'): ?>
                    <span class="status status-pending">Bảo trì</span>
                  <?php else: ?>
                    <span class="status status-muted">Tạm ẩn</span>
                  <?php endif; ?>
                </td>

                <!-- Cột: Thao tác -->
                <td style="padding: 12px 14px; text-align: right;">
                  <div style="display: flex; gap: 6px; justify-content: flex-end; align-items: center;">
                    <!-- Đổi nhanh trạng thái -->
                    <form method="POST" action="index.php?page=tables" style="display: inline;">
                      <input type="hidden" name="action" value="toggle_table_status">
                      <input type="hidden" name="id" value="<?= $t['id'] ?>">
                      <?php if ($t['status'] === 'active'): ?>
                        <input type="hidden" name="status" value="maintenance">
                        <button type="submit" class="btn btn-small btn-outline" title="Chuyển sang bảo trì">Bảo trì</button>
                      <?php else: ?>
                        <input type="hidden" name="status" value="active">
                        <button type="submit" class="btn btn-small btn-outline" style="color: var(--green);" title="Kích hoạt mở phục vụ">Mở bán</button>
                      <?php endif; ?>
                    </form>

                    <!-- Nút Sửa -->
                    <button type="button" class="btn btn-small btn-outline btn-edit-table"
                            data-id="<?= $t['id'] ?>"
                            data-code="<?= htmlspecialchars($t['code']) ?>"
                            data-name="<?= htmlspecialchars($t['name']) ?>"
                            data-area-id="<?= $t['area_id'] ?>"
                            data-capacity="<?= $t['capacity'] ?>"
                            data-shape="<?= htmlspecialchars($t['shape'] ?? 'square') ?>"
                            data-status="<?= $t['status'] ?>"
                            data-sort-order="<?= $t['sort_order'] ?>"
                            title="Chỉnh sửa thông tin bàn">
                      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 20h9"></path><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path></svg>
                    </button>

                    <!-- Nút Xóa -->
                    <button type="button" class="btn btn-small btn-outline btn-delete-table"
                            data-id="<?= $t['id'] ?>"
                            data-code="<?= htmlspecialchars($t['code']) ?>"
                            data-is-occupied="<?= $t['is_occupied'] ? '1' : '0' ?>"
                            style="color: var(--red); border-color: #fca5a5;"
                            title="Xóa bàn này">
                      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                    </button>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>

<?php elseif ($currentTab === 'areas'): ?>
  <!-- ==========================================================
       TAB 2: QUẢN LÝ KHU VỰC
       ========================================================== -->
  <section class="panel">
    <div class="panel-title" style="flex-wrap: wrap; gap: 14px;">
      <div>
        <h2>Danh mục Khu vực Không gian Nhà hàng</h2>
        <p class="muted">Thiết lập các phân khu nhà hàng (Trong nhà, Sân vườn, Phòng VIP, Sân thượng...)</p>
      </div>
      <div>
        <button type="button" class="btn btn-primary btn-small" id="btn-open-add-area-sub">
          + Thêm khu vực mới
        </button>
      </div>
    </div>

    <!-- BẢNG DỮ LIỆU KHU VỰC -->
    <div class="table-wrap" style="overflow-x: auto;">
      <table class="data-table" style="width: 100%; border-collapse: collapse;">
        <thead>
          <tr style="border-bottom: 2px solid var(--line); text-align: left;">
            <th style="padding: 12px 14px; width: 80px;">Thứ tự</th>
            <th style="padding: 12px 14px; width: 140px;">Mã định danh</th>
            <th style="padding: 12px 14px;">Tên khu vực</th>
            <th style="padding: 12px 14px;">Mô tả không gian</th>
            <th style="padding: 12px 14px; width: 160px;">Quy mô bàn</th>
            <th style="padding: 12px 14px; width: 130px;">Trạng thái</th>
            <th style="padding: 12px 14px; text-align: right; width: 140px;">Thao tác</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($allAreas)): ?>
            <tr>
              <td colspan="7" style="padding: 30px; text-align: center;" class="muted">
                Chưa có khu vực nào được cấu hình trong hệ thống.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($allAreas as $a): ?>
              <tr style="border-bottom: 1px solid var(--line);">
                <!-- Cột: Thứ tự -->
                <td style="padding: 12px 14px; font-weight: 700; color: #64748b;">
                  #<?= (int)$a['sort_order'] ?>
                </td>

                <!-- Cột: Mã code -->
                <td style="padding: 12px 14px;">
                  <span style="display: inline-block; font-family: monospace; font-size: 13px; font-weight: 700; padding: 4px 8px; border-radius: var(--radius-sm); background: #f8fafc; border: 1px solid #e2e8f0;">
                    <?= htmlspecialchars($a['code']) ?>
                  </span>
                </td>

                <!-- Cột: Tên khu vực -->
                <td style="padding: 12px 14px;">
                  <b><?= htmlspecialchars($a['name']) ?></b>
                </td>

                <!-- Cột: Mô tả -->
                <td style="padding: 12px 14px; color: #475569; font-size: 13px;">
                  <?= htmlspecialchars($a['description'] ?: '—') ?>
                </td>

                <!-- Cột: Số lượng bàn -->
                <td style="padding: 12px 14px;">
                  <b><?= (int)$a['total_tables'] ?> bàn</b>
                  <div class="muted" style="font-size: 12px;">
                    <?= (int)$a['active_tables'] ?> đang mở · <?= (int)$a['total_capacity'] ?> chỗ
                  </div>
                </td>

                <!-- Cột: Trạng thái -->
                <td style="padding: 12px 14px;">
                  <?php if ($a['status'] === 'active'): ?>
                    <span class="status status-ready">Hoạt động</span>
                  <?php else: ?>
                    <span class="status status-muted">Tạm ngưng</span>
                  <?php endif; ?>
                </td>

                <!-- Cột: Thao tác -->
                <td style="padding: 12px 14px; text-align: right;">
                  <div style="display: flex; gap: 6px; justify-content: flex-end;">
                    <button type="button" class="btn btn-small btn-outline btn-edit-area"
                            data-id="<?= $a['id'] ?>"
                            data-code="<?= htmlspecialchars($a['code']) ?>"
                            data-name="<?= htmlspecialchars($a['name']) ?>"
                            data-description="<?= htmlspecialchars($a['description'] ?? '') ?>"
                            data-sort-order="<?= $a['sort_order'] ?>"
                            data-status="<?= $a['status'] ?>"
                            title="Sửa khu vực">
                      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 20h9"></path><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path></svg>
                    </button>

                    <button type="button" class="btn btn-small btn-outline btn-delete-area"
                            data-id="<?= $a['id'] ?>"
                            data-name="<?= htmlspecialchars($a['name']) ?>"
                            data-tables-count="<?= (int)$a['total_tables'] ?>"
                            style="color: var(--red); border-color: #fca5a5;"
                            title="Xóa khu vực">
                      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                    </button>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>

<?php elseif ($currentTab === 'layout'): ?>
  <link rel="stylesheet" href="<?= assetUrl('assets/css/visual-floor.css') ?>">
  <script>
    window.SMARTDINE_AREAS = <?= json_encode($allAreas, JSON_UNESCAPED_UNICODE) ?>;
  </script>
  <!-- ==========================================================
       TAB 3: THIẾT KẾ SƠ ĐỒ BÀN 2D (VISUAL 2D LAYOUT EDITOR)
       ========================================================== -->
  <section class="panel">
    <div class="panel-title" style="flex-wrap: wrap; gap: 14px;">
      <div>
        <h2>Thiết kế Sơ đồ Bàn 2D (Visual Floor Plan Editor)</h2>
        <p class="muted">Kéo thả bàn & cửa ra vào vào vị trí thực tế trong nhà hàng, đổi hình dạng và lưu sơ đồ kiến trúc.</p>
      </div>

      <div style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
        <!-- Chọn phân khu để sắp xếp -->
        <div style="display: flex; align-items: center; gap: 8px;">
          <label for="layout-zone-select" style="font-weight: 700; font-size: 13px; color: #475569;">Khu vực:</label>
          <select id="layout-zone-select" style="padding: 7px 12px; border-radius: var(--radius-sm); border: 1px solid var(--line); font-weight: 600; font-size: 13px; outline: none; background: #fff;">
            <?php foreach ($allAreas as $idx => $a): ?>
              <option value="<?= htmlspecialchars($a['code']) ?>" <?= $idx === 0 ? 'selected' : '' ?>>
                <?= htmlspecialchars($a['name']) ?> (<?= htmlspecialchars($a['code']) ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- Chọn góc Cửa ra vào -->
        <div style="display: flex; align-items: center; gap: 8px;">
          <label for="entrance-pos-select" style="font-weight: 700; font-size: 13px; color: #475569;">🚪 Cửa ra vào:</label>
          <select id="entrance-pos-select" style="padding: 7px 12px; border-radius: var(--radius-sm); border: 1px solid var(--line); font-weight: 600; font-size: 13px; outline: none; background: #fff;">
            <option value="bottom_center">Dưới - Giữa (Mặc định)</option>
            <option value="bottom_left">Góc Dưới - Trái</option>
            <option value="bottom_right">Góc Dưới - Phải</option>
            <option value="top_center">Trên - Giữa</option>
            <option value="top_left">Góc Trên - Trái</option>
            <option value="top_right">Góc Trên - Phải</option>
            <option value="left_center">Cạnh Trái (Giữa)</option>
            <option value="right_center">Cạnh Phải (Giữa)</option>
            <option value="custom">Tùy chỉnh (Kéo thả)</option>
          </select>
        </div>

        <!-- Nút Lưu vị trí -->
        <button type="button" class="btn btn-primary" id="btn-save-layout" style="display: flex; align-items: center; gap: 6px;">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>
          <span>Lưu vị trí sơ đồ</span>
        </button>
      </div>
    </div>

    <!-- Hướng dẫn thao tác kéo thả -->
    <div style="margin-top: 6px; margin-bottom: 16px; padding: 10px 14px; background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: var(--radius-md); font-size: 13px; color: #166534; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px;">
      <div style="display: flex; align-items: center; gap: 8px;">
        <span style="font-size: 16px;">💡</span>
        <span><strong>Mẹo thao tác:</strong> Dùng chuột kéo bàn, <strong>Quầy thu ngân (🏪)</strong> và <strong>Cửa ra vào (🚪)</strong> đến bất kỳ vị trí/góc nào. Bấm nút <strong>[-] / [+]</strong> hoặc <strong>kéo góc ⤡</strong> để chỉnh to/nhỏ kích thước bàn. Bấm <strong>[Lưu vị trí sơ đồ]</strong> để hoàn tất.</span>
      </div>
      <div id="layout-save-indicator" style="font-weight: 700; font-size: 12px; display: none; padding: 4px 10px; border-radius: 6px;"></div>
    </div>

    <!-- Khung Canvas Mặt bằng 2D -->
    <div class="visual-floor-container" id="admin-layout-canvas" style="position: relative; width: 100%; min-height: 600px; background-color: #f8fafc; background-image: radial-gradient(#cbd5e1 1.2px, transparent 1.2px); background-size: 24px 24px; border: 2px dashed #cbd5e1; border-radius: var(--radius-lg); overflow: hidden; margin-top: 14px; user-select: none;">
      <!-- Các mốc kiến trúc không gian mặt bằng -->
      <!-- QUẦY THU NGÂN / PHA CHẾ (Kéo thả tự do) -->
      <div class="arch-landmark landmark-cashier draggable-cashier" id="admin-cashier-counter"
           data-pos-x="14.0"
           data-pos-y="6.0"
           style="position: absolute; transform: translate(-50%, -50%); left: 14%; top: 6%; cursor: grab; z-index: 4;"
           title="Kéo thả quầy thu ngân đến bất kỳ vị trí nào">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="4" width="20" height="16" rx="2"></rect><line x1="2" y1="10" x2="22" y2="10"></line></svg>
        <span>🏪 QUẦY THU NGÂN / PHA CHẾ</span>
      </div>
      <div class="arch-landmark landmark-window" style="position: absolute; top: 0; left: 30%; right: 30%; height: 4px; background: #38bdf8; opacity: 0.6; border-radius: 2px; z-index: 1;" title="Vách kính nhìn ra cảnh quan"></div>
      
      <!-- CỬA RA VÀO (Cho phép chuyển mọi góc hoặc kéo thả tự do) -->
      <div class="landmark-entrance draggable-entrance" id="admin-entrance-door"
           data-pos="bottom_center"
           data-pos-x="50.0"
           data-pos-y="96.0"
           style="position: absolute; transform: translate(-50%, -50%); left: 50%; top: 96%; cursor: grab; z-index: 5;"
           title="Kéo thả để dời cửa ra vào đến bất kỳ góc nào">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M3 21h18M6 21V5a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v16M14 11h1"></path></svg>
        <span id="admin-entrance-label">▲ LỐI VÀO CHÍNH (DƯỚI) ▲</span>
      </div>

      <!-- Danh sách bàn ăn kéo thả -->
      <?php
      $allLayoutTables = getAllDiningTables('', 'not_hidden');
      foreach ($allLayoutTables as $t):
        $shape  = in_array($t['shape'] ?? '', ['square', 'round', 'rectangle'], true) ? $t['shape'] : 'square';
        $w      = (int)($t['width'] ?? ($shape === 'rectangle' ? 120 : 85));
        $h      = (int)($t['height'] ?? 85);
        $posX   = (float)($t['pos_x'] ?? 20);
        $posY   = (float)($t['pos_y'] ?? 25);
        $radius = ($shape === 'round') ? '50%' : (($shape === 'rectangle') ? '16px' : '14px');
      ?>
        <div class="visual-table-node draggable-table-item shape-<?= $shape ?>"
             id="layout-table-<?= $t['id'] ?>"
             data-id="<?= $t['id'] ?>"
             data-code="<?= htmlspecialchars($t['code']) ?>"
             data-zone="<?= htmlspecialchars($t['area_code']) ?>"
             data-shape="<?= $shape ?>"
             data-width="<?= $w ?>"
             data-height="<?= $h ?>"
             data-pos-x="<?= $posX ?>"
             data-pos-y="<?= $posY ?>"
             data-capacity="<?= $t['capacity'] ?>"
             style="position: absolute; transform: translate(-50%, -50%); left: <?= $posX ?>%; top: <?= $posY ?>%; width: <?= $w ?>px; height: <?= $h ?>px; display: flex; flex-direction: column; align-items: center; justify-content: center; background: #ffffff; border: 2px dashed #475569; border-radius: <?= $radius ?>; color: #1e293b; box-shadow: 0 4px 12px rgba(0,0,0,0.08); touch-action: none; cursor: grab; z-index: 2; box-sizing: border-box;"
             title="Kéo để di chuyển bàn <?= htmlspecialchars($t['code']) ?>">
          
          <div style="font-weight: 800; font-size: 15px; pointer-events: none; line-height: 1.1;">
            <?= htmlspecialchars($t['code']) ?>
          </div>
          <div class="table-dim-label" style="font-size: 10px; color: #64748b; font-weight: 600; pointer-events: none; margin-top: 1px;">
            <?= $w ?>x<?= $h ?> · <?= $t['capacity'] ?>c
          </div>

          <!-- Thanh nút điều khiển kích thước & hình dạng -->
          <div class="table-ctrl-bar">
            <button type="button" class="btn-table-size btn-size-down" data-id="<?= $t['id'] ?>" title="Thu nhỏ kích thước bàn">-</button>
            <button type="button" class="btn-table-size btn-size-up" data-id="<?= $t['id'] ?>" title="Phóng to kích thước bàn">+</button>
            <button type="button" class="btn-toggle-shape" data-id="<?= $t['id'] ?>" title="Đổi hình (Vuông / Tròn / Chữ nhật)">Đổi</button>
          </div>

          <!-- Góc kéo co giãn kích thước bàn trực tiếp -->
          <div class="table-resize-handle" data-id="<?= $t['id'] ?>" title="Kéo góc để chỉnh to/nhỏ bàn">
            <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="21" y1="9" x2="9" y2="21"></line><line x1="21" y1="15" x2="15" y2="21"></line><line x1="21" y1="21" x2="21.01" y2="21"></line></svg>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </section>
<?php endif; ?>

<!-- ==========================================================
     MODAL 1: THÊM BÀN ĂN MỚI
     ========================================================== -->
<div class="custom-modal-backdrop" id="modal-add-table" style="display: none;">
  <div class="custom-modal-dialog">
    <div class="custom-modal-header">
      <div class="modal-title-wrap">
        <div class="modal-icon-badge" style="background: var(--brand-orange-light); color: var(--brand-orange);">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="3" y="3" width="18" height="18" rx="2"></rect><path d="M3 9h18M9 21V9"></path></svg>
        </div>
        <div>
          <h3>Thêm bàn ăn mới</h3>
          <p class="muted">Khai báo thông tin bàn ăn, mã định danh và số chỗ ngồi</p>
        </div>
      </div>
      <button type="button" class="btn-close-modal" data-close="modal-add-table">✕</button>
    </div>

    <form method="POST" action="index.php?page=tables" class="modal-form">
      <input type="hidden" name="action" value="add_table">

      <div class="form-grid-2" style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
        <div class="form-group">
          <label>Mã bàn <span class="required">*</span></label>
          <input type="text" name="code" placeholder="VD: B13, V01, S05" required style="font-family: monospace; font-weight: 700; text-transform: uppercase;">
        </div>
        <div class="form-group">
          <label>Tên bàn hiển thị</label>
          <input type="text" name="name" placeholder="Mặc định: Bàn + Mã">
        </div>
      </div>

      <div class="form-grid-2" style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
        <div class="form-group">
          <label>Khu vực phân bổ <span class="required">*</span></label>
          <select name="area_id" required>
            <?php foreach ($allAreas as $a): ?>
              <option value="<?= $a['id'] ?>"><?= htmlspecialchars($a['name']) ?> (<?= htmlspecialchars($a['code']) ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Sức chứa (Số khách) <span class="required">*</span></label>
          <input type="number" name="capacity" value="4" min="1" max="100" required>
        </div>
      </div>

      <div class="form-grid-3" style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 14px;">
        <div class="form-group">
          <label>Hình dạng trên sơ đồ</label>
          <select name="shape">
            <option value="square" selected>■ Vuông (Mặc định)</option>
            <option value="round">● Tròn</option>
            <option value="rectangle">▬ Chữ nhật</option>
          </select>
        </div>
        <div class="form-group">
          <label>Trạng thái hoạt động</label>
          <select name="status">
            <option value="active">Sẵn sàng phục vụ</option>
            <option value="maintenance">Đang bảo trì / Sửa chữa</option>
            <option value="hidden">Tạm ẩn khỏi sơ đồ</option>
          </select>
        </div>
        <div class="form-group">
          <label>Thứ tự sắp xếp</label>
          <input type="number" name="sort_order" value="0" min="0">
        </div>
      </div>

      <div class="modal-actions-footer">
        <button type="button" class="btn btn-outline" data-close="modal-add-table">Hủy bỏ</button>
        <button type="submit" class="btn btn-primary">Xác nhận thêm bàn</button>
      </div>
    </form>
  </div>
</div>

<!-- ==========================================================
     MODAL 2: CHỈNH SỬA BÀN ĂN
     ========================================================== -->
<div class="custom-modal-backdrop" id="modal-edit-table" style="display: none;">
  <div class="custom-modal-dialog">
    <div class="custom-modal-header">
      <div class="modal-title-wrap">
        <div class="modal-icon-badge" style="background: #e0f2fe; color: #0284c7;">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M12 20h9"></path><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path></svg>
        </div>
        <div>
          <h3>Chỉnh sửa bàn ăn</h3>
          <p class="muted">Cập nhật mã bàn, số chỗ ngồi hoặc chuyển khu vực</p>
        </div>
      </div>
      <button type="button" class="btn-close-modal" data-close="modal-edit-table">✕</button>
    </div>

    <form method="POST" action="index.php?page=tables" class="modal-form">
      <input type="hidden" name="action" value="edit_table">
      <input type="hidden" name="id" id="edit-table-id">

      <div class="form-grid-2" style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
        <div class="form-group">
          <label>Mã bàn <span class="required">*</span></label>
          <input type="text" name="code" id="edit-table-code" required style="font-family: monospace; font-weight: 700; text-transform: uppercase;">
        </div>
        <div class="form-group">
          <label>Tên bàn hiển thị</label>
          <input type="text" name="name" id="edit-table-name">
        </div>
      </div>

      <div class="form-grid-2" style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
        <div class="form-group">
          <label>Khu vực phân bổ <span class="required">*</span></label>
          <select name="area_id" id="edit-table-area-id" required>
            <?php foreach ($allAreas as $a): ?>
              <option value="<?= $a['id'] ?>"><?= htmlspecialchars($a['name']) ?> (<?= htmlspecialchars($a['code']) ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Sức chứa (Số khách) <span class="required">*</span></label>
          <input type="number" name="capacity" id="edit-table-capacity" min="1" max="100" required>
        </div>
      </div>

      <div class="form-grid-3" style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 14px;">
        <div class="form-group">
          <label>Hình dạng trên sơ đồ</label>
          <select name="shape" id="edit-table-shape">
            <option value="square">■ Vuông</option>
            <option value="round">● Tròn</option>
            <option value="rectangle">▬ Chữ nhật</option>
          </select>
        </div>
        <div class="form-group">
          <label>Trạng thái hoạt động</label>
          <select name="status" id="edit-table-status">
            <option value="active">Sẵn sàng phục vụ</option>
            <option value="maintenance">Đang bảo trì / Sửa chữa</option>
            <option value="hidden">Tạm ẩn khỏi sơ đồ</option>
          </select>
        </div>
        <div class="form-group">
          <label>Thứ tự sắp xếp</label>
          <input type="number" name="sort_order" id="edit-table-sort-order" min="0">
        </div>
      </div>

      <div class="modal-actions-footer">
        <button type="button" class="btn btn-outline" data-close="modal-edit-table">Hủy bỏ</button>
        <button type="submit" class="btn btn-primary">Lưu thay đổi</button>
      </div>
    </form>
  </div>
</div>

<!-- ==========================================================
     MODAL 3: XÁC NHẬN XÓA BÀN ĂN
     ========================================================== -->
<div class="custom-modal-backdrop" id="modal-delete-table" style="display: none;">
  <div class="custom-modal-dialog custom-modal-sm" style="max-width: 440px;">
    <div class="custom-modal-header">
      <div class="modal-title-wrap">
        <div class="modal-icon-badge" style="background: var(--red-light); color: var(--red);">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
        </div>
        <div>
          <h3>Xác nhận xóa bàn ăn</h3>
          <p class="muted">Hành động này sẽ xóa vĩnh viễn cấu hình bàn</p>
        </div>
      </div>
      <button type="button" class="btn-close-modal" data-close="modal-delete-table">✕</button>
    </div>

    <form method="POST" action="index.php?page=tables" class="modal-form">
      <input type="hidden" name="action" value="delete_table">
      <input type="hidden" name="id" id="delete-table-id">

      <div style="padding: 10px 0 18px;">
        <p>Bạn có chắc chắn muốn xóa bàn <strong id="delete-table-code">...</strong> khỏi sơ đồ nhà hàng không?</p>
        <div id="delete-table-warning" style="display: none; margin-top: 10px; padding: 10px 14px; background: #fee2e2; color: #991b1b; border-radius: var(--radius-sm); font-size: 13px;">
          ⚠ <strong>Cảnh báo:</strong> Bàn này đang có khách hoặc đơn gọi món. Bạn cần hoàn tất thanh toán trước khi xóa!
        </div>
      </div>

      <div class="modal-actions-footer">
        <button type="button" class="btn btn-outline" data-close="modal-delete-table">Hủy</button>
        <button type="submit" class="btn btn-danger" id="btn-confirm-delete-table">Xác nhận xóa bàn</button>
      </div>
    </form>
  </div>
</div>

<!-- ==========================================================
     MODAL 4: THÊM KHU VỰC MỚI
     ========================================================== -->
<div class="custom-modal-backdrop" id="modal-add-area" style="display: none;">
  <div class="custom-modal-dialog">
    <div class="custom-modal-header">
      <div class="modal-title-wrap">
        <div class="modal-icon-badge" style="background: #fef3c7; color: #d97706;">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><polygon points="12 2 2 7 12 12 22 7 12 2"></polygon><polyline points="2 17 12 22 22 17"></polyline><polyline points="2 12 12 17 22 12"></polyline></svg>
        </div>
        <div>
          <h3>Thêm khu vực mới</h3>
          <p class="muted">Định nghĩa phân khu không gian trong nhà hàng</p>
        </div>
      </div>
      <button type="button" class="btn-close-modal" data-close="modal-add-area">✕</button>
    </div>

    <form method="POST" action="index.php?page=tables" class="modal-form">
      <input type="hidden" name="action" value="add_area">

      <div class="form-grid-2" style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
        <div class="form-group">
          <label>Mã định danh khu vực <span class="required">*</span></label>
          <input type="text" name="code" placeholder="VD: indoor, garden, vip, terrace" required style="font-family: monospace; font-weight: 700;">
          <small class="muted">Chỉ chứa chữ thường, số và dấu gạch nối (VD: tang-2, rooftop)</small>
        </div>
        <div class="form-group">
          <label>Tên khu vực <span class="required">*</span></label>
          <input type="text" name="name" placeholder="VD: Tầng 2 (Sân thượng)" required>
        </div>
      </div>

      <div class="form-group">
        <label>Mô tả không gian</label>
        <textarea name="description" rows="2" placeholder="VD: Không gian ngoài trời thoáng đãng, ngắm trọn cảnh hoàng hôn..." style="padding: 10px; border: 1px solid var(--line); border-radius: var(--radius-sm); outline: none; font-family: inherit; font-size: 14px; width: 100%; resize: vertical;"></textarea>
      </div>

      <div class="form-grid-2" style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
        <div class="form-group">
          <label>Thứ tự hiển thị</label>
          <input type="number" name="sort_order" value="0" min="0">
        </div>
        <div class="form-group">
          <label>Trạng thái</label>
          <select name="status">
            <option value="active">Đang mở phục vụ</option>
            <option value="inactive">Tạm ngưng hoạt động</option>
          </select>
        </div>
      </div>

      <div class="modal-actions-footer">
        <button type="button" class="btn btn-outline" data-close="modal-add-area">Hủy bỏ</button>
        <button type="submit" class="btn btn-primary">Xác nhận tạo khu vực</button>
      </div>
    </form>
  </div>
</div>

<!-- ==========================================================
     MODAL 5: CHỈNH SỬA KHU VỰC
     ========================================================== -->
<div class="custom-modal-backdrop" id="modal-edit-area" style="display: none;">
  <div class="custom-modal-dialog">
    <div class="custom-modal-header">
      <div class="modal-title-wrap">
        <div class="modal-icon-badge" style="background: #e0f2fe; color: #0284c7;">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M12 20h9"></path><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path></svg>
        </div>
        <div>
          <h3>Chỉnh sửa khu vực</h3>
          <p class="muted">Cập nhật tên, mã định danh hoặc mô tả không gian</p>
        </div>
      </div>
      <button type="button" class="btn-close-modal" data-close="modal-edit-area">✕</button>
    </div>

    <form method="POST" action="index.php?page=tables" class="modal-form">
      <input type="hidden" name="action" value="edit_area">
      <input type="hidden" name="id" id="edit-area-id">

      <div class="form-grid-2" style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
        <div class="form-group">
          <label>Mã định danh <span class="required">*</span></label>
          <input type="text" name="code" id="edit-area-code" required style="font-family: monospace; font-weight: 700;">
        </div>
        <div class="form-group">
          <label>Tên khu vực <span class="required">*</span></label>
          <input type="text" name="name" id="edit-area-name" required>
        </div>
      </div>

      <div class="form-group">
        <label>Mô tả không gian</label>
        <textarea name="description" id="edit-area-description" rows="2" style="padding: 10px; border: 1px solid var(--line); border-radius: var(--radius-sm); outline: none; font-family: inherit; font-size: 14px; width: 100%; resize: vertical;"></textarea>
      </div>

      <div class="form-grid-2" style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
        <div class="form-group">
          <label>Thứ tự hiển thị</label>
          <input type="number" name="sort_order" id="edit-area-sort-order" min="0">
        </div>
        <div class="form-group">
          <label>Trạng thái</label>
          <select name="status" id="edit-area-status">
            <option value="active">Đang mở phục vụ</option>
            <option value="inactive">Tạm ngưng hoạt động</option>
          </select>
        </div>
      </div>

      <div class="modal-actions-footer">
        <button type="button" class="btn btn-outline" data-close="modal-edit-area">Hủy bỏ</button>
        <button type="submit" class="btn btn-primary">Lưu thay đổi</button>
      </div>
    </form>
  </div>
</div>

<!-- ==========================================================
     MODAL 6: XÁC NHẬN XÓA KHU VỰC
     ========================================================== -->
<div class="custom-modal-backdrop" id="modal-delete-area" style="display: none;">
  <div class="custom-modal-dialog custom-modal-sm" style="max-width: 440px;">
    <div class="custom-modal-header">
      <div class="modal-title-wrap">
        <div class="modal-icon-badge" style="background: var(--red-light); color: var(--red);">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
        </div>
        <div>
          <h3>Xác nhận xóa khu vực</h3>
          <p class="muted">Hành động này sẽ xóa vĩnh viễn khu vực</p>
        </div>
      </div>
      <button type="button" class="btn-close-modal" data-close="modal-delete-area">✕</button>
    </div>

    <form method="POST" action="index.php?page=tables" class="modal-form">
      <input type="hidden" name="action" value="delete_area">
      <input type="hidden" name="id" id="delete-area-id">

      <div style="padding: 10px 0 18px;">
        <p>Bạn có chắc chắn muốn xóa khu vực <strong id="delete-area-name">...</strong> không?</p>
        <div id="delete-area-warning" style="display: none; margin-top: 10px; padding: 10px 14px; background: #fee2e2; color: #991b1b; border-radius: var(--radius-sm); font-size: 13px;">
          ⚠ <strong>Cảnh báo:</strong> Khu vực này đang chứa <strong id="delete-area-tables-count">0</strong> bàn ăn. Bạn phải xóa hoặc di chuyển các bàn sang khu vực khác trước khi xóa khu vực này!
        </div>
      </div>

      <div class="modal-actions-footer">
        <button type="button" class="btn btn-outline" data-close="modal-delete-area">Hủy</button>
        <button type="submit" class="btn btn-danger" id="btn-confirm-delete-area">Xác nhận xóa khu vực</button>
      </div>
    </form>
  </div>
</div>

<!-- ==========================================================
     JAVASCRIPT ĐIỀU KHIỂN MODAL & FORM
     ========================================================== -->
<script>
document.addEventListener('DOMContentLoaded', function() {
  const openModal = id => {
    const m = document.getElementById(id);
    if (m) m.style.display = 'flex';
  };

  const closeModal = id => {
    const m = document.getElementById(id);
    if (m) m.style.display = 'none';
  };

  // Nút mở modal thêm bàn
  const btnAddTable = document.getElementById('btn-open-add-table');
  if (btnAddTable) {
    btnAddTable.addEventListener('click', () => openModal('modal-add-table'));
  }

  // Nút mở modal thêm khu vực
  const btnAddArea = document.getElementById('btn-open-add-area');
  if (btnAddArea) {
    btnAddArea.addEventListener('click', () => openModal('modal-add-area'));
  }
  const btnAddAreaSub = document.getElementById('btn-open-add-area-sub');
  if (btnAddAreaSub) {
    btnAddAreaSub.addEventListener('click', () => openModal('modal-add-area'));
  }

  // Đóng tất cả modal
  document.querySelectorAll('[data-close]').forEach(btn => {
    btn.addEventListener('click', function() {
      const targetId = this.getAttribute('data-close');
      closeModal(targetId);
    });
  });

  // Bấm backdrop để đóng modal
  document.querySelectorAll('.custom-modal-backdrop').forEach(modal => {
    modal.addEventListener('click', function(e) {
      if (e.target === this) {
        this.style.display = 'none';
      }
    });
  });

  // Phím ESC đóng modal
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
      document.querySelectorAll('.custom-modal-backdrop').forEach(m => m.style.display = 'none');
    }
  });

  // Nút Sửa bàn ăn
  document.querySelectorAll('.btn-edit-table').forEach(btn => {
    btn.addEventListener('click', function() {
      document.getElementById('edit-table-id').value         = this.dataset.id;
      document.getElementById('edit-table-code').value       = this.dataset.code;
      document.getElementById('edit-table-name').value       = this.dataset.name;
      document.getElementById('edit-table-area-id').value    = this.dataset.areaId;
      document.getElementById('edit-table-capacity').value   = this.dataset.capacity;
      document.getElementById('edit-table-shape').value      = this.dataset.shape || 'square';
      document.getElementById('edit-table-status').value     = this.dataset.status;
      document.getElementById('edit-table-sort-order').value = this.dataset.sortOrder;
      openModal('modal-edit-table');
    });
  });

  // Nút Xóa bàn ăn
  document.querySelectorAll('.btn-delete-table').forEach(btn => {
    btn.addEventListener('click', function() {
      document.getElementById('delete-table-id').value = this.dataset.id;
      document.getElementById('delete-table-code').textContent = this.dataset.code;

      const isOccupied = this.dataset.isOccupied === '1';
      const warningEl  = document.getElementById('delete-table-warning');
      const submitBtn  = document.getElementById('btn-confirm-delete-table');

      if (isOccupied) {
        warningEl.style.display = 'block';
        submitBtn.disabled = true;
        submitBtn.style.opacity = '0.5';
        submitBtn.style.cursor = 'not-allowed';
      } else {
        warningEl.style.display = 'none';
        submitBtn.disabled = false;
        submitBtn.style.opacity = '1';
        submitBtn.style.cursor = 'pointer';
      }

      openModal('modal-delete-table');
    });
  });

  // Nút Sửa khu vực
  document.querySelectorAll('.btn-edit-area').forEach(btn => {
    btn.addEventListener('click', function() {
      document.getElementById('edit-area-id').value          = this.dataset.id;
      document.getElementById('edit-area-code').value        = this.dataset.code;
      document.getElementById('edit-area-name').value        = this.dataset.name;
      document.getElementById('edit-area-description').value = this.dataset.description;
      document.getElementById('edit-area-sort-order').value  = this.dataset.sortOrder;
      document.getElementById('edit-area-status').value      = this.dataset.status;
      openModal('modal-edit-area');
    });
  });

  // Nút Xóa khu vực
  document.querySelectorAll('.btn-delete-area').forEach(btn => {
    btn.addEventListener('click', function() {
      const id = this.dataset.id;
      const name = this.dataset.name;
      const count = parseInt(this.dataset.tablesCount, 10) || 0;

      document.getElementById('delete-area-id').value = id;
      document.getElementById('delete-area-name').textContent = name;
      document.getElementById('delete-area-tables-count').textContent = count;

      const warningEl = document.getElementById('delete-area-warning');
      const submitBtn = document.getElementById('btn-confirm-delete-area');

      if (count > 0) {
        warningEl.style.display = 'block';
        submitBtn.disabled = true;
        submitBtn.style.opacity = '0.5';
        submitBtn.style.cursor = 'not-allowed';
      } else {
        warningEl.style.display = 'none';
        submitBtn.disabled = false;
        submitBtn.style.opacity = '1';
        submitBtn.style.cursor = 'pointer';
      }

      openModal('modal-delete-area');
    });
  });

  // ==========================================================
  // JAVASCRIPT TRÌNH THIẾT KẾ SƠ ĐỒ 2D KÉO THẢ (TAB 3)
  // ==========================================================
  const layoutZoneSelect = document.getElementById('layout-zone-select');
  const layoutCanvas     = document.getElementById('admin-layout-canvas');
  const entranceDoorEl   = document.getElementById('admin-entrance-door');
  const entranceSelectEl = document.getElementById('entrance-pos-select');
  const entranceLabelEl  = document.getElementById('admin-entrance-label');

  if (layoutZoneSelect && layoutCanvas) {
    // Các vị trí cửa ra vào định sẵn (8 góc & cạnh)
    const ENTRANCE_PRESETS = {
      'bottom_center': { x: 50.0, y: 96.0, label: '▲ CỬA RA VÀO (DƯỚI) ▲' },
      'bottom_left':   { x: 14.0, y: 96.0, label: '▲ CỬA RA VÀO (GÓC DƯỚI TRÁI) ▲' },
      'bottom_right':  { x: 86.0, y: 96.0, label: '▲ CỬA RA VÀO (GÓC DƯỚI PHẢI) ▲' },
      'top_center':    { x: 50.0, y: 4.0,  label: '▼ CỬA RA VÀO (TRÊN) ▼' },
      'top_left':      { x: 14.0, y: 4.0,  label: '▼ CỬA RA VÀO (GÓC TRÊN TRÁI) ▼' },
      'top_right':     { x: 86.0, y: 4.0,  label: '▼ CỬA RA VÀO (GÓC TRÊN PHẢI) ▼' },
      'left_center':   { x: 7.0,  y: 50.0, label: '▶ CỬA RA VÀO (CẠNH TRÁI) ▶' },
      'right_center':  { x: 93.0, y: 50.0, label: '◀ CỬA RA VÀO (CẠNH PHẢI) ◀' }
    };

    const applyEntrancePosition = (posCode, x, y, updateSelect = true) => {
      if (!entranceDoorEl) return;
      if (posCode && ENTRANCE_PRESETS[posCode]) {
        x = ENTRANCE_PRESETS[posCode].x;
        y = ENTRANCE_PRESETS[posCode].y;
        if (entranceLabelEl) entranceLabelEl.textContent = ENTRANCE_PRESETS[posCode].label;
      } else {
        if (entranceLabelEl) entranceLabelEl.textContent = '🚪 CỬA RA VÀO';
      }

      entranceDoorEl.style.left = x.toFixed(2) + '%';
      entranceDoorEl.style.top  = y.toFixed(2) + '%';
      entranceDoorEl.dataset.pos  = posCode || 'custom';
      entranceDoorEl.dataset.posX = x.toFixed(2);
      entranceDoorEl.dataset.posY = y.toFixed(2);

      if (updateSelect && entranceSelectEl) {
        entranceSelectEl.value = ENTRANCE_PRESETS[posCode] ? posCode : 'custom';
      }
    };

    const cashierCounterEl = document.getElementById('admin-cashier-counter');

    // 1. Lọc hiển thị bàn & đồng bộ vị trí Cửa ra vào & Quầy thu ngân theo khu vực
    const filterLayoutTables = () => {
      const selectedZone = layoutZoneSelect.value;
      layoutCanvas.querySelectorAll('.draggable-table-item').forEach(el => {
        if (!selectedZone || el.dataset.zone === selectedZone) {
          el.style.display = 'flex';
        } else {
          el.style.display = 'none';
        }
      });

      // Đồng bộ vị trí Cửa ra vào & Quầy thu ngân của khu vực được chọn
      const areaData = (window.SMARTDINE_AREAS || []).find(a => a.code === selectedZone);
      if (areaData) {
        // Cửa ra vào
        const pos = areaData.entrance_pos || 'bottom_center';
        const x   = parseFloat(areaData.entrance_x) || 50.0;
        const y   = parseFloat(areaData.entrance_y) || 96.0;
        applyEntrancePosition(pos, x, y, true);

        // Quầy thu ngân
        if (cashierCounterEl) {
          const cx = parseFloat(areaData.cashier_x) || 14.0;
          const cy = parseFloat(areaData.cashier_y) || 6.0;
          cashierCounterEl.style.left = cx.toFixed(2) + '%';
          cashierCounterEl.style.top  = cy.toFixed(2) + '%';
          cashierCounterEl.dataset.posX = cx.toFixed(2);
          cashierCounterEl.dataset.posY = cy.toFixed(2);
        }
      }
    };
    layoutZoneSelect.addEventListener('change', filterLayoutTables);
    filterLayoutTables();

    // 2. Chuyển vị trí cửa ra vào qua menu dropdown
    if (entranceSelectEl) {
      entranceSelectEl.addEventListener('change', function() {
        const val = this.value;
        if (val !== 'custom' && ENTRANCE_PRESETS[val]) {
          applyEntrancePosition(val, ENTRANCE_PRESETS[val].x, ENTRANCE_PRESETS[val].y, false);
        }
      });
    }

    // 3. Kéo thả Cửa ra vào đến bất kỳ vị trí/góc nào
    if (entranceDoorEl) {
      let isDraggingDoor = false;
      let doorStartX     = 0;
      let doorStartY     = 0;
      let doorInitLeft   = 50;
      let doorInitTop    = 96;

      entranceDoorEl.addEventListener('pointerdown', function(e) {
        isDraggingDoor = true;
        this.setPointerCapture(e.pointerId);
        this.classList.add('is-dragging');
        doorStartX   = e.clientX;
        doorStartY   = e.clientY;
        doorInitLeft = parseFloat(this.dataset.posX) || 50;
        doorInitTop  = parseFloat(this.dataset.posY) || 96;
        e.preventDefault();
      });

      entranceDoorEl.addEventListener('pointermove', function(e) {
        if (!isDraggingDoor) return;
        const rect = layoutCanvas.getBoundingClientRect();
        if (rect.width <= 0 || rect.height <= 0) return;

        const deltaX = ((e.clientX - doorStartX) / rect.width) * 100;
        const deltaY = ((e.clientY - doorStartY) / rect.height) * 100;

        let newX = Math.max(4, Math.min(96, doorInitLeft + deltaX));
        let newY = Math.max(4, Math.min(96, doorInitTop + deltaY));

        this.style.left = newX.toFixed(2) + '%';
        this.style.top  = newY.toFixed(2) + '%';
        this.dataset.posX = newX.toFixed(2);
        this.dataset.posY = newY.toFixed(2);
        this.dataset.pos  = 'custom';

        if (entranceSelectEl) entranceSelectEl.value = 'custom';
        if (entranceLabelEl)  entranceLabelEl.textContent = '🚪 CỬA RA VÀO';
      });

      const endDragDoor = function(e) {
        if (isDraggingDoor) {
          this.releasePointerCapture(e.pointerId);
          this.classList.remove('is-dragging');
          isDraggingDoor = false;
        }
      };

      entranceDoorEl.addEventListener('pointerup', endDragDoor);
      entranceDoorEl.addEventListener('pointercancel', endDragDoor);
    }

    // 4. Kéo thả Quầy thu ngân / Pha chế
    if (cashierCounterEl) {
      let isDraggingCashier = false;
      let cashierStartX     = 0;
      let cashierStartY     = 0;
      let cashierInitLeft   = 14;
      let cashierInitTop    = 6;

      cashierCounterEl.addEventListener('pointerdown', function(e) {
        isDraggingCashier = true;
        this.setPointerCapture(e.pointerId);
        this.classList.add('is-dragging');
        cashierStartX   = e.clientX;
        cashierStartY   = e.clientY;
        cashierInitLeft = parseFloat(this.dataset.posX) || 14;
        cashierInitTop  = parseFloat(this.dataset.posY) || 6;
        e.preventDefault();
      });

      cashierCounterEl.addEventListener('pointermove', function(e) {
        if (!isDraggingCashier) return;
        const rect = layoutCanvas.getBoundingClientRect();
        if (rect.width <= 0 || rect.height <= 0) return;

        const deltaX = ((e.clientX - cashierStartX) / rect.width) * 100;
        const deltaY = ((e.clientY - cashierStartY) / rect.height) * 100;

        let newX = Math.max(4, Math.min(96, cashierInitLeft + deltaX));
        let newY = Math.max(4, Math.min(96, cashierInitTop + deltaY));

        this.style.left = newX.toFixed(2) + '%';
        this.style.top  = newY.toFixed(2) + '%';
        this.dataset.posX = newX.toFixed(2);
        this.dataset.posY = newY.toFixed(2);
      });

      const endDragCashier = function(e) {
        if (isDraggingCashier) {
          this.releasePointerCapture(e.pointerId);
          this.classList.remove('is-dragging');
          isDraggingCashier = false;
        }
      };

      cashierCounterEl.addEventListener('pointerup', endDragCashier);
      cashierCounterEl.addEventListener('pointercancel', endDragCashier);
    }

    // 5. Kéo thả Bàn ăn (Pointer Events - hỗ trợ cả PC chuột & Tablet chạm cảm ứng)
    let activeDragEl   = null;
    let startX         = 0;
    let startY         = 0;
    let initialLeftPct = 0;
    let initialTopPct  = 0;

    layoutCanvas.querySelectorAll('.draggable-table-item').forEach(el => {
      el.addEventListener('pointerdown', function(e) {
        // Nếu click vào nút điều khiển hoặc góc kéo resize thì bỏ qua kéo vị trí bàn
        if (e.target.closest('.btn-toggle-shape') || e.target.closest('.btn-table-size') || e.target.closest('.table-resize-handle')) {
          return;
        }

        activeDragEl = this;
        this.setPointerCapture(e.pointerId);
        this.classList.add('is-dragging');

        const rect = layoutCanvas.getBoundingClientRect();
        startX = e.clientX;
        startY = e.clientY;
        initialLeftPct = parseFloat(this.dataset.posX) || 20;
        initialTopPct  = parseFloat(this.dataset.posY) || 25;
        e.preventDefault();
      });

      el.addEventListener('pointermove', function(e) {
        if (!activeDragEl || activeDragEl !== this) return;
        const rect = layoutCanvas.getBoundingClientRect();
        if (rect.width <= 0 || rect.height <= 0) return;

        const deltaXPct = ((e.clientX - startX) / rect.width) * 100;
        const deltaYPct = ((e.clientY - startY) / rect.height) * 100;

        let newXPct = initialLeftPct + deltaXPct;
        let newYPct = initialTopPct + deltaYPct;

        // Giới hạn bàn nằm trọn trong canvas (từ 5% đến 95%)
        newXPct = Math.max(5, Math.min(95, newXPct));
        newYPct = Math.max(5, Math.min(95, newYPct));

        this.style.left = newXPct.toFixed(2) + '%';
        this.style.top  = newYPct.toFixed(2) + '%';
        this.dataset.posX = newXPct.toFixed(2);
        this.dataset.posY = newYPct.toFixed(2);
      });

      const endDrag = function(e) {
        if (activeDragEl === this) {
          this.releasePointerCapture(e.pointerId);
          this.classList.remove('is-dragging');
          activeDragEl = null;
        }
      };

      el.addEventListener('pointerup', endDrag);
      el.addEventListener('pointercancel', endDrag);
    });

    // 6. Nút chỉnh kích thước to/nhỏ bàn ([-] và [+])
    layoutCanvas.querySelectorAll('.btn-size-down').forEach(btn => {
      btn.addEventListener('click', function(e) {
        e.stopPropagation();
        const table = this.closest('.draggable-table-item');
        if (!table) return;

        let w = parseInt(table.dataset.width, 10) || 85;
        let h = parseInt(table.dataset.height, 10) || 85;
        const shape = table.dataset.shape || 'square';
        const cap = table.dataset.capacity || 4;

        if (shape === 'rectangle') {
          w = Math.max(80, w - 15);
          h = Math.max(60, h - 10);
        } else {
          w = Math.max(60, w - 10);
          h = w;
        }

        table.style.width  = w + 'px';
        table.style.height = h + 'px';
        table.dataset.width  = w;
        table.dataset.height = h;

        const label = table.querySelector('.table-dim-label');
        if (label) label.textContent = `${w}x${h} · ${cap}c`;
      });
    });

    layoutCanvas.querySelectorAll('.btn-size-up').forEach(btn => {
      btn.addEventListener('click', function(e) {
        e.stopPropagation();
        const table = this.closest('.draggable-table-item');
        if (!table) return;

        let w = parseInt(table.dataset.width, 10) || 85;
        let h = parseInt(table.dataset.height, 10) || 85;
        const shape = table.dataset.shape || 'square';
        const cap = table.dataset.capacity || 4;

        if (shape === 'rectangle') {
          w = Math.min(220, w + 15);
          h = Math.min(150, h + 10);
        } else {
          w = Math.min(180, w + 10);
          h = w;
        }

        table.style.width  = w + 'px';
        table.style.height = h + 'px';
        table.dataset.width  = w;
        table.dataset.height = h;

        const label = table.querySelector('.table-dim-label');
        if (label) label.textContent = `${w}x${h} · ${cap}c`;
      });
    });

    // 7. Kéo góc dưới phải để chỉnh kích thước bàn trực tiếp
    layoutCanvas.querySelectorAll('.table-resize-handle').forEach(handle => {
      let isResizing = false;
      let startResizeX = 0;
      let startResizeY = 0;
      let startW = 85;
      let startH = 85;
      let resizeTable = null;

      handle.addEventListener('pointerdown', function(e) {
        isResizing = true;
        resizeTable = this.closest('.draggable-table-item');
        this.setPointerCapture(e.pointerId);
        startResizeX = e.clientX;
        startResizeY = e.clientY;
        startW = parseInt(resizeTable.dataset.width, 10) || 85;
        startH = parseInt(resizeTable.dataset.height, 10) || 85;
        e.stopPropagation();
        e.preventDefault();
      });

      handle.addEventListener('pointermove', function(e) {
        if (!isResizing || !resizeTable) return;
        const deltaX = (e.clientX - startResizeX);
        const deltaY = (e.clientY - startResizeY);
        const shape  = resizeTable.dataset.shape || 'square';
        const cap    = resizeTable.dataset.capacity || 4;

        let newW, newH;
        if (shape === 'rectangle') {
          newW = Math.max(75, Math.min(240, startW + deltaX * 1.5));
          newH = Math.max(55, Math.min(160, startH + deltaY * 1.5));
        } else {
          const delta = Math.max(deltaX, deltaY);
          newW = Math.max(55, Math.min(190, startW + delta * 1.5));
          newH = newW;
        }

        newW = Math.round(newW);
        newH = Math.round(newH);

        resizeTable.style.width  = newW + 'px';
        resizeTable.style.height = newH + 'px';
        resizeTable.dataset.width  = newW;
        resizeTable.dataset.height = newH;

        const label = resizeTable.querySelector('.table-dim-label');
        if (label) label.textContent = `${newW}x${newH} · ${cap}c`;
      });

      const endResize = function(e) {
        if (isResizing) {
          this.releasePointerCapture(e.pointerId);
          isResizing = false;
          resizeTable = null;
        }
      };

      handle.addEventListener('pointerup', endResize);
      handle.addEventListener('pointercancel', endResize);
    });

    // 8. Nút đổi hình dạng nhanh: Vuông -> Tròn -> Chữ nhật -> Vuông
    const shapeSequence = {
      'square':    { next: 'round',     width: 85,  height: 85 },
      'round':     { next: 'rectangle', width: 125, height: 85 },
      'rectangle': { next: 'square',    width: 85,  height: 85 }
    };

    layoutCanvas.querySelectorAll('.btn-toggle-shape').forEach(btn => {
      btn.addEventListener('click', function(e) {
        e.stopPropagation();
        const tableItem = this.closest('.draggable-table-item');
        if (!tableItem) return;

        const curShape = tableItem.dataset.shape || 'square';
        const nextCfg  = shapeSequence[curShape] || shapeSequence['square'];
        const cap      = tableItem.dataset.capacity || 4;

        tableItem.classList.remove('shape-square', 'shape-round', 'shape-rectangle');
        tableItem.classList.add('shape-' + nextCfg.next);

        tableItem.dataset.shape  = nextCfg.next;
        tableItem.dataset.width  = nextCfg.width;
        tableItem.dataset.height = nextCfg.height;

        tableItem.style.width        = nextCfg.width + 'px';
        tableItem.style.height       = nextCfg.height + 'px';
        tableItem.style.borderRadius = (nextCfg.next === 'round') ? '50%' : (nextCfg.next === 'rectangle' ? '16px' : '14px');

        const label = tableItem.querySelector('.table-dim-label');
        if (label) label.textContent = `${nextCfg.width}x${nextCfg.height} · ${cap}c`;
      });
    });

    // 9. Lưu vị trí bàn, kích thước, quầy thu ngân và cửa ra vào lên Server
    const btnSaveLayout = document.getElementById('btn-save-layout');
    const indicatorEl   = document.getElementById('layout-save-indicator');

    if (btnSaveLayout) {
      btnSaveLayout.addEventListener('click', function() {
        const payload = [];
        layoutCanvas.querySelectorAll('.draggable-table-item').forEach(node => {
          payload.push({
            id: parseInt(node.dataset.id, 10),
            pos_x: parseFloat(node.dataset.posX) || 20,
            pos_y: parseFloat(node.dataset.posY) || 25,
            shape: node.dataset.shape || 'square',
            width: parseInt(node.dataset.width, 10) || 85,
            height: parseInt(node.dataset.height, 10) || 85
          });
        });

        const curZone     = layoutZoneSelect ? layoutZoneSelect.value : 'indoor';
        const curDoorPos  = entranceDoorEl ? (entranceDoorEl.dataset.pos || 'bottom_center') : 'bottom_center';
        const curDoorX    = entranceDoorEl ? (parseFloat(entranceDoorEl.dataset.posX) || 50.0) : 50.0;
        const curDoorY    = entranceDoorEl ? (parseFloat(entranceDoorEl.dataset.posY) || 96.0) : 96.0;
        const curCashierX = cashierCounterEl ? (parseFloat(cashierCounterEl.dataset.posX) || 14.0) : 14.0;
        const curCashierY = cashierCounterEl ? (parseFloat(cashierCounterEl.dataset.posY) || 6.0) : 6.0;

        btnSaveLayout.disabled = true;
        btnSaveLayout.style.opacity = '0.7';
        if (indicatorEl) {
          indicatorEl.style.display = 'inline-block';
          indicatorEl.style.background = '#fef3c7';
          indicatorEl.style.color = '#92400e';
          indicatorEl.textContent = '⏳ Đang lưu sơ đồ...';
        }

        fetch('api/save-table-layout.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            area_code: curZone,
            entrance_pos: curDoorPos,
            entrance_x: curDoorX,
            entrance_y: curDoorY,
            cashier_x: curCashierX,
            cashier_y: curCashierY,
            tables: payload
          })
        })
        .then(res => res.json())
        .then(data => {
          btnSaveLayout.disabled = false;
          btnSaveLayout.style.opacity = '1';
          if (indicatorEl) {
            if (data.success) {
              // Cập nhật bộ nhớ cục bộ
              const targetArea = (window.SMARTDINE_AREAS || []).find(a => a.code === curZone);
              if (targetArea) {
                targetArea.entrance_pos = curDoorPos;
                targetArea.entrance_x   = curDoorX;
                targetArea.entrance_y   = curDoorY;
                targetArea.cashier_x    = curCashierX;
                targetArea.cashier_y    = curCashierY;
              }
              indicatorEl.style.background = '#dcfce7';
              indicatorEl.style.color = '#15803d';
              indicatorEl.textContent = '✔ ' + data.message;
            } else {
              indicatorEl.style.background = '#fee2e2';
              indicatorEl.style.color = '#b91c1c';
              indicatorEl.textContent = '✖ ' + (data.message || 'Lỗi khi lưu sơ đồ!');
            }
            setTimeout(() => {
              indicatorEl.style.display = 'none';
            }, 4000);
          }
        })
        .catch(err => {
          btnSaveLayout.disabled = false;
          btnSaveLayout.style.opacity = '1';
          if (indicatorEl) {
            indicatorEl.style.background = '#fee2e2';
            indicatorEl.style.color = '#b91c1c';
            indicatorEl.textContent = '✖ Lỗi mạng hoặc máy chủ không phản hồi';
            setTimeout(() => {
              indicatorEl.style.display = 'none';
            }, 4000);
          }
        });
      });
    }
  }
});
</script>


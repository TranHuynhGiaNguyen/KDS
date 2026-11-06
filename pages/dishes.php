<?php
/**
 * SmartDine - Quản lý Thực đơn, Món ăn, Danh mục, Giá & Hình ảnh
 * Dành riêng cho Quản trị viên (Admin)
 */

if (!isAdmin()) {
    $_SESSION['flash_error'] = 'Chỉ Quản trị viên (Admin) mới có quyền truy cập trang Quản lý thực đơn!';
    header('Location: index.php?page=' . getDefaultPageForRole(currentRole()));
    exit;
}

// ==========================================================
// XỬ LÝ CÁC HÀNH ĐỘNG POST (CRUD)
// ==========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // 1. THÊM MÓN ĂN MỚI
    if ($action === 'add_dish') {
        $name        = trim($_POST['name'] ?? '');
        $categoryId  = (int) ($_POST['category_id'] ?? 0);
        $price       = (float) ($_POST['price'] ?? 0);
        $imageUrl    = trim($_POST['image_url'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $tag         = trim($_POST['tag'] ?? '');
        $station     = trim($_POST['station'] ?? 'hot');
        $status      = trim($_POST['status'] ?? 'active');
        $rating      = !empty($_POST['rating']) ? (float)$_POST['rating'] : 5.0;

        if (empty($name)) {
            $_SESSION['flash_error'] = 'Tên món ăn không được để trống!';
        } elseif ($categoryId <= 0) {
            $_SESSION['flash_error'] = 'Vui lòng chọn danh mục cho món ăn!';
        } elseif ($price < 0) {
            $_SESSION['flash_error'] = 'Giá món ăn không được âm!';
        } else {
            // Xử lý tệp ảnh tải lên hoặc URL
            $imageFile = $_FILES['image_file'] ?? null;
            $uploadRes = handleDishImageUpload($imageFile, $imageUrl);

            if (!$uploadRes['success']) {
                $_SESSION['flash_error'] = $uploadRes['message'];
            } else {
                $finalImagePath = $uploadRes['path'];

                $res = createDish([
                    'category_id' => $categoryId,
                    'name'        => $name,
                    'price'       => $price,
                    'image'       => $finalImagePath,
                    'description' => $description,
                    'tag'         => $tag,
                    'station'     => $station,
                    'status'      => $status,
                    'rating'      => $rating
                ]);

                if ($res['success']) {
                    $_SESSION['flash_success'] = 'Thêm món ăn "' . htmlspecialchars($name) . '" thành công!';
                } else {
                    $_SESSION['flash_error'] = $res['message'];
                }
            }
        }
        header('Location: index.php?page=dishes');
        exit;
    }

    // 2. CHỈNH SỬA MÓN ĂN
    if ($action === 'edit_dish') {
        $id           = (int) ($_POST['id'] ?? 0);
        $name         = trim($_POST['name'] ?? '');
        $categoryId   = (int) ($_POST['category_id'] ?? 0);
        $price        = (float) ($_POST['price'] ?? 0);
        $imageUrl     = trim($_POST['image_url'] ?? '');
        $currentImage = trim($_POST['current_image'] ?? '');
        $description  = trim($_POST['description'] ?? '');
        $tag          = trim($_POST['tag'] ?? '');
        $station      = trim($_POST['station'] ?? 'hot');
        $status       = trim($_POST['status'] ?? 'active');
        $rating       = !empty($_POST['rating']) ? (float)$_POST['rating'] : 5.0;

        if (empty($name)) {
            $_SESSION['flash_error'] = 'Tên món ăn không được để trống!';
        } elseif ($categoryId <= 0) {
            $_SESSION['flash_error'] = 'Vui lòng chọn danh mục cho món ăn!';
        } elseif ($price < 0) {
            $_SESSION['flash_error'] = 'Giá món ăn không được âm!';
        } else {
            // Xử lý ảnh tải lên hoặc URL mới
            $imageFile = $_FILES['image_file'] ?? null;
            $uploadRes = handleDishImageUpload($imageFile, $imageUrl, $currentImage);

            if (!$uploadRes['success']) {
                $_SESSION['flash_error'] = $uploadRes['message'];
            } else {
                $finalImagePath = $uploadRes['path'];

                $res = updateDish($id, [
                    'category_id' => $categoryId,
                    'name'        => $name,
                    'price'       => $price,
                    'image'       => $finalImagePath,
                    'description' => $description,
                    'tag'         => $tag,
                    'station'     => $station,
                    'status'      => $status,
                    'rating'      => $rating
                ]);

                if ($res['success']) {
                    $_SESSION['flash_success'] = 'Cập nhật món ăn "' . htmlspecialchars($name) . '" thành công!';
                } else {
                    $_SESSION['flash_error'] = $res['message'];
                }
            }
        }
        header('Location: index.php?page=dishes');
        exit;
    }

    // 3. ĐỔI NHANH TRẠNG THÁI MÓN (Đang bán <-> Tạm hết)
    if ($action === 'toggle_dish_status') {
        $id = (int) ($_POST['id'] ?? 0);
        $res = toggleDishStatus($id);
        if ($res['success']) {
            $_SESSION['flash_success'] = $res['message'];
        } else {
            $_SESSION['flash_error'] = $res['message'];
        }
        header('Location: index.php?page=dishes');
        exit;
    }

    // 4. XÓA MÓN ĂN
    if ($action === 'delete_dish') {
        $id = (int) ($_POST['id'] ?? 0);
        $res = deleteDish($id);
        if ($res['success']) {
            $_SESSION['flash_success'] = $res['message'];
        } else {
            $_SESSION['flash_error'] = $res['message'];
        }
        header('Location: index.php?page=dishes');
        exit;
    }

    // 5. THÊM DANH MỤC MỚI
    if ($action === 'add_category') {
        $name        = trim($_POST['name'] ?? '');
        $slug        = trim($_POST['slug'] ?? '');
        $icon        = trim($_POST['icon'] ?? '✦');
        $description = trim($_POST['description'] ?? '');
        $sortOrder   = (int) ($_POST['sort_order'] ?? 0);
        $status      = trim($_POST['status'] ?? 'active');

        if (empty($name)) {
            $_SESSION['flash_error'] = 'Tên danh mục không được để trống!';
        } else {
            $res = createCategory([
                'name'        => $name,
                'slug'        => $slug,
                'icon'        => $icon,
                'description' => $description,
                'sort_order'  => $sortOrder,
                'status'      => $status
            ]);
            if ($res['success']) {
                $_SESSION['flash_success'] = $res['message'];
            } else {
                $_SESSION['flash_error'] = $res['message'];
            }
        }
        header('Location: index.php?page=dishes&tab=categories');
        exit;
    }

    // 6. CHỈNH SỬA DANH MỤC
    if ($action === 'edit_category') {
        $id          = (int) ($_POST['id'] ?? 0);
        $name        = trim($_POST['name'] ?? '');
        $slug        = trim($_POST['slug'] ?? '');
        $icon        = trim($_POST['icon'] ?? '✦');
        $description = trim($_POST['description'] ?? '');
        $sortOrder   = (int) ($_POST['sort_order'] ?? 0);
        $status      = trim($_POST['status'] ?? 'active');

        if (empty($name)) {
            $_SESSION['flash_error'] = 'Tên danh mục không được để trống!';
        } else {
            $res = updateCategory($id, [
                'name'        => $name,
                'slug'        => $slug,
                'icon'        => $icon,
                'description' => $description,
                'sort_order'  => $sortOrder,
                'status'      => $status
            ]);
            if ($res['success']) {
                $_SESSION['flash_success'] = $res['message'];
            } else {
                $_SESSION['flash_error'] = $res['message'];
            }
        }
        header('Location: index.php?page=dishes&tab=categories');
        exit;
    }

    // 7. XÓA DANH MỤC
    if ($action === 'delete_category') {
        $id = (int) ($_POST['id'] ?? 0);
        $res = deleteCategory($id);
        if ($res['success']) {
            $_SESSION['flash_success'] = $res['message'];
        } else {
            $_SESSION['flash_error'] = $res['message'];
        }
        header('Location: index.php?page=dishes&tab=categories');
        exit;
    }

    // 8. ĐỔI TRẠNG THÁI DANH MỤC
    if ($action === 'toggle_category_status') {
        $id = (int) ($_POST['id'] ?? 0);
        $res = toggleCategoryStatus($id);
        if ($res['success']) {
            $_SESSION['flash_success'] = $res['message'];
        } else {
            $_SESSION['flash_error'] = $res['message'];
        }
        header('Location: index.php?page=dishes&tab=categories');
        exit;
    }
}

// ==========================================================
// DỮ LIỆU HIỂN THỊ
// ==========================================================
$currentTab     = $_GET['tab'] ?? 'dishes';
$filterCategory = trim($_GET['category'] ?? '');
$filterStatus   = trim($_GET['status'] ?? '');
$filterStation  = trim($_GET['station'] ?? '');
$search         = trim($_GET['search'] ?? '');

$allCategories  = getAllCategories();
$allDishes      = getAllDishes($filterCategory, $filterStatus, $search, $filterStation);
$dishStats      = getDishStats();
?>

<!-- TIÊU ĐỀ TRANG VÀ NÚT TÁC VỤ NHANH -->
<div class="dash-header">
  <div>
    <span class="eyebrow">SMARTDINE MANAGEMENT • THỰC ĐƠN & BẾP</span>
    <h1>Quản lý Thực đơn, Món ăn & Giá bán</h1>
    <p>Thêm món ăn mới, điều chỉnh giá bán, tải ảnh món ăn và thiết lập danh mục phân loại nhà hàng.</p>
  </div>
  <div class="filter-row">
    <a class="btn btn-outline" href="index.php?page=menu" target="_blank" title="Xem menu khách hàng">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line></svg>
      <span>Xem menu gọi món</span>
    </a>
    <button type="button" class="btn btn-outline" id="btn-open-add-cat" data-modal-target="modal-add-category">
      <span>+ Thêm danh mục</span>
    </button>
    <button type="button" class="btn btn-primary-tool" id="btn-open-add-dish" data-modal-target="modal-add-dish">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
      <span>+ Thêm món ăn mới</span>
    </button>
  </div>
</div>

<!-- THỐNG KÊ TỔNG QUAN -->
<div class="stat-grid admin-stats">
  <div class="stat-card">
    <div class="stat-card-head">
      <span>Tổng số món ăn</span>
      <span class="stat-badge neutral"><?= $dishStats['total'] ?> món</span>
    </div>
    <b><?= $dishStats['total'] ?> món</b>
    <small>Được chia làm <?= $dishStats['categories'] ?> danh mục</small>
  </div>

  <div class="stat-card">
    <div class="stat-card-head">
      <span>Đang mở bán</span>
      <span class="stat-badge positive"><?= $dishStats['active'] ?> món</span>
    </div>
    <b><?= $dishStats['active'] ?> món sẵn sàng</b>
    <small class="positive">✓ Hiển thị trên thực đơn QR gọi món</small>
  </div>

  <div class="stat-card">
    <div class="stat-card-head">
      <span>Tạm hết / Bếp hết nguyên liệu</span>
      <span class="stat-badge <?= $dishStats['out_of_stock'] > 0 ? 'urgent' : 'neutral' ?>"><?= $dishStats['out_of_stock'] ?> món</span>
    </div>
    <b><?= $dishStats['out_of_stock'] ?> món tạm hết</b>
    <small><?= $dishStats['out_of_stock'] > 0 ? '⚠ Khách không thể thêm vào giỏ' : 'Tất cả nguyên liệu ổn định' ?></small>
  </div>

  <div class="stat-card">
    <div class="stat-card-head">
      <span>Giá bán trung bình</span>
      <span class="stat-badge neutral">Menu</span>
    </div>
    <b><?= formatPrice($dishStats['avg_price']) ?></b>
    <small>Mức giá trung bình mỗi phần ăn</small>
  </div>
</div>

<!-- TAB CHUYỂN ĐỔI: MÓN ĂN VÀ DANH MỤC -->
<div class="dishes-nav-tabs">
  <a href="index.php?page=dishes&tab=dishes" class="tab-item <?= $currentTab === 'dishes' ? 'active' : '' ?>">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8h1a4 4 0 0 1 0 8h-1"></path><path d="M2 8h16v9a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V8z"></path><line x1="6" y1="1" x2="6" y2="4"></line><line x1="10" y1="1" x2="10" y2="4"></line><line x1="14" y1="1" x2="14" y2="4"></line></svg>
    <span>Danh sách Món ăn & Giá bán (<?= $dishStats['total'] ?>)</span>
  </a>
  <a href="index.php?page=dishes&tab=categories" class="tab-item <?= $currentTab === 'categories' ? 'active' : '' ?>">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
    <span>Quản lý Danh mục (<?= $dishStats['categories'] ?>)</span>
  </a>
</div>

<?php if ($currentTab === 'dishes'): ?>
  <!-- ==========================================================
       TAB 1: QUẢN LÝ MÓN ĂN & GIÁ BÁN
       ========================================================== -->
  <div class="panel users-panel">
    <!-- BỘ LỌC VÀ TÌM KIẾM MÓN ĂN -->
    <form method="GET" action="index.php" class="user-filter-bar">
      <input type="hidden" name="page" value="dishes">
      <input type="hidden" name="tab" value="dishes">
      
      <div class="search-input-wrap">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Tìm tên món, mô tả, nhãn bán chạy...">
      </div>

      <div class="filter-controls-group">
        <select name="category" onchange="this.form.submit()">
          <option value="">-- Tất cả danh mục --</option>
          <?php foreach ($allCategories as $cat): ?>
            <option value="<?= $cat['id'] ?>" <?= ((string)$filterCategory === (string)$cat['id']) ? 'selected' : '' ?>>
              <?= htmlspecialchars($cat['icon']) ?> <?= htmlspecialchars($cat['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>

        <select name="status" onchange="this.form.submit()">
          <option value="">-- Tất cả trạng thái --</option>
          <option value="active" <?= $filterStatus === 'active' ? 'selected' : '' ?>>Đang mở bán</option>
          <option value="out_of_stock" <?= $filterStatus === 'out_of_stock' ? 'selected' : '' ?>>Tạm hết món</option>
          <option value="hidden" <?= $filterStatus === 'hidden' ? 'selected' : '' ?>>Đang ẩn</option>
        </select>

        <select name="station" onchange="this.form.submit()">
          <option value="">-- Trạm bếp KDS --</option>
          <option value="hot" <?= $filterStation === 'hot' ? 'selected' : '' ?>>Bếp nóng (Xào/Nướng)</option>
          <option value="soup" <?= $filterStation === 'soup' ? 'selected' : '' ?>>Bếp súp / Lẩu</option>
          <option value="cold" <?= $filterStation === 'cold' ? 'selected' : '' ?>>Bếp lạnh / Salad</option>
          <option value="bar" <?= $filterStation === 'bar' ? 'selected' : '' ?>>Quầy Bar / Nước</option>
        </select>

        <button type="submit" class="btn btn-small btn-primary-tool">Lọc</button>

        <?php if (!empty($filterCategory) || !empty($filterStatus) || !empty($filterStation) || !empty($search)): ?>
          <a href="index.php?page=dishes" class="btn btn-small btn-outline" title="Xóa bộ lọc">Đặt lại</a>
        <?php endif; ?>
      </div>
    </form>

    <!-- BẢNG DANH SÁCH MÓN ĂN -->
    <div style="overflow-x: auto;">
      <table class="users-table dishes-table">
        <thead>
          <tr>
            <th style="width: 70px;">Hình ảnh</th>
            <th>Tên món ăn & Mô tả</th>
            <th>Danh mục</th>
            <th>Trạm bếp KDS</th>
            <th>Giá bán</th>
            <th>Nhãn</th>
            <th>Trạng thái</th>
            <th style="text-align: right; width: 150px;">Thao tác</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($allDishes)): ?>
            <tr>
              <td colspan="8">
                <div class="empty-state-table">
                  <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="color: var(--muted);"><path d="M18 8h1a4 4 0 0 1 0 8h-1"></path><path d="M2 8h16v9a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V8z"></path></svg>
                  <h4>Không tìm thấy món ăn nào</h4>
                  <p>Hãy thử tìm kiếm với từ khóa khác hoặc nhấn nút Thêm món ăn mới.</p>
                  <div style="margin-top: 10px;">
                    <a href="index.php?page=dishes" class="btn btn-small btn-outline">Xem tất cả món</a>
                  </div>
                </div>
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($allDishes as $dish): 
              $imgUrl = getDishImageUrl($dish['image']);
              $isOut = ($dish['status'] === 'out_of_stock');
            ?>
              <tr class="<?= $isOut ? 'row-inactive' : '' ?>">
                <td>
                  <div class="dish-thumb-wrap">
                    <img src="<?= $imgUrl ?>" alt="<?= htmlspecialchars($dish['name']) ?>" class="dish-thumb-img" onerror="this.src='https://images.unsplash.com/photo-1546069901-ba9599a7e63c?auto=format&fit=crop&w=150&q=80'">
                  </div>
                </td>

                <td>
                  <div class="dish-title-cell">
                    <b class="dish-name-text"><?= htmlspecialchars($dish['name']) ?></b>
                    <small class="dish-desc-text"><?= htmlspecialchars($dish['description'] ?: 'Chưa có mô tả chi tiết') ?></small>
                    <div class="dish-sub-rating">
                      <span class="rating-badge">★ <?= number_format((float)$dish['rating'], 1) ?></span>
                    </div>
                  </div>
                </td>

                <td>
                  <span class="cat-pill">
                    <span class="cat-pill-icon"><?= htmlspecialchars($dish['category_icon']) ?></span>
                    <span><?= htmlspecialchars($dish['category_name']) ?></span>
                  </span>
                </td>

                <td>
                  <span class="station-badge station-<?= htmlspecialchars($dish['station']) ?>">
                    <?= getStationName($dish['station']) ?>
                  </span>
                </td>

                <td>
                  <span class="dish-price-badge"><?= formatPrice($dish['price']) ?></span>
                </td>

                <td>
                  <?php if (!empty($dish['tag'])): ?>
                    <span class="dish-tag-chip"><?= htmlspecialchars($dish['tag']) ?></span>
                  <?php else: ?>
                    <span style="color: var(--muted); font-size: 11px;">—</span>
                  <?php endif; ?>
                </td>

                <td>
                  <?= getDishStatusBadgeHtml($dish['status']) ?>
                </td>

                <td style="text-align: right;">
                  <div class="action-buttons-group">
                    <!-- Nút Sửa -->
                    <button type="button" 
                            class="btn-icon btn-edit-dish" 
                            title="Chỉnh sửa món ăn"
                            data-id="<?= $dish['id'] ?>"
                            data-name="<?= htmlspecialchars($dish['name']) ?>"
                            data-category-id="<?= $dish['category_id'] ?>"
                            data-price="<?= (float)$dish['price'] ?>"
                            data-image="<?= htmlspecialchars($dish['image'] ?? '') ?>"
                            data-description="<?= htmlspecialchars($dish['description'] ?? '') ?>"
                            data-tag="<?= htmlspecialchars($dish['tag'] ?? '') ?>"
                            data-station="<?= htmlspecialchars($dish['station']) ?>"
                            data-status="<?= htmlspecialchars($dish['status']) ?>"
                            data-rating="<?= (float)$dish['rating'] ?>">
                      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"></path></svg>
                    </button>

                    <!-- Nút Bật / Tắt tạm hết -->
                    <form method="POST" action="index.php?page=dishes" style="display:inline;" onsubmit="return confirm('Chuyển trạng thái món <?= htmlspecialchars($dish['name']) ?> sang <?= $isOut ? 'ĐANG MỞ BÁN' : 'TẠM HẾT' ?>?')">
                      <input type="hidden" name="action" value="toggle_dish_status">
                      <input type="hidden" name="id" value="<?= $dish['id'] ?>">
                      <button type="submit" 
                              class="btn-icon <?= $isOut ? 'btn-toggle-unlock' : 'btn-toggle-lock' ?>" 
                              title="<?= $isOut ? 'Kích hoạt mở bán lại' : 'Chuyển sang tạm hết' ?>">
                        <?php if ($isOut): ?>
                          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>
                        <?php else: ?>
                          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"></line></svg>
                        <?php endif; ?>
                      </button>
                    </form>

                    <!-- Nút Xóa -->
                    <button type="button" 
                            class="btn-icon btn-delete-dish" 
                            title="Xóa món ăn"
                            data-id="<?= $dish['id'] ?>"
                            data-name="<?= htmlspecialchars($dish['name']) ?>"
                            data-image="<?= htmlspecialchars($imgUrl) ?>">
                      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                    </button>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

<?php else: ?>
  <!-- ==========================================================
       TAB 2: QUẢN LÝ DANH MỤC MÓN
       ========================================================== -->
  <div class="panel users-panel">
    <div class="panel-title users-panel-header">
      <div class="grow">
        <h2>Danh mục phân loại thực đơn</h2>
        <p class="muted">Danh mục giúp khách hàng và nhân viên dễ dàng lọc món theo nhóm</p>
      </div>
      <button type="button" class="btn btn-primary-tool" data-modal-target="modal-add-category">
        <span>+ Thêm danh mục mới</span>
      </button>
    </div>

    <div style="overflow-x: auto;">
      <table class="users-table">
        <thead>
          <tr>
            <th style="width: 50px;">Icon</th>
            <th>Tên danh mục</th>
            <th>Mã định danh (Slug)</th>
            <th>Mô tả</th>
            <th>Số lượng món</th>
            <th>Thứ tự</th>
            <th>Trạng thái</th>
            <th style="text-align: right; width: 140px;">Thao tác</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($allCategories as $cat): ?>
            <tr class="<?= $cat['status'] === 'inactive' ? 'row-inactive' : '' ?>">
              <td>
                <span class="cat-icon-box"><?= htmlspecialchars($cat['icon']) ?></span>
              </td>
              <td>
                <b style="font-size: 14px; color: var(--ink);"><?= htmlspecialchars($cat['name']) ?></b>
              </td>
              <td>
                <code style="background: #f1f5f9; padding: 2px 6px; border-radius: 4px; font-size: 12px;"><?= htmlspecialchars($cat['slug']) ?></code>
              </td>
              <td>
                <small class="muted"><?= htmlspecialchars($cat['description'] ?: '—') ?></small>
              </td>
              <td>
                <a href="index.php?page=dishes&tab=dishes&category=<?= $cat['id'] ?>" class="badge-pill" title="Xem các món thuộc danh mục này">
                  <b><?= $cat['dish_count'] ?></b> món
                </a>
              </td>
              <td>
                <span>#<?= $cat['sort_order'] ?></span>
              </td>
              <td>
                <?= getStatusBadgeHtml($cat['status']) ?>
              </td>
              <td style="text-align: right;">
                <div class="action-buttons-group">
                  <!-- Nút sửa danh mục -->
                  <button type="button" 
                          class="btn-icon btn-edit-cat" 
                          title="Sửa danh mục"
                          data-id="<?= $cat['id'] ?>"
                          data-name="<?= htmlspecialchars($cat['name']) ?>"
                          data-slug="<?= htmlspecialchars($cat['slug']) ?>"
                          data-icon="<?= htmlspecialchars($cat['icon']) ?>"
                          data-description="<?= htmlspecialchars($cat['description'] ?? '') ?>"
                          data-sort-order="<?= $cat['sort_order'] ?>"
                          data-status="<?= htmlspecialchars($cat['status']) ?>">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"></path></svg>
                  </button>

                  <!-- Nút xóa danh mục -->
                  <button type="button" 
                          class="btn-icon btn-delete-cat" 
                          title="Xóa danh mục"
                          data-id="<?= $cat['id'] ?>"
                          data-name="<?= htmlspecialchars($cat['name']) ?>"
                          data-count="<?= $cat['dish_count'] ?>">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                  </button>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>

<!-- ==========================================================
     MODAL 1: THÊM MÓN ĂN MỚI
     ========================================================== -->
<div class="custom-modal-backdrop" id="modal-add-dish" style="display: none;">
  <div class="custom-modal-dialog">
    <div class="custom-modal-header">
      <div class="modal-title-wrap">
        <div class="modal-icon-badge" style="background: var(--brand-orange-light); color: var(--brand-orange);">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8h1a4 4 0 0 1 0 8h-1"></path><path d="M2 8h16v9a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V8z"></path><line x1="6" y1="1" x2="6" y2="4"></line><line x1="10" y1="1" x2="10" y2="4"></line><line x1="14" y1="1" x2="14" y2="4"></line></svg>
        </div>
        <div>
          <h3>Thêm món ăn mới vào thực đơn</h3>
          <p>Thiết lập tên món, danh mục, giá bán và hình ảnh hiển thị</p>
        </div>
      </div>
      <button type="button" class="btn-close-modal" data-modal-close="modal-add-dish">&times;</button>
    </div>

    <form method="POST" action="index.php?page=dishes" class="modal-form" enctype="multipart/form-data">
      <input type="hidden" name="action" value="add_dish">

      <div class="form-grid-2">
        <div class="form-group">
          <label>Tên món ăn <span class="required">*</span></label>
          <input type="text" name="name" placeholder="Ví dụ: Cơm sườn nướng mật ong" required autocomplete="off">
        </div>

        <div class="form-group">
          <label>Danh mục phân loại <span class="required">*</span></label>
          <select name="category_id" required>
            <option value="">-- Chọn danh mục --</option>
            <?php foreach ($allCategories as $cat): ?>
              <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['icon']) ?> <?= htmlspecialchars($cat['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="form-grid-2">
        <div class="form-group">
          <label>Giá bán (VNĐ) <span class="required">*</span></label>
          <div style="position: relative;">
            <input type="number" name="price" placeholder="Ví dụ: 75000" min="0" step="1000" required id="add-dish-price">
            <span style="position: absolute; right: 12px; top: 10px; color: var(--muted); font-size: 13px; pointer-events: none;">VNĐ</span>
          </div>
          <small class="muted" id="add-price-preview">Tương đương: 0đ</small>
        </div>

        <div class="form-group">
          <label>Trạm chế biến KDS <span class="required">*</span></label>
          <select name="station" required>
            <option value="hot">Bếp nóng (Xào / Nướng / Cơm niêu)</option>
            <option value="soup">Bếp súp / Nước lèo / Lẩu</option>
            <option value="cold">Bếp lạnh / Salad / Gỏi</option>
            <option value="bar">Quầy Bar / Nước pha chế</option>
          </select>
        </div>
      </div>

      <!-- KHỐI HÌNH ẢNH MÓN ĂN -->
      <div class="image-upload-section">
        <label style="font-weight: 700; margin-bottom: 8px; display: block;">Hình ảnh món ăn</label>
        
        <div class="image-upload-grid">
          <div class="image-preview-box" id="add-image-preview-box">
            <img src="assets/images/dish-placeholder.svg" id="add-preview-img" alt="Xem trước ảnh">
            <span class="preview-caption">Xem trước ảnh món</span>
          </div>

          <div style="flex: 1; display: flex; flex-direction: column; gap: 10px;">
            <div class="form-group" style="margin-bottom: 0;">
              <label style="font-size: 12px;">Cách 1: Tải ảnh từ máy tính (Khuyên dùng)</label>
              <input type="file" name="image_file" id="add-image-file" accept="image/*">
            </div>

            <div class="form-group" style="margin-bottom: 0;">
              <label style="font-size: 12px;">Cách 2: Hoặc dán đường dẫn ảnh mạng (URL)</label>
              <input type="url" name="image_url" id="add-image-url" placeholder="https://images.unsplash.com/...">
            </div>
          </div>
        </div>
      </div>

      <div class="form-grid-2" style="margin-top: 14px;">
        <div class="form-group">
          <label>Nhãn nổi bật (Badge)</label>
          <input type="text" name="tag" placeholder="Ví dụ: Bán chạy nhất, Healthy, Best Seller">
        </div>

        <div class="form-group">
          <label>Trạng thái kinh doanh</label>
          <select name="status">
            <option value="active" selected>Đang mở bán (Hiển thị gọi món)</option>
            <option value="out_of_stock">Tạm hết (Khóa thêm món)</option>
            <option value="hidden">Đang ẩn (Tạm dừng bán)</option>
          </select>
        </div>
      </div>

      <div class="form-group">
        <label>Mô tả món ăn</label>
        <textarea name="description" rows="3" placeholder="Mô tả nguyên liệu, hương vị hấp dẫn giúp thực khách dễ lựa chọn..."></textarea>
      </div>

      <div class="modal-actions-footer">
        <button type="button" class="btn btn-outline" data-modal-close="modal-add-dish">Hủy bỏ</button>
        <button type="submit" class="btn btn-primary-tool">
          <span>Lưu món ăn mới</span>
        </button>
      </div>
    </form>
  </div>
</div>

<!-- ==========================================================
     MODAL 2: CHỈNH SỬA MÓN ĂN
     ========================================================== -->
<div class="custom-modal-backdrop" id="modal-edit-dish" style="display: none;">
  <div class="custom-modal-dialog">
    <div class="custom-modal-header">
      <div class="modal-title-wrap">
        <div class="modal-icon-badge" style="background: var(--blue-light); color: var(--blue);">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
        </div>
        <div>
          <h3>Chỉnh sửa món ăn & Giá bán</h3>
          <p>Cập nhật giá, ảnh món, danh mục hoặc trạng thái kinh doanh</p>
        </div>
      </div>
      <button type="button" class="btn-close-modal" data-modal-close="modal-edit-dish">&times;</button>
    </div>

    <form method="POST" action="index.php?page=dishes" class="modal-form" enctype="multipart/form-data">
      <input type="hidden" name="action" value="edit_dish">
      <input type="hidden" name="id" id="edit-dish-id" value="">
      <input type="hidden" name="current_image" id="edit-current-image" value="">

      <div class="form-grid-2">
        <div class="form-group">
          <label>Tên món ăn <span class="required">*</span></label>
          <input type="text" name="name" id="edit-dish-name" required autocomplete="off">
        </div>

        <div class="form-group">
          <label>Danh mục phân loại <span class="required">*</span></label>
          <select name="category_id" id="edit-dish-category" required>
            <?php foreach ($allCategories as $cat): ?>
              <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['icon']) ?> <?= htmlspecialchars($cat['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="form-grid-2">
        <div class="form-group">
          <label>Giá bán (VNĐ) <span class="required">*</span></label>
          <div style="position: relative;">
            <input type="number" name="price" id="edit-dish-price" min="0" step="1000" required>
            <span style="position: absolute; right: 12px; top: 10px; color: var(--muted); font-size: 13px; pointer-events: none;">VNĐ</span>
          </div>
          <small class="muted" id="edit-price-preview">Tương đương: 0đ</small>
        </div>

        <div class="form-group">
          <label>Trạm chế biến KDS <span class="required">*</span></label>
          <select name="station" id="edit-dish-station" required>
            <option value="hot">Bếp nóng (Xào / Nướng / Cơm niêu)</option>
            <option value="soup">Bếp súp / Nước lèo / Lẩu</option>
            <option value="cold">Bếp lạnh / Salad / Gỏi</option>
            <option value="bar">Quầy Bar / Nước pha chế</option>
          </select>
        </div>
      </div>

      <!-- HÌNH ẢNH MÓN ĂN -->
      <div class="image-upload-section">
        <label style="font-weight: 700; margin-bottom: 8px; display: block;">Hình ảnh món ăn</label>
        
        <div class="image-upload-grid">
          <div class="image-preview-box" id="edit-image-preview-box">
            <img src="assets/images/dish-placeholder.svg" id="edit-preview-img" alt="Xem trước ảnh">
            <span class="preview-caption">Ảnh hiện tại</span>
          </div>

          <div style="flex: 1; display: flex; flex-direction: column; gap: 10px;">
            <div class="form-group" style="margin-bottom: 0;">
              <label style="font-size: 12px;">Tải ảnh mới từ máy tính (Sẽ ghi đè ảnh cũ)</label>
              <input type="file" name="image_file" id="edit-image-file" accept="image/*">
            </div>

            <div class="form-group" style="margin-bottom: 0;">
              <label style="font-size: 12px;">Hoặc cập nhật đường dẫn ảnh (URL)</label>
              <input type="url" name="image_url" id="edit-image-url" placeholder="https://...">
            </div>
          </div>
        </div>
      </div>

      <div class="form-grid-2" style="margin-top: 14px;">
        <div class="form-group">
          <label>Nhãn nổi bật (Badge)</label>
          <input type="text" name="tag" id="edit-dish-tag">
        </div>

        <div class="form-group">
          <label>Trạng thái kinh doanh</label>
          <select name="status" id="edit-dish-status">
            <option value="active">Đang mở bán</option>
            <option value="out_of_stock">Tạm hết món</option>
            <option value="hidden">Đang ẩn</option>
          </select>
        </div>
      </div>

      <div class="form-group">
        <label>Mô tả món ăn</label>
        <textarea name="description" id="edit-dish-description" rows="3"></textarea>
      </div>

      <div class="modal-actions-footer">
        <button type="button" class="btn btn-outline" data-modal-close="modal-edit-dish">Hủy bỏ</button>
        <button type="submit" class="btn btn-primary-tool">
          <span>Lưu thay đổi</span>
        </button>
      </div>
    </form>
  </div>
</div>

<!-- ==========================================================
     MODAL 3: XÁC NHẬN XÓA MÓN ĂN
     ========================================================== -->
<div class="custom-modal-backdrop" id="modal-delete-dish" style="display: none;">
  <div class="custom-modal-dialog custom-modal-sm">
    <div class="custom-modal-header">
      <div class="modal-title-wrap">
        <div class="modal-icon-badge" style="background: var(--red-light); color: var(--red);">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
        </div>
        <div>
          <h3>Xóa món ăn</h3>
          <p>Hành động này sẽ xóa vĩnh viễn món ăn</p>
        </div>
      </div>
      <button type="button" class="btn-close-modal" data-modal-close="modal-delete-dish">&times;</button>
    </div>

    <form method="POST" action="index.php?page=dishes" class="modal-form">
      <input type="hidden" name="action" value="delete_dish">
      <input type="hidden" name="id" id="delete-dish-id" value="">

      <div style="display: flex; gap: 14px; align-items: center; margin-bottom: 18px;">
        <img src="" id="delete-dish-img" style="width: 50px; height: 50px; border-radius: 8px; object-fit: cover;">
        <div>
          <p style="margin: 0; font-size: 14px;">Bạn có chắc chắn muốn xóa món: <strong id="delete-dish-name">...</strong>?</p>
          <small class="muted">Món ăn này sẽ không còn hiển thị trên thực đơn khách hàng.</small>
        </div>
      </div>

      <div class="modal-actions-footer">
        <button type="button" class="btn btn-outline" data-modal-close="modal-delete-dish">Hủy</button>
        <button type="submit" class="btn btn-danger">
          <span>Xóa vĩnh viễn</span>
        </button>
      </div>
    </form>
  </div>
</div>

<!-- ==========================================================
     MODAL 4: THÊM DANH MỤC MỚI
     ========================================================== -->
<div class="custom-modal-backdrop" id="modal-add-category" style="display: none;">
  <div class="custom-modal-dialog custom-modal-sm">
    <div class="custom-modal-header">
      <div class="modal-title-wrap">
        <div class="modal-icon-badge" style="background: var(--brand-orange-light); color: var(--brand-orange);">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
        </div>
        <div>
          <h3>Thêm danh mục món ăn</h3>
          <p>Phân loại thực đơn nhà hàng</p>
        </div>
      </div>
      <button type="button" class="btn-close-modal" data-modal-close="modal-add-category">&times;</button>
    </div>

    <form method="POST" action="index.php?page=dishes&tab=categories" class="modal-form">
      <input type="hidden" name="action" value="add_category">

      <div class="form-group">
        <label>Tên danh mục <span class="required">*</span></label>
        <input type="text" name="name" placeholder="Ví dụ: Món nướng BBQ, Cơm trưa..." required autocomplete="off">
      </div>

      <div class="form-grid-2">
        <div class="form-group">
          <label>Biểu tượng (Icon)</label>
          <input type="text" name="icon" value="✦" placeholder="✦, ♨, ❋, ◉, ✧, 🥗, 🍹">
        </div>

        <div class="form-group">
          <label>Thứ tự sắp xếp</label>
          <input type="number" name="sort_order" value="10" min="0">
        </div>
      </div>

      <div class="form-group">
        <label>Mã đường dẫn (Slug - Tùy chọn)</label>
        <input type="text" name="slug" placeholder="Để trống hệ thống sẽ tự sinh từ tên">
      </div>

      <div class="form-group">
        <label>Mô tả danh mục</label>
        <textarea name="description" rows="2" placeholder="Mô tả ngắn gọn về nhóm món này..."></textarea>
      </div>

      <div class="modal-actions-footer">
        <button type="button" class="btn btn-outline" data-modal-close="modal-add-category">Hủy</button>
        <button type="submit" class="btn btn-primary-tool">
          <span>Tạo danh mục</span>
        </button>
      </div>
    </form>
  </div>
</div>

<!-- ==========================================================
     MODAL 5: CHỈNH SỬA DANH MỤC
     ========================================================== -->
<div class="custom-modal-backdrop" id="modal-edit-category" style="display: none;">
  <div class="custom-modal-dialog custom-modal-sm">
    <div class="custom-modal-header">
      <div class="modal-title-wrap">
        <div class="modal-icon-badge" style="background: var(--blue-light); color: var(--blue);">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
        </div>
        <div>
          <h3>Chỉnh sửa danh mục</h3>
          <p>Cập nhật tên, biểu tượng hoặc thứ tự hiển thị</p>
        </div>
      </div>
      <button type="button" class="btn-close-modal" data-modal-close="modal-edit-category">&times;</button>
    </div>

    <form method="POST" action="index.php?page=dishes&tab=categories" class="modal-form">
      <input type="hidden" name="action" value="edit_category">
      <input type="hidden" name="id" id="edit-cat-id" value="">

      <div class="form-group">
        <label>Tên danh mục <span class="required">*</span></label>
        <input type="text" name="name" id="edit-cat-name" required>
      </div>

      <div class="form-grid-2">
        <div class="form-group">
          <label>Biểu tượng (Icon)</label>
          <input type="text" name="icon" id="edit-cat-icon">
        </div>

        <div class="form-group">
          <label>Thứ tự sắp xếp</label>
          <input type="number" name="sort_order" id="edit-cat-sort" min="0">
        </div>
      </div>

      <div class="form-grid-2">
        <div class="form-group">
          <label>Mã đường dẫn (Slug)</label>
          <input type="text" name="slug" id="edit-cat-slug" required>
        </div>

        <div class="form-group">
          <label>Trạng thái</label>
          <select name="status" id="edit-cat-status">
            <option value="active">Đang hiển thị</option>
            <option value="inactive">Đang ẩn</option>
          </select>
        </div>
      </div>

      <div class="form-group">
        <label>Mô tả danh mục</label>
        <textarea name="description" id="edit-cat-desc" rows="2"></textarea>
      </div>

      <div class="modal-actions-footer">
        <button type="button" class="btn btn-outline" data-modal-close="modal-edit-category">Hủy</button>
        <button type="submit" class="btn btn-primary-tool">
          <span>Lưu thay đổi</span>
        </button>
      </div>
    </form>
  </div>
</div>

<!-- ==========================================================
     MODAL 6: XÁC NHẬN XÓA DANH MỤC
     ========================================================== -->
<div class="custom-modal-backdrop" id="modal-delete-category" style="display: none;">
  <div class="custom-modal-dialog custom-modal-sm">
    <div class="custom-modal-header">
      <div class="modal-title-wrap">
        <div class="modal-icon-badge" style="background: var(--red-light); color: var(--red);">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
        </div>
        <div>
          <h3>Xác nhận xóa danh mục</h3>
          <p>Hành động này không thể hoàn tác</p>
        </div>
      </div>
      <button type="button" class="btn-close-modal" data-modal-close="modal-delete-category">&times;</button>
    </div>

    <form method="POST" action="index.php?page=dishes&tab=categories" class="modal-form">
      <input type="hidden" name="action" value="delete_category">
      <input type="hidden" name="id" id="delete-cat-id" value="">

      <p style="font-size: 14px; color: var(--ink); margin-bottom: 14px;">
        Bạn có chắc muốn xóa danh mục <strong id="delete-cat-name">...</strong>?
      </p>

      <div id="delete-cat-warning" style="display: none; background: #fef2f2; border: 1px solid #fecaca; color: #b91c1c; padding: 10px 12px; border-radius: 8px; font-size: 13px; margin-bottom: 14px;">
        ⚠ Danh mục này hiện đang có <b id="delete-cat-count">0</b> món ăn. Bạn cần xóa hoặc chuyển các món ăn này trước khi xóa danh mục!
      </div>

      <div class="modal-actions-footer">
        <button type="button" class="btn btn-outline" data-modal-close="modal-delete-category">Hủy</button>
        <button type="submit" class="btn btn-danger" id="btn-confirm-delete-cat">
          <span>Xóa danh mục</span>
        </button>
      </div>
    </form>
  </div>
</div>

<!-- ==========================================================
     JAVASCRIPT ĐIỀU KHIỂN TƯƠNG TÁC, MODAL & LIVE PREVIEW
     ========================================================== -->
<script>
document.addEventListener('DOMContentLoaded', function() {
  const formatMoney = n => new Intl.NumberFormat('vi-VN').format(n) + 'đ';

  // Format tiền tệ trực tiếp khi gõ
  const addPriceInput = document.getElementById('add-dish-price');
  const addPricePreview = document.getElementById('add-price-preview');
  if (addPriceInput && addPricePreview) {
    addPriceInput.addEventListener('input', function() {
      addPricePreview.textContent = 'Tương đương: ' + formatMoney(this.value || 0);
    });
  }

  const editPriceInput = document.getElementById('edit-dish-price');
  const editPricePreview = document.getElementById('edit-price-preview');
  if (editPriceInput && editPricePreview) {
    editPriceInput.addEventListener('input', function() {
      editPricePreview.textContent = 'Tương đương: ' + formatMoney(this.value || 0);
    });
  }

  // Xem trước ảnh khi chọn file hoặc nhập URL (Modal Thêm)
  const addFile = document.getElementById('add-image-file');
  const addUrl = document.getElementById('add-image-url');
  const addImg = document.getElementById('add-preview-img');

  if (addFile && addImg) {
    addFile.addEventListener('change', function() {
      if (this.files && this.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
          addImg.src = e.target.result;
        };
        reader.readAsDataURL(this.files[0]);
        if (addUrl) addUrl.value = '';
      }
    });
  }

  if (addUrl && addImg) {
    addUrl.addEventListener('input', function() {
      if (this.value.trim()) {
        addImg.src = this.value.trim();
        if (addFile) addFile.value = '';
      }
    });
  }

  // Xem trước ảnh khi chọn file hoặc nhập URL (Modal Sửa)
  const editFile = document.getElementById('edit-image-file');
  const editUrl = document.getElementById('edit-image-url');
  const editImg = document.getElementById('edit-preview-img');

  if (editFile && editImg) {
    editFile.addEventListener('change', function() {
      if (this.files && this.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
          editImg.src = e.target.result;
        };
        reader.readAsDataURL(this.files[0]);
        if (editUrl) editUrl.value = '';
      }
    });
  }

  if (editUrl && editImg) {
    editUrl.addEventListener('input', function() {
      if (this.value.trim()) {
        editImg.src = this.value.trim();
        if (editFile) editFile.value = '';
      }
    });
  }

  // Xử lý mở Modal Sửa món ăn
  document.querySelectorAll('.btn-edit-dish').forEach(btn => {
    btn.addEventListener('click', function() {
      const id = this.getAttribute('data-id');
      const name = this.getAttribute('data-name');
      const catId = this.getAttribute('data-category-id');
      const price = this.getAttribute('data-price');
      const image = this.getAttribute('data-image');
      const desc = this.getAttribute('data-description');
      const tag = this.getAttribute('data-tag');
      const station = this.getAttribute('data-station');
      const status = this.getAttribute('data-status');

      document.getElementById('edit-dish-id').value = id;
      document.getElementById('edit-dish-name').value = name;
      document.getElementById('edit-dish-category').value = catId;
      document.getElementById('edit-dish-price').value = price;
      document.getElementById('edit-current-image').value = image;
      document.getElementById('edit-dish-description').value = desc;
      document.getElementById('edit-dish-tag').value = tag;
      document.getElementById('edit-dish-station').value = station;
      document.getElementById('edit-dish-status').value = status;

      if (editPricePreview) editPricePreview.textContent = 'Tương đương: ' + formatMoney(price);

      if (editImg) {
        editImg.src = image ? image : 'assets/images/dish-placeholder.svg';
      }
      if (editUrl) editUrl.value = (image.startsWith('http://') || image.startsWith('https://')) ? image : '';
      if (editFile) editFile.value = '';

      const modal = document.getElementById('modal-edit-dish');
      if (modal) modal.style.display = 'flex';
    });
  });

  // Xử lý mở Modal Xóa món ăn
  document.querySelectorAll('.btn-delete-dish').forEach(btn => {
    btn.addEventListener('click', function() {
      const id = this.getAttribute('data-id');
      const name = this.getAttribute('data-name');
      const img = this.getAttribute('data-image');

      document.getElementById('delete-dish-id').value = id;
      document.getElementById('delete-dish-name').textContent = name;
      const deleteImg = document.getElementById('delete-dish-img');
      if (deleteImg) deleteImg.src = img;

      const modal = document.getElementById('modal-delete-dish');
      if (modal) modal.style.display = 'flex';
    });
  });

  // Xử lý mở Modal Sửa danh mục
  document.querySelectorAll('.btn-edit-cat').forEach(btn => {
    btn.addEventListener('click', function() {
      const id = this.getAttribute('data-id');
      const name = this.getAttribute('data-name');
      const slug = this.getAttribute('data-slug');
      const icon = this.getAttribute('data-icon');
      const desc = this.getAttribute('data-description');
      const sort = this.getAttribute('data-sort-order');
      const status = this.getAttribute('data-status');

      document.getElementById('edit-cat-id').value = id;
      document.getElementById('edit-cat-name').value = name;
      document.getElementById('edit-cat-slug').value = slug;
      document.getElementById('edit-cat-icon').value = icon;
      document.getElementById('edit-cat-desc').value = desc;
      document.getElementById('edit-cat-sort').value = sort;
      document.getElementById('edit-cat-status').value = status;

      const modal = document.getElementById('modal-edit-category');
      if (modal) modal.style.display = 'flex';
    });
  });

  // Xử lý mở Modal Xóa danh mục
  document.querySelectorAll('.btn-delete-cat').forEach(btn => {
    btn.addEventListener('click', function() {
      const id = this.getAttribute('data-id');
      const name = this.getAttribute('data-name');
      const count = parseInt(this.getAttribute('data-count') || '0', 10);

      document.getElementById('delete-cat-id').value = id;
      document.getElementById('delete-cat-name').textContent = name;

      const warning = document.getElementById('delete-cat-warning');
      const countSpan = document.getElementById('delete-cat-count');
      const confirmBtn = document.getElementById('btn-confirm-delete-cat');

      if (count > 0) {
        if (warning) warning.style.display = 'block';
        if (countSpan) countSpan.textContent = count;
        if (confirmBtn) {
          confirmBtn.disabled = true;
          confirmBtn.style.opacity = '0.5';
          confirmBtn.style.cursor = 'not-allowed';
        }
      } else {
        if (warning) warning.style.display = 'none';
        if (confirmBtn) {
          confirmBtn.disabled = false;
          confirmBtn.style.opacity = '1';
          confirmBtn.style.cursor = 'pointer';
        }
      }

      const modal = document.getElementById('modal-delete-category');
      if (modal) modal.style.display = 'flex';
    });
  });

  // Mở / Đóng modal chung
  document.querySelectorAll('[data-modal-target]').forEach(btn => {
    btn.addEventListener('click', function() {
      const id = this.getAttribute('data-modal-target');
      const m = document.getElementById(id);
      if (m) m.style.display = 'flex';
    });
  });

  document.querySelectorAll('[data-modal-close]').forEach(btn => {
    btn.addEventListener('click', function() {
      const id = this.getAttribute('data-modal-close');
      const m = document.getElementById(id);
      if (m) m.style.display = 'none';
    });
  });

  document.querySelectorAll('.custom-modal-backdrop').forEach(modal => {
    modal.addEventListener('click', function(e) {
      if (e.target === this) {
        this.style.display = 'none';
      }
    });
  });
});
</script>


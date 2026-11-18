<?php
/**
 * SmartDine - Quản lý Khuyến mãi & Mã giảm giá (Vouchers / Coupons).
 * Dành riêng cho Quản trị viên (Admin).
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/vouchers.php';

// Kiểm tra quyền Admin
if (!isAdmin()) {
    echo "<div class='flash-alert flash-error'>Bạn không có quyền truy cập trang quản lý khuyến mãi!</div>";
    return;
}

$msgSuccess = '';
$msgError = '';

// 1. Thêm voucher mới
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_voucher') {
    $code              = strtoupper(trim($_POST['code'] ?? ''));
    $name              = trim($_POST['name'] ?? '');
    $discountType      = trim($_POST['discount_type'] ?? 'percent');
    $discountValue     = (int) ($_POST['discount_value'] ?? 0);
    $minOrderValue     = (int) ($_POST['min_order_value'] ?? 0);
    $maxDiscountAmount = (int) ($_POST['max_discount_amount'] ?? 0);
    $usageLimit        = (int) ($_POST['usage_limit'] ?? 0);
    $startDate         = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
    $endDate           = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
    $status            = trim($_POST['status'] ?? 'active');

    $res = createVoucher([
        'code'                => $code,
        'name'                => $name,
        'discount_type'       => $discountType,
        'discount_value'      => $discountValue,
        'min_order_value'     => $minOrderValue,
        'max_discount_amount' => $maxDiscountAmount,
        'usage_limit'         => $usageLimit,
        'start_date'          => $startDate,
        'end_date'            => $endDate,
        'status'              => $status,
    ]);

    if ($res['success']) {
        $_SESSION['flash_success'] = 'Đã tạo mã khuyến mãi "' . htmlspecialchars($code) . '" thành công!';
        header('Location: index.php?page=vouchers');
        exit;
    } else {
        $msgError = $res['message'];
    }
}

// 2. Sửa voucher
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_voucher') {
    $voucherId         = (int) ($_POST['voucher_id'] ?? 0);
    $code              = strtoupper(trim($_POST['code'] ?? ''));
    $name              = trim($_POST['name'] ?? '');
    $discountType      = trim($_POST['discount_type'] ?? 'percent');
    $discountValue     = (int) ($_POST['discount_value'] ?? 0);
    $minOrderValue     = (int) ($_POST['min_order_value'] ?? 0);
    $maxDiscountAmount = (int) ($_POST['max_discount_amount'] ?? 0);
    $usageLimit        = (int) ($_POST['usage_limit'] ?? 0);
    $startDate         = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
    $endDate           = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
    $status            = trim($_POST['status'] ?? 'active');

    $res = updateVoucher($voucherId, [
        'code'                => $code,
        'name'                => $name,
        'discount_type'       => $discountType,
        'discount_value'      => $discountValue,
        'min_order_value'     => $minOrderValue,
        'max_discount_amount' => $maxDiscountAmount,
        'usage_limit'         => $usageLimit,
        'start_date'          => $startDate,
        'end_date'            => $endDate,
        'status'              => $status,
    ]);

    if ($res['success']) {
        $_SESSION['flash_success'] = 'Đã cập nhật mã khuyến mãi "' . htmlspecialchars($code) . '" thành công!';
        header('Location: index.php?page=vouchers');
        exit;
    } else {
        $msgError = $res['message'];
    }
}

// 3. Bật / Tắt trạng thái nhanh
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_status') {
    $voucherId = (int) ($_POST['voucher_id'] ?? 0);
    $voucher = getVoucherById($voucherId);
    if ($voucher) {
        $newStatus = $voucher['status'] === 'active' ? 'inactive' : 'active';
        $res = updateVoucher($voucherId, ['status' => $newStatus]);
        if ($res['success']) {
            $_SESSION['flash_success'] = 'Đã ' . ($newStatus === 'active' ? 'kích hoạt' : 'tạm dừng') . ' mã "' . $voucher['code'] . '"!';
            header('Location: index.php?page=vouchers');
            exit;
        } else {
            $msgError = $res['message'];
        }
    }
}

// 4. Xóa voucher
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_voucher') {
    $voucherId = (int) ($_POST['voucher_id'] ?? 0);
    $res = deleteVoucher($voucherId);
    if ($res['success']) {
        $_SESSION['flash_success'] = 'Đã xóa mã voucher thành công!';
        header('Location: index.php?page=vouchers');
        exit;
    } else {
        $msgError = $res['message'];
    }
}

// Lọc & Tìm kiếm
$filterStatus = trim($_GET['status'] ?? '');
$search       = trim($_GET['q'] ?? '');

$allVouchers = getAllVouchers($search, $filterStatus);
$totalCount = count($allVouchers);
$perPage = 10;
$totalPages = max(1, (int) ceil($totalCount / $perPage));
$currentPage = max(1, min($totalPages, (int) ($_GET['p'] ?? 1)));
$offset = ($currentPage - 1) * $perPage;
$vouchers = array_slice($allVouchers, $offset, $perPage);

$stats = getVoucherStats();
?>

<div class="dash-header">
  <div>
    <span class="eyebrow">QUẢN TRỊ KHUYẾN MÃI & CHIẾT KHẤU</span>
    <h1>Quản lý Mã giảm giá (Vouchers / Coupons)</h1>
    <p>Thiết lập mã ưu đãi theo tỷ lệ % hoặc số tiền cố định, giới hạn lượt dùng và thời hạn hiệu lực cho thực khách và quầy thu ngân.</p>
  </div>
  <div class="filter-row">
    <button class="btn btn-primary-tool" id="btn-open-add-voucher">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
      <span>Tạo mã voucher mới</span>
    </button>
  </div>
</div>

<?php if (isset($_SESSION['flash_success'])): ?>
  <div class="flash-alert flash-success">
    <span><?= htmlspecialchars($_SESSION['flash_success']) ?></span>
  </div>
  <?php unset($_SESSION['flash_success']); ?>
<?php endif; ?>

<?php if (!empty($msgError)): ?>
  <div class="flash-alert flash-error">
    <span><?= htmlspecialchars($msgError) ?></span>
  </div>
<?php endif; ?>

<!-- BẢNG THẺ THỐNG KÊ (KPI CARDS) -->
<div class="stats-overview-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 16px; margin-bottom: 24px;">
  <div class="revenue-stat-card" style="border-top: 4px solid var(--brand-orange);">
    <div class="revenue-stat-head">
      <span>Tổng số mã ưu đãi</span>
      <span class="stat-trend-badge neutral"><?= $stats['total'] ?> mã</span>
    </div>
    <div class="revenue-stat-value"><?= number_format($stats['total']) ?> <span style="font-size: 15px; font-weight: 500; color: #64748b;">chương trình</span></div>
    <div class="revenue-stat-sub">Bao gồm toàn bộ voucher đã tạo</div>
  </div>

  <div class="revenue-stat-card" style="border-top: 4px solid #16a34a;">
    <div class="revenue-stat-head">
      <span>Đang hoạt động</span>
      <span class="stat-trend-badge positive">Khả dụng</span>
    </div>
    <div class="revenue-stat-value" style="color: #16a34a;"><?= number_format($stats['active']) ?> <span style="font-size: 15px; font-weight: 500; color: #64748b;">mã</span></div>
    <div class="revenue-stat-sub">Thực khách và thu ngân có thể áp dụng</div>
  </div>

  <div class="revenue-stat-card" style="border-top: 4px solid #ef4444;">
    <div class="revenue-stat-head">
      <span>Tạm ngưng / Hết hạn</span>
      <span class="stat-trend-badge urgent">Vô hiệu</span>
    </div>
    <div class="revenue-stat-value" style="color: #ef4444;"><?= number_format($stats['inactive']) ?> <span style="font-size: 15px; font-weight: 500; color: #64748b;">mã</span></div>
    <div class="revenue-stat-sub">Mã bị tắt hoặc đã kết thúc thời hạn</div>
  </div>

  <div class="revenue-stat-card" style="border-top: 4px solid #0284c7;">
    <div class="revenue-stat-head">
      <span>Tổng lượt đã sử dụng</span>
      <span class="stat-trend-badge neutral">Lượt dùng</span>
    </div>
    <div class="revenue-stat-value" style="color: #0284c7;"><?= number_format($stats['total_used']) ?> <span style="font-size: 15px; font-weight: 500; color: #64748b;">lần</span></div>
    <div class="revenue-stat-sub">Số lần áp dụng thành công vào hóa đơn</div>
  </div>
</div>

<!-- KHỐI TÌM KIẾM VÀ LỌC -->
<div class="panel" style="padding: 16px 20px; margin-bottom: 20px;">
  <form method="GET" action="index.php" style="display: flex; gap: 12px; flex-wrap: wrap; align-items: center; justify-content: space-between;">
    <input type="hidden" name="page" value="vouchers">

    <div style="display: flex; gap: 10px; flex-wrap: wrap; flex: 1;">
      <div style="flex: 1; min-width: 240px;">
        <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Tìm mã voucher hoặc tên chương trình..." style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px;">
      </div>

      <div style="min-width: 160px;">
        <select name="status" style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px;">
          <option value="">Tất cả trạng thái</option>
          <option value="active" <?= $filterStatus === 'active' ? 'selected' : '' ?>>Đang hoạt động</option>
          <option value="inactive" <?= $filterStatus === 'inactive' ? 'selected' : '' ?>>Tạm ngưng</option>
        </select>
      </div>

      <button type="submit" class="btn btn-outline" style="padding: 8px 16px;">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
        <span>Lọc</span>
      </button>

      <?php if (!empty($search) || !empty($filterStatus)): ?>
        <a href="index.php?page=vouchers" class="btn btn-outline" style="padding: 8px 14px; color: #64748b;" title="Xóa bộ lọc">✕ Bỏ lọc</a>
      <?php endif; ?>
    </div>

    <div style="color: #64748b; font-size: 13px;">
      Tìm thấy <b><?= $totalCount ?></b> mã khuyến mãi
    </div>
  </form>
</div>

<!-- BẢNG DANH SÁCH VOUCHERS -->
<div class="panel" style="padding: 0; overflow: hidden; margin-bottom: 24px;">
  <div style="overflow-x: auto;">
    <table class="data-table" style="width: 100%; border-collapse: collapse; text-align: left; font-size: 13.5px;">
      <thead>
        <tr style="background: #f8fafc; border-bottom: 1px solid #e2e8f0; color: #475569; font-weight: 600;">
          <th style="padding: 12px 16px;">Mã Voucher</th>
          <th style="padding: 12px 16px;">Tên chương trình</th>
          <th style="padding: 12px 16px;">Mức giảm</th>
          <th style="padding: 12px 16px;">Điều kiện đơn</th>
          <th style="padding: 12px 16px;">Thời hạn hiệu lực</th>
          <th style="padding: 12px 16px;">Đã dùng / Giới hạn</th>
          <th style="padding: 12px 16px;">Trạng thái</th>
          <th style="padding: 12px 16px; text-align: right;">Thao tác</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($vouchers)): ?>
          <tr>
            <td colspan="8" style="padding: 40px; text-align: center; color: #94a3b8;">
              <div style="font-size: 32px; margin-bottom: 8px;">🏷️</div>
              <b>Không tìm thấy mã khuyến mãi nào phù hợp</b>
              <p style="margin-top: 4px; font-size: 13px;">Hãy thử điều chỉnh lại bộ lọc hoặc tạo thêm mã khuyến mãi mới.</p>
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($vouchers as $v): ?>
            <?php
              $isExpired = !empty($v['end_date']) && date('Y-m-d') > $v['end_date'];
              $isLimitReached = $v['usage_limit'] > 0 && $v['used_count'] >= $v['usage_limit'];
              $isActive = $v['status'] === 'active' && !$isExpired && !$isLimitReached;
            ?>
            <tr style="border-bottom: 1px solid #f1f5f9;">
              <td style="padding: 12px 16px;">
                <span style="display: inline-block; padding: 4px 10px; background: #fff7ed; border: 1px dashed #f97316; color: #c2410c; border-radius: 6px; font-weight: 800; font-family: monospace; font-size: 13px; letter-spacing: 0.5px;">
                  <?= htmlspecialchars($v['code']) ?>
                </span>
              </td>
              <td style="padding: 12px 16px;">
                <b><?= htmlspecialchars($v['name']) ?></b>
              </td>
              <td style="padding: 12px 16px;">
                <?php if ($v['discount_type'] === 'percent'): ?>
                  <span style="font-weight: 700; color: #0284c7; font-size: 14px;">Giảm <?= $v['discount_value'] ?>%</span>
                  <?php if ($v['max_discount_amount'] > 0): ?>
                    <small style="display: block; color: #64748b; font-size: 11.5px;">(Tối đa <?= number_format($v['max_discount_amount']) ?>đ)</small>
                  <?php endif; ?>
                <?php else: ?>
                  <span style="font-weight: 700; color: #16a34a; font-size: 14px;">Giảm <?= number_format($v['discount_value']) ?>đ</span>
                <?php endif; ?>
              </td>
              <td style="padding: 12px 16px;">
                <?php if ($v['min_order_value'] > 0): ?>
                  <span>Đơn từ <b><?= number_format($v['min_order_value']) ?>đ</b></span>
                <?php else: ?>
                  <span style="color: #64748b;">Không yêu cầu tối thiểu</span>
                <?php endif; ?>
              </td>
              <td style="padding: 12px 16px;">
                <?php if (empty($v['start_date']) && empty($v['end_date'])): ?>
                  <span style="color: #64748b;">Vô thời hạn</span>
                <?php else: ?>
                  <span style="font-size: 12.5px;">
                    <?= !empty($v['start_date']) ? date('d/m/Y', strtotime($v['start_date'])) : '—' ?>
                    → 
                    <b style="<?= $isExpired ? 'color:#ef4444;' : '' ?>"><?= !empty($v['end_date']) ? date('d/m/Y', strtotime($v['end_date'])) : 'Không hạn' ?></b>
                  </span>
                  <?php if ($isExpired): ?>
                    <span style="display: block; color: #ef4444; font-size: 11px; font-weight: 600;">(Đã hết hạn)</span>
                  <?php endif; ?>
                <?php endif; ?>
              </td>
              <td style="padding: 12px 16px;">
                <b><?= number_format($v['used_count']) ?></b> / <?= $v['usage_limit'] > 0 ? number_format($v['usage_limit']) : '∞' ?>
                <?php if ($isLimitReached): ?>
                  <span style="display: block; color: #ef4444; font-size: 11px; font-weight: 600;">(Hết lượt)</span>
                <?php endif; ?>
              </td>
              <td style="padding: 12px 16px;">
                <?php if ($isActive): ?>
                  <span class="status status-ready" style="font-size: 11px;">Đang hoạt động</span>
                <?php elseif ($isExpired): ?>
                  <span class="status status-cancelled" style="font-size: 11px;">Đã hết hạn</span>
                <?php elseif ($isLimitReached): ?>
                  <span class="status status-cancelled" style="font-size: 11px;">Hết số lượng</span>
                <?php else: ?>
                  <span class="status status-cancelled" style="font-size: 11px;">Đang tạm ngưng</span>
                <?php endif; ?>
              </td>
              <td style="padding: 12px 16px; text-align: right; white-space: nowrap;">
                <button type="button" class="btn btn-small btn-outline btn-edit-voucher"
                        data-id="<?= $v['id'] ?>"
                        data-code="<?= htmlspecialchars($v['code']) ?>"
                        data-name="<?= htmlspecialchars($v['name']) ?>"
                        data-type="<?= htmlspecialchars($v['discount_type']) ?>"
                        data-value="<?= (int) $v['discount_value'] ?>"
                        data-min="<?= (int) $v['min_order_value'] ?>"
                        data-max="<?= (int) $v['max_discount_amount'] ?>"
                        data-limit="<?= (int) $v['usage_limit'] ?>"
                        data-start="<?= htmlspecialchars($v['start_date'] ?? '') ?>"
                        data-end="<?= htmlspecialchars($v['end_date'] ?? '') ?>"
                        data-status="<?= htmlspecialchars($v['status']) ?>"
                        title="Chỉnh sửa voucher">
                  Sửa
                </button>

                <form method="POST" action="index.php?page=vouchers" style="display: inline-block; margin: 0 4px;">
                  <input type="hidden" name="action" value="toggle_status">
                  <input type="hidden" name="voucher_id" value="<?= $v['id'] ?>">
                  <button type="submit" class="btn btn-small btn-outline" style="color: <?= $v['status'] === 'active' ? '#ef4444' : '#16a34a' ?>;" title="<?= $v['status'] === 'active' ? 'Tạm dừng mã' : 'Bật kích hoạt mã' ?>">
                    <?= $v['status'] === 'active' ? 'Tạm dừng' : 'Kích hoạt' ?>
                  </button>
                </form>

                <button type="button" class="btn btn-small btn-outline btn-delete-voucher"
                        style="color: #ef4444;"
                        data-id="<?= $v['id'] ?>"
                        data-code="<?= htmlspecialchars($v['code']) ?>"
                        title="Xóa mã voucher">
                  Xóa
                </button>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- PHÂN TRANG -->
  <?php if ($totalPages > 1): ?>
    <div style="padding: 14px 20px; display: flex; align-items: center; justify-content: space-between; border-top: 1px solid #f1f5f9; background: #fafafa;">
      <span style="font-size: 13px; color: #64748b;">
        Trang <b><?= $currentPage ?></b> / <b><?= $totalPages ?></b> (Tổng <?= $totalCount ?> voucher)
      </span>
      <div style="display: flex; gap: 6px;">
        <?php
          $queryBase = "index.php?page=vouchers" . ($search !== '' ? "&q=" . urlencode($search) : '') . ($filterStatus !== '' ? "&status=" . urlencode($filterStatus) : '');
        ?>
        <?php if ($currentPage > 1): ?>
          <a class="btn btn-small btn-outline" href="<?= $queryBase ?>&p=<?= $currentPage - 1 ?>">← Trang trước</a>
        <?php endif; ?>

        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
          <a class="btn btn-small <?= $p === $currentPage ? 'btn-primary' : 'btn-outline' ?>" href="<?= $queryBase ?>&p=<?= $p ?>">
            <?= $p ?>
          </a>
        <?php endfor; ?>

        <?php if ($currentPage < $totalPages): ?>
          <a class="btn btn-small btn-outline" href="<?= $queryBase ?>&p=<?= $currentPage + 1 ?>">Trang sau →</a>
        <?php endif; ?>
      </div>
    </div>
  <?php endif; ?>
</div>

<!-- ==========================================================
     MODAL 1: TẠO MÃ VOUCHER MỚI
     ========================================================== -->
<div class="custom-modal-backdrop" id="modal-add-voucher" style="display: none;">
  <div class="custom-modal-dialog">
    <div class="custom-modal-header">
      <div class="modal-title-wrap">
        <div class="modal-icon-badge" style="background: var(--brand-orange-light); color: var(--brand-orange);">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path><line x1="7" y1="7" x2="7.01" y2="7"></line></svg>
        </div>
        <div>
          <h3>Tạo mã khuyến mãi / Voucher mới</h3>
          <p>Thiết lập mã coupon, hình thức giảm giá và các điều kiện áp dụng</p>
        </div>
      </div>
      <button type="button" class="btn-close-modal" data-close="modal-add-voucher">✕</button>
    </div>

    <form method="POST" action="index.php?page=vouchers" class="modal-form">
      <input type="hidden" name="action" value="add_voucher">

      <div class="form-grid-2">
        <div class="form-group">
          <label>Mã Voucher (Code) <span class="required">*</span></label>
          <input type="text" name="code" placeholder="VD: SUMMER20, GIAM50K" required style="text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px;">
          <small class="muted">Ký tự viết hoa, không dấu, không khoảng trắng</small>
        </div>

        <div class="form-group">
          <label>Tên chương trình ưu đãi <span class="required">*</span></label>
          <input type="text" name="name" placeholder="VD: Khuyến mãi chào hè 20%" required>
        </div>
      </div>

      <div class="form-grid-2">
        <div class="form-group">
          <label>Loại giảm giá <span class="required">*</span></label>
          <select name="discount_type" id="add-discount-type" required>
            <option value="percent">Giảm theo tỷ lệ %</option>
            <option value="amount">Giảm theo số tiền (đ)</option>
          </select>
        </div>

        <div class="form-group">
          <label id="add-discount-val-label">Giá trị giảm (%) <span class="required">*</span></label>
          <input type="number" name="discount_value" id="add-discount-value" min="1" placeholder="VD: 10, 20 hoặc 50000" required>
        </div>
      </div>

      <div class="form-grid-2">
        <div class="form-group">
          <label>Giá trị đơn hàng tối thiểu (đ)</label>
          <input type="number" name="min_order_value" min="0" step="1000" placeholder="0 (Không giới hạn)">
          <small class="muted">Đơn phải đạt số tiền này mới được áp dụng</small>
        </div>

        <div class="form-group" id="add-max-discount-group">
          <label>Giảm tối đa (đ)</label>
          <input type="number" name="max_discount_amount" min="0" step="1000" placeholder="0 (Không giới hạn)">
          <small class="muted">Áp dụng cho giảm theo % để kiểm soát ngân sách</small>
        </div>
      </div>

      <div class="form-grid-3" style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px;">
        <div class="form-group">
          <label>Ngày bắt đầu</label>
          <input type="date" name="start_date" value="<?= date('Y-m-d') ?>">
        </div>

        <div class="form-group">
          <label>Ngày kết thúc</label>
          <input type="date" name="end_date">
        </div>

        <div class="form-group">
          <label>Giới hạn lượt dùng</label>
          <input type="number" name="usage_limit" min="0" placeholder="0 = Vô hạn">
        </div>
      </div>

      <div class="form-group">
        <label>Trạng thái ban đầu</label>
        <select name="status">
          <option value="active">Kích hoạt ngay (Khách và thu ngân có thể dùng)</option>
          <option value="inactive">Tạm ngưng (Lưu nháp, kích hoạt sau)</option>
        </select>
      </div>

      <div class="modal-actions-footer">
        <button type="button" class="btn btn-outline" data-close="modal-add-voucher">Hủy bỏ</button>
        <button type="submit" class="btn btn-primary-tool">
          <span>Xác nhận tạo mã Voucher</span>
        </button>
      </div>
    </form>
  </div>
</div>

<!-- ==========================================================
     MODAL 2: CHỈNH SỬA VOUCHER
     ========================================================== -->
<div class="custom-modal-backdrop" id="modal-edit-voucher" style="display: none;">
  <div class="custom-modal-dialog">
    <div class="custom-modal-header">
      <div class="modal-title-wrap">
        <div class="modal-icon-badge" style="background: #e0f2fe; color: #0284c7;">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
        </div>
        <div>
          <h3>Chỉnh sửa mã khuyến mãi</h3>
          <p id="edit-voucher-sub">Cập nhật chính sách chiết khấu và thời hạn</p>
        </div>
      </div>
      <button type="button" class="btn-close-modal" data-close="modal-edit-voucher">✕</button>
    </div>

    <form method="POST" action="index.php?page=vouchers" class="modal-form">
      <input type="hidden" name="action" value="edit_voucher">
      <input type="hidden" name="voucher_id" id="edit-voucher-id">

      <div class="form-grid-2">
        <div class="form-group">
          <label>Mã Voucher (Code) <span class="required">*</span></label>
          <input type="text" name="code" id="edit-code" required style="text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px;">
        </div>

        <div class="form-group">
          <label>Tên chương trình ưu đãi <span class="required">*</span></label>
          <input type="text" name="name" id="edit-name" required>
        </div>
      </div>

      <div class="form-grid-2">
        <div class="form-group">
          <label>Loại giảm giá <span class="required">*</span></label>
          <select name="discount_type" id="edit-discount-type" required>
            <option value="percent">Giảm theo tỷ lệ %</option>
            <option value="amount">Giảm theo số tiền (đ)</option>
          </select>
        </div>

        <div class="form-group">
          <label id="edit-discount-val-label">Giá trị giảm <span class="required">*</span></label>
          <input type="number" name="discount_value" id="edit-discount-value" min="1" required>
        </div>
      </div>

      <div class="form-grid-2">
        <div class="form-group">
          <label>Giá trị đơn hàng tối thiểu (đ)</label>
          <input type="number" name="min_order_value" id="edit-min-order" min="0" step="1000">
        </div>

        <div class="form-group" id="edit-max-discount-group">
          <label>Giảm tối đa (đ)</label>
          <input type="number" name="max_discount_amount" id="edit-max-discount" min="0" step="1000">
        </div>
      </div>

      <div class="form-grid-3" style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px;">
        <div class="form-group">
          <label>Ngày bắt đầu</label>
          <input type="date" name="start_date" id="edit-start-date">
        </div>

        <div class="form-group">
          <label>Ngày kết thúc</label>
          <input type="date" name="end_date" id="edit-end-date">
        </div>

        <div class="form-group">
          <label>Giới hạn lượt dùng</label>
          <input type="number" name="usage_limit" id="edit-usage-limit" min="0">
        </div>
      </div>

      <div class="form-group">
        <label>Trạng thái</label>
        <select name="status" id="edit-status">
          <option value="active">Đang hoạt động</option>
          <option value="inactive">Tạm ngưng</option>
        </select>
      </div>

      <div class="modal-actions-footer">
        <button type="button" class="btn btn-outline" data-close="modal-edit-voucher">Hủy bỏ</button>
        <button type="submit" class="btn btn-primary-tool">
          <span>Lưu thay đổi</span>
        </button>
      </div>
    </form>
  </div>
</div>

<!-- ==========================================================
     MODAL 3: XÁC NHẬN XÓA VOUCHER
     ========================================================== -->
<div class="custom-modal-backdrop" id="modal-delete-voucher" style="display: none;">
  <div class="custom-modal-dialog custom-modal-sm">
    <div class="custom-modal-header">
      <div class="modal-title-wrap">
        <div class="modal-icon-badge" style="background: var(--red-light); color: var(--red);">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
        </div>
        <div>
          <h3>Xác nhận xóa voucher</h3>
          <p class="muted">Hành động này không thể hoàn tác</p>
        </div>
      </div>
      <button type="button" class="btn-close-modal" data-close="modal-delete-voucher">✕</button>
    </div>

    <form method="POST" action="index.php?page=vouchers" class="modal-form">
      <input type="hidden" name="action" value="delete_voucher">
      <input type="hidden" name="voucher_id" id="delete-voucher-id">

      <p style="font-size: 14px; margin-bottom: 16px;">
        Bạn có chắc muốn xóa mã voucher <strong id="delete-voucher-code" style="color: #ef4444;">...</strong> khỏi hệ thống?
      </p>

      <div class="modal-actions-footer">
        <button type="button" class="btn btn-outline" data-close="modal-delete-voucher">Hủy</button>
        <button type="submit" class="btn btn-danger">Xác nhận xóa</button>
      </div>
    </form>
  </div>
</div>

<!-- SCRIPT ĐIỀU KHIỂN MODAL VOUCHER -->
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

  // Nút mở modal thêm
  const btnAdd = document.getElementById('btn-open-add-voucher');
  if (btnAdd) {
    btnAdd.addEventListener('click', () => openModal('modal-add-voucher'));
  }

  // Nút đóng modal
  document.querySelectorAll('[data-close]').forEach(btn => {
    btn.addEventListener('click', function() {
      closeModal(this.getAttribute('data-close'));
    });
  });

  // Bấm ngoài backdrop
  document.querySelectorAll('.custom-modal-backdrop').forEach(modal => {
    modal.addEventListener('click', function(e) {
      if (e.target === this) this.style.display = 'none';
    });
  });

  // Toggle nhãn đơn vị theo loại giảm giá
  const setupDiscountTypeToggle = (selectId, labelId, maxGroupId) => {
    const sel = document.getElementById(selectId);
    const lbl = document.getElementById(labelId);
    const maxG = document.getElementById(maxGroupId);
    if (sel && lbl) {
      sel.addEventListener('change', () => {
        if (sel.value === 'percent') {
          lbl.innerHTML = 'Giá trị giảm (%) <span class="required">*</span>';
          if (maxG) maxG.style.display = 'block';
        } else {
          lbl.innerHTML = 'Giá trị giảm (đ) <span class="required">*</span>';
          if (maxG) maxG.style.display = 'none';
        }
      });
    }
  };

  setupDiscountTypeToggle('add-discount-type', 'add-discount-val-label', 'add-max-discount-group');
  setupDiscountTypeToggle('edit-discount-type', 'edit-discount-val-label', 'edit-max-discount-group');

  // Nút Sửa voucher
  document.querySelectorAll('.btn-edit-voucher').forEach(btn => {
    btn.addEventListener('click', function() {
      const id     = this.getAttribute('data-id');
      const code   = this.getAttribute('data-code');
      const name   = this.getAttribute('data-name');
      const type   = this.getAttribute('data-type');
      const val    = this.getAttribute('data-value');
      const min    = this.getAttribute('data-min');
      const max    = this.getAttribute('data-max');
      const limit  = this.getAttribute('data-limit');
      const start  = this.getAttribute('data-start');
      const end    = this.getAttribute('data-end');
      const status = this.getAttribute('data-status');

      document.getElementById('edit-voucher-id').value = id;
      document.getElementById('edit-code').value = code;
      document.getElementById('edit-name').value = name;
      document.getElementById('edit-discount-type').value = type;
      document.getElementById('edit-discount-value').value = val;
      document.getElementById('edit-min-order').value = min > 0 ? min : '';
      document.getElementById('edit-max-discount').value = max > 0 ? max : '';
      document.getElementById('edit-usage-limit').value = limit > 0 ? limit : '';
      document.getElementById('edit-start-date').value = start;
      document.getElementById('edit-end-date').value = end;
      document.getElementById('edit-status').value = status;

      const editLabel = document.getElementById('edit-discount-val-label');
      const editMaxGroup = document.getElementById('edit-max-discount-group');
      if (type === 'percent') {
        editLabel.innerHTML = 'Giá trị giảm (%) <span class="required">*</span>';
        if (editMaxGroup) editMaxGroup.style.display = 'block';
      } else {
        editLabel.innerHTML = 'Giá trị giảm (đ) <span class="required">*</span>';
        if (editMaxGroup) editMaxGroup.style.display = 'none';
      }

      openModal('modal-edit-voucher');
    });
  });

  // Nút Xóa voucher
  document.querySelectorAll('.btn-delete-voucher').forEach(btn => {
    btn.addEventListener('click', function() {
      const id   = this.getAttribute('data-id');
      const code = this.getAttribute('data-code');

      document.getElementById('delete-voucher-id').value = id;
      document.getElementById('delete-voucher-code').textContent = code;

      openModal('modal-delete-voucher');
    });
  });

  // Tự động mở modal nếu có action=add
  const params = new URLSearchParams(window.location.search);
  if (params.get('action') === 'add') {
    openModal('modal-add-voucher');
  }
});
</script>

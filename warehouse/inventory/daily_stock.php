<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(WAREHOUSE_ALLOWED_ROLES);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/daily_stock.php';

$selectedDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date'] ?? '') ? (string) $_GET['date'] : date('Y-m-d');
$todayDate = date('Y-m-d');

$successMsg = get_flash('daily_stock_success') ?? '';
$errorMsg = get_flash('daily_stock_error') ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    if (!can_manage_warehouse()) {
        set_flash('daily_stock_error', '❌ Chỉ Quản trị viên hoặc Quản lý ca mới có quyền chốt tồn cuối ngày.');
        redirect_to('inventory/daily_stock.php?date=' . urlencode($selectedDate));
    }

    $postDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_POST['snapshot_date'] ?? '') ? (string) $_POST['snapshot_date'] : $selectedDate;
    $closingInputs = is_array($_POST['closing_qty'] ?? null) ? $_POST['closing_qty'] : [];
    $noteInputs = is_array($_POST['note'] ?? null) ? $_POST['note'] : [];
    $syncProductStock = !empty($_POST['sync_product_stock']);

    try {
        $count = warehouse_save_daily_stock_snapshots(
            $conn,
            $postDate,
            $closingInputs,
            $noteInputs,
            current_username(),
            $syncProductStock
        );
        set_flash('daily_stock_success', '✅ Đã lưu và chốt tồn kho cuối ngày ' . date('d/m/Y', strtotime($postDate)) . ' cho ' . $count . ' mặt hàng.');
        redirect_to('inventory/daily_stock.php?date=' . urlencode($postDate));
    } catch (Throwable $e) {
        error_log('daily_stock save error: ' . $e->getMessage());
        $errorMsg = '❌ Không lưu được tồn theo ngày. Vui lòng thử lại.';
    }
}

$dailyRows = [];
try {
    $dailyRows = warehouse_get_daily_stock_rows($conn, $selectedDate);
} catch (Throwable $e) {
    error_log('daily_stock query error: ' . $e->getMessage());
    $errorMsg = '❌ Không tải được dữ liệu tồn kho theo ngày.';
}

$closedItemsCount = 0;
$totalClosingStock = 0;
$totalKdsSold = 0;
foreach ($dailyRows as $dr) {
    if ($dr['is_closed']) {
        $closedItemsCount++;
    }
    $totalClosingStock += ($dr['saved_closing'] !== null ? $dr['saved_closing'] : $dr['current_qty']);
    $totalKdsSold += $dr['kds_sold_qty'];
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Tồn theo ngày — SmartDine Kho</title>
  <link rel="stylesheet" href="<?= e(asset_url('assets/css/style.css')) ?>">
  <link rel="stylesheet" href="<?= e(asset_url('assets/css/header.css')) ?>">
  <link rel="stylesheet" href="<?= e(asset_url('assets/css/inventory.css')) ?>">
</head>
<body class="dashboard-body">

<?php include __DIR__ . '/../includes/sidebar.php'; ?>

<main class="dashboard-main">
  <?php include __DIR__ . '/../includes/header.php'; ?>

  <div class="dash-header">
    <div>
      <span class="eyebrow">DAILY STOCK TRACKING & SHIFT CLOSING</span>
      <h1>📅 Tồn Kho Theo Ngày &amp; Chốt Hàng Cuối Ca</h1>
      <p>Ghi nhận lượng hàng tồn lại mỗi ngày để đối chiếu trực tiếp với lượng sản phẩm bán ra bên KDS.</p>
    </div>
    <div class="header-action-group">
      <a href="<?= e(dirname(warehouse_base_url()) . '/index.php?page=admin&mode=reconcile&date=' . urlencode($selectedDate)) ?>" class="btn btn-primary">📊 Đối chiếu Thực xuất KDS</a>
      <a href="<?= e(warehouse_url('inventory/inventory.php')) ?>" class="btn btn-outline">← Tồn kho hiện tại</a>
    </div>
  </div>

  <?php if ($successMsg !== ''): ?>
    <div class="flash-alert flash-success"><?= e($successMsg) ?></div>
  <?php endif; ?>
  <?php if ($errorMsg !== ''): ?>
    <div class="flash-alert flash-error"><?= e($errorMsg) ?></div>
  <?php endif; ?>

  <div class="revenue-stats-grid">
    <div class="revenue-stat-card">
      <div class="revenue-stat-head">
        <span>Ngày theo dõi</span>
        <span class="stat-trend-badge positive"><?= e(date('d/m/Y', strtotime($selectedDate))) ?></span>
      </div>
      <div class="revenue-stat-value"><?= count($dailyRows) ?></div>
      <div class="revenue-stat-sub">Sản phẩm đang quản lý trong kho</div>
    </div>

    <div class="revenue-stat-card">
      <div class="revenue-stat-head">
        <span>Đã chốt tồn cuối ngày</span>
        <span class="stat-trend-badge <?= $closedItemsCount === count($dailyRows) && count($dailyRows) > 0 ? 'positive' : 'neutral' ?>">
          <?= $closedItemsCount ?> / <?= count($dailyRows) ?>
        </span>
      </div>
      <div class="revenue-stat-value"><?= $closedItemsCount ?></div>
      <div class="revenue-stat-sub">Mặt hàng đã lưu số tồn lại thực tế</div>
    </div>

    <div class="revenue-stat-card">
      <div class="revenue-stat-head">
        <span>Tổng lượng bán KDS ngày này</span>
        <span class="stat-trend-badge neutral">Đối chiếu</span>
      </div>
      <div class="revenue-stat-value"><?= number_format($totalKdsSold) ?></div>
      <div class="revenue-stat-sub">Tổng phần món khớp tên trên KDS</div>
    </div>

    <div class="revenue-stat-card">
      <div class="revenue-stat-head">
        <span>Tổng tồn cuối ngày</span>
        <span class="stat-trend-badge positive">Warehouse</span>
      </div>
      <div class="revenue-stat-value"><?= number_format($totalClosingStock) ?></div>
      <div class="revenue-stat-sub">Tổng số lượng hàng tồn lại</div>
    </div>
  </div>

  <div class="panel">
    <div class="panel-title" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
      <div>
        <h2>📋 Bảng kê tồn kho &amp; chốt hàng ngày <?= e(date('d/m/Y', strtotime($selectedDate))) ?></h2>
        <p class="muted">Ghi nhận số hàng tồn lại cuối ngày để đối chiếu trực tiếp với lượng món bán ra bên KDS.</p>
      </div>
      <form method="GET" action="" style="display:flex;align-items:center;gap:8px;">
        <input type="date" name="date" value="<?= e($selectedDate) ?>" style="padding:8px 12px;border:1px solid #cbd5e1;border-radius:8px;font-weight:600;">
        <button type="submit" class="btn btn-primary">Xem ngày</button>
      </form>
    </div>

    <?php if (empty($dailyRows)): ?>
      <div class="empty-state">
        <p>Kho chưa có sản phẩm nào. Hãy thêm sản phẩm để bắt đầu theo dõi tồn theo ngày.</p>
      </div>
    <?php else: ?>
      <form method="POST" action="">
        <?= csrf_field() ?>
        <input type="hidden" name="snapshot_date" value="<?= e($selectedDate) ?>">

        <div class="table-responsive">
          <table class="inventory-table">
            <thead>
              <tr>
                <th>Sản phẩm</th>
                <th>Mã</th>
                <th>Tồn đầu ngày</th>
                <th>Nhập ngày</th>
                <th>Tổng có</th>
                <th>Đã bán (KDS)</th>
                <th>Tồn dự kiến</th>
                <th>Tồn lại cuối ngày</th>
                <th>Ghi chú</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($dailyRows as $r):
                $pid = (int) $r['product_id'];
                $defaultClosing = $r['saved_closing'] !== null ? (int) $r['saved_closing'] : max(0, (int) $r['expected_closing']);
              ?>
                <tr>
                  <td>
                    <strong><?= e($r['name']) ?></strong>
                    <?php if ($r['is_closed']): ?>
                      <div style="font-size:11px;color:#15803d;font-weight:600;">
                        ✓ Đã chốt tồn · Tiêu hao: <?= (int) $r['consumed_qty'] ?>
                      </div>
                    <?php endif; ?>
                  </td>
                  <td><span class="sku-badge"><?= e($r['sku']) ?></span></td>
                  <td><?= number_format((int) $r['opening_qty']) ?></td>
                  <td style="color:#15803d;font-weight:600;">+<?= number_format((int) $r['in_qty']) ?></td>
                  <td><strong><?= number_format((int) $r['available_qty']) ?></strong></td>
                  <td style="color:#ea580c;font-weight:700;"><?= number_format((int) $r['kds_sold_qty']) ?></td>
                  <td><?= number_format((int) $r['expected_closing']) ?></td>
                  <td>
                    <?php if (can_manage_warehouse()): ?>
                      <input type="number"
                             name="closing_qty[<?= $pid ?>]"
                             min="0"
                             step="1"
                             value="<?= $defaultClosing ?>"
                             style="width:92px;padding:6px 8px;border:1px solid #cbd5e1;border-radius:6px;font-weight:700;text-align:right;">
                    <?php else: ?>
                      <strong><?= $r['saved_closing'] !== null ? number_format((int) $r['saved_closing']) : 'Chưa chốt' ?></strong>
                    <?php endif; ?>
                  </td>
                  <td>
                    <?php if (can_manage_warehouse()): ?>
                      <input type="text"
                             name="note[<?= $pid ?>]"
                             maxlength="255"
                             value="<?= e($r['note']) ?>"
                             placeholder="Ghi chú kiểm kê..."
                             style="width:160px;padding:6px 8px;border:1px solid #cbd5e1;border-radius:6px;">
                    <?php else: ?>
                      <?= e($r['note'] !== '' ? $r['note'] : '—') ?>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <?php if (can_manage_warehouse()): ?>
          <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-top:18px;padding-top:14px;border-top:1px solid #e2e8f0;">
            <?php if ($selectedDate === $todayDate): ?>
              <label style="display:flex;align-items:center;gap:8px;font-size:13px;color:#334155;cursor:pointer;">
                <input type="checkbox" name="sync_product_stock" value="1" checked>
                <span>Đồng bộ số <b>Tồn lại cuối ngày</b> vào tồn kho hiện tại cho ngày mai</span>
              </label>
            <?php else: ?>
              <span style="font-size:12.5px;color:#64748b;">Chốt bổ sung cho ngày cũ chỉ lưu lịch sử đối chiếu, không ghi đè tồn hiện tại hôm nay.</span>
            <?php endif; ?>

            <button type="submit" class="btn btn-primary">💾 Lưu &amp; Chốt tồn cuối ngày</button>
          </div>
        <?php endif; ?>
      </form>
    <?php endif; ?>
  </div>

  <?php include __DIR__ . '/../includes/footer.php'; ?>
</main>

</body>
</html>

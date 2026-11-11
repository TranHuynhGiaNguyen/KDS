<?php
/**
 * SmartDine - Lịch sử bill: xem lại và in lại bill món, hóa đơn đã thanh toán.
 * Vai trò bếp chỉ thấy tab Bill món; tab Hóa đơn (có tiền) dành cho nhân viên và admin.
 */

require_once __DIR__ . '/../includes/bills.php';
require_once __DIR__ . '/../includes/http.php';
require_once __DIR__ . '/../includes/bill-summary.php';

$canSeeInvoices = canRoleAccessPage(currentRole(), 'payment');
$tab            = ($_GET['tab'] ?? 'items') === 'invoices' && $canSeeInvoices ? 'invoices' : 'items';
$tableCode      = trim((string) ($_GET['table'] ?? ''));
$tableCode      = preg_match('/^[A-Za-z0-9]{1,10}$/', $tableCode) ? strtoupper($tableCode) : '';
$date           = normalizeBillDate($_GET['date'] ?? null);

$rows  = [];
$error = '';

try {
    $pdo  = getOrdersDb();
    $rows = $tab === 'items' ? fetchItemBills($pdo, $tableCode, $date) : fetchInvoiceList($pdo, $tableCode, $date);
} catch (RuntimeException | PDOException $e) {
    error_log('bills page: ' . $e->getMessage());
    $error = 'Không tải được lịch sử bill, vui lòng thử lại.';
}

$statusLabels = ORDER_ITEM_STATUSES;
?>
<section class="page-heading">
  <span class="eyebrow">LỊCH SỬ BILL</span>
  <h1>Lịch sử bill</h1>
  <p>Xem lại và in lại bill món gửi bếp<?= $canSeeInvoices ? ' và hóa đơn đã thanh toán' : '' ?></p>
</section>

<?php if ($error !== ''): ?>
  <div class="flash-alert flash-error"><span><?= escapeHtml($error) ?></span></div>
<?php endif; ?>

<div class="bills-tabs">
  <a class="<?= $tab === 'items' ? 'active' : '' ?>" href="index.php?page=bills&tab=items">Bill món</a>
  <?php if ($canSeeInvoices): ?>
    <a class="<?= $tab === 'invoices' ? 'active' : '' ?>" href="index.php?page=bills&tab=invoices">Hóa đơn đã thanh toán</a>
  <?php endif; ?>
</div>

<form class="bills-filter" method="get" action="index.php">
  <input type="hidden" name="page" value="bills">
  <input type="hidden" name="tab" value="<?= escapeHtml($tab) ?>">
  <input type="text" name="table" value="<?= escapeHtml($tableCode) ?>" placeholder="Mã bàn, ví dụ B05" maxlength="10" aria-label="Mã bàn">
  <input type="date" name="date" value="<?= escapeHtml($date) ?>" aria-label="Ngày">
  <button class="btn" type="submit">Lọc</button>
  <a class="btn btn-outline" href="index.php?page=bills&tab=<?= escapeHtml($tab) ?>">Xóa lọc</a>
</form>

<section class="panel bills-panel">
  <?php if ($rows === []): ?>
    <p class="muted bills-empty">Chưa có bill nào khớp bộ lọc.</p>
  <?php elseif ($tab === 'items'): ?>
    <table class="bills-table">
      <thead><tr><th>Giờ xuất</th><th>Bàn</th><th>Món</th><th>SL</th><th>Nhân viên</th><th>Trạng thái</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($rows as $row): ?>
          <tr>
            <td><?= escapeHtml($row['sent_at']) ?></td>
            <td><b><?= escapeHtml($row['table']) ?></b></td>
            <td><?= escapeHtml($row['name']) ?><?= $row['note'] !== '' ? '<small class="bills-note">' . escapeHtml($row['note']) . '</small>' : '' ?></td>
            <td><?= $row['qty'] ?></td>
            <td><?= escapeHtml($row['staff']) ?></td>
            <td><span class="status status-<?= escapeHtml($statusLabels[$row['status']]['class']) ?>"><?= escapeHtml($statusLabels[$row['status']]['label']) ?></span></td>
            <td>
              <?php if ($row['status'] !== 'cancelled'): ?>
                <button class="btn btn-small btn-outline" type="button" data-bill-print="<?= $row['id'] ?>">In lại</button>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php else: ?>
    <table class="bills-table">
      <thead><tr><th>Giờ thanh toán</th><th>Số hóa đơn</th><th>Bàn</th><th>Thu ngân</th><th>Phương thức</th><th>Tổng tiền</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($rows as $row): ?>
          <tr>
            <td><?= escapeHtml($row['paid_at']) ?></td>
            <td><b><?= escapeHtml($row['code']) ?></b></td>
            <td><?= escapeHtml($row['table']) ?></td>
            <td><?= escapeHtml($row['cashier']) ?></td>
            <td><?= escapeHtml($row['method']) ?></td>
            <td><b><?= formatMoney($row['total']) ?></b></td>
            <td><a class="btn btn-small btn-outline" href="index.php?page=payment&table=<?= escapeHtml($row['table']) ?>&invoice=<?= $row['id'] ?>">Xem / In lại</a></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
  <p class="muted bills-limit">Hiển thị tối đa <?= BILLS_LIST_LIMIT ?> bill gần nhất.</p>
</section>
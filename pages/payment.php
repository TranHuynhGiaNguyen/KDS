<?php
require_once __DIR__ . '/../includes/bill-summary.php';
require_once __DIR__ . '/../includes/http.php';
require_once __DIR__ . '/../includes/orders.php';

$tableCode  = normalizeTableCode($_GET['table'] ?? null);
$paymentUrl = 'index.php?page=payment&table=' . $tableCode;

$isPost        = $_SERVER['REQUEST_METHOD'] === 'POST';
$action        = $isPost ? ($_POST['action'] ?? '') : '';
$discountType  = $isPost ? ($_POST['discount_type'] ?? DISCOUNT_NONE) : DISCOUNT_NONE;
$discountValue = $isPost ? (int) ($_POST['discount_value'] ?? 0) : 0;
$method        = $isPost && in_array($_POST['pay'] ?? '', ['qr', 'cash'], true) ? $_POST['pay'] : 'qr';
$cashReceived  = $isPost ? (int) ($_POST['cash_received'] ?? 0) : 0;

$error = '';

try {
    $items = fetchTableItems(getOrdersDb(), $tableCode);
} catch (RuntimeException | PDOException $e) {
    error_log('payment page: ' . $e->getMessage());
    $items = [];
    $error = 'Không tải được hóa đơn, vui lòng thử lại.';
}

try {
    $bill = calculateBill($items, ['type' => $discountType, 'value' => $discountValue]);
} catch (InvalidArgumentException $e) {
    $error         = $e->getMessage();
    $discountType  = DISCOUNT_NONE;
    $discountValue = 0;
    $bill          = calculateBill($items);
}

if ($action === 'pay' && $error === '') {
    if ($bill['total'] <= 0) {
        $error = 'Hóa đơn không có món nào để thanh toán.';
    } elseif ($method === 'cash' && $cashReceived < $bill['total']) {
        $error = 'Tiền khách đưa chưa đủ. Cần tối thiểu ' . formatMoney($bill['total']) . '.';
    } else {
        $_SESSION['payment_result'][$tableCode] = [
            'bill'          => $bill,
            'method'        => $method,
            'cash_received' => $cashReceived,
            'change'        => $method === 'cash' ? $cashReceived - $bill['total'] : 0,
        ];
        redirectTo($paymentUrl . '&done=1');
    }
}

$paidResult = ($_GET['done'] ?? '') === '1' ? ($_SESSION['payment_result'][$tableCode] ?? null) : null;
$isPaid     = $paidResult !== null;
$change     = 0;

if ($isPaid) {
    $bill         = $paidResult['bill'];
    $method       = $paidResult['method'];
    $cashReceived = $paidResult['cash_received'];
    $change       = $paidResult['change'];
}
?>
<section class="page-heading">
  <div class="back-nav-row">
    <a class="back-link" href="index.php?page=table-order&table=<?= escapeHtml($tableCode) ?>">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
      <span>Quay lại chi tiết đơn bàn</span>
    </a>
  </div>
  <span class="eyebrow">THANH TOÁN HÓA ĐƠN</span>
  <h1>Thanh toán bữa ăn — Bàn <?= escapeHtml($tableCode) ?></h1>
  <p>Kiểm tra hóa đơn, áp dụng giảm giá và chọn phương thức thanh toán</p>
</section>

<div id="payment-alerts">
  <?php if ($error !== ''): ?>
    <div class="flash-alert flash-error"><span><?= escapeHtml($error) ?></span></div>
  <?php endif; ?>
</div>

<?php if (empty($bill['lines'])): ?>
  <section class="panel empty-state">
    <h3>Bàn <?= escapeHtml($tableCode) ?> chưa có món nào để thanh toán</h3>
    <a class="btn" href="index.php?page=staff">Về sơ đồ bàn</a>
  </section>
<?php else: ?>
<div class="two-col payment-layout">
  <section class="panel">
    <div class="panel-title">
      <h2>Chi tiết hóa đơn</h2>
      <?php if ($isPaid): ?>
        <span class="status status-ready">Đã thanh toán</span>
      <?php else: ?>
        <span class="status status-pending">Chờ thanh toán</span>
      <?php endif; ?>
    </div>

    <div class="bill-line-items">
      <?php foreach ($bill['lines'] as $line): ?>
        <div class="line-item">
          <div>
            <b><?= escapeHtml($line['name']) ?></b>
            <small><?= escapeHtml($line['qty']) ?> × <?= formatMoney($line['price']) ?></small>
          </div>
          <b><?= formatMoney($line['amount']) ?></b>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="bill-summary-box" id="bill-summary">
      <?= renderBillSummary($bill) ?>
    </div>
  </section>

  <?php if ($isPaid): ?>
    <section class="panel payment-success">
      <h2>Thanh toán thành công</h2>
      <p class="muted">Phương thức: <?= $method === 'cash' ? 'Tiền mặt' : 'Chuyển khoản QR' ?></p>
      <div class="summary-row"><span>Đã thanh toán</span><b><?= formatMoney($bill['total']) ?></b></div>
      <?php if ($method === 'cash'): ?>
        <div class="summary-row"><span>Khách đưa</span><b><?= formatMoney($cashReceived) ?></b></div>
        <div class="summary-row total"><span>Tiền thừa trả khách</span><b><?= formatMoney($change) ?></b></div>
      <?php endif; ?>
      <a class="btn btn-block" href="index.php?page=staff">Về sơ đồ bàn</a>
    </section>
  <?php else: ?>
    <form class="panel payment-methods-panel" method="post" action="<?= escapeHtml($paymentUrl) ?>"
          data-payment-form data-table="<?= escapeHtml($tableCode) ?>" data-calculate-url="api/payment-calculate.php">
      <h2>Giảm giá</h2>
      <div class="discount-row">
        <select name="discount_type" aria-label="Loại giảm giá">
          <option value="<?= DISCOUNT_NONE ?>" <?= $discountType === DISCOUNT_NONE ? 'selected' : '' ?>>Không giảm giá</option>
          <option value="<?= DISCOUNT_PERCENT ?>" <?= $discountType === DISCOUNT_PERCENT ? 'selected' : '' ?>>Giảm theo %</option>
          <option value="<?= DISCOUNT_AMOUNT ?>" <?= $discountType === DISCOUNT_AMOUNT ? 'selected' : '' ?>>Giảm theo số tiền (đ)</option>
        </select>
        <input type="number" name="discount_value" min="0" step="1" value="<?= $discountValue > 0 ? escapeHtml($discountValue) : '' ?>" aria-label="Giá trị giảm">
        <button class="btn btn-outline" type="button" data-apply-discount>Áp dụng</button>
      </div>

      <h2>Phương thức thanh toán</h2>

      <label class="payment-option">
        <input type="radio" name="pay" value="qr" <?= $method === 'qr' ? 'checked' : '' ?>>
        <div class="pay-content">
          <div class="pay-title-row">
            <b>Chuyển khoản QR (VietQR / MoMo / VNPay)</b>
            <span class="badge-recommend">Khuyên dùng</span>
          </div>
          <small class="muted">Khách quét mã, thu ngân xác nhận đã nhận tiền rồi bấm thanh toán</small>
          <div class="qr-mock-box">
            <div class="qr-code-art">
              <svg width="72" height="72" viewBox="0 0 24 24" fill="currentColor"><path d="M2 2h8v8H2zm2 2v4h4V4zm10-2h8v8h-8zm2 2v4h4V4zM2 14h8v8H2zm2 2v4h4v-4zm14-2h4v2h-4zm-4 0h2v4h-2zm2 4h2v4h-2zm2 0h2v2h-2zm0 2h2v2h-2zm-6-2h2v4h-2zm4-6h4v2h-4z"/></svg>
            </div>
            <div class="qr-info-meta">
              <b>SMARTDINE RESTAURANT</b>
              <span class="qr-amount">Số tiền: <b data-bill-total><?= formatMoney($bill['total']) ?></b></span>
            </div>
          </div>
        </div>
      </label>

      <label class="payment-option">
        <input type="radio" name="pay" value="cash" <?= $method === 'cash' ? 'checked' : '' ?>>
        <div class="pay-content">
          <div class="pay-title-row"><b>Tiền mặt</b></div>
          <small class="muted">Nhập số tiền khách đưa để tính tiền thừa</small>
          <input class="cash-input" type="number" name="cash_received" min="0" step="1000"
                 value="<?= $cashReceived > 0 ? escapeHtml($cashReceived) : '' ?>" placeholder="Tiền khách đưa (đ)" aria-label="Tiền khách đưa">
        </div>
      </label>

      <button class="btn btn-block btn-pay-now" type="submit" name="action" value="pay">
        <span>Xác nhận thanh toán <b data-bill-total><?= formatMoney($bill['total']) ?></b></span>
      </button>
    </form>
  <?php endif; ?>
</div>
<?php endif; ?>
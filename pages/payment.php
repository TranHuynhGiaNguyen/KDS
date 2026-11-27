<?php
require_once __DIR__ . '/../includes/bill-summary.php';
require_once __DIR__ . '/../includes/checkout.php';
require_once __DIR__ . '/../includes/receipt.php';
require_once __DIR__ . '/../includes/vietqr.php';
require_once __DIR__ . '/../includes/vouchers.php';
require_once __DIR__ . '/../includes/ratings.php';

$tableCode  = normalizeTableCode($_GET['table'] ?? null);
$paymentUrl = 'index.php?page=payment&table=' . $tableCode;
$invoiceId  = (int) ($_GET['invoice'] ?? 0);

$isPost        = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
$action        = $isPost ? ($_POST['action'] ?? '') : '';
$discountType  = $isPost ? ($_POST['discount_type'] ?? DISCOUNT_NONE) : DISCOUNT_NONE;
$discountValue = $isPost ? (int) ($_POST['discount_value'] ?? 0) : 0;
$voucherCode   = $isPost ? strtoupper(trim($_POST['voucher_code'] ?? '')) : '';
$method        = $isPost && isset(PAYMENT_METHODS[$_POST['pay'] ?? '']) ? $_POST['pay'] : 'cash';
$cashReceived  = $isPost ? (int) ($_POST['cash_received'] ?? 0) : 0;
$waiterId      = $isPost ? (int) ($_POST['waiter_id'] ?? 0) : 0;
$ratingScore   = $isPost ? (int) ($_POST['rating'] ?? 0) : 0;
$ratingComment = $isPost ? trim((string) ($_POST['rating_comment'] ?? '')) : '';

$error    = '';
$warning  = '';
$items    = [];
$invoice  = null;
$blockers = [];
$orderId  = null;
$invoiceRating = null;
$waiters       = [];

try {
  $pdo = getOrdersDb();

  if ($action === 'pay') {
    try {
      $newInvoiceId = checkoutTable(
        $pdo,
        $tableCode,
        ['type' => $discountType, 'value' => $discountValue, 'voucher_code' => $voucherCode],
        $method,
        $cashReceived,
        (int) $_SESSION['user_id'],
        $waiterId > 0 ? $waiterId : null,
        $ratingScore > 0 ? $ratingScore : null,
        $ratingComment
      );
      $_SESSION['flash_success'] = 'Thanh toán thành công Bàn ' . $tableCode . '! Bàn đã được giải phóng về trạng thái trống.';
      redirectTo('index.php?page=staff');
    } catch (CheckoutException $e) {
      $error = $e->getMessage();
    }
  }

  if ($invoiceId > 0) {
    $invoice = fetchInvoice($pdo, $invoiceId);
    if ($invoice === null || $invoice['table'] !== $tableCode) {
      $invoice = null;
      $error   = 'Không tìm thấy hóa đơn.';
    } else {
      $invoiceRating = fetchInvoiceRating($pdo, $invoiceId);

      if ($invoiceRating !== null && $invoiceRating['rating'] === null) {
        $invoice['rating_url'] = $invoiceRating['url'];
      }
    }
  }

  if ($invoice === null) {
    $items    = fetchTableItems($pdo, $tableCode);
    $blockers = findCheckoutBlockers($items);
    $orderId  = fetchOpenOrderId($pdo, $tableCode);
    $waiters  = fetchWaiters($pdo);

    if ($waiterId <= 0) {
      $waiterId = suggestWaiterId($pdo, $tableCode, (int) $_SESSION['user_id'], currentRole()) ?? 0;
    }
  }
} catch (RuntimeException | PDOException $e) {
  error_log('payment page: ' . $e->getMessage());
  $error = 'Không xử lý được thanh toán, vui lòng thử lại.';
}

$isPaid = $invoice !== null;
$bank   = getRestaurantInfo()['bank'];

if ($isPaid) {
  $bill         = $invoice['bill'];
  $method       = $invoice['method'];
  $cashReceived = $invoice['cash_received'];
  $change       = $invoice['change'];
} else {
  try {
    $bill = calculateBill($items, ['type' => $discountType, 'value' => $discountValue, 'voucher_code' => $voucherCode]);
  } catch (InvalidArgumentException $e) {
    $error         = $e->getMessage();
    $discountType  = DISCOUNT_NONE;
    $discountValue = 0;
    $bill          = calculateBill($items);
  }
  $change = 0;
}

$qrPayload = $isPaid ? '' : buildInvoiceQrPayload($orderId, $tableCode, $bill['total']);

if ($blockers !== [] && !$isPaid) {
  $warning = checkoutBlockersMessage($blockers);
}
?>
<section class="page-heading">
  <div class="back-nav-row">
    <a class="back-link no-print" href="index.php?page=table-order&table=<?= escapeHtml($tableCode) ?>">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <line x1="19" y1="12" x2="5" y2="12"></line>
        <polyline points="12 19 5 12 12 5"></polyline>
      </svg>
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
  <?php if ($warning !== ''): ?>
    <div class="flash-alert flash-warning"><span><?= escapeHtml($warning) ?></span></div>
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
      <p class="muted no-print" style="font-size: 12px; margin-top: 8px;">
        Thuế GTGT (VAT) áp dụng theo quy định Việt Nam (0%, 5%, 8%, 10% · mặc định dịch vụ ăn uống 8% đến 31/12/2026, 10% từ 01/01/2027).
      </p>
    </section>

    <?php if ($isPaid): ?>
      <section class="panel payment-success">
        <h2>Thanh toán thành công</h2>
        <p class="muted">Mã hóa đơn <b><?= escapeHtml($invoice['code']) ?></b> · <?= escapeHtml($invoice['paid_at']) ?> · Thu ngân: <?= escapeHtml($invoice['cashier']) ?></p>
        <p class="muted">Phương thức: <?= escapeHtml(PAYMENT_METHODS[$method]) ?></p>
        <div class="summary-row"><span>Đã thanh toán</span><b><?= formatMoney($bill['total']) ?></b></div>
        <?php if ($invoiceRating !== null): ?>
          <div class="rating-invite">
            <?php if ($invoiceRating['rating'] === null): ?>
              <h3>Mời khách đánh giá phục vụ</h3>
              <p class="muted">Khách quét mã QR bằng điện thoại để chấm sao cho nhân viên phục vụ. Mã QR cũng được in trên hóa đơn.</p>
              <div class="rating-qr" data-rate-qr="<?= escapeHtml($invoiceRating['url']) ?>"></div>
              <a class="rating-link" href="<?= escapeHtml($invoiceRating['url']) ?>" target="_blank" rel="noopener"><?= escapeHtml($invoiceRating['url']) ?></a>
              <p class="muted">Khách chưa đánh giá. Tải lại trang để xem kết quả.</p>
            <?php else: ?>
              <h3>Khách đã đánh giá</h3>
              <div class="summary-row rating-result">
                <span>Nhân viên: <?= escapeHtml($invoiceRating['staff'] ?? 'Không rõ') ?></span>
                <b class="rating-stars-static" title="<?= escapeHtml(RATING_LABELS[$invoiceRating['rating']]) ?>"><?= escapeHtml(renderStars($invoiceRating['rating'])) ?></b>
              </div>
              <?php if ($invoiceRating['comment'] !== ''): ?>
                <p class="muted">“<?= escapeHtml($invoiceRating['comment']) ?>”</p>
              <?php endif; ?>
            <?php endif; ?>
          </div>
        <?php endif; ?>
        <?php if ($method === 'cash'): ?>
          <div class="summary-row"><span>Khách đưa</span><b><?= formatMoney($cashReceived) ?></b></div>
          <div class="summary-row total"><span>Tiền thừa trả khách</span><b><?= formatMoney($change) ?></b></div>
        <?php endif; ?>
        <button class="btn btn-outline btn-block no-print" type="button" data-print-invoice>In hóa đơn</button>
        <a class="btn btn-block no-print" href="index.php?page=staff">Về sơ đồ bàn</a>
      </section>
    <?php else: ?>
      <?php $availableVouchers = getAllVouchers('', 'active'); ?>
      <form class="panel payment-methods-panel" method="post" action="<?= escapeHtml($paymentUrl) ?>"
        data-payment-form data-total="<?= (int) $bill['total'] ?>" data-table="<?= escapeHtml($tableCode) ?>" data-calculate-url="api/payment-calculate.php"
        data-qr-payload="<?= escapeHtml($qrPayload) ?>">
        <h2>Khuyến mãi & Giảm giá</h2>

        <div class="payment-field">
          <label class="field-label" for="input-payment-voucher">Áp dụng Voucher</label>
          <div class="voucher-row">
            <input class="field-control field-upper" type="text" name="voucher_code" id="input-payment-voucher" placeholder="Nhập voucher"
              value="<?= escapeHtml($voucherCode) ?>" autocomplete="off">
            <select class="field-control" id="select-active-voucher" aria-label="Chọn voucher">
              <option value="">Chọn voucher</option>
              <?php foreach ($availableVouchers as $v): ?>
                <option value="<?= escapeHtml($v['code']) ?>">
                  <?= escapeHtml($v['code']) ?> (<?= $v['discount_type'] === 'percent' ? $v['discount_value'] . '%' : number_format($v['discount_value']) . 'đ' ?><?= $v['min_order_value'] > 0 ? ' - Đơn từ ' . number_format($v['min_order_value']) . 'đ' : '' ?>)
                </option>
              <?php endforeach; ?>
            </select>
            <button class="btn btn-outline" type="button" data-apply-discount>Áp dụng</button>
          </div>
        </div>

        <div class="field-label">Hoặc giảm giá thủ công</div>
        <div class="discount-row">
          <select class="field-control" name="discount_type" aria-label="Loại giảm giá">
            <option value="<?= DISCOUNT_NONE ?>" <?= $discountType === DISCOUNT_NONE ? 'selected' : '' ?>>Không giảm giá</option>
            <option value="<?= DISCOUNT_PERCENT ?>" <?= $discountType === DISCOUNT_PERCENT ? 'selected' : '' ?>>Giảm theo %</option>
            <option value="<?= DISCOUNT_AMOUNT ?>" <?= $discountType === DISCOUNT_AMOUNT ? 'selected' : '' ?>>Giảm theo số tiền (đ)</option>
          </select>
          <input class="field-control" type="number" name="discount_value" min="0" step="1" value="<?= $discountValue > 0 ? escapeHtml($discountValue) : '' ?>" placeholder="Giá trị" aria-label="Giá trị giảm">
        </div>

        <h2>Phương thức thanh toán</h2>

        <label class="payment-option">
          <input type="radio" name="pay" value="qr" <?= $method === 'qr' ? 'checked' : '' ?>>
          <div class="pay-content">
            <div class="pay-title-row"><b>Chuyển khoản QR</b></div>
            <small class="muted">Khách quét mã bằng app ngân hàng, số tiền và nội dung được điền sẵn. Khi kiểm tra tiền đã vào tài khoản, nhân viên bấm Xác nhận thanh toán.</small>
            <div class="pay-detail qr-detail">
              <?php if ($qrPayload !== ''): ?>
                <div class="qr-image" data-qr-box></div>
              <?php endif; ?>
              <dl class="qr-info">
                <div>
                  <dt>Ngân hàng</dt>
                  <dd><?= escapeHtml($bank['name']) ?></dd>
                </div>
                <div>
                  <dt>Số tài khoản</dt>
                  <dd><?= escapeHtml($bank['account_no']) ?></dd>
                </div>
                <div>
                  <dt>Chủ tài khoản</dt>
                  <dd><?= escapeHtml($bank['account_name']) ?></dd>
                </div>
                <div>
                  <dt>Số tiền</dt>
                  <dd data-bill-total><?= formatMoney($bill['total']) ?></dd>
                </div>
                <div>
                  <dt>Nội dung</dt>
                  <dd><?= $orderId !== null ? escapeHtml(formatTransferContent($orderId, $tableCode)) : '—' ?></dd>
                </div>
              </dl>
            </div>
          </div>
        </label>

        <label class="payment-option">
          <input type="radio" name="pay" value="cash" <?= $method === 'cash' ? 'checked' : '' ?>>
          <div class="pay-content">
            <div class="pay-title-row"><b>Tiền mặt</b></div>
            <small class="muted">Nhập số tiền khách đưa, tiền thừa sẽ tự tính ngay bên dưới</small>
            <div class="pay-detail">
              <input class="cash-input field-control" type="number" name="cash_received" min="0" step="1"
                value="<?= $cashReceived > 0 ? escapeHtml($cashReceived) : '' ?>" placeholder="Tiền khách đưa (đ)" aria-label="Tiền khách đưa">
              <div class="cash-change" data-cash-change hidden>
                <span data-cash-change-label>Tiền thừa trả khách</span>
                <b data-cash-change-value></b>
              </div>
            </div>
          </div>
        </label>

        <h2>Đánh giá nhân viên phục vụ</h2>

        <div class="payment-rating-box">
          <div class="payment-field">
            <label class="field-label" for="select-payment-waiter">Nhân viên phụ trách bàn</label>
            <select class="field-control" id="select-payment-waiter" name="waiter_id" aria-label="Nhân viên phục vụ bàn">
              <option value="0">Chọn nhân viên phục vụ (không bắt buộc)</option>
              <?php foreach ($waiters as $waiter): ?>
                <option value="<?= (int) $waiter['id'] ?>" <?= $waiterId === (int) $waiter['id'] ? 'selected' : '' ?>><?= escapeHtml($waiter['fullname']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="payment-field">
            <span class="field-label">Chọn số sao đánh giá</span>
            <div class="pay-star-rating" role="radiogroup" aria-label="Đánh giá nhân viên">
              <?php for ($star = RATING_MAX; $star >= RATING_MIN; $star--): ?>
                <input type="radio" id="pay-star-<?= $star ?>" name="rating" value="<?= $star ?>" data-rating-text="<?= escapeHtml(RATING_LABELS[$star]) ?>" <?= $ratingScore === $star ? 'checked' : '' ?>>
                <label for="pay-star-<?= $star ?>" title="<?= escapeHtml(RATING_LABELS[$star]) ?>">★</label>
              <?php endfor; ?>
            </div>
            <p class="muted pay-rating-label" data-pay-rating-label>
              <?= ($ratingScore >= RATING_MIN && $ratingScore <= RATING_MAX) ? escapeHtml(RATING_LABELS[$ratingScore]) : 'Chạm vào ngôi sao để đánh giá' ?>
            </p>
          </div>

          <div class="payment-field" style="margin-bottom: 0;">
            <label class="field-label" for="input-rating-comment">Ghi chú đánh giá (không bắt buộc)</label>
            <textarea class="field-control" id="input-rating-comment" name="rating_comment" rows="2" maxlength="255" placeholder="Nhập nhận xét hoặc ghi chú phục vụ..."><?= escapeHtml($ratingComment) ?></textarea>
          </div>
        </div>

        <button class="btn btn-block btn-pay-now" type="submit" name="action" value="pay" data-pay-button>
          <span>Xác nhận thanh toán <b data-bill-total><?= formatMoney($bill['total']) ?></b></span>
        </button>
      </form>
    <?php endif; ?>
  </div>
<?php endif; ?>

<?php if ($isPaid): ?>
  <div class="invoice-receipt"><?= renderReceipt(buildInvoiceReceipt($invoice)) ?></div>
<?php endif; ?>
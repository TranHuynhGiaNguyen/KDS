<?php
/**
 * SmartDine - Hóa đơn / phiếu tạm tính dạng giấy in (khổ nhỏ như máy in bill).
 *
 * renderReceipt() nhận mảng dữ liệu tạo bởi buildInvoiceReceipt() (hóa đơn đã thanh toán)
 * hoặc buildProvisionalReceipt() (phiếu tạm tính cho khách kiểm tra món, chưa giảm giá).
 */

require_once __DIR__ . '/bill-summary.php';
require_once __DIR__ . '/restaurant.php';

function formatReceiptTime(string $dateTime): string {
    return date('H:i d/m/Y', strtotime($dateTime));
}

function buildInvoiceReceipt(array $invoice): array {
    return [
        'title'          => 'HÓA ĐƠN BÁN HÀNG',
        'code'           => $invoice['code'],
        'table'          => $invoice['table'],
        'staff_label'    => 'Thu ngân',
        'staff'          => $invoice['cashier'],
        'time_in'        => $invoice['time_in_raw'],
        'time_out_label' => 'Giờ ra',
        'time_out'       => $invoice['paid_at_raw'],
        'bill'           => $invoice['bill'],
        'total_label'    => 'Tổng thanh toán',
        'show_qr'        => true,
    ];
}

function buildProvisionalReceipt(string $tableCode, array $bill, ?string $timeIn, string $staffName): array {
    return [
        'title'          => 'PHIẾU TẠM TÍNH',
        'code'           => null,
        'table'          => $tableCode,
        'staff_label'    => 'Nhân viên',
        'staff'          => $staffName,
        'time_in'        => $timeIn,
        'time_out_label' => 'Giờ in',
        'time_out'       => currentTimeForDb(),
        'bill'           => $bill,
        'total_label'    => 'Tổng cộng',
        'show_qr'        => false,
    ];
}

function renderReceipt(array $data): string {
    $restaurant = getRestaurantInfo();
    $bill       = $data['bill'];

    ob_start();
    ?>
    <div class="receipt">
      <div class="receipt-head">
        <b><?= escapeHtml($restaurant['name']) ?></b>
        <span><?= escapeHtml($restaurant['address']) ?></span>
        <span>Điện thoại: <?= escapeHtml($restaurant['phone']) ?></span>
      </div>

      <h2 class="receipt-title"><?= escapeHtml($data['title']) ?></h2>

      <div class="receipt-meta">
        <?php if ($data['code'] !== null): ?>
          <div><span>Số hóa đơn</span><b><?= escapeHtml($data['code']) ?></b></div>
        <?php endif; ?>
        <div><span>Bàn</span><b><?= escapeHtml($data['table']) ?></b></div>
        <div><span><?= escapeHtml($data['staff_label']) ?></span><b><?= escapeHtml($data['staff']) ?></b></div>
        <?php if ($data['time_in'] !== null): ?>
          <div><span>Giờ vào</span><b><?= escapeHtml(formatReceiptTime($data['time_in'])) ?></b></div>
        <?php endif; ?>
        <div><span><?= escapeHtml($data['time_out_label']) ?></span><b><?= escapeHtml(formatReceiptTime($data['time_out'])) ?></b></div>
      </div>

      <table class="receipt-items">
        <thead>
          <tr><th>Tên món</th><th>SL</th><th>Đơn giá</th><th>Thành tiền</th></tr>
        </thead>
        <tbody>
          <?php foreach ($bill['lines'] as $line): ?>
            <tr>
              <td><?= escapeHtml($line['name']) ?></td>
              <td><?= escapeHtml($line['qty']) ?></td>
              <td><?= escapeHtml(formatMoney($line['price'])) ?></td>
              <td><?= escapeHtml(formatMoney($line['amount'])) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

      <div class="receipt-totals">
        <?= renderBillSummary($bill, $data['total_label']) ?>
      </div>

      <?php if ($data['show_qr']): ?>
        <div class="receipt-qr">
          <img src="<?= escapeHtml($restaurant['payment_qr']) ?>" alt="Mã QR của quán">
        </div>
      <?php endif; ?>

      <p class="receipt-thanks">Cảm ơn quý khách. Hẹn gặp lại!</p>
    </div>
    <?php
    return (string) ob_get_clean();
}
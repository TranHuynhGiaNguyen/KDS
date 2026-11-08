<?php
require_once __DIR__ . '/billing.php';
require_once __DIR__ . '/http.php';

function renderBillSummary(array $bill, string $totalLabel = 'Tổng thanh toán'): string {
    $vatPercent     = $bill['vat_rate'] * 100;
    $servicePercent = $bill['service_rate'] * 100;

    ob_start();
    ?>
    <div class="summary-row"><span>Tạm tính tiền món</span><b><?= formatMoney($bill['subtotal']) ?></b></div>
    <?php if ($bill['discount_amount'] > 0): ?>
      <div class="summary-row summary-discount">
        <span>Giảm giá<?= $bill['discount_type'] === DISCOUNT_PERCENT ? ' (' . escapeHtml($bill['discount_value']) . '%)' : '' ?></span>
        <b>−<?= formatMoney($bill['discount_amount']) ?></b>
      </div>
    <?php endif; ?>
    <?php if ($bill['service_fee'] > 0): ?>
      <div class="summary-row"><span>Phí phục vụ (<?= escapeHtml($servicePercent) ?>%)</span><b><?= formatMoney($bill['service_fee']) ?></b></div>
    <?php endif; ?>
    <div class="summary-row"><span>Thuế VAT (<?= escapeHtml($vatPercent) ?>%)</span><b><?= formatMoney($bill['vat']) ?></b></div>
    <div class="summary-row total"><span><?= escapeHtml($totalLabel) ?></span><b class="highlight-price"><?= formatMoney($bill['total']) ?></b></div>
    <?php
    return ob_get_clean();
}
<?php
/**
 * SmartDine - Mẫu in báo cáo thuần chữ theo chuẩn bill/Z-Report của các hệ thống nhà hàng lớn.
 * Chỉ hiển thị khi gọi In báo cáo (@media print), ẩn hoàn toàn giao diện UI web.
 */

require_once __DIR__ . '/restaurant.php';

function renderPureTextPrintBillReport(string $viewMode, array $ctx): string
{
    $restaurant = getRestaurantInfo();
    $restName = mb_strtoupper((string) ($restaurant['name'] ?? 'SMARTDINE RESTAURANT'), 'UTF-8');
    $restAddress = (string) ($restaurant['address'] ?? '');
    $restPhone = (string) ($restaurant['phone'] ?? '');

    $printerName = (string) ($ctx['printer_name'] ?? 'Quản trị viên');
    $selectedDate = (string) ($ctx['selected_date'] ?? date('Y-m-d'));
    $startDate = (string) ($ctx['start_date'] ?? $selectedDate);
    $endDate = (string) ($ctx['end_date'] ?? $selectedDate);

    $billTitle = match ($viewMode) {
        'reconcile' => 'PHIẾU BÁO CÁO MÓN THỰC XUẤT CUỐI NGÀY (KẾT CA)',
        'kitchen'   => 'PHIẾU BÁO CÁO HIỆU SUẤT BẾP (KDS REPORT)',
        'month'     => 'BÁO CÁO TỔNG HỢP DOANH THU THÁNG (Z-REPORT)',
        'range'     => 'BÁO CÁO TỔNG HỢP DOANH THU THEO KỲ (Z-REPORT)',
        default     => 'PHIẾU BÁO CÁO DOANH THU NGÀY (Z-REPORT)',
    };

    $periodText = match ($viewMode) {
        'reconcile' => 'Ngày ' . ($ctx['reconcile']['date_display'] ?? date('d/m/Y', strtotime($selectedDate))) . ' (' . ($ctx['reconcile']['day_name'] ?? '') . ')',
        'kitchen'   => 'Từ ' . date('d/m/Y', strtotime($startDate)) . ' đến ' . date('d/m/Y', strtotime($endDate)),
        'month'     => 'Tháng ' . ($ctx['report']['month_display'] ?? ''),
        'range'     => ($ctx['report']['date_display'] ?? ''),
        default     => 'Ngày ' . ($ctx['report']['date_display'] ?? '') . ' (' . ($ctx['report']['day_name'] ?? '') . ')',
    };

    $money = static fn(int|float $v): string => number_format($v, 0, ',', '.') . 'đ';

    ob_start();
    ?>
    <section class="print-bill-report" aria-label="Báo cáo in thuần chữ">
      <div class="pb-center pb-bold pb-title"><?= htmlspecialchars($restName) ?></div>
      <?php if ($restAddress !== ''): ?>
        <div class="pb-center">ĐC: <?= htmlspecialchars($restAddress) ?></div>
      <?php endif; ?>
      <?php if ($restPhone !== ''): ?>
        <div class="pb-center">ĐT: <?= htmlspecialchars($restPhone) ?></div>
      <?php endif; ?>

      <div class="pb-rule-double"></div>
      <div class="pb-center pb-bold pb-doc-title"><?= htmlspecialchars($billTitle) ?></div>
      <div class="pb-rule-dashed"></div>

      <div class="pb-kv"><span>Kỳ thống kê:</span><b><?= htmlspecialchars($periodText) ?></b></div>
      <div class="pb-kv"><span>Giờ in phiếu:</span><span><?= date('d/m/Y H:i:s') ?></span></div>
      <div class="pb-kv"><span>Người lập ca:</span><span><?= htmlspecialchars($printerName) ?></span></div>

      <div class="pb-rule-dashed"></div>

      <?php if ($viewMode === 'reconcile'):
        $rep = $ctx['reconcile'];
      ?>
        <div class="pb-sec-title">I. TỔNG HỢP THỰC XUẤT &amp; TỒN KHO THEO NGÀY</div>
        <div class="pb-kv"><span>Tổng mặt hàng phát sinh:</span><b><?= count($rep['items']) ?></b></div>
        <div class="pb-kv"><span>Tổng SL món thực xuất (KDS):</span><b><?= number_format((int) $rep['total_kds_actual_qty']) ?> phần</b></div>
        <div class="pb-kv"><span>Món chờ trên bàn mở:</span><span><?= number_format((int) $rep['total_kds_pending_open_qty']) ?> phần</span></div>
        <div class="pb-kv"><span>Tổng tồn đầu + nhập (Kho):</span><span><?= number_format((int) $rep['total_warehouse_available_qty']) ?></span></div>
        <div class="pb-kv"><span>Tổng tồn lại cuối ngày (Kho):</span><b><?= number_format((int) $rep['total_warehouse_closing_qty']) ?></b></div>

        <div class="pb-rule-dashed"></div>
        <div class="pb-sec-title">II. CHI TIẾT MÓN ĐÃ BÁN HÔM NAY THEO DANH MỤC (BẾP NÓNG, RAU...)</div>
        <table class="pb-table">
          <thead>
            <tr>
              <th style="width: 34px;">STT</th>
              <th>Danh mục / Tên món đã bán</th>
              <th style="width: 82px;">SKU</th>
              <th class="pb-num" style="width: 88px;">Thực xuất KDS</th>
              <th class="pb-num" style="width: 90px;">Tồn đầu+Nhập</th>
              <th class="pb-num" style="width: 92px;">Tồn cuối ngày</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($rep['groups'])): ?>
              <tr><td colspan="6" class="pb-center">Hôm nay chưa có món nào bán được.</td></tr>
            <?php else: ?>
              <?php $stt = 1; ?>
              <?php foreach ($rep['groups'] as $group): ?>
                <tr>
                  <td colspan="6" style="font-weight: 700; border-top: 1px solid #000; border-bottom: 1px solid #000; padding-top: 6px;">
                    [ <?= htmlspecialchars(mb_strtoupper($group['group_label'], 'UTF-8')) ?> ]
                  </td>
                </tr>
                <?php foreach ($group['items'] as $item): ?>
                  <tr>
                    <td><?= $stt++ ?></td>
                    <td><?= htmlspecialchars($item['item_name']) ?></td>
                    <td><?= htmlspecialchars($item['sku'] !== '' ? $item['sku'] : '-') ?></td>
                    <td class="pb-num"><?= number_format((int) $item['kds_actual_qty']) ?></td>
                    <td class="pb-num"><?= $item['has_product'] ? number_format((int) $item['warehouse_available_qty']) : '-' ?></td>
                    <td class="pb-num"><?= $item['warehouse_closing_qty'] !== null ? number_format((int) $item['warehouse_closing_qty']) : '-' ?></td>
                  </tr>
                <?php endforeach; ?>
                <tr>
                  <td colspan="3" style="font-weight: 700; text-align: right;">Cộng <?= htmlspecialchars($group['group_label']) ?>:</td>
                  <td class="pb-num" style="font-weight: 700;"><?= number_format((int) $group['total_kds_qty']) ?></td>
                  <td class="pb-num"><?= number_format((int) $group['total_available_qty']) ?></td>
                  <td class="pb-num"><?= number_format((int) $group['total_closing_qty']) ?></td>
                </tr>
              <?php endforeach; ?>
              <tr class="pb-total-row">
                <td colspan="3">TỔNG CỘNG MÓN ĐÃ BÁN HÔM NAY</td>
                <td class="pb-num"><?= number_format((int) $rep['total_kds_actual_qty']) ?></td>
                <td class="pb-num"><?= number_format((int) $rep['total_warehouse_available_qty']) ?></td>
                <td class="pb-num"><?= number_format((int) $rep['total_warehouse_closing_qty']) ?></td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>

      <?php elseif ($viewMode === 'kitchen'):
        $kr = $ctx['kitchen'];
      ?>
        <div class="pb-sec-title">I. CHỈ SỐ HIỆU SUẤT NHÀ BẾP</div>
        <div class="pb-kv"><span>Tổng phần món hoàn thành:</span><b><?= number_format((int) ($kr['total_completed'] ?? 0)) ?></b></div>
        <div class="pb-kv"><span>Thời gian chế biến TB:</span><span><?= htmlspecialchars((string) ($kr['avg_cook_minutes'] ?? 0)) ?> phút</span></div>
        <div class="pb-kv"><span>Thời gian chờ phục vụ TB:</span><span><?= htmlspecialchars((string) ($kr['avg_wait_minutes'] ?? 0)) ?> phút</span></div>
        <div class="pb-kv"><span>Tỷ lệ ra món đúng giờ (&lt;15p):</span><b><?= htmlspecialchars((string) ($kr['on_time_rate'] ?? 0)) ?>%</b></div>
        <div class="pb-kv"><span>Số món bị trễ (&ge;15p):</span><span><?= number_format((int) ($kr['delayed_count'] ?? 0)) ?> phần</span></div>

        <div class="pb-rule-dashed"></div>
        <div class="pb-sec-title">II. CHI TIẾT THEO MÓN CHẾ BIẾN</div>
        <table class="pb-table">
          <thead>
            <tr>
              <th>Tên món</th>
              <th>Quầy</th>
              <th class="pb-num">SL món</th>
              <th class="pb-num">Nấu TB</th>
              <th class="pb-num">Chờ TB</th>
              <th class="pb-num">Đúng giờ</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($kr['dish_breakdown'])): ?>
              <tr><td colspan="6" class="pb-center">Chưa có món hoàn thành trong kỳ.</td></tr>
            <?php else: ?>
              <?php foreach ($kr['dish_breakdown'] as $d): ?>
                <tr>
                  <td><?= htmlspecialchars($d['item_name']) ?></td>
                  <td><?= htmlspecialchars($d['station_name']) ?></td>
                  <td class="pb-num"><?= number_format((int) $d['total_qty']) ?></td>
                  <td class="pb-num"><?= htmlspecialchars((string) $d['avg_cook_minutes']) ?>p</td>
                  <td class="pb-num"><?= htmlspecialchars((string) $d['avg_wait_minutes']) ?>p</td>
                  <td class="pb-num"><?= htmlspecialchars((string) $d['on_time_rate']) ?>%</td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>

      <?php else:
        $rep = $ctx['report'];
      ?>
        <div class="pb-sec-title">I. TỔNG HỢP DOANH THU</div>
        <div class="pb-kv"><span>Số hóa đơn thanh toán:</span><b><?= number_format((int) $rep['total_invoices']) ?></b></div>
        <div class="pb-kv"><span>Tổng phần món đã bán:</span><span><?= number_format((int) $rep['total_items_sold']) ?> phần</span></div>
        <div class="pb-kv"><span>Tiền hàng tạm tính:</span><span><?= $money($rep['total_subtotal']) ?></span></div>
        <div class="pb-kv"><span>Giảm giá / Khuyến mãi:</span><span>-<?= $money($rep['total_discount']) ?></span></div>
        <div class="pb-kv"><span>Phí phục vụ:</span><span>+<?= $money($rep['total_service_fee']) ?></span></div>
        <div class="pb-kv"><span>Thuế VAT:</span><span>+<?= $money($rep['total_vat']) ?></span></div>
        <div class="pb-rule-dashed"></div>
        <div class="pb-kv pb-grand-total"><span>DOANH THU THỰC NHẬN:</span><span><?= $money($rep['total_revenue']) ?></span></div>
        <div class="pb-kv"><span>Giá trị trung bình / đơn:</span><span><?= $money($rep['avg_order_value']) ?></span></div>

        <div class="pb-rule-dashed"></div>
        <div class="pb-sec-title">II. PHÂN TÍCH PHƯƠNG THỨC THANH TOÁN</div>
        <div class="pb-kv">
          <span>Chuyển khoản QR (<?= (int) $rep['payment_methods']['qr']['count'] ?> đơn - <?= $rep['payment_methods']['qr']['percent'] ?>%):</span>
          <b><?= $money($rep['payment_methods']['qr']['total']) ?></b>
        </div>
        <div class="pb-kv">
          <span>Tiền mặt (<?= (int) $rep['payment_methods']['cash']['count'] ?> đơn - <?= $rep['payment_methods']['cash']['percent'] ?>%):</span>
          <b><?= $money($rep['payment_methods']['cash']['total']) ?></b>
        </div>

        <div class="pb-rule-dashed"></div>
        <?php if ($viewMode === 'month' || $viewMode === 'range'): ?>
          <div class="pb-sec-title">III. CHI TIẾT THEO NGÀY</div>
          <table class="pb-table">
            <thead>
              <tr>
                <th>Ngày</th>
                <th>Thứ</th>
                <th class="pb-num">HĐ</th>
                <th class="pb-num">Tiền mặt</th>
                <th class="pb-num">QR CK</th>
                <th class="pb-num">Thực nhận</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($rep['daily_data'] as $d): ?>
                <tr>
                  <td><?= htmlspecialchars($d['date']) ?></td>
                  <td><?= htmlspecialchars($d['day_name']) ?></td>
                  <td class="pb-num"><?= (int) $d['invoices'] ?></td>
                  <td class="pb-num"><?= $money($d['cash_total']) ?></td>
                  <td class="pb-num"><?= $money($d['qr_total']) ?></td>
                  <td class="pb-num"><?= $money($d['revenue']) ?></td>
                </tr>
              <?php endforeach; ?>
              <tr class="pb-total-row">
                <td colspan="2">TỔNG CỘNG</td>
                <td class="pb-num"><?= (int) $rep['total_invoices'] ?></td>
                <td class="pb-num"><?= $money($rep['payment_methods']['cash']['total']) ?></td>
                <td class="pb-num"><?= $money($rep['payment_methods']['qr']['total']) ?></td>
                <td class="pb-num"><?= $money($rep['total_revenue']) ?></td>
              </tr>
            </tbody>
          </table>
        <?php else: ?>
          <div class="pb-sec-title">III. DANH SÁCH HÓA ĐƠN THANH TOÁN TRONG NGÀY</div>
          <table class="pb-table">
            <thead>
              <tr>
                <th>Mã HĐ</th>
                <th>Bàn</th>
                <th>Giờ TT</th>
                <th>PTTT</th>
                <th class="pb-num">Tổng thu</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($rep['invoices_list'])): ?>
                <tr><td colspan="5" class="pb-center">Chưa có hóa đơn thanh toán trong ngày.</td></tr>
              <?php else: ?>
                <?php foreach ($rep['invoices_list'] as $inv): ?>
                  <tr>
                    <td><?= htmlspecialchars($inv['code']) ?></td>
                    <td><?= htmlspecialchars($inv['table_code']) ?></td>
                    <td><?= htmlspecialchars($inv['paid_at']) ?></td>
                    <td><?= htmlspecialchars($inv['method'] === 'cash' ? 'Tiền mặt' : 'QR') ?></td>
                    <td class="pb-num"><?= $money($inv['total']) ?></td>
                  </tr>
                <?php endforeach; ?>
                <tr class="pb-total-row">
                  <td colspan="4">TỔNG DOANH THU TRONG NGÀY</td>
                  <td class="pb-num"><?= $money($rep['total_revenue']) ?></td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        <?php endif; ?>

        <?php if (!empty($rep['top_dishes'])): ?>
          <div class="pb-rule-dashed"></div>
          <div class="pb-sec-title">IV. TOP MÓN BÁN CHẠY</div>
          <table class="pb-table">
            <thead>
              <tr>
                <th style="width: 34px;">#</th>
                <th>Tên món ăn</th>
                <th class="pb-num" style="width: 72px;">SL bán</th>
                <th class="pb-num" style="width: 110px;">Thành tiền</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($rep['top_dishes'] as $idx => $td): ?>
                <tr>
                  <td><?= $idx + 1 ?></td>
                  <td><?= htmlspecialchars($td['item_name']) ?></td>
                  <td class="pb-num"><?= number_format((int) $td['total_qty']) ?></td>
                  <td class="pb-num"><?= $money($td['total_amount']) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      <?php endif; ?>

      <div class="pb-rule-double" style="margin-top: 14px;"></div>
      <div class="pb-signatures">
        <div>
          <b>Người lập / Quản lý ca</b>
          <div class="pb-sign-note">(Ký, ghi rõ họ tên)</div>
        </div>
        <div>
          <b>Bếp trưởng (KDS)</b>
          <div class="pb-sign-note">(Ký, ghi rõ họ tên)</div>
        </div>
        <div>
          <b>Thủ kho / Kiểm kê</b>
          <div class="pb-sign-note">(Ký, ghi rõ họ tên)</div>
        </div>
      </div>
      <div class="pb-rule-dashed" style="margin-top: 14px;"></div>
      <div class="pb-center pb-footer-note">--- HẾT BÁO CÁO · SMARTDINE POS ---</div>
    </section>
    <?php
    return (string) ob_get_clean();
}

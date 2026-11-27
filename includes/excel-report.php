<?php
/**
 * SmartDine - Xuất báo cáo Excel (.xlsx) với bảng kẻ viền đầy đủ (PhpSpreadsheet).
 */

require_once __DIR__ . '/revenue.php';
require_once __DIR__ . '/kitchen-metrics.php';
require_once __DIR__ . '/shift-reconciliation.php';
require_once __DIR__ . '/restaurant.php';

$vendorAutoload = __DIR__ . '/../warehouse/vendor/autoload.php';
if (is_file($vendorAutoload)) {
    require_once $vendorAutoload;
}

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Làm sạch chuỗi tránh Excel Formula Injection nhưng giữ nguyên giá trị số.
 */
function excelSafeCell($value)
{
    if (is_int($value) || is_float($value)) {
        return $value;
    }
    $text = (string) $value;
    if ($text !== '' && preg_match('/^[=+@\t\r]/', $text) === 1) {
        return "'" . $text;
    }
    if ($text !== '' && str_starts_with($text, '-') && !is_numeric($text)) {
        return "'" . $text;
    }
    return $text;
}

/**
 * Áp dụng viền kẻ bảng đều đặn (bảng kẻ) cho một vùng ô trong Excel.
 */
function applyExcelTableBorders($sheet, string $range, string $color = 'FF94A3B8', string $borderStyle = Border::BORDER_THIN): void
{
    $sheet->getStyle($range)->applyFromArray([
        'borders' => [
            'allBorders' => [
                'borderStyle' => $borderStyle,
                'color'       => ['argb' => $color],
            ],
            'outline' => [
                'borderStyle' => Border::BORDER_MEDIUM,
                'color'       => ['argb' => 'FF334155'],
            ],
        ],
    ]);
}

/**
 * Định dạng bảng dữ liệu chuẩn có tiêu đề cột đậm, nền, viền kẻ đầy đủ và dòng tổng.
 */
function writeBorderedExcelTable($sheet, int &$row, string $sectionTitle, array $headers, array $rows, ?array $totalRow = null, array $numberCols = []): void
{
    $colCount = count($headers);
    $lastCol = Coordinate::stringFromColumnIndex($colCount);

    $sheet->setCellValue("A{$row}", $sectionTitle);
    $sheet->mergeCells("A{$row}:{$lastCol}{$row}");
    $sheet->getStyle("A{$row}:{$lastCol}{$row}")->applyFromArray([
        'font' => ['bold' => true, 'size' => 11, 'color' => ['argb' => 'FF0F172A']],
    ]);
    $row++;

    $tableStartRow = $row;
    foreach ($headers as $idx => $h) {
        $colLetter = Coordinate::stringFromColumnIndex($idx + 1);
        $sheet->setCellValue("{$colLetter}{$row}", $h);
    }
    $sheet->getStyle("A{$row}:{$lastCol}{$row}")->applyFromArray([
        'font'      => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
        'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF1E293B']],
        'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'horizontal' => Alignment::HORIZONTAL_CENTER],
    ]);
    $sheet->getRowDimension($row)->setRowHeight(24);
    $row++;

    $dataStartRow = $row;
    foreach ($rows as $rIdx => $dataRow) {
        foreach ($dataRow as $cIdx => $val) {
            $colLetter = Coordinate::stringFromColumnIndex($cIdx + 1);
            $sheet->setCellValue("{$colLetter}{$row}", excelSafeCell($val));
            if (in_array($cIdx + 1, $numberCols, true) && is_numeric($val)) {
                $sheet->getStyle("{$colLetter}{$row}")->getNumberFormat()->setFormatCode('#,##0');
                $sheet->getStyle("{$colLetter}{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            }
        }
        $bgColor = ($rIdx % 2 === 1) ? 'FFF8FAFC' : 'FFFFFFFF';
        $sheet->getStyle("A{$row}:{$lastCol}{$row}")->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB($bgColor);
        $sheet->getRowDimension($row)->setRowHeight(20);
        $row++;
    }

    if ($totalRow !== null) {
        foreach ($totalRow as $cIdx => $val) {
            $colLetter = Coordinate::stringFromColumnIndex($cIdx + 1);
            $sheet->setCellValue("{$colLetter}{$row}", excelSafeCell($val));
            if (in_array($cIdx + 1, $numberCols, true) && is_numeric($val)) {
                $sheet->getStyle("{$colLetter}{$row}")->getNumberFormat()->setFormatCode('#,##0');
                $sheet->getStyle("{$colLetter}{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            }
        }
        $sheet->getStyle("A{$row}:{$lastCol}{$row}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FF9A3412']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFFFF7ED']],
        ]);
        $sheet->getRowDimension($row)->setRowHeight(22);
        $row++;
    }

    $tableEndRow = max($tableStartRow, $row - 1);
    if ($row === $dataStartRow) {
        $sheet->setCellValue("A{$row}", 'Không có dữ liệu trong kỳ thống kê.');
        $sheet->mergeCells("A{$row}:{$lastCol}{$row}");
        $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $tableEndRow = $row;
        $row++;
    }

    applyExcelTableBorders($sheet, "A{$tableStartRow}:{$lastCol}{$tableEndRow}", 'FF475569');
    $sheet->getStyle("A{$tableStartRow}:{$lastCol}{$tableEndRow}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
    $row++;
}

/**
 * Tạo đối tượng Spreadsheet có bảng kẻ sẵn cho 5 chế độ báo cáo của admin.php.
 */
function buildAdminReportSpreadsheet(string $viewMode, array $payload): Spreadsheet
{
    $restaurant = getRestaurantInfo();
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setShowGridlines(true);

    $row = 1;
    $titleText = match ($viewMode) {
        'reconcile' => 'BÁO CÁO MÓN THỰC XUẤT CUỐI NGÀY (KẾT CA) & ĐỐI CHIẾU TỒN KHO',
        'kitchen'   => 'BÁO CÁO HIỆU SUẤT BẾP (KDS PERFORMANCE)',
        'month'     => 'BÁO CÁO DOANH THU THÁNG ' . ($payload['month_display'] ?? ''),
        'range'     => 'BÁO CÁO DOANH THU KỲ ' . ($payload['date_display'] ?? ''),
        default     => 'BÁO CÁO DOANH THU NGÀY ' . ($payload['date_display'] ?? ''),
    };

    $maxMergeCol = $viewMode === 'kitchen' ? 'J' : ($viewMode === 'reconcile' ? 'G' : 'J');
    $sheet->setCellValue("A{$row}", strtoupper((string) ($restaurant['name'] ?? 'SMARTDINE')) . ' — ' . $titleText);
    $sheet->mergeCells("A{$row}:{$maxMergeCol}{$row}");
    $sheet->getStyle("A{$row}:{$maxMergeCol}{$row}")->applyFromArray([
        'font'      => ['bold' => true, 'size' => 13, 'color' => ['argb' => 'FFFFFFFF']],
        'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFEA580C']],
        'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'horizontal' => Alignment::HORIZONTAL_LEFT],
    ]);
    $sheet->getRowDimension($row)->setRowHeight(28);
    $row++;

    $sheet->setCellValue("A{$row}", 'Xuất lúc: ' . date('d/m/Y H:i:s'));
    $sheet->mergeCells("A{$row}:{$maxMergeCol}{$row}");
    $row += 2;

    if ($viewMode === 'reconcile') {
        $rep = $payload['reconcile'];
        $summaryRows = [
            ['Ngày báo cáo', $rep['date_display'] . ' (' . $rep['day_name'] . ')'],
            ['Tổng SL món thực xuất KDS', $rep['total_kds_actual_qty']],
            ['Món đang chờ trên bàn mở', $rep['total_kds_pending_open_qty']],
            ['Tổng tồn lại cuối ngày (Kho)', $rep['total_warehouse_closing_qty']],
            ['Mặt hàng khớp / có trong Kho / chưa có trong Kho', $rep['matched_count'] . ' / ' . $rep['tracked_in_warehouse_count'] . ' / ' . $rep['unmapped_count']],
        ];
        writeBorderedExcelTable($sheet, $row, 'I. TỔNG HỢP CUỐI CA', ['Chỉ tiêu', 'Giá trị'], $summaryRows, null, [2]);

        $detailRows = [];
        foreach ($rep['groups'] ?? [] as $group) {
            foreach ($group['items'] ?? [] as $item) {
                $availText = $item['has_product'] ? (int) $item['warehouse_available_qty'] : '-';
                $closingText = $item['warehouse_closing_qty'] !== null ? (int) $item['warehouse_closing_qty'] : '-';
                $detailRows[] = [
                    $group['group_label'],
                    $item['item_name'],
                    $item['sku'] !== '' ? $item['sku'] : '-',
                    (int) $item['kds_actual_qty'],
                    $availText,
                    $closingText,
                    $item['status_label'],
                ];
            }
            $detailRows[] = [
                'Cộng ' . $group['group_label'],
                '',
                '',
                (int) $group['total_kds_qty'],
                (int) $group['total_available_qty'],
                (int) $group['total_closing_qty'],
                count($group['items'] ?? []) . ' món',
            ];
        }
        writeBorderedExcelTable(
            $sheet,
            $row,
            'II. BẢNG KÊ MÓN ĐÃ BÁN TRONG NGÀY THEO DANH MỤC (BẾP NÓNG, RAU...) & TỒN KHO',
            ['Danh mục / Quầy', 'Tên món đã bán', 'SKU', 'Thực xuất KDS', 'Tồn đầu + Nhập (Kho)', 'Tồn lại cuối ngày (Kho)', 'Trạng thái'],
            $detailRows,
            [
                'TỔNG CỘNG CUỐI CA',
                '',
                '',
                (int) $rep['total_kds_actual_qty'],
                (int) $rep['total_warehouse_available_qty'],
                (int) $rep['total_warehouse_closing_qty'],
                count($rep['items'] ?? []) . ' món',
            ],
            [4, 5, 6]
        );
    } elseif ($viewMode === 'kitchen') {
        $kr = $payload['kitchen'];
        $summaryRows = [
            ['Khoảng thời gian', date('d/m/Y', strtotime($payload['start_date'])) . ' – ' . date('d/m/Y', strtotime($payload['end_date']))],
            ['Thời gian chế biến TB (phút)', $kr['avg_cook_minutes'] ?? 0],
            ['Thời gian chờ phục vụ TB (phút)', $kr['avg_wait_minutes'] ?? 0],
            ['Tổng phần món hoàn thành', $kr['total_completed'] ?? 0],
            ['Tỷ lệ ra món đúng giờ (%)', ($kr['on_time_rate'] ?? 0) . '%'],
            ['Số món trễ (>= 15 phút)', $kr['delayed_count'] ?? 0],
        ];
        writeBorderedExcelTable($sheet, $row, 'I. TỔNG QUAN HIỆU SUẤT BẾP', ['Chỉ tiêu', 'Kết quả'], $summaryRows, null, [2]);

        $dishRows = [];
        foreach ($kr['dish_breakdown'] ?? [] as $d) {
            $dishRows[] = [
                $d['item_name'],
                $d['station_name'],
                (int) $d['total_cooked'],
                (int) $d['total_qty'],
                $d['min_cook_minutes'],
                $d['avg_cook_minutes'],
                $d['max_cook_minutes'],
                $d['avg_wait_minutes'],
                $d['on_time_rate'] . '%',
                $d['badge']['text'] ?? '',
            ];
        }
        writeBorderedExcelTable(
            $sheet,
            $row,
            'II. CHI TIẾT THỜI GIAN CHẾ BIẾN TỪNG MÓN',
            ['Tên món ăn', 'Quầy bếp', 'Số lượt', 'Tổng SL', 'Nhanh nhất (p)', 'Chế biến TB (p)', 'Lâu nhất (p)', 'Chờ TB (p)', 'Đúng giờ', 'Đánh giá'],
            $dishRows,
            null,
            [3, 4, 5, 6, 7, 8]
        );
    } else {
        $rep = $payload['report'];
        $summaryRows = [
            ['Tổng doanh thu thực nhận (đ)', (int) $rep['total_revenue']],
            ['Tổng tiền hàng tạm tính (đ)', (int) $rep['total_subtotal']],
            ['Giảm giá / Khuyến mãi (đ)', (int) $rep['total_discount']],
            ['Phí phục vụ & VAT (đ)', (int) ($rep['total_service_fee'] + $rep['total_vat'])],
            ['Tổng số hóa đơn', (int) $rep['total_invoices']],
            ['Giá trị trung bình/đơn (đ)', (int) $rep['avg_order_value']],
            ['Tổng phần món đã bán', (int) $rep['total_items_sold']],
            ['Doanh thu Chuyển khoản QR (đ)', (int) $rep['payment_methods']['qr']['total']],
            ['Doanh thu Tiền mặt (đ)', (int) $rep['payment_methods']['cash']['total']],
        ];
        writeBorderedExcelTable($sheet, $row, 'I. TỔNG HỢP DOANH THU', ['Chỉ tiêu', 'Giá trị'], $summaryRows, null, [2]);

        if ($viewMode === 'month' || $viewMode === 'range') {
            $dailyRows = [];
            foreach ($rep['daily_data'] as $d) {
                $dailyRows[] = [
                    $d['date'],
                    $d['day_name'],
                    (int) $d['invoices'],
                    (int) $d['subtotal'],
                    (int) $d['discount'],
                    (int) $d['tax_fee'],
                    (int) $d['revenue'],
                    (int) $d['cash_total'],
                    (int) $d['qr_total'],
                    (int) $d['avg_order'],
                ];
            }
            writeBorderedExcelTable(
                $sheet,
                $row,
                'II. BẢNG CHI TIẾT DOANH THU TỪNG NGÀY',
                ['Ngày', 'Thứ', 'Số HĐ', 'Tạm tính', 'Giảm giá', 'Thuế & Phí', 'Thực nhận', 'Tiền mặt', 'QR CK', 'TB/Đơn'],
                $dailyRows,
                [
                    'TỔNG CỘNG',
                    '-',
                    (int) $rep['total_invoices'],
                    (int) $rep['total_subtotal'],
                    (int) $rep['total_discount'],
                    (int) ($rep['total_service_fee'] + $rep['total_vat']),
                    (int) $rep['total_revenue'],
                    (int) $rep['payment_methods']['cash']['total'],
                    (int) $rep['payment_methods']['qr']['total'],
                    (int) $rep['avg_order_value'],
                ],
                [3, 4, 5, 6, 7, 8, 9, 10]
            );
        } else {
            $invRows = [];
            foreach ($rep['invoices_list'] as $inv) {
                $invRows[] = [
                    $inv['code'],
                    $inv['table_code'],
                    $inv['paid_at'],
                    $inv['cashier_name'],
                    $inv['method_label'],
                    (int) $inv['subtotal'],
                    (int) $inv['discount_amount'],
                    (int) $inv['service_fee'],
                    (int) $inv['vat'],
                    (int) $inv['total'],
                ];
            }
            writeBorderedExcelTable(
                $sheet,
                $row,
                'II. DANH SÁCH HÓA ĐƠN ĐÃ THANH TOÁN TRONG NGÀY',
                ['Mã HĐ', 'Bàn', 'Giờ TT', 'Thu ngân', 'Phương thức', 'Tạm tính', 'Giảm giá', 'Phí DV', 'VAT', 'Tổng thu'],
                $invRows,
                [
                    'TỔNG CỘNG',
                    '-',
                    '-',
                    '-',
                    '-',
                    (int) $rep['total_subtotal'],
                    (int) $rep['total_discount'],
                    (int) $rep['total_service_fee'],
                    (int) $rep['total_vat'],
                    (int) $rep['total_revenue'],
                ],
                [6, 7, 8, 9, 10]
            );
        }

        $topRows = [];
        foreach ($rep['top_dishes'] ?? [] as $idx => $td) {
            $topRows[] = [
                $idx + 1,
                $td['item_name'],
                (int) $td['total_qty'],
                (int) $td['total_amount'],
            ];
        }
        writeBorderedExcelTable(
            $sheet,
            $row,
            'III. TOP MÓN ĂN BÁN CHẠY',
            ['Hạng', 'Tên món ăn', 'Số lượng (phần)', 'Doanh thu (đ)'],
            $topRows,
            null,
            [1, 3, 4]
        );
    }

    $highestColIndex = Coordinate::columnIndexFromString($sheet->getHighestColumn());
    for ($c = 1; $c <= $highestColIndex; $c++) {
        $colLetter = Coordinate::stringFromColumnIndex($c);
        $sheet->getColumnDimension($colLetter)->setAutoSize(true);
    }

    return $spreadsheet;
}

/**
 * Gửi tệp Excel (.xlsx) có bảng kẻ trực tiếp về trình duyệt.
 */
function exportAdminReportExcel(string $viewMode, array $payload): void
{
    if (!class_exists(Spreadsheet::class)) {
        // Dự phòng CSV sạch nếu thư viện PhpSpreadsheet không sẵn sàng
        if ($viewMode === 'reconcile') {
            exportShiftReconciliationCsv($payload['reconcile']);
        } elseif ($viewMode === 'kitchen') {
            exportKitchenCsv($payload['kitchen'], $payload['start_date'], $payload['end_date']);
        } else {
            exportRevenueCsv($payload['report']);
        }
    }

    $spreadsheet = buildAdminReportSpreadsheet($viewMode, $payload);

    $filename = match ($viewMode) {
        'reconcile' => 'Bao_cao_thuc_xuat_ket_ca_' . str_replace('-', '_', $payload['reconcile']['date']) . '.xlsx',
        'kitchen'   => 'Bao_cao_hieu_suat_bep_' . str_replace('-', '', $payload['start_date']) . '_den_' . str_replace('-', '', $payload['end_date']) . '.xlsx',
        'range'     => 'Bao_cao_doanh_thu_' . str_replace('-', '', $payload['report']['start_date']) . '_den_' . str_replace('-', '', $payload['report']['end_date']) . '.xlsx',
        'month'     => 'Bao_cao_doanh_thu_thang_' . str_replace('-', '_', $payload['report']['year_month']) . '.xlsx',
        default     => 'Bao_cao_doanh_thu_ngay_' . str_replace('-', '_', $payload['report']['date']) . '.xlsx',
    };

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    $safeName = str_replace(["\r", "\n", '"'], '', $filename);
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $safeName . '"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    $spreadsheet->disconnectWorksheets();
    unset($spreadsheet);
    exit;
}

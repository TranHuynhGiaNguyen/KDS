<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(WAREHOUSE_ALLOWED_ROLES);
require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Mẫu Nhập Kho');

$headers = [
    'A1' => 'Mã',
    'B1' => 'Tên SP',
    'C1' => 'Ngày tháng năm nhập',
    'D1' => 'Số lượng',
];

foreach ($headers as $cell => $value) {
    $sheet->setCellValue($cell, $value);
}

$todayDisplay = date('d/m/Y');
$sampleData = [
    ['BO01', 'Bò áp chảo', $todayDisplay, 20],
    ['SL01', 'Salad hoa quả', $todayDisplay, 15],
    ['RAU01', 'Rau muống xào tỏi', $todayDisplay, 30],
];

$row = 2;
foreach ($sampleData as $data) {
    $sheet->setCellValue('A' . $row, $data[0]);
    $sheet->setCellValue('B' . $row, $data[1]);
    $sheet->setCellValue('C' . $row, $data[2]);
    $sheet->setCellValue('D' . $row, $data[3]);
    $row++;
}

$sheet->getStyle('A1:D1')->applyFromArray([
    'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'EA580C']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '334155']]],
]);

$sheet->getStyle('A2:D' . ($row - 1))->applyFromArray([
    'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '94A3B8']]],
    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
]);

$sheet->getStyle('C2:C' . ($row - 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle('D2:D' . ($row - 1))->getNumberFormat()->setFormatCode('#,##0');

foreach (range('A', 'D') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

$noteRow = $row + 2;
$sheet->setCellValue('A' . $noteRow, 'GHI CHÚ:');
$sheet->getStyle('A' . $noteRow)->getFont()->setBold(true);

$notes = [
    '• File mẫu gồm đúng 4 cột: Mã, Tên SP, Ngày tháng năm nhập, Số lượng.',
    '• Định dạng Ngày tháng năm nhập: dd/mm/yyyy (ví dụ 27/11/2026).',
    '• Xóa các dòng mẫu trước khi nhập dữ liệu thực tế.',
];

$noteRow++;
foreach ($notes as $note) {
    $sheet->setCellValue('A' . $noteRow, $note);
    $sheet->mergeCells('A' . $noteRow . ':D' . $noteRow);
    $sheet->getStyle('A' . $noteRow)->getFont()->setSize(10);
    $noteRow++;
}

clear_output_buffers();
$filename = 'Mẫu import tồn kho.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header(content_disposition_header($filename));
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
$spreadsheet->disconnectWorksheets();
unset($spreadsheet);
exit;

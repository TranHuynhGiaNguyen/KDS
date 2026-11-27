<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(WAREHOUSE_MANAGE_ROLES);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../vendor/autoload.php';

$message = '';
$error = '';
$import_results = [];
$selectedMode = 'add';

/**
 * Xử lý import một dòng dữ liệu 4 cột: [Mã, Tên SP, Ngày tháng năm nhập, Số lượng].
 */
if (!function_exists('warehouse_process_import_row')) {
function warehouse_process_import_row(mysqli $conn, array $row, int $rowNum, string $mode, string $actor): array
{
    $sku = trim((string) ($row[0] ?? ''));
    $name = trim((string) ($row[1] ?? ''));
    $rawDate = $row[2] ?? '';
    $rawQty = trim((string) ($row[3] ?? ''));

    if ($sku === '' || $name === '') {
        return ['row' => $rowNum, 'status' => 'error', 'message' => 'Thiếu Mã hoặc Tên SP'];
    }
    if (mb_strlen($sku) > 50 || mb_strlen($name) > 100) {
        return ['row' => $rowNum, 'status' => 'error', 'message' => "Mã hoặc Tên SP vượt quá độ dài (Mã: $sku)"];
    }

    $importDate = parse_warehouse_import_date($rawDate);
    if ($importDate === null) {
        return ['row' => $rowNum, 'status' => 'error', 'message' => "Ngày tháng năm nhập không hợp lệ (Mã: $sku, hãy dùng dd/mm/yyyy)"];
    }

    $parsedQty = filter_var($rawQty === '' ? '0' : str_replace([',', ' '], '', $rawQty), FILTER_VALIDATE_INT);
    if ($parsedQty === false || $parsedQty <= 0) {
        return ['row' => $rowNum, 'status' => 'error', 'message' => "Số lượng nhập phải là số nguyên > 0 (Mã: $sku)"];
    }

    $quantity = (int) $parsedQty;
    $txTimestamp = $importDate . ' ' . date('H:i:s');

    $conn->begin_transaction();
    try {
        $checkStmt = $conn->prepare('SELECT id, quantity FROM products WHERE sku = ? FOR UPDATE');
        $checkStmt->bind_param('s', $sku);
        $checkStmt->execute();
        $existing = $checkStmt->get_result()->fetch_assoc();
        $checkStmt->close();

        if ($existing) {
            $pid = (int) $existing['id'];
            $oldQty = (int) $existing['quantity'];

            if ($mode === 'skip') {
                $conn->rollback();
                return ['row' => $rowNum, 'status' => 'skipped', 'message' => "Đã tồn tại, bỏ qua (Mã: $sku)"];
            }

            $newQty = ($mode === 'add') ? ($oldQty + $quantity) : $quantity;
            $upd = $conn->prepare('UPDATE products SET name = ?, quantity = ? WHERE id = ?');
            $upd->bind_param('sii', $name, $newQty, $pid);
            $upd->execute();
            $upd->close();

            if ($mode === 'add') {
                record_stock_transaction($conn, $pid, 'in', $quantity, 'Import Excel ngày ' . date('d/m/Y', strtotime($importDate)), null, $actor, $txTimestamp);
            } else {
                $delta = $newQty - $oldQty;
                if ($delta !== 0) {
                    record_stock_transaction(
                        $conn,
                        $pid,
                        $delta > 0 ? 'in' : 'out',
                        abs($delta),
                        'Import Excel (cập nhật tồn) ngày ' . date('d/m/Y', strtotime($importDate)),
                        null,
                        $actor,
                        $txTimestamp
                    );
                }
            }

            $conn->commit();
            return ['row' => $rowNum, 'status' => 'updated', 'message' => "Nhập +{$quantity} cho {$name} (Mã: $sku, ngày " . date('d/m/Y', strtotime($importDate)) . ')'];
        }

        $zeroPrice = 0.0;
        $ins = $conn->prepare('INSERT INTO products (sku, name, quantity, price, created_at) VALUES (?, ?, ?, ?, ?)');
        $ins->bind_param('ssids', $sku, $name, $quantity, $zeroPrice, $txTimestamp);
        $ins->execute();
        $newId = (int) $conn->insert_id;
        $ins->close();

        record_stock_transaction($conn, $newId, 'in', $quantity, 'Import Excel ngày ' . date('d/m/Y', strtotime($importDate)), null, $actor, $txTimestamp);

        $conn->commit();
        return ['row' => $rowNum, 'status' => 'created', 'message' => "Tạo mới & nhập {$quantity} {$name} (Mã: $sku, ngày " . date('d/m/Y', strtotime($importDate)) . ')'];
    } catch (Throwable $ex) {
        $conn->rollback();
        error_log("import row $rowNum error: " . $ex->getMessage());
        return ['row' => $rowNum, 'status' => 'error', 'message' => "Lỗi lưu dữ liệu (Mã: $sku)"];
    }
}
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $selectedMode = in_array($_POST['import_mode'] ?? 'add', ['add', 'update', 'skip'], true)
        ? (string) $_POST['import_mode']
        : 'add';

    if (!isset($_FILES['excel_file'])) {
        $error = 'Vui lòng chọn file Excel cần import.';
    } else {
        $file = $_FILES['excel_file'];
        $uploadErr = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        $fileName = (string) ($file['name'] ?? '');
        $fileTmp = (string) ($file['tmp_name'] ?? '');
        $fileSize = (int) ($file['size'] ?? 0);
        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $maxBytes = 10 * 1024 * 1024;

        $allowedMimes = [
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-excel',
            'application/zip',
            'application/x-zip-compressed',
            'application/cdfv2',
            'application/octet-stream',
        ];

        $detectedMime = '';
        if ($uploadErr === UPLOAD_ERR_OK && $fileTmp !== '' && is_uploaded_file($fileTmp)) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                $detectedMime = (string) finfo_file($finfo, $fileTmp);
                finfo_close($finfo);
            }
        }

        if ($uploadErr !== UPLOAD_ERR_OK) {
            $error = 'Upload file thất bại (mã lỗi: ' . $uploadErr . ').';
        } elseif ($fileSize <= 0 || $fileSize > $maxBytes) {
            $error = 'Dung lượng file phải lớn hơn 0 và không vượt quá 10MB.';
        } elseif (!in_array($fileExt, ['xlsx', 'xls'], true)) {
            $error = 'Chỉ chấp nhận file Excel (.xlsx, .xls).';
        } elseif ($detectedMime === '' || !in_array($detectedMime, $allowedMimes, true)) {
            $error = 'Định dạng MIME của file không hợp lệ cho Excel.';
        } else {
            $tmpBase = tempnam(sys_get_temp_dir(), 'wh_imp_');
            $uploadPath = $tmpBase !== false ? ($tmpBase . '.' . $fileExt) : '';
            if ($tmpBase !== false && is_file($tmpBase)) {
                @unlink($tmpBase);
            }

            if ($uploadPath === '' || !move_uploaded_file($fileTmp, $uploadPath)) {
                $error = 'Không thể lưu file tạm để xử lý.';
            } else {
                try {
                    $readerType = $fileExt === 'xlsx' ? 'Xlsx' : 'Xls';
                    $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader($readerType);
                    $reader->setReadDataOnly(true);
                    $spreadsheet = $reader->load($uploadPath);
                    $rows = $spreadsheet->getActiveSheet()->toArray();

                    array_shift($rows);

                    $success_count = 0;
                    $error_count = 0;
                    $actor = current_username();

                    foreach ($rows as $index => $row) {
                        $rowNum = $index + 2;
                        $nonEmpty = array_filter($row, static fn($cell) => trim((string) ($cell ?? '')) !== '');
                        if (empty($nonEmpty)) {
                            continue;
                        }

                        $firstCell = trim((string) ($row[0] ?? ''));
                        if ($firstCell === 'GHI CHÚ:' || strpos($firstCell, '•') === 0) {
                            continue;
                        }

                        $res = warehouse_process_import_row($conn, $row, $rowNum, $selectedMode, $actor);
                        $import_results[] = $res;
                        if (in_array($res['status'], ['created', 'updated'], true)) {
                            $success_count++;
                        } elseif ($res['status'] === 'error') {
                            $error_count++;
                        }
                    }

                    $message = "Import hoàn tất! Thành công: $success_count, Lỗi: $error_count";
                } catch (Throwable $e) {
                    error_log('inventory_import error: ' . $e->getMessage());
                    $error = 'Không thể đọc file Excel. Vui lòng kiểm tra lại định dạng file.';
                } finally {
                    if (is_file($uploadPath)) {
                        @unlink($uploadPath);
                    }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Import Excel nhập kho — SmartDine Kho</title>
  <link rel="stylesheet" href="<?= e(asset_url('assets/css/style.css')) ?>">
  <link rel="stylesheet" href="<?= e(asset_url('assets/css/header.css')) ?>">
  <link rel="stylesheet" href="<?= e(asset_url('assets/css/inventory_import.css')) ?>">
</head>
<body class="dashboard-body">

<?php include __DIR__ . '/../includes/sidebar.php'; ?>

<main class="dashboard-main">
  <?php include __DIR__ . '/../includes/header.php'; ?>

  <div class="dash-header">
    <div>
      <span class="eyebrow">SMARTDINE EXCEL IMPORT</span>
      <h1>📥 Import Nhập kho từ Excel</h1>
      <p>Nhập kho nhanh từ bảng tính Excel gồm đúng 4 cột: Mã, Tên SP, Ngày tháng năm nhập và Số lượng.</p>
    </div>
    <div class="header-action-group">
      <a href="<?= e(warehouse_url('inventory/download_template.php')) ?>" class="btn btn-outline">📄 Tải file mẫu (4 cột)</a>
      <a href="<?= e(warehouse_url('inventory/inventory.php')) ?>" class="btn btn-outline">← Quay lại tồn kho</a>
    </div>
  </div>

  <?php if ($message !== ''): ?>
    <div class="flash-alert flash-success">✅ <?= e($message) ?></div>
  <?php endif; ?>

  <?php if ($error !== ''): ?>
    <div class="flash-alert flash-error">❌ <?= e($error) ?></div>
  <?php endif; ?>

  <div class="panel">
    <div class="panel-title">
      <h2>📤 Tải lên tệp Excel</h2>
    </div>

    <form method="post" enctype="multipart/form-data" id="importForm">
      <?= csrf_field() ?>
      <div class="upload-area" id="uploadArea">
        <div class="upload-icon">📁</div>
        <div class="upload-text">Kéo thả file Excel vào đây hoặc bấm để chọn</div>
        <div class="upload-hint">Hỗ trợ: .xlsx, .xls (Tối đa 10MB)</div>
        <input type="file" name="excel_file" accept=".xlsx,.xls" class="file-input" id="fileInput" required>
      </div>

      <div class="import-options">
        <div class="option-group">
          <label>Khi sản phẩm trùng Mã đã có trong Kho:</label>
          <div class="radio-group">
            <label class="radio-option">
              <input type="radio" name="import_mode" value="add" id="mode_add" <?= $selectedMode === 'add' ? 'checked' : '' ?>>
              <span>Cộng dồn (Cộng thêm số lượng nhập vào tồn hiện tại)</span>
            </label>
            <label class="radio-option">
              <input type="radio" name="import_mode" value="update" id="mode_update" <?= $selectedMode === 'update' ? 'checked' : '' ?>>
              <span>Ghi đè (Đặt lại tồn bằng số lượng trong file)</span>
            </label>
            <label class="radio-option">
              <input type="radio" name="import_mode" value="skip" id="mode_skip" <?= $selectedMode === 'skip' ? 'checked' : '' ?>>
              <span>Bỏ qua (Giữ nguyên nếu Mã đã tồn tại)</span>
            </label>
          </div>
        </div>
      </div>

      <button type="submit" class="btn btn-primary" style="width: 100%; padding: 13px; font-size: 14px;">
        🚀 Bắt đầu Import dữ liệu
      </button>
    </form>
  </div>

  <?php if (!empty($import_results)): ?>
    <div class="panel results-container">
      <div class="panel-title">
        <h2>📊 Kết quả Import</h2>
      </div>

      <?php
      $total = count($import_results);
      $success = count(array_filter($import_results, static fn($r) => in_array($r['status'], ['created', 'updated'], true)));
      $errors = count(array_filter($import_results, static fn($r) => $r['status'] === 'error'));
      $skipped = count(array_filter($import_results, static fn($r) => $r['status'] === 'skipped'));
      $progress = $total > 0 ? ($success / $total) * 100 : 0;
      ?>

      <div style="display: flex; justify-content: space-between; margin-bottom: 10px; color: var(--text-secondary); font-size: 13.5px; flex-wrap:wrap; gap:10px;">
        <span>Tổng: <strong><?= $total ?></strong></span>
        <span>Thành công: <strong style="color: var(--green);"><?= $success ?></strong></span>
        <span>Lỗi: <strong style="color: var(--red);"><?= $errors ?></strong></span>
        <span>Bỏ qua: <strong><?= $skipped ?></strong></span>
      </div>

      <div class="progress-bar">
        <div class="progress-fill" style="width: <?= (float) $progress ?>%;"></div>
      </div>

      <div style="max-height: 300px; overflow-y: auto; margin-top: 12px;">
        <?php foreach ($import_results as $result): ?>
          <div class="result-item">
            <span class="result-status <?= e($result['status']) ?>">
              <?= e(ucfirst($result['status'])) ?>
            </span>
            <span style="color: var(--muted); min-width: 64px;">Dòng <?= (int) $result['row'] ?>:</span>
            <span style="color: var(--ink);"><?= e($result['message']) ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>

  <div class="panel" id="sample">
    <div class="panel-title">
      <h2>📋 Cấu trúc file Excel mẫu (4 cột)</h2>
    </div>
    <p style="color: var(--text-secondary); font-size: 13.5px; margin-bottom: 14px;">
      Dòng đầu tiên là tiêu đề. Toàn bộ 4 cột đều bắt buộc; cột ngày dùng định dạng <code>dd/mm/yyyy</code> (hoặc ô ngày chuẩn Excel).
    </p>

    <div class="table-responsive">
      <table class="inventory-table">
        <thead>
          <tr>
            <th>Mã *</th>
            <th>Tên SP *</th>
            <th>Ngày tháng năm nhập *</th>
            <th>Số lượng *</th>
          </tr>
        </thead>
        <tbody>
          <tr><td><span class="sku-badge">BO01</span></td><td>Bò áp chảo</td><td><?= e(date('d/m/Y')) ?></td><td>20</td></tr>
          <tr><td><span class="sku-badge">SL01</span></td><td>Salad hoa quả</td><td><?= e(date('d/m/Y')) ?></td><td>15</td></tr>
          <tr><td><span class="sku-badge">RAU01</span></td><td>Rau muống xào tỏi</td><td><?= e(date('d/m/Y')) ?></td><td>30</td></tr>
        </tbody>
      </table>
    </div>
  </div>

  <?php include __DIR__ . '/../includes/footer.php'; ?>
</main>

<script>
const uploadArea = document.getElementById('uploadArea');
const fileInput = document.getElementById('fileInput');

uploadArea.addEventListener('dragover', (e) => {
    e.preventDefault();
    uploadArea.classList.add('dragover');
});

uploadArea.addEventListener('dragleave', () => {
    uploadArea.classList.remove('dragover');
});

uploadArea.addEventListener('drop', (e) => {
    e.preventDefault();
    uploadArea.classList.remove('dragover');
    const files = e.dataTransfer.files;
    if (files.length > 0) {
        fileInput.files = files;
        updateFileName(files[0].name);
    }
});

fileInput.addEventListener('change', (e) => {
    if (e.target.files.length > 0) {
        updateFileName(e.target.files[0].name);
    }
});

function updateFileName(fileName) {
    const uploadText = uploadArea.querySelector('.upload-text');
    uploadText.textContent = `Đã chọn: ${fileName}`;
    uploadArea.style.borderColor = '#f47b08';
    uploadArea.style.background = '#fff3e6';
}

document.getElementById('importForm').addEventListener('submit', function() {
    const button = this.querySelector('button[type="submit"]');
    button.textContent = '⏳ Đang xử lý...';
    button.disabled = true;
});
</script>

</body>
</html>

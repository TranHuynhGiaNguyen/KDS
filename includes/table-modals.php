<?php
/**
 * SmartDine - Modal dùng chung cho Gán bàn & Chuyển bàn
 * Dùng tại Sơ đồ bàn (staff.php) và Chi tiết bàn (table-order.php).
 */
$modalAllActiveTables = getAllDiningTables('', 'active', '');
$modalStaffList       = getAssignableStaffList();
$currentUserId        = (int) ($_SESSION['user_id'] ?? 0);

$emptyActiveTables    = array_values(array_filter($modalAllActiveTables, static fn(array $t): bool => empty($t['is_occupied'])));
$occupiedActiveTables = array_values(array_filter($modalAllActiveTables, static fn(array $t): bool => !empty($t['is_occupied'])));
?>

<!-- MODAL GÁN BÀN / MỞ BÀN -->
<div class="custom-modal-backdrop" id="modal-assign-table" style="display: none;">
  <div class="custom-modal-dialog custom-modal-sm">
    <div class="custom-modal-header">
      <div class="modal-title-wrap">
        <div>
          <h3 id="assign-modal-title">Gán bàn cho khách</h3>
          <p id="assign-modal-subtitle">Phân bổ bàn, số lượng khách và nhân viên phục vụ</p>
        </div>
      </div>
      <button type="button" class="btn-close-modal" data-close-table-modal="modal-assign-table" aria-label="Đóng">✕</button>
    </div>
    <form class="modal-form" id="form-assign-table">
      <input type="hidden" name="allow_update" id="assign-allow-update" value="0">
      <div class="form-group">
        <label>Chọn bàn <span class="required">*</span></label>
        <select name="table_code" id="assign-table-code" required>
          <option value="">-- Chọn bàn đang sẵn sàng --</option>
          <?php foreach ($modalAllActiveTables as $mt): ?>
            <option value="<?= htmlspecialchars($mt['code']) ?>"
                    data-occupied="<?= !empty($mt['is_occupied']) ? '1' : '0' ?>"
                    data-capacity="<?= (int) $mt['capacity'] ?>">
              Bàn <?= htmlspecialchars($mt['code']) ?> — <?= htmlspecialchars($mt['area_name']) ?> (<?= (int) $mt['capacity'] ?> chỗ)<?= !empty($mt['is_occupied']) ? ' [Đang phục vụ]' : ' [Trống]' ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-grid-2">
        <div class="form-group">
          <label>Số lượng khách <span class="required">*</span></label>
          <input type="number" name="guest_count" id="assign-guest-count" min="1" max="100" value="2" required>
        </div>
        <div class="form-group">
          <label>Nhân viên phụ trách</label>
          <select name="staff_id" id="assign-staff-id">
            <option value="0">-- Chưa phân công --</option>
            <?php foreach ($modalStaffList as $st): ?>
              <option value="<?= (int) $st['id'] ?>" <?= (int) $st['id'] === $currentUserId ? 'selected' : '' ?>>
                <?= htmlspecialchars($st['fullname'] ?: $st['username']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="form-grid-2">
        <div class="form-group">
          <label>Tên khách / Nhóm khách</label>
          <input type="text" name="customer_name" id="assign-customer-name" placeholder="VD: Anh Minh / Khách lẻ" maxlength="100">
        </div>
        <div class="form-group">
          <label>Số điện thoại</label>
          <input type="tel" name="customer_phone" id="assign-customer-phone" placeholder="09xx xxx xxx" maxlength="25">
        </div>
      </div>

      <div class="form-group">
        <label>Ghi chú phục vụ</label>
        <input type="text" name="note" id="assign-note" placeholder="VD: Cần ghế trẻ em, ăn ít cay..." maxlength="500">
      </div>

      <div id="assign-error-box" style="display: none; color: #dc2626; font-size: 13px; margin-top: 8px; font-weight: 600;"></div>

      <div class="modal-actions-footer">
        <button type="button" class="btn btn-outline" data-close-table-modal="modal-assign-table">Hủy</button>
        <button type="submit" class="btn btn-primary" id="btn-submit-assign">Xác nhận gán bàn</button>
      </div>
    </form>
  </div>
</div>

<!-- MODAL CHUYỂN BÀN -->
<div class="custom-modal-backdrop" id="modal-transfer-table" style="display: none;">
  <div class="custom-modal-dialog custom-modal-sm">
    <div class="custom-modal-header">
      <div class="modal-title-wrap">
        <div>
          <h3>Chuyển bàn</h3>
          <p>Chuyển toàn bộ đơn hàng, món đã gọi và thông tin khách sang bàn trống mới</p>
        </div>
      </div>
      <button type="button" class="btn-close-modal" data-close-table-modal="modal-transfer-table" aria-label="Đóng">✕</button>
    </div>
    <form class="modal-form" id="form-transfer-table">
      <div class="form-group">
        <label>Chuyển từ bàn (Đang phục vụ) <span class="required">*</span></label>
        <select name="from_table" id="transfer-from-table" required>
          <option value="">-- Chọn bàn đang có khách --</option>
          <?php foreach ($occupiedActiveTables as $ot): ?>
            <option value="<?= htmlspecialchars($ot['code']) ?>">
              Bàn <?= htmlspecialchars($ot['code']) ?> — <?= htmlspecialchars($ot['area_name']) ?> (<?= (int) $ot['occupied_items'] ?> món · <?= number_format((int) $ot['occupied_total'], 0, ',', '.') ?>đ)
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group">
        <label>Sang bàn đích (Bàn trống) <span class="required">*</span></label>
        <select name="to_table" id="transfer-to-table" required>
          <option value="">-- Chọn bàn trống đích --</option>
          <?php foreach ($emptyActiveTables as $et): ?>
            <option value="<?= htmlspecialchars($et['code']) ?>">
              Bàn <?= htmlspecialchars($et['code']) ?> — <?= htmlspecialchars($et['area_name']) ?> (<?= (int) $et['capacity'] ?> chỗ)
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group">
        <label>Lý do / Ghi chú chuyển bàn</label>
        <input type="text" name="note" id="transfer-note" placeholder="VD: Khách muốn đổi ra sân vườn, ghép thêm chỗ..." maxlength="250">
      </div>

      <div id="transfer-error-box" style="display: none; color: #dc2626; font-size: 13px; margin-top: 8px; font-weight: 600;"></div>

      <div class="modal-actions-footer">
        <button type="button" class="btn btn-outline" data-close-table-modal="modal-transfer-table">Hủy</button>
        <button type="submit" class="btn btn-primary" id="btn-submit-transfer">Xác nhận chuyển bàn</button>
      </div>
    </form>
  </div>
</div>

<!-- MODAL GỘP BÀN -->
<div class="custom-modal-backdrop" id="modal-merge-table" style="display: none;">
  <div class="custom-modal-dialog custom-modal-sm">
    <div class="custom-modal-header">
      <div class="modal-title-wrap">
        <div>
          <h3>Gộp bàn</h3>
          <p>Gộp toàn bộ món ăn, số khách và đơn hàng từ bàn nguồn vào bàn đích đang phục vụ</p>
        </div>
      </div>
      <button type="button" class="btn-close-modal" data-close-table-modal="modal-merge-table" aria-label="Đóng">✕</button>
    </div>
    <form class="modal-form" id="form-merge-table">
      <div class="form-group">
        <label>Bàn cần gộp (Bàn nguồn sẽ trở về trống) <span class="required">*</span></label>
        <select name="from_table" id="merge-from-table" required>
          <option value="">-- Chọn bàn đang phục vụ --</option>
          <?php foreach ($occupiedActiveTables as $ot): ?>
            <option value="<?= htmlspecialchars($ot['code']) ?>">
              Bàn <?= htmlspecialchars($ot['code']) ?> — <?= htmlspecialchars($ot['area_name']) ?> (<?= (int) $ot['occupied_items'] ?> món · <?= number_format((int) $ot['occupied_total'], 0, ',', '.') ?>đ)
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group">
        <label>Gộp vào bàn chính (Bàn đích đang phục vụ) <span class="required">*</span></label>
        <select name="to_table" id="merge-to-table" required>
          <option value="">-- Chọn bàn đích đang có khách --</option>
          <?php foreach ($occupiedActiveTables as $ot): ?>
            <option value="<?= htmlspecialchars($ot['code']) ?>">
              Bàn <?= htmlspecialchars($ot['code']) ?> — <?= htmlspecialchars($ot['area_name']) ?> (<?= (int) $ot['occupied_items'] ?> món · <?= number_format((int) $ot['occupied_total'], 0, ',', '.') ?>đ)
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group">
        <label>Ghi chú gộp bàn</label>
        <input type="text" name="note" id="merge-note" placeholder="VD: Nhóm bạn ngồi chung thanh toán 1 hóa đơn..." maxlength="250">
      </div>

      <div id="merge-error-box" style="display: none; color: #dc2626; font-size: 13px; margin-top: 8px; font-weight: 600;"></div>

      <div class="modal-actions-footer">
        <button type="button" class="btn btn-outline" data-close-table-modal="modal-merge-table">Hủy</button>
        <button type="submit" class="btn btn-primary" id="btn-submit-merge">Xác nhận gộp bàn</button>
      </div>
    </form>
  </div>
</div>

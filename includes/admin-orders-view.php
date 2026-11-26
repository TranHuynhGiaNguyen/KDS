<?php

/**
 * SmartDine - Hiển thị danh sách món đang phục vụ theo từng bàn trên Trang Admin.
 * Dùng chung cho lần tải đầu trên pages/admin.php và API làm mới tự động api/admin-orders.php.
 */

require_once __DIR__ . '/orders.php';
require_once __DIR__ . '/http.php';

function renderAdminActiveOrders(array $tables): string
{
    if ($tables === []) {
        return '<p class="muted" style="padding: 12px 0; margin: 0;">Hiện chưa có bàn nào đang gọi món.</p>';
    }

    ob_start();
?>
    <div class="admin-live-orders-grid">
      <?php foreach ($tables as $table): ?>
        <article class="admin-live-table-card">
          <div class="admin-live-card-head">
            <div>
              <b>Bàn <?= escapeHtml($table['table']) ?></b>
              <span class="badge-pill"><?= count($table['items']) ?> món</span>
            </div>
            <a class="btn btn-small btn-outline" href="index.php?page=table-order&table=<?= escapeHtml($table['table']) ?>">Chi tiết bàn →</a>
          </div>
          <div class="admin-live-items">
            <?php foreach ($table['items'] as $item): ?>
              <?php $statusInfo = ORDER_ITEM_STATUSES[$item['status']] ?? ['label' => $item['status'], 'class' => 'pending']; ?>
              <div class="admin-live-item" data-item-id="<?= (int) $item['id'] ?>">
                <div class="admin-live-item-info">
                  <b><?= escapeHtml($item['name']) ?> <span class="muted">× <?= (int) $item['qty'] ?></span></b>
                  <small class="muted">
                    <?= escapeHtml($item['time']) ?><?= $item['note'] !== '' ? ' · ' . escapeHtml($item['note']) : '' ?>
                  </small>
                </div>
                <span class="status status-<?= escapeHtml($statusInfo['class']) ?>"><?= escapeHtml($statusInfo['label']) ?></span>
                <button
                  class="btn btn-small btn-outline-danger"
                  type="button"
                  data-admin-cancel-item="<?= (int) $item['id'] ?>"
                  data-item-name="<?= escapeHtml($item['name']) ?>"
                >Hủy món</button>
              </div>
            <?php endforeach; ?>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
<?php
    return (string) ob_get_clean();
}

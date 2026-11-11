<?php
/**
 * SmartDine - Giao diện màn hình bếp (KDS): mỗi bàn một ô, mỗi món là một dòng bấm được.
 * Dùng chung cho lần tải trang đầu và cho API làm mới tự động.
 */

require_once __DIR__ . '/kitchen.php';
require_once __DIR__ . '/http.php';

function renderKitchenItemRow(array $ticket): string {
    $isCancelled = $ticket['status'] === 'cancelled';
    $station     = KITCHEN_STATIONS[$ticket['station']] ?? $ticket['station'];

    ob_start();
    if ($isCancelled): ?>
      <div class="kds-item kds-item-cancelled" data-station="<?= escapeHtml($ticket['station']) ?>">
        <div class="kds-item-main">
          <span class="item-name"><?= escapeHtml($ticket['name']) ?></span>
          <small class="item-note">Đã hủy - ngừng làm</small>
        </div>
        <b class="item-qty">× <?= $ticket['qty'] ?></b>
      </div>
    <?php else: ?>
      <button class="kds-item" type="button" data-kds-item data-item-id="<?= $ticket['id'] ?>" data-station="<?= escapeHtml($ticket['station']) ?>">
        <div class="kds-item-main">
          <span class="item-name"><?= escapeHtml($ticket['name']) ?></span>
          <small class="kds-item-meta"><?= escapeHtml($station) ?> · <?= escapeHtml($ticket['time']) ?> · chờ <?= $ticket['minutes'] ?> phút</small>
          <?php if ($ticket['note'] !== ''): ?>
            <small class="item-note"><?= escapeHtml($ticket['note']) ?></small>
          <?php endif; ?>
        </div>
        <b class="item-qty">× <?= $ticket['qty'] ?></b>
      </button>
    <?php endif;

    return (string) ob_get_clean();
}

function renderKitchenTableCard(array $table): string {
    ob_start();
    ?>
    <article class="kitchen-card kds-table-card urgency-<?= $table['urgency'] ?>" data-kds-table="<?= escapeHtml($table['table']) ?>">
      <div class="kitchen-card-head">
        <div class="card-meta-left">
          <div class="card-table-title"><b>Bàn <?= escapeHtml($table['table']) ?></b></div>
          <div class="card-time-row">
            <span class="time-created"><?= escapeHtml($table['time']) ?></span>
            <span class="time-elapsed badge-<?= $table['urgency'] ?>">Chờ: <?= $table['minutes'] ?> phút</span>
          </div>
        </div>
      </div>
      <div class="kds-items">
        <?php foreach ($table['items'] as $ticket): ?>
          <?= renderKitchenItemRow($ticket) ?>
        <?php endforeach; ?>
      </div>
      <p class="kds-tap-hint">Chạm vào món để in bill và nhận món</p>
    </article>
    <?php
    return (string) ob_get_clean();
}

function renderKitchenGrid(array $tickets): string {
    if ($tickets === []) {
        return '<p class="kds-empty">Hiện chưa có món nào cần chế biến.</p>';
    }

    return implode('', array_map('renderKitchenTableCard', groupKitchenTicketsByTable($tickets)));
}
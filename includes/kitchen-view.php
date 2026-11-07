<?php
/**
 * SmartDine - Giao diện các thẻ món trên màn hình bếp (KDS).
 * Dùng chung cho lần tải trang đầu và cho API làm mới tự động.
 */

require_once __DIR__ . '/kitchen.php';
require_once __DIR__ . '/http.php';

const KITCHEN_CARD_LABELS = [
    'pending'   => ['class' => 'new',       'text' => 'Chờ nhận'],
    'cooking'   => ['class' => 'cooking',   'text' => 'Đang nấu'],
    'done'      => ['class' => 'ready',     'text' => 'Chờ bưng'],
    'cancelled' => ['class' => 'cancelled', 'text' => 'Đã hủy'],
];

const KITCHEN_CARD_BUTTONS = [
    'pending' => ['action' => 'start',  'class' => 'btn-start-cooking', 'label' => 'Nhận món & Bắt đầu nấu'],
    'cooking' => ['action' => 'finish', 'class' => 'btn-ready-cooking', 'label' => 'Báo ra đồ (Hoàn thành)'],
];

function renderKitchenCard(array $ticket): string {
    $status = $ticket['status'];
    $label  = KITCHEN_CARD_LABELS[$status];
    $button = KITCHEN_CARD_BUTTONS[$status] ?? null;

    ob_start();
    ?>
    <article class="kitchen-card kds-card urgency-<?= $ticket['urgency'] ?> kds-card-<?= $status ?>"
             data-kds-card="<?= $status ?>" data-station="<?= escapeHtml($ticket['station']) ?>" data-item-id="<?= $ticket['id'] ?>">
      <div class="kitchen-card-head">
        <div class="card-meta-left">
          <div class="card-table-title">
            <b>Bàn <?= escapeHtml($ticket['table']) ?></b>
            <span class="type-pill"><?= escapeHtml(KITCHEN_STATIONS[$ticket['station']] ?? $ticket['station']) ?></span>
          </div>
          <div class="card-time-row">
            <span class="time-created"><?= escapeHtml($ticket['time']) ?></span>
            <?php if ($status !== 'cancelled'): ?>
              <span class="time-elapsed badge-<?= $ticket['urgency'] ?>">Chờ: <?= $ticket['minutes'] ?> phút</span>
            <?php endif; ?>
          </div>
        </div>
        <div class="card-meta-right">
          <span class="status status-<?= $label['class'] ?>"><?= $label['text'] ?></span>
        </div>
      </div>

      <div class="kitchen-items-list">
        <div class="kitchen-item">
          <div class="item-content grow">
            <span class="item-name"><?= escapeHtml($ticket['name']) ?></span>
            <?php if ($ticket['note'] !== ''): ?>
              <small class="item-note"><?= escapeHtml($ticket['note']) ?></small>
            <?php endif; ?>
          </div>
          <b class="item-qty">× <?= $ticket['qty'] ?></b>
        </div>
      </div>

      <div class="kitchen-actions">
        <?php if ($button !== null): ?>
          <button class="btn btn-kds <?= $button['class'] ?>" type="button" data-kds-action="<?= $button['action'] ?>">
            <span><?= $button['label'] ?></span>
          </button>
        <?php elseif ($status === 'cancelled'): ?>
          <button class="btn btn-kds btn-cancelled-cooking" type="button" disabled><span>Bill đã hủy - ngừng làm</span></button>
        <?php else: ?>
          <button class="btn btn-kds btn-done-cooking" type="button" disabled><span>Đã ra đồ, chờ nhân viên bưng ✓</span></button>
        <?php endif; ?>
      </div>
    </article>
    <?php
    return (string) ob_get_clean();
}

function renderKitchenGrid(array $tickets): string {
    if ($tickets === []) {
        return '<p class="kds-empty">Hiện chưa có món nào cần chế biến.</p>';
    }

    return implode('', array_map('renderKitchenCard', $tickets));
}
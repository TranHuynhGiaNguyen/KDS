<?php

/**
 * SmartDine - Giao diện màn hình bếp (KDS): mỗi bàn một ô, mỗi món là một dòng có nút thao tác.
 * Dùng chung cho lần tải trang đầu và cho API làm mới tự động.
 */

require_once __DIR__ . '/kitchen.php';
require_once __DIR__ . '/http.php';

const KITCHEN_ITEM_BUTTONS = [
  'pending' => ['action' => 'start',  'label' => 'In phiếu bếp',   'class' => 'btn-kds-print'],
  'cooking' => ['action' => 'finish', 'label' => 'Hoàn thành món', 'class' => 'btn-kds-finish'],
];

function renderKitchenItemRow(array $ticket): string
{
  $button    = KITCHEN_ITEM_BUTTONS[$ticket['status']];
  $station   = KITCHEN_STATIONS[$ticket['station']] ?? $ticket['station'];
  $isCooking = $ticket['status'] === 'cooking';

  ob_start();
?>
  <div class="kds-item kds-item-<?= escapeHtml($ticket['status']) ?>" data-kds-item data-item-id="<?= $ticket['id'] ?>" data-status="<?= escapeHtml($ticket['status']) ?>" data-station="<?= escapeHtml($ticket['station']) ?>">
    <div class="kds-item-main">
      <span class="item-name"><?= escapeHtml($ticket['name']) ?></span>
      <small class="kds-item-meta"><?= escapeHtml($station) ?> · <?= escapeHtml($ticket['time']) ?> · <?= $isCooking ? 'đang nấu' : 'chờ ' . $ticket['minutes'] . ' phút' ?></small>
      <?php if ($ticket['note'] !== ''): ?>
        <small class="item-note"><?= escapeHtml($ticket['note']) ?></small>
      <?php endif; ?>
    </div>
    <b class="item-qty">× <?= $ticket['qty'] ?></b>
    <button class="btn btn-small kds-item-action <?= $button['class'] ?>" type="button" data-kds-action="<?= $button['action'] ?>"><?= $button['label'] ?></button>
  </div>
<?php

  return (string) ob_get_clean();
}

function renderKitchenTableCard(array $table): string
{
  ob_start();
?>
  <article class="kitchen-card kds-table-card urgency-<?= $table['urgency'] ?>" data-kds-table="<?= escapeHtml($table['table']) ?>">
    <div class="kitchen-card-head">
      <div class="card-meta-left">
        <div class="card-table-title"><b>Bàn <?= escapeHtml($table['table']) ?></b></div>
        <div class="card-time-row">
          <span class="time-created"><?= escapeHtml($table['time']) ?></span>
          <?php if ($table['has_pending']): ?>
            <span class="time-elapsed badge-<?= $table['urgency'] ?>">Chờ: <?= $table['minutes'] ?> phút</span>
          <?php else: ?>
            <span class="time-elapsed badge-normal">Đang nấu</span>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <div class="kds-items">
      <?php foreach ($table['items'] as $ticket): ?>
        <?= renderKitchenItemRow($ticket) ?>
      <?php endforeach; ?>
    </div>
  </article>
<?php

  return (string) ob_get_clean();
}

function renderKitchenGrid(array $tickets): string
{
  if ($tickets === []) {
    return '<p class="kds-empty">Hiện chưa có món nào cần chế biến.</p>';
  }

  return implode('', array_map('renderKitchenTableCard', groupKitchenTicketsByTable($tickets)));
}

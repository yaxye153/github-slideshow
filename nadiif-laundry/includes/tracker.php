<?php
// =====================================================================
// tracker.php - one order shown as a card with its two trackers:
//   1. WORK:     Received -> Washing -> Drying -> Ironing -> Ready
//   2. HANDOVER: Ready -> (Out for Delivery) -> Delivered / Picked up
// Used by the Dashboard, Order Tracking and the order page.
// =====================================================================

// Print the steps of one tracker
function tracker_steps(array $steps, string $status, array $order): string
{
    $names = array_keys($steps);
    $all = array_merge(array_keys(work_steps()), ['Out for Delivery', 'Delivered']);
    $currentPos = array_search($status, $all, true);
    $html = '<div class="track-steps">';
    foreach ($steps as $step => $icon) {
        $pos = array_search($step, $all, true);
        $class = $currentPos === false ? '' : ($pos < $currentPos ? 'done' : ($pos === $currentPos ? 'current' : ''));
        if ($status === 'Delivered' && $step === 'Delivered') {
            $class = 'done current';
        }
        $label = $step === 'Delivered' ? ($order['pickup_type'] === 'Delivery' ? 'Delivered' : 'Picked up') : $step;
        $html .= '<div class="step ' . $class . '"><i class="bi ' . $icon . '"></i>' . e($label) . '</div>';
    }
    return $html . '</div>';
}

// The whole order card. $return = page to come back to ('tracking' or 'dashboard')
function order_tracker_card(array $o, string $return = 'tracking', int $customerId = 0, bool $showCustomer = true): string
{
    $next = next_status($o);
    $hidden = csrf_field() . '<input type="hidden" name="id" value="' . (int)$o['id'] . '">'
            . '<input type="hidden" name="return" value="' . e($return) . '"><input type="hidden" name="customer_id" value="' . $customerId . '">';
    $done = in_array($o['status'], ['Ready', 'Out for Delivery', 'Delivered'], true);

    ob_start();
    ?>
    <div class="card shadow-sm mb-3 order-card<?= $o['status'] === 'Ready' ? ' border-warning' : '' ?>">
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between gap-2 mb-2">
                <div>
                    <a class="fw-bold" href="<?= url('orders/view.php?id=' . (int)$o['id']) ?>"><?= e($o['order_number']) ?></a>
                    <?= speed_badge($o['service_speed']) ?> <?= badge($o['status']) ?> <?= ready_label($o) ?>
                    <?php if ($showCustomer): ?>
                        <div><a class="text-reset" href="<?= url('tracking/index.php?customer_id=' . (int)$o['customer_id']) ?>"><?= e($o['full_name']) ?></a>
                            <?= tier_badge($o['tier'] ?? 'Standard') ?> <span class="text-muted small"><?= e($o['phone'] ?? '') ?></span></div>
                    <?php endif; ?>
                    <div class="small text-muted"><?= e($o['items'] ?? '') ?></div>
                    <div class="small text-muted">Created by <b><?= e($o['created_by_name'] ?: '-') ?></b> &middot; <?= e($o['pickup_type']) ?></div>
                </div>
                <div class="text-end">
                    <div>Shelf: <span class="shelf"><?= e($o['shelf_number']) ?: '-' ?></span></div>
                    <div class="small text-muted">Ready by <?= $o['ready_at'] ? show_datetime($o['ready_at']) : show_date($o['expected_date']) ?></div>
                </div>
            </div>

            <div class="small fw-bold text-muted mb-1"><i class="bi bi-gear"></i> 1. Work</div>
            <?= tracker_steps(work_steps(), $o['status'], $o) ?>
            <div class="small fw-bold text-muted mt-2 mb-1"><i class="bi bi-box-arrow-right"></i> 2. Handover to customer</div>
            <?= tracker_steps(handover_steps($o['pickup_type']), $done ? $o['status'] : 'none', $o) ?>
            <?php if ($o['status'] === 'Delivered' && $o['handed_over_at']): ?>
                <div class="small text-success mt-1"><i class="bi bi-check-circle"></i> <?= show_datetime($o['handed_over_at']) ?><?= $o['received_by'] ? ', received by ' . e($o['received_by']) : '' ?></div>
            <?php endif; ?>

            <div class="d-flex flex-wrap gap-2 align-items-center mt-2">
                <?php if ($next && $next !== 'Delivered'): ?>
                    <form method="post" action="<?= url('orders/status.php') ?>">
                        <?= $hidden ?><input type="hidden" name="status" value="<?= e($next) ?>">
                        <button class="btn btn-success btn-sm" type="submit"><i class="bi bi-arrow-right-circle"></i> <?= e(next_status_label($o, $next)) ?></button>
                    </form>
                <?php elseif ($next === 'Delivered'): ?>
                    <form method="post" action="<?= url('orders/status.php') ?>" class="d-flex flex-wrap gap-1">
                        <?= $hidden ?><input type="hidden" name="status" value="Delivered">
                        <input class="form-control form-control-sm" name="received_by" placeholder="Received by (name)" style="width: 140px; max-width: 100%" maxlength="100">
                        <button class="btn btn-success btn-sm text-nowrap" type="submit"><i class="bi bi-person-check"></i> <?= e(next_status_label($o, $next)) ?></button>
                    </form>
                <?php endif; ?>
                <?php if ($o['status'] !== 'Delivered'): ?>
                    <form method="post" action="<?= url('orders/status.php') ?>" class="d-flex gap-1">
                        <?= $hidden ?><input type="hidden" name="status" value="<?= e($o['status']) ?>">
                        <input class="form-control form-control-sm" name="shelf_number" value="<?= e($o['shelf_number']) ?>" placeholder="Shelf" style="width: 80px" maxlength="20" list="shelf-list">
                        <button class="btn btn-outline-secondary btn-sm" type="submit" title="Save shelf"><i class="bi bi-check-lg"></i></button>
                    </form>
                <?php endif; ?>
                <?php if (in_array($o['status'], ['Ready', 'Out for Delivery'], true)): ?>
                    <form method="post" action="<?= url('orders/notify.php') ?>" target="_blank" class="d-flex gap-1">
                        <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$o['id'] ?>">
                        <button class="btn btn-sm <?= $o['notified_at'] ? 'btn-outline-success' : 'btn-success' ?>" name="via" value="whatsapp" type="submit" title="Send WhatsApp message"><i class="bi bi-whatsapp"></i> WhatsApp</button>
                        <button class="btn btn-sm btn-outline-primary" name="via" value="sms" type="submit" title="Send SMS"><i class="bi bi-chat-dots"></i> SMS</button>
                    </form>
                    <?php if ($o['notified_at']): ?><span class="small text-success"><i class="bi bi-check2-all"></i> told <?= date('d M H:i', strtotime($o['notified_at'])) ?></span><?php endif; ?>
                <?php endif; ?>
                <span class="ms-auto small">Balance: <b class="<?= $o['balance'] > 0 ? 'text-loss' : 'text-profit' ?>"><?= money($o['balance']) ?></b></span>
                <?php if ($o['balance'] > 0 && can('payments') && $o['status'] !== 'Cancelled'): ?><a class="btn btn-outline-success btn-sm" href="<?= url('payments/add.php?order_id=' . (int)$o['id']) ?>"><i class="bi bi-cash"></i> Pay</a><?php endif; ?>
                <a class="btn btn-outline-dark btn-sm" href="<?= url('receipt/print.php?id=' . (int)$o['id']) ?>" target="_blank" title="Receipt"><i class="bi bi-printer"></i></a>
            </div>
        </div>
    </div>
    <?php
    return (string)ob_get_clean();
}

// The SQL columns every order card needs
function tracker_select(): string
{
    return "o.*, c.full_name, c.phone, c.tier,
        (SELECT GROUP_CONCAT(CONCAT(i.quantity, ' ', i.item_name) SEPARATOR ', ') FROM order_items i WHERE i.order_id = o.id) AS items";
}

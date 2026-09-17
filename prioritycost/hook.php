<?php

use GlpiPlugin\Prioritycost\Ticket as PrioritycostTicket;

/**
 * Statuses that should carry a Priority Cost: a ticket entering either one
 * for the first time gets its cost applied.
 */
function plugin_prioritycost_target_statuses(): array
{
    return [Ticket::SOLVED, Ticket::CLOSED];
}

/**
 * Existing ticket transitioning into Solved or Closed (from any other
 * status). Moving from Solved to Closed does not re-trigger this, since the
 * ticket was already in a target status beforehand - it would just update
 * the same cost row again, harmlessly, but there is no need to.
 */
function plugin_prioritycost_item_update(CommonDBTM $item)
{
    if (!$item instanceof Ticket) {
        return;
    }

    if (!isset($item->oldvalues['status'])) {
        return;
    }

    $targets = plugin_prioritycost_target_statuses();
    $was_target = in_array((int)$item->oldvalues['status'], $targets, true);
    $is_target  = in_array((int)$item->fields['status'], $targets, true);

    if (!$was_target && $is_target) {
        PrioritycostTicket::applyPriorityCost($item);
    }
}

/**
 * Ticket created directly in Solved or Closed status (e.g. via the API, an
 * import, or a rule) - item_update never fires in that case since the
 * status never actually changed after creation, so this needs its own hook.
 */
function plugin_prioritycost_item_add(CommonDBTM $item)
{
    if (!$item instanceof Ticket) {
        return;
    }

    if (in_array((int)$item->fields['status'], plugin_prioritycost_target_statuses(), true)) {
        PrioritycostTicket::applyPriorityCost($item);
    }
}

<?php

namespace GlpiPlugin\Prioritycost;

use Ticket as GlpiTicket;
use TicketCost;

class Ticket
{
    /**
     * Internal name used to identify the TicketCost row this plugin owns.
     * This is a stable lookup key, NOT a display label: it must never be
     * translated, or existing rows created under one language would stop
     * matching under another and get duplicated instead of updated.
     */
    private const COST_ROW_NAME = 'Priority Cost';

    public static function applyPriorityCost(GlpiTicket $ticket): void
    {
        if (!$ticket->getID()) {
            return;
        }

        $priority = (int)$ticket->fields['priority'];
        if ($priority < 1 || $priority > 6) {
            return;
        }

        $rule = Config::getRuleForEntityAndPriority((int)$ticket->fields['entities_id'], $priority);
        if (!$rule || empty($rule['cost'])) {
            return;
        }

        $cost = (float)$rule['cost'];
        $budgets_id = !empty($rule['budgets_id']) ? (int)$rule['budgets_id'] : null;

        global $DB;
        $existing = $DB->request([
            'FROM'  => 'glpi_ticketcosts',
            'WHERE' => [
                'tickets_id' => $ticket->getID(),
                'name'       => self::COST_ROW_NAME,
            ],
        ]);

        $ticketCost = new TicketCost();

        if ($existing->count()) {
            $ticketCost->update([
                'id'         => (int)$existing->current()['id'],
                'cost_fixed' => $cost,
                'budgets_id' => $budgets_id,
            ]);
        } else {
            $ticketCost->add([
                'tickets_id' => $ticket->getID(),
                'name'       => self::COST_ROW_NAME,
                'comment'    => __('Automatic fixed cost based on ticket priority', 'prioritycost'),
                'cost_fixed' => $cost,
                'budgets_id' => $budgets_id,
            ]);
        }
    }
}

<?php
class PluginPrioritycostTicket {
   public static function applyPriorityCost(Ticket $ticket): void {
      if(!$ticket->getID()){
         return;
      }
      $priority=(int)$ticket->fields['priority'];
      if($priority<1 || $priority>6){
         return;
      }
      $rule=PluginPrioritycostConfig::getRuleForEntityAndPriority((int)$ticket->fields['entities_id'],$priority);
      if(!$rule || empty($rule['cost'])){
         return;
      }
      $cost=(float)$rule['cost'];
      $budgets_id=!empty($rule['budgets_id'])?(int)$rule['budgets_id']:null;
      global $DB;
      $existing=$DB->request([
         'FROM'=>'glpi_ticketcosts',
         'WHERE'=>['tickets_id'=>$ticket->getID(),'name'=>'Priority Cost']
      ]);
      $ticketCost=new TicketCost();
      if($existing->count()){
         $ticketCost->update([
            'id'=>(int)$existing->current()['id'],
            'cost_fixed'=>$cost,
            'budgets_id'=>$budgets_id
         ]);
      } else {
         $ticketCost->add([
            'tickets_id'=>$ticket->getID(),
            'name'=>'Priority Cost',
            'comment'=>'Automatic fixed cost based on ticket priority',
            'cost_fixed'=>$cost,
            'budgets_id'=>$budgets_id
         ]);
      }
   }
}

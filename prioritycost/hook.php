<?php
require_once __DIR__.'/src/ticket.class.php';
function plugin_prioritycost_item_update(CommonDBTM $item){
   if(!$item instanceof Ticket){return;}
   if(isset($item->oldvalues['status']) && $item->oldvalues['status']!=Ticket::SOLVED && $item->fields['status']==Ticket::SOLVED){
      PluginPrioritycostTicket::applyPriorityCost($item);
   }
}

<?php
class PluginPrioritycostConfig extends CommonDBTM {
   public static function getTypeName($nb=0): string {return __('Priority Cost','prioritycost');}
   public static function canView(): bool {return Session::haveRight('entity',READ);} 
   public static function canUpdate(): bool {return Session::haveRight('entity',UPDATE);} 
   public function getTabNameForItem(CommonGLPI $item,$withtemplate=0): string {
      return ($item instanceof Entity)?__('Priority Cost','prioritycost'):'';
   }
   public static function displayTabContentForItem(CommonGLPI $item,$tabnum=1,$withtemplate=0): bool {
      include GLPI_ROOT.'/plugins/prioritycost/front/entity.tab.php';
      return true;
   }
   public static function getRuleForEntityAndPriority(int $entity_id,int $priority): ?array {
      global $DB;
      $entity=new Entity();
      $ancestors=iterator_to_array($entity->getAncestors($entity_id),false);
      array_unshift($ancestors,$entity_id);
      foreach($ancestors as $eid){
         $res=$DB->request(['FROM'=>'glpi_plugin_prioritycost_rules','WHERE'=>['entities_id'=>$eid,'priority'=>$priority]]);
         if($res->count()){
            return $res->current();
         }
      }
      return null;
   }
}

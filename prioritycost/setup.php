<?php
function plugin_init_prioritycost() {
   global $PLUGIN_HOOKS;
   $PLUGIN_HOOKS['csrf_compliant']['prioritycost'] = true;
   $PLUGIN_HOOKS['item_update']['prioritycost'] = ['Ticket'=>'plugin_prioritycost_item_update'];
   Plugin::registerClass(PluginPrioritycostConfig::class, ['addtabon'=>[Entity::class]]);
}
function plugin_version_prioritycost() {
   return [
      'name'=>'Priority Cost',
      'version'=>'2.6.5',
      'author'=>'NexPoint Services',
      'license'=>'GPLv2+',
      'requirements'=>['glpi'=>['min'=>'11.0.0']]
   ];
}

function plugin_prioritycost_install() {
   global $DB;

   if (!$DB->tableExists('glpi_plugin_prioritycost_rules'))  {
      $query= "CREATE TABLE glpi_plugin_prioritycost_rules (
         id INT UNSIGNED NOT NULL AUTO_INCREMENT,
         entities_id INT UNSIGNED NOT NULL,
         priority INT NOT NULL,
         cost INT NOT NULL,
         budgets_id INT UNSIGNED NULL,
         PRIMARY KEY (id),
         UNIQUE KEY uniq_ep (entities_id, priority)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
      $DB->doQuery($query);
   }
   return true;
}

function plugin_prioritycost_uninstall(){
   global $DB;

   if ($DB->tableExists('glpi_plugin_prioritycost_rules')) {
       $DB->doQuery("DELETE FROM glpi_plugins WHERE directory = 'prioritycost'");
       $DB->doQuery("DROP TABLE IF EXISTS glpi_plugin_prioritycost_rules");
   }

   return true;
}

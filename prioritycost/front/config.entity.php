<?php
include('../../../inc/includes.php');
Session::checkRight('entity',UPDATE);
global $DB;
$entity_id=(int)($_GET['entities_id']??0);
if(isset($_POST['save'])){
   foreach($_POST['cost'] as $p=>$c){
      $DB->delete('glpi_plugin_prioritycost_rules',['entities_id'=>$entity_id,'priority'=>(int)$p]);
      if($c!==''){
         $DB->insert('glpi_plugin_prioritycost_rules',[
            'entities_id'=>$entity_id,'priority'=>(int)$p,
            'cost'=>(float)$c,'budgets_id'=>$_POST['budget'][$p]??null
         ]);
      }
   }
   Html::redirect($_SERVER['REQUEST_URI']);
}
Html::header(__('Priority Cost configuration'),'','admin','entity');
echo "<form method='post'>";
echo Html::hidden('_glpi_csrf_token',['value'=>Session::getNewCSRFToken()]);
echo "<table class='tab_cadre_fixe'><tr><th>".__('Priority')."</th><th>".__('Cost')."</th><th>".__('Associated budget')."</th></tr>";
for($p=1;$p<=6;$p++){
   $rule=$DB->request(['FROM'=>'glpi_plugin_prioritycost_rules','WHERE'=>['entities_id'=>$entity_id,'priority'=>$p]])->current();
   $cost=$rule['cost']??''; $bid=$rule['budgets_id']??0;
   echo "<tr><td><b>".__('Priority')." $p</b></td><td><input type='number' step='0.01' name='cost[$p]' value='$cost'></td><td>";
   Dropdown::show('Budget',['name'=>"budget[$p]",'entity'=>$entity_id,'value'=>$bid]);
   echo "</td></tr>";
}
echo "</table><br><button class='btn btn-success' name='save'>".__('Save')."</button></form>";
Html::footer();

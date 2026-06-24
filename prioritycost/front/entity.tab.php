<?php
Session::checkRight('entity',READ);
global $DB;
$entity_id=(int)($_GET['id']??0);
if (!Session::haveAccessToEntity($entity_id)) {
    Html::displayRightError();
}
echo "<h3>".__('Priority Cost rules','prioritycost')."</h3>";
echo "<table class='tab_cadre_fixe'><tr><th>".__('Priority')."</th><th>".__('Cost')."</th><th>".__('Associated budget')."</th></tr>";
for($p=1;$p<=6;$p++){
   $rule=$DB->request(['FROM'=>'glpi_plugin_prioritycost_rules','WHERE'=>['entities_id'=>$entity_id,'priority'=>$p]])->current();
   $cost=$rule['cost']??null;
   $bid=$rule['budgets_id']??null;
   $bname=null;
   if($bid){$b=$DB->request(['FROM'=>'glpi_budgets','WHERE'=>['id'=>(int)$bid]])->current();$bname=$b['name']??null;}
   echo "<tr><td><b>".__('Priority')." $p</b></td><td>".( $cost!==null?htmlspecialchars((string)$cost):"<em>".__('Inherited / not set','prioritycost')."</em>" )."</td><td>".( $bname?htmlspecialchars($bname):"<em>".__('None / inherited','prioritycost')."</em>" )."</td></tr>";
}
echo "</table>";
$url=Plugin::getWebDir('prioritycost')."/front/config.entity.php?entities_id=$entity_id";
echo "<br><a class='vsubmit' href='$url'><i class='fas fa-cog'></i> ".__('Configure','prioritycost')."</a>";

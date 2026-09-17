<?php

use GlpiPlugin\Prioritycost\Config;

include('../../../inc/includes.php');

Session::checkRight('entity', UPDATE);

$entity_id = (int)($_POST['entities_id'] ?? $_GET['entities_id'] ?? 0);

// Session::checkRight() only confirms the user holds the "entity" right
// *somewhere*; it does not confirm they may act on this specific entity.
// Without this check a user administering entity A could pass another
// entity's ID and overwrite its rules (IDOR / horizontal privilege escalation).
if (!Session::haveAccessToEntity($entity_id)) {
    Html::displayRightError();
}

if (isset($_POST['save'])) {
    // The root entity has no parent, so it can never be marked as inherited
    // - even if the checkbox were somehow forced into the request.
    $is_inherited = ($entity_id !== 0) && isset($_POST['is_inherited']);

    Config::saveInheritance($entity_id, $is_inherited);

    // When inherited, the cost/budget fields are disabled client-side (via
    // the surrounding <fieldset disabled>), so the browser won't submit
    // them - and even if it did, we deliberately ignore them here so a
    // toggle back to "own values" later still finds what was there before.
    if (!$is_inherited) {
        $costs = is_array($_POST['cost'] ?? null) ? $_POST['cost'] : [];
        $budgets_id = (int)($_POST['budgets_id'] ?? 0);

        Config::saveRulesForEntity($entity_id, $costs);
        Config::saveBudgetForEntity($entity_id, $budgets_id > 0 ? $budgets_id : null);
    }
}

// The form now lives directly in the entity's "Priority Cost" tab (see
// front/entity.tab.php); this endpoint only ever processes the save and
// sends the user straight back to that tab.
Html::redirect(Entity::getFormURLWithID($entity_id) . '&forcetab=' . urlencode(Config::class) . '$1');

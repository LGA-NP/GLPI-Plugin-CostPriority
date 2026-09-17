<?php

use GlpiPlugin\Prioritycost\Config;
use Glpi\Application\View\TemplateRenderer;

// This file is always included by Config::displayTabContentForItem(), which
// itself only runs after GLPI's core tab framework has already required
// includes.php / checked the "entity" READ right for the containing item.
// The entity-scope check below is what stops that generic right from being
// enough to read/edit another entity's configured costs.
Session::checkRight('entity', READ);

$entity_id = (int)($_GET['id'] ?? 0);

if (!Session::haveAccessToEntity($entity_id)) {
    Html::displayRightError();
}

$can_edit = Session::haveRight('entity', UPDATE);
$is_root = ($entity_id === 0);
$is_inherited = $is_root ? false : Config::isInherited($entity_id);

// Editable, unlocked: show this entity's own raw data so the admin edits
// exactly what is stored on it. Locked (inherited) or read-only viewer: show
// the resolved, effective values instead, since that is what actually applies.
if ($can_edit && !$is_inherited) {
    $rules      = Config::getRulesForEntity($entity_id);
    $budgets_id = Config::getBudgetForEntity($entity_id);
} else {
    $rules      = Config::getEffectiveRulesForEntity($entity_id);
    $budgets_id = Config::getEffectiveBudgetForEntity($entity_id);
}

$budget_dropdown = '';
$budget_name     = null;

if ($can_edit) {
    // Dropdown::show() echoes directly rather than returning a string, so it
    // is rendered here and passed to the template as a raw HTML fragment
    // (no user-controlled data goes into it: only entity_id and the
    // already-numeric budgets_id are used to build the widget).
    ob_start();
    Dropdown::show('Budget', [
        'name'   => 'budgets_id',
        'entity' => $entity_id,
        'value'  => $budgets_id,
    ]);
    $budget_dropdown = ob_get_clean();
} elseif ($budgets_id) {
    global $DB;
    $budget = $DB->request(['FROM' => 'glpi_budgets', 'WHERE' => ['id' => $budgets_id]])->current();
    $budget_name = $budget['name'] ?? null;
}

TemplateRenderer::getInstance()->display('@prioritycost/entity_tab.html.twig', [
    'entity_id'       => $entity_id,
    'can_edit'        => $can_edit,
    'is_root'         => $is_root,
    'is_inherited'    => $is_inherited,
    'rules'           => $rules,
    'budget_dropdown' => $budget_dropdown,
    'budget_name'     => $budget_name,
    'csrf_token'      => Session::getNewCSRFToken(),
    'save_url'        => Plugin::getWebDir('prioritycost') . '/front/config.entity.php',
]);

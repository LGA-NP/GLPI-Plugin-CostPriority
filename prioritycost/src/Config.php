<?php

namespace GlpiPlugin\Prioritycost;

use CommonDBTM;
use CommonGLPI;
use Entity;
use Session;

class Config extends CommonDBTM
{
    public static function getTypeName($nb = 0): string
    {
        return __('Priority Cost', 'prioritycost');
    }

    public static function canView(): bool
    {
        return Session::haveRight('entity', READ);
    }

    public static function canUpdate(): bool
    {
        return Session::haveRight('entity', UPDATE);
    }

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0): string
    {
        if (!($item instanceof Entity)) {
            return '';
        }

        // 4th arg is a Font Awesome icon class shown before the tab label.
        return self::createTabEntry(__('Priority Cost', 'prioritycost'), 0, $item::getType(), 'fas fa-coins');
    }

    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0): bool
    {
        include GLPI_ROOT . '/plugins/prioritycost/front/entity.tab.php';
        return true;
    }

    /**
     * Whether this entity is configured to inherit its Priority Cost setup
     * from its parent instead of defining its own. An entity that has never
     * been touched (no row yet) defaults to "inherited", since it has never
     * had a chance to define anything of its own. The root entity (id 0) has
     * no parent, so it can never be marked as inherited.
     */
    public static function isInherited(int $entity_id): bool
    {
        if ($entity_id === 0) {
            return false;
        }

        global $DB;
        $row = $DB->request([
            'FROM'  => 'glpi_plugin_prioritycost_configs',
            'WHERE' => ['entities_id' => $entity_id],
        ])->current();

        return $row === null || (bool)$row['is_inherited'];
    }

    /**
     * Walk up from $entity_id (inclusive) and return the ID of the first
     * entity that is NOT marked as inherited: that is the entity whose own
     * cost rules and budget are actually in effect. The root entity is
     * always a safe fallback since it can never be inherited itself.
     */
    private static function getAuthoritativeEntity(int $entity_id): int
    {
        if ($entity_id === 0) {
            return 0;
        }

        // getAncestorsOf() (the correct GLPI API - Entity has no such
        // instance method as getAncestors()) returns ancestor IDs ordered
        // from the root down to this entity's immediate parent, so it must
        // be reversed to walk nearest-parent-first, which is what "closest
        // configured entity wins" requires.
        $ancestor_ids = array_reverse(array_values(\getAncestorsOf('glpi_entities', $entity_id)));

        $chain = $ancestor_ids;
        array_unshift($chain, $entity_id);

        foreach ($chain as $eid) {
            if (!self::isInherited((int)$eid)) {
                return (int)$eid;
            }
        }

        return 0;
    }

    /**
     * The cost rule actually in effect for a priority on this entity, once
     * inheritance has been resolved, with the effective budget merged in
     * under 'budgets_id'.
     */
    public static function getRuleForEntityAndPriority(int $entity_id, int $priority): ?array
    {
        global $DB;

        $eid = self::getAuthoritativeEntity($entity_id);

        $res = $DB->request([
            'FROM'  => 'glpi_plugin_prioritycost_rules',
            'WHERE' => ['entities_id' => $eid, 'priority' => $priority],
        ]);
        if (!$res->count()) {
            return null;
        }

        $rule = $res->current();
        $rule['budgets_id'] = self::getBudgetForEntity($eid);

        return $rule;
    }

    /**
     * The 6 priority rows as OWN raw data stored directly on this entity
     * (never resolved through inheritance) - used to populate the edit form
     * when this entity is not marked as inherited.
     */
    public static function getRulesForEntity(int $entity_id): array
    {
        global $DB;

        $rows = [];
        for ($p = 1; $p <= 6; $p++) {
            $rule = $DB->request([
                'FROM'  => 'glpi_plugin_prioritycost_rules',
                'WHERE' => ['entities_id' => $entity_id, 'priority' => $p],
            ])->current();

            $rows[$p] = [
                'priority' => $p,
                'cost'     => $rule['cost'] ?? null,
            ];
        }

        return $rows;
    }

    /**
     * The 6 priority rows as they actually apply once inheritance is
     * resolved - used to display the (locked) effective values when this
     * entity is marked as inherited.
     */
    public static function getEffectiveRulesForEntity(int $entity_id): array
    {
        return self::getRulesForEntity(self::getAuthoritativeEntity($entity_id));
    }

    /**
     * The budget stored directly on this entity (one per entity, shared by
     * every priority level), or null if none is set. Never resolved through
     * inheritance.
     */
    public static function getBudgetForEntity(int $entity_id): ?int
    {
        global $DB;

        $row = $DB->request([
            'FROM'  => 'glpi_plugin_prioritycost_configs',
            'WHERE' => ['entities_id' => $entity_id],
        ])->current();

        return !empty($row['budgets_id']) ? (int)$row['budgets_id'] : null;
    }

    /**
     * The budget actually in effect once inheritance is resolved.
     */
    public static function getEffectiveBudgetForEntity(int $entity_id): ?int
    {
        return self::getBudgetForEntity(self::getAuthoritativeEntity($entity_id));
    }

    /**
     * Upsert the is_inherited flag without touching the stored budget.
     */
    public static function saveInheritance(int $entity_id, bool $is_inherited): void
    {
        global $DB;

        $existing = $DB->request([
            'FROM'  => 'glpi_plugin_prioritycost_configs',
            'WHERE' => ['entities_id' => $entity_id],
        ])->current();

        if ($existing) {
            $DB->update(
                'glpi_plugin_prioritycost_configs',
                ['is_inherited' => $is_inherited ? 1 : 0],
                ['entities_id' => $entity_id]
            );
        } else {
            $DB->insert('glpi_plugin_prioritycost_configs', [
                'entities_id'  => $entity_id,
                'is_inherited' => $is_inherited ? 1 : 0,
                'budgets_id'   => null,
            ]);
        }
    }

    /**
     * Save the posted per-priority costs for one entity. Only ever called
     * when the entity is NOT inherited (see front/config.entity.php).
     *
     * @param array<int,mixed> $costs priority => cost value (raw string from POST)
     */
    public static function saveRulesForEntity(int $entity_id, array $costs): void
    {
        global $DB;

        for ($p = 1; $p <= 6; $p++) {
            // Only ever act on the fixed, whitelisted 1-6 priority range: values
            // supplied under other array keys in the POST body are ignored.
            $DB->delete('glpi_plugin_prioritycost_rules', [
                'entities_id' => $entity_id,
                'priority'    => $p,
            ]);

            $cost = $costs[$p] ?? '';
            if ($cost === '' || $cost === null || !is_numeric($cost)) {
                continue;
            }

            $DB->insert('glpi_plugin_prioritycost_rules', [
                'entities_id' => $entity_id,
                'priority'    => $p,
                'cost'        => (float)$cost,
            ]);
        }
    }

    /**
     * Save (or clear) the single budget for one entity, without touching
     * its is_inherited flag. Only ever called when the entity is NOT
     * inherited (see front/config.entity.php).
     */
    public static function saveBudgetForEntity(int $entity_id, ?int $budgets_id): void
    {
        global $DB;

        $existing = $DB->request([
            'FROM'  => 'glpi_plugin_prioritycost_configs',
            'WHERE' => ['entities_id' => $entity_id],
        ])->current();

        if ($existing) {
            $DB->update(
                'glpi_plugin_prioritycost_configs',
                ['budgets_id' => $budgets_id],
                ['entities_id' => $entity_id]
            );
        } else {
            $DB->insert('glpi_plugin_prioritycost_configs', [
                'entities_id'  => $entity_id,
                'is_inherited' => 0,
                'budgets_id'   => $budgets_id,
            ]);
        }
    }
}

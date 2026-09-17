<?php

use GlpiPlugin\Prioritycost\Config;

define('PLUGIN_PRIORITYCOST_VERSION', '2.9.1');

function plugin_init_prioritycost()
{
    global $PLUGIN_HOOKS;

    $PLUGIN_HOOKS['csrf_compliant']['prioritycost'] = true;
    $PLUGIN_HOOKS['item_update']['prioritycost'] = ['Ticket' => 'plugin_prioritycost_item_update'];
    $PLUGIN_HOOKS['item_add']['prioritycost'] = ['Ticket' => 'plugin_prioritycost_item_add'];

    Plugin::registerClass(Config::class, ['addtabon' => [Entity::class]]);
}

function plugin_version_prioritycost()
{
    return [
        'name'         => 'Priority Cost',
        'version'      => PLUGIN_PRIORITYCOST_VERSION,
        'author'       => 'NexPoint Services',
        'license'      => 'GPLv3+',
        'requirements' => ['glpi' => ['min' => '11.0.0']],
    ];
}

/**
 * Run every ';'-terminated statement of a plain SQL file through the query
 * builder connection. The install/uninstall files shipped with this plugin
 * are simple, single-statement-per-line files with no string literal
 * containing a semicolon, so this naive split is safe here.
 */
function plugin_prioritycost_run_sql_file(string $path): void
{
    global $DB;

    $sql = file_get_contents($path);
    foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
        $DB->doQuery($statement);
    }
}

function plugin_prioritycost_install()
{
    global $DB;

    // Both CREATE TABLE statements use IF NOT EXISTS, so running this on
    // every install/update is safe whether the tables already exist or not.
    plugin_prioritycost_run_sql_file(__DIR__ . '/install/sql/mysql/install.sql');

    // Upgrade path from versions <= 2.6.6: budget used to be stored per
    // priority row on glpi_plugin_prioritycost_rules.budgets_id. It is now
    // one value per entity, held in glpi_plugin_prioritycost_configs. Carry
    // over the first non-empty budget found for each entity, then drop the
    // now-unused column. This block becomes a no-op once the column is gone.
    if ($DB->fieldExists('glpi_plugin_prioritycost_rules', 'budgets_id')) {
        $migrated_budgets = [];
        foreach ($DB->request(['FROM' => 'glpi_plugin_prioritycost_rules']) as $row) {
            $entities_id = (int)$row['entities_id'];
            if (!empty($row['budgets_id']) && !isset($migrated_budgets[$entities_id])) {
                $migrated_budgets[$entities_id] = (int)$row['budgets_id'];
            }
        }

        foreach ($migrated_budgets as $entities_id => $budgets_id) {
            if (!$DB->request(['FROM' => 'glpi_plugin_prioritycost_configs', 'WHERE' => ['entities_id' => $entities_id]])->count()) {
                $DB->insert('glpi_plugin_prioritycost_configs', [
                    'entities_id' => $entities_id,
                    'budgets_id'  => $budgets_id,
                ]);
            }
        }

        $migration = new Migration(PLUGIN_PRIORITYCOST_VERSION);
        $migration->dropField('glpi_plugin_prioritycost_rules', 'budgets_id');
        $migration->executeMigration();
    }

    // Upgrade path from versions <= 2.7.0: the "inherit from parent entity"
    // checkbox is new in 2.8.0. Add its column, then mark every entity that
    // already had ANY configuration of its own (an existing configs row, or
    // its own cost rules) as "not inherited", so pre-existing setups keep
    // applying exactly the same costs/budget as before this feature existed.
    // Entities with no data at all simply default to "inherited" going
    // forward (see Config::isInherited()), which is harmless since they had
    // nothing configured anyway.
    if (!$DB->fieldExists('glpi_plugin_prioritycost_configs', 'is_inherited')) {
        $migration = new Migration(PLUGIN_PRIORITYCOST_VERSION);
        $migration->addField('glpi_plugin_prioritycost_configs', 'is_inherited', 'bool', ['value' => 0]);
        $migration->executeMigration();

        $entities_with_data = [];
        foreach ($DB->request(['FROM' => 'glpi_plugin_prioritycost_configs']) as $row) {
            $entities_with_data[(int)$row['entities_id']] = true;
        }
        foreach ($DB->request(['FROM' => 'glpi_plugin_prioritycost_rules']) as $row) {
            $entities_with_data[(int)$row['entities_id']] = true;
        }

        foreach (array_keys($entities_with_data) as $entities_id) {
            $existing = $DB->request(['FROM' => 'glpi_plugin_prioritycost_configs', 'WHERE' => ['entities_id' => $entities_id]])->current();
            if ($existing) {
                $DB->update('glpi_plugin_prioritycost_configs', ['is_inherited' => 0], ['entities_id' => $entities_id]);
            } else {
                $DB->insert('glpi_plugin_prioritycost_configs', [
                    'entities_id'  => $entities_id,
                    'is_inherited' => 0,
                    'budgets_id'   => null,
                ]);
            }
        }
    }

    return true;
}

function plugin_prioritycost_uninstall()
{
    global $DB;

    if ($DB->tableExists('glpi_plugin_prioritycost_rules') || $DB->tableExists('glpi_plugin_prioritycost_configs')) {
        plugin_prioritycost_run_sql_file(__DIR__ . '/install/sql/mysql/uninstall.sql');
    }

    // Note: TicketCost rows created by this plugin (name = "Priority Cost")
    // are legitimate cost records on real tickets and are deliberately left
    // in place; GLPI itself removes the glpi_plugins row on uninstall, so
    // this function must not touch it.

    return true;
}

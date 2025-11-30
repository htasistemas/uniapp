<?php

function plugin_uniapp_uninstall() {
    global $DB;

    $plugin_name = 'uniapp';
    $config_table_name = 'glpi_plugin_' . $plugin_name . '_config';
    $table = 'glpi_users';
    $field = 'fcm_token';
    $migration = new Migration(100);

    if ($DB->tableExists($config_table_name)) {
        $migration->dropTable($config_table_name);
    }

    if ($DB->tableExists($table) && $DB->fieldExists($table, $field, false)) {
        $migration->dropField($table, $field);
    }

    $migration->executeMigration();

    return true;
}

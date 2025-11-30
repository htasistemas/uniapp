<?php

global $DB;

$table = "glpi_users";
$field = "fcm_token";

$plugin_name = "uniapp";

$config_table_name = "glpi_plugin_" . $plugin_name . "_config";

$migration = new Migration(100);

if ($DB->tableExists($config_table_name)) {
    $query = "DROP TABLE `$config_table_name`";
    $DB->queryOrDie(
        $query,
        $DB->error()
    );

}

if ($DB->tableExists($table)) {
    if ($DB->fieldExists($table, $field, false)) {
        $migration->dropField(
            $table,
            $field
        );
    }
}

$migration->executeMigration();
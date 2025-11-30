<?php

global $DB;

$plugin_name = "uniapp";
$config_table_name = "glpi_plugin_" . $plugin_name . "_config";
$token_table = "glpi_plugin_" . $plugin_name . "_user_tokens";

if ($DB->tableExists($token_table)) {
    // Remove a tabela exclusiva de tokens ao desinstalar
    $query = "DROP TABLE `$token_table`";
    $DB->queryOrDie(
        $query,
        $DB->error()
    );
}

if ($DB->tableExists($config_table_name)) {
    // Exclui a tabela de configuracoes do plugin
    $query = "DROP TABLE `$config_table_name`";
    $DB->queryOrDie(
        $query,
        $DB->error()
    );
}

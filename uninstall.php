<?php

global $DB;

$plugin_name       = 'uniapp';
$config_table_name = "glpi_plugin_{$plugin_name}_config";
$token_table_name  = "glpi_plugin_{$plugin_name}_user_tokens";

// Use transação quando disponível
$inTx = false;
try {
    if (method_exists($DB, 'beginTransaction')) {
        $DB->beginTransaction();
        $inTx = true;
    }

    // 1) Apaga a tabela de tokens (tem FK para glpi_users)
    if ($DB->tableExists($token_table_name)) {
        $sql = "DROP TABLE `$token_table_name`";
        $DB->queryOrDie($sql, 'Falha ao remover tabela de tokens do UniApp');
    }

    // 2) Apaga a tabela de configuração
    if ($DB->tableExists($config_table_name)) {
        $sql = "DROP TABLE `$config_table_name`";
        $DB->queryOrDie($sql, 'Falha ao remover tabela de configuração do UniApp');
    }

    if ($inTx && method_exists($DB, 'commit')) {
        $DB->commit();
    }
} catch (Throwable $e) {
    if ($inTx && method_exists($DB, 'rollBack')) {
        $DB->rollBack();
    }
    // Deixa o erro visível para o administrador
    $DB->queryOrDie('SELECT 1', 'Erro na desinstalação do UniApp: ' . $e->getMessage());
}

<?php

/* Tipos de usuários
      1: Solicitante                    (type do users_id na tabela glpi_tickets_users)
      2: Técnico responsável            (type do users_id na tabela glpi_tickets_users)
      3: Observador                     (type do users_id na tabela glpi_tickets_users)
    104: Destinatário                   (users_id_recipient na tabela glpi_tickets)
    105: Último atualizador             (users_id_lastupdater na tabela glpi_tickets)
    206: Criador do acompanhamento      (users_id na tabela glpi_itilfollowups )
    207: Editor do acompanhamento       (users_id_editor na tabela glpi_itilfollowups )
    308: Criador da solução             (users_id na tabela glpi_itilsolutions)
    309: Editor da solução              (users_id_editor na tabela glpi_itilsolutions)
    310: Aprovador da solução           (users_id_approval na tabela glpi_itilsolutions)
    411: Criador da aprovação           (users_id na tabela glpi_ticketvalidations)
    412: Validador da aprovação         (users_id_validate na tabela glpi_ticketvalidations)
*/

$parameters = [
    "fcm_project_id" => "",
    "fcm_client_email" => "",
    "fcm_private_key" => "",
    "ticket_title" => "",
    "ticket_message" => "",
    "ticket_user_types" => "",
    "followup_title" => "",
    "followup_message" => "",
    "followup_user_types" => "",
    "solution_title" => "",
    "solution_message" => "",
    "solution_user_types" => "",
    "validation_title" => "",
    "validation_message" => "",
    "validation_user_types" => ""
];

$write_log = '1';

$table = "glpi_users";
$field = "fcm_token";
$type = "VARCHAR(255)";

$plugin_name = "uniapp";

$config_table_name = "glpi_plugin_" . $plugin_name . "_config";
$configfile = __DIR__ . "/" . $plugin_name . ".cfg";
$logfile = "/opt/unihelp-prod/glpi/public/uniapp.log";


$fd = fopen($configfile, 'r') or die("Não foi possível abrir o arquivo de configuração '" . $configfile . "'");
while (!feof($fd)) {
    $str = trim(fgets($fd));
    $a = explode("=", $str, 2);

    if ($a[0] === 'write_log') {
        $write_log = $a[1];
    } elseif (array_key_exists($a[0], $parameters)) {
        $parameters[$a[0]] = $a[1];

    }
}
fclose($fd);

if ($write_log === "") {
    $write_log = '1';
}

foreach (array_keys($parameters) as $key) {
    if ($parameters[$key] == "") {
        die("Parâmetro '" . $key . "' está vazio. Verifique o arquivo de configuração '" . $configfile . "'");
    }
}

global $DB;

$migration = new Migration(100);

if (!$DB->tableExists($config_table_name)) {
    $query = "CREATE TABLE $config_table_name (
                  id INT AUTO_INCREMENT PRIMARY KEY,
                  par_name VARCHAR(255) NOT NULL,
                  par_value TEXT NOT NULL
               )";
    $DB->queryOrDie($query, $DB->error());
}

foreach (array_keys($parameters) as $key) {
    $value = addslashes($parameters[$key]);
    $query = "INSERT INTO $config_table_name (par_name,par_value) VALUES ('$key','$value')";
    $DB->queryOrDie($query, $DB->error());
}

$DB->queryOrDie("DELETE FROM $config_table_name WHERE par_name IN ('write_log','log_file')", $DB->error());
$log_value = addslashes($logfile);
$write_log_value = addslashes($write_log);
$query = "INSERT INTO $config_table_name (par_name,par_value) VALUES ('write_log','$write_log_value')";
$DB->queryOrDie($query, $DB->error());
$query = "INSERT INTO $config_table_name (par_name,par_value) VALUES ('log_file','$log_value')";
$DB->queryOrDie($query, $DB->error());

if ($DB->tableExists($table)) {
    if (!$DB->fieldExists($table, $field, false)) {
        $migration->addField(
            $table,
            $field,
            $type
        );
    }
}

$migration->executeMigration();
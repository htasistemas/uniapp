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
    "validation_user_types" => "",
    "enable_attachments" => "0",
    "color_header" => "#005a8d",
    "color_buttons" => "#486d1b",
    "color_background" => "#ffffff",
    "color_text" => "#333333"
];

$write_log = '0';
$plugin_name = "uniapp";
$config_table_name = "glpi_plugin_" . $plugin_name . "_config";
$configfile = __DIR__ . "/" . $plugin_name . ".cfg";
$logfile = '';

$fd = null;
if (file_exists($configfile)) {
    $fd = fopen($configfile, 'r');
}

if ($fd) {
    // Lê variaveis do arquivo de configuracao se existir
    while (!feof($fd)) {
        $str = trim(fgets($fd));
        if ($str === '') {
            continue;
        }

        $a = explode("=", $str, 2);

        if ($a[0] === 'write_log') {
            $write_log = $a[1];
        } elseif (array_key_exists($a[0], $parameters)) {
            $parameters[$a[0]] = $a[1];
        }
    }

    fclose($fd);
}

global $DB;

if (!$DB->tableExists($config_table_name)) {
    // Cria tabela de configuracoes com chave unica para nao duplicar parametros
    $query = "CREATE TABLE $config_table_name (
                  id INT AUTO_INCREMENT PRIMARY KEY,
                  par_name VARCHAR(255) NOT NULL,
                  par_value TEXT NOT NULL,
                  UNIQUE KEY (par_name)
               ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $DB->queryOrDie($query, $DB->error());
}

foreach ($parameters as $key => $value) {
    $escapedKey = $DB->escape($key);
    $escapedValue = $DB->escape($value);
    $query = "INSERT INTO $config_table_name (par_name,par_value)
                 VALUES ('$escapedKey','$escapedValue')
                 ON DUPLICATE KEY UPDATE par_value = '$escapedValue'";
    $DB->queryOrDie($query, $DB->error());
}

$log_value = $DB->escape($logfile);
$write_log_value = $DB->escape($write_log);
$query = "INSERT INTO $config_table_name (par_name,par_value)
             VALUES ('write_log','$write_log_value')
             ON DUPLICATE KEY UPDATE par_value = '$write_log_value'";
$DB->queryOrDie($query, $DB->error());
$query = "INSERT INTO $config_table_name (par_name,par_value)
             VALUES ('log_file','$log_value')
             ON DUPLICATE KEY UPDATE par_value = '$log_value'";
$DB->queryOrDie($query, $DB->error());

// Cria tabela de tokens para nao alterar a tabela nativa de usuarios
$token_table = "glpi_plugin_" . $plugin_name . "_user_tokens";

if (!$DB->tableExists($token_table)) {
    $query = "CREATE TABLE $token_table (
                  id INT(10) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                  users_id INT(10) UNSIGNED NOT NULL,
                  fcm_token VARCHAR(255) NOT NULL,
                  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                  UNIQUE KEY (users_id),
                  INDEX (fcm_token),
                  CONSTRAINT fk_uniapp_user FOREIGN KEY (users_id) REFERENCES glpi_users(id) ON DELETE CASCADE
               ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $DB->queryOrDie($query, $DB->error());
}

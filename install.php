<?php
/**
 * Hook de instalação/atualização do plugin UNIAPP
 * Compatível com GLPI 10.x
 */

function plugin_uniapp_install() {
    global $DB;

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

    // ------------------------
    // 1) Definições básicas
    // ------------------------
    $plugin_name       = "uniapp";
    $config_table_name = "glpi_plugin_" . $plugin_name . "_config";
    $configfile        = __DIR__ . "/" . $plugin_name . ".cfg";
    $logfile           = "/opt/unihelp-prod/glpi/public/uniapp.log";

    $parameters = [
        "fcm_project_id"      => "",
        "fcm_client_email"    => "",
        "fcm_private_key"     => "",
        "ticket_title"        => "",
        "ticket_message"      => "",
        "ticket_user_types"   => "",
        "followup_title"      => "",
        "followup_message"    => "",
        "followup_user_types" => "",
        "solution_title"      => "",
        "solution_message"    => "",
        "solution_user_types" => "",
        "validation_title"    => "",
        "validation_message"  => "",
        "validation_user_types"=> ""
    ];

    $write_log = "1";

    // campo extra na glpi_users
    $table = "glpi_users";
    $field = "fcm_token";

    // ------------------------
    // 2) Ler o arquivo .cfg
    // ------------------------
    $fd = fopen($configfile, "r");
    if (!$fd) {
        throw new \RuntimeException("Não foi possível abrir o arquivo de configuração '$configfile'");
    }

    while (!feof($fd)) {
        $str = trim(fgets($fd));
        if ($str === "" || strpos($str, "=") === false) {
            continue;
        }
        $a = explode("=", $str, 2);

        if ($a[0] === "write_log") {
            $write_log = $a[1];
        } elseif (array_key_exists($a[0], $parameters)) {
            $parameters[$a[0]] = $a[1];
        }
    }
    fclose($fd);

    if ($write_log === "") {
        $write_log = "1";
    }

    // valida parâmetros obrigatórios
    foreach ($parameters as $key => $val) {
        if ($val === "") {
            throw new \RuntimeException("Parâmetro '$key' está vazio. Verifique o arquivo '$configfile'");
        }
    }

    // ------------------------
    // 3) Migration (schema)
    // ------------------------
    $migration = new Migration(100);

    // 3.1) Cria tabela de config (DDL via addPostQuery)
    if (!$DB->tableExists($config_table_name)) {
        $query = "
            CREATE TABLE `$config_table_name` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `par_name` VARCHAR(255) NOT NULL,
                `par_value` TEXT NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `par_name` (`par_name`)
            ) ENGINE=InnoDB
              DEFAULT CHARSET=utf8mb4
              COLLATE=utf8mb4_unicode_ci
              ROW_FORMAT=DYNAMIC
        ";
        $migration->addPostQuery($query);
    }

    // 3.2) Adiciona campo fcm_token na glpi_users (via Migration)
    if ($DB->tableExists($table) && !$DB->fieldExists($table, $field, false)) {
        // 'string' é o tipo padrão equivalente a VARCHAR(255)
        $migration->addField($table, $field, "string");
    }

    // executa tudo que foi registrado acima
    $migration->executeMigration();

    // ------------------------
    // 4) Inserir configs (DML via DB->insert)
    // ------------------------
    // garante idempotência (rodar install de novo não duplica)
    foreach ($parameters as $key => $val) {
        $DB->delete($config_table_name, ["par_name" => $key]);
        $DB->insert($config_table_name, [
            "par_name"  => $key,
            "par_value" => $val
        ]);
    }

    // write_log e log_file
    $DB->delete($config_table_name, [
        "par_name" => ["IN", ["write_log", "log_file"]]
    ]);

    $DB->insert($config_table_name, [
        "par_name"  => "write_log",
        "par_value" => $write_log
    ]);

    $DB->insert($config_table_name, [
        "par_name"  => "log_file",
        "par_value" => $logfile
    ]);

    return true;
}

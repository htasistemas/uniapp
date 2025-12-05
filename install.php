<?php

/* Tipos de usuários
      1: Solicitante
      2: Técnico responsável
      3: Observador
    104: Destinatário
    105: Último atualizador
    206: Criador do acompanhamento
    207: Editor do acompanhamento
    308: Criador da solução
    309: Editor da solução
    310: Aprovador da solução
    411: Criador da aprovação
    412: Validador da aprovação
*/

global $DB;

$plugin_name        = 'uniapp';
$config_table_name  = "glpi_plugin_{$plugin_name}_config";
$token_table_name   = "glpi_plugin_{$plugin_name}_user_tokens";
$configfile         = __DIR__ . "/{$plugin_name}.cfg";

/**
 * Parâmetros padrão de configuração
 */
$parameters = [
    "fcm_project_id"        => "",
    "fcm_client_email"      => "",
    "fcm_private_key"       => "",
    "fcm_super_admin_user"  => "",
    "fcm_super_admin_password" => "",
    "ticket_title"          => "",
    "ticket_message"        => "",
    "ticket_user_types"     => "",
    "followup_title"        => "",
    "followup_message"      => "",
    "followup_user_types"   => "",
    "solution_title"        => "",
    "solution_message"      => "",
    "solution_user_types"   => "",
    "validation_title"      => "",
    "validation_message"    => "",
    "validation_user_types" => "",
    "enable_attachments"    => "0",
    "color_header"          => "#1A3557",
    "color_buttons"         => "#C7802F",
    "color_background"      => "#F5F5F5",
    "color_text"            => "#000000",
    "color_primary"         => "#1A3557",
    "color_primary_light"   => "#0D1A29",
    "color_primary_on"      => "#FFFFFF",
    "color_secondary"       => "#C7802F",
    "color_secondary_on"    => "#FFFFFF",
    "color_background_shadow" => "#000000",
    "color_content"         => "#F5F5F5",
    "color_content_on"      => "#000000",
    "color_content_on_light" => "#808080",
    "color_highlight"       => "#C7802F",
    "color_highlight_on"    => "#FFFFFF",
    "color_highlight_on_light" => "#FFFFFF",
    "color_alert"           => "#FFD700",
    "color_alert_on"        => "#000000",
    "color_success"         => "#008000",
    "color_warning"         => "#FF8C00",
    "color_critical"        => "#FF0000",
    "color_critical_on"     => "#FFFFFF",
    "color_completed"       => "#000000",
    "color_login_primary"            => "#1A3557",
    "color_login_primary_on"         => "#FFFFFF",
    "color_login_secondary"          => "#C7802F",
    "color_login_secondary_on"       => "#FFFFFF",
    "color_login_background_shadow"  => "#000000",
    "color_login_input_background"   => "#FFFFFF",
    "color_login_input_on"           => "#FFFFFF",
    "color_login_input_icon"         => "#1A3557",
    "color_login_highlight"          => "#C7802F",
    "color_login_highlight_on"       => "#FFFFFF",
    "color_login_highlight_on_light" => "#FFFFFF",
    "color_login_critical"           => "#FF7F7F",
    "color_splash_primary"           => "#1A3557",
    "color_splash_primary_on"        => "#FFFFFF",
    // chaves presentes na tela:
    "write_log"             => "0",
    "log_file"              => "",
    "public_colors_rps"     => "300",
    "public_colors_version" => "0",
    "public_colors_updated_at" => "",
    "app_max_tickets"       => "500",
    "app_max_tickets_old"   => "10",
    "app_max_files"         => "5",
    "app_max_file_size_mb"  => "2",
    "app_grid_space"        => "5",
    "app_text_scale"        => "1",
    "app_icon_scale"        => "1",
    "app_max_image_height"  => "400",
    "app_max_image_width"   => "300",
    "app_logo_png"          => "",
    "app_splash_png"        => "",
    "app_icon_png"          => "",
    "app_favicon_png"       => "",
    "app_adaptive_icon_png" => ""
];

// Leitura opcional de uniapp.cfg (compatibilidade)
if (is_readable($configfile)) {
    $fd = fopen($configfile, 'r');
    if ($fd) {
        while (!feof($fd)) {
            $str = trim((string)fgets($fd));
            if ($str === '' || strpos($str, '=') === false) {
                continue;
            }
            [$k, $v] = explode('=', $str, 2);
            if ($k === 'write_log') {
                $parameters['write_log'] = $v;
            } elseif (array_key_exists($k, $parameters)) {
                $parameters[$k] = $v;
            }
        }
        fclose($fd);
    }
}

/**
 * Criação das tabelas
 */
if (!$DB->tableExists($config_table_name)) {
    $sql = "CREATE TABLE `$config_table_name` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `par_name`  VARCHAR(255) NOT NULL,
                `par_value` TEXT NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq_par_name` (`par_name`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $DB->queryOrDie($sql, 'Falha ao criar tabela de configuração do UniApp');
}

if (!$DB->tableExists($token_table_name)) {
    // fcm_token pode ser grande — use VARCHAR(2048); índice por users_id (consulta principal)
    $sql = "CREATE TABLE `$token_table_name` (
                `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
                `users_id` INT(10) UNSIGNED NOT NULL,
                `fcm_token` VARCHAR(2048) NOT NULL,
                `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq_users_id` (`users_id`),
                CONSTRAINT `fk_{$plugin_name}_user`
                  FOREIGN KEY (`users_id`) REFERENCES `glpi_users`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $DB->queryOrDie($sql, 'Falha ao criar tabela de tokens do UniApp');
}

/**
 * UPGRADE de esquema (instalações antigas):
 * - Ajusta fcm_token para VARCHAR(2048) quando menor.
 */
if ($DB->tableExists($token_table_name)) {
    try {
        // SHOW COLUMNS retorna Type (ex.: varchar(255))
        $col = $DB->request([
            'QUERY' => "SHOW COLUMNS FROM `$token_table_name` LIKE 'fcm_token'"
        ])->current();

        if ($col && isset($col['Type'])) {
            $type = strtolower((string)$col['Type']); // ex: varchar(255)
            if (preg_match('/^varchar\((\d+)\)$/', $type, $m)) {
                $size = (int)$m[1];
                if ($size < 2048) {
                    $sql = "ALTER TABLE `$token_table_name`
                            MODIFY `fcm_token` VARCHAR(2048) NOT NULL";
                    $DB->queryOrDie($sql, 'Falha ao atualizar tamanho de fcm_token');
                }
            }
        }
    } catch (Throwable $e) {
        // Mantém a instalação mesmo se o upgrade de coluna falhar, mas registra o motivo
        $DB->queryOrDie('SELECT 1', 'Aviso (upgrade fcm_token): ' . $e->getMessage());
    }
}

/**
 * Semeadura (upsert) dos parâmetros
 */
$inTx = false;
try {
    if (method_exists($DB, 'beginTransaction')) {
        $DB->beginTransaction();
        $inTx = true;
    }

    foreach ($parameters as $key => $value) {
        $exists = false;
        $check = $DB->request([
            'SELECT' => ['id'],
            'FROM'   => $config_table_name,
            'WHERE'  => ['par_name' => (string)$key],
            'LIMIT'  => 1
        ]);
        foreach ($check as $row) {
            $exists = true;
            break;
        }

        if ($exists) {
            if (!$DB->update($config_table_name,
                ['par_value' => (string)$value],
                ['par_name'  => (string)$key]
            )) {
                throw new RuntimeException('Falha ao atualizar configuração: ' . $DB->error());
            }
        } else {
            $ok = $DB->insert($config_table_name, [
                'par_name'  => (string)$key,
                'par_value' => (string)$value
            ]);
            if (!$ok) {
                throw new RuntimeException('Falha ao inserir configuração: ' . $DB->error());
            }
        }
    }

    if ($inTx && method_exists($DB, 'commit')) {
        $DB->commit();
    }
} catch (Throwable $e) {
    if ($inTx && method_exists($DB, 'rollBack')) {
        $DB->rollBack();
    }
    // Deixa claro ao admin
    $DB->queryOrDie('SELECT 1', 'Erro na instalação do UniApp: ' . $e->getMessage());
}

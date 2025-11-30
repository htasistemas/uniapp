<?php

class PluginUniappConfig extends CommonDBTM
{
    private const CONFIG_TABLE = 'glpi_plugin_uniapp_config';
    private static $cache = null;

    /**
     * Retorna todos os valores salvos de configuracao em cache.
     */
    public static function getAll(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        global $DB;
        $config = [];

        if (!$DB->tableExists(self::CONFIG_TABLE)) {
            return $config;
        }

        $query = "SELECT par_name, par_value FROM " . self::CONFIG_TABLE;
        foreach ($DB->request($query) as $row) {
            $config[$row['par_name']] = $row['par_value'];
        }

        self::$cache = $config;
        return $config;
    }

    /**
     * Busca uma chave especifica com valor padrao.
     */
    public static function get(string $key, $default = null)
    {
        $values = self::getAll();
        return array_key_exists($key, $values) ? $values[$key] : $default;
    }

    /**
     * Persiste os valores informados e limpa o cache.
     */
    public static function save(array $values): array
    {
        global $DB;
        $errors = [];

        if (!$DB->tableExists(self::CONFIG_TABLE)) {
            $errors[] = 'Tabela de configuracoes nao encontrada';
            return $errors;
        }

        foreach ($values as $name => $value) {
            $name = trim($name);
            if ($name === '') {
                continue;
            }

            $escapedName = $DB->escape($name);
            $escapedValue = $DB->escape(trim((string)$value));
            $query = "INSERT INTO " . self::CONFIG_TABLE . " (par_name, par_value)
                        VALUES ('$escapedName', '$escapedValue')
                        ON DUPLICATE KEY UPDATE par_value = '$escapedValue'";

            if (!$DB->query($query)) {
                $errors[] = $DB->error();
            }
        }

        self::$cache = null;
        return $errors;
    }
}

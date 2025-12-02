<?php

class PluginUniappConfig extends CommonDBTM
{
    private const CONFIG_TABLE = 'glpi_plugin_uniapp_config';

    /** @var array<string,string>|null */
    private static $cache = null;

    /**
     * Retorna todos os valores salvos de configuração em cache.
     *
     * @return array<string,string>
     */
    public static function getAll(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        global $DB;
        $config = [];

        if (!$DB->tableExists(self::CONFIG_TABLE)) {
            // tabela ainda não criada (plugin não instalado/migrado)
            return $config;
        }

        // SELECT estruturado garante escaping e consistência
        $it = $DB->request([
            'SELECT' => ['par_name', 'par_value'],
            'FROM'   => self::CONFIG_TABLE,
        ]);

        foreach ($it as $row) {
            // garante array<string,string>
            $name  = (string)($row['par_name'] ?? '');
            $value = (string)($row['par_value'] ?? '');
            if ($name !== '') {
                $config[$name] = $value;
            }
        }

        self::$cache = $config;
        return $config;
    }

    /**
     * Busca uma chave específica com valor padrão.
     *
     * @param mixed $default
     * @return mixed
     */
    public static function get(string $key, $default = null)
    {
        $values = self::getAll();
        return array_key_exists($key, $values) ? $values[$key] : $default;
    }

    /**
     * Persiste os valores informados (UPSERT por par_name) e limpa o cache.
     * Usa helpers estruturados do GLPI ($DB->update/$DB->insert) e transação.
     *
     * @param array<string,scalar|Stringable|null> $values
     * @return string[] Lista de mensagens de erro (vazia em caso de sucesso)
     */
    public static function save(array $values): array
    {
        global $DB;
        $errors = [];

        if (!$DB->tableExists(self::CONFIG_TABLE)) {
            $errors[] = 'Tabela de configurações não encontrada';
            return $errors;
        }

        // Inicia transação para operação atômica
        $inTransaction = false;
        try {
            if (method_exists($DB, 'beginTransaction')) {
                $DB->beginTransaction();
                $inTransaction = true;
            }

            foreach ($values as $name => $value) {
                $name = trim((string)$name);
                if ($name === '') {
                    continue; // ignora chaves vazias
                }

                // normaliza valor para string (GLPI geralmente armazena configs como texto)
                if ($value instanceof Stringable) {
                    $value = (string)$value;
                } elseif (is_bool($value)) {
                    $value = $value ? '1' : '0';
                } elseif ($value === null) {
                    $value = '';
                } elseif (is_scalar($value)) {
                    $value = (string)$value;
                } else {
                    // evita serializações indevidas
                    $value = '';
                }

                // Tenta UPDATE por par_name; se não afetou linhas, faz INSERT
                $DB->update(self::CONFIG_TABLE,
                    ['par_value' => $value],
                    ['par_name'  => $name]
                );

                if ((int)$DB->affected_rows() === 0) {
                    $ok = $DB->insert(self::CONFIG_TABLE, [
                        'par_name'  => $name,
                        'par_value' => $value
                    ]);
                    if (!$ok) {
                        $errors[] = $DB->error();
                    }
                }
            }

            if ($inTransaction && method_exists($DB, 'commit')) {
                $DB->commit();
            }
        } catch (Throwable $e) {
            if ($inTransaction && method_exists($DB, 'rollBack')) {
                $DB->rollBack();
            }
            $errors[] = $e->getMessage();
        }

        // invalida cache após salvar
        self::$cache = null;

        return $errors;
    }

    /**
     * Invalida o cache manualmente (caso necessário em outros fluxos).
     */
    public static function invalidateCache(): void
    {
        self::$cache = null;
    }
}

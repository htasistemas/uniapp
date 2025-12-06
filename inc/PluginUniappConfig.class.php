<?php

class PluginUniappConfig extends CommonDBTM
{
    private const CONFIG_TABLE = 'glpi_plugin_uniapp_config';
    private const COLOR_FIELDS = [
        'color_header'               => '#1A3557',
        'color_buttons'              => '#C7802F',
        'color_background'           => '#F5F5F5',
        'color_text'                 => '#000000',
        'color_primary'              => '#1A3557',
        'color_primary_light'        => '#0D1A29',
        'color_primary_on'           => '#FFFFFF',
        'color_secondary'            => '#C7802F',
        'color_secondary_on'         => '#FFFFFF',
        'color_background_shadow'    => '#000000',
        'color_content'              => '#F5F5F5',
        'color_content_on'           => '#000000',
        'color_content_on_light'     => '#808080',
        'color_highlight'            => '#C7802F',
        'color_highlight_on'         => '#FFFFFF',
        'color_highlight_on_light'   => '#FFFFFF',
        'color_alert'                => '#FFD700',
        'color_alert_on'             => '#000000',
        'color_success'              => '#008000',
        'color_warning'              => '#FF8C00',
        'color_critical'             => '#FF0000',
        'color_critical_on'          => '#FFFFFF',
        'color_completed'            => '#000000',
        'color_login_primary'        => '#1A3557',
        'color_login_primary_on'     => '#FFFFFF',
        'color_login_secondary'      => '#C7802F',
        'color_login_secondary_on'   => '#FFFFFF',
        'color_login_background_shadow' => '#000000',
        'color_login_input_background'=> '#FFFFFF',
        'color_login_input_on'        => '#FFFFFF',
        'color_login_input_icon'      => '#1A3557',
        'color_login_highlight'       => '#C7802F',
        'color_login_highlight_on'    => '#FFFFFF',
        'color_login_highlight_on_light' => '#FFFFFF',
        'color_login_critical'        => '#FF7F7F',
        'color_splash_primary'       => '#1A3557',
        'color_splash_primary_on'    => '#FFFFFF',
    ];

    private const LOGO_STORAGE_MAP = [
        'app_logo_png'   => 'logo.png',
        'app_splash_png' => 'splash.png',
    ];

    private const LOGOS_SUBDIR = 'uniapp/logos';

    /** @var array<string,string>|null */
    private static $cache = null;

    /**
     * Retorna o mapa padrao de cores do plugin.
     *
     * @return array<string,string>
     */
    public static function getDefaultColors(): array
    {
        return self::COLOR_FIELDS;
    }

    public static function getLogosDirectory(): string
    {
        if (!defined('GLPI_PLUGIN_DOC_DIR')) {
            $base = defined('GLPI_DOC_DIR') ? rtrim(GLPI_DOC_DIR, '/') : '';
            if ($base === '') {
                $base = '/';
            }
            return $base . '/' . self::LOGOS_SUBDIR;
        }

        return rtrim(GLPI_PLUGIN_DOC_DIR, '/') . '/' . self::LOGOS_SUBDIR;
    }

    public static function ensureLogosDirectory(): bool
    {
        $dir = self::getLogosDirectory();
        if (is_dir($dir)) {
            return true;
        }

        return mkdir($dir, 0755, true);
    }

    public static function getLogoStorageFilename(string $field): ?string
    {
        return self::LOGO_STORAGE_MAP[$field] ?? null;
    }

    public static function getLogoBase64(string $field): string
    {
        $filename = self::normalizeLogoValue($field);
        if ($filename === '') {
            return '';
        }

        $path = self::getLogosDirectory() . '/' . basename($filename);
        if (!is_file($path)) {
            return '';
        }

        $content = file_get_contents($path);
        return $content === false ? '' : base64_encode($content);
    }

    private static function normalizeLogoValue(string $field): string
    {
        $value = trim((string)self::get($field, ''));
        if ($value === '') {
            return '';
        }

        $storageDir = self::getLogosDirectory();
        if (!self::ensureLogosDirectory()) {
            return '';
        }

        $targetName = self::getLogoStorageFilename($field);
        if ($targetName === null) {
            $targetName = basename($value);
        }

        $path = $storageDir . '/' . $targetName;
        if ($value === $targetName && is_file($path)) {
            return $targetName;
        }

        $cleanValue = preg_replace('/\\s+/', '', $value);
        if ($cleanValue !== '' && strlen($cleanValue) > 64 && preg_match('/^[A-Za-z0-9+\\/=]+$/', $cleanValue)) {
            $decoded = base64_decode($cleanValue, true);
            if ($decoded !== false) {
                file_put_contents($path, $decoded, LOCK_EX);
                @chmod($path, 0644);
                self::save([$field => $targetName]);
                return $targetName;
            }
        }

        if (is_file($path)) {
            return $targetName;
        }

        return '';
    }

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

                $exists = false;
                $check = $DB->request([
                    'SELECT' => ['id'],
                    'FROM'   => self::CONFIG_TABLE,
                    'WHERE'  => ['par_name' => $name],
                    'LIMIT'  => 1
                ]);
                foreach ($check as $row) {
                    $exists = true;
                    break;
                }

                if ($exists) {
                    if (!$DB->update(self::CONFIG_TABLE, ['par_value' => $value], ['par_name' => $name])) {
                        $errors[] = $DB->error();
                    }
                } else {
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

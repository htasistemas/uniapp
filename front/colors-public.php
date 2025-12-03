<?php
define('GLPI_ROOT', dirname(__DIR__, 3));
require_once GLPI_ROOT . '/inc/includes.php';

Plugin::load('uniapp');
require_once __DIR__ . '/../inc/PluginUniappConfig.class.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error'   => 'Metodo nao permitido'
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

$limit = get_public_colors_rate_limit();
if ($limit > 0) {
    enforce_public_colors_rate_limit($limit);
}

$defaultColors = PluginUniappConfig::getDefaultColors();
$configValues = array_merge($defaultColors, PluginUniappConfig::getAll());
$colors = [];
foreach ($defaultColors as $key => $default) {
    $colors[$key] = $configValues[$key] ?? $default;
}

$version = PluginUniappConfig::get('public_colors_version', '0');
$updatedAt = (int)PluginUniappConfig::get('public_colors_updated_at', '0');

echo json_encode([
    'success'    => true,
    'version'    => $version,
    'updated_at' => $updatedAt,
    'colors'     => $colors
], JSON_UNESCAPED_SLASHES);

/**
 * @return int
 */
function get_public_colors_rate_limit(): int
{
    static $limit = null;
    if ($limit !== null) {
        return $limit;
    }

    $raw = PluginUniappConfig::get('public_colors_rps', '300');
    $value = (int)$raw;
    if ($value <= 0) {
        $value = 300;
    }

    $limit = $value;
    return $limit;
}

/**
 * Bloqueia a API quando a taxa configurada por segundo for ultrapassada.
 */
function enforce_public_colors_rate_limit(int $limit, float $windowSeconds = 1.0): void
{
    $storage = sys_get_temp_dir() . '/uniapp-public-colors-rate.json';
    $windowKey = (int)floor(microtime(true) / $windowSeconds);
    $state = ['window' => $windowKey, 'count' => 0];

    $handle = @fopen($storage, 'c+b');
    if (!$handle) {
        return;
    }

    if (!flock($handle, LOCK_EX)) {
        fclose($handle);
        return;
    }

    $content = stream_get_contents($handle);
    if ($content !== '') {
        $existing = json_decode($content, true);
        if (is_array($existing) && isset($existing['window'], $existing['count'])) {
            if ((int)$existing['window'] === $windowKey) {
                $state['count'] = max(0, (int)$existing['count']);
            }
        }
    }

    if ($state['count'] >= $limit) {
        flock($handle, LOCK_UN);
        fclose($handle);
        http_response_code(429);
        echo json_encode([
            'success' => false,
            'error'   => 'Limite temporario atingido'
        ], JSON_UNESCAPED_SLASHES);
        exit;
    }

    $state['count']++;

    ftruncate($handle, 0);
    rewind($handle);
    fwrite($handle, json_encode($state));
    fflush($handle);
    flock($handle, LOCK_UN);
    fclose($handle);
}

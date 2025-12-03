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

/** @fixme taxa baixa para minimizar risco de abusos em API sem login */
function enforce_rate_limit(string $identity, int $intervalSeconds = 5): void
{
    $hash = sha1($identity);
    $storage = sys_get_temp_dir() . "/uniapp-public-colors-{$hash}";
    $now = time();

    $last = 0;
    if (is_file($storage)) {
        $last = (int)@file_get_contents($storage);
    }

    if ($last !== 0 && ($now - $last) < $intervalSeconds) {
        http_response_code(429);
        echo json_encode([
            'success' => false,
            'error'   => 'Taxa limite atingida'
        ], JSON_UNESCAPED_SLASHES);
        exit;
    }

    file_put_contents($storage, (string)$now, LOCK_EX);
}

$source = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
enforce_rate_limit($source);

$defaultColors = PluginUniappConfig::getDefaultColors();
$configValues = array_merge($defaultColors, PluginUniappConfig::getAll());
$colors = [];
foreach ($defaultColors as $key => $default) {
    $colors[$key] = $configValues[$key] ?? $default;
}

echo json_encode(['success' => true, 'colors' => $colors], JSON_UNESCAPED_SLASHES);

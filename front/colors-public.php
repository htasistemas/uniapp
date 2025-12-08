<?php
define('GLPI_ROOT', dirname(__DIR__, 3));
require_once GLPI_ROOT . '/inc/includes.php';

Plugin::load('uniapp');
require_once __DIR__ . '/../inc/PluginUniappConfig.class.php';
require_once __DIR__ . '/public-rate-limit.php';

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

$limit = get_public_rate_limit();
if ($limit > 0) {
    enforce_public_rate_limit($limit, 1.0, 'uniapp-public-colors-rate.json');
}

$defaultColors = PluginUniappConfig::getDefaultColors();
$configValues = array_merge($defaultColors, PluginUniappConfig::getAll());
$colors = [];
foreach ($defaultColors as $key => $default) {
    $colors[$key] = $configValues[$key] ?? $default;
}

$version = PluginUniappConfig::get('public_colors_version', '0');
$updatedAt = PluginUniappConfig::get('public_colors_updated_at', '');
echo json_encode([
    'success'    => true,
    'version'    => $version,
    'updated_at' => $updatedAt,
    'rate_limit' => $limit,
    'settings'   => ['rate_limit' => $limit],
    'colors'     => $colors
], JSON_UNESCAPED_SLASHES);

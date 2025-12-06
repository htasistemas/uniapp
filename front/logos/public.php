<?php
define('GLPI_ROOT', dirname(__DIR__, 4));
require_once GLPI_ROOT . '/inc/includes.php';

Plugin::load('uniapp');
require_once __DIR__ . '/../../inc/PluginUniappConfig.class.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    respond(['success' => false, 'error' => 'Metodo nao permitido'], 405);
}

$pathInfo = $_SERVER['PATH_INFO'] ?? '';
if ($pathInfo === '' && isset($_SERVER['REQUEST_URI'], $_SERVER['SCRIPT_NAME'])) {
    $pathInfo = substr($_SERVER['REQUEST_URI'], strlen($_SERVER['SCRIPT_NAME']));
    if ($pathInfo === false) {
        $pathInfo = '';
    }
}

$resource = trim((string)$pathInfo, '/');
$allowedResources = [
    'logo'   => 'app_logo_png',
    'splash' => 'app_splash_png'
];

if ($resource === '' || !array_key_exists($resource, $allowedResources)) {
    respond(['success' => false, 'error' => 'Recurso nao encontrado'], 404);
}

$image = PluginUniappConfig::getLogoBase64($allowedResources[$resource]);

$version = PluginUniappConfig::get('public_logos_version', '0');
$updatedAt = PluginUniappConfig::get('public_logos_updated_at', '');

respond([
    'success'    => true,
    'resource'   => $resource,
    'version'    => $version,
    'updated_at' => $updatedAt,
    'image'      => $image
]);

/**
 * Sends JSON response and terminates execution.
 *
 * @param array<string,mixed> $payload
 * @param int $statusCode
 */
function respond(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

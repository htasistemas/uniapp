<?php
define('GLPI_ROOT', dirname(__DIR__, 4));
require_once GLPI_ROOT . '/inc/includes.php';

Plugin::load('uniapp');
require_once __DIR__ . '/../../inc/PluginUniappConfig.class.php';
require_once __DIR__ . '/../public-rate-limit.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    respondError('Metodo nao permitido', 405);
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
    respondError('Recurso nao encontrado', 404);
}

$limit = get_public_rate_limit();
if ($limit > 0) {
    enforce_public_rate_limit($limit, 1.0, 'uniapp-public-logos-rate.json');
}

$logoField = $allowedResources[$resource];
$logoPath = PluginUniappConfig::getLogoPath($logoField);
if ($logoPath === '' || !is_readable($logoPath)) {
    respondError('Logo nao encontrada', 404);
}

clearstatcache(true, $logoPath);
$size = @filesize($logoPath);
if ($size === false) {
    respondError('Falha ao obter tamanho da imagem', 500);
}

header('Content-Type: image/png');
header('Content-Length: ' . $size);
header('Cache-Control: max-age=86400, public');
header('Access-Control-Allow-Origin: *');

readfile($logoPath);
exit;

function respondError(string $message, int $statusCode): void
{
    http_response_code($statusCode);
    header('Content-Type: text/plain; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    echo $message;
    exit;
}

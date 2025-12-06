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

$settings = [
    'enable_attachments' => PluginUniappConfig::get('enable_attachments', '0') === '1',
    'max_tickets'        => (int)PluginUniappConfig::get('app_max_tickets', '500'),
    'max_tickets_old'    => (int)PluginUniappConfig::get('app_max_tickets_old', '10'),
    'max_files'          => (int)PluginUniappConfig::get('app_max_files', '5'),
    'max_file_size_mb'   => (int)PluginUniappConfig::get('app_max_file_size_mb', '2'),
    'grid_space'         => (int)PluginUniappConfig::get('app_grid_space', '5'),
    'text_scale'         => (float)PluginUniappConfig::get('app_text_scale', '1'),
    'icon_scale'         => (float)PluginUniappConfig::get('app_icon_scale', '1'),
    'max_image_height'   => (int)PluginUniappConfig::get('app_max_image_height', '400'),
    'max_image_width'    => (int)PluginUniappConfig::get('app_max_image_width', '300'),
    'write_log'          => PluginUniappConfig::get('write_log', '0') === '1',
    'log_file'           => PluginUniappConfig::get('log_file', '')
];

echo json_encode([
    'success'  => true,
    'settings' => $settings
], JSON_UNESCAPED_SLASHES);

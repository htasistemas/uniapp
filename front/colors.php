<?php
require_once '../../../inc/includes.php';
require_once __DIR__ . '/../inc/PluginUniappConfig.class.php';

header('Content-Type: application/json; charset=utf-8');

Session::checkLoginUser();

$defaultColors = [
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

$configValues = array_merge($defaultColors, PluginUniappConfig::getAll());
$colors = [];
foreach ($defaultColors as $key => $default) {
    $colors[$key] = $configValues[$key] ?? $default;
}

echo json_encode(['success' => true, 'colors' => $colors], JSON_UNESCAPED_SLASHES);

<?php
require_once '../../../inc/includes.php';
require_once __DIR__ . '/../inc/PluginUniappConfig.class.php';

header('Content-Type: application/json; charset=utf-8');

Session::checkLoginUser();

$defaultColors = PluginUniappConfig::getDefaultColors();

$configValues = array_merge($defaultColors, PluginUniappConfig::getAll());
$colors = [];
foreach ($defaultColors as $key => $default) {
    $colors[$key] = $configValues[$key] ?? $default;
}

echo json_encode(['success' => true, 'colors' => $colors], JSON_UNESCAPED_SLASHES);

<?php
require_once '../../../inc/includes.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$user_id = $input['user_id'] ?? null;
$token = $input['token'] ?? null;

if (!$user_id || !$token) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit;
}

$result = PluginUniappEvent::saveUserFcmToken((int)$user_id, $token);

if (is_array($result) && ($result['success'] ?? false)) {
    echo json_encode([
        'success' => true,
        'already_synced' => $result['already_synced'] ?? false,
        'message' => $result['message'] ?? null
    ]);
} else {
    http_response_code(500);
    $message = is_array($result) ? ($result['message'] ?? 'Failed to update token') : 'Failed to update token';
    echo json_encode(['success' => false, 'message' => $message]);
}

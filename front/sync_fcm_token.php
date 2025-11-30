<?php
require_once '../../../inc/includes.php';
require_once __DIR__ . '/../inc/event.class.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$user_id = isset($input['user_id']) ? (int)$input['user_id'] : 0;
$token = trim((string)$input['token']);

if ($user_id <= 0 || $token === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing or invalid parameters']);
    exit;
}

$result = PluginUniappEvent::saveUserFcmToken($user_id, $token);

if (!empty($result['success'])) {
    echo json_encode([
        'success' => true,
        'already_synced' => !empty($result['already_synced']),
        'message' => $result['message'] ?? null
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $result['message'] ?? 'Failed to update token'
    ]);
}

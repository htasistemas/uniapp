<?php
require_once '../../../inc/includes.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Metodo nao permitido']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$user_id = $input['user_id'] ?? null;
$token = $input['token'] ?? null;

if (!$user_id || !$token) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Parametros ausentes']);
    exit;
}

// Persiste o token FCM usando a tabela exclusiva para usuarios
$result = PluginUniappEvent::saveUserFcmToken((int)$user_id, $token);

if (is_array($result)) {
    if ($result['success'] ?? false) {
        echo json_encode([
            'success' => true,
            'already_synced' => $result['already_synced'] ?? false
        ]);
        exit;
    }

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $result['message'] ?? 'Falha ao atualizar o token'
    ]);
    exit;
}

http_response_code(500);
echo json_encode(['success' => false, 'message' => 'Falha inesperada ao salvar token']);

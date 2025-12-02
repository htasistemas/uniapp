<?php
require_once '../../../inc/includes.php';

header('Content-Type: application/json');

// Exige POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

// Exige sessão válida (usuário logado)
Session::checkLoginUser();

// Captura token CSRF: primeiro do header, depois do corpo JSON (fallback)
$csrfToken = $_SERVER['HTTP_X_GLPI_CSRF_TOKEN'] ?? null;

// Lê o corpo uma única vez para reaproveitar
$rawBody = file_get_contents('php://input');
$input = json_decode($rawBody, true);
if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'JSON inválido']);
    exit;
}

if (!$csrfToken) {
    $csrfToken = $input['_glpi_csrf_token'] ?? null;
}

// Valida CSRF (plugin declara csrf_compliant, então toda ação sensível deve validar)
if (!$csrfToken) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'CSRF token ausente']);
    exit;
}
try {
    Session::checkCSRF(['_glpi_csrf_token' => $csrfToken]);
} catch (Throwable $e) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'CSRF check failed']);
    exit;
}

// Extrai parâmetros
$user_id  = isset($input['user_id']) ? (int)$input['user_id'] : null;
$fcmToken = isset($input['token']) ? trim((string)$input['token']) : null;

if (!$user_id || $fcmToken === '' ) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Parâmetros ausentes']);
    exit;
}

// Regras de autorização:
// - o próprio usuário pode sincronizar seu token
// - usuários com direito de atualizar usuários/config podem sincronizar para outros
$loggedId = (int) Session::getLoginUserID();
$canSyncOthers = Session::haveRight('user', UPDATE) || Session::haveRight('config', UPDATE);

if ($user_id !== $loggedId && !$canSyncOthers) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Sem permissão para sincronizar este usuário']);
    exit;
}

// Persiste o token FCM usando a tabela exclusiva para usuários
$result = PluginUniappEvent::saveUserFcmToken($user_id, $fcmToken);

if (is_array($result)) {
    if (!empty($result['success'])) {
        echo json_encode([
            'success'         => true,
            'already_synced'  => !empty($result['already_synced']),
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

// Caso inesperado
http_response_code(500);
echo json_encode(['success' => false, 'message' => 'Falha inesperada ao salvar token']);

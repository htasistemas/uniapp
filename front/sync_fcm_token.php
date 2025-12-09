<?php
// -------------------------------------------------------------------------
// PASSO 1: Capturar Headers ANTES de carregar o GLPI
// Isso é crucial para injetar o Session-Token no motor do PHP
// -------------------------------------------------------------------------
$session_id = '';

// Tenta pegar via $_SERVER (Padrão Apache/Nginx fastcgi)
if (isset($_SERVER['HTTP_SESSION_TOKEN'])) {
    $session_id = trim($_SERVER['HTTP_SESSION_TOKEN']);
}
// Fallback para getallheaders se necessário
elseif (function_exists('getallheaders')) {
    $headers = getallheaders();
    foreach ($headers as $key => $value) {
        if (strcasecmp($key, 'Session-Token') === 0) {
            $session_id = trim($value);
            break;
        }
    }
}

// Se tivermos um token, forçamos o PHP a usar este ID de sessão
if (!empty($session_id)) {
    session_id($session_id);
}

// -------------------------------------------------------------------------
// PASSO 2: Carregar o GLPI
// -------------------------------------------------------------------------
define('GLPI_ROOT', dirname(__DIR__, 3));
require_once GLPI_ROOT . '/inc/includes.php';

// Configurar Response JSON
header('Content-Type: application/json; charset=utf-8');

// Carrega o Plugin
Plugin::load('uniapp');
require_once __DIR__ . '/../inc/PluginUniappEvent.class.php';
require_once __DIR__ . '/../inc/PluginUniappConfig.class.php';

// -------------------------------------------------------------------------
// PASSO 3: Validações
// -------------------------------------------------------------------------

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respondJson(['success' => false, 'message' => 'Método não permitido'], 405);
}

// Validar App-Token (Mantive sua lógica manual, mas simplificada)
$appToken = getRequestHeader('App-Token');
if (empty($appToken) || !isValidAppToken($appToken)) {
    logSyncFailure('App-Token inválido ou ausente');
    respondJson(['success' => false, 'error' => 'App-Token inválido'], 401);
}

// Validar Sessão usando NATIVO do GLPI
// Como fizemos o session_id() lá em cima, o GLPI já carregou o usuário se o token for válido.
if (!Session::getLoginUserID()) {
    logSyncFailure('Sessão inválida ou expirada (GLPI Core)');
    respondJson(['success' => false, 'error' => 'Sessão expirada', 'message' => 'Sessão expirada'], 401);
}

// Ler Input
$rawBody = file_get_contents('php://input');
$input = json_decode($rawBody, true);

$user_id_payload = isset($input['user_id']) ? (int)$input['user_id'] : 0;
$fcmToken        = isset($input['token']) ? trim((string)$input['token']) : '';

if (!$user_id_payload || empty($fcmToken)) {
    respondJson(['success' => false, 'error' => 'Parâmetros ausentes'], 400);
}

// Validar se o usuário da sessão é o mesmo do payload
if (Session::getLoginUserID() !== $user_id_payload) {
    respondJson([
        'success' => false,
        'error' => 'ID do usuário não corresponde à sessão ativa'
    ], 403);
}

// -------------------------------------------------------------------------
// PASSO 4: Ação (Salvar Token)
// -------------------------------------------------------------------------

// NOTA: Se PluginUniappEvent::saveUserFcmToken precisar de permissão de Super-Admin,
// você deve lidar com isso dentro da classe do evento ou usar sudo aqui.
// Exemplo de impersonate temporário (caso necessário):
/*
if (Session::isCron() || true) { // Se precisar burlar permissão
   // Lógica de update direto no DB sem passar por validação de direitos da classe User
}
*/

$result = PluginUniappEvent::saveUserFcmToken($user_id_payload, $fcmToken);

if (is_array($result)) {
    if (!empty($result['success'])) {
        respondJson([
            'success'        => true,
            'already_synced' => !empty($result['already_synced'])
        ]);
    }
    respondJson([
        'success' => false,
        'error'   => $result['message'] ?? 'Falha ao processar'
    ], 500);
}

respondJson(['success' => false, 'error' => 'Falha desconhecida'], 500);


// -------------------------------------------------------------------------
// Helpers
// -------------------------------------------------------------------------

function getRequestHeader(string $name): string
{
    // Reutilizando sua lógica ou $_SERVER direto
    $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
    return isset($_SERVER[$key]) ? trim($_SERVER[$key]) : '';
}

function respondJson(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function logSyncFailure(string $message): void {
    // Implementação simplificada do seu log
    $entry = "[UniApp] " . $message;
    error_log($entry);
}

// Mantive sua função de validação de AppToken pois ela faz query direta segura
function isValidAppToken(string $token): bool
{
    global $DB;
    if (empty($token)) return false;

    // Tabelas comuns onde tokens residem
    $tables = ['glpi_apptokens', 'glpi_apiclients'];

    foreach ($tables as $table) {
        if (!$DB->tableExists($table)) continue;

        // Verifica colunas comuns de token
        $col = ($table === 'glpi_apiclients') ? 'app_token' : 'token';
        // Fallback check se a coluna existe
        if (!$DB->fieldExists($table, $col)) {
             if ($DB->fieldExists($table, 'value')) $col = 'value'; // GLPI antigos
             else continue;
        }

        $iterator = $DB->request([
            'SELECT' => 'id',
            'FROM'   => $table,
            'WHERE'  => [$col => $token],
            'LIMIT'  => 1
        ]);

        if (count($iterator) > 0) return true;
    }
    return false;
}
<?php
define('GLPI_ROOT', dirname(__DIR__, 3));
require_once GLPI_ROOT . '/inc/includes.php';

header('Content-Type: application/json; charset=utf-8');

Plugin::load('uniapp');
require_once __DIR__ . '/../inc/PluginUniappEvent.class.php';
require_once __DIR__ . '/../inc/PluginUniappConfig.class.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respondJson(['success' => false, 'message' => 'Método não permitido'], 405);
}

$appToken = getRequestHeader('App-Token');
if (trim($appToken) === '') {
    logSyncFailure('App-Token ausente');
    respondJson(['success' => false, 'error' => 'App-Token ausente', 'message' => 'App-Token ausente'], 401);
}

if (!isValidAppToken($appToken)) {
    logSyncFailure('App-Token inválido', ['app_token' => maskToken($appToken)]);
    respondJson(['success' => false, 'error' => 'App-Token inválido', 'message' => 'App-Token inválido'], 401);
}

$sessionToken = getRequestHeader('Session-Token');
if (trim($sessionToken) === '') {
    logSyncFailure('Session-Token ausente');
    respondJson(['success' => false, 'error' => 'Session-Token ausente', 'message' => 'Session-Token ausente'], 401);
}

$rawBody = file_get_contents('php://input');
$input = json_decode($rawBody, true);
if (!is_array($input)) {
    respondJson(['success' => false, 'error' => 'JSON inválido', 'message' => 'JSON inválido'], 400);
}

$user_id  = isset($input['user_id']) ? (int)$input['user_id'] : null;
$fcmToken = isset($input['token']) ? trim((string)$input['token']) : null;

if (!$user_id || $fcmToken === '') {
    respondJson(['success' => false, 'error' => 'Parâmetros ausentes', 'message' => 'Parâmetros ausentes'], 400);
}

$userIdFromSession = getUserIdBySessionToken($sessionToken);
if ($userIdFromSession === null) {
    logSyncFailure('Sessão inválida', ['session_token' => maskToken($sessionToken)]);
    respondJson(['success' => false, 'error' => 'Sessão expirada', 'message' => 'Sessão expirada'], 401);
}

if ($userIdFromSession !== $user_id) {
    respondJson([
        'success' => false,
        'error'   => 'Sessão não pertence ao usuário informado',
        'message' => 'Sessão não pertence ao usuário informado'
    ], 403);
}

$result = PluginUniappEvent::saveUserFcmToken($user_id, $fcmToken);

if (is_array($result)) {
    if (!empty($result['success'])) {
        respondJson([
            'success'        => true,
            'already_synced' => !empty($result['already_synced'])
        ]);
    }

    respondJson([
        'success' => false,
        'error'   => $result['message'] ?? 'Falha ao atualizar o token',
        'message' => $result['message'] ?? 'Falha ao atualizar o token'
    ], 500);
}

respondJson(['success' => false, 'error' => 'Falha inesperada ao salvar token', 'message' => 'Falha inesperada ao salvar token'], 500);

/**
 * Reads a request header in a case-insensitive way.
 *
 * @param string $name
 * @return string
 */
function getRequestHeader(string $name): string
{
    $serverKey = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
    if (isset($_SERVER[$serverKey])) {
        return trim((string)$_SERVER[$serverKey]);
    }

    if (function_exists('getallheaders')) {
        $headers = getallheaders();
        foreach ($headers as $header => $value) {
            if (strcasecmp($header, $name) === 0) {
                return trim((string)$value);
            }
        }
    }

    return '';
}

/**
 * Checks if the given App-Token exists in GLPI.
 */
function isValidAppToken(string $token): bool
{
    global $DB;

    if ($token === '') {
        return false;
    }

    $table = 'glpi_apptokens';
    if (!$DB->tableExists($table)) {
        return false;
    }

    $column = resolveTokenColumn($table, ['token', 'value', 'apptoken']);
    if ($column === '') {
        return false;
    }

    $result = $DB->request([
        'SELECT' => [$column],
        'FROM'   => $table,
        'WHERE'  => [$column => $token],
        'LIMIT'  => 1
    ]);

    foreach ($result as $_) {
        return true;
    }

    return false;
}

/**
 * Returns the user_id associated with the provided session token.
 */
function getUserIdBySessionToken(string $token): ?int
{
    global $DB;

    if ($token === '') {
        return null;
    }

    $table = 'glpi_sessions';
    if (!$DB->tableExists($table)) {
        return null;
    }

    $tokenColumn = resolveTokenColumn($table, ['session', 'session_value']);
    $userColumn = resolveTokenColumn($table, ['users_id', 'userid', 'user_id']);

    if ($tokenColumn === '' || $userColumn === '') {
        return null;
    }

    $result = $DB->request([
        'SELECT' => [$userColumn],
        'FROM'   => $table,
        'WHERE'  => [$tokenColumn => $token],
        'LIMIT'  => 1
    ]);

    foreach ($result as $row) {
        return (int)($row[$userColumn] ?? 0);
    }

    return null;
}

/**
 * Attempts to resolve an existing column name from the provided candidates.
 */
function resolveTokenColumn(string $table, array $candidates): string
{
    global $DB;

    static $cache = [];
    $key = $table . '|' . implode(',', $candidates);
    if (isset($cache[$key])) {
        return $cache[$key];
    }

    $column = '';
    try {
        $columns = $DB->request(['QUERY' => sprintf('SHOW COLUMNS FROM `%s`', $table)]);
        foreach ($columns as $row) {
            $name = strtolower((string)($row['Field'] ?? ''));
            foreach ($candidates as $candidate) {
                if ($name === strtolower($candidate)) {
                    $column = $name;
                    break 2;
                }
            }
        }
    } catch (Throwable $e) {
        $column = '';
    }

    $cache[$key] = $column;
    return $column;
}

/**
 * Writes a brief entry to the plugin log (if configured) and PHP error log.
 */
function logSyncFailure(string $message, array $context = []): void
{
    $prefix = '[UniApp sync_fcm_token]';
    $entry = sprintf('%s %s %s', gmdate('c'), $prefix, $message);
    if (!empty($context)) {
        $entry .= ' ' . json_encode($context, JSON_UNESCAPED_UNICODE);
    }

    $logFile = PluginUniappConfig::get('log_file', '');
    $writeLog = PluginUniappConfig::get('write_log', '0') === '1';
    if ($writeLog && $logFile !== '') {
        @file_put_contents($logFile, $entry . PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    error_log($entry);
}

function maskToken(string $token): string
{
    if ($token === '') {
        return '';
    }

    if (strlen($token) <= 8) {
        return $token;
    }

    return substr($token, 0, 4) . '…' . substr($token, -4);
}

/**
 * Sends a JSON response and terminates execution.
 */
function respondJson(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

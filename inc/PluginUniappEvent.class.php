<?php

class PluginUniappEvent extends CommonDBTM
{
    const TOKEN_TABLE = 'glpi_plugin_uniapp_user_tokens';

    /**
     * Salva ou atualiza o token FCM e retorna o status da operacao.
     */
    public static function saveUserFcmToken(int $userId, string $token)
    {
        global $DB;

        $userId = max(0, $userId);
        $token = trim($token);

        if ($userId === 0 || $token === '') {
            return ['success' => false, 'message' => 'Dados invalidos'];
        }

        if (!$DB->tableExists(self::TOKEN_TABLE)) {
            return ['success' => false, 'message' => 'Tabela de tokens nao encontrada'];
        }

        $escapedToken = $DB->escape($token);
        $existingToken = null;

        $result = $DB->query("SELECT fcm_token FROM " . self::TOKEN_TABLE . " WHERE users_id = $userId");
        if ($result && ($row = $DB->fetch_assoc($result))) {
            $existingToken = $row['fcm_token'];
        }

        $alreadySynced = ($existingToken !== null && $existingToken === $token);

        $query = "INSERT INTO " . self::TOKEN_TABLE . " (users_id, fcm_token)
                    VALUES ($userId, '$escapedToken')
                    ON DUPLICATE KEY UPDATE fcm_token = '$escapedToken', updated_at = CURRENT_TIMESTAMP";

        $DB->queryOrDie($query, $DB->error());

        return [
            'success' => true,
            'already_synced' => $alreadySynced
        ];
    }

    public static function item_add_ticket(Ticket $ticket)
    {
        return true;
    }

    public static function item_add_followup(ITILFollowup $followup)
    {
        return true;
    }

    public static function item_add_solution(ITILSolution $solution)
    {
        return true;
    }

    public static function item_add_validation(TicketValidation $validation)
    {
        return true;
    }
}

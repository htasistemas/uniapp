<?php

class PluginUniappEvent extends CommonDBTM
{
    public const TOKEN_TABLE = 'glpi_plugin_uniapp_user_tokens';

    /**
     * Salva ou atualiza o token FCM do usuário.
     * Retorna array com:
     *  - success: bool
     *  - already_synced: bool (true se o token já era igual)
     *  - message: string opcional em caso de falha
     */
    public static function saveUserFcmToken(int $userId, string $token): array
    {
        global $DB;

        $userId = max(0, (int)$userId);
        $token  = trim((string)$token);

        if ($userId === 0 || $token === '') {
            return ['success' => false, 'message' => 'Dados inválidos'];
        }

        if (!$DB->tableExists(self::TOKEN_TABLE)) {
            return ['success' => false, 'message' => 'Tabela de tokens não encontrada'];
        }

        // Busca token atual (se houver) usando request estruturado
        $existingToken = null;
        $hasRow = false;
        $it = $DB->request([
            'SELECT' => ['fcm_token'],
            'FROM'   => self::TOKEN_TABLE,
            'WHERE'  => ['users_id' => $userId],
            'LIMIT'  => 1
        ]);
        foreach ($it as $row) {
            $existingToken = (string)($row['fcm_token'] ?? '');
            $hasRow = true;
            break;
        }

        $alreadySynced = ($existingToken !== null && $existingToken === $token);

        // UPSERT seguro: tenta UPDATE; se não afetar linhas, faz INSERT
        // Também atualiza o "updated_at"
        $now = date('Y-m-d H:i:s');

        if ($hasRow) {
            if (!$DB->update(self::TOKEN_TABLE, [
                'fcm_token' => $token,
                'updated_at'=> $now
            ], [
                'users_id' => $userId
            ])) {
                return ['success' => false, 'message' => $DB->error()];
            }
        } else {
            $ok = $DB->insert(self::TOKEN_TABLE, [
                'users_id'  => $userId,
                'fcm_token' => $token,
                'updated_at'=> $now
            ]);
            if (!$ok) {
                return ['success' => false, 'message' => $DB->error()];
            }
        }

        return [
            'success'        => true,
            'already_synced' => $alreadySynced
        ];
    }

    // Hooks criados para futura lógica de notificação (mantidos como no original)
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

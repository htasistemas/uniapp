<?php

class PluginUniappEvent extends CommonDBTM
{
    private const CONFIG_TABLE = 'glpi_plugin_uniapp_config';
    private const USER_FIELD = 'fcm_token';

    public static function item_add_ticket(Ticket $ticket): bool
    {
        return self::triggerEvent('ticket', $ticket);
    }

    public static function item_add_followup(ITILFollowup $followup): bool
    {
        return self::triggerEvent('followup', $followup);
    }

    public static function item_add_solution(ITILSolution $solution): bool
    {
        return self::triggerEvent('solution', $solution);
    }

    public static function item_add_validation(TicketValidation $validation): bool
    {
        return self::triggerEvent('validation', $validation);
    }

    public static function saveUserFcmToken(int $user_id, string $token): array
    {
        $token = trim($token);

        if ($user_id <= 0 || $token === '') {
            return [
                'success' => false,
                'message' => 'Invalid user or token',
            ];
        }

        $user = new User();
        if (!$user->getFromDB($user_id)) {
            return [
                'success' => false,
                'message' => 'User not found',
            ];
        }

        $alreadySynced = ($user->fields[self::USER_FIELD] ?? '') === $token;

        if (!$user->update([
            'id' => $user_id,
            self::USER_FIELD => $token,
        ])) {
            return [
                'success' => false,
                'message' => 'Unable to save the token',
            ];
        }

        return [
            'success' => true,
            'already_synced' => $alreadySynced,
        ];
    }

    public static function getConfiguration(): array
    {
        global $DB;

        $config = [];
        if (!$DB->tableExists(self::CONFIG_TABLE)) {
            return $config;
        }

        $criteria = ['FROM' => self::CONFIG_TABLE];
        foreach ($DB->request($criteria) as $row) {
            $config[$row['par_name']] = $row['par_value'];
        }

        return $config;
    }

    private static function triggerEvent(string $event, $source): bool
    {
        // Placeholder for future notification logic
        return true;
    }
}

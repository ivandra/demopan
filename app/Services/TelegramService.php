<?php

class TelegramService
{
    private const EVENT_COLUMNS = [
        'ssl_ok' => 'tg_notify_ssl_ok',
        'xmlstock_detected' => 'tg_notify_xmlstock_detected',
        'redirect_enabled' => 'tg_notify_redirect_enabled',
        'redirect_disabled' => 'tg_notify_redirect_disabled',
        'manual_sync' => 'tg_notify_manual_sync',
    ];

    public function getSettings(): array
    {
        $this->ensureNotificationColumns();

        return DB::withReconnect(function(PDO $pdo) {
            $st = $pdo->query("SELECT * FROM webmaster_settings ORDER BY id ASC LIMIT 1");
            return $st ? ($st->fetch(PDO::FETCH_ASSOC) ?: []) : [];
        });
    }

    public function send(string $text, string $event = 'generic'): bool
    {
        $row = $this->getSettings();

        $token = trim((string)($row['tg_bot_token'] ?? ''));
        $chatId = trim((string)($row['tg_chat_id'] ?? ''));

        if ($token === '' || $chatId === '') {
            @error_log('[TG] token/chat_id missing');
            return false;
        }

        if (!$this->isEventEnabled($row, $event)) {
            @error_log('[TG] event disabled: ' . $event);
            return false;
        }

        $url = "https://api.telegram.org/bot{$token}/sendMessage";

        $payload = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => 1,
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 6);

        $resp = curl_exec($ch);
        $err  = (string)curl_error($ch);
        $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($resp === false || $http < 200 || $http >= 300) {
            @error_log('[TG] send fail http=' . $http . ' err=' . $err . ' resp=' . (string)$resp);
            return false;
        }

        return true;
    }

    private function isEventEnabled(array $row, string $event): bool
    {
        $event = trim($event);
        if ($event === '' || $event === 'generic') {
            return true;
        }

        $col = self::EVENT_COLUMNS[$event] ?? '';
        if ($col === '') {
            return true;
        }

        return (int)($row[$col] ?? 1) === 1;
    }

    public function ensureNotificationColumns(): void
    {
        DB::withReconnect(function(PDO $pdo) {
            foreach (self::EVENT_COLUMNS as $column) {
                if (!$this->columnExists($pdo, 'webmaster_settings', $column)) {
                    $pdo->exec("ALTER TABLE `webmaster_settings` ADD COLUMN `{$column}` TINYINT(1) NOT NULL DEFAULT 1");
                }
            }
        });
    }

    private function columnExists(PDO $pdo, string $table, string $column): bool
    {
        if (!preg_match('~^[a-zA-Z0-9_]+$~', $table) || !preg_match('~^[a-zA-Z0-9_]+$~', $column)) {
            throw new InvalidArgumentException('Bad identifier for columnExists()');
        }

        $sql = "SELECT 1
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = ?
                  AND COLUMN_NAME = ?
                LIMIT 1";

        $st = $pdo->prepare($sql);
        $st->execute([$table, $column]);
        return (bool)$st->fetchColumn();
    }
}

<?php

class TelegramService
{
    public function send(string $text): bool
    {
        $row = DB::withReconnect(function(PDO $pdo) {
            $st = $pdo->query("SELECT tg_bot_token, tg_chat_id FROM webmaster_settings ORDER BY id ASC LIMIT 1");
            return $st ? ($st->fetch(PDO::FETCH_ASSOC) ?: []) : [];
        });

        $token = trim((string)($row['tg_bot_token'] ?? ''));
        $chatId = trim((string)($row['tg_chat_id'] ?? ''));

        if ($token === '' || $chatId === '') {
            @error_log('[TG] token/chat_id missing');
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
}
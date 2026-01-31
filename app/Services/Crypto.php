<?php

class Crypto
{
    private static function key(): string
    {
        $k = (string)config('app_key', '');
        if ($k === '' || strlen($k) < 16) {
            throw new RuntimeException('APP_KEY is not set');
        }
        return $k;
    }

    public static function encrypt(string $plain): string
    {
        $key = hash('sha256', self::key(), true);
        $iv  = random_bytes(16);

        $cipher = openssl_encrypt($plain, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
        if ($cipher === false) {
            throw new RuntimeException('encrypt failed');
        }

        return base64_encode($iv . $cipher);
    }

    public static function decrypt(string $enc): string
    {
        $raw = base64_decode($enc, true);
        if ($raw === false || strlen($raw) < 17) {
            throw new RuntimeException('bad encrypted value');
        }

        $iv = substr($raw, 0, 16);
        $cipher = substr($raw, 16);

        $key = hash('sha256', self::key(), true);
        $plain = openssl_decrypt($cipher, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
        if ($plain === false) {
            throw new RuntimeException('decrypt failed');
        }

        return $plain;
    }
}

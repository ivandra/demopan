<?php

class DB
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }
        self::$pdo = self::connect();
        return self::$pdo;
    }

    public static function reset(): void
    {
        self::$pdo = null;
    }

    private static function connect(): PDO
    {
        // ВАЖНО: как было у вас — читаем реальный файл конфига
        $cfg = require __DIR__ . '/../../config/db.php';

        $host = $cfg['host'] ?? 'localhost';
        $port = (int)($cfg['port'] ?? 3306);

        // у вас раньше ключ был db, а не name
        $name = $cfg['db'] ?? ($cfg['name'] ?? '');

        $user = $cfg['user'] ?? '';
        $pass = $cfg['pass'] ?? '';

        if ($name === '' || $user === '') {
            throw new RuntimeException('DB config is missing: check config/db.php keys host/port/db/user/pass');
        }

        $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";

        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_PERSISTENT         => false,
        ]);

        $pdo->exec("SET NAMES utf8mb4");

        return $pdo;
    }

    public static function withReconnect(callable $fn)
    {
        try {
            return $fn(self::pdo());
        } catch (PDOException $e) {
            $msg = $e->getMessage();

            $needReconnect =
                stripos($msg, 'server has gone away') !== false ||
                stripos($msg, 'Packets out of order') !== false ||
                stripos($msg, 'Lost connection') !== false ||
                stripos($msg, 'MySQL server has gone away') !== false;

            if (!$needReconnect) {
                throw $e;
            }

            self::reset();
            return $fn(self::pdo());
        }
    }
}

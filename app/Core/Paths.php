<?php

final class Paths
{
    public static function appRoot(): string
    {
        // всегда якоримся на public_html
        if (defined('APP_ROOT')) return APP_ROOT;

        $root = realpath(__DIR__ . '/../../'); // app/Core -> public_html
        return $root ?: rtrim(dirname(__DIR__, 2), '/\\');
    }

    public static function storageRoot(): string
    {
        if (defined('STORAGE_ROOT')) return STORAGE_ROOT;
        return self::appRoot() . '/storage';
    }

    public static function storage(string $rel = ''): string
    {
        $base = self::storageRoot();
        return $rel === '' ? $base : rtrim($base, '/\\') . '/' . ltrim($rel, '/\\');
    }

    public static function ensureDir(string $dir): void
    {
        if (is_dir($dir)) return;

        if (!@mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException("mkdir failed: {$dir}");
        }
    }

    public static function ensureStorageTree(): void
    {
        self::ensureDir(self::storageRoot());
        self::ensureDir(self::storage('logs'));
        self::ensureDir(self::storage('builds'));
        self::ensureDir(self::storage('configs'));
        self::ensureDir(self::storage('templates'));
        self::ensureDir(self::storage('zips'));
    }
}

<?php

class MultiSiteConfigWriter
{
    /**
     * Новый API (который ты уже используешь в коде генерации).
     * Пишет config.default.php в корне build-директории.
     */
    public function writeDefaultConfig(string $rootDir, array $cfg): void
    {
        $path = rtrim($rootDir, "/\\") . '/config.default.php';
        $php  = $this->renderConfigPhp($cfg);
        $this->safeWrite($path, $php);
    }

    /**
     * Новый API.
     * Пишет subs/<label>/config.php в build-директории.
     */
    public function writeSubConfig(string $rootDir, string $label, array $cfg): void
    {
        $label = trim($label);
        if ($label === '') $label = '_default';

        $dir = rtrim($rootDir, "/\\") . '/subs/' . $label;
        Paths::ensureDir($dir);

        $path = rtrim($dir, "/\\") . '/config.php';
        $php  = $this->renderConfigPhp($cfg);
        $this->safeWrite($path, $php);
    }

    /**
     * ===== ALIASES ДЛЯ СТАРОГО КОДА =====
     * SiteController (и другие места) могут звать старые имена методов:
     *  - writeConfigDefaultPhp($buildDir, $domain, $cfg)
     *  - writeSubConfigPhp($buildDir, $label, $subCfg, $defaultCfg)
     *
     * Мы делаем обертки, чтобы ничего не падало.
     */

    // Старое имя: MultiSiteConfigWriter::writeConfigDefaultPhp
    public function writeConfigDefaultPhp(string $buildDir, string $domain, array $cfg): void
    {
        // $domain тут исторический параметр — в renderConfigPhp домен берется из $cfg, если нужен.
        $this->writeDefaultConfig($buildDir, $cfg);
    }

    // Старое имя: MultiSiteConfigWriter::writeSubConfigPhp
    public function writeSubConfigPhp(string $buildDir, string $label, array $subCfg, array $defaultCfg = []): void
    {
        // В старой версии могли передавать defaultCfg отдельно.
        // Если надо — можно аккуратно подмешать значения по умолчанию (не перетирая явные значения subCfg).
        if (is_array($defaultCfg) && $defaultCfg) {
            foreach ($defaultCfg as $k => $v) {
                if (!array_key_exists($k, $subCfg)) {
                    $subCfg[$k] = $v;
                }
            }
        }

        $this->writeSubConfig($buildDir, $label, $subCfg);
    }

    // ===== internals =====

    private function safeWrite(string $path, string $content): void
    {
        $dir = dirname($path);

        // Всегда создаем директорию перед записью
        Paths::ensureDir($dir);

        // Защита от записи вне builds (внутри storage) (без realpath): проверяем нормализованный префикс.
        $base = rtrim(str_replace('\\', '/', Paths::storage('builds')), '/');
        $dirN = rtrim(str_replace('\\', '/', $this->normalizePath($dir)), '/');

        if (strpos($dirN . '/', $base . '/') !== 0) {
            throw new RuntimeException('Refuse to write outside builds: ' . $path);
        }

        $tmp = $path . '.tmp_' . bin2hex(random_bytes(6));
        if (file_put_contents($tmp, $content) === false) {
            throw new RuntimeException('Cannot write temp file: ' . $tmp);
        }

        if (!@rename($tmp, $path)) {
            @unlink($tmp);
            throw new RuntimeException('Cannot move temp to target: ' . $path);
        }
    }

    private function normalizePath(string $path): string
    {
        $path = str_replace('\\', '/', $path);

        $isAbs = (substr($path, 0, 1) === '/');
        $parts = explode('/', $path);

        $out = [];
        foreach ($parts as $p) {
            if ($p === '' || $p === '.') continue;
            if ($p === '..') {
                array_pop($out);
                continue;
            }
            $out[] = $p;
        }

        $norm = implode('/', $out);
        return $isAbs ? '/' . $norm : $norm;
    }

    private function renderConfigPhp(array $cfg): string
    {
        $cfgExport = var_export($cfg, true);

        // ВАЖНО: texts_dir вычисляется от __DIR__ внутри build'а (это не Paths проекта панели)
        return <<<PHP
<?php

\$cfg = {$cfgExport};
\$pages = \$cfg['pages'] ?? [];
\$textsDir = __DIR__ . '/subs/_default/texts/';

return [
    'site' => [
        'title' => (string)(\$cfg['title'] ?? ''),
        'h1' => (string)(\$cfg['h1'] ?? ''),
        'description' => (string)(\$cfg['description'] ?? ''),
        'keywords' => (string)(\$cfg['keywords'] ?? ''),
        'promolink' => (string)(\$cfg['promolink'] ?? '/reg'),
        'internal_reg_url' => (string)(\$cfg['internal_reg_url'] ?? ''),
        'partner_override_url' => (string)(\$cfg['partner_override_url'] ?? ''),
        'redirect_enabled' => (int)(\$cfg['redirect_enabled'] ?? 0),
        'base_new_url' => (string)(\$cfg['base_new_url'] ?? ''),
        'base_second_url' => (string)(\$cfg['base_second_url'] ?? ''),
        'logo' => (string)(\$cfg['logo'] ?? 'assets/logo.png'),
        'favicon' => (string)(\$cfg['favicon'] ?? 'assets/favicon.png'),
    ],
    'pages' => \$pages,
    'texts_dir' => \$textsDir,
];
PHP;
    }
}

if (!class_exists('SiteConfigWriter', false)) {
    class_alias('MultiSiteConfigWriter', 'SiteConfigWriter');
}

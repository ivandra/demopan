<?php

class MultiSiteConfigWriter
{
    /**
     * Совместимость со старым вызовом из SiteController:
     * $w->writeConfigDefaultPhp($dir, $domain, $cfg)
     */
    public function writeConfigDefaultPhp(string $rootDir, string $domain, array $cfg): void
    {
        $cfg['domain'] = $domain;
        $this->writeDefaultConfig($rootDir, $cfg);
    }

    /**
     * Совместимость со старым вызовом из SiteController:
     * $w->writeSubConfigPhp($dir, $label, $subCfg, $cfg)
     */
    public function writeSubConfigPhp(string $rootDir, string $label, array $subCfg, array $baseCfg): void
    {
        // shallow merge: subCfg поверх baseCfg
        $cfg = $baseCfg;
        foreach ($subCfg as $k => $v) {
            $cfg[$k] = $v;
        }

        $baseDomain = (string)($baseCfg['domain'] ?? '');
        if ($label !== '_default' && $baseDomain !== '') {
            $cfg['domain'] = $label . '.' . $baseDomain;
        } else {
            $cfg['domain'] = $baseDomain;
        }

        $this->writeSubConfig($rootDir, $label, $cfg);
    }

    // -------------------- New API (то, что реально делает запись) --------------------

    public function writeDefaultConfig(string $rootDir, array $cfg): void
    {
        $path = rtrim($rootDir, '/\\') . '/config.default.php';
        $php  = $this->renderDefaultConfigPhp($cfg);
        $this->safeWrite($path, $php);
    }

    public function writeSubConfig(string $rootDir, string $label, array $cfg): void
    {
        $subDir = rtrim($rootDir, '/\\') . '/subs/' . $label;
        if (!is_dir($subDir)) {
            @mkdir($subDir, 0775, true);
        }

        $path = $subDir . '/config.php';
        $php  = $this->renderSubConfigPhp($cfg);
        $this->safeWrite($path, $php);
    }

    // -------------------- Renderers --------------------

    private function renderDefaultConfigPhp(array $cfg): string
    {
        $export = var_export($cfg, true);

        // config.default.php лежит в корне билда, тексты для дефолта в subs/_default/texts
        return <<<PHP
<?php

\$cfg = {$export};

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

    private function renderSubConfigPhp(array $cfg): string
    {
        $export = var_export($cfg, true);

        // config.php лежит внутри subs/<label>/, поэтому тексты локально: __DIR__/texts
        return <<<PHP
<?php

\$cfg = {$export};

\$pages = \$cfg['pages'] ?? [];
\$textsDir = __DIR__ . '/texts/';

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

    // -------------------- Safe write --------------------

    private function safeWrite(string $path, string $content): void
    {
        // Пишем атомарно
        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $tmp = $path . '.tmp.' . bin2hex(random_bytes(6));
        file_put_contents($tmp, $content, LOCK_EX);
        @chmod($tmp, 0664);
        rename($tmp, $path);
        @chmod($path, 0664);
    }
}

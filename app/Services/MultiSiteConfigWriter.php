<?php

class MultiSiteConfigWriter
{
    private const ROOT_LABEL = '_default';

    /**
     * Совместимость со старым вызовом из SiteController:
     * $w->writeConfigDefaultPhp($dir, $domain, $cfg)
     */
    public function writeConfigDefaultPhp(string $rootDir, string $domain, array $cfg): void
    {
        $cfg['domain'] = $domain;
        $cfg['label'] = self::ROOT_LABEL;

        $this->writeDefaultConfig($rootDir, $cfg);
    }

    /**
     * Совместимость со старым вызовом:
     * $w->writeSubConfigPhp($dir, $label, $subCfg, $baseCfg)
     *
     * ВАЖНО:
     * subCfg может быть partial JSON, поэтому тут нужен именно
     * рекурсивный merge, а не shallow merge.
     */
    public function writeSubConfigPhp(string $rootDir, string $label, array $subCfg, array $baseCfg): void
    {
        $label = $this->normalizeLabel($label);

        // partial sub-config поверх default/base config
        $cfg = $this->mergeRecursiveReplace($baseCfg, $subCfg);

        $baseDomain = (string)($baseCfg['domain'] ?? '');
        $cfg['label'] = $label;
        $cfg['domain'] = $this->buildDomainForLabel($baseDomain, $label);

        $this->writeSubConfig($rootDir, $label, $cfg);
    }

    // -------------------- New API --------------------

    public function writeDefaultConfig(string $rootDir, array $cfg): void
    {
        $cfg['label'] = self::ROOT_LABEL;
        $cfg['domain'] = (string)($cfg['domain'] ?? '');

        $path = rtrim($rootDir, '/\\') . '/config.default.php';
        $php  = $this->renderDefaultConfigPhp($cfg);

        $this->safeWrite($path, $php);
    }

    public function writeSubConfig(string $rootDir, string $label, array $cfg): void
    {
        $label = $this->normalizeLabel($label);
        $cfg['label'] = $label;
        $cfg['domain'] = (string)($cfg['domain'] ?? '');

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
        return $this->renderConfigPhp(
            $cfg,
            "__DIR__ . '/subs/_default/texts/'"
        );
    }

    private function renderSubConfigPhp(array $cfg): string
    {
        return $this->renderConfigPhp(
            $cfg,
            "__DIR__ . '/texts/'"
        );
    }

    private function renderConfigPhp(array $cfg, string $textsDirExpr): string
    {
        $export = var_export($cfg, true);

        return <<<PHP
<?php

\$cfg = {$export};

\$pages = is_array(\$cfg['pages'] ?? null) ? \$cfg['pages'] : [];
\$textsDir = {$textsDirExpr};

return [
    'site' => [
        'domain' => (string)(\$cfg['domain'] ?? ''),
        'label' => (string)(\$cfg['label'] ?? ''),
        'title' => (string)(\$cfg['title'] ?? ''),
        'h1' => (string)(\$cfg['h1'] ?? ''),
        'description' => (string)(\$cfg['description'] ?? ''),
        'keywords' => (string)(\$cfg['keywords'] ?? ''),
        'yandex_verification' => (string)(\$cfg['yandex_verification'] ?? ''),
        'yandex_metrika' => (string)(\$cfg['yandex_metrika'] ?? ''),
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

    // -------------------- Helpers --------------------

    private function normalizeLabel(string $label): string
    {
        $label = strtolower(trim($label));
        return $label === '' ? self::ROOT_LABEL : $label;
    }

    private function buildDomainForLabel(string $baseDomain, string $label): string
    {
        $baseDomain = trim($baseDomain);
        if ($baseDomain === '') {
            return '';
        }

        if ($label === self::ROOT_LABEL) {
            return $baseDomain;
        }

        return $label . '.' . $baseDomain;
    }

    /**
     * Рекурсивный merge:
     * - scalar из override заменяет base
     * - вложенные массивы мержатся рекурсивно
     * - подходит для pages и partial sub-config
     */
    private function mergeRecursiveReplace(array $base, array $override): array
    {
        foreach ($override as $key => $value) {
            if (
                array_key_exists($key, $base)
                && is_array($base[$key])
                && is_array($value)
            ) {
                $base[$key] = $this->mergeRecursiveReplace($base[$key], $value);
            } else {
                $base[$key] = $value;
            }
        }

        return $base;
    }

    // -------------------- Safe write --------------------

    private function safeWrite(string $path, string $content): void
    {
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
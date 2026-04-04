<?php

class SiteConfigResolver
{
    /** @var PDO */
    private $pdo;

    /** @var SiteStructure */
    private $structure;

    public function __construct($arg = null)
    {
        $this->structure = new SiteStructure();

        if ($arg instanceof PDO) {
            $this->pdo = $arg;
        } else {
            // если сюда случайно передали SiteStructure или null — просто берём DB::pdo()
            $this->pdo = DB::pdo();
        }
    }

    public function rootLabel(): string
    {
        return $this->structure->rootLabel();
    }

    public function isRootLabel(?string $label): bool
    {
        return $this->structure->isRootLabel($label);
    }

    public function normalizeSubLabel(string $label): string
    {
        return $this->structure->normalizeLabel($label, true);
    }

    public function listLabels(int $siteId, bool $includeRoot = true): array
    {
        $labels = [];

        if ($includeRoot) {
            $labels[$this->rootLabel()] = true;
        }

        // из site_subdomain_configs
        $st = $this->pdo->prepare("SELECT label FROM site_subdomain_configs WHERE site_id=? ORDER BY label ASC");
        $st->execute([$siteId]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as $r) {
            $lb = $this->normalizeSubLabel((string)($r['label'] ?? ''));
            if ($lb !== '') {
                $labels[$lb] = true;
            }
        }

        // плюс из site_subdomains, чтобы видеть label даже если overlay ещё не создан
        $st = $this->pdo->prepare("SELECT label FROM site_subdomains WHERE site_id=? ORDER BY label ASC");
        $st->execute([$siteId]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as $r) {
            $lb = $this->normalizeSubLabel((string)($r['label'] ?? ''));
            if ($lb !== '') {
                $labels[$lb] = true;
            }
        }

        $out = array_keys($labels);
        sort($out);

        if ($includeRoot) {
            $root = $this->rootLabel();
            $out = array_values(array_filter($out, static function ($v) use ($root) {
                return $v !== $root;
            }));
            array_unshift($out, $root);
        }

        return $out;
    }

    public function saveLegacySiteConfig(int $siteId, array $cfg): void
    {
        $json = json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $chk = $this->pdo->prepare("SELECT 1 FROM site_configs WHERE site_id=? LIMIT 1");
        $chk->execute([$siteId]);
        $exists = (bool)$chk->fetchColumn();

        if ($exists) {
            $st = $this->pdo->prepare("UPDATE site_configs SET json=? WHERE site_id=?");
            $st->execute([$json, $siteId]);
        } else {
            $st = $this->pdo->prepare("INSERT INTO site_configs (site_id, json) VALUES (?, ?)");
            $st->execute([$siteId, $json]);
        }
    }

    public function loadSiteDefaultConfig(int $siteId, string $domain = '', array $fallbackCfg = []): array
    {
        if ($domain === '') {
            $domain = $this->loadSiteDomain($siteId);
        }

        $st = $this->pdo->prepare("SELECT config_json FROM site_default_configs WHERE site_id=? LIMIT 1");
        $st->execute([$siteId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        if ($row && isset($row['config_json'])) {
            $cfg = json_decode((string)$row['config_json'], true);
            if (is_array($cfg)) {
                if (empty($cfg['domain'])) {
                    $cfg['domain'] = $domain;
                }
                return $cfg;
            }
        }

        $st = $this->pdo->prepare("SELECT json FROM site_configs WHERE site_id=? LIMIT 1");
        $st->execute([$siteId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        if ($row && isset($row['json'])) {
            $cfg = json_decode((string)$row['json'], true);
            if (is_array($cfg)) {
                if (empty($cfg['domain'])) {
                    $cfg['domain'] = $domain;
                }
                return $cfg;
            }
        }

        if (!empty($fallbackCfg)) {
            if (empty($fallbackCfg['domain'])) {
                $fallbackCfg['domain'] = $domain;
            }
            return $fallbackCfg;
        }

        return [
            'domain' => $domain,
            'title' => '',
            'h1' => '',
            'description' => '',
            'keywords' => '',
            'pages' => [],
            'promolink' => '/reg',
            'internal_reg_url' => '',
            'partner_override_url' => '',
            'redirect_enabled' => 0,
            'base_new_url' => '',
            'base_second_url' => '',
            'logo' => 'assets/logo.webp',
            'favicon' => 'assets/favicon.png',
        ];
    }

    public function getDefaultConfig(int $siteId): array
    {
        return $this->loadSiteDefaultConfig($siteId, $this->loadSiteDomain($siteId));
    }

    public function upsertSiteDefaultConfig(int $siteId, array $cfg): void
    {
        $this->saveSiteDefaultConfig($siteId, $cfg);
    }

    public function upsertDefaultConfig(int $siteId, array $cfg): void
    {
        $this->saveSiteDefaultConfig($siteId, $cfg);
    }

    public function saveSiteDefaultConfig(int $siteId, array $cfg): void
    {
        $json = json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $st = $this->pdo->prepare("
            INSERT INTO site_default_configs (site_id, config_json)
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE
              config_json = VALUES(config_json),
              updated_at = CURRENT_TIMESTAMP
        ");
        $st->execute([$siteId, $json]);
    }

    public function loadSubdomainConfig(int $siteId, string $label): array
    {
        $label = $this->normalizeSubLabel($label);

        $st = $this->pdo->prepare("SELECT config_json FROM site_subdomain_configs WHERE site_id=? AND label=? LIMIT 1");
        $st->execute([$siteId, $label]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        if ($row && isset($row['config_json'])) {
            $cfg = json_decode((string)$row['config_json'], true);
            return is_array($cfg) ? $cfg : [];
        }

        return [];
    }

    public function getSubConfig(int $siteId, string $label): array
    {
        return $this->loadSubdomainConfig($siteId, $label);
    }

    public function saveSubdomainConfig(int $siteId, string $label, array $cfg): void
    {
        $label = $this->normalizeSubLabel($label);
        $json = json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $st = $this->pdo->prepare("
            INSERT INTO site_subdomain_configs (site_id, label, config_json)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE
              config_json = VALUES(config_json),
              updated_at = CURRENT_TIMESTAMP
        ");
        $st->execute([$siteId, $label, $json]);
    }

    public function upsertSubConfig(int $siteId, string $label, array $cfg): void
    {
        $this->saveSubdomainConfig($siteId, $label, $cfg);
    }

    public function ensureSubdomainConfigExists(int $siteId, string $label, array $defaultCfg): array
    {
        $label = $this->normalizeSubLabel($label);

        $cfg = $this->loadSubdomainConfig($siteId, $label);
        if (!empty($cfg)) {
            return $cfg;
        }

        $cfg = $defaultCfg;
        $cfg['label'] = $label;

        // Новый поддомен никогда не должен создаваться с уже включенным редиректом.
        // redirect_enabled включается только после фактической индексации.
        if (!$this->isRootLabel($label)) {
            $cfg['redirect_enabled'] = 0;
        }

        $cfg = $this->applyGlobalAiMetaTemplates($cfg, $label);

        if (empty($cfg['logo'])) {
            $cfg['logo'] = 'assets/logo.webp';
        }
        if (empty($cfg['favicon'])) {
            $cfg['favicon'] = 'assets/favicon.png';
        }

        if ($this->isRootLabel($label)) {
            $this->saveSiteDefaultConfig($siteId, $cfg);
        } else {
            $this->saveSubdomainConfig($siteId, $label, $cfg);
        }

        return $cfg;
    }

    private function applyGlobalAiMetaTemplates(array $cfg, string $label): array
    {
        try {
            $st = $this->pdo->query("SELECT global_meta_title_template, global_meta_h1_template, global_meta_description_template FROM ai_settings ORDER BY id ASC LIMIT 1");
            $ai = $st ? $st->fetch(PDO::FETCH_ASSOC) : false;
        } catch (Throwable $e) {
            $ai = false;
        }

        if (!is_array($ai) || !$ai) {
            return $cfg;
        }

        $domain = (string)($cfg['domain'] ?? '');
        $brand = preg_replace('~\..*$~', '', $domain);
        $brand = trim((string)$brand);
        $brandRu = $brand;

        if (!$this->isRootLabel($label)) {
            try {
                $st = $this->pdo->prepare("SELECT brand_name, brand_name_ru FROM subdomain_catalog WHERE label=? LIMIT 1");
                $st->execute([$label]);
                $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];
                if (!empty($row['brand_name'])) $brand = trim((string)$row['brand_name']);
                if (!empty($row['brand_name_ru'])) $brandRu = trim((string)$row['brand_name_ru']);
            } catch (Throwable $e) {
            }
        }

        $vars = [
            '{BRAND}' => $brand,
            '{BRAND_RU}' => $brandRu !== '' ? $brandRu : $brand,
            '{DOMAIN}' => $domain,
            '{LABEL}' => $label,
        ];

        foreach ([
            'title' => 'global_meta_title_template',
            'h1' => 'global_meta_h1_template',
            'description' => 'global_meta_description_template',
        ] as $field => $src) {
            $tpl = trim((string)($ai[$src] ?? ''));
            if ($tpl !== '' && trim((string)($cfg[$field] ?? '')) === '') {
                $cfg[$field] = strtr($tpl, $vars);
            }
        }

        return $cfg;
    }

    public function ensureSubConfigExists(int $siteId, string $label, array $defaultCfg): array
    {
        return $this->ensureSubdomainConfigExists($siteId, $label, $defaultCfg);
    }

    public function getResolvedConfig(int $siteId, string $label): array
    {
        $label = $this->normalizeSubLabel($label);

        $base = $this->getDefaultConfig($siteId);

        if ($this->isRootLabel($label)) {
            $base['label'] = $this->rootLabel();
            if (empty($base['logo'])) {
                $base['logo'] = 'assets/logo.webp';
            }
            if (empty($base['favicon'])) {
                $base['favicon'] = 'assets/favicon.png';
            }
            return $base;
        }

        $sub = $this->loadSubdomainConfig($siteId, $label);
        if (empty($sub)) {
            $sub = $this->ensureSubdomainConfigExists($siteId, $label, $base);
        }

        $resolved = array_replace_recursive($base, $sub);
        $resolved['label'] = $label;

        if (empty($resolved['logo'])) {
            $resolved['logo'] = 'assets/logo.webp';
        }
        if (empty($resolved['favicon'])) {
            $resolved['favicon'] = 'assets/favicon.png';
        }

        return $resolved;
    }

    private function loadSiteDomain(int $siteId): string
    {
        $st = $this->pdo->prepare("SELECT domain FROM sites WHERE id=? LIMIT 1");
        $st->execute([$siteId]);
        $domain = (string)($st->fetchColumn() ?: '');
        return trim($domain);
    }
}
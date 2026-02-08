<?php

class SubdomainProvisioner
{
    public function ensureForSite(int $siteId, string $label): void
    {
        $label = trim((string)$label);
        if ($label === '') return;

        $site = $this->loadSite($siteId);
        if (!$site) return;

        if (($site['template'] ?? '') !== 'template-multy') {
            return;
        }

        $buildAbs = $this->getBuildAbs($siteId, $site);
        if ($buildAbs === '') return;

        $subDir = rtrim($buildAbs, '/\\') . '/subs/' . $label;
        $textsDir = $subDir . '/texts';
        $assetsDir = $subDir . '/assets';

        @mkdir($textsDir, 0775, true);
        @mkdir($assetsDir, 0775, true);

        // 1) если текстов нет — копируем из _default/texts
        if ($label !== '_default') {
            $defaultTexts = rtrim($buildAbs, '/\\') . '/subs/_default/texts';
            if (is_dir($defaultTexts)) {
                $this->copyRecursiveIfMissing($defaultTexts, $textsDir);
            }
        }

        // 2) гарантируем config.php (из БД, если есть)
        $baseCfg = $this->loadDefaultCfg($siteId);
        $subCfg  = $this->loadSubCfg($siteId, $label);

        $w = new MultiSiteConfigWriter();
        $w->writeSubConfigPhp($buildAbs, $label, $subCfg, $baseCfg);
    }

    public function deleteFolderForSite(int $siteId, string $label): void
    {
        $label = trim((string)$label);
        if ($label === '' || $label === '_default') return;

        $site = $this->loadSite($siteId);
        if (!$site) return;

        if (($site['template'] ?? '') !== 'template-multy') {
            return;
        }

        $buildAbs = $this->getBuildAbs($siteId, $site);
        if ($buildAbs === '') return;

        $subDir = rtrim($buildAbs, '/\\') . '/subs/' . $label;
        if (!is_dir($subDir)) return;

        $this->rrmdir($subDir);
    }

    // -------------------- DB helpers --------------------

    private function loadSite(int $siteId): ?array
    {
        $pdo = DB::pdo();
        $st = $pdo->prepare("SELECT * FROM sites WHERE id=? LIMIT 1");
        $st->execute([$siteId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function loadDefaultCfg(int $siteId): array
    {
        $pdo = DB::pdo();
        $st = $pdo->prepare("SELECT config_json FROM site_default_configs WHERE site_id=? LIMIT 1");
        $st->execute([$siteId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        $cfg = [];
        if ($row && isset($row['config_json'])) {
            $cfg = json_decode((string)$row['config_json'], true);
            if (!is_array($cfg)) $cfg = [];
        }
        return $cfg;
    }

    private function loadSubCfg(int $siteId, string $label): array
    {
        $pdo = DB::pdo();
        $st = $pdo->prepare("SELECT config_json FROM site_subdomain_configs WHERE site_id=? AND label=? LIMIT 1");
        $st->execute([$siteId, $label]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        $cfg = [];
        if ($row && isset($row['config_json'])) {
            $cfg = json_decode((string)$row['config_json'], true);
            if (!is_array($cfg)) $cfg = [];
        }
        return $cfg;
    }

    // -------------------- Filesystem helpers --------------------

    private function getBuildAbs(int $siteId, array $site): string
    {
        // по умолчанию как у тебя на скрине: storage/builds/site_10
        $p = trim((string)($site['build_path'] ?? ''));
        if ($p !== '') {
            // если абсолютный путь
            if ($p[0] === '/' && is_dir($p)) return $p;

            // если относительный — считаем от APP_ROOT
            $cand = rtrim(APP_ROOT, '/\\') . '/' . ltrim($p, '/\\');
            if (is_dir($cand)) return $cand;
        }

        $fallback = rtrim(APP_ROOT, '/\\') . '/storage/builds/site_' . $siteId;
        return $fallback;
    }

    private function copyRecursiveIfMissing(string $src, string $dst): void
    {
        $src = rtrim($src, '/\\');
        $dst = rtrim($dst, '/\\');

        if (!is_dir($src)) return;
        @mkdir($dst, 0775, true);

        $items = @scandir($src);
        if (!is_array($items)) return;

        foreach ($items as $it) {
            if ($it === '.' || $it === '..') continue;

            $from = $src . '/' . $it;
            $to   = $dst . '/' . $it;

            if (is_dir($from)) {
                @mkdir($to, 0775, true);
                $this->copyRecursiveIfMissing($from, $to);
            } else {
                if (!is_file($to)) {
                    @copy($from, $to);
                    @chmod($to, 0664);
                }
            }
        }
    }

    private function rrmdir(string $dir): void
    {
        $dir = rtrim($dir, '/\\');
        if (!is_dir($dir)) return;

        $items = @scandir($dir);
        if (is_array($items)) {
            foreach ($items as $it) {
                if ($it === '.' || $it === '..') continue;

                $p = $dir . '/' . $it;
                if (is_dir($p)) {
                    $this->rrmdir($p);
                } else {
                    @chmod($p, 0664);
                    @unlink($p);
                }
            }
        }
        @chmod($dir, 0775);
        @rmdir($dir);
    }
}

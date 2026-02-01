<?php
// app/Services/SubdomainProvisioner.php

class SubdomainProvisioner
{
    /**
     * Гарантирует:
     *  - строку в site_subdomain_configs (если нет — копирует из site_default_configs)
     *  - папки subs/<label>/{texts,assets}
     *  - копию базовых texts/assets из subs/_default
     *  - генерацию subs/<label>/config.php
     */
    public function ensureForSite(int $siteId, string $label): void
    {
        $label = $this->normalizeLabel($label, true);

        $site = $this->loadSite($siteId);
        if (!$site) return;

        if (($site['template'] ?? '') !== 'template-multy') return;

        $buildRel = (string)($site['build_path'] ?? '');
        if ($buildRel === '') return;

        $buildAbs = $this->toAbsPath($buildRel);

        $pdo = DB::pdo();

        $defaultCfg = $this->loadDefaultCfg($pdo, $siteId);
        $subCfg = $this->loadSubCfg($pdo, $siteId, $label);

        if ($subCfg === null) {
            $subCfg = $defaultCfg;
            $this->upsertSubCfg($pdo, $siteId, $label, $subCfg);
        }

        // ФС структура (если build еще не делали — папок может не быть, но это не ошибка)
        $this->ensureFs($buildAbs, $label);

        // Генерация config.php
        $this->writeSubConfig($buildAbs, $label, $subCfg, $defaultCfg);
    }

    public function ensureFs(string $buildAbs, string $label): void
    {
        $label = $this->normalizeLabel($label, true);

        $subsDir = rtrim($buildAbs, '/\\') . '/subs';
		if (!is_dir($subsDir)) {
			@mkdir($subsDir, 0775, true);
		}

        $srcBase = $subsDir . '/_default';
        $dstBase = $subsDir . '/' . $label;

        if (!is_dir($dstBase)) {
            @mkdir($dstBase, 0775, true);
        }

        // texts
        $srcTexts = $srcBase . '/texts';
        $dstTexts = $dstBase . '/texts';
        if (!is_dir($dstTexts)) @mkdir($dstTexts, 0775, true);

        $this->copyIfMissing($srcTexts . '/home.php', $dstTexts . '/home.php');
        $this->copyIfMissing($srcTexts . '/404.php',  $dstTexts . '/404.php');

        // если даже в _default нет — создаем минималки
        if (!is_file($dstTexts . '/home.php')) {
            file_put_contents($dstTexts . '/home.php', "<h2>Home</h2>\n<p>TODO</p>\n");
        }
        if (!is_file($dstTexts . '/404.php')) {
            file_put_contents($dstTexts . '/404.php', "<h2>404</h2>\n<p>Not found</p>\n");
        }

        // assets
        $srcAssets = $srcBase . '/assets';
        $dstAssets = $dstBase . '/assets';
        if (!is_dir($dstAssets)) @mkdir($dstAssets, 0775, true);

        // копируем все файлы из assets/_default, но только если их нет в сабе
        if (is_dir($srcAssets)) {
            $this->copyDirFilesIfMissing($srcAssets, $dstAssets);
        }
    }

    private function writeSubConfig(string $buildAbs, string $label, array $subCfg, array $defaultCfg): void
    {
        $label = $this->normalizeLabel($label, true);

        $writerPath = __DIR__ . '/MultiSiteConfigWriter.php';
        if (!is_file($writerPath)) return;

        require_once $writerPath;

        $w = new MultiSiteConfigWriter();

        $subDir = rtrim($buildAbs, '/\\') . '/subs/' . $label;
		if (!is_dir($subDir)) {
			@mkdir($subDir, 0775, true);
		}


        // Поддерживаем разные варианты writer-а
        if (method_exists($w, 'writeSubConfigPhp')) {
            // (buildDir, label, cfg, fallback)
            $w->writeSubConfigPhp($buildAbs, $label, $subCfg, $defaultCfg);
            return;
        }

        if (method_exists($w, 'writeSubConfig')) {
            // (subDir, cfg, fallback)
            $w->writeSubConfig($subDir, $subCfg, $defaultCfg);
            return;
        }
    }

    // ===== DB helpers =====

    private function loadSite(int $siteId): ?array
    {
        $pdo = DB::pdo();
        $st = $pdo->prepare("SELECT * FROM sites WHERE id = ? LIMIT 1");
        $st->execute([$siteId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function loadDefaultCfg(PDO $pdo, int $siteId): array
    {
        $st = $pdo->prepare("SELECT config_json FROM site_default_configs WHERE site_id = ? LIMIT 1");
        $st->execute([$siteId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) return [];
        $cfg = json_decode($row['config_json'] ?? '[]', true);
        return is_array($cfg) ? $cfg : [];
    }

    private function loadSubCfg(PDO $pdo, int $siteId, string $label): ?array
    {
        $st = $pdo->prepare("SELECT config_json FROM site_subdomain_configs WHERE site_id = ? AND label = ? LIMIT 1");
        $st->execute([$siteId, $label]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;
        $cfg = json_decode($row['config_json'] ?? '[]', true);
        return is_array($cfg) ? $cfg : [];
    }

    private function upsertSubCfg(PDO $pdo, int $siteId, string $label, array $cfg): void
    {
        $json = json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $st = $pdo->prepare("SELECT 1 FROM site_subdomain_configs WHERE site_id = ? AND label = ? LIMIT 1");
        $st->execute([$siteId, $label]);

        if ($st->fetchColumn()) {
            $u = $pdo->prepare("UPDATE site_subdomain_configs SET config_json = ? WHERE site_id = ? AND label = ?");
            $u->execute([$json, $siteId, $label]);
        } else {
            $i = $pdo->prepare("INSERT INTO site_subdomain_configs (site_id, label, config_json) VALUES (?, ?, ?)");
            $i->execute([$siteId, $label, $json]);
        }
    }

    // ===== FS helpers =====

    private function copyIfMissing(string $src, string $dst): void
    {
        if (!is_file($dst) && is_file($src)) {
            @copy($src, $dst);
        }
    }

    private function copyDirFilesIfMissing(string $srcDir, string $dstDir): void
    {
        $items = @scandir($srcDir);
        if (!$items) return;

        foreach ($items as $name) {
            if ($name === '.' || $name === '..') continue;

            $src = $srcDir . '/' . $name;
            $dst = $dstDir . '/' . $name;

            if (is_file($src) && !is_file($dst)) {
                @copy($src, $dst);
            }
        }
    }

private function toAbsPath(string $rel): string
{
    // APP_ROOT должен быть /.../public_html
    $appRoot = defined('APP_ROOT')
        ? rtrim(APP_ROOT, "/\\")
        : rtrim(realpath(__DIR__ . '/../../') ?: dirname(__DIR__, 2), "/\\");

    $rel = trim($rel);
    $rel = str_replace('\\', '/', $rel);
    $rel = ltrim($rel, '/');

    // запрет ../
    if (preg_match('~(^|/)\.\.(/|$)~', $rel)) {
        error_log("SECURITY: build_path contains .. : " . $rel);
        $rel = 'storage/builds/invalid_path';
    }

    // разрешаем только storage/builds/...
    if (!preg_match('~^storage/builds/~', $rel)) {
        error_log("SECURITY: unexpected build_path: " . $rel);
        $rel = 'storage/builds/invalid_path';
    }

    $abs = $appRoot . '/' . $rel;

    // финальная проверка: путь обязан начинаться с STORAGE_ROOT/builds
    if (defined('STORAGE_ROOT')) {
        $base = rtrim(str_replace('\\', '/', STORAGE_ROOT), '/') . '/builds/';
        $absN = str_replace('\\', '/', $abs);

        if (strpos($absN, $base) !== 0) {
            error_log("SECURITY: abs build path outside STORAGE_ROOT. abs={$absN} base={$base}");
            // форсируем внутрь правильного storage
            $abs = rtrim(STORAGE_ROOT, "/\\") . '/builds/invalid_path';
        }
    }

    return $abs;
}




    private function normalizeLabel(string $label, bool $allowDefault): string
    {
        $label = strtolower(trim($label));
        $label = preg_replace('~\s+~', '', $label);

        if ($allowDefault && $label === '_default') return '_default';

        $label = preg_replace('~[^a-z0-9\-]+~', '', $label);
        $label = trim($label, '-');

        if ($label === '') $label = 'sub';
        return $label;
    }
}

<?php
// app/Services/SubdomainProvisioner.php

class SubdomainProvisioner
{
    public function ensureForSite(int $siteId, string $label): array
    {
        $label = trim((string)$label);
        if ($label === '') {
            return ['ok' => 0, 'error' => 'empty label'];
        }

        $site = $this->loadSite($siteId);
        if (!$site) {
            return ['ok' => 0, 'error' => 'site not found'];
        }

        if (($site['template'] ?? '') !== 'template-multy') {
            return ['ok' => 0, 'error' => 'not template-multy'];
        }

        $buildAbs = $this->getBuildAbs($siteId, $site);
        if ($buildAbs === '') {
            return ['ok' => 0, 'error' => 'build path not found (getBuildAbs returned empty)'];
        }

        $buildAbs = rtrim($buildAbs, '/\\');

        $subsRoot = $buildAbs . '/subs';
        $subDir    = $subsRoot . '/' . $label;
        $textsDir  = $subDir . '/texts';
        $assetsDir = $subDir . '/assets';

        @error_log('[SubdomainProvisioner] site_id=' . $siteId . ' label=' . $label . ' buildAbs=' . $buildAbs);
        @error_log('[SubdomainProvisioner] paths subsRoot=' . $subsRoot . ' subDir=' . $subDir);

        try {
            $this->mkdirOrThrow($subsRoot, 0775, true);

            $this->mkdirOrThrow($subDir, 0775, true);
            $this->mkdirOrThrow($textsDir, 0775, true);
            $this->mkdirOrThrow($assetsDir, 0775, true);

            if (!is_dir($subDir) || !is_dir($textsDir) || !is_dir($assetsDir)) {
                return ['ok' => 0, 'error' => 'mkdir finished but directories not present on FS'];
            }

            // 1) если текстов нет — копируем из _default/texts
            if ($label !== '_default') {
                $defaultTexts = $subsRoot . '/_default/texts';
                if (is_dir($defaultTexts)) {
                    $this->copyRecursiveIfMissing($defaultTexts, $textsDir);
                } else {
                    @error_log('[SubdomainProvisioner] defaultTexts not found: ' . $defaultTexts);
                }
            }

            // 2) гарантируем config.php (из БД, если есть)
            $baseCfg = $this->loadDefaultCfg($siteId);
            $subCfg  = $this->loadSubCfg($siteId, $label);

            require_once __DIR__ . '/MultiSiteConfigWriter.php';
            $w = new MultiSiteConfigWriter();
            $w->writeSubConfigPhp($buildAbs, $label, $subCfg, $baseCfg);

            // проверка, что config.php реально появился
            $cfgPath = $buildAbs . '/subs/' . $label . '/config.php';
            if (!is_file($cfgPath)) {
                @error_log('[SubdomainProvisioner] config.php NOT created at ' . $cfgPath);
                return ['ok' => 0, 'error' => 'config.php not created: ' . $cfgPath];
            }

            @error_log('[SubdomainProvisioner] OK created label=' . $label . ' cfg=' . $cfgPath);

            return [
                'ok' => 1,
                'buildAbs' => $buildAbs,
                'subsRoot' => $subsRoot,
                'subDir' => $subDir,
                'config' => $cfgPath,
            ];
        } catch (Throwable $e) {
            @error_log('[SubdomainProvisioner] ERROR site_id=' . $siteId . ' label=' . $label . ' err=' . $e->getMessage());
            return ['ok' => 0, 'error' => $e->getMessage()];
        }
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

        @error_log('[SubdomainProvisioner delete] site_id=' . $siteId . ' label=' . $label . ' dir=' . $subDir);

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
        if ($row && array_key_exists('config_json', $row)) {
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
        if ($row && array_key_exists('config_json', $row)) {
            $cfg = json_decode((string)$row['config_json'], true);
            if (!is_array($cfg)) $cfg = [];
        }
        return $cfg;
    }

    // -------------------- Filesystem helpers --------------------

    private function getBuildAbs(int $siteId, array $site): string
	{
		$p = trim((string)($site['build_path'] ?? ''));
		if ($p === '') return '';

		// build_path у нас должен быть относительным и начинаться с builds/
		$p = str_replace('\\', '/', $p);
		$p = ltrim($p, '/');

		// на всякий: если кто-то записал storage/builds/... в БД
		if (strpos($p, 'storage/') === 0) {
			$p = substr($p, strlen('storage/'));
			$p = ltrim($p, '/');
		}

		if (strpos($p, 'builds/') !== 0) {
			// если в БД мусор — пытаемся стандартный путь
			$p = 'builds/site_' . $siteId;
		}

		// ЕДИНСТВЕННЫЙ источник истины: storage
		$abs = rtrim(Paths::storage($p), "/\\");
		if (is_dir($abs)) return $abs;

		// если папки еще нет — все равно возвращаем ожидаемый путь,
		// а mkdir дальше создаст недостающее
		return $abs;
	}


    private function mkdirOrThrow(string $dir, int $mode = 0775, bool $recursive = true): void
    {
        if (is_dir($dir)) return;

        $ok = @mkdir($dir, $mode, $recursive);
        if ($ok) {
            @error_log('[SubdomainProvisioner mkdir] OK dir=' . $dir);
            return;
        }

        $err = error_get_last();
        $msg = $err ? ((string)($err['message'] ?? 'mkdir failed')) : 'mkdir failed';

        error_log('[SubdomainProvisioner mkdir] FAIL dir=' . $dir . ' msg=' . $msg);
        throw new RuntimeException('Cannot create directory: ' . $dir . ' (' . $msg . ')');
    }

    private function copyRecursiveIfMissing(string $src, string $dst): void
    {
        $src = rtrim($src, '/\\');
        $dst = rtrim($dst, '/\\');

        if (!is_dir($src)) return;
        if (!is_dir($dst)) $this->mkdirOrThrow($dst, 0775, true);

        $items = @scandir($src);
        if (!is_array($items)) return;

        foreach ($items as $it) {
            if ($it === '.' || $it === '..') continue;

            $from = $src . '/' . $it;
            $to   = $dst . '/' . $it;

            if (is_dir($from)) {
                if (!is_dir($to)) $this->mkdirOrThrow($to, 0775, true);
                $this->copyRecursiveIfMissing($from, $to);
            } else {
                if (!is_file($to)) {
                    @copy($from, $to);
                    @chmod($to, 0664);
                    @error_log('[SubdomainProvisioner copy] ' . $from . ' -> ' . $to);
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

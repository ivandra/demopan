<?php
// app/Services/SubdomainProvisioner.php

class SubdomainProvisioner
{
    private SiteStructure $structure;
    private SiteConfigResolver $resolver;

    public function __construct(?SiteStructure $structure = null, ?SiteConfigResolver $resolver = null)
    {
        $this->structure = $structure ?: new SiteStructure();
        $this->resolver  = $resolver ?: new SiteConfigResolver();
    }

    public function ensureForSite(int $siteId, string $label): array
    {
        $label = $this->structure->normalizeLabel($label, true);
        if ($label === '') {
            return ['ok' => 0, 'error' => 'empty label'];
        }

        $site = $this->loadSite($siteId);
        if (!$site) {
            return ['ok' => 0, 'error' => 'site not found'];
        }

        $buildAbs        = $this->structure->buildAbsPath($siteId, $site);
        $subsRoot        = $this->structure->subsRootAbsPath($siteId, $site);
        $subDir          = $this->structure->subAbsPath($siteId, $label, $site);
        $textsDir        = $this->structure->textsAbsPath($siteId, $label, $site);
        $assetsDir       = $this->structure->assetsAbsPath($siteId, $label, $site);
        $cfgPath         = $this->structure->configPhpPath($siteId, $label, $site);
        $rootCfgPhpPath  = $this->structure->rootConfigDefaultPhpPath($siteId, $site);

        @error_log('[SubdomainProvisioner] site_id=' . $siteId . ' label=' . $label . ' buildAbs=' . $buildAbs);
        @error_log('[SubdomainProvisioner] paths subsRoot=' . $subsRoot . ' subDir=' . $subDir);

        try {
            $this->mkdirOrThrow($buildAbs, 0775, true);
            $this->mkdirOrThrow($subsRoot, 0775, true);
            $this->mkdirOrThrow($subDir, 0775, true);
            $this->mkdirOrThrow($textsDir, 0775, true);
            $this->mkdirOrThrow($assetsDir, 0775, true);

            if (!is_dir($subDir) || !is_dir($textsDir) || !is_dir($assetsDir)) {
                return ['ok' => 0, 'error' => 'mkdir finished but directories not present on FS'];
            }

            // если это не root — копируем отсутствующие texts/assets из _default
            if (!$this->structure->isRootLabel($label)) {
                $defaultTexts  = $this->structure->textsAbsPath($siteId, $this->structure->rootLabel(), $site);
                $defaultAssets = $this->structure->assetsAbsPath($siteId, $this->structure->rootLabel(), $site);

                if (is_dir($defaultTexts)) {
                    $this->copyRecursiveIfMissing($defaultTexts, $textsDir);
                } else {
                    @error_log('[SubdomainProvisioner] defaultTexts not found: ' . $defaultTexts);
                }

                if (is_dir($defaultAssets)) {
                    $this->copyRecursiveIfMissing($defaultAssets, $assetsDir);
                } else {
                    @error_log('[SubdomainProvisioner] defaultAssets not found: ' . $defaultAssets);
                }
            }

            // база из site_default_configs / fallback site_configs
            $baseCfg = $this->resolver->loadSiteDefaultConfig(
                $siteId,
                (string)($site['domain'] ?? '')
            );

            if (!isset($baseCfg['domain']) || trim((string)$baseCfg['domain']) === '') {
                $baseCfg['domain'] = (string)($site['domain'] ?? '');
            }

            // гарантируем наличие записи в site_subdomain_configs и для _default, и для обычного label
            $subCfg = $this->resolver->ensureSubdomainConfigExists($siteId, $label, $baseCfg);

            if (!is_array($subCfg) || empty($subCfg)) {
                $subCfg = $this->resolver->loadSubdomainConfig($siteId, $label);
            }

            if (!is_array($subCfg) || empty($subCfg)) {
                $subCfg = $baseCfg;
                $subCfg['label'] = $label;
            }

            require_once __DIR__ . '/MultiSiteConfigWriter.php';
            $writer = new MultiSiteConfigWriter();

            // всегда держим корневой config.default.php актуальным
            $writer->writeDefaultConfig($buildAbs, $baseCfg);

            // и локальный subs/<label>/config.php
            $writer->writeSubConfigPhp($buildAbs, $label, $subCfg, $baseCfg);

            if (!is_file($rootCfgPhpPath)) {
                @error_log('[SubdomainProvisioner] root config.default.php NOT created at ' . $rootCfgPhpPath);
                return ['ok' => 0, 'error' => 'config.default.php not created: ' . $rootCfgPhpPath];
            }

            if (!is_file($cfgPath)) {
                @error_log('[SubdomainProvisioner] config.php NOT created at ' . $cfgPath);
                return ['ok' => 0, 'error' => 'config.php not created: ' . $cfgPath];
            }

            @error_log('[SubdomainProvisioner] OK created label=' . $label . ' cfg=' . $cfgPath);

            return [
                'ok'         => 1,
                'label'      => $label,
                'buildAbs'   => $buildAbs,
                'subsRoot'   => $subsRoot,
                'subDir'     => $subDir,
                'textsDir'   => $textsDir,
                'assetsDir'  => $assetsDir,
                'rootConfig' => $rootCfgPhpPath,
                'config'     => $cfgPath,
            ];
        } catch (Throwable $e) {
            @error_log('[SubdomainProvisioner] ERROR site_id=' . $siteId . ' label=' . $label . ' err=' . $e->getMessage());
            return ['ok' => 0, 'error' => $e->getMessage()];
        }
    }

    public function deleteFolderForSite(int $siteId, string $label): void
    {
        $label = $this->structure->normalizeLabel($label, true);
        if ($label === '' || $this->structure->isRootLabel($label)) {
            return;
        }

        $site = $this->loadSite($siteId);
        if (!$site) {
            return;
        }

        $subDir = $this->structure->subAbsPath($siteId, $label, $site);
        if (!is_dir($subDir)) {
            return;
        }

        @error_log('[SubdomainProvisioner delete] site_id=' . $siteId . ' label=' . $label . ' dir=' . $subDir);

        $this->rrmdir($subDir);
    }

    private function loadSite(int $siteId): ?array
    {
        $pdo = DB::pdo();
        $st = $pdo->prepare("SELECT * FROM sites WHERE id=? LIMIT 1");
        $st->execute([$siteId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function mkdirOrThrow(string $dir, int $mode = 0775, bool $recursive = true): void
    {
        if (is_dir($dir)) {
            return;
        }

        $ok = @mkdir($dir, $mode, $recursive);
        if ($ok) {
            @error_log('[SubdomainProvisioner mkdir] OK dir=' . $dir);
            return;
        }

        $err = error_get_last();
        $msg = $err ? ((string)($err['message'] ?? 'mkdir failed')) : 'mkdir failed';

        @error_log('[SubdomainProvisioner mkdir] FAIL dir=' . $dir . ' msg=' . $msg);
        throw new RuntimeException('Cannot create directory: ' . $dir . ' (' . $msg . ')');
    }

    private function copyRecursiveIfMissing(string $src, string $dst): void
    {
        $src = rtrim($src, '/\\');
        $dst = rtrim($dst, '/\\');

        if (!is_dir($src)) {
            return;
        }
        if (!is_dir($dst)) {
            $this->mkdirOrThrow($dst, 0775, true);
        }

        $items = @scandir($src);
        if (!is_array($items)) {
            return;
        }

        foreach ($items as $it) {
            if ($it === '.' || $it === '..') {
                continue;
            }

            $from = $src . '/' . $it;
            $to   = $dst . '/' . $it;

            if (is_dir($from)) {
                if (!is_dir($to)) {
                    $this->mkdirOrThrow($to, 0775, true);
                }
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
        if (!is_dir($dir)) {
            return;
        }

        $items = @scandir($dir);
        if (is_array($items)) {
            foreach ($items as $it) {
                if ($it === '.' || $it === '..') {
                    continue;
                }

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
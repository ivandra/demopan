<?php
class SubdomainProvisioner
{
    private MultiSiteConfigWriter $writer;

    public function __construct()
    {
        $this->writer = new MultiSiteConfigWriter();
    }

    public function ensureForSite(int $siteId, string $label): void
    {
        $pdo = DB::pdo();

        $st = $pdo->prepare("SELECT * FROM sites WHERE id = ? LIMIT 1");
        $st->execute([$siteId]);
        $site = $st->fetch(PDO::FETCH_ASSOC);
        if (!$site) {
            throw new RuntimeException("site not found");
        }

        $buildRel = (string)($site['build_path'] ?? '');
        if ($buildRel === '') {
            throw new RuntimeException("build_path empty");
        }

        $buildAbs = $this->toAbsBuildPath($buildRel);

        // грузим дефолт
        $cfg = $this->loadDefaultCfg($pdo, $siteId);

        // оверлей для label
        if ($label !== '_default') {
            $sub = $this->loadSubCfg($pdo, $siteId, $label);
            if (is_array($sub)) {
                $cfg = array_merge($cfg, $sub);
            }
        }

        // гарантируем папки subs/<label>/texts + config.php
        $subDir = rtrim($buildAbs, "/\\") . '/subs/' . $label;
        if (!is_dir($subDir)) {
            @mkdir($subDir, 0775, true);
        }

        $textsDir = $subDir . '/texts';
        if (!is_dir($textsDir)) {
            @mkdir($textsDir, 0775, true);
        }

        $this->writer->writeSubConfig($buildAbs, $label, $cfg);
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

    private function toAbsBuildPath(string $rel): string
    {
        $rel = trim($rel);
        $rel = str_replace('\\', '/', $rel);
        $rel = ltrim($rel, '/');

        // поддержка legacy: "<storage_basename>/builds/..." и нового формата "builds/..."
        $storageBase = basename(rtrim(Paths::storage(''), "/\\"));
        if ($storageBase !== '' && strpos($rel, $storageBase . '/') === 0) {
            $rel = substr($rel, strlen($storageBase) + 1);
        }

        // запрещаем выходы наверх
        if (preg_match('~(^|/)\.\.(?:/|$)~', $rel)) {
            return Paths::storage('builds/invalid_path');
        }

        // ожидаем builds/...
        if (strpos($rel, 'builds/') !== 0) {
            return Paths::storage('builds/invalid_path');
        }

        $abs = Paths::storage($rel);
        return rtrim($abs, "/\\");
    }
}

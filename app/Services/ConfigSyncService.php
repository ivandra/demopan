<?php

class ConfigSyncService
{
    private SiteStructure $structure;

    public function __construct(?SiteStructure $structure = null)
    {
        $this->structure = $structure ?: new SiteStructure();
    }

    public function syncRootConfigFiles(int $siteId): array
    {
        $site = $this->loadSite($siteId);
        if (!$site) {
            throw new RuntimeException('site not found');
        }

        $localRoot = $this->structure->rootConfigDefaultPhpPath($siteId, $site);
        $localSub  = $this->structure->configPhpPath($siteId, '_default', $site);

        // ВАЖНО: для site-specific FTP логин уже попадает в корень сайта.
        // Нельзя использовать абсолютный /var/www/... путь, иначе FTP создаёт внутри сайта папку var/www/...
        $this->uploadFile($site, $localRoot, 'config.default.php');
        $this->uploadFile($site, $localSub,  'subs/_default/config.php');

        return [
            'ok' => true,
            'uploaded' => ['config.default.php', 'subs/_default/config.php'],
            'label' => '_default',
        ];
    }

    public function syncLabelConfigFiles(int $siteId, string $label): array
    {
        $label = (new SiteStructure())->normalizeLabel($label, true);
        if ($label === '_default') {
            return $this->syncRootConfigFiles($siteId);
        }

        $site = $this->loadSite($siteId);
        if (!$site) {
            throw new RuntimeException('site not found');
        }

        $localSub = $this->structure->configPhpPath($siteId, $label, $site);
        $this->uploadFile($site, $localSub, 'subs/' . $label . '/config.php');

        return [
            'ok' => true,
            'uploaded' => ['subs/' . $label . '/config.php'],
            'label' => $label,
        ];
    }

    private function uploadFile(array $site, string $localFile, string $remoteFile): void
    {
        if (!is_file($localFile)) {
            throw new RuntimeException('local file not found: ' . $localFile);
        }

        $serverId = (int)($site['fastpanel_server_id'] ?? 0);
        if ($serverId <= 0) {
            throw new RuntimeException('fastpanel_server_id is empty');
        }

        $ftpUser = trim((string)($site['fp_ftp_user'] ?? ''));
        $ftpPassEnc = (string)($site['fp_ftp_pass_enc'] ?? '');
        if ($ftpUser === '' || $ftpPassEnc === '') {
            throw new RuntimeException('FTP credentials are empty');
        }

        $st = DB::pdo()->prepare('SELECT * FROM fastpanel_servers WHERE id=? LIMIT 1');
        $st->execute([$serverId]);
        $server = $st->fetch(PDO::FETCH_ASSOC);
        if (!$server) {
            throw new RuntimeException('fastpanel server not found');
        }

        $ftpPass = Crypto::decrypt($ftpPassEnc);
        $ftpHost = $this->extractHost((string)($server['host'] ?? ''));
        if ($ftpHost === '') {
            throw new RuntimeException('FTP host is empty');
        }

        require_once Paths::appRoot() . '/app/Services/FtpUploader.php';
        $uploader = new \App\Services\FtpUploader();
        $uploader->upload($ftpHost, 21, $ftpUser, $ftpPass, $localFile, $remoteFile);
    }

    private function extractHost(string $host): string
    {
        $host = trim($host);
        if ($host === '') {
            return '';
        }
        if (preg_match('~^https?://~i', $host)) {
            $parsed = parse_url($host, PHP_URL_HOST);
            return is_string($parsed) ? $parsed : '';
        }
        $pos = strpos($host, ':');
        return $pos === false ? $host : substr($host, 0, $pos);
    }

    private function loadSite(int $siteId): ?array
    {
        $st = DB::pdo()->prepare('SELECT * FROM sites WHERE id=? LIMIT 1');
        $st->execute([$siteId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}

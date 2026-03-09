<?php

class SiteStructure
{
    public function rootLabel(): string
    {
        return '_default';
    }

    public function isRootLabel(?string $label): bool
    {
        return $this->normalizeLabel($label, true) === $this->rootLabel();
    }

    public function normalizeLabel(?string $label, bool $allowRoot = true): string
    {
        $label = strtolower(trim((string)$label));
        $label = preg_replace('~\s+~', '', $label);

        if ($allowRoot && ($label === '' || $label === '_default')) {
            return $this->rootLabel();
        }

        $label = preg_replace('~[^a-z0-9\-]+~', '', $label);
        $label = trim($label, '-');

        if ($label === '') {
            return $allowRoot ? $this->rootLabel() : 'sub';
        }

        return $label;
    }

    public function fqdn(array $site, ?string $label = null): string
    {
        $domain = trim((string)($site['domain'] ?? ''));
        $label  = $this->normalizeLabel($label, true);

        if ($domain === '' || $this->isRootLabel($label)) {
            return $domain;
        }

        return $label . '.' . $domain;
    }

    public function buildRelPath(int $siteId, array $site = []): string
    {
        $p = trim((string)($site['build_path'] ?? ''));
        if ($p === '') {
            return 'builds/site_' . $siteId;
        }

        $p = str_replace('\\', '/', $p);
        $p = ltrim($p, '/');

        if (strpos($p, 'storage/') === 0) {
            $p = substr($p, strlen('storage/'));
            $p = ltrim($p, '/');
        }

        $storageBase = basename(rtrim(Paths::storage(''), '/\\'));
        if ($storageBase !== '' && strpos($p, $storageBase . '/') === 0) {
            $p = substr($p, strlen($storageBase) + 1);
        }

        if (preg_match('~(^|/)\.\.(?:/|$)~', $p)) {
            return 'builds/site_' . $siteId;
        }

        if (strpos($p, 'builds/') !== 0) {
            return 'builds/site_' . $siteId;
        }

        return $p;
    }

    public function buildAbsPath(int $siteId, array $site = []): string
    {
        return rtrim(Paths::storage($this->buildRelPath($siteId, $site)), '/\\');
    }

    public function subsRootAbsPath(int $siteId, array $site = []): string
    {
        return $this->buildAbsPath($siteId, $site) . '/subs';
    }

    public function subAbsPath(int $siteId, ?string $label, array $site = []): string
    {
        $label = $this->normalizeLabel($label, true);
        return $this->subsRootAbsPath($siteId, $site) . '/' . $label;
    }

    public function textsAbsPath(int $siteId, ?string $label, array $site = []): string
    {
        return $this->subAbsPath($siteId, $label, $site) . '/texts';
    }

    public function assetsAbsPath(int $siteId, ?string $label, array $site = []): string
    {
        return $this->subAbsPath($siteId, $label, $site) . '/assets';
    }

    public function rootConfigDefaultPhpPath(int $siteId, array $site = []): string
    {
        return $this->buildAbsPath($siteId, $site) . '/config.default.php';
    }

    public function configPhpPath(int $siteId, ?string $label, array $site = []): string
    {
        return $this->subAbsPath($siteId, $label, $site) . '/config.php';
    }
}

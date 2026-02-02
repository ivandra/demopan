<?php
// app/Controllers/SiteSubCfgController.php

class SiteSubCfgController extends Controller
{
    private function requireAuth(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }

        if (empty($_SESSION['user_id'])) {
            $this->redirect('/login');
            exit;
        }
    }

    public function form(): void
    {
        $this->requireAuth();

        $siteId = (int)($_GET['id'] ?? 0);
        if ($siteId <= 0) {
            echo "no site id";
            return;
        }

        $site = $this->loadSite($siteId);
        if (!$site) {
            echo "site not found";
            return;
        }

        if (($site['template'] ?? '') !== 'template-multy') {
            echo "template is not template-multy";
            return;
        }

        $pdo = DB::pdo();

        $labels = $this->listLabels($pdo, $siteId); // всегда включает _default

        $label = (string)($_GET['label'] ?? '_default');
        $label = $this->normalizeLabel($label, true);
        if (!in_array($label, $labels, true)) {
            $label = '_default';
        }

        // гарантируем fs+config.php для выбранного
        $prov = new SubdomainProvisioner();
        $prov->ensureForSite($siteId, $label);

        // ВАЖНО:
        // _default редактируем через site_default_configs
        if ($label === '_default') {
            $cfg = $this->loadDefaultCfg($pdo, $siteId);
        } else {
            $cfg = $this->loadSubCfg($pdo, $siteId, $label);
        }
        if ($cfg === null) $cfg = [];

        $unused = $this->findUnusedTexts($site, $label, $cfg);

        $this->view('sites/subcfg', [
            'site'   => $site,
            'siteId' => $siteId,
            'label'  => $label,
            'labels' => $labels,
            'cfg'    => $cfg,
            'unused' => $unused,
        ]);
    }

    public function save(): void
    {
        $this->requireAuth();

        $siteId = (int)($_POST['site_id'] ?? 0);
        $label  = (string)($_POST['label'] ?? '_default');
        $label  = $this->normalizeLabel($label, true);

        if ($siteId <= 0) {
            $this->redirect('/sites');
            exit;
        }

        $pdo = DB::pdo();

        // Загружаем текущий cfg
        if ($label === '_default') {
            $cfg = $this->loadDefaultCfg($pdo, $siteId);
        } else {
            $cfg = $this->loadSubCfg($pdo, $siteId, $label);
        }
        if ($cfg === null) $cfg = [];

        // обновляем только параметры (pages не трогаем тут)
        $cfg['title']       = (string)($_POST['title'] ?? ($cfg['title'] ?? ''));
        $cfg['h1']          = (string)($_POST['h1'] ?? ($cfg['h1'] ?? ''));
        $cfg['description'] = (string)($_POST['description'] ?? ($cfg['description'] ?? ''));
        $cfg['keywords']    = (string)($_POST['keywords'] ?? ($cfg['keywords'] ?? ''));

        $cfg['promolink']            = (string)($_POST['promolink'] ?? ($cfg['promolink'] ?? '/reg'));
        $cfg['internal_reg_url']     = (string)($_POST['internal_reg_url'] ?? ($cfg['internal_reg_url'] ?? ''));
        $cfg['partner_override_url'] = (string)($_POST['partner_override_url'] ?? ($cfg['partner_override_url'] ?? ''));
        $cfg['redirect_enabled']     = (int)(isset($_POST['redirect_enabled']) ? 1 : 0);

        $cfg['base_new_url']    = (string)($_POST['base_new_url'] ?? ($cfg['base_new_url'] ?? ''));
        $cfg['base_second_url'] = (string)($_POST['base_second_url'] ?? ($cfg['base_second_url'] ?? ''));

        $cfg['logo']    = (string)($_POST['logo'] ?? ($cfg['logo'] ?? 'assets/logo.png'));
        $cfg['favicon'] = (string)($_POST['favicon'] ?? ($cfg['favicon'] ?? 'assets/favicon.png'));

        // Пишем в нужную таблицу
        if ($label === '_default') {
            $this->upsertDefaultCfg($pdo, $siteId, $cfg);
        } else {
            $this->upsertSubCfg($pdo, $siteId, $label, $cfg);
        }

        // fs + config.php
        $prov = new SubdomainProvisioner();
        $prov->ensureForSite($siteId, $label);

        $this->redirect('/sites/subcfg?id=' . $siteId . '&label=' . urlencode($label));
        exit;
    }

    public function create(): void
    {
        $this->requireAuth();

        $siteId = (int)($_POST['site_id'] ?? 0);
        $label  = (string)($_POST['new_label'] ?? '');
        $label  = $this->normalizeLabel($label, false);

        if ($siteId <= 0) {
            $this->redirect('/sites');
            exit;
        }

        $pdo = DB::pdo();

        // если нет — вставляем дефолт
        $exists = $pdo->prepare("SELECT 1 FROM site_subdomain_configs WHERE site_id = ? AND label = ? LIMIT 1");
        $exists->execute([$siteId, $label]);

        if (!$exists->fetchColumn()) {
            $default = $this->loadDefaultCfg($pdo, $siteId);
            if (!isset($default['logo']))    $default['logo'] = 'assets/logo.png';
            if (!isset($default['favicon'])) $default['favicon'] = 'assets/favicon.png';

            $json = json_encode($default, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $ins = $pdo->prepare("INSERT INTO site_subdomain_configs (site_id, label, config_json) VALUES (?, ?, ?)");
            $ins->execute([$siteId, $label, $json]);
        }

        // fs + config.php
        $prov = new SubdomainProvisioner();
        $prov->ensureForSite($siteId, $label);

        $this->redirect('/sites/subcfg?id=' . $siteId . '&label=' . urlencode($label));
        exit;
    }

    public function delete(): void
    {
        $this->requireAuth();

        $siteId = (int)($_POST['site_id'] ?? 0);
        $label  = (string)($_POST['label'] ?? '');
        $label  = $this->normalizeLabel($label, true);

        if ($siteId <= 0) {
            $this->redirect('/sites');
            exit;
        }

        if ($label === '_default') {
            $this->redirect('/sites/subcfg?id=' . $siteId . '&label=_default');
            exit;
        }

        $pdo = DB::pdo();
        $del = $pdo->prepare("DELETE FROM site_subdomain_configs WHERE site_id = ? AND label = ? LIMIT 1");
        $del->execute([$siteId, $label]);

        // опционально удалить папку
        if (isset($_POST['delete_folder'])) {
            $site = $this->loadSite($siteId);
            $buildRel = (string)($site['build_path'] ?? '');
            if ($buildRel !== '') {
                $buildAbs = $this->toAbsPath($buildRel);
                $dir = rtrim($buildAbs, '/\\') . '/subs/' . $label;
                $this->rmDir($dir);
            }
        }

        $this->redirect('/sites/subcfg?id=' . $siteId . '&label=_default');
        exit;
    }

    public function regenAll(): void
    {
        $this->requireAuth();

        $siteId = (int)($_POST['site_id'] ?? 0);
        if ($siteId <= 0) {
            $this->redirect('/sites');
            exit;
        }

        $site = $this->loadSite($siteId);
        if (!$site || ($site['template'] ?? '') !== 'template-multy') {
            $this->redirect('/sites');
            exit;
        }

        $pdo = DB::pdo();
        $labels = $this->listLabels($pdo, $siteId);
        $prov = new SubdomainProvisioner();

        foreach ($labels as $lb) {
            $prov->ensureForSite($siteId, $lb);
        }

        $this->redirect('/sites/subcfg?id=' . $siteId . '&label=_default');
        exit;
    }

    // ===== helpers =====

    private function loadSite(int $siteId): ?array
    {
        $pdo = DB::pdo();
        $st = $pdo->prepare("SELECT * FROM sites WHERE id = ? LIMIT 1");
        $st->execute([$siteId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    // ВАЖНО: всегда возвращаем _default + остальные
    private function listLabels(PDO $pdo, int $siteId): array
    {
        $out = ['_default'];

        $st = $pdo->prepare("SELECT label FROM site_subdomain_configs WHERE site_id = ? ORDER BY label");
        $st->execute([$siteId]);

        while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
            $lb = (string)$r['label'];
            if ($lb === '' || $lb === '_default') continue;
            $out[] = $lb;
        }

        return $out;
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

    private function upsertDefaultCfg(PDO $pdo, int $siteId, array $cfg): void
    {
        $json = json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $st = $pdo->prepare("SELECT 1 FROM site_default_configs WHERE site_id = ? LIMIT 1");
        $st->execute([$siteId]);

        if ($st->fetchColumn()) {
            $u = $pdo->prepare("UPDATE site_default_configs SET config_json = ? WHERE site_id = ?");
            $u->execute([$json, $siteId]);
        } else {
            $i = $pdo->prepare("INSERT INTO site_default_configs (site_id, config_json) VALUES (?, ?)");
            $i->execute([$siteId, $json]);
        }
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

    private function findUnusedTexts(array $site, string $label, array $cfg): array
    {
        $buildRel = (string)($site['build_path'] ?? '');
        if ($buildRel === '') return [];

        $buildAbs = $this->toAbsPath($buildRel);
        $textsDir = rtrim($buildAbs, '/\\') . '/subs/' . $label . '/texts';
        if (!is_dir($textsDir)) return [];

        $used = [];
        $pages = $cfg['pages'] ?? [];
        if (is_array($pages)) {
            foreach ($pages as $u => $p) {
                if (!is_array($p)) continue;
                $tf = (string)($p['text_file'] ?? '');
                $tf = basename(str_replace('\\', '/', $tf));
                if ($tf !== '') $used[$tf] = true;
            }
        }

        $unused = [];
        $items = @scandir($textsDir);
        if (!$items) return [];

        foreach ($items as $f) {
            if ($f === '.' || $f === '..') continue;
            if (!preg_match('~\.php$~i', $f)) continue;
            if (!isset($used[$f])) $unused[] = $f;
        }

        return $unused;
    }

    private function toAbsPath(string $rel): string
    {
        $rel = trim($rel);
        $rel = str_replace('\\', '/', $rel);
        $rel = ltrim($rel, '/');

        // поддержка legacy: "<storage_basename>/builds/..." и нового формата "builds/..."
        $storageBase = basename(rtrim(Paths::storage(''), "/\\"));
        if ($storageBase !== '' && strpos($rel, $storageBase . '/') === 0) {
            $rel = substr($rel, strlen($storageBase) + 1);
        }

        if (preg_match('~(^|/)\.\.(?:/|$)~', $rel)) {
            return Paths::storage('builds/invalid_path');
        }

        if (strpos($rel, 'builds/') !== 0) {
            return Paths::storage('builds/invalid_path');
        }

        $abs = Paths::storage($rel);
        return rtrim($abs, "/\\");
    }

    private function rmDir(string $dir): void
    {
        if (!is_dir($dir)) return;

        $items = scandir($dir);
        foreach ($items as $name) {
            if ($name === '.' || $name === '..') continue;
            $p = $dir . '/' . $name;
            if (is_dir($p)) $this->rmDir($p);
            else @unlink($p);
        }
        @rmdir($dir);
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

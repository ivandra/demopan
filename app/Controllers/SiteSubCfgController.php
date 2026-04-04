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

        $structure = new SiteStructure();
		$resolver  = new SiteConfigResolver();

		$labels = $resolver->listLabels($siteId, true);

		$label = $structure->normalizeLabel((string)($_GET['label'] ?? '_default'), true);
		if (!in_array($label, $labels, true)) {
			$label = $structure->rootLabel();
		}

        // гарантируем fs+config.php для выбранного
        $prov = new SubdomainProvisioner();
        $prov->ensureForSite($siteId, $label);

        $cfg = $resolver->getResolvedConfig($siteId, $label);

        if (!is_array($cfg)) {
            $cfg = [];
        }

        require_once Paths::appRoot() . '/app/Services/PartnerSubIdService.php';
        $partnerService = new PartnerSubIdService();
        $partnerSubId = $partnerService->buildSubId((string)($site['domain'] ?? ''), $label);

        $rootCfg = $resolver->getDefaultConfig($siteId);
        foreach (['partner_override_url', 'internal_reg_url', 'base_new_url', 'base_second_url'] as $field) {
            $value = trim((string)($cfg[$field] ?? ''));

            if ($value === '' && trim((string)($rootCfg[$field] ?? '')) !== '') {
                $value = (string)$rootCfg[$field];
            }

            if ($value !== '') {
                $cfg[$field] = $partnerService->applySubIdToUrl(
                    $value,
                    (string)($site['domain'] ?? ''),
                    $label
                );
            }
        }

        $unused = $this->findUnusedTexts($site, $label, $cfg);

        $this->view('sites/subcfg', [
            'site'   => $site,
            'siteId' => $siteId,
            'label'  => $label,
            'labels' => $labels,
            'cfg'    => $cfg,
            'unused' => $unused,
            'partnerSubId' => $partnerSubId,
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
        $site = $this->loadSite($siteId);
		
		$structure = new SiteStructure();
		$resolver  = new SiteConfigResolver();

        $cfg = $resolver->isRootLabel($label)
			? $resolver->getDefaultConfig($siteId)
			: $resolver->getSubConfig($siteId, $label);

        // обновляем только параметры (pages не трогаем тут)
        $cfg['title']       = (string)($_POST['title'] ?? ($cfg['title'] ?? ''));
        $cfg['h1']          = (string)($_POST['h1'] ?? ($cfg['h1'] ?? ''));
        $cfg['description'] = (string)($_POST['description'] ?? ($cfg['description'] ?? ''));
        $cfg['keywords']    = (string)($_POST['keywords'] ?? ($cfg['keywords'] ?? ''));

        require_once Paths::appRoot() . '/app/Services/PartnerSubIdService.php';
        $partnerService = new PartnerSubIdService();
        $rootCfg = $resolver->getDefaultConfig($siteId);

        $cfg['promolink'] = (string)($_POST['promolink'] ?? ($cfg['promolink'] ?? '/reg'));

        $postedPartnerUrls = [
            'partner_override_url' => (string)($_POST['partner_override_url'] ?? ($cfg['partner_override_url'] ?? '')),
            'internal_reg_url'     => (string)($_POST['internal_reg_url'] ?? ($cfg['internal_reg_url'] ?? '')),
            'base_new_url'         => (string)($_POST['base_new_url'] ?? ($cfg['base_new_url'] ?? '')),
            'base_second_url'      => (string)($_POST['base_second_url'] ?? ($cfg['base_second_url'] ?? '')),
        ];

        foreach ($postedPartnerUrls as $field => $value) {
            if (trim((string)$value) === '' && trim((string)($rootCfg[$field] ?? '')) !== '') {
                $value = (string)$rootCfg[$field];
            }
            $cfg[$field] = $partnerService->applySubIdToUrl((string)$value, (string)($site['domain'] ?? ''), $label);
        }

        $cfg['redirect_enabled']     = (int)(isset($_POST['redirect_enabled']) ? 1 : 0);

        $cfg['logo']    = (string)($_POST['logo'] ?? ($cfg['logo'] ?? 'assets/logo.png'));
        $cfg['favicon'] = (string)($_POST['favicon'] ?? ($cfg['favicon'] ?? 'assets/favicon.png'));

        if ($resolver->isRootLabel($label)) {
            $cfg['label'] = '_default';
            $cfg['domain'] = (string)($cfg['domain'] ?? ($site['domain'] ?? ''));

            $resolver->upsertDefaultConfig($siteId, $cfg);
            $resolver->saveLegacySiteConfig($siteId, $cfg);
            $resolver->upsertSubConfig($siteId, '_default', $cfg);
        } else {
            $cfg['label'] = $label;
            $cfg['domain'] = (string)($cfg['domain'] ?? ($site['domain'] ?? ''));
            $resolver->upsertSubConfig($siteId, $label, $cfg);
        }

        // fs + config.php
        $prov = new SubdomainProvisioner();
        $prov->ensureForSite($siteId, $label);

        if (!empty($_POST['copy_to_all_labels'])) {
            $allLabels = $resolver->listLabels($siteId, true);

            foreach ($allLabels as $targetLabel) {
                $targetCfg = $resolver->getResolvedConfig($siteId, $targetLabel);

                foreach (['title','h1','description','keywords','promolink','redirect_enabled','logo','favicon'] as $field) {
                    $targetCfg[$field] = $cfg[$field] ?? ($targetCfg[$field] ?? '');
                }

                foreach (['partner_override_url', 'internal_reg_url', 'base_new_url', 'base_second_url'] as $field) {
                    $sourceValue = (string)($cfg[$field] ?? '');

                    if (trim($sourceValue) === '' && trim((string)($rootCfg[$field] ?? '')) !== '') {
                        $sourceValue = (string)$rootCfg[$field];
                    }

                    $targetCfg[$field] = $partnerService->applySubIdToUrl(
                        $sourceValue,
                        (string)($site['domain'] ?? ''),
                        $targetLabel
                    );
                }

                $targetCfg['label'] = $targetLabel;
                $targetCfg['domain'] = (string)($targetCfg['domain'] ?? ($site['domain'] ?? ''));

                if ($resolver->isRootLabel($targetLabel)) {
                    $targetCfg['label'] = '_default';
                    $resolver->upsertDefaultConfig($siteId, $targetCfg);
                    $resolver->saveLegacySiteConfig($siteId, $targetCfg);
                    $resolver->upsertSubConfig($siteId, '_default', $targetCfg);
                } else {
                    $resolver->upsertSubConfig($siteId, $targetLabel, $targetCfg);
                }

                $prov->ensureForSite($siteId, $targetLabel);
            }

            $this->flash('success', 'Настройки SEO и ссылок сохранены и скопированы на все поддомены сайта с уникальными sub_id.');
        } else {
            $this->flash('success', 'Контент и SEO сохранены. Партнерские URL пересчитаны с sub_id для текущего label.');
        }
        (new PublishDirtyService())->markDirty($siteId, 'Изменены config, SEO или ссылки. Выгрузите актуальные данные на VPS.');
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

        $resolver  = new SiteConfigResolver();
        $default = $resolver->getDefaultConfig($siteId);
        if (!isset($default['logo']))    $default['logo'] = 'assets/logo.png';
        if (!isset($default['favicon'])) $default['favicon'] = 'assets/favicon.png';

        $resolver->ensureSubConfigExists($siteId, $label, $default);

        $prov = new SubdomainProvisioner();
        $prov->ensureForSite($siteId, $label);

        $this->flash('success', 'Поддомен создан.');
        (new PublishDirtyService())->markDirty($siteId, 'Изменена структура поддоменов. Выгрузите актуальные данные на VPS.');
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

        $this->flash('success', 'Поддомен удален из конфигов.');
        (new PublishDirtyService())->markDirty($siteId, 'Изменена структура поддоменов. Выгрузите актуальные данные на VPS.');
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
        if (!$site) {
			$this->redirect('/sites');
			exit;
		}

		$structure = new SiteStructure();
		$resolver  = new SiteConfigResolver();
		$labels = $resolver->listLabels($siteId, true);
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

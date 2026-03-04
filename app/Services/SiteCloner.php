<?php

class SiteCloner
{
    /**
     * Клонирует сайт:
     * - sites row (domain меняется)
     * - site_configs / site_default_configs / site_subdomain_configs (domain меняется)
     * - site_subdomains (только выбранные labels, fqdn пересобирается)
     * - build директория (копируется и чистится subs/)
     *
     * opts:
     *  - same_vps (0/1): копировать fastpanel_server_id и vps_ip
     *  - reset_state (0/1): сбросить provisioning статусы (FP/FTP/Files/SSL/DNS/Domain purchase)
     */
    public function cloneSite(int $srcSiteId, string $newDomain, array $labelsToInclude, array $opts = []): int
    {
        $newDomain = trim($newDomain);
        if ($newDomain === '') {
            throw new RuntimeException("New domain is empty");
        }

        $opts = array_merge([
            'same_vps' => 1,
            'reset_state' => 1,
        ], $opts);

        $srcSite = $this->loadSite($srcSiteId);
        if (!$srcSite) {
            throw new RuntimeException("Source site not found: {$srcSiteId}");
        }

        $pdo = DB::pdo();
        $pdo->beginTransaction();

        try {
            // Нормализуем labels и гарантируем _default
            $labelsToInclude = $this->normalizeLabels($labelsToInclude);
            if (!in_array('_default', $labelsToInclude, true)) {
                $labelsToInclude[] = '_default';
            }

            // 1) вставляем новую строку sites
            $newSiteId = $this->insertClonedSiteRow($pdo, $srcSite, $newDomain, (int)$opts['same_vps']);

            // 2) site_configs (если есть) — копируем с заменой domain
            $this->cloneSiteConfigs($pdo, $srcSiteId, $newSiteId, $newDomain);

            // 3) site_default_configs — копируем с заменой domain
            $defaultCfg = $this->loadDefaultCfg($pdo, $srcSiteId);
            if (!empty($defaultCfg)) {
                $defaultCfg['domain'] = $newDomain;
                $this->upsertDefaultCfg($pdo, $newSiteId, $defaultCfg);
            }

            // 4) site_subdomain_configs — копируем только выбранные labels
            foreach ($labelsToInclude as $lb) {
                $cfg = $this->loadSubCfg($pdo, $srcSiteId, $lb);
                if ($cfg === null) continue;

                $cfg['domain'] = $newDomain;
                $cfg['label'] = $lb;

                $this->upsertSubCfg($pdo, $newSiteId, $lb, $cfg);
            }

            // 5) site_subdomains — копируем только выбранные labels
            $this->cloneSiteSubdomains($pdo, $srcSiteId, $newSiteId, $newDomain, $labelsToInclude, (int)$opts['reset_state']);

            // 6) build директория
            $srcBuildAbs = $this->resolveBuildAbs($srcSite);

            $newBuildRel = 'builds/site_' . $newSiteId;
            $newBuildAbs = rtrim(Paths::storage($newBuildRel), "/\\");
            Paths::ensureDir($newBuildAbs);

            if (is_dir($srcBuildAbs)) {
                $this->copyDir($srcBuildAbs, $newBuildAbs);
            }

            // 7) чистим subs (оставляем выбранные)
            $subsDir = rtrim($newBuildAbs, "/\\") . '/subs';
            if (is_dir($subsDir)) {
                $this->pruneSubs($subsDir, $labelsToInclude);
            }

            // 8) фиксируем build_path
            $upd = $pdo->prepare("UPDATE sites SET build_path = ? WHERE id = ? LIMIT 1");
            $upd->execute([$newBuildRel, $newSiteId]);

            // 9) сброс provisioning state (если включено)
            if ((int)$opts['reset_state'] === 1) {
                $this->resetProvisioningState($pdo, $newSiteId);
            }

            $pdo->commit();

            // 10) перегенерить файловые config.php под новый домен/labels
            // (после commit, чтобы ensureForSite мог читать БД)
            $prov = new SubdomainProvisioner();
            $prov->ensureForSite($newSiteId, '_default');
            foreach ($labelsToInclude as $lb) {
                if ($lb === '_default') continue;
                $prov->ensureForSite($newSiteId, $lb);
            }

            return $newSiteId;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    // ---------------- DB helpers ----------------

    private function loadSite(int $id): ?array
    {
        $pdo = DB::pdo();
        $st = $pdo->prepare("SELECT * FROM sites WHERE id = ? LIMIT 1");
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function insertClonedSiteRow(PDO $pdo, array $srcSite, string $newDomain, int $sameVps): int
    {
        $row = $srcSite;

        unset($row['id']);

        // обязательные поля
        $row['domain'] = $newDomain;

        // build_path ставим потом
        $row['build_path'] = '';

        // config_path — чтобы не конфликтовать, генерим новый
        // (у тебя встречается и "configs/..." и "storage/configs/..." — оставим простой вариант)
        $row['config_path'] = 'configs/site_' . time() . '_' . random_int(1000, 9999) . '.json';

        if ($sameVps !== 1) {
            $row['fastpanel_server_id'] = null;
            $row['vps_ip'] = null;
        }

        // Собираем INSERT по реальным колонкам
        $cols = array_keys($row);
        $cols = array_values(array_filter($cols, fn($c) => $c !== ''));

        $placeholders = implode(',', array_fill(0, count($cols), '?'));
        $sql = "INSERT INTO sites (" . implode(',', $cols) . ") VALUES ({$placeholders})";

        $vals = [];
        foreach ($cols as $c) {
            $vals[] = $row[$c];
        }

        $ins = $pdo->prepare($sql);
        $ins->execute($vals);

        return (int)$pdo->lastInsertId();
    }

    private function cloneSiteConfigs(PDO $pdo, int $srcSiteId, int $newSiteId, string $newDomain): void
    {
        // site_configs — основной источник настроек в текущем коде
        $st = $pdo->prepare("SELECT json FROM site_configs WHERE site_id = ? LIMIT 1");
        $st->execute([$srcSiteId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) return;

        $cfg = json_decode((string)($row['json'] ?? '[]'), true);
        if (!is_array($cfg)) $cfg = [];
        $cfg['domain'] = $newDomain;

        $json = json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $ins = $pdo->prepare("INSERT INTO site_configs (site_id, json) VALUES (?, ?)");
        $ins->execute([$newSiteId, $json]);
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

    private function cloneSiteSubdomains(PDO $pdo, int $srcSiteId, int $newSiteId, string $newDomain, array $labelsToInclude, int $resetState): void
    {
        $keep = [];
        foreach ($labelsToInclude as $lb) $keep[$lb] = true;
        $keep['_default'] = true;

        $st = $pdo->prepare("SELECT * FROM site_subdomains WHERE site_id = ? ORDER BY id ASC");
        $st->execute([$srcSiteId]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $r) {
            $lb = (string)($r['label'] ?? '');
            if ($lb === '') continue;
            if (!isset($keep[$lb])) continue;

            $newFqdn = $lb . '.' . $newDomain;

            $fromCatalog = (int)($r['from_catalog'] ?? 0);
            $enabled = (int)($r['enabled'] ?? 1);

            // статусы на новом домене обычно надо сбрасывать
            $dnsStatus = $resetState ? null : ($r['dns_status'] ?? null);
            $sslStatus = $resetState ? null : ($r['ssl_status'] ?? null);
            $folderStatus = $resetState ? null : ($r['folder_status'] ?? null);
            $folderError = $resetState ? null : ($r['folder_error'] ?? null);
            $folderUpdatedAt = $resetState ? null : ($r['folder_updated_at'] ?? null);
            $lastError = $resetState ? null : ($r['last_error'] ?? null);

            $ins = $pdo->prepare(
                "INSERT INTO site_subdomains
                 (site_id, label, fqdn, from_catalog, enabled, dns_status, ssl_status, folder_status, folder_error, folder_updated_at, last_error)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $ins->execute([
                $newSiteId,
                $lb,
                $newFqdn,
                $fromCatalog,
                $enabled,
                $dnsStatus,
                $sslStatus,
                $folderStatus,
                $folderError,
                $folderUpdatedAt,
                $lastError,
            ]);
        }
    }

    private function resetProvisioningState(PDO $pdo, int $siteId): void
    {
        // Сбрасываем всё, что привязано к старому домену/FP состоянию.
        // Это безопасно: дальше ты запускаешь Build/Deploy/SSL уже как для нового сайта.
        $sql = "UPDATE sites SET
            fp_site_created = 0,
            fp_site_id = NULL,
            fp_index_dir = NULL,
            fp_ftp_ready = 0,
            fp_ftp_user = NULL,
            fp_ftp_pass_enc = NULL,
            fp_ftp_id = NULL,
            fp_ftp_last_ok = NULL,
            fp_files_ready = 0,
            fp_files_last_ok = NULL,

            ssl_checked_at = NULL,
            ssl_last_ok = NULL,
            ssl_ready = 0,
            ssl_has_cert = 0,
            ssl_cert_id = NULL,
            ssl_error = NULL,

            domain_registrar = NULL,
            registrar_account_id = NULL,
            registrar_contact_id = NULL,
            domain_purchase_status = 'none',
            domain_price_usd = NULL,
            domain_checked_at = NULL,
            domain_registered_at = NULL,
            domain_purchase_error = NULL,

            dns_status = 'none',
            dns_applied_at = NULL,
            dns_error = NULL
        WHERE id = ? LIMIT 1";

        $st = $pdo->prepare($sql);
        $st->execute([$siteId]);
    }

    // ---------------- FS helpers ----------------

    private function resolveBuildAbs(array $site): string
    {
        $buildRel = (string)($site['build_path'] ?? '');
        $buildRel = trim($buildRel);

        if ($buildRel === '') {
            $id = (int)($site['id'] ?? 0);
            $buildRel = 'builds/site_' . $id;
        }

        $buildRel = str_replace('\\', '/', $buildRel);
        $buildRel = ltrim($buildRel, '/');

        $storageBase = basename(rtrim(Paths::storage(''), "/\\"));
        if ($storageBase !== '' && strpos($buildRel, $storageBase . '/') === 0) {
            $buildRel = substr($buildRel, strlen($storageBase) + 1);
        }

        if (preg_match('~(^|/)\.\.(?:/|$)~', $buildRel)) {
            return Paths::storage('builds/invalid_path');
        }
        if (strpos($buildRel, 'builds/') !== 0) {
            return Paths::storage('builds/invalid_path');
        }

        return rtrim(Paths::storage($buildRel), "/\\");
    }

    private function copyDir(string $src, string $dst): void
    {
        Paths::ensureDir($dst);

        $items = scandir($src);
        foreach ($items as $name) {
            if ($name === '.' || $name === '..') continue;

            $from = rtrim($src, "/\\") . '/' . $name;
            $to   = rtrim($dst, "/\\") . '/' . $name;

            if (is_dir($from)) {
                $this->copyDir($from, $to);
            } else {
                @copy($from, $to);
            }
        }
    }

    private function pruneSubs(string $subsDir, array $labelsToKeep): void
    {
        $keep = [];
        foreach ($labelsToKeep as $lb) {
            $keep[$lb] = true;
        }
        $keep['_default'] = true;

        $items = scandir($subsDir);
        foreach ($items as $name) {
            if ($name === '.' || $name === '..') continue;

            $p = rtrim($subsDir, "/\\") . '/' . $name;
            if (!is_dir($p)) continue;

            if (!isset($keep[$name])) {
                $this->rmDir($p);
            }
        }
    }

    private function rmDir(string $dir): void
    {
        if (!is_dir($dir)) return;

        $items = scandir($dir);
        foreach ($items as $name) {
            if ($name === '.' || $name === '..') continue;
            $p = rtrim($dir, "/\\") . '/' . $name;
            if (is_dir($p)) $this->rmDir($p);
            else @unlink($p);
        }
        @rmdir($dir);
    }

    private function normalizeLabels(array $labels): array
    {
        $out = [];
        foreach ($labels as $lb) {
            $lb = strtolower(trim((string)$lb));
            $lb = preg_replace('~\s+~', '', $lb);
            if ($lb === '') continue;

            if ($lb === '_default') {
                $out['_default'] = true;
                continue;
            }

            $lb = preg_replace('~[^a-z0-9\-]+~', '', $lb);
            $lb = trim($lb, '-');
            if ($lb === '') continue;

            $out[$lb] = true;
        }
        return array_keys($out);
    }
}
<?php

class SiteCloner
{
    /**
     * Клонирует сайт целиком (DB + build папка), но переносит только выбранные labels.
     * Возвращает новый site_id.
     */
    public function cloneSite(int $srcSiteId, string $newDomain, array $labelsToInclude): int
    {
        $newDomain = trim($newDomain);
        if ($newDomain === '') {
            throw new RuntimeException("New domain is empty");
        }

        $srcSite = $this->loadSite($srcSiteId);
        if (!$srcSite) {
            throw new RuntimeException("Source site not found: {$srcSiteId}");
        }

        $pdo = DB::pdo();
        $pdo->beginTransaction();

        try {
            // 1) копируем row sites динамически (без знания схемы)
            $newSiteId = $this->insertClonedSiteRow($pdo, $srcSite, $newDomain);

            // 2) копируем default config
            $defaultCfg = $this->loadDefaultCfg($pdo, $srcSiteId);
            $defaultCfg['domain'] = $newDomain;
            $this->upsertDefaultCfg($pdo, $newSiteId, $defaultCfg);

            // 3) копируем sub configs только по выбранным labels (кроме _default)
            $labelsToInclude = $this->normalizeLabels($labelsToInclude);

            foreach ($labelsToInclude as $lb) {
                if ($lb === '_default') continue;

                $cfg = $this->loadSubCfg($pdo, $srcSiteId, $lb);
                if ($cfg === null) continue;

                $cfg['domain'] = $newDomain;
                $this->upsertSubCfg($pdo, $newSiteId, $lb, $cfg);
            }

            // 4) копируем build директорию (если есть)
            $srcBuildAbs = $this->resolveBuildAbs($srcSite);
            $newBuildRel = 'builds/site_' . $newSiteId;
            $newBuildAbs = rtrim(Paths::storage($newBuildRel), "/\\");

            Paths::ensureDir($newBuildAbs);

            if (is_dir($srcBuildAbs)) {
                $this->copyDir($srcBuildAbs, $newBuildAbs);
            }

            // 5) удаляем из нового build-а невыбранные subs (кроме _default)
            $subsDir = rtrim($newBuildAbs, "/\\") . '/subs';
            if (is_dir($subsDir)) {
                $this->pruneSubs($subsDir, $labelsToInclude);
            }

            // 6) фиксируем build_path у нового сайта (на случай если в исходнике было другое)
            $upd = $pdo->prepare("UPDATE sites SET build_path = ? WHERE id = ? LIMIT 1");
            $upd->execute([$newBuildRel, $newSiteId]);

            $pdo->commit();

            // 7) перегенерим config.php на FS под новый домен и выбранные labels
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

    private function insertClonedSiteRow(PDO $pdo, array $srcSite, string $newDomain): int
    {
        $row = $srcSite;

        unset($row['id']);
        // обязательные поля
        $row['domain'] = $newDomain;

        // build_path ставим сразу в новый
        $row['build_path'] = ''; // пока пусто, обновим после insert

        // соберем insert по всем колонкам, что реально есть в таблице
        $cols = array_keys($row);
        $cols = array_values(array_filter($cols, function ($c) {
            return $c !== '';
        }));

        // на всякий случай убираем created_at/updated_at если они not null и ставятся триггерами,
        // но если у вас их нет — не мешает.
        // (не удаляем принудительно: оставляем как есть)

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

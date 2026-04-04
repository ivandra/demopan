<?php

class YandexSearchApiService
{
    private const LOG_FILE = 'logs/yandex_search_api.log';
    private const SETTINGS_TABLE = 'search_api_settings';
    private const CRON_TABLE = 'search_api_cron_state';
    private const DEFAULT_LIMIT_SITES = 25;

    public function getSettings(): array
    {
        $this->ensureSettingsTable();

        $row = DB::withReconnect(function (PDO $pdo) {
            $st = $pdo->prepare('SELECT * FROM ' . self::SETTINGS_TABLE . ' WHERE id=1 LIMIT 1');
            $st->execute();
            return $st->fetch(PDO::FETCH_ASSOC) ?: null;
        });

        if (!$row) {
            return [
                'id' => 1,
                'enabled' => 0,
                'endpoint_xml' => 'https://xmlstock.com/yandexlive/xml/',
                'endpoint_json' => 'https://xmlstock.com/yandexlive/json/',
                'user' => '',
                'key' => '',
                'query_interval_minutes' => 30,
                'recheck_after_detect_minutes' => 1440,
                'max_pages_per_run' => 1,
                'created_at' => null,
                'updated_at' => null,
            ];
        }

        return [
            'id' => (int)($row['id'] ?? 1),
            'enabled' => (int)($row['enabled'] ?? 0),
            'endpoint_xml' => trim((string)($row['endpoint_xml'] ?? 'https://xmlstock.com/yandexlive/xml/')),
            'endpoint_json' => trim((string)($row['endpoint_json'] ?? 'https://xmlstock.com/yandexlive/json/')),
            'user' => trim((string)($row['user'] ?? '')),
            'key' => trim((string)($row['key'] ?? '')),
            'query_interval_minutes' => (int)($row['query_interval_minutes'] ?? 30),
            'recheck_after_detect_minutes' => (int)($row['recheck_after_detect_minutes'] ?? 1440),
            'max_pages_per_run' => (int)($row['max_pages_per_run'] ?? 1),
            'created_at' => (string)($row['created_at'] ?? ''),
            'updated_at' => (string)($row['updated_at'] ?? ''),
        ];
    }

    public function saveSettings(array $data): void
    {
        $this->ensureSettingsTable();

        $enabled = !empty($data['enabled']) ? 1 : 0;
        $endpointXml = trim((string)($data['endpoint_xml'] ?? 'https://xmlstock.com/yandexlive/xml/'));
        $endpointJson = trim((string)($data['endpoint_json'] ?? 'https://xmlstock.com/yandexlive/json/'));
        $user = trim((string)($data['user'] ?? ''));
        $key = trim((string)($data['key'] ?? ''));
        $queryIntervalMinutes = max(1, (int)($data['query_interval_minutes'] ?? 30));
        $recheckAfterDetectMinutes = max(60, (int)($data['recheck_after_detect_minutes'] ?? 1440));
        $maxPagesPerRun = max(1, min(10, (int)($data['max_pages_per_run'] ?? 1)));

        DB::withReconnect(function (PDO $pdo) use (
            $enabled,
            $endpointXml,
            $endpointJson,
            $user,
            $key,
            $queryIntervalMinutes,
            $recheckAfterDetectMinutes,
            $maxPagesPerRun
        ) {
            $st = $pdo->prepare("
                INSERT INTO " . self::SETTINGS_TABLE . "
                    (id, enabled, endpoint_xml, endpoint_json, `user`, `key`, query_interval_minutes, recheck_after_detect_minutes, max_pages_per_run, updated_at)
                VALUES
                    (1, :enabled, :endpoint_xml, :endpoint_json, :user, :key, :query_interval_minutes, :recheck_after_detect_minutes, :max_pages_per_run, NOW())
                ON DUPLICATE KEY UPDATE
                    enabled = VALUES(enabled),
                    endpoint_xml = VALUES(endpoint_xml),
                    endpoint_json = VALUES(endpoint_json),
                    `user` = VALUES(`user`),
                    `key` = VALUES(`key`),
                    query_interval_minutes = VALUES(query_interval_minutes),
                    recheck_after_detect_minutes = VALUES(recheck_after_detect_minutes),
                    max_pages_per_run = VALUES(max_pages_per_run),
                    updated_at = NOW()
            ");
            $st->execute([
                ':enabled' => $enabled,
                ':endpoint_xml' => $endpointXml,
                ':endpoint_json' => $endpointJson,
                ':user' => $user,
                ':key' => $key,
                ':query_interval_minutes' => $queryIntervalMinutes,
                ':recheck_after_detect_minutes' => $recheckAfterDetectMinutes,
                ':max_pages_per_run' => $maxPagesPerRun,
            ]);
        });
    }

    public function isConfigured(): bool
    {
        $s = $this->getSettings();
        return !empty($s['enabled']) && $s['user'] !== '' && $s['key'] !== '';
    }

    public function getStatusesForSite(int $siteId, array $desiredHosts = []): array
    {
        $this->ensureHostColumns();

        $rows = DB::withReconnect(function (PDO $pdo) use ($siteId) {
            $st = $pdo->prepare('SELECT * FROM webmaster_hosts WHERE site_id=? ORDER BY label ASC');
            $st->execute([$siteId]);
            return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        });

        $map = [];
        foreach ($rows as $row) {
            $map[(string)($row['label'] ?? '')] = $row;
        }

        foreach ($desiredHosts as $row) {
            $label = (string)($row['label'] ?? '');
            if (!isset($map[$label])) {
                $map[$label] = [
                    'label' => $label,
                    'host_url' => (string)($row['host_url'] ?? ''),
                    'search_api_status' => 'idle',
                    'search_api_last_checked_at' => '',
                    'search_api_indexed_at' => '',
                    'search_api_error' => '',
                    'search_api_result_count' => 0,
                    'search_api_last_query' => '',
                    'search_api_next_check_at' => '',
                    'search_api_found_hosts_json' => '[]',
                    'search_api_found_urls_json' => '[]',
                ];
            }
        }

        return $map;
    }

    public function getCronState(): array
    {
        $this->ensureCronStateTable();

        return DB::withReconnect(function (PDO $pdo) {
            $st = $pdo->prepare('SELECT * FROM ' . self::CRON_TABLE . ' WHERE id=1 LIMIT 1');
            $st->execute();
            return $st->fetch(PDO::FETCH_ASSOC) ?: [];
        });
    }

    public function getLogTailForSite(int $siteId, int $limit = 120): array
    {
        $limit = max(1, min($limit, 400));
        $site = $this->loadSite($siteId);
        $domain = trim((string)($site['domain'] ?? ''));

        $needles = ['site=' . $siteId];
        if ($domain !== '') {
            $needles[] = $domain;
        }

        $desired = (new YandexWebmasterService())->getDesiredHostsForSite($siteId);
        foreach ($desired as $row) {
            $hostUrl = trim((string)($row['host_url'] ?? ''));
            if ($hostUrl !== '') {
                $needles[] = $this->extractHostFromUrl($hostUrl);
            }
        }

        $file = Paths::storage(self::LOG_FILE);
        if (!is_file($file)) {
            return [];
        }

        $lines = @file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!is_array($lines)) {
            return [];
        }

        $filtered = [];
        foreach ($lines as $line) {
            foreach ($needles as $needle) {
                if ($needle !== '' && stripos((string)$line, (string)$needle) !== false) {
                    $filtered[] = (string)$line;
                    break;
                }
            }
        }

        if (!$filtered) {
            $filtered = $lines;
        }

        return array_slice($filtered, -$limit);
    }

    public function runManualCheck(int $siteId, string $targetLabel = 'ALL'): array
    {
        $site = $this->loadSite($siteId);
        if (!$site) {
            throw new RuntimeException('site not found');
        }
        if (!$this->isConfigured()) {
            throw new RuntimeException('XMLStock Search API is not configured');
        }

        $desiredHosts = (new YandexWebmasterService())->getDesiredHostsForSite($siteId);
        $results = [];

        foreach ($desiredHosts as $row) {
            $label = (string)($row['label'] ?? '');
            $hostUrl = (string)($row['host_url'] ?? '');
            if ($targetLabel !== 'ALL' && $targetLabel !== $label) {
                continue;
            }

            if (!$this->shouldCheckHost($siteId, $label, $hostUrl)) {
                $results[] = [
                    'label' => $label,
                    'host' => $this->extractHostFromUrl($hostUrl),
                    'host_url' => $hostUrl,
                    'query' => 'site:' . $hostUrl,
                    'indexed' => true,
                    'status' => 'skipped',
                    'error' => '',
                    'found_hosts' => [],
                    'found_urls' => [],
                    'next_check_at' => '',
                    'skip_reason' => 'already_indexed_or_waiting',
                ];
                continue;
            }

            $results[] = $this->checkHost($siteId, $label, $hostUrl, true);
        }

        return $results;
    }

    public function runCron(int $limitSites = self::DEFAULT_LIMIT_SITES): array
    {
        $summary = [
            'checked_sites' => 0,
            'checked_hosts' => 0,
            'detected_hosts' => 0,
            'skipped_hosts' => 0,
            'errors' => [],
        ];

        if (!$this->isConfigured()) {
            $summary['errors'][] = ['error' => 'XMLStock Search API is not configured'];
            $this->saveCronState(false, $summary, 'XMLStock Search API is not configured');
            return $summary;
        }

        $rows = DB::withReconnect(function (PDO $pdo) use ($limitSites) {
            $st = $pdo->query("SELECT id FROM sites WHERE status='active' ORDER BY id ASC LIMIT " . (int)$limitSites);
            return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        });

        foreach ($rows as $row) {
            $siteId = (int)($row['id'] ?? 0);
            if ($siteId <= 0) {
                continue;
            }

            try {
                $desiredHosts = (new YandexWebmasterService())->getDesiredHostsForSite($siteId);
                $summary['checked_sites']++;

                foreach ($desiredHosts as $hostRow) {
                    $label = (string)($hostRow['label'] ?? '');
                    $hostUrl = (string)($hostRow['host_url'] ?? '');

                    if (!$this->shouldCheckHost($siteId, $label, $hostUrl)) {
                        $summary['skipped_hosts']++;
                        continue;
                    }

                    $res = $this->checkHost($siteId, $label, $hostUrl, false);
                    $summary['checked_hosts']++;

                    if (!empty($res['indexed'])) {
                        $summary['detected_hosts']++;
                    }

                    usleep(200000);
                }
            } catch (Throwable $e) {
                $summary['errors'][] = [
                    'site_id' => $siteId,
                    'error' => $e->getMessage(),
                ];
                $this->appendLog('CRON_ERR site=' . $siteId . ' :: ' . $e->getMessage());
            }
        }

        $this->saveCronState(empty($summary['errors']), $summary, '');
        return $summary;
    }

    private function checkHost(int $siteId, string $label, string $hostUrl, bool $force): array
    {
        $settings = $this->getSettings();
        $host = $this->extractHostFromUrl($hostUrl);
        if ($host === '') {
            throw new RuntimeException('empty host');
        }

        $query = 'site:' . $hostUrl;
        $foundUrls = [];
        $foundHosts = [];
        $indexed = false;
        $error = '';

        $pages = max(1, (int)($settings['max_pages_per_run'] ?? 1));

        try {
            for ($page = 0; $page < $pages; $page++) {
                $xml = $this->requestXmlStock($query, $page, $settings);
                $urls = $this->extractUrlsFromXml($xml);
                $foundUrls = array_values(array_unique(array_merge($foundUrls, $urls)));
                $foundHosts = array_values(array_unique(array_merge($foundHosts, $this->collectHostsFromUrls($urls, $host))));

                if (!$urls) {
                    break;
                }
            }

            $indexed = !empty($foundHosts);
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }

        $prevRow = $this->getHostRow($siteId, $label);
        $wasIndexedBefore = !empty($prevRow['search_api_indexed_at']) || (string)($prevRow['yandex_index_status'] ?? '') === 'indexed';

        $status = $indexed ? 'indexed' : ($error !== '' ? 'error' : 'not_indexed');
        $indexedAt = $indexed ? date('Y-m-d H:i:s') : null;
        $nextCheckAt = $this->calcNextCheckAt($indexed, $settings, $force);

        $this->saveHostResult(
            $siteId,
            $label,
            $hostUrl,
            $query,
            $status,
            $indexedAt,
            $nextCheckAt,
            $error,
            $foundHosts,
            $foundUrls
        );

        if ($indexed && !$wasIndexedBefore) {
            $syncInfo = $this->maybeEnableRedirectAndSync($siteId, $label, $hostUrl);
            $this->sendDetectedTelegram(
                $siteId,
                $label,
                $hostUrl,
                $query,
                count($foundUrls),
                $syncInfo
            );
        }

        $this->appendLog(
            'CHECK site=' . $siteId
            . ' label=' . ($label === '' ? 'root' : $label)
            . ' host=' . $host
            . ' status=' . $status
            . ' urls=' . count($foundUrls)
            . ' next=' . $nextCheckAt
            . ($error !== '' ? ' error=' . $error : '')
        );

        return [
            'label' => $label,
            'host' => $host,
            'host_url' => $hostUrl,
            'query' => $query,
            'indexed' => $indexed,
            'status' => $status,
            'error' => $error,
            'found_hosts' => $foundHosts,
            'found_urls' => $foundUrls,
            'next_check_at' => $nextCheckAt,
        ];
    }

    public function resetHostStateForReindex(int $siteId, string $label, string $reason = 'manual_reindex'): void
    {
        $this->ensureHostColumns();

        $hostRow = $this->getHostRow($siteId, $label);
        $hostUrl = (string)($hostRow['host_url'] ?? '');
        $disableInfo = $this->maybeDisableRedirectAndSyncForReindex($siteId, $label, $hostUrl, $reason);

        DB::withReconnect(function (PDO $pdo) use ($siteId, $label) {
            $st = $pdo->prepare("
                UPDATE webmaster_hosts
                   SET search_api_status = 'idle',
                       search_api_indexed_at = NULL,
                       search_api_last_checked_at = NULL,
                       search_api_error = '',
                       search_api_result_count = 0,
                       search_api_last_query = '',
                       search_api_next_check_at = NOW(),
                       search_api_found_hosts_json = NULL,
                       search_api_found_urls_json = NULL,
                       updated_at = CURRENT_TIMESTAMP
                 WHERE site_id = ? AND label = ?
            ");
            $st->execute([$siteId, $label]);
        });

        $suffix = '';
        if (!empty($disableInfo['changed'])) {
            $suffix = ' redirect_disabled=1';
        } elseif (!empty($disableInfo['checked'])) {
            $suffix = ' redirect_disabled=0';
        }

        $this->appendLog('RESET site=' . $siteId . ' label=' . ($label === '' ? 'root' : $label) . ' reason=' . $reason . $suffix);
    }

    private function maybeDisableRedirectAndSyncForReindex(int $siteId, string $label, string $hostUrl, string $reason): array
    {
        require_once Paths::appRoot() . '/app/Services/SiteConfigResolver.php';
        require_once Paths::appRoot() . '/app/Services/SubdomainProvisioner.php';
        require_once Paths::appRoot() . '/app/Services/ConfigSyncService.php';

        $cfgLabel = ($label === '' ? '_default' : $label);
        $resolver = new SiteConfigResolver();
        $cfg = $resolver->getResolvedConfig($siteId, $cfgLabel);
        $wasEnabled = (int)($cfg['redirect_enabled'] ?? 0) === 1;

        if (!$wasEnabled) {
            return ['checked' => true, 'changed' => false, 'sync' => null];
        }

        $cfg['redirect_enabled'] = 0;
        if ($cfgLabel === '_default') {
            $cfg['label'] = '_default';
            $resolver->saveSiteDefaultConfig($siteId, $cfg);
            $resolver->saveLegacySiteConfig($siteId, $cfg);
            $resolver->saveSubdomainConfig($siteId, '_default', $cfg);
        } else {
            $cfg['label'] = $cfgLabel;
            $resolver->saveSubdomainConfig($siteId, $cfgLabel, $cfg);
        }

        try {
            $provisioner = new SubdomainProvisioner();
            $provisioner->ensureForSite($siteId, $cfgLabel);
        } catch (Throwable $e) {
        }

        $sync = null;
        $syncStatus = 'done';
        $syncContext = 'recrawl_pending:' . $reason;
        $syncError = '';

        try {
            $configSync = new ConfigSyncService();
            $sync = $configSync->syncLabelConfigFiles($siteId, $cfgLabel);
            $uploaded = implode(', ', (array)($sync['uploaded'] ?? []));
            if ($uploaded !== '') {
                $syncContext .= '; files=' . $uploaded;
            }
        } catch (Throwable $e) {
            $sync = ['ok' => false, 'error' => $e->getMessage()];
            $syncStatus = 'error';
            $syncError = $e->getMessage();
        }

        $this->markRedirectAutoDisabled($siteId, $label, $syncStatus, $syncContext, $syncError);
        $this->sendRedirectDisabledTelegram($siteId, $label, $hostUrl, $reason, $sync);

        return ['checked' => true, 'changed' => true, 'sync' => $sync];
    }

    public function resetSiteStateForReindex(int $siteId, array $labels, string $reason = 'manual_reindex'): void
    {
        foreach ($labels as $label) {
            $this->resetHostStateForReindex($siteId, (string)$label, $reason);
        }
    }

    private function getHostRow(int $siteId, string $label): ?array
    {
        return DB::withReconnect(function (PDO $pdo) use ($siteId, $label) {
            $st = $pdo->prepare('SELECT * FROM webmaster_hosts WHERE site_id=? AND label=? LIMIT 1');
            $st->execute([$siteId, $label]);
            return $st->fetch(PDO::FETCH_ASSOC) ?: null;
        });
    }

    private function shouldCheckHost(int $siteId, string $label, string $hostUrl): bool
    {
        $row = $this->getHostRow($siteId, $label);

        if (!$row) {
            return true;
        }

        if ((string)($row['yandex_index_status'] ?? '') === 'indexed') {
            return false;
        }

        if ($this->isRecrawlPendingRow($row)) {
            return false;
        }

        if (trim((string)($row['search_api_indexed_at'] ?? '')) !== '') {
            return false;
        }

        $nextCheckAt = trim((string)($row['search_api_next_check_at'] ?? ''));
        if ($nextCheckAt !== '' && strtotime($nextCheckAt) > time()) {
            return false;
        }

        if ((string)($row['search_api_status'] ?? '') === 'disabled') {
            return false;
        }

        return $this->extractHostFromUrl($hostUrl) !== '';
    }

    private function isRecrawlPendingRow(?array $row): bool
    {
        if (!$row) {
            return false;
        }
        $ctx = (string)($row['config_sync_context'] ?? '');
        return stripos($ctx, 'recrawl_pending') !== false;
    }

    private function maybeEnableRedirectAndSync(int $siteId, string $label, string $hostUrl): array
    {
        require_once Paths::appRoot() . '/app/Services/SiteConfigResolver.php';
        require_once Paths::appRoot() . '/app/Services/SubdomainProvisioner.php';
        require_once Paths::appRoot() . '/app/Services/ConfigSyncService.php';

        $cfgLabel = ($label === '' ? '_default' : $label);
        $resolver = new SiteConfigResolver();
        $cfg = $resolver->getResolvedConfig($siteId, $cfgLabel);
        $wasEnabled = (int)($cfg['redirect_enabled'] ?? 0) === 1;

        if ($wasEnabled) {
            $this->markRedirectAutoEnabled($siteId, $label, 'done', 'already_enabled', '');
            return ['changed' => false, 'already_enabled' => true, 'sync' => null];
        }

        $cfg['redirect_enabled'] = 1;
        if ($cfgLabel === '_default') {
            $cfg['label'] = '_default';
            $resolver->saveSiteDefaultConfig($siteId, $cfg);
            $resolver->saveLegacySiteConfig($siteId, $cfg);
            $resolver->saveSubdomainConfig($siteId, '_default', $cfg);
        } else {
            $cfg['label'] = $cfgLabel;
            $resolver->saveSubdomainConfig($siteId, $cfgLabel, $cfg);
        }

        $sync = null;
        try {
            $provisioner = new SubdomainProvisioner();
            $provisioner->ensureForSite($siteId, $cfgLabel);
        } catch (Throwable $e) {
        }

        try {
            $configSync = new ConfigSyncService();
            $sync = $configSync->syncLabelConfigFiles($siteId, $cfgLabel);
            $this->markRedirectAutoEnabled($siteId, $label, 'done', implode(', ', (array)($sync['uploaded'] ?? [])), '');
        } catch (Throwable $e) {
            $sync = ['ok' => false, 'error' => $e->getMessage()];
            $this->markRedirectAutoEnabled($siteId, $label, 'error', '', $e->getMessage());
        }

        $this->sendRedirectEnabledTelegram($siteId, $label, $hostUrl, $sync);

        return ['changed' => true, 'already_enabled' => false, 'sync' => $sync];
    }

    private function sendRedirectEnabledTelegram(int $siteId, string $label, string $hostUrl, $sync): void
    {
        try {
            $tg = new TelegramService();
            $cfgLabel = $label === '' ? '_default' : $label;
            $syncOk = is_array($sync) && !empty($sync['ok']);
            $files = is_array($sync) ? implode(', ', (array)($sync['uploaded'] ?? [])) : '';
            $text  = "🟡 <b>Авто-включение redirect_enabled</b>\n";
            $text .= 'Сайт ID: <b>' . (int)$siteId . "</b>\n";
            $text .= 'Host: <code>' . htmlspecialchars($hostUrl, ENT_QUOTES, 'UTF-8') . "</code>\n";
            $text .= 'Label: <code>' . htmlspecialchars($cfgLabel, ENT_QUOTES, 'UTF-8') . "</code>\n";
            $text .= 'Config выгружен на VPS: <b>' . ($syncOk ? 'да' : 'нет') . "</b>\n";
            if ($files !== '') {
                $text .= 'Файлы: <code>' . htmlspecialchars($files, ENT_QUOTES, 'UTF-8') . "</code>\n";
            }
            if (!$syncOk && is_array($sync) && !empty($sync['error'])) {
                $text .= 'Ошибка: <code>' . htmlspecialchars((string)$sync['error'], ENT_QUOTES, 'UTF-8') . "</code>\n";
            }
            $text .= 'Панель: https://hub.seotop-one.ru/webmaster/site?id=' . (int)$siteId;
            $tg->send($text, 'redirect_enabled');
        } catch (Throwable $e) {
            @error_log('[TG xmlstock redirect_enabled] ' . $e->getMessage());
        }
    }

    private function sendRedirectDisabledTelegram(int $siteId, string $label, string $hostUrl, string $reason, $sync): void
    {
        try {
            $tg = new TelegramService();
            $cfgLabel = $label === '' ? '_default' : $label;
            $syncOk = is_array($sync) && !empty($sync['ok']);
            $files = is_array($sync) ? implode(', ', (array)($sync['uploaded'] ?? [])) : '';

            $text  = "🟠 <b>Авто-выключение redirect_enabled</b>\n";
            $text .= 'Сайт ID: <b>' . (int)$siteId . "</b>\n";
            if ($hostUrl !== '') {
                $text .= 'Host: <code>' . htmlspecialchars($hostUrl, ENT_QUOTES, 'UTF-8') . "</code>\n";
            }
            $text .= 'Label: <code>' . htmlspecialchars($cfgLabel, ENT_QUOTES, 'UTF-8') . "</code>\n";
            $text .= 'Причина: <code>' . htmlspecialchars($reason, ENT_QUOTES, 'UTF-8') . "</code>\n";
            $text .= 'Config выгружен на VPS: <b>' . ($syncOk ? 'да' : 'нет') . "</b>\n";
            if ($files !== '') {
                $text .= 'Файлы: <code>' . htmlspecialchars($files, ENT_QUOTES, 'UTF-8') . "</code>\n";
            }
            if (!$syncOk && is_array($sync) && !empty($sync['error'])) {
                $text .= 'Ошибка: <code>' . htmlspecialchars((string)$sync['error'], ENT_QUOTES, 'UTF-8') . "</code>\n";
            }
            $text .= 'Панель: https://hub.seotop-one.ru/webmaster/site?id=' . (int)$siteId;
            $tg->send($text, 'redirect_disabled');
        } catch (Throwable $e) {
            @error_log('[TG xmlstock redirect_disabled] ' . $e->getMessage());
        }
    }

    private function markRedirectAutoEnabled(int $siteId, string $label, string $syncStatus, string $syncContext, string $syncError): void
    {
        DB::withReconnect(function(PDO $pdo) use ($siteId, $label, $syncStatus, $syncContext, $syncError) {
            $st = $pdo->prepare("
                UPDATE webmaster_hosts
                SET redirect_auto_enabled_at = NOW(),
                    config_sync_status = :sync_status,
                    config_sync_last_at = NOW(),
                    config_sync_context = :sync_context,
                    config_sync_error = :sync_error,
                    updated_at = CURRENT_TIMESTAMP
                WHERE site_id = :sid AND label = :label
                LIMIT 1
            ");
            $st->execute([
                ':sync_status' => $syncStatus !== '' ? $syncStatus : 'done',
                ':sync_context' => $syncContext,
                ':sync_error' => $syncError,
                ':sid' => $siteId,
                ':label' => $label,
            ]);

            if ($label === '') {
                $st2 = $pdo->prepare("
                    UPDATE sites
                    SET redirect_auto_enabled_at = NOW(),
                        config_sync_status = :sync_status,
                        config_sync_last_at = NOW(),
                        config_sync_context = :sync_context,
                        config_sync_error = :sync_error
                    WHERE id = :sid
                    LIMIT 1
                ");
                $st2->execute([
                    ':sync_status' => $syncStatus !== '' ? $syncStatus : 'done',
                    ':sync_context' => $syncContext,
                    ':sync_error' => $syncError,
                    ':sid' => $siteId,
                ]);
            }
        });
    }

    public function repairRedirectsForIndexedSite(int $siteId, string $targetLabel = 'ALL'): array
    {
        $desiredHosts = (new YandexWebmasterService())->getDesiredHostsForSite($siteId);
        $results = [];

        foreach ($desiredHosts as $row) {
            $label = (string)($row['label'] ?? '');
            if ($targetLabel !== 'ALL' && $targetLabel !== $label) {
                continue;
            }
            $hostUrl = (string)($row['host_url'] ?? '');
            $hostRow = $this->getHostRow($siteId, $label);
            if (!$hostRow) {
                continue;
            }
            if ((string)($hostRow['search_api_status'] ?? '') !== 'indexed') {
                continue;
            }
            if ($this->isRecrawlPendingRow($hostRow)) {
                $results[] = ['label' => $label, 'host_url' => $hostUrl, 'status' => 'skipped_recrawl_pending'];
                continue;
            }

            $cfgLabel = ($label === '' ? '_default' : $label);
            require_once Paths::appRoot() . '/app/Services/SiteConfigResolver.php';
            $resolver = new SiteConfigResolver();
            $cfg = $resolver->getResolvedConfig($siteId, $cfgLabel);
            $alreadyEnabled = (int)($cfg['redirect_enabled'] ?? 0) === 1;
            $alreadyMarked = trim((string)($hostRow['redirect_auto_enabled_at'] ?? '')) !== '';

            if ($alreadyEnabled && $alreadyMarked) {
                $results[] = ['label' => $label, 'host_url' => $hostUrl, 'status' => 'already_enabled'];
                continue;
            }

            $sync = $this->maybeEnableRedirectAndSync($siteId, $label, $hostUrl);
            $results[] = [
                'label' => $label,
                'host_url' => $hostUrl,
                'status' => !empty($sync['changed']) ? 'enabled_now' : 'marked_enabled',
                'sync' => $sync['sync'] ?? null,
            ];
        }

        return $results;
    }

    private function requestXmlStock(string $query, int $page, array $settings): string
    {
        $endpoint = trim((string)($settings['endpoint_xml'] ?? 'https://xmlstock.com/yandexlive/xml/'));
        if ($endpoint === '') {
            throw new RuntimeException('XML endpoint is empty');
        }

        $params = [
            'user' => (string)$settings['user'],
            'key' => (string)$settings['key'],
            'query' => $query,
            'page' => $page,
        ];

        $url = $endpoint . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 20,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_HTTPHEADER => [
                'Accept: application/xml,text/xml,*/*',
                'User-Agent: HubSearchApi/1.0',
            ],
        ]);

        $body = curl_exec($ch);
        $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($body === false || $body === '' || $err !== '') {
            throw new RuntimeException('request failed: ' . ($err !== '' ? $err : 'empty response'));
        }

        if ($http >= 500) {
            throw new RuntimeException('HTTP ' . $http . ': retry later');
        }

        if ($http !== 200) {
            throw new RuntimeException('HTTP ' . $http);
        }

        $xmlError = $this->extractXmlStockError($body);
        if ($xmlError !== null) {
            if ((int)$xmlError['code'] === 15) {
                return (string)$body;
            }
            throw new RuntimeException('XMLStock error ' . $xmlError['code'] . ($xmlError['message'] !== '' ? ': ' . $xmlError['message'] : ''));
        }

        return (string)$body;
    }

    private function extractXmlStockError(string $xml): ?array
    {
        $code = null;
        $message = '';

        libxml_use_internal_errors(true);
        $sx = simplexml_load_string($xml);
        if ($sx instanceof SimpleXMLElement) {
            $errors = $sx->xpath('//error');
            if (is_array($errors) && !empty($errors)) {
                $errNode = $errors[0];
                $attrs = $errNode->attributes();
                if (isset($attrs['code'])) {
                    $code = (int)$attrs['code'];
                }
                $message = trim((string)$errNode);
            }
        }
        libxml_clear_errors();

        if ($code === null && preg_match('~<error[^>]*code="(\d+)"[^>]*>(.*?)</error>~si', $xml, $m)) {
            $code = (int)$m[1];
            $message = trim(strip_tags($m[2]));
        }

        if ($code === null) {
            return null;
        }

        return [
            'code' => $code,
            'message' => $message,
        ];
    }

    private function extractUrlsFromXml(string $xml): array
    {
        $urls = [];

        if (preg_match_all('~<url>\s*(https?://[^<\s]+)\s*</url>~iu', $xml, $m)) {
            foreach ((array)$m[1] as $url) {
                $u = trim((string)$url);
                if ($u !== '') {
                    $urls[$u] = true;
                }
            }
        }

        return array_values(array_keys($urls));
    }

    private function collectHostsFromUrls(array $urls, string $needleHost): array
    {
        $needleHost = $this->normalizeHost($needleHost);
        $out = [];

        foreach ($urls as $url) {
            $host = $this->extractHostFromUrl((string)$url);
            if ($host !== '' && $host === $needleHost) {
                $out[$host] = true;
            }
        }

        return array_values(array_keys($out));
    }

    private function extractHostFromUrl(string $url): string
    {
        $host = (string)(parse_url(trim($url), PHP_URL_HOST) ?? '');
        return $this->normalizeHost($host);
    }

    private function normalizeHost(string $host): string
    {
        $host = trim((string)$host);
        $host = function_exists('mb_strtolower') ? mb_strtolower($host) : strtolower($host);
        return trim($host, '. ');
    }

    private function calcNextCheckAt(bool $indexed, array $settings, bool $force): string
    {
        if ($indexed) {
            return date('Y-m-d H:i:s', time() + ((int)($settings['recheck_after_detect_minutes'] ?? 1440) * 60));
        }

        $minutes = max(1, (int)($settings['query_interval_minutes'] ?? 30));
        if ($force) {
            $minutes = max(1, min($minutes, 15));
        }

        return date('Y-m-d H:i:s', time() + ($minutes * 60));
    }

    private function saveHostResult(
        int $siteId,
        string $label,
        string $hostUrl,
        string $query,
        string $status,
        ?string $indexedAt,
        string $nextCheckAt,
        string $error,
        array $foundHosts,
        array $foundUrls
    ): void {
        $foundHostsJson = json_encode(array_values($foundHosts), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $foundUrlsJson = json_encode(array_values($foundUrls), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $resultCount = count($foundUrls);

        DB::withReconnect(function (PDO $pdo) use (
            $siteId,
            $label,
            $hostUrl,
            $query,
            $status,
            $indexedAt,
            $nextCheckAt,
            $error,
            $foundHostsJson,
            $foundUrlsJson,
            $resultCount
        ) {
            $st = $pdo->prepare("
                INSERT INTO webmaster_hosts
                    (site_id, label, host_url, search_api_status, search_api_indexed_at, search_api_last_checked_at, search_api_error, search_api_result_count, search_api_last_query, search_api_next_check_at, search_api_found_hosts_json, search_api_found_urls_json, updated_at)
                VALUES
                    (?, ?, ?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
                ON DUPLICATE KEY UPDATE
                    host_url = VALUES(host_url),
                    search_api_status = VALUES(search_api_status),
                    search_api_indexed_at = CASE
                        WHEN VALUES(search_api_status)='indexed' THEN COALESCE(webmaster_hosts.search_api_indexed_at, NOW())
                        ELSE webmaster_hosts.search_api_indexed_at
                    END,
                    search_api_last_checked_at = NOW(),
                    search_api_error = VALUES(search_api_error),
                    search_api_result_count = VALUES(search_api_result_count),
                    search_api_last_query = VALUES(search_api_last_query),
                    search_api_next_check_at = VALUES(search_api_next_check_at),
                    search_api_found_hosts_json = VALUES(search_api_found_hosts_json),
                    search_api_found_urls_json = VALUES(search_api_found_urls_json),
                    updated_at = CURRENT_TIMESTAMP
            ");
            $st->execute([
                $siteId,
                $label,
                $hostUrl,
                $status,
                $indexedAt,
                $error,
                $resultCount,
                $query,
                $nextCheckAt,
                $foundHostsJson,
                $foundUrlsJson,
            ]);
        });
    }

    private function sendDetectedTelegram(
        int $siteId,
        string $label,
        string $hostUrl,
        string $query,
        int $resultCount,
        array $syncInfo = []
    ): void {
        try {
            $tg = new TelegramService();

            $cfgLabel = $label === '' ? '_default' : $label;
            $redirectChanged = !empty($syncInfo['changed']);
            $sync = (array)($syncInfo['sync'] ?? []);

            $syncOk = false;
            if ($redirectChanged) {
                $syncOk = is_array($sync) && !empty($sync['ok']);
            }

            $files = '';
            if ($redirectChanged && is_array($sync)) {
                $files = implode(', ', (array)($sync['uploaded'] ?? []));
            }

            $text  = "🟣 <b>XMLStock увидел хост в поиске</b>\n";
            $text .= 'Сайт ID: <b>' . (int)$siteId . "</b>\n";
            $text .= 'Label: <code>' . htmlspecialchars($cfgLabel, ENT_QUOTES, 'UTF-8') . "</code>\n";
            $text .= 'Host: <code>' . htmlspecialchars($hostUrl, ENT_QUOTES, 'UTF-8') . "</code>\n";
            $text .= 'Query: <code>' . htmlspecialchars($query, ENT_QUOTES, 'UTF-8') . "</code>\n";
            $text .= 'URL в ответе: <b>' . (int)$resultCount . "</b>\n";

            if ($redirectChanged) {
                $text .= 'redirect_enabled включен: <b>' . ($syncOk ? 'да' : 'ошибка выгрузки') . "</b>\n";
                if ($files !== '') {
                    $text .= 'Файлы: <code>' . htmlspecialchars($files, ENT_QUOTES, 'UTF-8') . "</code>\n";
                }
                if (!$syncOk && !empty($sync['error'])) {
                    $text .= 'Ошибка: <code>' . htmlspecialchars((string)$sync['error'], ENT_QUOTES, 'UTF-8') . "</code>\n";
                }
            } else {
                $text .= "redirect_enabled уже был включен\n";
            }

            $text .= 'Проверьте страницу: https://hub.seotop-one.ru/webmaster/search-api?id=' . (int)$siteId;
            $tg->send($text, 'xmlstock_detected');
        } catch (Throwable $e) {
            @error_log('[TG xmlstock search api] ' . $e->getMessage());
        }
    }

    private function saveCronState(bool $ok, array $summary, string $error): void
    {
        $this->ensureCronStateTable();

        DB::withReconnect(function (PDO $pdo) use ($ok, $summary, $error) {
            $st = $pdo->prepare("
                INSERT INTO " . self::CRON_TABLE . "
                    (id, last_run_at, last_ok, last_checked_sites, last_checked_hosts, last_detected_hosts, last_skipped_hosts, last_errors, last_error, updated_at)
                VALUES
                    (1, NOW(), ?, ?, ?, ?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE
                    last_run_at = VALUES(last_run_at),
                    last_ok = VALUES(last_ok),
                    last_checked_sites = VALUES(last_checked_sites),
                    last_checked_hosts = VALUES(last_checked_hosts),
                    last_detected_hosts = VALUES(last_detected_hosts),
                    last_skipped_hosts = VALUES(last_skipped_hosts),
                    last_errors = VALUES(last_errors),
                    last_error = VALUES(last_error),
                    updated_at = NOW()
            ");
            $st->execute([
                $ok ? 1 : 0,
                (int)($summary['checked_sites'] ?? 0),
                (int)($summary['checked_hosts'] ?? 0),
                (int)($summary['detected_hosts'] ?? 0),
                (int)($summary['skipped_hosts'] ?? 0),
                count((array)($summary['errors'] ?? [])),
                $error !== '' ? $error : null,
            ]);
        });
    }

    private function ensureSettingsTable(): void
    {
        DB::withReconnect(function (PDO $pdo) {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS " . self::SETTINGS_TABLE . " (
                    id INT NOT NULL PRIMARY KEY,
                    enabled TINYINT(1) NOT NULL DEFAULT 0,
                    endpoint_xml VARCHAR(255) NOT NULL DEFAULT 'https://xmlstock.com/yandexlive/xml/',
                    endpoint_json VARCHAR(255) NOT NULL DEFAULT 'https://xmlstock.com/yandexlive/json/',
                    `user` VARCHAR(64) NOT NULL DEFAULT '',
                    `key` VARCHAR(255) NOT NULL DEFAULT '',
                    query_interval_minutes INT NOT NULL DEFAULT 30,
                    recheck_after_detect_minutes INT NOT NULL DEFAULT 1440,
                    max_pages_per_run INT NOT NULL DEFAULT 1,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        });
    }

    private function ensureCronStateTable(): void
    {
        DB::withReconnect(function (PDO $pdo) {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS " . self::CRON_TABLE . " (
                    id INT NOT NULL PRIMARY KEY,
                    last_run_at DATETIME DEFAULT NULL,
                    last_ok TINYINT(1) NOT NULL DEFAULT 0,
                    last_checked_sites INT NOT NULL DEFAULT 0,
                    last_checked_hosts INT NOT NULL DEFAULT 0,
                    last_detected_hosts INT NOT NULL DEFAULT 0,
                    last_skipped_hosts INT NOT NULL DEFAULT 0,
                    last_errors INT NOT NULL DEFAULT 0,
                    last_error TEXT NULL,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        });
    }

    private function ensureHostColumns(): void
    {
        DB::withReconnect(function (PDO $pdo) {
            $columns = [
                "ALTER TABLE webmaster_hosts ADD COLUMN search_api_status VARCHAR(32) NOT NULL DEFAULT 'idle'",
                "ALTER TABLE webmaster_hosts ADD COLUMN search_api_indexed_at DATETIME DEFAULT NULL",
                "ALTER TABLE webmaster_hosts ADD COLUMN search_api_last_checked_at DATETIME DEFAULT NULL",
                "ALTER TABLE webmaster_hosts ADD COLUMN search_api_error TEXT NULL",
                "ALTER TABLE webmaster_hosts ADD COLUMN search_api_result_count INT NOT NULL DEFAULT 0",
                "ALTER TABLE webmaster_hosts ADD COLUMN search_api_last_query VARCHAR(255) NOT NULL DEFAULT ''",
                "ALTER TABLE webmaster_hosts ADD COLUMN search_api_next_check_at DATETIME DEFAULT NULL",
                "ALTER TABLE webmaster_hosts ADD COLUMN search_api_found_hosts_json LONGTEXT NULL",
                "ALTER TABLE webmaster_hosts ADD COLUMN search_api_found_urls_json LONGTEXT NULL",
            ];
            foreach ($columns as $sql) {
                try {
                    $pdo->exec($sql);
                } catch (Throwable $e) {
                }
            }
            try {
                $pdo->exec('CREATE INDEX idx_webmaster_hosts_search_api_next ON webmaster_hosts (search_api_next_check_at)');
            } catch (Throwable $e) {
            }
            try {
                $pdo->exec('CREATE INDEX idx_webmaster_hosts_search_api_status ON webmaster_hosts (search_api_status, search_api_indexed_at)');
            } catch (Throwable $e) {
            }
        });
    }

    private function loadSite(int $siteId): ?array
    {
        $st = DB::pdo()->prepare('SELECT * FROM sites WHERE id=? LIMIT 1');
        $st->execute([$siteId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function appendLog(string $line): void
    {
        try {
            $file = Paths::storage(self::LOG_FILE);
            @file_put_contents($file, '[' . date('Y-m-d H:i:s') . '] ' . $line . PHP_EOL, FILE_APPEND | LOCK_EX);
        } catch (Throwable $e) {
            @error_log('[search api log] ' . $e->getMessage());
        }
    }
}
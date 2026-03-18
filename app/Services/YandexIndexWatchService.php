<?php

class YandexIndexWatchService
{
    private SiteConfigResolver $resolver;
    private SubdomainProvisioner $provisioner;
    private ConfigSyncService $configSync;
    private SiteStructure $structure;
    private YandexWebmasterService $wm;

    public function __construct(
        ?SiteConfigResolver $resolver = null,
        ?SubdomainProvisioner $provisioner = null,
        ?ConfigSyncService $configSync = null,
        ?SiteStructure $structure = null,
        ?YandexWebmasterService $wm = null
    ) {
        $this->resolver = $resolver ?: new SiteConfigResolver();
        $this->provisioner = $provisioner ?: new SubdomainProvisioner();
        $this->configSync = $configSync ?: new ConfigSyncService();
        $this->structure = $structure ?: new SiteStructure();
        $this->wm = $wm ?: new YandexWebmasterService();
    }

    public function getStatusesForSite(int $siteId, array $desiredHosts = []): array
    {
        $st = DB::pdo()->prepare('SELECT * FROM webmaster_hosts WHERE site_id=? ORDER BY label ASC');
        $st->execute([$siteId]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $map = [];
        foreach ($rows as $row) {
            $map[(string)($row['label'] ?? '')] = $row;
        }
        foreach ($desiredHosts as $hrow) {
            $label = (string)($hrow['label'] ?? '');
            if (!isset($map[$label])) {
                $map[$label] = [
                    'label' => $label,
                    'host_url' => (string)($hrow['host_url'] ?? ''),
                    'yandex_index_status' => 'unknown',
                    'yandex_index_last_checked_at' => '',
                    'yandex_index_detected_at' => '',
                    'redirect_auto_enabled_at' => '',
                    'config_sync_status' => 'idle',
                    'config_sync_last_at' => '',
                    'config_sync_error' => '',
                    'config_sync_context' => '',
                    'yandex_pages_in_search' => 0,
                    'yandex_pages_added' => 0,
                ];
            }
        }
        return $map;
    }

    public function checkNow(int $siteId, string $targetLabel = 'ALL'): array
    {
        $desired = $this->wm->getDesiredHostsForSite($siteId);
        $site = $this->loadSite($siteId);
        if (!$site) {
            throw new RuntimeException('site not found');
        }

        $userId = null;
        try {
            $userId = (string)$this->wm->getUserId();
        } catch (Throwable $e) {
            $this->appendLog('USER_ID_ERR site=' . $siteId . ' :: ' . $e->getMessage());
        }

        $hostRows = $this->getHostRowsMap($siteId);
        $out = [];

        foreach ($desired as $hrow) {
            $label = (string)($hrow['label'] ?? '');
            if ($targetLabel !== 'ALL' && $targetLabel !== $label) {
                continue;
            }

            $hostUrl = (string)($hrow['host_url'] ?? '');
            $host = preg_replace('~^https?://~i', '', $hostUrl);
            $hostId = (string)($hostRows[$label]['host_id'] ?? '');

            $check = $this->checkHostIndexed($host, $userId, $hostId);
            $this->saveCheckStatus($siteId, $label, $hostUrl, $check);

            $rowResult = [
                'label' => $label,
                'host' => $host,
                'check' => $check,
                'redirect_changed' => false,
                'sync' => null,
            ];

            $pagesAdded = (int)($check['pages_added'] ?? 0);
            $pagesInSearch = (int)($check['pages_in_search'] ?? 0);
            $pagesFullyIndexed = ($pagesAdded > 0 && $pagesInSearch >= $pagesAdded);

            if (!empty($check['indexed']) && $pagesAdded > 0 && !$pagesFullyIndexed) {
                $this->appendLog('WAIT ' . $host . ' :: pages_in_search=' . $pagesInSearch . ' / pages_added=' . $pagesAdded . ' :: redirect stays off');
            }

            if (!empty($check['indexed']) && $pagesFullyIndexed) {
                $cfgLabel = ($label === '' ? '_default' : $label);
                $cfg = $this->resolver->getResolvedConfig($siteId, $cfgLabel);
                $wasEnabled = (int)($cfg['redirect_enabled'] ?? 0) === 1;
                if (!$wasEnabled) {
                    $cfg['redirect_enabled'] = 1;

                    if ($cfgLabel === '_default') {
                        $cfg['label'] = '_default';
                        $this->resolver->saveSiteDefaultConfig($siteId, $cfg);
                        $this->resolver->saveLegacySiteConfig($siteId, $cfg);
                        $this->resolver->saveSubdomainConfig($siteId, '_default', $cfg);
                    } else {
                        $cfg['label'] = $cfgLabel;
                        $this->resolver->saveSubdomainConfig($siteId, $cfgLabel, $cfg);
                    }

                    $this->provisioner->ensureForSite($siteId, $cfgLabel);

                    try {
                        $sync = $this->configSync->syncLabelConfigFiles($siteId, $cfgLabel);
                        $rowResult['sync'] = $sync;
                        $this->markSyncStatus($siteId, $label, 'done', implode(', ', (array)($sync['uploaded'] ?? [])), '');
                    } catch (Throwable $e) {
                        $rowResult['sync'] = ['ok' => false, 'error' => $e->getMessage()];
                        $this->markSyncStatus($siteId, $label, 'error', '', $e->getMessage());
                    }

                    $this->markRedirectAutoEnabled($siteId, $label);
                    $rowResult['redirect_changed'] = true;
                    $this->sendTelegram($siteId, $host, $cfgLabel, $rowResult['sync'], $pagesInSearch, $pagesAdded);
                }
            }

            $out[] = $rowResult;
        }

        return $out;
    }

    public function runCron(int $limitSites = 25): array
    {
        $rows = DB::pdo()->query("SELECT id FROM sites WHERE status='active' ORDER BY id ASC LIMIT " . (int)$limitSites)->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $checkedSites = 0;
        $checkedHosts = 0;
        $indexedHosts = 0;
        $redirects = 0;
        $errors = [];

        foreach ($rows as $row) {
            $siteId = (int)($row['id'] ?? 0);
            if ($siteId <= 0) {
                continue;
            }
            try {
                $res = $this->checkNow($siteId, 'ALL');
                $checkedSites++;
                foreach ($res as $item) {
                    $checkedHosts++;
                    if (!empty($item['check']['indexed'])) {
                        $indexedHosts++;
                    }
                    if (!empty($item['redirect_changed'])) {
                        $redirects++;
                    }
                }
            } catch (Throwable $e) {
                $errors[] = ['site_id' => $siteId, 'error' => $e->getMessage()];
                $this->appendLog('CRON_ERR site=' . $siteId . ' :: ' . $e->getMessage());
            }
        }

        return [
            'checked_sites' => $checkedSites,
            'checked_hosts' => $checkedHosts,
            'indexed_hosts' => $indexedHosts,
            'redirects_enabled' => $redirects,
            'errors' => $errors,
        ];
    }

    public function checkHostIndexed(string $host, ?string $userId = null, ?string $hostId = null): array
    {
        $host = trim($host);
        $debug = [];
        $pagesInSearch = 0;
        $pagesAdded = 0;
        $summaryOk = false;

        if ($host === '') {
            return ['ok' => false, 'indexed' => false, 'method' => '', 'http' => 0, 'final_url' => '', 'error' => 'empty host', 'debug' => ['empty host'], 'pages_in_search' => 0, 'pages_added' => 0];
        }

        $indexed = false;
        $method = '';
        $officialOk = false;

        if ($userId !== null && $userId !== '' && $hostId !== null && $hostId !== '') {
            $summary = $this->checkViaWebmasterSummary($userId, $hostId, $debug);
            if (!empty($summary['ok'])) {
                $summaryOk = true;
                $pagesInSearch = (int)($summary['pages_in_search'] ?? 0);
                $pagesAdded = (int)($summary['pages_added'] ?? 0);
            }

            $official = $this->checkViaWebmasterApi($userId, $hostId, $debug);
            $officialOk = !empty($official['ok']);
            if (!empty($official['indexed'])) {
                $indexed = true;
                $method = (string)($official['method'] ?? 'webmaster_api');
            }
        } else {
            $debug[] = 'webmaster api: skip, user_id or host_id empty';
        }

        $fallback = $this->checkViaHtmlSearch($host, $debug);
        if (!$indexed && !empty($fallback['indexed'])) {
            $indexed = true;
            $method = 'html_site_query';
        }

        if ($method === '' && $officialOk) {
            $method = 'webmaster_api';
        }

        if ($summaryOk && $pagesInSearch > 0 && $method === '') {
            $method = 'webmaster_summary';
        }

        if ($summaryOk) {
            if ($pagesAdded > 0) {
                $debug[] = 'redirect guard: pages_in_search=' . $pagesInSearch . ', pages_added=' . $pagesAdded . ', all=' . ($pagesInSearch >= $pagesAdded ? '1' : '0');
            } else {
                $debug[] = 'redirect guard: pages_added=0, auto redirect blocked until summary shows added pages';
            }
        } else {
            $debug[] = 'redirect guard: webmaster summary unavailable, auto redirect blocked';
        }

        $result = [
            'ok' => ($officialOk || !empty($fallback['ok']) || $summaryOk),
            'indexed' => $indexed,
            'method' => $method,
            'http' => (int)($fallback['http'] ?? 0),
            'final_url' => (string)($fallback['final_url'] ?? ''),
            'error' => (string)($fallback['error'] ?? ''),
            'debug' => $debug,
            'pages_in_search' => $pagesInSearch,
            'pages_added' => $pagesAdded,
        ];

        $this->appendLog('CHECK ' . $host . ' :: ' . json_encode([
            'indexed' => $result['indexed'],
            'method' => $result['method'],
            'http' => $result['http'],
            'error' => $result['error'],
            'pages_in_search' => $pagesInSearch,
            'pages_added' => $pagesAdded,
            'debug' => $debug,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return $result;
    }

    private function checkViaWebmasterSummary(string $userId, string $hostId, array &$debug): array
    {
        try {
            $resp = $this->wm->getHostSummary($userId, $hostId);

            $pagesInSearch = 0;
            $pagesExcluded = 0;

            if (isset($resp['searchable_pages_count'])) {
                $pagesInSearch = (int)$resp['searchable_pages_count'];
            } elseif (isset($resp['data']['searchable_pages_count'])) {
                $pagesInSearch = (int)$resp['data']['searchable_pages_count'];
            }

            if (isset($resp['excluded_pages_count'])) {
                $pagesExcluded = (int)$resp['excluded_pages_count'];
            } elseif (isset($resp['data']['excluded_pages_count'])) {
                $pagesExcluded = (int)$resp['data']['excluded_pages_count'];
            }

            $pagesAdded = $pagesInSearch + $pagesExcluded;
            $debug[] = 'webmaster summary: searchable=' . $pagesInSearch . ', excluded=' . $pagesExcluded . ', total=' . $pagesAdded;

            return [
                'ok' => true,
                'pages_in_search' => $pagesInSearch,
                'pages_added' => $pagesAdded,
            ];
        } catch (Throwable $e) {
            $debug[] = 'webmaster summary err: ' . $e->getMessage();
            return [
                'ok' => false,
                'pages_in_search' => 0,
                'pages_added' => 0,
            ];
        }
    }

    private function checkViaWebmasterApi(string $userId, string $hostId, array &$debug): array
    {
        $out = ['ok' => false, 'indexed' => false, 'method' => ''];

        try {
            $historyResp = $this->wm->getInSearchHistory(
                $userId,
                $hostId,
                date('c', strtotime('-30 days')),
                date('c')
            );

            $historyRows = [];
            if (isset($historyResp['history']) && is_array($historyResp['history'])) {
                $historyRows = $historyResp['history'];
            } elseif (isset($historyResp['data']['history']) && is_array($historyResp['data']['history'])) {
                $historyRows = $historyResp['data']['history'];
            }

            $values = [];
            foreach ($historyRows as $row) {
                $values[] = (int)($row['value'] ?? 0);
            }
            $maxValue = $values ? max($values) : 0;
            $lastValue = $values ? (int)end($values) : 0;
            $debug[] = 'webmaster history: points=' . count($values) . ', last=' . $lastValue . ', max=' . $maxValue;
            $out['ok'] = true;
            if ($maxValue > 0 || $lastValue > 0) {
                $out['indexed'] = true;
                $out['method'] = 'webmaster_history';
                return $out;
            }
        } catch (Throwable $e) {
            $debug[] = 'webmaster history err: ' . $e->getMessage();
        }

        try {
            $samplesResp = $this->wm->getInSearchSamples($userId, $hostId, 0, 50);
            $count = 0;
            $samples = [];
            if (isset($samplesResp['count'])) {
                $count = (int)$samplesResp['count'];
            } elseif (isset($samplesResp['data']['count'])) {
                $count = (int)$samplesResp['data']['count'];
            }
            if (isset($samplesResp['samples']) && is_array($samplesResp['samples'])) {
                $samples = $samplesResp['samples'];
            } elseif (isset($samplesResp['data']['samples']) && is_array($samplesResp['data']['samples'])) {
                $samples = $samplesResp['data']['samples'];
            }
            $debug[] = 'webmaster samples: count=' . $count . ', returned=' . count($samples);
            $out['ok'] = true;
            if ($count > 0 || count($samples) > 0) {
                $out['indexed'] = true;
                $out['method'] = 'webmaster_samples';
                return $out;
            }
        } catch (Throwable $e) {
            $debug[] = 'webmaster samples err: ' . $e->getMessage();
        }

        try {
            $eventsResp = $this->wm->getSearchEventSamples($userId, $hostId, 0, 50);
            $samples = [];
            if (isset($eventsResp['samples']) && is_array($eventsResp['samples'])) {
                $samples = $eventsResp['samples'];
            } elseif (isset($eventsResp['data']['samples']) && is_array($eventsResp['data']['samples'])) {
                $samples = $eventsResp['data']['samples'];
            }
            $appeared = 0;
            $removed = 0;
            foreach ($samples as $sample) {
                $event = (string)($sample['event'] ?? '');
                if ($event === 'APPEARED_IN_SEARCH') {
                    $appeared++;
                } elseif ($event === 'REMOVED_FROM_SEARCH') {
                    $removed++;
                }
            }
            $debug[] = 'webmaster events: returned=' . count($samples) . ', appeared=' . $appeared . ', removed=' . $removed;
            $out['ok'] = true;
            if ($appeared > 0) {
                $out['indexed'] = true;
                $out['method'] = 'webmaster_events';
                return $out;
            }
        } catch (Throwable $e) {
            $debug[] = 'webmaster events err: ' . $e->getMessage();
        }

        return $out;
    }

    private function checkViaHtmlSearch(string $host, array &$debug): array
    {
        $queryUrl = 'https://yandex.ru/search/?text=' . rawurlencode('site:' . $host);
        $ch = curl_init($queryUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 25,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER => [
                'Accept-Language: ru-RU,ru;q=0.9,en;q=0.8',
                'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
            ],
        ]);
        $html = curl_exec($ch);
        $err = (string)curl_error($ch);
        $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $finalUrl = (string)curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        curl_close($ch);

        if ($html === false || $err !== '') {
            $debug[] = 'html site:query err: ' . ($err !== '' ? $err : 'empty response');
            return ['ok' => false, 'indexed' => false, 'http' => $http, 'final_url' => $finalUrl, 'error' => $err !== '' ? $err : 'empty response'];
        }

        $occ = preg_match_all('~' . preg_quote($host, '~') . '~iu', (string)$html, $m);
        $hasSerpMarkers = stripos((string)$html, 'serp-item') !== false
            || stripos((string)$html, 'OrganicTitle') !== false
            || stripos((string)$html, 'SearchResults') !== false
            || stripos((string)$html, 'main__content') !== false;
        $directHref = preg_match('~href="https?://(?:www\.)?' . preg_quote($host, '~') . '(?:[/:?#"\\]|$)~iu', (string)$html) === 1;
        $indexed = $directHref || ($occ >= 2 && $hasSerpMarkers);

        $debug[] = 'html site:query: http=' . $http . ', occ=' . (int)$occ . ', serp_markers=' . ($hasSerpMarkers ? '1' : '0') . ', direct_href=' . ($directHref ? '1' : '0');

        return [
            'ok' => ($http >= 200 && $http < 500),
            'indexed' => $indexed,
            'http' => $http,
            'final_url' => $finalUrl,
            'error' => '',
        ];
    }

    private function saveCheckStatus(int $siteId, string $label, string $hostUrl, array $check): void
    {
        $status = 'error';
        $detectedAt = null;
        if (!empty($check['ok'])) {
            $status = !empty($check['indexed']) ? 'indexed' : 'not_indexed';
            if (!empty($check['indexed'])) {
                $detectedAt = date('Y-m-d H:i:s');
            }
        }

        $pagesInSearch = (int)($check['pages_in_search'] ?? 0);
        $pagesAdded = (int)($check['pages_added'] ?? 0);

        DB::pdo()->prepare("
            INSERT INTO webmaster_hosts (
                site_id,
                label,
                host_url,
                yandex_index_status,
                yandex_index_last_checked_at,
                yandex_index_detected_at,
                yandex_pages_in_search,
                yandex_pages_added,
                updated_at
            )
            VALUES (?, ?, ?, ?, NOW(), ?, ?, ?, CURRENT_TIMESTAMP)
            ON DUPLICATE KEY UPDATE
                host_url = VALUES(host_url),
                yandex_index_status = VALUES(yandex_index_status),
                yandex_index_last_checked_at = NOW(),
                yandex_index_detected_at = CASE
                    WHEN VALUES(yandex_index_status)='indexed' THEN COALESCE(webmaster_hosts.yandex_index_detected_at, NOW())
                    ELSE webmaster_hosts.yandex_index_detected_at
                END,
                yandex_pages_in_search = VALUES(yandex_pages_in_search),
                yandex_pages_added = VALUES(yandex_pages_added),
                updated_at = CURRENT_TIMESTAMP
        ")->execute([$siteId, $label, $hostUrl, $status, $detectedAt, $pagesInSearch, $pagesAdded]);
    }

    private function markRedirectAutoEnabled(int $siteId, string $label): void
    {
        DB::pdo()->prepare("UPDATE webmaster_hosts SET redirect_auto_enabled_at = COALESCE(redirect_auto_enabled_at, NOW()), updated_at = CURRENT_TIMESTAMP WHERE site_id=? AND label=? LIMIT 1")
            ->execute([$siteId, $label]);
    }

    private function markSyncStatus(int $siteId, string $label, string $status, string $context, string $error): void
    {
        DB::pdo()->prepare("UPDATE webmaster_hosts SET config_sync_status=?, config_sync_context=?, config_sync_error=?, config_sync_last_at=NOW(), updated_at=CURRENT_TIMESTAMP WHERE site_id=? AND label=? LIMIT 1")
            ->execute([$status, $context, $error, $siteId, $label]);
    }

    private function getHostRowsMap(int $siteId): array
    {
        $st = DB::pdo()->prepare('SELECT * FROM webmaster_hosts WHERE site_id=?');
        $st->execute([$siteId]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $map = [];
        foreach ($rows as $row) {
            $map[(string)($row['label'] ?? '')] = $row;
        }
        return $map;
    }

    private function loadSite(int $siteId): ?array
    {
        $st = DB::pdo()->prepare('SELECT * FROM sites WHERE id=? LIMIT 1');
        $st->execute([$siteId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function sendTelegram(int $siteId, string $host, string $cfgLabel, $sync, int $pagesInSearch, int $pagesAdded): void
    {
        try {
            $tg = new TelegramService();
            $syncOk = is_array($sync) && !empty($sync['ok']);
            $files = is_array($sync) ? implode(', ', (array)($sync['uploaded'] ?? [])) : '';
            $text  = "🟡 <b>Хост полностью вошел в индекс Яндекса</b>\n";
            $text .= 'Сайт ID: <b>' . (int)$siteId . "</b>\n";
            $text .= 'Хост: <code>' . htmlspecialchars($host, ENT_QUOTES, 'UTF-8') . "</code>\n";
            $text .= 'Label: <code>' . htmlspecialchars($cfgLabel, ENT_QUOTES, 'UTF-8') . "</code>\n";
            $text .= 'Страниц в поиске / добавлено: <b>' . (int)$pagesInSearch . ' / ' . (int)$pagesAdded . "</b>\n";
            $text .= 'Авто-включено <code>redirect_enabled = 1</code>.\n';
            $text .= 'Config выгружен на VPS: <b>' . ($syncOk ? 'да' : 'нет') . "</b>\n";
            if ($files !== '') {
                $text .= 'Файлы: <code>' . htmlspecialchars($files, ENT_QUOTES, 'UTF-8') . "</code>\n";
            }
            if (!$syncOk && is_array($sync) && !empty($sync['error'])) {
                $text .= 'Ошибка: <code>' . htmlspecialchars((string)$sync['error'], ENT_QUOTES, 'UTF-8') . "</code>\n";
            }
            $text .= 'Панель: https://hub.seotop-one.ru/webmaster/site?id=' . (int)$siteId;
            $tg->send($text);
        } catch (Throwable $e) {
            @error_log('[TG index watch] ' . $e->getMessage());
        }
    }

    private function appendLog(string $line): void
    {
        try {
            $file = Paths::storage('logs/yandex_index_watch.log');
            @file_put_contents($file, '[' . date('Y-m-d H:i:s') . '] ' . $line . PHP_EOL, FILE_APPEND | LOCK_EX);
        } catch (Throwable $e) {
            @error_log('[index watch log] ' . $e->getMessage());
        }
    }
}

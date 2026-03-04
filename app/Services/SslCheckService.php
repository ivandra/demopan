<?php

class SslCheckService
{
    public function upsertTargetsForSite(int $siteId, array $desiredHosts, bool $enable = true): void
    {
        DB::withReconnect(function(PDO $pdo) use ($siteId, $desiredHosts, $enable) {

            $st = $pdo->prepare("
                INSERT INTO ssl_checks (site_id, label, domain, enabled, updated_at)
                VALUES (:sid, :label, :domain, :enabled, NULL)
                ON DUPLICATE KEY UPDATE
                  domain = VALUES(domain),
                  enabled = VALUES(enabled)
            ");

            foreach ($desiredHosts as $h) {
                $label = (string)($h['label'] ?? '');
                $hostUrl = (string)($h['host_url'] ?? '');

                $domain = $this->hostUrlToDomain($hostUrl);
                if ($domain === '') continue;

                $st->execute([
                    ':sid' => $siteId,
                    ':label' => $label,
                    ':domain' => $domain,
                    ':enabled' => $enable ? 1 : 0,
                ]);
            }
        });
    }

    public function runOneCycle(int $limit = 50): array
    {
        $rows = DB::withReconnect(function(PDO $pdo) use ($limit) {
            $st = $pdo->prepare("
                SELECT * FROM ssl_checks
                WHERE enabled = 1
                ORDER BY COALESCE(updated_at,'1970-01-01') ASC
                LIMIT {$limit}
            ");
            $st->execute();
            return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        });

        $done = 0;
        $errors = 0;

        foreach ($rows as $r) {
            $id = (int)$r['id'];
            $domain = (string)$r['domain'];

            try {
                $res = $this->checkDomain($domain);

                DB::withReconnect(function(PDO $pdo) use ($id, $res) {
                    $st = $pdo->prepare("
                        UPDATE ssl_checks
                        SET
                          http_code = :http_code,
                          https_ok = :https_ok,
                          ssl_error = :ssl_error,
                          ssl_expires_at = :expires,
                          ssl_issuer = :issuer,
                          ssl_subject = :subject,
                          updated_at = NOW()
                        WHERE id = :id
                        LIMIT 1
                    ");
                    $st->execute([
                        ':http_code' => (int)$res['http_code'],
                        ':https_ok' => (int)$res['https_ok'],
                        ':ssl_error' => $res['ssl_error'],
                        ':expires' => $res['ssl_expires_at'],
                        ':issuer' => $res['ssl_issuer'],
                        ':subject' => $res['ssl_subject'],
                        ':id' => $id,
                    ]);
                });

                $done++;
            } catch (Throwable $e) {
                $errors++;
                DB::withReconnect(function(PDO $pdo) use ($id, $e) {
                    $st = $pdo->prepare("
                        UPDATE ssl_checks
                        SET https_ok=0, ssl_error=:err, updated_at=NOW()
                        WHERE id=:id LIMIT 1
                    ");
                    $st->execute([':err'=>$e->getMessage(), ':id'=>$id]);
                });
            }
        }

        return ['checked' => $done, 'errors' => $errors];
    }

    public function checkDomain(string $domain): array
    {
        $domain = trim($domain);
        if ($domain === '') throw new RuntimeException('empty domain');

        // HTTP check (может быть 404 — это не проблема для SSL)
        $httpCode = 0;
        try {
            $httpCode = $this->head("http://{$domain}/")['http'];
        } catch (Throwable $e) {
            $httpCode = 0;
        }

        // HTTPS / cert check
        $ssl = $this->checkSsl("https://{$domain}:443/");

        return [
            'http_code' => $httpCode,
            'https_ok' => $ssl['ok'] ? 1 : 0,
            'ssl_error' => $ssl['error'],
            'ssl_expires_at' => $ssl['expires_at'], // 'Y-m-d H:i:s' or null
            'ssl_issuer' => $ssl['issuer'],
            'ssl_subject' => $ssl['subject'],
        ];
    }

    private function head(string $url): array
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 12);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 8);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 5);

        curl_exec($ch);
        $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $final = (string)curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        $err = (string)curl_error($ch);
        curl_close($ch);

        return ['http'=>$http, 'final'=>$final, 'err'=>$err];
    }

    private function checkSsl(string $url): array
    {
        // Ключевая идея:
        // 1) нам НЕ важно, самоподписан ли сертификат
        // 2) нам важно, что TLS-соединение поднимается и мы можем достать cert
        $host = parse_url($url, PHP_URL_HOST);
        if (!$host) throw new RuntimeException('bad url');

        $ctx = stream_context_create([
            'ssl' => [
                'capture_peer_cert' => true,
                'verify_peer' => false,
                'verify_peer_name' => false,
                'SNI_enabled' => true,
                'peer_name' => $host,
                'allow_self_signed' => true,
            ]
        ]);

        $client = @stream_socket_client(
            "ssl://{$host}:443",
            $errno,
            $errstr,
            10,
            STREAM_CLIENT_CONNECT,
            $ctx
        );

        if (!$client) {
            return [
                'ok' => false,
                'error' => "ssl connect failed: {$errno} {$errstr}",
                'expires_at' => null,
                'issuer' => null,
                'subject' => null,
            ];
        }

        $params = stream_context_get_params($client);
        fclose($client);

        $cert = $params['options']['ssl']['peer_certificate'] ?? null;
        if (!$cert) {
            return [
                'ok' => false,
                'error' => "peer_certificate missing",
                'expires_at' => null,
                'issuer' => null,
                'subject' => null,
            ];
        }

        $info = openssl_x509_parse($cert);
        $expiresAt = null;
        if (is_array($info) && isset($info['validTo_time_t'])) {
            $expiresAt = date('Y-m-d H:i:s', (int)$info['validTo_time_t']);
        }

        $issuer = $this->dnToString($info['issuer'] ?? null);
        $subject = $this->dnToString($info['subject'] ?? null);

        return [
            'ok' => true,
            'error' => null,
            'expires_at' => $expiresAt,
            'issuer' => $issuer,
            'subject' => $subject,
        ];
    }

    private function dnToString($dn): ?string
    {
        if (!is_array($dn)) return null;
        $parts = [];
        foreach ($dn as $k => $v) $parts[] = $k.'='.$v;
        return implode(', ', $parts);
    }

    private function hostUrlToDomain(string $hostUrl): string
    {
        $hostUrl = trim($hostUrl);
        if ($hostUrl === '') return '';
        $h = parse_url($hostUrl, PHP_URL_HOST);
        if (!$h) {
            $hostUrl = preg_replace('~^https?://~i', '', $hostUrl);
            $hostUrl = preg_replace('~/.*$~', '', $hostUrl);
            return trim($hostUrl);
        }
        return (string)$h;
    }
	
	public function upsertTargetsForSiteFromDb(int $siteId, bool $enable = true): void
{
    $data = DB::withReconnect(function(PDO $pdo) use ($siteId) {

        $domain = (string)($pdo->query("SELECT domain FROM sites WHERE id=".(int)$siteId." LIMIT 1")->fetchColumn() ?: '');

        $subs = [];
        $st = $pdo->prepare("SELECT label, fqdn FROM site_subdomains WHERE site_id=:sid AND enabled=1");
        $st->execute([':sid'=>$siteId]);
        foreach (($st->fetchAll(PDO::FETCH_ASSOC) ?: []) as $r) {
            $subs[] = ['label' => (string)$r['label'], 'host_url' => 'https://' . (string)$r['fqdn']];
        }

        $hosts = [];
        if ($domain !== '') {
            $hosts[] = ['label' => '', 'host_url' => 'https://' . $domain];
        }
        // если хочешь также www корня:
        // $hosts[] = ['label' => 'www', 'host_url' => 'https://www.' . $domain];

        return array_merge($hosts, $subs);
    });

    $this->upsertTargetsForSite($siteId, $data, $enable);
}

public function syncTargetsFromDb(int $siteId, bool $enable = true): void
{
    $siteId = (int)$siteId;
    if ($siteId <= 0) return;

    $rows = DB::withReconnect(function(PDO $pdo) use ($siteId) {

        $domain = (string)($pdo->query("SELECT domain FROM sites WHERE id={$siteId} LIMIT 1")->fetchColumn() ?: '');
        $domain = trim($domain);

        $hosts = [];

        // корень (label = '')
        if ($domain !== '') {
            $hosts[] = ['label' => '', 'host_url' => 'https://' . $domain];
        }

        // поддомены enabled=1, кроме _default
        $st = $pdo->prepare("SELECT label, fqdn FROM site_subdomains WHERE site_id=:sid AND enabled=1 AND label<>'_default'");
        $st->execute([':sid' => $siteId]);

        foreach (($st->fetchAll(PDO::FETCH_ASSOC) ?: []) as $r) {
            $lb = trim((string)($r['label'] ?? ''));
            $fq = trim((string)($r['fqdn'] ?? ''));
            if ($lb === '' || $fq === '') continue;

            $hosts[] = ['label' => $lb, 'host_url' => 'https://' . $fq];
        }

        return $hosts;
    });

    // upsert enabled=1 по этим host'ам
    $this->upsertTargetsForSite($siteId, $rows, $enable);

    // выключаем в ssl_checks те, которых больше нет в enabled=1
    DB::withReconnect(function(PDO $pdo) use ($siteId, $rows) {
        $labelsKeep = [];
        foreach ($rows as $h) {
            $labelsKeep[] = (string)($h['label'] ?? '');
        }
        $labelsKeep = array_values(array_unique($labelsKeep));

        if (empty($labelsKeep)) return;

        // label='' тоже должен быть в keep
        $in = implode(',', array_fill(0, count($labelsKeep), '?'));

        $sql = "UPDATE ssl_checks SET enabled=0 WHERE site_id=? AND label NOT IN ($in)";
        $params = array_merge([$siteId], $labelsKeep);
        $pdo->prepare($sql)->execute($params);
    });
}

public function runForSite(int $siteId, int $limit = 200): array
{
    $checked = 0;
    $errors  = 0;

    $rows = DB::withReconnect(function(PDO $pdo) use ($siteId, $limit) {
        $st = $pdo->prepare("
            SELECT *
            FROM ssl_checks
            WHERE site_id=?
              AND enabled=1
            ORDER BY COALESCE(updated_at,'1970-01-01') ASC
            LIMIT " . (int)$limit
        );
        $st->execute([$siteId]);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    });

    foreach ($rows as $r) {
        $id = (int)($r['id'] ?? 0);
        $domain = (string)($r['domain'] ?? '');
        if ($id <= 0 || $domain === '') continue;

        $checked++;

        try {
            // ВАЖНО: тут должен быть твой “проверить один домен”
            // Если в сервисе уже есть метод типа checkOneRow()/checkDomain() — вызывай его.
            // Ниже — универсальный вызов: подстрой под свою реализацию.
            if (method_exists($this, 'checkOne')) {
                $this->checkOne($id, $domain);
            } elseif (method_exists($this, 'checkDomainAndUpdateRow')) {
                $this->checkDomainAndUpdateRow($id, $domain);
            } elseif (method_exists($this, 'checkDomain')) {
                $this->checkDomain($id, $domain);
            } else {
                // fallback: если у тебя только runOneCycle, то лучше НЕ делать тут ничего,
                // но оставим понятную ошибку, чтобы ты сразу увидел в логах
                throw new RuntimeException('SslCheckService: no single-domain check method found');
            }

        } catch (Throwable $e) {
            $errors++;
            @error_log('[SslCheckService runForSite] id=' . $id . ' domain=' . $domain . ' err=' . $e->getMessage());
        }
    }

    return ['checked' => $checked, 'errors' => $errors];
}
}
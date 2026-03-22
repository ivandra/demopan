<?php

class SslCheckService
{
    public function upsertTargetsForSite(int $siteId, array $desiredHosts, bool $enable = true): void
    {
        DB::withReconnect(function (PDO $pdo) use ($siteId, $desiredHosts, $enable) {
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
                if ($domain === '') {
                    $this->appendLog(
                        'UPSERT_SKIP site=' . $siteId .
                        ' label=' . ($label === '' ? 'root' : $label) .
                        ' host_url=' . $hostUrl .
                        ' :: empty domain'
                    );
                    continue;
                }

                $st->execute([
                    ':sid' => $siteId,
                    ':label' => $label,
                    ':domain' => $domain,
                    ':enabled' => $enable ? 1 : 0,
                ]);

                $this->appendLog(
                    'UPSERT site=' . $siteId .
                    ' label=' . ($label === '' ? 'root' : $label) .
                    ' domain=' . $domain .
                    ' enabled=' . ($enable ? '1' : '0')
                );
            }
        });
    }

    public function upsertTargetsForSiteFromDb(int $siteId, bool $enable = true): void
    {
        $data = DB::withReconnect(function (PDO $pdo) use ($siteId) {
            $siteSt = $pdo->prepare("SELECT domain FROM sites WHERE id = :id LIMIT 1");
            $siteSt->execute([':id' => $siteId]);
            $domain = (string)($siteSt->fetchColumn() ?: '');

            $hosts = [];
            if ($domain !== '') {
                $hosts[] = [
                    'label' => '',
                    'host_url' => 'https://' . $domain,
                ];
            }

            $subSt = $pdo->prepare("
                SELECT label, fqdn
                FROM site_subdomains
                WHERE site_id = :sid AND enabled = 1
                ORDER BY label ASC
            ");
            $subSt->execute([':sid' => $siteId]);

            foreach (($subSt->fetchAll(PDO::FETCH_ASSOC) ?: []) as $row) {
                $label = (string)($row['label'] ?? '');
                $fqdn = (string)($row['fqdn'] ?? '');

                if ($label === '_default') {
                    continue;
                }
                if ($fqdn === '') {
                    continue;
                }

                $hosts[] = [
                    'label' => $label,
                    'host_url' => 'https://' . $fqdn,
                ];
            }

            return $hosts;
        });

        $this->upsertTargetsForSite($siteId, $data, $enable);
    }

    public function runOneCycle(int $limit = 50): array
    {
        $rows = DB::withReconnect(function (PDO $pdo) use ($limit) {
            $st = $pdo->prepare("
                SELECT *
                FROM ssl_checks
                WHERE enabled = 1
                ORDER BY COALESCE(updated_at, '1970-01-01 00:00:00') ASC, id ASC
                LIMIT {$limit}
            ");
            $st->execute();
            return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        });

        $done = 0;
        $errors = 0;

        $this->appendLog('RUN start limit=' . (int)$limit . ' rows=' . count($rows));

        foreach ($rows as $r) {
            $id = (int)$r['id'];
            $siteId = (int)($r['site_id'] ?? 0);
            $label = (string)($r['label'] ?? '');
            $domain = (string)($r['domain'] ?? '');

            try {
                $this->appendLog(
                    'CHECK_BEGIN id=' . $id .
                    ' site=' . $siteId .
                    ' label=' . ($label === '' ? 'root' : $label) .
                    ' domain=' . $domain
                );

                $res = $this->checkDomain($domain);

                DB::withReconnect(function (PDO $pdo) use ($id, $res) {
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

                $this->appendLog(
                    'CHECK_DONE id=' . $id .
                    ' domain=' . $domain .
                    ' http=' . (int)$res['http_code'] .
                    ' https_ok=' . (int)$res['https_ok'] .
                    ' ssl_error=' . $this->stringify($res['ssl_error'] ?? '')
                );

                $done++;
            } catch (Throwable $e) {
                $errors++;

                $this->appendLog(
                    'CHECK_ERR id=' . $id .
                    ' domain=' . $domain .
                    ' :: ' . $e->getMessage()
                );

                DB::withReconnect(function (PDO $pdo) use ($id, $e) {
                    $st = $pdo->prepare("
                        UPDATE ssl_checks
                        SET
                          https_ok = 0,
                          ssl_error = :err,
                          updated_at = NOW()
                        WHERE id = :id
                        LIMIT 1
                    ");
                    $st->execute([
                        ':err' => $e->getMessage(),
                        ':id' => $id,
                    ]);
                });
            }
        }

        $this->appendLog('RUN finish checked=' . $done . ' errors=' . $errors);

        return [
            'checked' => $done,
            'errors' => $errors,
        ];
    }

    public function checkDomain(string $domain): array
    {
        $domain = trim($domain);
        if ($domain === '') {
            throw new RuntimeException('empty domain');
        }

        $httpCode = 0;
        try {
            $http = $this->httpProbe('http://' . $domain . '/');
            $httpCode = (int)$http['http'];

            $this->appendLog(
                'HTTP_PROBE domain=' . $domain .
                ' url=http://' . $domain . '/' .
                ' http=' . $httpCode .
                ' final=' . $this->stringify($http['final'] ?? '') .
                ' err=' . $this->stringify($http['err'] ?? '') .
                ' body_len=' . (int)($http['body_len'] ?? 0)
            );
        } catch (Throwable $e) {
            $httpCode = 0;
            $this->appendLog('HTTP_PROBE_ERR domain=' . $domain . ' :: ' . $e->getMessage());
        }

        $ssl = $this->checkSsl('https://' . $domain . ':443/');

        return [
            'http_code' => $httpCode,
            'https_ok' => $ssl['ok'] ? 1 : 0,
            'ssl_error' => $ssl['error'],
            'ssl_expires_at' => $ssl['expires_at'],
            'ssl_issuer' => $ssl['issuer'],
            'ssl_subject' => $ssl['subject'],
        ];
    }

    private function httpProbe(string $url): array
    {
        $ch = curl_init($url);
        if ($ch === false) {
            return [
                'http' => 0,
                'final' => $url,
                'err' => 'curl_init failed',
                'body_len' => 0,
            ];
        }

        curl_setopt($ch, CURLOPT_NOBODY, false);
        curl_setopt($ch, CURLOPT_HTTPGET, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 12);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 8);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; HubMonitor/1.0)');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: */*',
            'Connection: close',
        ]);

        $body = curl_exec($ch);
        $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $final = (string)curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        $err = (string)curl_error($ch);

        curl_close($ch);

        return [
            'http' => $http,
            'final' => $final,
            'err' => $err,
            'body_len' => is_string($body) ? strlen($body) : 0,
        ];
    }

    private function checkSsl(string $url): array
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (!$host) {
            throw new RuntimeException('bad url');
        }

        $ctx = stream_context_create([
            'ssl' => [
                'capture_peer_cert' => true,
                'verify_peer' => false,
                'verify_peer_name' => false,
                'SNI_enabled' => true,
                'peer_name' => $host,
                'allow_self_signed' => true,
            ],
        ]);

        $client = @stream_socket_client(
            'ssl://' . $host . ':443',
            $errno,
            $errstr,
            10,
            STREAM_CLIENT_CONNECT,
            $ctx
        );

        if (!$client) {
            $msg = 'ssl connect failed: ' . $errno . ' ' . $errstr;
            $this->appendLog('SSL_ERR host=' . $host . ' :: ' . $msg);

            return [
                'ok' => false,
                'error' => $msg,
                'expires_at' => null,
                'issuer' => null,
                'subject' => null,
            ];
        }

        $params = stream_context_get_params($client);
        fclose($client);

        $cert = $params['options']['ssl']['peer_certificate'] ?? null;
        if (!$cert) {
            $this->appendLog('SSL_ERR host=' . $host . ' :: peer_certificate missing');

            return [
                'ok' => false,
                'error' => 'peer_certificate missing',
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

        $this->appendLog(
            'SSL_OK host=' . $host .
            ' expires=' . $this->stringify($expiresAt) .
            ' issuer=' . $this->stringify($issuer) .
            ' subject=' . $this->stringify($subject)
        );

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
        if (!is_array($dn)) {
            return null;
        }

        $parts = [];
        foreach ($dn as $k => $v) {
            $parts[] = $k . '=' . $v;
        }

        return implode(', ', $parts);
    }

    private function hostUrlToDomain(string $hostUrl): string
    {
        $hostUrl = trim($hostUrl);
        if ($hostUrl === '') {
            return '';
        }

        $h = parse_url($hostUrl, PHP_URL_HOST);
        if ($h) {
            return (string)$h;
        }

        $hostUrl = preg_replace('~^https?://~i', '', $hostUrl);
        $hostUrl = preg_replace('~/.*$~', '', $hostUrl);

        return trim((string)$hostUrl);
    }

    private function appendLog(string $line): void
    {
        try {
            $file = Paths::storage('logs/ssl_checks.log');
            @file_put_contents($file, '[' . date('Y-m-d H:i:s') . '] ' . $line . PHP_EOL, FILE_APPEND | LOCK_EX);
        } catch (Throwable $e) {
            @error_log('[ssl checks log] ' . $e->getMessage());
        }
    }

    private function stringify($value): string
    {
        if (is_array($value) || is_object($value)) {
            $text = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } else {
            $text = (string)$value;
        }

        $text = preg_replace('~\s+~u', ' ', trim((string)$text));
        return $text === '' ? '(empty)' : $text;
    }
}
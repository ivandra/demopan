<?php

class FastpanelClient
{
    private string $baseUrl;
    private string $token = '';
    private bool $verifyTls;
    private int $timeout;

    public function __construct(string $host, bool $verifyTls = false, int $timeout = 30)
    {
        $host = trim($host);
        $host = preg_replace('~^https?://~i', '', $host);
        $host = rtrim($host, '/');

        $this->baseUrl   = 'https://' . $host;
        $this->verifyTls = $verifyTls;
        $this->timeout   = $timeout;
    }

    /**
     * POST /login
     * ответ: { "token": "..." }
     */
    public function login(string $username, string $password): void
    {
        $resp = $this->request('POST', '/login', [
            'username' => $username,
            'password' => $password,
        ], false);

        $token = $resp['token'] ?? null;
        if (!is_string($token) || $token === '') {
            throw new RuntimeException('Fastpanel login: token not found');
        }
        $this->token = $token;
    }

    /** GET /api/me */
    public function me(): array
    {
        return $this->request('GET', '/api/me');
    }

    /** GET /api/sites/simple */
    public function simpleSites(): array
    {
        return $this->request('GET', '/api/sites/simple');
    }

    /** GET /api/sites/{id} */
    public function site(int $id): array
    {
        return $this->request('GET', '/api/sites/' . $id);
    }

    /** PUT /api/sites/{id} */
    public function updateSite(int $siteId, array $data): array
    {
        return $this->request('PUT', "/api/sites/{$siteId}", $data);
    }

    /**
     * PUT /api/master
     * Создание сайта
     */
    public function createSite(array $payload): array
    {
        return $this->request('PUT', '/api/master', $payload);
    }

    /**
     * POST /api/ftp/accounts
     * Создание FTP-аккаунта
     */
    public function createFtpAccount(array $payload): array
    {
        return $this->request('POST', '/api/ftp/accounts', $payload);
    }

    /** GET /api/queue */
    public function queue(): array
    {
        return $this->request('GET', '/api/queue');
    }

    /** GET /api/certificates */
    public function certificates(): array
    {
        return $this->request('GET', '/api/certificates');
    }

    /** GET /api/certificates/{id} */
    public function certificate(int $id): array
    {
        return $this->request('GET', '/api/certificates/' . $id);
    }

    /**
     * POST /api/certificates
     * payload (как в UI):
     * {"type":"self_signed","email":"...","common_name":"...","alternative_name":"www....","virtualhost":881,"length":2048,"expires":365}
     */
    public function createSelfSignedCertificate(
        int $virtualhostId,
        string $email,
        string $commonName,
        string $alternativeName,
        int $length = 2048,
        int $expiresDays = 365
    ): array {
        $payload = [
            'type'             => 'self_signed',
            'email'            => $email,
            'common_name'      => $commonName,
            'alternative_name' => $alternativeName,
            'virtualhost'      => $virtualhostId,
            'length'           => $length,
            'expires'          => $expiresDays,
        ];

        return $this->request('POST', '/api/certificates', $payload);
    }

    /**
     * PUT /api/sites/{id} — применить сертификат (как в UI)
     * body:
     * {"manual_changes":false,"certificate":1851,"https_redirect":false,"hsts":false,"http2":false,"http3":false}
     */
    public function applyCertificateToSite(
        int $siteId,
        int $certificateId,
        bool $httpsRedirect = false,
        bool $hsts = false,
        bool $http2 = false,
        bool $http3 = false,
        bool $manualChanges = false
    ): array {
        $payload = [
            'manual_changes'  => $manualChanges,
            'certificate'     => $certificateId,
            'https_redirect'  => $httpsRedirect,
            'hsts'            => $hsts,
            'http2'           => $http2,
            'http3'           => $http3,
        ];

        return $this->updateSite($siteId, $payload);
    }

    /**
     * Ожидание job в /api/queue:
     * type=SSLCERTIFICATE, virtualhost_id=..., object_id=certificateId
     *
     * Вариант B: ждать можно ТОЛЬКО генерацию сертификата (это не "применение"),
     * чтобы не пытаться применить certId, который еще не готов.
     */
    public function waitQueueSslCertificateJob(
        int $virtualhostId,
        int $certificateId,
        int $timeoutSec = 120,
        int $pollSec = 2
    ): array {
        $t0 = time();
        $last = null;

        while (true) {
            $items = $this->queue();
            if (is_array($items)) {
                foreach ($items as $job) {
                    if (!is_array($job)) continue;

                    if ((string)($job['type'] ?? '') !== 'SSLCERTIFICATE') continue;
                    if ((int)($job['virtualhost_id'] ?? 0) !== $virtualhostId) continue;
                    if ((int)($job['object_id'] ?? 0) !== $certificateId) continue;

                    $last = $job;
                    $status = (string)($job['status'] ?? '');

                    if ($status === 'SUCCESS') {
                        return $job;
                    }
                    if ($status === 'ERROR' || $status === 'FAILED') {
                        $desc = (string)($job['description'] ?? '');
                        throw new RuntimeException(
                            'Fastpanel queue SSLCERTIFICATE failed: ' .
                            ($desc !== '' ? $desc : json_encode($job, JSON_UNESCAPED_UNICODE))
                        );
                    }
                }
            }

            if ((time() - $t0) >= $timeoutSec) {
                throw new RuntimeException(
                    'Fastpanel queue timeout waiting SSLCERTIFICATE. last=' .
                    json_encode($last, JSON_UNESCAPED_UNICODE)
                );
            }

            sleep($pollSec);
        }
    }

    /**
     * Вариант B: быстрый read сайта, без ожиданий.
     * Возвращает (certId, enabled) если есть.
     */
    public function getSiteCertificateState(int $siteId): array
    {
        $site = $this->site($siteId);

        $cert = $site['certificate'] ?? null;

        $certId = 0;
        $enabled = false;

        if (is_array($cert)) {
            $certId = (int)($cert['id'] ?? 0);
            $enabled = (bool)($cert['enabled'] ?? false);
        } elseif (is_numeric($cert)) {
            $certId = (int)$cert;
        }

        return [
            'site' => $site,
            'cert_id' => $certId,
            'enabled' => $enabled,
        ];
    }

    /**
     * Универсальный JSON request
     */
    private function request(string $method, string $path, ?array $json = null, bool $auth = true): array
    {
        $url = $this->baseUrl . $path;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);

        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $this->verifyTls ? 1 : 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $this->verifyTls ? 2 : 0);

        $headers = [
            'User-Agent: hub-fastpanel',
            'Accept: application/json',
        ];

        if ($auth) {
            if ($this->token === '') {
                throw new RuntimeException('Fastpanel: not authenticated');
            }
            $headers[] = 'Authorization: Bearer ' . $this->token;
        }

        if ($json !== null) {
            $body = json_encode($json, JSON_UNESCAPED_UNICODE);
            $headers[] = 'Content-Type: application/json';
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $resp = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($resp === false) {
            throw new RuntimeException('curl error: ' . $err);
        }

        $decoded = json_decode((string)$resp, true);

        if (!is_array($decoded)) {
            if ($code >= 400) {
                throw new RuntimeException("Fastpanel HTTP $code: " . substr((string)$resp, 0, 800));
            }
            return [];
        }

        if ($code >= 400) {
            $msg = null;

            if (isset($decoded['errors'])) {
                $msg = is_string($decoded['errors']) ? $decoded['errors'] : json_encode($decoded['errors'], JSON_UNESCAPED_UNICODE);
            } elseif (isset($decoded['message'])) {
                $msg = is_string($decoded['message']) ? $decoded['message'] : json_encode($decoded['message'], JSON_UNESCAPED_UNICODE);
            } elseif (isset($decoded['error'])) {
                $msg = is_string($decoded['error']) ? $decoded['error'] : json_encode($decoded['error'], JSON_UNESCAPED_UNICODE);
            } else {
                $msg = json_encode($decoded, JSON_UNESCAPED_UNICODE);
            }

            throw new RuntimeException('Fastpanel error: ' . $msg . ' | body=' . json_encode($decoded, JSON_UNESCAPED_UNICODE));
        }

        return (isset($decoded['data']) && is_array($decoded['data'])) ? $decoded['data'] : $decoded;
    }
	
	
   /**
	 * Удаление сайта (разные версии FastPanel имеют разные endpoints)
	 * Пробуем:
	 * 1) DELETE /api/sites/{id}/status
	 * 2) DELETE /api/sites/{id}
	 */
	public function deleteSite(int $id): array
	{
		try {
			return $this->request('DELETE', '/api/sites/' . $id . '/status');
		} catch (RuntimeException $e) {
			// если конкретно 404 — пробуем другой endpoint
			if (strpos($e->getMessage(), 'HTTP 404') !== false || strpos($e->getMessage(), '404 page not found') !== false) {
				return $this->request('DELETE', '/api/sites/' . $id);
			}
			throw $e;
		}
	}

	public function addSiteAliases(int $siteId, array $fqdns): array
{
    $site = $this->site($siteId);

    $existing = [];
    if (isset($site['aliases']) && is_array($site['aliases'])) {
        foreach ($site['aliases'] as $a) {
            if (is_array($a) && !empty($a['name'])) {
                $existing[strtolower(trim((string)$a['name']))] = true;
            } elseif (is_string($a)) {
                $existing[strtolower(trim($a))] = true;
            }
        }
    }

    foreach ($fqdns as $d) {
        $d = strtolower(trim((string)$d));
        if ($d !== '') $existing[$d] = true;
    }

    $aliasesPayload = [];
    foreach (array_keys($existing) as $name) {
        $aliasesPayload[] = ['name' => $name];
    }

    // обновляем только aliases
    return $this->updateSite($siteId, [
        'aliases' => $aliasesPayload,
    ]);
}


}

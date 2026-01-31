<?php

class NamecheapClient
{
    private string $endpoint;
    private string $apiUser;
    private string $apiKey;
    private string $username;
    private string $clientIp;
    private int $timeout;

    public function __construct(
        string $endpoint,
        string $apiUser,
        string $apiKey,
        string $username,
        string $clientIp,
        int $timeout = 30
    ) {
        $this->endpoint = $endpoint;
        $this->apiUser  = $apiUser;
        $this->apiKey   = $apiKey;
        $this->username = $username;
        $this->clientIp = $clientIp;
        $this->timeout  = $timeout;
    }

    public function checkDomain(string $domain): array
    {
        $domain = $this->normalizeDomain($domain);

        $resp = $this->request('namecheap.domains.check', [
            'DomainList' => $domain,
        ]);

        $results = $resp['DomainCheckResult'] ?? null;
        if (!$results) {
            throw new RuntimeException('Namecheap: no DomainCheckResult in response');
        }

        $item = $this->firstItem($results);

        $available = $this->toBool($item['@Available'] ?? 'false');
        $premium   = $this->toBool($item['@IsPremiumName'] ?? 'false');

        return [
            'domain'    => (string)($item['@Domain'] ?? $domain),
            'available' => $available,
            'premium'   => $premium,
            'raw'       => $item,
        ];
    }
	
	public function getDomainInfo(string $domain): array
{
    $domain = $this->normalizeDomain($domain);

    // Важно: для getInfo нужен именно domain.tld
    return $this->request('namecheap.domains.getInfo', [
        'DomainName' => $domain,
    ]);
}

	
	/**
 * REGISTER pricing variants for 1 year, USD.
 * Возвращает:
 * - price (минимальная из "адекватных" цен)
 * - regular_price / your_price / coupon_price (если есть)
 * - promo_code (если есть)
 * - candidates (все кандидаты 1Y USD)
 */
public function getPricingRegister1YVariants(string $tld): array
{
    $tld = ltrim(strtolower(trim($tld)), '.');

    $resp = $this->request('namecheap.users.getPricing', [
        'ProductType' => 'DOMAIN',
        'ActionName'  => 'REGISTER',
        'ProductName' => strtoupper($tld),
    ]);

    // В ответе встречаются разные структуры, проще собрать все узлы "Price"
    $prices = $this->deepFind($resp, 'Price');

    $candidates = [];

    foreach ($prices as $p) {
        if (!is_array($p)) continue;

        $duration = (int)($p['@Duration'] ?? 0);
        $currency = (string)($p['@Currency'] ?? '');
        $action   = (string)($p['@Action'] ?? '');

        if ($duration !== 1) continue;
        if ($currency !== 'USD') continue;

        // иногда Action пустой, иногда REGISTER — пропускаем только если явно не REGISTER
        if ($action !== '' && strtoupper($action) !== 'REGISTER') continue;

        $row = [
            'duration'       => $duration,
            'currency'       => $currency,
            'action'         => $action,
            'price'          => $p['@Price'] ?? null,
            'regular_price'  => $p['@RegularPrice'] ?? null,
            'your_price'     => $p['@YourPrice'] ?? null,
            'coupon_price'   => $p['@CouponPrice'] ?? null,
            'promo_code'     => $p['@PromoCode'] ?? null,
            // иногда есть подсказки типа Category/Type
            'source'         => $p['@Type'] ?? ($p['@Category'] ?? ''),
        ];

        $candidates[] = $row;
    }

    if (empty($candidates)) {
        throw new RuntimeException('Namecheap: cannot extract REGISTER 1Y USD price variants for .' . $tld);
    }

    // Вытащим “лучшие” значения:
    $regular = null;
    $your    = null;
    $coupon  = null;
    $promo   = null;

    // соберем минимальную "price" и минимальную "your/coupon" если есть
    $minPrice = null;
    $minPay   = null; // what you actually pay (prefer coupon/your)

    foreach ($candidates as $c) {
        if ($promo === null && !empty($c['promo_code'])) $promo = (string)$c['promo_code'];

        if (is_numeric($c['regular_price'])) {
            $v = (float)$c['regular_price'];
            if ($regular === null || $v < $regular) $regular = $v;
        }
        if (is_numeric($c['your_price'])) {
            $v = (float)$c['your_price'];
            if ($your === null || $v < $your) $your = $v;
            if ($minPay === null || $v < $minPay) $minPay = $v;
        }
        if (is_numeric($c['coupon_price'])) {
            $v = (float)$c['coupon_price'];
            if ($coupon === null || $v < $coupon) $coupon = $v;
            if ($minPay === null || $v < $minPay) $minPay = $v;
        }
        if (is_numeric($c['price'])) {
            $v = (float)$c['price'];
            if ($minPrice === null || $v < $minPrice) $minPrice = $v;
        }
    }

    // Итоговая “цена решения”:
    // если есть coupon/your — берем минимальную из них, иначе берем минимальную price
    $final = $minPay ?? $minPrice;

    if ($final === null) {
        throw new RuntimeException('Namecheap: variants extracted but no numeric price for .' . $tld);
    }

    return [
        'price'         => (float)$final,
        'regular_price' => $regular,
        'your_price'    => $your,
        'coupon_price'  => $coupon,
        'promo_code'    => $promo,
        'candidates'    => $candidates,
    ];
}


public function getPricingRegister1Y(string $tld): float
{
    $v = $this->getPricingRegister1YVariants($tld);
    return (float)$v['price'];
}


private function asList($node): array
{
    if (!is_array($node)) return [];
    // если это уже список (0..n)
    if (isset($node[0])) return $node;
    // иначе одиночный объект
    return [$node];
}


    /**
     * domains.create
     * $contacts keys: first_name,last_name,organization,address1,address2,city,state_province,postal_code,country,phone,email
     */
    public function createDomain(string $sld, string $tld, int $years, array $contacts): array
    {
        $sld = strtolower(trim($sld));
        $tld = ltrim(strtolower(trim($tld)), '.');

        if ($sld === '' || $tld === '') {
            throw new RuntimeException('Namecheap: bad SLD/TLD');
        }

        $params = [
            'DomainName' => $sld . '.' . $tld,
            'Years'      => $years,
            'AddFreeWhoisguard' => 'no',
            'WGEnabled'         => 'no',
        ];

        foreach (['Registrant', 'Admin', 'Tech', 'AuxBilling'] as $prefix) {
            $params[$prefix . 'FirstName']    = (string)($contacts['first_name'] ?? '');
            $params[$prefix . 'LastName']     = (string)($contacts['last_name'] ?? '');
            $params[$prefix . 'Organization'] = (string)($contacts['organization'] ?? '');
            $params[$prefix . 'Address1']     = (string)($contacts['address1'] ?? '');
            $params[$prefix . 'Address2']     = (string)($contacts['address2'] ?? '');
            $params[$prefix . 'City']         = (string)($contacts['city'] ?? '');
            $params[$prefix . 'StateProvince']= (string)($contacts['state_province'] ?? '');
            $params[$prefix . 'PostalCode']   = (string)($contacts['postal_code'] ?? '');
            $params[$prefix . 'Country']      = (string)($contacts['country'] ?? '');
            $params[$prefix . 'Phone']        = (string)($contacts['phone'] ?? '');
            $params[$prefix . 'EmailAddress'] = (string)($contacts['email'] ?? '');
        }

        return $this->request('namecheap.domains.create', $params);
    }

 /**
 * domains.dns.setHosts (ПОЛНОСТЬЮ перезаписывает набор записей!)
 *
 * Поддержка полей в каждом host-элементе:
 * - host (string)      // HostNameN
 * - type (string)      // RecordTypeN (A, AAAA, CNAME, MX, TXT, etc.)
 * - address (string)   // AddressN
 * - ttl (int|string)   // TTLN (в секундах, например 300)
 * - mxpref (int|string) // MXPrefN (ТОЛЬКО для MX)
 *
 * Пример элемента:
 * [
 *   'host' => '@',
 *   'type' => 'A',
 *   'address' => '1.2.3.4',
 *   'ttl' => 300,
 * ]
 *
 * [
 *   'host' => '@',
 *   'type' => 'MX',
 *   'address' => 'mail.example.com.',
 *   'ttl' => 300,
 *   'mxpref' => 10,
 * ]
 */
public function setHosts(string $sld, string $tld, array $hosts): array
{
    $sld = strtolower(trim($sld));
    $tld = ltrim(strtolower(trim($tld)), '.');

    if ($sld === '' || $tld === '') {
        throw new RuntimeException('Namecheap: bad SLD/TLD');
    }

    $params = [
        'SLD' => $sld,
        'TLD' => strtoupper($tld),
    ];

    $i = 1;

    foreach ($hosts as $h) {
        if (!is_array($h)) continue;

        $host = trim((string)($h['host'] ?? ''));
        $type = strtoupper(trim((string)($h['type'] ?? '')));
        $addr = trim((string)($h['address'] ?? ''));
        $ttl  = (int)($h['ttl'] ?? 300);

        // Не отправляем мусор
        if ($host === '' || $type === '' || $addr === '') continue;
        if ($ttl <= 0) $ttl = 300;

        $params['HostName'   . $i] = $host;
        $params['RecordType' . $i] = $type;
        $params['Address'    . $i] = $addr;
        $params['TTL'        . $i] = (string)$ttl;

        // MXPref только для MX
        if ($type === 'MX') {
            // Namecheap ожидает MXPrefN для MX-записей
            if (array_key_exists('mxpref', $h) && $h['mxpref'] !== null && $h['mxpref'] !== '') {
                $params['MXPref' . $i] = (string)(int)$h['mxpref'];
            } else {
                // дефолтный приоритет, если не задан
                $params['MXPref' . $i] = '10';
            }
        }

        $i++;
    }

    if ($i === 1) {
        throw new RuntimeException('Namecheap: setHosts called with empty/invalid hosts array');
    }

    return $this->request('namecheap.domains.dns.setHosts', $params);
}



    // ---------------- internals ----------------

    private function request(string $command, array $params): array
    {
        $query = array_merge([
            'ApiUser'  => $this->apiUser,
            'ApiKey'   => $this->apiKey,
            'UserName' => $this->username,
            'ClientIp' => $this->clientIp,
            'Command'  => $command,
        ], $params);

        $url = $this->endpoint . '?' . http_build_query($query);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        $resp = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($resp === false) {
            throw new RuntimeException('Namecheap curl error: ' . $err);
        }
        if ($code >= 400) {
            throw new RuntimeException("Namecheap HTTP $code: " . substr((string)$resp, 0, 800));
        }

        $xml = @simplexml_load_string((string)$resp);
        if (!$xml) {
            throw new RuntimeException('Namecheap: bad XML response: ' . substr((string)$resp, 0, 800));
        }

        $arr = $this->xmlToArray($xml);

        $status = (string)($arr['@Status'] ?? '');
        if (strtoupper($status) !== 'OK') {
            $errMsg = $this->extractErrors($arr);
            throw new RuntimeException('Namecheap error: ' . $errMsg);
        }

        $maybeErr = $this->extractErrors($arr);
        if ($maybeErr !== '') {
            throw new RuntimeException('Namecheap error: ' . $maybeErr);
        }

        $cr = $arr['CommandResponse'] ?? null;
        return is_array($cr) ? $cr : $arr;
    }

    private function extractErrors(array $arr): string
    {
        $errors = $arr['Errors'] ?? null;
        if (!$errors) return '';

        $e = $errors['Error'] ?? null;
        if (!$e) return '';

        if (is_string($e)) return trim($e);

        if (is_array($e)) {
            $msgs = [];
            foreach ($e as $v) {
                if (is_string($v)) $msgs[] = trim($v);
                elseif (is_array($v) && isset($v['_'])) $msgs[] = trim((string)$v['_']);
            }
            $out = trim(implode('; ', array_filter($msgs)));
            return $out !== '' ? $out : json_encode($e, JSON_UNESCAPED_UNICODE);
        }

        return '';
    }

    private function normalizeDomain(string $domain): string
    {
        $domain = preg_replace('~^https?://~i', '', trim($domain));
        $domain = rtrim($domain, '/');
        return strtolower($domain);
    }

    public function splitSldTld(string $domain): array
    {
        $domain = $this->normalizeDomain($domain);
        $parts = explode('.', $domain);
        if (count($parts) < 2) {
            throw new RuntimeException('Namecheap: cannot split domain: ' . $domain);
        }

        $tld = array_pop($parts);
        $sld = implode('.', $parts);

        return [$sld, $tld];
    }

    private function xmlToArray(\SimpleXMLElement $xml): array
    {
        $arr = [];

        foreach ($xml->attributes() as $k => $v) {
            $arr['@' . $k] = (string)$v;
        }

        foreach ($xml->children() as $child) {
            $name  = $child->getName();
            $value = $this->xmlToArray($child);

            $text = trim((string)$child);
            if ($text !== '' && count($child->children()) === 0) {
                $value['_'] = $text;
            }

            if (!isset($arr[$name])) {
                $arr[$name] = $value;
            } else {
                if (!is_array($arr[$name]) || !isset($arr[$name][0])) {
                    $arr[$name] = [$arr[$name]];
                }
                $arr[$name][] = $value;
            }
        }

        return $arr;
    }

    private function firstItem($val): array
    {
        if (is_array($val) && isset($val[0]) && is_array($val[0])) return $val[0];
        return is_array($val) ? $val : [];
    }

    private function toBool($v): bool
    {
        $s = strtolower((string)$v);
        return ($s === 'true' || $s === '1' || $s === 'yes');
    }

    private function deepFind(array $arr, string $key): array
    {
        $found = [];

        $walk = function($node) use (&$found, &$walk, $key) {
            if (!is_array($node)) return;

            foreach ($node as $k => $v) {
                if ($k === $key) {
                    if (is_array($v) && isset($v[0])) {
                        foreach ($v as $vv) $found[] = $vv;
                    } else {
                        $found[] = $v;
                    }
                }
                if (is_array($v)) $walk($v);
            }
        };

        $walk($arr);

        return $found;
    }
	
public function getHosts(string $sld, string $tld): array
{
    $sld = strtolower(trim($sld));
    $tld = ltrim(strtolower(trim($tld)), '.');

    $resp = $this->request('namecheap.domains.dns.getHosts', [
        'SLD' => $sld,
        'TLD' => strtoupper($tld),
    ]);

    $r = $resp['DomainDNSGetHostsResult'] ?? null;
    if (!is_array($r)) {
        throw new RuntimeException('Namecheap: no DomainDNSGetHostsResult');
    }

    $raw = $r['host'] ?? [];
    if (!is_array($raw) || empty($raw)) return [];

    // host может быть либо списком, либо одиночным assoc
    $items = (isset($raw[0]) && is_array($raw[0])) ? $raw : [$raw];

    $out = [];

    foreach ($items as $h) {
        if (!is_array($h)) continue;

        // ВАЖНО: xmlToArray кладет атрибуты как @Name @Type @Address @TTL @MXPref
        $host = trim((string)($h['@Name'] ?? $h['@HostName'] ?? $h['host'] ?? $h['HostName'] ?? ''));
        $type = strtoupper(trim((string)($h['@Type'] ?? $h['@RecordType'] ?? $h['type'] ?? $h['RecordType'] ?? '')));
        $addr = trim((string)($h['@Address'] ?? $h['address'] ?? $h['Address'] ?? ''));

        $ttlRaw = $h['@TTL'] ?? $h['ttl'] ?? $h['TTL'] ?? 300;
        $ttl = (int)$ttlRaw;
        if ($ttl <= 0) $ttl = 300;

        if ($host === '' || $type === '' || $addr === '') continue;

        $row = [
            'host'    => $host,
            'type'    => $type,
            'address' => $addr,
            'ttl'     => $ttl,
        ];

        if ($type === 'MX') {
            $mxRaw = $h['@MXPref'] ?? $h['mxpref'] ?? $h['MXPref'] ?? null;
            if ($mxRaw !== null && $mxRaw !== '') {
                $row['mxpref'] = (int)$mxRaw;
            }
        }

        $out[] = $row;
    }

    return $out;
}





}

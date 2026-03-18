<?php

class PartnerSubIdService
{
    public function normalizeLabel(?string $label): string
    {
        $label = strtolower(trim((string)$label));
        if ($label === '' || $label === '_default') {
            return '_default';
        }
        $label = preg_replace('~[^a-z0-9\-]+~', '', $label);
        $label = trim((string)$label, '-');
        return $label !== '' ? $label : '_default';
    }

    public function extractRootDomainPart(string $domain): string
    {
        $domain = strtolower(trim($domain));
        $domain = preg_replace('~^https?://~i', '', $domain);
        $domain = preg_replace('~[/?#].*$~', '', $domain);
        $domain = preg_replace('~:\d+$~', '', $domain);
        $parts = explode('.', $domain);
        $root = trim((string)($parts[0] ?? ''));
        $root = preg_replace('~[^a-z0-9]+~', '', $root);
        return $root;
    }

    public function buildSubId(string $domain, ?string $label = '_default'): string
    {
        $root = $this->extractRootDomainPart($domain);
        $label = $this->normalizeLabel($label);
        if ($label === '_default') {
            return $root;
        }
        return $label . $root;
    }

    public function applySubIdToUrl(string $url, string $domain, ?string $label = '_default'): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }
        if (!preg_match('~^https?://~i', $url)) {
            return $url;
        }

        $subId = $this->buildSubId($domain, $label);
        if ($subId === '') {
            return $url;
        }

        $parts = @parse_url($url);
        if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return $url;
        }

        $query = [];
        if (!empty($parts['query'])) {
            parse_str((string)$parts['query'], $query);
        }
        $query['sub_id'] = $subId;
        $parts['query'] = http_build_query($query, '', '&', PHP_QUERY_RFC3986);

        return $this->unparseUrl($parts);
    }

    public function applyToConfigUrls(array $cfg, string $domain, ?string $label = '_default'): array
    {
        foreach (['partner_override_url', 'internal_reg_url', 'base_new_url', 'base_second_url'] as $field) {
            $cfg[$field] = $this->applySubIdToUrl((string)($cfg[$field] ?? ''), $domain, $label);
        }
        return $cfg;
    }

    private function unparseUrl(array $parts): string
    {
        $scheme   = isset($parts['scheme']) ? $parts['scheme'] . '://' : '';
        $user     = $parts['user'] ?? '';
        $pass     = isset($parts['pass']) ? ':' . $parts['pass'] : '';
        $auth     = ($user !== '' || $pass !== '') ? $user . $pass . '@' : '';
        $host     = $parts['host'] ?? '';
        $port     = isset($parts['port']) ? ':' . $parts['port'] : '';
        $path     = $parts['path'] ?? '';
        $query    = isset($parts['query']) && $parts['query'] !== '' ? '?' . $parts['query'] : '';
        $fragment = isset($parts['fragment']) ? '#' . $parts['fragment'] : '';
        return $scheme . $auth . $host . $port . $path . $query . $fragment;
    }
}

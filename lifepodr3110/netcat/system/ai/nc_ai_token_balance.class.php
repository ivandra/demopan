<?php

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class nc_ai_token_balance {

    public const API_URL = 'https://netcat.ru/api';

    private Client $http;

    public function __construct(Client $http) {
        $this->http = $http;
    }

    /**
     * @throws nc_ai_token_balance_exception
     */
    public function get_balance(): int {
        return $this->get_balance_info()['balance'] ?? 0;
    }

    /**
     * @throws nc_ai_token_balance_exception
     */
    public function get_balance_info(): array {
        return (array)$this->make_get_request('/v1/ai/balance/info')['data'];
    }

    /**
     * @throws nc_ai_token_balance_exception
     */
    private function make_get_request(string $url, array $parameters = []): array {
        return $this->make_request($url, 'GET', $parameters);
    }

    /**
     * @throws nc_ai_token_balance_exception
     */
    private function make_request(string $url, string $method = 'GET', array $parameters = []): array {
        $nc_core = nc_core::get_object();

        try {
            $url = (defined('NC_SAAS_API_URL_PREFIX') ? NC_SAAS_API_URL_PREFIX : self::API_URL) . $url;

            $parameters += [
                'copy_id' => $nc_core->get_copy_id(),
                'product_number' => $nc_core->get_settings('ProductNumber'),
                'code' => $nc_core->get_settings('Code'),
                'domains' => $nc_core->catalogue->get_all_domains(),
                'language' => $nc_core->lang->detect_lang(1),
                'version' => $nc_core->get_full_version_number(),
                'edition' => $nc_core->get_edition_name(),
            ];

            $response = strtolower($method) === 'get'
                ? $this->http->get($url, ['query' => $parameters, 'verify' => false])
                : $this->http->post($url, ['form_params' => $parameters, 'verify' => false]);

            return json_decode($response->getBody()->getContents(), true) ?? [];
        } catch (Throwable $e) {
            if ($e instanceof RequestException && $e->hasResponse()) {
                $error_data = json_decode($e->getResponse()->getBody()->getContents(), true);

                throw new nc_ai_token_balance_exception(
                    $error_data['message'] ?? $e->getMessage(),
                    $error_data['code'] ?? $e->getCode(),
                    $e
                );
            }

            throw new nc_ai_token_balance_exception($e->getMessage(), $e->getCode(), $e);
        }
    }
}

<?php

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\ResponseInterface;

class nc_ai_generation_api {
    public const CONTENT_GENERATION_API_URL = 'https://ai.sitemanager.ru/api/v1/ai/generation';

    private Client $client;

    public function __construct(Client $client) {
        $this->client = $client;
    }

    /**
     * @throws nc_ai_content_generation_exception
     */
    public function generate_page_content(array $payload): array {
        return $this->send_generation_request('/page', $payload, false, 'form_params');
    }

    /**
     * @throws nc_ai_content_generation_exception
     */
    public function generate_images(array $payload): array {
        return $this->send_generation_request('/image', $payload, true, 'form_params');
    }

    /**
     * @throws nc_ai_content_generation_exception
     */
    private function send_generation_request(
        string $endpoint,
        array $payload,
        bool $omit_scheme_in_domain,
        string $payload_key
    ): array {
        try {
            $request_payload = array_merge(
                $this->get_required_request_parameters($omit_scheme_in_domain),
                $payload
            );

            $options = [
                $payload_key => $request_payload,
                'verify' => false,
                'timeout' => 600, // 10 минут для AI-генерации
            ];

            $response = $this->client->post(
                self::CONTENT_GENERATION_API_URL . $endpoint,
                $options
            );

            return $this->get_response_json_content($response);
        } catch (Throwable $e) {
            if ($e instanceof RequestException && $e->hasResponse()) {
                $error_data = json_decode($e->getResponse()->getBody()->getContents(), true);

                throw new nc_ai_content_generation_exception(
                    $error_data['message'] ?? $e->getMessage(),
                    $error_data['code'] ?? $e->getCode(),
                    $e,
                    $error_data['details'] ?? []
                );
            }

            throw new nc_ai_content_generation_exception($e->getMessage(), $e->getCode(), $e);
        }
    }

    private function get_required_request_parameters(bool $omit_scheme_in_domain = false): array {
        $nc_core = nc_core::get_object();
        $domain = $nc_core->catalogue->get_current('Domain');
        $scheme = nc_get_scheme();
        $product_number = $nc_core->get_settings('ProductNumber') ?: $nc_core->get_copy_id();

        return [
            'site_id' => $nc_core->catalogue->get_current('Catalogue_ID'),
            'copy_id' => $nc_core->get_copy_id(),
            'product_number' => $product_number,
            'domain' => $omit_scheme_in_domain ? $domain : $scheme . '://' . $domain,
            'cms_version' => $nc_core->get_full_version_number(),
        ];
    }

    private function get_response_json_content(ResponseInterface $response): array {
        return json_decode($response->getBody()->getContents() ?? '[]', true) ?? [];
    }
}

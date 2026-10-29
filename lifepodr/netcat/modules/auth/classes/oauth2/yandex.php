<?php
/**
 * OAuth2 провайдер для Яндекс ID
 *
 * @see https://yandex.ru/dev/id/doc/ru/
 * @package nc_auth
 */

class nc_auth_oauth2_yandex extends nc_auth_oauth2_provider {

    protected $provider_id = 'yandex';

    protected $authorize_url = 'https://oauth.yandex.ru/authorize';
    protected $token_url = 'https://oauth.yandex.ru/token';
    protected $userinfo_url = 'https://login.yandex.ru/info';

    protected $default_scope = 'login:email login:info';

    /**
     * @inheritDoc
     */
    public function get_client_id(): string {
        return $this->get_setting('client_id', '');
    }

    /**
     * @inheritDoc
     */
    public function get_client_secret(): string {
        return $this->get_setting('client_secret', '');
    }

    /**
     * @inheritDoc
     */
    public function get_display_name(): string {
        return defined('NETCAT_MODULE_AUTH_ADMIN_OAUTH2_YANDEX')
            ? NETCAT_MODULE_AUTH_ADMIN_OAUTH2_YANDEX
            : 'Яндекс ID';
    }

    /**
     * Получить профиль пользователя
     * Яндекс использует OAuth Token в query string, а не в header
     */
    public function get_user_profile(string $access_token): array {
        $response = $this->http_get($this->userinfo_url, [
            'oauth_token' => $access_token,
            'format' => 'json',
        ]);

        if (isset($response['error'])) {
            throw new Exception($response['error_description'] ?? $response['error']);
        }

        return $this->normalize_user_profile($response);
    }

    /**
     * @inheritDoc
     */
    protected function normalize_user_profile(array $response): array {
        return [
            'id' => $response['id'],
            'email' => $response['default_email'] ?? ($response['emails'][0] ?? ''),
            'name' => trim(($response['first_name'] ?? '') . ' ' . ($response['last_name'] ?? '')),
            'first_name' => $response['first_name'] ?? '',
            'last_name' => $response['last_name'] ?? '',
        ];
    }
}

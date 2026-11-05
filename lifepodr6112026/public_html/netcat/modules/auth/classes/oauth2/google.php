<?php
/**
 * OAuth2 провайдер для Google
 *
 * @see https://developers.google.com/identity/protocols/oauth2
 * @package nc_auth
 */

class nc_auth_oauth2_google extends nc_auth_oauth2_provider {

    protected $provider_id = 'google';

    protected $authorize_url = 'https://accounts.google.com/o/oauth2/v2/auth';
    protected $token_url = 'https://oauth2.googleapis.com/token';
    protected $userinfo_url = 'https://www.googleapis.com/oauth2/v2/userinfo';

    protected $default_scope = 'openid email profile';

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
        return defined('NETCAT_MODULE_AUTH_ADMIN_OAUTH2_GOOGLE')
            ? NETCAT_MODULE_AUTH_ADMIN_OAUTH2_GOOGLE
            : 'Google';
    }

    /**
     * @inheritDoc
     */
    public function get_authorization_url(array $params = []): string {
        $default_params = [
            'response_type' => 'code',
            'client_id' => $this->get_client_id(),
            'redirect_uri' => $this->get_callback_url(),
            'state' => $this->generate_state(),
            'scope' => $this->default_scope,
            'access_type' => 'offline', // Для получения refresh_token
            'prompt' => 'consent', // Всегда показывать consent screen для refresh_token
        ];

        $params = array_merge($default_params, $params);

        return $this->authorize_url . '?' . http_build_query($params);
    }

    /**
     * @inheritDoc
     */
    protected function normalize_user_profile(array $response): array {
        return [
            'id' => $response['id'],
            'email' => $response['email'] ?? '',
            'name' => $response['name'] ?? '',
            'first_name' => $response['given_name'] ?? '',
            'last_name' => $response['family_name'] ?? '',
        ];
    }
}

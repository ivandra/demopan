<?php
/**
 * OAuth2 провайдер для VK ID
 *
 * @see https://id.vk.com/about/business/go/docs/ru/vkid/latest/vk-id/connection/api-integration/api-description
 * @package nc_auth
 */

class nc_auth_oauth2_vkid extends nc_auth_oauth2_provider {

    protected $provider_id = 'vkid';

    protected $authorize_url = 'https://id.vk.com/authorize';
    protected $token_url = 'https://id.vk.com/oauth2/auth';
    protected $userinfo_url = 'https://id.vk.com/oauth2/user_info';

    protected $default_scope = 'email';

    /** @var string PKCE code verifier */
    protected $code_verifier;

    /**
     * @inheritDoc
     */
    public function get_client_id(): string {
        return $this->get_setting('app_id', '');
    }

    /**
     * @inheritDoc
     */
    public function get_client_secret(): string {
        return $this->get_setting('secure_key', '');
    }

    /**
     * @inheritDoc
     */
    public function get_display_name(): string {
        return defined('NETCAT_MODULE_AUTH_ADMIN_OAUTH2_VKID')
            ? NETCAT_MODULE_AUTH_ADMIN_OAUTH2_VKID
            : 'VK ID';
    }

    /**
     * Генерация code_verifier для PKCE
     */
    protected function generate_code_verifier(): string {
        $random = bin2hex(random_bytes(32));
        return rtrim(strtr(base64_encode($random), '+/', '-_'), '=');
    }

    /**
     * Генерация code_challenge из code_verifier
     */
    protected function generate_code_challenge(string $verifier): string {
        $hash = hash('sha256', $verifier, true);
        return rtrim(strtr(base64_encode($hash), '+/', '-_'), '=');
    }

    /**
     * @inheritDoc
     */
    public function get_authorization_url(array $params = []): string {
        // Генерируем PKCE
        $this->code_verifier = $this->generate_code_verifier();
        $code_challenge = $this->generate_code_challenge($this->code_verifier);

        // Сохраняем code_verifier в сессию
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $_SESSION['nc_oauth2_vkid_verifier'] = $this->code_verifier;

        $default_params = [
            'response_type' => 'code',
            'client_id' => $this->get_client_id(),
            'redirect_uri' => $this->get_callback_url(),
            'state' => $this->generate_state(),
            'scope' => $this->default_scope,
            'code_challenge' => $code_challenge,
            'code_challenge_method' => 's256',
        ];

        $params = array_merge($default_params, $params);

        return $this->authorize_url . '?' . http_build_query($params);
    }

    /**
     * @inheritDoc
     */
    public function exchange_code_for_token(string $code): array {
        // Получаем code_verifier из сессии
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $code_verifier = $_SESSION['nc_oauth2_vkid_verifier'] ?? '';
        unset($_SESSION['nc_oauth2_vkid_verifier']);

        // VK ID возвращает device_id в callback - используем его
        $device_id = $_GET['device_id'] ?? '';

        $params = [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'client_id' => $this->get_client_id(),
            'redirect_uri' => $this->get_callback_url(),
            'code_verifier' => $code_verifier,
            'device_id' => $device_id,
            'state' => $_GET['state'] ?? '',
        ];

        $response = $this->http_post($this->token_url, $params);

        if (isset($response['error'])) {
            throw new Exception($response['error_description'] ?? $response['error']);
        }

        return $response;
    }

    /**
     * @inheritDoc
     */
    public function get_user_profile(string $access_token): array {
        $params = [
            'access_token' => $access_token,
            'client_id' => $this->get_client_id(),
        ];

        $response = $this->http_post($this->userinfo_url, $params);

        if (isset($response['error'])) {
            throw new Exception($response['error_description'] ?? $response['error']);
        }

        // VK ID возвращает данные в поле user
        $user_data = $response['user'] ?? $response;

        return $this->normalize_user_profile($user_data);
    }

    /**
     * @inheritDoc
     */
    protected function normalize_user_profile(array $response): array {
        return [
            'id' => $response['user_id'] ?? $response['id'] ?? '',
            'email' => $response['email'] ?? '',
            'name' => trim(($response['first_name'] ?? '') . ' ' . ($response['last_name'] ?? '')),
            'first_name' => $response['first_name'] ?? '',
            'last_name' => $response['last_name'] ?? '',
        ];
    }
}

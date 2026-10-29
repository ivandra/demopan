<?php

use GuzzleHttp\Client;
use Psr\Http\Message\ResponseInterface;

class nc_ai_image_generation_controller extends nc_ui_controller {

    protected $use_layout = false;

    private const DEFAULT_MAX_IMAGES = 4;
    private const MAX_IMAGES_LIMIT = 10;

    private nc_ai_generation_api $generation_api;
    private Client $http_client;
    private ?string $stub_image_public_path = null;

    public function __construct(
        ?string $view_path = null,
        ?nc_ai_generation_api $generation_api = null,
        ?Client $http_client = null
    ) {
        $nc_core = nc_core::get_object();

        if (!$nc_core->admin_mode) {
            die($this->json_error('Insufficient permissions', 403));
        }

        parent::__construct($nc_core->ADMIN_FOLDER . '/views/ai/image');
        $this->generation_api = $generation_api ?? new nc_ai_generation_api(new Client());
        $this->http_client = $http_client ?? new Client(['verify' => false]);
    }

    protected function action_show_dialog(): nc_ui_view {
        $payload = array(
            'upload_id' => (string)$this->input->fetch_get('upload_id'),
            'max_images' => $this->get_max_images_from_request(),
        );

        return $this->view('image_generation_dialog', array('payload' => $payload));
    }

    protected function action_generate(): string {
        $prompt = trim((string)$this->input->fetch_post('prompt'));
        if ($prompt === '') {
            return $this->json_error(
                NETCAT_AI_IMAGE_GENERATION_PROMPT_REQUIRED,
                0,
                array(),
                nc_http::HTTP_STATUS_BAD_REQUEST
            );
        }

        $images_count = $this->normalize_images_count(
            (int)$this->input->fetch_post('images_count')
        );

        try {
            $response = $this->generation_api->generate_images(
                array(
                    'prompt' => $prompt,
                    'images_count' => $images_count,
                )
            );

            if (empty($response['success'])) {
                $message = strip_tags((string)nc_array_value($response, 'message', NETCAT_AI_IMAGE_GENERATION_GENERAL_ERROR));
                throw new nc_ai_content_generation_exception($message);
            }

            $data = nc_array_value($response, 'data', array());

            return $this->json_success($data);
        } catch (nc_ai_content_generation_exception $e) {
            if ($this->should_use_stub_images()) {
                return $this->json_success($this->get_stub_generation_result($prompt, $images_count));
            }

            return $this->json_error(
                strip_tags($e->getMessage()),
                $e->getCode(),
                $e->get_details() ?? array(),
                nc_http::HTTP_STATUS_BAD_REQUEST
            );
        } catch (\Throwable $e) {
            if ($this->should_use_stub_images()) {
                return $this->json_success($this->get_stub_generation_result($prompt, $images_count));
            }

            return $this->json_error(strip_tags($e->getMessage()), $e->getCode());
        }
    }

    protected function action_download(): string {
        $image_url = trim((string)$this->input->fetch_post('image_url'));

        if ($image_url === '' || strpos($image_url, AI_SITE_CONTENT_GENERATION_URL . '/') !== 0) {
            return $this->json_error(
                NETCAT_AI_IMAGE_GENERATION_DOWNLOAD_ERROR,
                0,
                array(),
                nc_http::HTTP_STATUS_BAD_REQUEST
            );
        }

        try {
            $file_info = $this->download_image($image_url);

            return $this->json_success($file_info);
        } catch (\Throwable $e) {
            return $this->json_error(
                NETCAT_AI_IMAGE_GENERATION_DOWNLOAD_ERROR,
                $e->getCode(),
                array('details' => $e->getMessage()),
                nc_http::HTTP_STATUS_BAD_REQUEST
            );
        }
    }

    private function get_max_images_from_request(): int {
        $max_images = (int)$this->input->fetch_get_post('max_images');
        if ($max_images <= 0) {
            $max_images = self::DEFAULT_MAX_IMAGES;
        }

        return min(self::MAX_IMAGES_LIMIT, max(1, $max_images));
    }

    private function normalize_images_count(int $count): int {
        $max_images = $this->get_max_images_from_request();
        if ($count <= 0) {
            $count = self::DEFAULT_MAX_IMAGES;
        }

        return min($max_images, max(1, min(self::MAX_IMAGES_LIMIT, $count)));
    }

    private function should_use_stub_images(): bool {
        $nc_core = nc_core::get_object();
        if ($nc_core->get_copy_id()) {
            return false;
        }

        return (bool)$this->get_stub_image_public_path();
    }

    private function get_stub_generation_result(string $prompt, int $images_count): array {
        $nc_core = nc_core::get_object();
        $public_path = $this->get_stub_image_public_path();

        $absolute_path = $public_path
            ? $nc_core->DOCUMENT_ROOT . $public_path
            : null;

        $size = ($absolute_path && file_exists($absolute_path)) ? @getimagesize($absolute_path) : array(1024, 768);
        $image_width = $size[0] ?? 1024;
        $image_height = $size[1] ?? 768;

        $image_url = $public_path ? $this->build_public_url($public_path) : '';

        $images = array();
        for ($i = 0; $i < $images_count; $i++) {
            $images[] = array(
                'url' => $image_url,
                'width' => $image_width,
                'height' => $image_height,
                'prompt' => $prompt,
                'description' => NETCAT_AI_IMAGE_GENERATION_STUB_DESCRIPTION,
                'index' => $i,
            );
        }

        return array(
            'request_id' => $this->generate_request_id(),
            'images' => $images,
        );
    }

    private function get_stub_image_public_path(): string {
        if ($this->stub_image_public_path !== null) {
            return $this->stub_image_public_path;
        }

        $nc_core = nc_core::get_object();
        $candidates = array(
            $nc_core->SUB_FOLDER . $nc_core->HTTP_ROOT_PATH . 'modules/saas_management/tpl/template/saas/assets/images/business-mock-image.png',
            $nc_core->SUB_FOLDER . $nc_core->HTTP_ROOT_PATH . 'admin/skins/v5/img/transparent-100x100.png',
        );

        foreach ($candidates as $path) {
            if ($path && file_exists($nc_core->DOCUMENT_ROOT . $path)) {
                $this->stub_image_public_path = $path;
                return $this->stub_image_public_path;
            }
        }

        $this->stub_image_public_path = '';
        return $this->stub_image_public_path;
    }

    private function build_public_url(string $public_path): string {
        if (!$public_path) {
            return '';
        }

        $nc_core = nc_core::get_object();
        $domain = $nc_core->catalogue->get_current('Domain');

        if (!$domain) {
            $domain = $_SERVER['HTTP_HOST'] ?? 'localhost';
        }

        return nc_get_scheme() . '://' . $domain . $public_path;
    }

    private function generate_request_id(): string {
        try {
            return bin2hex(random_bytes(8));
        } catch (\Throwable $e) {
            return uniqid('', true);
        }
    }

    /**
     * @throws Throwable
     */
    private function download_image(string $url): array {
        /** @var ResponseInterface $response */
        $response = $this->http_client->get($url, array(
            'http_errors' => false,
            'timeout' => 60,
            'verify' => false,
        ));

        $status = $response->getStatusCode();
        if ($status < 200 || $status >= 300) {
            throw new \RuntimeException("Unexpected status code {$status}");
        }

        $contents = $response->getBody()->getContents();
        if ($contents === '') {
            throw new \RuntimeException('Empty image body received');
        }

        $nc_core = nc_core::get_object();

        $mime_type = $response->getHeaderLine('Content-Type') ?: 'application/octet-stream';
        $extension = $this->guess_extension($mime_type, $url);
        $file_name = 'ai-image-' . uniqid('', true) . $extension;

        $absolute_path = $nc_core->TMP_FOLDER . $file_name;

        file_put_contents($absolute_path, $contents);
        @chmod($absolute_path, $nc_core->FILECHMOD);

        $relative_path = $nc_core->SUB_FOLDER . $nc_core->HTTP_TMP_PATH . $file_name;

        return array(
            'path' => $relative_path,
            'name' => $file_name,
            'mime' => $mime_type,
            'size' => strlen($contents),
        );
    }

    private function guess_extension(string $mime, string $url): string {
        $map = array(
            'image/jpeg' => '.jpg',
            'image/png' => '.png',
            'image/webp' => '.webp',
            'image/gif' => '.gif',
        );

        if (isset($map[$mime])) {
            return $map[$mime];
        }

        $path = parse_url($url, PHP_URL_PATH);
        $ext = $path ? pathinfo($path, PATHINFO_EXTENSION) : '';

        return $ext ? '.' . $ext : '.png';
    }
}

<?php
/**
 * Класс для работы с видео из различных источников
 * Поддерживает: YouTube, Vimeo, VK, Rutube, TikTok, Likee и прямые ссылки на видеофайлы
 */
class nc_video_embed {

    /**
     * Исходный URL видео
     * @var string
     */
    protected $url = '';

    /**
     * Идентификатор провайдера (youtube, vimeo, vk, rutube, tiktok, likee, direct)
     * @var string|null
     */
    protected $provider = null;

    /**
     * ID видео на платформе
     * @var string|null
     */
    protected $video_id = null;

    /**
     * Разрешённые расширения для прямых видеофайлов
     * @var array
     */
    protected static $allowed_video_extensions = array('mp4', 'webm', 'ogg', 'mov', 'm4v');

    /**
     * Паттерны для определения провайдеров
     * @var array
     */
    protected static $provider_patterns = array(
        'youtube' => array(
            '#(?:youtube\.com/(?:watch\?v=|embed/|v/|shorts/)|youtu\.be/)([a-zA-Z0-9_-]{11})#i',
        ),
        'vimeo' => array(
            '#vimeo\.com/(?:video/)?(\d+)#i',
            '#player\.vimeo\.com/video/(\d+)#i',
        ),
        'vk' => array(
            '#vk\.com/video(-?\d+_\d+)#i',
            '#vk\.com/video_ext\.php\?oid=(-?\d+)&id=(\d+)#i',
            '#vkvideo\.ru/video(-?\d+_\d+)#i',
        ),
        'rutube' => array(
            '#rutube\.ru/video/([a-f0-9]+)#i',
            '#rutube\.ru/play/embed/(\d+)#i',
        ),
        'tiktok' => array(
            '#tiktok\.com/@[^/]+/video/(\d+)#i',
        ),
        'likee' => array(
            '#likee\.video/@[^/]+/video/(\d+)#i',
            '#l\.likee\.video/v/(\w+)#i',
        ),
    );

    /**
     * Конструктор
     * @param string $url URL видео
     */
    public function __construct($url = '') {
        $this->url = trim($url);
        $this->parse_url();
    }

    /**
     * Фабричный метод для создания объекта
     * @param string $url
     * @return static
     */
    public static function from_url($url) {
        return new static($url);
    }

    /**
     * Парсинг URL и определение провайдера
     */
    protected function parse_url() {
        if (empty($this->url)) {
            return;
        }

        // Проверка на прямую ссылку на видеофайл
        if ($this->is_direct_video_url()) {
            $this->provider = 'direct';
            $this->video_id = $this->url;
            return;
        }

        // Проверка провайдеров
        foreach (self::$provider_patterns as $provider => $patterns) {
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $this->url, $matches)) {
                    $this->provider = $provider;
                    // Для VK может быть два варианта формата
                    if ($provider === 'vk' && isset($matches[2])) {
                        $this->video_id = $matches[1] . '_' . $matches[2];
                    } else {
                        $this->video_id = $matches[1];
                    }
                    return;
                }
            }
        }
    }

    /**
     * Проверка валидности URL
     * @return bool
     */
    public function is_valid() {
        return !empty($this->provider) && !empty($this->video_id);
    }

    /**
     * Это видео с видеохостинга (требует embed iframe)?
     * @return bool
     */
    public function is_embed_video() {
        return $this->is_valid() && $this->provider !== 'direct';
    }

    /**
     * Это прямая ссылка на видеофайл?
     * @return bool
     */
    public function is_direct_video_url() {
        if (empty($this->url)) {
            return false;
        }

        $parsed_url = parse_url($this->url);
        if (!isset($parsed_url['path'])) {
            return false;
        }

        $extension = strtolower(pathinfo($parsed_url['path'], PATHINFO_EXTENSION));
        return in_array($extension, self::$allowed_video_extensions, true);
    }

    /**
     * Получить HTML код видео (автоматический выбор: iframe или video)
     * @param array $attributes Дополнительные атрибуты
     * @return string
     */
    public function get_html(array $attributes = array()) {
        if (!$this->is_valid()) {
            return '';
        }

        if ($this->provider === 'direct') {
            return $this->get_video_tag($attributes);
        }

        return $this->get_embed_code($attributes);
    }

    /**
     * Получить HTML тег <video> для прямых ссылок
     * @param array $attributes Дополнительные атрибуты
     * @return string
     */
    public function get_video_tag(array $attributes = array()) {
        if ($this->provider !== 'direct' || empty($this->video_id)) {
            return '';
        }

        $default_attributes = array(
            'controls' => true,
            'playsinline' => true,
            'preload' => 'metadata',
        );

        $attributes = array_merge($default_attributes, $attributes);

        // Определение MIME-типа
        $extension = strtolower(pathinfo($this->video_id, PATHINFO_EXTENSION));
        $mime_types = array(
            'mp4' => 'video/mp4',
            'webm' => 'video/webm',
            'ogg' => 'video/ogg',
            'mov' => 'video/quicktime',
            'm4v' => 'video/mp4',
        );
        $mime_type = isset($mime_types[$extension]) ? $mime_types[$extension] : 'video/mp4';

        $attr_string = $this->build_attributes($attributes);

        return '<video ' . $attr_string . '>' .
               '<source src="' . htmlspecialchars($this->video_id) . '" type="' . $mime_type . '">' .
               'Ваш браузер не поддерживает видео.' .
               '</video>';
    }

    /**
     * Получить embed код (iframe) для видеохостингов
     * @param array $attributes Дополнительные атрибуты iframe
     * @return string
     */
    public function get_embed_code(array $attributes = array()) {
        if (!$this->is_embed_video()) {
            return '';
        }

        $embed_url = $this->get_embed_url();
        if (empty($embed_url)) {
            return '';
        }

        $default_attributes = array(
            'frameborder' => '0',
            'allowfullscreen' => true,
            'allow' => 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture',
        );

        $attributes = array_merge($default_attributes, $attributes);
        $attributes['src'] = $embed_url;

        $attr_string = $this->build_attributes($attributes);

        return '<iframe ' . $attr_string . '></iframe>';
    }

    /**
     * Получить URL для embed (iframe src)
     * @return string
     */
    public function get_embed_url() {
        if (!$this->is_embed_video()) {
            return '';
        }

        switch ($this->provider) {
            case 'youtube':
                return 'https://www.youtube.com/embed/' . $this->video_id;

            case 'vimeo':
                return 'https://player.vimeo.com/video/' . $this->video_id;

            case 'vk':
                // VK video_id формат: oid_id
                $parts = explode('_', $this->video_id);
                if (count($parts) >= 2) {
                    return 'https://vk.com/video_ext.php?oid=' . $parts[0] . '&id=' . $parts[1];
                }
                return '';

            case 'rutube':
                return 'https://rutube.ru/play/embed/' . $this->video_id;

            case 'tiktok':
                return 'https://www.tiktok.com/embed/v2/' . $this->video_id;

            case 'likee':
                return 'https://likee.video/embed/' . $this->video_id;

            default:
                return '';
        }
    }

    /**
     * Получить URL превью видео (если доступно)
     * @param string $quality Качество превью (для YouTube: default, mqdefault, hqdefault, sddefault, maxresdefault)
     * @return string
     */
    public function get_thumbnail_url($quality = 'hqdefault') {
        if (!$this->is_valid()) {
            return '';
        }

        switch ($this->provider) {
            case 'youtube':
                return 'https://img.youtube.com/vi/' . $this->video_id . '/' . $quality . '.jpg';

            case 'vimeo':
                // Для Vimeo нужен API запрос, возвращаем пустую строку
                return '';

            default:
                return '';
        }
    }

    /**
     * Получить идентификатор провайдера
     * @return string|null
     */
    public function get_provider() {
        return $this->provider;
    }

    /**
     * Получить ID видео
     * @return string|null
     */
    public function get_video_id() {
        return $this->video_id;
    }

    /**
     * Получить исходный URL
     * @return string
     */
    public function get_url() {
        return $this->url;
    }

    /**
     * Получить список разрешённых расширений видеофайлов
     * @return array
     */
    public static function get_allowed_video_extensions() {
        return self::$allowed_video_extensions;
    }

    /**
     * Построение строки атрибутов HTML тега
     * @param array $attributes
     * @return string
     */
    protected function build_attributes(array $attributes) {
        $result = array();

        foreach ($attributes as $key => $value) {
            if ($value === true) {
                $result[] = htmlspecialchars($key);
            } elseif ($value !== false && $value !== null) {
                $result[] = htmlspecialchars($key) . '="' . htmlspecialchars($value) . '"';
            }
        }

        return implode(' ', $result);
    }

    /**
     * Преобразование к строке — возвращает HTML
     * @return string
     */
    public function __toString() {
        return $this->get_html();
    }
}

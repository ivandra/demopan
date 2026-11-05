<?php

class nc_image_provider_icon extends nc_image_provider {
    protected $library_dir = null;

    /**
     * Приоритеты библиотек (меньше = выше)
     */
    protected $library_priorities = array(
        'google_material_icons' => 1,
        'font_awesome' => 2,
        'themify_icons' => 3,
        'shopping' => 10,
        'supermarket_1' => 11,
        'supermarket_2' => 12,
        'supermarket_3' => 13,
        'supermarket_4' => 14,
        'supermarket_line_craft' => 15,
        'grocery_1' => 20,
        'grocery_2' => 21,
        'grocery_3' => 22,
        'grocery_4' => 23,
        'grocery_5' => 24,
        'candy_shop' => 30,
        'home_stuff' => 40,
        'pet_lovers' => 50,
        'pet_shop_fill' => 51,
    );

    public function __construct() {
        parent::__construct();
        $this->library_dir = $this->nc_core->DOCUMENT_ROOT . $this->nc_core->HTTP_TEMPLATE_PATH . 'icon';
    }

    public function save($dir, $library, $icon, $color) {
        // Есть ли такая библиотека?
        if (!in_array($library, $this->get_libraries_info(true))) {
            return null;
        }

        // Извлекаем SVG из compiled HTML
        $svg_content = $this->extract_svg_from_library($library, $icon);
        if (!$svg_content) {
            return null;
        }

        // Создаем директорию если нужно
        if (!file_exists($dir)) {
            mkdir($dir, nc_core::get_object()->DIRCHMOD, true);
        }

        // Сохраняем SVG файл
        $icon_path = $dir . '/' . $icon . '.svg';
        file_put_contents($icon_path, $svg_content);

        // Внедряем data-атрибуты и цвет
        $this->svg_file_inject_data($icon_path, $library, $icon, $color);

        return $icon_path;
    }

    /**
     * Извлечение SVG иконки из HTML файла библиотеки
     */
    private function extract_svg_from_library($library, $icon) {
        $html_path = $this->library_dir . '/' . $library . '.html';
        if (!file_exists($html_path)) {
            return null;
        }

        $html_content = file_get_contents($html_path);

        // Ищем div с нужной иконкой
        $pattern = '/<div[^>]+data-icon="' . preg_quote($icon, '/') . '"[^>]*>\s*(<svg[^>]*>.*?<\/svg>)\s*<\/div>/s';
        if (preg_match($pattern, $html_content, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }

    /**
     * Получить список всех библиотек с данными для отображения
     */
    public function get_libraries() {
        $data = array();
        $libraries = $this->get_libraries_info();

        // Сортируем по приоритету
        $sorted = array();
        foreach ($libraries as $library_keyword => $library_name) {
            $priority = isset($this->library_priorities[$library_keyword])
                ? $this->library_priorities[$library_keyword]
                : 1000;
            $sorted[] = array(
                'keyword' => $library_keyword,
                'name' => $library_name,
                'priority' => $priority
            );
        }

        usort($sorted, function($a, $b) {
            if ($a['priority'] != $b['priority']) {
                return $a['priority'] - $b['priority'];
            }
            return strcmp($a['name'], $b['name']);
        });

        foreach ($sorted as $lib) {
            $library_data = $this->get_library($lib['keyword']);
            if ($library_data) {
                $data[$lib['keyword']] = $library_data;
            }
        }

        return $data;
    }

    /**
     * Получить список библиотек (keyword => name) или только ключевые слова
     * Список формируется из HTML файлов в директории /files/netcat_template/icon/
     */
    public function get_libraries_info($only_keywords = false) {
        $result = array();
        $html_files = glob($this->library_dir . '/*.html');

        if ($html_files) {
            foreach ($html_files as $html_file) {
                $library_keyword = pathinfo($html_file, PATHINFO_FILENAME);
                $library_name = $this->get_library_name($library_keyword);
                $result[$library_keyword] = $library_name;
            }
        }

        // Сортировка по имени
        asort($result);

        if ($only_keywords) {
            $result = array_keys($result);
        }

        return $result;
    }

    /**
     * Получить данные библиотеки
     */
    public function get_library($library_keyword) {
        $html_path = $this->library_dir . '/' . $library_keyword . '.html';

        if (!file_exists($html_path)) {
            return null;
        }

        // Подсчёт количества иконок
        $html_content = file_get_contents($html_path);
        $icons_count = substr_count($html_content, ' data-icon=');

        return array(
            'name' => $this->get_library_name($library_keyword),
            'html_path' => $html_path,
            'http_path' => nc_get_http_folder($this->library_dir) . $library_keyword . '.html',
            'icons_count' => $icons_count,
        );
    }

    /**
     * Получить человекочитаемое название библиотеки из ключевого слова
     */
    private function get_library_name($keyword) {
        return implode(' ', array_map('ucfirst', explode('_', $keyword)));
    }

    /**
     * Внедрить data-атрибуты и цвет в SVG файл
     */
    protected function svg_file_inject_data($icon_file_path, $library, $icon, $color) {
        $xml = simplexml_load_file($icon_file_path);
        $xml['data-library'] = $library;
        $xml['data-icon'] = $icon;
        $xml['data-color'] = $color;
        $xml['fill'] = $color;
        $xml->saveXML($icon_file_path);
    }

    /**
     * Распарсить информацию об иконке из SVG файла
     */
    public function parse_icon_info($icon_file_path) {
        if (!file_exists($icon_file_path)) {
            return null;
        }
        $xml = @simplexml_load_file($icon_file_path);
        if (!$xml) {
            return null;
        }
        return array(
            'library' => (string)$xml['data-library'],
            'icon' => (string)$xml['data-icon'],
            'color' => (string)$xml['data-color']
        );
    }
}

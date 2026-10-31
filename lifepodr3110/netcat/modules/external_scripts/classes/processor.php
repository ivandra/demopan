<?php

/**
 * Обработчик буфера вывода для вставки внешних скриптов
 */
class nc_external_scripts_processor {

    /** @var int */
    protected $site_id;

    /** @var nc_external_scripts_snippet[] */
    protected $snippets = [];

    /** @var bool */
    protected $loaded = false;

    /**
     * @param int $site_id
     */
    public function __construct(int $site_id) {
        $this->site_id = $site_id;
    }

    /**
     * Загрузка активных сниппетов
     */
    protected function load_snippets(): void {
        if ($this->loaded) {
            return;
        }

        $rows = nc_db()->get_results(
            "SELECT * FROM `ExternalScripts_Snippet`
             WHERE `Catalogue_ID` = {$this->site_id}
               AND `Enabled` = 1
             ORDER BY `Priority` ASC",
            ARRAY_A
        );

        if ($rows) {
            foreach ($rows as $row) {
                $snippet = nc_external_scripts_snippet::factory($row);
                if ($snippet) {
                    $this->snippets[] = $snippet;
                }
            }
        }

        $this->loaded = true;
    }

    /**
     * Обработка буфера вывода
     *
     * @param string $buffer
     * @return string
     */
    public function process(string $buffer): string {
        $this->load_snippets();

        if (empty($this->snippets)) {
            return $buffer;
        }

        // Гейтинг согласия включён? (режим ConsentMode != notice)
        $gating = nc_consent::is_gating_enabled($this->site_id);
        $has_inert = false;

        // Собираем код по позициям
        $code = [
            'head_start' => '',
            'head_end' => '',
            'body_start' => '',
            'body_end' => '',
        ];

        $needs_analytics_event_script = false;

        foreach ($this->snippets as $snippet) {
            if (!$snippet->should_display()) {
                continue;
            }

            // Категория cookie сниппета. В режиме гейтинга блокируемые категории
            // (functional/analytics/marketing) выводим инертно
            // (<script type="text/plain" data-nc-consent="...">), чтобы браузер не
            // исполнял код до согласия. none/necessary не блокируются никогда.
            $category = $snippet->get_cookie_category();
            $inert = $gating && nc_consent::is_blockable($category);

            $parts = [
                'head_start' => $snippet->get_code_head_start(),
                'head_end'   => $snippet->get_code_head_end(),
                'body_start' => $snippet->get_code_body_start(),
                'body_end'   => $snippet->get_code_body_end(),
            ];

            foreach ($parts as $position => $html) {
                if ($html === '') {
                    continue;
                }
                if ($inert) {
                    $html = nc_consent::wrap_inert($html, $category);
                    $has_inert = true;
                }
                $code[$position] .= $html . "\n";
            }

            // Проверяем, нужен ли скрипт отправки событий
            if ($snippet->needs_analytics_event_script()) {
                $needs_analytics_event_script = true;
            }
        }

        // Скрипт отправки событий аналитики. Это машинерия категории analytics:
        // при гейтинге выводим его инертно, чтобы события не уходили до согласия.
        if ($needs_analytics_event_script) {
            $event_code = $this->get_analytics_event_script_code();
            if ($gating) {
                $event_code = nc_consent::wrap_inert($event_code, nc_consent::CATEGORY_ANALYTICS);
                $has_inert = true;
            }
            $code['body_end'] .= $event_code;
        }

        // Если хоть что-то выведено инертно — подключаем фронтовый рантайм согласия,
        // который активирует разрешённые скрипты после получения согласия.
        if ($has_inert) {
            $code['head_end'] .= nc_consent::runtime_script_tag() . "\n";
        }

        // Вставляем в буфер
        $buffer = $this->insert_after_tag($buffer, '<head', $code['head_start']);
        $buffer = $this->insert_before_tag($buffer, '</head>', $code['head_end']);
        $buffer = $this->insert_after_tag($buffer, '<body', $code['body_start']);
        $buffer = $this->insert_before_tag($buffer, '</body>', $code['body_end']);

        return $buffer;
    }

    /**
     * Вставка кода после открывающего тега
     *
     * @param string $buffer
     * @param string $tag
     * @param string $code
     * @return string
     */
    protected function insert_after_tag(string $buffer, string $tag, string $code): string {
        if (empty($code)) {
            return $buffer;
        }

        $pos = stripos($buffer, $tag);
        if ($pos === false) {
            return $buffer;
        }

        // Находим конец тега (символ >)
        $end_pos = strpos($buffer, '>', $pos);
        if ($end_pos === false) {
            return $buffer;
        }

        return substr($buffer, 0, $end_pos + 1) . "\n" . $code . substr($buffer, $end_pos + 1);
    }

    /**
     * Вставка кода перед закрывающим тегом
     *
     * @param string $buffer
     * @param string $tag
     * @param string $code
     * @return string
     */
    protected function insert_before_tag(string $buffer, string $tag, string $code): string {
        if (empty($code)) {
            return $buffer;
        }

        $pos = stripos($buffer, $tag);
        if ($pos === false) {
            return $buffer;
        }

        return substr($buffer, 0, $pos) . $code . "\n" . substr($buffer, $pos);
    }

    /**
     * Код подключения скрипта отправки событий аналитики
     *
     * @return string
     */
    protected function get_analytics_event_script_code(): string {
        $script_path = nc_module_path('external_scripts') . 'js/analytics_event.min.js';

        return <<<HTML
<!-- Netcat Analytics Events -->
<script>
(function(){var f=nc_stats_analytics_event=function(){f.E?f.E.push(arguments):f.E=[arguments]}})();
</script>
<script src="{$script_path}" defer></script>
<!-- /Netcat Analytics Events -->
HTML;
    }

    /**
     * Получение предпросмотра кода для админки
     *
     * @return array
     */
    public function get_preview(): array {
        $this->load_snippets();

        $preview = [
            'head_start' => '',
            'head_end' => '',
            'body_start' => '',
            'body_end' => '',
        ];

        foreach ($this->snippets as $snippet) {
            $head_start = $snippet->get_code_head_start();
            $head_end = $snippet->get_code_head_end();
            $body_start = $snippet->get_code_body_start();
            $body_end = $snippet->get_code_body_end();

            if ($head_start) {
                $preview['head_start'] .= "<!-- " . htmlspecialchars($snippet->get_name()) . " [cookie: " . $snippet->get_cookie_category() . "] -->\n" . $head_start . "\n\n";
            }
            if ($head_end) {
                $preview['head_end'] .= "<!-- " . htmlspecialchars($snippet->get_name()) . " [cookie: " . $snippet->get_cookie_category() . "] -->\n" . $head_end . "\n\n";
            }
            if ($body_start) {
                $preview['body_start'] .= "<!-- " . htmlspecialchars($snippet->get_name()) . " [cookie: " . $snippet->get_cookie_category() . "] -->\n" . $body_start . "\n\n";
            }
            if ($body_end) {
                $preview['body_end'] .= "<!-- " . htmlspecialchars($snippet->get_name()) . " [cookie: " . $snippet->get_cookie_category() . "] -->\n" . $body_end . "\n\n";
            }
        }

        return $preview;
    }
}

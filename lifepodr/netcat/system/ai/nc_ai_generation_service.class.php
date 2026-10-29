<?php

class nc_ai_generation_service {

    private nc_ai_company_context $company_context;
    private nc_ai_generation_api $api;
    private nc_content_changer_runner $instructions_runner;
    private nc_ai_blocks_library $ai_blocks_library;
    private array $subdivision_data = [];

    public function __construct(
        nc_ai_generation_api $api,
        nc_ai_company_context $company_context,
        nc_content_changer_runner $instructions_runner,
        nc_ai_blocks_library $ai_blocks_library
    ) {
        $this->api = $api;
        $this->company_context = $company_context;
        $this->instructions_runner = $instructions_runner;
        $this->ai_blocks_library = $ai_blocks_library;
    }

    protected function get_page_content(string $source_url): string {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $source_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        $content = curl_exec($ch);
        curl_close($ch);

        $dom = new DOMDocument();

        // mb_convert_encoding: такое преобразование позволяет избавиться от некорректных для UTF-8
        // последовательностей, из-за которых могут возникать неожиданные проблемы:
        @$dom->loadHTML(mb_convert_encoding($content, 'UTF-8', 'UTF-8'));

        // Удаляем все скрипты
        $scripts = $dom->getElementsByTagName('script');
        $styles = $dom->getElementsByTagName('style');

        $remove_list = [];
        foreach ($scripts as $item) {
            $remove_list[] = $item;
        }
        foreach ($styles as $item) {
            $remove_list[] = $item;
        }

        foreach ($remove_list as $item) {
            $item->parentNode->removeChild($item);
        }

        // Получаем только текстовое содержимое
        $content = $dom->textContent ?: strip_tags($content);

        // Удаляем лишние пробелы
        $content = preg_replace('/\s+/', ' ', $content);
        $content = trim($content);

        $content = mb_substr($content, 0, 32768, 'UTF-8');

        return $content;
    }

    /**
     * @return nc_content_changer_instruction[]
     * @throws nc_ai_content_generation_exception
     */
    public function get_page_changer_instructions(
        int $subdivision_id,
        string $scenario_type,
        ?string $user_prompt = null,
        ?array $settings = null
    ): array {
        $subdivision_data = $this->get_subdivision_data($subdivision_id);

        if ($settings['context_source'] === "text") {
            $got_content = mb_substr($settings['source_text'], 0, 32768, 'UTF-8');
            $rich_prompt = NETCAT_AI_RICHPROMPT_TEXT_1 . $user_prompt . NETCAT_AI_RICHPROMPT_TEXT_2 . $got_content;
        } else if ($settings['context_source'] === "generate") {
            $rich_prompt = $user_prompt;
        } else if ($settings['context_source'] === "another-page") {
            $got_content = $this->get_page_content($settings['source_url']);
            $rich_prompt = NETCAT_AI_RICHPROMPT_PAGE_1 . $user_prompt . NETCAT_AI_RICHPROMPT_PAGE_2 . $got_content;
        } else if ($settings['context_source'] === "this-page") {
            $page_view_url = nc_folder_url($subdivision_id);
            $got_content = $this->get_page_content($page_view_url);
            $rich_prompt = NETCAT_AI_RICHPROMPT_THIS_1 . $user_prompt . NETCAT_AI_RICHPROMPT_THIS_2 . $got_content;
        }

        $payload = [
            'subdivision_keyword' => $subdivision_data['EnglishName'],
            'user_prompt' => $rich_prompt ?? '',
            'page_structure' => $this->get_subdivision_map($subdivision_id),
            'context' => $this->company_context->to_array(),
            'settings' => $settings ?? [],
            'session_meta' => [
                'site_id' => $subdivision_data['Catalogue_ID'],
                'subdivision_id' => $subdivision_id,
            ],
            // 'available_blocks' => $this->get_available_library_blocks($scenario_type, $settings),
        ];

        // С версии 7.2 генерация использует единственный сценарий уникальной
        // вёрстки FREE_LANDING_ON_MULTIPAGE. Прочие сценарии блочной сборки и
        // detect_scenario_type() помечены @deprecated (см.
        // nc_ai_generation_scenario_type) и будут удалены в одной из следующих
        // версий. Значение шлём всегда — для совместимости со стороной генератора.
        $payload['scenario_type'] = nc_ai_generation_scenario_type::FREE_LANDING_ON_MULTIPAGE;

        // standalone_sitemap отправляется всегда (на будущее: генератор использует
        // карту сайта как контекст). Ранее слался только для сценария MULTIPAGE.
        $payload['standalone_sitemap'] = $this->get_subdivision_tree();

        $response = $this->api->generate_page_content($payload);

        $instructions = [];
        foreach ($response['data']['instructions'] as $i) {
            $instructions[] = unserialize($i);
        }

        return $instructions;
    }

    /**
     * @throws nc_ai_content_generation_exception
     */
    public function get_subdivision_data(int $subdivision_id) {
        try {
            if (isset($this->subdivision_data[$subdivision_id])) {
                return $this->subdivision_data[$subdivision_id];
            }

            $this->subdivision_data[$subdivision_id] = nc_core::get_object()->subdivision->get_by_id($subdivision_id);

            return $this->subdivision_data[$subdivision_id];
        } catch (Exception $e) {
            throw new nc_ai_content_generation_exception('Cannot find subdivision');
        }
    }

    /**
     * @deprecated С версии 7.2 генерация всегда использует единственный сценарий
     * FREE_LANDING_ON_MULTIPAGE, поэтому определение сценария по структуре сайта
     * больше не применяется. Метод оставлен для возможности вернуть блочную
     * генерацию малым хаком и будет удалён в одной из следующих версий.
     */
    public function detect_scenario_type(int $subdivision_id): string {
        if (nc_module_check_by_keyword('landing') && nc_landing::get_instance()->get_landing_page_id_by_subdivision_id(
                $subdivision_id
            )) {
            // Для landing-разделов по умолчанию предлагаем свободную вёрстку
            return nc_ai_generation_scenario_type::FREE_LANDING_ON_MULTIPAGE;
        }

        $site = nc_core::get_object()->catalogue->get_current();

        $has_root_level_checked_subdivisions = (bool)nc_db_table::make('Subdivision')
            ->select('Subdivision_ID')
            ->where('Catalogue_ID', $site['Catalogue_ID'])
            ->where('Checked', 1)
            ->where('Parent_Sub_ID', 0)
            ->get_result();

        if ($has_root_level_checked_subdivisions) {
            return $site['Title_Sub_ID'] == $subdivision_id
                ? nc_ai_generation_scenario_type::MULTIPAGE_MAIN_PAGE
                : nc_ai_generation_scenario_type::MULTIPAGE;
        }

        $nav = $this->get_navigation_instance();
        $nav->set_ignore_check(true);

        if ($nav->get_sub($subdivision_id)) {
            return nc_ai_generation_scenario_type::LANDING_ON_MULTIPAGE;
        }

        return nc_ai_generation_scenario_type::LANDING;
    }

    /**
     * @throws nc_ai_content_generation_exception
     */
    public function apply_generated_instructions(int $subdivision_id, array $instructions): void {
        try {
            $changeset_name = sprintf(
                'Генерация контента на странице "%s"',
                $this->get_subdivision_data($subdivision_id)['Subdivision_Name']
            );

            $version = nc_core::get_object()->version;

            $version->init_changeset($changeset_name);
            $version->prevent_reopen_changeset();
            $this->delete_content_from_page($subdivision_id);
            $this->instructions_runner->set_instructions($instructions);
            $this->instructions_runner->run();
            $version->commit_changeset();
            $version->update_changeset_description($changeset_name);
        } catch (Exception $e) {
            throw new nc_ai_content_generation_exception($e->getMessage(), $e->getCode(), $e);
        }
    }

    public function get_subdivision_tree(int $subdivision_id = 0, int $nesting_level = 1): array {
        $navigation = $this->get_navigation_instance();
        $navigation->set_ignore_check(false);
        $get_sitemap_callback = function (int $subdivision_id, int $depth) use (&$get_sitemap_callback, $navigation) {
            $tree = $navigation->as_array()->get_sub($subdivision_id);

            if (!$tree) {
                return [];
            }

            $sitemap = [];

            foreach ($tree as $subdivision) {
                if (in_array($subdivision['EnglishName'], ['library', 'promo', 'requests'])) {
                    continue;
                }

                $item = [
                    'name' => $subdivision['Subdivision_Name'],
                    'full_name' => $subdivision['ncH1'] ?? '',
                    'keyword' => $subdivision['EnglishName'],
                    'blocks' => $this->get_subdivision_library_blocks($subdivision['Subdivision_ID']),
                    'children' => [],
                ];

                if ($depth > 0) {
                    $children = $navigation->as_array()->get_sub($subdivision['Subdivision_ID']);

                    if ($children) {
                        $item['children'] = $get_sitemap_callback($subdivision['Subdivision_ID'], --$depth);
                    }
                }

                $sitemap[] = $item;
            }

            return $sitemap;
        };

        return $get_sitemap_callback($subdivision_id, $nesting_level);
    }

    public function update_company_context(array $parameters): void {
        $old_context_state = $this->company_context->to_array();

        if (!empty($parameters['company_name'])) {
            $this->company_context->set_company_name($parameters['company_name']);
        }

        if (!empty($parameters['target_audience'])) {
            $this->company_context->set_target_audience($parameters['target_audience']);
        }

        if (!empty($parameters['company_description'])) {
            $this->company_context->set_company_description($parameters['company_description']);
        }

        if (!empty($parameters['services'])) {
            $this->company_context->set_services($parameters['services']);
        }

        if (!empty($parameters['advantages'])) {
            $this->company_context->set_advantages($parameters['advantages']);
        }

        if (!empty($parameters['feelings'])) {
            $this->company_context->set_feelings($parameters['feelings']);
        }

        if ($old_context_state !== $this->company_context->to_array()) {
            $this->company_context->update();
        }
    }

    private function get_navigation_instance(): nc_nav {
        $nav = nc_nav::get_instance();
        $nav->set_site_id(nc_core::get_object()->catalogue->get_current('Catalogue_ID'));

        return $nav;
    }

    /**
     * @throws nc_ai_content_generation_exception
     */
    private function get_subdivision_map(int $subdivision_id): array {
        $subdivision = $this->get_subdivision_data($subdivision_id);

        return [
            'name' => $subdivision['Subdivision_Name'],
            'full_name' => $subdivision['ncH1'] ?? '',
            'keyword' => $subdivision['EnglishName'],
            'blocks' => $this->get_subdivision_library_blocks($subdivision_id),
        ];
    }

    /**
     * @deprecated Возвращает блоки страницы, пришедшие из библиотеки (Source_Keyword).
     * С версии 7.2 библиотека блоков не используется, поэтому без легаси-данных
     * метод возвращает пустой массив. Будет удалён в одной из следующих версий.
     */
    private function get_subdivision_library_blocks(int $subdivision_id): array {
        $blocks_by_subdivision = $this->ai_blocks_library->get_subdivision_library_blocks($subdivision_id);

        if (empty($blocks_by_subdivision)) {
            return [];
        }

        $result = [];

        foreach ($blocks_by_subdivision as $block) {
            if (!$block['Source_Keyword']) {
                continue;
            }

            $result[] = [
                'name' => $block['Sub_Class_Name'],
                'infoblock_keyword' => $block['EnglishName'],
            ];
        }

        return $result;
    }

    private function delete_content_from_page(int $subdivision_id): void {
        DeleteFromSubClass($subdivision_id);

        // Требуется дополнительное удаление осиротевших блоков, см. nc_Sub_Class(158 строка)
        nc_db_table::make('Sub_Class')->where('Subdivision_ID', $subdivision_id)->delete();
    }

    /**
     * @deprecated Список доступных блоков библиотеки для блочной сборки. С версии
     * 7.2 не используется (payload-поле available_blocks отключено). Будет удалён
     * в одной из следующих версий.
     */
    private function get_available_library_blocks(string $scenario_type, array $settings = []): array {
        $skip_by_name_regexp = '/(?:\b|_)(?:sitecover|pagecover|pagetitle)(?:\b|_)/';
        return array_filter(
            array_map(
                static function ($block) use ($settings, $skip_by_name_regexp) {
                    if ($settings['prevent_append_headers'] && preg_match($skip_by_name_regexp, $block['EnglishName'])) {
                        return null;
                    }
                    return [
                        'type' => $block['Sub_Class_Name'],
                        'keyword' => $block['EnglishName'],
                    ];
                },
                $this->ai_blocks_library->get_available_blocks($scenario_type)
            )
        );
    }

}

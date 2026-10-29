<?php


use GuzzleHttp\Client;

class nc_ai_content_generation_controller extends nc_ui_controller {

    protected $use_layout = false;
    private nc_ai_generation_service $generation_service;
    private nc_ai_company_context $company_context;
    private nc_ai_token_balance $ai_token_balance;

    private const TASK_STATUS_IN_PROGRESS = 'in_progress';
    private const TASK_STATUS_COMPLETED = 'completed';
    private const TASK_STATUS_ERROR = 'error';

    public function __construct(
        ?string $view_path = null,
        ?nc_ai_company_context $company_context = null,
        ?nc_ai_generation_service $generation_service = null,
        ?nc_ai_token_balance $ai_token_balance = null
    ) {
        parent::__construct(nc_core::get_object()->ADMIN_FOLDER . '/views/ai/generation');

        $this->company_context = $company_context ?? nc_ai_company_context::getFromCurrentSite();
        $this->generation_service = $generation_service ??
            new nc_ai_generation_service(
                new nc_ai_generation_api(new Client()),
                $this->company_context,
                new nc_content_changer_runner(),
                new nc_ai_blocks_library()
            );

        $this->ai_token_balance = $ai_token_balance ?? new nc_ai_token_balance(new Client());
    }

    protected function action_show_dialog(): nc_ui_view {
        $dialog = $this->input->fetch_get('dialog');
        $method = "action_show_$dialog";

        if (method_exists($this, $method)) {
            return call_user_func(array($this, $method));
        }

        return $this->view("error_message", ['message' => "The dialog not found"]);
    }

    private function action_show_page_generation_dialog(): nc_ui_view {
        try {
            $parameters = $this->input->fetch_get_post();
            $payload = array_merge(
                $parameters,
                [
                    // С версии 7.2 сценарий генерации единый (FREE_LANDING_ON_MULTIPAGE),
                    // определение сценария по структуре сайта больше не требуется.
                    'site_id' => $this->nc_core->subdivision->get_by_id($parameters['subdivision_id'], 'Catalogue_ID'),
                    'subdivision' => $this->generation_service->get_subdivision_data($parameters['subdivision_id']),
                    'page_has_blocks' => (bool)$this->nc_core->sub_class->get_by_subdivision_id($parameters['subdivision_id']),
//                    'balance_info' => $this->ai_token_balance->get_balance_info(),
                ]
            );

            return $this->view(
                'page_generation_dialog',
                [
                    'company_context' => $this->company_context,
                    'payload' => $payload,
                ]
            );
        } catch (nc_ai_content_generation_exception|nc_ai_token_balance_exception $e) {
            return $this->view('error_message')->with('message', strip_tags($e->getMessage()));
        }
    }

    protected function action_generate_page(): string {
        session_write_close();
        set_time_limit(0);
        ignore_user_abort(true);

        $subdivision_id = (int)$this->input->fetch_post('subdivision_id');
        $this->check_subdivision_permissions($subdivision_id);

        $company_context = $this->input->fetch_post('company_context');

        if ($company_context) {
            $this->generation_service->update_company_context($company_context);
        }

        try {
            $scenario_type = $this->input->fetch_post('scenario_type');

            $settings = $this->input->fetch_post('settings');
            // prevent_append_headers передаётся в генератор как есть (на будущее).
            // Ранее вырезался для не-MULTIPAGE сценариев — теперь сценарий единый
            // (FREE_LANDING_ON_MULTIPAGE), а значение приходит falsy и игнорируется.

            $instructions = $this->generation_service->get_page_changer_instructions(
                $subdivision_id,
                $scenario_type,
                $this->input->fetch_post('user_prompt'),
                $settings
            );

            $this->generation_service->apply_generated_instructions($subdivision_id, $instructions);

            return $this->json_success();
        } catch (nc_ai_content_generation_exception $e) {
            return $this->json_error($e->getMessage(), 0, $e->get_details(), nc_http::HTTP_STATUS_BAD_REQUEST);
        }
    }

    /**
     * Запускает генерацию страницы в фоновом режиме.
     * Сразу возвращает task_id клиенту, затем продолжает выполнение.
     */
    protected function action_start_generate_page(): void {
        $subdivision_id = (int)$this->input->fetch_post('subdivision_id');
        $this->check_subdivision_permissions($subdivision_id);

        $task_id = $this->create_task_id();
        $status_file = $this->get_task_status_file_path($task_id);

        $this->write_task_status($status_file, [
            'status' => self::TASK_STATUS_IN_PROGRESS,
            'started_at' => time(),
        ]);

        $this->flush_response_and_continue(json_encode([
            'success' => true,
            'task_id' => $task_id,
        ]));

        $company_context = $this->input->fetch_post('company_context');
        if ($company_context) {
            $this->generation_service->update_company_context($company_context);
        }

        try {
            $scenario_type = $this->input->fetch_post('scenario_type');

            $settings = $this->input->fetch_post('settings');
            // prevent_append_headers передаётся в генератор как есть (на будущее).
            // Ранее вырезался для не-MULTIPAGE сценариев — теперь сценарий единый
            // (FREE_LANDING_ON_MULTIPAGE), а значение приходит falsy и игнорируется.

            $instructions = $this->generation_service->get_page_changer_instructions(
                $subdivision_id,
                $scenario_type,
                $this->input->fetch_post('user_prompt'),
                $settings
            );

            $this->generation_service->apply_generated_instructions($subdivision_id, $instructions);

            $this->write_task_status($status_file, [
                'status' => self::TASK_STATUS_COMPLETED,
                'completed_at' => time(),
            ]);
        } catch (nc_ai_content_generation_exception $e) {
            $this->write_task_status($status_file, [
                'status' => self::TASK_STATUS_ERROR,
                'message' => $e->getMessage(),
                'details' => $e->get_details(),
                'completed_at' => time(),
            ]);
        } catch (Throwable $e) {
            $this->write_task_status($status_file, [
                'status' => self::TASK_STATUS_ERROR,
                'message' => $e->getMessage(),
                'completed_at' => time(),
            ]);
        }

        exit;
    }

    /**
     * Проверяет статус задачи генерации.
     */
    protected function action_check_generation_status(): string {
        $task_id = $this->input->fetch_get_post('task_id');

        if (!$task_id || !preg_match('/^[a-f0-9]{32}$/', $task_id)) {
            return $this->json_error('Invalid task_id', 0, [], nc_http::HTTP_STATUS_BAD_REQUEST);
        }

        $status_file = $this->get_task_status_file_path($task_id);

        if (!file_exists($status_file)) {
            return $this->json_error('Task not found', 0, [], nc_http::HTTP_STATUS_NOT_FOUND);
        }

        $status_data = $this->read_task_status($status_file);

        if ($status_data['status'] !== self::TASK_STATUS_IN_PROGRESS) {
            @unlink($status_file);
        }

        return $this->json_success($status_data);
    }

    /**
     * Генерирует уникальный идентификатор задачи.
     */
    private function create_task_id(): string {
        return md5(uniqid((string)mt_rand(), true));
    }

    /**
     * Возвращает путь к файлу статуса задачи.
     */
    private function get_task_status_file_path(string $task_id): string {
        $dir = $this->nc_core->TMP_FOLDER . 'ai_generation/';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        return $dir . $task_id . '.json';
    }

    /**
     * Записывает статус задачи в файл.
     */
    private function write_task_status(string $file_path, array $data): void {
        file_put_contents($file_path, json_encode($data, JSON_UNESCAPED_UNICODE));
    }

    /**
     * Читает статус задачи из файла.
     */
    private function read_task_status(string $file_path): array {
        $content = @file_get_contents($file_path);
        return $content ? json_decode($content, true) : [];
    }

    /**
     * Отправляет ответ клиенту и закрывает соединение, но продолжает выполнение скрипта.
     */
    private function flush_response_and_continue(string $content): void {
        session_write_close();
        set_time_limit(0);
        ignore_user_abort(true);

        header('Content-Type: application/json; charset=utf-8');
        header('X-Accel-Buffering: no');
        header('Connection: close');
        header('Content-Length: ' . strlen($content));

        echo $content;

        while (@ob_end_flush()) {
        }
        flush();

        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }
    }

    private function check_subdivision_permissions(int $subdivision_id) {
        /** @var Permission $perm */
        global $perm;
        $perm->ExitIfNotAccess(NC_PERM_ITEM_SUB, NC_PERM_ACTION_ADMIN, $subdivision_id, null, 1);
    }

    /**
     * @deprecated С версии 7.2 генерация отказалась от блочной сборки из библиотеки,
     * поэтому скачивание/разворачивание библиотеки блоков больше не вызывается из
     * диалога генерации. Экшен оставлен для возможности вернуть функциональность
     * малым хаком в частных случаях и будет удалён в одной из следующих версий.
     */
    protected function action_update_library(): string {
        session_write_close();
        ignore_user_abort();

        $nc_core = nc_core::get_object();
        $site_id = (int)$nc_core->input->fetch_post_get('site_id');
        $library_sub_id = $nc_core->catalogue->get_or_create_block_library_sub_id($site_id);
        $error_prefix = CONTROL_CONTENT_BLOCK_LIBRARY_UPDATE_ERROR;

        /** @var Permission $perm */
        global $perm;

        if (!$perm->isAccess(NC_PERM_ITEM_SUB, NC_PERM_ACTION_ADMIN, $library_sub_id)) {
            $message = sprintf(
                CONTROL_CONTENT_BLOCK_LIBRARY_UPDATE_ERROR_NO_RIGHTS,
                $nc_core->subdivision->get_by_id($library_sub_id, 'Subdivision_Name')
            );

            return $nc_core->ui->alert()->error("$error_prefix: $message");
        }

        try {
            nc_store_block::update_library($site_id);
        } catch (nc_store_exception $e) {
            return $nc_core->ui->alert()->error("$error_prefix: {$e->getMessage()}");
        }

        return $nc_core->ui->alert()->success(CONTROL_CONTENT_BLOCK_LIBRARY_UPDATED);
    }

    protected function action_get_balance_info(): string {
        try {
            return $this->json_success($this->ai_token_balance->get_balance_info());
        } catch (nc_ai_token_balance_exception $e) {
            return $this->json_error(NECAT_AI_BALANCE_STATE_ERROR);
        }
    }
}

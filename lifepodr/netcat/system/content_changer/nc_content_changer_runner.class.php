<?php

class nc_content_changer_runner {

    /**
     * @var nc_content_changer_instruction[]
     */
    private $instructions;

    /**
     * Ошибки выполнения по инструкциям (entity_type.action → сообщение)
     * @var array
     */
    private $errors = array();

    /**
     * @var int
     */
    private $succeeded = 0;

    /**
     * Результат по каждой инструкции в порядке порции:
     * [{index, instruction: 'entity_type.action', status: ok|failed, error, warnings: string[]}]
     * @var array
     */
    private $results = array();


    /**
     * @param array $instructions
     * @param int $site_id
     */
    public function __construct(array $instructions = [], $site_id = null) {
        $this->instructions = $instructions;

        if ($site_id) {
            nc_core::get_object()->catalogue->set_current_by_id($site_id);
        }
    }

    /**
     * @return void
     */
    public function run() {
        set_time_limit(0);
        $db = nc_core::get_object()->db;

        foreach (array_values($this->get_instructions()) as $index => $instruction) {
            // Создаем логгер для каждой инструкции
            $logger = new nc_content_changer_logger($instruction);
            $instruction_label = $instruction->get_entity_type() . '.' . $instruction->get_action();

            $content_change_class = "nc_content_changer_" . $instruction->get_entity_type();

            if (!class_exists($content_change_class)) {
                $logger->set_skipped("Content changer class '$content_change_class' does not exist");
                $logger->save();
                $this->fail($index, $instruction_label, "no content changer class $content_change_class");
                continue;
            }

            /** @var nc_content_changer $content_change_class */
            $content_changer = new $content_change_class($instruction);

            // Передаем логгер в content_changer для детального логирования
            if (method_exists($content_changer, 'set_logger')) {
                $content_changer->set_logger($logger);
            }

            // Ошибки SQL ядро не бросает исключением, а складывает в nc_db::$captured_errors.
            // Исход инструкции определяет apply(): если она удалась (сущность создана), ошибки
            // SQL по пути — предупреждения; если нет — они уточняют причину отказа.
            $db_errors_before = count($db->captured_errors);

            try {
                $result = $content_changer->apply();
                $sql_messages = $this->format_db_errors(array_slice($db->captured_errors, $db_errors_before));

                if ($result !== false) {
                    $logger->set_success();
                    $this->succeeded++;
                    $this->results[] = array(
                        'index' => $index,
                        'instruction' => $instruction_label,
                        'status' => 'ok',
                        'error' => '',
                        'warnings' => $sql_messages,
                    );
                    if ($result !== true && $result !== null) {
                        $logger->set_execution_result($result);
                    }
                    if ($sql_messages) {
                        // Статус в логе остаётся success; предупреждения — в Error_Message
                        $logger->set_warnings($sql_messages);
                    }
                } else {
                    // Точную причину обработчик уже записал в логгер — её и отдаём, не затирая
                    $error_message = $logger->get_error_message();
                    if ($error_message === '') {
                        $error_message = $sql_messages ? end($sql_messages) : 'Content changer apply() returned false';
                    } elseif ($sql_messages) {
                        $error_message .= '; ' . end($sql_messages);
                    }
                    $logger->set_error($error_message);
                    $this->fail($index, $instruction_label, $error_message);
                }

                $logger->save();
            } catch (Error $e) {
                $error_message = $e->getMessage();
                $logger->set_error($error_message);
                $logger->save();
                $this->fail($index, $instruction_label, $error_message);

                $message = "[Instruction execution error]: \r\nInstruction: " . $instruction->to_json() . ", \r\nError: " . $error_message;
                trigger_error($message, E_USER_WARNING);
                continue;
            } catch (Exception $e) {
                $error_message = $e->getMessage();
                $logger->set_error($error_message);
                $logger->save();
                $this->fail($index, $instruction_label, $error_message);

                $message = "[Instruction execution error]: Instruction: " . $instruction->to_json() . ", Error: " . $error_message;
                trigger_error($message, E_USER_WARNING);
                continue;
            }
        }
    }

    /**
     * @param array $db_errors элементы nc_db::$captured_errors
     * @return string[]
     */
    private function format_db_errors(array $db_errors) {
        $messages = array();
        foreach ($db_errors as $db_error) {
            $messages[] = "SQL error: " . nc_array_value($db_error, 'error_str')
                . " [" . mb_substr(preg_replace('/\s+/', ' ', (string)nc_array_value($db_error, 'query')), 0, 300) . "]";
        }
        return $messages;
    }

    /**
     * Ошибки выполнения (пусто, если все инструкции прошли)
     *
     * @return array [{instruction: 'entity.action', error: '...'}, ...]
     */
    public function get_errors() {
        return $this->errors;
    }

    /**
     * Результат по каждой инструкции, в порядке переданного массива: панель сопоставляет
     * index с Instruction_ID порции и так знает, какая именно инструкция отклонена
     *
     * @return array [{index: int, instruction: 'entity.action', status: 'ok'|'failed', error: string, warnings: string[]}, ...]
     */
    public function get_results() {
        return $this->results;
    }

    /**
     * @param int $index
     * @param string $instruction_label
     * @param string $error
     */
    private function fail($index, $instruction_label, $error) {
        $this->errors[] = array('instruction' => $instruction_label, 'error' => $error);
        $this->results[] = array('index' => $index, 'instruction' => $instruction_label, 'status' => 'failed', 'error' => (string)$error, 'warnings' => array());
    }

    /**
     * @return int число успешно выполненных инструкций
     */
    public function get_succeeded_count() {
        return $this->succeeded;
    }

    /**
     * @param nc_content_changer_instruction[] $instructions
     */
    public function set_instructions(array $instructions) {
        $this->instructions = $instructions;
    }

    /**
     * @return nc_content_changer_instruction[]
     */
    public function get_instructions() {
        return $this->instructions;
    }

}

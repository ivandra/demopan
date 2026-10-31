<?php


class nc_ai_content_generation_exception extends Exception {

    private array $details = [];

    public function __construct($message = "", $code = 0, Throwable $previous = null, array $details = []) {
        $this->details = $details;

        if ($code === 402) {
            $message = NECAT_AI_INSUFFICIENT_BALANCE_ERROR;
        }

        // Генератор может вернуть строковый код ошибки (например, "cms_version_unsupported"),
        // а нативный Exception требует int — приводим, чтобы не ронять конструктор
        parent::__construct($message, (int)$code, $previous);
    }

    public function get_details(): array {
        return $this->details;
    }

}

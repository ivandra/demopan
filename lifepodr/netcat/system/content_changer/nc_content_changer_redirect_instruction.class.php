<?php

/**
 * Клас-структура, описывающая инструкции для изменения сущности "redirect"
 */
class nc_content_changer_redirect_instruction extends nc_content_changer_instruction {

    /**
     * @var string
     */
    protected static $entity_type = "redirect";

    /**
     * @var string
     */
    protected $old_url;

    /**
     * @var string
     */
    protected $new_url;

    /**
     * @var int
     */
    protected $status_code;

    /**
     * @param string $action
     * @param string $old_url
     * @param string $new_url
     * @param int $status_code
     */
    public function __construct(
        string $action,
        string $old_url,
        string $new_url,
        int $status_code = 301
    ) {
        $this->old_url = $old_url;
        $this->new_url = $new_url;
        $this->status_code = $status_code;

        parent::__construct($action, "");
    }

    /**
     * @return string
     */
    public function get_old_url() {
        return $this->old_url;
    }

    /**
     * @return string
     */
    public function get_new_url() {
        return $this->new_url;
    }

    /**
     * @return int
     */
    public function get_status_code() {
        return $this->status_code;
    }

    /**
     * @inheritDoc
     */
    public function to_array() {
        return array(
            "entity_type" => self::$entity_type,
            "action" => $this->action,
            "old_url" => $this->old_url,
            "new_url" => $this->new_url,
            "status_code" => $this->status_code,
        );
    }
}

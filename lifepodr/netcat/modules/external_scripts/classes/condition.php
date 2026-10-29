<?php

/**
 * Класс условий показа сниппета
 */
class nc_external_scripts_condition {

    /** @var string Все страницы */
    const TYPE_ALL = 'all';

    /** @var string Только указанные разделы */
    const TYPE_INCLUDE = 'include';

    /** @var string Все кроме указанных разделов */
    const TYPE_EXCLUDE = 'exclude';

    /** @var string */
    protected $type = self::TYPE_ALL;

    /** @var array */
    protected $subdivision_ids = [];

    /** @var bool Включать дочерние разделы */
    protected $include_children = true;

    /**
     * Создание экземпляра из массива
     *
     * @param array $data
     * @return self
     */
    public static function from_array(array $data): self {
        $condition = new self();
        $condition->type = $data['type'] ?? self::TYPE_ALL;
        $condition->subdivision_ids = $data['subdivision_ids'] ?? [];
        $condition->include_children = $data['include_children'] ?? true;
        return $condition;
    }

    /**
     * Преобразование в массив для сохранения
     *
     * @return array
     */
    public function to_array(): array {
        return [
            'type' => $this->type,
            'subdivision_ids' => $this->subdivision_ids,
            'include_children' => $this->include_children,
        ];
    }

    /**
     * Получение типа условия
     *
     * @return string
     */
    public function get_type(): string {
        return $this->type;
    }

    /**
     * Получение ID разделов
     *
     * @return array
     */
    public function get_subdivision_ids(): array {
        return $this->subdivision_ids;
    }

    /**
     * Проверка условия для текущей страницы
     *
     * @return bool
     */
    public function check(): bool {
        if ($this->type === self::TYPE_ALL) {
            return true;
        }

        $current_subdivision = $this->get_current_subdivision_id();
        if (!$current_subdivision) {
            return $this->type === self::TYPE_EXCLUDE;
        }

        $matches = $this->matches_subdivision($current_subdivision);

        if ($this->type === self::TYPE_INCLUDE) {
            return $matches;
        }

        if ($this->type === self::TYPE_EXCLUDE) {
            return !$matches;
        }

        return true;
    }

    /**
     * Получение ID текущего раздела
     *
     * @return int|null
     */
    protected function get_current_subdivision_id(): ?int {
        $nc_core = nc_core::get_object();

        // Попытка получить через subdivision
        try {
            $sub_id = $nc_core->subdivision->get_current('Subdivision_ID');
            if ($sub_id) {
                return (int)$sub_id;
            }
        } catch (Exception $e) {
            // Игнорируем
        }

        // Попытка через глобальную переменную
        global $sub;
        if (!empty($sub)) {
            return (int)$sub;
        }

        return null;
    }

    /**
     * Проверка соответствия раздела условию
     *
     * @param int $subdivision_id
     * @return bool
     */
    protected function matches_subdivision(int $subdivision_id): bool {
        if (in_array($subdivision_id, $this->subdivision_ids)) {
            return true;
        }

        // Проверка дочерних разделов
        if ($this->include_children) {
            return $this->is_child_of_any($subdivision_id, $this->subdivision_ids);
        }

        return false;
    }

    /**
     * Проверка, является ли раздел дочерним для любого из указанных
     *
     * @param int $subdivision_id
     * @param array $parent_ids
     * @return bool
     */
    protected function is_child_of_any(int $subdivision_id, array $parent_ids): bool {
        if (empty($parent_ids)) {
            return false;
        }

        $nc_core = nc_core::get_object();

        try {
            $current_id = $subdivision_id;
            $max_depth = 20; // Защита от бесконечного цикла

            while ($max_depth-- > 0) {
                $parent_id = $nc_core->subdivision->get_by_id($current_id, 'Parent_Sub_ID');

                if (!$parent_id) {
                    break;
                }

                if (in_array($parent_id, $parent_ids)) {
                    return true;
                }

                $current_id = $parent_id;
            }
        } catch (Exception $e) {
            // Игнорируем ошибки
        }

        return false;
    }

    /**
     * Получение названий типов условий
     *
     * @return array
     */
    public static function get_type_names(): array {
        return [
            self::TYPE_ALL => NETCAT_MODULE_EXTSCRIPTS_CONDITION_ALL,
            self::TYPE_INCLUDE => NETCAT_MODULE_EXTSCRIPTS_CONDITION_INCLUDE,
            self::TYPE_EXCLUDE => NETCAT_MODULE_EXTSCRIPTS_CONDITION_EXCLUDE,
        ];
    }
}

<?php

class nc_security_filter_xss extends nc_security_filter {

    static protected $filter_type_string = 'xss';
    static protected $input_check_types = array(
        'dangerous_tag',
        'attribute',
        'javascript',
        // проверки для замаскированного мусорными строками инпута
        'masked_dangerous_tag',
        'masked_attribute',
        'masked_javascript',
    );

    /**
     * Убирает мусорные подстроки и выполняет соответствующую проверку check_input_for_<check_type>
     * @param $check_type
     * @param $input
     * @return false|string
     */
    protected function check_for_masked_input($check_type, $input) {
        $unmasked_input = nc_macro_functions::remove_from($input);
        $check_method = 'check_input_for_' . $check_type;
        if ($unmasked_input && $unmasked_input !== $input && $this->$check_method($unmasked_input)) {
            return $unmasked_input;
        }
        return false;
    }

    /**
     * Возвращает название проверки без префикса "masked_"
     * @param string $type
     * @return string
     */
    protected function get_unmasked_check_type($type) {
        return preg_replace('/^masked_/', '', $type);
    }

    /**
     * Проверяет наличие тэгов script, style в строке
     * (другие тэги без атрибутов не могут использоваться для XSS (?))
     * @param string $input
     * @return bool
     */
    protected function check_input_for_dangerous_tag($input) {
        return
            stripos($input, '<script') !== false ||
            stripos($input, '<style') !== false;
    }

    /**
     * @param string $input
     * @return false|string
     */
    protected function check_input_for_masked_dangerous_tag($input) {
        return $this->check_for_masked_input('dangerous_tag', $input);
    }

    /**
     * Проверяет наличие "=" и хотя бы одного символа после него
     * @param string $input
     * @return bool
     */
    protected function check_input_for_attribute($input) {
        $eq_position = strpos($input, '=');
        return $eq_position !== false && strlen($input) > $eq_position + 1;
    }

    /**
     * @param string $input
     * @return false|string
     */
    protected function check_input_for_masked_attribute($input) {
        return $this->check_for_masked_input('attribute', $input);
    }

    /**
     * Проверяет строку на символы, которые могут быть использованы для инъекции
     * кода внутрь <script>
     * @param string $input
     * @return bool
     */
    protected function check_input_for_javascript($input) {
        // для уменьшения вероятности ложных срабатываний считаем всё, что
        // не длиннее 8 символов, безопасным...     ("onload=x")
        if (strlen($input) < 9) {
            return false;
        }

        // location='javascript:alert\x281\x29', onload='...' etc
        // Вызов функций: (), ``
        return preg_match('/[()`=]/', $input) === 1;
    }

    /**
     * @param string $input
     * @return false|string
     */
    protected function check_input_for_masked_javascript($input) {
        return $this->check_for_masked_input('javascript', $input);
    }

    /**
     * @param string $checked_string
     * @param array $suspicious_input
     * @param mixed $context
     * @return string
     */
    protected function check_string_against_input($checked_string, $suspicious_input, $context) {
        foreach (self::$input_check_types as $type) {
            if (isset($suspicious_input[$type])) {
                $check_method = 'check_' . $this->get_unmasked_check_type($type);
                $checked_string = $this->$check_method($checked_string, $suspicious_input[$type]);
            }
        }
        return $checked_string;
    }

    /**
     * @param string $checked_string
     * @param array $suspicious_input
     * @return string
     */
    protected function check_dangerous_tag($checked_string, $suspicious_input) {
        foreach ($suspicious_input as $source => $input) {
            $position = -1;
            while (false !== ($position = strpos($checked_string, $input, $position + 1))) {
                $this->trigger_error('dangerous_tag', $checked_string, $source, $input);
                // санирование строки можно будет добавить здесь:
                // $checked_string = ...
            }
        }

        return $checked_string;
    }

    /**
     * @param string $checked_string
     * @param array $suspicious_input
     * @return string
     */
    protected function check_attribute($checked_string, $suspicious_input) {
        foreach ($suspicious_input as $source => $input) {
            $position = -1;
            while (false !== ($position = strpos($checked_string, $input, $position + 1))) {
                $quote = $this->get_attribute_quote_type_at_position($checked_string, $position);

                $error =
                    // $input вставлен вне тэга и в $input есть "<":
                    ($quote === null && strpos($input, '<') !== false) ||
                    // $input вставлен внутри тэга, вне кавычки:
                    $quote === false ||
                    // $input вставлен внутри тэга внутри кавычек, но такая же кавычка есть в $input:
                    ($quote !== null && strpos($input, $quote) !== false);

                if ($error) {
                    $this->trigger_error('attribute', $checked_string, $source, $input);
                    // санирование строки можно будет добавить здесь:
                    // $checked_string = ...
                }
            }
        }

        return $checked_string;
    }

    /**
     * @param $html
     * @param $position
     * @return false|null|string
     *      null: не в тэге
     *      false: в тэге, нет кавычки
     *      ' или "
     */
    protected function get_attribute_quote_type_at_position($html, $position) {
        $length = strlen($html);
        $inside_tag = false;
        $quote_type = false;

        for ($i = 0; $i < $position; $i++) {
            $char = $html[$i];
            if ($inside_tag) {
                if ($quote_type === false) { // inside tag, outside quote
                    if ($char === '>') {
                        $inside_tag = false;
                    } else if ($char === '"' || $char === "'") {
                        $quote_type = $char;
                    }
                } elseif ($char === $quote_type) { // inside tag, inside quote – closing quote
                    $quote_type = false;
                }
            } else if ($char === '<' && $i < $length - 1 && preg_match('/[A-Za-z]/', $html[$i + 1])) {
                $inside_tag = true;
            }
        }

        return $inside_tag ? $quote_type : null;
    }

    /**
     * @param $checked_string
     * @param $suspicious_input
     */
    protected function check_javascript($checked_string, $suspicious_input) {
        foreach ($suspicious_input as $source => $input) {
            $position = -1;
            while (false !== ($position = strpos($checked_string, $input, $position + 1))) {
                $quote_type = $this->get_script_quote_type_at_position($checked_string, $position);

                if ($quote_type === null) {
                    continue;
                }

                $error = $quote_type === false ||
                    $this->string_has_unescaped_quote($input, $quote_type) ||
                    ($quote_type === '`' && strpos($input, '${') !== false);

                if ($error) {
                    $this->trigger_error('javascript', $checked_string, $source, $input);
                    // санирование строки можно будет добавить здесь:
                    // $checked_string = ...
                }
            }
        }
        return $checked_string;
    }

    /**
     * @param string $html
     * @param int $position
     * @return mixed
     *      null: не в <script>
     *      false: в <script>, не в кавычках
     *      ', ", `: тип открытой кавычки
     */
    protected function get_script_quote_type_at_position($html, $position) {
        $backwards_position = (strlen($html) - $position) * -1;

        $previous_script_begin_position = strripos($html, '<script', $backwards_position);
        if ($previous_script_begin_position === false) {
            return null;
        }

        $previous_script_end_position = (int)strripos($html, '</script', $backwards_position);
        if ($previous_script_end_position > $previous_script_begin_position) {
            return null;
        }

        $quote_type = false;
        $escaped = false;

        for ($i = $previous_script_begin_position; $i < $position; $i++) {
            $char = $html[$i];
            if ($char === '\\') {
                $escaped = !$escaped;
            } else if (!$escaped && ($char === '"' || $char === "'" || $char === '`')) {
                if ($quote_type === $char) { // closing quote
                    $quote_type = false;
                } else { // opening quote
                    $quote_type = $char;
                }
            } else if ($escaped) {
                $escaped = false;
            }
        }

        return $quote_type;
    }

    /**
     * @param $input_source
     * @param string $input_value
     * @param string $check_type
     * @return string
     */
    protected function escape_input($input_source, $input_value, $check_type) {
        $check_type = $this->get_unmasked_check_type($check_type);

        if ($check_type === 'dangerous_tag') {
            $input_value = htmlspecialchars($input_value, ENT_QUOTES);
        } else if ($check_type === 'attribute') {
            $input_value = str_replace('=', '&#61;', $input_value);
        } else if ($check_type === 'javascript' && !nc_core::get_object()->input->is_input_escaped($input_source)) {
            // ↑ проверка is_input_escaped() в условии: если значение уже было экранировано
            //   при предыдущем запросе, то повторной попытки не предпринимаем, чтобы избежать
            //   бесконечного редиректа
            $escaped_value = addcslashes($input_value, '()$`"\'');
            $this->mark_input_as_escaped_for_redirect($input_source, $escaped_value);
            $input_value = $escaped_value;
        }
        return $input_value;
    }

}

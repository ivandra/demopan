<?php

class nc_html_tag_helper {

    /**
     * Находит позицию '<' первого открывающего HTML-тега (не комментария, не '</')
     * @param string $html
     * @return int|false
     */
    public static function find_first_tag_position($html) {
        $length = strlen($html);
        $pos = 0;
        while ($pos < $length) {
            $pos = strpos($html, '<', $pos);
            if ($pos === false) {
                return false;
            }
            // Пропускаем комментарии
            if (substr($html, $pos, 4) === '<!--') {
                $end = strpos($html, '-->', $pos + 4);
                if ($end === false) {
                    return false;
                }
                $pos = $end + 3;
                continue;
            }
            // Пропускаем закрывающие теги
            if (isset($html[$pos + 1]) && $html[$pos + 1] === '/') {
                $pos++;
                continue;
            }
            // Открывающий тег — проверяем, что после < идёт буква
            if (isset($html[$pos + 1]) && preg_match('/[\w]/', $html[$pos + 1])) {
                return $pos;
            }
            $pos++;
        }
        return false;
    }

    /**
     * Находит позицию '<' последнего закрывающего HTML-тега
     * Перенос из nc_partial_object_list::get_last_tag_position()
     * @param string $html
     * @return int|false
     */
    public static function find_last_tag_position($html) {
        $length = $position = strlen($html);
        while (false !== ($position = strrpos($html, '<', $position - $length - 1))) {
            if (preg_match('/[\w\/]/', $html[$position + 1])) {
                return $position;
            }
        }
        return false;
    }

    /**
     * Проверяет, что HTML имеет ровно один корневой элемент
     * @param string $html
     * @return bool
     */
    public static function has_single_root_element($html) {
        // Убираем пробелы и комментарии по краям
        $html = self::strip_comments_and_trim($html);

        if ($html === '') {
            return false;
        }

        // Находим первый открывающий тег
        $first_pos = self::find_first_tag_position($html);
        if ($first_pos === false) {
            return false;
        }

        // Проверяем, что перед первым тегом нет значимого текста
        $before = trim(substr($html, 0, $first_pos));
        if ($before !== '') {
            return false;
        }

        // Определяем имя первого тега
        preg_match('/^<([a-zA-Z][\w-]*)/s', substr($html, $first_pos), $matches);
        if (empty($matches[1])) {
            return false;
        }
        $tag_name = strtolower($matches[1]);

        // Void- и невизуальные корни не годятся как маркер блока: у них нет
        // визуального бокса в edit-mode (void схлопывается, script/style/link —
        // display:none), поэтому hover/тулбары/insert на них не работают.
        // Для таких — обёртка (возвращаем false).
        $void_elements = array('area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link', 'meta', 'param', 'source', 'track', 'wbr');
        $non_visual_elements = array('script', 'style', 'template', 'head', 'title', 'noscript');
        if (in_array($tag_name, $void_elements, true) || in_array($tag_name, $non_visual_elements, true)) {
            return false;
        }

        // Реальная проверка единственного корня: проходим по всем тегам, считаем
        // глубину (с учётом void/самозакрывающихся и кавычек в атрибутах). Корень
        // должен закрыться ровно в конце строки — иначе это несколько сиблингов
        // (напр. список из N записей), и маркер сел бы только на первый.
        // Комментарий — первая альтернация: <!-- ... --> матчится одним токеном,
        // поэтому тегоподобный текст внутри него (<div>..<!-- было <b> -->..</div>)
        // не попадёт в подсчёт глубины. <!-- внутри значения атрибута поглощается
        // альтернативой кавычек в составе тега, так что не ломает разбор.
        if (!preg_match_all(
            '/<!--.*?-->|<(\/?)([a-zA-Z][\w-]*)((?:"[^"]*"|\'[^\']*\'|[^>])*?)(\/?)>/s',
            $html, $tags, PREG_OFFSET_CAPTURE | PREG_SET_ORDER
        )) {
            return false;
        }

        $depth = 0;
        $root_closed_at = null;
        foreach ($tags as $tag) {
            // Токен-комментарий: у него нет групп тега — пропускаем.
            if (substr($tag[0][0], 0, 4) === '<!--') {
                continue;
            }
            $is_close = $tag[1][0] === '/';
            $name = strtolower($tag[2][0]);
            $self_closing = $tag[4][0] === '/';

            if ($is_close) {
                $depth--;
                if ($depth <= 0) {
                    $root_closed_at = $tag[0][1] + strlen($tag[0][0]);
                    break;
                }
            } else if (!$self_closing && !in_array($name, $void_elements, true)) {
                $depth++;
            }
        }

        if ($root_closed_at === null) {
            return false; // корень не закрылся в пределах вывода
        }

        // После закрытия корневого элемента не должно быть значимого контента
        return trim(substr($html, $root_closed_at)) === '';
    }

    /**
     * Добавляет указанные класс и атрибуты к тегу в указанной позиции
     * Перенос из nc_partial_object_list::add_attributes_to_tag()
     * @param string $html модифицируемая строка
     * @param int $tag_start положение тега в строке (положение '<')
     * @param string $class класс, который будет добавлен к элементу
     * @param string $attributes строка с атрибутами, которая будет добавлена к элементу
     * @return string
     */
    public static function add_attributes_to_tag($html, $tag_start, $class, $attributes) {
        // (1) смотрим, есть ли атрибут class='x', class="x", class=x
        $tag_end = self::find_tag_end($html, $tag_start);
        $class_value_start = $class_value = $class_has_quote = null;

        if (
            preg_match('/(?<![\w-])class\s*=\s*(?P<quote>[\'"])?(?P<class>.*?)(?P=quote)/is', $html, $matches, PREG_OFFSET_CAPTURE, $tag_start) ||
            preg_match('/(?<![\w-])class\s*=\s*(?P<class>[^\s>]+)/is', $html, $matches, PREG_OFFSET_CAPTURE, $tag_start)
        ) {
            $class_value = $matches['class'][0];
            $class_value_start = $matches['class'][1];
            $class_has_quote = !empty($matches['quote'][0]);
        }

        if ($class) {
            if ($class_value_start && $class_value_start < $tag_end) {
                // вставляем класс в существующий атрибут class
                $new_class_value = "$class_value $class";
                if (!$class_has_quote) {
                    $new_class_value = "'$new_class_value'";
                }
                $html = substr_replace($html, $new_class_value, $class_value_start, strlen($class_value));
            } else {
                // добавляем атрибут class
                $html = substr_replace($html, " class='$class'>", $tag_end, 1);
            }
        }

        // (2) добавляем прочие атрибуты в конец тега
        if ($attributes) {
            $tag_end = self::find_tag_end($html, $tag_start);
            $html = substr_replace($html, " $attributes>", $tag_end, 1);
        }

        return $html;
    }

    /**
     * Находит позицию закрывающего '>' тега, начинающегося в $tag_start,
     * корректно пропуская '>' внутри значений атрибутов (в кавычках).
     * @param string $html
     * @param int $tag_start позиция '<'
     * @return int позиция '>' (или последний символ, если не найдено)
     */
    protected static function find_tag_end($html, $tag_start) {
        $length = strlen($html);
        $quote = null;
        for ($i = $tag_start; $i < $length; $i++) {
            $ch = $html[$i];
            if ($quote !== null) {
                if ($ch === $quote) {
                    $quote = null;
                }
            } else if ($ch === '"' || $ch === "'") {
                $quote = $ch;
            } else if ($ch === '>') {
                return $i;
            }
        }
        return $length - 1;
    }

    /**
     * Убирает HTML-комментарии и trim
     * @param string $html
     * @return string
     */
    protected static function strip_comments_and_trim($html) {
        $html = trim($html);
        // Убираем комментарии в начале и конце
        while (preg_match('/^\s*<!--.*?-->\s*/s', $html, $m)) {
            $html = substr($html, strlen($m[0]));
        }
        while (preg_match('/\s*<!--.*?-->\s*$/s', $html, $m)) {
            $html = substr($html, 0, -strlen($m[0]));
        }
        return trim($html);
    }

}

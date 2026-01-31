<?php

class SiteConfigWriter
{
    public function write(string $dir, array $cfg): void
    {
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $out = "<?php\n";

        // базовые
        $out .= '$domain = ' . $this->q($cfg['domain'] ?? '') . ";\n";
        $out .= '$yandex_verification = ' . $this->q($cfg['yandex_verification'] ?? '') . ";\n";
        $out .= '$yandex_metrika = ' . $this->q($cfg['yandex_metrika'] ?? '') . ";\n";
        $out .= '$promolink = ' . $this->q($cfg['promolink'] ?? '/play') . ";\n\n";

        // seo
        $out .= '$title = ' . $this->q($cfg['title'] ?? '') . ";\n";
        $out .= '$description = ' . $this->q($cfg['description'] ?? '') . ";\n";
        $out .= '$keywords = ' . $this->q($cfg['keywords'] ?? '') . ";\n";
        $out .= '$h1 = ' . $this->q($cfg['h1'] ?? '') . ";\n\n";

        // pages
        $pages = $cfg['pages'] ?? [];
        if (!is_array($pages)) $pages = [];

        $out .= '$pages = [' . "\n";

        foreach ($pages as $url => $page) {
            $url = (string)$url;
            if (!is_array($page)) $page = [];

            $out .= "   " . $this->q($url) . " => [\n";

            foreach ($page as $k => $v) {
                $k = (string)$k;

                if ($k === 'text_file') {
                    // ожидаем "home.php", "play.php" и т.п.
                    $file = (string)($v ?? '');
                    $file = ltrim($file, '/'); // на всякий случай
                    $out .= "    " . $this->q($k) . " => __DIR__ . '/texts/{$file}',\n";
                    continue;
                }

                // $inherit -> $title/$h1/$description/$keywords
                if (in_array($k, ['title', 'h1', 'description', 'keywords'], true) && $v === '$inherit') {
                    $out .= "    " . $this->q($k) . " => \${$k},\n";
                    continue;
                }

                $out .= "    " . $this->q($k) . " => " . $this->exportValue($v) . ",\n";
            }

            $out .= "],\n\n";
        }

        $out .= "];\n\n";

        // redirect / partner (плоские переменные как в исходнике)
        $out .= '$partner_override_url = ' . $this->q($cfg['partner_override_url'] ?? '') . ";\n";
        $out .= '$internal_reg_url = ' . $this->q($cfg['internal_reg_url'] ?? '') . ";\n";
        $out .= '$redirect_enabled = ' . (int)($cfg['redirect_enabled'] ?? 0) . ";\n\n";
        $out .= '$base_new_url = ' . $this->q($cfg['base_new_url'] ?? '') . ";\n";
        $out .= '$base_second_url = ' . $this->q($cfg['base_second_url'] ?? '') . ";\n";

        $out .= "?>";

        file_put_contents($dir . '/config.php', $out);
    }

    /**
     * Кавычит значение как PHP-строку.
     * null -> ''
     */
    private function q($v): string
    {
        $s = (string)($v ?? '');
        // экранирование для одинарных кавычек и обратного слэша
        $s = str_replace(['\\', "'"], ['\\\\', "\\'"], $s);
        return "'" . $s . "'";
    }

    /**
     * Экспорт значений для массива pages:
     * bool -> true/false, number -> number, null -> ''
     * string -> '...'
     */
    private function exportValue($v): string
    {
        if ($v === null) return "''";
        if (is_bool($v)) return $v ? 'true' : 'false';
        if (is_int($v) || is_float($v)) return (string)$v;

        // если вдруг прилетел числовой текст и ты хочешь как строку — оставляем строкой
        return $this->q($v);
    }
}

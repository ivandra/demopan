<?php

class nc_search_document_parser_html implements nc_search_document_parser {

    protected nc_search_context $context;

    /**
     * @var nc_search_document_parser_html_fragment[]
     *  array key: field name, value: nc_search_document_parser_html_fragment
     */
    protected array $parts = array();

    public function __construct(nc_search_context $context) {
        $this->context = $context;
        libxml_use_internal_errors(true);
    }

    /**
     * Предварительная обработка HTML-документа
     */
    public function load(nc_search_indexer_crawler_response $response): void {
        libxml_clear_errors();

        // такое преобразование позволяет избавиться от некорректных для UTF-8
        // последовательностей, из-за которых могут возникать неожиданные проблемы:
        $html = mb_convert_encoding($response->get_body(), 'UTF-8', 'UTF-8');

        // это позволит различать предложения, не оканчивающиеся на точку,
        // после того, как будут убраны тэги (в макетах Netcat немало таких мест):
        $html = str_ireplace(
                  array("<div",     "</div>",      "<p",     "</p>",      "<ul",    "<ol",      "<li",     "</li>",
                        "<tr",   "<td",   "<br",   "</a><a",  "<option",   "</option>",
                        "</h1>",     "</h2>",     "</h3>",     "</h4>",     "</h5>"),

                  array("\n\n<div", " \n\n</div>", "\n\n<p", " \n\n</p>", "\n\n<ul", "\n\n<ol", "\n\n<li", "\n</li>",
                        "\n<tr", "\n<td", "\n<br", "</a> <a", "\n<option", "\n</option>",
                        "\n\n</h1>", "\n\n</h2>", "\n\n</h3>", "\n\n</h4>", "\n\n</h5>"),

                  $html);

        // уберем тег script
        $html = preg_replace("#<script(.*?)>(.*?)</script>#siu", '', $html);

        $this->parts = array('document' => new nc_search_document_parser_html_fragment($html));
    }

    /**
     * Если есть мета-тэг robots или с именем бота и значением 'noindex',
     * документ не индексируется (внимание: атрибуты case-sensitive)
     */
    public function should_index(): bool {
        if (!nc_search::should('ObeyMetaNoindex')) {
            return true;
        }

        $xpath_query = '//meta[((@name="robots") or (@name="' .
            nc_search::get_setting('CrawlerUserAgent') .
            '")) and (contains(@content, "noindex") or (contains(@content, "none")))]';

        return ($this->xpath($xpath_query)->length == 0);
    }

    /**
     * Если есть мета-тэг robots или с именем бота и значением 'nofollow',
     * ссылки на странице не индексируется (внимание: атрибуты case-sensitive)
     */
    public function should_follow_links_on_page(): bool {
        if (!nc_search::should('ObeyMetaNofollow')) {
            return true;
        }

        $xpath_query = '//meta[((@name="robots") or (@name="' .
            nc_search::get_setting('CrawlerUserAgent') .
            '")) and (contains(@content, "nofollow") or (contains(@content, "none")))]';

        return ($this->xpath($xpath_query)->length == 0);
    }

    /**
     * Если есть мета-тэг robots или с именем бота и значениями 'none' или 'noindex' и 'nofollow' одновременно
     * документ не индексируется (внимание: атрибуты case-sensitive)
     */
    public function should_process(): bool {
        if (!nc_search::should('ObeyMetaNoindex')) {
            return true;
        }

        if (!nc_search::should('ObeyMetaNofollow')) {
            return true;
        }

        $xpath_query = '//meta[((@name="robots") or (@name="' .
            nc_search::get_setting('CrawlerUserAgent') .
            '")) and ((contains(@content, "noindex") and contains(@content, "nofollow")) or (contains(@content, "none")))]';

        return ($this->xpath($xpath_query)->length == 0);
    }

    public function get_document(): nc_search_document {
        $doc = new nc_search_document;
        $fields = $this->get_field_settings();

        // first get fields which are fetched from the 'document' (incl. 'content' field)
        foreach (array('document', 'content') as $scope) {
            foreach ($fields->where('query_scope', $scope) as $field) {
                $this->extract_field_value($field);
            }
            // сохранить полное содержимое <!-- content --> — затем из него могут быть
            // удалены различные части:
            if ($scope == 'document') {
                $doc->set('intact_content', $this->parts['content']->get_text());
            }
        }

        foreach ($fields as $field) { // записать полученные результаты в поля документа
            $field_name = $field->get('name');
            $set_option = ($field_name == 'content' || $field_name == 'title');
            $doc->add_field(clone $field);
            $doc->set_field_value($field_name, $this->parts[$field_name]->get_text(), $set_option);
        }

        return $doc;
    }

    public function extract_links(): array {
        $res = array();
        foreach ($this->xpath("//a[not(@rel) or @rel != 'nofollow']/@href") as $attr_node) {
            $res[] = trim($attr_node->nodeValue);
        }
        return $res;
    }

    public function check_environment(): void {
        //DOM
        if (!class_exists('DOMDocument')) {
            nc_print_status(NETCAT_MODULE_SEARCH_NO_DOM_EXTENSION_ERROR, 'error');
        }

        // mbstring
        if (!function_exists('mb_convert_encoding')) {
            nc_print_status(NETCAT_MODULE_SEARCH_NO_MBSTRING_EXTENSION_ERROR, 'error');
        }

        // PCRE UTF support (rare configuration quirk)
        if (!@preg_match("/\pL/u", "А")) {
            nc_print_status(NETCAT_MODULE_SEARCH_PCRE_UTF_ERROR, 'error');
        }
    }

    protected function xpath(string $query): DOMNodeList {
        return $this->parts['document']->evaluate_xpath($query);
    }

    /**
     * @throws nc_search_exception
     */
    protected function extract_field_value(nc_search_field $field) {
        $source_name = $field->get('query_scope');
        $recipient_name = $field->get('name');

        if (!isset($this->parts[$source_name])) {
            throw new nc_search_exception("Cannot extract field value: '$source_name' part does not exist");
        }
        $source = $this->parts[$source_name];

        if (!isset($this->parts[$recipient_name])) {
            $this->parts[$recipient_name] = new nc_search_document_parser_html_fragment;
        }
        $recipient = $this->parts[$recipient_name];

        $this->execute_queries(
            $field->get('query'),
            $source,
            $recipient,
            (bool)$field->get('remove_from_parent'),
            (bool)$field->get('query_use_first_matched')
        );
        // filter
        if ($field->get('filter_content')) {
            $this->execute_queries($field->get('filter_content'), $recipient, null, true, false);
        }
    }

    protected function execute_queries(
        string $query_string,
        nc_search_document_parser_html_fragment $source,
        ?nc_search_document_parser_html_fragment $recipient,
        bool $remove,
        bool $only_first
    ): bool {
        if (!trim($query_string)) { return false; }

        if (preg_match("/^[\w\x20\-]+$/", $query_string)) { // tag names delimited with a space
            $queries = explode(" ", $query_string);
        } else {
            $queries = explode("\n", $query_string);
        }

        $matched = false;

        foreach ($queries as $query) {
            $query = trim($query);
            if ($query[0] == "#") {
                $matched = $source->extract_regexp($query, $recipient, $remove);
            } elseif (strpos($query, "/") !== false) {
                $matched = $source->extract_xpath($query, $recipient, $remove);
            } elseif (preg_match("/^[\w\-]+$/", $query)) {
                $matched = $source->extract_tag($query, $recipient, $remove);
            }

            if ($matched && $only_first) {
                break;
            }

        }

        return $matched;
    }

    protected function get_field_settings(): nc_search_data_persistent_collection {
        return nc_search::load_all('nc_search_field');
    }
}

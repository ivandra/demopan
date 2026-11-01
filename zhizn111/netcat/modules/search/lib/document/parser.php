<?php
interface nc_search_document_parser extends nc_search_extension {
    /**
     * Загрузить документ.
     * Данный метод должен использоваться перед вызовом остальных методов.
     * (Парсер не создаётся для каждого документа для того, чтобы для подключения
     * парсеров для различных типов документов можно было использовать extension
     * manager — соответственно, парсер должен реализовывать интерфейс nc_search_extension).
     * @param nc_search_indexer_crawler_response $response
     */
    public function load(nc_search_indexer_crawler_response $response): void;

    public function should_process(): bool;

    public function should_index(): bool;

    public function should_follow_links_on_page(): bool;

    public function get_document(): nc_search_document;

    public function extract_links(): array;

    /**
     * Проверка требований к работе парсера, вывод сообщений о найденных ошибках
     * конфигурации (используется в панели управления модулем)
     */
    public function check_environment(): void;
}

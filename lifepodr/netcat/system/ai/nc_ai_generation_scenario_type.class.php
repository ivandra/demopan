<?php

interface nc_ai_generation_scenario_type {

    /**
     * @deprecated Сценарии блочной сборки из библиотеки. С версии 7.2 генерация
     * использует единственный сценарий FREE_LANDING_ON_MULTIPAGE (уникальная
     * вёрстка без библиотеки блоков). Эти константы оставлены для возможности
     * вернуть блочную генерацию малым хаком в частных случаях и будут удалены
     * в одной из следующих версий.
     */
    public const  MULTIPAGE = 'multipage';
    /** @deprecated см. MULTIPAGE */
    public const MULTIPAGE_MAIN_PAGE = 'multipage_main_page';
    /** @deprecated см. MULTIPAGE */
    public const  LANDING_ON_MULTIPAGE = 'landing_on_multipage';
    /** @deprecated см. MULTIPAGE */
    public const  LANDING = 'landing';

    public const  FREE_LANDING_ON_MULTIPAGE = 'free_landing_on_multipage';

}

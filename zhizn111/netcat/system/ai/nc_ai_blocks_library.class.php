<?php

/**
 * @deprecated С версии 7.2 генерация контента отказалась от блочной сборки из
 * библиотеки блоков (используется единый сценарий уникальной вёрстки
 * FREE_LANDING_ON_MULTIPAGE). Класс оставлен для возможности вернуть блочную
 * генерацию малым хаком в частных случаях и будет удалён в одной из следующих
 * версий. См. также @deprecated nc_ai_content_generation_controller::action_update_library().
 */
class  nc_ai_blocks_library {

    public const BLOCKS_SUBDIVISION_KEYWORD = 'tipovye-bloki';
    public const LANDING_BLOCKS_KEYWORD = 'landing';
    public const MULTIPAGE_BLOCKS_KEYWORD = 'corporate';


    public function get_available_blocks(string $scenario_type = nc_ai_generation_scenario_type::LANDING): array {
        $library_blocks_subdivision_id = $this->find_library_subdivision_id();
        $site_id = nc_core::get_object()->catalogue->get_current('Catalogue_ID');
        $keyword = $this->get_blocks_keyword_from_scenario_type($scenario_type);
        $query = "SELECT b.`Sub_Class_ID`, b.`Sub_Class_Name`, b.`EnglishName`, b.`Parent_Sub_Class_ID` 
                  FROM `Sub_Class` b
                  LEFT JOIN `Subdivision` s ON s.`Subdivision_ID` = b.`Subdivision_ID`
                  WHERE (b.`Subdivision_ID` = 0 OR s.`Parent_Sub_ID` = $library_blocks_subdivision_id OR s.`Subdivision_ID` = $library_blocks_subdivision_id) AND b.`Parent_Sub_Class_ID` = 0 AND b.`Checked` = 1
                  AND b.`Catalogue_ID`IN(0, $site_id) 
                  AND b.`EnglishName` LIKE '%$keyword%'";

        return nc_core::get_object()->db->get_results($query, ARRAY_A) ?? [];
    }

    public function get_subdivision_library_blocks(int $subdivision_id): array {
        return (array)nc_core::get_object()->db->get_results(
            "SELECT `Sub_Class_Name`,`Source_Keyword`,`EnglishName`,`Parent_Sub_Class_ID` 
             FROM `Sub_Class`
             WHERE `Subdivision_ID` = $subdivision_id
             AND `Parent_Sub_Class_ID` = 0
             AND `Source_Keyword` != ''",
            ARRAY_A
        );
    }

    private function find_library_subdivision_id(): int {
        return (int)nc_core::get_object()->db->get_var(
            "SELECT Subdivision_ID FROM `Subdivision` WHERE `EnglishName` = '" . self::BLOCKS_SUBDIVISION_KEYWORD . "'"
        ) ?: -1;
    }

    private function get_blocks_keyword_from_scenario_type(string $scenario_type): string {
        return in_array($scenario_type, [nc_ai_generation_scenario_type::LANDING, nc_ai_generation_scenario_type::LANDING_ON_MULTIPAGE]) ?
            self::LANDING_BLOCKS_KEYWORD : self::MULTIPAGE_BLOCKS_KEYWORD;
    }
}

<?php

/**
 *
 */
class nc_requests_backup extends nc_backup_extension {

    /**
     * @param string $type
     * @param int $id
     */
    public function export($type, $id) {
        if ($type !== 'site') {
            return;
        }

        $table = 'Requests_Form_SubdivisionSetting';
        // К словарю реальных разделов добавляем 0: под Subdivision_ID = 0 хранятся
        // настройки формы «по умолчанию для сайта» (в т.ч. состав и тексты галочек
        // согласия). Этого значения нет в словаре экспортируемых разделов, поэтому
        // без явного добавления дефолтная конфигурация выпадает из экспорта, и при
        // импорте форма откатывается к умолчаниям из кода (одна галочка AgreementRules).
        // При импорте Subdivision_ID = 0 остаётся нулём (в словаре нет ключа 0),
        // а Catalogue_ID ремапается на новый сайт автоматически.
        $subdivision_ids = $this->dumper->get_dict('Subdivision_ID');
        $subdivision_ids[0] = 0;
        $this->dumper->export_data(
            $table,
            $table . '_ID',
            nc_db_table::make($table)
                ->where('Catalogue_ID', $id)
                ->where_in('Subdivision_ID', $subdivision_ids)->get_result()
        );

        $table = 'Requests_Form_InfoblockSetting';
        $this->dumper->export_data(
            $table,
            $table . '_ID',
            nc_db_table::make($table)->where_in('Infoblock_ID', $this->dumper->get_dict('Sub_Class_ID'))->get_result()
        );
    }

    /**
     * @param string $type
     * @param int $id
     */
    public function import($type, $id) {
        if ($type !== 'site') {
            return;
        }

        $this->dumper->import_data('Requests_Form_SubdivisionSetting');
        $this->dumper->import_data('Requests_Form_InfoblockSetting', null, array('Infoblock_ID' => 'Sub_Class_ID'));
    }
}
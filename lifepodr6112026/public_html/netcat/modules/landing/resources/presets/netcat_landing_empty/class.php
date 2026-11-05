<?php

class nc_landing_preset_netcat_landing_empty extends nc_landing_preset {
    protected $name = array(
        'ru' => 'Пустая страница',
        'en' => 'Empty page'
    );

    protected $description = array(
        'ru' => 'Основа для любого лендинга и AI-конструктора страниц.',
        'en' => 'Foundation for any landing page and AI page builder.'
    );

    protected $blocks = array(
    );

    /**
     * @param array $landing_data
     * @return array
     */
    protected function get_subdivision_properties(array $landing_data) {
        return array(
            'Subdivision_Name' => isset($landing_data['subdivision_name']) && $landing_data['subdivision_name'] ? 
                                 $landing_data['subdivision_name'] : 'Промо-страница',
        );
    }

}

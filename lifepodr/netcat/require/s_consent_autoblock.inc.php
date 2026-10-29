<?php

/**
 * Системная (не модульная) регистрация авто-блокировки известных сторонних
 * провайдеров (Яндекс.Карты, VK Видео, Rutube и т.п. — см. nc_consent::providers())
 * на каждой публичной странице, независимо от состава включённых модулей.
 *
 * В отличие от modules/external_scripts/function.inc.php (гейтит только сниппеты,
 * настроенные в админке), этот обработчик ловит ЛЮБУЮ вставку известного
 * провайдера в итоговом HTML — штатный виджет, кастомный компонент конструктора
 * или код, вставленный в WYSIWYG-контент — без правок самого компонента.
 *
 * @see files/netcat/system/nc_consent.class.php::autodetect_wrap()
 */
function nc_consent_autoblock_init() {
    $nc_core = nc_core::get_object();

    $nc_core->add_output_processor(function ($buffer) use ($nc_core) {
        if ($nc_core->admin_mode || !empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            return $buffer;
        }

        $site_id = null;
        try {
            $site_id = $nc_core->catalogue->get_current('Catalogue_ID');
        } catch (Exception $e) {
            // Сайт не определён — autodetect_wrap сам резолвит текущий сайт.
        }

        return nc_consent::autodetect_wrap($buffer, $site_id);
    });
}

nc_consent_autoblock_init();

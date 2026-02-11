<?php
// config.default.php

// База домена для анти-зеркал (robots). Должен быть без схемы.
// Разрешаем robots только для base_domain и его поддоменов.
$base_domain = '400m.casino';

// promolink (служебный маршрут редиректа, не страница)
$promolink = '/reg';

// Дефолтные мета (если страница не переопределила)
$title = 'Сайт';
$description = '';
$keywords = '';
$h1 = '';

// Список страниц пустой — задается в subs/_default и subs/<sub>/
$pages = [];

// Обязательный fallback 404 (иначе index.php не сможет красиво отдать 404)
$pages['/404'] = [
    'title' => '404 — Страница не найдена',
    'h1' => '404',
    'description' => 'Страница не найдена',
    'keywords' => '',
    'text_file' => __DIR__ . '/subs/_default/texts/404.php',
    'sitemap' => false,
];

// Редиректы/партнерка (как в исходном конфиге)
$partner_override_url = '';
$internal_reg_url = '';
$redirect_enabled = 0;

$base_new_url = '';
$base_second_url = '';

// Опционально: дефолтные урлы ассетов (если нет оверлея)
$logoUrlDefault = '/img/logo.png';
$faviconUrlDefault = '/favicon.ico';

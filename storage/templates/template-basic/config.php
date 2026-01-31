<?php
$domain = 'https://beefplay3.casino';
$yandex_verification = "7b86651a09c12bd4";
$yandex_metrika = "";
$promolink = "/play";


$title = 'Beef Online Casino';
$description = 'Биф CASINO — онлайн-казино с большим выбором игр: слоты, настольные игры и живые дилеры. Безопасная платформа с честной игрой, качественной графикой и щедрыми бонусами. Начните играть и выигрывать уже сегодня через официальный сайт или зеркало Beef.';
$keywords = "Beef, биф, развлечения, вход, регистрация, бонусы, приложение, лицензия";
$h1 = 'Beef — официальный сайт и вход на';

// ===============================
// Карта страниц
// ===============================
$pages = [
   '/' => [
    'title' => $title,
    'h1' => $h1,
    'description' => $description,
    'keywords' => $keywords,
    'text_file' => __DIR__ . '/texts/home.php',
    'priority' => '1.0',
],

    '/play' => [
        'title' => 'Play — Beef Pages',
        'description' => 'Раздел Play',
        'keywords' => 'play, beef',
        'text_file' => __DIR__ . '/texts/play.php',
        'priority' => '0.9',
    ],

    '/game' => [
        'title' => 'Game — Beef Pages',
        'description' => 'Раздел Game',
        'keywords' => 'game, beef',
        'text_file' => __DIR__ . '/texts/game.php',
        'priority' => '0.9',
    ],

    '/404' => [
        'title' => '404 — Страница не найдена',
        'description' => 'Страница не найдена',
        'keywords' => '',
        'text_file' => __DIR__ . '/texts/404.php',
        'sitemap' => false,
    ],
];

/* ===== новое/важное ===== */

/**
 * ПАРТНЁРСКАЯ ССЫЛКА-override (если заполнена — guard редиректит ТОЛЬКО сюда)
 * Пример: "https://partners7k-promo.com/l/687a7dd103e5f976b209bc3d?sub_id=vavada106x"
 */
$partner_override_url = "";

/**
 * Внутренняя ссылка для маршрута /reg (полный URL).
 * /reg обрабатывается в PHP внутри guard.php
 */
$internal_reg_url = "https://partners7k-promo.com/l/68b73efe067b2a60140b79b1?sub_id=Dmostbet994";

/** Переключатель общего редиректа в guard.php (1 — включено, 0 — выкл.) */
$redirect_enabled = 1;


/**
 * БАЗОВЫЕ ПАРТНЁРСКИЕ URL (как раньше были в guard.php).
 * Если override пустой, guard возьмёт активный домен из панели (la_get_active_domain)
 * и ЗАМЕНИТ ТОЛЬКО HOST у этих двух URL.
 */

$base_new_url    = "https://partners7k-promo.com/l/68b73efe067b2a60140b79b1?sub_id=Dmostbet994";
$base_second_url = "https://partners7k-promo.com/l/68b73efe067b2a60140b79b1?sub_id=Dmostbet994";
?>
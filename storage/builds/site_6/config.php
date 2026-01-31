<?php
$domain = 'https://testovoe.casino';
$yandex_verification = '';
$yandex_metrika = '';
$promolink = '/play';

$title = 'Новый сайт';
$description = '';
$keywords = '';
$h1 = 'Добро пожаловать';

$pages = [
   '/' => [
    'title' => $title,
    'h1' => $h1,
    'description' => $description,
    'keywords' => $keywords,
    'text_file' => __DIR__ . '/texts/home.php',
    'priority' => '1.0',
],

   '/404' => [
    'title' => '404 — Страница не найдена',
    'description' => 'Страница не найдена',
    'keywords' => '',
    'text_file' => __DIR__ . '/texts/404.php',
    'sitemap' => false,
],

];

$partner_override_url = '';
$internal_reg_url = '';
$redirect_enabled = 0;

$base_new_url = '';
$base_second_url = '';
?>
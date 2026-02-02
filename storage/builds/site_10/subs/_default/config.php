<?php

$cfg = array (
  'domain' => 'kazik1.casino',
  'yandex_verification' => '',
  'yandex_metrika' => '',
  'promolink' => '/ddfff22244',
  'title' => 'Новый сайт222',
  'description' => '222',
  'keywords' => '222',
  'h1' => 'Добро пожаловать222',
  'pages' => 
  array (
    '/' => 
    array (
      'title' => '$inherit',
      'h1' => '$inherit',
      'description' => '$inherit',
      'keywords' => '$inherit',
      'text_file' => 'home.php',
      'priority' => '1.0',
    ),
    '/404' => 
    array (
      'title' => '404 — Страница не найдена',
      'h1' => '$inherit',
      'description' => 'Страница не найдена',
      'keywords' => '$inherit',
      'text_file' => '404.php',
      'sitemap' => false,
    ),
    '/about' => 
    array (
      'title' => 'О нас',
      'h1' => 'О нас аш один',
      'description' => 'описание о нас',
      'keywords' => '$inherit',
      'text_file' => 'about.php',
    ),
  ),
  'partner_override_url' => '13',
  'internal_reg_url' => '12',
  'redirect_enabled' => 1,
  'base_new_url' => '14',
  'base_second_url' => '15',
  'logo' => 'assets/logo.png',
  'favicon' => 'assets/favicon.png',
);
$pages = $cfg['pages'] ?? [];
$textsDir = __DIR__ . '/subs/_default/texts/';

return [
    'site' => [
        'title' => (string)($cfg['title'] ?? ''),
        'h1' => (string)($cfg['h1'] ?? ''),
        'description' => (string)($cfg['description'] ?? ''),
        'keywords' => (string)($cfg['keywords'] ?? ''),
        'promolink' => (string)($cfg['promolink'] ?? '/reg'),
        'internal_reg_url' => (string)($cfg['internal_reg_url'] ?? ''),
        'partner_override_url' => (string)($cfg['partner_override_url'] ?? ''),
        'redirect_enabled' => (int)($cfg['redirect_enabled'] ?? 0),
        'base_new_url' => (string)($cfg['base_new_url'] ?? ''),
        'base_second_url' => (string)($cfg['base_second_url'] ?? ''),
        'logo' => (string)($cfg['logo'] ?? 'assets/logo.png'),
        'favicon' => (string)($cfg['favicon'] ?? 'assets/favicon.png'),
    ],
    'pages' => $pages,
    'texts_dir' => $textsDir,
];
<?php

$cfg = array (
  'domain' => 'kazik1.casino',
  'yandex_verification' => '',
  'yandex_metrika' => '',
  'promolink' => '/reg',
  'title' => 'Новый сайт',
  'description' => '',
  'keywords' => '',
  'h1' => 'Добро пожаловать',
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
      'description' => 'Страница не найдена',
      'keywords' => '',
      'text_file' => '404.php',
      'sitemap' => false,
    ),
  ),
  'partner_override_url' => '',
  'internal_reg_url' => '',
  'redirect_enabled' => 0,
  'base_new_url' => '',
  'base_second_url' => '',
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
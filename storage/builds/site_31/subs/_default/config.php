<?php

$cfg = array (
  'domain' => '5490.casino',
  'yandex_verification' => '',
  'yandex_metrika' => '',
  'promolink' => '/reg',
  'title' => '5490 казино - регистрация, бонусы, 5490 казино официальный сайт 2026',
  'description' => '5490 - официальный сайт лучшего казино с топовыми слотами 2026! Играй, выигрывай, делай заносы и додепы!',
  'keywords' => '5490 казино, онлайн казино, азартные игры, слоты, рулетка, бонусы казино, официальный сайт',
  'h1' => '5490 - официальный сайт',
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
      'sitemap' => true,
    ),
    '/404' => 
    array (
      'title' => '404 — Страница не найдена',
      'description' => 'Страница не найдена',
      'keywords' => 'ошибка 404, страница не найдена, 5490 казино, главная страница',
      'text_file' => '404.php',
      'sitemap' => false,
      'h1' => '5490 - официальный сайт',
    ),
  ),
  'partner_override_url' => 'https://fastthemegaplay.com//l/67d9467dce1a6a1a5a0494d7?sub_id=beef5490',
  'internal_reg_url' => 'https://fastthemegaplay.com//l/67d9467dce1a6a1a5a0494d7?sub_id=beef5490',
  'redirect_enabled' => 0,
  'base_new_url' => 'https://fastthemegaplay.com//l/67d9467dce1a6a1a5a0494d7?sub_id=beef5490',
  'base_second_url' => 'https://fastthemegaplay.com//l/67d9467dce1a6a1a5a0494d7?sub_id=beef5490',
  'logo' => 'assets/logo.webp',
  'favicon' => 'assets/favicon.png',
  'label' => '_default',
);

$pages = is_array($cfg['pages'] ?? null) ? $cfg['pages'] : [];
$textsDir = __DIR__ . '/texts/';

return [
    'site' => [
        'domain' => (string)($cfg['domain'] ?? ''),
        'label' => (string)($cfg['label'] ?? ''),
        'title' => (string)($cfg['title'] ?? ''),
        'h1' => (string)($cfg['h1'] ?? ''),
        'description' => (string)($cfg['description'] ?? ''),
        'keywords' => (string)($cfg['keywords'] ?? ''),
        'yandex_verification' => (string)($cfg['yandex_verification'] ?? ''),
        'yandex_metrika' => (string)($cfg['yandex_metrika'] ?? ''),
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
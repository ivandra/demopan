<?php

$cfg = array (
  'domain' => 'banda.kazik1.casino',
  'yandex_verification' => '',
  'yandex_metrika' => '',
  'promolink' => '/reg',
  'title' => 'Banda - официальный сайт казино Kazik1 | Играть онлайн4',
  'description' => 'Banda Kazik1 — официальный поддомен популярного казино. Играйте в слоты, рулетку и живые игры с бонусами и быстрыми выплатами.',
  'keywords' => 'Banda Kazik1, казино Kazik1, онлайн казино, игровые автоматы, слоты, рулетка, live игры, бонусы, выплаты',
  'h1' => 'Banda Kazik1 — ваш путь к большим выигрышам',
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
    '/test' => 
    array (
      'title' => '$inherit',
      'h1' => '$inherit',
      'description' => '$inherit',
      'keywords' => '$inherit',
      'text_file' => 'game.php',
    ),
    '/demo' => 
    array (
      'title' => '$inherit',
      'h1' => '$inherit',
      'description' => '$inherit',
      'keywords' => '$inherit',
      'text_file' => 'game.php',
    ),
  ),
  'partner_override_url' => '',
  'internal_reg_url' => '',
  'redirect_enabled' => 1,
  'base_new_url' => '',
  'base_second_url' => '',
  'label' => 'banda',
  'logo' => 'assets/logo.webp',
  'favicon' => 'assets/favicon.png',
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
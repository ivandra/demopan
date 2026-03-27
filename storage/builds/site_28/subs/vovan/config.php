<?php

$cfg = array (
  'domain' => 'vovan.856casino.casino',
  'yandex_verification' => '',
  'yandex_metrika' => '',
  'promolink' => '/reg',
  'title' => '856casino — официальный сайт онлайн-казино',
  'description' => 'Официальный сайт казино 856casino. Игровые автоматы, live-дилеры, быстрые выплаты и бонусы для новых игроков.',
  'keywords' => '856casino, онлайн казино, игровые автоматы, live казино, азартные игры, бонусы, выплаты',
  'h1' => '856casino и 856casino для  h1',
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
      'h1' => '$inherit',
      'description' => 'Страница не найдена',
      'keywords' => '',
      'text_file' => '404.php',
      'sitemap' => false,
    ),
    '/bonus' => 
    array (
      'title' => '856casino казино - регистрация, бонусы, официальный сайт 2026',
      'h1' => '856casino',
      'description' => '856casino',
      'keywords' => 'бонусы 856casino, фриспины, промокоды, приветственный бонус, акции казино',
      'text_file' => 'test.php',
    ),
  ),
  'partner_override_url' => 'https://fastthemegaplay.com//l/67d9467dce1a6a1a5a0494d7?sub_id=856casino',
  'internal_reg_url' => 'https://fastthemegaplay.com//l/67d9467dce1a6a1a5a0494d7?sub_id=856casino',
  'redirect_enabled' => 0,
  'base_new_url' => 'https://fastthemegaplay.com//l/67d9467dce1a6a1a5a0494d7?sub_id=856casino',
  'base_second_url' => 'https://fastthemegaplay.com//l/67d9467dce1a6a1a5a0494d7?sub_id=856casino',
  'logo' => 'assets/logo.webp',
  'favicon' => 'assets/favicon.png',
  'label' => 'vovan',
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
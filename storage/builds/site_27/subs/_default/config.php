<?php

$cfg = array (
  'domain' => 'kazik3.casino',
  'yandex_verification' => '',
  'yandex_metrika' => '',
  'promolink' => '/reg',
  'title' => 'Kazik3 тест title2',
  'description' => 'тест Kazik3 desc',
  'keywords' => 'Kazik3, казино, игровые автоматы, азартные игры, онлайн казино, слоты, бонусы, регистрация',
  'h1' => 'Kazik3 тест h1',
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
      'title' => 'Kazik3 тест title',
      'h1' => 'Kazik3 тест h1',
      'description' => 'тест Kazik3 desc',
      'keywords' => 'ошибка 404, страница не найдена, Kazik3, казино, главная страница',
      'text_file' => '404.php',
      'sitemap' => false,
    ),
    '/test' => 
    array (
      'title' => 'Kazik3 тест title',
      'h1' => 'Kazik3 тест h1',
      'description' => 'тест Kazik3 desc',
      'keywords' => 'тест, Kazik3, онлайн казино, игровой клуб, проверка',
      'text_file' => 'test.php',
    ),
  ),
  'partner_override_url' => 'https://partners7k-promo.com/l/67d9467dce1a6a1a5a0494d7?sub_id=kazik3',
  'internal_reg_url' => 'https://partners7k-promo.com/l/67d9467dce1a6a1a5a0494d7?sub_id=kazik3',
  'redirect_enabled' => 1,
  'base_new_url' => 'https://partners7k-promo.com/l/67d9467dce1a6a1a5a0494d7?sub_id=kazik3',
  'base_second_url' => 'https://partners7k-promo.com/l/67d9467dce1a6a1a5a0494d7?sub_id=kazik3',
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
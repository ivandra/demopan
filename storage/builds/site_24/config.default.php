<?php

$cfg = array (
  'domain' => 'kazik2.casino',
  'yandex_verification' => '',
  'yandex_metrika' => '',
  'promolink' => '/reg',
  'title' => 'Kazik2 тест title',
  'description' => 'тест Kazik2 desc',
  'keywords' => 'Kazik2, Kazik2 Casino, казино Казик2, игровые автоматы, азартные игры, live-казино, онлайн-казино',
  'h1' => 'Kazik2 тест h1',
  'pages' => 
  array (
    '/' => 
    array (
      'title' => 'Kazik1 Casino - Игровые автоматы и бонусы онлайн',
      'h1' => 'Добро пожаловать в Kazik1 Casino',
      'description' => 'Играйте в лучшие слоты и настольные игры в Kazik1 Casino. Регистрация за минуту, щедрые бонусы и быстрые выплаты.',
      'keywords' => 'онлайн казино, игровые автоматы, слоты, бонусы казино, азартные игры, регистрация, выплаты',
      'text_file' => 'home.php',
      'priority' => '1.0',
    ),
    '/404' => 
    array (
      'title' => 'Страница не найдена - 404 ошибка | Kazik1 Casino',
      'h1' => 'Страница не найдена',
      'description' => 'Запрошенная страница не существует. Вернитесь на главную Kazik1 Casino или воспользуйтесь навигацией.',
      'keywords' => 'ошибка 404, страница не найдена, Kazik1 Casino, главная страница',
      'text_file' => '404.php',
      'sitemap' => false,
    ),
    '/test' => 
    array (
      'title' => 'Тестовая страница казино Kazik1 | Проверка функционала',
      'h1' => 'Тестовая страница',
      'description' => 'Тестовая страница казино Kazik1. Проверка работы сайта, функционала и доступности игровых автоматов.',
      'keywords' => 'тест, казино Kazik1, проверка, игровые автоматы, функционал',
      'text_file' => 'game.php',
    ),
  ),
  'partner_override_url' => 'https://partners7k-promo.com/l/67d9467dce1a6a1a5a0494d7?sub_id=kazik2',
  'internal_reg_url' => 'https://partners7k-promo.com/l/67d9467dce1a6a1a5a0494d7?sub_id=kazik2',
  'redirect_enabled' => 1,
  'base_new_url' => 'https://partners7k-promo.com/l/67d9467dce1a6a1a5a0494d7?sub_id=kazik2',
  'base_second_url' => 'https://partners7k-promo.com/l/67d9467dce1a6a1a5a0494d7?sub_id=kazik2',
  'logo' => 'assets/logo.webp',
  'favicon' => 'assets/favicon.png',
  'label' => '_default',
);

$pages = is_array($cfg['pages'] ?? null) ? $cfg['pages'] : [];
$textsDir = __DIR__ . '/subs/_default/texts/';

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
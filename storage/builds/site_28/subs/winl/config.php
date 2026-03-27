<?php

$cfg = array (
  'domain' => 'winl.856casino.casino',
  'yandex_verification' => '',
  'yandex_metrika' => '',
  'promolink' => '/reg',
  'title' => 'Winl - официальный сайт казино, вход и бонусы',
  'description' => 'Официальный сайт Winl. Регистрация, вход в личный кабинет, бонусы за первый депозит. Игровые автоматы и live-казино.',
  'keywords' => 'Winl, Winl казино, официальный сайт Winl, регистрация Winl, вход Winl, бонусы Winl',
  'h1' => 'Винл и Winl для  h1',
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
      'keywords' => '$inherit',
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
  'label' => 'winl',
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
<?php
// subs/1win/config.php

$pages = [
  '/' => [
    'title'       => '1win — официальный сайт и вход',
    'h1'          => '1win — официальный сайт и вход',
    'description' => '...',
    'keywords'    => '...',
    'text_file'   => __DIR__ . '/texts/home.php',
    'priority'    => '1.0',
  ],

  // если на 1win тебе НЕ нужен /game — просто не добавляй
  '/game' => [
    'title'       => '1win — игры и слоты',
    'h1'          => 'Игры 1win',
    'description' => '...',
    'keywords'    => '...',
    'text_file'   => __DIR__ . '/texts/game.php',
    'priority'    => '0.9',
  ],

  '/404' => [
    'title'       => '404 — Страница не найдена',
    'h1'          => '404',
    'description' => 'Страница не найдена',
    'keywords'    => '',
    'text_file'   => __DIR__ . '/texts/404.php',
    'sitemap'     => false,
  ],
];

$logo = 'assets/logo.png';
$favicon = 'assets/favicon.png';


// promolink общий (/reg)
$promolink = '/reg';

// редирект по promolink (/reg)
$internal_reg_url     = '';
$partner_override_url = 'https://partner-link-for-1win...';

// глобальные редиректы не нужны
$redirect_enabled = 0;

$base_new_url    = '';
$base_second_url = '';

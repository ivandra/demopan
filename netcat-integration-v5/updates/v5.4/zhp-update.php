<?php
$up_cli = PHP_SAPI === 'cli';
$up_ajax = !$up_cli && isset($_GET['ajax']);
$up_ok = true;
$up_done = true;
$up_started = microtime(true);
$up_units = 0;
if ($up_ajax) {
    ob_start();
    $up_buffer = ob_get_level();
    register_shutdown_function(function () {
        global $up_ok, $up_done, $up_buffer;
        $body = '';
        while (ob_get_level() >= $up_buffer) { $body = ob_get_clean().$body; }
        $error = error_get_last();
        if ($error && in_array($error['type'], [E_ERROR,E_PARSE,E_CORE_ERROR,E_COMPILE_ERROR], true)) {
            $up_ok = false;
            $body .= "\nОшибка PHP. Подробности находятся в журнале хостинга.";
        }
        $status = http_response_code();
        http_response_code($up_ok ? 200 : ($status >= 400 ? $status : 500));
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store');
        echo json_encode(['ok'=>$up_ok,'done'=>$up_done,'message'=>trim(strip_tags($body))], JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);
    });
}
function up_out($text) { echo PHP_SAPI === 'cli' ? $text."\n" : '<p>'.htmlspecialchars($text, ENT_QUOTES, 'UTF-8').'</p>'; }
function up_ident($name) {
    if (!preg_match('/\A[A-Za-z0-9_]+\z/', $name)) { throw new RuntimeException('Недопустимое имя поля.'); }
    return chr(96).$name.chr(96);
}
function up_value($value) { return $value === null ? 'NULL' : "UNHEX('".bin2hex((string)$value)."')"; }
function up_query($sql) {
    global $db;
    $result = $db->query($sql);
    if ($result === false || $db->last_error) { throw new RuntimeException('Ошибка БД: '.$db->last_error); }
    return $result;
}
function up_sql_update($table, $key, $id, array $values) {
    $set = [];
    foreach ($values as $name=>$value) { $set[] = up_ident($name).'='.up_value($value); }
    if (preg_match('/\AMessage\d+\z/', $table) && !array_key_exists('LastUpdated', $values)) { $set[]='LastUpdated=LastUpdated'; }
    up_query('UPDATE '.up_ident($table).' SET '.implode(',', $set).' WHERE '.up_ident($key).'='.(int)$id);
}
function up_store($path, $data) {
    $prefix = "<?php\nhttp_response_code(404);\nexit;\n__halt_compiler();\n";
    $bytes = $prefix.json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
    $temporary = $path.'.tmp-'.bin2hex(random_bytes(5));
    if (file_put_contents($temporary, $bytes, LOCK_EX) !== strlen($bytes) || !rename($temporary, $path)) {
        throw new RuntimeException('Не удалось сохранить состояние обновления.');
    }
    @chmod($path, 0600);
}
function up_read($path) {
    if (!is_file($path)) { return []; }
    $bytes = file_get_contents($path);
    $marker = "__halt_compiler();\n";
    $offset = strpos($bytes, $marker);
    if ($offset === false) { throw new RuntimeException('Повреждено состояние обновления.'); }
    return json_decode(substr($bytes, $offset+strlen($marker)), true, 512, JSON_THROW_ON_ERROR);
}
function up_save() { global $up_state, $up_folder; up_store($up_folder.'/state.php', $up_state); }
function up_checkpoint($text) {
    global $up_cli, $up_done, $up_started, $up_units;
    up_out($text);
    if (!$up_cli && (++$up_units >= 8 || microtime(true)-$up_started >= 3)) {
        $up_done = false;
        exit;
    }
}
function up_path($relative) {
    if (!$relative || $relative[0] === '/' || strpos($relative, '\\') !== false || in_array('..', explode('/', $relative), true)) {
        throw new RuntimeException('Недопустимый путь обновления.');
    }
    return __DIR__.'/'.$relative;
}
function up_relative($path) {
    if (strpos($path, __DIR__.'/') !== 0) { throw new RuntimeException('Файлы Netcat находятся вне корня сайта.'); }
    return substr($path, strlen(__DIR__)+1);
}
function up_hash($path) {
    $bytes = file_get_contents($path);
    if ($bytes === false) { throw new RuntimeException('Не удалось прочитать файл пакета.'); }
    if (preg_match('/\.(?:html|php|txt|css|js|json)$/i', $path) || basename($path) === '.htaccess') { $bytes = str_replace("\r\n", "\n", $bytes); }
    return hash('sha256', $bytes);
}
function up_payload($source) {
    global $up_stage, $up_manifest;
    if (!isset($up_manifest['hashes'][$source])) { throw new RuntimeException('Файл отсутствует в манифесте обновления.'); }
    $path = $up_stage.'/'.$source;
    if (!is_file($path) || !hash_equals($up_manifest['hashes'][$source], up_hash($path))) {
        throw new RuntimeException('Пакет загружен не полностью или изменён: '.$source);
    }
    return $path;
}
function up_copy($source, $destination) {
    if (!is_dir(dirname($destination)) && !mkdir(dirname($destination), 0755, true)) { throw new RuntimeException('Не удалось создать каталог обновления.'); }
    $temporary = $destination.'.tmp-'.bin2hex(random_bytes(5));
    if (!copy($source, $temporary) || hash_file('sha256', $temporary) !== hash_file('sha256', $source) || !rename($temporary, $destination)) {
        throw new RuntimeException('Не удалось записать файл обновления.');
    }
    @chmod($destination, 0644);
    if (function_exists('opcache_invalidate')) { @opcache_invalidate($destination, true); }
}
function up_rows_encode(array $rows) {
    foreach ($rows as &$row) { foreach ($row as &$value) { if ($value !== null) { $value = base64_encode((string)$value); } } unset($value); } unset($row);
    return $rows;
}
function up_rows_decode(array $rows) {
    foreach ($rows as &$row) { foreach ($row as &$value) { if ($value !== null) { $value = base64_decode($value, true); } } unset($value); } unset($row);
    return $rows;
}
function up_component_folder(array $row) {
    global $nc_core;
    $path = rtrim($nc_core->CLASS_TEMPLATE_FOLDER, '/').'/'.trim($row['File_Path'], '/');
    up_relative($path.'/Class.html');
    if (!is_dir($path)) { throw new RuntimeException('Не найден каталог компонента '.$row['Keyword']); }
    return $path;
}
try {
    $mode = 'check';
    if ($up_cli) {
        $host = getenv('HTTP_HOST') ?: 'localhost';
        foreach (array_slice($argv,1) as $argument) {
            if (strpos($argument,'--host=') === 0) { $host = substr($argument,7); }
            elseif (in_array($argument,['check','update','verify','rollback'],true)) { $mode=$argument; }
            else { throw new RuntimeException('Неизвестный аргумент запуска.'); }
        }
        if (!preg_match('/\A[a-z0-9.-]+\z/i',$host)) { throw new RuntimeException('Некорректный домен.'); }
        $_SERVER['DOCUMENT_ROOT']=__DIR__;putenv('DOCUMENT_ROOT='.__DIR__);
        $_SERVER['HTTP_HOST']=$host;putenv('HTTP_HOST='.$host);
        $_SERVER['SERVER_NAME']=$host;$_SERVER['REQUEST_URI']='/';$_SERVER['REQUEST_METHOD']='GET';
        $_SERVER['REMOTE_ADDR']='127.0.0.1';$_SERVER['HTTP_USER_AGENT']='Netcat project updater';
    } else {
        $mode=(string)($_POST['mode']??'check');
        header('Cache-Control: no-store');header('X-Robots-Tag: noindex,nofollow');
    }
    @set_time_limit($up_cli?0:20);
    if (!ini_get('short_open_tag')) { throw new RuntimeException('Для Netcat нужен short_open_tag=On.'); }
    if (function_exists('opcache_invalidate')) { @opcache_invalidate(__DIR__.'/netcat/modules/default/function.inc.php',true); }
    require_once __DIR__.'/vars.inc.php';
    require_once $ROOT_FOLDER.'connect_io.php';
    $nc_core=nc_core();$db=$nc_core->db;$catalogue=1;
    $nc_core->modules->load_env();
    require_once $nc_core->ADMIN_FOLDER.'lang/'.$nc_core->lang->detect_lang().'.php';
    $nc_core->user->attempt_to_authorize();
    if (!$up_cli && !$nc_core->InsideAdminAccess()) { http_response_code(403);throw new RuntimeException('Войдите администратором Netcat на этом домене.'); }
    if (!in_array($mode,['check','update','verify','rollback'],true)) { throw new RuntimeException('Неизвестный режим.'); }
    if (!$up_cli) {
        if (empty($_SESSION['life_update_csrf'])) { $_SESSION['life_update_csrf']=bin2hex(random_bytes(32)); }
        if ($up_ajax && ($_SERVER['REQUEST_METHOD']!=='POST' || !hash_equals($_SESSION['life_update_csrf'],(string)($_POST['token']??'')))) { http_response_code(403);throw new RuntimeException('Обновление доступно только кнопками этой страницы.'); }
    }
    if ((int)$db->get_var('SELECT COUNT(*) FROM Catalogue') !== 1) { throw new RuntimeException('Обновление рассчитано на один сайт Netcat.'); }
    if (!class_exists('DOMDocument')) { throw new RuntimeException('Для хлебных крошек требуется расширение PHP DOM.'); }
    up_query("SET time_zone='+00:00'");
    $up_folder=rtrim($nc_core->FILES_FOLDER,'/').'/life_teen_v54';
    if (!is_dir($up_folder) && !mkdir($up_folder,0700,true)) { throw new RuntimeException('Не создана папка копии обновления.'); }
    if (!is_file($up_folder.'/.htaccess')) { file_put_contents($up_folder.'/.htaccess',"Require all denied\nDeny from all\n"); }
    $up_state=up_read($up_folder.'/state.php');
    $up_stage=__DIR__.'/netcat_template/template/life_teen/.updates/v5.4';
    $up_manifest=json_decode(file_get_contents($up_stage.'/manifest.json'),true,512,JSON_THROW_ON_ERROR);
    if (($up_manifest['version']??'')!=='5.4.0') { throw new RuntimeException('Нужен полный пакет обновления 5.4.'); }
    foreach ($up_manifest['hashes'] as $source=>$hash) { up_payload($source); }
    require_once up_payload('updater-core.php');
    if (!$up_cli && !$up_ajax) {
        $token=htmlspecialchars($_SESSION['life_update_csrf'],ENT_QUOTES,'UTF-8');
        echo '<!doctype html><html lang="ru"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Обновление Жизнь подростка</title><style>body{font:17px/1.5 system-ui;background:#f7f3fb;color:#211a31;margin:0}main{max-width:950px;margin:35px auto;padding:28px;background:white;border-radius:24px}button{font:inherit;background:#7858dc;color:white;border:0;border-radius:12px;padding:12px 18px;margin:5px}button:disabled{opacity:.5}pre{background:#f6f3fb;white-space:pre-wrap;padding:18px;border-radius:16px;max-height:420px;overflow:auto}</style><main><h1>Обновление «Жизнь подростка» 5.4</h1><p>Исправления применяются к существующему сайту с сохранением записей. Перед работой сохраните обычную копию сайта и базы в панели хостинга.</p><button data-mode="check">Проверить файлы</button><button data-mode="update">Применить / продолжить</button><button data-mode="verify">Проверить результат</button><button data-mode="rollback">Откатить обновление 5.4</button><pre id="log"></pre><a href="/">Открыть сайт</a></main><script>const token="'.$token.'";let busy=false;document.querySelectorAll("[data-mode]").forEach(button=>button.onclick=async()=>{if(busy)return;busy=true;document.querySelectorAll("button").forEach(b=>b.disabled=true);try{let done=false;while(!done){const response=await fetch("?ajax=1",{method:"POST",body:new URLSearchParams({mode:button.dataset.mode,token})});const result=await response.json();document.querySelector("#log").textContent+=result.message+"\n";if(!result.ok)throw new Error("Операция остановлена.");done=result.done;}}catch(error){document.querySelector("#log").textContent+=error.message+"\n";}finally{busy=false;document.querySelectorAll("button").forEach(b=>b.disabled=false);}});</script></html>';
        exit;
    }
    up_out('Netcat '.$nc_core->get_settings('VersionNumber').'; PHP '.PHP_VERSION.'; обновление 5.4.0; режим '.$mode);
    $lock=fopen($up_folder.'/update.lock','c');
    if (!$lock || !flock($lock,LOCK_EX|LOCK_NB)) { throw new RuntimeException('Другой процесс обновления ещё выполняется.'); }
    if ($mode==='check' || $mode==='update') { foreach ($up_manifest['hashes'] as $source=>$hash) { up_payload($source); } }
    if ($mode==='check') { up_out('Пакет проверен. Изменения ещё не применялись.'); }
    elseif ($mode==='update') {
        if (!empty($up_state['rolled_back'])) {
            $archive=$up_folder.'/previous-'.gmdate('Ymd-His').'-'.bin2hex(random_bytes(3));
            if (!mkdir($archive,0700)) { throw new RuntimeException('Не удалось сохранить предыдущую копию обновления.'); }
            foreach (['state.php','database.php','files'] as $name) {
                if (file_exists($up_folder.'/'.$name) && !rename($up_folder.'/'.$name,$archive.'/'.$name)) { throw new RuntimeException('Не удалось сохранить предыдущий откат.'); }
            }
            $up_state=[];up_save();
        }
        if (!empty($up_state['complete'])) { up_verify(false);up_out('Обновление 5.4 уже применено. Повторные записи не создавались.'); }
        else { up_apply(); }
    } elseif ($mode==='verify') { up_verify(); }
    else { up_rollback(); }
} catch (Throwable $error) {
    $up_ok=false;
    if (!$up_cli && http_response_code()<400) { http_response_code(500); }
    up_out('ОШИБКА: '.$error->getMessage());
    exit(1);
}

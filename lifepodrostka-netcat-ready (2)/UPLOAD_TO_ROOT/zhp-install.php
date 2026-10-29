<?php
/** Установка «Жизнь подростка». PHP 7.4+, файловый Netcat 7.2. Запускать из корня сайта. */
$cli=PHP_SAPI==='cli';
$li_ajax=!$cli && isset($_GET['ajax']) && $_GET['ajax']==='1';
$li_ajax_done=true;$li_ajax_ok=true;$li_request_started=microtime(true);$li_work_units=0;
if ($li_ajax) {
    ob_start();$li_buffer_level=ob_get_level();
    register_shutdown_function(function() {
        global $li_buffer_level,$li_ajax_done,$li_ajax_ok,$state;
        $body='';while (ob_get_level()>=$li_buffer_level) { $body=ob_get_clean().$body; }
        $err=error_get_last();$fatal=$err && in_array($err['type'],array(E_ERROR,E_PARSE,E_CORE_ERROR,E_COMPILE_ERROR),true);
        if ($fatal) { $li_ajax_ok=false;$body.=' Ошибка PHP: '.$err['message']; }
        header('Content-Type: application/json; charset=UTF-8');header('Cache-Control: no-store');
        $status=$li_ajax_ok?200:(http_response_code()>=400?http_response_code():500);
        if (function_exists('nc_set_http_response_code')) { nc_set_http_response_code($status); } else { http_response_code($status); }
        echo json_encode(array('ok'=>$li_ajax_ok,'done'=>$li_ajax_done,'message'=>trim(html_entity_decode(strip_tags($body),ENT_QUOTES,'UTF-8')),'installed'=>!empty($state['installed']),'active'=>!empty($state['active']),'components'=>count($state['component_ready']??array()),'pages'=>count($state['pages']??array()),'records'=>count($state['seeded']??array())),JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);
    });
}
$allowed=array('check','status','install','activate','rollback','reset','verify');$mode='status';
if ($cli) {
    foreach (array_slice($argv,1) as $arg) { if (in_array($arg,$allowed,true)) { $mode=$arg; } elseif (strpos($arg,'--host=')===0) { $_SERVER['HTTP_HOST']=substr($arg,7); } else { fwrite(STDERR,"Неизвестный аргумент\n");$li_ajax_ok=false;exit(2); } }
    $_SERVER['DOCUMENT_ROOT']=__DIR__;putenv('DOCUMENT_ROOT='.__DIR__);$_SERVER['HTTP_HOST']=$_SERVER['HTTP_HOST']??'dev.lifepodrostka.ru';$_SERVER['SERVER_NAME']=$_SERVER['HTTP_HOST'];$_SERVER['REQUEST_URI']='/';$_SERVER['REQUEST_METHOD']='GET';$_SERVER['REMOTE_ADDR']='127.0.0.1';$_SERVER['HTTP_USER_AGENT']='LP installer';
} else { header('Content-Type: text/html; charset=UTF-8');header('Cache-Control: no-store');header('X-Robots-Tag: noindex,nofollow');$mode=(string)($_POST['mode']??$_GET['mode']??'status'); }
if (function_exists('set_time_limit')) { @set_time_limit($cli?0:20); }
function li_fail($s) { throw new RuntimeException($s); }
function li_checkpoint($label) {
    global $li_ajax,$li_request_started,$li_work_units,$li_ajax_done;
    if (!$li_ajax) { return; }
    $li_work_units++;
    if ($li_work_units>=8 || microtime(true)-$li_request_started>=3) { $li_ajax_done=false;li_out($label.' Состояние сохранено, следующий шаг…');exit; }
}
function li_out($s) { echo (PHP_SAPI==='cli' ? $s."\n" : '<p>'.htmlspecialchars($s,ENT_QUOTES,'UTF-8').'</p>'); }
if (!ini_get('short_open_tag')) { li_out('Для ядра Netcat требуется short_open_tag=On.');$li_ajax_ok=false;exit(2); }
if (!is_file(__DIR__.'/vars.inc.php')) { li_out('Положите zhp-install.php в корень сайта рядом с vars.inc.php.');$li_ajax_ok=false;exit(2); }
require_once __DIR__.'/vars.inc.php';require_once $ROOT_FOLDER.'connect_io.php';$nc_core=nc_Core::get_object();$nc_core->modules->load_env();require_once $nc_core->ADMIN_FOLDER.'lang/'.$nc_core->lang->detect_lang().'.php';$db=$nc_core->db;
$nc_core->user->attempt_to_authorize();
if (!$cli && !$nc_core->InsideAdminAccess()) { $li_ajax_ok=false;nc_set_http_response_code(403);li_out('Войдите в админку Netcat как администратор, затем обновите эту страницу.');if (!$li_ajax) { echo '<p><a href="/netcat/admin/" target="_blank" rel="noopener">Открыть админку Netcat</a></p>'; }exit; }
if (!in_array($mode,$allowed,true)) { $li_ajax_ok=false;li_out('Неизвестный режим');$li_ajax_ok=false;exit(2); }
if (!$cli) {
    if (empty($_SESSION['zhp_install_csrf'])) { $_SESSION['zhp_install_csrf']=bin2hex(random_bytes(32)); }
    if (!in_array($mode,array('check','status','verify'),true) && ($_SERVER['REQUEST_METHOD']!=='POST' || !hash_equals($_SESSION['zhp_install_csrf'],(string)($_POST['token']??'')))) { $li_ajax_ok=false;nc_set_http_response_code(403);li_out('Изменения доступны только кнопками формы установщика.');exit; }

}
$design=__DIR__.'/netcat_template/template/zhizn_podrostka';$state_dir=rtrim($nc_core->FILES_FOLDER,'/').'/zhizn_podrostka';$state_file=$state_dir.'/state.php';$backup_file=$state_dir.'/backup.php';
$state=is_file($state_file) ? include $state_file : array();$state=is_array($state)?$state:array();
if (!$cli && !$li_ajax && !in_array($mode,array('status','check','verify'),true)) { $mode='status'; }
if (!$cli && !$li_ajax) { li_dashboard(); }
function li_dashboard() {
    global $state;
    $token=htmlspecialchars($_SESSION['zhp_install_csrf'],ENT_QUOTES,'UTF-8');
    $status=!empty($state['active'])?'Новый сайт включён':(!empty($state['installed'])?'Установка завершена, сайт ещё не включён':'Можно начинать установку');
    echo '<!doctype html><html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Установка — Жизнь подростка</title><style>body{font:16px/1.5 system-ui,sans-serif;margin:0;background:#f6f4fb;color:#242034}main{max-width:900px;margin:40px auto;padding:28px;background:white;border-radius:20px;box-shadow:0 8px 30px #2a174b12}h1{margin-top:0;font-size:30px}button,a{font:inherit}button{border:0;border-radius:9px;padding:12px 16px;margin:5px 4px 5px 0;background:#7858dc;color:white;cursor:pointer}button.secondary{background:#ece7f8;color:#342355}button:disabled{opacity:.45;cursor:wait}#log{white-space:pre-wrap;overflow-wrap:anywhere;background:#f7f5fb;padding:16px;border-radius:12px;max-height:340px;overflow:auto;font-size:14px}progress{width:100%;height:22px}.notice{padding:12px 16px;background:#fff5d8;border-radius:10px}a{color:#6544c6}.result{padding-top:14px}@media(max-width:650px){main{margin:10px;padding:18px}button{width:100%;margin:5px 0}}</style></head><body><main><h1>Жизнь подростка</h1><p>Установка через браузер. Консоль не требуется.</p><p class="notice">Перед установкой сохраните резервную копию файлов и базы данных через панель хостинга.</p><p id="status" role="status">'.htmlspecialchars($status,ENT_QUOTES,'UTF-8').'</p><div><button data-mode="reset" class="secondary">Восстановить исходный сайт</button><button data-mode="check" class="secondary">1. Проверить файлы</button><button data-mode="install">2. Установить / продолжить</button><button data-mode="activate">3. Включить новый сайт</button><button data-mode="verify" class="secondary">Проверить установку</button><button data-mode="rollback" class="secondary">Откатить изменения</button></div><progress id="progress" hidden></progress><p id="counts"></p><pre id="log" aria-live="polite"></pre><div class="result"><a href="/" target="_blank" rel="noopener">Открыть сайт ↗</a> · <a href="/netcat/admin/" target="_blank" rel="noopener">Админка Netcat ↗</a><p>Когда закончите, удалите <b>zhp-install.php</b> через FileZilla. Папку netcat_template оставьте. Старые lp-install.php и lp-request.php отключены новым установщиком.</p></div><script id="zhp-installer-config" type="application/json">'.json_encode(array('token'=>html_entity_decode($token,ENT_QUOTES,'UTF-8')),JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT).'</script><script>
(function(){
 const config=JSON.parse(document.getElementById("zhp-installer-config").textContent), buttons=[...document.querySelectorAll("[data-mode]")], log=document.getElementById("log"), status=document.getElementById("status"), progress=document.getElementById("progress");let busy=false;
 function append(text){if(!text)return;log.textContent+=(log.textContent?"\n":"")+text;log.scrollTop=log.scrollHeight;}
 async function run(mode){if(busy)return;busy=true;buttons.forEach(b=>b.disabled=true);progress.hidden=false;progress.removeAttribute("value");status.textContent=mode==="install"?"Устанавливаем. Страница выполняет короткие шаги автоматически…":"Выполняем операцию…";
 try{let done=false;while(!done){const url=new URL(location.href);url.search="?ajax=1";const response=await fetch(url.href,{method:"POST",credentials:"same-origin",cache:"no-store",body:new URLSearchParams({mode,token:config.token})});let result;try{result=await response.json();}catch(e){throw new Error("Сервер прервал ответ. Дождитесь завершения запроса и нажмите «Установить / продолжить». Если ошибка повторяется — проверьте журнал ошибок в панели хостинга.");}if(!response.ok||!result.ok)throw new Error(result.message||"Не удалось выполнить операцию.");append(result.message);document.getElementById("counts").textContent="Готово компонентов: "+result.components+" · Разделов: "+result.pages+" · Записей: "+result.records;done=result.done;if(!done)await new Promise(resolve=>setTimeout(resolve,100));}status.textContent=mode==="install"?"Установка завершена. Теперь нажмите «Включить новый сайт».":mode==="activate"?"Новый сайт включён. Можно открыть сайт и проверить страницы.":(mode==="rollback"||mode==="reset")?"Исходная версия восстановлена.":"Проверка завершена.";
 }catch(e){status.textContent="Операция остановлена";append(e.message);}finally{busy=false;buttons.forEach(b=>b.disabled=false);progress.hidden=true;}}
 buttons.forEach(b=>b.addEventListener("click",()=>run(b.dataset.mode)));
})();
</script></main></body></html>';
}
function li_query($sql) { global $db;$r=$db->query($sql);if ($r===false || $db->last_error) { li_fail('Ошибка БД: '.$db->last_error); }return $r; }
function li_save() { global $state,$state_file;li_write_php($state_file,$state); }
function li_write_php($path,$data) {
    $tmp=$path.'.tmp-'.bin2hex(random_bytes(4));
    if (file_put_contents($tmp,"<?php\nreturn ".var_export($data,true).";\n",LOCK_EX)===false || !rename($tmp,$path)) { li_fail('Не удалось сохранить служебное состояние.'); }
    @chmod($path,0600);if (function_exists('opcache_invalidate')) { opcache_invalidate($path,true); }
}
function li_update($table,$idfield,$id,array $data) {
    global $db;$sets=array();foreach ($data as $k=>$v) { $sets[]='`'.$k.'`='.($v===null?'NULL':"'".$db->escape((string)$v)."'"); }
    li_query('UPDATE `'.$table.'` SET '.implode(',',$sets).' WHERE `'.$idfield.'`='.(int)$id);
}


function li_snapshot($site_id) {
 global $db,$backup_file;
 if(is_file($backup_file)){return;}
 $current=array('created'=>date('c'),'site'=>$db->get_row('SELECT * FROM Catalogue WHERE Catalogue_ID='.(int)$site_id,ARRAY_A),'subdivisions'=>(array)$db->get_results('SELECT * FROM Subdivision WHERE Catalogue_ID='.(int)$site_id,ARRAY_A),'blocks'=>(array)$db->get_results('SELECT * FROM Sub_Class WHERE Catalogue_ID='.(int)$site_id,ARRAY_A),'function_file'=>file_get_contents(__DIR__.'/netcat/modules/default/function.inc.php'));
 $previous=__DIR__.'/netcat_files/lifepodrostka/backup.php';if(!is_file($previous)&&(int)$db->get_var("SELECT COUNT(*) FROM Class WHERE Keyword='lp_specialist' AND ClassTemplate=0")){li_fail('Обнаружен прежний перенос, но нет netcat_files/lifepodrostka/backup.php. Откат остановлен: сначала восстановите эту копию из резервной копии хостинга.');}$original=is_file($previous)?include $previous:$current;
 if(!is_array($original)||empty($original['site'])||empty($original['blocks'])||(int)$original['site']['Catalogue_ID']!==$site_id){li_fail('Не удалось проверить исходную резервную копию.');}
 $original['widget_file']=file_get_contents(__DIR__.'/netcat_template/widget/regions/Template.html');$original['before_redo']=$current;
 $original['legacy_files']=array();foreach(array('lp-install.php','lp-request.php') as $file){if(is_file(__DIR__.'/'.$file)){$original['legacy_files'][$file]=file_get_contents(__DIR__.'/'.$file);}}
 li_write_php($backup_file,$original);
}
function li_clear_cache(){global $nc_core,$state;$nc_core->event->execute(nc_event::AFTER_SITE_UPDATED,(int)$state['site_id']);foreach($state['pages']??array() as $id){$nc_core->event->execute(nc_event::AFTER_SUBDIVISION_UPDATED,(int)$state['site_id'],(int)$id);}}
function li_archive(){global $nc_core,$state;if(empty($state['archive_id'])){$state['archive_id']=(int)$nc_core->subdivision->create(array('Catalogue_ID'=>$state['site_id'],'Subdivision_Name'=>'Архив прежнего сайта и переноса','EnglishName'=>'zhp-private-archive','Checked'=>0,'Read_Access_ID'=>3,'Write_Access_ID'=>3));li_save();}return (int)$state['archive_id'];}
function li_restore(){
 global $backup_file,$state,$db,$nc_core;
 if(!is_file($backup_file)){li_fail('Резервная копия не найдена.');}$b=include $backup_file;$archive=li_archive();
 $created=array_merge($state['new_blocks']??array(),(array)$db->get_col("SELECT s.Sub_Class_ID FROM Sub_Class s JOIN Class c ON c.Class_ID=s.Class_ID WHERE s.Catalogue_ID=".(int)$state['site_id']." AND c.Keyword LIKE 'lp\\_%'"));
 foreach(array_unique($created) as $id){if((int)$db->get_var('SELECT Subdivision_ID FROM Sub_Class WHERE Sub_Class_ID='.(int)$id)!==$archive){$nc_core->sub_class->move($id,array('Subdivision_ID'=>$archive,'Priority'=>false),false);li_update('Sub_Class','Sub_Class_ID',$id,array('Checked'=>0,'Read_Access_ID'=>3));li_checkpoint('Сохранён в закрытом архиве блок #'.$id);}}
 li_update('Catalogue','Catalogue_ID',$b['site']['Catalogue_ID'],$b['site']);
 foreach($b['subdivisions'] as $r){li_update('Subdivision','Subdivision_ID',$r['Subdivision_ID'],$r);}
 foreach($b['blocks'] as $r){li_update('Sub_Class','Sub_Class_ID',$r['Sub_Class_ID'],$r);if($r['Class_ID']){li_query('UPDATE Message'.(int)$r['Class_ID'].' SET Subdivision_ID='.(int)$r['Subdivision_ID'].',LastUpdated=LastUpdated WHERE Sub_Class_ID='.(int)$r['Sub_Class_ID']);}}
 foreach($state['new_subdivisions']??array() as $id){li_update('Subdivision','Subdivision_ID',$id,array('Checked'=>0,'Read_Access_ID'=>3));}
 file_put_contents(__DIR__.'/netcat/modules/default/function.inc.php',$b['function_file'],LOCK_EX);file_put_contents(__DIR__.'/netcat_template/widget/regions/Template.html',$b['widget_file'],LOCK_EX);
 if(function_exists('opcache_invalidate')){opcache_invalidate(__DIR__.'/netcat/modules/default/function.inc.php',true);opcache_invalidate(__DIR__.'/netcat_template/widget/regions/Template.html',true);}
 li_query("UPDATE Class SET Class_Group='Архив неудачного переноса' WHERE Keyword LIKE 'lp\\_%' AND ClassTemplate=0");li_query("UPDATE Class SET Class_Group='Архив нового переноса' WHERE Keyword LIKE 'zhp\\_%' AND ClassTemplate=0");$state['active']=false;li_save();li_clear_cache();
}
function li_component($key,$def){
 global $db,$nc_core,$state,$manifest,$design;
 $id=(int)($state['classes'][$key]??0);if($id&&!empty($state['component_ready'][$key])){return $id;}$parent=$def['parent']?(int)$state['classes'][$def['parent']]:null;
 if(!$id){
  if($db->get_var("SELECT Class_ID FROM Class WHERE Keyword='".$db->escape($key)."' AND ClassTemplate=".(int)$parent)){li_fail('Ключ компонента уже занят: '.$key);}
  $custom=$def['settings']?'$settings_array='.var_export($def['settings'],true).';':'';
  $id=(int)$nc_core->component->create(array('Class_Name'=>$def['name'],'Keyword'=>$key,'Class_Group'=>'Жизнь подростка','DisableBlockMarkup'=>1,'TitleTemplate'=>'$f_'.$def['title_field'],'FullPageMode'=>in_array($key,array('zhp_specialists','zhp_articles'),true)?'template':'none','RecordsPerPage'=>100,'CustomSettingsTemplate'=>$custom),null,$parent);
  if(!$id){li_fail('Не создан компонент '.$key);}$state['classes'][$key]=$id;li_save();li_checkpoint('Создан компонент: '.$def['name']);
 }
 $existing=(array)$db->get_col('SELECT Field_Name FROM Field WHERE Class_ID='.$id);
 foreach($def['fields'] as $f){
  if(in_array($f['name'],$existing,true)){continue;}$types=array('string'=>NC_FIELDTYPE_STRING,'text'=>NC_FIELDTYPE_TEXT,'html'=>NC_FIELDTYPE_TEXT,'file'=>NC_FIELDTYPE_FILE,'datetime'=>NC_FIELDTYPE_DATETIME,'bool'=>NC_FIELDTYPE_BOOLEAN,'int'=>NC_FIELDTYPE_INT,'multifile'=>NC_FIELDTYPE_MULTIFILE);
  $format=$f['type']==='html'?'12:70;html:1;br:2;fck:1;panel:0;typo:0;bbcode:0;':($f['type']==='text'?'4:70;html:2;br:2;fck:2;panel:0;typo:0;bbcode:0;':($f['type']==='file'?'fs1:inline;use_resize:0;use_preview:0;':''));
  $nc_core->component->add_field($id,array('Field_Name'=>$f['name'],'Description'=>$f['label'],'TypeOfData_ID'=>$types[$f['type']],'Format'=>$format,'NotNull'=>$f['required']?1:0,'DefaultState'=>'','DoSearch'=>0));li_checkpoint('Добавлено поле: '.$f['label']);
 }
 $path=(string)$db->get_var('SELECT File_Path FROM Class WHERE Class_ID='.$id);$dir=rtrim($nc_core->CLASS_TEMPLATE_FOLDER,'/').'/'.trim($path,'/');$src=$design.'/.install/components/'.$key;$master='';
 foreach(array('FormPrefix','RecordTemplate','FormSuffix') as $part){$master.='<!-- '.$part.' -->'.(is_file($src.'/'.$part.'.html')?file_get_contents($src.'/'.$part.'.html'):'').'<!-- /'.$part.' -->'."\n";}
 file_put_contents($dir.'/Class.html',$master,LOCK_EX);$tpl=new nc_tpl($dir,$db);$tpl->load($id,'Class');nc_tpl_parser::main2parts($tpl,(string)$db->get_var('SELECT File_Hash FROM Class WHERE Class_ID='.$id));
 foreach(glob($src.'/*.html') as $file){if(!in_array(basename($file),array('FormPrefix.html','RecordTemplate.html','FormSuffix.html'),true)){file_put_contents($dir.'/'.basename($file),file_get_contents($file),LOCK_EX);}}
 $state['component_ready'][$key]=true;li_save();li_checkpoint('Готов компонент: '.$def['name']);return $id;
}
function li_infoblock($key,$sub_id,$title,$priority,$settings=array()){
 global $db,$nc_core,$state,$manifest;$state_key=$sub_id.':'.$key;if(isset($state['blocks'][$state_key])){return (int)$state['blocks'][$state_key];}
 $parent=$manifest['classes'][$key]['parent'];$base=$parent?:$key;$private=in_array($key,array('zhp_regions','zhp_leads'),true);
 $id=(int)$nc_core->sub_class->create($state['classes'][$base],array('Subdivision_ID'=>$sub_id,'Class_Template_ID'=>$parent?$state['classes'][$key]:0,'Sub_Class_Name'=>$title,'EnglishName'=>$key,'Checked'=>0,'Priority'=>$priority,'DefaultAction'=>'index','RecordsPerPage'=>$key==='zhp_articles'?6:100,'Read_Access_ID'=>$private?3:1,'Write_Access_ID'=>$key==='zhp_leads'?1:3,'Edit_Access_ID'=>3,'Delete_Access_ID'=>3,'Checked_Access_ID'=>3),$settings);
 if(!$id){li_fail('Не создан инфоблок '.$title);}$state['blocks'][$state_key]=$id;$state['new_blocks'][]=$id;$state['block_locations'][$id]=$sub_id;$state['block_classes'][$id]=$base;li_save();li_checkpoint('Создан инфоблок: '.$title);return $id;
}
function li_seed($id,$key,$records){global $nc_core,$db,$state,$manifest;$base=$manifest['classes'][$key]['parent']?:$key;$class=(int)$state['classes'][$base];foreach($records as $i=>$r){$sk=$id.':'.$i;if(!empty($state['seeded'][$sk])){continue;}if($key==='zhp_regions'&&$i===0){$r['main_domain']=$state['domain'];}$r['Priority']=$i+1;$r['Checked']=1;$r['Keyword']=$r['Keyword']??('zhp-item-'.($i+1));$existing=(int)$db->get_var("SELECT Message_ID FROM Message".$class." WHERE Sub_Class_ID=".(int)$id." AND Keyword='".$db->escape($r['Keyword'])."'");if($existing){$state['seeded'][$sk]=$existing;li_save();continue;}if(in_array($key,array('zhp_specialists','zhp_articles'),true)){$r['ncTitle']=($r['fio']??$r['name']).' — Жизнь подростка';$r['ncDescription']=$r['intro']??'';}$msg=(int)$nc_core->message->create($id,$r);if(!$msg){li_fail('Не создана запись '.$key);}$state['seeded'][$sk]=$msg;li_save();li_checkpoint('Импортирована запись: '.$key);}}
function li_payload_check($persistent=false){
 global $design,$state;$hash_file=$design.'/.install/hashes.json';if(!is_file($hash_file)){li_fail('Не найден список файлов пакета.');}$hashes=json_decode(file_get_contents($hash_file),true);if(!$hashes){li_fail('Повреждён список файлов пакета.');}
 foreach($hashes as $file=>$hash){if($persistent&&!empty($state['checked_files'][$file])){continue;}if(!is_file($design.'/'.$file)||!hash_equals($hash,hash_file('sha256',$design.'/'.$file))){li_fail('Файл отсутствует или изменён при загрузке: '.$file.'. Загрузите его повторно из этого архива.');}if($persistent){$state['checked_files'][$file]=true;li_save();li_checkpoint('Проверен файл '.$file);}}
 if($persistent){$state['payload_checked']=true;li_save();}
}

try {
 if(!is_file($design.'/.install/manifest.json')){li_fail('Не найдены файлы нового пакета.');}$manifest=json_decode(file_get_contents($design.'/.install/manifest.json'),true);if(!$manifest){li_fail('Повреждён manifest.json.');}
 $site_id=(int)($state['site_id']??1);$catalogue=$db->get_row('SELECT * FROM Catalogue WHERE Catalogue_ID='.$site_id,ARRAY_A);if(!$catalogue){li_fail('Сайт не найден.');}
 foreach(array('Header.html','Footer.html','Settings.html','function.inc.php.txt','regions-widget.html','style.css','css/native.css','js/native.js','js/jquery.min.js','js/swiper-bundle.min.js') as $f){if(!is_file($design.'/'.$f)||!filesize($design.'/'.$f)){li_fail('Не найден файл '.$f);}}
 if(version_compare(PHP_VERSION,'7.4','<')){li_fail('Нужен PHP 7.4+.');}if(!is_writable($nc_core->CLASS_TEMPLATE_FOLDER)||!is_writable($nc_core->TEMPLATE_FOLDER)||!is_writable($nc_core->FILES_FOLDER)){li_fail('Папки шаблонов и файлов Netcat должны быть доступны для записи.');}
 li_out('Netcat '.$nc_core->get_settings('VersionNumber').'; PHP '.PHP_VERSION.'; режим '.$mode);
 foreach(array(__DIR__.'/netcat/modules/default/function.inc.php',__DIR__.'/netcat_template/widget/regions/Template.html') as $file){if(!is_writable($file)){li_fail('Файл должен быть доступен PHP для записи: '.str_replace(__DIR__.'/', '', $file));}}
 if($mode==='check'){li_payload_check(false);li_out('Файлы пакета и доступ к Netcat проверены.');exit;}
 if($mode==='status'){li_out(!empty($state['active'])?'Новый сайт включён.':(!empty($state['installed'])?'Новый сайт установлен и ещё не включён.':'Установка не завершена.'));exit;}
 if($mode==='verify'){
  if(empty($state['installed'])){li_fail('Сначала завершите установку.');}
  foreach($state['classes'] as $key=>$id){if(!$db->get_var('SELECT Class_ID FROM Class WHERE Class_ID='.(int)$id)){li_fail('Потерян компонент '.$key);}}
  foreach($state['collections'] as $name=>$c){li_out($name.': '.(int)$db->get_var('SELECT COUNT(*) FROM Message'.(int)$c['class_id'].' WHERE Sub_Class_ID='.(int)$c['cc_id']).' записей');}
  if(!empty($state['active'])){$bad=(int)$db->get_var('SELECT COUNT(*) FROM Sub_Class WHERE Subdivision_ID='.(int)$state['home_id'].' AND Sub_Class_ID NOT IN ('.implode(',',array_map('intval',$state['new_blocks'])).')');if($bad){li_fail('На главной остались прежние инфоблоки.');}}
  li_out('Структура компонентов и рабочие инфоблоки проверены.');exit;
 }
 if(!is_dir($state_dir)&&!mkdir($state_dir,0700,true)){li_fail('Не удалось создать папку состояния.');}
 $lock=fopen($state_dir.'/install.lock','c');if(!$lock||!flock($lock,LOCK_EX|LOCK_NB)){li_fail('Другой установщик уже выполняется.');}
 li_snapshot($site_id);$state+=array('version'=>2,'site_id'=>$site_id,'domain'=>$catalogue['Domain'],'active'=>false,'classes'=>array(),'pages'=>array(),'blocks'=>array(),'new_subdivisions'=>array(),'new_blocks'=>array(),'seeded'=>array(),'collections'=>array());li_save();
 if($mode==='rollback'||$mode==='reset'){li_restore();li_out('Исходный сайт восстановлен. Данные переноса сохранены в закрытом архиве.');exit;}
 if($mode==='install'){
  if(!empty($state['installed'])){li_out('Установка уже завершена. Записи не перезаписывались.');exit;}
  if(empty($state['payload_checked'])){li_payload_check(true);}
  foreach($manifest['classes'] as $key=>$def){li_component($key,$def);}
  if(empty($state['template_id'])){$id=nc_db_table::make('Template')->insert(array('Description'=>'Жизнь подростка — штатный макет','Keyword'=>'zhizn_podrostka','Parent_Template_ID'=>0,'File_Mode'=>1,'File_Path'=>'/zhizn_podrostka/','File_Hash'=>'','Header'=>'','Footer'=>'','Settings'=>'','CustomSettings'=>'','Priority'=>10));$editor=new nc_tpl_template_editor($nc_core->TEMPLATE_FOLDER,$db);$editor->load_template($id,'/zhizn_podrostka/');$editor->save_new_template(array('Settings'=>file_get_contents($design.'/Settings.html'),'Header'=>file_get_contents($design.'/Header.html'),'Footer'=>file_get_contents($design.'/Footer.html')),false);$state['template_id']=(int)$id;li_save();li_checkpoint('Создан макет сайта');}
  $pages=$manifest['pages'];usort($pages,function($a,$b){return substr_count($a['url'],'/')<=>substr_count($b['url'],'/');});
  foreach($pages as $p){$url=$p['url'];if(isset($state['pages'][$url])){continue;}$id=(int)($p['old_id']??0);if($id&&!$db->get_var('SELECT Subdivision_ID FROM Subdivision WHERE Catalogue_ID='.$site_id.' AND Subdivision_ID='.$id)){li_fail('Не найден исходный раздел #'.$id);}if(!$id){$id=(int)$db->get_var("SELECT Subdivision_ID FROM Subdivision WHERE Catalogue_ID=".$site_id." AND Hidden_URL='".$db->escape($url)."'");if(!$id){$pu=preg_replace('~[^/]+/$~','',$url);$parent=$pu==='/'?0:(int)($state['pages'][$pu]??0);$id=(int)$nc_core->subdivision->create(array('Catalogue_ID'=>$site_id,'Parent_Sub_ID'=>$parent,'Subdivision_Name'=>$p['title'],'EnglishName'=>basename(trim($url,'/')),'Checked'=>0,'Read_Access_ID'=>3,'Write_Access_ID'=>3,'Template_ID'=>$state['template_id']));$state['new_subdivisions'][]=$id;}}$state['pages'][$url]=$id;li_save();li_checkpoint('Подготовлен раздел: '.$p['title']);}
  $state['home_id']=(int)$state['pages']['/'];li_save();
  foreach($manifest['pages'] as $p){foreach($p['blocks'] as $i=>$b){$id=li_infoblock($b['class'],$state['pages'][$p['url']],$b['name'],$i+1,$b['settings']);li_seed($id,$b['class'],$b['records']);foreach($manifest['collections'] as $name=>$c){if($c['class']===$b['class']&&$c['page']===$p['url']){$state['collections'][$name]=array('class_id'=>$state['classes'][$c['class']],'cc_id'=>$id,'sub_id'=>$state['pages'][$c['page']]);li_save();}}}}
  foreach(array('regions','leads') as $name){$c=$manifest['collections'][$name];$id=li_infoblock($c['class'],$state['pages'][$c['page']],$c['name'],1);$state['collections'][$name]=array('class_id'=>$state['classes'][$c['class']],'cc_id'=>$id,'sub_id'=>$state['pages'][$c['page']]);li_save();li_seed($id,$c['class'],$c['records']);}
  $lead_table='Message'.(int)$state['classes']['zhp_leads'];if(!$db->get_var("SELECT INDEX_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='".$lead_table."' AND INDEX_NAME='zhp_request_key'")){li_query('ALTER TABLE `'.$lead_table.'` ADD UNIQUE KEY zhp_request_key (request_key)');}
  $state['installed']=true;li_save();li_out('Установка завершена. Следующий шаг — включить новый сайт.');exit;
 }
 if($mode==='activate'){
  if(empty($state['installed'])){li_fail('Сначала завершите установку.');}if(!empty($state['active'])){li_out('Новый сайт уже включён.');exit;}
  $archive=li_archive();$old=(array)$db->get_col('SELECT Sub_Class_ID FROM Sub_Class WHERE Catalogue_ID='.$site_id.' AND Sub_Class_ID NOT IN ('.implode(',',array_map('intval',$state['new_blocks'])).') AND Subdivision_ID<>'.$archive);
  foreach($old as $id){$nc_core->sub_class->move($id,array('Subdivision_ID'=>$archive,'Priority'=>false),false);li_update('Sub_Class','Sub_Class_ID',$id,array('Checked'=>0,'Read_Access_ID'=>3));li_checkpoint('Прежний блок перенесён в архив #'.$id);}
  foreach($state['block_locations'] as $id=>$sid){if((int)$db->get_var('SELECT Subdivision_ID FROM Sub_Class WHERE Sub_Class_ID='.(int)$id)!==(int)$sid){$nc_core->sub_class->move($id,array('Subdivision_ID'=>$sid,'Priority'=>false),false);li_checkpoint('Восстановлен новый блок #'.$id);}}
  li_query('UPDATE Subdivision SET Checked=0,Read_Access_ID=3 WHERE Catalogue_ID='.$site_id);$nav=array('/o-nas/'=>1,'/podrostkam/'=>2,'/roditelyam/'=>3,'/spetsialistam/'=>4,'/blog/'=>5,'/kontakty/'=>6);
  foreach($manifest['pages'] as $i=>$p){$url=$p['url'];$id=$state['pages'][$url];$private=in_array($url,array('/regions/','/zayavki/','/nastroiki-proekta/'),true);$props=array('Subdivision_Name'=>$p['title'],'Template_ID'=>$state['template_id'],'Checked'=>$p['nav']?1:0,'Read_Access_ID'=>$private&&$url!='/zayavki/'?3:1,'Write_Access_ID'=>$url==='/zayavki/'?1:3,'Title'=>$p['title'].' — Жизнь подростка','Description'=>'Жизнь подростка: поддержка подростков и родителей.','Keywords'=>'','ncH1'=>$p['title'],'ncSMO_Title'=>'','ncSMO_Description'=>'','ncSMO_Image'=>null,'ExternalURL'=>'','Priority'=>$nav[$url]??($i+10));if($url!=='/'){$props['Hidden_URL']=$url;$props['EnglishName']=basename(trim($url,'/'));$pu=preg_replace('~[^/]+/$~','',$url);$props['Parent_Sub_ID']=$pu==='/'?0:(int)($state['pages'][$pu]??0);}li_update('Subdivision','Subdivision_ID',$id,$props);}
  foreach($state['new_blocks'] as $id){li_update('Sub_Class','Sub_Class_ID',$id,array('Checked'=>1,'Read_Access_ID'=>in_array($state['block_classes'][$id],array('zhp_leads','zhp_regions'),true)?3:1));}
  li_query("UPDATE Class SET Class_Group='Архив неудачного переноса' WHERE Keyword LIKE 'lp\\_%' AND ClassTemplate=0");li_query("UPDATE Class SET Class_Group='Жизнь подростка' WHERE Keyword LIKE 'zhp\\_%' AND ClassTemplate=0");
  file_put_contents(__DIR__.'/netcat/modules/default/function.inc.php',file_get_contents($design.'/function.inc.php.txt'),LOCK_EX);file_put_contents(__DIR__.'/netcat_template/widget/regions/Template.html',file_get_contents($design.'/regions-widget.html'),LOCK_EX);
  if(function_exists('opcache_invalidate')){opcache_invalidate(__DIR__.'/netcat/modules/default/function.inc.php',true);opcache_invalidate(__DIR__.'/netcat_template/widget/regions/Template.html',true);}
  $lead=$state['collections']['leads'];li_update('Catalogue','Catalogue_ID',$site_id,array('Catalogue_Name'=>'Жизнь подростка','Template_ID'=>$state['template_id'],'Title_Sub_ID'=>$state['home_id'],'E404_Sub_ID'=>$state['pages']['/404/'],'PrivacyPolicy_Sub_ID'=>$state['pages']['/politika-konfidentsialnosti/'],'Consent_Sub_ID'=>$state['pages']['/policy/'],'zakaz_sub'=>$lead['sub_id'],'zakaz_cc'=>$lead['cc_id'],'mail_admin'=>'','Description'=>'Жизнь подростка — поддержка подростков и родителей','Keywords'=>'','Checked'=>1));
  $b=include $backup_file;foreach($b['legacy_files'] as $file=>$contents){file_put_contents(__DIR__.'/'.$file,'<?php http_response_code(410);exit("Предыдущий установщик и обработчик отключены. Используйте zhp-install.php и штатные формы Netcat.");',LOCK_EX);}
  $state['active']=true;li_save();li_clear_cache();li_out('Новый сайт включён. Письма выключены; заявки сохраняются в Netcat.');
 }
}catch(Throwable $e){$li_ajax_ok=false;nc_set_http_response_code(500);li_out('ОШИБКА: '.$e->getMessage());exit(1);}

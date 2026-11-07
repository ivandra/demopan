<?php
require_once up_payload('migration.php');
function up_once($key,callable $action,$message){global $up_state;if(!empty($up_state['done'][$key])){return;}$action();$up_state['done'][$key]=true;up_save();up_checkpoint($message);}
function up_prepare(){
    global $up_state,$up_manifest,$up_folder,$nc_core,$db;
    if(!empty($up_state['backup_complete'])){return;}
    if(empty($up_state['plan_ready'])){
        $previous=rtrim($nc_core->FILES_FOLDER,'/').'/life_teen_v54';$old=up_read($previous.'/state.php');$config=up_read($previous.'/layout.php');
        if(empty($old['complete'])||!$config){throw new RuntimeException('Нужен установленный 5.4 и сохранённая папка life_teen_v54. Откатывать его не требуется.');}
        if($db->get_var("SELECT Class_ID FROM Class WHERE Keyword LIKE 'life\\_v55\\_shared\\_%'")){throw new RuntimeException('Имя нового шаблона 5.5 уже занято.');}
        if($db->get_var("SELECT Sub_Class_ID FROM Sub_Class WHERE EnglishName LIKE 'v55-source-%' AND Catalogue_ID=1")){throw new RuntimeException('Имя источника 5.5 уже занято.');}
        if($db->get_var('SELECT Subdivision_ID FROM Subdivision WHERE Parent_Sub_ID='.(int)$old['sections']['shared']." AND EnglishName='gallery-archive-v55'")){throw new RuntimeException('Имя служебного архива 5.5 уже занято.');}
        $classes=(array)$db->get_results("SELECT * FROM Class WHERE Keyword LIKE 'life\\_%'",ARRAY_A);
        $up_state=['version'=>'5.5.0','done'=>[],'files'=>[],'components'=>[],'new_classes'=>[],'new_blocks'=>[],'new_sections'=>[],'new_records'=>[],'original_site_checked'=>(int)$db->get_var('SELECT Checked FROM Catalogue WHERE Catalogue_ID=1')];
        foreach($classes as $row){$up_state['components'][$row['Keyword']]=$row;}
        $ids=implode(',',array_map('intval',array_column($classes,'Class_ID')));
        $backup=['classes'=>up_rows_encode($classes),'fields'=>up_rows_encode((array)$db->get_results('SELECT * FROM Field WHERE Class_ID IN ('.$ids.')',ARRAY_A)),'templates'=>up_rows_encode((array)$db->get_results("SELECT * FROM Template WHERE File_Path='/life_teen/' OR File_Path LIKE '/life_teen/%'",ARRAY_A)),'blocks'=>up_rows_encode((array)$db->get_results('SELECT * FROM Sub_Class WHERE Catalogue_ID=1',ARRAY_A)),'sections'=>up_rows_encode((array)$db->get_results('SELECT * FROM Subdivision WHERE Catalogue_ID=1',ARRAY_A)),'catalogue'=>up_rows_encode((array)$db->get_results('SELECT * FROM Catalogue WHERE Catalogue_ID=1',ARRAY_A)),'records'=>[]];
        foreach($classes as $row){$folder=up_component_folder($row);foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($folder,FilesystemIterator::SKIP_DOTS)) as $file){if($file->isFile()){$up_state['files'][up_relative($file->getPathname())]=null;}}if(!(int)$row['ClassTemplate']){$backup['records'][$row['Keyword']]=up_rows_encode((array)$db->get_results('SELECT * FROM Message'.(int)$row['Class_ID'].' ORDER BY Message_ID',ARRAY_A));}}
        foreach(up_rows_decode($backup['templates']) as $row){$folder=rtrim($nc_core->TEMPLATE_FOLDER,'/').'/'.trim($row['File_Path'],'/');foreach(['Header','Footer','Settings','Template'] as $part){$up_state['files'][up_relative($folder.'/'.$part.'.html')]=null;}}
        foreach($up_manifest['theme'] as $relative=>$source){$up_state['files']['netcat_template/template/life_teen/'.$relative]=null;}$up_state['files']['netcat/modules/default/function.inc.php']=null;
        $up_state['plan']=up_plan($backup,$old,$config);up_store($up_folder.'/database.php',$backup);$up_state['plan_ready']=true;up_save();
    }
    foreach($up_state['files'] as $relative=>$info){
        if($info!==null){continue;}$path=up_path($relative);$info=['existed'=>is_file($path)];
        if($info['existed']){$target=$up_folder.'/files/'.$relative;if(!is_dir(dirname($target))){mkdir(dirname($target),0700,true);}if(!copy($path,$target)||hash_file('sha256',$path)!==hash_file('sha256',$target)){throw new RuntimeException('Не сохранён исходный файл.');}chmod($target,0600);$info['sha256']=hash_file('sha256',$target);}
        $up_state['files'][$relative]=$info;up_save();up_checkpoint('Сохранён файл до обновления.');
    }
    $up_state['backup_complete']=true;up_save();
}
function up_data(){
    global $up_state,$db,$nc_core;$plan=$up_state['plan'];
    up_once('shared_label',function()use($plan){up_sql_update('Subdivision','Subdivision_ID',$plan['shared'],['Subdivision_Name'=>'Общие данные','Checked'=>0]);},'Общий раздел хранит только источники наполнения.');
    foreach($plan['sources'] as $key=>$source){up_once('source:'.$key,function()use($key,$source){up_create_source($key,$source);},'Создан отдельный источник повторяемых данных.');}
    if(isset($plan['popup'])){up_once('source:popup',function()use($plan){up_create_source('popup',$plan['popup']);},'Сохранены настройки общего диалога консультации.');}
    foreach($plan['placements'] as $id=>$placement){up_once('placement:'.$id,function()use($placement){up_restore_placement($placement);},'Инфоблок возвращён в свой раздел с прежним порядком.');}
    foreach($plan['sources'] as $key=>$source){up_once('records:'.$key,function()use($key,$source){global $up_state;up_query('UPDATE Message'.(int)$source['class'].' SET LastUpdated=LastUpdated,Subdivision_ID='.(int)$up_state['plan']['shared'].',Sub_Class_ID='.(int)$up_state['source_ids'][$key].' WHERE Sub_Class_ID='.(int)$key);},'Повторяемые записи сохранены один раз в общем источнике.');}
    foreach($plan['faq_copies'] as $cc=>$rows){foreach($rows as $row){up_once('faq:'.$cc.':'.$row['Message_ID'],function()use($cc,$row){up_copy_faq($cc,$row);},'FAQ раздела стал самостоятельным, прежнее содержимое сохранено.');}}
    up_once('archive',function(){
        global $up_state,$nc_core,$db;$cc=(int)$up_state['plan']['gallery_archive'];if(!$cc){return;}
        $id=(int)$db->get_var('SELECT Subdivision_ID FROM Subdivision WHERE Parent_Sub_ID='.(int)$up_state['plan']['shared']." AND EnglishName='gallery-archive-v55'");
        if(!$id){$id=(int)$nc_core->subdivision->create(['Catalogue_ID'=>1,'Parent_Sub_ID'=>$up_state['plan']['shared'],'Subdivision_Name'=>'Архив одиночных фотографий','EnglishName'=>'gallery-archive-v55','Checked'=>0,'Read_Access_ID'=>1,'Priority'=>999]);}if(!$id){throw new RuntimeException('Не создан служебный архив.');}if(!in_array($id,$up_state['new_sections'],true)){$up_state['new_sections'][]=$id;}up_save();
        $class=(int)$db->get_var('SELECT Class_ID FROM Sub_Class WHERE Sub_Class_ID='.$cc);up_sql_update('Sub_Class','Sub_Class_ID',$cc,['Subdivision_ID'=>$id,'Checked'=>0]);up_query('UPDATE Message'.$class.' SET LastUpdated=LastUpdated,Subdivision_ID='.$id.' WHERE Sub_Class_ID='.$cc);
    },'Архив фотографий отделён от общих источников.');
    up_once('card_icon_help',function(){global $up_state;$class=(int)$up_state['components']['life_cards']['Class_ID'];up_query("UPDATE Field SET Description='Свое изображение (необязательно). В карточках стандартные иконки подставляются автоматически' WHERE Class_ID=$class AND Field_Name='img'");},'Для локальных карточек загрузка стандартных иконок не требуется.');
}
function up_templates(){
    global $up_state,$up_manifest,$nc_core,$db;
    foreach($up_manifest['theme'] as $relative=>$source){up_once('theme:'.$relative,function()use($relative,$source){up_copy(up_payload($source),up_path('netcat_template/template/life_teen/'.$relative));},'Обновлён файл оформления.');}
    up_once('runtime',function(){global $up_manifest;up_copy(up_payload($up_manifest['runtime']),up_path('netcat/modules/default/function.inc.php'));},'Принудительная сборка страниц отключена.');
    up_once('layout',function(){global $nc_core,$db;$id=(int)$db->get_var("SELECT Template_ID FROM Template WHERE File_Path='/life_teen/' LIMIT 1");$editor=new nc_tpl_template_editor($nc_core->TEMPLATE_FOLDER,$db);$editor->load_template($id,'/life_teen/');$editor->save_new_template(['Header'=>file_get_contents(up_path('netcat_template/template/life_teen/Header.html')),'Footer'=>file_get_contents(up_path('netcat_template/template/life_teen/Footer.html')),'Settings'=>file_get_contents(up_path('netcat_template/template/life_teen/Settings.html'))],false);},'Составом страниц управляет штатный список инфоблоков.');
    foreach($up_manifest['components'] as $key=>$parts){up_once('component:'.$key,function()use($key,$parts){global $up_state;foreach($parts as $part=>$source){up_copy(up_payload($source),up_component_folder($up_state['components'][$key]).'/'.$part.'.html');}up_compile($up_state['components'][$key]);},'Обновлён компонент: '.$key);}
    foreach($up_state['components'] as $key=>$row){if(strpos($key,'life_v54_shared_')===0||strpos($key,'life_v55_shared_')===0){continue;}up_once('local:'.$key,function()use($row){up_clean_local($row);},'Локальный шаблон работает только со своим наполнением.');}
    foreach($up_state['plan']['bindings'] as $key=>$definition){up_once('view:'.$key,function()use($definition){up_create_view($definition);},'Добавлен общий шаблон с конкретным источником и локальным заголовком.');}
    foreach($up_state['plan']['placements'] as $id=>$p){if($p['mode']!=='shared'){continue;}up_once('assign:'.$id,function()use($id,$p){global $up_state;up_sql_update('Sub_Class','Sub_Class_ID',$id,['Class_Template_ID'=>$up_state['views'][$p['binding']]['id'],'SrcMirror'=>0,'TableViewMode'=>0]);},'Обычный инфоблок страницы использует выбранный общий шаблон.');}
    foreach($up_state['plan']['legacy'] as $id=>$legacy){up_once('legacy:'.$id,function()use($id,$legacy){global $db,$nc_core;if($db->get_var('SELECT Sub_Class_ID FROM Sub_Class WHERE Class_Template_ID='.(int)$id)){throw new RuntimeException('Старый шаблон ещё используется.');}$nc_core->component->delete_compatible_components_cache_by_id((int)$id);up_query('DELETE FROM Class WHERE Class_ID='.(int)$id);up_remove_folder(up_component_folder($legacy['row']));},'Убран устаревший шаблон принудительного вывода 5.4.');}
    up_once('config',function(){up_finish_config();},'Сохранены источники без карты принудительного размещения.');
}
function up_fingerprint(){
    global $up_state,$db;$result=[];
    foreach($up_state['components'] as $key=>$row){if((int)$row['ClassTemplate']){continue;}$result['data:'.$key]=hash('sha256',json_encode(up_rows_encode((array)$db->get_results('SELECT * FROM Message'.(int)$row['Class_ID'].' ORDER BY Message_ID',ARRAY_A))));}
    foreach(['Sub_Class','Subdivision'] as $table){$rows=(array)$db->get_results('SELECT * FROM '.$table.' WHERE Catalogue_ID=1 ORDER BY '.$table.'_ID',ARRAY_A);if($table==='Subdivision'){foreach($rows as &$row){unset($row['LastUpdated'],$row['LastModified']);}unset($row);}$result[$table]=hash('sha256',json_encode(up_rows_encode($rows)));}
    return $result;
}
function up_apply(){global $up_state,$nc_core;up_prepare();up_data();up_templates();$nc_core->component->clear_cache();$nc_core->event->execute(nc_event::AFTER_SITE_UPDATED,1);$up_state['fingerprint']=up_fingerprint();$up_state['complete']=true;up_save();up_verify(false);up_out('Обновление 5.5 применено. Инфоблоки находятся в своих разделах; порядок и включение управляются в Netcat.');}
function up_verify($runtime=true){
    global $up_state,$up_manifest,$up_folder,$db;
    if(empty($up_state['complete'])){throw new RuntimeException('Обновление 5.5 ещё не завершено.');}
    if(PHP_SAPI!=='cli'&&function_exists('opcache_invalidate')){foreach(array_keys($up_state['files']) as $relative){@opcache_invalidate(up_path($relative),true);}}
    foreach($up_manifest['theme'] as $relative=>$source){if(!hash_equals($up_manifest['hashes'][$source],up_hash(up_path('netcat_template/template/life_teen/'.$relative)))){throw new RuntimeException('Не совпадает файл оформления: '.$relative);}}
    if(!hash_equals($up_manifest['hashes'][$up_manifest['runtime']],up_hash(up_path('netcat/modules/default/function.inc.php')))){throw new RuntimeException('Не совпадают функции сайта.');}
    if((int)$db->get_var('SELECT Checked FROM Catalogue WHERE Catalogue_ID=1')!==$up_state['original_site_checked']){throw new RuntimeException('Изменился статус публикации сайта.');}
    $config=up_read($up_folder.'/layout.php');if(isset($config['pages'])||($config['version']??'')!=='5.5.0'){throw new RuntimeException('Осталась принудительная карта страниц.');}
    if((int)$db->get_var('SELECT Checked FROM Subdivision WHERE Subdivision_ID='.(int)$up_state['plan']['shared'])!==0){throw new RuntimeException('Раздел источников должен быть выключен.');}
    foreach($up_state['plan']['placements'] as $id=>$p){$current=$db->get_row('SELECT Subdivision_ID,SrcMirror FROM Sub_Class WHERE Sub_Class_ID='.(int)$id,ARRAY_A);if(!$current||(int)$current['Subdivision_ID']===(int)$up_state['plan']['shared']||(int)$current['SrcMirror']){throw new RuntimeException('Не восстановлено нативное размещение инфоблока №'.$id);}}
    foreach($up_state['views'] as $view){$row=$db->get_row('SELECT * FROM Class WHERE Class_ID='.(int)$view['id'],ARRAY_A);$settings=$row?file_get_contents(up_component_folder($row).'/Settings.html'):'';if(!$row||strpos($settings,'project_shared_source_where('.(int)$view['source'].',')===false||!preg_match('/\$ignore_cc\s*=\s*1\s*;/',$settings)||!preg_match('/\$ignore_sub\s*=\s*1\s*;/',$settings)){throw new RuntimeException('Общий шаблон не ограничен своим источником или не включён общий режим.');}}
    if($db->get_var("SELECT Class_ID FROM Class WHERE Keyword LIKE 'life\\_v54\\_shared\\_%'")){throw new RuntimeException('Остались устаревшие общие шаблоны 5.4.');}
    foreach($up_manifest['components'] as $key=>$parts){foreach($parts as $part=>$source){$actual=file_get_contents(up_component_folder($up_state['components'][$key]).'/'.$part.'.html');$expected=file_get_contents(up_payload($source));if($part==='RecordTemplate'){$actual=nc_cleaned_RecordTemplate_of_string_service($actual);$expected=nc_cleaned_RecordTemplate_of_string_service($expected);}if(trim(str_replace(["\r\n","\r"],"\n",$actual))!==trim(str_replace(["\r\n","\r"],"\n",$expected))){throw new RuntimeException('Не совпадает шаблон '.$key.'/'.$part);}}}
    if($runtime&&(!function_exists('project_popup_widget')||isset(project_native_config()['pages']))){throw new RuntimeException('Обновите страницу для загрузки функций 5.5.');}
    up_out('Проверка 5.5 пройдена: штатные инфоблоки, локальный FAQ, конкретные общие источники.');
}
function up_restore_rows($table,array $rows) {
    foreach($rows as $row){up_query('REPLACE INTO '.up_ident($table).' ('.implode(',',array_map('up_ident',array_keys($row))).') VALUES ('.implode(',',array_map('up_value',array_values($row))).')');}
}
function up_remove_folder($folder) {
    if(!is_dir($folder)){return;}$iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($folder,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);foreach($iterator as $file){if($file->isDir()&&!$file->isLink()){rmdir($file->getPathname());}else{unlink($file->getPathname());}}rmdir($folder);
}
function up_rollback(){
    global $up_state,$up_folder,$db,$nc_core;
    if(empty($up_state['backup_complete'])){throw new RuntimeException('Резервная копия 5.5 ещё не завершена.');}
    if(!empty($up_state['fingerprint'])&&$up_state['fingerprint']!==up_fingerprint()){throw new RuntimeException('После обновления изменились материалы или настройки. Откат остановлен до изменений, чтобы сохранить вашу работу.');}
    $backup=up_read($up_folder.'/database.php');
    foreach($up_state['new_classes'] as $key=>$id){$nc_core->component->delete_compatible_components_cache_by_id($id);up_query('DELETE FROM Class WHERE Class_ID='.(int)$id);up_remove_folder(up_component_folder($up_state['components'][$key]));}
    foreach($up_state['new_blocks'] as $id){up_query('DELETE FROM Sub_Class WHERE Sub_Class_ID='.(int)$id);}foreach($up_state['new_sections'] as $id){up_query('DELETE FROM Subdivision WHERE Subdivision_ID='.(int)$id);}
    foreach($backup['records'] as $key=>$rows){$class=(int)$up_state['components'][$key]['Class_ID'];up_query('DELETE FROM Message'.$class);up_restore_rows('Message'.$class,up_rows_decode($rows));}
    foreach(['classes'=>'Class','fields'=>'Field','templates'=>'Template','blocks'=>'Sub_Class','sections'=>'Subdivision','catalogue'=>'Catalogue'] as $key=>$table){up_restore_rows($table,up_rows_decode($backup[$key]));}
    foreach($up_state['files'] as $relative=>$info){if($info['existed']){up_copy($up_folder.'/files/'.$relative,up_path($relative));}elseif(is_file(up_path($relative))){unlink(up_path($relative));}}
    if(is_file($up_folder.'/layout.php')){unlink($up_folder.'/layout.php');}$nc_core->component->clear_cache();$nc_core->event->execute(nc_event::AFTER_SITE_UPDATED,1);
    foreach(up_rows_decode($backup['sections']) as $row){up_sql_update('Subdivision','Subdivision_ID',$row['Subdivision_ID'],['LastUpdated'=>$row['LastUpdated'],'LastModified'=>$row['LastModified']]);}
    $up_state['complete']=false;$up_state['rolled_back']=true;up_save();up_out('Откат 5.5 завершён. Восстановлено состояние перед этим обновлением.');
}

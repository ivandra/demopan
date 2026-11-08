<?php
require_once up_payload('helpers.php');
require_once up_payload('compile.php');
require_once up_payload('migration.php');
function up_prepare(){
    global $up_state,$up_manifest,$up_folder,$nc_core,$db;
    if(!empty($up_state['backup_complete'])){return;}
    if(empty($up_state['plan_ready'])){
        $previous=rtrim($nc_core->FILES_FOLDER,'/').'/life_teen_v55';
        $old=up_read($previous.'/state.php');$config=up_read($previous.'/layout.php');
        if(empty($old['complete'])||!$config){throw new RuntimeException('Нужен установленный 5.5 и папка life_teen_v55. Откатывать его не требуется.');}
        $classes=(array)$db->get_results("SELECT * FROM Class WHERE Keyword LIKE 'life\\_%'",ARRAY_A);
        $up_state=['version'=>'5.6.0','done'=>[],'files'=>[],'components'=>[],'new_classes'=>[],'new_blocks'=>[],'new_sections'=>[],'new_records'=>[],'original_site_checked'=>(int)$db->get_var('SELECT Checked FROM Catalogue WHERE Catalogue_ID=1')];
        foreach($classes as $row){$up_state['components'][$row['Keyword']]=$row;}
        $ids=implode(',',array_map('intval',array_column($classes,'Class_ID')));
        $backup=['classes'=>up_rows_encode($classes),'fields'=>up_rows_encode((array)$db->get_results('SELECT * FROM Field WHERE Class_ID IN ('.$ids.')',ARRAY_A)),'templates'=>up_rows_encode((array)$db->get_results("SELECT * FROM Template WHERE File_Path='/life_teen/' OR File_Path LIKE '/life_teen/%'",ARRAY_A)),'blocks'=>up_rows_encode((array)$db->get_results('SELECT * FROM Sub_Class WHERE Catalogue_ID=1',ARRAY_A)),'sections'=>up_rows_encode((array)$db->get_results('SELECT * FROM Subdivision WHERE Catalogue_ID=1',ARRAY_A)),'catalogue'=>up_rows_encode((array)$db->get_results('SELECT * FROM Catalogue WHERE Catalogue_ID=1',ARRAY_A)),'records'=>[]];
        foreach($classes as $row){$folder=up_component_folder($row);foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($folder,FilesystemIterator::SKIP_DOTS)) as $file){if($file->isFile()){$up_state['files'][up_relative($file->getPathname())]=null;}}if(!(int)$row['ClassTemplate']){$backup['records'][$row['Keyword']]=up_rows_encode((array)$db->get_results('SELECT * FROM Message'.(int)$row['Class_ID'].' ORDER BY Message_ID',ARRAY_A));}}
        foreach($up_manifest['components'] as $key=>$parts){foreach($parts as $part=>$source){$up_state['files'][up_relative(up_component_folder($up_state['components'][$key]).'/'.$part.'.html')]=null;}}
        foreach(up_rows_decode($backup['templates']) as $row){$folder=rtrim($nc_core->TEMPLATE_FOLDER,'/').'/'.trim($row['File_Path'],'/');foreach(['Header','Footer','Settings','Template'] as $part){$up_state['files'][up_relative($folder.'/'.$part.'.html')]=null;}}
        foreach($up_manifest['theme'] as $relative=>$source){$up_state['files']['netcat_template/template/life_teen/'.$relative]=null;}$up_state['files']['netcat/modules/default/function.inc.php']=null;
        $up_state['plan']=up_plan($backup,$config);up_store($up_folder.'/database.php',$backup);$up_state['plan_ready']=true;up_save();
    }
    foreach($up_state['files'] as $relative=>$info){
        if($info!==null){continue;}$path=up_path($relative);$info=['existed'=>is_file($path)];
        if($info['existed']){$target=$up_folder.'/files/'.$relative;if(!is_dir(dirname($target))){mkdir(dirname($target),0700,true);}if(!copy($path,$target)||hash_file('sha256',$path)!==hash_file('sha256',$target)){throw new RuntimeException('Не сохранён исходный файл.');}chmod($target,0600);$info['sha256']=hash_file('sha256',$target);}
        $up_state['files'][$relative]=$info;up_save();up_checkpoint('Сохранён файл до обновления.');
    }
    $up_state['backup_complete']=true;up_save();
}
function up_data() {
    global $up_state,$db;
    $plan=$up_state['plan'];
    foreach($plan['targets'] as $id=>$section){up_once('section:'.$id,function()use($section){up_fill_section($section);},'Подготовлены обычные инфоблоки: '.$section['Subdivision_Name']);}
    up_once('source_layout',function()use($plan){
        global $up_state,$db;
        $section=$db->get_row('SELECT * FROM Subdivision WHERE Subdivision_ID='.(int)$plan['source_sub'],ARRAY_A);up_fill_section($section);
        $way=(int)$up_state['components']['life_cards_way']['Class_ID'];
        foreach($plan['seed'] as $seed){if((int)$seed['Class_Template_ID']===$way){
            $cc=(int)$seed['Sub_Class_ID'];up_sql_update('Sub_Class','Sub_Class_ID',$cc,['Checked'=>1,'CustomSettings'=>$seed['CustomSettings']]);
            if(!$db->get_var('SELECT Message_ID FROM Message'.(int)$seed['Class_ID'].' WHERE Sub_Class_ID='.$cc)){
                foreach($plan['global_views'] as $global=>$binding){if((int)$binding['original']===$way){up_sql_update('Sub_Class','Sub_Class_ID',$cc,['Class_Template_ID'=>$global]);break;}}
            }
        }}
    },'В эталонной услуге проверены блок пути, текст и подписка.');
    up_once('archive_cleanup',function()use($plan){
        $archive=$plan['archive'];if(empty($archive['section'])){return;}
        foreach($archive['records']??[] as $id){up_query('DELETE FROM Message'.(int)$archive['class'].' WHERE Message_ID='.(int)$id.' AND Subdivision_ID='.(int)$archive['section']);}
        foreach($archive['blocks']??[] as $id){up_query('DELETE FROM Sub_Class WHERE Sub_Class_ID='.(int)$id.' AND Subdivision_ID='.(int)$archive['section']);}
        up_query('DELETE FROM Subdivision WHERE Subdivision_ID='.(int)$archive['section']);
    },'Убран старый архив одиночных фотографий. Массовая галерея и файлы сохранены.');
}
function up_templates() {
    global $up_manifest,$up_state,$db,$nc_core;
    foreach($up_manifest['theme'] as $relative=>$source){up_once('theme:'.$relative,function()use($relative,$source){up_copy(up_payload($source),up_path('netcat_template/template/life_teen/'.$relative));},'Обновлён файл оформления.');}
    up_once('runtime',function(){global $up_manifest;up_copy(up_payload($up_manifest['runtime']),up_path('netcat/modules/default/function.inc.php'));},'Обновлены ссылки, навигация и региональные подстановки.');
    up_once('layout',function(){global $db,$nc_core;$id=(int)$db->get_var("SELECT Template_ID FROM Template WHERE File_Path='/life_teen/' LIMIT 1");$editor=new nc_tpl_template_editor($nc_core->TEMPLATE_FOLDER,$db);$editor->load_template($id,'/life_teen/');$editor->save_new_template(['Header'=>file_get_contents(up_path('netcat_template/template/life_teen/Header.html')),'Footer'=>file_get_contents(up_path('netcat_template/template/life_teen/Footer.html')),'Settings'=>file_get_contents(up_path('netcat_template/template/life_teen/Settings.html'))],false);},'Обновлены шапка, метатеги и маски форм.');
    foreach($up_manifest['components'] as $key=>$parts){up_once('component:'.$key,function()use($key,$parts){global $up_state;foreach($parts as $part=>$source){up_copy(up_payload($source),up_component_folder($up_state['components'][$key]).'/'.$part.'.html');}up_compile($up_state['components'][$key]);},'Обновлён компонент: '.$key);}
    foreach($up_state['plan']['global_views'] as $id=>$binding){
        $original=null;foreach($up_state['components'] as $key=>$row){if((int)$row['Class_ID']===(int)$binding['original']){$original=$key;break;}}
        if(!$original||!isset($up_manifest['components'][$original])){continue;}
        up_once('global:'.$id,function()use($id,$binding,$original){
            global $up_state,$up_manifest,$db;$row=$db->get_row('SELECT * FROM Class WHERE Class_ID='.(int)$id,ARRAY_A);if(!$row){throw new RuntimeException('Нет общего шаблона №'.$id);}
            foreach($up_manifest['components'][$original] as $part=>$source){
                if($part==='Settings'){continue;}
                $code=file_get_contents(up_payload($source));
                if($part==='FormPrefix'){$code='<?= project_source_notice('.(int)$binding['source'].') ?>'."\n".$code;}
                if($part==='RecordTemplate'){$code="<?php \$f_AdminButtons = ''; ?>\n".$code;}
                file_put_contents(up_component_folder($row).'/'.$part.'.html',$code,LOCK_EX);
            }up_compile($row);
        },'Обновлены ссылки и оформление общего варианта.');
    }
}
function up_apply() {
    global $up_state,$nc_core;
    up_prepare();up_data();up_templates();$nc_core->component->clear_cache();$nc_core->event->execute(nc_event::AFTER_SITE_UPDATED,1);
    $up_state['fingerprint']=up_fingerprint();$up_state['complete']=true;up_save();up_verify(false);
    up_out('Обновление 5.6 применено. Страницы подготовлены, ссылки, оформление, маски и шорткоды обновлены.');
}
function up_verify($runtime=true) {
    global $up_state,$up_manifest,$db,$nc_core;
    if(empty($up_state['complete'])){throw new RuntimeException('Обновление 5.6 ещё не завершено.');}
    if(function_exists('opcache_invalidate')){foreach(array_keys($up_state['files']) as $path){@opcache_invalidate(up_path($path),true);}}
    foreach($up_manifest['theme'] as $relative=>$source){if(!hash_equals($up_manifest['hashes'][$source],up_hash(up_path('netcat_template/template/life_teen/'.$relative)))){throw new RuntimeException('Не совпадает файл оформления: '.$relative);}}
    if(!hash_equals($up_manifest['hashes'][$up_manifest['runtime']],up_hash(up_path('netcat/modules/default/function.inc.php')))){throw new RuntimeException('Не совпадают функции сайта.');}
    if((int)$db->get_var('SELECT Checked FROM Catalogue WHERE Catalogue_ID=1')!==$up_state['original_site_checked']){throw new RuntimeException('Изменился статус публикации сайта.');}
    foreach($up_state['plan']['targets'] as $id=>$section){
        $banner=(int)$up_state['components']['life_banner']['Class_ID'];$text=(int)$up_state['components']['life_text']['Class_ID'];$way=(int)$up_state['components']['life_cards_way']['Class_ID'];
        foreach([$banner,$text] as $class){if(!$db->get_var('SELECT Sub_Class_ID FROM Sub_Class WHERE Subdivision_ID='.(int)$id.' AND Class_ID='.$class)){throw new RuntimeException('Неполный состав инфоблоков: '.$section['Subdivision_Name']);}}
        $way_views=[$way];foreach($up_state['plan']['global_views'] as $global=>$binding){if((int)$binding['original']===$way){$way_views[]=(int)$global;}}
        if(!$db->get_var('SELECT Sub_Class_ID FROM Sub_Class WHERE Subdivision_ID='.(int)$id.' AND Checked=1 AND Class_Template_ID IN ('.implode(',',$way_views).') AND Class_ID='.(int)$up_state['components']['life_cards']['Class_ID'])){throw new RuntimeException('Нет блока пути: '.$section['Subdivision_Name']);}
    }
    if(!empty($up_state['plan']['archive']['section'])&&$db->get_var('SELECT Subdivision_ID FROM Subdivision WHERE Subdivision_ID='.(int)$up_state['plan']['archive']['section'])){throw new RuntimeException('Остался старый архив фотографий.');}
    foreach($up_state['plan']['global_views'] as $id=>$binding){
        $row=$db->get_row('SELECT * FROM Class WHERE Class_ID='.(int)$id,ARRAY_A);$settings=$row?file_get_contents(up_component_folder($row).'/Settings.html'):'';
        if(strpos($settings,'project_shared_source_where('.(int)$binding['source'].',')===false||!preg_match('/\$ignore_cc\s*=\s*1\s*;/',$settings)||!preg_match('/\$ignore_sub\s*=\s*1\s*;/',$settings)){throw new RuntimeException('Общий шаблон потерял источник.');}
    }
    foreach($up_manifest['components'] as $key=>$parts){foreach($parts as $part=>$source){$actual=file_get_contents(up_component_folder($up_state['components'][$key]).'/'.$part.'.html');$expected=file_get_contents(up_payload($source));if($part==='RecordTemplate'){$actual=nc_cleaned_RecordTemplate_of_string_service($actual);$expected=nc_cleaned_RecordTemplate_of_string_service($expected);}if(trim(str_replace(["\r\n","\r"],"\n",$actual))!==trim(str_replace(["\r\n","\r"],"\n",$expected))){throw new RuntimeException('Не совпадает шаблон '.$key.'/'.$part);}}}
    if($runtime&&!function_exists('project_article_navigation')){throw new RuntimeException('Обновите страницу, чтобы загрузить функции 5.6.');}
    up_out('Проверка 5.6 пройдена. Разделов с подготовленным составом: '.(count($up_state['plan']['targets'])+1).'. Статус публикации сохранён.');
}

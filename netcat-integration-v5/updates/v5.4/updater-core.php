<?php
require_once up_payload('migration.php');

function up_once($key, callable $action, $message) {
    global $up_state;
    if (!empty($up_state['done'][$key])) { return; }
    $action();
    $up_state['done'][$key] = true;
    up_save();
    up_checkpoint($message);
}

function up_prepare() {
    global $up_state,$up_manifest,$up_folder,$nc_core,$db;
    if (!empty($up_state['backup_complete'])) { return; }
    if (empty($up_state['plan_ready'])) {
        if (!$db->get_var("SELECT Field_ID FROM Field JOIN Class USING(Class_ID) WHERE Keyword='life_articles' AND Field_Name='recommended'")) { throw new RuntimeException('Сначала примените обновление 5.3.'); }
        if ($db->get_var("SELECT Subdivision_ID FROM Subdivision WHERE Catalogue_ID=1 AND EnglishName IN ('project-shared-v54','project-specialist-archive-v54')")) { throw new RuntimeException('Имя служебного раздела уже занято.'); }
        foreach (['life_gallery_bulk'] as $key) {
            if ($db->get_var("SELECT Class_ID FROM Class WHERE Keyword='".$key."'")) { throw new RuntimeException('Имя нового компонента уже занято: '.$key); }
        }
        if ($db->get_var("SELECT Class_ID FROM Class WHERE Keyword LIKE 'life\\_v54\\_shared\\_%'")) { throw new RuntimeException('Имя общего шаблона 5.4 уже занято.'); }
        if ($db->get_var("SELECT Classificator_ID FROM Classificator WHERE Table_Name='LifeSpecialistMethod'")) { throw new RuntimeException('Имя списка методов уже занято.'); }
        $classes=(array)$db->get_results("SELECT * FROM Class WHERE Keyword LIKE 'life\\_%'",ARRAY_A);
        $up_state=['version'=>'5.4.0','done'=>[],'files'=>[],'components'=>[],'new_classes'=>[],'added_fields'=>[],'new_blocks'=>[],'new_sections'=>[],'new_lists'=>[],'new_media'=>[],'original_site_checked'=>(int)$db->get_var('SELECT Checked FROM Catalogue WHERE Catalogue_ID=1')];
        foreach ($classes as $row) { $up_state['components'][$row['Keyword']]=$row; }
        $person=(int)$up_state['components']['life_specialists']['Class_ID'];
        if ($db->get_var("SELECT Field_ID FROM Field WHERE Class_ID=$person AND Field_Name IN ('region','method_choices')")) { throw new RuntimeException('Новое поле города или методов уже существует.'); }
        $ids=implode(',',array_map('intval',array_column($classes,'Class_ID')));
        $backup=['classes'=>up_rows_encode($classes),'fields'=>up_rows_encode((array)$db->get_results('SELECT * FROM Field WHERE Class_ID IN ('.$ids.')',ARRAY_A)),'templates'=>up_rows_encode((array)$db->get_results("SELECT * FROM Template WHERE File_Path='/life_teen/' OR File_Path LIKE '/life_teen/%'",ARRAY_A)),'blocks'=>up_rows_encode((array)$db->get_results('SELECT * FROM Sub_Class WHERE Catalogue_ID=1',ARRAY_A)),'sections'=>up_rows_encode((array)$db->get_results('SELECT * FROM Subdivision WHERE Catalogue_ID=1',ARRAY_A)),'catalogue'=>up_rows_encode((array)$db->get_results('SELECT * FROM Catalogue WHERE Catalogue_ID=1',ARRAY_A)),'records'=>[]];
        foreach ($classes as $row) {
            $folder=up_component_folder($row);
            foreach (glob($folder.'/*.html') as $file) { $up_state['files'][up_relative($file)]=null; }
            if (!(int)$row['ClassTemplate']) { $backup['records'][$row['Keyword']]=up_rows_encode((array)$db->get_results('SELECT * FROM Message'.(int)$row['Class_ID'].' ORDER BY Message_ID',ARRAY_A)); }
        }
        foreach (up_rows_decode($backup['templates']) as $row) {
            $folder=rtrim($nc_core->TEMPLATE_FOLDER,'/').'/'.trim($row['File_Path'],'/');
            foreach (['Header','Footer','Settings','Template'] as $part) { $up_state['files'][up_relative($folder.'/'.$part.'.html')]=null; }
        }
        foreach ($up_manifest['theme'] as $relative=>$source) { $up_state['files']['netcat_template/template/life_teen/'.$relative]=null; }
        $up_state['files']['netcat/modules/default/function.inc.php']=null;
        $up_state['plan']=up_plan($backup);
        up_store($up_folder.'/database.php',$backup);
        $up_state['plan_ready']=true;up_save();
    }
    foreach ($up_state['files'] as $relative=>$info) {
        if ($info!==null) { continue; }
        $path=up_path($relative);$info=['existed'=>is_file($path)];
        if ($info['existed']) {
            $target=$up_folder.'/files/'.$relative;
            if (!is_dir(dirname($target))) { mkdir(dirname($target),0700,true); }
            if (!copy($path,$target) || hash_file('sha256',$path)!==hash_file('sha256',$target)) { throw new RuntimeException('Не сохранён файл до обновления.'); }
            chmod($target,0600);$info['sha256']=hash_file('sha256',$target);
        }
        $up_state['files'][$relative]=$info;up_save();up_checkpoint('Сохранён файл до обновления.');
    }
    $up_state['backup_complete']=true;up_save();
}

function up_add_field($class, $name, $description, $type, $format='') {
    global $up_state,$nc_core,$db;
    $id=(int)$db->get_var("SELECT Field_ID FROM Field WHERE Class_ID=".(int)$class." AND Field_Name='".$db->escape($name)."'");
    if (!$id) { $id=(int)$nc_core->component->add_field($class,['Field_Name'=>$name,'Description'=>$description,'TypeOfData_ID'=>$type,'Format'=>$format,'NotNull'=>0,'DefaultState'=>'','DoSearch'=>0]); }
    if (!$id) { throw new RuntimeException('Не создано поле '.$description); }
    $up_state['added_fields'][(int)$class][$name]=$id;up_save();
}

function up_schema() {
    global $up_state,$nc_core,$db;
    $person=(int)$up_state['components']['life_specialists']['Class_ID'];
    up_once('field:region',function()use($person){up_add_field($person,'region','Город из справочника «Регионы»',9,$GLOBALS['up_state']['plan']['regions_class'].':m.city');},'Добавлен выбор готового города.');
    up_once('method_list',function(){
        global $up_state,$nc_core,$db;
        require_once $nc_core->ADMIN_FOLDER.'classificator.inc.php';
        $id=(int)$db->get_var("SELECT Classificator_ID FROM Classificator WHERE Table_Name='LifeSpecialistMethod'");
        if(!$id){$id=(int)AddClassificatorCompleted('Методы работы специалистов','LifeSpecialistMethod',0,0,0);}
        if(!$id){throw new RuntimeException('Не создан список методов.');}$up_state['new_lists']['LifeSpecialistMethod']=$id;up_save();
    },'Добавлен список методов работы.');
    up_once('field:methods',function()use($person){up_add_field($person,'method_choices','Методы работы',10,'LifeSpecialistMethod:checkbox:6');},'Методы выбираются несколькими галочками.');
    up_once('class:gallery',function(){
        global $up_state,$nc_core,$db;
        $key='life_gallery_bulk';$id=(int)$db->get_var("SELECT Class_ID FROM Class WHERE Keyword='$key' AND ClassTemplate=0");
        if(!$id){$id=(int)$nc_core->component->create(['Keyword'=>$key,'Class_Name'=>'Фотогалерея — массовая загрузка по категориям','Class_Group'=>'Жизнь подростка','DisableBlockMarkup'=>1,'TitleTemplate'=>'$f_name','FullPageMode'=>'none','RecordsPerPage'=>1000]);}
        if(!$id){throw new RuntimeException('Не создан компонент массовой галереи.');}
        $up_state['new_classes'][$key]=$id;$up_state['components'][$key]=$db->get_row('SELECT * FROM Class WHERE Class_ID='.$id,ARRAY_A);up_save();
    },'Создана фотогалерея с массовой загрузкой.');
    $gallery=(int)$up_state['new_classes']['life_gallery_bulk'];
    foreach ([['name','Название группы',1,''],['category','Категория фотографий',4,'LifePhotoCategory'],['img','Фотографии — можно выбрать несколько файлов',11,'0:image/*;use_preview:1;preview_use_resize:1;preview_width:340;preview_height:228;use_resize:0;']] as $field) {
        up_once('gallery:field:'.$field[0],function()use($gallery,$field){up_add_field($gallery,$field[0],$field[1],$field[2],$field[3]);},'Добавлено поле массовой галереи.');
    }
    up_once('profile_metadata',function()use($person){
        up_query("UPDATE Field SET Checked=0,InTableView=0 WHERE Class_ID=$person AND Field_Name IN ('city_from_region','city')");
        up_query("UPDATE Field SET Checked=1,Description='Образование',Format='8:70;html:2;br:2;fck:2;' WHERE Class_ID=$person AND Field_Name='education'");
        up_query("UPDATE Field SET Description='Очный приём: брать адрес выбранного региона' WHERE Class_ID=$person AND Field_Name='reception_from_region'");
        $method=(int)$GLOBALS['up_state']['components']['life_specialist_methods']['Class_ID'];
        up_query("UPDATE Field SET Checked=0,NotNull=0,InTableView=0 WHERE Class_ID=$method AND Field_Name IN ('specialist','img')");
        up_sql_update('Class','Class_ID',$method,['Class_Name'=>'Методы работы — общий справочник']);
    },'Убраны ручной город и HTML образования из формы специалиста.');
}

function up_new_section($key,$name,$parent=0) {
    global $up_state,$nc_core,$db;
    if (!empty($up_state['sections'][$key])) { return (int)$up_state['sections'][$key]; }
    $english='project-'.$key.'-v54';
    $id=(int)$db->get_var("SELECT Subdivision_ID FROM Subdivision WHERE Catalogue_ID=1 AND EnglishName='".$english."'");
    if(!$id){$root=(int)$db->get_var("SELECT Template_ID FROM Template WHERE File_Path='/life_teen/' LIMIT 1");$id=(int)$nc_core->subdivision->create(['Catalogue_ID'=>1,'Parent_Sub_ID'=>$parent,'Subdivision_Name'=>$name,'EnglishName'=>$english,'Template_ID'=>$root,'Checked'=>0,'Read_Access_ID'=>1,'Priority'=>999]);}
    if(!$id){throw new RuntimeException('Не создан служебный раздел.');}
    $up_state['sections'][$key]=$id;$up_state['new_sections'][]=$id;up_save();return $id;
}

function up_new_block($key,$class,$sub,$name,$checked=0) {
    global $up_state,$nc_core,$db;
    if (!empty($up_state['blocks'][$key])) { return (int)$up_state['blocks'][$key]; }
    $english='v54-'.$key;$id=(int)$db->get_var('SELECT Sub_Class_ID FROM Sub_Class WHERE Subdivision_ID='.(int)$sub." AND EnglishName='".$db->escape($english)."'");
    if(!$id){$id=(int)$nc_core->sub_class->create($class,['Subdivision_ID'=>$sub,'Sub_Class_Name'=>$name,'EnglishName'=>$english,'Checked'=>$checked,'Read_Access_ID'=>1,'Priority'=>999]);}
    if(!$id){throw new RuntimeException('Не создан служебный инфоблок.');}
    $up_state['blocks'][$key]=$id;$up_state['new_blocks'][]=$id;up_save();return $id;
}

function up_data() {
    global $up_state,$db,$nc_core;
    $plan=$up_state['plan'];
    up_once('section:shared',function(){up_new_section('shared','Общие блоки');},'Создан выключенный раздел общих блоков.');
    up_once('section:archive',function()use($plan){up_new_section('specialist-archive','Архив данных до 5.4',(int)$plan['specialists_sub']);},'Архив образования находится в разделе специалистов.');
    up_once('methods_data',function(){up_migrate_methods();},'Методы перенесены в единый справочник.');
    $plan=$up_state['plan'];
    foreach ($plan['people'] as $id=>$values) {
        up_once('person:'.$id,function()use($id,$values){up_sql_update('Message'.(int)$GLOBALS['up_state']['components']['life_specialists']['Class_ID'],'Message_ID',$id,$values);},'Сохранены город, выбранные методы и образование специалиста.');
    }
    up_once('education_archive',function()use($plan){
        global $up_state;
        foreach($plan['education_blocks'] as $cc){up_sql_update('Sub_Class','Sub_Class_ID',$cc,['Subdivision_ID'=>$up_state['sections']['specialist-archive'],'Checked'=>0]);up_query('UPDATE Message'.(int)$up_state['components']['life_specialist_education']['Class_ID'].' SET LastUpdated=LastUpdated,Subdivision_ID='.(int)$up_state['sections']['specialist-archive'].' WHERE Sub_Class_ID='.(int)$cc);}
    },'Образование доступно прямо в карточке специалиста.');
    up_once('gallery_source',function(){up_migrate_gallery_source();},'Фотографии сохранены, для заполнения открыта массовая галерея.');
    foreach($plan['gallery_groups'] as $key=>$group){up_once('gallery_group:'.$key,function()use($key,$group){up_migrate_gallery_group($key,$group);},'Перенесена группа фотографий без потери исходных файлов.');}
    foreach($plan['service_sections'] as $section){up_once('service_text:'.$section['Subdivision_ID'],function()use($section){up_service_text($section);},'Текст услуги доступен для редактирования в своём разделе.');}
    up_once('common_move',function(){up_move_common();},'Общие блоки читаются из конкретных источников.');
}

function up_templates() {
    global $up_state,$up_manifest,$nc_core,$db;
    foreach($up_manifest['theme'] as $relative=>$source){up_once('theme:'.$relative,function()use($relative,$source){up_copy(up_payload($source),up_path('netcat_template/template/life_teen/'.$relative));},'Обновлён файл оформления.');}
    up_once('runtime',function(){global $up_manifest;up_copy(up_payload($up_manifest['runtime']),up_path('netcat/modules/default/function.inc.php'));},'Обновлены функции сайта.');
    up_once('layout',function(){
        global $nc_core,$db;
        $id=(int)$db->get_var("SELECT Template_ID FROM Template WHERE File_Path='/life_teen/' LIMIT 1");$editor=new nc_tpl_template_editor($nc_core->TEMPLATE_FOLDER,$db);$editor->load_template($id,'/life_teen/');
        $editor->save_new_template(['Header'=>file_get_contents(up_path('netcat_template/template/life_teen/Header.html')),'Footer'=>file_get_contents(up_path('netcat_template/template/life_teen/Footer.html')),'Settings'=>file_get_contents(up_path('netcat_template/template/life_teen/Settings.html'))],false);
    },'Добавлен статический вывод общих блоков.');
    foreach($up_manifest['components'] as $key=>$parts){up_once('component:'.$key,function()use($key,$parts){global $up_state;foreach($parts as $part=>$source){up_copy(up_payload($source),up_component_folder($up_state['components'][$key]).'/'.$part.'.html');}up_compile($up_state['components'][$key]);},'Обновлён компонент: '.$key);}
    foreach($up_state['plan']['shared_views'] as $binding=>$definition){up_once('shared_view:'.$binding,function()use($definition){up_shared_view($definition);},'Созданы локальный и общий шаблоны с точным источником.');}
    foreach($up_state['plan']['shared_originals'] as $original){up_once('local_view:'.$original,function()use($original){up_local_view($original);},'Локальный шаблон ограничен своим инфоблоком.');}
    up_once('layout_config',function(){up_finish_config();},'Сохранены точные источники общих блоков и редакторов.');
}

function up_fingerprint() {
    global $up_state,$db;
    $fingerprint=[];
    foreach($up_state['components'] as $key=>$row){if((int)$row['ClassTemplate']){continue;}$rows=(array)$db->get_results('SELECT * FROM Message'.(int)$row['Class_ID'].' ORDER BY Message_ID',ARRAY_A);$fingerprint['data:'.$key]=hash('sha256',json_encode(up_rows_encode($rows)));}
    foreach(['Sub_Class','Subdivision'] as $table){
        $rows=(array)$db->get_results('SELECT * FROM '.$table.' WHERE Catalogue_ID=1 ORDER BY '.$table.'_ID',ARRAY_A);
        // Netcat refreshes these cache dates during ordinary administration.
        if($table==='Subdivision'){foreach($rows as &$row){unset($row['LastUpdated'],$row['LastModified']);}unset($row);}
        $fingerprint[$table]=hash('sha256',json_encode(up_rows_encode($rows)));
    }
    $fields=array_map('intval',array_values($up_state['added_fields'][$up_state['new_classes']['life_gallery_bulk']] ?? []));
    if($fields){$fingerprint['gallery_files']=hash('sha256',json_encode(up_rows_encode((array)$db->get_results('SELECT * FROM Multifield WHERE Field_ID IN ('.implode(',',$fields).') ORDER BY ID',ARRAY_A))));}
    return $fingerprint;
}

function up_apply() {
    global $up_state,$nc_core;
    up_prepare();up_schema();up_data();up_templates();$nc_core->component->clear_cache();$nc_core->event->execute(nc_event::AFTER_SITE_UPDATED,1);
    $up_state['fingerprint']=up_fingerprint();$up_state['complete']=true;up_save();up_verify(false);up_out('Обновление 5.4 применено. Проверьте редактирование специалистов, массовую галерею и общие блоки.');
}

function up_verify($runtime=true) {
    global $up_state,$up_manifest,$up_folder,$db;
    if(empty($up_state['complete'])){throw new RuntimeException('Обновление 5.4 ещё не завершено. Продолжите update.');}
    if(PHP_SAPI!=='cli'&&function_exists('opcache_invalidate')){foreach(array_keys($up_state['files']) as $relative){@opcache_invalidate(up_path($relative),true);}}
    foreach($up_manifest['theme'] as $relative=>$source){if(!hash_equals($up_manifest['hashes'][$source],up_hash(up_path('netcat_template/template/life_teen/'.$relative)))){throw new RuntimeException('Не совпадает файл: '.$relative);}}
    if(!hash_equals($up_manifest['hashes'][$up_manifest['runtime']],up_hash(up_path('netcat/modules/default/function.inc.php')))){throw new RuntimeException('Не совпадают общие функции.');}
    if((int)$db->get_var('SELECT Checked FROM Catalogue WHERE Catalogue_ID=1')!==$up_state['original_site_checked']){throw new RuntimeException('Изменился статус публикации сайта.');}
    if(!is_file($up_folder.'/layout.php')){throw new RuntimeException('Не сохранены источники общих блоков.');}
    $config=up_read($up_folder.'/layout.php');
    if(!hash_equals($up_state['layout_hash'],hash_file('sha256',$up_folder.'/layout.php'))){throw new RuntimeException('Изменена карта общих блоков.');}
    foreach($up_manifest['components'] as $key=>$parts){foreach($parts as $part=>$source){
        $actual=file_get_contents(up_component_folder($up_state['components'][$key]).'/'.$part.'.html');$expected=file_get_contents(up_payload($source));
        if($part==='RecordTemplate'){$actual=nc_cleaned_RecordTemplate_of_string_service($actual);$expected=nc_cleaned_RecordTemplate_of_string_service($expected);}
        if(trim(str_replace(["\r\n","\r"],"\n",$actual))!==trim(str_replace(["\r\n","\r"],"\n",$expected))){throw new RuntimeException('Не совпадает шаблон: '.$key.'/'.$part);}
    }}
    foreach($up_state['plan']['shared_views'] as $binding=>$definition){
        $id=(int)($up_state['shared_view_ids'][$binding]??0);$row=$db->get_row('SELECT * FROM Class WHERE Class_ID='.$id,ARRAY_A);
        if(!$row){throw new RuntimeException('Не найден общий шаблон '.$binding);}
        $settings=file_get_contents(up_component_folder($row).'/Settings.html');
        if(strpos($settings,'project_shared_source_where('.(int)$definition['source'].',')===false){throw new RuntimeException('Общий шаблон не ограничен источником.');}
    }
    foreach($up_state['plan']['shared_originals'] as $original){foreach($up_state['components'] as $row){if((int)$row['Class_ID']!==(int)$original){continue;}$file=up_component_folder($row).'/Settings.html';if(is_file($file)&&preg_match('/\$ignore_(?:cc|sub)\s*=\s*(?:1|true)\s*;/i',file_get_contents($file))){throw new RuntimeException('Локальный шаблон захватывает чужие записи.');}}}
    $person=(int)$config['specialist_class'];
    foreach(['region'=>9,'method_choices'=>10] as $name=>$type){if((int)$db->get_var("SELECT TypeOfData_ID FROM Field WHERE Class_ID=$person AND Field_Name='".$name."'")!==$type){throw new RuntimeException('Не создан выбор '.$name);}}
    $gallery=(int)$up_state['new_classes']['life_gallery_bulk'];$field=(int)$up_state['added_fields'][$gallery]['img'];
    if((int)$db->get_var('SELECT TypeOfData_ID FROM Field WHERE Field_ID='.$field)!==11){throw new RuntimeException('Галерея не использует массовую загрузку Netcat.');}
    foreach($up_state['photo_map']??[] as $id){$path=$db->get_var('SELECT Path FROM Multifield WHERE ID='.(int)$id);if(!$path||!is_file($_SERVER['DOCUMENT_ROOT'].$path)){throw new RuntimeException('Не найдено перенесённое фото.');}}
    foreach($config['service_texts'] as $cc=>$item){if(!(int)$db->get_var('SELECT COUNT(*) FROM Message'.(int)$up_state['components']['life_text']['Class_ID'].' WHERE Message_ID='.(int)$item['message'].' AND Sub_Class_ID='.(int)$cc)){throw new RuntimeException('Не найден редактор текста услуги.');}}
    if($runtime&&!function_exists('project_native_config')){throw new RuntimeException('Обновите страницу для загрузки функций 5.4.');}
    foreach($up_state['plan']['warnings']??[] as $warning){up_out('ВНИМАНИЕ: '.$warning.'. Исходное значение сохранено; выберите готовый город в карточке.');}
    up_out('Проверка обновления 5.4 пройдена.');
}

function up_restore_rows($table,array $rows) {
    foreach($rows as $row){up_query('REPLACE INTO '.up_ident($table).' ('.implode(',',array_map('up_ident',array_keys($row))).') VALUES ('.implode(',',array_map('up_value',array_values($row))).')');}
}
function up_remove_folder($folder) {
    if(!is_dir($folder)){return;}$iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($folder,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);foreach($iterator as $file){if($file->isDir()&&!$file->isLink()){rmdir($file->getPathname());}else{unlink($file->getPathname());}}rmdir($folder);
}
function up_rollback() {
    global $up_state,$up_folder,$db,$nc_core;
    if(empty($up_state['backup_complete'])){throw new RuntimeException('Резервная копия 5.4 ещё не завершена.');}
    if(!empty($up_state['fingerprint'])&&$up_state['fingerprint']!==up_fingerprint()){throw new RuntimeException('После обновления изменились материалы или настройки. Откат остановлен до изменений, чтобы сохранить вашу работу.');}
    $backup=up_read($up_folder.'/database.php');
    foreach($up_state['new_classes'] as $key=>$class){
        $nc_core->component->delete_compatible_components_cache_by_id($class);
        if(!(int)$up_state['components'][$key]['ClassTemplate']){$ids=(array)$db->get_col('SELECT Message_ID FROM Message'.(int)$class);foreach($ids as $id){$nc_core->message->delete_by_id((int)$id,(int)$class,false);}up_query('DROP TABLE IF EXISTS Message'.(int)$class);}
        up_query('DELETE FROM Field WHERE Class_ID='.(int)$class);up_query('DELETE FROM Class WHERE Class_ID='.(int)$class);up_remove_folder(up_component_folder($up_state['components'][$key]));
    }
    foreach($up_state['new_blocks'] as $id){up_query('DELETE FROM Sub_Class WHERE Sub_Class_ID='.(int)$id);}foreach($up_state['new_sections'] as $id){up_query('DELETE FROM Subdivision WHERE Subdivision_ID='.(int)$id);}
    foreach($up_state['new_lists'] as $table=>$id){up_query('DELETE FROM Classificator WHERE Classificator_ID='.(int)$id);up_query('DROP TABLE IF EXISTS '.up_ident('Classificator_'.$table));}
    foreach($up_state['added_fields'] as $class=>$fields){if(in_array((int)$class,array_map('intval',$up_state['new_classes']),true)){continue;}foreach($fields as $name=>$id){up_query('DELETE FROM Field WHERE Field_ID='.(int)$id);$column=$db->get_var('SHOW COLUMNS FROM Message'.(int)$class." LIKE '".$db->escape($name)."'");if($column){up_query('ALTER TABLE Message'.(int)$class.' DROP COLUMN '.up_ident($name));}}}
    foreach($backup['records'] as $key=>$rows){$class=(int)$up_state['components'][$key]['Class_ID'];up_query('DELETE FROM Message'.$class);up_restore_rows('Message'.$class,up_rows_decode($rows));}
    foreach(['classes'=>'Class','fields'=>'Field','templates'=>'Template','blocks'=>'Sub_Class','sections'=>'Subdivision','catalogue'=>'Catalogue'] as $key=>$table){up_restore_rows($table,up_rows_decode($backup[$key]));}
    foreach($up_state['files'] as $relative=>$info){if($info['existed']){up_copy($up_folder.'/files/'.$relative,up_path($relative));}elseif(is_file(up_path($relative))){unlink(up_path($relative));}}
    if(is_file($up_folder.'/layout.php')){unlink($up_folder.'/layout.php');}$nc_core->component->clear_cache();$nc_core->event->execute(nc_event::AFTER_SITE_UPDATED,1);
    foreach(up_rows_decode($backup['sections']) as $row){up_sql_update('Subdivision','Subdivision_ID',$row['Subdivision_ID'],['LastUpdated'=>$row['LastUpdated'],'LastModified'=>$row['LastModified']]);}
    $up_state['complete']=false;$up_state['rolled_back']=true;up_save();up_out('Откат обновления 5.4 завершён. Данные 5.3 восстановлены.');
}

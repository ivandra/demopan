<?php
require_once up_payload('migration.php');
$up_model=json_decode(file_get_contents(up_payload('model.json')),true,512,JSON_THROW_ON_ERROR);

function up_prepare() {
    global $up_state,$up_manifest,$up_folder,$up_model,$db,$nc_core;
    if (!empty($up_state['backup_complete'])) { return; }
    if (empty($up_state['plan_ready'])) {
        $up_state=['version'=>'5.3.0','created_utc'=>gmdate('c'),'done'=>[],'files'=>[],'components'=>[],'added_fields'=>[],'new_classes'=>[],'new_blocks'=>[],'new_lists'=>[],'new_sections'=>[],'original_site_checked'=>(int)$db->get_var('SELECT Checked FROM Catalogue WHERE Catalogue_ID=1')];
        $all=(array)$db->get_results("SELECT * FROM Class WHERE Keyword LIKE 'life\\_%'",ARRAY_A);
        foreach($all as $row){$up_state['components'][$row['Keyword']]=$row;}
        foreach($up_model['new_components'] as $key=>$def){if(isset($up_state['components'][$key])){throw new RuntimeException('Имя нового справочника уже занято: '.$key);}}
        foreach($all as $row){$folder=up_component_folder($row);foreach(glob($folder.'/*.html') as $file){$up_state['files'][up_relative($file)]=null;}}
        $templates=(array)$db->get_results("SELECT * FROM Template WHERE File_Path='/life_teen/' OR File_Path LIKE '/life_teen/%'",ARRAY_A);
        foreach($templates as $template){$folder=rtrim($nc_core->TEMPLATE_FOLDER,'/').'/'.trim($template['File_Path'],'/');foreach(['Header','Footer','Settings','Template'] as $part){$up_state['files'][up_relative($folder.'/'.$part.'.html')]=null;}}
        foreach($up_manifest['theme'] as $relative=>$source){$up_state['files']['netcat_template/template/life_teen/'.$relative]=null;}
        $up_state['files']['netcat/modules/default/function.inc.php']=null;
        if($db->get_var("SELECT Subdivision_ID FROM Subdivision WHERE Catalogue_ID=1 AND EnglishName='project-data-v53'")){throw new RuntimeException('Имя раздела справочников уже занято.');}
        $class_ids=array_map('intval',array_column($all,'Class_ID'));$fields=(array)$db->get_results('SELECT * FROM Field WHERE Class_ID IN ('.implode(',',$class_ids).')',ARRAY_A);
        $blocks=(array)$db->get_results('SELECT * FROM Sub_Class WHERE Catalogue_ID=1',ARRAY_A);$sections=(array)$db->get_results('SELECT * FROM Subdivision WHERE Catalogue_ID=1',ARRAY_A);
        $records=[];$columns=[];
        foreach(['life_specialists','life_cards','life_founder','life_program','life_program_service','life_articles','life_gallery','life_text'] as $key){
            $id=(int)$up_state['components'][$key]['Class_ID'];$records[$key]=(array)$db->get_results('SELECT * FROM Message'.$id.' ORDER BY Message_ID',ARRAY_A);$columns[$key]=(array)$db->get_results('SHOW FULL COLUMNS FROM Message'.$id,ARRAY_A);
        }
        foreach($up_model['new_fields'] as $field_key=>$definitions){$field_class=(int)$up_state['components'][$field_key]['Class_ID'];foreach($definitions as $definition){foreach($fields as $old_field){if((int)$old_field['Class_ID']===$field_class&&$old_field['Field_Name']===$definition['name']){throw new RuntimeException('Новое поле уже существует: '.$field_key.'.'.$definition['name']);}}}}
        $plans=[];foreach($records['life_specialists'] as $row){$plans[$row['Message_ID']]=up_specialist_plan($row);}
        $up_state['specialist_plans']=$plans;$up_state['article_plans']=[];foreach($records['life_articles'] as $article){$up_state['article_plans'][$article['Message_ID']]=up_article_plan($article);}$up_state['settings_plan']=[];
        foreach($all as $row){
            $defs=up_settings_array($row['CustomSettingsTemplate'],'settings_array');
            if(!$defs && (int)$row['ClassTemplate']){foreach($all as $parent){if((int)$parent['Class_ID']===(int)$row['ClassTemplate']){$defs=up_settings_array($parent['CustomSettingsTemplate'],'settings_array');break;}}}
            if(isset($defs['heading'])){$defs['heading']['embededitor']=false;$defs['heading']['codemirror']=false;}
            if(strpos($row['Keyword'],'life_articles')===0){unset($defs['filter_teens'],$defs['filter_parents'],$defs['filter_specialists']);}
            $extra=$up_model['settings'][$row['Keyword']]??[];$defs=array_replace($defs,$extra);
            $up_state['settings_plan'][$row['Keyword']]=['definitions'=>$defs,'blocks'=>[]];
            foreach($blocks as $block){$effective=(int)($block['Class_Template_ID']?:$block['Class_ID']);if($effective!==(int)$row['Class_ID']){continue;}$values=up_settings_array($block['CustomSettings'],'CustomSettings');$up_state['settings_plan'][$row['Keyword']]['blocks'][(int)$block['Sub_Class_ID']]=$values;}
        }
        foreach($up_model['category_lists'] as $key=>$def){if($db->get_var("SELECT Classificator_ID FROM Classificator WHERE Table_Name='".$db->escape($def['table'])."'")){throw new RuntimeException('Имя списка уже занято: '.$def['table']);}}
        $backup=['classes'=>up_rows_encode($all),'fields'=>up_rows_encode($fields),'templates'=>up_rows_encode($templates),'blocks'=>up_rows_encode($blocks),'sections'=>up_rows_encode($sections),'records'=>[],'columns'=>$columns];
        foreach($records as $key=>$rows){$backup['records'][$key]=up_rows_encode($rows);}
        up_store($up_folder.'/database.php',$backup);$up_state['plan_ready']=true;up_save();
    }
    foreach($up_state['files'] as $relative=>$info){
        if($info!==null){continue;}$source=up_path($relative);$info=['existed'=>is_file($source)];
        if($info['existed']){$target=$up_folder.'/files/'.$relative;if(!is_dir(dirname($target))){mkdir(dirname($target),0700,true);}if(!copy($source,$target)||hash_file('sha256',$source)!==hash_file('sha256',$target)){throw new RuntimeException('Не сохранён файл резервной копии.');}@chmod($target,0600);$info['sha256']=hash_file('sha256',$target);}
        $up_state['files'][$relative]=$info;up_save();up_checkpoint('Сохранён файл обновляемой части сайта.');
    }
    $up_state['backup_complete']=true;up_save();
}
function up_add_field($key,array $field){
    global $up_state,$nc_core,$db;
    $class=(int)$up_state['components'][$key]['Class_ID'];$name=$field['name'];$id=(int)$db->get_var("SELECT Field_ID FROM Field WHERE Class_ID=".$class." AND Field_Name='".$db->escape($name)."'");
    if(!$id){
        $types=['string'=>1,'text'=>3,'bool'=>5,'file'=>6,'relation'=>9];$format=$field['type']==='text'?'6:70;html:2;br:2;fck:2;':($field['type']==='file'?'fs1:inline;use_resize:0;use_preview:0;':($field['type']==='relation'?(int)$up_state['components'][$field['parent']??'life_specialists']['Class_ID'].(!empty($field['parent'])?':m.name':':m.fio'):''));
        $id=(int)$nc_core->component->add_field($class,['Field_Name'=>$name,'Description'=>$field['label'],'TypeOfData_ID'=>$types[$field['type']],'Format'=>$format,'NotNull'=>($field['type']==='bool'||!empty($field['required']))?1:0,'DefaultState'=>$field['type']==='bool'?'0':'','DoSearch'=>0]);
        if(!$id){throw new RuntimeException('Не создано поле '.$field['label']);}$up_state['added_fields'][$key][$name]=$id;up_save();
    }else{$up_state['added_fields'][$key][$name]=$id;up_save();}
}
function up_schema(){
    global $up_state,$up_model,$nc_core,$db;
    $backup=up_read($GLOBALS['up_folder'].'/database.php');
    foreach($up_model['new_fields'] as $key=>$fields){foreach($fields as $field){$done='field:'.$key.':'.$field['name'];if(!empty($up_state['done'][$done])){continue;}up_add_field($key,$field);$up_state['done'][$done]=true;up_save();up_checkpoint('Добавлено понятное поле: '.$field['label']);}}
    foreach($up_model['new_components'] as $key=>$def){
        if(empty($up_state['new_classes'][$key])){
            $id=(int)$db->get_var("SELECT Class_ID FROM Class WHERE Keyword='".$db->escape($key)."' AND ClassTemplate=0");
            if(!$id){$id=(int)$nc_core->component->create(['Class_Name'=>$def['name'],'Keyword'=>$key,'Class_Group'=>'Жизнь подростка — справочники','DisableBlockMarkup'=>1,'TitleTemplate'=>'$f_name','FullPageMode'=>'none','RecordsPerPage'=>100]);}
            if(!$id){throw new RuntimeException('Не создан справочник '.$def['name']);}$up_state['new_classes'][$key]=$id;$up_state['components'][$key]=$db->get_row('SELECT * FROM Class WHERE Class_ID='.$id,ARRAY_A);up_save();up_checkpoint('Создан справочник: '.$def['name']);
        }
        foreach($def['fields'] as $field){$done='field:'.$key.':'.$field['name'];if(!empty($up_state['done'][$done])){continue;}up_add_field($key,$field);$up_state['done'][$done]=true;up_save();up_checkpoint('Добавлено поле справочника.');}
        up_query('UPDATE Field SET InTableView=1 WHERE Class_ID='.(int)$up_state['new_classes'][$key]." AND Field_Name IN ('specialist','name','description','period')");
    }
    foreach($up_model['category_lists'] as $key=>$def){
        if(!empty($up_state['done']['list:'.$key])){continue;}require_once $nc_core->ADMIN_FOLDER.'classificator.inc.php';
        $id=(int)$db->get_var("SELECT Classificator_ID FROM Classificator WHERE Table_Name='".$def['table']."'");
        if(!$id){$id=(int)AddClassificatorCompleted($def['label'],$def['table'],0,0,0);}$up_state['new_lists'][$def['table']]=$id;up_save();
        $class=(int)$up_state['components'][$key]['Class_ID'];$labels=$def['items'];
        if($key==='life_articles'){foreach(up_rows_decode($backup['blocks']) as $old_block){if((int)$old_block['Class_ID']===$class&&!(int)$old_block['Class_Template_ID']){$old_settings=up_settings_array($old_block['CustomSettings'],'CustomSettings');foreach($labels as $token=>$label){if(!empty($old_settings['filter_'.$token])){$labels[$token]=$old_settings['filter_'.$token];}}break;}}}foreach(array_unique(array_column(up_rows_decode($backup['records'][$key]),'category')) as $value){if(trim((string)$value)!==''&&!isset($labels[$value])){$labels[$value]=$value;}}
        $ids=[];foreach($labels as $value=>$label){$item=(int)$db->get_var('SELECT '.$def['table'].'_ID FROM Classificator_'.$def['table']." WHERE Value='".$db->escape($value)."' LIMIT 1");if(!$item){InsertInOneClassificatorCompleted($id,$label,count($ids)+1,$value);$item=(int)$db->get_var('SELECT '.$def['table'].'_ID FROM Classificator_'.$def['table']." WHERE Value='".$db->escape($value)."' LIMIT 1");}$ids[$value]=$item;}
        foreach(up_rows_decode($backup['records'][$key]) as $row){up_sql_update('Message'.$class,'Message_ID',$row['Message_ID'],['category'=>$ids[$row['category']]??0,'LastUpdated'=>$row['LastUpdated']]);}
        up_query('ALTER TABLE Message'.$class.' MODIFY category INT NULL');$field=(int)$db->get_var("SELECT Field_ID FROM Field WHERE Class_ID=".$class." AND Field_Name='category'");up_sql_update('Field','Field_ID',$field,['TypeOfData_ID'=>4,'Format'=>$def['table'],'Description'=>$def['label'],'NotNull'=>0]);
        $up_state['done']['list:'.$key]=true;up_save();$nc_core->component->clear_cache();up_checkpoint('Категории перенесены в штатный список Netcat.');
    }
}
function up_data(){
    global $up_state,$up_folder,$db;
    foreach($up_state['specialist_plans'] as $message=>$plan){$done='specialist:'.$message;if(!empty($up_state['done'][$done])){continue;}up_sql_update('Message'.(int)$up_state['components']['life_specialists']['Class_ID'],'Message_ID',$message,$plan['values']);$up_state['done'][$done]=true;up_save();up_checkpoint('Данные специалиста разделены на поля.');}
    foreach($up_state['article_plans'] as $message=>$plan){$done='article:'.$message;if(!empty($up_state['done'][$done])){continue;}up_sql_update('Message'.(int)$up_state['components']['life_articles']['Class_ID'],'Message_ID',$message,$plan['values']);$up_state['done'][$done]=true;up_save();up_checkpoint('Содержимое статьи отделено от вёрстки карточек.');}
    $backup=up_read($up_folder.'/database.php');
    $methods_field=(int)$db->get_var("SELECT Field_ID FROM Field WHERE Class_ID=".(int)$up_state['components']['life_specialists']['Class_ID']." AND Field_Name='methods'");up_sql_update('Field','Field_ID',$methods_field,['Description'=>'Вступление к разделу методов','Format'=>'6:70;html:2;br:2;fck:2;']);
    foreach(['life_program'=>['directions','support'],'life_program_service'=>['directions','support'],'life_founder'=>['achievements']] as $key=>$names){foreach(up_rows_decode($backup['records'][$key]) as $row){$done='lines:'.$key.':'.$row['Message_ID'];if(!empty($up_state['done'][$done])){continue;}$values=['LastUpdated'=>$row['LastUpdated']];foreach($names as $name){$values[$name]=up_plain_lines($row[$name]);}up_sql_update('Message'.(int)$up_state['components'][$key]['Class_ID'],'Message_ID',$row['Message_ID'],$values);$up_state['done'][$done]=true;up_save();up_checkpoint('Список перенесён в обычное текстовое поле.');}}
    foreach(up_rows_decode($backup['records']['life_cards']) as $row){if(!preg_match('~<li\b~i',(string)$row['text'])){continue;}$done='card:'.$row['Message_ID'];if(!empty($up_state['done'][$done])){continue;}up_sql_update('Message'.(int)$up_state['components']['life_cards']['Class_ID'],'Message_ID',$row['Message_ID'],['items'=>up_plain_lines($row['text']),'text'=>'','LastUpdated'=>$row['LastUpdated']]);$up_state['done'][$done]=true;up_save();up_checkpoint('Пункты карточки перенесены без HTML.');}
}
function up_import_image_matches($target,$source){
    if(!is_file($target)||!is_file($source)){return false;}
    if(hash_file('sha256',$target)===hash_file('sha256',$source)){return true;}
    // Netcat sanitizes SVG uploads; its serializer can change bytes across PHP versions.
    if(strtolower(pathinfo($source,PATHINFO_EXTENSION))!=='svg'){return false;}
    $temporary=tempnam(sys_get_temp_dir(),'life-v53-');if(!$temporary){return false;}
    try{return copy($source,$temporary)&&nc_sanitize_svg_file($temporary)&&hash_file('sha256',$target)===hash_file('sha256',$temporary);}
    finally{if(is_file($temporary)){unlink($temporary);}}
}
function up_sources(){
    global $up_state,$up_model,$nc_core,$db;
    if(empty($up_state['data_section'])){
        $name='project-data-v53';$existing=(int)$db->get_var("SELECT Subdivision_ID FROM Subdivision WHERE Catalogue_ID=1 AND EnglishName='".$name."'");
        if($existing){$id=$existing;}else{
        $root=(int)$db->get_var("SELECT Template_ID FROM Template WHERE File_Path='/life_teen/' LIMIT 1");
        $id=(int)$nc_core->subdivision->create(['Catalogue_ID'=>1,'Parent_Sub_ID'=>0,'Subdivision_Name'=>'Данные проекта','EnglishName'=>$name,'Template_ID'=>$root,'Checked'=>0,'Read_Access_ID'=>1,'Priority'=>999]);}
        if(!$id){throw new RuntimeException('Не создан раздел справочников.');}$up_state['data_section']=$id;$up_state['new_sections'][]=$id;up_save();up_checkpoint('Добавлен раздел справочников для редактора.');
    }
    foreach($up_model['new_components'] as $key=>$def){
        if(!empty($up_state['source_blocks'][$key])){continue;}$class=(int)$up_state['new_classes'][$key];
        $id=(int)$db->get_var('SELECT Sub_Class_ID FROM Sub_Class WHERE Subdivision_ID='.(int)$up_state['data_section'].' AND Class_ID='.$class.' LIMIT 1');
        if(!$id){$id=(int)$nc_core->sub_class->create($class,['Subdivision_ID'=>(int)$up_state['data_section'],'Sub_Class_Name'=>$def['name'],'EnglishName'=>$key,'Checked'=>1,'Read_Access_ID'=>1,'TableViewMode'=>1,'RecordsPerPage'=>100]);}
        if(!$id){throw new RuntimeException('Не создан инфоблок справочника.');}$up_state['source_blocks'][$key]=$id;$up_state['new_blocks'][]=$id;up_save();up_checkpoint('Добавлен инфоблок: '.$def['name']);
    }
    foreach(array_merge(array_values($up_state['specialist_plans']),array_values($up_state['article_plans'])) as $plan){foreach($plan['children'] as $key=>$rows){foreach($rows as $row){
        $done='child:'.$key.':'.$row['Keyword'];if(!empty($up_state['done'][$done])){continue;}$class=(int)$up_state['new_classes'][$key];$cc=(int)$up_state['source_blocks'][$key];
        $id=(int)$db->get_var('SELECT Message_ID FROM Message'.$class." WHERE Sub_Class_ID=".$cc." AND Keyword='".$db->escape($row['Keyword'])."' LIMIT 1");
        if(!$id){$id=(int)$nc_core->message->create($cc,$row);}
        if(!$id){throw new RuntimeException('Не перенесён элемент справочника.');}
        if(!empty($row['img'])){$url=nc_file_path($class,$id,'img');if(!$url||!is_file($_SERVER['DOCUMENT_ROOT'].$url)){if(!$nc_core->files->field_save_file($class,'img',$id,$row['img'])){throw new RuntimeException('Не сохранено изображение элемента.');}$url=nc_file_path($class,$id,'img');}if(!up_import_image_matches($_SERVER['DOCUMENT_ROOT'].$url,$_SERVER['DOCUMENT_ROOT'].$row['img'])){throw new RuntimeException('Изображение справочника не прошло проверку.');}}
        $up_state['done'][$done]=$id;up_save();up_checkpoint('Перенесён элемент образования или методов.');
    }}}
    if(empty($up_state['done']['featured'])){
        $articles=(int)$up_state['components']['life_articles']['Class_ID'];$view=(int)$up_state['components']['life_articles_featured']['Class_ID'];
        $target=$db->get_row('SELECT * FROM Sub_Class WHERE Class_ID='.$articles.' AND Class_Template_ID=0 AND Checked=1 ORDER BY Priority,Sub_Class_ID LIMIT 1',ARRAY_A);
        if(!$target){throw new RuntimeException('Не найден основной инфоблок статей.');}
        $featured=(array)$db->get_results('SELECT * FROM Sub_Class WHERE Class_ID='.$articles.' AND Class_Template_ID='.$view,ARRAY_A);
        foreach($featured as $block){$own=(array)$db->get_results('SELECT * FROM Message'.$articles.' WHERE Sub_Class_ID='.(int)$block['Sub_Class_ID'].' ORDER BY Priority,Message_ID',ARRAY_A);foreach($own as $row){up_sql_update('Message'.$articles,'Message_ID',$row['Message_ID'],['Sub_Class_ID'=>(int)$target['Sub_Class_ID'],'Subdivision_ID'=>(int)$target['Subdivision_ID'],'recommended'=>1,'LastUpdated'=>$row['LastUpdated']]);}up_sql_update('Sub_Class','Sub_Class_ID',$block['Sub_Class_ID'],['SrcMirror'=>(int)$target['Sub_Class_ID']]);}
        $selected=(int)$db->get_var('SELECT Message_ID FROM Message'.$articles.' WHERE Checked=1 AND recommended=1 ORDER BY Priority,Message_ID LIMIT 1');
        if(!$selected){$row=$db->get_row('SELECT Message_ID,LastUpdated FROM Message'.$articles.' WHERE Sub_Class_ID='.(int)$target['Sub_Class_ID'].' AND Checked=1 ORDER BY Priority,Message_ID LIMIT 1',ARRAY_A);if($row){up_sql_update('Message'.$articles,'Message_ID',$row['Message_ID'],['recommended'=>1,'LastUpdated'=>$row['LastUpdated']]);}}
        $home=(int)$up_state['components']['life_articles_home']['Class_ID'];up_query('UPDATE Sub_Class SET SrcMirror='.(int)$target['Sub_Class_ID'].' WHERE Class_ID='.$articles.' AND Class_Template_ID='.$home);
        $up_state['done']['featured']=true;up_save();up_checkpoint('Рекомендуемая статья берётся из общей коллекции.');
    }
    $text=(int)$up_state['components']['life_text']['Class_ID'];$sections=(array)$db->get_results("SELECT s.Subdivision_ID FROM Subdivision s JOIN Template t ON t.Template_ID=s.Template_ID WHERE s.Catalogue_ID=1 AND t.File_Path LIKE '/life_teen/service/%'",ARRAY_A);
    foreach($sections as $section){$done='text:'.$section['Subdivision_ID'];if(!empty($up_state['done'][$done])){continue;}$id=(int)$db->get_var('SELECT Sub_Class_ID FROM Sub_Class WHERE Subdivision_ID='.(int)$section['Subdivision_ID'].' AND Class_ID='.$text.' LIMIT 1');if(!$id){$priority=1+(int)$db->get_var('SELECT MAX(Priority) FROM Sub_Class WHERE Subdivision_ID='.(int)$section['Subdivision_ID']);$id=(int)$nc_core->sub_class->create($text,['Subdivision_ID'=>(int)$section['Subdivision_ID'],'Sub_Class_Name'=>'Текст страницы','EnglishName'=>'page-text','Checked'=>1,'Read_Access_ID'=>1,'Priority'=>$priority]);if(!$id){throw new RuntimeException('Не добавлен текстовый инфоблок услуги.');}$up_state['new_blocks'][]=$id;}$up_state['done'][$done]=$id;up_save();up_checkpoint('Добавлен пустой текстовый инфоблок услуги.');}
}
function up_settings(){
    global $up_state,$db;
    foreach($up_state['settings_plan'] as $key=>$plan){$done='settings:'.$key;if(!empty($up_state['done'][$done])){continue;}$class=(int)$up_state['components'][$key]['Class_ID'];$code=$plan['definitions']?'$settings_array='.var_export($plan['definitions'],true).';':'';up_sql_update('Class','Class_ID',$class,['CustomSettingsTemplate'=>$code]);foreach($plan['blocks'] as $cc=>$values){up_sql_update('Sub_Class','Sub_Class_ID',$cc,['CustomSettings'=>'$CustomSettings='.var_export($values,true).';']);}$up_state['done'][$done]=true;up_save();up_checkpoint('Настройки блока доступны понятными полями.');}
    foreach(['life_program'=>['directions','support'],'life_program_service'=>['directions','support'],'life_founder'=>['achievements']] as $key=>$names){$class=(int)$up_state['components'][$key]['Class_ID'];foreach($names as $name){$id=(int)$db->get_var("SELECT Field_ID FROM Field WHERE Class_ID=".$class." AND Field_Name='".$name."'");up_sql_update('Field','Field_ID',$id,['Format'=>'10:70;html:2;br:2;fck:2;','Description'=>['directions'=>'Направления: каждый пункт с новой строки','support'=>'Сопровождение: каждый пункт с новой строки','achievements'=>'Достижения: каждое с новой строки'][$name]]);}}
}
function up_templates(){
    global $up_state,$up_manifest,$nc_core,$db;
    foreach($up_manifest['theme'] as $relative=>$source){$done='theme:'.$relative;if(!empty($up_state['done'][$done])){continue;}up_copy(up_payload($source),up_path('netcat_template/template/life_teen/'.$relative));$up_state['done'][$done]=true;up_save();up_checkpoint('Обновлён файл оформления.');}
    if(empty($up_state['done']['runtime'])){up_copy(up_payload($up_manifest['runtime']),up_path('netcat/modules/default/function.inc.php'));$up_state['done']['runtime']=true;up_save();up_checkpoint('Обновлены общие функции сайта.');}
    if(empty($up_state['done']['layout'])){
        $root=(int)$db->get_var("SELECT Template_ID FROM Template WHERE File_Path='/life_teen/' LIMIT 1");$editor=new nc_tpl_template_editor($nc_core->TEMPLATE_FOLDER,$db);$editor->load_template($root, '/life_teen/');
        $editor->save_new_template(['Header'=>file_get_contents(up_path('netcat_template/template/life_teen/Header.html')),'Footer'=>file_get_contents(up_path('netcat_template/template/life_teen/Footer.html')),'Settings'=>file_get_contents(up_path('netcat_template/template/life_teen/Settings.html'))], false);
        $up_state['done']['layout']=true;up_save();up_checkpoint('Обновлены общие шапка и подвал.');
    }
    foreach($up_manifest['components'] as $key=>$parts){$done='component:'.$key;if(!empty($up_state['done'][$done])){continue;}$row=$up_state['components'][$key];$folder=up_component_folder($row);
        foreach($parts as $part=>$source){up_copy(up_payload($source),$folder.'/'.$part.'.html');}
        $tpl=new nc_tpl($nc_core->CLASS_TEMPLATE_FOLDER,$db);$tpl->load((int)$row['Class_ID'],'Class');$master='';
        foreach(['FormPrefix','RecordTemplate','FormSuffix'] as $part){$content=is_file($folder.'/'.$part.'.html')?file_get_contents($folder.'/'.$part.'.html'):'';if($part==='RecordTemplate'){$content=nc_cleaned_RecordTemplate_of_string_service($content);}$master.='<!-- '.$part.' -->'.$content.'<!-- /'.$part." -->\n";}
        file_put_contents($folder.'/Class.html',$master,LOCK_EX);nc_tpl_parser::main2parts($tpl,'');$nc_core->event->execute(nc_event::AFTER_COMPONENT_UPDATED,(int)$row['Class_ID']);$up_state['done'][$done]=true;up_save();up_checkpoint('Обновлён компонент: '.$row['Class_Name']);
    }
    $class=(int)$up_state['components']['life_specialists']['Class_ID'];up_query("UPDATE Field SET Checked=0 WHERE Class_ID=".$class." AND Field_Name IN ('stats','details','education')");
}
function up_fingerprint(){
    global $up_state,$db;
    $result=[];foreach(array_merge(['life_specialists','life_cards','life_founder','life_program','life_program_service','life_articles','life_gallery','life_text'],array_keys($up_state['new_classes'])) as $key){$class=(int)$up_state['components'][$key]['Class_ID'];$rows=(array)$db->get_results('SELECT * FROM Message'.$class.' ORDER BY Message_ID',ARRAY_A);$result[$key]=hash('sha256',json_encode(up_rows_encode($rows),JSON_UNESCAPED_UNICODE));}return $result;
}
function up_apply(){
    global $up_state,$nc_core;
    up_prepare();up_schema();up_data();up_sources();up_settings();up_templates();
    $nc_core->component->clear_cache();$nc_core->event->execute(nc_event::AFTER_SITE_UPDATED,1);$up_state['content_fingerprint']=up_fingerprint();$up_state['complete']=true;up_save();up_verify(false);up_out('Обновление 5.3 применено. Следующий шаг — проверка страниц и заполнения в админке.');
}
function up_verify($runtime=true){
    global $up_state,$up_manifest,$db;
    if(empty($up_state['complete'])){throw new RuntimeException('Обновление 5.3 ещё не завершено. Продолжите update.');}
    if(PHP_SAPI!=='cli'&&function_exists('opcache_invalidate')){foreach(array_keys($up_state['files']) as $relative){@opcache_invalidate(up_path($relative),true);}}
    foreach($up_manifest['theme'] as $relative=>$source){if(!hash_equals($up_manifest['hashes'][$source],up_hash(up_path('netcat_template/template/life_teen/'.$relative)))){throw new RuntimeException('Не совпадает файл оформления: '.$relative);}}
    if(!hash_equals($up_manifest['hashes'][$up_manifest['runtime']],up_hash(up_path('netcat/modules/default/function.inc.php')))){throw new RuntimeException('Не совпадают общие функции.');}
    foreach($up_manifest['components'] as $key=>$parts){$folder=up_component_folder($up_state['components'][$key]);foreach($parts as $part=>$source){$actual=file_get_contents($folder.'/'.$part.'.html');$expected=file_get_contents(up_payload($source));if($part==='RecordTemplate'){$actual=nc_cleaned_RecordTemplate_of_string_service($actual);}$actual=trim(str_replace("\r\n","\n",$actual));$expected=trim(str_replace("\r\n","\n",$expected));if(!hash_equals(hash('sha256',$expected),hash('sha256',$actual))){throw new RuntimeException('Не совпадает часть компонента '.$key.'/'.$part);}}}
    if((int)$db->get_var('SELECT Checked FROM Catalogue WHERE Catalogue_ID=1')!==$up_state['original_site_checked']){throw new RuntimeException('Изменился статус публикации сайта.');}
    foreach(['life_articles','life_gallery'] as $key){if((int)$db->get_var("SELECT TypeOfData_ID FROM Field WHERE Class_ID=".(int)$up_state['components'][$key]['Class_ID']." AND Field_Name='category'")!==4){throw new RuntimeException('Категории не переведены в список.');}}
    if($runtime&&!function_exists('project_schema_jsonld')){throw new RuntimeException('Обновлённые функции ещё не загружены. Обновите страницу.');}up_out('Проверка обновления 5.3 пройдена.');
}
function up_restore_rows($table,array $rows){foreach($rows as $row){$columns=array_map('up_ident',array_keys($row));up_query('REPLACE INTO '.up_ident($table).' ('.implode(',',$columns).') VALUES ('.implode(',',array_map('up_value',array_values($row))).')');}}
function up_remove_folder($folder){if(!is_dir($folder)){return;}$files=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($folder,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);foreach($files as $file){if($file->isDir()&&!$file->isLink()){rmdir($file->getPathname());}else{unlink($file->getPathname());}}rmdir($folder);}
function up_rollback(){
    global $up_state,$up_folder,$up_model,$db,$nc_core;
    if(empty($up_state['backup_complete'])){throw new RuntimeException('Копия обновления ещё не завершена.');}
    if(!empty($up_state['content_fingerprint'])&&$up_state['content_fingerprint']!==up_fingerprint()){throw new RuntimeException('После обновления изменились записи или добавлены новые материалы. Откат остановлен до изменения данных: сначала сохраните новые данные.');}
    $backup=up_read($up_folder.'/database.php');
    foreach($up_state['new_classes'] as $key=>$class){$nc_core->component->delete_compatible_components_cache_by_id((int)$class);$ids=(array)$db->get_col('SELECT Message_ID FROM Message'.(int)$class);foreach($ids as $id){$nc_core->message->delete_by_id((int)$id,(int)$class,false);}up_query('DELETE FROM Field WHERE Class_ID='.(int)$class);up_query('DELETE FROM Class WHERE Class_ID='.(int)$class);up_query('DROP TABLE IF EXISTS Message'.(int)$class);$folder=up_component_folder($up_state['components'][$key]);up_remove_folder($folder);}
    foreach($up_state['new_blocks'] as $id){up_query('DELETE FROM Sub_Class WHERE Sub_Class_ID='.(int)$id);}foreach($up_state['new_sections'] as $id){up_query('DELETE FROM Subdivision WHERE Subdivision_ID='.(int)$id);}
    foreach($up_state['new_lists'] as $table=>$id){up_query('DELETE FROM Classificator WHERE Classificator_ID='.(int)$id);up_query('DROP TABLE IF EXISTS '.up_ident('Classificator_'.$table));}
    foreach($up_model['category_lists'] as $key=>$def){$class=(int)$up_state['components'][$key]['Class_ID'];foreach($backup['columns'][$key] as $column){if($column['Field']!=='category'){continue;}$type=$column['Type'];if(!preg_match('/\A[a-z]+(?:\([0-9,]+\))?(?: unsigned)?\z/i',$type)){throw new RuntimeException('Не распознан исходный тип категории.');}$collation=$column['Collation'];$sql='ALTER TABLE Message'.$class.' MODIFY category '.$type.($collation?' COLLATE '.up_ident($collation):'').($column['Null']==='YES'?' NULL':' NOT NULL');up_query($sql);}}
    foreach($backup['records'] as $key=>$rows){$class=(int)$up_state['components'][$key]['Class_ID'];foreach(up_rows_decode($rows) as $row){$id=$row['Message_ID'];unset($row['Message_ID']);up_sql_update('Message'.$class,'Message_ID',$id,$row);}}
    foreach($up_state['added_fields'] as $key=>$fields){if(isset($up_state['new_classes'][$key])){continue;}$class=(int)$up_state['components'][$key]['Class_ID'];foreach($fields as $name=>$id){up_query('DELETE FROM Field WHERE Field_ID='.(int)$id);up_query('ALTER TABLE Message'.$class.' DROP COLUMN '.up_ident($name));}}
    foreach(['classes'=>'Class','fields'=>'Field','templates'=>'Template','blocks'=>'Sub_Class','sections'=>'Subdivision'] as $key=>$table){up_restore_rows($table,up_rows_decode($backup[$key]));}
    foreach($up_state['files'] as $relative=>$info){if($info['existed']){up_copy($up_folder.'/files/'.$relative,up_path($relative));}elseif(is_file(up_path($relative))){unlink(up_path($relative));}}
    $nc_core->component->clear_cache();$nc_core->event->execute(nc_event::AFTER_SITE_UPDATED,1);
    foreach(up_rows_decode($backup['sections']) as $row){up_sql_update('Subdivision','Subdivision_ID',$row['Subdivision_ID'],['LastUpdated'=>$row['LastUpdated'],'LastModified'=>$row['LastModified']]);}
    $up_state['complete']=false;$up_state['rolled_back']=true;up_save();up_out('Откат обновления 5.3 завершён.');
}

<?php
function up_settings_values($code) {
    if(trim((string)$code)===''){return [];}
    return (static function($code){$CustomSettings=[];eval($code);return (array)$CustomSettings;})($code);
}
function up_effective_template(array $section, array $sections, array $catalogue) {
    $seen=[];
    while(empty($section['Template_ID'])&&!empty($section['Parent_Sub_ID'])){
        if(isset($seen[$section['Subdivision_ID']])){break;}$seen[$section['Subdivision_ID']]=true;
        $section=$sections[$section['Parent_Sub_ID']]??[];
    }
    return (int)($section['Template_ID']?:($catalogue['Template_ID']??0));
}
function up_record_signature($key,array $rows,array $block) {
    global $up_state;
    $data=[];$class=(int)$up_state['components'][$key]['Class_ID'];
    $fields=(array)$GLOBALS['db']->get_results('SELECT Field_Name,TypeOfData_ID FROM Field WHERE Class_ID='.$class.' ORDER BY Priority,Field_ID',ARRAY_A);
    usort($rows,function($a,$b){return [(int)$a['Priority'],(int)$a['Message_ID']]<=>[(int)$b['Priority'],(int)$b['Message_ID']];});
    foreach($rows as $row){
        $item=['Checked'=>$row['Checked']];
        foreach($fields as $field){
            $name=$field['Field_Name'];$value=$row[$name]??null;
            if((int)$field['TypeOfData_ID']===6&&$value){$url=nc_file_path($class,(int)$row['Message_ID'],$name);$item[$name]=is_file($_SERVER['DOCUMENT_ROOT'].$url)?hash_file('sha256',$_SERVER['DOCUMENT_ROOT'].$url):$value;}else{$item[$name]=$value;}
        }
        $data[]=$item;
    }
    $view=$key==='life_subscription'?0:(int)$block['Class_Template_ID'];
    return hash('sha256',json_encode([$key,$view,up_settings_values($block['CustomSettings']),$data],JSON_UNESCAPED_UNICODE));
}

function up_plan(array $backup) {
    global $up_state,$db;
    $classes=[];foreach(up_rows_decode($backup['classes']) as $row){$classes[(int)$row['Class_ID']]=$row;}
    $sections=[];foreach(up_rows_decode($backup['sections']) as $row){$sections[(int)$row['Subdivision_ID']]=$row;}
    $blocks=[];foreach(up_rows_decode($backup['blocks']) as $row){$blocks[(int)$row['Sub_Class_ID']]=$row;}
    $records=[];$by_block=[];foreach($backup['records'] as $key=>$encoded){$records[$key]=up_rows_decode($encoded);foreach($records[$key] as $row){$by_block[(int)$row['Sub_Class_ID']][]=$row;}}
    $main=[];
    foreach($blocks as $block){$key=$classes[$block['Class_ID']]['Keyword']??'';if($key&&!$block['Class_Template_ID']&&!$block['SrcMirror']&&!empty($block['Checked'])){$main[$key]=$main[$key]??$block;}}
    foreach(['life_specialists','life_gallery','life_articles','life_faq','life_reviews','life_certificates','life_specialist_methods','life_specialist_education'] as $key){if(empty($main[$key])){throw new RuntimeException('Не найден основной инфоблок '.$key);}}
    $region=(array)$db->get_row("SELECT s.Class_ID,s.Sub_Class_ID FROM Sub_Class s JOIN Field f ON f.Class_ID=s.Class_ID AND f.Field_Name='main_domain' JOIN Field city ON city.Class_ID=s.Class_ID AND city.Field_Name='city' WHERE s.Catalogue_ID=1 ORDER BY s.Sub_Class_ID LIMIT 1",ARRAY_A);
    if(!$region){throw new RuntimeException('Не найден существующий компонент регионов.');}
    $regions=(array)$db->get_results('SELECT * FROM Message'.(int)$region['Class_ID'].' WHERE Sub_Class_ID='.(int)$region['Sub_Class_ID'].' AND Checked=1 ORDER BY Priority,Message_ID',ARRAY_A);
    $host=strtolower(preg_replace('/:\d+$/','',$_SERVER['HTTP_HOST']??''));$default=[];foreach($regions as $row){if(strtolower($row['main_domain'])===$host){$default=$row;break;}}
    $catalogue=up_rows_decode($backup['catalogue'])[0];$templates=[];foreach(up_rows_decode($backup['templates']) as $row){$templates[(int)$row['Template_ID']]=$row;}
    $plan=['regions_class'=>(int)$region['Class_ID'],'regions_cc'=>(int)$region['Sub_Class_ID'],'specialists_sub'=>(int)$main['life_specialists']['Subdivision_ID'],'gallery_sub'=>(int)$main['life_gallery']['Subdivision_ID'],'gallery_cc'=>(int)$main['life_gallery']['Sub_Class_ID'],'methods_cc'=>(int)$main['life_specialist_methods']['Sub_Class_ID'],'education_blocks'=>[],'people'=>[],'methods'=>[],'method_selections'=>[],'common'=>[],'source_map'=>[],'pages'=>[],'shared_views'=>[],'service_sections'=>[],'gallery_groups'=>[],'warnings'=>[],'faq_sub'=>(int)$main['life_faq']['Subdivision_ID']];
    $method_groups=[];
    foreach($records['life_specialist_methods'] as $row){
        if(!(int)$row['Checked']){continue;}$signature=hash('sha256',json_encode([$row['name'],$row['description']],JSON_UNESCAPED_UNICODE));
        if(!isset($method_groups[$signature])){$method_groups[$signature]=$row;$plan['methods'][(int)$row['Message_ID']]=$row;}
        $id=(int)$method_groups[$signature]['Message_ID'];$plan['method_selections'][(int)$row['specialist']][]=$id;
    }
    foreach($records['life_specialists'] as $person){
        $city=!empty($person['city_from_region'])?($default['city']??''):trim(strip_tags(shortcode($person['city']??'')));$region_id=0;
        if(!empty($person['city_from_region'])&&$default){$region_id=(int)$default['Message_ID'];}
        elseif($city!==''){foreach($regions as $row){if(mb_strtolower(trim($row['city']))===mb_strtolower($city)){$region_id=(int)$row['Message_ID'];break;}}}
        if($city!==''&&!$region_id){$plan['warnings'][]='Не найден город в справочнике для специалиста №'.$person['Message_ID'].': '.$city;}
        $education=[];foreach($records['life_specialist_education'] as $item){if((int)$item['specialist']===(int)$person['Message_ID']&&(int)$item['Checked']){$education[]=['name'=>$item['name'],'description'=>$item['description'],'period'=>$item['period'],'priority'=>(int)$item['Priority'],'id'=>(int)$item['Message_ID']];}}
        usort($education,function($a,$b){return [$a['priority'],$a['id']]<=>[$b['priority'],$b['id']];});foreach($education as &$item){unset($item['priority'],$item['id']);}unset($item);
        if(!$education&&trim((string)$person['education'])!==''&&strpos((string)$person['education'],'specialist-page__education')===false){$education=[['name'=>'','description'=>trim(strip_tags($person['education'])),'period'=>'']];}
        $plan['people'][(int)$person['Message_ID']]=['region'=>$region_id,'education'=>json_encode($education,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),'LastUpdated'=>$person['LastUpdated']];
    }
    foreach($blocks as $block){if((int)$block['Class_ID']===(int)$up_state['components']['life_specialist_education']['Class_ID']){$plan['education_blocks'][]=(int)$block['Sub_Class_ID'];}}
    $gallery_categories=(array)$db->get_results('SELECT * FROM Classificator_LifePhotoCategory WHERE Checked=1 ORDER BY LifePhotoCategory_Priority,LifePhotoCategory_ID',ARRAY_A);
    foreach($gallery_categories as $category){$plan['gallery_groups'][(int)$category['LifePhotoCategory_ID']]=['name'=>$category['LifePhotoCategory_Name'],'category'=>(int)$category['LifePhotoCategory_ID'],'photos'=>[]];}
    foreach($records['life_gallery'] as $row){
        if((int)$row['Sub_Class_ID']!==$plan['gallery_cc']){continue;}$category=(int)$row['category'];
        if(!isset($plan['gallery_groups'][$category])){$plan['gallery_groups'][$category]=['name'=>'Без категории','category'=>$category,'photos'=>[]];}
        if((int)$row['Checked']&&$row['img']){$url=nc_file_path((int)$up_state['components']['life_gallery']['Class_ID'],(int)$row['Message_ID'],'img');if(!is_file($_SERVER['DOCUMENT_ROOT'].$url)){throw new RuntimeException('Не найдена исходная фотография №'.$row['Message_ID']);}$row['source_path']=$url;$plan['gallery_groups'][$category]['photos'][]=$row;}
    }
    $per_section=[];foreach($blocks as $block){$per_section[(int)$block['Subdivision_ID']][]=$block;}
    $dedupe=[];$source_groups=[];
    foreach($per_section as $sub=>$items){
        usort($items,function($a,$b){return [(int)$a['Priority'],(int)$a['Sub_Class_ID']]<=>[(int)$b['Priority'],(int)$b['Sub_Class_ID']];});
        $section=$sections[$sub]??[];$template=up_effective_template($section,$sections,$catalogue);
        if(strpos($templates[$template]['File_Path']??'', '/life_teen/service/')===0){$plan['service_sections'][]=$section;}
        $first_banner=0;foreach($items as $block){if(($classes[$block['Class_ID']]['Keyword']??'')==='life_banner'&&!empty($block['Checked'])){$first_banner=(int)$block['Sub_Class_ID'];break;}}
        $local=[];
        foreach($items as $block){
            $key=$classes[$block['Class_ID']]['Keyword']??'';$id=(int)$block['Sub_Class_ID'];
            $is_local=$id===$first_banner||in_array($key,['life_leads','life_specialist_methods','life_specialist_education','life_article_tips','life_gallery'],true)||($key==='life_articles'&&!(int)$block['Class_Template_ID'])||($key==='life_specialists'&&!(int)$block['Class_Template_ID'])||($key==='life_text'&&$sub!==(int)$catalogue['Title_Sub_ID']);
            if($is_local){$local[$id]=true;}
        }
        foreach($items as $index=>$block){
            $key=$classes[$block['Class_ID']]['Keyword']??'';$id=(int)$block['Sub_Class_ID'];
            if(!$key||isset($local[$id])||in_array($key,['life_specialist_methods','life_specialist_education','life_article_tips'],true)||empty($block['Checked'])){continue;}
            if($sub===(int)$main['life_specialist_methods']['Subdivision_ID']||$sub===(int)$main['life_specialist_education']['Subdivision_ID']){continue;}
            $source=(int)$block['SrcMirror'];$settings_source=$id;
            if(!$source&&empty($by_block[$id])&&isset($main[$key])&&in_array($key,['life_specialists','life_reviews','life_certificates','life_faq','life_articles'],true)){$source=(int)$main[$key]['Sub_Class_ID'];}
            if(!$source){
                $signature=up_record_signature($key,$by_block[$id]??[],$block);
                $source=$dedupe[$signature]??$id;$dedupe[$signature]=$source;
                $settings_source=$source;
            }
            $plan['common'][$id]=$block;$plan['source_map'][$id]=$source;
            $effective=(int)($block['Class_Template_ID']?:$block['Class_ID']);$plan['shared_views'][]=$effective;
            $before=0;for($next=$index+1;$next<count($items);$next++){if(isset($local[(int)$items[$next]['Sub_Class_ID']])){$before=(int)$items[$next]['Sub_Class_ID'];break;}}
            $plan['pages'][$sub][]=['placement'=>$id,'host'=>$id,'source'=>$source,'settings_source'=>$settings_source,'original_view'=>$effective,'before'=>$before];
        }
    }
    $plan['shared_views']=array_values(array_unique($plan['shared_views']));
    $plan['named']=[];
    foreach(['life_subscription','life_popup'] as $key){if(isset($main[$key])){$cc=(int)$main[$key]['Sub_Class_ID'];$effective=(int)($main[$key]['Class_Template_ID']?:$main[$key]['Class_ID']);if($key==='life_subscription'&&isset($up_state['components']['life_subscription_shared'])){$effective=(int)$up_state['components']['life_subscription_shared']['Class_ID'];}$plan['named'][$key]=['host'=>$cc,'source'=>$plan['source_map'][$cc]??$cc,'original_view'=>$effective];$plan['shared_views'][]=$effective;}}
    $bindings=[];$originals=[];
    foreach($plan['pages'] as &$placements){foreach($placements as &$placement){$binding=$placement['original_view'].'-'.$placement['source'];$placement['binding']=$binding;$bindings[$binding]=['key'=>$binding,'original'=>(int)$placement['original_view'],'source'=>(int)$placement['source']];$originals[]=(int)$placement['original_view'];}unset($placement);}unset($placements);
    foreach($plan['named'] as &$placement){$binding=$placement['original_view'].'-'.$placement['source'];$placement['binding']=$binding;$bindings[$binding]=['key'=>$binding,'original'=>(int)$placement['original_view'],'source'=>(int)$placement['source']];$originals[]=(int)$placement['original_view'];}unset($placement);
    $plan['shared_views']=$bindings;$plan['shared_originals']=array_values(array_unique($originals));
    return $plan;
}

function up_migrate_methods() {
    global $up_state,$db;
    $plan=$up_state['plan'];$class=(int)$up_state['components']['life_specialist_methods']['Class_ID'];$cc=(int)$plan['methods_cc'];$archive=up_new_block('methods-archive',$class,$up_state['sections']['specialist-archive'],'Архив прежних методов',0);
    $canonical=array_map('intval',array_keys($plan['methods']));
    $rows=(array)$db->get_results('SELECT Message_ID FROM Message'.$class,ARRAY_A);
    foreach($rows as $row){if(!in_array((int)$row['Message_ID'],$canonical,true)){up_sql_update('Message'.$class,'Message_ID',$row['Message_ID'],['Sub_Class_ID'=>$archive,'Subdivision_ID'=>$up_state['sections']['specialist-archive']]);}}
    up_sql_update('Sub_Class','Sub_Class_ID',$cc,['Subdivision_ID'=>(int)$plan['specialists_sub'],'Checked'=>0,'Sub_Class_Name'=>'Методы работы — общий справочник','TableViewMode'=>1]);
    require_once $GLOBALS['nc_core']->ADMIN_FOLDER.'classificator.inc.php';$map=[];
    foreach($plan['methods'] as $id=>$method){
        up_sql_update('Message'.$class,'Message_ID',$id,['Sub_Class_ID'=>$cc,'Subdivision_ID'=>(int)$plan['specialists_sub'],'specialist'=>0]);
        $value='catalogue-'.(int)$id;$item=(int)$db->get_var("SELECT LifeSpecialistMethod_ID FROM Classificator_LifeSpecialistMethod WHERE Value='".$value."'");
        if(!$item){InsertInOneClassificatorCompleted((int)$up_state['new_lists']['LifeSpecialistMethod'],$method['name'],(int)$method['Priority'],$value);$item=(int)$db->get_var("SELECT LifeSpecialistMethod_ID FROM Classificator_LifeSpecialistMethod WHERE Value='".$value."'");}
        $map[(int)$id]=$item;
    }
    $person=(int)$up_state['components']['life_specialists']['Class_ID'];
    foreach($plan['people'] as $id=>$values){$selected=[];foreach($plan['method_selections'][$id]??[] as $method){if(isset($map[$method])){$selected[]=$map[$method];}}$selected=array_unique($selected);$up_state['plan']['people'][$id]['method_choices']=$selected?','.implode(',',$selected).',':'';}
    up_save();
}

function up_migrate_gallery_source() {
    global $up_state;
    $plan=$up_state['plan'];$old=(int)$up_state['components']['life_gallery']['Class_ID'];
    $archive=up_new_block('gallery-archive',$old,$up_state['sections']['shared'],'Архив одиночных фотографий до 5.4',0);
    up_query('UPDATE Message'.$old.' SET LastUpdated=LastUpdated,Sub_Class_ID='.$archive.',Subdivision_ID='.(int)$up_state['sections']['shared'].' WHERE Sub_Class_ID='.(int)$plan['gallery_cc']);
    up_sql_update('Sub_Class','Sub_Class_ID',$plan['gallery_cc'],['Class_ID'=>(int)$up_state['new_classes']['life_gallery_bulk'],'Class_Template_ID'=>0,'Sub_Class_Name'=>'Фотографии — массовая загрузка по категориям','TableViewMode'=>1]);
}

function up_migrate_gallery_group($key,array $group) {
    global $up_state,$db,$nc_core;
    $class=(int)$up_state['new_classes']['life_gallery_bulk'];$cc=(int)$up_state['plan']['gallery_cc'];$keyword='v54-gallery-'.(int)$key;
    $id=(int)$db->get_var('SELECT Message_ID FROM Message'.$class." WHERE Keyword='".$keyword."' LIMIT 1");
    if(!$id){$id=(int)$nc_core->message->create($cc,['Keyword'=>$keyword,'name'=>$group['name'],'category'=>$group['category'],'Checked'=>1,'Priority'=>count($up_state['gallery_groups']??[])+1]);}
    if(!$id){throw new RuntimeException('Не создана группа фотографий.');}$up_state['gallery_groups'][$key]=$id;up_save();
    $field=(int)$up_state['added_fields'][$class]['img'];$format=$db->get_var('SELECT Format FROM Field WHERE Field_ID='.$field);$multifield=new nc_multifield('img','Фотографии',$format,$field,$class);
    foreach($group['photos'] as $photo){
        $token='photo:'.(int)$photo['Message_ID'];if(isset($up_state['photo_map'][$token])){continue;}
        $file=new nc_multifield_file();$file->set_values(['field_id'=>$field,'object_id'=>$id,'name'=>$photo['name']?:basename($photo['source_path']),'priority'=>(int)$photo['Priority'],'source_file'=>$photo['source_path']])->set_multifield($multifield)->save();
        if(!$file->get('id')||!is_file($file->get_full_path())){throw new RuntimeException('Не перенесена фотография №'.$photo['Message_ID']);}
        if(hash_file('sha256',$file->get_full_path())!==hash_file('sha256',$_SERVER['DOCUMENT_ROOT'].$photo['source_path'])){throw new RuntimeException('Не совпадает перенесённая фотография.');}
        $up_state['photo_map'][$token]=(int)$file->get('id');$up_state['new_media'][]=(int)$file->get('id');up_save();up_checkpoint('Сохранена фотография и её превью.');
    }
}

function up_service_text(array $section) {
    global $up_state,$db,$nc_core;
    $class=(int)$up_state['components']['life_text']['Class_ID'];$sub=(int)$section['Subdivision_ID'];
    $cc=(int)$db->get_var('SELECT Sub_Class_ID FROM Sub_Class WHERE Subdivision_ID='.$sub.' AND Class_ID='.$class.' ORDER BY Priority,Sub_Class_ID LIMIT 1');
    if(!$cc){$cc=up_new_block('text-'.$sub,$class,$sub,'Текст страницы услуги',1);}
    up_sql_update('Sub_Class','Sub_Class_ID',$cc,['Checked'=>1,'Sub_Class_Name'=>'Текст страницы услуги — редактируется здесь']);
    $id=(int)$db->get_var('SELECT Message_ID FROM Message'.$class.' WHERE Sub_Class_ID='.$cc.' ORDER BY Priority,Message_ID LIMIT 1');
    if(!$id){$id=(int)$nc_core->message->create($cc,['text'=>'','heading'=>'','Checked'=>1,'Priority'=>1,'Keyword'=>'v54-service-text']);}
    if(!$id){throw new RuntimeException('Не создан редактор текста услуги.');}
    $up_state['service_texts'][$cc]=['sub'=>$sub,'message'=>$id];up_save();
}

function up_move_common() {
    global $up_state;
    $shared=(int)$up_state['sections']['shared'];
    foreach($up_state['plan']['common'] as $id=>$block){
        $root=(int)$block['Class_ID'];$source=(int)$up_state['plan']['source_map'][$id];
        up_sql_update('Sub_Class','Sub_Class_ID',$id,['Subdivision_ID'=>$shared,'EnglishName'=>'shared-'.(int)$id,'SrcMirror'=>$source===(int)$id?0:$source,'Sub_Class_Name'=>$block['Sub_Class_Name']]);
        up_query('UPDATE Message'.$root.' SET LastUpdated=LastUpdated,Subdivision_ID='.$shared.' WHERE Sub_Class_ID='.(int)$id);
    }
}

function up_compile(array $row) {
    global $nc_core,$db;
    $folder=up_component_folder($row);$master='';
    foreach(['FormPrefix','RecordTemplate','FormSuffix'] as $part){$content=is_file($folder.'/'.$part.'.html')?file_get_contents($folder.'/'.$part.'.html'):'';if($part==='RecordTemplate'){$content=nc_cleaned_RecordTemplate_of_string_service($content);}$master.='<!-- '.$part.' -->'.$content.'<!-- /'.$part." -->\n";}
    file_put_contents($folder.'/Class.html',$master,LOCK_EX);$tpl=new nc_tpl($nc_core->CLASS_TEMPLATE_FOLDER,$db);$tpl->load((int)$row['Class_ID'],'Class');nc_tpl_parser::main2parts($tpl,'');$nc_core->event->execute(nc_event::AFTER_COMPONENT_UPDATED,(int)$row['Class_ID']);
}

function up_shared_view(array $binding) {
    global $up_state,$nc_core,$db;
    $original=(int)$binding['original'];$source_id=(int)$binding['source'];
    $old=null;foreach($up_state['components'] as $row){if((int)$row['Class_ID']===(int)$original){$old=$row;break;}}
    if(!$old){throw new RuntimeException('Не найден исходный шаблон общего блока.');}
    $key='life_v54_shared_'.(int)$original.'_'.(int)$source_id;$root=(int)($old['ClassTemplate']?:$old['Class_ID']);
    $id=(int)$db->get_var("SELECT Class_ID FROM Class WHERE Keyword='".$key."' AND ClassTemplate=".$root);
    if(!$id){$id=(int)$nc_core->component->create(['Keyword'=>$key,'Class_Name'=>$old['Class_Name'].' — общий вывод'],(int)$old['Class_ID'],$root);}
    if(!$id){throw new RuntimeException('Не создан общий шаблон.');}
    $row=$db->get_row('SELECT * FROM Class WHERE Class_ID='.$id,ARRAY_A);$up_state['new_classes'][$key]=$id;$up_state['components'][$key]=$row;up_save();
    $folder=up_component_folder($row);$source=up_component_folder($old);
    foreach(glob($source.'/*.html') as $file){if(basename($file)==='Class.html'){continue;}copy($file,$folder.'/'.basename($file));}
    $settings=is_file($source.'/Settings.html')?file_get_contents($source.'/Settings.html'):'';
    $settings.="\n<?php\n\$ignore_cc = 1;\n\$ignore_sub = 1;\n\$query_where = project_shared_source_where(".$source_id.", \$query_where ?? '');\n?>\n";
    file_put_contents($folder.'/Settings.html',$settings,LOCK_EX);up_compile($row);$up_state['shared_view_ids'][$binding['key']]=$id;up_save();
}

function up_finish_config() {
    global $up_state,$up_folder,$db;
    $plan=$up_state['plan'];$config=['regions_class'=>$plan['regions_class'],'regions_cc'=>$plan['regions_cc'],'specialist_class'=>(int)$up_state['components']['life_specialists']['Class_ID'],'methods_class'=>(int)$up_state['components']['life_specialist_methods']['Class_ID'],'methods_cc'=>$plan['methods_cc'],'faq_sub'=>$plan['faq_sub'],'service_texts'=>$up_state['service_texts']??[],'pages'=>[],'sources'=>[],'named'=>[]];
    $icons=['Интерактивный подход'=>1,'Поддержка и принятие'=>2,'Работа с подростками и родителями'=>3,'Практические инструменты'=>4];
    $config['method_icons']=[];foreach($plan['methods'] as $id=>$method){$config['method_icons'][$id]=$icons[$method['name']]??1;}
    foreach($plan['pages'] as $sub=>$placements){foreach($placements as $placement){$placement['view']=$up_state['shared_view_ids'][$placement['binding']];unset($placement['original_view'],$placement['binding']);$config['pages'][$sub][]=$placement;}}
    foreach($plan['named'] as $key=>$placement){$placement['view']=$up_state['shared_view_ids'][$placement['binding']];unset($placement['original_view'],$placement['binding']);$config['named'][$key]=$placement;}
    $blocks=(array)$db->get_results('SELECT Sub_Class_ID,Subdivision_ID,Class_ID,Checked FROM Sub_Class WHERE Catalogue_ID=1',ARRAY_A);
    foreach($blocks as $block){$config['sources'][(int)$block['Sub_Class_ID']]=['sub'=>(int)$block['Subdivision_ID'],'class'=>(int)$block['Class_ID'],'checked'=>(int)$block['Checked']];}
    up_store($up_folder.'/layout.php',$config);$up_state['layout_hash']=hash_file('sha256',$up_folder.'/layout.php');up_save();
}

function up_local_view($original) {
    global $up_state,$nc_core;
    foreach($up_state['components'] as $row){if((int)$row['Class_ID']!==(int)$original){continue;}$file=up_component_folder($row).'/Settings.html';if(!is_file($file)){return;}$code=file_get_contents($file);$code=preg_replace('/\$ignore_(?:cc|sub)\s*=\s*(?:1|true)\s*;/i','',$code);file_put_contents($file,$code,LOCK_EX);$nc_core->component->clear_cache();return;}
}

<?php
function up_plan(array $backup, array $old, array $config) {
    $classes=[];foreach(up_rows_decode($backup['classes']) as $row){$classes[(int)$row['Class_ID']]=$row;}
    $blocks=[];foreach(up_rows_decode($backup['blocks']) as $row){$blocks[(int)$row['Sub_Class_ID']]=$row;}
    $rows=[];foreach($backup['records'] as $key=>$encoded){foreach(up_rows_decode($encoded) as $row){$rows[(int)$row['Sub_Class_ID']][]=$row;}}
    $visible_settings=[];foreach($config['pages']??[] as $placements){foreach($placements as $p){$visible_settings[(int)$p['host']]=(int)($p['settings_source']??$p['host']);}}
    $services=array_map('intval',array_column($old['plan']['service_sections'],'Subdivision_ID'));
    $plan=['shared'=>(int)$old['sections']['shared'],'placements'=>[],'sources'=>[],'bindings'=>[],'faq_copies'=>[],'legacy'=>[],'config'=>$config,'gallery_archive'=>(int)($old['blocks']['gallery-archive']??0)];
    unset($plan['config']['pages'],$plan['config']['named'],$plan['config']['sources']);
    $movable=['life_founder','life_video','life_program','life_quiz','life_cards'];
    foreach($old['plan']['common'] as $id=>$original){
        $id=(int)$id;if(!isset($blocks[$id])){continue;}$current=$blocks[$id];$class=$classes[(int)$current['Class_ID']];$keyword=$class['Keyword'];
        $original_view=(int)($original['Class_Template_ID']?:$original['Class_ID']);$source=(int)$old['plan']['source_map'][$id];
        $section=(int)$current['Subdivision_ID']===$plan['shared']?(int)$original['Subdivision_ID']:(int)$current['Subdivision_ID'];
        $settings_cc=$visible_settings[$id]??$id;$settings=$blocks[$settings_cc]['CustomSettings']??$current['CustomSettings'];
        $placement=['id'=>$id,'sub'=>$section,'name'=>$current['Sub_Class_Name'],'english'=>$original['EnglishName'],'priority'=>(int)$original['Priority'],'class'=>(int)$current['Class_ID'],'original'=>$original_view,'settings'=>$settings,'mode'=>'local'];
        $is_service=in_array($section,$services,true);
        if(in_array($keyword,$movable,true)&&!($keyword==='life_cards'&&$is_service)&&!empty($rows[$source])){
            $plan['sources'][$source]=['old'=>$source,'class'=>(int)$blocks[$source]['Class_ID'],'view'=>(int)$blocks[$source]['Class_Template_ID'],'name'=>$blocks[$source]['Sub_Class_Name'],'settings'=>$blocks[$source]['CustomSettings']];
            $placement['mode']='shared';$placement['source_key']=$source;
        } elseif(in_array($keyword,['life_articles','life_specialists','life_reviews','life_certificates'],true)&&$source!==$id){
            $placement['mode']='shared';$placement['source']=$source;
        }
        if($keyword==='life_faq'&&empty($rows[$id])&&$source!==$id){$plan['faq_copies'][$id]=$rows[$source]??[];}
        if($placement['mode']==='shared'){$binding=$original_view.'-'.$source;$placement['binding']=$binding;$plan['bindings'][$binding]=['key'=>$binding,'original'=>$original_view,'source'=>$source,'moved'=>isset($placement['source_key'])];}
        $plan['placements'][$id]=$placement;
    }
    foreach($classes as $id=>$row){if(preg_match('/\Alife_v54_shared_([0-9]+)_([0-9]+)\z/',$row['Keyword'],$m)){$plan['legacy'][$id]=['row'=>$row,'original'=>(int)$m[1],'source'=>(int)$m[2]];}}
    $popup=(int)($old['plan']['named']['life_popup']['source']??0);
    if($popup&&isset($blocks[$popup])){$plan['popup']=['old'=>$popup,'class'=>(int)$blocks[$popup]['Class_ID'],'view'=>0,'name'=>'Форма консультации — общий диалог','settings'=>$blocks[$popup]['CustomSettings']];}
    // Preserve any placements added after 5.4 and turn legacy global FAQ into local copies.
    foreach($blocks as $id=>$block){$legacy=$plan['legacy'][(int)$block['Class_Template_ID']]??null;if(!$legacy||isset($plan['placements'][$id])){continue;}
        $key=$classes[(int)$block['Class_ID']]['Keyword'];$p=['id'=>$id,'sub'=>(int)$block['Subdivision_ID'],'name'=>$block['Sub_Class_Name'],'english'=>$block['EnglishName'],'priority'=>(int)$block['Priority'],'class'=>(int)$block['Class_ID'],'original'=>$legacy['original'],'settings'=>$block['CustomSettings'],'mode'=>'local'];
        if($key==='life_faq'){if(empty($rows[$id])){$plan['faq_copies'][$id]=$rows[$legacy['source']]??[];}}
        elseif(!in_array($key,['life_banner','life_text','life_contacts','life_location','life_program_service','life_subscription','life_parent_group','life_calculator','life_first_step','life_socials','life_popup'],true)){$binding=$legacy['original'].'-'.$legacy['source'];$p['mode']='shared';$p['binding']=$binding;$plan['bindings'][$binding]=['key'=>$binding,'original'=>$legacy['original'],'source'=>$legacy['source'],'moved'=>isset($plan['sources'][$legacy['source']])];}
        $plan['placements'][$id]=$p;
    }
    foreach($plan['placements'] as $p){$collision=(int)$GLOBALS['db']->get_var('SELECT Sub_Class_ID FROM Sub_Class WHERE Subdivision_ID='.(int)$p['sub']." AND EnglishName='".$GLOBALS['db']->escape($p['english'])."' AND Sub_Class_ID<>".(int)$p['id']);if($collision){$plan['placements'][$p['id']]['english']='block-'.$p['id'];}}
    return $plan;
}

function up_create_source($key,array $item) {
    global $up_state,$nc_core,$db;
    if(isset($up_state['source_ids'][$key])){return;}
    $id=(int)$db->get_var('SELECT Sub_Class_ID FROM Sub_Class WHERE Subdivision_ID='.(int)$up_state['plan']['shared']." AND EnglishName='".$db->escape('v55-source-'.$key)."' AND Class_ID=".(int)$item['class']);
    if(!$id){$id=(int)$nc_core->sub_class->create((int)$item['class'],['Subdivision_ID'=>$up_state['plan']['shared'],'Sub_Class_Name'=>'Общие данные: '.$item['name'],'EnglishName'=>'v55-source-'.$key,'Class_Template_ID'=>(int)$item['view'],'Checked'=>1,'Read_Access_ID'=>1,'Priority'=>100+count($up_state['source_ids']??[]),'CustomSettings'=>$item['settings']]);}
    if(!$id){throw new RuntimeException('Не создан источник общих данных.');}$up_state['source_ids'][$key]=$id;if(!in_array($id,$up_state['new_blocks'],true)){$up_state['new_blocks'][]=$id;}up_save();
}
function up_restore_placement(array $p) {
    global $up_state;
    up_sql_update('Sub_Class','Sub_Class_ID',$p['id'],['Subdivision_ID'=>$p['sub'],'EnglishName'=>$p['english'],'Priority'=>$p['priority'],'SrcMirror'=>0,'Class_Template_ID'=>$p['original']===$p['class']?0:$p['original'],'CustomSettings'=>$p['settings']]);
    up_query('UPDATE Message'.$p['class'].' SET LastUpdated=LastUpdated,Subdivision_ID='.(int)$p['sub'].' WHERE Sub_Class_ID='.(int)$p['id']);
}
function up_copy_faq($target,array $row) {
    global $up_state,$db,$nc_core;
    $p=$up_state['plan']['placements'][$target];$class=(int)$p['class'];$keyword='v55-faq-'.(int)$row['Message_ID'];
    $id=(int)$db->get_var('SELECT Message_ID FROM Message'.$class.' WHERE Sub_Class_ID='.(int)$target." AND Keyword='".$keyword."'");
    if(!$id){unset($row['Message_ID'],$row['Subdivision_ID'],$row['Sub_Class_ID']);$row['Keyword']=$keyword;$id=(int)$nc_core->message->create($target,$row);}
    if(!$id){throw new RuntimeException('Не скопирован локальный FAQ.');}$up_state['new_records'][$class][]=$id;up_save();
}
function up_compile(array $row) {
    global $nc_core,$db;
    $folder=up_component_folder($row);$master='';
    foreach(['FormPrefix','RecordTemplate','FormSuffix'] as $part){$content=is_file($folder.'/'.$part.'.html')?file_get_contents($folder.'/'.$part.'.html'):'';if($part==='RecordTemplate'){$content=nc_cleaned_RecordTemplate_of_string_service($content);}$master.='<!-- '.$part.' -->'.$content.'<!-- /'.$part." -->\n";}
    file_put_contents($folder.'/Class.html',$master,LOCK_EX);$tpl=new nc_tpl($nc_core->CLASS_TEMPLATE_FOLDER,$db);$tpl->load((int)$row['Class_ID'],'Class');nc_tpl_parser::main2parts($tpl,'');if(function_exists('opcache_invalidate')){foreach(glob($folder.'/*.html') as $file){@opcache_invalidate($file,true);}}$nc_core->event->execute(nc_event::AFTER_COMPONENT_UPDATED,(int)$row['Class_ID']);
}
function up_clean_local(array $row) {
    $folder=up_component_folder($row);$dirty=false;
    foreach(['Settings','FormPrefix'] as $part){$file=$folder.'/'.$part.'.html';if(!is_file($file)){continue;}$old=file_get_contents($file);$code=str_replace("<?= project_shared_before((int)\$cc) ?>\n",'',$old);if($part==='Settings'){$code=preg_replace('/\$ignore_(?:cc|sub)\s*=\s*(?:1|true)\s*;/i','',$code);}if($code!==$old){file_put_contents($file,$code,LOCK_EX);$dirty=true;}}
    if($dirty){up_compile($row);}
}
function up_create_view(array $definition) {
    global $up_state,$nc_core,$db;
    $original=(int)$definition['original'];$source=$definition['moved']?(int)$up_state['source_ids'][$definition['source']]:(int)$definition['source'];$old=null;
    foreach($up_state['components'] as $row){if((int)$row['Class_ID']===$original){$old=$row;break;}}
    if(!$old){throw new RuntimeException('Нет локального шаблона для общего вида.');}
    $keyword='life_v55_shared_'.$original.'_'.$source;$root=(int)($old['ClassTemplate']?:$original);
    $id=(int)$db->get_var("SELECT Class_ID FROM Class WHERE Keyword='".$keyword."'");if(!$id){$id=(int)$nc_core->component->create(['Keyword'=>$keyword,'Class_Name'=>$old['Class_Name'].' — общие данные'], $original,$root);}
    if(!$id){throw new RuntimeException('Не создан шаблон общих данных.');}
    $new=$db->get_row('SELECT * FROM Class WHERE Class_ID='.$id,ARRAY_A);$up_state['new_classes'][$keyword]=$id;$up_state['components'][$keyword]=$new;up_save();
    $folder=up_component_folder($new);$source_folder=up_component_folder($old);
    foreach(glob($source_folder.'/*') as $file){if(is_file($file)&&basename($file)!=='Class.html'){copy($file,$folder.'/'.basename($file));}}
    $settings=is_file($source_folder.'/Settings.html')?file_get_contents($source_folder.'/Settings.html'):'';
    $settings.="\n<?php\n\$ignore_cc = 1;\n\$ignore_sub = 1;\n\$project_content_source = ".$source.";\n\$query_where = project_shared_source_where(".$source.", \$query_where ?? '');\n?>\n";
    file_put_contents($folder.'/Settings.html',$settings,LOCK_EX);
    $prefix=is_file($folder.'/FormPrefix.html')?file_get_contents($folder.'/FormPrefix.html'):'';file_put_contents($folder.'/FormPrefix.html','<?= project_source_notice('.$source.') ?>'."\n".$prefix,LOCK_EX);
    $record=is_file($folder.'/RecordTemplate.html')?nc_cleaned_RecordTemplate_of_string_service(file_get_contents($folder.'/RecordTemplate.html')):'';
    file_put_contents($folder.'/RecordTemplate.html',"<?php \$f_AdminButtons = ''; ?>\n".$record,LOCK_EX);up_compile($new);
    $up_state['views'][$definition['key']]=['id'=>$id,'source'=>$source,'original'=>$original];up_save();
}
function up_finish_config() {
    global $up_state,$up_folder;
    $config=$up_state['plan']['config'];$config['version']='5.5.0';$config['shared_sub']=$up_state['plan']['shared'];$config['popup_source']=(int)($up_state['source_ids']['popup']??0);$config['global_views']=[];
    foreach($up_state['views'] as $view){$config['global_views'][$view['id']]=['source'=>$view['source'],'original'=>$view['original']];}
    $config['data_sources']=$up_state['source_ids'];up_store($up_folder.'/layout.php',$config);$up_state['layout_hash']=hash_file('sha256',$up_folder.'/layout.php');up_save();
}

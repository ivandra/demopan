<?php
function up_plan(array $backup, array $config) {
    global $db, $nc_core;
    $classes=[];foreach(up_rows_decode($backup['classes']) as $row){$classes[$row['Keyword']]=$row;}
    $sections=[];foreach(up_rows_decode($backup['sections']) as $row){$sections[(int)$row['Subdivision_ID']]=$row;}
    $blocks=up_rows_decode($backup['blocks']);$banner=(int)$classes['life_banner']['Class_ID'];
    $teen=0;$source=0;$source_sub=0;$targets=[];
    foreach($sections as $id=>$section){if($section['Hidden_URL']==='/podrostkam/'){$teen=$id;}if(in_array($section['Hidden_URL'],['/roditelyam/','/spetsialistam/'],true)){$targets[$id]=$section;}}
    foreach(up_rows_decode($backup['records']['life_banner']) as $record){
        if(preg_match('/Тревога и стресс/u',strip_tags($record['name']))){$source_sub=(int)$record['Subdivision_ID'];$source=(int)$record['Sub_Class_ID'];break;}
    }
    if(!$teen||!$source_sub||!$source){throw new RuntimeException('Не найден образец услуги «Тревога и стресс у подростков».');}
    foreach($sections as $id=>$section){$ancestor=(int)$section['Parent_Sub_ID'];$visited=[];while($ancestor&&isset($sections[$ancestor])&&!isset($visited[$ancestor])){if($ancestor===$teen){$targets[$id]=$section;break;}$visited[$ancestor]=true;$ancestor=(int)$sections[$ancestor]['Parent_Sub_ID'];}}
    unset($targets[$source_sub]);
    $seed=[];foreach($blocks as $block){if((int)$block['Subdivision_ID']===$source_sub){$seed[]=$block;}}
    usort($seed,function($a,$b){return [(int)$a['Priority'],(int)$a['Sub_Class_ID']]<=>[(int)$b['Priority'],(int)$b['Sub_Class_ID']];});
    $way=(int)$classes['life_cards_way']['Class_ID'];$has_way=false;
    foreach($seed as &$block){if((int)$block['Class_Template_ID']===$way||(int)($config['global_views'][(int)$block['Class_Template_ID']]['original']??0)===$way){
        $has_way=true;$block['Checked']=1;$settings=(static function($code){$CustomSettings=[];if($code){eval($code);}return (array)$CustomSettings;})($block['CustomSettings']);
        $settings+=['heading'=>'Простой и <span class="hero__title-accent">понятный</span> путь','content_formprefix_1'=>'КАК МЫ РАБОТАЕМ'];
        $block['CustomSettings']='$CustomSettings='.var_export($settings,true).';';
    }}unset($block);
    if(!$has_way){
        foreach($config['global_views']??[] as $view=>$binding){if((int)$binding['original']!==$way){continue;}
            foreach($blocks as $block){if((int)$block['Class_Template_ID']===(int)$view){$block['EnglishName']='life_cards_way';$block['Sub_Class_Name']='Как проходит работа';$block['Priority']=5;$seed[]=$block;$has_way=true;break 2;}}
        }
    }
    if(count($seed)<6||!$has_way){throw new RuntimeException('Образец услуги не содержит полного набора инфоблоков.');}
    usort($seed,function($a,$b){return [(int)$a['Priority'],(int)$a['Sub_Class_ID']]<=>[(int)$b['Priority'],(int)$b['Sub_Class_ID']];});
    $subscription=null;foreach($blocks as $block){if((int)$block['Class_ID']===(int)$classes['life_subscription']['Class_ID']){$subscription=$block;break;}}
    if(!$subscription){throw new RuntimeException('Нет образца подписки из service.html.');}
    $archive=[];$gallery=(int)$classes['life_gallery']['Class_ID'];
    $bulk=(int)$classes['life_gallery_bulk']['Class_ID'];$hashes=[];
    foreach((array)$db->get_col('SELECT m.Path FROM Multifield m JOIN Field f USING(Field_ID) WHERE f.Class_ID='.$bulk) as $path){$file=up_path(ltrim($path,'/'));if(is_file($file)){$hashes[hash_file('sha256',$file)]=true;}}
    foreach($sections as $id=>$section){
        if($section['EnglishName']!=='gallery-archive-v55'){continue;}
        foreach($sections as $child){if((int)$child['Parent_Sub_ID']===$id){throw new RuntimeException('В архиве фотографий появились новые подразделы.');}}
        foreach($blocks as $block){if((int)$block['Subdivision_ID']!==$id){continue;}if((int)$block['Class_ID']!==$gallery){throw new RuntimeException('В архиве фотографий появились новые инфоблоки.');}$archive['blocks'][]=(int)$block['Sub_Class_ID'];}
        foreach(up_rows_decode($backup['records']['life_gallery']) as $row){
            if((int)$row['Subdivision_ID']!==$id){continue;}
            $path=nc_file_path($gallery,(int)$row['Message_ID'],'img');$file=$path?up_path(ltrim($path,'/')):'';
            if(!$file||!is_file($file)||empty($hashes[hash_file('sha256',$file)])){throw new RuntimeException('В архиве есть фотография, не перенесённая в массовую галерею. Архив не изменён.');}
            $archive['records'][]=(int)$row['Message_ID'];
        }
        $archive['section']=$id;$archive['class']=$gallery;
    }
    return ['source_sub'=>$source_sub,'source'=>$source,'targets'=>$targets,'seed'=>$seed,'subscription'=>$subscription,'archive'=>$archive,'global_views'=>$config['global_views']??[]];
}

function up_block_for(array $section, array $seed, $priority) {
    global $db,$nc_core,$up_state;
    $sub=(int)$section['Subdivision_ID'];$class=(int)$seed['Class_ID'];$view=(int)$seed['Class_Template_ID'];
    // Retain existing editable text blocks and any already-filled matching placements.
    $id=(int)$db->get_var('SELECT Sub_Class_ID FROM Sub_Class WHERE Subdivision_ID='.$sub.' AND Class_ID='.$class.' AND (EnglishName='.up_value($seed['EnglishName']).' OR (Class_Template_ID='.$view.' AND NOT EXISTS (SELECT 1 FROM Sub_Class x WHERE x.Subdivision_ID='.$sub.' AND x.Class_ID='.$class.' AND x.EnglishName='.up_value($seed['EnglishName']).'))) ORDER BY Sub_Class_ID LIMIT 1');
    if(!$id){
        $properties=$seed;unset($properties['Sub_Class_ID']);
        $properties['Subdivision_ID']=$sub;$properties['Priority']=$priority;$properties['SrcMirror']=0;
        if($db->get_var('SELECT Sub_Class_ID FROM Sub_Class WHERE Subdivision_ID='.$sub.' AND EnglishName='.up_value($properties['EnglishName']))){$properties['EnglishName']='v56-block-'.(int)$seed['Sub_Class_ID'];}
        $id=(int)$nc_core->sub_class->create($class,$properties);
        if(!$id){throw new RuntimeException('Не создан инфоблок в разделе '.$section['Subdivision_Name']);}
        $up_state['new_blocks'][]=$id;up_save();
    }
    up_sql_update('Sub_Class','Sub_Class_ID',$id,['Priority'=>$priority]);
    return $id;
}

function up_copy_record($class, array $row, $target, $heading=null) {
    global $db,$nc_core,$up_state;
    $keyword='v56-copy-'.(int)$row['Message_ID'];
    $id=(int)$db->get_var('SELECT Message_ID FROM Message'.(int)$class.' WHERE Sub_Class_ID='.(int)$target.' AND Keyword='.up_value($keyword));
    if($id){return;}
    $source=(int)$row['Message_ID'];unset($row['Message_ID'],$row['Sub_Class_ID'],$row['Subdivision_ID']);$row['Keyword']=$keyword;
    if($heading!==null){$row['name']=$heading;}
    foreach($nc_core->get_component($class)->get_all_file_fields() as $field){
        $name=$field['name'];$info=$nc_core->file_info->get_file_info($class,$source,$name,false);
        unset($row[$name]);
        if(!empty($info['url'])){$row[$name]=['path'=>$info['url'],'type'=>$info['type'],'name'=>$info['name']];}
    }
    $id=(int)$nc_core->message->create($target,$row);
    if(!$id){throw new RuntimeException('Не скопирована запись образца.');}
    $up_state['new_records'][$class][]=$id;up_save();
}

function up_fill_section(array $section) {
    global $db,$up_state;
    $backup=up_read($GLOBALS['up_folder'].'/database.php');$by_class=[];
    foreach(up_rows_decode($backup['classes']) as $class){$by_class[(int)$class['Class_ID']]=$class['Keyword'];}
    foreach($up_state['plan']['seed'] as $index=>$seed){
        $target=up_block_for($section,$seed,$index+1);$class=(int)$seed['Class_ID'];$key=$by_class[$class];
        if($key==='life_text') {continue;}
        // Existing source-specific records and all editor changes are retained.
        $existing=$db->get_row('SELECT Keyword FROM Message'.$class.' WHERE Sub_Class_ID='.$target.' ORDER BY Message_ID LIMIT 1',ARRAY_A);
        if($existing&&strpos($existing['Keyword'],'v56-copy-')!==0){continue;}
        $view=(int)($seed['Class_Template_ID']?:$class);
        if(isset($up_state['plan']['global_views'][$view])){continue;}
        if($key==='life_cards'&&$view===(int)$up_state['components']['life_cards_way']['Class_ID']){
            foreach($up_state['plan']['global_views'] as $global=>$binding){if((int)$binding['original']===$view){up_sql_update('Sub_Class','Sub_Class_ID',$target,['Class_Template_ID'=>$global]);continue 2;}}
        }
        foreach(up_rows_decode($backup['records'][$key]) as $row){if((int)$row['Sub_Class_ID']===(int)$seed['Sub_Class_ID']){up_copy_record($class,$row,$target,$key==='life_banner'?$section['Subdivision_Name']:null);}}
    }
    // Reuse the existing blank editable text record; add one if a new section lacked it.
    $text=(int)$up_state['components']['life_text']['Class_ID'];
    $cc=(int)$db->get_var('SELECT Sub_Class_ID FROM Sub_Class WHERE Subdivision_ID='.(int)$section['Subdivision_ID'].' AND Class_ID='.$text.' ORDER BY Priority LIMIT 1');
    if($cc&&!$db->get_var('SELECT Message_ID FROM Message'.$text.' WHERE Sub_Class_ID='.$cc)){
        up_copy_record($text,['Message_ID'=>0,'Keyword'=>'','heading'=>'','text'=>'','Checked'=>1,'Priority'=>1],$cc);
    }
    up_block_for($section,$up_state['plan']['subscription'],count($up_state['plan']['seed'])+1);
}

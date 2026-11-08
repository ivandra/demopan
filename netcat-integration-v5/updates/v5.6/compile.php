<?php
function up_compile(array $row) {
    global $nc_core,$db;
    $folder=up_component_folder($row);$master='';
    foreach(['FormPrefix','RecordTemplate','FormSuffix'] as $part){$content=is_file($folder.'/'.$part.'.html')?file_get_contents($folder.'/'.$part.'.html'):'';if($part==='RecordTemplate'){$content=nc_cleaned_RecordTemplate_of_string_service($content);}$master.='<!-- '.$part.' -->'.$content.'<!-- /'.$part." -->\n";}
    file_put_contents($folder.'/Class.html',$master,LOCK_EX);$tpl=new nc_tpl($nc_core->CLASS_TEMPLATE_FOLDER,$db);$tpl->load((int)$row['Class_ID'],'Class');nc_tpl_parser::main2parts($tpl,'');if(function_exists('opcache_invalidate')){foreach(glob($folder.'/*.html') as $file){@opcache_invalidate($file,true);}}$nc_core->event->execute(nc_event::AFTER_COMPONENT_UPDATED,(int)$row['Class_ID']);
}

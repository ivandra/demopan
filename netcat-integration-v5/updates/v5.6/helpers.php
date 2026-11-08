<?php
function up_once($key,callable $action,$message){global $up_state;if(!empty($up_state['done'][$key])){return;}$action();$up_state['done'][$key]=true;up_save();up_checkpoint($message);}
function up_fingerprint(){
    global $up_state,$db;$result=[];
    foreach($up_state['components'] as $key=>$row){if((int)$row['ClassTemplate']){continue;}$result['data:'.$key]=hash('sha256',json_encode(up_rows_encode((array)$db->get_results('SELECT * FROM Message'.(int)$row['Class_ID'].' ORDER BY Message_ID',ARRAY_A))));}
    foreach(['Sub_Class','Subdivision'] as $table){$rows=(array)$db->get_results('SELECT * FROM '.$table.' WHERE Catalogue_ID=1 ORDER BY '.$table.'_ID',ARRAY_A);if($table==='Subdivision'){foreach($rows as &$row){unset($row['LastUpdated'],$row['LastModified']);}unset($row);}$result[$table]=hash('sha256',json_encode(up_rows_encode($rows)));}
    return $result;
}
function up_restore_rows($table,array $rows) {
    foreach($rows as $row){up_query('REPLACE INTO '.up_ident($table).' ('.implode(',',array_map('up_ident',array_keys($row))).') VALUES ('.implode(',',array_map('up_value',array_values($row))).')');}
}
function up_remove_folder($folder) {
    if(!is_dir($folder)){return;}$iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($folder,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);foreach($iterator as $file){if($file->isDir()&&!$file->isLink()){rmdir($file->getPathname());}else{unlink($file->getPathname());}}rmdir($folder);
}
function up_rollback(){
    global $up_state,$up_folder,$db,$nc_core;
    if(empty($up_state['backup_complete'])){throw new RuntimeException('Резервная копия 5.6 ещё не завершена.');}
    if(!empty($up_state['fingerprint'])&&$up_state['fingerprint']!==up_fingerprint()){throw new RuntimeException('После обновления изменились материалы или настройки. Откат остановлен до изменений, чтобы сохранить вашу работу.');}
    $backup=up_read($up_folder.'/database.php');
    foreach($up_state['new_records'] as $class=>$ids){foreach(array_unique($ids) as $id){$nc_core->message->delete_by_id((int)$id,(int)$class,false);}}
    foreach($up_state['new_classes'] as $key=>$id){$nc_core->component->delete_compatible_components_cache_by_id($id);up_query('DELETE FROM Class WHERE Class_ID='.(int)$id);up_remove_folder(up_component_folder($up_state['components'][$key]));}
    foreach($up_state['new_blocks'] as $id){up_query('DELETE FROM Sub_Class WHERE Sub_Class_ID='.(int)$id);}foreach($up_state['new_sections'] as $id){up_query('DELETE FROM Subdivision WHERE Subdivision_ID='.(int)$id);}
    foreach($backup['records'] as $key=>$rows){$class=(int)$up_state['components'][$key]['Class_ID'];up_query('DELETE FROM Message'.$class);up_restore_rows('Message'.$class,up_rows_decode($rows));}
    foreach(['classes'=>'Class','fields'=>'Field','templates'=>'Template','blocks'=>'Sub_Class','sections'=>'Subdivision','catalogue'=>'Catalogue'] as $key=>$table){up_restore_rows($table,up_rows_decode($backup[$key]));}
    foreach($up_state['files'] as $relative=>$info){if($info['existed']){up_copy($up_folder.'/files/'.$relative,up_path($relative));}elseif(is_file(up_path($relative))){unlink(up_path($relative));}}
    if(is_file($up_folder.'/layout.php')){unlink($up_folder.'/layout.php');}$nc_core->component->clear_cache();$nc_core->event->execute(nc_event::AFTER_SITE_UPDATED,1);
    foreach(up_rows_decode($backup['sections']) as $row){up_sql_update('Subdivision','Subdivision_ID',$row['Subdivision_ID'],['LastUpdated'=>$row['LastUpdated'],'LastModified'=>$row['LastModified']]);}
    $up_state['complete']=false;$up_state['rolled_back']=true;up_save();up_out('Откат 5.6 завершён. Восстановлено состояние перед этим обновлением.');
}

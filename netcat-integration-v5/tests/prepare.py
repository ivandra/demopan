"""Create test-only endpoints in an isolated copy, never in the upload package."""
from pathlib import Path
import shutil
import os

SITE = Path(os.environ.get('NETCAT_TEST_SITE', '/workspace/netcat-runtime/site'))
PACKAGE = Path(__file__).resolve().parents[1]
shutil.copytree(PACKAGE / 'files', SITE, dirs_exist_ok=True)
(SITE / '_life_auth.php').write_text('''<?php
require __DIR__.'/vars.inc.php';
require $ROOT_FOLDER.'connect_io.php';
$nc_core=nc_core(); $nc_core->modules->load_env();
require $nc_core->ADMIN_FOLDER.'lang/'.$nc_core->lang->detect_lang().'.php';
echo $nc_core->user->authorize_by_id(1);
''')
(SITE / '_life_state.php').write_text('''<?php
require __DIR__.'/vars.inc.php';require $ROOT_FOLDER.'connect_io.php';
$nc=nc_core();$nc->user->attempt_to_authorize();
if(!$nc->InsideAdminAccess()){http_response_code(403);exit;}
require_once $nc->ADMIN_FOLDER.'lang/'.$nc->lang->detect_lang().'.php';
header('Content-Type: application/json');
$s=include __DIR__.'/netcat_files/life_teen_v5/state.php';
if(isset($_GET['inspect'])) {
    $inspect=array('components'=>array(),'templates'=>array());
    foreach($s['classes'] as $key=>$id) {
        $row=$nc->db->get_row('SELECT Class_ID,Class_Name,File_Path FROM Class WHERE Class_ID='.(int)$id,ARRAY_A);
        $folder=rtrim($nc->CLASS_TEMPLATE_FOLDER,'/').'/'.trim($row['File_Path'],'/');
        $entry=array('id'=>(int)$id,'name'=>$row['Class_Name'],'fields'=>(array)$nc->db->get_col('SELECT Field_Name FROM Field WHERE Checked=1 AND Class_ID='.(int)$id));
        foreach(array('FormPrefix','RecordTemplate','FormSuffix') as $part) {
            $entry[$part]=is_file($folder.'/'.$part.'.html')?file_get_contents($folder.'/'.$part.'.html'):'';
            if($part==='RecordTemplate')$entry[$part]=nc_cleaned_RecordTemplate_of_string_service($entry[$part]);
        }
        $inspect['components'][$key]=$entry;
    }
    $inspect['all_templates']=$nc->db->get_results('SELECT Template_ID,Parent_Template_ID,Description,File_Path FROM Template',ARRAY_A);
    $inspect['all_sections']=$nc->db->get_results('SELECT Subdivision_ID,Subdivision_Name,EnglishName,Parent_Sub_ID,Hidden_URL,Template_ID,Checked,Read_Access_ID,(SELECT COUNT(*) FROM Sub_Class cc WHERE cc.Subdivision_ID=Subdivision.Subdivision_ID) AS BlockCount FROM Subdivision WHERE Catalogue_ID=1',ARRAY_A);
    $inspect['old_classes']=(int)$nc->db->get_var("SELECT COUNT(*) FROM Class WHERE Keyword LIKE 'zhp\\_%' OR Keyword LIKE 'lp\\_%' OR Class_Group IN ('medelta','medelta о нас')");
    $inspect['template_parts']=array();
    foreach($inspect['all_templates'] as $row) {
        if(strpos($row['File_Path'],'/life_teen/')!==0)continue;
        $parts=array();
        foreach(array('Header','Footer','Settings') as $part) {
            $path=rtrim($nc->TEMPLATE_FOLDER,'/').'/'.trim($row['File_Path'],'/').'/'.$part.'.html';
            $parts[$part]=is_file($path)?file_get_contents($path):'';
        }
        $comments=0;
        foreach($parts as $value)foreach(token_get_all($value) as $token){if(is_array($token)&&in_array($token[0],array(T_COMMENT,T_DOC_COMMENT),true))$comments++;}
        $inspect['template_parts'][]=array('id'=>(int)$row['Template_ID'],'parts'=>$parts,'comments'=>$comments);
    }
    echo json_encode($inspect);exit;
}
if(isset($_GET['settings'])) {
    if($_GET['settings']==='reset') {
        $nc->subdivision->update($s['pages']['/blog/'],array('TemplateSettings'=>$_SESSION['qa_blog_settings']));
    } else {
        $_SESSION['qa_blog_settings']=$nc->subdivision->get_by_id($s['pages']['/blog/'],'TemplateSettings');
        $form=new nc_a2f($nc->template->get_custom_settings($s['template_id']));
        $form->set_value(array('support_title'=>'Поддержка из настроек макета'));
        $nc->subdivision->update($s['pages']['/blog/'],array('TemplateSettings'=>$form->get_values_as_string()));
    }
}
if(isset($_GET['nav'])) {
    if($_GET['nav']==='reset') {
        foreach(($_SESSION['qa_nav']??array()) as $row)$nc->subdivision->update($row['Subdivision_ID'],array('Checked'=>$row['Checked'],'Read_Access_ID'=>$row['Read_Access_ID']));
    } else {
        $ids=array($s['pages']['/podrostkam/'],$s['pages']['/podrostkam/trevoga-i-perezhivaniya/trevozhnye-mysli-i-strahi/']);
        $_SESSION['qa_nav']=(array)$nc->db->get_results('SELECT Subdivision_ID,Checked,Read_Access_ID FROM Subdivision WHERE Subdivision_ID IN ('.implode(',',array_map('intval',$ids)).')',ARRAY_A);
        foreach($ids as $id)$nc->subdivision->update($id,array('Checked'=>1,'Read_Access_ID'=>1));
    }
}
if(isset($_GET['seed_region'])) {
    $nc->message->create(57,array('main_domain'=>'spb.lifepodrostka.ru','codename'=>'spb',
        'city'=>'Санкт-Петербург','phone'=>'+7 (812) 000-00-00',
        'address'=>'Региональный адрес','email'=>'spb@example.test','Checked'=>1));
}
echo json_encode($s);
''')
print('Test-only admin endpoints created in isolated copy')

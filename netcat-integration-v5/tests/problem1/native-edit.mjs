import fs from 'node:fs';
import {createRequire} from 'node:module';
import {execFileSync,spawnSync} from 'node:child_process';
const require=createRequire(import.meta.url);
const {chromium}=require(process.env.CODEX_PRIMARY_RUNTIME_NODE_MODULES+'/playwright');
const {default:binary}=await import(process.env.NETCAT_CHROMIUM_MODULE);
const address=process.env.NETCAT_REPRO_ADDRESS,host=process.env.NETCAT_TEST_HOST,output=process.env.NETCAT_QA_OUTPUT,container=process.env.NETCAT_REPRO_WEB_CONTAINER||'demopan-repro-web';
if(!/^172\.(1[6-9]|2[0-9]|3[01])\.\d+\.\d+$/.test(address)||!host||!output?.startsWith('/tmp/demopan-')||!['demopan-repro-web','demopan-repro-web83'].includes(container))throw new Error('Isolated test settings required');
if(execFileSync('docker',['inspect','--format','{{range .NetworkSettings.Networks}}{{.IPAddress}}{{end}}',container],{encoding:'utf8'}).trim()!==address)throw new Error('Wrong test container');
const base='http://'+host+':8080';fs.mkdirSync(output,{recursive:true});
const checks=[];function check(name,ok,detail=''){checks.push({name,ok:!!ok,detail});console.log((ok?'PASS ':'FAIL ')+name);if(!ok)throw new Error(name+' '+JSON.stringify(detail));}
function php(code,input){return execFileSync('docker',['exec',...(input?['-i']:[]),'-e','HTTP_HOST='+host,container,'php','-r',code],{encoding:'utf8',input});}
const boot='$_SERVER["DOCUMENT_ROOT"]="/site";putenv("DOCUMENT_ROOT=/site");$_SERVER["HTTP_HOST"]=getenv("HTTP_HOST");$_SERVER["REQUEST_URI"]="/";$_SERVER["REQUEST_METHOD"]="GET";$_SERVER["REMOTE_ADDR"]="127.0.0.1";$_SERVER["HTTP_USER_AGENT"]="QA";require "/site/vars.inc.php";require $ROOT_FOLDER."connect_io.php";$nc_core=nc_core();require_once $nc_core->ADMIN_FOLDER.\'lang/\'.$nc_core->lang->detect_lang().\'.php\';$db=$nc_core->db;$catalogue=1;$db->query("SET time_zone=\'+00:00\'");';
function query(code){return php(boot+code);}
const metadata=JSON.parse(query('$class=(int)$db->get_var("SELECT Class_ID FROM Class WHERE Keyword=".chr(39)."life_specialists".chr(39)." AND ClassTemplate=0");$field=(int)$db->get_var("SELECT Field_ID FROM Field WHERE Class_ID=$class AND Field_Name=".chr(39)."documents".chr(39)."");$cc=$db->get_row("SELECT Sub_Class_ID,Subdivision_ID FROM Sub_Class WHERE Class_ID=$class AND Class_Template_ID=0 LIMIT 1",ARRAY_A);echo json_encode(["class"=>$class,"field"=>$field,"cc"=>$cc,"rows"=>$db->get_results("SELECT * FROM Message$class ORDER BY Message_ID LIMIT 2",ARRAY_A),"existing_files"=>(int)$db->get_var("SELECT COUNT(*) FROM Multifield WHERE Field_ID=$field"),"leads"=>(int)$db->get_var("SELECT Class_ID FROM Class WHERE Keyword=".chr(39)."life_leads".chr(39)." AND ClassTemplate=0")]);'));
metadata.leadMax=Number(query('echo (int)$db->get_var("SELECT MAX(Message_ID) FROM Message'+metadata.leads+'");'));
if((metadata.existing_files!==0&&!process.env.NETCAT_TEST_CLEANUP_ONLY)||metadata.rows.length!==2)throw new Error('Expected fresh QA fixture without personal documents');
const browser=await chromium.launch({executablePath:await binary.executablePath(),args:[...binary.args,'--host-resolver-rules=MAP '+host+' '+address,'--proxy-server=direct://']});
const context=await browser.newContext({viewport:{width:1440,height:1000}});context.setDefaultTimeout(20000);context.setDefaultNavigationTimeout(20000);await context.route('**/*',r=>{const u=new URL(r.request().url());return u.hostname===host&&u.port==='8080'?r.continue():r.abort();});
const page=await context.newPage();
async function edit(id){await page.goto(base+'/netcat/message.php?inside_admin=1&catalogue=1&sub='+metadata.cc.Subdivision_ID+'&cc='+metadata.cc.Sub_Class_ID+'&message='+id+'&isModal=1');}
let saveCount=0;
async function save(){const result=await page.locator('input[name="f_fio"]').evaluate(async n=>{const data=new FormData(n.form);data.set('partial','1');const u=new URL(n.form.action,location.href);const r=await fetch(u.pathname+u.search,{method:'POST',body:data});return{status:r.status,text:await r.text()};});fs.writeFileSync(output+'/save-'+(++saveCount)+'.html',result.text);if(result.status!==200){fs.writeFileSync(output+'/native-save-failure.html',result.text);throw new Error('Native edit HTTP '+result.status);}return result;}
async function consent(){const n=page.locator('[data-project-cookie]');if(await n.isVisible())await n.locator('button').click();}
let docPaths=[];
try{
 if(process.env.NETCAT_TEST_CLEANUP_ONLY)throw new Error('Cleanup only');
 await page.goto(base+'/_qa_auth.php');
 await page.goto(base+'/netcat/admin/classificator.php');check('native list manager shows specialties',await page.getByText('Специализации специалистов',{exact:false}).count()>0);
 await edit(metadata.rows[0].Message_ID);
 const before=await page.locator('input[type=checkbox][name^="f_category["]').evaluateAll(ns=>ns.map(n=>({name:n.name,checked:n.checked,value:n.value})));
 const family=JSON.parse(query('echo json_encode($db->get_var("SELECT LifeSpecialistCategory_ID FROM Classificator_LifeSpecialistCategory WHERE Value=".chr(39)."family".chr(39).""));'));
 await page.locator('input[type=checkbox][name^="f_category["]').evaluateAll((ns,family)=>ns.forEach(n=>{n.checked=n.value===String(family);}),family);
 await save();const changed=JSON.parse(query('echo json_encode($db->get_var("SELECT category FROM Message'+metadata.class+' WHERE Message_ID='+(+metadata.rows[0].Message_ID)+'"));'));check('native checkbox edit saves selected category',changed===','+family+',',changed);
 await edit(metadata.rows[0].Message_ID);await page.locator('input[type=checkbox][name^="f_category["]').evaluateAll((ns,before)=>ns.forEach(n=>{n.checked=before.find(v=>v.name===n.name)?.checked||false;}),before);await save();
 const fixture=fs.readFileSync(process.env.NETCAT_DOCUMENT_FIXTURE);
 for(let index=0;index<2;index++){
  await edit(metadata.rows[index].Message_ID);
  const files=Array.from({length:index===0?2:1},(_,n)=>({name:'qa-problem1-document-'+index+'-'+n+'.png',mimeType:'image/png',buffer:fixture}));
  await page.locator('input[name="f_documents_file[]"]').last().setInputFiles(files);
  await page.waitForFunction(expected=>document.querySelectorAll('input[name="multifile_upload_index[documents][]"]').length>=expected,files.length);
  fs.writeFileSync(output+'/upload-input-'+index+'.json',JSON.stringify(await page.locator('input[name=\"f_fio\"]').evaluate(n=>Array.from(new FormData(n.form).entries()).filter(([k])=>k.includes('documents')).map(([k,v])=>[k,typeof v==='string'?v:{name:v.name,size:v.size,type:v.type}])),null,2));
  await save();
 }
 const docs=JSON.parse(query('echo json_encode($db->get_results("SELECT Message_ID,Name,Path FROM Multifield WHERE Field_ID='+(+metadata.field)+' ORDER BY ID",ARRAY_A));'))||[];docPaths=docs.map(d=>d.Path);check('native upload saves three personal documents',docs.length===3,docs.length);
 await page.goto(base+'/o-nas/spetsialisty/');await consent();check('specialist directory aggregates all personal documents',await page.locator('[data-specialist-document]:not([data-specialist-document="0"]):not(.swiper-slide-duplicate)').count()===3);
 const links=await page.locator('.specialists-block__card-btn').evaluateAll(ns=>ns.slice(0,2).map(n=>n.getAttribute('href')));
 for(let index=0;index<2;index++){await page.goto(base+links[index]);check('personal documents shown only on their own detail '+index,await page.locator('[data-specialist-document="'+metadata.rows[index].Message_ID+'"]:not(.swiper-slide-duplicate)').count()===(index===0?2:1));}
 await page.goto(base+'/o-nas/sertifikaty/');check('all-certificates page includes personal documents',await page.locator('[data-specialist-document]:not([data-specialist-document="0"]):not(.swiper-slide-duplicate)').count()===3);
 for(const p of docPaths){const status=await page.evaluate(p=>fetch(p).then(r=>r.status),p);check('uploaded document is readable',status===200);}
 const rollback=spawnSync('docker',['exec','-e','HTTP_HOST='+host,container,'php','-d','short_open_tag=1','/site/zhp-update.php','rollback','--host='+host],{encoding:'utf8'});check('rollback protects documents uploaded after update',rollback.status===1&&rollback.stdout.includes('загружены документы'));
 for(const [type,url]of [['contact','/kontakty/'],['subscribe','/'],['consult','/o-nas/']]){
  await page.goto(base+url);await consent();if(type==='consult')await page.locator('.header__button').click();
  const form=page.locator('form[data-zhp-form="'+type+'"]').first();
  if(await form.locator('[name=f_name]').count())await form.locator('[name=f_name]').fill('QA problem1 '+type);
  if(await form.locator('[name=f_contact]').count())await form.locator('[name=f_contact]').fill('qa-problem1@example.test');
  if(await form.locator('[name=f_phone]').count())await form.locator('[name=f_phone]').fill('+79990000001');
  if(await form.locator('[name=f_email]').count())await form.locator('[name=f_email]').fill('qa-problem1@example.test');
  const input=form.locator('[name=f_consent]');const id=await input.getAttribute('id');if(id)await form.locator('label[for="'+id+'"]').click();else await input.check();
  await form.locator('[type=submit]').click();await page.waitForFunction(type=>document.querySelector('form[data-zhp-form="'+type+'"] [data-form-status]').textContent.includes('сохран'),type,{timeout:20000});
  check('native '+type+' form saves',true);
 }
}catch(error){console.error(error.stack);checks.push({name:'exception',ok:false,detail:error.message});process.exitCode=1;try{fs.writeFileSync(output+'/failure.html',await page.content());await page.screenshot({path:output+'/failure.png',fullPage:true});}catch{}}
finally{
 try{
  await page.goto(base+'/_qa_auth.php');
  for(const row of metadata.rows){await edit(row.Message_ID);await page.locator('input[name="multifile_delete[documents][]"]').evaluateAll(ns=>ns.forEach(n=>n.value='1'));await save();}
  const cleanup=metadata.rows.map(row=>Object.fromEntries(Object.entries(row).map(([k,v])=>[k,v===null?null:Buffer.from(String(v)).toString('base64')])));
  php(boot+'$rows=json_decode(stream_get_contents(STDIN),true);foreach($rows as $row){$set=[];foreach($row as $k=>$v){if($k==="Message_ID")continue;$set[]=chr(96).$k.chr(96)."=".($v===null?"NULL":"UNHEX(\'".bin2hex(base64_decode($v))."\')");}$db->query("UPDATE Message'+metadata.class+' SET ".implode(",",$set)." WHERE Message_ID=".(int)base64_decode($row["Message_ID"]));}',JSON.stringify(cleanup));
  query('$ids=(array)$db->get_col("SELECT Message_ID FROM Message'+metadata.leads+' WHERE Message_ID>'+metadata.leadMax+' AND (name LIKE \'QA problem1 %\' OR email=\'qa-problem1@example.test\')");foreach($ids as $id){$nc_core->message->delete_by_id((int)$id,'+metadata.leads+',false);}');
  const left=JSON.parse(query('echo json_encode((int)$db->get_var("SELECT COUNT(*) FROM Multifield WHERE Field_ID='+(+metadata.field)+'"));'));check('test documents removed through native editor',left===0,left);
 }catch(error){console.error('Cleanup:',error.message);checks.push({name:'fixture cleanup',ok:false,detail:error.message});process.exitCode=1;}
 fs.writeFileSync(output+'/native-results.json',JSON.stringify({checks,documentsTested:docPaths.length},null,2));await browser.close();
}

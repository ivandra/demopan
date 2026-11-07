import fs from 'node:fs';import {createRequire} from 'node:module';import {execFileSync} from 'node:child_process';
const require=createRequire(import.meta.url),{chromium}=require(process.env.CODEX_PRIMARY_RUNTIME_NODE_MODULES+'/playwright');
const {default:binary}=await import(process.env.NETCAT_CHROMIUM_MODULE);
const host=process.env.NETCAT_TEST_HOST,address=process.env.NETCAT_REPRO_ADDRESS,out=process.env.NETCAT_QA_OUTPUT,container=process.env.NETCAT_REPRO_WEB_CONTAINER||'demopan-repro-web';
if(!host||!/^172\.(1[6-9]|2\d|3[01])\.\d+\.\d+$/.test(address)||!out?.startsWith('/tmp/demopan-')||!['demopan-repro-web','demopan-repro-web83'].includes(container))throw new Error('Isolated fixture required');
if(execFileSync('docker',['inspect','--format','{{range .NetworkSettings.Networks}}{{.IPAddress}}{{end}}',container],{encoding:'utf8'}).trim()!==address)throw new Error('Wrong fixture');
fs.mkdirSync(out,{recursive:true});
const boot='$_SERVER["DOCUMENT_ROOT"]="/site";putenv("DOCUMENT_ROOT=/site");$_SERVER["HTTP_HOST"]="'+host+'";$_SERVER["REQUEST_URI"]="/";$_SERVER["REQUEST_METHOD"]="GET";$_SERVER["REMOTE_ADDR"]="127.0.0.1";$_SERVER["HTTP_USER_AGENT"]="QA";require "/site/vars.inc.php";require $ROOT_FOLDER."connect_io.php";$nc_core=nc_core();$nc_core->modules->load_env();$db=$nc_core->db;';
function php(code,input){return execFileSync('docker',['exec',...(input?['-i']:[]),container,'php','-d','short_open_tag=1','-r',boot+code],{encoding:'utf8',input});}
function query(sql){return JSON.parse(php('$r=$db->get_results(base64_decode("'+Buffer.from(sql).toString('base64')+'"),ARRAY_A);echo json_encode($r?:[]);'));}
function mutate(sql){php('$db->query(base64_decode("'+Buffer.from(sql).toString('base64')+'"));if($db->last_error)throw new Exception($db->last_error);');}
function restore(table,rows){php('$rows=json_decode(stream_get_contents(STDIN),true);foreach($rows as $row){$set=[];foreach($row as $k=>$v){$set[]="`".$k."`=".($v===null?"NULL":"UNHEX(\'".bin2hex(base64_decode($v))."\')");}$db->query("REPLACE INTO '+table+' SET ".implode(",",$set));}',JSON.stringify(rows.map(r=>Object.fromEntries(Object.entries(r).map(([k,v])=>[k,v===null?null:Buffer.from(String(v)).toString('base64')])))));}
const checks=[],requests=[],cleanup=[];function check(name,ok,detail=''){checks.push({name,ok:!!ok,detail});console.log((ok?'PASS ':'FAIL ')+name);if(!ok)throw new Error(name+' '+JSON.stringify(detail));}
const base='http://'+host+':8080',b=await chromium.launch({executablePath:await binary.executablePath(),args:[...binary.args,'--host-resolver-rules=MAP '+host+' '+address,'--proxy-server=direct://']});
async function context(){const c=await b.newContext();await c.route('**/*',r=>{const u=new URL(r.request().url());return u.hostname===host&&u.port==='8080'?r.continue():r.abort();});return c;}
const anon=await context(),auth=await context(),p=await auth.newPage(),a=await anon.newPage();
async function guest(path){return a.goto(base+path,{waitUntil:'domcontentloaded'});}
async function post(mode,token){const start=Date.now();const r=await p.evaluate(async({mode,token})=>{const r=await fetch('/zhp-update.php?ajax=1',{method:'POST',body:new URLSearchParams({mode,token})});return{status:r.status,body:await r.json()};},{mode,token});requests.push({mode,status:r.status,ms:Date.now()-start,done:r.body.done});return r;}
try{
 let r=await guest('/zhp-update.php');check('updater rejects anonymous access',r.status()===403);
 await p.goto(base+'/_qa_auth.php');await p.goto(base+'/zhp-update.php');const token=(await p.content()).match(/const token="([a-f0-9]+)"/)[1];
 r=await post('update','invalid');check('updater rejects invalid CSRF',r.status===403&&!r.body.ok);
 r=await post('check',token);check('browser preflight passes',r.status===200&&r.body.ok);
 if(process.env.NETCAT_BROWSER_APPLY){let done=false;for(let i=0;i<1000&&!done;i++){r=await post('update',token);check('checkpoint '+i,r.status===200&&r.body.ok,r.body.message.slice(-120));done=r.body.done;}check('browser update completed from checkpoints',done);check('browser update uses short resumable requests',requests.filter(x=>x.mode==='update').length>5&&Math.max(...requests.map(x=>x.ms))<20000);}
 r=await post('verify',token);check('browser verification passes',r.status===200&&r.body.ok,r.body.message);
 r=await post('update',token);check('repeat update is idempotent',r.status===200&&r.body.ok&&r.body.message.includes('Повторные записи не создавались'));
 r=await guest('/netcat_files/life_teen_v55/state.php');check('backup state returns HTTP 404',r.status()===404);
 r=await guest('/netcat_files/life_teen_v55/database.php');check('backup database returns HTTP 404',r.status()===404);
 const articles=+query("SELECT Class_ID FROM Class WHERE Keyword='life_articles' AND ClassTemplate=0")[0].Class_ID;
 const person=+query("SELECT Class_ID FROM Class WHERE Keyword='life_specialists' AND ClassTemplate=0")[0].Class_ID;
 const row=query('SELECT * FROM Message'+articles+' ORDER BY Message_ID LIMIT 1');mutate('UPDATE Message'+articles+" SET intro=CONCAT(intro,' QA rollback guard') WHERE Message_ID="+row[0].Message_ID);
 try{r=await post('rollback',token);check('rollback stops before changing edited content',r.status===500&&!r.body.ok&&r.body.message.includes('изменились материалы'));check('rollback guard left update installed',+query("SELECT COUNT(*) AS n FROM Class WHERE Keyword LIKE 'life_v55_shared_%'")[0].n>0);}finally{restore('Message'+articles,row);}
 const section=query('SELECT * FROM Subdivision WHERE Subdivision_ID=57');mutate("UPDATE Subdivision SET Subdivision_Name=CONCAT(Subdivision_Name,' QA rollback guard') WHERE Subdivision_ID=57");
 try{r=await post('rollback',token);check('rollback also protects section settings',r.status===500&&!r.body.ok&&r.body.message.includes('изменились материалы'));}finally{restore('Subdivision',section);}
 const block=query("SELECT s.* FROM Sub_Class s JOIN Class c ON c.Class_ID=IF(s.Class_Template_ID>0,s.Class_Template_ID,s.Class_ID) WHERE c.Keyword='life_faq' AND s.SrcMirror=0 LIMIT 1");check('FAQ settings fixture exists',block.length===1);cleanup.push(()=>restore('Sub_Class',block));
 await p.goto(base+'/netcat/admin/index.php');await p.waitForFunction(()=>window.nc&&typeof nc.load_dialog==='function');await p.evaluate(id=>nc.load_dialog('/netcat/action.php?ctrl=admin.infoblock&infoblock_id='+id+'&action=show_settings_dialog'),block[0].Sub_Class_ID);const heading=p.locator('textarea[name="custom_settings[heading]"]');await heading.waitFor();check('heading remains one ordinary textarea',await heading.count()===1);check('heading has no split controls',await p.locator('[name*="heading_before"],[name*="heading_accent"],[name*="heading_after"]').count()===0);
 await heading.fill('QA единый <span class="hero__title-accent">заголовок</span>');r=await heading.evaluate(async n=>{const f=new FormData(n.form);const r=await fetch(n.form.getAttribute('action'),{method:'POST',body:f});return{status:r.status,text:await r.text()};});fs.writeFileSync(out+'/heading-save.html',r.text);check('one-field heading saves through native settings',r.status===200&&!/Fatal error|Ошибка/.test(r.text));await p.goto(base+'/o-nas/voprosy-i-otvety/');check('minimal heading formatting preserved',await p.locator('.hero__title-accent').filter({hasText:'заголовок'}).count()===1&&(await p.locator('body').innerText()).includes('QA единый'));
 restore('Sub_Class',block);cleanup.pop();
 const catalogue=query('SELECT * FROM Catalogue WHERE Catalogue_ID=1');cleanup.push(()=>restore('Catalogue',catalogue));check('site remains closed before guest probe',+catalogue[0].Checked===0);
 r=await guest('/blog/');check('closed site stays unavailable to guests',!(await r.text()).includes('articles-block__card'));
 mutate('UPDATE Catalogue SET Checked=1 WHERE Catalogue_ID=1');r=await guest('/blog/');const html=await r.text();check('guest rendering has schema without administrator controls',r.status()===200&&html.includes('application/ld+json')&&!html.includes('Netcat QuickBar')&&!html.includes('var nc_token'));
 restore('Catalogue',catalogue);cleanup.pop();check('site closed status restored',+query('SELECT Checked FROM Catalogue WHERE Catalogue_ID=1')[0].Checked===0);
}catch(e){checks.push({name:'exception',ok:false,detail:e.stack});console.error(e.stack);process.exitCode=1;try{fs.writeFileSync(out+'/failure.html',await p.content());}catch{}}
finally{for(const f of cleanup.reverse())try{f();}catch(e){process.exitCode=1;console.error(e.message);}fs.writeFileSync(out+'/lifecycle-results.json',JSON.stringify({checks,requests},null,2));await b.close();}

import fs from 'node:fs';
import {createRequire} from 'node:module';
import {execFileSync} from 'node:child_process';
const require=createRequire(import.meta.url);
const {chromium}=require(process.env.CODEX_PRIMARY_RUNTIME_NODE_MODULES+'/playwright');
const {default:binary}=await import(process.env.NETCAT_CHROMIUM_MODULE);
const host=process.env.NETCAT_TEST_HOST, address=process.env.NETCAT_REPRO_ADDRESS, out=process.env.NETCAT_QA_OUTPUT;
const container=process.env.NETCAT_REPRO_WEB_CONTAINER||'demopan-repro-web';
if(!host||!/^172\.(1[6-9]|2\d|3[01])\.\d+\.\d+$/.test(address)||!out?.startsWith('/tmp/demopan-')||!['demopan-repro-web','demopan-repro-web83'].includes(container))throw new Error('Isolated fixture required');
if(execFileSync('docker',['inspect','--format','{{range .NetworkSettings.Networks}}{{.IPAddress}}{{end}}',container],{encoding:'utf8'}).trim()!==address)throw new Error('Wrong fixture address');
fs.mkdirSync(out,{recursive:true});
const boot='$_SERVER["DOCUMENT_ROOT"]="/site";putenv("DOCUMENT_ROOT=/site");$_SERVER["HTTP_HOST"]="'+host+'";$_SERVER["REQUEST_URI"]="/";$_SERVER["REQUEST_METHOD"]="GET";$_SERVER["REMOTE_ADDR"]="127.0.0.1";$_SERVER["HTTP_USER_AGENT"]="QA";require "/site/vars.inc.php";require $ROOT_FOLDER."connect_io.php";$nc_core=nc_core();$nc_core->modules->load_env();$db=$nc_core->db;$catalogue=1;require_once $nc_core->ADMIN_FOLDER."lang/".$nc_core->lang->detect_lang().".php";$db->query("SET time_zone=\'+00:00\'");';
function php(code,input){return execFileSync('docker',['exec',...(input?['-i']:[]),container,'php','-d','short_open_tag=1','-r',boot+code],{encoding:'utf8',input});}
function query(sql){return JSON.parse(php('$r=$db->get_results(base64_decode("'+Buffer.from(sql).toString('base64')+'"),ARRAY_A);echo json_encode($r?:[]);'));}
function mutate(sql){php('$db->query(base64_decode("'+Buffer.from(sql).toString('base64')+'"));if($db->last_error){throw new Exception($db->last_error);}');}
const classes=Object.fromEntries(query("SELECT Class_ID,Keyword FROM Class WHERE Keyword LIKE 'life_%' AND ClassTemplate=0").map(x=>[x.Keyword,+x.Class_ID]));
const blocks=query("SELECT Sub_Class_ID,Subdivision_ID,Class_ID,Class_Template_ID FROM Sub_Class WHERE Catalogue_ID=1");
const checks=[],pages=[];function check(name,ok,detail=''){checks.push({name,ok:!!ok,detail});console.log((ok?'PASS ':'FAIL ')+name);if(!ok)throw new Error(name+' '+JSON.stringify(detail));}
const base='http://'+host+':8080',browser=await chromium.launch({executablePath:await binary.executablePath(),args:[...binary.args,'--host-resolver-rules=MAP '+host+' '+address,'--proxy-server=direct://']});
const context=await browser.newContext({viewport:{width:1440,height:1000}});context.setDefaultTimeout(15000);context.setDefaultNavigationTimeout(25000);
await context.route('**/*',r=>{const u=new URL(r.request().url());return u.hostname===host&&u.port==='8080'?r.continue():r.abort();});
const p=await context.newPage(),errors=[];p.on('pageerror',e=>errors.push({message:e.message,stack:e.stack,url:p.url()}));
async function go(path){const r=await p.goto(base+path,{waitUntil:'domcontentloaded'});if(await p.locator('.nc-component-template-error').count())throw new Error(await p.locator('.nc-component-template-error').allTextContents());return r;}
async function consent(){const n=p.locator('[data-project-cookie]');if(await n.isVisible())await n.locator('button').click();}
async function edit(key,id){const b=blocks.find(x=>+x.Class_ID===classes[key]&&!+x.Class_Template_ID);await go('/netcat/message.php?inside_admin=1&catalogue=1&sub='+b.Subdivision_ID+'&cc='+b.Sub_Class_ID+'&message='+id+'&isModal=1');}
let saves=0;
async function save(marker){const r=await p.locator('[name="'+marker+'"]').first().evaluate(async n=>{if(window.CKEDITOR)for(const x of Object.values(CKEDITOR.instances))x.updateElement();const d=new FormData(n.form);d.set('partial','1');const r=await fetch(n.form.action,{method:'POST',body:d});return{status:r.status,text:await r.text()};});fs.writeFileSync(out+'/save-'+(++saves)+'.html',r.text);check('native save HTTP '+saves,r.status===200&&!/Fatal error|Не заполнено обязательное поле|Ошибка при сохранении|загружены файлы недопустимого типа|превышающим допустимый/.test(r.text),r.status);}
const restore=[];
function snapshot(table,where='1'){const rows=query('SELECT * FROM '+table+' WHERE '+where).sort((a,b)=>+(a.Message_ID??a.LifeArticleCategory_ID??a.Sub_Class_ID)-+(b.Message_ID??b.LifeArticleCategory_ID??b.Sub_Class_ID));restore.push(()=>php('$rows=json_decode(stream_get_contents(STDIN),true);foreach($rows as $row){$set=[];foreach($row as $k=>$v){$set[]="`".$k."`=".($v===null?"NULL":"UNHEX(\'".bin2hex(base64_decode($v))."\')");}$db->query("REPLACE INTO '+table+' SET ".implode(",",$set));}',JSON.stringify(rows.map(r=>Object.fromEntries(Object.entries(r).map(([k,v])=>[k,v===null?null:Buffer.from(String(v)).toString('base64')]))))));return rows;}
try{
 await go('/_qa_auth.php');
 const articleRows=snapshot('Message'+classes.life_articles),personRows=snapshot('Message'+classes.life_specialists);
 snapshot('Sub_Class');snapshot('Subdivision');snapshot('Message'+classes.life_specialist_methods);snapshot('Classificator_LifeSpecialistMethod');
 const articles=await (async()=>{await go('/blog/');return p.locator('.blog__item .blog__item-title a,.blog__item-link').evaluateAll(ns=>ns.map(n=>n.getAttribute('href')));})();
 const articleLinks=await p.locator('.blog__item a[href*=".html"]').evaluateAll(ns=>[...new Set(ns.map(n=>n.getAttribute('href')))]);
 await go('/o-nas/spetsialisty/');const personLinks=await p.locator('.specialists-block__card-btn').evaluateAll(ns=>ns.map(n=>n.getAttribute('href')));
 check('canonical article and specialist links found',articleLinks.length>=3&&personLinks.length>=2,{articleLinks,personLinks});
 const paths=[['home','/'],['about','/o-nas/'],['blog','/blog/'],['gallery','/o-nas/foto/'],['faq','/o-nas/voprosy-i-otvety/'],['specialists','/o-nas/spetsialisty/'],['contacts','/kontakty/'],['certificates','/o-nas/sertifikaty/'],['reviews','/o-nas/otzyvy/'],['article',articleLinks[0]],['article-2',articleLinks[1]],['article-3',articleLinks[2]],['person',personLinks[0]],['empty','/podrostkam/']];
 const service=query("SELECT s.Hidden_URL FROM Subdivision s JOIN Template t USING(Template_ID) WHERE t.File_Path='/life_teen/service/' AND EXISTS (SELECT 1 FROM Sub_Class b WHERE b.Subdivision_ID=s.Subdivision_ID AND b.Class_ID="+classes.life_banner+") LIMIT 1");
 if(service[0])paths.push(['service',service[0].Hidden_URL]);
 for(const width of (process.env.NETCAT_SKIP_GRID?[]:[1440,820,390])){
  await p.setViewportSize({width,height:1000});
  for(const [name,path] of paths){const r=await go(path);await consent();await p.waitForTimeout(80);const result=await p.evaluate(()=>({width:document.documentElement.scrollWidth,h1:document.querySelectorAll('h1').length,broken:[...document.images].filter(n=>n.getAttribute('src')&&n.complete&&n.naturalWidth===0&&!n.closest('[hidden]')).map(n=>n.getAttribute('src')),scripts:[...document.querySelectorAll('script[type="application/ld+json"]')].map(n=>JSON.parse(n.textContent)),text:document.body.innerText}));pages.push({name,path,viewport:width,status:r.status(),width:result.width,h1:result.h1,broken:result.broken});check(name+' '+width+' renders without overflow',r.status()===200&&result.width<=width+1,{status:r.status(),width:result.width});check(name+' '+width+' has no broken images',result.broken.length===0,result.broken);check(name+' '+width+' has at most one H1',result.h1<=1,result.h1);check(name+' '+width+' emits one JSON-LD graph',result.scripts.length===1,result.scripts.length);if(width===1440){fs.writeFileSync(out+'/'+name+'.html',await p.content());fs.writeFileSync(out+'/'+name+'.json',JSON.stringify(result.scripts[0],null,2));await p.screenshot({path:out+'/'+name+'.png',fullPage:true});}if(width===390&&['article','person','home'].includes(name))await p.screenshot({path:out+'/'+name+'-390.png',fullPage:true});}
 }
 await p.setViewportSize({width:1440,height:1000});await go('/o-nas/voprosy-i-otvety/');
 const trigger=p.locator('.faq-block__trigger').first(),answer=p.locator('.faq-block__answer').first();await trigger.scrollIntoViewIfNeeded();const samples=await trigger.evaluate(n=>new Promise(resolve=>{const a=n.closest('.faq-block__item').querySelector('.faq-block__answer'),start=performance.now(),values=[];n.click();function frame(){values.push({ms:performance.now()-start,h:a.getBoundingClientRect().height});if(performance.now()-start<650)requestAnimationFrame(frame);else resolve(values);}requestAnimationFrame(frame);}));const full=samples.at(-1).h;check('FAQ has an intermediate opening height',samples.some(x=>x.h>0&&x.h<full),samples);
 await trigger.click();await p.waitForTimeout(100);await trigger.click();await p.waitForTimeout(450);check('FAQ rapid reversal ends open',await answer.isVisible());await trigger.click();await p.waitForTimeout(450);check('FAQ close sets hidden',await answer.getAttribute('hidden')!==null);
 await p.emulateMedia({reducedMotion:'reduce'});await trigger.click();await p.waitForTimeout(30);check('FAQ respects reduced motion',await answer.evaluate(n=>n.getBoundingClientRect().height)>=full-1);await p.emulateMedia({reducedMotion:'no-preference'});
 await go('/blog/');await p.locator('[data-article-category="parents"]').click();await p.waitForTimeout(500);check('article filter has no visible success status',!(await p.locator('body').innerText()).includes('Список статей обновлён')&&!(await p.locator('body').innerText()).includes('Загрузка статей'));
 await go(articleLinks[0]);const size=await p.locator('.article-page__related-image').first().evaluate(n=>{const r=n.getBoundingClientRect();return{w:r.width,h:r.height,fit:getComputedStyle(n.querySelector('img')||n).objectFit};});check('related article images square and cover',size.w===70&&size.h===70&&size.fit==='cover',size);
 await edit('life_articles',articleRows[1].Message_ID);check('article category is a native select',await p.locator('select[name=f_category]').count()===1);check('recommendation is native checkbox',await p.locator('input[type=checkbox][name=f_recommended]').count()===1);
 await p.locator('input[type=checkbox][name=f_recommended]').check();await save('f_name');fs.writeFileSync(out+'/article-after-save.json',JSON.stringify(query('SELECT * FROM Message'+classes.life_articles+' WHERE Message_ID='+articleRows[1].Message_ID),null,2));check('recommended checkbox persisted',+query('SELECT recommended FROM Message'+classes.life_articles+' WHERE Message_ID='+articleRows[1].Message_ID)[0].recommended===1);
 mutate('UPDATE Message'+classes.life_articles+' SET recommended=0 WHERE Message_ID<>'+articleRows[1].Message_ID);await go('/blog/');check('featured view displays selected normal article',(await p.locator('.articles-block__card').innerText()).includes(articleRows[1].name));
 mutate('UPDATE Message'+classes.life_articles+' SET recommended=0');await go('/blog/');check('zero recommendations retains blog heading and hides card',await p.locator('h1').count()===1&&await p.locator('.articles-block__card').count()===0);check('recommendation did not duplicate articles',query('SELECT Message_ID FROM Message'+classes.life_articles).length===articleRows.length);

 const emptyArticle=articleRows[1];mutate('UPDATE Message'+classes.life_articles+" SET img='',category=0,recommended=1 WHERE Message_ID="+emptyArticle.Message_ID);await go(articleLinks[1]);check('empty article category and image leave no badge or hero',await p.locator('.article-preview .blog__item-tag,.article-page__hero').count()===0);await go('/blog/');check('featured article without image uses one column',await p.locator('.articles-block__card.project-card-no-image').count()===1&&await p.locator('.articles-block__card-image').count()===0);mutate('UPDATE Message'+classes.life_articles+" SET img=UNHEX('"+Buffer.from(emptyArticle.img).toString('hex')+"'),category="+(+emptyArticle.category)+",recommended=0 WHERE Message_ID="+emptyArticle.Message_ID);

 // Real specialist editor: native relation, checkboxes and an in-card repeater.
 await edit('life_specialists',personRows[0].Message_ID);
 check('city is a region dropdown; no manual city controls',await p.locator('select[name=f_region]').count()===1&&await p.locator('[name=f_city],[name=f_city_from_region]').count()===0);
 check('methods are native multiple checkboxes',await p.locator('input[type=checkbox][name^="f_method_choices["]').count()===4);
 check('education editor retains all original rows',await p.locator('[data-education-row]').count()===3&&await p.locator('textarea[name=f_education]').count()===0);
 check('diplomas retain native multiple upload',await p.locator('input[type=file][name="f_documents_file[]"]').count()===1);
 check('headings have no split controls',await p.locator('[name*="_before"],[name*="_accent"],[name*="_after"]').count()===0);
 const choices=await p.locator('input[type=checkbox][name^="f_method_choices["]').evaluateAll(ns=>ns.map(n=>n.name));
 for(const name of choices.slice(1))await p.locator('input[name="'+name+'"]').uncheck();
 await p.locator('[data-education-remove]').last().click();await p.locator('[data-education-add]').click();
 const er=p.locator('[data-education-row]').last();await er.locator('[data-education-field=name]').fill('Сертификат «QA & практика»');await er.locator('[data-education-field=description]').fill('Психология "подростков"');await er.locator('[data-education-field=period]').fill('2026');
 await save('f_fio');
 let current=query('SELECT region,method_choices,education FROM Message'+classes.life_specialists+' WHERE Message_ID='+personRows[0].Message_ID)[0];
 check('native save persists one selected method and repeater quotes',current.method_choices.split(',').filter(Boolean).length===1&&JSON.parse(current.education).at(-1).name==='Сертификат «QA & практика»');
 await go(personLinks[0]);check('selected methods render without per-person child entries',await p.locator('.specialist-page__method-card').count()===1&&query('SELECT Message_ID FROM Message'+classes.life_specialist_methods).length===4);
 check('education renders automatic icons and escaped text',(await p.locator('.specialist-page__education').innerText()).includes('Сертификат «QA & практика»')&&await p.locator('.specialist-page__education-icon img[src$="education2.svg"]').count()>=1);
 // Choose another real region created only inside this disposable fixture.
 const regions=JSON.parse(php('$nc_core->modules->load_env();echo json_encode(project_native_config());'));
 const regionTable='Message'+regions.regions_class;const regionRows=snapshot(regionTable);const regionMax=Math.max(...regionRows.map(x=>+x.Message_ID));
 restore.push(()=>mutate('DELETE FROM '+regionTable+' WHERE Message_ID>'+regionMax));
 const regionCopy=regionRows.find(x=>+x.Checked===1);const tempRegion=JSON.parse(php('$row=$db->get_row("SELECT * FROM '+regionTable+' WHERE Message_ID='+regionCopy.Message_ID+'",ARRAY_A);unset($row["Message_ID"]);$row["city"]="QA Казань";$row["address"]="QA адрес 12";$row["main_domain"]="qa.invalid";$row["Keyword"]="qa-region-v54";$id=$nc_core->message->create('+regions.regions_cc+',$row);echo json_encode($id);'));
 check('region fixture created',+tempRegion>regionMax);
 await edit('life_specialists',personRows[0].Message_ID);await p.locator('select[name=f_region]').selectOption(String(tempRegion));await save('f_fio');await go(personLinks[0]);
 check('chosen region drives city and reception address',(await p.locator('.specialist-page__stats').innerText()).includes('QA Казань')&&(await p.locator('.specialist-page__details').innerText()).includes('QA адрес 12'));
 // A blank selection / no education produces no empty frontend groups.
 await edit('life_specialists',personRows[0].Message_ID);for(const name of choices)await p.locator('input[name="'+name+'"]').uncheck();while(await p.locator('[data-education-remove]').count())await p.locator('[data-education-remove]').first().click();await save('f_fio');await go(personLinks[0]);check('empty education omitted',await p.locator('.specialist-page__education').count()===0);check('unchecked method cards omitted',await p.locator('.specialist-page__method-card').count()===0);
 // Creating a new specialist uses the same native form and saves documents.
 const staffMax=Math.max(...personRows.map(x=>+x.Message_ID));restore.push(()=>php('$ids=(array)$db->get_col("SELECT Message_ID FROM Message'+classes.life_specialists+' WHERE Message_ID>'+staffMax+'");foreach($ids as $id){$nc_core->message->delete_by_id((int)$id,'+classes.life_specialists+',false);}'));
 await go('/netcat/add.php?inside_admin=1&catalogue=1&sub=57&cc=246&isModal=1');
 check('new specialist has city dropdown and education widget',await p.locator('select[name=f_region]').count()===1&&await p.locator('[data-education-add]').count()===1);
 await p.locator('[name=f_fio]').fill('QA Новый специалист');await p.locator('select[name=f_region]').selectOption(String(tempRegion));await p.locator('[data-education-add]').click();await p.locator('[data-education-field=name]').fill('QA образование');
 await p.locator('input[type=file][name="f_documents_file[]"]').setInputFiles({name:'qa-document.png',mimeType:'image/png',buffer:Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jX1sAAAAASUVORK5CYII=','base64')});await save('f_fio');
 const newPerson=query('SELECT * FROM Message'+classes.life_specialists+' WHERE Message_ID>'+staffMax)[0];check('new specialist persists region and in-card education',!!newPerson&&+newPerson.region===+tempRegion&&JSON.parse(newPerson.education)[0].name==='QA образование');
 check('new specialist saves native document upload',query('SELECT m.ID FROM Multifield m JOIN Field f USING(Field_ID) WHERE f.Class_ID='+classes.life_specialists+" AND f.Field_Name='documents' AND m.Message_ID="+newPerson.Message_ID).length===1);
 php('$nc_core->message->delete_by_id('+(+newPerson.Message_ID)+','+classes.life_specialists+',false);');
 // Shared methods are edited once below Specialists, without parent/image inputs.
 const method=query('SELECT * FROM Message'+classes.life_specialist_methods+' ORDER BY Message_ID LIMIT 1')[0];await edit('life_specialist_methods',method.Message_ID);
 check('method catalogue is below specialists and hidden',+blocks.find(x=>+x.Sub_Class_ID===regions.methods_cc).Subdivision_ID===57&&+query('SELECT Checked FROM Sub_Class WHERE Sub_Class_ID='+regions.methods_cc)[0].Checked===0);
 check('catalogue does not ask for owner or icon',await p.locator('[name=f_specialist],input[type=file][name=f_img]').count()===0);
 await p.locator('[name=f_name]').fill('QA изменённый метод');await p.locator('[name=f_description]').fill('QA общее описание');await save('f_name');await edit('life_specialists',personRows[0].Message_ID);
 check('catalogue change updates checkbox labels',(await p.locator('body').innerText()).includes('QA изменённый метод'));
 await p.locator('input[name="'+choices[0]+'"]').check();await save('f_fio');await go(personLinks[0]);check('catalogue change and stable builtin icon render',(await p.locator('.specialist-page__methods-list').innerText()).includes('QA общее описание')&&await p.locator('.specialist-page__method-icon img[src$="method1.png"]').count()===1);
 // Gallery: upload three files through Netcat's actual type-11 widget in one save.
 const galleryTable='Message'+classes.life_gallery_bulk,groups=snapshot(galleryTable),multis=snapshot('Multifield');const originalIds=multis.map(x=>+x.ID);const galleryField=+query('SELECT Field_ID FROM Field WHERE Class_ID='+classes.life_gallery_bulk+" AND Field_Name='img'")[0].Field_ID;
 restore.push(()=>{const added=query('SELECT ID FROM Multifield WHERE Field_ID='+galleryField).map(x=>+x.ID).filter(id=>!originalIds.includes(id));for(const id of added)php('(new nc_multifield_file('+id+'))->delete();');});
 await edit('life_gallery_bulk',groups[0].Message_ID);fs.writeFileSync(out+'/gallery-editor.html',await p.content());
 check('gallery uses native multiple file input',await p.locator('input[type=file][name="f_img_file[]"]').count()===1&&await p.locator('input[type=file][name="f_img_file[]"]').getAttribute('multiple')!==null);
 const png=Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jX1sAAAAASUVORK5CYII=','base64');
 await p.locator('input[type=file][name="f_img_file[]"]').setInputFiles([1,2,3].map(i=>({name:'qa-v54-'+i+'.png',mimeType:'image/png',buffer:png})));await save('f_name');
 const uploaded=query('SELECT * FROM Multifield WHERE Field_ID='+galleryField).filter(x=>!originalIds.includes(+x.ID));check('three images persisted from one native form submit',uploaded.length===3,uploaded.length);
 await go('/o-nas/foto/');check('gallery renders originals plus batch upload',await p.locator('.gallery__item').count()===15);check('native previews and originals exist',uploaded.every(x=>x.Path&&x.Preview&&fs.existsSync(process.env.NETCAT_TEST_SITE+x.Path)&&fs.existsSync(process.env.NETCAT_TEST_SITE+x.Preview)));
 await edit('life_gallery_bulk',groups[0].Message_ID);
 await p.locator('#nc_upload_file_img_'+uploaded[2].ID+' .nc-upload-file-remove').click();
 await p.locator('#nc_upload_file_img_'+uploaded[1].ID).evaluate(n=>n.parentElement.prepend(n));await save('f_name');
 const retained=query('SELECT * FROM Multifield WHERE Field_ID='+galleryField+' AND Message_ID='+groups[0].Message_ID+' ORDER BY Priority,ID');
 check('native delete removes only chosen uploaded image',!retained.some(x=>+x.ID===+uploaded[2].ID)&&!fs.existsSync(process.env.NETCAT_TEST_SITE+uploaded[2].Path));
 check('native reorder persists chosen first image',+retained[0].ID===+uploaded[1].ID);
 await go('/o-nas/foto/');const category=await p.locator('.gallery__item').first().getAttribute('data-photo-category');await p.locator('.gallery [data-filter="'+category+'"]').click();check('category filter shows only its grouped files',await p.locator('.gallery__item:visible').count()===retained.length);
 // Service: existing empty object is immediately editable, no Add object required.
 const serviceBlock=blocks.find(x=>+x.Class_ID===classes.life_text&&+x.Subdivision_ID===58);const serviceRows=snapshot('Message'+classes.life_text);const serviceRow=serviceRows.find(x=>+x.Sub_Class_ID===+serviceBlock.Sub_Class_ID);
 await go('/podrostkam/trevoga-i-perezhivaniya/');check('service has a direct edit-text link',await p.locator('.project-editor-link a[href*="cc='+serviceBlock.Sub_Class_ID+'"]') .count()===1);check('empty service text not rendered',await p.locator('section.text-block').count()===0);
 await edit('life_text',serviceRow.Message_ID);await p.locator('[name=f_heading]').fill('QA текст страницы услуги');await p.locator('[name=f_text]').evaluate(n=>{n.value='<p>QA заполнение через редактор</p>';if(window.CKEDITOR&&CKEDITOR.instances[n.id])CKEDITOR.instances[n.id].setData(n.value);});await save('f_heading');await go('/podrostkam/trevoga-i-perezhivaniya/');check('service text rendered with correct layout and one H1',await p.locator('section.text-block').count()===1&&await p.locator('h1').count()===1&&(await p.locator('section.text-block').innerText()).includes('QA заполнение через редактор'));
 check('no browser JavaScript exceptions',errors.length===0,errors);check('site remains closed',+query('SELECT Checked FROM Catalogue WHERE Catalogue_ID=1')[0].Checked===0);
}catch(e){checks.push({name:'exception',ok:false,detail:e.stack});console.error(e.stack);process.exitCode=1;try{fs.writeFileSync(out+'/failure.html',await p.content());await p.screenshot({path:out+'/failure.png',fullPage:true});}catch{}}
finally{for(const f of restore.reverse())try{f();}catch(e){console.error('Restore:',e.message);process.exitCode=1;}fs.writeFileSync(out+'/results.json',JSON.stringify({checks,pages,errors},null,2));await browser.close();}

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
const literal=value=>"UNHEX('"+Buffer.from(value).toString('hex')+"')";
try {
 await go('/_qa_auth.php');
 const target=query("SELECT Subdivision_ID,Subdivision_Name,Hidden_URL FROM Subdivision WHERE (Hidden_URL LIKE '/podrostkam/%' AND Hidden_URL<>'/podrostkam/') OR Hidden_URL IN('/roditelyam/','/spetsialistam/') ORDER BY Subdivision_ID");
 check('all requested service pages found',target.length===18,target.length);
 for(const width of (process.env.NETCAT_SKIP_GRID?[]:[1440,820,390])) {
  await p.setViewportSize({width,height:1000});
  for(const section of target) {
   const response=await go(section.Hidden_URL);
   const state=await p.evaluate(()=>({h1:[...document.querySelectorAll('main h1,.contacts-page h1')].map(n=>n.textContent.trim()),way:document.querySelectorAll('.way .steps__stage').length,subscription:document.querySelectorAll('form[data-zhp-form=subscribe]').length,width:document.documentElement.scrollWidth,bad:document.querySelectorAll('.nc-component-template-error').length,sections:['.contacts-page','.program--inner','.steps','.help-block','.way','.team'].map(s=>document.querySelectorAll(s).length)}));
   pages.push({path:section.Hidden_URL,viewport:width,status:response.status(),...state});
   check('complete service '+section.Subdivision_ID+' at '+width,response.status()===200&&state.h1.length===1&&state.way===4&&state.subscription===1&&state.width<=width+1&&!state.bad&&state.sections.every(n=>n===1),state);
   if(section.Hidden_URL!=='/podrostkam/trevoga-i-perezhivaniya/')check('matching heading '+section.Subdivision_ID+' at '+width,state.h1[0]===section.Subdivision_Name,state.h1);
   if(width===1440)fs.writeFileSync(out+'/service-'+section.Subdivision_ID+'.html',await p.content());
  }
 }
 for(const section of target){const count=query('SELECT b.Sub_Class_ID,(SELECT COUNT(*) FROM Message'+classes.life_text+' t WHERE t.Sub_Class_ID=b.Sub_Class_ID) n FROM Sub_Class b WHERE b.Subdivision_ID='+section.Subdivision_ID+' AND b.Class_ID='+classes.life_text);check('one editable service text '+section.Subdivision_ID,count.length===1&&+count[0].n===1,count);}
 await p.setViewportSize({width:1440,height:1000});await go('/');await consent();
 const photo=await p.locator('.specialists__item-img').first().evaluate(n=>({width:n.clientWidth,height:n.clientHeight}));check('home portraits match reference 192 by 160 ratio',Math.abs(photo.width/photo.height-1.2)<0.02,photo);
 check('home portrait and name links exist',await p.locator('.specialists__item-img a[href$=".html"]').count()>=5&&await p.locator('.specialists__item-title a[href$=".html"]').count()>=5);
 await p.locator('.specialists__item.swiper-slide-active .specialists__item-title a').first().click();await p.waitForLoadState('domcontentloaded');check('home specialist link opens profile',new URL(p.url()).pathname.includes('/o-nas/spetsialisty/')&&await p.locator('.specialist-page').count()===1,p.url());
 const other=await p.locator('.specialist-page__other-card').evaluateAll(ns=>ns.map(n=>({tag:n.tagName,href:n.getAttribute('href')})));check('other specialists have direct canonical links',other.length===4&&other.every(n=>n.tag==='A'&&/^\/o-nas\/spetsialisty\/.+\.html$/.test(n.href)),other);
 await p.locator('.specialist-page__other-card').first().click();await p.waitForLoadState('domcontentloaded');check('other specialist opens profile',await p.locator('.specialist-page').count()===1);
 await go('/podrostkam/trevoga-i-perezhivaniya/');check('team portraits and names link to primary profiles',await p.locator('.team__item .project-person-link[href$=".html"]').count()>=6);await p.locator('.team__item-name a').first().click();await p.waitForLoadState('domcontentloaded');check('team link opens primary profile',new URL(p.url()).pathname.includes('/o-nas/spetsialisty/')&&await p.locator('.specialist-page').count()===1);
 await go('/kontakty/');const line=p.locator('.contacts-page__header h1 .hero__title-line');check('contact heading has original underline asset',await line.count()===1&&await line.evaluate(n=>n.complete&&n.naturalWidth>0&&n.src.endsWith('/line5.svg')));
 const crumb=await p.locator('.project-breadcrumbs ol').evaluate(n=>({size:getComputedStyle(n).fontSize,weight:getComputedStyle(n).fontWeight,line:getComputedStyle(n).lineHeight}));check('breadcrumbs match reference font',crumb.size==='13px'&&+crumb.weight===600,crumb);
 await p.screenshot({path:out+'/contacts.png',fullPage:true});
 const article=snapshot('Message'+classes.life_articles);const published=article.filter(r=>+r.Checked).sort((a,b)=>+a.Priority-+b.Priority||+a.Message_ID-+b.Message_ID);
 await go('/blog/');const links=await p.locator('.blog__item-title a').evaluateAll(ns=>ns.map(n=>n.getAttribute('href')));check('articles exist for navigation tests',links.length===3,links);
 for(let i=0;i<links.length;i++){await go(links[i]);check('article navigation is below tips',await p.locator('.article-page__left > .article-page__nav').count()===1);check('article back link targets blog',await p.locator('.article-page__btn--prev').getAttribute('href')==='/blog/');const expected=links[(i+1)%links.length];check('next article uses canonical checked article',await p.locator('.article-page__btn--next').getAttribute('href')===expected,{expected,actual:await p.locator('.article-page__btn--next').getAttribute('href')});if(i===0){await p.screenshot({path:out+'/article.png',fullPage:true});await p.locator('.article-page__btn--next').click();await p.waitForLoadState('domcontentloaded');check('next article click opens different article',new URL(p.url()).pathname===expected);}}
 const table='Message'+classes.life_articles;mutate('UPDATE '+table+' SET LastUpdated=LastUpdated,Checked=0 WHERE Message_ID='+published[1].Message_ID);await go(links[0]);check('next skips disabled article',await p.locator('.article-page__btn--next').getAttribute('href')===links[2]);mutate('UPDATE '+table+' SET LastUpdated=LastUpdated,Checked=0 WHERE Message_ID='+published[2].Message_ID);await go(links[0]);check('single remaining article has no next self link',await p.locator('.article-page__btn--next').count()===0);restore.pop()();
 await go('/');await consent();await p.locator('header .header__button').first().click();const tel=p.locator('#popup input[type=tel]');await tel.waitFor({state:'visible'});
 for(const value of ['89991234567','9991234567','+7 999 123 45 67']){await tel.fill(value);check('Russian phone formats '+value,await tel.inputValue()==='+7(999)123-45-67'&&await tel.evaluate(n=>n.checkValidity()));}
 await tel.fill('435345345');check('incomplete phone cannot submit',!(await tel.evaluate(n=>n.checkValidity())));
 await tel.fill('');await tel.pressSequentially('9991234567');check('phone formats during typing',await tel.inputValue()==='+7(999)123-45-67');
 await tel.evaluate(n=>{n.setSelectionRange(7,7);});await tel.press('Backspace');check('backspace across separator removes a digit',!(await tel.evaluate(n=>n.checkValidity()))&&await tel.inputValue()!=='+7(999)123-45-67');
 await tel.fill('+79991234567');await p.locator('.fancybox-slide--current .fancybox-close-small').first().click();await p.locator('.fancybox-container').waitFor({state:'hidden'});
 const email=p.locator('.subscription input[type=email]');await email.fill('bad-address');check('email rejects missing at/domain',!(await email.evaluate(n=>n.checkValidity())));await email.fill('name@example');check('email rejects missing domain suffix',!(await email.evaluate(n=>n.checkValidity())));await email.fill(' user+tag@example.ru ');check('email accepts valid address and removes pasted spaces',await email.inputValue()==='user+tag@example.ru'&&await email.evaluate(n=>n.checkValidity()));
 check('all dedicated phone fields have mask pattern',await p.locator('form[data-zhp-form] input[type=tel]').evaluateAll(ns=>ns.length>=2&&ns.every(n=>n.pattern&&n.inputMode==='tel')));
 const gallery=query('SELECT COUNT(*) n FROM Multifield m JOIN Field f USING(Field_ID) WHERE f.Class_ID='+classes.life_gallery_bulk);check('gallery keeps all twelve photos',+gallery[0].n===12);check('old gallery archive removed from tree',query("SELECT Subdivision_ID FROM Subdivision WHERE EnglishName='gallery-archive-v55'").length===0);
 const leadKeys=[];restore.push(()=>{for(const key of leadKeys)mutate('DELETE FROM Message'+classes.life_leads+' WHERE request_key='+literal(key));});
 async function submit(form){const key=await form.locator('[name=f_request_key]').inputValue();leadKeys.push(key);await form.locator('[type=submit]').click();await p.waitForFunction(key=>{const form=[...document.querySelectorAll('form[data-zhp-form]')].find(f=>f.querySelector('[name=f_request_key]')?.value===key);return !form||!form.dataset.busy;},key);const rows=query('SELECT * FROM Message'+classes.life_leads+' WHERE request_key='+literal(key));check('public form creates one native request',rows.length===1,rows.map(r=>({type:r.form_type,phone:r.phone,email:r.email})));return rows[0];}
 await p.locator('header .header__button').first().click();const consult=p.locator('form#popup[data-zhp-form]');await consult.locator('[name=f_name]').fill('Проверка 5.6');await consult.locator('input[type=tel]').fill('89991234567');await consult.locator('[name=f_consent]').check();const request=await submit(consult);check('masked phone is stored correctly',request.phone==='+7(999)123-45-67',request.phone);await p.locator('.fancybox-slide--current .fancybox-close-small').first().click();await p.locator('.fancybox-container').waitFor({state:'hidden'});
 const subscription=p.locator('.subscription form');await subscription.locator('input[type=email]').fill('qa-v56@example.invalid');await subscription.locator('[name=f_consent]').check();const signup=await submit(subscription);check('valid newsletter email is stored correctly',signup.email==='qa-v56@example.invalid',signup.email);
 const subRows=snapshot('Subdivision'),bannerRows=snapshot('Message'+classes.life_banner),textRows=snapshot('Message'+classes.life_text),regionRows=snapshot('Message223');
 const section=target.find(x=>x.Hidden_URL==='/podrostkam/neuverennost-v-sebe/');const sid=+section.Subdivision_ID;
 const update=(table,where,values)=>mutate('UPDATE '+table+' SET '+Object.entries(values).map(([k,v])=>'`'+k+'`='+literal(v)).join(',')+',LastUpdated=LastUpdated WHERE '+where);
 const region=regionRows.find(r=>r.main_domain===host&&+r.Checked);check('matching active region exists',!!region);
 update('Subdivision','Subdivision_ID='+sid,{Title:'Помощь [inCity] — [phone]',Description:'Описание [inCity] [phone]',Keywords:'[inCity], [phone]',ncH1:'Заголовок [inCity]',ncSMO_Title:'Соцсети [inCity]',ncSMO_Description:'Позвоните [phone]'});
 update('Message'+classes.life_banner,'Subdivision_ID='+sid,{name:'Баннер [inCity]',text:'Консультация [inCity], телефон [phone]'});
 update('Message'+classes.life_text,'Subdivision_ID='+sid,{heading:'Текст [inCity]',text:'<p>Позвоните [phone]. Мы работаем [inCity].</p>'});
 for(const [city,phone]of [[region.inCity,region.phone],['в Тестовом городе','+7(900)111-22-33']]){
  update('Message223','Message_ID='+region.Message_ID,{inCity:city,phone});await go(section.Hidden_URL);
  const actual=await p.evaluate(()=>({title:document.title,h1:document.querySelector('.contacts-page h1').textContent.trim(),description:document.querySelector('meta[name=description]')?.content,keywords:document.querySelector('meta[name=keywords]')?.content,og:document.querySelector('meta[property="og:title"]')?.content,banner:document.querySelector('.contacts-page .hero__description').textContent.trim(),text:document.querySelector('.text-block').textContent}));
  check('regional shortcodes in SEO title H1 description keywords and SMM',actual.title==='Помощь '+city+' — '+phone&&actual.h1==='Заголовок '+city&&actual.description==='Описание '+city+' '+phone&&actual.keywords===city+', '+phone&&actual.og==='Соцсети '+city,actual);
  check('regional shortcodes in banner and text component',actual.banner.includes(city)&&actual.banner.includes(phone)&&actual.text.includes(city)&&actual.text.includes(phone),actual);
 }
 update('Subdivision','Subdivision_ID='+sid,{ncH1:''});await go(section.Hidden_URL);check('banner heading supports shortcodes without SEO H1',await p.locator('.contacts-page h1').innerText()==='Баннер в Тестовом городе');
 check('site remains closed',+query('SELECT Checked FROM Catalogue WHERE Catalogue_ID=1')[0].Checked===0);
} catch(e){errors.push({message:e.message,stack:e.stack});console.error(e.stack);process.exitCode=1;}
finally{for(const fn of restore.reverse()){try{fn();}catch(e){errors.push({restore:e.message});process.exitCode=1;}}await browser.close();fs.writeFileSync(out+'/results.json',JSON.stringify({checks,pages,errors},null,2));}

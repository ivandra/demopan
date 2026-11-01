import fs from 'node:fs';
import path from 'node:path';
import {createRequire} from 'node:module';
import {execFileSync} from 'node:child_process';
const require=createRequire(import.meta.url);
const {chromium}=require(process.env.CODEX_PRIMARY_RUNTIME_NODE_MODULES+'/playwright');
const {default:binary}=await import(process.env.NETCAT_CHROMIUM_MODULE);
const address=process.env.NETCAT_REPRO_ADDRESS;
const hostname=process.env.NETCAT_TEST_HOST;
const container=process.env.NETCAT_REPRO_WEB_CONTAINER||'demopan-repro-web';
const output=process.env.NETCAT_QA_OUTPUT;
if(!/^172\.(1[6-9]|2[0-9]|3[01])\.\d+\.\d+$/.test(address)||!hostname||!output?.startsWith('/tmp/demopan-')||!['demopan-repro-web','demopan-repro-web83'].includes(container))throw new Error('Isolated test settings required');
const observed=execFileSync('docker',['inspect','--format','{{range .NetworkSettings.Networks}}{{.IPAddress}}{{end}}',container],{encoding:'utf8'}).trim();
if(observed!==address)throw new Error('Unexpected test container address');
const base='http://'+hostname+':8080';
fs.mkdirSync(output,{recursive:true});fs.mkdirSync(output+'/screens',{recursive:true});
const browser=await chromium.launch({executablePath:await binary.executablePath(),args:[...binary.args,'--host-resolver-rules=MAP '+hostname+' '+address,'--proxy-server=direct://']});
const checks=[],errors=[];
function check(name,ok,detail=''){checks.push({name,ok:!!ok,detail});console.log((ok?'PASS ':'FAIL ')+name);if(!ok)throw new Error(name+' '+JSON.stringify(detail));}
function php(code){return execFileSync('docker',['exec','-e','HTTP_HOST='+hostname,container,'php','-r',code],{encoding:'utf8'});}
function boot(code){return php('$_SERVER["DOCUMENT_ROOT"]="/site";putenv("DOCUMENT_ROOT=/site");$_SERVER["HTTP_HOST"]=getenv("HTTP_HOST");$_SERVER["REQUEST_URI"]="/";$_SERVER["REQUEST_METHOD"]="GET";$_SERVER["REMOTE_ADDR"]="127.0.0.1";$_SERVER["HTTP_USER_AGENT"]="QA";require "/site/vars.inc.php";require $ROOT_FOLDER."connect_io.php";$nc_core=nc_core();$db=$nc_core->db;$catalogue=1;'+code);}
const context=await browser.newContext({viewport:{width:1440,height:1000}});
await context.route('**/*',r=>{const u=new URL(r.request().url());return u.hostname===hostname&&u.port==='8080'?r.continue():r.abort();});
const page=await context.newPage();page.on('pageerror',e=>errors.push(e.message));
const modes=[];
async function updater(mode){
 await page.goto(base+'/zhp-update.php');
 const content=await page.content();const token=content.match(/const token="([0-9a-f]+)"/)?.[1];if(!token)throw new Error('Updater dashboard unavailable');
 for(let n=0;n<1000;n++){
  const result=await page.evaluate(async({mode,token})=>{const r=await fetch('/zhp-update.php?ajax=1',{method:'POST',body:new URLSearchParams({mode,token})});return{status:r.status,...await r.json()};},{mode,token});
  if(!result.ok)throw new Error(JSON.stringify(result));
  if(result.done){modes.push({mode,requests:n+1});return;}
 }
 throw new Error('Update did not finish');
}
async function consent(){const b=page.locator('[data-project-cookie]');if(await b.isVisible())await b.locator('button').click();}
const pages=[['home','/'],['about','/o-nas/'],['blog','/blog/'],['contacts','/kontakty/'],['specialists','/o-nas/spetsialisty/'],['gallery','/o-nas/foto/'],['reviews','/o-nas/otzyvy/'],['faq','/o-nas/voprosy-i-otvety/'],['certificates','/o-nas/sertifikaty/'],['service','/podrostkam/trevoga-i-perezhivaniya/'],['consent','/policy/'],['privacy','/politika-konfidentsialnosti/'],['404','/404/']];
try {
 let response=await page.goto(base+'/zhp-update.php');check('updater denies anonymous access',response.status()===403);
 response=await page.goto(base+'/netcat_files/life_teen_v52/state.php');check('private update state is not served',response.status()===404);
 await page.goto(base+'/_qa_auth.php');
 if(process.env.NETCAT_TEST_APPLY==='1'){await updater('check');await updater('update');check('browser update completed in short requests',modes.find(m=>m.mode==='update').requests>1);await updater('update');}
 await updater('verify');check('update verification succeeds',true);
 await page.goto(base+'/zhp-update.php');
 const bad=await page.evaluate(()=>fetch('/zhp-update.php?ajax=1',{method:'POST',body:new URLSearchParams({mode:'update',token:'invalid'})}).then(r=>r.status));check('updater rejects invalid CSRF',bad===403);
 await page.goto(base+'/');check('cookie banner initially visible',await page.locator('[data-project-cookie]').isVisible());await consent();await page.reload();check('cookie consent persists',!await page.locator('[data-project-cookie]').isVisible());
 for(const width of [1440,820,390]){
  await page.setViewportSize({width,height:1000});
  for(const[name,url]of pages){
   const r=await page.goto(base+url);await page.waitForTimeout(70);await consent();
   check(name+' '+width+' HTTP',r.status()===(name==='404'?404:200));
   const overflow=await page.evaluate(()=>document.documentElement.scrollWidth-innerWidth);check(name+' '+width+' no horizontal overflow',overflow<=1,overflow);
   const broken=await page.locator('img').evaluateAll(ns=>ns.filter(n=>{const r=n.getBoundingClientRect();return r.width&&r.height&&n.complete&&n.naturalWidth===0;}).map(n=>n.getAttribute('src')));check(name+' '+width+' images load',broken.length===0,broken.slice(0,4));
   if(['home','about','blog','contacts','specialists','gallery','faq','404'].includes(name))await page.screenshot({path:output+'/screens/'+name+'-'+width+'.png',fullPage:true});
   if(name!=='home')check(name+' '+width+' breadcrumbs',await page.locator('.project-breadcrumbs [aria-current=page]').count()===1);
   if(name==='home'){
    check('complete top navigation '+width,await page.locator('.header__list > li > a').count()===6);
    const heights=await page.locator('.specialists__item-img').evaluateAll(ns=>ns.map(n=>n.getBoundingClientRect().height));check('uniform home specialist photos '+width,Math.max(...heights)-Math.min(...heights)<1,heights);
    const email=page.locator('.subscription__form input[type=email]').first();check('styled email input '+width,(await email.boundingBox()).height>=47);
    check('footer email link '+width,await page.locator('.footer__col--contacts a[href^="mailto:"]').count()===1);check('footer phone link '+width,await page.locator('.footer__col--contacts a[href^="tel:"]').count()===1);
   }
   if(name==='about'){
    const slider=page.locator('.team__slider');check('all team members retained '+width,await slider.locator('.team__item').count()===5);
    const before=await slider.evaluate(n=>n.swiper.activeIndex);await slider.locator('[data-team-next]').click();await page.waitForTimeout(320);check('team carousel advances '+width,await slider.evaluate(n=>n.swiper.activeIndex)>before);
   }
   if(name==='blog'){
    const bounds=await page.locator('.articles-block__card').evaluate(n=>{const a=n.getBoundingClientRect(),b=n.querySelector('.articles-block__card-image').getBoundingClientRect(),t=n.querySelector('.blog__item-title').getBoundingClientRect();return{inside:b.right<=a.right+1&&b.left>=a.left-1&&b.bottom<=a.bottom+1,noOverlap:b.left>=t.right-1||b.top>=t.bottom-1,photoWidth:b.width};});check('featured banner bounds '+width,bounds.inside&&bounds.noOverlap&&bounds.photoWidth>140,bounds);
   }
   if(name==='gallery'){
    check('gallery has all photos '+width,await page.locator('.gallery__item').count()===12);
    const fitting=await page.locator('.gallery__item').evaluateAll(ns=>ns.every(n=>{const a=n.getBoundingClientRect(),b=n.querySelector('img').getBoundingClientRect();return Math.abs(a.width-b.width)<1&&Math.abs(a.height-b.height)<1;}));check('gallery image fits tile '+width,fitting);
    await page.locator('[data-filter=events]').click();check('gallery filter '+width,await page.locator('.gallery__item:visible').count()>0&&await page.locator('.gallery__item:visible').count()<12);
    await page.locator('[data-filter=all]').click();check('gallery reset '+width,await page.locator('.gallery__item:visible').count()===12);
   }
   if(name==='faq'||name==='contacts'){
    check(name+' two FAQ groups '+width,JSON.stringify(await page.locator('.faq-block__col').evaluateAll(ns=>ns.map(n=>n.querySelectorAll('.faq-block__item').length)))==='[3,3]');
    const trigger=page.locator('.faq-block__trigger').first();await trigger.click();check(name+' accordion opens '+width,await trigger.getAttribute('aria-expanded')==='true');const answer=page.locator('.faq-block__answer').first();check(name+' answer stays within column '+width,(await answer.boundingBox()).width<=(await page.locator('.faq-block__col').first().boundingBox()).width);
    await trigger.click();check(name+' accordion closes '+width,await trigger.getAttribute('aria-expanded')==='false');
   }
   if(name==='specialists'){
    check('native classifications render as tokens '+width,await page.locator('[data-specialist-category]').first().getAttribute('data-specialist-category')==='psychologist psychotherapist');
    await page.locator('[data-specialist-filter=family]').click();check('specialist list filter '+width,await page.locator('[data-specialist-category]:visible').count()===1);
    await page.locator('[data-specialist-filter=other]').click();check('other specialties stay accessible '+width,await page.locator('[data-specialist-category]:visible').count()===2);
    check('specialist certificates use supplied layout '+width,await page.locator('.certificates-block').count()===1);
   }
  }
 }
 await page.setViewportSize({width:1440,height:1000});
 await page.goto(base+'/blog/');await consent();
 const articleUrl=await page.locator('.articles__list a[href$=".html"]').first().getAttribute('href');
 const start=await page.evaluate(()=>performance.getEntriesByType('navigation')[0].timeOrigin||performance.timeOrigin);
 const enabled=page.locator('[data-article-category]:not([disabled])');
 const category=await enabled.nth(1).getAttribute('data-article-category');await enabled.nth(1).click();await page.waitForFunction(c=>new URL(location.href).searchParams.get('category')===c,category);check('blog filter uses same document',await page.evaluate(()=>performance.getEntriesByType('navigation')[0].timeOrigin||performance.timeOrigin)===start);check('blog category URL updates',new URL(page.url()).searchParams.get('category')===category);
 await page.goBack();await page.waitForFunction(()=>!new URL(location.href).searchParams.has('category'));check('blog Back restores all articles',true);
 const disabled=await page.locator('[data-article-category][disabled]').count();check('empty article categories disabled',disabled>0);
 await page.goto(base+articleUrl);check('article breadcrumbs include object',await page.locator('.project-breadcrumbs li').count()>=3);
 await page.goto(base+'/o-nas/spetsialisty/');const specialistUrl=await page.locator('.specialists-block__card-btn').first().getAttribute('href');await page.goto(base+specialistUrl);check('specialist breadcrumbs include object',await page.locator('.project-breadcrumbs li').count()>=4);
 await page.goto(base+'/o-nas/');await page.locator('.header__button').click();check('shared popup exists on internal page',await page.locator('.fancybox-container #popup').isVisible());await page.locator('.fancybox-container [data-fancybox-close]:visible').first().click();
 await page.goto(base+'/');await page.locator('.video-card__video a').click();await page.waitForSelector('.fancybox-container video');const video=page.locator('.fancybox-container video');check('video has no layout poster',await video.getAttribute('poster')===null);check('video has playback controls',await video.evaluate(n=>n.controls));await page.locator('.fancybox-container [data-fancybox-close]:visible').first().click();
 await page.goto(base+'/netcat/message.php?inside_admin=1&catalogue=1&sub=57&cc=246&message=1&isModal=1');check('native specialty checkboxes',await page.locator('input[type=checkbox][name^="f_category["]').count()>=6);check('native multiple-document upload',await page.locator('input[type=file][name="f_documents_file[]"]').getAttribute('multiple')!==null);
 fs.writeFileSync(output+'/native-edit.html',await page.content());
 check('no browser JavaScript errors',errors.length===0,errors);
} catch(error){console.error(error.stack);checks.push({name:'exception',ok:false,detail:error.message});process.exitCode=1;try{fs.writeFileSync(output+'/failure.html',await page.content());await page.screenshot({path:output+'/failure.png',fullPage:true});}catch{}}
finally {fs.writeFileSync(output+'/browser-results.json',JSON.stringify({checks,modes,errors},null,2));await browser.close();}

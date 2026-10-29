import fs from 'node:fs';
import {createRequire} from 'node:module';
import {execFileSync} from 'node:child_process';
const require=createRequire(import.meta.url);
const {chromium}=require(process.env.CODEX_PRIMARY_RUNTIME_NODE_MODULES+'/playwright');
const {default:binary}=await import(process.env.NETCAT_CHROMIUM_MODULE);
const root='netcat-integration-v5';
const manifest=JSON.parse(fs.readFileSync(root+'/installer/manifest.json'));
const browser=await chromium.launch({executablePath:await binary.executablePath(),args:[...binary.args,'--host-resolver-rules=MAP *.lifepodrostka.ru 127.0.0.1','--proxy-server=direct://']});
const context=await browser.newContext();const page=await context.newPage();
const base='http://dev.lifepodrostka.ru:8080';const checks=[];const errors=[];let token;
page.on('pageerror',error=>errors.push(error.message));
function check(name,passed,detail=''){checks.push({name,ok:!!passed,detail});console.log((passed?'PASS ':'FAIL ')+name);if(!passed)throw new Error(name+' '+JSON.stringify(detail));}
async function install(mode){
    for(let count=0;count<8000;count++){
        const result=await page.evaluate(async({mode,token})=>{
            const response=await fetch('/zhp-install.php?ajax=1',{method:'POST',body:new URLSearchParams({mode,token})});
            return {status:response.status,...await response.json()};
        },{mode,token});
        if(!result.ok)throw new Error(JSON.stringify(result));
        if(result.done)return;
    }
    throw new Error('Installer did not finish');
}
async function auth(){
    await page.goto(base+'/_life_auth.php');await page.goto(base+'/zhp-install.php');
    token=await page.locator('#zhp-installer-config').evaluate(node=>JSON.parse(node.textContent).token);
}
async function state(){return page.evaluate(()=>fetch('/_life_state.php').then(response=>response.json()));}
async function edit(collection,id,changes){
    await page.goto(base+`/netcat/message.php?inside_admin=1&catalogue=1&sub=${collection.sub_id}&cc=${collection.cc_id}&message=${id}&isModal=1`);
    const control=page.locator('[name='+Object.keys(changes)[0]+']').first();
    const old=await control.evaluate((node,changes)=>Object.fromEntries(Object.keys(changes).map(name=>[name,node.form.elements.namedItem(name)?.value])),changes);
    const result=await control.evaluate(async(node,changes)=>{
        const data=new FormData(node.form);data.set('partial','1');
        for(const [key,value]of Object.entries(changes))data.set(key,value);
        const response=await fetch(node.form.action,{method:'POST',body:data});return response.status;
    },changes);
    check('native edit '+Object.keys(changes).join(','),result===200);return old;
}
try {
    let response=await page.goto(base+'/zhp-install.php');check('installer requires administrator',response.status()===403);
    await auth();await install('check');await install('install');await install('install');await install('activate');await install('verify');

    const s=await state();check('v5 state',s.version===5);
    const inspect=await page.evaluate(()=>fetch('/_life_state.php?inspect=1').then(response=>response.json()));
    check('no old templates in CMS',inspect.all_templates.every(t=>!/^\/(medelta|lifepodrostka|zhizn_podrostka)\//.test(t.File_Path)));
    check('no old components in CMS',inspect.old_classes===0);
    const expectedSections=new Set(Object.values(s.pages).map(Number));
    check('no old or empty extra sections',inspect.all_sections.length===expectedSections.size&&inspect.all_sections.every(row=>expectedSections.has(Number(row.Subdivision_ID))));
    check('no draft or archive section slugs',inspect.all_sections.every(row=>!/^life-(draft|old)-/.test(row.EnglishName)));
    check('only supplied project layouts',inspect.all_templates.filter(t=>t.File_Path.startsWith('/life_teen/')).length===8);
    const video=inspect.components.life_video;
    check('video has exactly four editorial fields',video.fields.length===4&&['name','text','link','img'].every(name=>video.fields.includes(name)));
    check('video named from source caption',video.name==='ПОЗНАКОМЬТЕСЬ С НАМИ БЛИЖЕ');
    check('video prefix opens source containers',video.FormPrefix.includes('<section class="video-card"')&&video.FormPrefix.includes('    <div class="wrap">')&&!video.FormPrefix.includes('video-card__content'));
    check('video object only contains object content',video.RecordTemplate.includes('video-card__content')&&!video.RecordTemplate.includes('<section'));
    check('video suffix closes containers',video.FormSuffix.includes('</div>')&&video.FormSuffix.includes('</section>'));
    check('video code has readable indentation',video.RecordTemplate.includes('\n        <div class="video-card__content">'));
    for(const key of ['life_parent_group','life_first_step','life_socials','life_subscription','life_calculator']){
        const component=inspect.components[key];
        check('no object filling needed '+key,component.fields.length===0&&!component.RecordTemplate.trim()&&!component.FormSuffix.trim()&&component.FormPrefix.includes('<section'));
    }
    check('parent group uses region shortcut',inspect.components.life_parent_group.FormPrefix.includes("shortcode('[max]')"));
    check('existing regions retained',s.collections.regions.class_id===223&&s.collections.regions.cc_id===57);
    await page.goto(base+`/netcat/admin/class/index.php?phase=4&ClassID=${video.id}&fs=1`);
    check('native component editor shows formatted prefix',(await page.locator('[name=FormPrefix]').inputValue()).includes('\n    <div class="wrap">'));
    check('native component editor shows object separately',(await page.locator('[name=RecordTemplate]').inputValue()).includes('video-card__content'));
    await page.goto(base+`/netcat/admin/template/index.php?phase=4&TemplateID=${s.template_id}&fs=1`);
    const header = await page.locator('[name=Header]').inputValue();
    const navigation=await page.locator('[name=Settings]').inputValue();
    check('explicit navigation arrays as in source CMS',navigation.includes('$mobile_menu[0]')&&navigation.includes('$h_menu[2]')&&!navigation.includes('foreach')&&!navigation.includes('/*')&&!navigation.includes('//'));
    check('native layout editor shows formatted source',header.startsWith('<!DOCTYPE html>\n')&&header.includes('\n    <'));
    check('native layout editor has no added wrapper or comments',!header.includes('zhp-layout')&&!header.includes('<!--'));
    await page.goto(base+`/netcat/admin/template/index.php?phase=4&TemplateID=${s.templates.home}&fs=1`);
    check('child layout inherits header and footer',(await page.locator('[name=Header]').inputValue()).trim()==='%Header'&&(await page.locator('[name=Footer]').inputValue()).trim()==='%Footer');
    for(const url of ['/','/o-nas/','/blog/','/kontakty/','/o-nas/spetsialisty/','/o-nas/foto/','/podrostkam/trevoga-i-perezhivaniya/']){
        response=await page.goto(base+url);check('page '+url,response.status()===200);
        check('no added layout wrapper '+url,await page.locator('main,.zhp-layout,[data-zhp-layout]').count()===0);
        check('shared source footer '+url,await page.locator('footer.footer').count()===1);
    }
    await page.goto(base+'/');
    check('one video block wrapper',await page.locator('section.video-card > .wrap').count()===1);
    check('fieldless parent group is visible',await page.locator('section.parents-group').count()===1);
    check('typographic text from source retained',await page.locator('section.text-block').count()===1);
    check('source jQuery UI sliders retained',await page.locator('#slider-range-max.ui-slider').count()===1);
    check('footer year is current',(await page.locator('.footer__copy').innerText()).includes(String(new Date().getFullYear())));
    const articleLink=await page.locator('.blog__item h3 a').first().getAttribute('href');
    response=await page.goto(new URL(articleLink,base).href);check('article full view from home',response.status()===200&&await page.locator('.article-page').count()===1);
    check('source related articles sidebar',await page.locator('.article-page__related-item').count()===2);
    check('full article uses common source subscription',await page.locator('section.subscription').count()===1);
    const specialists=s.collections.specialists;const first=s.seeded[specialists.cc_id+':0'];
    const previous=await edit(specialists,first,{f_fio:'Проверка общей записи',f_position:'Проверка должности'});
    await page.goto(base+'/');check('native specialist edit updates carousel',(await page.locator('.specialists__item h3').allTextContents()).some(text=>text.trim()==='Проверка общей записи'));
    await page.goto(base+'/o-nas/spetsialisty/');const full=await page.locator('.specialists-block__card-btn').first().getAttribute('href');
    await page.goto(new URL(full,base).href);check('native specialist edit updates full view',(await page.locator('h1').innerText())==='Проверка общей записи');
    check('source other specialists use same collection',await page.locator('.specialist-page__other-card').count()>0&&!(await page.locator('.specialist-page__other').innerText()).includes('Проверка общей записи'));
    check('full specialist uses common source subscription',await page.locator('section.subscription').count()===1);
    await edit(specialists,first,previous);
    const articles=s.collections.articles;const articleId=s.seeded[articles.cc_id+':0'];
    const oldDate=await edit(articles,articleId,{f_date_day:'17',f_date_month:'11',f_date_year:'2027'});
    await page.goto(base+'/blog/');check('article date is dynamic',(await page.locator('.articles-block .blog__item-meta').innerText()).includes('17.11.2027'));
    await edit(articles,articleId,oldDate);
    const oldPhone=await edit(s.collections.regions,1,{f_phone:'+7 (999) 222-33-44'});
    await page.goto(base+'/kontakty/');check('original region phone shown',(await page.locator('.contacts-page').innerText()).includes('+7 (999) 222-33-44'));
    await edit(s.collections.regions,1,oldPhone);
    await page.goto(base+'/_life_state.php?seed_region=1');
    await page.goto('http://spb.lifepodrostka.ru:8080/kontakty/');
    check('regional data follows current domain',(await page.locator('.contacts-page').innerText()).includes('+7 (812) 000-00-00'));
    await page.goto(base+'/_life_state.php?settings=set');
    await page.goto(new URL(articleLink,base).href);
    check('full article inherits section template settings',(await page.locator('.article-page__aside-title').innerText()).trim()==='Поддержка из настроек макета');
    await page.goto(base+'/_life_state.php?settings=reset');
    await context.clearCookies();await page.goto(base+'/kontakty/');
    const form=page.locator('form[data-zhp-form=contact]');
    await form.locator('[name=f_name]').fill('Проверка формы');await form.locator('[name=f_contact]').fill('test@example.test');
    await form.locator('[name=f_consent]').check();await form.locator('[type=submit]').click();
    await page.waitForFunction(()=>document.querySelector('[data-form-status]').textContent.includes('сохранена'));
    check('contact form saves through Netcat',(await form.locator('[data-form-status]').innerText()).includes('сохранена'));
    await page.goto(base+'/');const subscription=page.locator('form[data-zhp-form=subscribe]').first();
    await subscription.locator('[name=f_email]').fill('subscribe@example.test');await subscription.locator('[name=f_consent]').check();await subscription.locator('[type=submit]').click();
    await page.waitForFunction(()=>document.querySelector('.subscription [data-form-status]').textContent.includes('сохранена'));
    check('source subscription form works',true);
    for(let index=0;index<5;index++){await page.locator('.quiz__step.active label').first().click();await page.locator('.quiz__btn--next').click();}
    await page.locator('.quiz [name=f_name]').fill('Тест квиза');await page.locator('.quiz [name=f_phone]').fill('+79990000000');await page.locator('.quiz [name=f_email]').fill('quiz@example.test');await page.locator('.quiz__btn--submit').click();
    await page.waitForFunction(()=>document.querySelector('.quiz [data-form-status]').textContent.includes('сохранена'));
    check('quiz works in source markup',true);
    await page.locator('.header .header__button').click();
    const popup=page.locator('.fancybox-container form#popup');
    await popup.locator('[name=f_name]').fill('Проверка исходного окна');
    await popup.locator('[name=f_phone]').fill('+79990000001');
    await popup.locator('label[for=consult-policy]').click();
    await popup.locator('[type=submit]').click();
    await page.waitForFunction(()=>document.querySelector('#popup [data-form-status]').textContent.includes('сохранена'));
    check('provided popup opens and saves through Netcat',true);
    await page.locator('.fancybox-container [data-fancybox-close]:visible').first().click();
    await auth();await install('rollback');
    console.log(execFileSync('python3',[root+'/tests/verify-restore.py'],{encoding:'utf8'}));
    await auth();await install('install');await install('activate');await install('verify');check('full rollback and clean reinstall',true);
    check('no public JavaScript errors',errors.length===0,errors);
} catch(error) {console.error(error.stack);checks.push({name:'exception',ok:false,detail:error.stack});process.exitCode=1;fs.writeFileSync(root+'/tests/failure.html',await page.content());}
finally {fs.writeFileSync(root+'/tests/browser-results-'+(process.env.LIFE_SCENARIO||'original')+'.json',JSON.stringify(checks,null,2));await browser.close();}

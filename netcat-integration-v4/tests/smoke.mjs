import fs from 'node:fs';
import {createRequire} from 'node:module';
const require=createRequire(import.meta.url);
const {chromium}=require(process.env.CODEX_PRIMARY_RUNTIME_NODE_MODULES ? process.env.CODEX_PRIMARY_RUNTIME_NODE_MODULES+'/playwright' : 'playwright');
const {default:binary}=await import(process.env.NETCAT_CHROMIUM_MODULE);
const browser=await chromium.launch({executablePath:await binary.executablePath(),args:[
  ...binary.args,'--host-resolver-rules=MAP *.lifepodrostka.ru 127.0.0.1','--proxy-server=direct://'
]});
const context=await browser.newContext();
const page=await context.newPage();
const base='http://dev.lifepodrostka.ru:8080';
const sourceFolder='netcat-integration-v4/build/reference-layout';
const assetFolder='netcat-integration-v4/files/netcat_template/template/zhizn_podrostka';
await page.route(base+'/__source/**',async route=>{
  const name=new URL(route.request().url()).pathname.replace('/__source/','');
  let file=(name==='style.css'||name.startsWith('fonts/')||name.startsWith('img/'))?assetFolder+'/'+name:sourceFolder+'/'+name;
  if(!fs.existsSync(file))file=assetFolder+'/'+name;
  let body=fs.readFileSync(file);
  if(name.endsWith('.html')) {
    let html=body.toString();
    const local='/netcat_template/template/zhizn_podrostka/';
    html=html.replace(/https:\/\/code.jquery.com\/jquery-[^"']+\.js/g,local+'js/jquery.min.js')
      .replace(/https:\/\/code.jquery.com\/ui\/[^"']+jquery-ui\.js/g,local+'js/jquery-ui.min.js')
      .replace(/https:\/\/code.jquery.com\/ui\/[^"']+jquery-ui\.css/g,local+'css/jquery-ui.min.css')
      .replace(/https:\/\/cdn.jsdelivr.net\/npm\/swiper@8\/swiper-bundle.min\.(js|css)/g,(_,ext)=>local+ext+'/swiper-bundle.min.'+ext)
      .replace(/https:\/\/cdn.jsdelivr.net\/gh\/fancyapps\/fancybox@3.5.7\/dist\/jquery.fancybox.min\.(js|css)/g,(_,ext)=>local+ext+'/jquery.fancybox.min.'+ext);
    body=Buffer.from(html);
  }
  const type=name.endsWith('.html')?'text/html':name.endsWith('.css')?'text/css':name.endsWith('.js')?'application/javascript':name.endsWith('.svg')?'image/svg+xml':undefined;
  await route.fulfill({body,contentType:type});
});
const manifest=JSON.parse(fs.readFileSync('netcat-integration-v4/installer/manifest.json'));
const result={pages:[],assets:[],details:[],checks:[],errors:[],sourceOverflow:[],sourceErrors:[]};
page.on('pageerror',error=>{
  if(page.url().includes('/__source/'))result.sourceErrors.push({url:page.url(),message:error.message});
  else result.errors.push(error.message);
});
const assets=new Set();
function check(name,passed){result.checks.push({name,passed});if(!passed)throw new Error(name);}
try {
  for(const definition of manifest.pages) {
    if(['/regions/','/zayavki/','/nastroiki-proekta/'].includes(definition.url))continue;
    const response=await page.goto(base+definition.url);
    const expected=definition.url==='/404/'?404:200;
    check('HTTP '+definition.url,response.status()===expected);
    const expectedHeadings=definition.url==='/'?2:definition.url==='/o-nas/'?3:definition.url==='/blog/'?0:definition.blocks.some(block=>block.class==='zhp_banner_service')?2:1;
    check('heading structure '+definition.url,await page.locator('h1').count()===expectedHeadings);
    for(const src of await page.locator('img[src],script[src],link[rel=stylesheet]').evaluateAll(nodes=>
      nodes.map(n=>n.getAttribute('src')||n.getAttribute('href')))) {
      if(src && src.startsWith('/'))assets.add(src);
    }
    result.pages.push({url:definition.url,status:response.status(),layout:definition.layout,
      placeholder:(await page.locator('body').innerText()).includes('Материал готовится')});
  }
  for(const path of assets) {
    // Browser host-resolver rules do not apply to Playwright's Node HTTP client.
    const response=await context.request.get('http://127.0.0.1:8080'+path,
      {headers:{Host:'dev.lifepodrostka.ru:8080'}});
    result.assets.push({path,status:response.status()});
    check('asset '+path,response.status()===200);
  }
  await page.goto(base+'/blog/');
  const article=await page.locator('.articles__list h3 a').first().getAttribute('href');
  await page.goto(new URL(article,base).href);
  result.details.push({type:'article',path:new URL(page.url()).pathname});
  check('article body',(await page.locator('.article-page__left').innerText()).length>300);
  check('article source subscription',await page.locator('section.subscription').count()===1);
  await page.goto(base+'/o-nas/spetsialisty/');
  const specialist=await page.locator('.specialists-block__card-btn').first().getAttribute('href');
  await page.goto(new URL(specialist,base).href);
  result.details.push({type:'specialist',path:new URL(page.url()).pathname});
  check('specialist bio',(await page.locator('.specialist-page').innerText()).length>300);
  check('specialist source subscription',await page.locator('section.subscription').count()===1);

  await page.goto(base+'/o-nas/spetsialisty/');
  await page.locator('[data-specialist-filter]').nth(1).click();
  check('specialist filter hides other categories',await page.locator('[data-specialist-category]').evaluateAll(
    cards=>cards.filter(c=>!c.dataset.specialistCategory.split(/\s+/).includes('psychologist')).every(c=>getComputedStyle(c).display==='none')));
  await page.setViewportSize({width:390,height:844});
  await page.goto(base+'/');
  await page.locator('.header__burger').click();
  check('mobile menu opens',await page.locator('.mobile-menu').evaluate(e=>e.classList.contains('mobile-menu--active')));
  const submenu=page.locator('.mobile-menu li > a').filter({hasText:'Подросткам'}).first();
  await submenu.click();
  check('mobile submenu opens',await submenu.getAttribute('aria-expanded')==='true');
  await page.locator('.mobile-menu__close').click();
  check('mobile menu closes',!(await page.locator('.mobile-menu').evaluate(e=>e.classList.contains('mobile-menu--active'))));
  await page.setViewportSize({width:1440,height:900});
  check('menu anchors have targets',await page.locator('#video').count()===1&&await page.locator('#quiz').count()===1);
  const oldTotal=await page.locator('.calc__result-value').innerText();
  await page.locator('#slider-range-max .ui-slider-handle').focus();
  await page.keyboard.press('ArrowRight');
  await page.waitForTimeout(50);
  check('source calculator reacts to slider',await page.locator('.calc__result-value').innerText()!==oldTotal);
  const layouts=['/','/o-nas/','/blog/','/kontakty/','/o-nas/spetsialisty/','/o-nas/foto/',
    '/podrostkam/trevoga-i-perezhivaniya/',...result.details.map(detail=>detail.path)];
  fs.mkdirSync('netcat-integration-v4/internal-qa',{recursive:true});
  for(const width of [360,768,1440]) {
    await page.setViewportSize({width,height:900});
    for(const [index,url] of layouts.entries()) {
      await page.goto(base+url);await page.waitForTimeout(50);
      const actual=await page.evaluate(()=>document.documentElement.scrollWidth);
      if(actual>width+1) {
        const sourceName=index===8?'specialist':index===7?'article':['index','about','blog','contacts','specialists','gallery','service'][index];
        await page.goto(base+'/__source/'+sourceName+'.html');await page.evaluate(()=>document.fonts.ready);
        const original=await page.evaluate(()=>document.documentElement.scrollWidth);
        result.sourceOverflow.push({url,viewport:width,integrated:actual,original});
        check('responsive matches source '+width+' '+url,actual<=original+1);
        await page.goto(base+url);
      } else check('responsive '+width+' '+url,true);
      if(width!==768)await page.screenshot({path:`netcat-integration-v4/internal-qa/layout-${index}-${width}.png`});
    }
  }
  check('no public JavaScript errors',result.errors.length===0);
  console.log('PASS Smoke: '+result.pages.length+' pages, '+result.assets.length+' assets, detail pages and interactions');
} catch(error) {
  result.errors.push(error.stack);process.exitCode=1;console.error(error.stack);
} finally {
  fs.writeFileSync('netcat-integration-v4/tests/smoke-results.json',JSON.stringify(result,null,2));
  await browser.close();
}

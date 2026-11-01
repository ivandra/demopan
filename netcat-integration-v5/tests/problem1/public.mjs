import fs from 'node:fs';
import {createRequire} from 'node:module';
import {execFileSync} from 'node:child_process';
const require=createRequire(import.meta.url),{chromium}=require(process.env.CODEX_PRIMARY_RUNTIME_NODE_MODULES+'/playwright');
const{default:binary}=await import(process.env.NETCAT_CHROMIUM_MODULE);
const container=process.env.NETCAT_REPRO_WEB_CONTAINER||'demopan-repro-web',address=process.env.NETCAT_REPRO_ADDRESS,host=process.env.NETCAT_TEST_HOST,output=process.env.NETCAT_QA_OUTPUT;
if(!['demopan-repro-web','demopan-repro-web83'].includes(container)||!/^172\.(1[6-9]|2[0-9]|3[01])\.\d+\.\d+$/.test(address)||!host||!output?.startsWith('/tmp/demopan-'))throw new Error('Isolated test settings required');
if(execFileSync('docker',['inspect','--format','{{range .NetworkSettings.Networks}}{{.IPAddress}}{{end}}',container],{encoding:'utf8'}).trim()!==address)throw new Error('Wrong test container');
function flag(value){return execFileSync('docker',['exec',container,'php','-r','$_SERVER["DOCUMENT_ROOT"]="/site";putenv("DOCUMENT_ROOT=/site");$_SERVER["HTTP_HOST"]="localhost";$_SERVER["REQUEST_METHOD"]="GET";$_SERVER["REQUEST_URI"]="/";require "/site/vars.inc.php";require $ROOT_FOLDER."connect_io.php";$db=nc_core()->db;'+(value===undefined?'echo (int)$db->get_var("SELECT Checked FROM Catalogue WHERE Catalogue_ID=1");':'$db->query("UPDATE Catalogue SET Checked='+(+value)+',LastUpdated=LastUpdated WHERE Catalogue_ID=1");')],{encoding:'utf8'}).trim();}
const previous=Number(flag());if(previous!==0)throw new Error('Expected disabled original site');
const browser=await chromium.launch({executablePath:await binary.executablePath(),args:[...binary.args,'--host-resolver-rules=MAP '+host+' '+address,'--proxy-server=direct://']});
const context=await browser.newContext(),checks=[];context.setDefaultTimeout(15000);await context.route('**/*',r=>{const u=new URL(r.request().url());return u.hostname===host&&u.port==='8080'?r.continue():r.abort();});const page=await context.newPage(),base='http://'+host+':8080';
function check(name,ok){checks.push({name,ok:!!ok});if(!ok)throw new Error(name);console.log('PASS '+name);}
try{
 await page.goto(base+'/');check('guest receives empty page while site disabled',(await page.locator('body').textContent()).trim()==='');
 flag(1);
 const empty=JSON.parse(fs.readFileSync(new URL('../../updates/v5.2/changes.json',import.meta.url))).empty_pages;
 for(const width of [1440,820,390]){
  await page.setViewportSize({width,height:1000});await page.goto(base+'/');check('guest sees published QA copy '+width,await page.locator('.header__list > li').count()===6);const consent=page.locator('[data-project-cookie]');if(await consent.isVisible())await consent.locator('button').click();
  if(width===390){await page.locator('.header__burger').click();check('mobile navigation opens',await page.locator('.mobile-menu').isVisible());await page.locator('.mobile-menu__link--dropdown').first().click();check('mobile submenu expands',await page.locator('.mobile-menu__link--dropdown').first().getAttribute('aria-expanded')==='true');}
  for(const path of empty){const response=await page.goto(base+path);check('empty section accessible '+width+' '+path,response.status()===200&&await page.locator('.project-breadcrumbs').count()===1);check('empty section no overflow '+width+' '+path,await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1));}
 }
} catch(e){console.error(e.stack);process.exitCode=1;}finally{flag(previous);check('site publication flag restored',flag()==='0');fs.mkdirSync(output,{recursive:true});fs.writeFileSync(output+'/public-results.json',JSON.stringify({checks},null,2));await browser.close();}

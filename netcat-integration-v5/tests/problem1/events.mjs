import fs from 'node:fs';
import {createRequire} from 'node:module';
import {execFileSync} from 'node:child_process';
const require=createRequire(import.meta.url),{chromium}=require(process.env.CODEX_PRIMARY_RUNTIME_NODE_MODULES+'/playwright');
const{default:binary}=await import(process.env.NETCAT_CHROMIUM_MODULE);
const container=process.env.NETCAT_REPRO_WEB_CONTAINER||'demopan-repro-web',address=process.env.NETCAT_REPRO_ADDRESS,host=process.env.NETCAT_TEST_HOST,output=process.env.NETCAT_QA_OUTPUT;
if(!['demopan-repro-web','demopan-repro-web83'].includes(container)||!/^172\.(1[6-9]|2[0-9]|3[01])\.\d+\.\d+$/.test(address)||!host||!output?.startsWith('/tmp/demopan-'))throw new Error('Isolated test settings required');
if(execFileSync('docker',['inspect','--format','{{range .NetworkSettings.Networks}}{{.IPAddress}}{{end}}',container],{encoding:'utf8'}).trim()!==address)throw new Error('Wrong test container');
const browser=await chromium.launch({executablePath:await binary.executablePath(),args:[...binary.args,'--host-resolver-rules=MAP '+host+' '+address,'--proxy-server=direct://']});
const context=await browser.newContext();context.setDefaultTimeout(15000);await context.route('**/*',r=>{const u=new URL(r.request().url());return u.hostname===host&&u.port==='8080'?r.continue():r.abort();});const page=await context.newPage(),base='http://'+host+':8080',checks=[];
try{
 await page.goto(base+'/_qa_auth.php');
 for(const[url,selector]of [['/o-nas/voprosy-i-otvety/','.faq-block__trigger'],['/o-nas/foto/','.gallery .specialists-block__filter'],['/o-nas/spetsialisty/','[data-specialist-filter]'],['/blog/','[data-article-category]']]){
  await page.goto(base+url);const consent=page.locator('[data-project-cookie]');if(await consent.isVisible())await consent.locator('button').click();
  await page.evaluate(()=>{window.qaClicks=0;document.addEventListener('click',()=>window.qaClicks++);});await page.locator(selector).first().click();const ok=await page.evaluate(()=>window.qaClicks===1);checks.push({name:'document click remains observable '+url,ok});if(!ok)throw new Error(url);console.log('PASS document click remains observable '+url);
 }
}catch(e){console.error(e.stack);process.exitCode=1;}finally{fs.mkdirSync(output,{recursive:true});fs.writeFileSync(output+'/events-results.json',JSON.stringify({checks},null,2));await browser.close();}

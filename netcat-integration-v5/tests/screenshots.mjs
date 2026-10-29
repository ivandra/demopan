import fs from 'node:fs';import {createRequire} from 'node:module';
const require=createRequire(import.meta.url);const {chromium}=require(process.env.CODEX_PRIMARY_RUNTIME_NODE_MODULES+'/playwright');
const {default:binary}=await import(process.env.NETCAT_CHROMIUM_MODULE);
const browser=await chromium.launch({executablePath:await binary.executablePath(),args:[...binary.args,'--host-resolver-rules=MAP *.lifepodrostka.ru 127.0.0.1','--proxy-server=direct://']});
const page=await browser.newPage({viewport:{width:1280,height:1000}});const base='http://dev.lifepodrostka.ru:8080';
try {
 await page.goto(base+'/_life_auth.php');
 const s=await page.evaluate(()=>fetch('/_life_state.php').then(r=>r.json()));
 const folder='netcat-integration-v5/docs/screenshots';fs.mkdirSync(folder,{recursive:true});
 await page.goto(base+`/netcat/admin/class/index.php?phase=4&ClassID=${s.classes.life_video}&fs=1`);
 await page.screenshot({path:folder+'/video-editor.png'});
 await page.goto(base+`/netcat/admin/template/index.php?phase=4&TemplateID=${s.template_id}&fs=1`);
 await page.screenshot({path:folder+'/layout-editor.png'});
 await page.goto(base+'/netcat/admin/template/index.php?phase=1&fs=1');
 await page.screenshot({path:folder+'/templates-native.png',fullPage:true});
 await page.goto(base+'/netcat/admin/subdivision/index.php?phase=1&CatalogueID=1');
 await page.screenshot({path:folder+'/sections-native.png',fullPage:true});
 console.log('PASS Native Netcat editor and structure screenshots');
}finally{await browser.close();}

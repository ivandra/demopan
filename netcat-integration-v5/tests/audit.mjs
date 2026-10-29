import fs from 'node:fs';import {createRequire} from 'node:module';
const require=createRequire(import.meta.url);const {chromium}=require(process.env.CODEX_PRIMARY_RUNTIME_NODE_MODULES+'/playwright');
const {default:binary}=await import(process.env.NETCAT_CHROMIUM_MODULE);
const browser=await chromium.launch({executablePath:await binary.executablePath(),args:[...binary.args,'--host-resolver-rules=MAP *.lifepodrostka.ru 127.0.0.1','--proxy-server=direct://']});
const page=await browser.newPage();const base='http://dev.lifepodrostka.ru:8080';const checks=[];
function check(name,pass){checks.push({name,passed:!!pass});if(!pass)throw new Error(name);}
try{
 await page.goto(base+'/_life_auth.php');
 const state=await page.evaluate(()=>fetch('/_life_state.php').then(r=>r.json()));
 const audit=await page.evaluate(()=>fetch('/_life_state.php?inspect=1').then(r=>r.json()));
 const manifest=JSON.parse(fs.readFileSync('netcat-integration-v5/installer/manifest.json'));
 check('installed and physically cleaned',state.active&&state.cleaned);
 check('every project template inspected',audit.template_parts.length===8);
 for(const row of audit.template_parts){
  const markup=Object.values(row.parts).join('\n');
  check('no comments or extra wrappers in template '+row.id,row.comments===0&&!/<!--|zhp-layout|data-zhp-layout/.test(markup));
 }
 check('old project templates absent from CMS',audit.all_templates.every(row=>!/^\/(medelta|lifepodrostka|zhizn_podrostka)\//.test(row.File_Path)));
 check('old project components absent from CMS',audit.old_classes===0);
 for(const definition of manifest.pages){
  const section=audit.all_sections.find(row=>Number(row.Subdivision_ID)===Number(state.pages[definition.url]));
  check('section exists '+definition.url,!!section);
  if(definition.draft)check('empty unpublished draft '+definition.url,Number(section.BlockCount)===0&&Number(section.Checked)===0&&Number(section.Read_Access_ID)===3);
  else if(!['/regions/','/zayavki/'].includes(definition.url))check('ready section has components '+definition.url,Number(section.BlockCount)>0);
 }
 check('no extra sections in tree',audit.all_sections.length===manifest.pages.length);
 for(const [key,row] of Object.entries(audit.components)){
  check('component parts have no added wrappers or HTML comments '+key,!/<!--|zhp-layout|data-zhp-layout/.test(row.FormPrefix+row.RecordTemplate+row.FormSuffix));
 }
 console.log('PASS Live CMS audit:',checks.length,'checks; 8 layouts, 18 empty drafts');
}catch(error){process.exitCode=1;checks.push({name:'exception',passed:false,detail:error.stack});console.error(error.stack);}
finally{fs.writeFileSync('netcat-integration-v5/tests/audit-results.json',JSON.stringify(checks,null,2));await browser.close();}

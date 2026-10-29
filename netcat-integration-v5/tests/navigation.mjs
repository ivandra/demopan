import fs from 'node:fs';import {createRequire} from 'node:module';const require=createRequire(import.meta.url);
const {chromium}=require(process.env.CODEX_PRIMARY_RUNTIME_NODE_MODULES+'/playwright');const {default:binary}=await import(process.env.NETCAT_CHROMIUM_MODULE);
const browser=await chromium.launch({executablePath:await binary.executablePath(),args:[...binary.args,'--host-resolver-rules=MAP *.lifepodrostka.ru 127.0.0.1','--proxy-server=direct://']});
const page=await browser.newPage({viewport:{width:390,height:844}});const base='http://dev.lifepodrostka.ru:8080';const checks=[];
function check(name,pass){checks.push({name,passed:!!pass});if(!pass)throw new Error(name);}
try{
 await page.goto(base+'/_life_auth.php');await page.goto(base+'/_life_state.php?nav=set');
 await page.goto(base+'/');await page.locator('.header__burger').click();
 const level1=page.locator('.mobile-menu__link--dropdown').first();await level1.click();
 check('level one source dropdown works',await level1.getAttribute('aria-expanded')==='true');
 const level2=page.locator('.mobile-menu__sublink--dropdown').first();await level2.click();
 check('level two source dropdown works',await level2.getAttribute('aria-expanded')==='true');
 check('third level is visible',await page.locator('.mobile-menu__subsublink').filter({hasText:'Тревожные мысли и страхи'}).isVisible());
 await page.locator('.mobile-menu__close').click();
 await page.locator('.header__burger').click();
 await page.locator('.mobile-menu__contacts > button').click();
 check('source popup is visible at mobile width',await page.locator('.fancybox-container #popup').isVisible());
 check('source popup fits mobile window',await page.locator('.fancybox-container #popup').evaluate(n=>n.getBoundingClientRect().width<=innerWidth));
 await page.screenshot({path:'netcat-integration-v5/docs/screenshots/popup-mobile.png'});
 console.log('PASS Three-level navigation and source popup on mobile');
}catch(error){process.exitCode=1;checks.push({name:'exception',passed:false,detail:error.stack});console.error(error.stack);}
finally{
 await page.goto(base+'/_life_state.php?nav=reset');
 fs.writeFileSync('netcat-integration-v5/tests/navigation-results.json',JSON.stringify(checks,null,2));await browser.close();
}

import {createRequire} from 'node:module';
import fs from 'node:fs';
const require=createRequire(import.meta.url);
const {chromium}=require(process.env.PLAYWRIGHT_MODULE_PATH||'playwright');
const base=process.env.TEST_BASE_URL||'http://127.0.0.1:8098';
const browser=await chromium.launch({headless:true,executablePath:process.env.CHROME_PATH||undefined});
const page=await browser.newPage();const failures=[];let count=0;
for(const width of [320,768,1440])for(const lang of ['en','mk','sq','de','fr','it'])for(const route of ['home','memberships','classes','contact','news']){
 await page.setViewportSize({width,height:900});
 const response=await page.goto(base+'/?'+new URLSearchParams({page:route,lang}),{waitUntil:'domcontentloaded'});
 const state=await page.evaluate(()=>({lang:document.documentElement.lang,width:document.documentElement.scrollWidth,viewport:innerWidth,h1:document.querySelector('h1')?.textContent}));
 count++;if(response.status()!==200||state.lang!==lang||state.width>state.viewport+1||!state.h1)failures.push({width,lang,route,state,status:response.status()});
}
await browser.close();
const result={test:'Six languages across five public pages at 320/768/1440 pixels',rendered:count,status:failures.length?'NEEDS FIX':'PASS',failures};
if(process.env.TEST_OUTPUT_FILE)fs.writeFileSync(process.env.TEST_OUTPUT_FILE,JSON.stringify(result,null,2));console.log(JSON.stringify(result,null,2));process.exitCode=failures.length?1:0;

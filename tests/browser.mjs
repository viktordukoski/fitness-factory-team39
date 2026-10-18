// Browser QA only; Playwright is a development dependency, never an app runtime.
import { createRequire } from 'node:module';
import fs from 'node:fs';
import path from 'node:path';
const require = createRequire(import.meta.url);
const {chromium}=require(process.env.PLAYWRIGHT_MODULE_PATH || 'playwright');
const base=process.env.TEST_BASE_URL || 'http://127.0.0.1:8098';
if(!/^http:\/\/(127\.0\.0\.1|localhost)(:\d+)?$/.test(base)) throw Error('Use a local demonstration URL.');
const output=process.env.TEST_OUTPUT_DIR || path.resolve('test-results');
fs.mkdirSync(output,{recursive:true});
const browser=await chromium.launch({headless:true,executablePath:process.env.CHROME_PATH || undefined});
const context=await browser.newContext({viewport:{width:1440,height:1000}});
const page=await context.newPage();const results=[];const jsErrors=[];
page.setDefaultTimeout(10000);
page.on('pageerror',error=>jsErrors.push(error.message));
const route=(name,params={})=>base+'/?'+new URLSearchParams({page:name,lang:'en',...params});
async function check(name,task){try{await task();results.push({test:name,status:'PASS'});console.log('PASS: '+name);}catch(error){results.push({test:name,status:'NEEDS FIX',reason:error.message});console.log('NEEDS FIX: '+name+' '+error.message);}}
function expect(test,message){if(!test)throw Error(message);}
await check('Homepage assets render',async()=>{
 await page.goto(base,{waitUntil:'networkidle'});
 await page.locator('img').evaluateAll(imgs=>Promise.all(imgs.map(img=>{img.loading='eager';return img.decode().catch(()=>{});})));
 const loaded=await page.locator('img').evaluateAll(imgs=>imgs.every(i=>i.complete&&i.naturalWidth>0));
 expect(loaded,'One or more local images failed to load');
 expect(await page.locator('body').evaluate(el=>getComputedStyle(el).backgroundColor)!=='rgba(0, 0, 0, 0)','Stylesheet unavailable');
 await page.screenshot({path:path.join(output,'home-desktop.png'),fullPage:true});
 await page.screenshot({path:path.join(output,'home-desktop-viewport.png')});
});
await check('Reject optional cookies; no language cookie before consent',async()=>{
 await page.locator('[data-cookie-reject]').click();
 await page.goto(route('services',{lang:'de'}));
 const cookies=await context.cookies();
 expect(!cookies.some(c=>c.name==='ff_language'),'Optional language cookie set without consent');
 expect(cookies.some(c=>c.name==='ff_consent'),'Consent decision not persisted');
});
await check('Manage preferences, language persistence and Escape',async()=>{
 await page.locator('footer [data-cookie-open]').click();
 await page.locator('#cookie-dialog input[name=preferences]').check();
 await page.locator('#cookie-preferences-form button[type=submit]').click();
 expect((await context.cookies()).some(c=>c.name==='ff_language'&&c.value==='de'),'Consented language not stored');
 await page.locator('footer [data-cookie-open]').click();
 await page.keyboard.press('Escape');
 expect(!(await page.locator('#cookie-dialog').isVisible()),'Escape did not close dialog');
});
await check('Nine representative widths without horizontal page overflow',async()=>{
 const issues=[];
 for(const width of [320,375,390,430,768,1024,1280,1440,1920]){
  await page.setViewportSize({width,height:900});
  for(const name of ['home','memberships','classes','contact','login']){
   await page.goto(route(name),{waitUntil:'domcontentloaded'});
   const dims=await page.evaluate(()=>({page:document.documentElement.scrollWidth,viewport:innerWidth}));
   if(dims.page>dims.viewport+1)issues.push(`${name} ${width}px has ${dims.page}px content`);
  }
  if(width===390)await page.screenshot({path:path.join(output,'mobile-login.png'),fullPage:true});
 }
 expect(!issues.length,issues.join('; '));
});
await check('Mobile navigation can be opened from keyboard',async()=>{
 await page.setViewportSize({width:390,height:844});await page.goto(route('home'));
 const button=page.locator('.nav-toggle');await button.focus();await page.keyboard.press('Enter');
 expect(await button.getAttribute('aria-expanded')==='true','Navigation did not open');
 expect(await page.locator('#primary-navigation').isVisible(),'Mobile links not visible');
 await page.keyboard.press('Escape');
 await page.locator('img').evaluateAll(imgs=>Promise.all(imgs.map(img=>{img.loading='eager';return img.decode().catch(()=>{});})));
 await page.screenshot({path:path.join(output,'home-mobile.png'),fullPage:true});
 await page.screenshot({path:path.join(output,'home-mobile-viewport.png')});
});
await check('Calendar toggle and keyboard-accessible event links',async()=>{
 await page.setViewportSize({width:1280,height:900});await page.goto(route('classes'));
 await page.locator('[data-schedule-view=calendar]').click();
 expect(await page.locator('[data-schedule-calendar]').isVisible(),'Calendar not visible');
 expect(await page.locator('[data-schedule-calendar] a').count()>0,'Calendar links missing');
 await page.locator('[data-schedule-view=list]').click();expect(await page.locator('[data-schedule-list]').isVisible(),'List unavailable');
});
await check('Gallery lightbox opens and closes with Escape',async()=>{
 await page.goto(route('gallery'));
 await page.locator('[data-gallery-src]').first().click();
 expect(await page.locator('#gallery-dialog').isVisible(),'Gallery dialog did not open');
 await page.keyboard.press('ArrowRight');await page.keyboard.press('Escape');
 expect(!(await page.locator('#gallery-dialog').isVisible()),'Gallery Escape failed');
});
await check('Login and account screens at mobile and desktop widths',async()=>{
 await page.goto(route('login'));await page.locator('input[name=email]').first().fill('member@demo.test');await page.locator('input[name=password]').fill('FactoryMember!2026');
 await Promise.all([page.waitForURL(/page=dashboard/),page.locator('.auth-content button[type=submit]').click()]);
 for(const width of [320,390,768,1440]){
  await page.setViewportSize({width,height:900});
  for(const name of ['dashboard','passes','bookings','orders','favorites','settings','checkout']){
   await page.goto(route(name,name==='checkout'?{id:4}:{}));
   expect(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),`${name} overflow at ${width}`);
  }
 }
});
await check('Administration and image upload restrictions',async()=>{
 await page.goto(route('settings'));await page.locator('form[action*="page=logout"] button').click();
 await page.goto(route('login'));await page.locator('input[name=email]').first().fill('admin@demo.test');await page.locator('input[name=password]').fill('FactoryAdmin!2026');
 await Promise.all([page.waitForURL(/page=dashboard/),page.locator('.auth-content button[type=submit]').click()]);
 await page.goto(route('admin',{section:'content'}));
 await page.locator('input[type=file]').setInputFiles({name:'forbidden.php',mimeType:'application/x-httpd-php',buffer:Buffer.from('<?php echo 1;')});
 await page.locator('.upload-panel button[type=submit]').click();
 expect(await page.locator('.alert.error').count()>0,'Executable upload not rejected');
 await page.goto(route('admin',{section:'content'}));
 await page.locator('input[type=file]').setInputFiles({name:'test.png',mimeType:'image/png',buffer:Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAIAAAACCAIAAAD91JpzAAAAEElEQVR4nGP4f40BiBggFAA3VgdVKLi9dQAAAABJRU5ErkJggg==','base64')});
 await page.locator('.upload-panel button[type=submit]').click();
 const text=await page.locator('body').innerText();const uploaded=text.match(/uploads\/[a-f0-9]+\.webp/);
 expect(uploaded,'Valid raster image not re-encoded/stored');
 const response=await context.request.get(base+'/'+uploaded[0]);expect(response.status()===200,'Uploaded image cannot be retrieved');
 expect((response.headers()['content-type']||'').includes('image/webp'),'Wrong upload content type');
 for(const width of [320,390,768,1440]){await page.setViewportSize({width,height:900});for(const section of ['dashboard','content','sessions','plans','users','inquiries']){await page.goto(route('admin',{section}));expect(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),`admin ${section} overflow at ${width}`);}}
 await page.setViewportSize({width:1440,height:1000});await page.screenshot({path:path.join(output,'admin-desktop.png'),fullPage:true});
});
await check('No JavaScript runtime exceptions',async()=>expect(!jsErrors.length,jsErrors.join('; ')));
await browser.close();
const report={checks:results,passed:results.filter(r=>r.status==='PASS').length,total:results.length};
fs.writeFileSync(path.join(output,'browser-results.json'),JSON.stringify(report,null,2));console.log(JSON.stringify(report,null,2));
process.exitCode=results.some(r=>r.status!=='PASS')?1:0;

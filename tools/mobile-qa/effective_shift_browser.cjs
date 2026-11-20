// Uses disposable database and synthetic credentials only.
const fs=require('node:fs'),path=require('node:path'),{chromium}=require('playwright');
(async()=>{
 const qa=process.argv[2];if(!qa||!fs.existsSync(path.join(qa,'.naltech-qa')))throw Error('Disposable QA required');
 const fixture=JSON.parse(fs.readFileSync(path.join(qa,'fixture.json'),'utf8'));
 const browser=await chromium.launch({channel:'chrome',headless:true});const context=await browser.newContext({viewport:{width:1440,height:1000}});const page=await context.newPage();
 const base='http://127.0.0.1:8018',checks=[],out=path.resolve('docs/screenshots/effective-shifts');fs.mkdirSync(out,{recursive:true});
 const check=(ok,label)=>{if(!ok)throw Error(label);checks.push(label);};
 try{
 await page.goto(base+'/login');await page.locator('[name=email]').fill('face-reviewer@example.test');await page.locator('[name=password]').fill(fixture.password);await Promise.all([page.waitForURL('**/home'),page.locator('button[type=submit]').click()]);
 await page.goto(base+'/shifts/create');await page.locator('[name=name]').fill('Malam QA');await page.locator('[name=time_in]').fill('22:00');await page.locator('[name=time_out]').fill('06:00');await Promise.all([page.waitForURL('**/shifts'),page.getByRole('button',{name:'Tambah shift',exact:true}).click()]);
 await page.goto(base+'/effective-shifts');const options=page.locator('#employee option').filter({hasText:'Penguji Face'});const owner=await options.count()?await options.first().getAttribute('value'):null;
 const staff=owner || await page.locator('#employee option').first().getAttribute('value');await page.locator('#employee').selectOption(staff);await Promise.all([page.waitForURL('**/effective-shifts?user_id=*'),page.getByRole('button',{name:'Lihat jadwal'}).click()]);
 const today=await page.locator('#effective').inputValue();const tomorrow=new Date(today+'T12:00:00Z');tomorrow.setUTCDate(tomorrow.getUTCDate()+1);
 const shift=await page.locator('#shift option').filter({hasText:'Malam QA'}).getAttribute('value');await page.locator('#shift').selectOption(shift);await page.locator('#effective').fill(tomorrow.toISOString().slice(0,10));await page.locator('#reason').fill('Rotasi malam mulai besok');
 await page.locator('#btn-open-preview').click();
 check(await page.locator('#shift-assignment-preview-dialog').isVisible(),'preview dialog opened');
 check((await page.locator('#preview-session-impact').textContent()).includes('Jadwal baru akan aktif mulai tanggal mendatang'),'preview shows dynamic impact');
 await Promise.all([page.waitForURL('**/effective-shifts?user_id=*'),page.locator('#shift-preview-confirm').click()]);
 check(await page.getByRole('cell',{name:'Rotasi malam mulai besok',exact:true}).count()===1,'future assignment stored through form');
 check(await page.getByRole('cell',{name:'Salinan penempatan saat penjadwalan efektif dimulai',exact:true}).count()===1,'current assignment baseline preserved');
 await page.goto(base+'/shifts/'+shift+'/edit');await page.locator('[name=name]').fill('Master malam berubah');await page.locator('[name=time_in]').fill('23:00');await Promise.all([page.waitForURL('**/shifts'),page.getByRole('button',{name:'Simpan perubahan',exact:true}).click()]);
 await page.goto(base+'/effective-shifts?user_id='+staff);check(await page.getByRole('cell',{name:'Malam QA',exact:true}).count()===1,'history keeps original master name');check((await page.getByRole('row').filter({hasText:'Rotasi malam mulai besok'}).textContent()).includes('22:00'),'history keeps original start time');
 for(const theme of ['light','dark']){await page.locator('[data-theme-choice='+theme+']').click();for(const [size,width,height] of [['desktop',1440,1000],['mobile',390,844]]){await page.setViewportSize({width,height});await page.evaluate(()=>window.scrollTo(0,0));await page.screenshot({path:path.join(out,theme+'-'+size+'.png'),fullPage:true,animations:'disabled'});check(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),theme+' '+size+' no page overflow');}}
 await page.goto(base+'/users/'+staff+'/edit');check(await page.locator('#shift_id').isDisabled(),'legacy selector disabled after effective assignment');check(await page.getByRole('link',{name:'Kelola jadwal efektif',exact:true}).count()===1,'employee form links to effective schedule');
 fs.writeFileSync('docs/effective-shift-browser-qa.json',JSON.stringify({scope:'Disposable SQLite; synthetic fixtures only',passed:checks.length,checks,screenshots:'docs/screenshots/effective-shifts'},null,2)+'\n');console.log('Effective shift browser checks passed: '+checks.length);
 }finally{await browser.close();}
})().catch(e=>{console.error('Effective shift QA failed: '+e.message);process.exitCode=1;});

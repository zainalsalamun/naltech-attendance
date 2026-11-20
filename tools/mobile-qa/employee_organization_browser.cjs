// Prepared disposable fixtures only. No credentials, tokens or HTML in output.
const fs=require('node:fs');const path=require('node:path');const {chromium}=require('playwright');
async function main(){
 const qa=process.argv[2];if(!qa||!fs.existsSync(path.join(qa,'.naltech-qa')))throw Error('Disposable fixture required');
 const fixture=JSON.parse(fs.readFileSync(path.join(qa,'fixture.json'),'utf8'));const browser=await chromium.launch({headless:true,channel:'chrome'});
 const context=await browser.newContext({viewport:{width:1440,height:1000}});const page=await context.newPage();const base='http://127.0.0.1:8018';
 const checks=[];const check=(ok,name)=>{if(!ok)throw Error(name);checks.push(name);};const out=path.resolve('docs/screenshots/employee-organization');fs.mkdirSync(out,{recursive:true});
 const capture=async(prefix)=>{for(const theme of ['light','dark']){await page.locator('[data-theme-choice='+theme+']').click();for(const [size,width,height]of [['desktop',1440,1000],['mobile',390,844]]){await page.setViewportSize({width,height});await page.evaluate(()=>window.scrollTo(0,0));await page.evaluate(()=>new Promise(resolve=>requestAnimationFrame(()=>requestAnimationFrame(resolve))));await page.screenshot({path:path.join(out,prefix+'-'+theme+'-'+size+'.png'),fullPage:width===1440,animations:'disabled'});check(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),prefix+' '+theme+' '+size+' no page overflow');}}};
 try{
 await page.goto(base+'/login');await page.locator('[name=email]').fill('face-reviewer@example.test');await page.locator('[name=password]').fill(fixture.password);await Promise.all([page.waitForURL('**/home'),page.locator('button[type=submit]').click()]);
 await page.goto(base+'/units');check(await page.getByText('Tidak ada unit sesuai filter',{exact:true}).count()===1,'empty units state');
 await page.goto(base+'/units/create');await page.locator('[name=code]').fill('ops');await page.locator('[name=name]').fill('Operasional dan Layanan Pelanggan');await capture('unit-form');
 await Promise.all([page.waitForURL('**/units'),page.getByRole('button',{name:'Simpan unit',exact:true}).click()]);check(await page.getByText('OPS',{exact:true}).count()===1,'unit created uppercase');await capture('unit-list');
 const unitEdit=await page.getByRole('link',{name:/Edit Operasional/}).getAttribute('href');
 await page.goto(base+'/users');const staffRow=page.locator('tr').filter({hasText:'face@example.test'});const staffEdit=await staffRow.getByRole('link',{name:/Edit/}).getAttribute('href');
 await page.goto(staffEdit);await page.locator('[name=department]').fill('Layanan lama');await page.locator('[name=organization_unit_id]').selectOption({label:'OPS · Operasional dan Layanan Pelanggan'});await page.locator('[name=manager_id]').selectOption({label:'HR Penguji'});await capture('employee-mapping');
 await Promise.all([page.waitForURL('**/users'),page.getByRole('button',{name:'Simpan perubahan',exact:true}).click()]);
 check(await page.locator('tr').filter({hasText:'face@example.test'}).getByText(/Departemen lama: Layanan lama/).count()===1,'legacy department retained alongside unit');
 await page.goto(unitEdit);await page.locator('[name=is_active]').selectOption('0');await Promise.all([page.waitForURL('**/units'),page.getByRole('button',{name:'Simpan unit',exact:true}).click()]);
 check(await page.getByText('OPS',{exact:true}).count()===0,'inactive hidden from active filter');
 await page.locator('[name=status]').selectOption('inactive');await Promise.all([page.waitForURL('**/units?**'),page.getByRole('button',{name:'Cari',exact:true}).click()]);check(await page.getByText('OPS',{exact:true}).count()===1,'inactive filter works');
 await page.goto(staffEdit);check((await page.locator('[name=organization_unit_id] option:checked').textContent()).includes('(nonaktif)'),'existing inactive unit retained');check(await page.locator('[name=department]').inputValue()==='Layanan lama','legacy retained after inactive unit');
 await page.goto(base+'/users/create');check(await page.locator('[name=organization_unit_id] option').count()===1,'inactive unit unavailable to new employees');
 fs.writeFileSync('docs/employee-organization-browser-qa.json',JSON.stringify({scope:'Disposable SQLite/storage and synthetic employees; no main database changes',checks,passed:checks.length,screenshots:'docs/screenshots/employee-organization'},null,2)+'\n');process.stdout.write('Organization browser checks passed: '+checks.length+'\n');
 }finally{await browser.close();}
}main().catch(e=>{process.stderr.write('Organization browser QA failed: '+e.message+'\n');process.exitCode=1;});

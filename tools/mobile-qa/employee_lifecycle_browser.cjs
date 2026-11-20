// Disposable fixture only; never print credentials, cookies, tokens or HTML.
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');
async function main() {
 const qa=process.argv[2];
 if(!qa) throw Error('Pass a prepared disposable QA directory');
 if(!fs.existsSync(path.join(qa,'.naltech-qa'))) throw Error('fixture missing');
 const fixture=JSON.parse(fs.readFileSync(path.join(qa,'fixture.json'),'utf8'));
 const browser=await chromium.launch({headless:true,channel:'chrome'});
 const context=await browser.newContext({viewport:{width:1440,height:1000}});
 const page=await context.newPage();const checks=[];
 const check=(ok,name)=>{if(!ok)throw Error(name);checks.push(name);};
 const base='http://127.0.0.1:8018';const out=path.resolve('docs/screenshots/employee-lifecycle');fs.mkdirSync(out,{recursive:true});
 const shot=async(name)=>page.screenshot({path:path.join(out,name+'.png'),fullPage:true,animations:'disabled'});
 try {
 await page.goto(base+'/login');await page.locator('[name=email]').fill('face-reviewer@example.test');await page.locator('[name=password]').fill(fixture.password);
 await Promise.all([page.waitForURL('**/home'),page.locator('button[type=submit]').click()]);
 await page.goto(base+'/users/create');
 for(const [name,value] of Object.entries({employee_number:'qa-101',joined_on:'2026-10-01',name:'Karyawan Uji Lifecycle',email:'lifecycle@example.test',password:fixture.password})) await page.locator('[name='+name+']').fill(value);
 await shot('create-light-desktop');await page.setViewportSize({width:390,height:844});await shot('create-light-mobile');
 check(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'create mobile no page overflow');
 await page.locator('[data-theme-choice=dark]').click();await shot('create-dark-mobile');
 await Promise.all([page.waitForURL('**/users'),page.getByRole('button',{name:'Tambah karyawan',exact:true}).click()]);
 check(await page.getByText('QA-101',{exact:true}).count()===1,'create and uppercase number');
 await page.setViewportSize({width:1440,height:1000});
 const row=page.locator('tr').filter({hasText:'lifecycle@example.test'});const editHref=await row.getByRole('link',{name:/Edit/}).getAttribute('href');
 for(const theme of ['light','dark']) {
 await page.locator('[data-theme-choice='+theme+']').click();await shot('list-'+theme+'-desktop');
 await page.setViewportSize({width:390,height:844});await shot('list-'+theme+'-mobile');
 check(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),theme+' list mobile no page overflow');
 await page.setViewportSize({width:1440,height:1000});
 }
 await row.getByRole('button',{name:/Arsipkan/}).click();await page.locator('dialog[open]').waitFor();await shot('archive-dialog-dark');
 await page.locator('dialog[open]').getByRole('button',{name:'Batal',exact:true}).click();check(await page.locator('dialog[open]').count()===0,'archive cancel');
 await row.getByRole('button',{name:/Arsipkan/}).click();await page.locator('dialog[open] [name=lifecycle_reason]').fill('Kontrak uji berakhir');await page.locator('dialog[open] [name=left_on]').fill('2026-10-21');
 await Promise.all([page.waitForURL('**/users'),page.getByRole('button',{name:'Arsipkan akun',exact:true}).click()]);
 check(await page.getByText('QA-101',{exact:true}).count()===0,'archived absent from active filter');
 await page.locator('[name=status]').selectOption('archived');await page.locator('[name=name]').fill('QA-101');await Promise.all([page.waitForURL('**/users?**'),page.getByRole('button',{name:'Cari',exact:true}).click()]);
 check(await page.getByText('QA-101',{exact:true}).count()===1,'archive filter and number search');
 await page.goto(editHref);check(await page.getByText('Kontrak uji berakhir',{exact:true}).count()===1,'actor reason history renders');await shot('history-dark-desktop');
 await page.locator('[name=employment_status]').selectOption('active');await page.locator('[name=left_on]').fill('');await page.locator('[name=lifecycle_reason]').fill('Kontrak uji dilanjutkan');
 await Promise.all([page.waitForURL('**/users'),page.getByRole('button',{name:'Simpan perubahan',exact:true}).click()]);
 check(await page.getByText('QA-101',{exact:true}).count()===1,'reactivation returns employee to active list');
 fs.writeFileSync('docs/employee-lifecycle-browser-qa.json',JSON.stringify({scope:'Disposable SQLite/storage, synthetic employees; no main database changes',checks,passed:checks.length,screenshots:'docs/screenshots/employee-lifecycle'},null,2)+'\n');
 process.stdout.write('Employee lifecycle browser checks passed: '+checks.length+'\n');
 }finally{await browser.close();}
}
main().catch(error=>{process.stderr.write('Employee lifecycle QA failed: '+error.message+'\n');process.exitCode=1;});

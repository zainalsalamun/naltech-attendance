// Run only against marked, isolated fixtures; never log authentication material.
const fs = require('node:fs'), path = require('node:path');
const {chromium} = require('playwright');
async function main() {
 const qa=process.argv[2]; if(!qa || !fs.existsSync(path.join(qa,'.naltech-qa'))) throw Error('Disposable directory required');
 const fixture=JSON.parse(fs.readFileSync(path.join(qa,'fixture.json'),'utf8'));
 const browser=await chromium.launch({channel:'chrome',headless:true});
 const context=await browser.newContext({viewport:{width:1440,height:1000}});const page=await context.newPage();const base='http://127.0.0.1:8018';const checks=[];let phase='configuration';
 const check=(ok,name)=>{if(!ok)throw Error(name);checks.push(name);};
 const login=async(email)=>{await context.clearCookies();await page.goto(base+'/login');await page.locator('[name=email]').fill(email);await page.locator('[name=password]').fill(fixture.password);await Promise.all([page.waitForURL('**/home'),page.locator('button[type=submit]').click()]);};
 const out=path.resolve('docs/screenshots/approval-workflows');fs.mkdirSync(out,{recursive:true});
 const capture=async(prefix)=>{for(const theme of ['light','dark']){await page.locator('[data-theme-choice='+theme+']').click();if(prefix==='configuration')check(await page.locator('details[data-persistent-details]').first().getAttribute('open')!==null,'configuration remains open after theme '+theme);for(const [label,width,height] of [['desktop',1440,1000],['narrow',390,844]]){await page.setViewportSize({width,height});await page.evaluate(()=>window.scrollTo(0,0));await page.evaluate(()=>new Promise(resolve=>requestAnimationFrame(()=>requestAnimationFrame(resolve))));await page.screenshot({path:path.join(out,prefix+'-'+theme+'-'+label+'.png'),fullPage:true,animations:'disabled'});check(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),prefix+' '+theme+' '+label+' no page overflow');}}};
 try {
  await login('face-reviewer@example.test');await page.goto(base+'/approval-workflows');
  let form=page.locator('form').filter({has:page.locator('input[name=kind][value=permission]')});
  await form.locator('[name="draft[0][label]"]').fill('Atasan langsung');
  await form.locator('[name="draft[0][fallback_id]"]').selectOption({label:'QA Backup · admin'});
  await form.locator('[name="draft[1][label]"]').fill('HR');
  await form.locator('[name="draft[1][type]"]').selectOption('user');
  await form.locator('[name="draft[1][reviewer_id]"]').selectOption({label:'HR Penguji · hrd'});
  await form.locator('[name="draft[1][fallback_id]"]').selectOption({label:'QA Backup · admin'});
  await form.locator('[name=confirm]').check();await Promise.all([page.waitForURL('**/approval-workflows'),form.getByRole('button',{name:'Simpan alur izin',exact:true}).click()]);
  check(await page.getByText('Alur saat ini: Atasan langsung → HR.',{exact:true}).count()===1,'workflow saved through web form');await capture('configuration');
  const result=await context.request.post(base+'/api/login',{data:{email:'face@example.test',password:fixture.password}});const auth=await result.json();const headers={Authorization:'Bearer '+auth.token,Accept:'application/json'};
  const created=await context.request.post(base+'/api/api-permissions',{headers,data:{date:'2026-10-21',reason:'Uji alur dua tahap'}});check(created.status()===201,'native-compatible API creates workflow snapshot');const row=(await created.json()).data;check(row.approval_progress.total===2,'API exposes two stages');
  await page.goto(base+'/permissions/'+row.id+'/edit');check(await page.getByRole('button',{name:'Simpan keputusan',exact:true}).count()===0,'HR cannot skip manager stage');
  phase='manager approval';await login('manager@example.test');await page.goto(base+'/permissions/'+row.id+'/edit');await page.locator('[name=decision_reason]').fill('Atasan menyetujui');await Promise.all([page.waitForURL('**/permissions/**/edit'),page.getByRole('button',{name:'Simpan keputusan',exact:true}).click()]);check(await page.getByText('Tahap 2 dari 2: HR',{exact:true}).count()===1,'first approval advances active stage');check(await page.getByRole('button',{name:'Simpan keputusan',exact:true}).count()===0,'manager cannot approve HR stage');await capture('waiting-hr');
  phase='HR approval';await login('face-reviewer@example.test');await page.goto(base+'/permissions/'+row.id+'/edit');await page.locator('[name=decision_reason]').fill('HR menyetujui');await Promise.all([page.waitForURL('**/permissions/**/edit'),page.getByRole('button',{name:'Simpan keputusan',exact:true}).click()]);check(await page.getByRole('button',{name:'Simpan keputusan',exact:true}).count()===0,'final stage locks decision form');check(await page.getByRole('cell',{name:'QA Manager',exact:true}).count()===1,'manager history retained');check(await page.getByRole('cell',{name:'HR Penguji',exact:true}).count()===1,'HR history retained');
  const detail=await context.request.get(base+'/api/api-permissions/'+row.id,{headers});const final=(await detail.json()).data;check(final.status==='approved' && final.is_approved===true && final.approval_version===2,'API final approval only after both stages');await capture('final');
  await login('manager@example.test');await page.goto(base+'/approval-workflows');check(await page.getByRole('button',{name:'Simpan alur izin',exact:true}).count()===0,'supervisor sees configuration read only');
  fs.writeFileSync('docs/approval-workflow-browser-qa.json',JSON.stringify({date:'2026-10-21',scope:'Disposable SQLite, real Chrome web forms, synthetic accounts, no main database changes',checks,passed:checks.length,screenshots:'docs/screenshots/approval-workflows'},null,2)+'\n');process.stdout.write('Workflow browser checks passed: '+checks.length+'\n');
 } catch(e) {await page.screenshot({path:path.join(out,'failure.png'),fullPage:true});throw Error(phase+': '+e.message);} finally {await browser.close();}
}
main().catch(e=>{process.stderr.write('Workflow QA failed: '+e.message+'\n');process.exitCode=1;});

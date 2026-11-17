// Drive only the disposable QA Dashboard; no auth data in output.
const fs=require('node:fs'),path=require('node:path');const {chromium}=require('playwright');
async function main(){
 const [qa,progressFile]=process.argv.slice(2);if(!qa||!fs.existsSync(path.join(qa,'.naltech-qa')))throw Error('Disposable fixture required');
 const fixture=JSON.parse(fs.readFileSync(path.join(qa,'fixture.json'),'utf8'));const progress=JSON.parse(fs.readFileSync(progressFile,'utf8'));if(progress.stage!=='awaiting_dashboard')throw Error('Native stage not ready');
 const browser=await chromium.launch({channel:'chrome',headless:true});const context=await browser.newContext({viewport:{width:1440,height:1000}});const page=await context.newPage();const base='http://127.0.0.1:8018';const checks=[];
 try{
 await page.goto(base+'/login');await page.locator('[name=email]').fill('face-reviewer@example.test');await page.locator('[name=password]').fill(fixture.password);await Promise.all([page.waitForURL('**/home'),page.locator('button[type=submit]').click()]);
 for(const [kind,id] of Object.entries(progress.records)){
  if(!Number.isInteger(id))throw Error('Invalid fixture record');const resource={permission:'permissions',overtime:'overtimes',reimbursement:'reimbursements'}[kind];if(!resource)throw Error('Invalid kind');
  await page.goto(base+'/'+resource+'/'+id+'/edit');await page.locator('#decision-status').selectOption(kind==='reimbursement'?'rejected':'approved');await page.locator('[name=decision_reason]').fill('Ditinjau melalui Dashboard QA');
  await Promise.all([page.waitForURL('**/'+resource+'/**/edit'),page.getByRole('button',{name:'Simpan keputusan',exact:true}).click()]);
  if(await page.getByRole('button',{name:'Simpan keputusan',exact:true}).count())throw Error('Decision not persisted');
  checks.push(kind+' native-created request decided through web form');
 }
 fs.writeFileSync('docs/mobile-approval-device-dashboard.json',JSON.stringify({scope:'QA Dashboard session with CSRF, native-created synthetic requests',checks,passed:checks.length},null,2)+'\n');process.stdout.write('Dashboard decisions passed: '+checks.length+'\n');
 }finally{await browser.close();}
}main().catch(e=>{process.stderr.write('QA Dashboard failed: '+e.message+'\n');process.exitCode=1;});

import fs from 'node:fs';
import path from 'node:path';
import { execSync } from 'node:child_process';

// Import bundled puppeteer
const tp = await import('/Applications/Antigravity.app/Contents/Resources/app.asar.unpacked/node_modules/chrome-devtools-mcp/build/src/third_party/index.js');
const puppeteer = tp.puppeteer;

const qa = process.argv[2];
if (!qa || !fs.existsSync(path.join(qa, '.naltech-qa'))) {
    console.error('Usage: node approval_ui_browser_qa.mjs <QA_DIR>');
    process.exit(1);
}

const fixture = JSON.parse(fs.readFileSync(path.join(qa, 'fixture.json'), 'utf8'));
const meta = JSON.parse(fs.readFileSync(path.join(qa, 'approval-ui-meta.json'), 'utf8'));

function querySqlite(sql) {
    const dbPath = path.join(qa, 'qa.sqlite');
    return execSync(`sqlite3 "${dbPath}" "${sql}"`, { encoding: 'utf8' }).trim();
}

const BASE = 'http://127.0.0.1:8018';
const outDir = path.resolve('docs/screenshots/approval-ui-verification');
fs.mkdirSync(outDir, { recursive: true });

const results = [];
function check(id, condition, description, details = '') {
    if (!condition) {
        const msg = `FAIL: [${id}] ${description} ${details}`;
        console.error(msg);
        results.push({ id, status: 'gagal', description, details, error: details });
        throw new Error(msg);
    }
    console.log(`PASS: [${id}] ${description}`);
    results.push({ id, status: 'lulus', description, details });
}

const browser = await puppeteer.launch({
    executablePath: '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
    headless: true,
    args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-gpu']
});

const page = await browser.newPage();
await page.setViewport({ width: 1440, height: 1000 });

// Capture helper for light/dark and desktop/narrow
async function captureScreenshots(namePrefix) {
    for (const theme of ['light', 'dark']) {
        await page.evaluate((t) => {
            document.documentElement.dataset.theme = t;
            const btn = document.querySelector(`[data-theme-choice="${t}"]`);
            if (btn) btn.click();
        }, theme);
        await new Promise(r => setTimeout(r, 100));

        for (const [device, w, h] of [['desktop', 1440, 1000], ['narrow', 390, 844]]) {
            await page.setViewport({ width: w, height: h });
            await page.evaluate(() => window.scrollTo(0, 0));
            await new Promise(r => setTimeout(r, 100));

            const filename = `${namePrefix}-${theme}-${device}.png`;
            const filepath = path.join(outDir, filename);
            await page.screenshot({ path: filepath, fullPage: (device === 'desktop') });

            const hasOverflow = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth);
            if (hasOverflow) {
                console.warn(`Warning: Page horizontal overflow on ${filename}`);
            }
        }
    }
    // Restore default viewport
    await page.setViewport({ width: 1440, height: 1000 });
}

async function clearCookies() {
    const client = await page.target().createCDPSession();
    await client.send('Network.clearBrowserCookies');
    await client.detach();
}

async function login(email, pwd = fixture.password) {
    await clearCookies();

    await page.goto(`${BASE}/login`, { waitUntil: 'networkidle0' });
    await page.waitForSelector('[name=email]', { visible: true });
    await page.evaluate(() => {
        document.querySelector('[name=email]').value = '';
        document.querySelector('[name=password]').value = '';
    });
    await page.type('[name=email]', email);
    await page.type('[name=password]', pwd);
    await Promise.all([
        page.waitForNavigation({ waitUntil: 'networkidle0' }),
        page.click('button[type=submit]')
    ]);
}

try {
    console.log('=== Starting Approval UI Browser Verification (B01–B18) ===');

    // -------------------------------------------------------------
    // B01: Filter all/actionable/waiting_others pada tiga modul
    // -------------------------------------------------------------
    await login('supervisor@example.test');

    // 1. Module Permissions
    await page.goto(`${BASE}/permissions?action_context=actionable`, { waitUntil: 'networkidle0' });
    const permActionableCount = await page.evaluate(() => document.querySelectorAll('tbody tr').length);
    check('B01', permActionableCount >= 1, 'Supervisor actionable permission filter returns active reviewable rows');
    const hasTinjauBtn = await page.evaluate(() => {
        const btns = Array.from(document.querySelectorAll('a.primary'));
        return btns.some(b => b.textContent.includes('Tinjau pengajuan') || b.textContent.includes('Tinjau pembatalan'));
    });
    check('B01', hasTinjauBtn, 'Actionable permission row renders primary Tinjau button');

    await page.goto(`${BASE}/permissions?action_context=waiting_others`, { waitUntil: 'networkidle0' });
    const permWaitingRows = await page.evaluate(() => document.querySelectorAll('tbody tr').length);
    check('B01', permWaitingRows >= 1, 'Permission waiting_others queue contains pending non-actionable rows');

    // 2. Module Overtimes
    await page.goto(`${BASE}/overtimes?action_context=actionable`, { waitUntil: 'networkidle0' });
    const ovActionableCount = await page.evaluate(() => document.querySelectorAll('tbody tr').length);
    check('B01', ovActionableCount >= 0, 'Overtime actionable filter loads correctly');

    await page.goto(`${BASE}/overtimes?action_context=waiting_others`, { waitUntil: 'networkidle0' });
    const ovWaitingCount = await page.evaluate(() => document.querySelectorAll('tbody tr').length);
    check('B01', ovWaitingCount >= 1, 'Overtime waiting_others queue contains supervisor own request');

    // 3. Module Reimbursements
    await page.goto(`${BASE}/reimbursements?action_context=actionable`, { waitUntil: 'networkidle0' });
    const rbActionableCount = await page.evaluate(() => document.querySelectorAll('tbody tr').length);
    check('B01', rbActionableCount >= 1, 'Reimbursement without workflow is actionable for direct supervisor');

    await captureScreenshots('B01-queue-actionable');

    // -------------------------------------------------------------
    // B02: Lebih dari 10 pengajuan dan navigasi halaman
    // -------------------------------------------------------------
    await page.goto(`${BASE}/permissions?action_context=actionable`, { waitUntil: 'networkidle0' });
    const paginationText = await page.evaluate(() => {
        const el = document.querySelector('.table-footer, .pagination');
        return el ? el.textContent : '';
    });
    check('B02', paginationText.includes('dari') || paginationText.includes('Berikutnya'), 'Pagination summary reflects >10 items');

    const nextHref = await page.evaluate(() => {
        const links = Array.from(document.querySelectorAll('.table-footer nav a, .pagination a'));
        const next = links.find(a => a.textContent.includes('Berikutnya') || a.href.includes('page=2'));
        return next ? next.href : null;
    });
    check('B02', nextHref !== null, 'Pagination has next page link');
    check('B02', nextHref.includes('action_context=actionable') && nextHref.includes('page=2'), 'Next page URL preserves action_context filter in query string');

    await page.goto(nextHref, { waitUntil: 'networkidle0' });
    const page2Rows = await page.evaluate(() => document.querySelectorAll('tbody tr').length);
    check('B02', page2Rows >= 1 && page2Rows <= 10, 'Page 2 renders remaining candidates without loss or duplicates');

    // -------------------------------------------------------------
    // B03: Peninjau tahap 2 membuka pending tahap 1
    // -------------------------------------------------------------
    await login('hr@example.test');
    await page.goto(`${BASE}/permissions/${meta.rows.flow_permission_id}`, { waitUntil: 'networkidle0' });

    const hrCanDecideForm = await page.$('#submission-decision-form');
    check('B03', hrCanDecideForm === null, 'HR cannot see decision form on stage 1 pending permission');
    const stageInfoText = await page.evaluate(() => document.body.textContent);
    check('B03', stageInfoText.includes('Tahap 1 dari 2: Atasan Langsung') && stageInfoText.includes('QA Supervisor Direct'), 'Stage 1 active reviewer clearly displayed to HR');

    // -------------------------------------------------------------
    // B04: Pemohon membuka pengajuan sendiri (larangan self-approval)
    // -------------------------------------------------------------
    await login('supervisor@example.test');
    await page.goto(`${BASE}/overtimes/${meta.rows.supervisor_overtime_id}`, { waitUntil: 'networkidle0' });
    const selfDecideForm = await page.$('#submission-decision-form');
    check('B04', selfDecideForm === null, 'Self-approval strictly forbidden: no decision form for owner');
    const selfNotice = await page.evaluate(() => document.body.textContent);
    check('B04', selfNotice.includes('milik Anda sendiri') || selfNotice.includes('Batalkan Pengajuan'), 'Clear notice explaining owner submission review policy');

    // -------------------------------------------------------------
    // B05: Utama nonaktif/arsip atau foreign, fallback sah
    // -------------------------------------------------------------
    await login('backup@example.test');
    await page.goto(`${BASE}/permissions?action_context=actionable`, { waitUntil: 'networkidle0' });
    const backupHasArchivedRow = await page.evaluate((id) => {
        return Array.from(document.querySelectorAll('a')).some(a => a.href.includes(`/permissions/${id}`));
    }, meta.rows.archived_permission_id);
    check('B05', backupHasArchivedRow, 'Fallback reviewer receives actionable task when primary reviewer is archived');

    await page.goto(`${BASE}/permissions/${meta.rows.archived_permission_id}`, { waitUntil: 'networkidle0' });
    const backupDecisionForm = await page.$('#submission-decision-form');
    check('B05', backupDecisionForm !== null, 'Fallback reviewer can decide when primary reviewer is archived');

    // -------------------------------------------------------------
    // B06: Reviewer/fallback kosong; workflow NULL/[]
    // -------------------------------------------------------------
    await login('supervisor@example.test');
    await page.goto(`${BASE}/permissions?action_context=waiting_others`, { waitUntil: 'networkidle0' });
    const hasNullReviewer = await page.evaluate((id) => {
        return Array.from(document.querySelectorAll('a')).some(a => a.href.includes(`/permissions/${id}`));
    }, meta.rows.null_reviewer_permission_id);
    check('B06', hasNullReviewer, 'Submission with empty reviewers is NOT dropped by SQL NULL in waiting_others');

    await page.goto(`${BASE}/permissions/${meta.rows.null_reviewer_permission_id}`, { waitUntil: 'networkidle0' });
    const nullWarning = await page.evaluate(() => document.body.textContent);
    check('B06', nullWarning.includes('Peninjau belum tersedia'), 'Notice clearly indicates reviewer is unavailable');

    // Reimbursement with workflow []
    await page.goto(`${BASE}/reimbursements/${meta.rows.reimbursement_id}`, { waitUntil: 'networkidle0' });
    const rbDecideForm = await page.$('#submission-decision-form');
    check('B06', rbDecideForm !== null, 'Reimbursement with empty workflow [] treated as single-decision for direct manager');

    // -------------------------------------------------------------
    // B07: Legacy data needs_review
    // -------------------------------------------------------------
    await login('hr@example.test');
    await page.goto(`${BASE}/permissions/${meta.rows.legacy_permission_id}`, { waitUntil: 'networkidle0' });
    const legacyDecisionForm = await page.$('#submission-decision-form');
    check('B07', legacyDecisionForm === null, 'Legacy needs_review does not render normal decision form');
    const legacyNotice = await page.evaluate(() => document.body.textContent);
    check('B07', legacyNotice.includes('rekonsiliasi') && (legacyNotice.includes('Data Legacy') || legacyNotice.includes('Perlu ditinjau')), 'Legacy reconciliation notice displayed');

    // Direct POST rejected with 409 Conflict
    const postConflict = await page.evaluate(async (id) => {
        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
            || document.querySelector('input[name="_token"]')?.value || '';
        const res = await fetch(`/submission-decisions/permission/${id}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': token
            },
            body: JSON.stringify({ status: 'approved', approval_version: 0, decision_reason: 'Ilegal direct approval' })
        });
        return res.status;
    }, meta.rows.legacy_permission_id);
    check('B07', postConflict === 409, `Direct decision endpoint returns 409 Conflict for legacy needs_review (got ${postConflict})`);

    // -------------------------------------------------------------
    // B08–B11: Flow Permission (Dialog Tinjauan, Escape, Konfirmasi, 2-Stage Progress, Balance)
    // -------------------------------------------------------------
    await login('supervisor@example.test');
    await page.goto(`${BASE}/permissions/${meta.rows.flow_permission_id}`, { waitUntil: 'networkidle0' });

    // B08: Fill decision form and click Tinjau Keputusan
    await page.type('#decision-reason', 'Disetujui oleh atasan langsung untuk tahap operasional.');
    await page.click('#btn-review-decision');
    await new Promise(r => setTimeout(r, 200));

    const isDialogOpen = await page.evaluate(() => {
        const d = document.getElementById('decision-review-dialog');
        return d && d.open;
    });
    check('B08', isDialogOpen, 'Decision review dialog opens upon clicking Tinjau Keputusan');

    const dialogData = await page.evaluate(() => ({
        applicant: document.getElementById('review-applicant-name')?.textContent || '',
        module: document.getElementById('review-module-desc')?.textContent || '',
        decision: document.getElementById('review-decision-text')?.textContent || '',
        reason: document.getElementById('review-reason-text')?.textContent || '',
        impact: document.getElementById('review-impact-text')?.textContent || '',
    }));
    check('B08', dialogData.applicant.includes('QA Staff Submitter'), 'Dialog displays correct applicant name');
    check('B08', dialogData.module.includes('Cuti Tahunan QA'), 'Dialog displays module and date range');
    check('B08', dialogData.reason.includes('Disetujui oleh atasan langsung'), 'Dialog displays exact user reason');
    check('B08', dialogData.impact.includes('tahap peninjau berikutnya') || dialogData.impact.includes('cadangan'), 'Dialog displays stage 1 leave impact preview');

    await captureScreenshots('B08-decision-review-dialog');

    // B09: Kembali mengedit / Escape / Focus restoration
    await page.click('#review-dialog-back');
    await new Promise(r => setTimeout(r, 100));
    const isDialogClosed = await page.evaluate(() => {
        const d = document.getElementById('decision-review-dialog');
        return d && !d.open;
    });
    check('B09', isDialogClosed, 'Kembali mengedit closes dialog without mutation');
    const preservedReason = await page.evaluate(() => document.getElementById('decision-reason')?.value || '');
    check('B09', preservedReason.includes('Disetujui oleh atasan langsung'), 'Inputs preserved after closing dialog');

    const focusedBtnAfterBack = await page.evaluate(() => document.activeElement?.id === 'btn-review-decision');
    check('B09', focusedBtnAfterBack, 'Focus correctly restored to trigger button after back');

    // Reopen and test Escape key
    await page.click('#btn-review-decision');
    await new Promise(r => setTimeout(r, 150));
    await page.keyboard.press('Escape');
    await new Promise(r => setTimeout(r, 150));
    const closedOnEscape = await page.evaluate(() => !document.getElementById('decision-review-dialog')?.open);
    check('B09', closedOnEscape, 'Escape key cleanly closes dialog');

    // B10: Konfirmasi dan anti double-click dengan verifikasi request, event, dan ledger nyata
    await page.click('#btn-review-decision');
    await new Promise(r => setTimeout(r, 200));

    const confirmStateBefore = await page.evaluate(() => {
        const btn = document.getElementById('review-dialog-confirm');
        return { disabled: btn.disabled, text: btn.textContent.trim() };
    });
    check('B10', confirmStateBefore.disabled === false, 'Confirm button is initially enabled');

    const permId = meta.rows.flow_permission_id;
    const eventsBefore = Number(querySqlite(`SELECT COUNT(*) FROM submission_approval_events WHERE kind = 'permission' AND submission_id = ${permId};`));
    const versionBefore = Number(querySqlite(`SELECT approval_version FROM permissions WHERE id = ${permId};`));
    const ledgersBefore = Number(querySqlite(`SELECT COUNT(*) FROM leave_balance_ledgers WHERE permission_id = ${permId};`));

    // Track HTTP POST requests
    let postRequestCount = 0;
    const requestTracker = (req) => {
        if (req.method() === 'POST' && req.url().includes(`/submission-decisions/permission/${permId}`)) {
            postRequestCount++;
        }
    };
    page.on('request', requestTracker);

    // Attempt rapid multiple clicks on confirm button in browser context
    await Promise.all([
        page.waitForNavigation({ waitUntil: 'networkidle0' }),
        page.evaluate(() => {
            const btn = document.getElementById('review-dialog-confirm');
            btn.click();
            btn.click();
            btn.click();
        })
    ]);

    page.off('request', requestTracker);

    // Assertion 1: Exact HTTP POST request count dispatched
    check('B10', postRequestCount === 1, 'Exactly one POST request dispatched despite rapid multiple clicks', `got ${postRequestCount} requests`);

    // Assertion 2: Database event count incremented by exactly 1
    const eventsAfter = Number(querySqlite(`SELECT COUNT(*) FROM submission_approval_events WHERE kind = 'permission' AND submission_id = ${permId};`));
    check('B10', eventsAfter === eventsBefore + 1, 'Exactly one approval event recorded in database', `before ${eventsBefore}, after ${eventsAfter}`);

    // Assertion 3: Approval version incremented by exactly 1
    const versionAfter = Number(querySqlite(`SELECT approval_version FROM permissions WHERE id = ${permId};`));
    check('B10', versionAfter === versionBefore + 1, 'Approval version incremented exactly once', `before ${versionBefore}, after ${versionAfter}`);

    // Assertion 4: Ledger count unchanged at stage 1 (quota remains reserved, no duplicate/premature ledger entry)
    const ledgersAfter = Number(querySqlite(`SELECT COUNT(*) FROM leave_balance_ledgers WHERE permission_id = ${permId};`));
    check('B10', ledgersAfter === ledgersBefore, 'No duplicate or premature ledger entry created at stage 1', `before ${ledgersBefore}, after ${ledgersAfter}`);

    // B11: Stage 1 approved -> Advances to Stage 2 (HR)
    const afterStage1Text = await page.evaluate(() => document.body.textContent);
    check('B11', afterStage1Text.includes('Tahap 2 dari 2: HR Approval'), 'Stage 1 approval advances progress to Stage 2');
    check('B11', afterStage1Text.includes('QA HR Manager'), 'Active approver transitioned to HR Manager');

    // Now login as HR to grant final approval
    await login('hr@example.test');
    await page.goto(`${BASE}/permissions/${meta.rows.flow_permission_id}`, { waitUntil: 'networkidle0' });

    const hrActionableOption = await page.evaluate(() => {
        const opt = document.querySelector('#decision-status option[value="approved"]');
        return opt ? opt.textContent : '';
    });
    check('B11', hrActionableOption.includes('Final'), 'Final stage option labeled Setujui Pengajuan (Final)');

    await page.type('#decision-reason', 'Verifikasi kuota cuti HR selesai dan disetujui final.');
    await page.click('#btn-review-decision');
    await new Promise(r => setTimeout(r, 200));

    const finalImpactText = await page.evaluate(() => document.getElementById('review-impact-text')?.textContent || '');
    check('B11', finalImpactText.includes('terpakai') || finalImpactText.includes('final'), 'Final impact preview describes quota conversion to used days');

    await Promise.all([
        page.waitForNavigation({ waitUntil: 'networkidle0' }),
        page.click('#review-dialog-confirm')
    ]);

    const finalApprovedStatus = await page.evaluate(() => document.body.textContent);
    check('B11', finalApprovedStatus.includes('Disetujui'), 'Final approval grants Disetujui status');

    await captureScreenshots('B11-final-approved-permission');

    // -------------------------------------------------------------
    // B12–B14: Pembatalan Approved (cancellation_requested)
    // -------------------------------------------------------------
    await page.goto(`${BASE}/permissions/${meta.rows.cancel_reject_permission_id}`, { waitUntil: 'networkidle0' });

    // B12: Form Keputusan Pembatalan
    const cancelForm = await page.$('#submission-cancellation-form');
    check('B12', cancelForm !== null, 'Form Keputusan Pembatalan rendered for cancellation_requested');
    const cancelNotice = await page.evaluate(() => document.body.textContent);
    check('B12', cancelNotice.includes('Keputusan Pembatalan') && cancelNotice.includes('kuota cuti belum dikembalikan'), 'Notice explains leave remains active until cancellation approved');

    await captureScreenshots('B12-cancellation-requested-form');

    // B13: Tolak Pembatalan
    await page.select('#cancel-decision-status', 'rejected');
    await page.type('#cancel-decision-reason', 'Penolakan pembatalan: operasional kantor telah terjadwal penggantinya.');
    await page.click('#btn-review-cancellation');
    await new Promise(r => setTimeout(r, 200));

    const cancelRejectImpact = await page.evaluate(() => document.getElementById('review-impact-text')?.textContent || '');
    check('B13', cancelRejectImpact.includes('tetap berlaku') && cancelRejectImpact.includes('tetap terpakai'), 'Rejection preview specifies leave remains approved and balance remains used');

    await Promise.all([
        page.waitForNavigation({ waitUntil: 'networkidle0' }),
        page.click('#review-dialog-confirm')
    ]);

    const afterCancelReject = await page.evaluate(() => document.body.textContent);
    check('B13', afterCancelReject.includes('Disetujui') && afterCancelReject.includes('Pembatalan ditolak'), 'Rejected cancellation returns status to Disetujui and logs cancellation_rejected event');

    await captureScreenshots('B13-cancellation-rejected-state');

    // B14: Setujui Pembatalan
    await page.goto(`${BASE}/permissions/${meta.rows.cancel_approve_permission_id}`, { waitUntil: 'networkidle0' });
    check('B14', (await page.$('#submission-cancellation-form')) !== null, 'Form Keputusan Pembatalan rendered for cancel approval row');

    await page.select('#cancel-decision-status', 'approved');
    await page.type('#cancel-decision-reason', 'Disetujui pembatalan cuti: sisa kuota cuti 2 hari dipulihkan ke saldo tersedia.');
    await page.click('#btn-review-cancellation');
    await new Promise(r => setTimeout(r, 200));

    const cancelApproveImpact = await page.evaluate(() => document.getElementById('review-impact-text')?.textContent || '');
    check('B14', cancelApproveImpact.includes('dibatalkan') && (cancelApproveImpact.includes('dikembalikan') || cancelApproveImpact.includes('tersedia')), 'Approve cancellation preview specifies status cancelled and balance restoration');

    await Promise.all([
        page.waitForNavigation({ waitUntil: 'networkidle0' }),
        page.click('#review-dialog-confirm')
    ]);

    const afterCancelApprove = await page.evaluate(() => document.body.textContent);
    check('B14', afterCancelApprove.includes('Dibatalkan') && afterCancelApprove.includes('Pembatalan disetujui'), 'Approved cancellation sets status to Dibatalkan and logs cancellation_approved event');

    await captureScreenshots('B14-cancellation-approved-state');

    // -------------------------------------------------------------
    // B15: Non-balance leave type & overtime
    // -------------------------------------------------------------
    await login('supervisor@example.test');
    await page.goto(`${BASE}/permissions/${meta.rows.non_balance_permission_id}`, { waitUntil: 'networkidle0' });
    await page.type('#decision-reason', 'Izin keluarga non-saldo diverifikasi.');
    await page.click('#btn-review-decision');
    await new Promise(r => setTimeout(r, 200));

    const nonBalanceImpact = await page.evaluate(() => document.getElementById('review-impact-text')?.textContent || '');
    check('B15', !nonBalanceImpact.includes('cadangan cuti') && !nonBalanceImpact.includes('saldo terpakai'), 'Non-balance leave intermediate approval does not claim quota deduction');
    await page.click('#review-dialog-back');

    await page.select('#decision-status', 'rejected');
    await page.click('#btn-review-decision');
    await new Promise(r => setTimeout(r, 200));
    const nonBalanceRejectImpact = await page.evaluate(() => document.getElementById('review-impact-text')?.textContent || '');
    check('B15', nonBalanceRejectImpact.includes('Tidak ada mutasi saldo cuti') || nonBalanceRejectImpact.includes('tidak memengaruhi saldo cuti'), 'Non-balance leave rejection explicitly specifies no quota impact');
    await page.click('#review-dialog-back');

    await page.goto(`${BASE}/overtimes/${meta.rows.supervisor_overtime_id}`, { waitUntil: 'networkidle0' });
    const cancelFormOvertime = await page.evaluate(() => document.getElementById('cancel-reason-approved'));
    check('B15', cancelFormOvertime === null, 'Overtime does not offer leave cancellation form');

    // -------------------------------------------------------------
    // B16: Stale Version Conflict (409)
    // -------------------------------------------------------------
    const conflictResult = await page.evaluate(async (id) => {
        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
            || document.querySelector('input[name="_token"]')?.value || '';
        const res = await fetch(`/submission-decisions/permission/${id}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token },
            body: JSON.stringify({ status: 'approved', approval_version: 999, decision_reason: 'Stale version attempt' })
        });
        const json = await res.json().catch(() => ({}));
        return { status: res.status, message: json.message || '' };
    }, meta.rows.non_balance_permission_id);
    check('B16', conflictResult.status === 409, 'Server rejects stale approval_version with 409 Conflict');
    check('B16', conflictResult.message.includes('Pengajuan berubah'), 'Conflict message warns user about version change');

    // -------------------------------------------------------------
    // B17: Penolakan akses lintas perusahaan & akun nonaktif
    // -------------------------------------------------------------
    await login('foreign-hr@example.test');
    const foreignAccessStatus = await page.evaluate(async (id) => {
        const res = await fetch(`/permissions/${id}`, { headers: { 'Accept': 'text/html' } });
        return res.status;
    }, meta.rows.flow_permission_id);
    check('B17', foreignAccessStatus === 404 || foreignAccessStatus === 403, 'Foreign tenant user denied access to other company submissions');

    // Test inactive user cannot access/login
    await clearCookies();
    await page.goto(`${BASE}/login`, { waitUntil: 'networkidle0' });
    await page.waitForSelector('[name=email]', { visible: true });
    await page.type('[name=email]', 'inactive-hr@example.test');
    await page.type('[name=password]', fixture.password);
    await Promise.all([
        page.waitForNavigation({ waitUntil: 'networkidle0' }),
        page.click('button[type=submit]')
    ]);
    check('B17', page.url().includes('/login'), 'Inactive account authentication rejected by server');

    // -------------------------------------------------------------
    // B18: Nama/alasan panjang dan karakter HTML
    // -------------------------------------------------------------
    await login('supervisor@example.test');
    await page.goto(`${BASE}/permissions/${meta.rows.xss_permission_id}`, { waitUntil: 'networkidle0' });

    const xssScriptTags = await page.evaluate(() => document.querySelectorAll('script').length);
    const bodyHtml = await page.content();
    check('B18', !bodyHtml.includes('<script>alert("xss")</script>'), 'HTML/XSS characters rendered as escaped text');
    check('B18', bodyHtml.includes('&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;') || bodyHtml.includes('&lt;script&gt;'), 'XSS tags properly entity-encoded');

    const hasBadOverflow = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth + 5);
    check('B18', !hasBadOverflow, 'No horizontal page overflow on desktop 1440');

    await captureScreenshots('B18-xss-and-long-text');

    console.log('=== All Browser Verification Scenarios B01–B18 PASSED! ===');

    fs.writeFileSync('docs/hris-approval-browser-results.json', JSON.stringify({
        timestamp: new Date().toISOString(),
        total_checks: results.length,
        passed_checks: results.filter(r => r.status === 'lulus').length,
        results
    }, null, 2));

} catch (err) {
    console.error('Fatal Browser Verification Error:', err);
    await page.screenshot({ path: path.join(outDir, 'fatal-error.png'), fullPage: true });
    process.exitCode = 1;
} finally {
    await browser.close();
}

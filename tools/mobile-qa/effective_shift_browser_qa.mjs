import fs from 'node:fs';
import path from 'node:path';
import { execSync } from 'node:child_process';

// Import bundled puppeteer from Antigravity app
const tp = await import('/Applications/Antigravity.app/Contents/Resources/app.asar.unpacked/node_modules/chrome-devtools-mcp/build/src/third_party/index.js');
const puppeteer = tp.puppeteer;

const qa = process.argv[2];
if (!qa || !fs.existsSync(path.join(qa, '.naltech-qa'))) {
    console.error('Usage: node effective_shift_browser_qa.mjs <QA_DIR>');
    process.exit(1);
}

const fixture = JSON.parse(fs.readFileSync(path.join(qa, 'fixture.json'), 'utf8'));

function querySqlite(sql) {
    const dbPath = path.join(qa, 'qa.sqlite');
    return execSync(`sqlite3 "${dbPath}" "${sql}"`, { encoding: 'utf8' }).trim();
}

function execSqlite(sql) {
    const dbPath = path.join(qa, 'qa.sqlite');
    execSync(`sqlite3 "${dbPath}" "${sql}"`, { encoding: 'utf8' });
}

const BASE = 'http://127.0.0.1:8018';
const outDir = path.resolve('docs/screenshots/effective-shifts');
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

async function captureScreenshots(namePrefix) {
    for (const theme of ['light', 'dark']) {
        await page.evaluate((t) => {
            document.documentElement.dataset.theme = t;
            const btn = document.querySelector(`[data-theme-choice="${t}"]`);
            if (btn) btn.click();
        }, theme);
        await new Promise(r => setTimeout(r, 120));

        for (const [device, w, h] of [['desktop', 1440, 1000], ['mobile', 390, 844]]) {
            await page.setViewport({ width: w, height: h });
            await page.evaluate(() => window.scrollTo(0, 0));
            await new Promise(r => setTimeout(r, 120));

            const filename = `${namePrefix}-${theme}-${device}.png`;
            const filepath = path.join(outDir, filename);
            await page.screenshot({ path: filepath, fullPage: (device === 'desktop') });

            const hasOverflow = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth);
            if (hasOverflow) {
                console.warn(`Warning: Page horizontal overflow on ${filename}`);
            }
        }
    }
    await page.setViewport({ width: 1440, height: 1000 });
}

async function login(email, pwd = fixture.password) {
    await page.goto(`${BASE}/login`, { waitUntil: 'networkidle0' });
    await page.waitForSelector('[name=email]', { visible: true });
    await page.evaluate((e, p) => {
        const emailEl = document.querySelector('[name=email]');
        const pwdEl = document.querySelector('[name=password]');
        emailEl.value = e;
        pwdEl.value = p;
        emailEl.dispatchEvent(new Event('input', { bubbles: true }));
        emailEl.dispatchEvent(new Event('change', { bubbles: true }));
        pwdEl.dispatchEvent(new Event('input', { bubbles: true }));
        pwdEl.dispatchEvent(new Event('change', { bubbles: true }));
    }, email, pwd);
    await Promise.all([
        page.waitForNavigation({ waitUntil: 'networkidle0' }),
        page.click('button[type=submit]')
    ]);
}

try {
    console.log('--- Memulai Verifikasi Browser Shift Efektif ---');

    // 1. Login HR
    await login('face-reviewer@example.test');
    check('B01-LOGIN', page.url().includes('/home'), 'Login HR penguji berhasil ke Dashboard');

    // 2. Buat shift master 'Malam QA'
    await page.goto(`${BASE}/shifts/create`, { waitUntil: 'networkidle0' });
    await page.$eval('#name', el => { el.value = 'Malam QA'; el.dispatchEvent(new Event('input', { bubbles: true })); });
    await page.$eval('#time_in', el => { el.value = '22:00:00'; el.dispatchEvent(new Event('input', { bubbles: true })); });
    await page.$eval('#time_out', el => { el.value = '06:00:00'; el.dispatchEvent(new Event('input', { bubbles: true })); });
    await Promise.all([
        page.waitForNavigation({ waitUntil: 'networkidle0' }),
        page.click('.record-form button[type=submit]')
    ]);
    const afterShiftUrl = page.url();
    const shiftPageText = await page.evaluate(() => document.body.textContent);
    check('B02-CREATE-SHIFT', afterShiftUrl.includes('/shifts') && !afterShiftUrl.includes('/create'), 'Master shift lintas tengah malam berhasil dibuat', `URL: ${afterShiftUrl}`);

    // 3. Masuk ke halaman jadwal efektif karyawan
    await page.goto(`${BASE}/effective-shifts`, { waitUntil: 'networkidle0' });
    const staffId = await page.evaluate(() => {
        const options = Array.from(document.querySelectorAll('#employee option'));
        const opt = options.find(o => o.textContent.includes('Penguji Face')) || options[0];
        return opt ? opt.value : '4';
    });
    await page.select('#employee', staffId);
    await Promise.all([
        page.waitForNavigation({ waitUntil: 'networkidle0' }),
        page.click('button.secondary')
    ]);
    check('B03-SELECT-EMPLOYEE', page.url().includes(`user_id=${staffId}`), 'Form jadwal efektif karyawan termuat');

    const todayVal = await page.$eval('#effective', el => el.value);
    const tomorrow = new Date(todayVal + 'T12:00:00Z');
    tomorrow.setUTCDate(tomorrow.getUTCDate() + 1);
    const tomorrowStr = tomorrow.toISOString().slice(0, 10);

    const shiftOptionVal = await page.evaluate(() => {
        const opt = Array.from(document.querySelectorAll('#shift option')).find(o => o.textContent.includes('Malam QA'));
        return opt ? opt.value : null;
    });
    check('B04-SHIFT-OPTION-EXISTS', shiftOptionVal !== null, 'Opsi master shift Malam QA tersedia pada form');

    // 4. Validasi client-side: Alasan wajib diisi sebelum preview terbuka
    await page.select('#shift', shiftOptionVal);
    await page.$eval('#effective', (el, v) => el.value = v, tomorrowStr);
    await page.$eval('#reason', el => el.value = ''); // Kosongkan alasan

    await page.click('#btn-open-preview');
    const dialogOpenWithBlankReason = await page.$eval('#shift-assignment-preview-dialog', el => el.open);
    check('B05-VALIDATE-REASON-REQUIRED', !dialogOpenWithBlankReason, 'Form menolak membuka pratinjau jika alasan kosong');

    // 5. Buka modal pratinjau penugasan jadwal
    const testReason = 'Rotasi malam divisi operasional terjadwal';
    await page.$eval('#reason', (el, v) => el.value = v, testReason);
    await page.click('#btn-open-preview');
    await new Promise(r => setTimeout(r, 200));

    const dialogVisible = await page.$eval('#shift-assignment-preview-dialog', el => el.open);
    check('B06-OPEN-PREVIEW-DIALOG', dialogVisible, 'Dialog pratinjau penugasan jadwal terbuka');

    // 6. Verifikasi isi tabel pratinjau (nama, jam, tanggal, alasan)
    const previewName = await page.$eval('#preview-shift-name', el => el.textContent.trim());
    const previewTimes = await page.$eval('#preview-shift-times', el => el.textContent.trim());
    const previewDate = await page.$eval('#preview-effective-from', el => el.textContent.trim());
    const previewReason = await page.$eval('#preview-shift-reason', el => el.textContent.trim());

    check('B07-PREVIEW-DATA-MATCH', 
        previewName.includes('Malam QA') && 
        previewTimes.includes('22:00') && previewTimes.includes('06:00') && 
        previewDate === tomorrowStr && 
        previewReason === testReason, 
        'Data sebelum/sesudah dan alasan pada dialog pratinjau akurat'
    );

    // 7. Verifikasi copy kalimat spesifik shift malam sesuai arahan
    const impactText = await page.$eval('#preview-session-impact', el => el.textContent.trim());
    const expectedCopy = 'Jam pulang berada pada hari berikutnya. Tanggal kerja mengikuti jadwal yang ditentukan server dan dapat berbeda dari tanggal kalender saat check-in.';
    check('B08-PREVIEW-COPY-ACCURATE', 
        impactText.includes(expectedCopy), 
        'Copy teks dampak shift malam telah diselaraskan persis sesuai arahan pengguna',
        `Aktual: "${impactText}"`
    );

    // Ambil screenshot dialog pratinjau (light/dark, desktop/narrow)
    await captureScreenshots('preview-dialog');

    // 8. Uji tombol "Kembali mengedit" — Tanpa mutasi data dan mempertahankan isian
    const countBeforeCancel = parseInt(querySqlite(`SELECT COUNT(*) FROM employee_shift_assignments WHERE user_id = ${staffId};`) || '0', 10);
    await page.click('#shift-preview-back');
    await new Promise(r => setTimeout(r, 200));

    const dialogClosedAfterBack = await page.$eval('#shift-assignment-preview-dialog', el => !el.open);
    const countAfterCancel = parseInt(querySqlite(`SELECT COUNT(*) FROM employee_shift_assignments WHERE user_id = ${staffId};`) || '0', 10);
    const retainedReason = await page.$eval('#reason', el => el.value);
    const retainedDate = await page.$eval('#effective', el => el.value);
    const retainedShift = await page.$eval('#shift', el => el.value);

    check('B09-BACK-NO-MUTATION', 
        dialogClosedAfterBack && 
        countBeforeCancel === countAfterCancel && 
        retainedReason === testReason && 
        retainedDate === tomorrowStr && 
        retainedShift === shiftOptionVal,
        'Tombol Kembali mengedit menutup dialog tanpa mutasi data dan mempertahankan seluruh isian form'
    );

    // 9. Uji Konfirmasi Sekali dengan Penguncian Tombol (Double-Submit Protection)
    await page.click('#btn-open-preview');
    await new Promise(r => setTimeout(r, 200));

    const navPromise = page.waitForNavigation({ waitUntil: 'networkidle0' });
    const lockResult = await page.evaluate(() => {
        const btn = document.getElementById('shift-preview-confirm');
        btn.click();
        return {
            disabled: btn.disabled,
            text: btn.textContent.trim()
        };
    });
    check('B10-CONFIRM-LOCK-ACTIVE', lockResult.disabled && lockResult.text.includes('Menyimpan'), 'Tombol konfirmasi terkunci (disabled) mencegah mutasi ganda');
    await navPromise;

    // 10. Verifikasi keberhasilan penyimpanan dan penambahan riwayat
    const countAfterSave = parseInt(querySqlite(`SELECT COUNT(*) FROM employee_shift_assignments WHERE user_id = ${staffId};`) || '0', 10);
    const currentUrl = page.url();
    const successNotice = await page.evaluate(() => {
        const el = document.querySelector('.notice');
        return el ? el.textContent.trim() : '';
    });
    const tableText = await page.$eval('.table-scroll table', el => el.textContent);
    console.log(`Debug B11: URL=${currentUrl}, count=${countAfterSave}, notice="${successNotice}", tableHasMalam=${tableText.includes('Malam QA')}`);

    check('B11-MUTATION-SUCCESS', 
        countAfterSave >= 1 && 
        successNotice.includes('Jadwal efektif disimpan') && 
        tableText.includes('Malam QA') && 
        tableText.includes(testReason),
        'Penugasan jadwal baru berhasil disimpan dan riwayat tampil di tabel',
        `URL=${currentUrl}, notice="${successNotice}"`
    );

    // 11. Uji Penanganan Konflik Konkurensi & Pemulihan Input (Stale expected_id)
    // Ambil expected_id yang saat ini ter-render di halaman
    const currentExpectedId = await page.$eval('[name=expected_id]', el => el.value);
    
    // Simulasikan mutasi latar belakang oleh HR lain secara atomik langsung di DB
    const companyId = querySqlite(`SELECT company_id FROM users WHERE id = ${staffId};`);
    execSqlite(`INSERT INTO employee_shift_assignments (company_id, user_id, shift_id, effective_from, shift_name, time_in, time_out, timezone, actor_id, reason, created_at) VALUES (${companyId}, ${staffId}, NULL, '2026-11-20', 'Tanpa shift', NULL, NULL, 'Asia/Jakarta', 5, 'Mutasi simultan HR lain', datetime('now'));`);
    
    const dbMaxId = querySqlite(`SELECT MAX(id) FROM employee_shift_assignments WHERE user_id = ${staffId};`);
    console.log(`Simulasi balapan: Form expected_id = ${currentExpectedId}, DB latest ID = ${dbMaxId}`);

    // Isi formulir dengan data penting baru
    const conflictReason = 'Alasan penting operasional yang tidak boleh hilang saat balapan update';
    const futureDate = '2026-11-25';
    await page.$eval('#reason', (el, v) => el.value = v, conflictReason);
    await page.$eval('#effective', (el, v) => el.value = v, futureDate);

    // Buka preview dan konfirmasi simpan
    await page.click('#btn-open-preview');
    await new Promise(r => setTimeout(r, 200));

    const conflictPromise = page.waitForNavigation({ waitUntil: 'networkidle0' });
    await page.evaluate(() => {
        document.getElementById('shift-preview-confirm').click();
    });
    await conflictPromise;

    // 12. Verifikasi Penanganan Konflik
    const conflictNoticeEl = await page.$('#shift-conflict-notice');
    const conflictNoticeText = conflictNoticeEl ? await page.evaluate(el => el.textContent.trim(), conflictNoticeEl) : '';
    
    check('B12-CONFLICT-NOTICE-DISPLAYED', 
        conflictNoticeEl !== null && conflictNoticeText.includes('konflik versi'), 
        'Kotak pemberitahuan konflik konkurensi (HTTP 409) tampil membawa pesan informatif'
    );

    // 13. Verifikasi Pemulihan Input setelah Konflik
    const restoredReason = await page.$eval('#reason', el => el.value);
    const restoredDate = await page.$eval('#effective', el => el.value);
    const newExpectedId = await page.$eval('[name=expected_id]', el => el.value);

    check('B13-INPUTS-PRESERVED-ON-CONFLICT', 
        restoredReason === conflictReason && 
        restoredDate === futureDate && 
        parseInt(newExpectedId, 10) === parseInt(dbMaxId, 10), 
        'Seluruh input form (alasan, tanggal) dipulihkan utuh melalui old() dan expected_id ter-refresh otomatis'
    );

    // Ambil screenshot halaman konflik dengan input yang dipulihkan (light/dark, desktop/narrow)
    await captureScreenshots('conflict-preserved');

    // 14. Verifikasi Form Pengeditan Karyawan terkunci setelah ada jadwal efektif
    await page.goto(`${BASE}/users/${staffId}/edit`, { waitUntil: 'networkidle0' });
    const isLegacyShiftDisabled = await page.$eval('#shift_id', el => el.disabled);
    const hasEffectiveLink = await page.evaluate(() => {
        const links = Array.from(document.querySelectorAll('a'));
        return links.some(a => a.textContent.includes('Kelola jadwal efektif'));
    });

    check('B14-LEGACY-SHIFT-LOCKED', 
        isLegacyShiftDisabled && hasEffectiveLink, 
        'Selector shift lama di form karyawan terkunci disabled dan menyediakan tautan ke jadwal efektif'
    );

    // Simpan ringkasan hasil JSON
    const reportData = {
        title: 'Verifikasi Browser Alur Penugasan Shift Web & Penanganan Konflik H2',
        scope: 'Disposable SQLite & Loopback Server 127.0.0.1:8018',
        timestamp: new Date().toISOString(),
        total_checks: results.length,
        passed_checks: results.filter(r => r.status === 'lulus').length,
        failed_checks: results.filter(r => r.status === 'gagal').length,
        checks: results,
        screenshots_dir: 'docs/screenshots/effective-shifts'
    };

    fs.writeFileSync('docs/effective-shift-browser-qa.json', JSON.stringify(reportData, null, 2) + '\n');
    console.log(`\n=== SEMUA PEMERIKSAAN BROWSER LULUS (${results.length}/${results.length}) ===`);

} finally {
    await browser.close();
}

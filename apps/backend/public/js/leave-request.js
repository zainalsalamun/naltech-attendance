(() => {
    const form = document.getElementById('leave-form');
    if (!form) return;
    const el = id => document.getElementById(id);
    const start = el('start_date'), end = el('end_date'), type = el('leave_type_id');
    const box = el('calculation-box'), summary = el('calc-summary'), breakdown = el('calc-breakdown'), impact = el('calc-balance-impact');
    const button = el('submit-btn'), retry = el('calc-retry'), dialog = el('leave-review');
    let revision = 0, controller, preview = null, previewKey = '', sending = false, confirmed = false;
    const key = () => JSON.stringify([start.value, end.value, type.value]);
    const line = (container, value) => { const p = document.createElement('p'); p.textContent = value; container.append(p); };
    const fail = message => { el('calc-badge').textContent = 'Belum siap dikirim'; summary.textContent = message; retry.hidden = false; button.disabled = true; };
    async function calculate() {
        const current = ++revision;
        controller?.abort(); controller = new AbortController();
        preview = null; button.disabled = true; confirmed = false; retry.hidden = true;
        summary.textContent = ''; breakdown.replaceChildren(); impact.textContent = '';
        const selected = type.selectedOptions[0];
        el('image').required = selected?.dataset.attachment === '1';
        el('attachment-note').textContent = el('image').required ? '(Wajib diunggah)' : '(Opsional)';
        if (!start.value || !end.value) { box.style.display = 'none'; return; }
        box.style.display = 'block';
        if (end.value < start.value) { fail('Tanggal selesai tidak boleh mendahului tanggal mulai.'); return; }
        el('calc-badge').textContent = 'Menghitung…'; summary.textContent = 'Memeriksa kalender dan saldo periode pilihan…';
        const snapshot = key();
        try {
            const response = await fetch(form.dataset.calculateUrl, {
                method: 'POST', signal: controller.signal,
                headers: {'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': form.querySelector('[name=_token]').value},
                body: JSON.stringify({start_date: start.value, end_date: end.value, leave_type_id: type.value || null})
            });
            const data = await response.json();
            if (current !== revision || snapshot !== key()) return;
            if (!response.ok || !data.success) {
                fail(data.errors ? Object.values(data.errors).flat().join(' ') : 'Perhitungan belum dapat dimuat. Periksa koneksi atau sesi, lalu hitung ulang.'); return;
            }
            const calc = data.calculation, balance = data.balance;
            if (!calc || !Array.isArray(calc.dates_breakdown)) throw Error('Invalid preview');
            if (!calc.calendar_complete) { fail('Kalender kerja belum lengkap. Hubungi HR sebelum mengajukan.'); return; }
            if (calc.working_days_count <= 0) { fail('Seluruh tanggal merupakan hari libur. Tidak ada hari kerja untuk diajukan.'); return; }
            el('calc-badge').textContent = `${calc.working_days_count} hari kerja`;
            summary.textContent = `${calc.working_days_count} hari dihitung; ${calc.skipped_days_count} hari libur dilewati. Periode ${data.period_year}.`;
            calc.dates_breakdown.forEach(day => line(breakdown, `${day.date} · ${day.label} · ${day.is_working ? 'Dihitung' : 'Dilewati'}`));
            if (balance?.not_allocated) { fail(`Saldo periode ${data.period_year} belum dialokasikan. Hubungi HR.`); return; }
            if (balance && !balance.sufficient) { fail(`Saldo tersedia ${balance.remaining_days} hari tidak cukup untuk ${calc.working_days_count} hari kerja.`); return; }
            impact.textContent = balance
                ? `Periode ${balance.period_year}: alokasi ${balance.allocated_days}, tersedia ${balance.remaining_days}, cadangan ${balance.reserved_days}, terpakai ${balance.used_days} hari. Setelah dikirim: tersedia ${balance.remaining_after} hari; ${calc.working_days_count} hari menjadi cadangan, belum terpakai.`
                : 'Pengajuan ini tidak mengurangi saldo cuti.';
            preview = data; previewKey = snapshot; button.disabled = false;
        } catch (error) {
            if (current !== revision || error.name === 'AbortError') return;
            fail('Perhitungan gagal dimuat. Pilihan tetap tersimpan; hitung ulang sebelum melanjutkan.');
        }
    }
    form.addEventListener('submit', event => {
        if (sending) { event.preventDefault(); return; }
        if (confirmed && preview && previewKey === key()) { sending = true; button.disabled = true; el('review-send').disabled = true; return; }
        event.preventDefault();
        if (!preview || previewKey !== key()) { calculate(); return; }
        const content = el('leave-review-content'); content.replaceChildren();
        line(content, `Jenis: ${type.selectedOptions[0].textContent.trim()}`);
        line(content, `Tanggal: ${start.value} sampai ${end.value}`);
        line(content, `${preview.calculation.working_days_count} hari kerja · ${preview.calculation.skipped_days_count} hari libur dilewati`);
        line(content, impact.textContent);
        line(content, `Alasan: ${el('reason').value}`);
        line(content, `Lampiran: ${el('image').files[0]?.name || 'Tidak ada'}`);
        dialog.showModal();
    });
    el('review-close').addEventListener('click', () => dialog.close());
    el('review-send').addEventListener('click', () => {
        if (!preview || previewKey !== key()) { dialog.close(); calculate(); return; }
        confirmed = true; dialog.close(); form.requestSubmit(button);
    });
    start.addEventListener('change', () => { if (!end.value || end.value < start.value) end.value = start.value; calculate(); });
    end.addEventListener('change', calculate); type.addEventListener('change', calculate); retry.addEventListener('click', calculate);
    calculate();
})();

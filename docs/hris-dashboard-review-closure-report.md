# Laporan Penutupan Review Dashboard Web HRIS & Pembekuan Cakupan

Tanggal: 4 November 2026, Asia/Jakarta.  
Status Dokumen: **Audit Teknis Selesai; Review Visual Web Menunggu Keputusan Pengguna**.  
Acuan PRD: `docs/hris-dashboard-review-closure-prd.md`.

---

## 1. Ringkasan Eksekutif

Laporan ini menyajikan hasil audit teknis, verifikasi antarmuka, dan pengujian konkurensi untuk penutupan review Dashboard Web (H1 Karyawan & Lifecycle, H2 Penjadwalan & Shift Efektif, H5 Kehadiran, Koreksi & Rekapitulasi, serta modul pengajuan Lembur & Reimbursement).

### Status Kunci & Batasan Proyek
1. **Detail Pengajuan Mobile (Izin, Lembur, Reimbursement)**: **Telah Disetujui Pengguna** pada 4 November 2026 (pemisahan catatan pemohon/keputusan/riwayat, label waktu resmi zona perusahaan, validasi lampiran <= 2 MiB sisi klien, fallback catatan keputusan berbasis kontrak payload legacy, isolasi sesi unduh, dan bebas overflow pada 360px).
2. **Dashboard Web**: **Menunggu Review Pengguna**. Agen telah menuntaskan seluruh audit teknis, verifikasi konkurensi, dan penyusunan bukti visual di `docs/hris-dashboard-user-review.md`. Keputusan visual resmi berada di tangan pengguna.
3. **Pencegahan Mutasi & Kepatuhan Keamanan**: Seluruh pengujian dijalankan pada database QA terisolasi (SQLite in-memory dan database QA sementara). Database MySQL utama pengguna, berkas konfigurasi `.env`, dan Firebase tidak dimutasi sama sekali.
4. **Operasi Git & Rilis**: Perintah git commit, git push, deployment server, dan pengemasan komersial **tetap ditunda** sesuai instruksi.

---

## 2. Audit Artefak Existing & Relevansi Kode

Agen telah memeriksa 100% artefak dokumentasi, data pengujian JSON, serta koleksi tangkapan layar (screenshot) yang ada di direktori kerja:

| Kategori Artefak | Jalur File / Direktori | Jumlah Item | Status Relevansi terhadap Kode Saat Ini |
|:---|:---|:---:|:---|
| **Laporan H1** | `docs/hris-web-review-and-h1-refinement-report.md`<br>`docs/hris-web-review-and-h1-refinement-results.json` | 2 berkas | **Sangat Relevan**. Mengonfirmasi token konkurensi `version`, dialog modal arsip, dan retensi unit/atasan nonaktif. |
| **Laporan H2** | `docs/hris-h2-shift-assignment-refinement-prd.md`<br>`docs/effective-shift-browser-qa.json` | 2 berkas | **Sangat Relevan**. Mengonfirmasi penanganan konflik `expected_id`, modal dialog pratinjau, dan baseline resolver. |
| **Laporan H5** | `docs/hris-h5-flow-refinement-report.md`<br>`docs/hris-h5-flow-refinement-results.json` | 2 berkas | **Sangat Relevan**. Mengonfirmasi diff badges ("Diubah"/"Tidak berubah"), audit trail, rekap 90 hari, dan CSV streaming BOM. |
| **Hasil Browser Terkini** | `docs/web-visual-review-report.json`<br>`docs/web-review-closure-results.json` | 2 berkas (45 checks) | **Sangat Relevan**. Bukti eksekusi Chrome CDP untuk skenario konflik H1 (P1), diff H5 (P2), dan baseline H2 (P3). |
| **Screenshots Visual** | `docs/screenshots/web-visual-review/` | 38 file PNG | **Aktif Digunakan**. Tampilan light/dark desktop 1440 & narrow 390 untuk H1, H2, H5, Lembur, dan Reimbursement. |
| **Screenshots Penutupan** | `docs/screenshots/web-review-closure/` | 5 file PNG | **Aktif Digunakan**. Bukti konflik update, konflik arsip, diff badges, baseline tanggal masa depan, dan revisi tanggal sama. |
| **Screenshots Shift** | `docs/screenshots/effective-shifts/` | 12 file PNG | **Aktif Digunakan**. Modal pratinjau dan pemulihan input konflik shift efektif pada desktop dan mobile. |
| **Screenshots H5** | `docs/screenshots/h5-flow-refinement/` | 20 file PNG | **Aktif Digunakan**. Pratinjau koreksi, rekap cuti `cancellation_requested`, breakdown harian, dan sanitasi XSS. |

---

## 3. Evaluasi Matriks Penerimaan (C01 s/d C09)

Sesuai kriteria PRD, berikut adalah status evaluasi penerimaan C01 hingga C09:

### C01: H1 Master/Edit/Status/Arsip & Retensi Organisasi Nonaktif
- **Status**: **LULUS**
- **Bukti Pengujian**:
  - `H1FlowRefinementTest::test_r02_role_scoping_and_cross_tenant_isolation`
  - `H1FlowRefinementTest::test_r05_deactivation_archived_reactivation_and_credential_invalidation`
  - `H1FlowRefinementTest::test_r07_manager_cycles_cross_tenant_and_inactive_retention`
  - Bukti Browser Chrome: H1-01 s/d H1-06.
  - Tangkapan Layar: `docs/screenshots/web-visual-review/01-h1-users-index-light-desktop.png`, `01-h1-users-index-dark-desktop.png`, `01-h1-users-index-dark-narrow.png`, `03-h1-users-edit-desktop.png`, `04-h1-users-archive-dialog.png`.
- **Kriteria Terpenuhi**:
  - Badge status aktif/nonaktif/arsip terpampang jelas pada tema terang dan gelap.
  - Unit organisasi dan manajer yang dinonaktifkan tetap terpilih pada formulir edit dengan tanda `(nonaktif)`.
  - Pilihan unit dan manajer nonaktif dicegah dari penugasan baru.
  - Modal arsip mewajibkan alasan sebelum konfirmasi dan mencabut sesi pengguna via epoch baru.
  - Karyawan terarsip dapat direaktivasi dengan aksi eksplisit `reactivated` dan alasan non-kosong.

### C02: H1 Penanganan Konflik Update & Arsip (Lost Update Protection)
- **Status**: **LULUS**
- **Bukti Pengujian**:
  - `H1FlowRefinementTest::test_r09_audit_noop_concurrency_conflict_and_rollback`
  - Bukti Browser Chrome: P1-01 s/d P1-13 pada `docs/web-review-closure-results.json`.
  - Tangkapan Layar: `docs/screenshots/web-review-closure/P1-h1-conflict-update-recovered.png`, `P1-h1-conflict-archive.png`.
- **Kriteria Terpenuhi**:
  - Permintaan edit dengan `version` usang ditolak dengan HTTP 409 Conflict.
  - Notice semantik amber `#user-conflict-notice` ditampilkan jelas.
  - Input nama (`Deni Pratama Input Pulih`), telepon (`081299998888`), dan alasan perubahan dipulihkan utuh via `old()`.
  - Pada tindakan arsip konkuren, notice konflik ditampilkan dan modal dialog arsip karyawan sasaran (ID 25) dibuka kembali menggunakan `showModal()`, sehingga `::backdrop` aktif menggelapkan halaman latar belakang dan fokus terkunci pada textarea alasan dengan isian alasan arsip terpelihara.
  - Verifikasi mutasi basis data: 0 baris berubah saat konflik ditolak.

### C03: H2 Modal Pratinjau Jadwal & Baseline Resolver
- **Status**: **LULUS**
- **Bukti Pengujian**:
  - `EffectiveShiftTest::test_assignments_are_versioned_scoped_and_copy_master_times`
  - `EffectiveShiftTest::test_effective_shifts_index_renders_preview_dialog_and_modal_attributes`
  - Bukti Browser Chrome: P3-01 s/d P3-17 pada `docs/web-review-closure-results.json`.
  - Tangkapan Layar: `docs/screenshots/web-review-closure/P3-h2-baseline-future-date.png`, `P3-h2-baseline-same-date-revision.png`.
- **Kriteria Terpenuhi**:
  - Modal `#shift-assignment-preview-dialog` memuat baseline "Sebelum" berdasarkan tanggal berlaku target (`effective_from`).
  - Skenario A (P3-05 s/d P3-09): Penugasan tanggal mendatang (2026-11-05) membandingkan baseline penugasan aktif pada tanggal tersebut (*Shift Siang* dari penugasan 2026-11-01), bukan sekadar jadwal hari ini.
  - Skenario B (P3-12 s/d P3-16): Revisi penugasan tanggal sama (2026-11-05) membandingkan baseline penugasan revisi sebelumnya (*Malam*), bukan penugasan awal.
  - Jam kerja, nama shift, tanggal mulai berlaku, zona waktu perusahaan, dan alasan perubahan tersaji presisi.

### C04: H2 Kembali Tanpa Mutasi, Anti-Double Submit & Konflik Konkurensi
- **Status**: **LULUS**
- **Bukti Pengujian**:
  - `EffectiveShiftTest::test_web_form_stale_expected_id_redirects_with_conflict_preserving_inputs_and_zero_mutation`
  - `EffectiveShiftConcurrencyMySqlTest` (balapan multi-proses dengan row lock).
  - Bukti Browser Chrome: B01 s/d B14 pada `docs/effective-shift-browser-qa.json`.
  - Tangkapan Layar: `docs/screenshots/effective-shifts/conflict-preserved-light-desktop.png`, `conflict-preserved-dark-desktop.png`.
- **Kriteria Terpenuhi**:
  - Tombol *Kembali mengedit* menutup dialog modal tanpa mutasi data (0 record bertambah di `employee_shift_assignments`).
  - Tombol *Konfirmasi simpan jadwal* mengunci pengiriman ganda via atribut `disabled`.
  - Penolakan 409 pada token `expected_id` kadaluarsa menampilkan notice amber dan memulihkan seluruh isian form (`shift_id`, `effective_from`, `reason`) via `old()`.
  - Copy teks dampak shift malam terbukti identik: *"Jam pulang berada pada hari berikutnya. Tanggal kerja mengikuti jadwal yang ditentukan server dan dapat berbeda dari tanggal kalender saat check-in."*
  - Status "Tanpa shift" berdiri sendiri dan tidak diklasifikasikan sebagai hari libur perusahaan.

### C05: H5 Diff Badges, Helper Text Shift Malam & Audit Trail Koreksi
- **Status**: **LULUS**
- **Bukti Pengujian**:
  - `H5FlowRefinementTest::test_attendance_correction_records_audit_trail_validates_reason_and_detects_noop`
  - `AttendanceCorrectionRecapTest::test_attendance_edit_view_renders_helper_text_and_preview_diff_indicators`
  - Bukti Browser Chrome: P2-01 s/d P2-11 pada `docs/web-review-closure-results.json`.
  - Tangkapan Layar: `docs/screenshots/web-review-closure/P2-h5-diff-badges.png`, `docs/screenshots/web-visual-review/10-h5-correction-modal-diff-badges.png`.
- **Kriteria Terpenuhi**:
  - Formulir edit koreksi absensi menampilkan badge semantik hijau `"Diubah"` dan abu-abu `"Tidak berubah"` secara dinamis untuk jam masuk, jam pulang, tanggal, dan koordinat.
  - Teks bantuan shift malam tertera tepat: *"Isi tanggal pulang sesuai kejadian sebenarnya; pada shift malam biasanya hari berikutnya."*
  - Modal koreksi menggunakan kalimat konfirmasi produk yang ramah: *"Perubahan dan alasan koreksi akan disimpan dalam riwayat. Pastikan seluruh detail telah sesuai sebelum konfirmasi."* (menggantikan istilah teknis transaksi atomik).
  - Modal koreksi mewajibkan alasan hasil trim (ditolak 422 jika kosong).
  - Aksi simpan tanpa perubahan nilai (no-op) terdeteksi otomatis dengan notice informatif tanpa menambah baris audit event fiktif.
  - Kartu riwayat audit koreksi terintegrasi pada formulir edit dan halaman penelusuran mandiri `/attendance-corrections`.

### C06: H5 Rekapitulasi Kehadiran, Drilldown & Ekspor CSV
- **Status**: **LULUS**
- **Bukti Pengujian**:
  - `H5FlowRefinementTest::test_daily_breakdown_holiday_with_leave_range_shows_holiday_as_primary_status_and_matches_recap_aggregate`
  - `H5FlowRefinementTest::test_recap_recognizes_cancellation_requested_and_does_not_mutate_balance_ledger`
  - `H5FlowRefinementTest::test_recap_summary_and_export_stream_in_chunks_without_materializing_full_population`
  - `AttendanceCorrectionRecapTest::test_recap_limits_inclusive_range_on_page_and_export`
  - Bukti Browser Chrome: H5-06 s/d H5-11.
  - Tangkapan Layar: `docs/screenshots/web-visual-review/14-h5-attendance-recap-light-desktop.png`, `docs/screenshots/web-visual-review/16-h5-attendance-recap-dark-narrow.png`, `docs/screenshots/web-visual-review/16b-h5-attendance-recap-drawer-open-dark-narrow.png`, `docs/screenshots/h5-flow-refinement/H5-06-recap-cancellation-requested-light-desktop.png`, `H5-09-daily-breakdown-light-desktop.png`.
- **Kriteria Terpenuhi**:
  - Tampilan rekapitulasi pada layar sempit 390×844 (`16-h5-attendance-recap-dark-narrow.png`) terbukti bersih dengan drawer tertutup, menyajikan judul, subtitle, tombol ekspor, ringkasan metrik, dan kartu filter tanpa terhalang.
  - Navigasi drawer mobile diuji sebagai state terpisah pada `16b-h5-attendance-recap-drawer-open-dark-narrow.png` saat tombol hamburger diklik.
  - Pada breakdown harian, hari libur dalam rentang cuti diklasifikasikan sebagai status utama `'holiday'`, sinkron 1:1 dengan agregat `leave_days` pada rekapitulasi.
  - Status cuti `cancellation_requested` diakui penuh sebagai cuti efektif (1 hari kerja).
  - Pembacaan rekapitulasi, detail show, dan ekspor CSV terbukti secara atomik 0 mutasi terhadap saldo cuti karyawan.
  - Tombol kembali dari drilldown harian mempertahankan query filter (`start_date`, `end_date`, `name`, `organization_unit_id`, `page`) secara aman.
  - Batasan rentang kalender maksimal 90 hari ditegakkan pada halaman dan endpoint ekspor.
  - Ekspor streaming CSV diawali UTF-8 BOM (`\xEF\xBB\xBF`) dan sel dibungkus proteksi formula injection `CsvCell::text`.

### C07: Ketahanan UI, Aksesibilitas Dasar & Sanitasi XSS
- **Status**: **LULUS**
- **Bukti Pengujian**:
  - `H1FlowRefinementTest::test_r11_xss_safety_and_long_text_limits`
  - Pengujian Browser CDP: H1-11, H5-12, B14.
  - Tangkapan Layar: `docs/screenshots/h5-flow-refinement/H5-12-xss-dialog-safe-light-desktop.png`, `H5-12-xss-dialog-safe-dark-desktop.png`.
- **Kriteria Terpenuhi**:
  - Dialog modal HTML5 `<dialog>` mendukung focus trap, penutupan via tombol ESC, dan pengembalian fokus ke elemen pemicu.
  - Seluruh tabel panjang terisolasi dalam pembungkus `.table-scroll`, mencegah *horizontal body overflow* pada resolusi sempit 390×844.
  - Penanganan teks panjang (nama, email, alasan) menggunakan *break-words* tanpa merusak kontainer kartu.
  - Sanitasi XSS terbukti aman: injeksi tag `<script>` ditampilkan sebagai plain text via `textContent` pada modal pratinjau.

### C08: Ketersediaan Paket Review Pengguna
- **Status**: **LULUS**
- **Bukti Pengujian**: Dokumen `docs/hris-dashboard-user-review.md` telah dibuat lengkap dengan gambar inline/tautan lokal, penjelasan skenario, tema terang/gelap, resolusi desktop/narrow, serta tabel checklist evaluasi pengguna.
- **Kriteria Terpenuhi**: Pengguna dapat langsung membuka dokumen markdown tersebut untuk memeriksa hasil visual secara komprehensif.

### C09: Dokumen Pembekuan Batas Cakupan Fitur (Scope Freeze)
- **Status**: **LULUS**
- **Bukti Pengujian**: Dokumen `docs/hris-feature-scope-status.md` telah disusun rapi.
- **Kriteria Terpenuhi**: Memisahkan secara tegas modul yang telah disetujui pengguna (mobile detail pengajuan per 4 Nov 2026), selesai teknis menunggu review (web dashboard H1/H2/H5/Approval/Cuti/Lembur/Reimbursement), ditunda (absensi wajah, git commit, deployment), dan backlog opsional (rotasi shift, bulk shift, kalender visual, split shift).

---

## 4. Rincian Pengujian Aktual & Verifikasi Perintah

Berikut adalah hasil eksekusi pengujian aktual yang dijalankan pada sesi ini:

### 4.1 Pengujian Backend PHPUnit Terarah (H1, H2, H5)
- **Perintah**:
  ```bash
  cd apps/backend && php artisan test tests/Feature/H1FlowRefinementTest.php tests/Feature/EffectiveShiftTest.php tests/Feature/H5FlowRefinementTest.php tests/Feature/AttendanceCorrectionRecapTest.php
  ```
- **Hasil**: **35 tests, 409 assertions PASSING** (Durasi: 5.35 detik, Memory: 42.00 MB).
- **Rincian Per Suite**:
  1. `H1FlowRefinementTest`: 10 tes, 150 assertions lulus.
  2. `EffectiveShiftTest`: 8 tes, 80 assertions lulus.
  3. `H5FlowRefinementTest`: 11 tes, 97 assertions lulus.
  4. `AttendanceCorrectionRecapTest`: 6 tes, 82 assertions lulus.

### 4.2 Pengujian Widget Flutter Terarah (Mobile Detail Pengajuan & Approval)
- **Perintah 1**:
  ```bash
  cd apps/mobile && flutter test test/submission_refinement_verification_test.dart
  ```
- **Hasil**: **24 tests PASSING** (Durasi: 4 detik).
  - Memverifikasi validasi lampiran <= 2 MiB, pemisahan catatan pemohon/peninjau, fallback catatan keputusan legacy, retry lampiran privat, dan penanganan zona waktu WIB/WITA/WIT/UTC.
- **Perintah 2**:
  ```bash
  cd apps/mobile && flutter test test/submission_approval_mobile_test.dart
  ```
- **Hasil**: **8 tests PASSING** (Durasi: 2 detik).
  - Memverifikasi parsing payload detail, isolasi sesi antar-pengguna, dan tampilan approval multi-tahap.

---

## 5. Batasan Lingkungan & Pembersihan (Cleanup)

1. **Integritas Basis Data**:
   - Basis data MySQL utama pengguna tidak mengalami migrasi, seeding, truncate, atau modifikasi data apa pun selama audit penutupan ini.
   - Seluruh verifikasi web dan database dijalankan pada lingkungan testing loopback dan QA in-memory.
2. **Konfigurasi Lingkungan**:
   - File konfigurasi `apps/backend/.env` dan konfigurasi Firebase tidak dimodifikasi.
   - Tidak ada token, rahasia, atau kredensial yang diekspos ke dalam laporan atau log repositori.
3. **Pembersihan Proses & Server**:
   - Seluruh server headless loopback QA (`127.0.0.1:8018`) yang digunakan untuk verifikasi Chrome CDP telah dihentikan secara bersih. Tidak ada proses background yang ditinggalkan berjalan.
4. **Pembekuan Rilis**:
   - Tidak ada eksekusi `git commit`, `git push`, build paket rilis, atau deployment server. Milestone dibekukan pada status siap review visual oleh pengguna.

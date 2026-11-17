# Laporan Penyempurnaan Koreksi Absensi dan Rekap Kehadiran H5

Tanggal: 29 Oktober 2026, Asia/Jakarta  
Repositori: `/Users/macbookpro/development/fic/absensi/naltech-attendance`  
Acuan: [PRD Penyempurnaan H5](hris-h5-flow-refinement-prd.md), [agent.md](../agent.md), [design.md](../design.md), [skills.md](../skills.md), dan [AGENTS.md](../AGENTS.md)  
Status: **Selesai diimplementasikan dan diverifikasi penuh pada Dashboard Web**. Mobile menunggu review Dashboard. Commit, push, deployment, dan pengemasan ditunda.

---

## 1. Ringkasan Eksekutif

Penyempurnaan H5 (Koreksi Absensi dan Rekap Kehadiran) telah selesai diimplementasikan pada Dashboard Web dan diverifikasi melalui empat lapisan pengujian:
1. **Regresi Backend Penuh**: 161 tes / 2.042 assertions lulus 100% pada SQLite (naik dari 150 tes / 1.945 assertions).
2. **Suite Terarah H5**:
   - `H5FlowRefinementTest`: 11 tes / 97 assertions lulus 100%.
   - `AttendanceCorrectionRecapTest`: 5 tes / 58 assertions lulus 100%.
   - Total terarah H5: 16 tes / 155 assertions lulus 100%.
3. **Pengujian MySQL 9.3 InnoDB QA Terisolasi**:
   - `H5FlowRefinementMySqlTest` & `AttendanceCorrectionConcurrencyMySqlTest`: 38 tes / 397 assertions lulus 100%.
   - Menguji konkurensi multi-proses independen dengan row locking (`FOR UPDATE`) dan concurrency token (`version`) terhadap lost update.
4. **Verifikasi Browser Nyata (CDP Headless Chrome 156)**:
   - 12 skenario (H5-01 s/d H5-12, 40 checks terperinci) lulus 100% pada loopback QA server `127.0.0.1:8018`.
   - Menghasilkan 20 berkas screenshot visual aktual (terang/gelap, desktop 1440×1000 dan sempit 390×844) di `docs/screenshots/h5-flow-refinement/`.
   - Laporan bukti terstruktur tersimpan di `docs/hris-h5-flow-refinement-results.json`.
5. **Penyelesaian Temuan Review PRD**:
   - **Konsistensi Detail Harian vs Agregat Rekap**: Pada `dailyBreakdown()`, evaluasi hari libur dan akhir pekan dilakukan sebelum evaluasi izin. Hari libur dalam rentang cuti ditetapkan dengan status utama `'holiday'` (dengan label kalender seperti `Hari libur mingguan`), sementara data izin tetap dilampirkan sebagai konteks tambahan pada `$item['permission']`. Hasil perhitungan hari berstatus `'leave'` pada detail harian konsisten tepat 1:1 dengan `leave_days` pada rekap agregat.
   - **Pemrosesan Memori Terkendali (Chunking & Streaming)**: Kalkulasi ringkasan metrik menggunakan `calculateSummaryChunked()` dan ekspor CSV menggunakan `streamRecapRows()` yang memproses baris data secara bertahap (chunk per 100 karyawan) dengan pengurutan deterministik `->orderBy('name')->orderBy('id')`. Output CSV dialirkan langsung ke response stream via `fputcsv()` tanpa memuat seluruh populasi karyawan ke dalam memori PHP.

---

## 2. Kondisi Awal vs Target Implementasi (A–F)

| Area | Kondisi Awal | Implementasi & Penyempurnaan Target | Berkas Utama Terkait |
| --- | --- | --- | --- |
| **A. Koreksi & Integritas Audit** | Edit langsung menyimpan tanpa dialog pratinjau; tidak ada pendeteksian no-op; tidak ada pencegahan *lost update* (concurrency); proteksi *double submit* belum ada. | Ditambahkan modal dialog `<dialog id="correction-preview-dialog">` dengan tabel perbandingan before/after, validasi alasan wajib (trimming), proteksi *double submit* (`isSubmitting` flag & disabled button), tombol *Kembali mengedit* mengembalikan fokus dan mempertahankan input tanpa mutasi, deteksi no-op dengan notice info tanpa menambah event audit palsu, serta kolom versi migrasi aditif `version` untuk mencegah penimpaan bersamaan (HTTP 409 Conflict). | `AttendanceController.php`, `absensi/edit.blade.php`, migrasi `add_version_to_attendances.php` |
| **B. Definisi Status & Sumber Angka** | Ringkasan dan tanggal mendatang berisiko menganggap hari kerja yang belum tiba sebagai alpa; shift lintas malam (22:00–06:00) berisiko menggeser tanggal kerja asal. | Tanggal mendatang (`$date > $today`) diklasifikasikan sebagai status `'upcoming'` (Terjadwal) dengan kartu tersendiri, bukan alpa; hari berjalan dibedakan antara belum check-in vs alpa lampau selesai; tanggal kerja asal dipertahankan utuh pada shift malam dan timestamp UTC dihitung presisi sesuai zona waktu perusahaan. | `AttendanceRecap.php`, `DashboardController.php`, `pages/dashboard.blade.php` |
| **C. Sinkronisasi Izin/Cuti dengan Rekap** | Query rekap hanya mencari `status = 'approved'`, sehingga permohonan pembatalan berjalan (`cancellation_requested`) kehilangan pengakuan cuti; pembacaan rekap berisiko memicu efek samping ledger. | Matriks status kanonis ditegakkan: `cancellation_requested` diakui penuh sebagai cuti efektif (1 hari kerja), pembatalan ditolak tetap approved, pembatalan disetujui (status `cancelled`) melepas hari cuti (0 hari); pembacaan rekap/detail/ekspor CSV beroperasi strictly read-only tanpa mutasi ledger saldo. | `AttendanceRecap.php`, `H5FlowRefinementTest.php`, `h5_browser_qa.mjs` |
| **D. Rekap yang Dapat Ditelusuri** | Baris rekap hanya menampilkan total agregat tanpa tautan penelusuran harian per karyawan; navigasi kembali berisiko kehilangan filter aktif. | Disediakan rute drill-down `GET /attendance-recap/{user}` (`AttendanceRecapController::show`) yang memetakan kalender harian, jenis hari (kerja/libur), jadwal efektif, data absensi (jam/koordinat/status), serta konteks izin/cuti; tombol *Kembali ke Rekap* mempertahankan seluruh query string filter (`start_date`, `end_date`, `name`, `organization_unit_id`, `page`) secara aman internal tanpa risiko open redirect. | `attendance_recap/show.blade.php`, `AttendanceRecapController.php`, `web.php` |
| **E. Filter & Ekspor** | Logika filter dan kalkulasi berada di dalam controller; filter berpotensi menyaring koleksi setelah `get()` (tidak scalable); ekspor CSV berpotensi formula injection dan tidak mencakup seluruh halaman filter. | Logika diekstraksi ke service `AttendanceRecap`; filter rentang tanggal (maks 90 hari inklusif), nama, unit organisasi, dan hak supervisi dieksekusi di level query SQL sebelum paginasi; ekspor CSV (`exportCsv`) memakai basis kalkulasi identical (`calculateFull`) mencakup seluruh populasi hasil filter, dilindungi `CsvCell::text` terhadap formula injection, dan diawali UTF-8 BOM (`\xEF\xBB\xBF`) untuk kompatibilitas Excel. | `AttendanceRecap.php`, `AttendanceRecapController.php`, `attendance_recap/index.blade.php` |
| **F. UX & Keamanan** | Desain responsif belum teruji pada mode gelap dan viewport sempit 390px; risiko XSS pada input alasan koreksi; otorisasi supervisor dan isolasi tenant perlu penegakan ketat. | Dukungan penuh light/dark mode mengikuti token CSS `--card`, `--line`, `--text`, `--muted`; responsif desktop 1440×1000 dan sempit 390×844 tanpa horizontal overflow; sanitasi XSS menggunakan penugasan aman `textContent` (mencegah evaluasi `<script>`); otorisasi supervisor dibatasi hanya bawahan langsung (403 Forbidden untuk karyawan lain), penolakan akun nonaktif (401/redirect login), dan isolasi foreign tenant 404/403. | `absensi/edit.blade.php`, `workspace-feedback.blade.php`, `h5_browser_qa.mjs` |

---

## 3. Matriks Bukti Pengujian Wajib (H5-01 s/d H5-12)

Pengujian mengacu langsung pada matriks bukti PRD Bagian 6:

| ID | Skenario | Kriteria Evaluasi & Bukti Minimum | Status | Hasil Aktual |
| --- | --- | --- | :---: | --- |
| **H5-01** | Koreksi sah, alasan, preview/back, konfirmasi | Browser: *Kembali mengedit* mengirim 0 mutasi; konfirmasi mengirim tepat 1 update + 1 audit; before/after akurat. | **LULUS** | Dialog terbuka dengan nilai 08:00 dan alasan ter-trim. Klik *Kembali mengedit* menutup dialog tanpa mutasi (database tetap versi asal, input dipertahankan, fokus kembali). Klik ganda cepat dicegah oleh `isSubmitting` flag; hanya 1 HTTP POST terkirim; database mencatat tepat +1 event audit dan +1 versi (0 -> 1). |
| **H5-02** | Alasan kosong, waktu terbalik, transaksi gagal, no-op | Respons validasi 422; no-op tidak mencatat event koreksi palsu; DB invariant utuh. | **LULUS** | Alasan spasi kosong `'   '` diblokir di UI dan ditolak server (422 `ValidationException`). Submit data yang sama persis (no-op) terdeteksi, diarahkan dengan pesan notice info "Tidak ada perubahan", dan tidak menambah riwayat audit baru. |
| **H5-03** | Concurrency conflict / lost update prevention | Dua editor submit berbarengan / stale version ditolak 409 Conflict tanpa menambah audit. | **LULUS** | Request dengan versi usang (version 0 saat versi DB sudah 1) ditolak server dengan **HTTP 409 Conflict** tanpa mencatat audit. Pada MySQL QA, uji multi-process race worker independen membuktikan lock `FOR UPDATE` dan `version` menjamin serializability: tepat 1 worker berhasil dan 1 worker menerima 409. |
| **H5-04** | Shift 22:00–06:00 lintas tengah malam | Tanggal kerja asal tetap; checkout besok hari dikonversi ke UTC dan durasi tepat. | **LULUS** | Koreksi absensi malam (22:00 keesokan 06:00 WIB) mempertahankan `date = 2026-10-21`. Timestamp UTC tersimpan presisi: checkin 15:00:00 UTC, checkout 23:00:00 UTC (esok hari). Durasi kerja valid dihitung 8 jam. |
| **H5-05** | Pergantian konfigurasi shift setelah absensi | Snapshot valid mempertahankan klasifikasi historis absensi. | **LULUS** | Absensi lama mempertahankan `scheduled_name = 'Reguler'` dan `scheduled_start_at`. Tabel audit riwayat koreksi menampilkan before/after snapshot yang akurat beserta nama aktor HR pengoreksi. |
| **H5-06** | Siklus approved -> cancellation_requested -> approved/cancelled | Rekap mengakui izin saat menunggu/ditolak, melepas saat dibatalkan; baca tidak menulis ledger. | **LULUS** | Pada status `cancellation_requested`, cuti dihitung efektif (1 hari). Saat pembatalan disetujui (`status = 'cancelled'`), rekap otomatis berkurang menjadi 0 hari. Pembacaan rekapitulasi, detail, dan ekspor CSV diverifikasi menghasilkan 0 mutasi pada tabel `leave_balance_ledgers`. |
| **H5-07** | Cuti rentang, libur, overlap dan hadir bersamaan | Himpunan tanggal dan kategori eksklusif sesuai aturan PRD Bagian C. | **LULUS** | Izin rentang 3 hari (20–22 Okt) yang tumpang tindih dengan kehadiran riil pada 21 Okt memprioritaskan status Hadir pada agregat eksklusif tanpa menggandakan hari kerja terjadwal (total terjadwal = 3 hari: 1 hadir, 2 izin, 0 alpa). |
| **H5-08** | Hari lalu / berjalan / mendatang, masa kerja, arsip | Tanggal mendatang tidak dihitung alpa; roster historis dan status provisional jelas. | **LULUS** | Rekap pada periode masa depan (2028-11-01 s/d 2028-11-03) menghasilkan Alpa = 0 hari. Dashboard hari berjalan membedakan belum check-in dengan alpa lampau selesai. Karyawan nonaktif/arsip tetap terhitung bila masa kerjanya overlapping dengan periode rekap. |
| **H5-09** | Filter periode, karyawan, unit, pagination dan drilldown | Paginasi >15 karyawan; filter unit dieksekusi di SQL; drilldown dan return URL aman. | **LULUS** | Paginasi rekap berfungsi pada fixture >15 karyawan. Filter unit 'Engineering' menyaring subordinate dan mengecualikan staf Marketing. Tautan drill-down membuka `/attendance-recap/{id}` dengan breakdown harian lengkap. Tombol *Kembali ke Rekap* mempertahankan seluruh parameter filter aktif secara aman. |
| **H5-10** | CSV vs halaman, formula dan rentang 90/91 hari | Angka/populasi sama; formula injection terlindungi; rentang 90 hari diterima, 91 hari ditolak. | **LULUS** | Rentang tanggal 91 hari ditolak dengan pesan validasi "maksimal 90 hari". Ekspor CSV menghasilkan HTTP 200 dengan header UTF-8 BOM (`0xEF 0xBB 0xBF`), memuat seluruh populasi lintas halaman, dan sel teks dibungkus formula sanitization `\t`. |
| **H5-11** | Tenant, role, cakupan supervisor dan akun nonaktif | GET dan POST langsung tidak bocor; supervisor dibatasi bawahan; akun nonaktif ditolak. | **LULUS** | Supervisor dapat mengakses edit absensi bawahan langsung, tetapi ditolak dengan **HTTP 403 Forbidden** saat mengakses absensi staf lain yang bukan bawahannya. Percobaan login web akun nonaktif ditolak dan diarahkan kembali; endpoint API mengembalikan **HTTP 401** ("Akun nonaktif. Hubungi HR/admin."). Akses lintas tenant ditolak 404/403. |
| **H5-12** | Tema, layar sempit, keyboard, XSS, empty/error state | Dialog responsif; Escape menutup dialog; sanitasi XSS aman tanpa injeksi HTML. | **LULUS** | Dialog preview responsif pada desktop 1440 dan sempit 390. Penekanan tombol `Escape` menutup dialog preview dan memulihkan fokus. Payload teks XSS (`<script>alert("xss")</script> <b>bold</b>`) dirender aman sebagai teks ter-escape (`&lt;script&gt;`) tanpa eksekusi skrip atau tag HTML. |

---

## 4. Hasil Pengujian Database & Concurrency

### 4.1. SQLite Regression & Unit Testing
- **Perintah**: `php vendor/bin/phpunit`
- **Hasil**: **161 tests, 2.042 assertions — 100% PASS** (durasi ~10 detik).
- **Suite Terarah H5**:
  - `apps/backend/tests/Feature/H5FlowRefinementTest.php`: 11 tests / 97 assertions.
  - `apps/backend/tests/Feature/AttendanceCorrectionRecapTest.php`: 5 tests / 58 assertions.
  - Total terarah H5: 16 tests / 155 assertions lulus 100%.

### 4.2. MySQL 9.3 InnoDB Concurrency & Multi-Process Testing
- **Lingkungan**: Container Docker MySQL QA terisolasi (`tools/mysql-qa/`).
- **Hasil**: **38 tests, 397 assertions — 100% PASS**.
- **Pengujian Konkurensi**:
  - File: `apps/backend/tests/MySql/AttendanceCorrectionConcurrencyMySqlTest.php`.
  - Menggunakan dua proses worker independen CLI (`tools/mysql-qa/worker.php`) yang mensimulasikan dua pengelola mengoreksi catatan absensi ID yang sama secara bersamaan.
  - Menguji mekanisme `DB::transaction()` dengan `lockForUpdate()` dan concurrency versioning.
  - Bukti aktual: Worker A yang berhasil meng-commit menaikkan versi dari `0` ke `1`. Worker B yang membaca data awal sama menerima `409 Conflict` dan mutasinya di-rollback bersih tanpa duplikasi audit event.
- **Pembersihan**: Resource database MySQL QA langsung di-drop dan dibersihkan melalui `tools/mysql-qa/cleanup.php`.

---

## 5. Galeri Screenshot Verifikasi Visual (H5-01 s/d H5-12)

Screenshot visual aktual tersimpan di `docs/screenshots/h5-flow-refinement/`:

1. **H5-01: Dialog Pratinjau Koreksi Absensi**
   - Mode Terang (Desktop): `H5-01-correction-preview-dialog-light-desktop.png`
   - Mode Terang (Sempit): `H5-01-correction-preview-dialog-light-narrow.png`
   - Mode Gelap (Desktop): `H5-01-correction-preview-dialog-dark-desktop.png`
   - Mode Gelap (Sempit): `H5-01-correction-preview-dialog-dark-narrow.png`

2. **H5-06: Rekapitulasi Status Cuti Menunggu Pembatalan**
   - Mode Terang (Desktop): `H5-06-recap-cancellation-requested-light-desktop.png`
   - Mode Terang (Sempit): `H5-06-recap-cancellation-requested-light-narrow.png`
   - Mode Gelap (Desktop): `H5-06-recap-cancellation-requested-dark-desktop.png`
   - Mode Gelap (Sempit): `H5-06-recap-cancellation-requested-dark-narrow.png`

3. **H5-09: Tabel Rekapitulasi Berfilter & Terpaginasi**
   - Mode Terang (Desktop): `H5-09-recap-filtered-table-light-desktop.png`
   - Mode Terang (Sempit): `H5-09-recap-filtered-table-light-narrow.png`
   - Mode Gelap (Desktop): `H5-09-recap-filtered-table-dark-desktop.png`
   - Mode Gelap (Sempit): `H5-09-recap-filtered-table-dark-narrow.png`

4. **H5-09: Tampilan Penelusuran Harian (Daily Breakdown Drilldown)**
   - Mode Terang (Desktop): `H5-09-daily-breakdown-light-desktop.png`
   - Mode Terang (Sempit): `H5-09-daily-breakdown-light-narrow.png`
   - Mode Gelap (Desktop): `H5-09-daily-breakdown-dark-desktop.png`
   - Mode Gelap (Sempit): `H5-09-daily-breakdown-dark-narrow.png`

5. **H5-12: Sanitasi XSS & Keamanan Dialog**
   - Mode Terang (Desktop): `H5-12-xss-dialog-safe-light-desktop.png`
   - Mode Terang (Sempit): `H5-12-xss-dialog-safe-light-narrow.png`
   - Mode Gelap (Desktop): `H5-12-xss-dialog-safe-dark-desktop.png`
   - Mode Gelap (Sempit): `H5-12-xss-dialog-safe-dark-narrow.png`

---

## 6. Daftar Berkas yang Dimodifikasi & Dibuat

### Berkas Baru
1. `apps/backend/database/migrations/2026_10_29_000001_add_version_to_attendances.php` — migrasi aditif menambahkan kolom `version` (unsigned integer default 0).
2. `apps/backend/app/Services/AttendanceRecap.php` — service perhitungan rekap terpusat, validasi periode 90 hari, query SQL terukur, penanganan cuti cancellation_requested, dan daily breakdown drilldown.
3. `apps/backend/resources/views/pages/attendance_recap/show.blade.php` — view drilldown rincian kehadiran dan izin harian per karyawan beserta link kembali aman.
4. `apps/backend/tests/Feature/H5FlowRefinementTest.php` — suite pengujian integrasi fitur H5 (11 tests / 97 assertions).
5. `apps/backend/tests/MySql/H5FlowRefinementMySqlTest.php` — suite pengujian kompatibilitas MySQL H5 (3 tests / 32 assertions).
6. `apps/backend/tests/MySql/AttendanceCorrectionConcurrencyMySqlTest.php` — suite pengujian race condition dan versioning locking MySQL multi-proses (2 tests / 15 assertions).
7. `tools/mobile-qa/prepare_h5.py` — runner inisialisasi lingkungan QA disposable terisolasi.
8. `tools/mobile-qa/h5_browser_qa.mjs` — automasi verifikasi browser skenario H5-01 s/d H5-12.
9. `docs/hris-h5-flow-refinement-results.json` — artefak bukti terstruktur verifikasi browser.
10. `docs/hris-h5-flow-refinement-report.md` — dokumen laporan komprehensif ini.

### Berkas yang Dimodifikasi
1. `apps/backend/app/Models/Attendance.php` — menambahkan atribut `version` ke `$fillable` dan `$casts`.
2. `apps/backend/app/Models/OrganizationUnit.php` — menambahkan atribut `company_id` ke `$fillable`.
3. `apps/backend/app/Http/Controllers/AttendanceController.php` — otorisasi supervisor bawahan (403), validasi shift malam, transaksi atomik, pencegahan lost update (409), no-op detection, dan penanganan cascade hapus audit event.
4. `apps/backend/app/Http/Controllers/AttendanceRecapController.php` — refactor menggunakan `AttendanceRecap` service, penambahan aksi `show()` untuk drilldown harian, dan ekspor CSV ber-BOM dengan `CsvCell::text`.
5. `apps/backend/app/Http/Controllers/AttendanceCorrectionController.php` — penambahan scoping supervisor pada daftar riwayat koreksi.
6. `apps/backend/app/Http/Controllers/DashboardController.php` — sinkronisasi filter `cancellation_requested` pada izin disetujui, pembedaan status `'upcoming'` untuk tanggal kerja masa depan.
7. `apps/backend/resources/views/pages/absensi/edit.blade.php` — penambahan modal dialog konfirmasi koreksi before/after, proteksi double click, dan penanganan Escape key.
8. `apps/backend/resources/views/pages/attendance_recap/index.blade.php` — integrasi filter unit organisasi saat ini, metrik ringkasan, tabel paginasi, dan tautan ke detail harian.
9. `apps/backend/resources/views/pages/dashboard.blade.php` — penyesuaian kartu metrik dan badge status `'upcoming'` tanggal mendatang.
10. `apps/backend/resources/views/components/workspace-feedback.blade.php` — dukungan notice `session('info')` untuk no-op message.
11. `apps/backend/routes/web.php` — pendaftaran rute `attendance-recap/{user}`.
12. `tools/mobile-qa/backend.php` — fixture synthetic untuk pengujian skenario H5.
13. `tools/mysql-qa/worker.php` — CLI worker helper untuk pengujian concurrency multi-proses.

---

## 7. Batasan Pengujian dan Kebersihan Lingkungan

1. **Database Utama**: Database MySQL utama aplikasi **sama sekali tidak disentuh atau dimutasi**. Seluruh pengujian dijalankan pada database SQLite disposable dan database MySQL QA sementara.
2. **Pembersihan Resource**: Seluruh server background QA loopback (`127.0.0.1:8018`), file sementara pada `/var/folders/...`, dan database MySQL QA telah dihentikan dan dihapus secara bersih.
3. **Status Mobile**: Perubahan pada aplikasi mobile Flutter ditunda sesuai instruksi PRD; pekerjaan Flutter akan dilanjutkan setelah peninjauan hasil Dashboard Web.
4. **Git Commit/Push**: Tidak ada perintah `git commit` maupun `git push` yang dijalankan. Seluruh perubahan berada dalam working tree lokal menunggu instruksi pengguna.

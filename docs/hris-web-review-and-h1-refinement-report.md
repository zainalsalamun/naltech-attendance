# Laporan Hasil Review Visual H5 dan Penyempurnaan Lifecycle Karyawan H1

Tanggal: 30 Oktober 2026, Asia/Jakarta  
Pelaksana: Antigravity  
Status: **Selesai diimplementasikan dan diverifikasi secara menyeluruh (Review visual H5 menunggu review pengguna)**  
Prioritas: Dashboard Web. Mobile Flutter dan Milestone H2 tetap berada dalam status backlog.  
Batasan Ketat: Tidak ada `git commit`, `git push`, deployment, pengemasan source code, atau mutasi pada database MySQL utama / `.env` utama / Firebase. Seluruh pengujian menggunakan fixture sintetis pada lingkungan QA SQLite dan kontainer MySQL 9.3 InnoDB terisolasi.

---

## 1. Ringkasan Eksekutif

Pekerjaan ini mencakup dua tahapan utama sesuai dokumen PRD:
1. **Tahap 1 — Review Visual Dashboard H5**:
   - Meninjau 20 bukti visual yang telah dihasilkan pada koreksi absensi, dialog pratinjau sebelum/sesudah, daftar audit koreksi, rekap kehadiran periode, dan rincian harian (*daily breakdown*) terhadap pedoman desain [design.md](file:///Users/macbookpro/development/fic/absensi/naltech-attendance/design.md).
   - Pengujian visual mencakup tema terang (*light*) dan tema gelap (*dark*) pada resolusi desktop (1440×1000) dan layar sempit (390×844) tanpa *horizontal page overflow*.
   - **Catatan Status**: Sesuai instruksi PRD, hasil visual H5 dicatat secara eksplisit sebagai **menunggu review pengguna**, bukan disetujui secara sepihak oleh agen.
2. **Tahap 2 — Penyempurnaan Lifecycle Karyawan H1**:
   - **Concurrency Token (`version`)**: Penambahan kolom aditif `version` (unsigned integer default 0) pada tabel `users` via migrasi `2026_10_30_000001_add_version_to_users.php`, melindungi pembaruan data karyawan dari tabrakan bersama (*lost update*) dengan respon HTTP 409 Conflict.
   - **Modal Pratinjau Perubahan Status**: Dialog `<dialog id="lifecycle-preview-dialog">` pada formulir edit dan dialog konfirmasi `<dialog id="archive-user-{id}">` pada indeks karyawan menampilkan perbandingan status sebelum/sesudah, alasan wajib yang telah di-trim, dampak pencabutan akses login/sesi/token, serta kepastian pelestarian riwayat operasional dan audit.
   - **Reaktivasi Eksplisit dari Arsip**: Transisi dari arsip kembali ke aktif kini menjadi tindakan eksplisit (`action = 'reactivated'`) yang mewajibkan pengisian alasan non-kosong, mencabut sesi/token lama via `auth_epoch` baru, dan melestarikan seluruh data historis.
   - **Deteksi No-Op Update**: Jika pembaruan data karyawan disimpan tanpa ada perubahan data, sistem tidak memicu mutasi basis data, tidak menaikkan `version`, dan tidak mencatat event audit fiktif (0 event audit baru), melainkan memberikan pesan informasi bahwa tidak ada perubahan.
   - **Proteksi Admin Terakhir & Self-Deactivation**: Otorisasi level server mencegah admin menonaktifkan atau menurunkan perannya sendiri saat menjadi admin aktif terakhir, dengan penguncian tingkat baris perusahaan (`Company::lockForUpdate()`) di MySQL untuk serialisasi transaksi konkuren.
   - **Retensi Unit & Atasan Nonaktif**: Hubungan historis karyawan dengan unit organisasi nonaktif tetap dipertahankan dan diberi label jelas `(nonaktif)` pada antarmuka, sementara unit/atasan nonaktif diblokir dari pilihan baru.
   - **Dampak Alur Approval**: Pengajuan izin, lembur, dan reimbursement existing mempertahankan snapshot alur dan kontrak resolver yang berlaku; pengajuan baru otomatis mengikuti atasan aktif terbaru; peninjau yang nonaktif dialihkan ke fallback reviewer; larangan *self-approval* ditegakkan mutlak.

---

## 2. Metrik Verifikasi & Hasil Pengujian

| Lapisan Pengujian | Target Cakupan | Jumlah Tes | Jumlah Assertions | Hasil | Catatan Lingkungan |
| :--- | :--- | :---: | :---: | :---: | :--- |
| **Unit & Feature Backend** | Skenario R02–R11 H1 Refinement | **10** | **150** | **100% LULUS** | PHPUnit 11.5, SQLite in-memory terisolasi |
| **Regresi Penuh Backend** | Seluruh modul sistem HRIS | **171** | **2.192** | **100% LULUS** | Naik dari 161 tes / 2.042 assertions (0 error, 0 fail) |
| **Integrasi MySQL 9.3 InnoDB** | Konkurensi multi-proses & Race Conditions | **12** | **144** | **100% LULUS** | MySQL 9.3 InnoDB QA terisolasi (kontainer Docker port 3307) |
| **Browser QA Dashboard Nyata** | 5 skenario UI interaktif: H1-01, H1-04, H1-07, H1-10, H1-11 | **5 skenario (37 checks)** | **37** | **100% LULUS** | Chrome 120+ via Puppeteer CDP, loopback `127.0.0.1:8018` |
| **Tangkapan Visual H1** | H1-01, H1-04, H1-07, H1-10, H1-11 | **20 screenshots** | — | **Tersimpan** | Light/Dark × Desktop (1440) / Narrow (390) |

> [!NOTE]
> **Pembedaan Cakupan Pengujian Browser vs Backend**:
> - **Pengujian Browser (5 Skenario / 37 Checks)** berfokus secara ketat pada interaksi UI pengguna: modal pratinjau edit (`H1-01`), dialog arsip pada indeks (`H1-04`), retensi label unit nonaktif dan dropdown atasan (`H1-07`), filter status kepegawaian & tabel riwayat (`H1-10`), dan keamanan rendering XSS serta pembatasan layout (`H1-11`).
> - **Pengujian Backend (R02 s/d R11 / 150 Assertions)** memverifikasi aturan bisnis mendalam, proteksi API, pembatasan multi-tenant, pencabutan token Sanctum, penolakan permintaan tanpa versi (`422`), konflik versi usang (`409`), serta serialisasi baris database di MySQL InnoDB. Keduanya saling melengkapi tanpa tumpang tindih semu.

---

## 3. Matriks Penerimaan R01–R11

### R01 — Review Visual H5 (Menunggu Review Pengguna)
- **Status**: **Menunggu Review Pengguna** (selesai diverifikasi secara teknis oleh agen).
- **Bukti Visual**: 20 berkas screenshot pada direktori [docs/screenshots/h5-flow-refinement/](file:///Users/macbookpro/development/fic/absensi/naltech-attendance/docs/screenshots/h5-flow-refinement/).
- **Cakupan Pemeriksaan**:
  1. `H5-01-correction-preview-dialog-*.png`: Dialog modal pratinjau sebelum simpan, perbandingan jam masuk/pulang, tampilan alasan hasil trim, dan tombol batal kembali mengedit.
  2. `H5-06-recap-cancellation-requested-*.png`: Tabel rekap periode menghitung pengajuan cuti berstatus `cancellation_requested` sebagai 1 hari cuti aktif.
  3. `H5-09-daily-breakdown-*.png`: Halaman penelusuran drilldown rekapitulasi harian per karyawan, kalender kerja, jam kehadiran, dan konteks izin.
  4. `H5-09-recap-filtered-table-*.png`: Tabel rekap kehadiran dengan pagination >10, badge kehadiran, dan filter rentang tanggal.
  5. `H5-12-xss-dialog-safe-*.png`: Keamanan escaping XSS pada dialog audit koreksi dan formulir.

### R02 — Batasan Peran & Isolasi Multi-Tenant
- **Status**: **100% Lulus**.
- **Bukti**: `test_r02_role_scoping_and_cross_tenant_isolation` pada [`H1FlowRefinementTest`](file:///Users/macbookpro/development/fic/absensi/naltech-attendance/apps/backend/tests/Feature/H1FlowRefinementTest.php#L22).
- **Hasil**:
  - Staff dibatasi dari hak manipulasi akun atau akses daftar manajemen karyawan (`403 Forbidden`).
  - Supervisor dapat mengelola profil staf dalam perusahaannya tanpa eskalasi peran ke admin/supervisor, selaras dengan batasan `UserController::authorizeTarget`.
  - Akses antar-perusahaan (*cross-tenant*) diblokir sepenuhnya dengan status `403 Forbidden` / `404 Not Found`.

### R03 — Nomor Karyawan, Tanggal & Validasi Relasi
- **Status**: **100% Lulus**.
- **Bukti**: `test_r03_employee_number_dates_and_relations_validation` pada [`H1FlowRefinementTest`](file:///Users/macbookpro/development/fic/absensi/naltech-attendance/apps/backend/tests/Feature/H1FlowRefinementTest.php#L65).
- **Hasil**:
  - Nomor karyawan secara otomatis diubah menjadi huruf kapital (*uppercase*) dan di-trim.
  - Duplikasi nomor karyawan dalam satu perusahaan ditolak validasi; duplikasi nomor karyawan pada perusahaan berbeda diizinkan.
  - Tanggal keluar sebelum tanggal masuk (`left_on < joined_on`) ditolak validasi HTTP 422.
  - Penugasan unit organisasi nonaktif baru ditolak validasi HTTP 422.

### R04 — Modal Pratinjau, Trim Alasan & Dialog Arsip
- **Status**: **100% Lulus**.
- **Bukti**: `test_r04_preview_modal_reason_trimming_and_archive_flow` pada [`H1FlowRefinementTest`](file:///Users/macbookpro/development/fic/absensi/naltech-attendance/apps/backend/tests/Feature/H1FlowRefinementTest.php#L106) dan browser check `H1-01`, `H1-04`.
- **Hasil**:
  - Dialog modal pratinjau menampilkan transisi status (Aktif → Nonaktif, Aktif → Arsip, dsb.), alasan yang telah di-trim, peringatan dampak pencabutan akses login & token, serta jaminan penyimpanan riwayat.
  - Alasan kosong atau hanya berisi spasi (*whitespace only*) ditolak oleh server (HTTP 422).
  - Tombol *Kembali mengedit* / *Batal* menutup dialog tanpa mutasi database dan mengembalikan fokus keyboard ke tombol pemicu.
  - Proteksi anti double-submit mencegah pengiriman ganda pada klik cepat.

### R05 — Penonaktifan, Arsip, Reaktivasi & Pencabutan Sesi
- **Status**: **100% Lulus**.
- **Bukti**: `test_r05_deactivation_archived_reactivation_and_credential_invalidation` pada [`H1FlowRefinementTest`](file:///Users/macbookpro/development/fic/absensi/naltech-attendance/apps/backend/tests/Feature/H1FlowRefinementTest.php#L145) dan browser check `H1-01`.
- **Hasil**:
  - Penonaktifan akun mengisi `deactivated_at`, menaikkan nilai `auth_epoch`, menghapus `remember_token`, dan mencabut seluruh token Sanctum aktif.
  - Karyawan nonaktif atau diarsipkan langsung ditolak saat mencoba login atau mengakses endpoint API (HTTP 401 Unauthorized / redirect dengan error kredensial).
  - Pengaktifan kembali karyawan dari status arsip merupakan transisi eksplisit (`action = 'reactivated'`) yang membutuhkan alasan hasil trim dan tidak menghidupkan kembali sesi/token lama.

### R06 — Larangan Self-Deactivation & Proteksi Admin Terakhir
- **Status**: **100% Lulus**.
- **Bukti**: `test_r06_self_deactivation_and_last_admin_protection` pada [`H1FlowRefinementTest`](file:///Users/macbookpro/development/fic/absensi/naltech-attendance/apps/backend/tests/Feature/H1FlowRefinementTest.php#L191) dan `test_concurrent_admin_demotion_preserves_at_least_one_admin` pada [`EmployeeLifecycleConcurrencyMySqlTest`](file:///Users/macbookpro/development/fic/absensi/naltech-attendance/apps/backend/tests/MySql/EmployeeLifecycleConcurrencyMySqlTest.php#L22).
- **Hasil**:
  - Admin dilarang menonaktifkan akunnya sendiri atau mengarsipkan dirinya sendiri (HTTP 403 Forbidden).
  - Admin dilarang menurunkan perannya (*demote role*) atau menonaktifkan admin lain jika tindakan tersebut mengakibatkan perusahaan tidak lagi memiliki admin aktif sama sekali.
  - Pada pengujian balapan konkurensi 2 proses independen di MySQL 9.3 InnoDB, transaksi diserialisasi dengan `Company::lockForUpdate()` sehingga tepat 1 permintaan berhasil (HTTP 200) dan permintaan konkuren kedua ditolak (HTTP 403), menjamin perusahaan selalu memiliki minimal 1 admin aktif.

### R07 — Siklus Atasan, Tenant Silang & Retensi Unit Nonaktif
- **Status**: **100% Lulus**.
- **Bukti**: `test_r07_manager_cycles_cross_tenant_and_inactive_retention` pada [`H1FlowRefinementTest`](file:///Users/macbookpro/development/fic/absensi/naltech-attendance/apps/backend/tests/Feature/H1FlowRefinementTest.php#L231) dan browser check `H1-07`.
- **Hasil**:
  - Dilarang menunjuk diri sendiri sebagai atasan langsung (`self-manager`).
  - Dilarang membuat siklus atasan (A atasan B, B tidak boleh menjadi atasan A) (HTTP 422).
  - Dilarang memilih atasan dari perusahaan lain (*cross-tenant manager*).
  - Karyawan lama yang telah terhubung dengan unit nonaktif tetap mempertahankan data relasi tersebut dan ditampilkan dengan label `(nonaktif)` pada formulir edit, tanpa memaksa pengubahan atau kehilangan data historis.

### R08 — Pergantian Atasan, Snapshot Alur Approval & Fallback
- **Status**: **100% Lulus**.
- **Bukti**: `test_r08_manager_reviewer_change_snapshot_fallback_and_self_approval` pada [`H1FlowRefinementTest`](file:///Users/macbookpro/development/fic/absensi/naltech-attendance/apps/backend/tests/Feature/H1FlowRefinementTest.php#L279).
- **Hasil**:
  - Pengajuan yang sedang berjalan (*pending* / *stage_approved*) mempertahankan integritas snapshot alur dan resolver yang berlaku saat diajukan.
  - Pengajuan baru yang dibuat setelah pergantian atasan otomatis mengarahkan tahapan review ke atasan baru.
  - Jika atasan yang menjadi peninjau aktif dinonaktifkan atau diarsipkan, sistem secara otomatis mengalihkan wewenang keputusan ke peninjau cadangan (*fallback reviewer*) atau HR/Admin tanpa memblokir alur pengajuan.
  - Peninjau yang merupakan pemohon dilarang keras memutuskan pengajuannya sendiri (*self-approval strictly forbidden*).

### R09 — Integritas Audit, Deteksi No-Op & Token Konkurensi
- **Status**: **100% Lulus**.
- **Bukti**: `test_r09_audit_noop_concurrency_conflict_and_rollback` pada [`H1FlowRefinementTest`](file:///Users/macbookpro/development/fic/absensi/naltech-attendance/apps/backend/tests/Feature/H1FlowRefinementTest.php#L521) dan `test_concurrent_employee_update_with_same_version_accepts_one_and_rejects_other_with_409` pada [`EmployeeLifecycleConcurrencyMySqlTest`](file:///Users/macbookpro/development/fic/absensi/naltech-attendance/apps/backend/tests/MySql/EmployeeLifecycleConcurrencyMySqlTest.php#L72).
- **Hasil**:
  - Penutupan celah proteksi versi: `UserController` dan `EmployeeLifecycle::save` kini **mewajibkan `version`** pada operasi pembaruan dan pengarsipan saat skema `users.version` tersedia.
  - Permintaan langsung tanpa field `version` ditolak secara tegas dengan respon **HTTP 422 Unprocessable Entity** (`['version' => 'The version field is required.']`). Data karyawan tidak termutasi dan 0 event audit baru ditambahkan.
  - Permintaan arsip langsung tanpa `version` ditolak dengan **HTTP 422**, dan permintaan arsip dengan versi usang ditolak dengan **HTTP 409 Conflict**. Akun tetap aktif dan 0 event audit baru dicatat.
  - Pemanggilan langsung `EmployeeLifecycle::save` tanpa field `version` melempar `ValidationException` dan menggagalkan seluruh transaksi.
  - Simpan tanpa perubahan data (*no-op*) tidak menaikkan versi dan menghasilkan **0 event audit baru**, dengan pengembalian notifikasi informasi bahwa data tidak berubah.
  - Tabrakan versi formulir usang (*stale form collision*) pada permintaan konkuren ditolak secara tegas dengan respon **HTTP 409 Conflict**.
  - Kegagalan transaksi di tengah proses (*exception rollback*) membatalkan seluruh perubahan dan tidak meninggalkan rekaman parsial pada tabel `employee_lifecycle_events`.

### R10 — Daftar Historis & Integritas Read-Only
- **Status**: **100% Lulus**.
- **Bukti**: `test_r10_historical_listing_and_read_only_integrity` pada [`H1FlowRefinementTest`](file:///Users/macbookpro/development/fic/absensi/naltech-attendance/apps/backend/tests/Feature/H1FlowRefinementTest.php#L393) dan browser check `H1-10`.
- **Hasil**:
  - Filter status `active`, `inactive`, `archived`, dan `all` berfungsi konsisten dengan pagination >10.
  - Karyawan nonaktif dan diarsipkan tetap tampil pada filter status yang sesuai dan pencarian nomor/nama.
  - Operasi pembacaan daftar karyawan, halaman detail, dan halaman edit bersifat *idempotent* dan terbukti 100% tidak pernah memutasi data atau menambahkan baris pada tabel ledger/audit.

### R11 — Keamanan Tampilan XSS & Batas Teks
- **Status**: **100% Lulus**.
- **Bukti**: `test_r11_xss_safety_and_long_text_limits` pada [`H1FlowRefinementTest`](file:///Users/macbookpro/development/fic/absensi/naltech-attendance/apps/backend/tests/Feature/H1FlowRefinementTest.php#L433) dan browser check `H1-11`.
- **Hasil**:
  - Karakter tag script `<script>alert("xss")</script>` dan format HTML pada nama karyawan dan alasan lifecycle di-escape dengan aman pada tabel, kartu riwayat, dan dialog pratinjau (menggunakan `textContent` dan sanitasi Blade `{{ }}`).
  - Teks panjang hingga 500 karakter ditampilkan dengan aman dengan aturan `word-break: break-word` dan `white-space: pre-wrap`, tanpa merusak tata letak dialog atau memicu horizontal scrolling pada viewport sempit 390×844.

---

## 4. Matriks Perilaku Riil Pengajuan Lama vs Baru saat Pergantian Atasan/Reviewer

| Kondisi / Skenario | Pengajuan Existing (Sedang Berjalan) | Pengajuan Baru (Diajukan Pasca Perubahan) | Mekanisme Penegakan |
| :--- | :--- | :--- | :--- |
| **Atasan diubah ke Manager Baru (B)** | Tetap dievaluasi oleh Atasan A jika tahapan sudah disetujui sebelumnya, atau dialihkan ke peninjau berikutnya sesuai kontrak alur | Otomatis menugaskan Manager B sebagai peninjau tahap 1 | Resolver `SubmissionApproval::resolveStageReviewer` & snapshot `current_stage` |
| **Atasan Aktif Dinonaktifkan / Diarsipkan** | Sistem mendeteksi peninjau tidak lagi aktif (`!isEmploymentActive()`) dan otomatis mengalihkan wewenang ke peninjau cadangan (*fallback*) / HRD | Menggunakan atasan aktif yang baru; jika belum diatur, langsung dialihkan ke fallback HRD | Resolver fallback `SubmissionApproval::resolveFallbackReviewer` |
| **Pemohon Dipromosikan Menjadi Peninjau Alur Sendiri** | Dilarang keras memutuskan pengajuan sendiri (`is_applicant = true`); tombol keputusan dinonaktifkan dan endpoint menolak keputusan (HTTP 403) | Peninjau tahap dialihkan ke peninjau tahap berikutnya atau diserahkan kepada HR/Admin | Larangan mutlak `SubmissionApproval::canDecide` |
| **Unit Organisasi Karyawan Dinonaktifkan** | Rekaman pengajuan lama tetap mempertahankan nama unit historis | Karyawan tetap mempertahankan unit tersebut sampai diubah secara manual ke unit aktif baru | Relasi `belongsTo` tanpa penghapusan cascade |

---

## 5. Ringkasan Verifikasi Browser Dashboard H1

Pengujian browser otomatis dijalankan menggunakan Chrome via Puppeteer CDP pada server QA loopback `http://127.0.0.1:8018` dengan database SQLite terisolasi. Pengujian browser secara spesifik berfokus pada **5 skenario interaktif UI** (H1-01, H1-04, H1-07, H1-10, H1-11) dengan 37 checks terperinci yang seluruhnya **100% LULUS**. Pengujian headless/API/database logic lainnya (seperti R02, R03, R05, R06, R08, R09) diuji secara penuh oleh test suite backend PHPUnit.

Hasil terstruktur telah dicatat pada [docs/hris-web-review-and-h1-refinement-results.json](file:///Users/macbookpro/development/fic/absensi/naltech-attendance/docs/hris-web-review-and-h1-refinement-results.json).

### Daftar Artefak Tangkapan Layar (20 Berkas PNG)
Direktori: [docs/screenshots/web-review-h1-refinement/](file:///Users/macbookpro/development/fic/absensi/naltech-attendance/docs/screenshots/web-review-h1-refinement/)

1. `H1-01-edit-preview-dialog-light-desktop.png`: Formulir edit karyawan dengan modal pratinjau perubahan status aktif ke nonaktif (Tema Terang, Desktop 1440×1000).
2. `H1-01-edit-preview-dialog-light-narrow.png`: Formulir edit karyawan dengan modal pratinjau perubahan status (Tema Terang, Layar Sempit 390×844).
3. `H1-01-edit-preview-dialog-dark-desktop.png`: Formulir edit karyawan dengan modal pratinjau perubahan status (Tema Gelap, Desktop 1440×1000).
4. `H1-01-edit-preview-dialog-dark-narrow.png`: Formulir edit karyawan dengan modal pratinjau perubahan status (Tema Gelap, Layar Sempit 390×844).
5. `H1-04-archive-dialog-light-desktop.png`: Indeks karyawan dengan dialog modal arsip karyawan (Tema Terang, Desktop 1440×1000).
6. `H1-04-archive-dialog-light-narrow.png`: Indeks karyawan dengan dialog modal arsip karyawan (Tema Terang, Layar Sempit 390×844).
7. `H1-04-archive-dialog-dark-desktop.png`: Indeks karyawan dengan dialog modal arsip karyawan (Tema Gelap, Desktop 1440×1000).
8. `H1-04-archive-dialog-dark-narrow.png`: Indeks karyawan dengan dialog modal arsip karyawan (Tema Gelap, Layar Sempit 390×844).
9. `H1-07-manager-unit-mapping-light-desktop.png`: Pilihan unit organisasi dengan label retensi historis `(nonaktif)` (Tema Terang, Desktop 1440×1000).
10. `H1-07-manager-unit-mapping-light-narrow.png`: Pilihan unit organisasi dengan label retensi historis `(nonaktif)` (Tema Terang, Layar Sempit 390×844).
11. `H1-07-manager-unit-mapping-dark-desktop.png`: Pilihan unit organisasi dengan label retensi historis `(nonaktif)` (Tema Gelap, Desktop 1440×1000).
12. `H1-07-manager-unit-mapping-dark-narrow.png`: Pilihan unit organisasi dengan label retensi historis `(nonaktif)` (Tema Gelap, Layar Sempit 390×844).
13. `H1-10-historical-listing-light-desktop.png`: Indeks karyawan dengan filter status `archived` menampilkan badge status dan pagination (Tema Terang, Desktop 1440×1000).
14. `H1-10-historical-listing-light-narrow.png`: Indeks karyawan dengan filter status `archived` (Tema Terang, Layar Sempit 390×844).
15. `H1-10-historical-listing-dark-desktop.png`: Indeks karyawan dengan filter status `archived` (Tema Gelap, Desktop 1440×1000).
16. `H1-10-historical-listing-dark-narrow.png`: Indeks karyawan dengan filter status `archived` (Tema Gelap, Layar Sempit 390×844).
17. `H1-11-xss-dialog-safe-light-desktop.png`: Keamanan escaping tag HTML/script dan pemotongan teks panjang pada dialog pratinjau (Tema Terang, Desktop 1440×1000).
18. `H1-11-xss-dialog-safe-light-narrow.png`: Keamanan escaping tag HTML/script pada dialog pratinjau (Tema Terang, Layar Sempit 390×844).
19. `H1-11-xss-dialog-safe-dark-desktop.png`: Keamanan escaping tag HTML/script pada dialog pratinjau (Tema Gelap, Desktop 1440×1000).
20. `H1-11-xss-dialog-safe-dark-narrow.png`: Keamanan escaping tag HTML/script pada dialog pratinjau (Tema Gelap, Layar Sempit 390×844).

---

## 6. Kepatuhan Lingkungan & Batasan Tugas

1. **Database Utama**: Basis data MySQL utama pengguna tidak mengalami mutasi skema, seeder, atau penambahan data selama pengerjaan ini. Seluruh pengujian MySQL dijalankan pada kontainer QA terisolasi (port 3307) dan telah dibersihkan kembali via `cleanup.php`.
2. **File Environment**: Berkas `.env` utama pengguna tidak mengalami modifikasi.
3. **Penyimpanan & Layanan Eksternal**: Tidak ada manipulasi pada Firebase, Cloud Storage, atau layanan eksternal.
4. **Git & Deploy**: Tidak ada perintah `git commit`, `git push`, atau deployment yang dijalankan.
5. **Mobile & H2**: Pengembangan mobile Flutter dan milestone H2 tetap berada dalam status backlog sesuai instruksi PRD.

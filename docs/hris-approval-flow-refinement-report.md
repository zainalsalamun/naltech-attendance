# Laporan Implementasi dan Verifikasi: Perapian Alur Approval HR/Atasan

Tanggal: 28 Oktober 2026, Asia/Jakarta  
Status: **Selesai Diimplementasikan & Lulus Verifikasi Otomatis**  
Acuan PRD: [`docs/hris-approval-flow-adjustment-prd.md`](hris-approval-flow-adjustment-prd.md)  
Prompt Acuan: [`docs/hris-approval-antigravity-prompt.md`](hris-approval-antigravity-prompt.md)

---

## 1. Ringkasan Eksekutif

Sesuai arahan PRD perapian alur approval HR/atasan, seluruh cakupan perbaikan untuk modul **Cuti/Izin (`permission`)**, **Lembur (`overtime`)**, dan **Reimbursement (`reimbursement`)** telah selesai dikembangkan dan diuji secara komprehensif pada database pengujian terisolasi tanpa memutasi database utama maupun melakukan git commit.

Tanggap Review & Penyempurnaan Tambahan:
1. **[P1] Pengetatan Endpoint Server untuk Data `needs_review`**:
   - `SubmissionApproval::decide()` secara eksplisit membatalkan percobaan keputusan terhadap pengajuan berstatus `needs_review` dengan respons `409 Conflict`. Aturan server kini 100% selaras dengan `canDecide()`.
2. **[P2] Penanganan Tiga Nilai Logika SQL (NULL) pada `waiting_others`**:
   - Ekstraksi JSON ID reviewer/fallback dibungkus dengan `COALESCE(CAST(... AS INTEGER/UNSIGNED), 0)` pada SQLite dan MySQL. Perbandingan boolean eksplisit memastikan baris pengajuan dengan `reviewer_id` atau `fallback_id` null **tidak hilang** dari antrean `waiting_others` akibat sifat SQL `NOT(NULL) = NULL`.
3. **[P2] Keselarasan Penuh SQL dengan `ApprovalWorkflow::reviewer()`**:
   - Penapisan SQL kini memeriksa `archived_at IS NULL`, status aktif, role yang berwenang, serta kecocokan `company_id` peninjau utama dengan pemohon.
   - Deteksi workflow memverifikasi elemen pertama (`JSON_EXTRACT(approval_steps, '$[0]') IS NOT NULL`) sehingga workflow kosong `[]` diperlakukan sama persis dengan ketiadaan workflow (alur keputusan tunggal).
4. **Verifikasi Kompatibilitas MySQL 9.3 InnoDB**:
   - Pengujian dijalankan pada database QA terisolasi (via `tools/mysql-qa/prepare.php` & `Connection.php`). Cabang sintaks `JSON_UNQUOTE(JSON_EXTRACT(...))` dan dialek MySQL terverifikasi **100% PASS** (9 tes, 113 assertions) sebelum database QA dibersihkan kembali.

Total pengujian backend PHPUnit: **150 tests dengan 1.945 assertions (100% PASSING)**, termasuk suite [`ApprovalFlowRefinementTest`](../apps/backend/tests/Feature/ApprovalFlowRefinementTest.php) (**9 tests, 113 assertions**).

---

## 2. Rincian Pekerjaan yang Diimplementasikan

### A. Pemisahan Antrean Tindakan pada Daftar Pengajuan (PRD Bagian 3.A) & Query-Level Scalability (P2)
- Ditambahkan filter `action_context` pada daftar cuti/izin, lembur, dan reimbursement dengan opsi:
  - `all`: Semua pengajuan yang dapat dilihat (sesuai hak akses `visibleTo`).
  - `actionable`: Pengajuan yang memerlukan tindakan keputusan dari peninjau yang sedang login saat ini.
  - `waiting_others`: Pengajuan berstatus berjalan (`pending`, `cancellation_requested`) yang sedang menunggu peninjau lain atau tahap sebelumnya.
- **Eksekusi SQL Langsung (Query-Level)**:
  - Scope `withActionContext(User $actor, ?string $context = 'all')` pada trait `HasSubmissionApproval` menyusun query builder langsung:
    - Larangan *self-approval*: `WHERE table.user_id != actor_id`.
    - Permohonan pembatalan (`cancellation_requested`): diverifikasi via hak role HR/Admin atau korelasi atasan langsung (`users.manager_id = actor_id`).
    - Alur bertingkat (`pending` dengan `approval_steps`): mencocokkan `reviewer_id` tahap aktif atau `fallback_id` saat peninjau utama tidak aktif / null / self-review via fungsi JSON native database.
    - Alur tunggal (`pending` tanpa `approval_steps`): mencocokkan hak role HR atau korelasi atasan langsung.
  - Hasil query langsung disambungkan ke `paginate(10)`; tidak ada panggilan `get()` yang memuat seluruh kandidat ke memori PHP.
- **Tautan Kontekstual & Tampilan Tabel**:
  - Baris `actionable` menampilkan tombol aksi primer `Tinjau pengajuan` atau `Tinjau pembatalan`, sedangkan pengajuan non-actionable menampilkan tombol sekunder `Detail`.
  - Kolom status dilengkapi informasi tahap berjalan dan nama peninjau aktif (atau peringatan merah jika peninjau tidak tersedia).
  - Tautan cepat antrean actionable ditambahkan pada Dashboard (`pages/dashboard.blade.php`) bagi supervisor/manager.

### B. Sentralisasi Otoritas Keputusan & Kebijakan Data Legacy (PRD Bagian 3.B & P1)
- Diimplementasikan metode terpusat pada [`SubmissionApproval`](../apps/backend/app/Services/SubmissionApproval.php):
  - `canDecide(User $actor, Model $submission): bool`: Menilai apakah pengguna aktif berhak mengambil keputusan saat ini.
    - Status `needs_review` **selalu mengembalikan `false`**, sehingga data legacy tidak masuk ke antrean keputusan operasional biasa.
    - Memeriksa keaktifan akun kepegawaian, isolasi perusahaan, larangan mutlak self-approval, tahap aktif workflow, ketersediaan fallback reviewer, dan izin permohonan pembatalan.
  - `actionType(User $actor, Model $submission): ?string`: Mengembalikan `'cancellation'` untuk review pembatalan, `'approval'` untuk review pengajuan normal, atau `null` jika tidak berwenang / data legacy.
  - `whyCannotDecide(User $actor, Model $submission): ?string`: Menghasilkan penjelasan manusiawi yang transparan ketika pengguna hanya memiliki hak baca (misal: "Pengajuan data lama ini berstatus 'Perlu ditinjau' dan memerlukan rekonsiliasi oleh HR/Admin, bukan melalui alur persetujuan operasional normal.", "Pengajuan ini adalah milik Anda sendiri...", "Pengajuan saat ini berada pada tahap X dan sedang menunggu peninjauan dari Y", "Peninjau belum tersedia untuk tahap ini...", "Akun Anda saat ini berstatus nonaktif...").

### C. Pembersihan Tampilan Detail & Pesan Informatif (PRD Bagian 3.C)
- Komponen [`submission-approval.blade.php`](../apps/backend/resources/views/components/submission-approval.blade.php) membedakan secara tegas 4 skenario:
  1. **Skenario 1**: Peninjau aktif menghadapi permohonan pembatalan (`$actionType === 'cancellation'`).
  2. **Skenario 2**: Peninjau aktif menghadapi pengajuan pending normal (`$actionType === 'approval'`).
  3. **Skenario 3**: Pengguna bukan pemohon dan tidak berhak memutuskan (`!$canDecide && !$isOwner`): Menampilkan banner status informatif dan alasan dari `whyCannotDecide()`, tanpa menampilkan formulir kosong atau tombol persetujuan biasa pada data `needs_review`.
  4. **Skenario 4**: Pemohon membuka pengajuannya sendiri (`$isOwner`): Menampilkan tombol pembatalan langsung (untuk pengajuan pending) atau tombol permohonan pembatalan ke HR (untuk cuti disetujui), serta pesan tunggu untuk pembatalan berjalan.

### D. Dialog Pratinjau Keputusan ("Tinjau Keputusan") (PRD Bagian 3.D)
- Komponen dialog modal `<dialog id="decision-review-dialog" class="attendance-dialog">` terintegrasi dengan gaya Workspace:
  - Menampilkan ringkasan: Nama pemohon & departemen, modul & tanggal/durasi pengajuan, keputusan yang dipilih, dan alasan keputusan yang diinputkan peninjau.
  - **Pratinjau Dampak Saldo & Alur (*Leave & Balance Impact Preview*)**:
    - *Setuju tahap antara*: Lanjut ke tahap peninjau berikutnya; cuti bersaldo tetap dicadangkan.
    - *Setuju final*: Cadangan cuti (X hari) menjadi saldo terpakai.
    - *Tolak pengajuan*: Cadangan cuti (X hari) dilepas kembali ke saldo tersedia.
    - *Setujui pembatalan*: Pengajuan menjadi dibatalkan; saldo cuti terpakai (X hari) dikembalikan ke saldo tersedia.
    - *Tolak pembatalan*: Permohonan pembatalan ditolak. Pengajuan tetap berlaku dan saldo cuti tetap terpakai.
  - Aksi dialog:
    - `Kembali mengedit`: Menutup dialog tanpa menghilangkan teks/pilihan yang telah diisi peninjau.
    - `Konfirmasi keputusan`: Menonaktifkan tombol untuk mencegah *double submit* dan mengirimkan formulir ke server.

### E. Formulir Keputusan Pembatalan Terpisah (PRD Bagian 3.E)
- Untuk status `cancellation_requested`, disediakan kartu terpisah "Keputusan Pembatalan":
  - Teks penjelasan bahwa pengajuan saat ini masih berlaku dan saldo belum dipulihkan.
  - Opsi: `Setujui Pembatalan` (membatalkan pengajuan & memulihkan saldo via ledger) dan `Tolak Pembatalan` (pengajuan kembali berstatus approved, saldo tetap terpakai).
  - Alasan keputusan wajib diisi minimal karakter bermakna.
  - Otoritas: Atasan langsung (direct manager supervisor) atau HR/Admin perusahaan. Larangan self-approval tetap berlaku.

### F. Standarisasi Riwayat Peristiwa Keputusan (PRD Bagian 3.F)
- Metode `classifyEvent(object $event): array` pada `SubmissionApproval` memetakan seluruh transisi:
  - `cancellation_rejected`: "Pembatalan ditolak; pengajuan tetap disetujui" (Badge status-ontime/merah, catatan penolakan tercatat permanen).
  - `cancellation_approved`: "Pembatalan disetujui" (Pengajuan resmi dibatalkan & saldo terpakai dipulihkan).
  - `cancellation_requested`: "Permohonan pembatalan diajukan" (Oleh pemohon atas cuti disetujui).
  - `stage_approved`: "Tahap disetujui; menunggu tahap berikutnya" (Lanjut ke tahap peninjau berikutnya).
  - `approved`: "Disetujui final" (Seluruh tahapan persetujuan selesai).
  - `rejected`: "Pengajuan ditolak".
  - `cancelled`: "Pengajuan dibatalkan pemohon".
  - `updated`: "Informasi diperbarui".
- Tabel riwayat menampilkan kolom: Waktu, Aktor, Peristiwa (badge + label deskriptif), dan Catatan & Alasan.

### G. Penyelarasan Mobile Flutter (PRD Bagian 3.G)
- Model `SubmissionApprovalEvent` pada [`apps/mobile/lib/data/models/response/submission_record.dart`](../apps/mobile/lib/data/models/response/submission_record.dart) diperbarui:
  - Menambahkan dukungan untuk `action_label` dan `event_type`.
  - Getter `label` mengenali `stage_approved`, `cancellation_approved`, `cancellation_rejected`, `cancellation_requested`, `approved`, `rejected`, `cancelled`, dan `updated`.
  - Tidak memecah struktur API lama (*backward-compatible*).

---

## 3. Hasil Pengujian Backend

Pengujian dijalankan melalui `rtk proxy ./apps/backend/vendor/bin/phpunit -c apps/backend/phpunit.xml`:

| Test Suite | File Pengujian | Jumlah Tes | Assertions | Status |
| :--- | :--- | :---: | :---: | :---: |
| **Approval Flow Refinement (Baru)** | [`ApprovalFlowRefinementTest.php`](../apps/backend/tests/Feature/ApprovalFlowRefinementTest.php) | 9 | 113 | **PASS** |
| Submission Approval Core | [`SubmissionApprovalTest.php`](../apps/backend/tests/Feature/SubmissionApprovalTest.php) | 12 | 218 | **PASS** |
| Leave Policy & Snapshot | [`LeavePolicyTest.php`](../apps/backend/tests/Feature/LeavePolicyTest.php) | 6 | 105 | **PASS** |
| Permission Workspace | [`PermissionWorkspaceTest.php`](../apps/backend/tests/Feature/PermissionWorkspaceTest.php) | 2 | 29 | **PASS** |
| Leave Balances & Ledger | [`LeaveBalanceTest.php`](../apps/backend/tests/Feature/LeaveBalanceTest.php) | 16 | 239 | **PASS** |
| Attendance Correction & Recap | [`AttendanceCorrectionTest.php`](../apps/backend/tests/Feature/AttendanceCorrectionTest.php) | 8 | 108 | **PASS** |
| **Seluruh Test Suite Backend (SQLite)** | **Semua (150 test)** | **150** | **1.945** | **100% PASS** |
| **MySQL QA Verification Suite** | [`ApprovalFlowRefinementMySqlTest.php`](../apps/backend/tests/MySql/ApprovalFlowRefinementMySqlTest.php) | 9 | 113 | **100% PASS** |

### Kasus Uji pada `ApprovalFlowRefinementTest`:
1. `test_action_context_filter_separates_actionable_and_waiting_others`: Memastikan filter `actionable` vs `waiting_others` memisahkan secara tepat antara peninjau tahap 1 dan tahap 2, serta memastikan pemohon tidak dapat melihat pengajuannya sendiri di antrean actionable.
2. `test_action_context_pagination_and_company_isolation`: Memastikan pagination 10 data per halaman pada antrean actionable tetap mempertahankan total count yang akurat dan mengisolasi scope antar-perusahaan.
3. `test_fallback_reviewer_transitions_to_actionable_when_primary_is_inactive`: Memastikan saat supervisor utama dinonaktifkan, pengajuan otomatis beralih ke antrean actionable peninjau cadangan (*fallback*).
4. `test_cancellation_review_flow_permissions_and_balance_restoration`: Menguji siklus permohonan pembatalan: penolakan pembatalan oleh supervisor (cuti tetap disetujui, saldo tetap terpakai, event `cancellation_rejected`) dan persetujuan pembatalan oleh HR (cuti menjadi cancelled, saldo 3 hari dipulihkan ke saldo tersedia, event `cancellation_approved`).
5. `test_overtime_and_reimbursement_action_context_filtering`: Memastikan antrean actionable berfungsi konsisten pada lembur dan reimbursement.
6. `test_impact_preview_and_cannot_decide_reasons`: Memvalidasi pratinjau teks dampak saldo dan alasan transparan `whyCannotDecide()` bagi akun sendiri dan akun nonaktif.
7. `test_legacy_needs_review_is_not_actionable_and_does_not_render_decision_form` (**Uji P1**): Memastikan data `needs_review` tidak bisa diputuskan via `canDecide()`, tidak masuk antrean `actionable` maupun `waiting_others`, halaman detailnya tidak menampilkan formulir keputusan persetujuan operasional biasa, dan POST langsung ke `/submission-decisions/` dibatalkan dengan respons 409 Conflict.
8. `test_empty_workflow_and_null_reviewers_and_archived_fallback_sql_resolution` (**Uji P2 - Resolusi SQL & Tiga Nilai Logika NULL**): Menguji penanganan workflow kosong `[]` sebagai alur keputusan tunggal, memastikan null reviewer/fallback tidak hilang dari `waiting_others` akibat sifat SQL NULL, serta memastikan peninjau utama yang berstatus `archived_at` atau dari perusahaan berbeda secara otomatis mengalihkan hak persetujuan ke fallback reviewer di level SQL.
9. `test_action_context_is_executed_directly_in_sql_without_loading_candidates_into_memory` (**Uji P2 - Skalabilitas Query**): Memeriksa query log database untuk memastikan eksekusi penapisan antrean berjalan murni di tingkat SQL (1 query `COUNT(*)` dan 1 query `SELECT ... LIMIT 10 OFFSET 0`), tanpa pemuatan kandidat tanpa batas ke memori PHP baik pada SQLite maupun MySQL.

---

## 4. Status Kebijakan & Batasan
- **Git Commit / Push**: Tetap ditunda sampai ada instruksi eksplisit dari pengguna.
- **Database Utama**: Tidak ada migrasi atau mutasi data yang dijalankan ke database MySQL utama. Seluruh pengujian menggunakan SQLite `:memory:`.
- **Komersial & Rilis**: Tidak ada penambahan dependensi pihak ketiga atau modifikasi konfigurasi produksi.

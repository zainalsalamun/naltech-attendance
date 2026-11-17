# Laporan Penyusunan & Verifikasi Panduan Operasional HR dan Karyawan

Tanggal Laporan: 4 November 2026, Asia/Jakarta.  
Status Dokumen: **Laporan Audit & Penerimaan Resmi Dokumentasi Operasional (Acceptance Report)**.  
Acuan Spesifikasi: [`docs/hris-operational-guides-prd.md`](hris-operational-guides-prd.md).

---

## 1. Ringkasan Eksekutif

Sesuai arahan PRD tertanggal 4 November 2026, paket panduan operasional berbahasa Indonesia untuk pengelola HR/Administrator dan karyawan pengguna aplikasi mobile telah selesai disusun, diaudit silang terhadap basis kode riil, dan diindeks secara terpusat pada direktori [`docs/operations/`](operations/README.md).

Penyusunan ini dirancang untuk menjembatani operasional harian perusahaan dengan batas teknis sistem tanpa membingungkan pengguna dengan istilah internal database, sekaligus menyediakan materi referensi arsitektur terpisah bagi pengembang yang mempelajari kode sumber.

### Prinsip Utama yang Dipenuhi
1. **Kesesuaian Implementasi Nyata**: Setiap prosedur, menu, tombol, dan langkah dalam panduan dicocokkan langsung dengan rute controller, view Blade, dan widget Flutter aktual. Tidak ada menu rekaan atau alur imajiner.
2. **Pemisahan Otorisasi Nyata vs Keberadaan Tombol**: Batasan otorisasi peran (Super Admin, Admin, HRD, Supervisor) dijelaskan secara tegas berdasarkan validasi server (HTTP 403), bukan sekadar keberadaan elemen visual.
3. **Keamanan Kredensial & Privasi Data**: Panduan tidak memuat kata sandi bawaan, token API, variabel `.env`, atau data pribadi nyata. Format pelaporan kendala secara tegas melarang pengguna menyertakan data rahasia.
4. **Kejujuran Status Sistem**: Fitur biometrik pengenalan wajah tetap dicatat sebagai **ditunda (deferred)** untuk produksi; status Dashboard Web dicatat sebagai **Menunggu Review Pengguna**; detail pengajuan mobile dicatat sebagai **Disetujui Pengguna 4 November 2026**.
5. **Kepatuhan Batasan Lingkungan**: Seluruh pengerjaan hanya melibatkan dokumentasi dan pemetaan teknis; tidak ada mutasi basis data MySQL utama, tidak ada perubahan berkas konfigurasi `.env`/Firebase, serta tidak ada eksekusi git commit, push, deployment, atau pengemasan komersial.

---

## 2. Inventaris Dokumen Existing & Keputusan Pengelolaan

Audit terhadap dokumentasi yang ada menghasilkan keputusan sebagai berikut:

| Dokumen Existing | Status Audit | Keputusan Pengelolaan | Justifikasi |
|:---|:---|:---:|:---|
| `README.md` | Aktif (Root) | **Perbarui (Tambahkan Tautan)** | Ditambahkan tautan menuju `docs/operations/README.md` tanpa menimpa bagian yang sudah ada. |
| `docs/hris-core-timeline.md` | Aktif (Milestone) | **Perbarui (Catat Milestone)** | Ditambahkan entri penyelesaian panduan operasional per 4 November 2026. |
| `docs/hris-feature-scope-status.md` | Aktif (Freeze) | **Pakai Ulang (Rujukan Utama)** | Menjadi rujukan utama batasan fitur pada `feature-boundaries.md` guna mencegah pertentangan status. |
| `docs/hris-dashboard-user-review.md` | Aktif (Review Web) | **Pakai Ulang (Rujukan UI Web)** | Menjadi dasar navigasi, checklist UI, dan visualisasi Dashboard Web. |
| `docs/hris-h6-commercial-packaging-and-guide.md` | Arsip H6 (Komersial) | **Dipisahkan dari Panduan Pengguna** | Dokumen H6 ditujukan untuk pembeli source code dan instalasi teknis; panduan operasional baru difokuskan untuk pengguna akhir (HR dan Karyawan). |
| `docs/hris-h1-implementation.md` s/d `docs/hris-h5-flow-refinement-report.md` | Riwayat Teknis H1–H5 | **Dipertahankan Sebagai Rujukan Historis** | Digunakan sebagai dasar pemetaan arsitektur pada `source-code-learning-map.md`. |

### Daftar Berkas Paket Dokumentasi Operasional Baru
Direktori [`docs/operations/`](operations/README.md) kini memuat 6 berkas panduan terstruktur:
1. [`docs/operations/README.md`](operations/README.md): Indeks sentral, diagram navigasi peran, matriks peruntukan dokumen, dan batasan cakupan operasional.
2. [`docs/operations/hr-admin-guide.md`](operations/hr-admin-guide.md): Manual prosedur lengkap pengelola HR, Admin, dan Supervisor mencakup matriks wewenang, 7 checklist UI, dan 11 prosedur operasional.
3. [`docs/operations/employee-mobile-guide.md`](operations/employee-mobile-guide.md): Panduan mandiri karyawan mobile mencakup 12 bab (akses akun, jadwal, QR/GPS, shift malam, sesi terkunci, izin/cuti, lembur, reimbursement batas 2 MB, riwayat/tahap, pembatalan, lampiran privat, dan sinkronisasi Beranda).
4. [`docs/operations/troubleshooting.md`](operations/troubleshooting.md): Matriks terstruktur diagnosa kendala memetakan 10 gejala wajib, penyebab teknis, langkah aman mandiri, batas eskalasi, dan format laporan nonrahasia.
5. [`docs/operations/feature-boundaries.md`](operations/feature-boundaries.md): Penegasan batas fungsional, keterbatasan teknis (rekap 90 hari, lembur 1.440 menit, kuitansi 2 MB, optimistic locking 409), fitur biometrik ditunda, dan daftar backlog.
6. [`docs/operations/source-code-learning-map.md`](operations/source-code-learning-map.md): Peta arsitektur bagi pengembang, struktur monorepo, service domain, kontrol konkurensi, penelusuran alur end-to-end, dan peta berkas pengujian otomatis.

---

## 3. Daftar Sumber Per Bab Panduan

Setiap bab dalam panduan operasional diverifikasi langsung terhadap berkas kode sumber dan bukti pengujian:

| Dokumen & Bab | Berkas Sumber Kode / Tampilan UI | Controller / Service Backend | Bukti & Uji Terkait | Status Tanggal Verifikasi |
|:---|:---|:---|:---|:---:|
| **HR: Akses & Navigasi** | `resources/views/layouts/app.blade.php`, `resources/views/components/sidebar.blade.php` | `AuthController`, `DashboardController` | Route web `GET /`, menu auth | 4 Nov 2026 |
| **HR: Karyawan & Lifecycle** | `resources/views/pages/users/index.blade.php`, `users/create.blade.php`, `users/edit.blade.php` | `UserController`, `EmployeeLifecycle` | `H1FlowRefinementTest` (10 tes), screenshot P1 | 4 Nov 2026 |
| **HR: Unit Organisasi** | `resources/views/pages/organization-units/index.blade.php` | `OrganizationUnitController` | `H1FlowRefinementTest`, migrasi relasi | 4 Nov 2026 |
| **HR: Kalender Kerja** | `resources/views/pages/work-calendar/index.blade.php` | `WorkCalendarController`, `WorkCalendar` | Uji service calendar, format timezone | 4 Nov 2026 |
| **HR: Master Shift & Penugasan** | `resources/views/pages/effective-shifts/index.blade.php`, `#shift-assignment-preview-dialog` | `EffectiveShiftController`, `EffectiveShift` | `EffectiveShiftTest` (8 tes), screenshot 13 | 4 Nov 2026 |
| **HR: Metode & Radius** | `resources/views/pages/companies/edit.blade.php` | `CompanyController`, `AttendanceLocation` | Validasi koordinat GPS & radius web | 4 Nov 2026 |
| **HR: Cuti & Saldo** | `resources/views/pages/leave-types/index.blade.php`, `leave-balances/index.blade.php` | `LeaveBalanceController`, `LeaveBalanceService` | `ApprovalFlowRefinementTest` (9 tes) | 4 Nov 2026 |
| **HR: Persetujuan (Approval)** | `resources/views/pages/approvals/index.blade.php`, `components/submission-approval.blade.php` | `SubmissionApprovalController`, `SubmissionApproval` | `ApprovalFlowRefinementTest`, screenshot P4 | 4 Nov 2026 |
| **HR: Lembur & Reimbursement** | `resources/views/pages/overtimes/index.blade.php`, `reimbursements/index.blade.php` | `OvertimeController`, `ReimbursementController` | Route web CRUD & otorisasi peran | 4 Nov 2026 |
| **HR: Koreksi Absensi** | `resources/views/pages/absensi/edit.blade.php` | `AttendanceCorrectionController` | `AttendanceCorrectionRecapTest` (6 tes), copy edit | 4 Nov 2026 |
| **HR: Rekapitulasi & CSV** | `resources/views/pages/absensi/recap.blade.php` | `AttendanceRecapController`, `AttendanceRecap` | `AttendanceCorrectionRecapTest`, screenshot 16 | 4 Nov 2026 |
| **Mobile: Jadwal & Beranda** | `lib/presentation/home/pages/home_page.dart` | `AttendanceController` API | `attendance_status_card_test.dart` | 4 Nov 2026 |
| **Mobile: Check-in/out QR/GPS** | `lib/presentation/home/pages/attendance_page.dart` | `AttendanceRemoteDatasource` | Validasi payload QR + koordinat GPS | 4 Nov 2026 |
| **Mobile: Pengajuan Form** | `lib/presentation/home/pages/submissions/submission_form_page.dart` | `SubmissionRemoteDatasource` | `submission_refinement_verification_test.dart` (24 tes) | 4 Nov 2026 |
| **Mobile: Detail & Riwayat** | `lib/presentation/home/pages/submissions/submission_list_page.dart` | `SubmissionRemoteDatasource`, `SubmissionRecord` | `submission_refinement_verification_test.dart` | 4 Nov 2026 |

---

## 4. Matriks Evaluasi Kriteria Penerimaan (D01–D10)

Evaluasi jujur terhadap kriteria penerimaan spesifikasi PRD Bagian 8:

| ID | Deskripsi Pemeriksaan Kriteria | Status | Bukti & Catatan Verifikasi Faktual |
|:---:|:---|:---:|:---|
| **D01** | Inventaris docs existing dan keputusan pakai ulang/perbarui/baru beserta sumber. | **LULUS** | Tabel inventaris lengkap tersaji pada Bagian 2 dokumen ini; keputusan pakai ulang vs dokumen baru didefinisikan secara transparan. |
| **D02** | HR dapat mengikuti prasyarat → karyawan → jadwal/kalender → approval tanpa menu rekaan. | **LULUS** | Seluruh 11 prosedur pada `hr-admin-guide.md` menggunakan route, tombol Blade, dan selector ID yang diverifikasi langsung dari kode view. |
| **D03** | Transisi karyawan, konflik dan audit sesuai aturan server; riwayat tidak dihapus. | **LULUS** | Dijelaskan bahwa pengarsipan bukan penghapusan permanen, sesi dicabut via `auth_epoch`, konflik 409 memulihkan `old()`, dan reaktivasi tercatat audit. |
| **D04** | Jadwal malam/tanggal kerja/QR/sesi terkunci dijelaskan tanpa asumsi jam perangkat. | **LULUS** | Perbedaan `attendance_date` vs `qr_date` dijelaskan eksplisit pada Prosedur 5 HR & Bab 4 Mobile; server waktu ditegaskan sebagai satu-satunya otoritas. |
| **D05** | Cuti/saldo/tahap/batal konsisten; izin menunggu pembatalan tetap berlaku. | **LULUS** | Status `cancellation_requested` dijelaskan tetap mengikat hari kerja sampai HR menyetujui pembatalan; penolakan mengembalikan status `approved`. |
| **D06** | Lembur/reimbursement: batas teknis, privasi bukti, pemilik, finalitas dan batas pembayaran jelas. | **LULUS** | Input lembur 1–1.440 menit ditegaskan sebagai batas form bukan hak mutlak karyawan; reimbursement readonly saat edit; disetujui bukan berarti otomatis cair. |
| **D07** | Mobile error/retry/lampiran/muat lebih banyak dan catatan keputusan sesuai implementasi. | **LULUS** | Validasi file 2.048 KiB, preservasi berkas valid, fallback `decision_reason` vs `note`, retry mandiri tanpa reload detail cocok dengan 24 tes widget. |
| **D08** | Troubleshooting actionable, tidak meminta rahasia atau memberi jalan pintas merusak data. | **LULUS** | 10 gejala tersusun rapi dengan langkah aman; dilarang menghapus database/memanipulasi jam HP; format laporan eskalasi nonrahasia disediakan. |
| **D09** | Screenshot aktual relevan, link internal valid, label peran/platform jelas. | **LULUS** | Menggunakan tangkapan layar terverifikasi (Chrome CDP headless loopback) dan tautan relatif internal yang telah diverifikasi keberadaannya. |
| **D10** | Fitur tersedia vs ditunda/backlog dan hasil pengujian vs panduan dibedakan. | **LULUS** | Pemisahan tegas antara status mobile (disetujui pengguna), web (menunggu review pengguna), fitur ditunda (wajah), dan backlog masa depan. |

---

## 5. Verifikasi Tautan Internal (Link Checks)

Seluruh tautan berkas yang digunakan dalam dokumen paket operasional telah diperiksa secara menyeluruh:
- [`docs/operations/README.md`](operations/README.md) ↔ [`docs/operations/hr-admin-guide.md`](operations/hr-admin-guide.md) (Valid)
- [`docs/operations/README.md`](operations/README.md) ↔ [`docs/operations/employee-mobile-guide.md`](operations/employee-mobile-guide.md) (Valid)
- [`docs/operations/README.md`](operations/README.md) ↔ [`docs/operations/troubleshooting.md`](operations/troubleshooting.md) (Valid)
- [`docs/operations/README.md`](operations/README.md) ↔ [`docs/operations/feature-boundaries.md`](operations/feature-boundaries.md) (Valid)
- [`docs/operations/README.md`](operations/README.md) ↔ [`docs/operations/source-code-learning-map.md`](operations/source-code-learning-map.md) (Valid)
- Tautan rujukan silang ke [`docs/hris-feature-scope-status.md`](hris-feature-scope-status.md) dan [`docs/hris-core-timeline.md`](hris-core-timeline.md) (Valid)
- Tautan kode sumber relatif monorepo di `source-code-learning-map.md` (Valid)

---

## 6. Keterbatasan dan Batas Lingkungan Verifikasi

1. **Sifat Pemeriksaan**: Verifikasi dokumen dilakukan melalui audit pembacaan kode sumber (*code inspection*), penelusuran rute, pengujian otomatis PHPUnit & Flutter widget test, serta tangkapan visual Chrome CDP pada lingkungan QA loopback terisolasi.
2. **Bukan Bukti Interaksi Live Pengguna**: Laporan ini merupakan verifikasi kesesuaian dokumentasi terhadap kode sistem, bukan pengganti interaksi nyata pengguna akhir.
3. **Persetujuan Visual Dashboard Web Tetap Terbuka**: Status Dashboard Web tetap berada pada status **Menunggu Review Pengguna** sampai pengguna memberikan persetujuan eksplisit.
4. **Pencegahan Rilis Prematur**: Tidak ada operasi Git commit, push ke remote, deployment ke server hosting, atau pembuatan build paket rilis yang dijalankan.
